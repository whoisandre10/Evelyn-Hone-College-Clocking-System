<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Staff Shift History & Attendance Logs
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Staff Clocking Records';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('staff');

$db = get_db();
$userId = $currentUser['id'];

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? 'all';

$sql = "
    SELECT * FROM clocking_records 
    WHERE user_id = :uid 
      AND clock_date >= :sdate 
      AND clock_date <= :edate
";
$params = ['uid' => $userId, 'sdate' => $startDate, 'edate' => $endDate];

if ($statusFilter !== 'all') {
    $sql .= " AND status = :status";
    $params['status'] = $statusFilter;
}

$sql .= " ORDER BY clock_in DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$totalFilteredHours = 0;
foreach ($records as $r) {
    $totalFilteredHours += (float)$r['total_hours'];
}
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">My Shift Attendance & Gate Records</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Audited log of on-premise time recordings at Evelyn Hone College
      </p>
    </div>

    <div>
      <?php if (!$clockStatus['is_clocked_in']): ?>
        <form action="<?= base_url('backend/actions/clock_action.php') ?>" method="POST" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
          <input type="hidden" name="action" value="clock_in">
          <input type="hidden" name="entry_gate" value="Dushambe Rd Gate">
          <input type="hidden" name="redirect" value="<?= base_url('frontend/views/staff/clocking.php') ?>">
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-right-to-bracket"></i> Clock In Now
          </button>
        </form>
      <?php else: ?>
        <form action="<?= base_url('backend/actions/clock_action.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Confirm Clock-Out?');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
          <input type="hidden" name="action" value="clock_out">
          <input type="hidden" name="redirect" value="<?= base_url('frontend/views/staff/clocking.php') ?>">
          <button type="submit" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket"></i> Clock Out Now
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filter Controls -->
  <form action="" method="GET" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Shift Status</label>
        <select name="status" class="form-select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Records</option>
          <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed Shifts</option>
          <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress (Active)</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= base_url('frontend/views/staff/clocking.php') ?>" class="btn btn-secondary">Reset</a>
      </div>
    </div>
  </form>

  <div style="display:flex; justify-content:space-between; align-items:center; background:#ECFDF5; border:1px solid #A7F3D0; padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; flex-wrap:wrap; gap:10px;">
    <div>
      <span style="font-size:0.85rem; color:#065F46; font-weight:700;">Total Clocked Hours in Period:</span>
      <span style="font-size:1.25rem; font-weight:800; color:#047857; margin-left:8px;"><?= number_format($totalFilteredHours, 2) ?> Hours</span>
    </div>
    <div style="font-size:0.82rem; color:#065F46;">
      Showing <?= count($records) ?> records
    </div>
  </div>

  <div class="table-responsive">
    <table class="table-custom table-searchable">
      <thead>
        <tr>
          <th>Shift Date</th>
          <th>Campus Gate</th>
          <th>Clock-In Method</th>
          <th>Time In</th>
          <th>Time Out</th>
          <th>Total Hours</th>
          <th>Premise Verification</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($records)): ?>
          <tr class="empty-row">
            <td colspan="8" style="text-align:center; padding:40px; color:var(--text-muted);">
              No shift records found for the selected dates.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($records as $row): ?>
            <tr>
              <td><strong><?= format_date($row['clock_date']) ?></strong></td>
              <td><?= htmlspecialchars($row['entry_gate'] ?? 'Main Gate - Church Rd') ?></td>
              <td><span class="badge badge-secondary"><?= str_replace('_', ' ', htmlspecialchars($row['clock_in_method'])) ?></span></td>
              <td><span style="color:#10B981; font-weight:700;"><?= format_time($row['clock_in']) ?></span></td>
              <td>
                <?php if (!empty($row['clock_out'])): ?>
                  <span style="color:#F59E0B; font-weight:700;"><?= format_time($row['clock_out']) ?></span>
                <?php else: ?>
                  <span class="badge badge-success">Active Now</span>
                <?php endif; ?>
              </td>
              <td style="font-weight:700;"><?= number_format($row['total_hours'], 2) ?> hrs</td>
              <td><span class="badge badge-success"><i class="fa-solid fa-satellite"></i> Verified On Site</span></td>
              <td>
                <?php if ($row['status'] === 'completed'): ?>
                  <span class="badge badge-success">Completed</span>
                <?php elseif ($row['status'] === 'in_progress'): ?>
                  <span class="badge badge-warning">On Shift</span>
                <?php else: ?>
                  <span class="badge badge-info"><?= ucfirst($row['status']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
