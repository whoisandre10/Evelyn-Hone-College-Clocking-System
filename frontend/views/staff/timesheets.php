<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Staff Timesheets & Self-Service Transparency Dashboard
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Staff Timesheets';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('staff');

$db = get_db();
$userId = $currentUser['id'];

// Fetch all submitted timesheets for this user
$stmtTS = $db->prepare("
    SELECT ts.*, 
           u_hod.full_name AS hod_reviewer_name,
           u_hr.full_name AS hr_reviewer_name,
           u_fin.full_name AS fin_reviewer_name
    FROM timesheets ts
    LEFT JOIN users u_hod ON ts.hod_approved_by = u_hod.id
    LEFT JOIN users u_hr ON ts.hr_approved_by = u_hr.id
    LEFT JOIN users u_fin ON ts.finance_approved_by = u_fin.id
    WHERE ts.user_id = :uid
    ORDER BY ts.period_end DESC, ts.id DESC
");
$stmtTS->execute(['uid' => $userId]);
$timesheets = $stmtTS->fetchAll();

// Pre-calculate active period preview
$defaultStart = date('Y-m-d', strtotime('-14 days'));
$defaultEnd = date('Y-m-d');
$previewBilling = calculate_staff_billing($userId, $defaultStart, $defaultEnd);
?>

<div class="card" style="border-top: 4px solid var(--ehc-cyan-dark);">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.78rem; font-weight:800; color:var(--ehc-cyan-dark); text-transform:uppercase; letter-spacing:1px;">
        STAFF TRANSPARENCY DASHBOARD
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">My Duty Timesheets & Multi-Level Approvals</h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Complete transparency over clocked hours, overtime allowances, and departmental payroll verification.
      </p>
    </div>

    <button type="button" class="btn btn-primary" onclick="openModal('newTimesheetModal')">
      <i class="fa-solid fa-file-circle-plus"></i> Submit New Timesheet
    </button>
  </div>
</div>

<!-- Active Pay Period Preview -->
<div class="card" style="background: linear-gradient(135deg, #0F172A, #1E293B); color:#fff; border-color:#2D3540;">
  <div class="card-header-flex" style="border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:16px;">
    <div>
      <h3 style="color:#FFFFFF; font-size:1.1rem;"><i class="fa-solid fa-chart-line" style="color:var(--ehc-cyan);"></i> Active Shift Cycle Preview (Past 14 Days)</h3>
      <span style="font-size:0.8rem; color:#94A3B8;">From <?= format_date($defaultStart) ?> to <?= format_date($defaultEnd) ?></span>
    </div>
    <span class="badge badge-info"><i class="fa-solid fa-calculator"></i> Automated Billing Engine</span>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-top:20px;">
    <div style="background:rgba(255,255,255,0.04); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:#94A3B8; font-weight:700;">Total Clocked Hours</div>
      <div style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin-top:4px;"><?= $previewBilling['total_clocked_hours'] ?> hrs</div>
      <div style="font-size:0.75rem; color:#10B981; margin-top:2px;">Across <?= $previewBilling['days_worked'] ?> duty days</div>
    </div>

    <div style="background:rgba(255,255,255,0.04); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:#94A3B8; font-weight:700;">Base Pay</div>
      <div style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin-top:4px;"><?= format_currency($previewBilling['base_pay']) ?></div>
      <div style="font-size:0.75rem; color:#94A3B8; margin-top:2px;">Rate: <?= format_currency($previewBilling['hourly_rate']) ?>/hr</div>
    </div>

    <div style="background:rgba(255,255,255,0.04); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:#94A3B8; font-weight:700;">Overtime & Allowances</div>
      <div style="font-size:1.6rem; font-weight:800; color:var(--ehc-cyan); margin-top:4px;"><?= format_currency($previewBilling['claims_total'] + $previewBilling['overtime_pay']) ?></div>
      <div style="font-size:0.75rem; color:#94A3B8; margin-top:2px;"><?= $previewBilling['overtime_hours'] ?> overtime hours logged</div>
    </div>

    <div style="background:rgba(0,176,255,0.12); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(0,176,255,0.3);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:var(--ehc-cyan); font-weight:800;">Estimated Gross Billing</div>
      <div style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin-top:4px;"><?= format_currency($previewBilling['gross_billing_amount']) ?></div>
      <div style="font-size:0.75rem; color:#94A3B8; margin-top:2px;">Payroll authorized amount</div>
    </div>
  </div>
</div>

<!-- Timesheet Submissions -->
<div class="card">
  <div class="card-header-flex">
    <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Timesheet Workflow Status</h3>
    <span style="font-size:0.82rem; color:var(--text-muted);">Stages: 1. HOD Review &bull; 2. HR Verification &bull; 3. Finance Authorization</span>
  </div>

  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Timesheet Ref</th>
          <th>Period Covered</th>
          <th>Hours Logged</th>
          <th>Allowances</th>
          <th>Gross Billing</th>
          <th>Workflow Progression</th>
          <th>Overall Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($timesheets)): ?>
          <tr class="empty-row">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">
              No timesheets submitted yet. Click "Submit New Timesheet" to submit your hours for payroll processing.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($timesheets as $ts): ?>
            <tr>
              <td>
                <strong style="font-family:monospace; color:var(--ehc-dark);">#TS-<?= $ts['id'] ?></strong><br>
                <small style="color:var(--text-muted);"><?= format_date($ts['created_at']) ?></small>
              </td>
              <td><strong><?= format_date($ts['period_start']) ?></strong> &rarr; <strong><?= format_date($ts['period_end']) ?></strong></td>
              <td>
                <strong><?= number_format($ts['total_clocked_hours'], 2) ?> hrs</strong><br>
                <small style="color:var(--text-muted);"><?= $ts['regular_hours'] ?> reg / <?= $ts['overtime_hours'] ?> OT</small>
              </td>
              <td style="color:var(--ehc-primary); font-weight:700;"><?= format_currency($ts['claims_total_amount']) ?></td>
              <td style="font-weight:800; font-size:1rem; color:var(--ehc-dark);"><?= format_currency($ts['gross_billing_amount']) ?></td>
              <td style="min-width:240px;">
                <div style="display:flex; align-items:center; gap:6px; font-size:0.75rem;">
                  <div style="flex:1; text-align:center;">
                    <div style="padding:4px; border-radius:4px; font-weight:700; background:<?= $ts['hod_status'] === 'approved' ? '#ECFDF5; color:#065F46;' : '#FFFBEB; color:#92400E;' ?> border:1px solid;">
                      HOD: <?= ucfirst($ts['hod_status']) ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-arrow-right" style="color:var(--text-subtle); font-size:0.7rem;"></i>
                  <div style="flex:1; text-align:center;">
                    <div style="padding:4px; border-radius:4px; font-weight:700; background:<?= $ts['hr_status'] === 'approved' ? '#ECFDF5; color:#065F46;' : '#FFFBEB; color:#92400E;' ?> border:1px solid;">
                      HR: <?= ucfirst($ts['hr_status']) ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-arrow-right" style="color:var(--text-subtle); font-size:0.7rem;"></i>
                  <div style="flex:1; text-align:center;">
                    <div style="padding:4px; border-radius:4px; font-weight:700; background:<?= $ts['finance_status'] === 'approved' ? '#ECFDF5; color:#065F46;' : '#FFFBEB; color:#92400E;' ?> border:1px solid;">
                      Finance: <?= ucfirst($ts['finance_status']) ?>
                    </div>
                  </div>
                </div>

                <?php if (!empty($ts['hod_comment']) || !empty($ts['hr_comment']) || !empty($ts['finance_comment'])): ?>
                  <div style="font-size:0.73rem; color:var(--text-muted); margin-top:4px;">
                    <i class="fa-regular fa-comment-dots"></i> Notes: 
                    <?= htmlspecialchars($ts['finance_comment'] ?? $ts['hr_comment'] ?? $ts['hod_comment']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($ts['overall_status'] === 'approved'): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> Fully Authorized</span>
                <?php elseif ($ts['overall_status'] === 'under_review'): ?>
                  <span class="badge badge-warning"><i class="fa-solid fa-arrows-rotate"></i> In Workflow</span>
                <?php elseif ($ts['overall_status'] === 'rejected'): ?>
                  <span class="badge badge-danger">Rejected</span>
                <?php else: ?>
                  <span class="badge badge-info">Submitted</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= SUBMIT TIMESHEET MODAL ================= -->
<div class="modal-backdrop" id="newTimesheetModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--ehc-primary);"></i> Submit Staff Timesheet</h3>
      <button type="button" class="modal-close" onclick="closeModal('newTimesheetModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/timesheet_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="submit_timesheet">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/staff/timesheets.php') ?>">

      <div class="modal-body">
        <p style="font-size:0.86rem; color:var(--text-muted); margin-bottom:18px;">
          Select the shift period. The automated calculation engine will compile your biometric gate logs, regular hours, and approved duty claims.
        </p>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Period Start Date <span class="required">*</span></label>
            <input type="date" name="period_start" class="form-control" value="<?= date('Y-m-d', strtotime('-14 days')) ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Period End Date <span class="required">*</span></label>
            <input type="date" name="period_end" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newTimesheetModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit to Head of Department</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
