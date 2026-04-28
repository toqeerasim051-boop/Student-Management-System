<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');
$pageTitle = 'Activity Logs';
$breadcrumb = 'Logs';

$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20;
$search = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? '';
$offset = ($page-1)*$perPage;

$where = "WHERE 1=1";
$params = []; $types = '';
if ($search) {
    $like = "%$search%";
    $where .= " AND (l.action LIKE ? OR u.name LIKE ?)";
    $params = [$like,$like]; $types = 'ss';
}
if ($filterRole) {
    $where .= " AND l.user_role=?";
    $params[] = $filterRole; $types .= 's';
}

$cStmt = $conn->prepare("SELECT COUNT(*) FROM logs l LEFT JOIN users u ON l.user_id=u.id $where");
if ($params) $cStmt->bind_param($types,...$params);
$cStmt->execute();
$total = $cStmt->get_result()->fetch_row()[0];
$cStmt->close();
$totalPages = ceil($total/$perPage);

$stmt = $conn->prepare("SELECT l.*,u.name FROM logs l LEFT JOIN users u ON l.user_id=u.id $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param($types.'ii',...array_merge($params,[$perPage,$offset]));
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Activity Logs <span class="badge badge-secondary"><?= $total ?></span></span>
        <button onclick="exportCSV('logsTable','activity_logs')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export</button>
    </div>

    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchIn" placeholder="Search by action or user..." value="<?= h($search) ?>" onkeyup="liveSearch()">
        </div>
        <form method="GET" style="display:flex;gap:8px;">
            <select name="role" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="admin" <?= $filterRole==='admin'?'selected':'' ?>>Admin</option>
                <option value="teacher" <?= $filterRole==='teacher'?'selected':'' ?>>Teacher</option>
                <option value="student" <?= $filterRole==='student'?'selected':'' ?>>Student</option>
            </select>
            <?php if ($filterRole||$search): ?><a href="logs.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
            <?php if ($search): ?><input type="hidden" name="search" value="<?= h($search) ?>"><?php endif; ?>
        </form>
    </div>

    <div class="table-wrapper">
        <table id="logsTable">
            <thead><tr><th>#</th><th>User</th><th>Role</th><th>Action</th><th>Old Value</th><th>New Value</th><th>Time</th></tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="fas fa-history"></i><h3>No logs found</h3></div></td></tr>
            <?php else: $n=($page-1)*$perPage+1; foreach ($logs as $l): ?>
            <tr>
                <td class="text-muted"><?= $n++ ?></td>
                <td style="font-weight:600"><?= h($l['name'] ?? 'System') ?></td>
                <td><span class="badge badge-<?= $l['user_role']==='admin'?'danger':($l['user_role']==='teacher'?'success':'info') ?>"><?= ucfirst(h($l['user_role'])) ?></span></td>
                <td><?= h($l['action']) ?></td>
                <td class="text-muted" style="font-size:12px"><?= h($l['old_value'] ?: '—') ?></td>
                <td class="text-muted" style="font-size:12px"><?= h($l['new_value'] ?: '—') ?></td>
                <td class="text-muted" style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">Showing <?= min(($page-1)*$perPage+1,$total) ?>–<?= min($page*$perPage,$total) ?> of <?= $total ?></span>
        <?php
        $qp = http_build_query(array_filter(['search'=>$search,'role'=>$filterRole]));
        for ($i=1;$i<=$totalPages;$i++): ?>
        <a href="logs.php?page=<?= $i ?>&<?= $qp ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function liveSearch() {
    const val = document.getElementById('searchIn').value.toLowerCase();
    document.querySelectorAll('#logsTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
