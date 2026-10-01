<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Staff Profile & Security Management
 */

define('EHC_SYSTEM', true);
$pageTitle = 'My Profile';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('staff');
?>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:24px;">

  <!-- Profile Details Card -->
  <div class="card">
    <div class="card-header-flex">
      <h3 class="card-title"><i class="fa-solid fa-id-card"></i> Staff Member Details</h3>
    </div>

    <form action="<?= base_url('backend/actions/user_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="update_profile">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/staff/profile.php') ?>">

      <div class="form-group">
        <label class="form-label">Staff ID</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['staff_id']) ?>" readonly style="background:var(--bg-subtle); font-family:monospace; font-weight:700;">
      </div>

      <div class="form-group">
        <label class="form-label">Gate RFID Token ID</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['rfid_card_id'] ?? 'Unassigned') ?>" readonly style="background:var(--bg-subtle); font-family:monospace;">
      </div>

      <div class="form-group">
        <label class="form-label">Full Name <span class="required">*</span></label>
        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($currentUser['full_name']) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Official Email</label>
        <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly style="background:var(--bg-subtle);">
      </div>

      <div class="form-group">
        <label class="form-label">Phone Contact</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">NRC Number (National Registration)</label>
        <input type="text" name="nrc_number" class="form-control" value="<?= htmlspecialchars($currentUser['nrc_number'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Department & Assigned Wing</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['department_name'] ?? 'General Institutional Staff') ?>" readonly style="background:var(--bg-subtle);">
      </div>

      <div class="form-group">
        <label class="form-label">Category & Base Rate</label>
        <input type="text" class="form-control" value="<?= str_replace('_', ' ', ucfirst($currentUser['employment_type'])) ?> &bull; Hourly Rate: <?= format_currency($currentUser['hourly_rate']) ?>" readonly style="background:var(--bg-subtle);">
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:8px;">
        <i class="fa-solid fa-save"></i> Save Profile Details
      </button>
    </form>
  </div>

  <!-- Security & Password Change Card -->
  <div class="card">
    <div class="card-header-flex">
      <h3 class="card-title"><i class="fa-solid fa-shield-halved"></i> Security & Password</h3>
    </div>

    <form action="<?= base_url('backend/actions/user_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/staff/profile.php') ?>">

      <div class="form-group">
        <label class="form-label">Current Password <span class="required">*</span></label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="current_password" id="curPwd" class="form-control" placeholder="Enter current password" required>
          <i class="fa-regular fa-eye toggle-password" data-target="curPwd"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">New Password <span class="required">*</span></label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-key"></i>
          <input type="password" name="new_password" id="newPwd" class="form-control" placeholder="Min 6 characters" required>
          <i class="fa-regular fa-eye toggle-password" data-target="newPwd"></i>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm New Password <span class="required">*</span></label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-key"></i>
          <input type="password" name="confirm_password" id="confPwd" class="form-control" placeholder="Repeat new password" required>
          <i class="fa-regular fa-eye toggle-password" data-target="confPwd"></i>
        </div>
      </div>

      <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:var(--radius-md); padding:12px; font-size:0.8rem; color:#92400E; margin-bottom:20px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong>Single Session Notice:</strong> Updating your password invalidates older sessions across other browsers or mobile phones.
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-lock-open"></i> Update Password
      </button>
    </form>
  </div>

</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
