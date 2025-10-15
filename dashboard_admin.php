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
$validSections = [
    'control-panel', 'accounts', 'reports', 'events',
    'health_records', 'immunization', 'maternal_patients', 'parent_accounts',
    'children_management', 'nutrition_data_entry', 'supplementation'
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

    $roles = [];
    $stmt = $mysqli->prepare("
        SELECT role_name, role_description, created_at 
        FROM roles
    ");
    if ($stmt === false) {
        $msg = "<div class='alert alert-danger'>Database error: Unable to fetch roles.</div>";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        $stmt->close();
    }

    $edit_account = null;
    if (isset($_GET['edit'])) {
        $uid = intval($_GET['edit']);
        $stmt = $mysqli->prepare("
            SELECT user_id, first_name, last_name, email, password 
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
   Data fetching for Events section
-------------------------------------------------------------------*/
if ($section === 'events') {
    $events = [];
    $stmt = $mysqli->prepare("
        SELECT event_id, event_title AS title, event_description AS `desc`, event_type AS type, event_date AS `date`, event_time AS `time`, location, event_type AS color 
        FROM events 
        ORDER BY event_date ASC
    ");
    if ($stmt === false) {
        $msg = "<div class='alert alert-danger'>Database error: Unable to fetch events.</div>";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();
    }

    $timeline = [];
    $stmt = $mysqli->prepare("
        SELECT event_title AS title, event_date AS `date`, 50 AS participants 
        FROM events 
        ORDER BY event_date ASC
    ");
    if ($stmt === false) {
        $msg = "<div class='alert alert-danger'>Database error: Unable to fetch timeline.</div>";
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $timeline[] = $row;
        }
        $stmt->close();
    }

    $edit_event = null;
    if (isset($_GET['edit'])) {
        $eid = intval($_GET['edit']);
        $stmt = $mysqli->prepare("
            SELECT event_id, event_title, event_description, event_type, event_date, event_time, location 
            FROM events 
            WHERE event_id = ?
        ");
        if ($stmt === false) {
            $msg = "<div class='alert alert-danger'>Database error: Unable to fetch event details.</div>";
        } else {
            $stmt->bind_param("i", $eid);
            $stmt->execute();
            $result = $stmt->get_result();
            $edit_event = $result->fetch_assoc();
            $stmt->close();
        }
    }
}

/* ------------------------------------------------------------------
   Data fetching for Reports section
-------------------------------------------------------------------*/
if ($section === 'reports') {
    $vaccination_coverage = ['completed' => 0, 'pending' => 0];
    $completed = (int)($mysqli->query("SELECT COUNT(DISTINCT child_id) FROM child_immunizations WHERE vaccination_date IS NOT NULL")->fetch_row()[0] ?? 0);
    $total_children = (int)($mysqli->query("SELECT COUNT(*) FROM children")->fetch_row()[0] ?? 0);
    $vaccination_coverage['completed'] = $completed;
    $vaccination_coverage['pending'] = $total_children - $completed;

    $maternal_stats = [
        'prenatal_checkups' => 0,
        'prenatal_delta' => '+5%',
        'postnatal_visits' => 0,
        'postnatal_delta' => '+3%'
    ];
    $maternal_stats['prenatal_checkups'] = (int)($mysqli->query("SELECT COUNT(*) FROM health_records")->fetch_row()[0] ?? 0);
    $maternal_stats['postnatal_visits'] = (int)($mysqli->query("SELECT COUNT(*) FROM postnatal_visits")->fetch_row()[0] ?? 0);
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
            $last_name = $mysqli->real_escape_string($_POST['last_name']);
            $email = $mysqli->real_escape_string($_POST['email']);
            $stmt = $mysqli->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ? 
                WHERE user_id = ?
            ");
            if ($stmt === false) {
                $msg = "<div class='alert alert-danger'>Database error: Unable to update account.</div>";
            } else {
                $stmt->bind_param("sssi", $first_name, $last_name, $email, $uid);
                if ($stmt->execute()) {
                    $msg = "<div class='alert alert-success'>Account updated!</div>";
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
        
        // ADD EVENT
        if ($section === 'events' && isset($_POST['add_event'])) {
            $title = $mysqli->real_escape_string($_POST['event_title']);
            $desc = $mysqli->real_escape_string($_POST['event_description']);
            $type = $mysqli->real_escape_string($_POST['event_type']);
            $date = $mysqli->real_escape_string($_POST['event_date']);
            $time = $mysqli->real_escape_string($_POST['event_time']);
            $loc = $mysqli->real_escape_string($_POST['location']);
            $created_by_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $created_by_sql = $created_by_user_id ? $created_by_user_id : 'NULL';
            $stmt = $mysqli->prepare("
                INSERT INTO events (event_title, event_description, event_type, event_date, event_time, location, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, $created_by_sql)
            ");
            if ($stmt === false) {
                $msg = "<div class='alert alert-danger'>Database error: Unable to add event.</div>";
            } else {
                $stmt->bind_param("ssssss", $title, $desc, $type, $date, $time, $loc);
                if ($stmt->execute()) {
                    $msg = "<div class='alert alert-success'>Event added!</div>";
                } else {
                    $msg = "<div class='alert alert-danger'>Failed to add event: " . $mysqli->error . "</div>";
                }
                $stmt->close();
            }
        }
        
        // EDIT EVENT
        if ($section === 'events' && isset($_POST['edit_event'])) {
            $eid = intval($_POST['event_id']);
            $title = $mysqli->real_escape_string($_POST['event_title']);
            $desc = $mysqli->real_escape_string($_POST['event_description']);
            $type = $mysqli->real_escape_string($_POST['event_type']);
            $date = $mysqli->real_escape_string($_POST['event_date']);
            $time = $mysqli->real_escape_string($_POST['event_time']);
            $loc = $mysqli->real_escape_string($_POST['location']);
            $stmt = $mysqli->prepare("
                UPDATE events 
                SET event_title = ?, event_description = ?, event_type = ?, event_date = ?, event_time = ?, location = ? 
                WHERE event_id = ?
            ");
            if ($stmt === false) {
                $msg = "<div class='alert alert-danger'>Database error: Unable to update event.</div>";
            } else {
                $stmt->bind_param("ssssssi", $title, $desc, $type, $date, $time, $loc, $eid);
                if ($stmt->execute()) {
                    $msg = "<div class='alert alert-success'>Event updated!</div>";
                } else {
                    $msg = "<div class='alert alert-danger'>Failed to update event: " . $mysqli->error . "</div>";
                }
                $stmt->close();
            }
        }
        
    }
}

// CSV EXPORT
if ($section === 'reports' && isset($_GET['action']) && $_GET['action'] == 'export_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=nutrition_report.csv');
    echo "Month,Normal,Overweight,Underweight,Severely Underweight\n";
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $nutrition_stats = [
        'Normal' => [450, 460, 455, 470, 480, 490],
        'Overweight' => [40, 45, 44, 50, 48, 47],
        'Underweight' => [120, 118, 115, 110, 112, 115],
        'Severely Underweight' => [35, 37, 38, 36, 34, 32]
    ];
    for ($i = 0; $i < count($months); $i++) {
        echo "{$months[$i]},{$nutrition_stats['Normal'][$i]},{$nutrition_stats['Overweight'][$i]},{$nutrition_stats['Underweight'][$i]},{$nutrition_stats['Severely Underweight'][$i]}\n";
    }
    exit;
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
    'reports' => 'System Reports',
    'events' => 'Event Management',
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
    'reports' => 'View health and nutrition statistics',
    'events' => 'Schedule and manage community events',
    'health_records' => 'Manage maternal health records',
    'immunization' => 'Track child immunizations',
    'maternal_patients' => 'Manage maternal patient records',
    'parent_accounts' => 'Manage parent portal accounts',
    'children_management' => 'Manage child registry & quick nutrition status',
    'nutrition_data_entry' => 'Enter and review nutrition measurements',
    'supplementation' => 'Track vitamin & supplementation records'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($titles[$section]); ?> - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .calendar-box {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
        }
        .calendar-header {
            font-weight: 600;
            font-size: 1.01em;
            margin-bottom: 0.5rem;
        }
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
        }
        .calendar-table th, .calendar-table td {
            text-align: center;
            padding: 0.5rem;
            font-size: 0.9rem;
        }
        .calendar-table td.selected {
            background: var(--green-soft);
            border-radius: 50%;
        }
        .event-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.8rem;
            margin-bottom: 0.5rem;
        }
        .timeline-list {
            list-style: none;
            padding: 0;
        }
        .timeline-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .timeline-icon {
            font-size: 1.2rem;
            color: var(--green);
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
                <h1>Barangay Sabang</h1>
                <small>Health &amp; Nutrition Management System</small>
            </div>
            <div class="nav-section">
                <small class="text-muted d-block mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0.5rem;">Admin Navigation</small>
                <ul class="nav-list">
                    <li><a class="<?php echo $section === 'control-panel' ? 'active' : ''; ?>" href="?section=control-panel"><i class="bi bi-grid"></i><span>Control Panel</span></a></li>
                    <li><a class="<?php echo $section === 'accounts' ? 'active' : ''; ?>" href="?section=accounts"><i class="bi bi-people"></i><span>Account Management</span></a></li>
                    <li><a class="<?php echo $section === 'reports' ? 'active' : ''; ?>" href="?section=reports"><i class="bi bi-file-bar-graph"></i><span>System Reports</span></a></li>
                    <li><a class="<?php echo $section === 'events' ? 'active' : ''; ?>" href="?section=events"><i class="bi bi-calendar-event"></i><span>Event Management</span></a></li>
                </ul>
            </div>
            <div class="nav-section">
                <small class="text-muted d-block mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0.5rem;">BHW Functions</small>
                <ul class="nav-list">
                    <li><a class="<?php echo $section === 'health_records' ? 'active' : ''; ?>" href="?section=health_records"><i class="bi bi-heart-pulse"></i><span>Health Records</span></a></li>
                    <li><a class="<?php echo $section === 'immunization' ? 'active' : ''; ?>" href="?section=immunization"><i class="bi bi-shield-check"></i><span>Immunization</span></a></li>
                    <li><a class="<?php echo $section === 'maternal_patients' ? 'active' : ''; ?>" href="?section=maternal_patients"><i class="bi bi-person-heart"></i><span>Maternal Patients</span></a></li>
                    <li><a class="<?php echo $section === 'parent_accounts' ? 'active' : ''; ?>" href="?section=parent_accounts"><i class="bi bi-person-badge"></i><span>Parent Accounts</span></a></li>
                </ul>
            </div>
            <div class="nav-section">
                <small class="text-muted d-block mb-2" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; padding-left: 0.5rem;">BNS Functions</small>
                <ul class="nav-list">
                    <li><a class="<?php echo $section === 'children_management' ? 'active' : ''; ?>" href="?section=children_management"><i class="bi bi-heart-pulse"></i><span>Children Management</span></a></li>
                    <li><a class="<?php echo $section === 'nutrition_data_entry' ? 'active' : ''; ?>" href="?section=nutrition_data_entry"><i class="bi bi-shield-check"></i><span>Nutrition Data Entry</span></a></li>
                    <li><a class="<?php echo $section === 'supplementation' ? 'active' : ''; ?>" href="?section=supplementation"><i class="bi bi-person-heart"></i><span>Supplementation</span></a></li>
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
                <h1 class="page-title mb-0">
                    <?php echo htmlspecialchars($titles[$section]); ?>
                </h1>
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
                        <div class="col-md-6 col-lg-4">
                            <button type="button" class="card-action w-100" onclick="exportUsers()">
                                <div class="icon"><i class="bi bi-download"></i></div>
                                <div>
                                    <div style="font-weight:600;">Export Users</div>
                                    <div style="font-size:.97em;color:#5c6872;">Download user list</div>
                                </div>
                            </button>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <button type="button" class="card-action w-100" onclick="bulkActions()">
                                <div class="icon"><i class="bi bi-gear"></i></div>
                                <div>
                                    <div style="font-weight:600;">Bulk Actions</div>
                                    <div style="font-size:.97em;color:#5c6872;">Manage multiple users</div>
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <div class="tabs-bar mb-3">
                        <button class="tab-btn <?php echo !isset($_GET['tab']) || $_GET['tab'] == 'user' ? 'active' : ''; ?>" onclick="location.href='?section=accounts&tab=user'">User Management</button>
                        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] == 'audit' ? 'active' : ''; ?>" onclick="location.href='?section=accounts&tab=audit'">Audit Log</button>
                        <button class="tab-btn <?php echo isset($_GET['tab']) && $_GET['tab'] == 'role' ? 'active' : ''; ?>" onclick="location.href='?section=accounts&tab=role'">Role Permissions</button>
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
                                                            <div style="font-size: 0.75rem; color: #6c757d;">ID: <?php echo $ac['user_id']; ?></div>
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
                                                    <div class="d-flex align-items-center gap-2">
                                                    <label class="switch">
                                                        <input type="checkbox" <?php echo $ac['is_active'] ? 'checked' : ''; ?> onchange="toggleActive(<?php echo $ac['user_id']; ?>, this.checked); window.location.reload();">
                                                        <span class="slider"></span>
                                                    </label>
                                                    <span class="status-label <?php echo $ac['is_active'] ? '' : 'inactive'; ?>">
                                                        <?php echo $ac['is_active'] ? 'active' : 'inactive'; ?>
                                                    </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a class="btn btn-outline-primary btn-sm" href="?section=accounts&edit=<?php echo $ac['user_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button class="btn btn-outline-info btn-sm" onclick="viewUserDetails(<?php echo $ac['user_id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning btn-sm" onclick="resetPassword(<?php echo $ac['user_id']; ?>)">
                                                            <i class="bi bi-key"></i>
                                                        </button>
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
                                              <label class="required">First Name</label>
                                              <input type="text" name="first_name" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['first_name']); ?>" placeholder="First Name">
                                              <label class="required">Last Name</label>
                                              <input type="text" name="last_name" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['last_name']); ?>" placeholder="Last Name">
                                              <label class="required">Email</label>
                                              <input type="email" name="email" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_account['email']); ?>" placeholder="Email">
                                              <label>Password</label>
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
                                            <th>User</th>
                                            <th>Account Type</th>
                                            <th>Created By</th>
                                            <th>Reason</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($audit_logs as $log) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($log['created_user_id']); ?></td>
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
                    <?php elseif (isset($_GET['tab']) && $_GET['tab'] == 'role') : ?>
                        <div class="panel">
                            <div class="panel-header mb-2">
                                <h6>Role Permissions</h6>
                                <p>System roles and descriptions</p>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle">
                                    <thead>
                                        <tr>
                                            <th>Role Name</th>
                                            <th>Description</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $r) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($r['role_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['role_description']); ?></td>
                                                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
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
                    <div class="reports-card mb-4">
                        <div class="reports-tools-bar">
                            <div style="font-weight:600;font-size:1.08em;">Data Export Tools</div>
                            <div class="d-flex gap-2">
                                <a href="?section=reports&action=export_csv" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
                                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
                                <button class="btn btn-success btn-sm"><i class="bi bi-bar-chart"></i> Full Report</button>
                            </div>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:1.01em;">Nutrition Status Overview</div>
                            <div style="font-size:.98em;color:#5c6872;">Monthly trends of nutritional status across the barangay</div>
                            <canvas id="nutritionChart" style="max-width:100%;max-height:385px;margin-top:.7em;"></canvas>
                            <div class="mt-2 text-center">
                                <span style="color:#047857;font-weight:600;">Normal</span>
                                <span style="color:#1aa09c;font-weight:600;margin-left:.9em;">Overweight</span>
                                <span style="color:#d41a5a;font-weight:600;margin-left:.9em;">Severely Underweight</span>
                                <span style="color:#e9b51a;font-weight:600;margin-left:.9em;">Underweight</span>
                            </div>
                        </div>
                    </div>
                    <div class="row gy-4">
                        <div class="col-md-6">
                            <div class="reports-card">
                                <div style="font-weight:600;">Vaccination Coverage Report</div>
                                <div style="color:#5c6872;">Immunization completion status</div>
                                <div class="progress mt-2" style="height:11px;border-radius:6px;">
                                    <div class="progress-bar bg-success" style="width:<?php echo $vaccination_coverage['completed']; ?>%"></div>
                                </div>
                                <div class="mt-2"><span style="font-weight:600;color:#047857;"><?php echo $vaccination_coverage['completed']; ?>%</span> completed, <span style="color:#aaa;"><?php echo $vaccination_coverage['pending']; ?>%</span> pending</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="reports-card">
                                <div style="font-weight:600;">Maternal Health Statistics</div>
                                <div style="color:#5c6872;">Prenatal and postnatal care summary</div>
                                <div class="mt-2">
                                    <div><i class="bi bi-heart-fill text-success"></i> Prenatal Checkups <span class="fw-bold ms-2"><?php echo $maternal_stats['prenatal_checkups']; ?></span> <span class="ms-2 text-success"><?php echo $maternal_stats['prenatal_delta']; ?></span></div>
                                    <div class="mt-1"><i class="bi bi-person-bounding-box text-info"></i> Postnatal Visits <span class="fw-bold ms-2"><?php echo $maternal_stats['postnatal_visits']; ?></span> <span class="ms-2 text-info"><?php echo $maternal_stats['postnatal_delta']; ?></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($section === 'events') : ?>
                    <div class="alert-reminder">
                        <strong>Reminder: Complete Vaccination Records</strong>
                        <span class="badge bg-danger ms-2">Important</span><br>
                        <span style="font-size:.97em;">Please ensure all vaccination records are updated before the end of the month.</span>
                    </div>
                    <div class="row">
                        <div class="col-lg-5">
                            <div class="calendar-box mb-4">
                                <div class="calendar-header">Community Calendar</div>
                                <div style="font-size:.98em;color:#5c6872;">Schedule of health and nutrition activities</div>
                                <div class="mt-2 mb-3">
                                    <table class="calendar-table">
                                        <thead>
                                            <tr>
                                                <th>Su</th>
                                                <th>Mo</th>
                                                <th>Tu</th>
                                                <th>We</th>
                                                <th>Th</th>
                                                <th>Fr</th>
                                                <th>Sa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $firstDay = strtotime('2025-10-01');
                                            $startWeekDay = date('w', $firstDay);
                                            $daysInMonth = 31;
                                            $week = [];
                                            $day = 1;
                                            for ($r = 0; $r < 6; $r++) {
                                                echo '<tr>';
                                                for ($c = 0; $c < 7; $c++) {
                                                    if ($r == 0 && $c < $startWeekDay) {
                                                        echo '<td></td>';
                                                    } elseif ($day > $daysInMonth) {
                                                        echo '<td></td>';
                                                    } else {
                                                        $selected = ($day == 3) ? 'selected' : '';
                                                        echo "<td class='$selected'>$day</td>";
                                                        $day++;
                                                    }
                                                }
                                                echo '</tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal"><i class="bi bi-calendar-plus"></i> New Event</button>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:1.01em;">Upcoming Events</div>
                                <?php foreach ($events as $ev) : ?>
                                    <div class="event-card mb-2">
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($ev['title']); ?></div>
                                        <div>
                                            <span class="badge bg-<?php echo htmlspecialchars($ev['color']); ?> me-2"><?php echo htmlspecialchars($ev['type']); ?></span>
                                            <i class="bi bi-calendar-event me-2"></i><?php echo htmlspecialchars($ev['date']); ?>
                                            <?php if ($ev['time']) : ?>
                                                <i class="bi bi-clock ms-2 me-1"></i><?php echo htmlspecialchars($ev['time']); ?>
                                            <?php endif; ?>
                                            <a class="btn btn-link btn-sm p-0 ms-2" href="?section=events&edit=<?php echo $ev['event_id']; ?>">Edit</a>
                                        </div>
                                        <div style="font-size:.97em;color:#5c6872;"><?php echo htmlspecialchars($ev['desc']); ?></div>
                                        <div style="font-size:.97em;color:#5c6872;"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div style="font-weight:600;font-size:1.01em;margin-bottom:.7em;">Event Timeline</div>
                            <ul class="timeline-list">
                                <?php foreach ($timeline as $te) : ?>
                                    <li>
                                        <div class="timeline-icon"><i class="bi bi-calendar-check"></i></div>
                                        <div>
                                            <div style="font-weight:600;"><?php echo htmlspecialchars($te['title']); ?></div>
                                            <div style="font-size:.98em;color:#5c6872;"><?php echo htmlspecialchars($te['date']); ?> <span class="ms-2 text-success"><?php echo $te['participants']; ?> expected participants</span></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="modal fade" id="addEventModal" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="?section=events">
                                <input type="hidden" name="add_event" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5>New Event</h5>
                                    </div>
                                    <div class="modal-body">
                                        <label class="required">Event Title</label>
                                        <input type="text" name="event_title" required class="form-control mb-2" placeholder="Event Title">
                                        <label>Description</label>
                                        <textarea name="event_description" class="form-control mb-2" placeholder="Description"></textarea>
                                        <label class="required">Event Type</label>
                                        <select name="event_type" class="form-control mb-2" required>
                                            <option value="vaccination">Vaccination</option>
                                            <option value="nutrition">Nutrition</option>
                                            <option value="education">Education</option>
                                            <option value="maternal">Maternal</option>
                                            <option value="supplementation">Supplementation</option>
                                            <option value="other">Other</option>
                                        </select>
                                        <label class="required">Event Date</label>
                                        <input type="date" name="event_date" required class="form-control mb-2">
                                        <label>Event Time</label>
                                        <input type="time" name="event_time" class="form-control mb-2">
                                        <label>Location</label>
                                        <input type="text" name="location" class="form-control mb-2" placeholder="Location">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Add Event</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($edit_event) : ?>
                        <div class="modal fade show" id="editEventModal" tabindex="-1" style="display:block" aria-modal="true" role="dialog">
                            <div class="modal-dialog">
                                <form method="POST" action="?section=events">
                                    <input type="hidden" name="edit_event" value="1">
                                    <input type="hidden" name="event_id" value="<?php echo $edit_event['event_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5>Edit Event</h5>
                                        </div>
                                        <div class="modal-body">
                                            <label class="required">Event Title</label>
                                            <input type="text" name="event_title" required class="form-control mb-2" value="<?php echo htmlspecialchars($edit_event['event_title']); ?>" placeholder="Event Title">
                                            <label>Description</label>
                                            <textarea name="event_description" class="form-control mb-2" placeholder="Description"><?php echo htmlspecialchars($edit_event['event_description']); ?></textarea>
                                            <label class="required">Event Type</label>
                                            <select name="event_type" class="form-control mb-2" required>
                                                <?php
                                                $types = ['vaccination', 'nutrition', 'education', 'maternal', 'supplementation', 'other'];
                                                foreach ($types as $type) {
                                                    echo "<option value='$type'" . ($type == $edit_event['event_type'] ? ' selected' : '') . ">$type</option>";
                                                }
                                                ?>
                                            </select>
                                            <label class="required">Event Date</label>
                                            <input type="date" name="event_date" required class="form-control mb-2" value="<?php echo htmlspecialchars($edit_event['event_date']); ?>">
                                            <label>Event Time</label>
                                            <input type="time" name="event_time" class="form-control mb-2" value="<?php echo htmlspecialchars($edit_event['event_time']); ?>">
                                            <label>Location</label>
                                            <input type="text" name="location" class="form-control mb-2" placeholder="Location" value="<?php echo htmlspecialchars($edit_event['location']); ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <a href="?section=events" class="btn btn-outline-secondary">Cancel</a>
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
                    
                <?php elseif ($section === 'health_records' || $section === 'immunization' || 
                              $section === 'maternal_patients' || $section === 'parent_accounts' ||
                              $section === 'children_management' || $section === 'nutrition_data_entry' ||
                              $section === 'supplementation') : ?>

                    <div class="panel" id="dynamicSectionPanel">
                        <div class="panel-header mb-3">
                            <h6><?php echo htmlspecialchars($titles[$section]); ?></h6>
                            <p>Dynamic management interface (loaded via JavaScript)</p>
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
                            <div class="col-md-3 col-sm-6">
                                <div class="card-action" onclick="location.href='?section=parent_accounts'">
                                    <div class="icon"><i class="bi bi-people"></i></div>
                                    <div>
                                        <div style="font-weight:600;">Parent Portal</div>
                                        <div style="font-size:.85em;color:#5c6872;">Manage parent accounts</div>
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

        <?php if ($section === 'health_records'): ?>
            <script src="admin_access/health_records.js"></script>
        <?php endif; ?>
        <?php if ($section === 'immunization'): ?>
            <script src="admin_access/immunization.js"></script>
        <?php endif; ?>
        <?php if ($section === 'maternal_patients'): ?>
            <script src="admin_access/maternal_patients.js"></script>
        <?php endif; ?>
        <?php if ($section === 'parent_accounts'): ?>
            <script src="admin_access/parent_accounts.js"></script>
        <?php endif; ?>
        <?php if ($section === 'children_management'): ?>
            <script src="admin_access/children_management.js"></script>
        <?php endif; ?>
        <?php if ($section === 'nutrition_data_entry'): ?>
            <script src="admin_access/nutrition_data_entry.js"></script>
        <?php endif; ?>
        <?php if ($section === 'supplementation'): ?>
            <script src="admin_access/supplementation.js"></script>
        <?php endif; ?>
            
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
        
        window.viewUserDetails = function(userId) {
            alert('User details view for ID: ' + userId);
        };
        
        window.resetPassword = function(userId) {
            if (confirm('Are you sure you want to reset the password for this user?')) {
                alert('Password reset functionality will be implemented for ID: ' + userId);
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
        
        function toggleActive(userId, isChecked) {
            fetch('?section=accounts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `toggle_active=1&user_id=${userId}&is_active=${isChecked ? 1 : 0}&csrf_token=${window.__ADMIN_CSRF}`
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) {
                        alert('Failed to update status: ' + (data.error || 'Unknown error'));
                        location.reload();
                    }
                })
                .catch(() => {
                    alert('Error updating status');
                    location.reload();
                });
        }
        
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
            
            // Nutrition chart
            const ctx = document.getElementById('nutritionChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [
                            {
                                label: 'Normal',
                                data: [450, 460, 455, 470, 480, 490],
                                borderColor: '#047857',
                                backgroundColor: '#047857',
                                fill: false
                            },
                            {
                                label: 'Overweight',
                                data: [40, 45, 44, 50, 48, 47],
                                borderColor: '#1aa09c',
                                backgroundColor: '#1aa09c',
                                fill: false
                            },
                            {
                                label: 'Underweight',
                                data: [120, 118, 115, 110, 112, 115],
                                borderColor: '#e9b51a',
                                backgroundColor: '#e9b51a',
                                fill: false
                            },
                            {
                                label: 'Severely Underweight',
                                data: [35, 37, 38, 36, 34, 32],
                                borderColor: '#d41a5a',
                                backgroundColor: '#d41a5a',
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- LIGHT RUNTIME BRIDGE (Optional small enhancements) -->
<script>
(function(){
    const section = "<?php echo $section; ?>";
    function onClick(id, handler){
        const el = document.getElementById(id);
        if(el) el.addEventListener('click', handler);
    }
    if(section==='health_records'){
        onClick('hrQuickAdd', ()=>{ if(window.HealthRecordsApp) HealthRecordsApp.showAddRecordModal(); });
        onClick('hrQuickAll', ()=>{ if(window.HealthRecordsApp) HealthRecordsApp.loadAllRecords(); });
        onClick('hrQuickRisk', ()=>{ if(window.HealthRecordsApp) HealthRecordsApp.loadRiskSummary(); });
    }
    if(section==='immunization'){
        onClick('immuQuickAddDose', ()=>{ if(window.ImmunizationApp) ImmunizationApp.openAddImmunizationModal(); });
        onClick('immuQuickChildren', ()=>{ if(window.ImmunizationApp){ ImmunizationApp.state.tab='children'; ImmunizationApp.refreshCurrentTab(); }});
        onClick('immuQuickOverdue', ()=>{ if(window.ImmunizationApp){ ImmunizationApp.state.tab='overdue'; ImmunizationApp.refreshCurrentTab(); }});
        onClick('immuQuickVaccines', ()=>{ if(window.ImmunizationApp){ ImmunizationApp.state.tab='vaccines'; ImmunizationApp.refreshCurrentTab(); }});
    }
    if(section==='maternal_patients'){
        onClick('mpQuickAdd', ()=>{ if(window.MaternalPatientsApp) MaternalPatientsApp.showAddModal(); });
        onClick('mpQuickList', ()=>{ if(window.MaternalPatientsApp) MaternalPatientsApp.loadList(); });
        onClick('mpQuickSearch', ()=>{ 
            const s=prompt('Enter search term (name/purok):','');
            if(s!==null && window.MaternalPatientsApp){
                const f=document.getElementById('mpSearch');
                if(f) f.value=s;
                MaternalPatientsApp.state.search=s;
                MaternalPatientsApp.loadList();
            }
        });
    }
    if(section==='parent_accounts'){
        onClick('paQuickCreate', ()=>{ if(window.ParentAccountsApp) ParentAccountsApp.showCreateModal(); });
        onClick('paQuickList', ()=>{ if(window.ParentAccountsApp){ ParentAccountsApp.state.tab='parents'; ParentAccountsApp.loadTab(); } });
        onClick('paQuickActivity', ()=>{ if(window.ParentAccountsApp){ ParentAccountsApp.state.tab='activity'; ParentAccountsApp.loadTab(); } });
        onClick('paQuickLink', ()=>{ if(window.ParentAccountsApp){ alert('Use Link Child inside a parent row.'); } });
    }
})();
</script>
</body>
</html>