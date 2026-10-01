<?php
/**
 * EVELYN HONE COLLEGE CLOCKING SYSTEM
 * Lecturer Collaboration & Feedback Desk
 */

define('EHC_SYSTEM', true);
$pageTitle = 'Campus Collaboration & Inquiries';
require_once __DIR__ . '/../../../backend/includes/header.php';
require_role('lecturer');

$db = get_db();
$userId = $currentUser['id'];

// Fetch user's feedback messages
$stmt = $db->prepare("
    SELECT fb.*, d.name AS dept_name, u_resp.full_name AS responder_name
    FROM feedback_messages fb
    LEFT JOIN departments d ON fb.department_id = d.id
    LEFT JOIN users u_resp ON fb.responded_by = u_resp.id
    WHERE fb.sender_id = :uid
    ORDER BY fb.created_at DESC
");
$stmt->execute(['uid' => $userId]);
$messages = $stmt->fetchAll();
?>

<div class="card">
  <div class="card-header-flex">
    <div>
      <h2 style="font-size:1.35rem; color:var(--ehc-dark);">Collaboration & Inquiry Desk</h2>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;">
        Enhanced platform to resolve attendance disputes, claim inquiries, timetable coordination, and administrative collaboration.
      </p>
    </div>

    <button type="button" class="btn btn-primary" onclick="openModal('newFeedbackModal')">
      <i class="fa-solid fa-plus-circle"></i> New Inquiry / Message
    </button>
  </div>

  <div class="table-responsive">
    <table class="table-custom">
      <thead>
        <tr>
          <th>Ticket Ref</th>
          <th>Category</th>
          <th>Subject</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Sent Date</th>
          <th>Response from Admin / HOD</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr class="empty-row">
            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">
              <i class="fa-regular fa-comments" style="font-size:2rem; margin-bottom:8px; display:block; color:var(--border-color);"></i>
              No messages posted yet. Use the button above to communicate with your HOD or Administration.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($messages as $msg): ?>
            <tr>
              <td><span style="font-family:monospace; font-weight:700;">#FBC-<?= $msg['id'] ?></span></td>
              <td><span class="badge badge-secondary"><?= htmlspecialchars($msg['category']) ?></span></td>
              <td>
                <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                <p style="font-size:0.82rem; color:var(--text-muted); margin-top:3px;"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
              </td>
              <td>
                <span class="badge <?= $msg['priority'] === 'urgent' || $msg['priority'] === 'high' ? 'badge-danger' : 'badge-info' ?>">
                  <?= ucfirst($msg['priority']) ?>
                </span>
              </td>
              <td>
                <?php if ($msg['status'] === 'resolved'): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-check"></i> Resolved</span>
                <?php elseif ($msg['status'] === 'in_progress'): ?>
                  <span class="badge badge-warning">In Progress</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Open</span>
                <?php endif; ?>
              </td>
              <td><?= format_datetime($msg['created_at']) ?></td>
              <td style="max-width:280px;">
                <?php if (!empty($msg['response_text'])): ?>
                  <div style="background:#ECFDF5; border:1px solid #A7F3D0; padding:10px; border-radius:var(--radius-sm); font-size:0.82rem; color:#065F46;">
                    <strong><?= htmlspecialchars($msg['responder_name'] ?? 'Administration') ?>:</strong><br>
                    <?= nl2br(htmlspecialchars($msg['response_text'])) ?>
                    <div style="font-size:0.72rem; color:#047857; margin-top:4px;">Replied on <?= format_datetime($msg['responded_at']) ?></div>
                  </div>
                <?php else: ?>
                  <span style="font-size:0.8rem; color:var(--text-muted); font-style:italic;">Awaiting administrative response...</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= NEW INQUIRY MODAL ================= -->
<div class="modal-backdrop" id="newFeedbackModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-comment-medical" style="color:var(--ehc-primary);"></i> Submit Inquiry or Message</h3>
      <button type="button" class="modal-close" onclick="closeModal('newFeedbackModal')">&times;</button>
    </div>
    <form action="<?= base_url('backend/actions/feedback_action.php') ?>" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="redirect" value="<?= base_url('frontend/views/lecturer/feedback.php') ?>">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Category <span class="required">*</span></label>
            <select name="category" class="form-select" required>
              <option value="Attendance Dispute">Attendance & Gate Reader Dispute</option>
              <option value="Claim Inquiry">Exam Marking & Invigilation Claim Inquiry</option>
              <option value="Timetable Collaboration">Lecture Timetable Collaboration</option>
              <option value="Premise Access">Campus Premise Access Issue</option>
              <option value="General">General Administrative Inquiry</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Priority Level <span class="required">*</span></label>
            <select name="priority" class="form-select" required>
              <option value="low">Low (General Inquiry)</option>
              <option value="medium" selected>Medium (Standard)</option>
              <option value="high">High (Payroll / Timetable Affecting)</option>
              <option value="urgent">Urgent (Immediate Gate / Exam Conflict)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Subject / Title <span class="required">*</span></label>
          <input type="text" name="subject" class="form-control" placeholder="Brief subject summary" required>
        </div>

        <div class="form-group">
          <label class="form-label">Message Details <span class="required">*</span></label>
          <textarea name="message" class="form-control" style="min-height:120px;" placeholder="Describe the inquiry, course code, dates involved, or administrative question..." required></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newFeedbackModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Inquiry</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../../backend/includes/footer.php'; ?>
