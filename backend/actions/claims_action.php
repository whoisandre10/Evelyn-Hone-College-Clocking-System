<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Claims Processing Action Controller (CRUD & Review Workflow)
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

// CSRF Protection
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('danger', 'Security token mismatch. Please try again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? base_url('index.php')));
    exit;
}

$action = sanitize_input($_POST['action'] ?? '');
$redirect = $_POST['redirect'] ?? base_url('frontend/views/' . $user['role'] . '/claims.php');

// ============================================================================
// 1. CREATE CLAIM (Lecturer or Staff Submission)
// ============================================================================
if ($action === 'create') {
    $claimType = sanitize_input($_POST['claim_type'] ?? '');
    $claimDate = sanitize_input($_POST['claim_date'] ?? date('Y-m-d'));
    $courseCode = sanitize_input($_POST['course_code'] ?? null);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $ratePerUnit = (float)($_POST['rate_per_unit'] ?? 0);
    $description = sanitize_input($_POST['description'] ?? '');

    // Validation
    $errors = [];
    if (empty($claimType)) $errors[] = "Please select a claim type.";
    if (empty($claimDate)) $errors[] = "Please specify the claim date.";
    if ($quantity <= 0) $errors[] = "Quantity / Hours must be greater than zero.";
    if ($ratePerUnit <= 0) $errors[] = "Rate per unit must be greater than zero.";
    if (empty($description)) $errors[] = "Please provide a detailed claim description.";

    // Role-specific check
    if ($user['role'] === 'lecturer' && in_array($claimType, ['weekend_duty', 'special_assignment'])) {
        // Warning or allowed
    }
    if ($user['role'] === 'staff' && in_array($claimType, ['exam_invigilation', 'exam_marking', 'extra_lecture'])) {
        $errors[] = "Only academic staff (lecturers) can submit exam marking or invigilation claims.";
    }

    if (!empty($errors)) {
        set_flash_message('danger', implode(' ', $errors));
        header('Location: ' . $redirect);
        exit;
    }

    $totalAmount = round($quantity * $ratePerUnit, 2);

    // Optional File Upload (e.g. Invigilation timetable or marking schedule)
    $evidenceFile = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['evidence'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'docx'];

        if (in_array($ext, $allowedExts, true)) {
            $uploadDir = __DIR__ . '/../../uploads/claims/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'claim_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $evidenceFile = $filename;
            }
        }
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO claims (user_id, claim_type, claim_date, course_code, quantity, rate_per_unit, total_amount, description, evidence_file, status)
            VALUES (:uid, :type, :cdate, :code, :qty, :rate, :total, :desc, :file, 'pending')
            RETURNING id
        ");
        $stmt->execute([
            'uid'   => $user['id'],
            'type'  => $claimType,
            'cdate' => $claimDate,
            'code'  => !empty($courseCode) ? $courseCode : null,
            'qty'   => $quantity,
            'rate'  => $ratePerUnit,
            'total' => $totalAmount,
            'desc'  => $description,
            'file'  => $evidenceFile
        ]);
        $claimId = $stmt->fetchColumn();

        log_audit_trail('CLAIM_SUBMITTED', 'claims', $claimId, "Submitted {$claimType} claim for ZMW {$totalAmount}.", $user['id']);
        
        // Notify administration / HR
        $stmtAdmin = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = $stmtAdmin->fetchColumn();
        if ($adminId) {
            send_notification($adminId, 'New Claim Submitted', "Staff {$user['full_name']} submitted a claim of ZMW {$totalAmount} ({$claimType}).", 'info', base_url('frontend/views/admin/claims_management.php'));
        }

        set_flash_message('success', "Claim submitted successfully! Reference #CLM-{$claimId} (ZMW " . number_format($totalAmount, 2) . ") is pending approval.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        error_log("Error creating claim: " . $e->getMessage());
        set_flash_message('danger', 'Database error submitting claim: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 2. DELETE PENDING CLAIM (User or Admin)
// ============================================================================
if ($action === 'delete') {
    $claimId = (int)($_POST['claim_id'] ?? 0);

    try {
        // Check ownership or admin
        $stmt = $db->prepare("SELECT * FROM claims WHERE id = :id");
        $stmt->execute(['id' => $claimId]);
        $claim = $stmt->fetch();

        if (!$claim) {
            set_flash_message('danger', 'Claim record not found.');
            header('Location: ' . $redirect);
            exit;
        }

        if ($user['role'] !== 'admin' && $claim['user_id'] != $user['id']) {
            set_flash_message('danger', 'Unauthorized: You can only delete your own claims.');
            header('Location: ' . $redirect);
            exit;
        }

        if ($user['role'] !== 'admin' && $claim['status'] !== 'pending') {
            set_flash_message('danger', 'Cannot delete a claim that has already been ' . $claim['status'] . '.');
            header('Location: ' . $redirect);
            exit;
        }

        $stmtDel = $db->prepare("DELETE FROM claims WHERE id = :id");
        $stmtDel->execute(['id' => $claimId]);

        log_audit_trail('CLAIM_DELETED', 'claims', $claimId, "Deleted claim #{$claimId} (ZMW {$claim['total_amount']})", $user['id']);
        set_flash_message('success', "Claim #CLM-{$claimId} deleted successfully.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error deleting claim: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 3. APPROVE CLAIM (Admin / HR)
// ============================================================================
if ($action === 'approve') {
    require_role('admin');
    $claimId = (int)($_POST['claim_id'] ?? 0);

    try {
        $stmt = $db->prepare("
            UPDATE claims 
            SET status = 'approved',
                reviewed_by = :admin_id,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id
            RETURNING user_id, total_amount, claim_type
        ");
        $stmt->execute([
            'admin_id' => $user['id'],
            'id'       => $claimId
        ]);
        $claim = $stmt->fetch();

        if ($claim) {
            log_audit_trail('CLAIM_APPROVED', 'claims', $claimId, "Approved claim #{$claimId} (ZMW {$claim['total_amount']})", $user['id']);
            send_notification($claim['user_id'], 'Claim Approved!', "Your claim for {$claim['claim_type']} of ZMW " . number_format($claim['total_amount'], 2) . " has been approved.", 'success');
            set_flash_message('success', "Claim #CLM-{$claimId} has been approved.");
        } else {
            set_flash_message('warning', "Claim #CLM-{$claimId} could not be updated.");
        }

        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error approving claim: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 4. REJECT CLAIM (Admin / HR)
// ============================================================================
if ($action === 'reject') {
    require_role('admin');
    $claimId = (int)($_POST['claim_id'] ?? 0);
    $reason = sanitize_input($_POST['rejection_reason'] ?? 'Documentation insufficient or does not match timetable.');

    try {
        $stmt = $db->prepare("
            UPDATE claims 
            SET status = 'rejected',
                rejection_reason = :reason,
                reviewed_by = :admin_id,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id
            RETURNING user_id, total_amount, claim_type
        ");
        $stmt->execute([
            'reason'   => $reason,
            'admin_id' => $user['id'],
            'id'       => $claimId
        ]);
        $claim = $stmt->fetch();

        if ($claim) {
            log_audit_trail('CLAIM_REJECTED', 'claims', $claimId, "Rejected claim #{$claimId}. Reason: {$reason}", $user['id']);
            send_notification($claim['user_id'], 'Claim Rejected', "Your claim for {$claim['claim_type']} was rejected. Reason: {$reason}", 'danger');
            set_flash_message('info', "Claim #CLM-{$claimId} has been rejected.");
        } else {
            set_flash_message('warning', "Claim not found.");
        }

        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error rejecting claim: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

header('Location: ' . $redirect);
exit;
