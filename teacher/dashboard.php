<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('teacher');
$pageTitle = 'Teacher Dashboard';

$user = getCurrentUser();
$teacherId = $user['entity_id'];

if (!$teacherId) {
    die('<div style="padding:40px;text-align:center;font-family:sans-serif"><h2>⚠️ Profile Error</h2><p>Your teacher profile is not configured. Please contact admin.</p></div>');
}

// Get this teacher's assigned classes & subjects ONLY
$myAssignments = $conn->prepare(
    "SELECT ta.id as asgn_id, s.id as subject_id, s.subject_name, s.subject_code,
            c.id as class_id, c.class_name, c.section,
            (SELECT COUNT(*) FROM students WHERE class_id=c.id) as student_count
     FROM teacher_assignments ta
     JOIN subjects s ON ta.subject_id=s.id
     JOIN classes c ON ta.class_id=c.id
     WHERE ta.teacher_id=?
     ORDER BY c.class_name, c.section, s.subject_name"
);
$myAssignments->bind_param("i", $teacherId);
$myAssignments->execute();
$assignments = $myAssignments->get_result()->fetch_all(MYSQLI_ASSOC);
$myAssignments->close();

// Stats for this teacher only
$myClassCount = count(array_unique(array_column($assignments, 'class_id')));
$mySubjectCount = count($assignments);
$myStudentCount = 0;
$seenClasses = [];
foreach ($assignments as $a) {
    if (!in_array($a['class_id'], $seenClasses)) {
        $myStudentCount += $a['student_count'];
        $seenClasses[] = $a['class_id'];
    }
}

// Today's attendance marked by this teacher
$today = date('Y-m-d');
$todayAtt = $conn->prepare(
    "SELECT COUNT(*) FROM attendance a
     JOIN teacher_assignments ta ON ta.subject_id=a.subject_id AND ta.class_id=a.class_id
     WHERE ta.teacher_id=? AND a.date=?"
);
$todayAtt->bind_param("is", $teacherId, $today);
$todayAtt->execute();
$markedToday = $todayAtt->get_result()->fetch_row()[0];
$todayAtt->close();

// Announcements for teachers
$announcements = $conn->query("SELECT * FROM announcements WHERE target_role IN ('all','teacher') ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,var(--primary),#6366f1);border-radius:var(--radius);padding:24px;margin-bottom:20px;color:#fff;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:20px;font-weight:800;margin-bottom:4px;">Welcome back, <?= h(explode(' ', $user['name'])[0]) ?>! 👋</h2>
            <p style="opacity:0.85;font-size:14px;">You are teaching <?= $mySubjectCount ?> subject(s) across <?= $myClassCount ?> class(es)</p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:13px;opacity:0.8"><?= date('l, d M Y') ?></div>
            <div style="font-size:12px;opacity:0.7;margin-top:2px;">Attendance marked today: <?= $markedToday ?> record(s)</div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-school"></i></div>
        <div class="stat-info">
            <div class="stat-label">My Classes</div>
            <div class="stat-value"><?= $myClassCount ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-book"></i></div>
        <div class="stat-info">
            <div class="stat-label">My Subjects</div>
            <div class="stat-value"><?= $mySubjectCount ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-info">
            <div class="stat-label">My Students</div>
            <div class="stat-value"><?= $myStudentCount ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-info">
            <div class="stat-label">Marked Today</div>
            <div class="stat-value"><?= $markedToday ?></div>
        </div>
    </div>
</div>

<!-- My Assignments -->
<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:16px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">📚 My Assigned Classes & Subjects</span>
            <a href="attendance.php" class="btn btn-primary btn-sm"><i class="fas fa-calendar-check"></i> Mark Attendance</a>
        </div>
        <?php if (empty($assignments)): ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard"></i>
            <h3>No classes assigned yet</h3>
            <p>Please contact admin to assign classes and subjects.</p>
        </div>
        <?php else:
            // Group by class
            $grouped = [];
            foreach ($assignments as $a) {
                $key = $a['class_id'];
                $grouped[$key]['class_name'] = $a['class_name'] . ' ' . $a['section'];
                $grouped[$key]['class_id'] = $a['class_id'];
                $grouped[$key]['student_count'] = $a['student_count'];
                $grouped[$key]['subjects'][] = $a;
            }
        ?>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
            <?php foreach ($grouped as $cls): ?>
            <div style="background:var(--surface2);border-radius:10px;padding:16px;border:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div style="font-weight:700;font-size:15px;">
                        <i class="fas fa-school" style="color:var(--primary);margin-right:6px"></i>
                        <?= h($cls['class_name']) ?>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span class="badge badge-info"><?= $cls['student_count'] ?> students</span>
                        <a href="attendance.php?class_id=<?= $cls['class_id'] ?>" class="btn btn-primary btn-xs">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($cls['subjects'] as $sub): ?>
                    <span class="badge badge-primary" style="font-size:12px;padding:5px 12px;">
                        <i class="fas fa-book" style="margin-right:4px"></i>
                        <?= h($sub['subject_name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div style="padding:16px;border-top:1px solid var(--border);">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-dim);margin-bottom:10px;">Quick Actions</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="attendance.php" class="btn btn-outline btn-sm"><i class="fas fa-calendar-check"></i> Mark Attendance</a>
                <a href="grades.php" class="btn btn-outline btn-sm"><i class="fas fa-star"></i> Enter Grades</a>
                <a href="students.php" class="btn btn-outline btn-sm"><i class="fas fa-users"></i> View Students</a>
            </div>
        </div>
    </div>

    <!-- Announcements -->
    <div class="card">
        <div class="card-header"><span class="card-title">📢 Announcements</span></div>
        <div style="padding:12px;display:flex;flex-direction:column;gap:10px;">
            <?php if (empty($announcements)): ?>
            <div class="empty-state" style="padding:30px"><p>No announcements.</p></div>
            <?php else: foreach ($announcements as $ann): ?>
            <div style="background:var(--surface2);border-radius:8px;padding:12px;border-left:3px solid var(--primary)">
                <div style="font-weight:600;font-size:14px;margin-bottom:4px"><?= h($ann['title']) ?></div>
                <p style="font-size:12px;color:var(--text-muted);line-height:1.5"><?= h(substr($ann['content'],0,120)) ?><?= strlen($ann['content'])>120?'...':'' ?></p>
                <div style="font-size:11px;color:var(--text-dim);margin-top:6px"><?= date('d M Y', strtotime($ann['created_at'])) ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
