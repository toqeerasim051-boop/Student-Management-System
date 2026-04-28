<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');
$pageTitle = 'Attendance';
$breadcrumb = 'Attendance';

$classes = $conn->query("SELECT * FROM classes ORDER BY class_name,section")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT s.*,c.class_name,c.section FROM subjects s LEFT JOIN classes c ON s.class_id=c.id ORDER BY c.class_name,s.subject_name")->fetch_all(MYSQLI_ASSOC);

$filterClass   = (int)($_GET['class_id'] ?? 0);
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterDate    = $_GET['date'] ?? date('Y-m-d');
$filterStatus  = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($filterClass) {
    $where .= " AND a.class_id=?";
    $params[] = $filterClass; $types .= 'i';
}
if ($filterSubject) {
    $where .= " AND a.subject_id=?";
    $params[] = $filterSubject; $types .= 'i';
}
if ($filterDate) {
    $where .= " AND a.date=?";
    $params[] = $filterDate; $types .= 's';
}
if ($filterStatus) {
    $where .= " AND a.status=?";
    $params[] = $filterStatus; $types .= 's';
}

$cSql = "SELECT COUNT(*) FROM attendance a $where";
$cStmt = $conn->prepare($cSql);
if ($params) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total/$perPage);
$offset = ($page-1)*$perPage;

$sql = "SELECT a.*,u.name as student_name,s.subject_name,c.class_name,c.section
        FROM attendance a
        JOIN students st ON a.student_id=st.id
        JOIN users u ON st.user_id=u.id
        JOIN subjects s ON a.subject_id=s.id
        JOIN classes c ON a.class_id=c.id
        $where ORDER BY a.date DESC, u.name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types.'ii', ...array_merge($params,[$perPage,$offset]));
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary stats for selected date
$sumStmt = $conn->prepare("SELECT a.status,COUNT(*) as cnt FROM attendance a $where GROUP BY a.status");
if ($params) $sumStmt->bind_param($types, ...$params);
$sumStmt->execute();
$sumRows = $sumStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sumStmt->close();
$sum = ['present'=>0,'absent'=>0,'late'=>0];
foreach ($sumRows as $sr) $sum[$sr['status']] = $sr['cnt'];

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check"></i></div>
        <div class="stat-info"><div class="stat-label">Present</div><div class="stat-value text-success"><?= $sum['present'] ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times"></i></div>
        <div class="stat-info"><div class="stat-label">Absent</div><div class="stat-value text-danger"><?= $sum['absent'] ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        <div class="stat-info"><div class="stat-label">Late</div><div class="stat-value text-warning"><?= $sum['late'] ?></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Attendance Records</span>
        <button onclick="exportCSV('attTable','attendance')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export CSV</button>
    </div>

    <div class="filter-bar">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;width:100%">
            <input type="date" name="date" value="<?= h($filterDate) ?>" onchange="this.form.submit()" style="width:auto;">
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
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="present" <?= $filterStatus==='present'?'selected':'' ?>>Present</option>
                <option value="absent" <?= $filterStatus==='absent'?'selected':'' ?>>Absent</option>
                <option value="late" <?= $filterStatus==='late'?'selected':'' ?>>Late</option>
            </select>
            <?php if ($filterClass||$filterSubject||$filterStatus||$filterDate!=date('Y-m-d')): ?>
            <a href="attendance.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper">
        <table id="attTable">
            <thead><tr><th>#</th><th>Student</th><th>Class</th><th>Subject</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-calendar-check"></i><h3>No attendance records</h3><p>Use the filters to find records or teachers will mark attendance.</p></div></td></tr>
            <?php else: $n=($page-1)*$perPage+1; foreach ($records as $r): ?>
            <tr>
                <td class="text-muted"><?= $n++ ?></td>
                <td style="font-weight:600"><?= h($r['student_name']) ?></td>
                <td><?= h($r['class_name'].' '.$r['section']) ?></td>
                <td><?= h($r['subject_name']) ?></td>
                <td><?= date('d M Y', strtotime($r['date'])) ?></td>
                <td>
                    <span class="badge badge-<?= $r['status']==='present'?'success':($r['status']==='absent'?'danger':'warning') ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">Showing <?= min(($page-1)*$perPage+1,$total) ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?></span>
        <?php
        $qp = http_build_query(array_filter(['class_id'=>$filterClass,'subject_id'=>$filterSubject,'date'=>$filterDate,'status'=>$filterStatus]));
        for ($i=1;$i<=$totalPages;$i++): ?>
        <a href="attendance.php?page=<?= $i ?>&<?= $qp ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>