<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Executive Management Reports & Institutional Analytics
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Executive Reports & Analytics';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

$reportType = $_GET['report'] ?? 'billing';
$month = $_GET['month'] ?? date('Y-m');
$startDate = $month . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// 1. Departmental Billing & Hours Report Data
$stmtDeptBilling = $db->prepare("
    SELECT 
        d.id AS dept_id, d.name AS dept_name, s.name AS school_name, d.hod_name,
        COUNT(DISTINCT u.id) AS staff_count,
        COALESCE(SUM(c.total_hours), 0) AS total_hours_worked,
        COALESCE(SUM(clm.total_amount), 0) AS claims_expenditure
    FROM departments d
    JOIN schools s ON d.school_id = s.id
    LEFT JOIN users u ON d.id = u.department_id AND u.status = 'active'
    LEFT JOIN clocking_records c ON u.id = c.user_id AND c.clock_date >= :sdate AND c.clock_date <= :edate AND c.status IN ('completed', 'adjusted')
    LEFT JOIN claims clm ON u.id = clm.user_id AND clm.claim_date >= :sdate AND clm.claim_date <= :edate AND clm.status IN ('approved', 'paid')
    GROUP BY d.id, d.name, s.name, d.hod_name
    ORDER BY total_hours_worked DESC
");
$stmtDeptBilling->execute(['sdate' => $startDate, 'edate' => $endDate]);
$deptReports = $stmtDeptBilling->fetchAll();

// 2. Daily Attendance Punctuality Log for chosen month
$stmtDailyPunctuality = $db->prepare("
    SELECT 
        clock_date,
        COUNT(DISTINCT user_id) as total_present,
        COUNT(CASE WHEN CAST(clock_in AS TIME) <= '08:00:00' THEN 1 END) as punctual_arrivals,
        COUNT(CASE WHEN CAST(clock_in AS TIME) > '08:00:00' THEN 1 END) as late_arrivals,
        COALESCE(SUM(total_hours), 0) as cumulative_hours
    FROM clocking_records
    WHERE clock_date >= :sdate AND clock_date <= :edate
    GROUP BY clock_date
    ORDER BY clock_date DESC
");
$stmtDailyPunctuality->execute(['sdate' => $startDate, 'edate' => $endDate]);
$dailyLogs = $stmtDailyPunctuality->fetchAll();

// 3. Claims by Category
$stmtClaimsBreakdown = $db->prepare("
    SELECT 
        claim_type,
        COUNT(*) as claim_count,
        SUM(quantity) as sum_units,
        SUM(total_amount) as sum_amount
    FROM claims
    WHERE claim_date >= :sdate AND claim_date <= :edate AND status IN ('approved', 'paid')
    GROUP BY claim_type
    ORDER BY sum_amount DESC
");
$stmtClaimsBreakdown->execute(['sdate' => $startDate, 'edate' => $endDate]);
$claimsBreakdown = $stmtClaimsBreakdown->fetchAll();
?>

<!-- Header -->
<div class="card" style="border-top: 4px solid var(--ehc-primary);">
  <div class="card-header-flex">
    <div>
      <div style="font-size:0.8rem; font-weight:800; color:var(--ehc-primary-light); text-transform:uppercase; letter-spacing:1px;">
        INSTITUTIONAL METRICS & GOVERNANCE
      </div>
      <h2 style="font-size:1.45rem; color:var(--ehc-dark); margin:4px 0 2px;">
        Automated Management Reports
      </h2>
      <p style="font-size:0.85rem; color:var(--text-muted);">
        Comprehensive summaries generated directly from the PostgreSQL attendance and billing database.
      </p>
    </div>

    <div style="display:flex; gap:10px;">
      <button type="button" class="btn btn-secondary" onclick="window.print();">
        <i class="fa-solid fa-print"></i> Print Official Report
      </button>
    </div>
  </div>

  <!-- Report Selection Tabs & Date Picker -->
  <div class="no-print" style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; border-bottom:1px solid var(--border-color); padding-bottom:16px;">
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <a href="?report=billing&month=<?= urlencode($month) ?>" class="btn <?= $reportType === 'billing' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <i class="fa-solid fa-calculator"></i> Departmental Billing & Hours
      </a>
      <a href="?report=punctuality&month=<?= urlencode($month) ?>" class="btn <?= $reportType === 'punctuality' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <i class="fa-solid fa-clock"></i> Punctuality & Gate Arrivals
      </a>
      <a href="?report=claims&month=<?= urlencode($month) ?>" class="btn <?= $reportType === 'claims' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <i class="fa-solid fa-receipt"></i> Claims Expenditure Analysis
      </a>
    </div>

    <form action="" method="GET" style="display:flex; align-items:center; gap:8px; margin:0;">
      <input type="hidden" name="report" value="<?= htmlspecialchars($reportType) ?>">
      <label class="form-label" style="margin:0; font-size:0.82rem;">Select Month:</label>
      <input type="month" name="month" class="form-control" style="width:auto; padding:6px 12px; font-size:0.85rem;" value="<?= htmlspecialchars($month) ?>" onchange="this.form.submit()">
    </form>
  </div>
</div>

<?php if ($reportType === 'billing'): ?>
  <!-- REPORT 1: DEPARTMENTAL BILLING & HOURS -->
  <div class="card">
    <div class="card-header-flex">
      <div>
        <h3 class="card-title"><i class="fa-solid fa-building-columns"></i> Faculty & Departmental Hours & Expenditure</h3>
        <span style="font-size:0.82rem; color:var(--text-muted);">Cycle: <?= date('F Y', strtotime($startDate)) ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Department Name</th>
            <th>School / Faculty</th>
            <th>Current HOD</th>
            <th>Active Staff</th>
            <th>Hours Clocked</th>
            <th>Claims Expenditure</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $totStaff = 0; $totHrs = 0; $totClaims = 0;
            foreach ($deptReports as $dr): 
              $totStaff += (int)$dr['staff_count'];
              $totHrs += (float)$dr['total_hours_worked'];
              $totClaims += (float)$dr['claims_expenditure'];
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($dr['dept_name']) ?></strong></td>
              <td><?= htmlspecialchars($dr['school_name']) ?></td>
              <td><?= htmlspecialchars($dr['hod_name']) ?></td>
              <td><span class="badge badge-secondary"><?= $dr['staff_count'] ?> Staff</span></td>
              <td style="font-weight:700;"><?= number_format($dr['total_hours_worked'], 2) ?> hrs</td>
              <td style="font-weight:700; color:var(--ehc-primary);"><?= format_currency($dr['claims_expenditure']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg-subtle); font-weight:800; border-top:2px solid var(--border-color);">
            <td colspan="3">COLLEGE TOTAL:</td>
            <td><?= $totStaff ?> Staff</td>
            <td><?= number_format($totHrs, 2) ?> hrs</td>
            <td style="color:var(--ehc-primary);"><?= format_currency($totClaims) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

<?php elseif ($reportType === 'punctuality'): ?>
  <!-- REPORT 2: PUNCTUALITY & ATTENDANCE -->
  <div class="card">
    <div class="card-header-flex">
      <div>
        <h3 class="card-title"><i class="fa-solid fa-clock"></i> Daily Arrival Punctuality & Gate Attendance</h3>
        <span style="font-size:0.82rem; color:var(--text-muted);">Evaluation Period: <?= date('F Y', strtotime($startDate)) ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Date</th>
            <th>Staff Present on Campus</th>
            <th>Punctual Arrivals (Before 08:00 AM)</th>
            <th>Late Arrivals (After 08:00 AM)</th>
            <th>Punctuality Rate</th>
            <th>Cumulative Hours Logged</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dailyLogs)): ?>
            <tr class="empty-row"><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No attendance recorded for this month.</td></tr>
          <?php else: ?>
            <?php foreach ($dailyLogs as $dl): 
              $pRate = $dl['total_present'] > 0 ? round(($dl['punctual_arrivals'] / $dl['total_present']) * 100, 1) : 0;
            ?>
              <tr>
                <td><strong><?= format_date($dl['clock_date']) ?></strong></td>
                <td><span class="badge badge-info"><?= $dl['total_present'] ?> Staff</span></td>
                <td><span style="color:#10B981; font-weight:700;"><?= $dl['punctual_arrivals'] ?> arrivals</span></td>
                <td><span style="color:#EF4444; font-weight:700;"><?= $dl['late_arrivals'] ?> arrivals</span></td>
                <td>
                  <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; height:8px; background:#E2E8F0; border-radius:4px; overflow:hidden;">
                      <div style="width:<?= $pRate ?>%; height:100%; background:<?= $pRate >= 75 ? '#10B981' : ($pRate >= 50 ? '#F59E0B' : '#EF4444') ?>;"></div>
                    </div>
                    <span style="font-size:0.8rem; font-weight:700;"><?= $pRate ?>%</span>
                  </div>
                </td>
                <td style="font-weight:700;"><?= number_format($dl['cumulative_hours'], 2) ?> hrs</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <!-- REPORT 3: CLAIMS EXPENDITURE ANALYSIS -->
  <div class="card">
    <div class="card-header-flex">
      <div>
        <h3 class="card-title"><i class="fa-solid fa-receipt"></i> Academic & Support Duty Claims Expenditure</h3>
        <span style="font-size:0.82rem; color:var(--text-muted);">Cycle: <?= date('F Y', strtotime($startDate)) ?></span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th>Claim Category</th>
            <th>Count of Approved Claims</th>
            <th>Total Units / Quantity</th>
            <th>Expenditure in Kwacha</th>
            <th>Percentage of Claims Budget</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $grandSum = 0;
            foreach ($claimsBreakdown as $cb) { $grandSum += (float)$cb['sum_amount']; }
            if (empty($claimsBreakdown)):
          ?>
            <tr class="empty-row"><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No approved claims for this period.</td></tr>
          <?php else: ?>
            <?php foreach ($claimsBreakdown as $cb): 
              $pct = $grandSum > 0 ? round(($cb['sum_amount'] / $grandSum) * 100, 1) : 0;
            ?>
              <tr>
                <td><strong><?= ucwords(str_replace('_', ' ', $cb['claim_type'])) ?></strong></td>
                <td><span class="badge badge-secondary"><?= $cb['claim_count'] ?> Claims</span></td>
                <td><?= number_format($cb['sum_units'], 1) ?> units</td>
                <td style="font-weight:800; color:var(--ehc-primary); font-size:1rem;"><?= format_currency($cb['sum_amount']) ?></td>
                <td>
                  <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; height:8px; background:#E2E8F0; border-radius:4px; overflow:hidden;">
                      <div style="width:<?= $pct ?>%; height:100%; background:var(--ehc-primary);"></div>
                    </div>
                    <span style="font-size:0.8rem; font-weight:700;"><?= $pct ?>%</span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($claimsBreakdown)): ?>
          <tfoot>
            <tr style="background:var(--bg-subtle); font-weight:800; border-top:2px solid var(--border-color);">
              <td colspan="3">TOTAL APPROVED CLAIMS DISBURSEMENT:</td>
              <td style="color:var(--ehc-primary); font-size:1.1rem;"><?= format_currency($grandSum) ?></td>
              <td>100.0%</td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
