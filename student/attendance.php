<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('student');
$pageTitle = 'My Attendance';
$breadcrumb = 'Attendance';

$user = getCurrentUser();
$studentId = $user['entity_id'];

$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterStatus  = $_GET['status'] ?? '';
$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page-1)*$perPage;

// My subjects
$mySubjects = $conn->prepare(
    "SELECT DISTINCT s.id, s.subject_name FROM attendance a
     JOIN subjects s ON a.subject_id=s.id
     WHERE a.student_id=? ORDER BY s.subject_name"
);
$mySubjects->bind_param("i", $studentId);
$mySubjects->execute();
$subjects = $mySubjects->get_result()->fetch_all(MYSQLI_ASSOC);
$mySubjects->close();

// Summary
$sumStmt = $conn->prepare(
    "SELECT status, COUNT(*) as cnt FROM attendance WHERE student_id=? GROUP BY status"
);
$sumStmt->bind_param("i", $studentId);
$sumStmt->execute();
$sumRows = $sumStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sumStmt->close();
$sum = ['present'=>0,'absent'=>0,'late'=>0];
foreach ($sumRows as $r) $sum[$r['status']] = $r['cnt'];
$total_all = array_sum($sum);
$attPct = $total_all > 0 ? round($sum['present']/$total_all*100) : 0;

// Subject-wise summary
$subSumStmt = $conn->prepare(
    "SELECT s.subject_name, a.status, COUNT(*) as cnt
     FROM attendance a JOIN subjects s ON a.subject_id=s.id
     WHERE a.student_id=? GROUP BY a.subject_id, a.status ORDER BY s.subject_name"
);
$subSumStmt->bind_param("i", $studentId);
$subSumStmt->execute();
$subSumRows = $subSumStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$subSumStmt->close();

$subSummary = [];
foreach ($subSumRows as $r) {
    $subSummary[$r['subject_name']][$r['status']] = $r['cnt'];
}

// Filter & paginate records
$where = "WHERE a.student_id=?";
$params = [$studentId]; $types = 'i';
if ($filterSubject) { $where .= " AND a.subject_id=?"; $params[]=$filterSubject; $types.='i'; }
if ($filterStatus)  { $where .= " AND a.status=?"; $params[]=$filterStatus; $types.='s'; }

$cStmt = $conn->prepare("SELECT COUNT(*) FROM attendance a $where");
$cStmt->bind_param($types,...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total/$perPage);

$stmt = $conn->prepare(
    "SELECT a.date, a.status, s.subject_name, c.class_name, c.section
     FROM attendance a JOIN subjects s ON a.subject_id=s.id
     JOIN classes c ON a.class_id=c.id $where
     ORDER BY a.date DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param($types.'ii',...array_merge($params,[$perPage,$offset]));
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Overall Summary -->
<div class="stats-grid" style="margin-bottom:16px">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><div class="stat-label">Present</div><div class="stat-value text-success"><?= $sum['present'] ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info"><div class="stat-label">Absent</div><div class="stat-value text-danger"><?= $sum['absent'] ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        <div class="stat-info"><div class="stat-label">Late</div><div class="stat-value text-warning"><?= $sum['late'] ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $attPct>=75?'green':($attPct>=60?'yellow':'red') ?>"><i class="fas fa-percent"></i></div>
        <div class="stat-info">
            <div class="stat-label">Overall %</div>
            <div class="stat-value <?= $attPct>=75?'text-success':($attPct>=60?'text-warning':'text-danger') ?>"><?= $attPct ?>%</div>
            <div class="stat-sub"><?= $attPct<75?'⚠️ Below 75%':'✅ Good' ?></div>
        </div>
    </div>
</div>

<!-- Subject-wise Summary -->
<?php if (!empty($subSummary)): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header"><span class="card-title">📊 Subject-wise Attendance</span></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Subject</th><th>Present</th><th>Absent</th><th>Late</th><th>Total</th><th>%</th></tr></thead>
            <tbody>
            <?php foreach ($subSummary as $subName => $sums):
                $p = $sums['present'] ?? 0;
                $a = $sums['absent'] ?? 0;
                $l = $sums['late'] ?? 0;
                $tot = $p + $a + $l;
                $pct = $tot > 0 ? round($p/$tot*100) : 0;
            ?>
            <tr>
                <td style="font-weight:600"><?= h($subName) ?></td>
                <td class="text-success"><?= $p ?></td>
                <td class="text-danger"><?= $a ?></td>
                <td class="text-warning"><?= $l ?></td>
                <td><?= $tot ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div class="progress-bar" style="width:80px">
                            <div class="progress-fill <?= $pct>=75?'good':($pct>=60?'warn':'bad') ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <strong class="<?= $pct>=75?'text-success':($pct>=60?'text-warning':'text-danger') ?>"><?= $pct ?>%</strong>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Detailed Records -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📋 Attendance Details</span>
        <button onclick="exportCSV('attTable','my_attendance')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export</button>
    </div>
    <div class="filter-bar">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;width:100%">
            <select name="subject_id" onchange="this.form.submit()" style="min-width:160px">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSubject==$s['id']?'selected':'' ?>><?= h($s['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="present" <?= $filterStatus==='present'?'selected':'' ?>>Present</option>
                <option value="absent" <?= $filterStatus==='absent'?'selected':'' ?>>Absent</option>
                <option value="late" <?= $filterStatus==='late'?'selected':'' ?>>Late</option>
            </select>
            <?php if ($filterSubject||$filterStatus): ?><a href="attendance.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
        </form>
    </div>
    <div class="table-wrapper">
        <table id="attTable">
            <thead><tr><th>#</th><th>Date</th><th>Subject</th><th>Class</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($records)): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="fas fa-calendar"></i><h3>No records found</h3></div></td></tr>
            <?php else: $n=($page-1)*$perPage+1; foreach ($records as $r): ?>
            <tr>
                <td class="text-muted"><?= $n++ ?></td>
                <td><?= date('d M Y (l)', strtotime($r['date'])) ?></td>
                <td style="font-weight:600"><?= h($r['subject_name']) ?></td>
                <td><?= h($r['class_name'].' '.$r['section']) ?></td>
                <td><span class="badge badge-<?= $r['status']==='present'?'success':($r['status']==='absent'?'danger':'warning') ?>"><?= ucfirst($r['status']) ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">Showing <?= min(($page-1)*$perPage+1,$total) ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?></span>
        <?php
        $qp = http_build_query(array_filter(['subject_id'=>$filterSubject,'status'=>$filterStatus]));
        for ($i=1;$i<=$totalPages;$i++): ?>
        <a href="attendance.php?page=<?= $i ?>&<?= $qp ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
