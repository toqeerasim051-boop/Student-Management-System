<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');
$pageTitle = 'Grades';
$breadcrumb = 'Grades';

$classes  = $conn->query("SELECT * FROM classes ORDER BY class_name,section")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT s.*,c.class_name,c.section FROM subjects s LEFT JOIN classes c ON s.class_id=c.id ORDER BY c.class_name")->fetch_all(MYSQLI_ASSOC);

$filterClass   = (int)($_GET['class_id'] ?? 0);
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterTerm    = $_GET['term'] ?? '';
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;

$where = "WHERE 1=1";
$params = []; $types = '';
if ($filterClass) { $where .= " AND st.class_id=?"; $params[]=$filterClass; $types.='i'; }
if ($filterSubject) { $where .= " AND g.subject_id=?"; $params[]=$filterSubject; $types.='i'; }
if ($filterTerm) { $where .= " AND g.term=?"; $params[]=$filterTerm; $types.='s'; }

$cStmt = $conn->prepare("SELECT COUNT(*) FROM grades g JOIN students st ON g.student_id=st.id $where");
if ($params) $cStmt->bind_param($types,...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total/$perPage);
$offset = ($page-1)*$perPage;

$sql = "SELECT g.*,u.name as student_name,sub.subject_name,c.class_name,c.section,
        ROUND((g.marks/g.max_marks)*100,1) as percentage
        FROM grades g
        JOIN students st ON g.student_id=st.id
        JOIN users u ON st.user_id=u.id
        JOIN subjects sub ON g.subject_id=sub.id
        LEFT JOIN classes c ON st.class_id=c.id
        $where ORDER BY u.name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types.'ii',...array_merge($params,[$perPage,$offset]));
$stmt->execute();
$grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$terms = $conn->query("SELECT DISTINCT term FROM grades ORDER BY term")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Grades <span class="badge badge-primary"><?= $total ?></span></span>
        <button onclick="exportCSV('gradesTable','grades')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export CSV</button>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;width:100%">
            <select name="class_id" onchange="this.form.submit()" style="min-width:140px;">
                <option value="">All Classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= h($c['class_name']) ?> <?= h($c['section']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subject_id" onchange="this.form.submit()" style="min-width:160px;">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSubject==$s['id']?'selected':'' ?>><?= h($s['subject_name']) ?> (<?= h($s['class_name'].' '.$s['section']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <select name="term" onchange="this.form.submit()">
                <option value="">All Terms</option>
                <?php foreach ($terms as $t): ?>
                <option value="<?= h($t['term']) ?>" <?= $filterTerm==$t['term']?'selected':'' ?>><?= h($t['term']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterClass||$filterSubject||$filterTerm): ?>
            <a href="grades.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper">
        <table id="gradesTable">
            <thead><tr><th>#</th><th>Student</th><th>Class</th><th>Subject</th><th>Term</th><th>Marks</th><th>Percentage</th><th>Grade</th></tr></thead>
            <tbody>
            <?php if (empty($grades)): ?>
            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-star"></i><h3>No grades found</h3><p>Teachers will enter grades after exams.</p></div></td></tr>
            <?php else: $n=($page-1)*$perPage+1; foreach ($grades as $g):
                $pct = $g['percentage'];
                $grade = $pct>=90?'A+':($pct>=80?'A':($pct>=70?'B':($pct>=60?'C':($pct>=50?'D':'F'))));
                $gradeBadge = $pct>=60?'success':($pct>=50?'warning':'danger');
            ?>
            <tr>
                <td class="text-muted"><?= $n++ ?></td>
                <td style="font-weight:600"><?= h($g['student_name']) ?></td>
                <td><?= h($g['class_name'].' '.$g['section']) ?></td>
                <td><?= h($g['subject_name']) ?></td>
                <td><span class="badge badge-secondary"><?= h($g['term']) ?></span></td>
                <td><?= $g['marks'] ?>/<?= $g['max_marks'] ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress-bar" style="width:80px">
                            <div class="progress-fill <?= $pct>=60?'good':($pct>=50?'warn':'bad') ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <?= $pct ?>%
                    </div>
                </td>
                <td><span class="badge badge-<?= $gradeBadge ?>"><?= $grade ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">Showing <?= min(($page-1)*$perPage+1,$total) ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?></span>
        <?php
        $qp = http_build_query(array_filter(['class_id'=>$filterClass,'subject_id'=>$filterSubject,'term'=>$filterTerm]));
        for ($i=1;$i<=$totalPages;$i++): ?>
        <a href="grades.php?page=<?= $i ?>&<?= $qp ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
