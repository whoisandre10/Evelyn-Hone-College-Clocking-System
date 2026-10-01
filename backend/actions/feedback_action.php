<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Feedback & Campus Collaboration Action Controller
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
    set_flash_message('danger', 'Security validation failed.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? base_url('index.php')));
    exit;
}

$action = sanitize_input($_POST['action'] ?? '');
$redirect = $_POST['redirect'] ?? base_url('frontend/views/' . $user['role'] . '/feedback.php');

// 1. SUBMIT INQUIRY / FEEDBACK
if ($action === 'create') {
    $category = sanitize_input($_POST['category'] ?? 'General');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $message = sanitize_input($_POST['message'] ?? '');
    $priority = sanitize_input($_POST['priority'] ?? 'medium');
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : $user['department_id'];

    if (empty($subject) || empty($message)) {
        set_flash_message('danger', 'Subject and message are required.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO feedback_messages (sender_id, department_id, category, subject, message, priority, status)
            VALUES (:uid, :did, :cat, :sub, :msg, :pri, 'open')
            RETURNING id
        ");
        $stmt->execute([
            'uid' => $user['id'],
            'did' => $deptId,
            'cat' => $category,
            'sub' => $subject,
            'msg' => $message,
            'pri' => $priority
        ]);
        $fbId = $stmt->fetchColumn();

        log_audit_trail('FEEDBACK_SUBMITTED', 'feedback_messages', $fbId, "Submitted feedback #{$fbId}: {$subject}", $user['id']);

        // Notify Admins
        $stmtAdmin = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminId = $stmtAdmin->fetchColumn();
        if ($adminId) {
            send_notification($adminId, 'New Staff Feedback', "Staff {$user['full_name']} posted inquiry: {$subject}", 'info', base_url('frontend/views/admin/feedback_collaboration.php'));
        }

        set_flash_message('success', "Your message has been posted to the collaboration desk (Ticket #FBC-{$fbId}). Administration has been alerted.");
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error posting feedback: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

// 2. REPLY TO FEEDBACK (Admin or Management)
if ($action === 'reply') {
    require_role('admin');
    $feedbackId = (int)($_POST['feedback_id'] ?? 0);
    $response = sanitize_input($_POST['response_text'] ?? '');
    $newStatus = sanitize_input($_POST['status'] ?? 'resolved');

    if (empty($response)) {
        set_flash_message('danger', 'Response message cannot be empty.');
        header('Location: ' . $redirect);
        exit;
    }

    try {
        $stmt = $db->prepare("
            UPDATE feedback_messages 
            SET response_text = :resp,
                status = :status,
                responded_by = :admin_id,
                responded_at = CURRENT_TIMESTAMP
            WHERE id = :id
            RETURNING sender_id, subject
        ");
        $stmt->execute([
            'resp'     => $response,
            'status'   => $newStatus,
            'admin_id' => $user['id'],
            'id'       => $feedbackId
        ]);
        $fb = $stmt->fetch();

        if ($fb) {
            log_audit_trail('FEEDBACK_RESOLVED', 'feedback_messages', $feedbackId, "Responded to #{$feedbackId}. Status set to {$newStatus}.", $user['id']);
            send_notification($fb['sender_id'], 'Response to your inquiry', "Admin responded to '{$fb['subject']}': " . substr($response, 0, 80) . "...", 'info');
            set_flash_message('success', "Response sent and Ticket #FBC-{$feedbackId} updated to {$newStatus}.");
        }

        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        set_flash_message('danger', 'Error submitting reply: ' . $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }
}

header('Location: ' . $redirect);
exit;
