<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Multi-Level Timesheet Approval Workflow Desk
 * Stage 1: HOD -> Stage 2: HR -> Stage 3: Finance
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Timesheet Approval Workflow';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$deptFilter = $_GET['department_id'] ?? 'all';

$sql = "
    SELECT ts.*, 
           u.full_name, u.staff_id, u.role, u.employment_type, u.hourly_rate,
           d.name AS dept_name, d.hod_name, s.name AS school_name,
           u_hod.full_name AS hod_reviewer_name,
           u_hr.full_name AS hr_reviewer_name,
           u_fin.full_name AS fin_reviewer_name
    FROM timesheets ts
    JOIN users u ON ts.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN schools s ON d.school_id = s.id
    LEFT JOIN users u_hod ON ts.hod_approved_by = u_hod.id
    LEFT JOIN users u_hr ON ts.hr_approved_by = u_hr.id
    LEFT JOIN users u_fin ON ts.finance_approved_by = u_fin.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND ts.overall_status = :st";
    $params['st'] = $statusFilter;
}

if ($deptFilter !== 'all') {
    $sql .= " AND u.department_id = :did";
    $params['did'] = (int)$deptFilter;
}

$sql .= " ORDER BY ts.period_end DESC, ts.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$timesheets = $stmt->fetchAll();

$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">Multi-Level Timesheet Verification & Approval</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Institutional timesheet routing: <strong>Stage 1 (HOD)</strong> &rarr; <strong>Stage 2 (HR)</strong> &rarr; <strong>Stage 3 (Finance)</strong>
      </p>
    </div>

    <button type="button" class="btn btn-secondary" onclick="window.print();">
      <i class="fa-solid fa-print"></i> Print Summary
    </button>
  </div>

  <!-- Filter Ribbon -->
  <form action="" method="GET" class="no-print" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Workflow Status</label>
        <select name="status" class="form-select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Timesheets</option>
          <option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Stage 1: Awaiting HOD Review</option>
          <option value="under_review" <?= $statusFilter === 'under_review' ? 'selected' : '' ?>>Stage 2/3: HR / Finance In Progress</option>
          <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Stage 3 Authorized (Payroll Ready)</option>
          <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected Submissions</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Academic / Admin Department</label>
        <select name="department_id" class="form-select">
          <option value="all">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= base_url('frontend/views/admin/timesheets_approval.php') ?>" class="btn btn-secondary">Clear</a>
      </div>
    </div>
  </form>

  <!-- Timesheets Master Table -->
  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Timesheet</th>
          <th>Staff Details</th>
          <th>Department & School</th>
          <th>Period Covered</th>
          <th>Total Hours</th>
          <th>Claims Value</th>
          <th>Gross Billing</th>
          <th>Approval Stages</th>
          <th class="no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($timesheets)): ?>
          <tr class="empty-row"><td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No timesheets found matching criteria.</td></tr>
        <?php else: ?>
          <?php foreach ($timesheets as $ts): ?>
            <tr>
              <td>
                <strong style="font-family:monospace; color:var(--ehc-dark);">#TS-<?= $ts['id'] ?></strong><br>
                <small style="color:var(--text-muted);"><?= format_date($ts['created_at']) ?></small>
              </td>
              <td>
                <strong><?= htmlspecialchars($ts['full_name']) ?></strong><br>
                <span style="font-size:0.75rem; color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($ts['staff_id']) ?></span> &bull;
                <span class="badge <?= $ts['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>" style="font-size:0.68rem;">
                  <?= ucfirst($ts['role']) ?>
                </span>
              </td>
              <td>
                <div style="font-weight:600; font-size:0.85rem;"><?= htmlspecialchars($ts['dept_name'] ?? 'General') ?></div>
                <div style="font-size:0.75rem; color:var(--text-muted);">HOD: <?= htmlspecialchars($ts['hod_name'] ?? 'Assigned HOD') ?></div>
              </td>
              <td>
                <strong><?= format_date($ts['period_start']) ?></strong><br>
                &rarr; <?= format_date($ts['period_end']) ?>
              </td>
              <td>
                <strong><?= number_format($ts['total_clocked_hours'], 2) ?> hrs</strong><br>
                <small style="color:var(--text-muted);"><?= $ts['regular_hours'] ?> reg / <?= $ts['overtime_hours'] ?> OT</small>
              </td>
              <td style="color:var(--ehc-primary); font-weight:700;">
                <?= format_currency($ts['claims_total_amount']) ?>
              </td>
              <td style="font-weight:800; font-size:1.05rem; color:var(--ehc-dark);">
                <?= format_currency($ts['gross_billing_amount']) ?>
              </td>
              <td style="min-width:260px;">
                <!-- 3 Stage Badges -->
                <div style="display:flex; flex-direction:column; gap:4px; font-size:0.75rem;">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Stage 1 (HOD):</span>
                    <span class="badge <?= $ts['hod_status'] === 'approved' ? 'badge-success' : ($ts['hod_status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>">
                      <?= ucfirst($ts['hod_status']) ?> <?= !empty($ts['hod_reviewer_name']) ? "({$ts['hod_reviewer_name']})" : '' ?>
                    </span>
                  </div>
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Stage 2 (HR):</span>
                    <span class="badge <?= $ts['hr_status'] === 'approved' ? 'badge-success' : ($ts['hr_status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>">
                      <?= ucfirst($ts['hr_status']) ?> <?= !empty($ts['hr_reviewer_name']) ? "({$ts['hr_reviewer_name']})" : '' ?>
                    </span>
                  </div>
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Stage 3 (Finance):</span>
                    <span class="badge <?= $ts['finance_status'] === 'approved' ? 'badge-success' : ($ts['finance_status'] === 'rejected' ? 'badge-danger' : 'badge-warning') ?>">
                      <?= ucfirst($ts['finance_status']) ?> <?= !empty($ts['fin_reviewer_name']) ? "({$ts['fin_reviewer_name']})" : '' ?>
                    </span>
                  </div>
                </div>

                <?php if (!empty($ts['hod_comment']) || !empty($ts['hr_comment']) || !empty($ts['finance_comment'])): ?>
                  <div style="font-size:0.72rem; color:var(--text-muted); margin-top:4px; background:var(--bg-subtle); padding:4px 6px; border-radius:4px;">
                    <?= htmlspecialchars($ts['finance_comment'] ?? $ts['hr_comment'] ?? $ts['hod_comment']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="no-print">
                <div style="display:flex; flex-direction:column; gap:6px;">
                  <?php if ($ts['hod_status'] === 'pending'): ?>
                    <!-- Approve as HOD -->
                    <button type="button" class="btn btn-warning btn-sm" onclick='openWorkflowModal(<?= $ts['id'] ?>, "approve_hod", "Stage 1: HOD Signoff")'>
                      <i class="fa-solid fa-check"></i> Sign HOD
                    </button>
                  <?php elseif ($ts['hr_status'] === 'pending'): ?>
                    <!-- Approve as HR -->
                    <button type="button" class="btn btn-info btn-sm" onclick='openWorkflowModal(<?= $ts['id'] ?>, "approve_hr", "Stage 2: HR Verification")'>
                      <i class="fa-solid fa-check"></i> Verify HR
                    </button>
                  <?php elseif ($ts['finance_status'] === 'pending'): ?>
                    <!-- Approve as Finance -->
                    <button type="button" class="btn btn-success btn-sm" onclick='openWorkflowModal(<?= $ts['id'] ?>, "approve_finance", "Stage 3: Finance Authorization")'>
                      <i class="fa-solid fa-stamp"></i> Authorize Payroll
                    </button>
                  <?php else: ?>
                    <span class="badge badge-success" style="text-align:center;"><i class="fa-solid fa-check-double"></i> Complete</span>
                  <?php endif; ?>

                  <?php if ($ts['overall_status'] !== 'rejected' && $ts['overall_status'] !== 'approved'): ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick='openRejectModal(<?= $ts['id'] ?>)' title="Reject Timesheet">
                      <i class="fa-solid fa-xmark"></i> Reject
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= APPROVE WORKFLOW MODAL ================= -->
<div class="modal-backdrop" id="workflowModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" id="workflowModalTitle">Timesheet Approval</h3>
      <button type="button" class="modal-close" onclick="closeModal('workflowModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/timesheet_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" id="workflowAction">
      <input type="hidden" name="timesheet_id" id="workflowTsId">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/timesheets_approval.php') ?>">

      <div class="modal-body">
        <p style="font-size:0.86rem; color:var(--text-muted); margin-bottom:16px;">
          Confirming this action stamps your administrative signature on the timesheet for this departmental approval stage.
        </p>

        <div class="form-group">
          <label class="form-label">Reviewer Verification Note / Remarks <span class="required">*</span></label>
          <textarea name="comment" class="form-control" placeholder="e.g. Hours verified against timetable and biometric logs. Approved for payroll." required>Verified and authorized.</textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('workflowModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Sign & Authorize</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= REJECT WORKFLOW MODAL ================= -->
<div class="modal-backdrop" id="rejectModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" style="color:#EF4444;"><i class="fa-solid fa-triangle-exclamation"></i> Reject Timesheet Submission</h3>
      <button type="button" class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/timesheet_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="reject_timesheet">
      <input type="hidden" name="timesheet_id" id="rejectTsId">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/timesheets_approval.php') ?>">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Rejection Stage <span class="required">*</span></label>
          <select name="stage" class="form-select" required>
            <option value="hod">HOD Review Stage</option>
            <option value="hr">HR Verification Stage</option>
            <option value="finance">Finance Authorization Stage</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Reason for Rejection (Visible to staff member) <span class="required">*</span></label>
          <textarea name="rejection_reason" class="form-control" placeholder="Specify why the timesheet is rejected (e.g. hours discrepancy with gate log, unverified claim attached)." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openWorkflowModal(tsId, actionName, title) {
    document.getElementById('workflowTsId').value = tsId;
    document.getElementById('workflowAction').value = actionName;
    document.getElementById('workflowModalTitle').textContent = title;
    openModal('workflowModal');
  }

  function openRejectModal(tsId) {
    document.getElementById('rejectTsId').value = tsId;
    openModal('rejectModal');
  }
</script>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
