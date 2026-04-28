<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('student');
$pageTitle = 'My Grades';
$breadcrumb = 'Grades';

$user = getCurrentUser();
$studentId = $user['entity_id'];

$filterTerm = $_GET['term'] ?? '';

// All grades for this student
$where = "WHERE g.student_id=?";
$params = [$studentId]; $types = 'i';
if ($filterTerm) { $where .= " AND g.term=?"; $params[]=$filterTerm; $types.='s'; }

$stmt = $conn->prepare(
    "SELECT g.*, s.subject_name, ROUND(g.marks/g.max_marks*100,1) as pct
     FROM grades g JOIN subjects s ON g.subject_id=s.id
     $where ORDER BY g.term, s.subject_name"
);
$stmt->bind_param($types,...$params);
$stmt->execute();
$grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Terms available
$termsStmt = $conn->prepare("SELECT DISTINCT term FROM grades WHERE student_id=? ORDER BY term");
$termsStmt->bind_param("i", $studentId);
$termsStmt->execute();
$terms = $termsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$termsStmt->close();

// Overall stats
$overallStmt = $conn->prepare(
    "SELECT COUNT(*) as total, AVG(marks/max_marks*100) as avg_pct,
            MAX(marks/max_marks*100) as best, MIN(marks/max_marks*100) as lowest
     FROM grades WHERE student_id=?"
);
$overallStmt->bind_param("i", $studentId);
$overallStmt->execute();
$overall = $overallStmt->get_result()->fetch_assoc();
$overallStmt->close();

// Group by term
$gradesByTerm = [];
foreach ($grades as $g) {
    $gradesByTerm[$g['term']][$g['exam_type']][] = $g;
}

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:16px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-book"></i></div>
        <div class="stat-info"><div class="stat-label">Total Exams</div><div class="stat-value"><?= $overall['total'] ?? 0 ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info"><div class="stat-label">Average</div><div class="stat-value"><?= round($overall['avg_pct'] ?? 0, 1) ?>%</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-trophy"></i></div>
        <div class="stat-info"><div class="stat-label">Best Score</div><div class="stat-value text-success"><?= round($overall['best'] ?? 0, 1) ?>%</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-arrow-down"></i></div>
        <div class="stat-info"><div class="stat-label">Lowest Score</div><div class="stat-value text-warning"><?= round($overall['lowest'] ?? 0, 1) ?>%</div></div>
    </div>
</div>

<!-- Filter -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <a href="grades.php" class="btn <?= !$filterTerm?'btn-primary':'btn-outline' ?> btn-sm">All Terms</a>
    <?php foreach ($terms as $t): ?>
    <a href="grades.php?term=<?= urlencode($t['term']) ?>" class="btn <?= $filterTerm===$t['term']?'btn-primary':'btn-outline' ?> btn-sm">
        <?= h($t['term']) ?>
    </a>
    <?php endforeach; ?>
    <?php if (!empty($grades)): ?>
    <button onclick="exportCSV('gradesTable','my_grades')" class="btn btn-outline btn-sm" style="margin-left:auto"><i class="fas fa-file-csv"></i> Export</button>
    <?php endif; ?>
</div>

<?php if (empty($grades)): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-star"></i>
        <h3>No grades yet</h3>
        <p>Your grades will appear here after exams are marked by your teachers.</p>
    </div>
</div>

<?php else: ?>
<!-- Grades by Term -->
<?php foreach ($gradesByTerm as $term => $examTypes): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <span class="card-title">📚 <?= h($term) ?></span>
        <?php
        $termGrades = array_merge(...array_values($examTypes));
        $termAvg = round(array_sum(array_column($termGrades,'pct'))/count($termGrades),1);
        $termGradeL = $termAvg>=90?'A+':($termAvg>=80?'A':($termAvg>=70?'B':($termAvg>=60?'C':($termAvg>=50?'D':'F'))));
        $termBadge = $termAvg>=60?'success':($termAvg>=50?'warning':'danger');
        ?>
        <span class="badge badge-<?= $termBadge ?>" style="font-size:13px">Term Average: <?= $termAvg ?>% (<?= $termGradeL ?>)</span>
    </div>

    <?php foreach ($examTypes as $examType => $examGrades): ?>
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-dim)">
        📝 <?= h($examType) ?>
    </div>
    <div class="table-wrapper">
        <table id="gradesTable">
            <thead><tr><th>Subject</th><th>Marks</th><th>Max</th><th>Performance</th><th>Grade</th></tr></thead>
            <tbody>
            <?php foreach ($examGrades as $g):
                $pct = $g['pct'];
                $gl = $pct>=90?'A+':($pct>=80?'A':($pct>=70?'B':($pct>=60?'C':($pct>=50?'D':'F'))));
                $gb = $pct>=60?'success':($pct>=50?'warning':'danger');
                $fillClass = $pct>=60?'good':($pct>=50?'warn':'bad');
            ?>
            <tr>
                <td style="font-weight:600"><?= h($g['subject_name']) ?></td>
                <td style="font-size:16px;font-weight:700"><?= $g['marks'] ?></td>
                <td class="text-muted"><?= $g['max_marks'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="progress-bar" style="width:120px">
                            <div class="progress-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <strong><?= $pct ?>%</strong>
                    </div>
                </td>
                <td>
                    <span class="badge badge-<?= $gb ?>" style="font-size:13px"><?= $gl ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
