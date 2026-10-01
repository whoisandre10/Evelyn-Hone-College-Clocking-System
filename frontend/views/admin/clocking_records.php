<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Admin All Clocking Records & Campus Gate Logs
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Master Clocking Records';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Handle Manual Time Adjustment from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_record') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $recordId = (int)$_POST['record_id'];
        $timeIn = $_POST['clock_in'];
        $timeOut = !empty($_POST['clock_out']) ? $_POST['clock_out'] : null;
        $notes = sanitize_input($_POST['notes'] ?? 'Administrative Adjustment');

        $tIn = strtotime($timeIn);
        $tOut = $timeOut ? strtotime($timeOut) : null;
        $hours = ($tOut && $tIn) ? max(0, round(($tOut - $tIn) / 3600, 2)) : 0.00;

        $stmtUp = $db->prepare("
            UPDATE clocking_records 
            SET clock_in = :cin, clock_out = :cout, total_hours = :hrs, notes = :notes, status = 'adjusted' 
            WHERE id = :id
        ");
        $stmtUp->execute([
            'cin'   => $timeIn,
            'cout'  => $timeOut,
            'hrs'   => $hours,
            'notes' => $notes,
            'id'    => $recordId
        ]);

        log_audit_trail('CLOCK_ADJUSTED', 'clocking_records', $recordId, "Admin adjusted record #{$recordId} to {$hours} hrs. Note: {$notes}", $currentUser['id']);
        set_flash_message('success', "Clocking record #{$recordId} has been adjusted.");
        header('Location: ' . base_url('frontend/views/admin/clocking_records.php'));
        exit;
    }
}

// Search & Filter
$searchQuery = trim($_GET['search'] ?? '');
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$roleFilter = $_GET['role'] ?? 'all';
$gateFilter = $_GET['gate'] ?? 'all';

$sql = "
    SELECT c.*, u.full_name, u.staff_id, u.role, u.employment_type, d.name AS dept_name
    FROM clocking_records c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE c.clock_date >= :sdate AND c.clock_date <= :edate
";
$params = ['sdate' => $startDate, 'edate' => $endDate];

if ($roleFilter !== 'all') {
    $sql .= " AND u.role = :role";
    $params['role'] = $roleFilter;
}

if ($gateFilter !== 'all') {
    $sql .= " AND c.entry_gate = :gate";
    $params['gate'] = $gateFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(u.full_name) LIKE :q OR LOWER(u.staff_id) LIKE :q)";
    $params['q'] = '%' . strtolower($searchQuery) . '%';
}

$sql .= " ORDER BY c.clock_in DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$totalHoursInFilter = 0;
foreach ($records as $r) {
    $totalHoursInFilter += (float)$r['total_hours'];
}
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">College Premise Attendance & Gate Logs</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Comprehensive log of all staff arrivals, departures, gate readers, and verified hours.
      </p>
    </div>

    <div style="display:flex; gap:10px;">
      <a href="<?= base_url('frontend/views/terminal/tap_terminal.php') ?>" class="btn btn-secondary" target="_blank">
        <i class="fa-solid fa-satellite-dish" style="color:var(--ehc-cyan);"></i> Open Tap Reader
      </a>
      <button type="button" class="btn btn-primary" onclick="window.print();">
        <i class="fa-solid fa-print"></i> Print Report
      </button>
    </div>
  </div>

  <!-- Filter Form -->
  <form action="" method="GET" class="no-print" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Search Staff</label>
        <input type="text" name="search" class="form-control" placeholder="Staff Name or ID..." value="<?= htmlspecialchars($searchQuery) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">From Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">To Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Staff</option>
          <option value="lecturer" <?= $roleFilter === 'lecturer' ? 'selected' : '' ?>>Lecturers Only</option>
          <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Members of Staff</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
        <a href="<?= base_url('frontend/views/admin/clocking_records.php') ?>" class="btn btn-secondary">Reset</a>
      </div>
    </div>
  </form>

  <!-- Summary Banner -->
  <div style="display:flex; justify-content:space-between; align-items:center; background:#EFF6FF; border:1px solid #BFDBFE; padding:12px 18px; border-radius:var(--radius-md); margin-bottom:20px; flex-wrap:wrap; gap:10px;">
    <div>
      <span style="font-size:0.85rem; color:#1E40AF; font-weight:700;">Filtered Cumulative Clocked Hours:</span>
      <span style="font-size:1.3rem; font-weight:800; color:#1D4ED8; margin-left:8px;"><?= number_format($totalHoursInFilter, 2) ?> Hours</span>
    </div>
    <div style="font-size:0.82rem; color:#1E40AF;">
      Displaying <strong><?= count($records) ?></strong> clocking records
    </div>
  </div>

  <div class="table-responsive">
    <table class="table-custom table-searchable">
      <thead>
        <tr>
          <th>Staff Member</th>
          <th>Role / Category</th>
          <th>Department</th>
          <th>Date</th>
          <th>Gate / Terminal</th>
          <th>Clock In</th>
          <th>Clock Out</th>
          <th>Hours</th>
          <th>Status</th>
          <th class="no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($records)): ?>
          <tr class="empty-row"><td colspan="10" style="text-align:center; padding:40px; color:var(--text-muted);">No clocking records found for this period.</td></tr>
        <?php else: ?>
          <?php foreach ($records as $r): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($r['full_name']) ?></strong><br>
                <small style="color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($r['staff_id']) ?></small>
              </td>
              <td>
                <span class="badge <?= $r['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>">
                  <?= ucfirst($r['role']) ?>
                </span><br>
                <small style="color:var(--text-muted);"><?= str_replace('_', ' ', $r['employment_type']) ?></small>
              </td>
              <td><?= htmlspecialchars($r['dept_name'] ?? 'General') ?></td>
              <td><strong><?= format_date($r['clock_date']) ?></strong></td>
              <td>
                <small><?= htmlspecialchars($r['entry_gate'] ?? 'Main Gate') ?></small><br>
                <span style="font-size:0.72rem; color:var(--text-muted);"><?= str_replace('_', ' ', $r['clock_in_method']) ?></span>
              </td>
              <td><span style="color:#10B981; font-weight:700;"><?= format_time($r['clock_in']) ?></span></td>
              <td>
                <?php if (!empty($r['clock_out'])): ?>
                  <span style="color:#F59E0B; font-weight:700;"><?= format_time($r['clock_out']) ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><span class="status-pulse-dot pulse" style="display:inline-block; margin-right:4px;"></span> Active Now</span>
                <?php endif; ?>
              </td>
              <td style="font-weight:800; color:var(--ehc-dark);"><?= number_format($r['total_hours'], 2) ?> hrs</td>
              <td>
                <?php if ($r['status'] === 'completed'): ?>
                  <span class="badge badge-success">Completed</span>
                <?php elseif ($r['status'] === 'in_progress'): ?>
                  <span class="badge badge-warning">On Campus</span>
                <?php elseif ($r['status'] === 'adjusted'): ?>
                  <span class="badge badge-info">Adjusted</span>
                <?php else: ?>
                  <span class="badge badge-secondary"><?= ucfirst($r['status']) ?></span>
                <?php endif; ?>
              </td>
              <td class="no-print">
                <button type="button" class="btn btn-secondary btn-sm" onclick='openAdjustModal(<?= json_encode($r) ?>)' title="Adjust Time Record">
                  <i class="fa-solid fa-sliders"></i> Adjust
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= ADJUST RECORD MODAL ================= -->
<div class="modal-backdrop" id="adjustModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-sliders" style="color:var(--ehc-primary);"></i> Adjust Attendance Record</h3>
      <button type="button" class="modal-close" onclick="closeModal('adjustModal')">&times;</button>
    </div>
    <form action="<?= base_url('frontend/views/admin/clocking_records.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="adjust_record">
      <input type="hidden" name="record_id" id="adjRecordId">

      <div class="modal-body">
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
          Adjusting staff arrival or departure times will be stamped in the institutional audit trail for compliance.
        </p>

        <div class="form-group">
          <label class="form-label">Staff Member</label>
          <input type="text" id="adjStaffName" class="form-control" readonly style="background:var(--bg-subtle); font-weight:700;">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Clock-In Timestamp <span class="required">*</span></label>
            <input type="text" name="clock_in" id="adjClockIn" class="form-control" required placeholder="YYYY-MM-DD HH:MM:SS">
          </div>

          <div class="form-group">
            <label class="form-label">Clock-Out Timestamp</label>
            <input type="text" name="clock_out" id="adjClockOut" class="form-control" placeholder="YYYY-MM-DD HH:MM:SS">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Reason for Adjustment <span class="required">*</span></label>
          <textarea name="notes" class="form-control" placeholder="e.g. Card reader hardware delay verified by Campus Security" required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Adjustment</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAdjustModal(r) {
    document.getElementById('adjRecordId').value = r.id;
    document.getElementById('adjStaffName').value = r.full_name + ' (' + r.staff_id + ')';
    document.getElementById('adjClockIn').value = r.clock_in;
    document.getElementById('adjClockOut').value = r.clock_out || '';
    openModal('adjustModal');
  }
</script>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
