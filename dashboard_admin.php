<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/mail.php';
require_role(['Admin']);

if (session_status() === PHP_SESSION_NONE) {
    // Optional cookie scope tightening
    session_set_cookie_params([
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ------------------------------------------------------------------
   Section routing
-------------------------------------------------------------------*/
// Only core admin sections remain (BNS functions removed)
$validSections = [
    'control-panel', 'accounts', 'reports'
];
$section = $_GET['section'] ?? ($_SESSION['active_section'] ?? 'control-panel');
if (!in_array($section, $validSections)) $section = 'control-panel';
$_SESSION['active_section'] = $section;

/* ------------------------------------------------------------------
   Helper Functions
-------------------------------------------------------------------*/
function fail($m, $c = 400) {
    http_response_code($c);
    echo json_encode(['success' => false, 'error' => $m]);
    exit;
}

function ok($d = []) {
    echo json_encode(array_merge(['success' => true], $d));
    exit;
}

function nz($s) {
    $s = trim((string)$s);
    return $s === '' ? null : $s;
}

function rand_password($len = 10) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function log_parent_activity(mysqli $mysqli, int $parent_user_id, string $action_code, ?int $child_id = null, array $meta = []) {
    if ($parent_user_id <= 0 || $action_code === '') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    $j = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
    $stmt = $mysqli->prepare("INSERT INTO parent_audit_log
        (parent_user_id,action_code,child_id,meta_json,ip_address,user_agent)
        VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isisss', $parent_user_id, $action_code, $child_id, $j, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

function has_column(mysqli $mysqli, string $table, string $col): bool {
    static $cache = [];
    $key = $table . '|' . $col;
    if (isset($cache[$key])) return $cache[$key];

    $sql = "SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('ss', $table, $col);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = false;
    if ($res && ($row = $res->fetch_assoc())) $exists = ((int)$row['c']) > 0;
    $stmt->close();
    return $cache[$key] = $exists;
}

function rel_time($ts) {
    $dt = new DateTime($ts, new DateTimeZone('UTC'));
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $interval = $now->diff($dt);

    if ($interval->invert === 0) {
        $secs = $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->d * 86400);
        if ($secs < 60) return $secs . 's ago';
        if ($secs < 3600) return floor($secs / 60) . ' min ago';
        if ($secs < 86400) return floor($secs / 3600) . ' hr ago';
        return $dt->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, H:i');
    } else {
        $secs = $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->d * 86400);
        if ($secs < 60) return $secs . 's ago';
        if ($secs < 3600) return floor($secs / 60) . ' min ago';
        if ($secs < 86400) return floor($secs / 3600) . ' hr ago';
        return $dt->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, H:i');
    }
}

/* ------------------------------------------------------------------
   Data fetching for Reports section
-------------------------------------------------------------------*/
if ($section === 'reports') {
    // BHW Reports Data
    $bhwStats = [];
    
    // Total health records
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM health_records");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $bhwStats['total_health_records'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Total maternal patients
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM maternal_patients");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $bhwStats['total_maternal_patients'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Total immunizations
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM child_immunizations");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $bhwStats['total_immunizations'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Health records by month (last 6 months)
    $healthRecordsByMonth = [];
    $stmt = $mysqli->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM health_records 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $healthRecordsByMonth[] = $row;
        }
        $stmt->close();
    }
    
    // Risk factors distribution
    $riskFactors = [];
    $stmt = $mysqli->prepare("
        SELECT 
            SUM(vaginal_bleeding) as vaginal_bleeding,
            SUM(urinary_infection) as urinary_infection,
            SUM(high_blood_pressure) as high_blood_pressure,
            SUM(fever_38_celsius) as fever_38_celsius,
            SUM(pallor) as pallor,
            SUM(abnormal_abdominal_size) as abnormal_abdominal_size,
            SUM(abnormal_presentation) as abnormal_presentation,
            SUM(absent_fetal_heartbeat) as absent_fetal_heartbeat,
            SUM(swelling) as swelling,
            SUM(vaginal_infection) as vaginal_infection
        FROM health_records
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $riskFactors = $result->fetch_assoc() ?? [];
        $stmt->close();
    }
    
    // BNS Reports Data
    $bnsStats = [];
    
    // Total nutrition records
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM nutrition_records");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $bnsStats['total_nutrition_records'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Total supplementation records
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM supplementation_records");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $bnsStats['total_supplementation'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Total children
    $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM children");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $bnsStats['total_children'] = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
    
    // Nutrition records by month (last 6 months)
    $nutritionRecordsByMonth = [];
    $stmt = $mysqli->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
        FROM nutrition_records 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $nutritionRecordsByMonth[] = $row;
        }
        $stmt->close();
    }
    
    // Weight-for-length status distribution
    $wflStatus = [];
    $stmt = $mysqli->prepare("
        SELECT 
            wst.status_description as wfl_status,
            COUNT(*) as count
        FROM nutrition_records nr
        LEFT JOIN wfl_ht_status_types wst ON nr.wfl_ht_status_id = wst.status_id
        WHERE nr.wfl_ht_status_id IS NOT NULL
        GROUP BY nr.wfl_ht_status_id, wst.status_description
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $wflStatus[] = $row;
        }
        $stmt->close();
    }
    
    // Supplementation by type
    $supplementationByType = [];
    $stmt = $mysqli->prepare("
        SELECT 
            supplement_type,
            COUNT(*) as count
        FROM supplementation_records 
        GROUP BY supplement_type
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $supplementationByType[] = $row;
        }
        $stmt->close();
    }
    
    // Recent activity (last 30 days)
    $recentActivity = [];
    $stmt = $mysqli->prepare("
        SELECT 
            'health_record' as type,
            'Health Record Created' as description,
            created_at
        FROM health_records 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'nutrition_record' as type,
            'Nutrition Record Created' as description,
            created_at
        FROM nutrition_records 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'immunization' as type,
            'Immunization Recorded' as description,
            created_at
        FROM child_immunizations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recentActivity[] = $row;
        }
        $stmt->close();
    }
}

/* ------------------------------------------------------------------
   Data fetching for Accounts section
-------------------------------------------------------------------*/
if ($section === 'accounts') {
    $accounts = [];
    $stmt = $mysqli->prepare("
        SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, r.role_name AS role, u.created_at, u.is_active 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id
    ");
    if ($stmt === false) {
        $msg = "<div class='alert alert-danger'>Database error: Unable to fetch accounts.</div>";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        $stmt->close();
    }

    $audit_logs = [];
    $stmt = $mysqli->prepare("
        SELECT l.created_user_id, l.account_type, u.first_name, u.last_name, l.creation_reason, l.created_at 
        FROM account_creation_log l 
        JOIN users u ON l.created_by_user_id = u.user_id 
        ORDER BY l.created_at DESC
    ");
    if ($stmt === false) {
        $msg = "<div class='alert alert-danger'>Database error: Unable to fetch audit logs.</div>";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $audit_logs[] = $row;
        }
        $stmt->close();
    }


    $edit_account = null;
    if (isset($_GET['edit'])) {
        $uid = intval($_GET['edit']);
        $stmt = $mysqli->prepare("
            SELECT user_id, first_name, middle_name, last_name, email, password 
            FROM users 
            WHERE user_id = ?
        ");
        if ($stmt === false) {
            $msg = "<div class='alert alert-danger'>Database error: Unable to fetch account details.</div>";
        } else {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $result = $stmt->get_result();
            $edit_account = $result->fetch_assoc();
            $stmt->close();
        }
    }
}



/* ------------------------------------------------------------------
   Data for Control Panel
-------------------------------------------------------------------*/
if ($section === 'control-panel') {
    $activeUsers = (int)($mysqli->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetch_row()[0] ?? 0);
    $childCount = (int)($mysqli->query("SELECT COUNT(*) FROM children")->fetch_row()[0] ?? 0);
    $nutritionCount = (int)($mysqli->query("SELECT COUNT(*) FROM nutrition_records")->fetch_row()[0] ?? 0);
    $healthCount = (int)($mysqli->query("SELECT COUNT(*) FROM health_records")->fetch_row()[0] ?? 0);
    $totalRecords = $childCount + $nutritionCount + $healthCount;
    $systemUptimePct = 99.8;
    $avgResponseMs = 142;

    $activity = [];
    $stmt = $mysqli->prepare("
        SELECT u.username AS actor, l.account_type, 'Added new ' AS action_base, l.created_at AS created_at
        FROM account_creation_log l
        JOIN users u ON u.user_id = l.created_by_user_id
        ORDER BY l.created_at DESC
        LIMIT 9
    ");
    if ($stmt === false) {
        $msg = "<div class='alert alert-danger'>Database error: Unable to fetch activity logs.</div>";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($r = $result->fetch_assoc()) {
            $activity[] = [
                'user' => $r['actor'],
                'action' => $r['action_base'] . '(' . $r['account_type'] . ') account',
                'time' => $r['created_at'],
                'status' => 'success'
            ];
        }
        $stmt->close();
    }
}

$msg = '';

/* ------------------------------------------------------------------
   Handle Success Message from Redirect
-------------------------------------------------------------------*/
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $username = $_GET['username'] ?? '';
    $password = $_GET['password'] ?? '';
    $role = $_GET['role'] ?? '';
    $email = $_GET['email'] ?? '';
    $emailSent = $_GET['emailSent'] ?? '0';
    $emailError = $_GET['emailError'] ?? '';
    
    $msg = "<div class='alert alert-success alert-dismissible fade show' role='alert'>"
         . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
         . "A $role account has been created with Username: "
         . htmlspecialchars($username)
         . " and Password: " . htmlspecialchars($password);
    
    if ($emailSent == '1') {
        $msg .= "<br><small class='text-success'><i class='bi bi-envelope-check me-1'></i>Credentials sent to " . htmlspecialchars($email) . "</small>";
    } else {
        $msg .= "<br><small class='text-warning'><i class='bi bi-envelope-exclamation me-1'></i>Email not sent: " . htmlspecialchars($emailError ?: 'Email service unavailable') . "</small>";
    }
    
    $msg .= "</div>";
}

/* ------------------------------------------------------------------
   POST Request Handling
-------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger'>Invalid request. Please try again.</div>";
    } else {
        // CREATE BHW/BNS
        if ($section === 'accounts' && isset($_POST['create_account'])) {
            $first_name = $mysqli->real_escape_string($_POST['first_name']);
            $middle_name = $mysqli->real_escape_string($_POST['middle_name'] ?? '');
            $last_name  = $mysqli->real_escape_string($_POST['last_name']);
            $email      = $mysqli->real_escape_string($_POST['email']);
            $role       = $mysqli->real_escape_string($_POST['role']);
            $birthday   = $mysqli->real_escape_string($_POST['birthday']);
            $barangay   = 'Sabang';

            // NEW: Check for existing email early to avoid exception
            $chkEmail = $mysqli->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            if ($chkEmail) {
                $chkEmail->bind_param("s", $email);
                $chkEmail->execute();
                $chkRes = $chkEmail->get_result();
                if ($chkRes && $chkRes->num_rows) {
                    $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                         . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                         . "Email already registered. Please use another email.</div>";
                    $chkEmail->close();
                    // Abort create block
                    goto create_account_end;
                }
                $chkEmail->close();
            }

            // Generate username: first letter of first name + surname + year of birthday
            $first_letter = strtolower(substr($first_name, 0, 1));
            $surname = strtolower($last_name);
            $birth_year = date('y', strtotime($birthday)); // Get 2-digit year
            $base_username = $first_letter . $surname . $birth_year;
            $username = $base_username;

            // Ensure unique username (tries up to 10 now)
            $check_stmt = $mysqli->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
            $attempt = 0;
            while ($attempt < 10) {
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows === 0) break;
                $username = $base_username . rand(1, 9);
                $attempt++;
            }
            $check_stmt->close();

            if ($attempt >= 10) {
                $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                     . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                     . "Failed to generate unique username. Please try again.</div>";
                goto create_account_end;
            }

            // Generate password: FirstName[2 letters of LastName][BirthdayMMDD]
            $first_name_capitalized = ucfirst(strtolower($first_name));
            $last_name_2_letters = substr(strtolower($last_name), 0, 2);
            $birthday_mmdd = date('md', strtotime($birthday)); // MM and DD
            $password = $first_name_capitalized . $last_name_2_letters . $birthday_mmdd;
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Resolve role_id
            $role_stmt = $mysqli->prepare("SELECT role_id FROM roles WHERE role_name = ? LIMIT 1");
            $role_stmt->bind_param("s", $role);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            $role_id = $role_result->fetch_row()[0] ?? 2;
            $role_stmt->close();

            $created_by_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            if ($created_by_user_id) {
                $user_check_stmt = $mysqli->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
                $user_check_stmt->bind_param("i", $created_by_user_id);
                $user_check_stmt->execute();
                $ucr = $user_check_stmt->get_result();
                if (!$ucr || !$ucr->num_rows) $created_by_user_id = null;
                $user_check_stmt->close();
            }
            $created_by_sql = $created_by_user_id ? $created_by_user_id : 'NULL';

            $stmt = $mysqli->prepare("
                INSERT INTO users
                    (username,email,password,password_hash,first_name,middle_name,last_name,role_id,barangay,birthday,is_active,created_by_user_id)
                VALUES
                    (?,?,?,?,?,?,?,?,?,?,1,$created_by_sql)
            ");
            if (!$stmt) {
                $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                     . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                     . "Database error: Unable to prepare account insert.</div>";
                goto create_account_end;
            }

            $stmt->bind_param(
                "sssssssiss",
                $username,
                $email,
                $password,
                $password_hash,
                $first_name,
                $middle_name,
                $last_name,
                $role_id,
                $barangay,
                $birthday
            );

            try {
                $stmt->execute();
                $new_user_id = $mysqli->insert_id;

                $log_stmt = $mysqli->prepare("
                    INSERT INTO account_creation_log
                        (created_user_id, account_type, created_by_user_id, creation_reason, created_at)
                    VALUES
                        (?, ?, $created_by_sql, ?, NOW())
                ");
                if ($log_stmt) {
                    $creation_reason = 'New ' . $role . ' account created';
                    $log_stmt->bind_param("iss", $new_user_id, $role, $creation_reason);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                // Send email with credentials
                $emailSent = false;
                $emailError = '';
                if (function_exists('bhw_mail_send')) {
                    $subject = "Your Staff Account Credentials - Barangay Health System";
                    $staffName = $first_name . ($middle_name ? ' ' . $middle_name : '') . ' ' . $last_name;
                    $html = "
                        <div style='font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;'>
                          <h2 style='margin:0 0 10px;color:#047857;'>Staff Account Created Successfully</h2>
                          <p>Hello <strong>" . htmlspecialchars($staffName) . "</strong>,</p>
                          <p>Your {$role} account for the Barangay Health Management System has been created.</p>
                          <div style='background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:20px;margin:15px 0;'>
                            <h3 style='margin:0 0 15px;color:#047857;'>Your Login Credentials</h3>
                            <table cellpadding='8' style='border-collapse:collapse;margin:10px 0;width:100%;'>
                              <tr>
                                <td style='background:#e8f5e8;border:1px solid #c3e6cb;font-weight:bold;width:120px;'>Role</td>
                                <td style='border:1px solid #c3e6cb;'>" . htmlspecialchars($role) . "</td>
                              </tr>
                              <tr>
                                <td style='background:#e8f5e8;border:1px solid #c3e6cb;font-weight:bold;'>Username</td>
                                <td style='border:1px solid #c3e6cb;font-family:monospace;font-size:16px;color:#047857;font-weight:bold;'>" . htmlspecialchars($username) . "</td>
                              </tr>
                              <tr>
                                <td style='background:#e8f5e8;border:1px solid #c3e6cb;font-weight:bold;'>Password</td>
                                <td style='border:1px solid #c3e6cb;font-family:monospace;font-size:16px;color:#047857;font-weight:bold;'>" . htmlspecialchars($password) . "</td>
                              </tr>
                            </table>
                          </div>
                          <div style='background:#fff3cd;border:1px solid #ffeaa7;border-radius:6px;padding:15px;margin:15px 0;'>
                            <p style='margin:0;color:#856404;'><strong>Important:</strong> Please keep these credentials secure and change your password after first login.</p>
                          </div>
                          <p>You can now access the system using these credentials.</p>
                          <p style='font-size:12px;color:#555;'>If you did not request this account, please contact the system administrator immediately.</p>
                          <p style='font-size:12px;color:#888;'>-- Barangay Health Management System</p>
                        </div>
                    ";
                    $text = "Staff Account Created Successfully\n\n" .
                            "Hello {$staffName},\n\n" .
                            "Your {$role} account has been created.\n\n" .
                            "Login Credentials:\n" .
                            "Role: {$role}\n" .
                            "Username: {$username}\n" .
                            "Password: {$password}\n\n" .
                            "Please keep these credentials secure and change your password after first login.\n\n" .
                            "-- Barangay Health Management System";
                    
                    $emailSent = bhw_mail_send($email, $subject, $html, $text);
                    if (!$emailSent) {
                        $emailError = bhw_mail_last_error();
                    }
                }

                // Redirect to prevent duplicate submission on refresh
                header("Location: " . $_SERVER['PHP_SELF'] . "?section=accounts&success=1&username=" . urlencode($username) . "&password=" . urlencode($password) . "&role=" . urlencode($role) . "&email=" . urlencode($email) . "&emailSent=" . ($emailSent ? '1' : '0') . "&emailError=" . urlencode($emailError ?: ''));
                exit;
            } catch (mysqli_sql_exception $e) {
                $err = $e->getMessage();
                if (strpos($err, 'Duplicate entry') !== false) {
                    if (strpos($err, 'uq_email') !== false || strpos($err, 'email') !== false) {
                        $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                             . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                             . "Email already exists. Choose another.</div>";
                    } elseif (strpos($err, 'username') !== false) {
                        $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                             . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                             . "Username collision occurred. Please retry.</div>";
                    } else {
                        $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                             . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                             . "Duplicate value detected.</div>";
                    }
                } else {
                    $msg = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>"
                         . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>"
                         . "Failed to create account: " . htmlspecialchars($err) . "</div>";
                }
            } finally {
                $stmt->close();
            }
            create_account_end:
            ; // label end (no-op)
        }
        
        // EDIT ACCOUNT
        if ($section === 'accounts' && isset($_POST['edit_account'])) {
            $uid = intval($_POST['user_id']);
            $first_name = $mysqli->real_escape_string($_POST['first_name']);
            $middle_name = $mysqli->real_escape_string($_POST['middle_name'] ?? '');
            $last_name = $mysqli->real_escape_string($_POST['last_name']);
            $email = $mysqli->real_escape_string($_POST['email']);
            $stmt = $mysqli->prepare("
                UPDATE users 
                SET first_name = ?, middle_name = ?, last_name = ?, email = ? 
                WHERE user_id = ?
            ");
            if ($stmt === false) {
                $msg = "<div class='alert alert-danger'>Database error: Unable to update account.</div>";
            } else {
                $stmt->bind_param("ssssi", $first_name, $middle_name, $last_name, $email, $uid);
                if ($stmt->execute()) {
                    $msg = "<div class='alert alert-success'>Account updated successfully!</div>";
                } else {
                    $msg = "<div class='alert alert-danger'>Failed to update account: " . $mysqli->error . "</div>";
                }
                $stmt->close();
            }
        }
        
        // TOGGLE ACTIVE
        if ($section === 'accounts' && isset($_POST['toggle_active'])) {
            $uid = intval($_POST['user_id']);
            $active = $_POST['is_active'] ? 1 : 0;
            $stmt = $mysqli->prepare("
                UPDATE users 
                SET is_active = ? 
                WHERE user_id = ?
            ");
            if ($stmt === false) {
                exit(json_encode(['ok' => false, 'error' => 'Database error']));
            } else {
                $stmt->bind_param("ii", $active, $uid);
                $stmt->execute();
                $stmt->close();
                exit(json_encode(['ok' => true]));
            }
        }
        
        // RESET PASSWORD
        if ($section === 'accounts' && isset($_POST['reset_password'])) {
            $uid = intval($_POST['user_id']);
            
            // Get user details
            $stmt = $mysqli->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                if ($user) {
                    // Generate new password
                    $new_password = rand_password(12);
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $update_stmt = $mysqli->prepare("UPDATE users SET password = ?, password_hash = ? WHERE user_id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("ssi", $new_password, $password_hash, $uid);
                        if ($update_stmt->execute()) {
                            // Send email notification
                            if (function_exists('bhw_mail_send')) {
                                $subject = "Password Reset - Barangay Health System";
                                $html = "
                                    <div style='font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;'>
                                      <h2 style='margin:0 0 10px;color:#047857;'>Password Reset Notification</h2>
                                      <p>Hello <strong>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</strong>,</p>
                                      <p>Your password has been reset by the system administrator.</p>
                                      <div style='background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:20px;margin:15px 0;'>
                                        <h3 style='margin:0 0 15px;color:#047857;'>Your New Password</h3>
                                        <div style='font-family:monospace;font-size:18px;color:#047857;font-weight:bold;background:#e8f5e8;padding:10px;border-radius:4px;text-align:center;'>" . htmlspecialchars($new_password) . "</div>
                                      </div>
                                      <div style='background:#fff3cd;border:1px solid #ffeaa7;border-radius:6px;padding:15px;margin:15px 0;'>
                                        <p style='margin:0;color:#856404;'><strong>Important:</strong> Please change your password after logging in for security purposes.</p>
                                      </div>
                                      <p>You can now log in using your email and the new password above.</p>
                                      <p style='font-size:12px;color:#555;'>If you did not request this password reset, please contact the system administrator immediately.</p>
                                      <p style='font-size:12px;color:#888;'>-- Barangay Health Management System</p>
                                    </div>
                                ";
                                $text = "Password Reset Notification\n\n" .
                                        "Hello " . $user['first_name'] . ' ' . $user['last_name'] . ",\n\n" .
                                        "Your password has been reset.\n\n" .
                                        "New Password: " . $new_password . "\n\n" .
                                        "Please change your password after logging in.\n\n" .
                                        "-- Barangay Health Management System";
                                
                                bhw_mail_send($user['email'], $subject, $html, $text);
                            }
                            
                            exit(json_encode(['success' => true, 'new_password' => $new_password]));
                        }
                        $update_stmt->close();
                    }
                }
            }
            exit(json_encode(['success' => false, 'error' => 'Failed to reset password']));
        }
        
        
    }
}


$currentUsername = $_SESSION['username'] ?? 'Admin User';
$currentRoleName = $_SESSION['role'] ?? 'System Administrator';

function initials($name) {
    $p = preg_split('/\s+/', $name);
    if (!$p) return 'AD';
    if (count($p) === 1) return strtoupper(substr($p[0], 0, 2));
    return strtoupper(substr($p[0], 0, 1) . substr(end($p), 0, 1));
}

$initials = initials($currentUsername);

$titles = [
    'control-panel' => 'Control Panel',
    'accounts' => 'Account Management',
    'reports' => 'Reports & Analytics',
    'health_records' => 'Health Records',
    'immunization' => 'Immunization Management',
    'maternal_patients' => 'Maternal Patients',
    'parent_accounts' => 'Parent Account Management',
    'children_management' => 'Children Management',
    'nutrition_data_entry' => 'Nutrition Data Entry',
    'supplementation' => 'Supplementation'
];

$descs = [
    'control-panel' => 'Monitor system performance and user activity',
    'accounts' => 'Manage BHW / BNS user accounts',
    'reports' => 'View comprehensive reports and analytics for BHW and BNS activities',
    'health_records' => 'Manage maternal health records',
    'immunization' => 'Track child immunizations',
    'maternal_patients' => 'Manage maternal patient records',
    'parent_accounts' => 'Manage parent portal accounts',
    'children_management' => 'Manage child registry & quick nutrition status',
    'nutrition_data_entry' => 'Enter and review nutrition measurements',
    'supplementation' => 'Track vitamin & supplementation records'
];

// External interface links (update these to your actual paths)
// Examples:
//   bhw/index.php or dashboard_bhw.php
//   bns/index.php or dashboard_bns.php
$bhwInterfaceUrl = $_ENV['BHW_URL'] ?? 'dashboard_bhw';
$bnsInterfaceUrl = $_ENV['BNS_URL'] ?? 'dashboard_bns';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($titles[$section]); ?> - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="assets/img/sabang.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="assets/img/sabang.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --green: #047857;
            --green-soft: #e8f9f1;
            --sidebar-border: #e5e9ed;
            --surface: #ffffff;
            --surface-alt: #f6f8fa;
            --border-color: #e2e7ec;
            --text-soft: #5f6b76;
            --radius-md: 16px;
            --radius-sm: 8px;
            --badge-bg: #eef1f4;
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08), 0 0 0 1px rgba(0, 0, 0, .02);
        }
        html, body {
            height: 100%;
            background: #f4f6f8;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 17px;
        }
        .layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            flex: 0 0 var(--sidebar-width);
            background: #fff;
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .brand {
            padding: .85rem 1rem .65rem;
            border-bottom: 1px solid var(--sidebar-border);
        }
        .brand h1 {
            font-size: 1.3rem;
            margin: 0;
            font-weight: 600;
            color: var(--green);
        }
        .brand small {
            font-size: .7rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #6a7680;
        }
        .nav-section {
            padding: 1rem .75rem .5rem;
        }
        .nav-section+.nav-section {
            padding-top: .25rem;
        }
        .nav-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .nav-list li {
            margin-bottom: 3px;
        }
        .nav-list a {
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
            font-size: .95rem;
            padding: .7rem .8rem;
            border-radius: 12px;
            color: #1b2830;
            font-weight: 500;
            position: relative;
            transition: .15s;
        }
        .nav-list a .bi {
            font-size: 1rem;
            opacity: .75;
        }
        .nav-list a:hover {
            background: #f2f6f8;
        }
        .nav-list a.active {
            background: var(--green-soft);
            color: #033d29;
            font-weight: 600;
        }
        .nav-list a.active::before {
            content: "";
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            border-radius: 2px;
            background: var(--green);
        }
        .sidebar-footer {
            margin-top: auto;
            padding: .85rem .75rem;
            font-size: .6rem;
            line-height: 1.2;
            text-align: center;
            background: linear-gradient(135deg, #f0faf5, #f1f7ff);
            border-top: 1px solid var(--sidebar-border);
            color: #33504a;
        }
        .topbar {
            height: 60px;
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            padding: .55rem 1.25rem;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 1040;
        }
        .topbar .page-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--green);
            margin: 0;
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: .6rem;
            background: #f1f4f6;
            padding: .35rem .75rem .35rem .4rem;
            border-radius: 32px;
        }
        .user-chip .avatar {
            width: 34px;
            height: 34px;
            background: #047857;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 600;
        }
        .user-chip small {
            display: block;
            line-height: 1.05;
        }
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            margin-left: var(--sidebar-width);
        }
        .main-inner {
            padding: 1.4rem 1.8rem 2.2rem;
        }
        .metrics-row {
            display: flex;
            gap: 1.25rem;
            flex-wrap: wrap;
        }
        .metric-card {
            flex: 1 1 200px;
            background: #fff;
            border: 1px solid #e2e7ec;
            border-radius: 18px;
            padding: .95rem 1rem 1rem;
            position: relative;
            min-width: 190px;
            display: flex;
            flex-direction: column;
            gap: .45rem;
            box-shadow: var(--shadow-xs);
        }
        .metric-card .title {
            font-size: .62rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 600;
            color: #5e6a73;
            margin: 0;
        }
        .metric-card .value {
            font-size: 2rem;
            font-weight: 800;
            color: #16242e;
            line-height: 1;
        }
        .metric-card .delta {
            font-size: .6rem;
            font-weight: 700;
            background: #eceff2;
            color: #1b2932;
            padding: .28rem .55rem;
            border-radius: 12px;
            display: inline-block;
        }
        .metric-icon {
            position: absolute;
            top: .75rem;
            right: .75rem;
            width: 40px;
            height: 40px;
            background: #e6f0ff;
            color: #275dd4;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.1rem;
        }
        .metric-icon.green {
            background: #e6f7ed;
            color: #08704b;
        }
        .metric-icon.up {
            background: #e6f7ed;
            color: #08704b;
        }
        .metric-icon.purple {
            background: #efe6ff;
            color: #6e3fce;
        }
        .panel {
            background: #fff;
            border: 1px solid #e2e7ec;
            border-radius: 18px;
            padding: 1.1rem 1.25rem;
            box-shadow: var(--shadow-xs);
        }
        .panel+.panel {
            margin-top: 1.25rem;
        }
        .panel-header h6 {
            font-size: .9rem;
            font-weight: 600;
            margin: 0;
        }
        .panel-header p {
            font-size: .68rem;
            margin: .2rem 0 0;
            color: var(--text-soft);
        }
        .table-activity {
            width: 100%;
            border-collapse: collapse;
            font-size: .86rem;
        }
        .table-activity th, .table-activity td {
            padding: .6rem .7rem;
        }
        .table-activity thead th {
            font-size: .66rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 600;
            color: #59656f;
            background: #f3f6f8;
            border-bottom: 1px solid #e3e7eb;
        }
        .table-activity tbody tr {
            border-bottom: 1px solid #eff2f4;
        }
        .table-activity tbody tr:last-child {
            border-bottom: none;
        }
        .badge-soft {
            font-size: .58rem;
            font-weight: 600;
            letter-spacing: .03em;
            padding: .32rem .55rem;
            border-radius: 14px;
            background: #e8edf2;
            color: #2b3b46;
            display: inline-block;
        }
        .badge-soft.success {
            background: #dcf7e9;
            color: #046b3e;
        }
        .badge-soft.info {
            background: #e1ebff;
            color: #1053c2;
        }
        .badge-soft.system {
            background: #eceff2;
            color: #46545e;
        }
        .progress-slim {
            height: 6px;
            background: #e3e7eb;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .progress-slim .bar {
            height: 100%;
            background: linear-gradient(90deg, #047857, #089867);
        }
        .card-action {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--surface);
            text-align: left;
            cursor: pointer;
        }
        .card-action.green {
            background: var(--green-soft);
        }
        .card-action .icon {
            font-size: 1.5rem;
            color: var(--green);
        }
        .tabs-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tab-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: var(--surface-alt);
            border-radius: var(--radius-sm);
            font-weight: 500;
            color: var(--text-soft);
            cursor: pointer;
        }
        .tab-btn.active {
            background: var(--green);
            color: #fff;
        }
        .role-tag {
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            background: var(--badge-bg);
            font-size: 0.75rem;
        }
        .role-tag.bhw {
            background: #e6f7ed;
            color: #08704b;
        }
        .role-tag.bns {
            background: #e6f0ff;
            color: #275dd4;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            border-radius: 20px;
            transition: 0.4s;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background: #fff;
            border-radius: 50%;
            transition: 0.4s;
        }
        input:checked+.slider {
            background: var(--green);
        }
        input:checked+.slider:before {
            transform: translateX(20px);
        }
        .status-label {
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .status-label.inactive {
            color: #d9534f;
        }
        .reports-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .reports-tools-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .alert-reminder {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .required::after {
            content: '*';
            color: #dc3545;
            margin-left: 0.2rem;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .data-table th, .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .data-table th {
            background: var(--surface-alt);
            font-weight: 600;
            font-size: 0.85rem;
        }
        .data-table tbody tr:hover {
            background: var(--surface-alt);
        }
        .btn-action {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 6px;
        }
        /* Additional UI Enhancements for dynamic modules */
        .loading-placeholder {
            display:flex; align-items:center; gap:.75rem;
            font-size:.85rem; color:#5c6872;
        }
        .loading-placeholder .spinner-border {
            width:1rem; height:1rem;
        }
        .mini-badge {
            display:inline-block;
            background:#edf2f6;
            padding:.2rem .5rem;
            font-size:.6rem;
            border-radius:10px;
            font-weight:600;
            letter-spacing:.05em;
            text-transform:uppercase;
        }
        .mini-badge.danger { background:#ffe5e5; color:#a40000; }
        .mini-badge.warn { background:#fff4d6; color:#7d5a00; }
        .mini-badge.ok { background:#e3f9ec; color:#036635; }
        .space-y-3 > * + * {
            margin-top: 1rem;
        }
        .card-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        /* Chart container fixes */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
        .chart-container canvas {
            max-height: 300px !important;
            max-width: 100% !important;
        }
        /* Logo styling */
        .brand img, .topbar img {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 2px solid rgba(4, 120, 87, 0.2);
        }
        .brand img:hover, .topbar img:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease;
        }
        @media (max-width: 900px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 1040;
                transform: translateX(-100%);
                transition: .25s;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .topbar {
                position: sticky;
                top: 0;
                z-index: 1030;
            }
            .main {
                margin-left: 0;
            }
            .main-inner {
                padding: 1.2rem 1rem 2rem;
            }
            .metrics-row {
                gap: .9rem;
            }
            .metric-card {
                min-width: calc(50% - .9rem);
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <img src="assets/img/sabang.jpg" alt="Barangay Sabang Logo" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                    <div>
                        <h1 style="font-size: 1.1rem; margin: 0;">Barangay Sabang</h1>
                        <small style="font-size: 0.65rem;">Health &amp; Nutrition Management System</small>
                    </div>
                </div>
            </div>
            <div class="nav-section">
                <small class="text-muted d-block mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0.5rem;">Admin Navigation</small>
                <ul class="nav-list">
                    <li><a class="<?php echo $section === 'control-panel' ? 'active' : ''; ?>" href="?section=control-panel"><i class="bi bi-grid"></i><span>Control Panel</span></a></li>
                    <li><a class="<?php echo $section === 'accounts' ? 'active' : ''; ?>" href="?section=accounts"><i class="bi bi-people"></i><span>Account Management</span></a></li>
                    <li><a class="<?php echo $section === 'reports' ? 'active' : ''; ?>" href="?section=reports"><i class="bi bi-graph-up"></i><span>Reports & Analytics</span></a></li>
                </ul>
            </div>
            <!-- Shortcuts to external role interfaces -->
            <div class="nav-section">
                <small class="text-muted d-block mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0.5rem;">Interfaces</small>
                <ul class="nav-list">
                    <li>
                        <a href="<?php echo htmlspecialchars($bhwInterfaceUrl); ?>" title="Open Barangay Health Worker tools">
                            <i class="bi bi-heart-pulse"></i><span>BHW Workspace</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo htmlspecialchars($bnsInterfaceUrl); ?>" title="Open Barangay Nutrition Scholar tools">
                            <i class="bi bi-shield-check"></i><span>BNS Workspace</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <div>Barangay Health System</div>
                <div>Version 2.0.1</div>
                <div class="mt-1">&copy; <?php echo date('Y'); ?></div>
                <a href="logout" class="btn btn-success btn-sm w-100 mt-2">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </aside>
        
        <!-- Main -->
        <div class="main">
            <div class="topbar">
                <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
                <div class="d-flex align-items-center gap-3">
                    <img src="assets/img/sabang.jpg" alt="Barangay Sabang Logo" style="width: 35px; height: 35px; border-radius: 6px; object-fit: cover;">
                    <h1 class="page-title mb-0">
                        <?php echo htmlspecialchars($titles[$section]); ?>
                    </h1>
                </div>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <div class="position-relative">
                        <button class="btn btn-link text-decoration-none p-0 position-relative" style="color:#1c2a32;">
                            <i class="bi bi-bell" style="font-size:1.1rem;"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.55rem;">3</span>
                        </button>
                    </div>
                    <div class="user-chip">
                        <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="d-none d-sm-flex flex-column">
                            <small class="fw-semibold" style="font-size:.63rem;"><?php echo htmlspecialchars($currentUsername); ?></small>
                            <small style="font-size:.55rem; opacity:.65;"><?php echo htmlspecialchars($currentRoleName); ?></small>
                        </div>
                        <i class="bi bi-chevron-down" style="font-size:.7rem; opacity:.55;"></i>
                    </div>
                </div>
            </div>
            
            <div class="main-inner">
                <div class="mb-4">
                    <p class="mb-1" style="font-size:.8rem; font-weight:600; color:#0a583c;">
                        <?php echo htmlspecialchars($titles[$section]); ?>
                    </p>
                    <p class="mb-0" style="font-size:.7rem; color:#5c6872;">
                        <?php echo htmlspecialchars($descs[$section]); ?>
                    </p>
                </div>
                
                <?php echo $msg; ?>
                
                <?php if ($section === 'accounts') : ?>
                    <!-- Account Management Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <p class="title">Total Users</p>
                                <div class="value"><?php echo count($accounts); ?></div>
                                <span class="delta">+<?php echo count($accounts); ?>%</span>
                                <div class="metric-icon"><i class="bi bi-people"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <p class="title">Active Users</p>
                                <div class="value"><?php echo count(array_filter($accounts, function($a) { return $a['is_active']; })); ?></div>
                                <span class="delta">Active</span>
                                <div class="metric-icon green"><i class="bi bi-person-check"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <p class="title">BHW Staff</p>
                                <div class="value"><?php echo count(array_filter($accounts, function($a) { return $a['role'] === 'BHW'; })); ?></div>
                                <span class="delta">Health Workers</span>
                                <div class="metric-icon up"><i class="bi bi-heart-pulse"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <p class="title">BNS Staff</p>
                                <div class="value"><?php echo count(array_filter($accounts, function($a) { return $a['role'] === 'BNS'; })); ?></div>
                                <span class="delta">Nutrition Scholars</span>
                                <div class="metric-icon purple"><i class="bi bi-shield-check"></i></div>
                            </div>
                        </div>
                    </div>

                    <!-- Create Account Button -->
                    <div class="row mb-4">
                        <div class="col-md-6 col-lg-4">
                            <button type="button" class="card-action green w-100" data-bs-toggle="modal" data-bs-target="#createAccountModal">
                                <div class="icon"><i class="bi bi-person-plus"></i></div>
                                <div>
                                    <div style="font-weight:600;">Create New Account</div>
                                    <div style="font-size:.97em;color:#5c6872;">Add BHW or BNS staff</div>
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <div class="tabs-bar mb-3">
                        <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'user' ? 'active' : ''; ?>" onclick="location.href='?section=accounts&tab=user'">User Management</button>
                        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] == 'audit' ? 'active' : ''; ?>" onclick="location.href='?section=accounts&tab=audit'">Audit Log</button>
                    </div>
                    
                    <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'user') : ?>
                        <div class="panel">
                            <div class="panel-header mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                <h6>All Accounts</h6>
                                <p>Manage user accounts and access control</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <div class="input-group" style="width: 250px;">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" placeholder="Search users..." id="userSearch">
                                        </div>
                                        <select class="form-select" style="width: 120px;" id="roleFilter">
                                            <option value="">All Roles</option>
                                            <option value="Admin">Admin</option>
                                            <option value="BHW">BHW</option>
                                            <option value="BNS">BNS</option>
                                            <option value="Parent">Parent</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Created</th>
                                            <th>Last Login</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($accounts as $ac) : ?>
                                            <tr data-role="<?php echo strtolower($ac['role']); ?>" data-name="<?php echo strtolower($ac['name']); ?>" data-email="<?php echo strtolower($ac['email']); ?>">
                                                <td>
                                                    <input type="checkbox" class="form-check-input user-checkbox" value="<?php echo $ac['user_id']; ?>">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="avatar-sm" style="width: 32px; height: 32px; background: #047857; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600;">
                                                            <?php echo strtoupper(substr($ac['name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($ac['name']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div><?php echo htmlspecialchars($ac['email']); ?></div>
                                                        <div style="font-size: 0.75rem; color: #6c757d;">
                                                            <i class="bi bi-envelope me-1"></i>Email verified
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="role-tag <?php echo strtolower($ac['role']); ?>">
                                                        <i class="bi bi-<?php echo $ac['role'] === 'BHW' ? 'heart-pulse' : ($ac['role'] === 'BNS' ? 'shield-check' : ($ac['role'] === 'Admin' ? 'gear' : 'person')); ?> me-1"></i>
                                                        <?php echo htmlspecialchars($ac['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div style="font-size: 0.85rem;"><?php echo date('M j, Y', strtotime($ac['created_at'])); ?></div>
                                                        <div style="font-size: 0.75rem; color: #6c757d;"><?php echo date('g:i A', strtotime($ac['created_at'])); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-size: 0.85rem; color: #6c757d;">
                                                        <i class="bi bi-clock me-1"></i>Never
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $ac['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $ac['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a class="btn btn-outline-primary btn-sm" href="?section=accounts&edit=<?php echo $ac['user_id']; ?>" title="Edit Account">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button class="btn btn-outline-success btn-sm" onclick="resetPassword(<?php echo $ac['user_id']; ?>)" title="Reset Password">
                                                            <i class="bi bi-arrow-clockwise"></i>
                                                        </button>
                                                        <?php if ($ac['is_active']) : ?>
                                                            <button class="btn btn-outline-danger btn-sm" onclick="toggleUserStatus(<?php echo $ac['user_id']; ?>, 0)" title="Deactivate User">
                                                                <i class="bi bi-person-x"></i>
                                                        </button>
                                                        <?php else : ?>
                                                            <button class="btn btn-outline-success btn-sm" onclick="toggleUserStatus(<?php echo $ac['user_id']; ?>, 1)" title="Activate User">
                                                                <i class="bi bi-person-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bulk Actions Bar -->
                            <div class="d-none" id="bulkActionsBar">
                                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                                    <div>
                                        <span id="selectedCount">0</span> user(s) selected
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn btn-outline-success btn-sm" onclick="bulkActivate()">
                                            <i class="bi bi-check-circle me-1"></i>Activate
                                        </button>
                                        <button class="btn btn-outline-warning btn-sm" onclick="bulkDeactivate()">
                                            <i class="bi bi-x-circle me-1"></i>Deactivate
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="bulkDelete()">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($edit_account) : ?>
                          <div class="modal fade show" id="editAccountModal" tabindex="-1" style="display:block" aria-modal="true" role="dialog">
                              <div class="modal-dialog">
                                  <form method="POST" action="?section=accounts">
                                      <input type="hidden" name="user_id" value="<?php echo $edit_account['user_id']; ?>">
                                      <input type="hidden" name="edit_account" value="1">
                                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                      <div class="modal-content">
                                          <div class="modal-header">
                                              <h5>Edit Account</h5>
                                          </div>
                                          <div class="modal-body">
                                              <div class="row">
                                                  <div class="col-md-6">
                                              <label class="required">First Name</label>
                                              <input type="text" name="first_name" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['first_name']); ?>" placeholder="First Name">
                                                  </div>
                                                  <div class="col-md-6">
                                                      <label>Middle Name</label>
                                                      <input type="text" name="middle_name" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['middle_name'] ?? ''); ?>" placeholder="Middle Name">
                                                  </div>
                                              </div>
                                              <div class="row">
                                                  <div class="col-md-6">
                                              <label class="required">Last Name</label>
                                              <input type="text" name="last_name" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['last_name']); ?>" placeholder="Last Name">
                                                  </div>
                                                  <div class="col-md-6">
                                              <label class="required">Email</label>
                                              <input type="email" name="email" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['email']); ?>" placeholder="Email">
                                                  </div>
                                              </div>
                                              <label>Current Password</label>
                                              <input type="text" value="<?php echo htmlspecialchars($edit_account['password'] ?? 'Not available'); ?>" class="form-control mb-2" readonly>
                                          </div>
                                          <div class="modal-footer">
                                              <a href="?section=accounts" class="btn btn-outline-secondary">Cancel</a>
                                              <button type="submit" class="btn btn-success">Save</button>
                                          </div>
                                      </div>
                                  </form>
                              </div>
                          </div>
                          <script>
                              document.body.style.overflow = 'hidden';
                          </script>
                      <?php endif; ?>
                    <?php elseif (isset($_GET['tab']) && $_GET['tab'] == 'audit') : ?>
                        <div class="panel">
                            <div class="panel-header mb-2">
                                <h6>Audit Log</h6>
                                <p>Recent account creation activities</p>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle">
                                    <thead>
                                        <tr>
                                            <th>Account Type</th>
                                            <th>Created By</th>
                                            <th>Reason</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($audit_logs as $log) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['account_type']); ?></td>
                                                <td><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($log['creation_reason']); ?></td>
                                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="modal fade" id="createAccountModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <form method="POST" action="?section=accounts">
                                <input type="hidden" name="create_account" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5><i class="bi bi-person-plus me-2"></i>Create New Staff Account</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            <strong>Account Creation:</strong> Username and password will be generated automatically and sent to the provided email address.
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                        <label class="required">First Name</label>
                                                <input type="text" name="first_name" required class="form-control mb-3" placeholder="Enter first name">
                                            </div>
                                            <div class="col-md-4">
                                                <label>Middle Name</label>
                                                <input type="text" name="middle_name" class="form-control mb-3" placeholder="Enter middle name">
                                            </div>
                                            <div class="col-md-4">
                                        <label class="required">Last Name</label>
                                                <input type="text" name="last_name" required class="form-control mb-3" placeholder="Enter last name">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="required">Email Address</label>
                                                <input type="email" name="email" required class="form-control mb-3" placeholder="Enter email address">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="required">Staff Role</label>
                                                <select name="role" required class="form-control mb-3">
                                                    <option value="">Select staff role</option>
                                                    <option value="BHW">BHW - Barangay Health Worker</option>
                                                    <option value="BNS">BNS - Barangay Nutrition Scholar</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="required">Birthday</label>
                                                <input type="date" name="birthday" required class="form-control mb-3" max="<?php echo date('Y-m-d'); ?>" id="birthdayInput">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label>Generated Username</label>
                                                <input type="text" id="generatedUsername" class="form-control mb-3" readonly style="background-color: #f8f9fa;">
                                                <small class="text-muted">Auto-generated based on name and birthday</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label>Generated Password</label>
                                                <input type="text" id="generatedPassword" class="form-control mb-3" readonly style="background-color: #f8f9fa;">
                                                <small class="text-muted">Auto-generated based on name and birthday</small>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <strong>Auto-generated credentials:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li><strong>Username:</strong> First letter of first name + surname + birth year (e.g., jdelacruz98)</li>
                                                <li><strong>Password:</strong> FirstName + 2 letters of LastName + Birthday MMDD (e.g., MariaCr0712)</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                            <i class="bi bi-x-circle me-1"></i>Cancel
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-person-plus me-1"></i>Create Account
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif ($section === 'reports') : ?>
                    <!-- Reports Overview -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1" style="font-size:.9rem; font-weight:600; color:#162630;">Comprehensive Reports</h6>
                                <p class="mb-0" style="font-size:.75rem; color:#5c6872;">BHW and BNS activity analytics and insights</p>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-success btn-sm" onclick="exportReports()">
                                    <i class="bi bi-download me-1"></i>Export
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="refreshReports()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                        
                        <!-- BHW Reports Section -->
                        <div class="panel mb-4">
                            <div class="panel-header mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-heart-pulse text-success" style="font-size:1.2rem;"></i>
                                    <h6 class="mb-0">BHW Health Reports</h6>
                                </div>
                                <p class="mb-0" style="font-size:.75rem; color:#5c6872;">Barangay Health Worker activity and health data</p>
                            </div>
                            
                            <!-- BHW Stats Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Health Records</p>
                                        <div class="value"><?php echo number_format($bhwStats['total_health_records'] ?? 0); ?></div>
                                        <span class="delta">Total Records</span>
                                        <div class="metric-icon green"><i class="bi bi-file-medical"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Maternal Patients</p>
                                        <div class="value"><?php echo number_format($bhwStats['total_maternal_patients'] ?? 0); ?></div>
                                        <span class="delta">Active Patients</span>
                                        <div class="metric-icon up"><i class="bi bi-person-heart"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Immunizations</p>
                                        <div class="value"><?php echo number_format($bhwStats['total_immunizations'] ?? 0); ?></div>
                                        <span class="delta">Vaccinations</span>
                                        <div class="metric-icon purple"><i class="bi bi-shield-check"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Risk Factors</p>
                                        <div class="value"><?php echo number_format(array_sum($riskFactors)); ?></div>
                                        <span class="delta">Total Cases</span>
                                        <div class="metric-icon"><i class="bi bi-exclamation-triangle"></i></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- BHW Charts Row -->
                            <div class="row g-3">
                                <div class="col-lg-8">
                                    <div class="panel">
                                        <div class="panel-header mb-2">
                                            <h6>Health Records Trend (6 Months)</h6>
                                            <p style="font-size:.7rem; color:#5c6872;">Monthly health record creation</p>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="healthRecordsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="panel">
                                        <div class="panel-header mb-2">
                                            <h6>Risk Factors Distribution</h6>
                                            <p style="font-size:.7rem; color:#5c6872;">Common health risk factors</p>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="riskFactorsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- BNS Reports Section -->
                        <div class="panel mb-4">
                            <div class="panel-header mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-shield-check text-primary" style="font-size:1.2rem;"></i>
                                    <h6 class="mb-0">BNS Nutrition Reports</h6>
                                </div>
                                <p class="mb-0" style="font-size:.75rem; color:#5c6872;">Barangay Nutrition Scholar activity and nutrition data</p>
                            </div>
                            
                            <!-- BNS Stats Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Nutrition Records</p>
                                        <div class="value"><?php echo number_format($bnsStats['total_nutrition_records'] ?? 0); ?></div>
                                        <span class="delta">Total Records</span>
                                        <div class="metric-icon green"><i class="bi bi-clipboard-data"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Children</p>
                                        <div class="value"><?php echo number_format($bnsStats['total_children'] ?? 0); ?></div>
                                        <span class="delta">Registered</span>
                                        <div class="metric-icon up"><i class="bi bi-people"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">Supplementation</p>
                                        <div class="value"><?php echo number_format($bnsStats['total_supplementation'] ?? 0); ?></div>
                                        <span class="delta">Total Doses</span>
                                        <div class="metric-icon purple"><i class="bi bi-capsule"></i></div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="metric-card">
                                        <p class="title">WFL Status</p>
                                        <div class="value"><?php echo count($wflStatus); ?></div>
                                        <span class="delta">Categories</span>
                                        <div class="metric-icon"><i class="bi bi-graph-up"></i></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- BNS Charts Row -->
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="panel">
                                        <div class="panel-header mb-2">
                                            <h6>Nutrition Records Trend (6 Months)</h6>
                                            <p style="font-size:.7rem; color:#5c6872;">Monthly nutrition record creation</p>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="nutritionRecordsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="panel">
                                        <div class="panel-header mb-2">
                                            <h6>Weight-for-Length Status</h6>
                                            <p style="font-size:.7rem; color:#5c6872;">Child nutrition status distribution</p>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="wflStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Supplementation Chart -->
                            <div class="row g-3 mt-3">
                                <div class="col-lg-6">
                                    <div class="panel">
                                        <div class="panel-header mb-2">
                                            <h6>Supplementation by Type</h6>
                                            <p style="font-size:.7rem; color:#5c6872;">Distribution of supplement types</p>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="supplementationChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="panel">
                                        <div class="panel-header mb-2">
                                            <h6>Recent Activity</h6>
                                            <p style="font-size:.7rem; color:#5c6872;">Last 30 days activity</p>
                                        </div>
                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Description</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($recentActivity, 0, 10) as $activity) : ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-<?php echo $activity['type'] === 'health_record' ? 'success' : ($activity['type'] === 'nutrition_record' ? 'primary' : 'info'); ?>">
                                                                    <?php echo ucfirst(str_replace('_', ' ', $activity['type'])); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                                            <td style="font-size:.75rem;"><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                <?php else : ?>
                    <div class="mb-4">
                        <h6 class="mb-3" style="font-size:.75rem; font-weight:600; color:#162630;">System Overview</h6>
                        <div class="metrics-row">
                            <div class="metric-card">
                                <p class="title">Active Users</p>
                                <div class="value"><?php echo $activeUsers; ?></div>
                                <span class="delta">+12%</span>
                                <div class="metric-icon"><i class="bi bi-people"></i></div>
                            </div>
                            <div class="metric-card">
                                <p class="title">Total Records</p>
                                <div class="value"><?php echo number_format($totalRecords); ?></div>
                                <span class="delta">+8%</span>
                                <div class="metric-icon green"><i class="bi bi-database"></i></div>
                            </div>
                            <div class="metric-card">
                                <p class="title">System Uptime</p>
                                <div class="value"><?php echo number_format($systemUptimePct, 1); ?>%</div>
                                <span class="delta">Stable</span>
                                <div class="metric-icon up"><i class="bi bi-graph-up"></i></div>
                            </div>
                            <div class="metric-card">
                                <p class="title">Avg Response Time</p>
                                <div class="value"><?php echo (int)$avgResponseMs; ?>ms</div>
                                <span class="delta">-5%</span>
                                <div class="metric-icon purple"><i class="bi bi-clock-history"></i></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions Panel -->
                    <div class="panel mb-4">
                        <div class="panel-header mb-3">
                            <h6>Quick Actions</h6>
                            <p>Frequently used administrative tasks</p>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3 col-sm-6">
                                <div class="card-action" onclick="location.href='?section=accounts'">
                                    <div class="icon"><i class="bi bi-person-plus"></i></div>
                                    <div>
                                        <div style="font-weight:600;">Create Account</div>
                                        <div style="font-size:.85em;color:#5c6872;">Add new BHW/BNS</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="card-action" onclick="location.href='?section=events'">
                                    <div class="icon"><i class="bi bi-calendar-plus"></i></div>
                                    <div>
                                        <div style="font-weight:600;">Schedule Event</div>
                                        <div style="font-size:.85em;color:#5c6872;">Community activities</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="card-action" onclick="location.href='?section=reports'">
                                    <div class="icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
                                    <div>
                                        <div style="font-weight:600;">Generate Report</div>
                                        <div style="font-size:.85em;color:#5c6872;">Health & nutrition data</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Recent Notifications Panel -->
                    <div class="panel mb-4">
                        <div class="panel-header mb-3">
                            <h6>Recent Notifications</h6>
                            <p>Latest system alerts and updates</p>
                        </div>
                        <div class="space-y-3">
                            <div class="d-flex align-items-start gap-3 p-3 border rounded-3" style="background:#fff3cd;border-color:#ffeaa7;">
                                <div class="text-warning">
                                    <i class="bi bi-exclamation-triangle" style="font-size:1.2rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div style="font-weight:600;font-size:.9rem;">Vaccination Records Update</div>
                                    <div style="font-size:.8rem;color:#5c6872;">15 children have overdue vaccinations</div>
                                    <div style="font-size:.75rem;color:#6c757d;">2 hours ago</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3 p-3 border rounded-3" style="background:#d1ecf1;border-color:#bee5eb;">
                                <div class="text-info">
                                    <i class="bi bi-info-circle" style="font-size:1.2rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div style="font-weight:600;font-size:.9rem;">New Parent Registration</div>
                                    <div style="font-size:.8rem;color:#5c6872;">3 new parent accounts created today</div>
                                    <div style="font-size:.75rem;color:#6c757d;">4 hours ago</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3 p-3 border rounded-3" style="background:#d4edda;border-color:#c3e6cb;">
                                <div class="text-success">
                                    <i class="bi bi-check-circle" style="font-size:1.2rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div style="font-weight:600;font-size:.9rem;">System Backup Complete</div>
                                    <div style="font-size:.8rem;color:#5c6872;">Daily backup completed successfully</div>
                                    <div style="font-size:.75rem;color:#6c757d;">1 day ago</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="panel mb-4">
                        <div class="panel-header mb-2">
                            <h6>User Activity Monitoring</h6>
                            <p>Recent system usage and login logs</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table-activity mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:240px;">User</th>
                                        <th>Action</th>
                                        <th style="width:160px;">Time</th>
                                        <th style="width:100px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity as $row) :
                                        $class = 'badge-soft';
                                        if ($row['status'] === 'success') $class .= ' success';
                                        elseif ($row['status'] === 'info') $class .= ' info';
                                        elseif ($row['status'] === 'system') $class .= ' system';
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['user']); ?></td>
                                            <td><?php echo htmlspecialchars($row['action']); ?></td>
                                            <td class="text-muted"><?php echo htmlspecialchars(rel_time($row['time'])); ?></td>
                                            <td><span class="<?php echo $class; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="admin_access/admin_utils.js"></script>
    <!-- BNS scripts removed -->
            
    <script>

        window.__ADMIN_CSRF = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";

        if (window.AdminAPI && window.__ADMIN_CSRF) {
            AdminAPI.setCSRF(window.__ADMIN_CSRF);
        }
        
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // User search and filtering
        document.getElementById('userSearch')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const email = row.getAttribute('data-email') || '';
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Role filtering
        document.getElementById('roleFilter')?.addEventListener('change', function() {
            const selectedRole = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const role = row.getAttribute('data-role') || '';
                
                if (!selectedRole || role === selectedRole) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Select all functionality
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionsBar();
        });
        
        // Individual checkbox change
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsBar);
        });
        
        function updateBulkActionsBar() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            const bulkBar = document.getElementById('bulkActionsBar');
            const countSpan = document.getElementById('selectedCount');
            
            if (checkedBoxes.length > 0) {
                bulkBar.classList.remove('d-none');
                countSpan.textContent = checkedBoxes.length;
            } else {
                bulkBar.classList.add('d-none');
            }
        }
        
        // Bulk action functions
        window.exportUsers = function() {
            alert('Export functionality will be implemented');
        };
        
        window.bulkActions = function() {
            alert('Bulk actions panel will be implemented');
        };
        
        window.resetPassword = function(userId) {
            if (confirm('Are you sure you want to reset the password for this user? A new password will be generated and sent to their email.')) {
                // Show loading state
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                button.disabled = true;
                
                // Simulate API call
                fetch('?section=accounts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `reset_password=1&user_id=${userId}&csrf_token=${window.__ADMIN_CSRF}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Password reset successfully! New password: ' + data.new_password);
                    } else {
                        alert('Error: ' + (data.error || 'Failed to reset password'));
                    }
                })
                .catch(() => {
                    alert('Error resetting password. Please try again.');
                })
                .finally(() => {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                });
            }
        };
        
        window.toggleUserStatus = function(userId, newStatus) {
            const action = newStatus ? 'activate' : 'deactivate';
            const message = newStatus 
                ? 'Are you sure you want to activate this user? They will be able to log in again.'
                : 'Are you sure you want to deactivate this user? They will not be able to log in until reactivated.';
            
            if (confirm(message)) {
                fetch('?section=accounts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `toggle_active=1&user_id=${userId}&is_active=${newStatus}&csrf_token=${window.__ADMIN_CSRF}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || `Failed to ${action} user`));
                    }
                })
                .catch(() => {
                    alert(`Error ${action}ing user. Please try again.`);
                });
            }
        };
        
        window.bulkActivate = function() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length > 0 && confirm(`Activate ${checkedBoxes.length} user(s)?`)) {
                alert('Bulk activate functionality will be implemented');
            }
        };
        
        window.bulkDeactivate = function() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length > 0 && confirm(`Deactivate ${checkedBoxes.length} user(s)?`)) {
                alert('Bulk deactivate functionality will be implemented');
            }
        };
        
        window.bulkDelete = function() {
            const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
            if (checkedBoxes.length > 0 && confirm(`Delete ${checkedBoxes.length} user(s)? This action cannot be undone.`)) {
                alert('Bulk delete functionality will be implemented');
            }
        };
        
        
        // Auto-generate username and password
        function generateCredentials() {
            const firstName = document.querySelector('input[name="first_name"]')?.value || '';
            const lastName = document.querySelector('input[name="last_name"]')?.value || '';
            const birthday = document.querySelector('input[name="birthday"]')?.value || '';
            
            if (firstName && lastName && birthday) {
                // Generate username: first letter of first name + surname + year of birthday
                const firstLetter = firstName.charAt(0).toLowerCase();
                const surname = lastName.toLowerCase();
                const birthYear = birthday.substring(2, 4); // Get 2-digit year
                const username = firstLetter + surname + birthYear;
                
                // Generate password: FirstName[2 letters of LastName][Birthday MMDD]
                const firstNameCapitalized = firstName.charAt(0).toUpperCase() + firstName.slice(1).toLowerCase();
                const lastName2Letters = lastName.substring(0, 2).toLowerCase();
                const birthdayMMDD = birthday.substring(5, 7) + birthday.substring(8, 10); // MM + DD
                const password = firstNameCapitalized + lastName2Letters + birthdayMMDD;
                
                // Update the display fields
                const usernameField = document.getElementById('generatedUsername');
                const passwordField = document.getElementById('generatedPassword');
                
                if (usernameField) usernameField.value = username;
                if (passwordField) passwordField.value = password;
            } else {
                // Clear fields if not all required fields are filled
                const usernameField = document.getElementById('generatedUsername');
                const passwordField = document.getElementById('generatedPassword');
                
                if (usernameField) usernameField.value = '';
                if (passwordField) passwordField.value = '';
            }
        }
        
        // Add event listeners to form fields
        document.addEventListener('DOMContentLoaded', function() {
            // Clean up URL parameters after showing success message
            if (window.location.search.includes('success=1')) {
                // Remove success parameters from URL after a short delay
                setTimeout(function() {
                    const url = new URL(window.location);
                    url.searchParams.delete('success');
                    url.searchParams.delete('username');
                    url.searchParams.delete('password');
                    url.searchParams.delete('role');
                    url.searchParams.delete('email');
                    url.searchParams.delete('emailSent');
                    url.searchParams.delete('emailError');
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                }, 2000);
            }
            
            // Add event listeners for auto-generation
            const firstNameField = document.querySelector('input[name="first_name"]');
            const lastNameField = document.querySelector('input[name="last_name"]');
            const birthdayField = document.querySelector('input[name="birthday"]');
            
            if (firstNameField) firstNameField.addEventListener('input', generateCredentials);
            if (lastNameField) lastNameField.addEventListener('input', generateCredentials);
            if (birthdayField) birthdayField.addEventListener('input', generateCredentials);
        
        
        // Auto-dismiss success/error messages
        const alertMessages = document.querySelectorAll('.alert');
        alertMessages.forEach(alert => {
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }
            }, 3000);
        });
        
        // Specific auto-dismiss for event success messages
        const eventSuccessMessages = document.querySelectorAll('.alert-success');
        eventSuccessMessages.forEach(alert => {
            // Check if it's an event-related success message
            if (alert.textContent.includes('Event') || alert.textContent.includes('event')) {
                // Add close button if not present
                if (!alert.querySelector('.btn-close')) {
                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'btn-close';
                    closeBtn.setAttribute('aria-label', 'Close');
                    closeBtn.style.position = 'absolute';
                    closeBtn.style.right = '10px';
                    closeBtn.style.top = '50%';
                    closeBtn.style.transform = 'translateY(-50%)';
                    closeBtn.onclick = () => {
                        alert.style.transition = 'opacity 0.3s ease-out';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    };
                    alert.style.position = 'relative';
                    alert.style.paddingRight = '40px';
                    alert.appendChild(closeBtn);
                }
                
                // Auto-dismiss after 2.5 seconds
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 2500);
            }
        });
            
        });
        
        // Reports Charts Initialization
        <?php if ($section === 'reports') : ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Force chart resize to prevent infinite stretching
            function forceChartResize() {
                const charts = Chart.instances;
                Object.values(charts).forEach(chart => {
                    if (chart && chart.resize) {
                        chart.resize();
                    }
                });
            }
            
            // Resize charts after a short delay
            setTimeout(forceChartResize, 100);
            setTimeout(forceChartResize, 500);
            // Health Records Trend Chart
            const healthRecordsCtx = document.getElementById('healthRecordsChart');
            if (healthRecordsCtx) {
                const healthRecordsData = <?php echo json_encode($healthRecordsByMonth); ?>;
                const months = healthRecordsData.map(item => item.month);
                const counts = healthRecordsData.map(item => parseInt(item.count));
                
                new Chart(healthRecordsCtx, {
                    type: 'line',
                    data: {
                        labels: months,
                        datasets: [{
                            label: 'Health Records',
                            data: counts,
                            borderColor: '#047857',
                            backgroundColor: 'rgba(4, 120, 87, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 2,
                        resizeDelay: 0,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f0f0f0'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
            
            // Risk Factors Chart
            const riskFactorsCtx = document.getElementById('riskFactorsChart');
            if (riskFactorsCtx) {
                const riskData = <?php echo json_encode($riskFactors); ?>;
                const labels = Object.keys(riskData).map(key => 
                    key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
                );
                const values = Object.values(riskData);
                
                new Chart(riskFactorsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: [
                                '#dc3545',
                                '#fd7e14',
                                '#ffc107',
                                '#20c997',
                                '#6f42c1',
                                '#0dcaf0',
                                '#198754',
                                '#fd7e14',
                                '#6c757d',
                                '#0d6efd'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 1,
                        resizeDelay: 0,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    usePointStyle: true,
                                    boxWidth: 12
                                }
                            }
                        }
                    }
                });
            }
            
            // Nutrition Records Trend Chart
            const nutritionRecordsCtx = document.getElementById('nutritionRecordsChart');
            if (nutritionRecordsCtx) {
                const nutritionData = <?php echo json_encode($nutritionRecordsByMonth); ?>;
                const months = nutritionData.map(item => item.month);
                const counts = nutritionData.map(item => parseInt(item.count));
                
                new Chart(nutritionRecordsCtx, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [{
                            label: 'Nutrition Records',
                            data: counts,
                            backgroundColor: '#0d6efd',
                            borderColor: '#0d6efd',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 2,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f0f0f0'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
            
            // WFL Status Chart
            const wflStatusCtx = document.getElementById('wflStatusChart');
            if (wflStatusCtx) {
                const wflData = <?php echo json_encode($wflStatus); ?>;
                const labels = wflData.map(item => item.wfl_status);
                const counts = wflData.map(item => parseInt(item.count));
                
                new Chart(wflStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: counts,
                            backgroundColor: [
                                '#28a745',
                                '#ffc107',
                                '#dc3545',
                                '#17a2b8',
                                '#6f42c1'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 1,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }
            
            // Supplementation Chart
            const supplementationCtx = document.getElementById('supplementationChart');
            if (supplementationCtx) {
                const suppData = <?php echo json_encode($supplementationByType); ?>;
                const labels = suppData.map(item => item.supplement_type);
                const counts = suppData.map(item => parseInt(item.count));
                
                new Chart(supplementationCtx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Supplementation',
                            data: counts,
                            backgroundColor: '#6f42c1',
                            borderColor: '#6f42c1',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 2,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f0f0f0'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
            
            // Final resize to ensure proper sizing
            setTimeout(forceChartResize, 1000);
        });
        
        // Export and Refresh Functions
        window.exportReports = function() {
            // Create a simple CSV export
            const data = {
                bhw: {
                    health_records: <?php echo $bhwStats['total_health_records'] ?? 0; ?>,
                    maternal_patients: <?php echo $bhwStats['total_maternal_patients'] ?? 0; ?>,
                    immunizations: <?php echo $bhwStats['total_immunizations'] ?? 0; ?>
                },
                bns: {
                    nutrition_records: <?php echo $bnsStats['total_nutrition_records'] ?? 0; ?>,
                    children: <?php echo $bnsStats['total_children'] ?? 0; ?>,
                    supplementation: <?php echo $bnsStats['total_supplementation'] ?? 0; ?>
                }
            };
            
            const csvContent = "Report Type,Category,Value\n" +
                "BHW,Health Records," + data.bhw.health_records + "\n" +
                "BHW,Maternal Patients," + data.bhw.maternal_patients + "\n" +
                "BHW,Immunizations," + data.bhw.immunizations + "\n" +
                "BNS,Nutrition Records," + data.bns.nutrition_records + "\n" +
                "BNS,Children," + data.bns.children + "\n" +
                "BNS,Supplementation," + data.bns.supplementation;
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'barangay_reports_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        };
        
        window.refreshReports = function() {
            location.reload();
        };
        <?php endif; ?>
        
        
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- LIGHT RUNTIME BRIDGE (Optional small enhancements) -->
<script>
(function(){
    const section = "<?php echo $section; ?>";
    function onClick(id, handler){
        const el = document.getElementById(id);
        if(el) el.addEventListener('click', handler);
    }
    // BHW module hooks removed
})();
</script>
</body>
</html>