<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Clocking Action Controller (Web Dashboard, Mobile & Gate Tap In Terminal)
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
          (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
          isset($_POST['is_ajax']) || isset($_GET['is_ajax']);

// Set JSON header if AJAX
if ($isAjax) {
    header('Content-Type: application/json');
}

$db = get_db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$redirectUrl = $_POST['redirect'] ?? base_url('index.php');

// Special Terminal ID Tap Action (Hardware/Terminal Gateway can be called with staff_id or rfid_card_id)
if ($action === 'terminal_tap') {
    $identifier = trim($_POST['identifier'] ?? '');
    $gate = trim($_POST['gate'] ?? 'Main Gate - Church Rd');

    if (empty($identifier)) {
        echo json_encode(['success' => false, 'message' => 'Please swipe RFID Card or enter Staff ID.']);
        exit;
    }

    try {
        // Find user by staff_id, rfid_card_id, or nrc_number
        $stmt = $db->prepare("
            SELECT u.*, d.name AS department_name, s.name AS school_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN schools s ON d.school_id = s.id
            WHERE LOWER(u.staff_id) = LOWER(:id) 
               OR LOWER(u.rfid_card_id) = LOWER(:id)
               OR LOWER(u.nrc_number) = LOWER(:id)
        ");
        $stmt->execute(['id' => $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => "Unrecognized ID Card or Staff ID: '{$identifier}'"]);
            exit;
        }

        if ($user['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => "Access Denied: Staff account is {$user['status']}."]);
            exit;
        }

        // Check if currently clocked in
        $status = get_user_current_clocking_status($user['id']);

        if (!$status['is_clocked_in']) {
            // CLOCK IN AT GATE
            $stmtIn = $db->prepare("
                INSERT INTO clocking_records (user_id, clock_date, clock_in, clock_in_method, entry_gate, premise_verified, ip_address, status)
                VALUES (:uid, CURRENT_DATE, CURRENT_TIMESTAMP, 'id_tap_gate', :gate, TRUE, :ip, 'in_progress')
                RETURNING id, clock_in
            ");
            $stmtIn->execute([
                'uid'  => $user['id'],
                'gate' => $gate,
                'ip'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            $newRec = $stmtIn->fetch();

            log_audit_trail('CLOCK_IN_GATE', 'clocking_records', $newRec['id'], "Staff {$user['full_name']} tapped IN at {$gate}.", $user['id']);
            send_notification($user['id'], 'Gate Tap-In Recorded', "You clocked in at {$gate} on " . date('H:i'), 'success');

            echo json_encode([
                'success'      => true,
                'type'         => 'clock_in',
                'user'         => [
                    'name'         => $user['full_name'],
                    'staff_id'     => $user['staff_id'],
                    'role'         => ucfirst($user['role']),
                    'category'     => str_replace('_', ' ', ucfirst($user['employment_type'])),
                    'department'   => $user['department_name'] ?? 'General Staff',
                    'avatar'       => $user['avatar']
                ],
                'gate'         => $gate,
                'time'         => date('H:i:s'),
                'message'      => "Welcome to Evelyn Hone College, {$user['full_name']}! Clocked IN successfully."
            ]);
            exit;
        } else {
            // CLOCK OUT AT GATE
            $recordId = $status['current_record']['id'];
            $clockInTime = strtotime($status['current_record']['clock_in']);
            $now = time();
            $totalHours = max(0.01, round(($now - $clockInTime) / 3600, 2));

            $stmtOut = $db->prepare("
                UPDATE clocking_records 
                SET clock_out = CURRENT_TIMESTAMP,
                    clock_out_method = 'id_tap_gate',
                    total_hours = :hours,
                    status = 'completed'
                WHERE id = :rid
            ");
            $stmtOut->execute([
                'hours' => $totalHours,
                'rid'   => $recordId
            ]);

            log_audit_trail('CLOCK_OUT_GATE', 'clocking_records', $recordId, "Staff {$user['full_name']} tapped OUT at {$gate}. Total duration: {$totalHours} hrs.", $user['id']);
            send_notification($user['id'], 'Gate Tap-Out Recorded', "You clocked out at {$gate}. Total recorded: {$totalHours} hours.", 'info');

            echo json_encode([
                'success'      => true,
                'type'         => 'clock_out',
                'user'         => [
                    'name'         => $user['full_name'],
                    'staff_id'     => $user['staff_id'],
                    'role'         => ucfirst($user['role']),
                    'category'     => str_replace('_', ' ', ucfirst($user['employment_type'])),
                    'department'   => $user['department_name'] ?? 'General Staff',
                    'avatar'       => $user['avatar']
                ],
                'gate'         => $gate,
                'hours'        => $totalHours,
                'time'         => date('H:i:s'),
                'message'      => "Goodbye {$user['full_name']}! Clocked OUT successfully. Total session: {$totalHours} hours."
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Terminal Tap Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error processing ID tap: ' . $e->getMessage()]);
        exit;
    }
}

// For Dashboard Clock-In & Clock-Out: Authenticated user required
$user = require_login();

// Verify CSRF Token for standard form submissions
if (!$isAjax && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('danger', 'Security validation failed (Invalid CSRF Token). Please try again.');
    header('Location: ' . $redirectUrl);
    exit;
}

if ($action === 'clock_in') {
    $status = get_user_current_clocking_status($user['id']);
    if ($status['is_clocked_in']) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'You are already clocked in!']);
            exit;
        }
        set_flash_message('warning', 'You are already clocked in for this shift.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $gate = sanitize_input($_POST['entry_gate'] ?? 'Web Portal / Campus Premises');
    $notes = sanitize_input($_POST['notes'] ?? 'Clocked in via Staff Web Portal');

    try {
        $stmt = $db->prepare("
            INSERT INTO clocking_records (user_id, clock_date, clock_in, clock_in_method, entry_gate, premise_verified, ip_address, notes, status)
            VALUES (:uid, CURRENT_DATE, CURRENT_TIMESTAMP, 'web_dashboard', :gate, TRUE, :ip, :notes, 'in_progress')
            RETURNING id, clock_in
        ");
        $stmt->execute([
            'uid'   => $user['id'],
            'gate'  => $gate,
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'notes' => $notes
        ]);
        $rec = $stmt->fetch();

        log_audit_trail('CLOCK_IN_WEB', 'clocking_records', $rec['id'], "User clocked in via dashboard on campus premises.", $user['id']);
        send_notification($user['id'], 'Shift Started', "Clock-in confirmed at " . date('H:i') . ". Have a productive session!", 'success');

        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => 'Clock-in recorded successfully!']);
            exit;
        }

        set_flash_message('success', 'Clocked IN successfully! You are now logged on Evelyn Hone College premises.');
        header('Location: ' . $redirectUrl);
        exit;
    } catch (Exception $e) {
        error_log("Clock in error: " . $e->getMessage());
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Database error recording clock-in.']);
            exit;
        }
        set_flash_message('danger', 'Unable to clock in: ' . $e->getMessage());
        header('Location: ' . $redirectUrl);
        exit;
    }
}

if ($action === 'clock_out') {
    $status = get_user_current_clocking_status($user['id']);
    if (!$status['is_clocked_in']) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'You do not have an active clock-in session.']);
            exit;
        }
        set_flash_message('warning', 'You do not have an active clock-in session.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $recordId = $status['current_record']['id'];
    $clockInTime = strtotime($status['current_record']['clock_in']);
    $now = time();
    $totalHours = max(0.01, round(($now - $clockInTime) / 3600, 2));

    try {
        $stmt = $db->prepare("
            UPDATE clocking_records 
            SET clock_out = CURRENT_TIMESTAMP,
                clock_out_method = 'web_dashboard',
                total_hours = :hours,
                status = 'completed'
            WHERE id = :rid
        ");
        $stmt->execute([
            'hours' => $totalHours,
            'rid'   => $recordId
        ]);

        log_audit_trail('CLOCK_OUT_WEB', 'clocking_records', $recordId, "User clocked out via dashboard. Shift duration: {$totalHours} hrs.", $user['id']);
        send_notification($user['id'], 'Shift Completed', "Clock-out recorded. Total shift: {$totalHours} hours. Great work today!", 'info');

        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => "Clocked out successfully. Duration: {$totalHours} hours."]);
            exit;
        }

        set_flash_message('success', "Clocked OUT successfully! Session duration recorded: {$totalHours} hours.");
        header('Location: ' . $redirectUrl);
        exit;
    } catch (Exception $e) {
        error_log("Clock out error: " . $e->getMessage());
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Database error recording clock-out.']);
            exit;
        }
        set_flash_message('danger', 'Unable to clock out: ' . $e->getMessage());
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Fallback
header('Location: ' . $redirectUrl);
exit;
