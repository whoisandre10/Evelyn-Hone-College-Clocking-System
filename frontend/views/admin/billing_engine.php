<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Automated Staff Billing & Payroll Calculation Engine
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Automated Billing Calculation Engine';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Period Selection
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$targetStaffId = $_GET['staff_id'] ?? 'all';
$deptFilter = $_GET['department_id'] ?? 'all';

// Fetch candidate staff list
$sqlStaff = "SELECT u.id, u.staff_id, u.full_name, u.role, u.employment_type, u.hourly_rate, d.name AS dept_name, s.name AS school_name FROM users u LEFT JOIN departments d ON u.department_id = d.id LEFT JOIN schools s ON d.school_id = s.id WHERE u.status = 'active'";
$params = [];

if ($targetStaffId !== 'all') {
    $sqlStaff .= " AND u.id = :uid";
    $params['uid'] = (int)$targetStaffId;
}

if ($deptFilter !== 'all') {
    $sqlStaff .= " AND u.department_id = :did";
    $params['did'] = (int)$deptFilter;
}

$sqlStaff .= " ORDER BY u.role, u.full_name ASC";
$stmt = $db->prepare($sqlStaff);
$stmt->execute($params);
$staffList = $stmt->fetchAll();

// Execute Automated Billing Engine for each staff member in filtered list
$billingResults = [];
$grandTotalRegularHours = 0;
$grandTotalOvertimeHours = 0;
$grandTotalBasePay = 0;
$grandTotalOvertimePay = 0;
$grandTotalClaims = 0;
$grandTotalPayable = 0;

foreach ($staffList as $stf) {
    try {
        $b = calculate_staff_billing($stf['id'], $startDate, $endDate);
        $billingResults[] = $b;

        $grandTotalRegularHours += $b['regular_hours'];
        $grandTotalOvertimeHours += $b['overtime_hours'];
        $grandTotalBasePay += $b['base_pay'];
        $grandTotalOvertimePay += $b['overtime_pay'];
        $grandTotalClaims += $b['claims_total'];
        $grandTotalPayable += $b['gross_billing_amount'];
    } catch (Exception $e) {
        error_log("Billing calc failed for user {$stf['id']}: " . $e->getMessage());
    }
}

// All departments and staff for dropdowns
$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$allUsers = $db->query("SELECT id, full_name, staff_id, role FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();
?>

<!-- Header -->
<div class="card" style="border-top: 4px solid var(--ehc-primary);">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.8rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        PAYROLL ACCURACY & INSTITUTIONAL ACCOUNTABILITY
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">
        Automated Billing & Staff Hours Calculation Engine
      </h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Computes accurate payable amounts based on biometric clocking, overtime multipliers, and verified claims for Evelyn Hone College staff.
      </p>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="button" class="btn btn-secondary" onclick="window.print();">
        <i class="fa-solid fa-print"></i> Print Payroll Schedule
      </button>
    </div>
  </div>
</div>

<!-- Parameter Filter Ribbon -->
<form action="" method="GET" class="no-print" style="background:var(--bg-subtle); padding:18px; border-radius:var(--radius-lg); border:1px solid var(--border-color); margin-bottom:24px;">
  <div class="form-row" style="align-items:flex-end;">
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Billing Cycle Start <span class="required">*</span></label>
      <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Billing Cycle End <span class="required">*</span></label>
      <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Department Scope</label>
      <select name="department_id" class="form-select">
        <option value="all">Entire College (All Departments)</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Specific Staff Member</label>
      <select name="staff_id" class="form-select">
        <option value="all">All Enrolled Staff</option>
        <?php foreach ($allUsers as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $targetStaffId == $u['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['staff_id']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
      <button type="submit" class="btn btn-primary" style="flex:1;">
        <i class="fa-solid fa-calculator"></i> Run Billing Engine
      </button>
      <a href="<?= base_url('frontend/views/admin/billing_engine.php') ?>" class="btn btn-secondary">Reset</a>
    </div>
  </div>
</form>

<!-- Institutional Billing Total KPI Cards -->
<div class="stats-grid">
  <div class="stat-card" style="--stat-color:#10B981; --stat-bg:#ECFDF5;">
    <div class="stat-icon"><i class="fa-solid fa-money-check-dollar"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($grandTotalPayable) ?></div>
      <div class="stat-label">Total Institutional Gross Billing</div>
      <div class="stat-subtext"><?= count($billingResults) ?> staff evaluated for this cycle</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:#3B82F6; --stat-bg:#EFF6FF;">
    <div class="stat-icon"><i class="fa-solid fa-business-time"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= number_format($grandTotalRegularHours + $grandTotalOvertimeHours, 1) ?> hrs</div>
      <div class="stat-label">Total Verified Hours Worked</div>
      <div class="stat-subtext"><?= number_format($grandTotalRegularHours, 1) ?> regular &bull; <?= number_format($grandTotalOvertimeHours, 1) ?> overtime</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:var(--ehc-primary); --stat-bg:var(--ehc-primary-soft);">
    <div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($grandTotalClaims) ?></div>
      <div class="stat-label">Academic & Duty Claims Total</div>
      <div class="stat-subtext">Invigilation, exam marking & duty allowances</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:#6366F1; --stat-bg:#EEF2FF;">
    <div class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($grandTotalBasePay + $grandTotalOvertimePay) ?></div>
      <div class="stat-label">Base Hourly Wage Total</div>
      <div class="stat-subtext"><?= format_currency($grandTotalOvertimePay) ?> overtime premium</div>
    </div>
  </div>
</div>

<!-- Detailed Billing Breakdown Table -->
<div class="card">
  <div class="card-header-flex">
    <div>
      <h3 class="card-title">
        <i class="fa-solid fa-table-list" style="color:var(--ehc-primary);"></i> Staff Billing Breakdown Schedule
      </h3>
      <span style="font-size:0.82rem; color:var(--text-muted);">Period: <?= format_date($startDate) ?> &rarr; <?= format_date($endDate) ?></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Staff Member</th>
          <th>Role / Category</th>
          <th>Department</th>
          <th>Days</th>
          <th>Regular Hrs</th>
          <th>OT Hrs</th>
          <th>Base Rate</th>
          <th>Base Pay</th>
          <th>OT Pay</th>
          <th>Claims Total</th>
          <th>Gross Payable</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($billingResults)): ?>
          <tr class="empty-row"><td colspan="11" style="text-align:center; padding:40px; color:var(--text-muted);">No staff records evaluated.</td></tr>
        <?php else: ?>
          <?php foreach ($billingResults as $b): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($b['user']['full_name']) ?></strong><br>
                <small style="font-family:monospace; color:var(--text-muted);"><?= htmlspecialchars($b['user']['staff_id']) ?></small>
              </td>
              <td>
                <span class="badge <?= $b['user']['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>">
                  <?= ucfirst($b['user']['role']) ?>
                </span><br>
                <small style="color:var(--text-muted);"><?= str_replace('_', ' ', $b['user']['employment_type']) ?></small>
              </td>
              <td><?= htmlspecialchars($b['user']['department_name'] ?? 'General') ?></td>
              <td style="text-align:center;"><?= $b['days_worked'] ?></td>
              <td><?= number_format($b['regular_hours'], 2) ?></td>
              <td>
                <?php if ($b['overtime_hours'] > 0): ?>
                  <span style="color:#F59E0B; font-weight:700;">+<?= number_format($b['overtime_hours'], 2) ?></span>
                <?php else: ?>
                  0.00
                <?php endif; ?>
              </td>
              <td><?= format_currency($b['hourly_rate']) ?></td>
              <td><?= format_currency($b['base_pay']) ?></td>
              <td><?= format_currency($b['overtime_pay']) ?></td>
              <td style="color:var(--ehc-primary); font-weight:700;">
                <?= format_currency($b['claims_total']) ?>
                <?php if (!empty($b['claims_breakdown'])): ?>
                  <div style="font-size:0.72rem; color:var(--text-muted);">
                    (<?= count($b['claims_breakdown']) ?> claim types)
                  </div>
                <?php endif; ?>
              </td>
              <td style="font-weight:800; font-size:1.05rem; color:var(--ehc-dark);">
                <?= format_currency($b['gross_billing_amount']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg-subtle); font-weight:800; border-top:2px solid var(--border-color);">
          <td colspan="4" style="text-transform:uppercase;">Institutional Total:</td>
          <td><?= number_format($grandTotalRegularHours, 2) ?></td>
          <td><?= number_format($grandTotalOvertimeHours, 2) ?></td>
          <td>-</td>
          <td><?= format_currency($grandTotalBasePay) ?></td>
          <td><?= format_currency($grandTotalOvertimePay) ?></td>
          <td style="color:var(--ehc-primary);"><?= format_currency($grandTotalClaims) ?></td>
          <td style="color:var(--ehc-primary); font-size:1.15rem;"><?= format_currency($grandTotalPayable) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
