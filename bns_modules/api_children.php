<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start output buffering to prevent any HTML output
ob_start();

try {
    // Verify CSRF token (UI always sends window.__BNS_CSRF)
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $csrf_token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? '';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    // Check authentication and role
    require_role(['BNS', 'BHW', 'Admin']);

    $action = $_GET['action'] ?? $_GET['list'] ?? 'list';

    switch ($action) {
        case 'list':
        case '1':
            $children = getChildrenList();
            echo json_encode([
                'success' => true,
                'children' => $children
            ]);
            break;

        case 'get':
            $child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
            if ($child_id <= 0) {
                throw new Exception('Child ID is required');
            }
            $child = getChildDetails($child_id);
            echo json_encode([
                'success' => true,
                'child' => $child
            ]);
            break;

        case 'register':
            assert_method('POST');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data');
            }
            $result = registerNewChild($data);
            echo json_encode([
                'success' => true,
                'message' => 'Child registered successfully',
                'child_id' => $result['child_id'],
                'mother_id' => $result['mother_id']
            ]);
            break;

        case 'update':
            assert_method('POST');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON data');
            }
            $updated = updateChildAndMother($data);
            $child = getChildDetails((int)$data['child_id']);

            echo json_encode([
                'success' => true,
                'updated' => $updated,
                'child' => $child
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    // Clear any output that might have been generated
    ob_clean();

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    ob_end_flush();
}

function assert_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        throw new Exception("$method method required");
    }
}

/*
 Consistent sa UI:
 - Field names: mother_name, mother_contact, purok_name, address_details, last_weighing_formatted,
   birth_date_formatted, current_age_months/years, nutrition_status
 - Address format: "<house_number> <StreetName> Street, <Subdivision>"
   (Ang "Street" ay idinadagdag kung wala pa; hindi sinasama ang Purok dito.)
 - Fallback chain:
   1) maternal_patients (house/street/subdivision)
   2) mothers_caregivers (house/street/subdivision)
   3) mothers_caregivers.address_details (free text)
 - UI normalization: title case + clean spacing/commas
*/

/* Same thresholds used in api_nutrition.php provisional_classify() */
function provisional_classify_from_wh(float $weightKg, float $lengthCm): ?string {
    if ($weightKg <= 0 || $lengthCm <= 0) return null;
    $m = $lengthCm / 100;
    if ($m <= 0) return null;
    $bmi = $weightKg / ($m*$m);
    if ($bmi < 12.5) return 'SAM';
    if ($bmi < 13.0) return 'MAM';
    if ($bmi < 13.5) return 'UW';
    if ($bmi < 17.5) return 'NOR';
    if ($bmi < 19.0) return 'OW';
    return 'OB';
}

function getChildrenList() {
    global $mysqli;

    try {
        $sql = "
            SELECT 
                c.child_id,
                c.full_name,
                c.sex,
                c.birth_date,
                c.created_at,

                -- add raw anthropometrics for fallback classification
                c.weight_kg,
                c.height_cm,
                c.updated_at AS child_updated_at,

                -- Mother (prefer maternal_patients)
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', mp.first_name, mp.middle_name, mp.last_name)), ''),
                    mc.full_name
                ) AS mother_name,
                COALESCE(mp.contact_number, mc.contact_number) AS mother_contact,

                -- Purok (prefer maternal_patients.purok_id)
                COALESCE(p_mp.purok_name, p_mc.purok_name) AS purok_name,

                -- Address composed (adds 'Street' if missing)
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(
                        ', ',
                        NULLIF(TRIM(CONCAT_WS(
                            ' ',
                            NULLIF(TRIM(mp.house_number), ''),
                            NULLIF(TRIM(
                                CASE
                                  WHEN mp.street_name IS NULL OR TRIM(mp.street_name) = '' THEN ''
                                  WHEN LOWER(TRIM(mp.street_name)) REGEXP '( street$| st\\.?$)' THEN TRIM(mp.street_name)
                                  ELSE CONCAT(TRIM(mp.street_name), ' Street')
                                END
                            ), '')
                        )), ''),
                        NULLIF(TRIM(mp.subdivision_name), '')
                    )), ''),
                    NULLIF(TRIM(CONCAT_WS(
                        ', ',
                        NULLIF(TRIM(CONCAT_WS(
                            ' ',
                            NULLIF(TRIM(mc.house_number), ''),
                            NULLIF(TRIM(
                                CASE
                                  WHEN mc.street_name IS NULL OR TRIM(mc.street_name) = '' THEN ''
                                  WHEN LOWER(TRIM(mc.street_name)) REGEXP '( street$| st\\.?$)' THEN TRIM(mc.street_name)
                                  ELSE CONCAT(TRIM(mc.street_name), ' Street')
                                END
                            ), '')
                        )), ''),
                        NULLIF(TRIM(mc.subdivision_name), '')
                    )), ''),
                    mc.address_details
                ) AS address_details,

                COALESCE(wfl.status_code, 'Not Available') AS nutrition_status,
                COALESCE(nr_latest.weighing_date, DATE(c.created_at)) AS last_weighing_date,

                TIMESTAMPDIFF(MONTH, c.birth_date, CURDATE()) AS current_age_months,
                TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) AS current_age_years
            FROM children c
            JOIN mothers_caregivers mc ON c.mother_id = mc.mother_id
            LEFT JOIN puroks p_mc ON p_mc.purok_id = mc.purok_id

            LEFT JOIN maternal_patients mp ON mp.mother_id = c.mother_id
            LEFT JOIN puroks p_mp ON p_mp.purok_id = mp.purok_id

            -- latest nutrition status per child
            LEFT JOIN (
                SELECT nr1.*
                FROM nutrition_records nr1
                JOIN (
                    SELECT child_id, MAX(weighing_date) AS max_date
                    FROM nutrition_records
                    GROUP BY child_id
                ) mx ON mx.child_id = nr1.child_id AND mx.max_date = nr1.weighing_date
            ) nr_latest ON nr_latest.child_id = c.child_id
            LEFT JOIN wfl_ht_status_types wfl ON wfl.status_id = nr_latest.wfl_ht_status_id

            ORDER BY c.created_at DESC
        ";

        $res = $mysqli->query($sql);
        $children = [];

        while ($row = $res->fetch_assoc()) {
            // Format dates for UI
            if (!empty($row['birth_date'])) {
                $birth = new DateTime($row['birth_date']);
                $row['birth_date_formatted'] = $birth->format('Y-m-d');
            } else {
                $row['birth_date_formatted'] = 'Not Set';
            }

            // Default Last Weighing formatted from nutrition record (if any)
            if (!empty($row['last_weighing_date']) && $row['last_weighing_date'] !== 'Never') {
                try {
                    $wd = new DateTime($row['last_weighing_date']);
                    $row['last_weighing_formatted'] = $wd->format('n/j/Y');
                } catch (Throwable $e) {
                    $row['last_weighing_formatted'] = !empty($row['created_at']) ? (new DateTime(substr($row['created_at'],0,10)))->format('n/j/Y') : 'Never';
                }
            } else {
                $row['last_weighing_formatted'] = !empty($row['created_at']) ? (new DateTime(substr($row['created_at'],0,10)))->format('n/j/Y') : 'Never';
            }

            // Fallback nutrition status using children's weight/height (only when Not Available)
            $row['nutrition_status'] = $row['nutrition_status'] ?? 'Not Available';
            if ($row['nutrition_status'] === 'Not Available') {
                $w = isset($row['weight_kg']) ? (float)$row['weight_kg'] : 0;
                $h = isset($row['height_cm']) ? (float)$row['height_cm'] : 0;
                if ($w > 0 && $h > 0) {
                    $code = provisional_classify_from_wh($w, $h);
                    if ($code) {
                        // Use computed code so UI badges show proper color
                        $row['nutrition_status'] = $code;

                        // Also set a sensible Last Weighing date fallback if we still have 'Never'
                        if ($row['last_weighing_formatted'] === 'Never' && !empty($row['child_updated_at'])) {
                            try {
                                $ud = new DateTime(substr($row['child_updated_at'], 0, 10));
                                $row['last_weighing_formatted'] = $ud->format('n/j/Y');
                            } catch (Throwable $e) {
                                // keep 'Never' if parse fails
                            }
                        }
                    }
                }
            }

            // UI defaults
            $row['mother_name'] = $row['mother_name'] ?: 'Unknown';
            $row['mother_contact'] = $row['mother_contact'] ?? '';
            $row['purok_name'] = $row['purok_name'] ?? 'Not Set';
            $row['nutrition_status'] = $row['nutrition_status'] ?? 'Not Available';

            // Normalize address for consistent UI
            $row['address_details'] = normalize_address_for_ui($row['address_details'] ?? '');

            // Remove internal fields not needed by UI payload
            unset($row['weight_kg'], $row['height_cm'], $row['child_updated_at'], $row['birth_date'], $row['created_at'], $row['last_weighing_date']);

            $children[] = $row;
        }

        return $children;

    } catch (Throwable $e) {
        error_log("Error in getChildrenList (mysqli): ".$e->getMessage());
        return [];
    }
}

function getChildDetails($child_id) {
    global $mysqli;

    try {
        $sql = "
            SELECT 
                c.*,

                -- Preferred mother full name and contact
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', mp.first_name, mp.middle_name, mp.last_name)), ''),
                    mc.full_name
                ) AS mother_name,
                COALESCE(mp.contact_number, mc.contact_number) AS mother_contact,

                mp.first_name  AS mother_first_name,
                mp.middle_name AS mother_middle_name,
                mp.last_name   AS mother_last_name,

                mp.emergency_contact_name,
                mp.emergency_contact_number,

                -- Purok (hiwalay na field)
                COALESCE(p_mp.purok_name, p_mc.purok_name) AS purok_name,

                -- Address composed (adds 'Street' if missing)
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(
                        ', ',
                        NULLIF(TRIM(CONCAT_WS(
                            ' ',
                            NULLIF(TRIM(mp.house_number), ''),
                            NULLIF(TRIM(
                                CASE
                                  WHEN mp.street_name IS NULL OR TRIM(mp.street_name) = '' THEN ''
                                  WHEN LOWER(TRIM(mp.street_name)) REGEXP '( street$| st\\.?$)' THEN TRIM(mp.street_name)
                                  ELSE CONCAT(TRIM(mp.street_name), ' Street')
                                END
                            ), '')
                        )), ''),
                        NULLIF(TRIM(mp.subdivision_name), '')
                    )), ''),
                    NULLIF(TRIM(CONCAT_WS(
                        ', ',
                        NULLIF(TRIM(CONCAT_WS(
                            ' ',
                            NULLIF(TRIM(mc.house_number), ''),
                            NULLIF(TRIM(
                                CASE
                                  WHEN mc.street_name IS NULL OR TRIM(mc.street_name) = '' THEN ''
                                  WHEN LOWER(TRIM(mc.street_name)) REGEXP '( street$| st\\.?$)' THEN TRIM(mc.street_name)
                                  ELSE CONCAT(TRIM(mc.street_name), ' Street')
                                END
                            ), '')
                        )), ''),
                        NULLIF(TRIM(mc.subdivision_name), '')
                    )), ''),
                    mc.address_details
                ) AS address_details,

                mp.date_of_birth AS mother_birth_date
            FROM children c
            JOIN mothers_caregivers mc ON c.mother_id = mc.mother_id
            LEFT JOIN puroks p_mc ON p_mc.purok_id = mc.purok_id

            LEFT JOIN maternal_patients mp ON mp.mother_id = c.mother_id
            LEFT JOIN puroks p_mp ON p_mp.purok_id = mp.purok_id

            WHERE c.child_id = ?
            LIMIT 1
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $child_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            // Fallbacks
            $row['mother_name'] = $row['mother_name'] ?: (
                ($row['mother_first_name'] || $row['mother_last_name'])
                    ? trim(($row['mother_first_name'] ?? '').' '.($row['mother_middle_name'] ?? '').' '.($row['mother_last_name'] ?? ''))
                    : 'Unknown'
            );
            $row['purok_name'] = $row['purok_name'] ?? 'Not Set';
            $row['address_details'] = normalize_address_for_ui($row['address_details'] ?? '');
        }

        return $row ?: null;

    } catch (Throwable $e) {
        error_log("Error in getChildDetails (mysqli): ".$e->getMessage());
        return null;
    }
}

function registerNewChild($data) {
    global $mysqli;

    try {
        $child_data  = $data['child'] ?? [];
        $mother_data = $data['mother_caregiver'] ?? [];

        if (empty($child_data['full_name']) || empty($child_data['sex']) || empty($child_data['birth_date'])) {
            throw new Exception('Child information is incomplete');
        }

        if (empty($mother_data['full_name']) || empty($mother_data['contact_number']) || empty($mother_data['purok_id'])) {
            throw new Exception('Mother/caregiver information is incomplete');
        }

        $current_user_id = (int)($_SESSION['user_id'] ?? 1);

        $mysqli->begin_transaction();

        // Find or create mother
        $stmt = $mysqli->prepare("SELECT mother_id FROM mothers_caregivers WHERE full_name = ? AND contact_number = ? LIMIT 1");
        $stmt->bind_param('ss', $mother_data['full_name'], $mother_data['contact_number']);
        $stmt->execute();
        $stmt->bind_result($existing_mother_id);
        $mother_id = null;
        if ($stmt->fetch()) {
            $mother_id = (int)$existing_mother_id;
        }
        $stmt->close();

        if (!$mother_id) {
            $sql = "INSERT INTO mothers_caregivers
                    (full_name, date_of_birth, emergency_contact_name, emergency_contact_number,
                     purok_id, address_details, contact_number, created_by, user_account_id)
                    VALUES (?,?,?,?,?,?,?,?,?)";
            $stmt = $mysqli->prepare($sql);
            $dob = $mother_data['date_of_birth'] ?? null;
            $ecn = $mother_data['emergency_contact_name'] ?? null;
            $ecn_num = $mother_data['emergency_contact_number'] ?? null;
            $purok_id = (int)$mother_data['purok_id'];
            $addr = $mother_data['address_details'] ?? '';
            $contact = $mother_data['contact_number'];

            $stmt->bind_param(
                'ssssissii',
                $mother_data['full_name'],
                $dob,
                $ecn,
                $ecn_num,
                $purok_id,
                $addr,
                $contact,
                $current_user_id,
                $current_user_id
            );
            $stmt->execute();
            $mother_id = (int)$mysqli->insert_id;
            $stmt->close();
        }

        // Insert child
        $sql = "INSERT INTO children (full_name, sex, birth_date, mother_id, created_by)
                VALUES (?,?,?,?,?)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param(
            'sssii',
            $child_data['full_name'],
            $child_data['sex'],
            $child_data['birth_date'],
            $mother_id,
            $current_user_id
        );
        $stmt->execute();
        $child_id = (int)$mysqli->insert_id;
        $stmt->close();

        $mysqli->commit();

        return [
            'child_id'  => $child_id,
            'mother_id' => $mother_id
        ];

    } catch (Throwable $e) {
        try { $mysqli->rollback(); } catch (Throwable $ignored) {}
        error_log("Error in registerNewChild (mysqli): ".$e->getMessage());
        throw $e;
    }
}

/* Update helper (used by Edit modal) */
function updateChildAndMother(array $data): bool {
    global $mysqli;

    $child_id = (int)($data['child_id'] ?? 0);
    if ($child_id <= 0) {
        throw new Exception('Missing child_id');
    }

    // Validate child fields
    $full_name  = trim((string)($data['full_name'] ?? ''));
    $sex        = trim((string)($data['sex'] ?? ''));
    $birth_date = trim((string)($data['birth_date'] ?? ''));

    if ($full_name === '' || ($sex !== 'male' && $sex !== 'female')) {
        throw new Exception('Invalid child name or sex');
    }
    if ($birth_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        throw new Exception('Invalid birth date');
    }

    // Optional mother fields (edit)
    $mother_name    = isset($data['mother_name'])    ? trim((string)$data['mother_name'])    : null;
    $mother_contact = isset($data['mother_contact']) ? trim((string)$data['mother_contact']) : null;
    $address        = isset($data['address_details'])? trim((string)$data['address_details']): null;
    $purok_name     = isset($data['purok_name'])     ? trim((string)$data['purok_name'])     : null;

    // Get mother_id via child
    $stmt = $mysqli->prepare("SELECT mother_id FROM children WHERE child_id=? LIMIT 1");
    if (!$stmt) throw new Exception('DB prepare failed');
    $stmt->bind_param('i', $child_id);
    $stmt->execute();
    $stmt->bind_result($mother_id);
    if (!$stmt->fetch()) {
        $stmt->close();
        throw new Exception('Child not found');
    }
    $stmt->close();

    $mysqli->begin_transaction();

    try {
        // Update child
        $stmt = $mysqli->prepare("UPDATE children SET full_name=?, sex=?, birth_date=?, updated_at=NOW() WHERE child_id=? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (child)');
        $stmt->bind_param('sssi', $full_name, $sex, $birth_date, $child_id);
        if (!$stmt->execute()) throw new Exception('Child update failed: '.$stmt->error);
        $stmt->close();

        // Update mother (mothers_caregivers) for UI consistency
        $sets = [];
        $vals = [];
        $types = '';

        if ($mother_name !== null)    { $sets[] = 'full_name = ?';       $vals[] = $mother_name;    $types.='s'; }
        if ($mother_contact !== null) { $sets[] = 'contact_number = ?';  $vals[] = $mother_contact; $types.='s'; }
        if ($address !== null)        { $sets[] = 'address_details = ?'; $vals[] = $address;        $types.='s'; }

        // Resolve or create purok if name provided
        if ($purok_name !== null && $purok_name !== '') {
            $current_user_id = (int)($_SESSION['user_id'] ?? 0);
            $purok_id = resolveOrCreatePurokId($purok_name, $current_user_id);
            if ($purok_id) {
                $sets[] = 'purok_id = ?';
                $vals[] = $purok_id;
                $types .= 'i';
            }
        }

        if ($sets) {
            $sql = "UPDATE mothers_caregivers SET ".implode(',', $sets)." WHERE mother_id=? LIMIT 1";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception('DB prepare failed (mother)');
            $types .= 'i';
            $vals[] = $mother_id;
            $stmt->bind_param($types, ...$vals);
            if (!$stmt->execute()) throw new Exception('Mother update failed: '.$stmt->error);
            $stmt->close();
        }

        $mysqli->commit();
        return true;

    } catch (Throwable $e) {
        try { $mysqli->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    }
}

function resolveOrCreatePurokId(string $purok_name, int $user_id): ?int {
    global $mysqli;
    if ($purok_name === '') return null;

    // Try find existing purok (case-insensitive)
    $stmt = $mysqli->prepare("SELECT purok_id FROM puroks WHERE LOWER(purok_name)=LOWER(?) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $purok_name);
        $stmt->execute();
        $stmt->bind_result($pid);
        if ($stmt->fetch()) {
            $stmt->close();
            return (int)$pid;
        }
        $stmt->close();
    }

    // Determine barangay from current user
    $barangay = 'Barangay';
    $qb = $mysqli->prepare("SELECT barangay FROM users WHERE user_id=? LIMIT 1");
    if ($qb) {
        $qb->bind_param('i', $user_id);
        $qb->execute();
        $qb->bind_result($bgy);
        if ($qb->fetch() && $bgy) $barangay = $bgy;
        $qb->close();
    }

    // Create new purok
    $ins = $mysqli->prepare("INSERT INTO puroks (purok_name, barangay) VALUES (?,?)");
    if (!$ins) return null;
    $ins->bind_param('ss', $purok_name, $barangay);
    if (!$ins->execute()) { $ins->close(); return null; }
    $newId = (int)$ins->insert_id;
    $ins->close();
    return $newId;
}

/* --- Helpers --- */

/**
 * Normalize address string for consistent UI:
 * - Trim and collapse spaces
 * - Ensure comma+space separators
 * - Title case words (keeps numbers)
 * - Standardize 'St'/'St.' -> 'Street'
 */
function normalize_address_for_ui(?string $s): string {
    $t = trim((string)$s);
    if ($t === '') return '—';

    // Normalize commas and spaces
    $t = preg_replace('/\s+/', ' ', $t);
    $t = preg_replace('/\s*,\s*/', ', ', $t);
    $t = preg_replace('/,\s*,+/', ', ', $t);

    // Standardize common abbreviations at end of street tokens
    // (lightweight; avoids over-aggressive replacements)
    $t = preg_replace('/\bSt\.?\b/i', 'Street', $t);

    // Title case (but keep ALL-CAPS acronyms as-is is complex; simple approach is ok for addresses)
    $t = ucwords(mb_strtolower($t, 'UTF-8'));

    // Final tidy: remove duplicate spaces
    $t = preg_replace('/\s+/', ' ', $t);

    return $t;
}