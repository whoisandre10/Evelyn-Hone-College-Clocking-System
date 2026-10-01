<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * System Settings & Institutional Parameters
 */

define('EHC_SYSTEM', true);
$pageTitle = 'System Settings';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed. Please try again.');
    } else {
        $allowedKeys = [
            'institution_name', 'institution_motto', 'currency_symbol', 
            'premise_latitude', 'premise_longitude', 'premise_radius_meters',
            'standard_daily_hours', 'overtime_rate_multiplier', 
            'invigilation_rate_per_hour', 'exam_marking_rate_per_script', 
            'extra_lecture_rate_per_hour', 'weekend_duty_flat_rate',
            'single_session_enforcement'
        ];

        foreach ($allowedKeys as $key) {
            if (isset($_POST[$key])) {
                $val = sanitize_input($_POST[$key]);
                $stmtUp = $db->prepare("UPDATE system_settings SET setting_value = :val, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :key");
                $stmtUp->execute(['val' => $val, 'key' => $key]);
            }
        }

        log_audit_trail('SETTINGS_UPDATED', 'system_settings', null, "Admin updated institutional parameters and rates.", $currentUser['id']);
        set_flash_message('success', 'System parameters and billing rates updated successfully.');
        header('Location: ' . base_url('frontend/views/admin/system_settings.php'));
        exit;
    }
}

// Fetch current settings
$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="card" style="border-top: 4px solid var(--ehc-primary);">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.8rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        INSTITUTIONAL CONFIGURATION
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">
        College Clocking & Billing Parameters
      </h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Configure baseline hourly claim rates, campus GPS geofence, and security rules.
      </p>
    </div>
  </div>
</div>

<form action="" method="POST">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(420px, 1fr)); gap:24px;">

    <!-- 1. Billing & Claims Standard Rates -->
    <div class="card">
      <div class="card-header-flex">
        <h3 class="card-title"><i class="fa-solid fa-coins"></i> Standard Claim & Overtime Rates (ZMW)</h3>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Currency Symbol <span class="required">*</span></label>
          <input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'ZMW') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Overtime Multiplier <span class="required">*</span></label>
          <input type="number" step="0.1" name="overtime_rate_multiplier" class="form-control" value="<?= htmlspecialchars($settings['overtime_rate_multiplier'] ?? '1.50') ?>" required>
          <small style="color:var(--text-muted);">e.g. 1.5 &times; base rate</small>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Invigilation Rate / Hour (ZMW) <span class="required">*</span></label>
          <input type="number" step="0.01" name="invigilation_rate_per_hour" class="form-control" value="<?= htmlspecialchars($settings['invigilation_rate_per_hour'] ?? '120.00') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Exam Marking / Script (ZMW) <span class="required">*</span></label>
          <input type="number" step="0.01" name="exam_marking_rate_per_script" class="form-control" value="<?= htmlspecialchars($settings['exam_marking_rate_per_script'] ?? '35.00') ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Extra Lecture Rate / Hour (ZMW) <span class="required">*</span></label>
          <input type="number" step="0.01" name="extra_lecture_rate_per_hour" class="form-control" value="<?= htmlspecialchars($settings['extra_lecture_rate_per_hour'] ?? '180.00') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Weekend Duty Flat Allowance (ZMW) <span class="required">*</span></label>
          <input type="number" step="0.01" name="weekend_duty_flat_rate" class="form-control" value="<?= htmlspecialchars($settings['weekend_duty_flat_rate'] ?? '250.00') ?>" required>
        </div>
      </div>
    </div>

    <!-- 2. Campus Premises Geofence & Location -->
    <div class="card">
      <div class="card-header-flex">
        <h3 class="card-title"><i class="fa-solid fa-map-location-dot"></i> Campus Premises Boundary</h3>
      </div>

      <div class="form-group">
        <label class="form-label">College Campus Name</label>
        <input type="text" name="institution_name" class="form-control" value="<?= htmlspecialchars($settings['institution_name'] ?? 'Evelyn Hone College') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Premises Latitude (GPS)</label>
          <input type="text" name="premise_latitude" class="form-control" value="<?= htmlspecialchars($settings['premise_latitude'] ?? '-15.421528') ?>">
          <small style="color:var(--text-muted);">Church Rd / Dushambe Rd center point</small>
        </div>

        <div class="form-group">
          <label class="form-label">Premises Longitude (GPS)</label>
          <input type="text" name="premise_longitude" class="form-control" value="<?= htmlspecialchars($settings['premise_longitude'] ?? '28.293319') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Verification Radius (Meters)</label>
          <input type="number" name="premise_radius_meters" class="form-control" value="<?= htmlspecialchars($settings['premise_radius_meters'] ?? '1000') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Single Session Enforcement</label>
          <select name="single_session_enforcement" class="form-select">
            <option value="enabled" <?= ($settings['single_session_enforcement'] ?? '') === 'enabled' ? 'selected' : '' ?>>Enabled (Terminates older sessions)</option>
            <option value="disabled" <?= ($settings['single_session_enforcement'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
          </select>
        </div>
      </div>
    </div>

  </div>

  <div style="margin-top:20px; text-align:right;">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fa-solid fa-floppy-disk"></i> Save System Parameters
    </button>
  </div>
</form>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
