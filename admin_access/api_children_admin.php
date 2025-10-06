<?php
require_once __DIR__.'/../inc/db.php';
require_once __DIR__.'/../inc/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ob_start();

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Determine action early (default list)
    $action = $_GET['action'] ?? $_GET['list'] ?? 'list';

    // Only enforce CSRF on mutating requests (POST/PUT/DELETE) or actions that change data
    $isWrite = ($_SERVER['REQUEST_METHOD'] !== 'GET') ||
               in_array($action, ['register','update'], true);

    if ($isWrite) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $csrf_token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? '';
        if ($csrf_token === '' || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
    }

    // Auth
    require_role(['BNS', 'BHW', 'Admin']);

    switch ($action) {
        case 'list':
        case '1':
            $children = getChildrenList();
            echo json_encode(['success' => true,'children' => $children]);
            break;

        case 'get':
            $child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
            if ($child_id <= 0) throw new Exception('Child ID is required');
            $child = getChildDetails($child_id);
            echo json_encode(['success'=>true,'child'=>$child]);
            break;

        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST method required');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid JSON data');
            $result = registerNewChild($data);
            echo json_encode([
                'success'=>true,
                'message'=>'Child registered successfully',
                'child_id'=>$result['child_id'],
                'mother_id'=>$result['mother_id']
            ]);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST method required');
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Invalid JSON data');
            $updated = updateChildAndMother($data);
            $child = getChildDetails((int)$data['child_id']);
            echo json_encode(['success'=>true,'updated'=>$updated,'child'=>$child]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} finally {
    ob_end_flush();
}

/* (Rest of original functions unchanged below) */
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
                mc.full_name AS mother_name,
                mc.contact_number AS mother_contact,
                p.purok_name,
                mc.address_details,
                COALESCE(wfl.status_code, 'Not Available') AS nutrition_status,
                COALESCE(nr_latest.weighing_date, 'Never') AS last_weighing_date,
                TIMESTAMPDIFF(MONTH, c.birth_date, CURDATE()) AS current_age_months,
                TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) AS current_age_years
            FROM children c
            JOIN mothers_caregivers mc ON c.mother_id = mc.mother_id
            LEFT JOIN puroks p ON mc.purok_id = p.purok_id
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
            if (!empty($row['birth_date'])) {
                $birth = new DateTime($row['birth_date']);
                $row['birth_date_formatted'] = $birth->format('n/j/Y');
            } else {
                $row['birth_date_formatted'] = 'Not Set';
            }
            if (!empty($row['last_weighing_date']) && $row['last_weighing_date'] !== 'Never') {
                $wd = new DateTime($row['last_weighing_date']);
                $row['last_weighing_formatted'] = $wd->format('n/j/Y');
            } else {
                $row['last_weighing_formatted'] = 'Never';
            }
            $row['mother_name'] = $row['mother_name'] ?? 'Unknown';
            $row['mother_contact'] = $row['mother_contact'] ?? '';
            $row['purok_name'] = $row['purok_name'] ?? 'Not Set';
            $row['nutrition_status'] = $row['nutrition_status'] ?? 'Not Available';
            $children[] = $row;
        }
        return $children;
    } catch (Throwable $e) {
        error_log("Error in getChildrenList: ".$e->getMessage());
        return [];
    }
}

function getChildDetails($child_id) {
    global $mysqli;
    try {
        $sql = "
            SELECT 
                c.*,
                mc.full_name AS mother_name,
                mc.contact_number AS mother_contact,
                mc.emergency_contact_name,
                mc.emergency_contact_number,
                mc.date_of_birth AS mother_birth_date,
                p.purok_name,
                mc.address_details
            FROM children c
            JOIN mothers_caregivers mc ON c.mother_id = mc.mother_id
            LEFT JOIN puroks p ON mc.purok_id = p.purok_id
            WHERE c.child_id = ?
            LIMIT 1
        ";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $child_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log("Error in getChildDetails: ".$e->getMessage());
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

        $stmt = $mysqli->prepare("SELECT mother_id FROM mothers_caregivers WHERE full_name = ? AND contact_number = ? LIMIT 1");
        $stmt->bind_param('ss', $mother_data['full_name'], $mother_data['contact_number']);
        $stmt->execute();
        $stmt->bind_result($existing_mother_id);
        $mother_id = null;
        if ($stmt->fetch()) $mother_id = (int)$existing_mother_id;
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
        return ['child_id'=>$child_id,'mother_id'=>$mother_id];

    } catch (Throwable $e) {
        try { $mysqli->rollback(); } catch (Throwable $ignored) {}
        error_log("Error in registerNewChild: ".$e->getMessage());
        throw $e;
    }
}

function updateChildAndMother(array $data): bool {
    global $mysqli;
    $child_id = (int)($data['child_id'] ?? 0);
    if ($child_id <= 0) throw new Exception('Missing child_id');

    $full_name  = trim((string)($data['full_name'] ?? ''));
    $sex        = trim((string)($data['sex'] ?? ''));
    $birth_date = trim((string)($data['birth_date'] ?? ''));
    if ($full_name === '' || ($sex !== 'male' && $sex !== 'female')) throw new Exception('Invalid child name or sex');
    if ($birth_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) throw new Exception('Invalid birth date');

    $mother_name    = isset($data['mother_name'])    ? trim((string)$data['mother_name'])    : null;
    $mother_contact = isset($data['mother_contact']) ? trim((string)$data['mother_contact']) : null;
    $address        = isset($data['address_details'])? trim((string)$data['address_details']): null;
    $purok_name     = isset($data['purok_name'])     ? trim((string)$data['purok_name'])     : null;

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
        $stmt = $mysqli->prepare("UPDATE children SET full_name=?, sex=?, birth_date=?, updated_at=NOW() WHERE child_id=? LIMIT 1");
        if (!$stmt) throw new Exception('DB prepare failed (child)');
        $stmt->bind_param('sssi', $full_name, $sex, $birth_date, $child_id);
        if (!$stmt->execute()) throw new Exception('Child update failed: '.$stmt->error);
        $stmt->close();

        $sets = []; $vals=[]; $types='';
        if ($mother_name !== null)    { $sets[]='full_name=?';        $vals[]=$mother_name;    $types.='s'; }
        if ($mother_contact !== null) { $sets[]='contact_number=?';   $vals[]=$mother_contact; $types.='s'; }
        if ($address !== null)        { $sets[]='address_details=?';  $vals[]=$address;        $types.='s'; }

        if ($purok_name !== null && $purok_name !== '') {
            $current_user_id = (int)($_SESSION['user_id'] ?? 0);
            $purok_id = resolveOrCreatePurokId($purok_name, $current_user_id);
            if ($purok_id) {
                $sets[]='purok_id=?';
                $vals[]=$purok_id;
                $types.='i';
            }
        }

        if ($sets) {
            $sql = "UPDATE mothers_caregivers SET ".implode(',', $sets)." WHERE mother_id=? LIMIT 1";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) throw new Exception('DB prepare failed (mother)');
            $types.='i';
            $vals[]=$mother_id;
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
    $stmt = $mysqli->prepare("SELECT purok_id FROM puroks WHERE LOWER(purok_name)=LOWER(?) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $purok_name);
        $stmt->execute();
        $stmt->bind_result($pid);
        if ($stmt->fetch()) { $stmt->close(); return (int)$pid; }
        $stmt->close();
    }
    $barangay = 'Barangay';
    $qb = $mysqli->prepare("SELECT barangay FROM users WHERE user_id=? LIMIT 1");
    if ($qb) {
        $qb->bind_param('i', $user_id);
        $qb->execute();
        $qb->bind_result($bgy);
        if ($qb->fetch() && $bgy) $barangay = $bgy;
        $qb->close();
    }
    $ins = $mysqli->prepare("INSERT INTO puroks (purok_name, barangay) VALUES (?,?)");
    if (!$ins) return null;
    $ins->bind_param('ss', $purok_name, $barangay);
    if (!$ins->execute()) { $ins->close(); return null; }
    $newId = (int)$ins->insert_id;
    $ins->close();
    return $newId;
}