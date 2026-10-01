<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Lecturer Claims Management Portal (Invigilation, Exam Marking, Overtime)
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Academic Claims';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('lecturer');

$db = get_db();
$userId = $currentUser['id'];

// Filter & Search
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM claims WHERE user_id = :uid";
$params = ['uid' => $userId];

if ($statusFilter !== 'all') {
    $sql .= " AND status = :st";
    $params['st'] = $statusFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(description) LIKE :q OR LOWER(course_code) LIKE :q OR LOWER(claim_type) LIKE :q)";
    $params['q'] = '%' . strtolower($searchQuery) . '%';
}

$sql .= " ORDER BY claim_date DESC, id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// KPI Stats
$stmtKPI = $db->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS total_submitted,
        COALESCE(SUM(CASE WHEN status IN ('approved', 'paid') THEN total_amount ELSE 0 END), 0) AS total_approved,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) AS total_pending,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN total_amount ELSE 0 END), 0) AS total_rejected
    FROM claims 
    WHERE user_id = :uid
");
$stmtKPI->execute(['uid' => $userId]);
$kpi = $stmtKPI->fetch();
?>

<!-- KPI Overview -->
<div class="stats-grid">
  <div class="stat-card" style="--stat-color:var(--ehc-primary); --stat-bg:var(--ehc-primary-soft);">
    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($kpi['total_submitted']) ?></div>
      <div class="stat-label">Total Claims Submitted</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:#10B981; --stat-bg:#ECFDF5;">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($kpi['total_approved']) ?></div>
      <div class="stat-label">Approved & Payable</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:#F59E0B; --stat-bg:#FFFBEB;">
    <div class="stat-icon"><i class="fa-solid fa-hourglass-start"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($kpi['total_pending']) ?></div>
      <div class="stat-label">Pending HOD / HR Review</div>
    </div>
  </div>

  <div class="stat-card" style="--stat-color:#EF4444; --stat-bg:#FEF2F2;">
    <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
    <div class="stat-content">
      <div class="stat-value"><?= format_currency($kpi['total_rejected']) ?></div>
      <div class="stat-label">Rejected Claims</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">Academic Teaching & Exam Claims</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Submit and track exam invigilation, script marking, overtime, and extra lecture claims
      </p>
    </div>

    <button type="button" class="btn btn-primary" onclick="openModal('claimModal')">
      <i class="fa-solid fa-plus-circle"></i> Submit New Claim
    </button>
  </div>

  <!-- Search & Filter Bar -->
  <form action="" method="GET" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0; flex:2;">
        <label class="form-label">Search Keyword</label>
        <div class="input-icon-wrapper">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" name="search" class="form-control" placeholder="Search by course code, description..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Filter by Status</label>
        <select name="status" class="form-select">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
          <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
          <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search"></i> Search</button>
        <a href="<?= base_url('frontend/views/lecturer/claims.php') ?>" class="btn btn-secondary">Clear</a>
      </div>
    </div>
  </form>

  <!-- Claims Table -->
  <div class="table-responsive">
    <table class="table-custom table-searchable">
      <thead>
        <tr>
          <th>Ref #</th>
          <th>Claim Type</th>
          <th>Course Code</th>
          <th>Date of Duty</th>
          <th>Qty / Rate</th>
          <th>Total Claim</th>
          <th>Status</th>
          <th>Description & Feedback</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($claims)): ?>
          <tr class="empty-row">
            <td colspan="9" style="text-align:center; padding:40px; color:var(--text-muted);">
              <i class="fa-solid fa-inbox" style="font-size:2rem; margin-bottom:8px; display:block; color:var(--border-color);"></i>
              No claims found. Click "Submit New Claim" to record exam invigilation or marking allowances.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($claims as $c): ?>
            <tr>
              <td><span style="font-family:monospace; font-weight:700; color:var(--text-muted);">#CLM-<?= $c['id'] ?></span></td>
              <td>
                <strong><?= ucwords(str_replace('_', ' ', $c['claim_type'])) ?></strong>
              </td>
              <td>
                <span class="badge badge-secondary"><?= htmlspecialchars($c['course_code'] ?? 'N/A') ?></span>
              </td>
              <td><?= format_date($c['claim_date']) ?></td>
              <td>
                <?= $c['quantity'] ?> &times; <?= format_currency($c['rate_per_unit']) ?>
              </td>
              <td style="font-weight:800; color:var(--ehc-primary); font-size:0.95rem;">
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
              <td style="max-width:240px;">
                <div style="font-size:0.84rem;"><?= htmlspecialchars($c['description']) ?></div>
                <?php if (!empty($c['rejection_reason'])): ?>
                  <div style="font-size:0.75rem; color:#EF4444; margin-top:3px; background:#FEF2F2; padding:3px 6px; border-radius:4px;">
                    <strong>Reason:</strong> <?= htmlspecialchars($c['rejection_reason']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($c['status'] === 'pending'): ?>
                  <form action="<?= base_url('backend/actions/claims_action.php') ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete claim #CLM-<?= $c['id'] ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/claims.php') ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Pending Claim">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:var(--text-subtle);">Locked</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= CLAIM MODAL ================= -->
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
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/claims.php') ?>">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Claim Type <span class="required">*</span></label>
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
          <label class="form-label">Description & Details <span class="required">*</span></label>
          <textarea name="description" class="form-control" placeholder="Provide full details of the exam session, papers marked, or lecture delivered." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Supporting Evidence (Timetable or Script list)</label>
          <input type="file" name="evidence" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.docx">
        </div>

        <div style="background:var(--bg-subtle); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:16px; display:flex; align-items:center; justify-content:space-between;">
          <div>
            <span style="font-size:0.78rem; text-transform:uppercase; color:var(--text-muted); font-weight:700;">Computed Kwacha Value</span>
            <div style="font-size:1.6rem; font-weight:800; color:var(--ehc-primary);" id="claimTotalDisplay">ZMW 120.00</div>
          </div>
          <i class="fa-solid fa-calculator" style="font-size:2rem; color:var(--border-color);"></i>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('claimModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Claim</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
