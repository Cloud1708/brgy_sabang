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
    // Verify CSRF token
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
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

function getChildrenList() {
    global $mysqli;

    try {
        // Use MAX(weighing_date) join to get latest nutrition status per child (compat with MySQL/MariaDB)
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
            // Format dates
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

            // Ensure defaults
            $row['mother_name'] = $row['mother_name'] ?? 'Unknown';
            $row['mother_contact'] = $row['mother_contact'] ?? '';
            $row['purok_name'] = $row['purok_name'] ?? 'Not Set';
            $row['nutrition_status'] = $row['nutrition_status'] ?? 'Not Available';

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

        // Check existing mother
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
            // Insert mother/caregiver
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
        if ($mysqli->errno || $mysqli->error) {
            // leave as is
        }
        try { $mysqli->rollback(); } catch (Throwable $ignored) {}
        error_log("Error in registerNewChild (mysqli): ".$e->getMessage());
        throw $e;
    }
}