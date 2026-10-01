<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Lecturer Dashboard (Full-Time & Part-Time Faculty)
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Lecturer Dashboard';
require_once __DIR__ . '/../../../backend/includes/header.php';

// Role protection
require_role('lecturer');

$db = get_db();
$userId = $currentUser['id'];

// 1. Fetch Month-to-date hours
$stmtHours = $db->prepare("
    SELECT COALESCE(SUM(total_hours), 0) AS mtd_hours 
    FROM clocking_records 
    WHERE user_id = :uid 
      AND date_trunc('month', clock_date) = date_trunc('month', CURRENT_DATE)
      AND status IN ('completed', 'adjusted')
");
$stmtHours->execute(['uid' => $userId]);
$mtdHours = (float)$stmtHours->fetchColumn();

// 2. Fetch Claims Summary (Total Approved Amount & Pending Count)
$stmtClaims = $db->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN status IN ('approved', 'paid') THEN total_amount ELSE 0 END), 0) as approved_amount,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount
    FROM claims
    WHERE user_id = :uid
");
$stmtClaims->execute(['uid' => $userId]);
$claimsSummary = $stmtClaims->fetch();

// 3. Compute Estimated Billing for Current Month
$billing = calculate_staff_billing(
    $userId, 
    date('Y-m-01'), 
    date('Y-m-t')
);

// 4. Fetch 5 Recent Clocking Records
$stmtRecentClock = $db->prepare("
    SELECT * FROM clocking_records 
    WHERE user_id = :uid 
    ORDER BY clock_in DESC 
    LIMIT 5
");
$stmtRecentClock->execute(['uid' => $userId]);
$recentClockings = $stmtRecentClock->fetchAll();

// 5. Fetch 5 Recent Claims
$stmtRecentClaims = $db->prepare("
    SELECT * FROM claims 
    WHERE user_id = :uid 
    ORDER BY claim_date DESC, id DESC 
    LIMIT 5
");
$stmtRecentClaims->execute(['uid' => $userId]);
$recentClaims = $stmtRecentClaims->fetchAll();
?>

<!-- Welcome Banner -->
<div class="card" style="border-left: 5px solid var(--ehc-primary); margin-bottom: 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="font-size:0.8rem; font-weight:700; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        FACULTY PORTAL &bull; <?= htmlspecialchars($currentUser['school_name'] ?? 'School of Business Studies') ?>
      </div>
      <h2 style="font-size:1.55rem; color:var(--ehc-dark); margin:4px 0 2px;">
        Welcome, <?= htmlspecialchars($currentUser['full_name']) ?>
      </h2>
      <p style="color:var(--text-muted); font-size:0.88rem;">
        <?= htmlspecialchars($currentUser['designation'] ?? 'Lecturer') ?> &bull; 
        <strong><?= htmlspecialchars($currentUser['department_name'] ?? 'Academic Department') ?></strong> &bull;
        Category: <span class="badge <?= $currentUser['employment_type'] === 'full_time' ? 'badge-success' : 'badge-warning' ?>"><?= str_replace('_', ' ', ucfirst($currentUser['employment_type'])) ?></span>
      </p>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="button" class="btn btn-claims-special" onclick="openModal('claimModal')">
        <i class="fa-solid fa-file-invoice-dollar"></i> Submit Claim
      </button>
      <a href="<?= base_url('frontend/views/lecturer/timesheets.php') ?>" class="btn btn-secondary">
        <i class="fa-solid fa-calendar-check"></i> My Timesheets
      </a>
    </div>
  </div>
</div>

<!-- ================= CLOCKING HERO BANNER (LIVE PREMISES CHECK) ================= -->
<div class="clocking-banner">
  <div class="clocking-flex">
    <div>
      <div style="font-size:0.82rem; color:var(--ehc-primary-light); font-weight:700; text-transform:uppercase; letter-spacing:1px;">
        <i class="fa-solid fa-satellite-dish"></i> Campus Premises Time Tracker
      </div>
      <div class="clocking-live-time" id="liveClockDisplay">00:00:00</div>
      <div class="clocking-live-date" id="liveDateDisplay">Thursday, 1 October 2026</div>

      <div>
        <?php if ($clockStatus['is_clocked_in']): ?>
          <span class="clocking-status-tag clocked-in">
            <span class="status-pulse-dot pulse"></span>
            CLOCKED IN on Campus (<?= htmlspecialchars($clockStatus['entry_gate'] ?? 'Main Gate') ?>) &bull; <?= $clockStatus['elapsed_hours'] ?> hrs elapsed
          </span>
        <?php else: ?>
          <span class="clocking-status-tag clocked-out">
            <i class="fa-solid fa-clock-rotate-left"></i> Currently Clocked Out &bull; Ready for shift
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="clock-actions-group">
      <?php if (!$clockStatus['is_clocked_in']): ?>
        <form action="<?= base_url('backend/actions/clock_action.php') ?>" method="POST" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
          <input type="hidden" name="action" value="clock_in">
          <input type="hidden" name="entry_gate" value="Main Gate - Church Rd">
          <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/dashboard.php') ?>">
          <button type="submit" class="btn-clock btn-clock-in">
            <i class="fa-solid fa-right-to-bracket"></i> CLOCK IN NOW
          </button>
        </form>
      <?php else: ?>
        <form action="<?= base_url('backend/actions/clock_action.php') ?>" method="POST" style="margin:0;" onsubmit="return confirm('Confirm Clock-Out from Evelyn Hone College premises?');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
          <input type="hidden" name="action" value="clock_out">
          <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/dashboard.php') ?>">
          <button type="submit" class="btn-clock btn-clock-out">
            <i class="fa-solid fa-right-from-bracket"></i> CLOCK OUT NOW
          </button>
        </form>
      <?php endif; ?>

      <button type="button" class="btn btn-secondary" onclick="openModal('claimModal')" style="padding:16px 20px; font-weight:700; background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
        <i class="fa-solid fa-plus-circle" style="color:var(--ehc-primary-light);"></i> Claims (Exam/Marking)
      </button>
    </div>
  </div>
</div>

<!-- ================= IMPORTANT STATS ================= -->
<div class="stats-grid">
  <!-- Month Hours -->
  <div class="stat-card" style="--stat-color:#10B981; --stat-bg:#ECFDF5;">
    <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= number_format($mtdHours, 2) ?> hrs</div>
      <div class="stat-label">Hours Clocked This Month</div>
      <div class="stat-subtext">Today: <?= number_format($clockStatus['hours_today'], 2) ?> hours logged</div>
    </div>
  </div>

  <!-- Approved Claims Amount -->
  <div class="stat-card" style="--stat-color:var(--ehc-primary); --stat-bg:var(--ehc-primary-soft);">
    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($claimsSummary['approved_amount']) ?></div>
      <div class="stat-label">Approved Claims (Paid/Payable)</div>
      <div class="stat-subtext">Invigilation, Marking & Overtime</div>
    </div>
  </div>

  <!-- Pending Claims -->
  <div class="stat-card" style="--stat-color:#F59E0B; --stat-bg:#FFFBEB;">
    <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$claimsSummary['pending_count'] ?> Pending</div>
      <div class="stat-label">Claims Under Review</div>
      <div class="stat-subtext"><?= format_currency($claimsSummary['pending_amount']) ?> awaiting signoff</div>
    </div>
  </div>

  <!-- Estimated Billing Calculation Engine Output -->
  <div class="stat-card" style="--stat-color:#0288D1; --stat-bg:#E0F2FE;">
    <div class="stat-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($billing['gross_billing_amount']) ?></div>
      <div class="stat-label">Gross Est. Month Billing</div>
      <div class="stat-subtext"><?= $billing['regular_hours'] ?> hrs base + <?= count($billing['claims_breakdown']) ?> claims</div>
    </div>
  </div>
</div>

<!-- ================= TWO COLUMNS: RECENT SESSIONS & RECENT CLAIMS ================= -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(450px, 1fr)); gap:24px;">

  <!-- Recent Clocking Sessions -->
  <div class="card">
    <div class="card-header-flex">
      <h3 class="card-title"><i class="fa-solid fa-stopwatch"></i> Recent Clocking History</h3>
      <a href="<?= base_url('frontend/views/lecturer/clocking.php') ?>" class="btn btn-outline-primary btn-sm">View Full History</a>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Date</th>
            <th>Gate / Method</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Total Hours</th>
            <th>Premise</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentClockings)): ?>
            <tr class="empty-row"><td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">No clocking records recorded yet.</td></tr>
          <?php else: ?>
            <?php foreach ($recentClockings as $rc): ?>
              <tr>
                <td><strong><?= format_date($rc['clock_date']) ?></strong></td>
                <td><small><?= htmlspecialchars($rc['entry_gate'] ?? 'Main Gate') ?></small></td>
                <td><span style="color:#10B981; font-weight:700;"><?= format_time($rc['clock_in']) ?></span></td>
                <td>
                  <?php if (!empty($rc['clock_out'])): ?>
                    <span style="color:#F59E0B; font-weight:700;"><?= format_time($rc['clock_out']) ?></span>
                  <?php else: ?>
                    <span class="badge badge-success">Active Now</span>
                  <?php endif; ?>
                </td>
                <td style="font-weight:700;"><?= number_format($rc['total_hours'], 2) ?> hrs</td>
                <td>
                  <span class="badge badge-success"><i class="fa-solid fa-check"></i> Verified</span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Claims -->
  <div class="card">
    <div class="card-header-flex">
      <h3 class="card-title"><i class="fa-solid fa-file-invoice-dollar"></i> My Claims & Allowances</h3>
      <a href="<?= base_url('frontend/views/lecturer/claims.php') ?>" class="btn btn-outline-primary btn-sm">Manage Claims</a>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Claim Type</th>
            <th>Course</th>
            <th>Qty / Rate</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentClaims)): ?>
            <tr class="empty-row"><td colspan="5" style="text-align:center; color:var(--text-muted); padding:30px;">No claims submitted yet. Click "Submit Claim" above!</td></tr>
          <?php else: ?>
            <?php foreach ($recentClaims as $clm): ?>
              <tr>
                <td>
                  <strong><?= ucwords(str_replace('_', ' ', $clm['claim_type'])) ?></strong><br>
                  <small style="color:var(--text-muted);"><?= format_date($clm['claim_date']) ?></small>
                </td>
                <td><?= htmlspecialchars($clm['course_code'] ?? 'N/A') ?></td>
                <td><?= $clm['quantity'] ?> &times; <?= format_currency($clm['rate_per_unit']) ?></td>
                <td style="font-weight:700; color:var(--ehc-primary);"><?= format_currency($clm['total_amount']) ?></td>
                <td>
                  <?php if ($clm['status'] === 'approved'): ?>
                    <span class="badge badge-success">Approved</span>
                  <?php elseif ($clm['status'] === 'pending'): ?>
                    <span class="badge badge-warning">Pending</span>
                  <?php else: ?>
                    <span class="badge badge-danger"><?= ucfirst($clm['status']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ================= SUBMIT CLAIM MODAL ================= -->
<div class="modal-backdrop" id="claimModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-file-signature" style="color:var(--ehc-primary);"></i> Submit Academic Claim</h3>
      <button type="button" class="modal-close" onclick="closeModal('claimModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/claims_action.php') ?>" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="total_amount" id="claimTotalAmount" value="0.00">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/dashboard.php') ?>">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Claim Category <span class="required">*</span></label>
          <select name="claim_type" id="claimTypeSelect" class="form-select" required>
            <option value="exam_invigilation">Exam Invigilation (ZMW 120.00 / hour)</option>
            <option value="exam_marking">Exam Script Marking (ZMW 35.00 / script)</option>
            <option value="extra_lecture">Extra / Remedial Lecture (ZMW 180.00 / hour)</option>
            <option value="overtime">Teaching Overtime (ZMW 150.00 / hour)</option>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date of Duty <span class="required">*</span></label>
            <input type="date" name="claim_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Course / Subject Code</label>
            <input type="text" name="course_code" class="form-control" placeholder="e.g. BCS-210, ACCA-F3">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Quantity (<span id="claimUnitLabel">Hours</span>) <span class="required">*</span></label>
            <input type="number" step="0.5" min="0.5" name="quantity" id="claimQuantityInput" class="form-control" value="1" required>
          </div>

          <div class="form-group">
            <label class="form-label">Rate Per Unit (ZMW) <span class="required">*</span></label>
            <input type="number" step="0.01" min="1" name="rate_per_unit" id="claimRateInput" class="form-control" value="120.00" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Description & Venue <span class="required">*</span></label>
          <textarea name="description" class="form-control" placeholder="e.g. Invigilated Final Examination for BCS-210 in Main Lecture Hall 2." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Supporting Evidence / Timetable (Optional PDF or Image)</label>
          <input type="file" name="evidence" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.docx">
        </div>

        <!-- Dynamic Kwacha Calculation Box -->
        <div style="background:var(--bg-subtle); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; justify-content:space-between;">
          <div>
            <span style="font-size:0.78rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Computed Claim Value</span>
            <div style="font-size:1.6rem; font-weight:800; color:var(--ehc-primary);" id="claimTotalDisplay">ZMW 120.00</div>
          </div>
          <i class="fa-solid fa-calculator" style="font-size:2rem; color:var(--border-color);"></i>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('claimModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit to HOD & HR</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
