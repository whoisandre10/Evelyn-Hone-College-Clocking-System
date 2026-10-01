<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Self-Service Staff Transparency Dashboard & Timesheets (Lecturer)
 */

define('EHC_SYSTEM', true);
$pageTitle = 'My Timesheets & Transparency Dashboard';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('lecturer');

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

// Pre-calculate current bi-weekly period preview
$defaultStart = date('Y-m-d', strtotime('-14 days'));
$defaultEnd = date('Y-m-d');
$previewBilling = calculate_staff_billing($userId, $defaultStart, $defaultEnd);
?>

<!-- Header Card -->
<div class="card" style="border-top: 4px solid var(--ehc-primary);">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.78rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        SELF-SERVICE TRANSPARENCY DASHBOARD
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">My Activity & Timesheet Workflow</h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Real-time visibility into your teaching hours, claims, billing calculations, and multi-level departmental approvals.
      </p>
    </div>

    <button type="button" class="btn btn-primary" onclick="openModal('newTimesheetModal')">
      <i class="fa-solid fa-file-circle-plus"></i> Submit New Timesheet
    </button>
  </div>
</div>

<!-- Real-time Activity Transparency Banner -->
<div class="card" style="background: linear-gradient(135deg, #1E232A, #121417); color:#fff; border-color:#2D3540;">
  <div class="card-header-flex" style="border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:16px;">
    <div>
      <h3 style="color:#FFFFFF; font-size:1.1rem;"><i class="fa-solid fa-chart-line" style="color:var(--ehc-primary-light);"></i> Active Pay Period Real-Time Preview (Past 14 Days)</h3>
      <span style="font-size:0.8rem; color:#94A3B8;">From <?= format_date($defaultStart) ?> to <?= format_date($defaultEnd) ?> (Live Automated Billing Engine)</span>
    </div>
    <span class="badge badge-success"><i class="fa-solid fa-bolt"></i> Live Transparency</span>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-top:20px;">
    <div style="background:rgba(255,255,255,0.04); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:#94A3B8; font-weight:700;">Total Clocked Hours</div>
      <div style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin-top:4px;"><?= $previewBilling['total_clocked_hours'] ?> hrs</div>
      <div style="font-size:0.75rem; color:#10B981; margin-top:2px;">Across <?= $previewBilling['days_worked'] ?> active teaching days</div>
    </div>

    <div style="background:rgba(255,255,255,0.04); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:#94A3B8; font-weight:700;">Base Rate / Pay</div>
      <div style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin-top:4px;"><?= format_currency($previewBilling['base_pay']) ?></div>
      <div style="font-size:0.75rem; color:#94A3B8; margin-top:2px;">Rate: <?= format_currency($previewBilling['hourly_rate']) ?> / hr</div>
    </div>

    <div style="background:rgba(255,255,255,0.04); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:#94A3B8; font-weight:700;">Overtime / Claims</div>
      <div style="font-size:1.6rem; font-weight:800; color:var(--ehc-primary-light); margin-top:4px;"><?= format_currency($previewBilling['claims_total'] + $previewBilling['overtime_pay']) ?></div>
      <div style="font-size:0.75rem; color:#94A3B8; margin-top:2px;"><?= count($previewBilling['claims_breakdown']) ?> approved claim batches</div>
    </div>

    <div style="background:rgba(230,81,0,0.12); padding:16px; border-radius:var(--radius-md); border:1px solid rgba(245,124,0,0.3);">
      <div style="font-size:0.75rem; text-transform:uppercase; color:var(--ehc-primary-light); font-weight:800;">Estimated Gross Billing</div>
      <div style="font-size:1.6rem; font-weight:800; color:#FFFFFF; margin-top:4px;"><?= format_currency($previewBilling['gross_billing_amount']) ?></div>
      <div style="font-size:0.75rem; color:#38BDF8; margin-top:2px;">Pre-tax institutional billing</div>
    </div>
  </div>
</div>

<!-- Timesheets List -->
<div class="card">
  <div class="card-header-flex">
    <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Submitted Timesheets & Approval Workflow</h3>
    <span style="font-size:0.82rem; color:var(--text-muted);">Stages: 1. HOD Review &bull; 2. HR Verification &bull; 3. Finance Authorization</span>
  </div>

  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Timesheet Ref</th>
          <th>Period Covered</th>
          <th>Hours Logged</th>
          <th>Claims Value</th>
          <th>Gross Billing</th>
          <th>Workflow Progression</th>
          <th>Overall Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($timesheets)): ?>
          <tr class="empty-row">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">
              <i class="fa-solid fa-calendar-xmark" style="font-size:2rem; margin-bottom:8px; display:block; color:var(--border-color);"></i>
              No timesheets submitted yet. Click "Submit New Timesheet" to compile your activity for review.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($timesheets as $ts): ?>
            <tr>
              <td>
                <strong style="font-family:monospace; color:var(--ehc-dark);">#TS-<?= $ts['id'] ?></strong><br>
                <small style="color:var(--text-muted);"><?= format_date($ts['created_at']) ?></small>
              </td>
              <td>
                <strong><?= format_date($ts['period_start']) ?></strong> &rarr; <strong><?= format_date($ts['period_end']) ?></strong>
              </td>
              <td>
                <strong><?= number_format($ts['total_clocked_hours'], 2) ?> hrs</strong><br>
                <small style="color:var(--text-muted);"><?= $ts['regular_hours'] ?> reg / <?= $ts['overtime_hours'] ?> OT</small>
              </td>
              <td style="color:var(--ehc-primary); font-weight:700;">
                <?= format_currency($ts['claims_total_amount']) ?>
              </td>
              <td style="font-weight:800; font-size:1rem; color:var(--ehc-dark);">
                <?= format_currency($ts['gross_billing_amount']) ?>
              </td>
              <td style="min-width:240px;">
                <!-- 3-Stage Progress Stepper -->
                <div style="display:flex; align-items:center; gap:6px; font-size:0.75rem;">
                  <!-- Stage 1: HOD -->
                  <div style="flex:1; text-align:center;">
                    <div style="padding:4px; border-radius:4px; font-weight:700; background:<?= $ts['hod_status'] === 'approved' ? '#ECFDF5; color:#065F46;' : ($ts['hod_status'] === 'rejected' ? '#FEF2F2; color:#991B1B;' : '#FFFBEB; color:#92400E;') ?> border:1px solid;">
                      HOD: <?= ucfirst($ts['hod_status']) ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-arrow-right" style="color:var(--text-subtle); font-size:0.7rem;"></i>
                  <!-- Stage 2: HR -->
                  <div style="flex:1; text-align:center;">
                    <div style="padding:4px; border-radius:4px; font-weight:700; background:<?= $ts['hr_status'] === 'approved' ? '#ECFDF5; color:#065F46;' : ($ts['hr_status'] === 'rejected' ? '#FEF2F2; color:#991B1B;' : '#FFFBEB; color:#92400E;') ?> border:1px solid;">
                      HR: <?= ucfirst($ts['hr_status']) ?>
                    </div>
                  </div>
                  <i class="fa-solid fa-arrow-right" style="color:var(--text-subtle); font-size:0.7rem;"></i>
                  <!-- Stage 3: Finance -->
                  <div style="flex:1; text-align:center;">
                    <div style="padding:4px; border-radius:4px; font-weight:700; background:<?= $ts['finance_status'] === 'approved' ? '#ECFDF5; color:#065F46;' : ($ts['finance_status'] === 'rejected' ? '#FEF2F2; color:#991B1B;' : '#FFFBEB; color:#92400E;') ?> border:1px solid;">
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
                  <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Rejected</span>
                <?php else: ?>
                  <span class="badge badge-info"><i class="fa-solid fa-paper-plane"></i> Submitted</span>
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
      <h3 class="modal-title"><i class="fa-solid fa-calendar-plus" style="color:var(--ehc-primary);"></i> Submit Timesheet for Departmental Review</h3>
      <button type="button" class="modal-close" onclick="closeModal('newTimesheetModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/timesheet_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="submit_timesheet">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/timesheets.php') ?>">

      <div class="modal-body">
        <p style="font-size:0.86rem; color:var(--text-muted); margin-bottom:18px;">
          Select the start and end dates of the teaching shift cycle. The automated billing engine will aggregate your clocked hours, calculate regular and overtime pay, and attach approved claims.
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

        <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:var(--radius-md); padding:14px; margin-top:10px;">
          <div style="font-weight:700; font-size:0.85rem; color:#92400E;">
            <i class="fa-solid fa-route"></i> Institutional Approval Routing:
          </div>
          <ol style="font-size:0.8rem; color:#78350F; margin:6px 0 0 18px; line-height:1.5;">
            <li><strong>Stage 1 (HOD):</strong> Verifies academic teaching hours against timetable.</li>
            <li><strong>Stage 2 (HR):</strong> Reconciles gate biometric timestamps and leave records.</li>
            <li><strong>Stage 3 (Finance):</strong> Authorizes Kwacha disbursement into payroll.</li>
          </ol>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newTimesheetModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit to HOD</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
