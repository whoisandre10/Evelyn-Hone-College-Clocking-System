<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * User Administration & Profile Action Controller
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_login();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('index.php'));
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('danger', 'Security token invalid. Please refresh the page and try again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? base_url('index.php')));
    exit;
}

$action = sanitize_input($_POST['action'] ?? '');
$redirect = $_POST['redirect'] ?? base_url('index.php');

// ============================================================================
// 1. ADMIN CREATE USER
// ============================================================================
if ($action === 'create_user') {
    require_role('admin');

    $staffId = strtoupper(sanitize_input($_POST['staff_id'] ?? ''));
    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $email = strtolower(sanitize_input($_POST['email'] ?? ''));
    $phone = sanitize_input($_POST['phone'] ?? '');
    $nrc = sanitize_input($_POST['nrc_number'] ?? '');
    $rfid = sanitize_input($_POST['rfid_card_id'] ?? '');
    $role = sanitize_input($_POST['role'] ?? 'lecturer');
    $empType = sanitize_input($_POST['employment_type'] ?? 'full_time');
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $designation = sanitize_input($_POST['designation'] ?? '');
    $hourlyRate = (float)($_POST['hourly_rate'] ?? 0.0);
    $password = $_POST['password'] ?? '';
    $status = sanitize_input($_POST['status'] ?? 'active');

    if (empty($staffId) || empty($fullName) || empty($email) || empty($password)) {
        set_flash_message('danger', 'Staff ID, Full Name, Email, and Temporary Password are required.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        // Check uniqueness of staff_id and email
        $stmtChk = $db->prepare("SELECT id FROM users WHERE email = :email OR staff_id = :sid");
        $stmtChk->execute(['email' => $email, 'sid' => $staffId]);
        if ($stmtChk->fetch()) {
            set_flash_message('danger', "A user with Staff ID '{$staffId}' or Email '{$email}' already exists.");
            header('Location: ' . $redirect);
            exit;
        }

        $pwdHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO users (
                staff_id, nrc_number, rfid_card_id, full_name, email, phone, 
                password_hash, role, employment_type, designation, department_id, 
                hourly_rate, status
            ) VALUES (
                :sid, :nrc, :rfid, :name, :email, :phone, 
                :pwd, :role, :emp, :desig, :dept, 
                :rate, :status
            ) RETURNING id
        ");

        $stmt->execute([
            'sid'    => $staffId,
            'nrc'    => !empty($nrc) ? $nrc : null,
            'rfid'   => !empty($rfid) ? $rfid : ('RFID-' . rand(100000, 999999)),
            'name'   => $fullName,
            'email'  => $email,
            'phone'  => $phone,
            'pwd'    => $pwdHash,
            'role'   => $role,
            'emp'    => $empType,
            'desig'  => $designation,
            'dept'   => $deptId,
            'rate'   => $hourlyRate,
            'status' => $status
        ]);
        $newId = $stmt->fetchColumn();

        log_audit_trail('USER_CREATED', 'users', $newId, "Admin created staff member {$fullName} ({$staffId} - {$role}).", $user['id']);
        set_flash_message('success', "Staff member {$fullName} ({$staffId}) registered successfully.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error adding user: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 2. ADMIN UPDATE USER
// ============================================================================
if ($action === 'update_user') {
    require_role('admin');
    $targetId = (int)($_POST['user_id'] ?? 0);

    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $email = strtolower(sanitize_input($_POST['email'] ?? ''));
    $phone = sanitize_input($_POST['phone'] ?? '');
    $nrc = sanitize_input($_POST['nrc_number'] ?? '');
    $rfid = sanitize_input($_POST['rfid_card_id'] ?? '');
    $role = sanitize_input($_POST['role'] ?? 'lecturer');
    $empType = sanitize_input($_POST['employment_type'] ?? 'full_time');
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $designation = sanitize_input($_POST['designation'] ?? '');
    $hourlyRate = (float)($_POST['hourly_rate'] ?? 0.0);
    $status = sanitize_input($_POST['status'] ?? 'active');

    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET full_name = :name,
                email = :email,
                phone = :phone,
                nrc_number = :nrc,
                rfid_card_id = :rfid,
                role = :role,
                employment_type = :emp,
                department_id = :dept,
                designation = :desig,
                hourly_rate = :rate,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([
            'name'   => $fullName,
            'email'  => $email,
            'phone'  => $phone,
            'nrc'    => $nrc,
            'rfid'   => $rfid,
            'role'   => $role,
            'emp'    => $empType,
            'dept'   => $deptId,
            'desig'  => $designation,
            'rate'   => $hourlyRate,
            'status' => $status,
            'id'     => $targetId
        ]);

        log_audit_trail('USER_UPDATED', 'users', $targetId, "Admin updated user details for {$fullName}.", $user['id']);
        set_flash_message('success', "User details for {$fullName} updated successfully.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error updating user: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 3. ADMIN TOGGLE SUSPENSION
// ============================================================================
if ($action === 'toggle_status') {
    require_role('admin');
    $targetId = (int)($_POST['user_id'] ?? 0);
    $newStatus = sanitize_input($_POST['new_status'] ?? 'active');

    if ($targetId === $user['id']) {
        set_flash_message('danger', 'You cannot suspend your own administrative account.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE users SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id RETURNING full_name");
        $stmt->execute(['status' => $newStatus, 'id' => $targetId]);
        $name = $stmt->fetchColumn();

        // If suspending, terminate active sessions
        if ($newStatus === 'suspended') {
            $stmtSess = $db->prepare("UPDATE user_sessions SET is_active = FALSE, logout_time = CURRENT_TIMESTAMP, logout_reason = 'account_suspended' WHERE user_id = :id");
            $stmtSess->execute(['id' => $targetId]);
            $stmtClr = $db->prepare("UPDATE users SET current_session_token = NULL WHERE id = :id");
            $stmtClr->execute(['id' => $targetId]);
        }

        log_audit_trail('USER_STATUS_CHANGE', 'users', $targetId, "Admin changed {$name} status to {$newStatus}.", $user['id']);
        set_flash_message('success', "Staff member {$name} status is now {$newStatus}.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error updating status: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 4. ADMIN DELETE USER
// ============================================================================
if ($action === 'delete_user') {
    require_role('admin');
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId === $user['id']) {
        set_flash_message('danger', 'You cannot delete your own account.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id RETURNING full_name, staff_id");
        $stmt->execute(['id' => $targetId]);
        $deleted = $stmt->fetch();

        if ($deleted) {
            log_audit_trail('USER_DELETED', 'users', $targetId, "Admin deleted user {$deleted['full_name']} ({$deleted['staff_id']}).", $user['id']);
            set_flash_message('success', "Staff record {$deleted['full_name']} has been removed from system.");
        }
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error deleting user: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 5. USER SELF PROFILE UPDATE
// ============================================================================
if ($action === 'update_profile') {
    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $nrc = sanitize_input($_POST['nrc_number'] ?? '');

    if (empty($fullName)) {
        set_flash_message('danger', 'Full Name is required.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET full_name = :name, phone = :phone, nrc_number = :nrc, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->execute(['name' => $fullName, 'phone' => $phone, 'nrc' => $nrc, 'id' => $user['id']]);
        $_SESSION['full_name'] = $fullName;

        log_audit_trail('PROFILE_UPDATED', 'users', $user['id'], "User updated their personal profile.", $user['id']);
        set_flash_message('success', 'Your profile details have been saved.');
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error updating profile: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 6. USER CHANGE PASSWORD
// ============================================================================
if ($action === 'change_password') {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    if (empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
        set_flash_message('danger', 'All password fields are required.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($newPwd !== $confirmPwd) {
        set_flash_message('danger', 'New password and confirmation do not match.');
        header('Location: ' . $redirect);
        exit;
    }

    if (strlen($newPwd) < 6) {
        set_flash_message('danger', 'New password must be at least 6 characters.');
        header('Location: ' . $redirect);
        exit;
    }

    // Verify current password
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute(['id' => $user['id']]);
    $currentHash = $stmt->fetchColumn();

    if (!password_verify($currentPwd, $currentHash)) {
        set_flash_message('danger', 'Current password is incorrect.');
        header('Location: ' . $redirect);
        exit;
    }

    $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
    $stmtUp = $db->prepare("UPDATE users SET password_hash = :pwd, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmtUp->execute(['pwd' => $newHash, 'id' => $user['id']]);

    log_audit_trail('PASSWORD_CHANGED', 'users', $user['id'], "User changed their login password.", $user['id']);
    set_flash_message('success', 'Password updated successfully! Please keep your credentials secure.');
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
