<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('student');
$pageTitle = 'My Dashboard';

$user = getCurrentUser();
$studentId = $user['entity_id'];

if (!$studentId) {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif"><h2>⚠️ Profile Error</h2><p>Your student profile is not configured. Please contact admin.</p></div>');
}

// Student info
$stmt = $conn->prepare(
    "SELECT s.*, u.name, u.email, c.class_name, c.section
     FROM students s JOIN users u ON s.user_id=u.id
     LEFT JOIN classes c ON s.class_id=c.id
     WHERE s.id=?"
);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Attendance summary
$attStmt = $conn->prepare(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
     FROM attendance WHERE student_id=?"
);
$attStmt->bind_param("i", $studentId);
$attStmt->execute();
$attSummary = $attStmt->get_result()->fetch_assoc();
$attStmt->close();
$attPct = $attSummary['total'] > 0 ? round($attSummary['present']/$attSummary['total']*100) : 0;

// Recent grades
$gradeStmt = $conn->prepare(
    "SELECT g.*, s.subject_name, ROUND(g.marks/g.max_marks*100,1) as pct
     FROM grades g JOIN subjects s ON g.subject_id=s.id
     WHERE g.student_id=? ORDER BY g.updated_at DESC LIMIT 6"
);
$gradeStmt->bind_param("i", $studentId);
$gradeStmt->execute();
$grades = $gradeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$gradeStmt->close();

$avgGrade = 0;
if ($grades) {
    $avgGrade = round(array_sum(array_column($grades, 'pct')) / count($grades), 1);
}

// Announcements
$anns = $conn->query("SELECT * FROM announcements WHERE target_role IN ('all','student') ORDER BY created_at DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,#2563eb,#7c3aed);border-radius:var(--radius);padding:24px;margin-bottom:20px;color:#fff;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;">Hello, <?= h(explode(' ',$user['name'])[0]) ?>! 🎓</h2>
            <p style="opacity:0.85;font-size:14px;">
                Class: <strong><?= $profile['class_name'] ? h($profile['class_name'].' '.$profile['section']) : 'Not Assigned' ?></strong>
                &bull; Roll No: <strong><?= h($profile['roll_no'] ?: '—') ?></strong>
            </p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:13px;opacity:0.8"><?= date('l, d M Y') ?></div>
            <div style="font-size:12px;opacity:0.7;margin-top:2px;">Attendance: <?= $attPct ?>%</div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-info">
            <div class="stat-label">Present Days</div>
            <div class="stat-value text-success"><?= $attSummary['present'] ?? 0 ?></div>
            <div class="stat-sub">out of <?= $attSummary['total'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info">
            <div class="stat-label">Absent Days</div>
            <div class="stat-value text-danger"><?= $attSummary['absent'] ?? 0 ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $attPct>=75?'green':($attPct>=60?'yellow':'red') ?>"><i class="fas fa-percent"></i></div>
        <div class="stat-info">
            <div class="stat-label">Attendance %</div>
            <div class="stat-value <?= $attPct>=75?'text-success':($attPct>=60?'text-warning':'text-danger') ?>"><?= $attPct ?>%</div>
            <div class="stat-sub"><?= $attPct>=75?'✅ Good':'⚠️ Needs improvement' ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-star"></i></div>
        <div class="stat-info">
            <div class="stat-label">Average Grade</div>
            <div class="stat-value"><?= $avgGrade ?>%</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:16px;">

<!-- Recent Grades -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📊 Recent Grades</span>
        <a href="grades.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <?php if (empty($grades)): ?>
    <div class="empty-state"><i class="fas fa-star"></i><h3>No grades yet</h3><p>Grades will appear after exams.</p></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Subject</th><th>Term</th><th>Marks</th><th>Grade</th></tr></thead>
            <tbody>
            <?php foreach ($grades as $g):
                $pct = $g['pct'];
                $gl = $pct>=90?'A+':($pct>=80?'A':($pct>=70?'B':($pct>=60?'C':($pct>=50?'D':'F'))));
                $gb = $pct>=60?'success':($pct>=50?'warning':'danger');
            ?>
            <tr>
                <td style="font-weight:600"><?= h($g['subject_name']) ?></td>
                <td><span class="badge badge-secondary"><?= h($g['term']) ?></span></td>
                <td><?= $g['marks'] ?>/<?= $g['max_marks'] ?>
                    <div class="progress-bar" style="margin-top:4px">
                        <div class="progress-fill <?= $pct>=60?'good':($pct>=50?'warn':'bad') ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </td>
                <td><span class="badge badge-<?= $gb ?>"><?= $gl ?> (<?= $pct ?>%)</span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Announcements -->
<div class="card">
    <div class="card-header"><span class="card-title">📢 Announcements</span></div>
    <div style="padding:12px;display:flex;flex-direction:column;gap:10px;">
        <?php if (empty($anns)): ?>
        <div class="empty-state" style="padding:30px"><p>No announcements.</p></div>
        <?php else: foreach ($anns as $ann): ?>
        <div style="background:var(--surface2);border-radius:8px;padding:12px;border-left:3px solid var(--primary)">
            <div style="font-weight:600;margin-bottom:4px"><?= h($ann['title']) ?></div>
            <p style="font-size:12px;color:var(--text-muted)"><?= h(substr($ann['content'],0,100)) ?><?= strlen($ann['content'])>100?'...':'' ?></p>
            <small class="text-muted"><?= date('d M Y', strtotime($ann['created_at'])) ?></small>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

</div>

<?php require_once '../includes/footer.php'; ?>
