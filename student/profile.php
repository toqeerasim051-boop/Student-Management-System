<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('student');
$pageTitle = 'My Profile';
$breadcrumb = 'Profile';

$user = getCurrentUser();
$studentId = $user['entity_id'];

$stmt = $conn->prepare(
    "SELECT s.*, u.name, u.email, u.created_at, c.class_name, c.section
     FROM students s JOIN users u ON s.user_id=u.id
     LEFT JOIN classes c ON s.class_id=c.id
     WHERE s.id=?"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Subjects in this class
$subjects = [];
if ($profile['class_id']) {
    $sStmt = $conn->prepare(
        "SELECT s.subject_name, s.subject_code, u.name as teacher_name
         FROM subjects s
         LEFT JOIN teacher_assignments ta ON ta.subject_id=s.id AND ta.class_id=s.class_id
         LEFT JOIN teachers t ON ta.teacher_id=t.id
         LEFT JOIN users u ON t.user_id=u.id
         WHERE s.class_id=?"
    );
    $sStmt->bind_param("i", $profile['class_id']);
    $sStmt->execute();
    $subjects = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sStmt->close();
}

require_once '../includes/header.php';
?>

<div style="display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start;">

<!-- Profile Card -->
<div class="card">
    <div style="background:linear-gradient(135deg,#2563eb,#7c3aed);padding:28px;text-align:center;border-radius:var(--radius) var(--radius) 0 0">
        <div style="width:72px;height:72px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:30px;font-weight:800;color:#fff">
            <?= strtoupper(substr($profile['name'],0,1)) ?>
        </div>
        <h2 style="color:#fff;font-size:18px;font-weight:800"><?= h($profile['name']) ?></h2>
        <p style="color:rgba(255,255,255,0.8);font-size:13px;margin-top:4px"><?= h($profile['email']) ?></p>
        <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff;margin-top:8px">🎓 Student</span>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div><label>Roll Number</label><p style="font-weight:700;font-size:16px"><?= h($profile['roll_no'] ?: '—') ?></p></div>
            <div><label>Class</label><p><?= $profile['class_name'] ? h($profile['class_name'].' - Section '.$profile['section']) : '—' ?></p></div>
            <div><label>Phone</label><p><?= h($profile['phone'] ?: '—') ?></p></div>
            <div><label>Address</label><p><?= h($profile['address'] ?: '—') ?></p></div>
            <div style="border-top:1px solid var(--border);padding-top:14px">
                <label>Parent / Guardian</label>
                <p style="font-weight:600"><?= h($profile['parent_name'] ?: '—') ?></p>
            </div>
            <div><label>Parent Phone</label><p><?= h($profile['parent_phone'] ?: '—') ?></p></div>
            <div><label>Enrolled On</label><p><?= date('d M Y', strtotime($profile['created_at'])) ?></p></div>
        </div>
    </div>
</div>

<!-- Subjects -->
<div class="card">
    <div class="card-header"><span class="card-title">📚 My Subjects & Teachers</span></div>
    <?php if (empty($subjects)): ?>
    <div class="empty-state"><i class="fas fa-book"></i><h3>No subjects assigned</h3></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Subject</th><th>Code</th><th>Teacher</th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $i => $s): ?>
            <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td style="font-weight:600"><?= h($s['subject_name']) ?></td>
                <td><span class="badge badge-secondary"><?= h($s['subject_code'] ?: '—') ?></span></td>
                <td><?= h($s['teacher_name'] ?: 'Not Assigned') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div style="padding:16px;border-top:1px solid var(--border)">
        <div style="font-size:12px;color:var(--text-dim);background:var(--surface2);padding:12px;border-radius:8px;">
            <i class="fas fa-lock" style="margin-right:6px"></i>
            Profile information is managed by admin. Contact your school admin to update any information.
        </div>
    </div>
</div>
</div>

<?php require_once '../includes/footer.php'; ?>
