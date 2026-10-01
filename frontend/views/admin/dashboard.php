<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Administrator & Executive Management Dashboard
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Administrator Dashboard';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// 1. User Statistics
$stmtUserStats = $db->query("
    SELECT 
        COUNT(*) AS total_users,
        COUNT(CASE WHEN status = 'active' THEN 1 END) AS active_users,
        COUNT(CASE WHEN status IN ('inactive', 'suspended') THEN 1 END) AS inactive_users,
        COUNT(CASE WHEN role = 'lecturer' THEN 1 END) AS total_lecturers,
        COUNT(CASE WHEN role = 'staff' THEN 1 END) AS total_staff
    FROM users
");
$userStats = $stmtUserStats->fetch();

// 2. Attendance & Records Statistics
$stmtRecordStats = $db->query("
    SELECT 
        COUNT(*) AS total_records,
        COUNT(CASE WHEN clock_date = CURRENT_DATE THEN 1 END) AS today_records,
        COUNT(CASE WHEN clock_out IS NULL AND clock_date = CURRENT_DATE THEN 1 END) AS active_on_campus,
        COALESCE(SUM(CASE WHEN date_trunc('month', clock_date) = date_trunc('month', CURRENT_DATE) THEN total_hours ELSE 0 END), 0) AS month_hours
    FROM clocking_records
");
$recordStats = $stmtRecordStats->fetch();

// 3. Claims Statistics
$stmtClaimStats = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_claims,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS approved_claims,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected_claims,
        COALESCE(SUM(CASE WHEN status IN ('approved', 'paid') THEN total_amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) AS pending_amount
    FROM claims
");
$claimStats = $stmtClaimStats->fetch();

// 4. Timesheet Workflow Statistics
$stmtTSStats = $db->query("
    SELECT 
        COUNT(CASE WHEN overall_status IN ('submitted', 'under_review') THEN 1 END) AS pending_timesheets,
        COUNT(CASE WHEN overall_status = 'approved' THEN 1 END) AS approved_timesheets,
        COUNT(CASE WHEN overall_status = 'rejected' THEN 1 END) AS rejected_timesheets,
        COALESCE(SUM(CASE WHEN overall_status = 'approved' THEN gross_billing_amount ELSE 0 END), 0) AS approved_billing
    FROM timesheets
");
$tsStats = $stmtTSStats->fetch();

// 5. Recent System Activities (Audit Trail)
$recentAudits = $db->query("
    SELECT * FROM audit_trail 
    ORDER BY created_at DESC 
    LIMIT 6
")->fetchAll();

// 6. Live Clocked Staff On Campus Right Now
$liveOnCampus = $db->query("
    SELECT c.*, u.full_name, u.staff_id, u.role, u.employment_type, d.name AS dept_name
    FROM clocking_records c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE c.clock_out IS NULL AND c.clock_date = CURRENT_DATE
    ORDER BY c.clock_in DESC
    LIMIT 6
")->fetchAll();
?>

<!-- Header Ribbon -->
<div class="card" style="border-left: 5px solid var(--ehc-primary); margin-bottom: 24px;">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.8rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        EXECUTIVE INSTITUTIONAL CONTROL DESK
      </div>
      <h2 style="font-size:1.55rem; color:var(--ehc-dark); margin:4px 0 2px;">
        Evelyn Hone College Administration
      </h2>
      <p style="color:var(--text-muted); font-size:0.88rem;">
        Welcome, <?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['designation'] ?? 'Administrator') ?>) &bull; Knowledge with Integrity
      </p>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="<?= base_url('frontend/views/admin/timesheets_approval.php') ?>" class="btn btn-primary">
        <i class="fa-solid fa-file-signature"></i> Review Timesheets (<?= $tsStats['pending_timesheets'] ?>)
      </a>
      <a href="<?= base_url('frontend/views/admin/claims_management.php') ?>" class="btn btn-secondary">
        <i class="fa-solid fa-file-invoice-dollar"></i> Review Claims (<?= $claimStats['pending_claims'] ?>)
      </a>
      <a href="<?= base_url('frontend/views/admin/billing_engine.php') ?>" class="btn btn-secondary" style="background:#0F172A; color:#38BDF8; border-color:#1E293B;">
        <i class="fa-solid fa-calculator"></i> Billing Engine
      </a>
    </div>
  </div>
</div>

<!-- ================= EXECUTIVE KPI STATS (REQUIREMENTS: TOTAL, ACTIVE, INACTIVE, TOTAL RECORDS, PENDING, APPROVED, REJECTED) ================= -->
<div class="stats-grid">
  <!-- Total Users -->
  <div class="stat-card" style="--stat-color:#3B82F6; --stat-bg:#EFF6FF;">
    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= number_format($userStats['total_users']) ?></div>
      <div class="stat-label">Total System Users</div>
      <div class="stat-subtext"><?= $userStats['total_lecturers'] ?> Lecturers &bull; <?= $userStats['total_staff'] ?> Staff</div>
    </div>
  </div>

  <!-- Active vs Inactive Users -->
  <div class="stat-card" style="--stat-color:#10B981; --stat-bg:#ECFDF5;">
    <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= number_format($userStats['active_users']) ?> <span style="font-size:0.95rem; color:#EF4444; font-weight:600;">(<?= $userStats['inactive_users'] ?> Inactive)</span></div>
      <div class="stat-label">Active / Inactive Users</div>
      <div class="stat-subtext"><?= number_format($recordStats['active_on_campus']) ?> live on campus grounds</div>
    </div>
  </div>

  <!-- Total Clocking Records -->
  <div class="stat-card" style="--stat-color:var(--ehc-primary); --stat-bg:var(--ehc-primary-soft);">
    <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= number_format($recordStats['total_records']) ?></div>
      <div class="stat-label">Total Clocking Records</div>
      <div class="stat-subtext"><?= number_format($recordStats['month_hours'], 1) ?> hrs logged this month</div>
    </div>
  </div>

  <!-- Pending Approvals -->
  <div class="stat-card" style="--stat-color:#F59E0B; --stat-bg:#FFFBEB;">
    <div class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$claimStats['pending_claims'] + (int)$tsStats['pending_timesheets'] ?> Pending</div>
      <div class="stat-label">Pending Records</div>
      <div class="stat-subtext"><?= $claimStats['pending_claims'] ?> claims &bull; <?= $tsStats['pending_timesheets'] ?> timesheets</div>
    </div>
  </div>

  <!-- Approved Records -->
  <div class="stat-card" style="--stat-color:#059669; --stat-bg:#D1FAE5;">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$claimStats['approved_claims'] + (int)$tsStats['approved_timesheets'] ?> Approved</div>
      <div class="stat-label">Approved Records</div>
      <div class="stat-subtext"><?= format_currency($claimStats['approved_amount']) ?> claims approved</div>
    </div>
  </div>

  <!-- Rejected Records -->
  <div class="stat-card" style="--stat-color:#EF4444; --stat-bg:#FEF2F2;">
    <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$claimStats['rejected_claims'] + (int)$tsStats['rejected_timesheets'] ?> Rejected</div>
      <div class="stat-label">Rejected Records</div>
      <div class="stat-subtext">Disputed attendance & claims</div>
    </div>
  </div>
</div>

<!-- ================= TWO COLUMNS: LIVE ON CAMPUS & AUDIT TRAIL ================= -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(460px, 1fr)); gap:24px; margin-bottom:24px;">

  <!-- Live On Campus Presence -->
  <div class="card">
    <div class="card-header-flex">
      <h3 class="card-title">
        <i class="fa-solid fa-satellite-dish" style="color:#10B981;"></i> Staff Live on Campus Grounds (<?= count($liveOnCampus) ?>)
      </h3>
      <a href="<?= base_url('frontend/views/admin/clocking_records.php') ?>" class="btn btn-outline-primary btn-sm">All Records</a>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Staff Member</th>
            <th>Role & Category</th>
            <th>Entry Gate</th>
            <th>Time In</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($liveOnCampus)): ?>
            <tr class="empty-row"><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No staff currently clocked in on campus grounds.</td></tr>
          <?php else: ?>
            <?php foreach ($liveOnCampus as $loc): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($loc['full_name']) ?></strong><br>
                  <small style="color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($loc['staff_id']) ?></small>
                </td>
                <td>
                  <span class="badge <?= $loc['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>">
                    <?= ucfirst($loc['role']) ?> (<?= str_replace('_', ' ', $loc['employment_type']) ?>)
                  </span>
                </td>
                <td><small><?= htmlspecialchars($loc['entry_gate'] ?? 'Main Gate') ?></small></td>
                <td>
                  <span style="color:#10B981; font-weight:700;"><?= format_time($loc['clock_in']) ?></span>
                </td>
                <td>
                  <span class="badge badge-success"><span class="status-pulse-dot pulse" style="display:inline-block; margin-right:4px;"></span> Active</span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Institutional Audit Trail -->
  <div class="card">
    <div class="card-header-flex">
      <h3 class="card-title">
        <i class="fa-solid fa-shield-halved" style="color:var(--ehc-primary);"></i> Recent Institutional Activity
      </h3>
      <a href="<?= base_url('frontend/views/admin/audit_logs.php') ?>" class="btn btn-outline-primary btn-sm">Full Audit Trail</a>
    </div>

    <div style="display:flex; flex-direction:column; gap:12px;">
      <?php if (empty($recentAudits)): ?>
        <div style="text-align:center; padding:30px; color:var(--text-muted);">No activity logged yet.</div>
      <?php else: ?>
        <?php foreach ($recentAudits as $log): ?>
          <div style="padding:12px 14px; background:var(--bg-subtle); border:1px solid var(--border-color); border-radius:var(--radius-md); font-size:0.84rem;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span class="badge badge-secondary" style="font-family:monospace;"><?= htmlspecialchars($log['action']) ?></span>
              <span style="font-size:0.75rem; color:var(--text-muted);"><?= format_datetime($log['created_at']) ?></span>
            </div>
            <div style="font-weight:700; color:var(--ehc-dark); margin-top:4px;">
              <?= htmlspecialchars($log['user_name'] ?? 'System') ?> (<?= htmlspecialchars($log['staff_id'] ?? 'SYS') ?>)
            </div>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
              <?= htmlspecialchars($log['details']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ================= QUICK ACCESS ACTIONS GRID ================= -->
<div class="card">
  <div class="card-header-flex">
    <h3 class="card-title"><i class="fa-solid fa-compass"></i> Administrative Management Shortcuts</h3>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
    <a href="<?= base_url('frontend/views/admin/users.php') ?>" style="padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-md); text-decoration:none; color:inherit; background:var(--bg-subtle); display:flex; align-items:center; gap:14px; transition:var(--transition);" onmouseover="this.style.borderColor='var(--ehc-primary)';" onmouseout="this.style.borderColor='var(--border-color)';">
      <div style="width:44px; height:44px; border-radius:10px; background:rgba(230,81,0,0.1); color:var(--ehc-primary); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
        <i class="fa-solid fa-user-plus"></i>
      </div>
      <div>
        <div style="font-weight:700; font-size:0.92rem; color:var(--ehc-dark);">Manage Staff</div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Enroll, edit, suspend & rates</div>
      </div>
    </a>

    <a href="<?= base_url('frontend/views/admin/departments.php') ?>" style="padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-md); text-decoration:none; color:inherit; background:var(--bg-subtle); display:flex; align-items:center; gap:14px; transition:var(--transition);" onmouseover="this.style.borderColor='var(--ehc-primary)';" onmouseout="this.style.borderColor='var(--border-color)';">
      <div style="width:44px; height:44px; border-radius:10px; background:rgba(16,185,129,0.1); color:#059669; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
        <i class="fa-solid fa-building-columns"></i>
      </div>
      <div>
        <div style="font-weight:700; font-size:0.92rem; color:var(--ehc-dark);">Schools & HODs</div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Official faculty structure</div>
      </div>
    </a>

    <a href="<?= base_url('frontend/views/admin/billing_engine.php') ?>" style="padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-md); text-decoration:none; color:inherit; background:var(--bg-subtle); display:flex; align-items:center; gap:14px; transition:var(--transition);" onmouseover="this.style.borderColor='var(--ehc-primary)';" onmouseout="this.style.borderColor='var(--border-color)';">
      <div style="width:44px; height:44px; border-radius:10px; background:rgba(0,176,255,0.1); color:var(--ehc-cyan-dark); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
        <i class="fa-solid fa-calculator"></i>
      </div>
      <div>
        <div style="font-weight:700; font-size:0.92rem; color:var(--ehc-dark);">Billing Engine</div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Compute staff amounts owed</div>
      </div>
    </a>

    <a href="<?= base_url('frontend/views/admin/reports.php') ?>" style="padding:16px; border:1px solid var(--border-color); border-radius:var(--radius-md); text-decoration:none; color:inherit; background:var(--bg-subtle); display:flex; align-items:center; gap:14px; transition:var(--transition);" onmouseover="this.style.borderColor='var(--ehc-primary)';" onmouseout="this.style.borderColor='var(--border-color)';">
      <div style="width:44px; height:44px; border-radius:10px; background:rgba(124,58,237,0.1); color:#7C3AED; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
        <i class="fa-solid fa-chart-pie"></i>
      </div>
      <div>
        <div style="font-weight:700; font-size:0.92rem; color:var(--ehc-dark);">Executive Reports</div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Export payroll & attendance</div>
      </div>
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
