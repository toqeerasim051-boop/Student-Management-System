<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('teacher');
$pageTitle = 'My Students';
$breadcrumb = 'Students';

$user = getCurrentUser();
$teacherId = $user['entity_id'];

// Only classes this teacher is assigned to
$myClasses = $conn->prepare(
    "SELECT DISTINCT c.id, c.class_name, c.section
     FROM teacher_assignments ta JOIN classes c ON ta.class_id=c.id
     WHERE ta.teacher_id=? ORDER BY c.class_name, c.section"
);
$myClasses->bind_param("i", $teacherId);
$myClasses->execute();
$classes = $myClasses->get_result()->fetch_all(MYSQLI_ASSOC);
$myClasses->close();

$selClass = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));

// Verify teacher owns this class
$owns = false;
foreach ($classes as $c) { if ($c['id'] == $selClass) { $owns = true; break; } }
if (!$owns) $selClass = $classes[0]['id'] ?? 0;

$students = [];
$selClassName = '';
if ($selClass) {
    foreach ($classes as $c) { if ($c['id']==$selClass) { $selClassName = $c['class_name'].' '.$c['section']; break; } }

    $stmt = $conn->prepare(
        "SELECT s.id, s.roll_no, s.phone, s.parent_name, s.parent_phone, u.name, u.email,
                (SELECT COUNT(*) FROM attendance WHERE student_id=s.id AND status='present') as present_count,
                (SELECT COUNT(*) FROM attendance WHERE student_id=s.id) as total_att,
                (SELECT AVG(marks/max_marks*100) FROM grades WHERE student_id=s.id) as avg_grade
         FROM students s JOIN users u ON s.user_id=u.id
         WHERE s.class_id=? AND u.status='active'
         ORDER BY s.roll_no, u.name"
    );
    $stmt->bind_param("i", $selClass);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Class Tabs -->
<?php if (!empty($classes)): ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
    <?php foreach ($classes as $c): ?>
    <a href="students.php?class_id=<?= $c['id'] ?>"
       class="btn <?= $selClass==$c['id']?'btn-primary':'btn-outline' ?>">
        <i class="fas fa-school"></i> <?= h($c['class_name'].' '.$c['section']) ?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-user-graduate" style="color:var(--primary)"></i>
            Students in <?= h($selClassName) ?>
            <span class="badge badge-primary"><?= count($students) ?></span>
        </span>
        <div class="d-flex gap-10">
            <button onclick="exportCSV('studentsTable','my_students_<?= $selClass ?>')" class="btn btn-outline btn-sm">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>

    <?php if (empty($classes)): ?>
    <div class="empty-state">
        <i class="fas fa-chalkboard"></i>
        <h3>No classes assigned</h3>
        <p>Please contact admin to assign classes to you.</p>
    </div>

    <?php elseif (empty($students)): ?>
    <div class="empty-state">
        <i class="fas fa-user-graduate"></i>
        <h3>No students in this class</h3>
        <p>Admin needs to add students to <?= h($selClassName) ?></p>
    </div>

    <?php else: ?>
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchIn" placeholder="Search students..." onkeyup="liveSearch()">
        </div>
        <span class="text-muted" style="font-size:13px"><i class="fas fa-info-circle"></i> Showing only your assigned class</span>
    </div>

    <div class="table-wrapper">
        <table id="studentsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Roll No</th>
                    <th>Phone</th>
                    <th>Parent</th>
                    <th>Attendance</th>
                    <th>Avg Grade</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $i => $st):
                $attPct = $st['total_att'] > 0 ? round($st['present_count']/$st['total_att']*100) : 0;
                $attClass = $attPct >= 75 ? 'good' : ($attPct >= 60 ? 'warn' : 'bad');
                $avgGrade = $st['avg_grade'] !== null ? round($st['avg_grade'],1) : null;
                $gradeLabel = $avgGrade !== null ? ($avgGrade>=90?'A+':($avgGrade>=80?'A':($avgGrade>=70?'B':($avgGrade>=60?'C':($avgGrade>=50?'D':'F'))))) : '—';
                $gradeBadge = $avgGrade !== null ? ($avgGrade>=60?'success':($avgGrade>=50?'warning':'danger')) : 'secondary';
            ?>
            <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td>
                    <div style="font-weight:600"><?= h($st['name']) ?></div>
                    <small class="text-muted"><?= h($st['email']) ?></small>
                </td>
                <td><span class="badge badge-info"><?= h($st['roll_no'] ?: '—') ?></span></td>
                <td class="text-muted"><?= h($st['phone'] ?: '—') ?></td>
                <td>
                    <div style="font-size:13px"><?= h($st['parent_name'] ?: '—') ?></div>
                    <?php if ($st['parent_phone']): ?><small class="text-muted"><?= h($st['parent_phone']) ?></small><?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress-bar" style="width:70px">
                            <div class="progress-fill <?= $attClass ?>" style="width:<?= $attPct ?>%"></div>
                        </div>
                        <span style="font-size:12px;font-weight:600"><?= $attPct ?>%</span>
                    </div>
                    <small class="text-muted"><?= $st['present_count'] ?>/<?= $st['total_att'] ?> classes</small>
                </td>
                <td>
                    <span class="badge badge-<?= $gradeBadge ?>"><?= $gradeLabel ?></span>
                    <?php if ($avgGrade !== null): ?><small class="text-muted">(<?= $avgGrade ?>%)</small><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function liveSearch() {
    const val = document.getElementById('searchIn').value.toLowerCase();
    document.querySelectorAll('#studentsTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
