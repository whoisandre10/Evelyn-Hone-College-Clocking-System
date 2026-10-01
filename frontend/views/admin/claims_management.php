<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Admin Claims Verification Desk
 * (Invigilation, Exam Marking, Overtime & Special Duty Allowances)
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Claims Verification Desk';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

// Search & Filter
$searchQuery = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['claim_type'] ?? 'all';

$sql = "
    SELECT c.*, u.full_name, u.staff_id, u.role, u.employment_type, d.name AS dept_name,
           u_rev.full_name AS reviewer_name
    FROM claims c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN users u_rev ON c.reviewed_by = u_rev.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND c.status = :status";
    $params['status'] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $sql .= " AND c.claim_type = :type";
    $params['type'] = $typeFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(u.full_name) LIKE :q OR LOWER(u.staff_id) LIKE :q OR LOWER(c.course_code) LIKE :q OR LOWER(c.description) LIKE :q)";
    $params['q'] = '%' . strtolower($searchQuery) . '%';
}

$sql .= " ORDER BY c.claim_date DESC, c.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// Claims Totals
$stmtTotals = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS count_pending,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) AS sum_pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) AS count_approved,
        COALESCE(SUM(CASE WHEN status = 'approved' THEN total_amount ELSE 0 END), 0) AS sum_approved
    FROM claims
");
$totals = $stmtTotals->fetch();
?>

<!-- KPI Cards -->
<div class="stats-grid">
  <div class="stat-card" style="--stat-color:#F59E0B; --stat-bg:#FFFBEB;">
    <div class="stat-icon"><i class="fa-solid fa-hourglass-start"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$totals['count_pending'] ?> Claims</div>
      <div class="stat-label">Pending Institutional Review</div>
      <div class="stat-subtext"><?= format_currency($totals['sum_pending']) ?> awaiting authorization</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:#10B981; --stat-bg:#ECFDF5;">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$totals['count_approved'] ?> Claims</div>
      <div class="stat-label">Approved & Authorized</div>
      <div class="stat-subtext"><?= format_currency($totals['sum_approved']) ?> authorized for disbursement</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:var(--ehc-primary); --stat-bg:var(--ehc-primary-soft);">
    <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= count($claims) ?> Filtered</div>
      <div class="stat-label">Filtered Records Displayed</div>
      <div class="stat-subtext">Teaching, exam marking, overtime</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">Institutional Claims & Allowances Verification</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Examine, approve, or reject exam invigilation, script marking, overtime, and duty claims.
      </p>
    </div>

    <button type="button" class="btn btn-secondary" onclick="window.print();">
      <i class="fa-solid fa-print"></i> Print Report
    </button>
  </div>

  <!-- Filter Form -->
  <form action="" method="GET" class="no-print" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0; flex:2;">
        <label class="form-label">Search Claimant or Details</label>
        <input type="text" name="search" class="form-control" placeholder="Search by staff name, ID, course..." value="<?= htmlspecialchars($searchQuery) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Claim Type</label>
        <select name="claim_type" class="form-select">
          <option value="all">All Types</option>
          <option value="exam_invigilation" <?= $typeFilter === 'exam_invigilation' ? 'selected' : '' ?>>Exam Invigilation</option>
          <option value="exam_marking" <?= $typeFilter === 'exam_marking' ? 'selected' : '' ?>>Exam Marking</option>
          <option value="extra_lecture" <?= $typeFilter === 'extra_lecture' ? 'selected' : '' ?>>Extra Lecture</option>
          <option value="overtime" <?= $typeFilter === 'overtime' ? 'selected' : '' ?>>Overtime</option>
          <option value="weekend_duty" <?= $typeFilter === 'weekend_duty' ? 'selected' : '' ?>>Weekend Duty</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
          <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
          <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Apply</button>
        <a href="<?= base_url('frontend/views/admin/claims_management.php') ?>" class="btn btn-secondary">Clear</a>
      </div>
    </div>
  </form>

  <!-- Claims Table -->
  <div class="table-responsive">
    <table class="table-custom table-searchable">
      <thead>
        <tr>
          <th>Claim Ref</th>
          <th>Claimant Staff</th>
          <th>Duty Type & Course</th>
          <th>Date of Duty</th>
          <th>Quantity & Rate</th>
          <th>Total Claim</th>
          <th>Status</th>
          <th>Evidence / Description</th>
          <th class="no-print">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($claims)): ?>
          <tr class="empty-row"><td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">No claims found for this filter.</td></tr>
        <?php else: ?>
          <?php foreach ($claims as $c): ?>
            <tr>
              <td><strong style="font-family:monospace;">#CLM-<?= $c['id'] ?></strong></td>
              <td>
                <strong><?= htmlspecialchars($c['full_name']) ?></strong><br>
                <small style="color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($c['staff_id']) ?></small> &bull;
                <span class="badge <?= $c['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>" style="font-size:0.68rem;">
                  <?= ucfirst($c['role']) ?>
                </span>
                <div style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($c['dept_name'] ?? 'General') ?></div>
              </td>
              <td>
                <strong><?= ucwords(str_replace('_', ' ', $c['claim_type'])) ?></strong>
                <?php if (!empty($c['course_code'])): ?>
                  <br><span class="badge badge-secondary"><?= htmlspecialchars($c['course_code']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= format_date($c['claim_date']) ?></td>
              <td>
                <?= $c['quantity'] ?> &times; <?= format_currency($c['rate_per_unit']) ?>
              </td>
              <td style="font-weight:800; font-size:1rem; color:var(--ehc-primary);">
                <?= format_currency($c['total_amount']) ?>
              </td>
              <td>
                <?php if ($c['status'] === 'approved'): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-check"></i> Approved</span>
                <?php elseif ($c['status'] === 'pending'): ?>
                  <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pending</span>
                <?php elseif ($c['status'] === 'rejected'): ?>
                  <span class="badge badge-danger"><i class="fa-solid fa-xmark"></i> Rejected</span>
                <?php else: ?>
                  <span class="badge badge-info"><?= ucfirst($c['status']) ?></span>
                <?php endif; ?>
              </td>
              <td style="max-width:220px;">
                <div style="font-size:0.84rem;"><?= htmlspecialchars($c['description']) ?></div>
                <?php if (!empty($c['evidence_file'])): ?>
                  <div style="margin-top:4px;">
                    <a href="<?= base_url('uploads/claims/' . htmlspecialchars($c['evidence_file'])) ?>" target="_blank" style="font-size:0.78rem; font-weight:700;">
                      <i class="fa-solid fa-paperclip"></i> View Attached File
                    </a>
                  </div>
                <?php endif; ?>
                <?php if (!empty($c['rejection_reason'])): ?>
                  <div style="font-size:0.75rem; color:#EF4444; margin-top:3px; background:#FEF2F2; padding:3px 6px; border-radius:4px;">
                    <?= htmlspecialchars($c['rejection_reason']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="no-print">
                <?php if ($c['status'] === 'pending'): ?>
                  <div style="display:flex; gap:6px;">
                    <!-- Approve Button -->
                    <form action="<?= base_url('backend/actions/claims_action.php') ?>" method="POST" style="margin:0;" onsubmit="return confirm('Authorize claim #CLM-<?= $c['id'] ?> for ZMW <?= number_format($c['total_amount'], 2) ?>?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/claims_management.php') ?>">
                      <button type="submit" class="btn btn-success btn-sm" title="Approve Claim">
                        <i class="fa-solid fa-check"></i> Approve
                      </button>
                    </form>

                    <!-- Reject Button -->
                    <button type="button" class="btn btn-danger btn-sm" onclick='openRejectClaimModal(<?= $c['id'] ?>, "<?= htmlspecialchars($c['full_name']) ?>", "<?= format_currency($c['total_amount']) ?>")' title="Reject Claim">
                      <i class="fa-solid fa-xmark"></i> Reject
                    </button>
                  </div>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:var(--text-subtle);">
                    Reviewed by <?= htmlspecialchars($c['reviewer_name'] ?? 'Admin') ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= REJECT CLAIM MODAL ================= -->
<div class="modal-backdrop" id="rejectClaimModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" style="color:#EF4444;"><i class="fa-solid fa-triangle-exclamation"></i> Reject Academic / Duty Claim</h3>
      <button type="button" class="modal-close" onclick="closeModal('rejectClaimModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/claims_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="claim_id" id="rejectClaimId">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/claims_management.php') ?>">

      <div class="modal-body">
        <div id="rejectClaimDetails" style="font-weight:700; font-size:0.9rem; color:var(--ehc-dark); margin-bottom:14px;"></div>

        <div class="form-group">
          <label class="form-label">Reason for Rejection <span class="required">*</span></label>
          <textarea name="rejection_reason" class="form-control" placeholder="Specify why the claim is rejected (e.g. script count does not match registry enrolment, invigilation duty was assigned to another lecturer)..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectClaimModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openRejectClaimModal(claimId, staffName, amount) {
    document.getElementById('rejectClaimId').value = claimId;
    document.getElementById('rejectClaimDetails').textContent = 'Claimant: ' + staffName + ' &bull; Amount: ' + amount;
    openModal('rejectClaimModal');
  }
</script>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
