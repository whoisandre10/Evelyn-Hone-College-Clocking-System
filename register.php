<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Staff Registration & Enrollment
 */

define('EHC_SYSTEM', true);
require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        redirect_to_dashboard($user['role']);
        exit;
    }
}

$db = get_db();
$error = '';
$success = '';

// Fetch all schools and departments for dropdown
$stmtDepts = $db->query("
    SELECT d.id, d.name AS dept_name, d.code AS dept_code, d.hod_name, s.name AS school_name
    FROM departments d
    JOIN schools s ON d.school_id = s.id
    ORDER BY s.id, d.name
");
$departmentsBySchool = [];
while ($row = $stmtDepts->fetch()) {
    $departmentsBySchool[$row['school_name']][] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please reload and try again.';
    } else {
        $staffId = strtoupper(sanitize_input($_POST['staff_id'] ?? ''));
        $fullName = sanitize_input($_POST['full_name'] ?? '');
        $email = strtolower(sanitize_input($_POST['email'] ?? ''));
        $phone = sanitize_input($_POST['phone'] ?? '');
        $nrc = sanitize_input($_POST['nrc_number'] ?? '');
        $role = sanitize_input($_POST['role'] ?? 'lecturer');
        $empType = sanitize_input($_POST['employment_type'] ?? 'full_time');
        $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $designation = sanitize_input($_POST['designation'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($staffId) || empty($fullName) || empty($email) || empty($password)) {
            $error = 'Staff ID, Full Name, Email, and Password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } else {
            // Check duplicates
            $stmtChk = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) OR LOWER(staff_id) = LOWER(:sid)");
            $stmtChk->execute(['email' => $email, 'sid' => $staffId]);
            if ($stmtChk->fetch()) {
                $error = "A staff member with this Email or Staff ID ({$staffId}) is already enrolled.";
            } else {
                // Determine baseline hourly rate based on role & category
                $hourlyRate = ($role === 'lecturer') ? (($empType === 'part_time') ? 220.00 : 280.00) : (($empType === 'part_time') ? 95.00 : 160.00);
                $rfid = 'RFID-' . rand(100000, 999999);
                $pwdHash = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $stmt = $db->prepare("
                        INSERT INTO users (
                            staff_id, nrc_number, rfid_card_id, full_name, email, phone, 
                            password_hash, role, employment_type, designation, department_id, 
                            hourly_rate, status
                        ) VALUES (
                            :sid, :nrc, :rfid, :name, :email, :phone, 
                            :pwd, :role, :emp, :desig, :dept, 
                            :rate, 'active'
                        ) RETURNING id, staff_id, full_name, email, role, employment_type
                    ");
                    $stmt->execute([
                        'sid'    => $staffId,
                        'nrc'    => !empty($nrc) ? $nrc : null,
                        'rfid'   => $rfid,
                        'name'   => $fullName,
                        'email'  => $email,
                        'phone'  => $phone,
                        'pwd'    => $pwdHash,
                        'role'   => $role,
                        'emp'    => $empType,
                        'desig'  => $designation,
                        'dept'   => $deptId,
                        'rate'   => $hourlyRate
                    ]);
                    $newUser = $stmt->fetch();

                    log_audit_trail('STAFF_ENROLLED', 'users', $newUser['id'], "New staff member enrolled: {$fullName} ({$staffId} - {$role}).", $newUser['id']);
                    send_notification($newUser['id'], 'Welcome to Evelyn Hone College!', 'Your account has been enrolled successfully. You can now clock in on premises and submit claims.', 'success');

                    // Auto-login
                    login_user($newUser);
                    set_flash_message('success', "Welcome to Evelyn Hone College, {$fullName}! Your account has been created.");
                    redirect_to_dashboard($newUser['role']);
                    exit;
                } catch (Exception $e) {
                    error_log("Enrollment error: " . $e->getMessage());
                    $error = 'Database error creating staff account: ' . $e->getMessage();
                }
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
  <title>Staff Enrollment | Evelyn Hone College Clocking System</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('frontend/assets/css/style.css') ?>">
</head>
<body class="auth-wrapper" style="padding: 40px 20px;">

  <div class="auth-card" style="max-width: 650px;">
    
    <div class="auth-brand">
      <a href="<?= base_url('index.php') ?>">
        <img src="<?= base_url('frontend/assets/images/ehc_logo.png') ?>" alt="Evelyn Hone College Emblem">
      </a>
      <h1>STAFF ENROLLMENT</h1>
      <p>Evelyn Hone College Clocking & Billing Platform</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <div><i class="fa-solid fa-circle-exclamation" style="margin-right:6px;"></i> <?= htmlspecialchars($error) ?></div>
        <button type="button" class="alert-close-btn">&times;</button>
      </div>
    <?php endif; ?>

    <form action="<?= base_url('register.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Staff ID <span class="required">*</span></label>
          <input type="text" name="staff_id" class="form-control" placeholder="e.g. EHC-LEC-105" required value="<?= htmlspecialchars($_POST['staff_id'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Full Name <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="e.g. Dr. John Tembo" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Official / Personal Email <span class="required">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="j.tembo@evelynhone.edu.zm" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" placeholder="+260 977 123456" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">NRC Number (National ID)</label>
          <input type="text" name="nrc_number" class="form-control" placeholder="e.g. 192837/11/1" value="<?= htmlspecialchars($_POST['nrc_number'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Designation / Title</label>
          <input type="text" name="designation" class="form-control" placeholder="e.g. Lecturer / Admin Officer" value="<?= htmlspecialchars($_POST['designation'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Staff Category / Role <span class="required">*</span></label>
          <select name="role" class="form-select" required>
            <option value="lecturer" <?= (($_POST['role'] ?? '') === 'lecturer') ? 'selected' : '' ?>>Lecturer (Academic Teaching Staff)</option>
            <option value="staff" <?= (($_POST['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Member of Staff (Administrative / Support)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Employment Type <span class="required">*</span></label>
          <select name="employment_type" class="form-select" required>
            <option value="full_time" <?= (($_POST['employment_type'] ?? '') === 'full_time') ? 'selected' : '' ?>>Full-Time Staff</option>
            <option value="part_time" <?= (($_POST['employment_type'] ?? '') === 'part_time') ? 'selected' : '' ?>>Part-Time Staff (Contract Hourly)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Academic School & Department <span class="required">*</span></label>
        <select name="department_id" class="form-select" required>
          <option value="">-- Select Your Department --</option>
          <?php foreach ($departmentsBySchool as $schoolName => $depts): ?>
            <optgroup label="<?= htmlspecialchars($schoolName) ?>">
              <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>" <?= (($_POST['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['dept_name']) ?> (HOD: <?= htmlspecialchars($d['hod_name']) ?>)
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Account Password <span class="required">*</span></label>
          <div class="input-icon-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" id="regPassword" class="form-control" placeholder="Min 6 characters" required>
            <i class="fa-regular fa-eye toggle-password" data-target="regPassword"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password <span class="required">*</span></label>
          <div class="input-icon-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" placeholder="Repeat password" required>
            <i class="fa-regular fa-eye toggle-password" data-target="regConfirmPassword"></i>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%; padding:13px; font-weight:700; font-size:1rem; margin-top:8px;">
        <i class="fa-solid fa-user-check"></i> Complete Staff Enrollment
      </button>
    </form>

    <div style="margin-top:20px; text-align:center; font-size:0.84rem; color:var(--text-muted);">
      Already have an enrolled account? <a href="<?= base_url('login.php') ?>" style="font-weight:700;">Sign In</a>
    </div>

  </div>

  <script src="<?= base_url('frontend/assets/js/app.js') ?>"></script>
</body>
</html>
