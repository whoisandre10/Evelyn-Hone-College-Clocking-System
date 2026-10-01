<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Multi-Level Timesheet Approval Action Controller
 * (Levels: 1. HOD -> 2. HR/Admin -> 3. Finance & Accounts)
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
    set_flash_message('danger', 'Security validation failed. Please try again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? base_url('index.php')));
    exit;
}

$action = sanitize_input($_POST['action'] ?? '');
$redirect = $_POST['redirect'] ?? base_url('frontend/views/' . $user['role'] . '/timesheets.php');

// ============================================================================
// 1. SUBMIT TIMESHEET FOR PERIOD (Staff or Lecturer)
// ============================================================================
if ($action === 'submit_timesheet') {
    $periodStart = sanitize_input($_POST['period_start'] ?? '');
    $periodEnd = sanitize_input($_POST['period_end'] ?? '');

    if (empty($periodStart) || empty($periodEnd) || $periodStart > $periodEnd) {
        set_flash_message('danger', 'Please select a valid date range for your timesheet period.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        // Run Automated Billing Engine for the candidate period
        $billing = calculate_staff_billing($user['id'], $periodStart, $periodEnd);

        if ($billing['total_clocked_hours'] <= 0 && $billing['claims_total'] <= 0) {
            set_flash_message('warning', 'No clocking records or claims found between ' . format_date($periodStart) . ' and ' . format_date($periodEnd) . '. Please ensure your shifts are completed.');
            header('Location: ' . $redirect);
            exit;
        }

        // Insert into timesheets
        $stmt = $db->prepare("
            INSERT INTO timesheets (
                user_id, period_start, period_end, total_clocked_hours, regular_hours, 
                overtime_hours, claims_total_amount, base_pay_amount, gross_billing_amount, 
                hod_status, hr_status, finance_status, overall_status
            ) VALUES (
                :uid, :sdate, :edate, :tot_hrs, :reg_hrs, 
                :ot_hrs, :claims_tot, :base_pay, :gross, 
                'pending', 'pending', 'pending', 'submitted'
            ) RETURNING id
        ");

        $stmt->execute([
            'uid'        => $user['id'],
            'sdate'      => $periodStart,
            'edate'      => $periodEnd,
            'tot_hrs'    => $billing['total_clocked_hours'],
            'reg_hrs'    => $billing['regular_hours'],
            'ot_hrs'     => $billing['overtime_hours'],
            'claims_tot' => $billing['claims_total'],
            'base_pay'   => $billing['base_pay'] + $billing['overtime_pay'],
            'gross'      => $billing['gross_billing_amount']
        ]);
        $tsId = $stmt->fetchColumn();

        log_audit_trail('TIMESHEET_SUBMITTED', 'timesheets', $tsId, "Timesheet #TS-{$tsId} submitted for period {$periodStart} to {$periodEnd}. Gross: ZMW {$billing['gross_billing_amount']}", $user['id']);

        // Notify HOD & Admin
        $stmtAdmin = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = $stmtAdmin->fetchColumn();
        if ($adminId) {
            send_notification($adminId, 'Timesheet Awaiting Review', "Staff {$user['full_name']} submitted timesheet #TS-{$tsId} ({$billing['total_clocked_hours']} hrs, ZMW " . number_format($billing['gross_billing_amount'], 2) . ")", 'info', base_url('frontend/views/admin/timesheets_approval.php'));
        }

        set_flash_message('success', "Timesheet #TS-{$tsId} submitted successfully! It has been routed to your Head of Department (HOD) for Stage 1 signoff.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        error_log("Timesheet submission error: " . $e->getMessage());
        set_flash_message('danger', 'Error submitting timesheet: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 2. APPROVE TIMESHEET (Multi-Level Workflow: HOD, HR, Finance)
// ============================================================================
if (in_array($action, ['approve_hod', 'approve_hr', 'approve_finance'])) {
    require_role('admin');
    $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
    $comment = sanitize_input($_POST['comment'] ?? 'Verified and approved.');

    try {
        $stmt = $db->prepare("SELECT * FROM timesheets WHERE id = :id");
        $stmt->execute(['id' => $timesheetId]);
        $ts = $stmt->fetch();

        if (!$ts) {
            set_flash_message('danger', 'Timesheet record not found.');
            header('Location: ' . $redirect);
            exit;
        }

        if ($action === 'approve_hod') {
            $stmtUp = $db->prepare("
                UPDATE timesheets 
                SET hod_status = 'approved',
                    hod_comment = :comment,
                    hod_approved_by = :uid,
                    hod_approved_at = CURRENT_TIMESTAMP,
                    overall_status = 'under_review'
                WHERE id = :id
            ");
            $stmtUp->execute(['comment' => $comment, 'uid' => $user['id'], 'id' => $timesheetId]);
            log_audit_trail('TIMESHEET_HOD_APPROVED', 'timesheets', $timesheetId, "HOD Stage 1 Approved: {$comment}", $user['id']);
            send_notification($ts['user_id'], 'Timesheet: HOD Approved', "Your timesheet #TS-{$timesheetId} passed HOD verification and is now at HR.", 'info');
            set_flash_message('success', "Stage 1 (HOD Approval) recorded for Timesheet #TS-{$timesheetId}.");
        } 
        elseif ($action === 'approve_hr') {
            $stmtUp = $db->prepare("
                UPDATE timesheets 
                SET hr_status = 'approved',
                    hr_comment = :comment,
                    hr_approved_by = :uid,
                    hr_approved_at = CURRENT_TIMESTAMP,
                    overall_status = 'under_review'
                WHERE id = :id
            ");
            $stmtUp->execute(['comment' => $comment, 'uid' => $user['id'], 'id' => $timesheetId]);
            log_audit_trail('TIMESHEET_HR_APPROVED', 'timesheets', $timesheetId, "HR Stage 2 Approved: {$comment}", $user['id']);
            send_notification($ts['user_id'], 'Timesheet: HR Verified', "Your timesheet #TS-{$timesheetId} passed HR check and is forwarded to Finance.", 'info');
            set_flash_message('success', "Stage 2 (HR Approval) recorded for Timesheet #TS-{$timesheetId}.");
        } 
        elseif ($action === 'approve_finance') {
            $stmtUp = $db->prepare("
                UPDATE timesheets 
                SET finance_status = 'approved',
                    finance_comment = :comment,
                    finance_approved_by = :uid,
                    finance_approved_at = CURRENT_TIMESTAMP,
                    overall_status = 'approved'
                WHERE id = :id
            ");
            $stmtUp->execute(['comment' => $comment, 'uid' => $user['id'], 'id' => $timesheetId]);
            log_audit_trail('TIMESHEET_FINANCE_FINAL_APPROVED', 'timesheets', $timesheetId, "Finance Final Authorization: {$comment}", $user['id']);
            send_notification($ts['user_id'], 'Timesheet: Fully Approved for Payroll!', "Timesheet #TS-{$timesheetId} is fully approved for payment (ZMW " . number_format($ts['gross_billing_amount'], 2) . ")!", 'success');
            set_flash_message('success', "Stage 3 (Finance Final Approval) complete! Timesheet #TS-{$timesheetId} is authorized for payroll.");
        }

        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error updating timesheet: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// ============================================================================
// 3. REJECT TIMESHEET (Any Stage)
// ============================================================================
if ($action === 'reject_timesheet') {
    require_role('admin');
    $timesheetId = (int)($_POST['timesheet_id'] ?? 0);
    $stage = sanitize_input($_POST['stage'] ?? 'hod'); // hod, hr, finance
    $reason = sanitize_input($_POST['rejection_reason'] ?? 'Timesheet rejected due to discrepancies.');

    try {
        $field = ($stage === 'hr') ? 'hr_status' : (($stage === 'finance') ? 'finance_status' : 'hod_status');
        $commentField = ($stage === 'hr') ? 'hr_comment' : (($stage === 'finance') ? 'finance_comment' : 'hod_comment');

        $stmt = $db->prepare("
            UPDATE timesheets 
            SET {$field} = 'rejected',
                {$commentField} = :reason,
                overall_status = 'rejected'
            WHERE id = :id
            RETURNING user_id
        ");
        $stmt->execute(['reason' => $reason, 'id' => $timesheetId]);
        $uid = $stmt->fetchColumn();

        if ($uid) {
            log_audit_trail('TIMESHEET_REJECTED', 'timesheets', $timesheetId, "Rejected at {$stage} stage. Reason: {$reason}", $user['id']);
            send_notification($uid, 'Timesheet Rejected', "Timesheet #TS-{$timesheetId} was rejected during {$stage} stage. Reason: {$reason}", 'danger');
            set_flash_message('info', "Timesheet #TS-{$timesheetId} has been marked rejected.");
        }

        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error rejecting timesheet: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

header('Location: ' . $redirect);
exit;
