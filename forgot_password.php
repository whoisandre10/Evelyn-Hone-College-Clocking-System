<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Password Recovery Request
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

$error = '';
$success = '';
$resetUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Please enter your registered email address.';
        } else {
            $db = get_db();
            $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE LOWER(email) = LOWER(:email)");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Security practice: don't disclose existence, or show generic message
                $success = "If that email is registered in our system, a password reset link has been dispatched.";
            } else {
                $token = bin2hex(random_bytes(32));
                // Valid for 1 hour
                $stmtDel = $db->prepare("DELETE FROM password_resets WHERE email = :email");
                $stmtDel->execute(['email' => $user['email']]);

                $stmtIns = $db->prepare("
                    INSERT INTO password_resets (email, token, expires_at)
                    VALUES (:email, :token, CURRENT_TIMESTAMP + INTERVAL '1 hour')
                ");
                $stmtIns->execute(['email' => $user['email'], 'token' => $token]);

                $resetUrl = base_url("reset_password.php?token={$token}&email=" . urlencode($user['email']));
                $success = "Password reset instructions generated successfully.";

                log_audit_trail('PASSWORD_RESET_REQUESTED', 'users', $user['id'], "Password reset requested for {$user['email']}", $user['id']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | Evelyn Hone College Clocking System</title>
  
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
      <h1>PASSWORD RECOVERY</h1>
      <p>Enter your email to reset your staff portal password</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <div><i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i> <?= htmlspecialchars($error) ?></div>
        <button type="button" class="alert-close-btn">&times;</button>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <div><i class="fa-solid fa-circle-check" style="margin-right:6px;"></i> <?= htmlspecialchars($success) ?></div>
        <button type="button" class="alert-close-btn">&times;</button>
      </div>
    <?php endif; ?>

    <?php if (!empty($resetUrl)): ?>
      <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:var(--radius-md); padding:16px; margin-bottom:20px;">
        <h4 style="font-size:0.85rem; color:#1E40AF; margin-bottom:6px;"><i class="fa-solid fa-link"></i> Direct Test Reset Link:</h4>
        <p style="font-size:0.78rem; color:#1E3A8A; word-break:break-all;">Click below to proceed to password reset immediately:</p>
        <a href="<?= $resetUrl ?>" class="btn btn-primary btn-sm" style="margin-top:8px;">
          Set New Password &rarr;
        </a>
      </div>
    <?php endif; ?>

    <form action="<?= base_url('forgot_password.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

      <div class="form-group">
        <label class="form-label">Registered Email Address <span class="required">*</span></label>
        <div class="input-icon-wrapper">
          <i class="fa-regular fa-envelope"></i>
          <input type="email" name="email" class="form-control" placeholder="e.g. dr.mwape@evelynhone.edu.zm" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-weight:700;">
        <i class="fa-solid fa-paper-plane"></i> Send Password Reset Link
      </button>
    </form>

    <div style="margin-top:24px; text-align:center; font-size:0.84rem;">
      <a href="<?= base_url('login.php') ?>">&larr; Return to Sign In</a>
    </div>
  </div>

  <script src="<?= base_url('frontend/assets/js/app.js') ?>"></script>
</body>
</html>
