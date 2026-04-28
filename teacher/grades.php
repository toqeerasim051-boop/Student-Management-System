<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('teacher');
$pageTitle = 'Enter Grades';
$breadcrumb = 'Grades';

$user = getCurrentUser();
$teacherId = $user['entity_id'];

function teacherOwns($conn, $teacherId, $classId, $subjectId) {
    $s = $conn->prepare("SELECT id FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=?");
    $s->bind_param("iii", $teacherId, $classId, $subjectId);
    $s->execute();
    $ok = $s->get_result()->num_rows > 0;
    $s->close();
    return $ok;
}

// Get assignments
$stmt = $conn->prepare(
    "SELECT ta.subject_id, ta.class_id, s.subject_name, c.class_name, c.section
     FROM teacher_assignments ta
     JOIN subjects s ON ta.subject_id=s.id
     JOIN classes c ON ta.class_id=c.id
     WHERE ta.teacher_id=? ORDER BY c.class_name, s.subject_name"
);
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$assignedClasses = [];
$assignedSubjects = [];
foreach ($assignments as $a) {
    $assignedClasses[$a['class_id']] = $a['class_name'].' - Section '.$a['section'];
    $assignedSubjects[$a['class_id']][$a['subject_id']] = $a['subject_name'];
}

$selClass   = (int)($_GET['class_id'] ?? 0);
$selSubject = (int)($_GET['subject_id'] ?? 0);

if ($selClass && $selSubject && !teacherOwns($conn, $teacherId, $selClass, $selSubject)) {
    setFlash('error', '⛔ Access denied.');
    $selClass = $selSubject = 0;
}

// Save grades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    verifyCsrf();
    $postClass   = (int)$_POST['class_id'];
    $postSubject = (int)$_POST['subject_id'];
    $term        = trim($_POST['term']);
    $examType    = trim($_POST['exam_type']);
    $maxMarks    = (float)$_POST['max_marks'];

    if (!teacherOwns($conn, $teacherId, $postClass, $postSubject)) {
        setFlash('error', '⛔ Access denied.'); redirect('grades.php');
    }

    $gradesData = $_POST['marks'] ?? [];
    $saved = 0;
    foreach ($gradesData as $studentId => $marks) {
        $studentId = (int)$studentId;
        if ($marks === '' || $marks === null) continue;
        $marks = (float)$marks;
        if ($marks < 0) $marks = 0;
        if ($marks > $maxMarks) $marks = $maxMarks;

        // Verify student is in this class
        $chk = $conn->prepare("SELECT id FROM students WHERE id=? AND class_id=?");
        $chk->bind_param("ii", $studentId, $postClass);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) { $chk->close(); continue; }
        $chk->close();

        $g = $conn->prepare(
            "INSERT INTO grades (student_id, subject_id, marks, max_marks, term, exam_type)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE marks=VALUES(marks), max_marks=VALUES(max_marks)"
        );
        $g->bind_param("iiddss", $studentId, $postSubject, $marks, $maxMarks, $term, $examType);
        $g->execute(); $g->close();
        $saved++;
    }

    logAction($conn, $user['id'], 'teacher', "Entered grades", '', "$saved students, $term $examType");
    setFlash('success', "✅ Grades saved for $saved student(s).");
    redirect("grades.php?class_id=$postClass&subject_id=$postSubject");
}

// Load students + existing grades
$students = [];
$existingGrades = [];
$selTerm = $_GET['term'] ?? 'Term 1';
$selExam = $_GET['exam_type'] ?? 'Mid Term';

if ($selClass && $selSubject) {
    $stmt = $conn->prepare(
        "SELECT s.id, s.roll_no, u.name
         FROM students s JOIN users u ON s.user_id=u.id
         WHERE s.class_id=? AND u.status='active' ORDER BY s.roll_no, u.name"
    );
    $stmt->bind_param("i", $selClass);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $gStmt = $conn->prepare(
        "SELECT student_id, marks, max_marks FROM grades WHERE subject_id=? AND term=? AND exam_type=?"
    );
    $gStmt->bind_param("iss", $selSubject, $selTerm, $selExam);
    $gStmt->execute();
    foreach ($gStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $existingGrades[$row['student_id']] = $row;
    }
    $gStmt->close();
}

// History of grades this teacher entered
$histStmt = $conn->prepare(
    "SELECT g.*, u.name as student_name, s.subject_name, c.class_name, c.section,
            ROUND((g.marks/g.max_marks)*100,1) as pct
     FROM grades g
     JOIN students st ON g.student_id=st.id
     JOIN users u ON st.user_id=u.id
     JOIN subjects s ON g.subject_id=s.id
     LEFT JOIN classes c ON st.class_id=c.id
     WHERE EXISTS (
         SELECT 1 FROM teacher_assignments ta
         WHERE ta.teacher_id=? AND ta.subject_id=g.subject_id AND ta.class_id=st.class_id
     )
     ORDER BY g.updated_at DESC LIMIT 20"
);
$histStmt->bind_param("i", $teacherId);
$histStmt->execute();
$gradeHistory = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$histStmt->close();

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Filter -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title"><i class="fas fa-filter" style="color:var(--primary)"></i> Select Class & Subject</span></div>
    <div class="card-body">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <div class="form-group" style="flex:1;min-width:180px">
                <label>Class *</label>
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">— Choose class —</option>
                    <?php foreach ($assignedClasses as $cId => $cName): ?>
                    <option value="<?= $cId ?>" <?= $selClass==$cId?'selected':'' ?>><?= h($cName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selClass && isset($assignedSubjects[$selClass])): ?>
            <div class="form-group" style="flex:1;min-width:180px">
                <label>Subject *</label>
                <select name="subject_id" onchange="this.form.submit()">
                    <option value="">— Choose subject —</option>
                    <?php foreach ($assignedSubjects[$selClass] as $sId => $sName): ?>
                    <option value="<?= $sId ?>" <?= $selSubject==$sId?'selected':'' ?>><?= h($sName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($selClass): ?><input type="hidden" name="class_id" value="<?= $selClass ?>"><?php endif; ?>
            <div class="form-group" style="flex:1;min-width:140px">
                <label>Term</label>
                <select name="term" onchange="this.form.submit()">
                    <?php foreach (['Term 1','Term 2','Term 3','Annual'] as $t): ?>
                    <option value="<?= $t ?>" <?= $selTerm===$t?'selected':'' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:140px">
                <label>Exam Type</label>
                <select name="exam_type" onchange="this.form.submit()">
                    <?php foreach (['Mid Term','Final','Quiz','Assignment'] as $e): ?>
                    <option value="<?= $e ?>" <?= $selExam===$e?'selected':'' ?>><?= $e ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Grade Entry Table -->
<?php if ($selClass && $selSubject && !empty($students)): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <span class="card-title">
            ✏️ Enter Grades — <?= h($assignedClasses[$selClass] ?? '') ?> | <?= h($assignedSubjects[$selClass][$selSubject] ?? '') ?> | <?= $selTerm ?> - <?= $selExam ?>
        </span>
    </div>
    <form method="POST">
            <?= csrfField() ?>
        <input type="hidden" name="class_id" value="<?= $selClass ?>">
        <input type="hidden" name="subject_id" value="<?= $selSubject ?>">
        <input type="hidden" name="term" value="<?= h($selTerm) ?>">
        <input type="hidden" name="exam_type" value="<?= h($selExam) ?>">
        <input type="hidden" name="save_grades" value="1">

        <div style="padding:12px 20px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
            <label style="font-weight:600">Max Marks:</label>
            <input type="number" name="max_marks" value="<?= $existingGrades ? array_values($existingGrades)[0]['max_marks'] : 100 ?>"
                   min="1" max="1000" style="width:100px" required>
        </div>

        <div class="table-wrapper">
            <table>
                <thead><tr><th>#</th><th>Roll No</th><th>Student Name</th><th style="width:180px">Marks Obtained</th><th>Current Grade</th></tr></thead>
                <tbody>
                <?php foreach ($students as $i => $st):
                    $existing = $existingGrades[$st['id']] ?? null;
                    $currentMarks = $existing ? $existing['marks'] : '';
                    $maxM = $existing ? $existing['max_marks'] : 100;
                    $pct = $existing ? round(($existing['marks']/$maxM)*100,1) : null;
                    $grade = $pct !== null ? ($pct>=90?'A+':($pct>=80?'A':($pct>=70?'B':($pct>=60?'C':($pct>=50?'D':'F'))))) : '—';
                    $gradeBadge = $pct !== null ? ($pct>=60?'success':($pct>=50?'warning':'danger')) : 'secondary';
                ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td><span class="badge badge-secondary"><?= h($st['roll_no'] ?: '—') ?></span></td>
                    <td style="font-weight:600"><?= h($st['name']) ?></td>
                    <td>
                        <input type="number" name="marks[<?= $st['id'] ?>]"
                               value="<?= $currentMarks ?>"
                               min="0" step="0.5" placeholder="e.g. 75"
                               style="width:120px;text-align:center">
                    </td>
                    <td>
                        <span class="badge badge-<?= $gradeBadge ?>"><?= $grade ?></span>
                        <?php if ($pct !== null): ?>
                        <small class="text-muted">(<?= $pct ?>%)</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="padding:16px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save All Grades</button>
        </div>
    </form>
</div>
<?php elseif ($selClass && $selSubject): ?>
<div class="card"><div class="empty-state"><i class="fas fa-users"></i><h3>No students in this class</h3></div></div>
<?php endif; ?>

<!-- Grades History -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📊 Recent Grades Entered</span>
        <button onclick="exportCSV('gradeHistTable','my_grades')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export</button>
    </div>
    <div class="table-wrapper">
        <table id="gradeHistTable">
            <thead><tr><th>Student</th><th>Class</th><th>Subject</th><th>Term</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
            <tbody>
            <?php if (empty($gradeHistory)): ?>
            <tr><td colspan="7"><div class="empty-state" style="padding:30px"><i class="fas fa-star"></i><h3>No grades entered yet</h3></div></td></tr>
            <?php else: foreach ($gradeHistory as $g):
                $g_pct = $g['pct'];
                $g_grade = $g_pct>=90?'A+':($g_pct>=80?'A':($g_pct>=70?'B':($g_pct>=60?'C':($g_pct>=50?'D':'F'))));
                $g_badge = $g_pct>=60?'success':($g_pct>=50?'warning':'danger');
            ?>
            <tr>
                <td style="font-weight:600"><?= h($g['student_name']) ?></td>
                <td><?= h($g['class_name'].' '.$g['section']) ?></td>
                <td><?= h($g['subject_name']) ?></td>
                <td><span class="badge badge-secondary"><?= h($g['term']) ?></span></td>
                <td><?= $g['marks'] ?>/<?= $g['max_marks'] ?></td>
                <td><?= $g_pct ?>%</td>
                <td><span class="badge badge-<?= $g_badge ?>"><?= $g_grade ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
