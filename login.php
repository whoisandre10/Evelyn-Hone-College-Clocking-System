<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Secure Authentication & Login Gateway
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

// Redirect if already logged in
if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        redirect_to_dashboard($user['role']);
        exit;
    }
}

$error = '';
$success = '';

// Handle URL messages
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'concurrent_session':
            $error = 'You were logged out because your account was logged into from another device or location.';
            break;
        case 'login_required':
            $error = 'Please log in to access the requested system page.';
            break;
        case 'account_suspended':
            $error = 'Your account has been suspended by administration. Please contact the Registrar.';
            break;
        case 'account_inactive':
            $error = 'Your account is currently inactive. Please contact HR.';
            break;
        case 'session_invalid':
            $error = 'Your session has expired. Please sign in again.';
            break;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $success = 'You have been safely signed out.';
}

// Process POST login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $loginInput = trim($_POST['login_identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($loginInput) || empty($password)) {
            $error = 'Please enter your Evelyn Hone College Email or Staff ID, and password.';
        } else {
            $db = get_db();
            $stmt = $db->prepare("
                SELECT * FROM users 
                WHERE LOWER(email) = LOWER(:id) 
                   OR LOWER(staff_id) = LOWER(:id)
            ");
            $stmt->execute(['id' => $loginInput]);
            $foundUser = $stmt->fetch();

            if (!$foundUser || !password_verify($password, $foundUser['password_hash'])) {
                $error = 'Invalid email/staff ID or password. Please verify your credentials.';
                log_audit_trail('LOGIN_FAILED', 'users', null, "Failed login attempt for identity: {$loginInput}");
            } elseif ($foundUser['status'] === 'suspended') {
                $error = 'This account has been suspended by administration. Access restricted.';
            } elseif ($foundUser['status'] !== 'active') {
                $error = 'Account is not active (Status: ' . htmlspecialchars($foundUser['status']) . '). Contact HR.';
            } else {
                // Successful login - enfore single active session
                login_user($foundUser);

                set_flash_message('success', 'Welcome back, ' . $foundUser['full_name'] . '! Signed in successfully.');
                redirect_to_dashboard($foundUser['role']);
                exit;
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
  <title>Staff Login | Evelyn Hone College Clocking System</title>
  
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
      <h1>EVELYN HONE COLLEGE</h1>
      <p>Staff Clocking & Transparent Billing System</p>
      <div style="font-size:0.75rem; color:var(--ehc-primary-light); font-weight:700; letter-spacing:1px; text-transform:uppercase; margin-top:2px;">
        Knowledge with Integrity
      </div>
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

    <!-- Login Form -->
    <form action="<?= base_url('login.php') ?>" method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

      <div class="form-group">
        <label for="loginIdentity" class="form-label">Email Address or Staff ID <span class="required">*</span></label>
        <div class="input-icon-wrapper">
          <i class="fa-regular fa-envelope"></i>
          <input type="text" name="login_identity" id="loginIdentity" class="form-control" placeholder="e.g. dr.mwape@evelynhone.edu.zm or EHC-LEC-101" required value="<?= htmlspecialchars($_POST['login_identity'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label for="loginPassword" class="form-label" style="margin-bottom:0;">Password <span class="required">*</span></label>
          <a href="<?= base_url('forgot_password.php') ?>" style="font-size:0.78rem; font-weight:600;">Forgot Password?</a>
        </div>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter your password" required>
          <i class="fa-regular fa-eye toggle-password" data-target="loginPassword" title="Show/Hide Password"></i>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:13px; font-weight:700; font-size:1rem; margin-top:8px;">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In to Portal
      </button>
    </form>

    <div style="margin-top:20px; text-align:center; font-size:0.84rem; color:var(--text-muted);">
      Don't have an account? <a href="<?= base_url('register.php') ?>" style="font-weight:700;">Enroll Staff Member</a>
    </div>

    <!-- Convenient Quick-Fill Demo Accounts -->
    <div class="demo-account-pills">
      <h6><i class="fa-solid fa-key" style="color:var(--ehc-primary);"></i> Quick Test Accounts (One-Click Fill):</h6>
      <button type="button" class="demo-pill-btn" onclick="fillLogin('admin@evelynhone.edu.zm', 'Admin@12345')">
        <i class="fa-solid fa-crown" style="color:#F59E0B;"></i> Principal / Admin
      </button>
      <button type="button" class="demo-pill-btn" onclick="fillLogin('dr.mwape@evelynhone.edu.zm', 'Lecturer@12345')">
        <i class="fa-solid fa-chalkboard-user" style="color:var(--ehc-primary);"></i> Full-Time Lecturer (HOD)
      </button>
      <button type="button" class="demo-pill-btn" onclick="fillLogin('pt.banda@evelynhone.edu.zm', 'Lecturer@12345')">
        <i class="fa-solid fa-user-clock" style="color:var(--ehc-primary);"></i> Part-Time Lecturer
      </button>
      <button type="button" class="demo-pill-btn" onclick="fillLogin('staff.lungu@evelynhone.edu.zm', 'Staff@12345')">
        <i class="fa-solid fa-briefcase" style="color:var(--ehc-cyan-dark);"></i> Full-Time Staff
      </button>
      <button type="button" class="demo-pill-btn" onclick="fillLogin('pt.tembo@evelynhone.edu.zm', 'Staff@12345')">
        <i class="fa-solid fa-flask" style="color:var(--ehc-cyan-dark);"></i> Part-Time Staff
      </button>
      <button type="button" class="demo-pill-btn" onclick="fillLogin('hr@evelynhone.edu.zm', 'Admin@12345')">
        <i class="fa-solid fa-clipboard-user"></i> HR Head
      </button>
    </div>

    <div style="margin-top:16px; text-align:center;">
      <a href="<?= base_url('frontend/views/terminal/tap_terminal.php') ?>" target="_blank" style="font-size:0.8rem; color:var(--ehc-cyan-dark); font-weight:700;">
        <i class="fa-solid fa-fingerprint"></i> Open Campus Gate ID Tap-In Simulator &rarr;
      </a>
    </div>

  </div>

  <script src="<?= base_url('frontend/assets/js/app.js') ?>"></script>
  <script>
    function fillLogin(identity, password) {
      document.getElementById('loginIdentity').value = identity;
      document.getElementById('loginPassword').value = password;
    }
  </script>
</body>
</html>
