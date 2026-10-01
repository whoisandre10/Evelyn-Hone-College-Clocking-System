<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Password Reset Execution
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error = '';
$success = '';

$db = get_db();

// Verify token validity
$stmt = $db->prepare("SELECT * FROM password_resets WHERE token = :token AND email = :email AND expires_at > CURRENT_TIMESTAMP");
$stmt->execute(['token' => $token, 'email' => $email]);
$resetRecord = $stmt->fetch();

if (!$resetRecord) {
    $error = 'This password reset link is invalid or has expired. Please request a new link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRecord) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in both password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update user password and clear tokens
            $stmtUp = $db->prepare("UPDATE users SET password_hash = :pwd, current_session_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE LOWER(email) = LOWER(:email)");
            $stmtUp->execute(['pwd' => $newHash, 'email' => $email]);

            $stmtDel = $db->prepare("DELETE FROM password_resets WHERE email = :email");
            $stmtDel->execute(['email' => $email]);

            log_audit_trail('PASSWORD_RESET_COMPLETED', 'users', null, "Password reset completed for {$email}");
            set_flash_message('success', 'Your password has been reset successfully! You can now log in.');
            header('Location: ' . base_url('login.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | Evelyn Hone College</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('frontend/assets/css/style.css') ?>">
</head>
<body class="auth-wrapper">

  <div class="auth-card">
    <div class="auth-brand">
      <a href="<?= base_url('index.php') ?>">
        <img src="<?= base_url('frontend/assets/images/ehc_logo.png') ?>" alt="Evelyn Hone College Emblem">
      </a>
      <h1>SET NEW PASSWORD</h1>
      <p>Reset password for <?= htmlspecialchars($email) ?></p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <div><i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i> <?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($resetRecord): ?>
      <form action="<?= base_url('reset_password.php') ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

        <div class="form-group">
          <label class="form-label">New Password <span class="required">*</span></label>
          <div class="input-icon-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="new_password" id="resetNewPwd" class="form-control" placeholder="Min 6 characters" required>
            <i class="fa-regular fa-eye toggle-password" data-target="resetNewPwd"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password <span class="required">*</span></label>
          <div class="input-icon-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="confirm_password" id="resetConfirmPwd" class="form-control" placeholder="Repeat new password" required>
            <i class="fa-regular fa-eye toggle-password" data-target="resetConfirmPwd"></i>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:700;">
          <i class="fa-solid fa-check"></i> Update Password & Sign In
        </button>
      </form>
    <?php else: ?>
      <div style="text-align:center; margin-top:20px;">
        <a href="<?= base_url('forgot_password.php') ?>" class="btn btn-secondary">Request New Reset Link</a>
      </div>
    <?php endif; ?>

    <div style="margin-top:24px; text-align:center; font-size:0.84rem;">
      <a href="<?= base_url('login.php') ?>">&larr; Return to Sign In</a>
    </div>
  </div>

  <script src="<?= base_url('frontend/assets/js/app.js') ?>"></script>
</body>
</html>
