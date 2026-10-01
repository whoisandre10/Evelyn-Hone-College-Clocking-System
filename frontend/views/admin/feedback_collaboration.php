<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Admin Campus Feedback & Collaboration Desk
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Campus Collaboration Desk';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('admin');

$db = get_db();

$statusFilter = $_GET['status'] ?? 'all';
$catFilter = $_GET['category'] ?? 'all';

$sql = "
    SELECT fb.*, u.full_name AS sender_name, u.staff_id, u.role, u.email,
           d.name AS dept_name,
           u_resp.full_name AS responder_name
    FROM feedback_messages fb
    JOIN users u ON fb.sender_id = u.id
    LEFT JOIN departments d ON fb.department_id = d.id
    LEFT JOIN users u_resp ON fb.responded_by = u_resp.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND fb.status = :status";
    $params['status'] = $statusFilter;
}

if ($catFilter !== 'all') {
    $sql .= " AND fb.category = :cat";
    $params['cat'] = $catFilter;
}

$sql .= " ORDER BY fb.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">Institutional Feedback & Collaboration Hub</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Review staff inquiries, attendance disputes, timetable collaboration, and send administrative resolutions.
      </p>
    </div>
  </div>

  <!-- Filters -->
  <form action="" method="GET" style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:24px;">
    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <option value="all">All Categories</option>
          <option value="Attendance Dispute" <?= $catFilter === 'Attendance Dispute' ? 'selected' : '' ?>>Attendance & Gate Reader Dispute</option>
          <option value="Claim Inquiry" <?= $catFilter === 'Claim Inquiry' ? 'selected' : '' ?>>Claim & Allowance Inquiry</option>
          <option value="Timetable Collaboration" <?= $catFilter === 'Timetable Collaboration' ? 'selected' : '' ?>>Timetable Collaboration</option>
          <option value="Premise Access" <?= $catFilter === 'Premise Access' ? 'selected' : '' ?>>Campus Premise Access</option>
          <option value="General" <?= $catFilter === 'General' ? 'selected' : '' ?>>General Inquiry</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; flex:1;">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="all">All Tickets</option>
          <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open Tickets</option>
          <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:0; display:flex; gap:8px;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?= base_url('frontend/views/admin/feedback_collaboration.php') ?>" class="btn btn-secondary">Clear</a>
      </div>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Staff Member</th>
          <th>Category & Subject</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Date Sent</th>
          <th>Administrative Response</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr class="empty-row"><td colspan="8" style="text-align:center; padding:40px; color:var(--text-muted);">No feedback or collaboration messages found.</td></tr>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <tr>
              <td><span style="font-family:monospace; font-weight:700;">#FBC-<?= $m['id'] ?></span></td>
              <td>
                <strong><?= htmlspecialchars($m['sender_name']) ?></strong><br>
                <small style="color:var(--text-muted); font-family:monospace;"><?= htmlspecialchars($m['staff_id']) ?></small> &bull;
                <span class="badge <?= $m['role'] === 'lecturer' ? 'badge-warning' : 'badge-info' ?>" style="font-size:0.68rem;"><?= ucfirst($m['role']) ?></span>
              </td>
              <td style="max-width:280px;">
                <span class="badge badge-secondary"><?= htmlspecialchars($m['category']) ?></span>
                <div style="font-weight:700; color:var(--ehc-dark); margin-top:4px;"><?= htmlspecialchars($m['subject']) ?></div>
                <div style="font-size:0.83rem; color:var(--text-muted); margin-top:2px;"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
              </td>
              <td>
                <span class="badge <?= $m['priority'] === 'urgent' || $m['priority'] === 'high' ? 'badge-danger' : 'badge-info' ?>">
                  <?= ucfirst($m['priority']) ?>
                </span>
              </td>
              <td>
                <?php if ($m['status'] === 'resolved'): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-check"></i> Resolved</span>
                <?php elseif ($m['status'] === 'in_progress'): ?>
                  <span class="badge badge-warning">In Progress</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Open</span>
                <?php endif; ?>
              </td>
              <td style="font-size:0.8rem;"><?= format_datetime($m['created_at']) ?></td>
              <td style="max-width:240px;">
                <?php if (!empty($m['response_text'])): ?>
                  <div style="background:#ECFDF5; padding:8px 10px; border-radius:var(--radius-sm); border:1px solid #A7F3D0; font-size:0.8rem; color:#065F46;">
                    <strong><?= htmlspecialchars($m['responder_name'] ?? 'Admin') ?>:</strong><br>
                    <?= nl2br(htmlspecialchars($m['response_text'])) ?>
                  </div>
                <?php else: ?>
                  <span style="font-size:0.78rem; color:var(--text-muted); font-style:italic;">No response sent yet</span>
                <?php endif; ?>
              </td>
              <td>
                <button type="button" class="btn btn-primary btn-sm" onclick='openReplyModal(<?= json_encode($m) ?>)'>
                  <i class="fa-solid fa-reply"></i> Reply
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= REPLY MODAL ================= -->
<div class="modal-backdrop" id="replyModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-reply" style="color:var(--ehc-primary);"></i> Respond to Staff Inquiry</h3>
      <button type="button" class="modal-close" onclick="closeModal('replyModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/feedback_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="reply">
      <input type="hidden" name="feedback_id" id="replyFbId">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/admin/feedback_collaboration.php') ?>">

      <div class="modal-body">
        <div id="replySubjectDisplay" style="font-weight:700; color:var(--ehc-dark); margin-bottom:12px;"></div>

        <div class="form-group">
          <label class="form-label">Administrative Resolution / Reply <span class="required">*</span></label>
          <textarea name="response_text" id="replyResponseText" class="form-control" style="min-height:130px;" placeholder="Provide clarification, gate log confirmation, or action taken..." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Ticket Status <span class="required">*</span></label>
          <select name="status" id="replyStatusSelect" class="form-select" required>
            <option value="resolved">Resolved (Inquiry Addressed)</option>
            <option value="in_progress">In Progress (Investigation Underway)</option>
            <option value="closed">Closed</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('replyModal')">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fa-solid fa-paper-plane"></i> Send Official Response</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openReplyModal(m) {
    document.getElementById('replyFbId').value = m.id;
    document.getElementById('replySubjectDisplay').textContent = '#' + m.id + ' - ' + m.sender_name + ': "' + m.subject + '"';
    document.getElementById('replyResponseText').value = m.response_text || '';
    document.getElementById('replyStatusSelect').value = m.status === 'open' ? 'resolved' : m.status;
    openModal('replyModal');
  }
</script>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
