<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Teachers';
$breadcrumb = 'Teachers';
$action = $_GET['action'] ?? 'list';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $phone  = preg_replace('/[^0-9+\-\s]/', '', trim($_POST['phone'] ?? ''));
        $qual   = trim($_POST['qualification'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        // Input validation
        if (strlen($name) < 2 || strlen($name) > 100) {
            setFlash('error', 'Name must be between 2 and 100 characters.'); redirect('teachers.php?action=' . ($act === 'add' ? 'add' : 'edit&id=' . (int)($_POST['teacher_id'] ?? 0)));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.'); redirect('teachers.php?action=' . ($act === 'add' ? 'add' : 'edit&id=' . (int)($_POST['teacher_id'] ?? 0)));
        }
        if ($act === 'add' && strlen($password) > 0 && strlen($password) < 6) {
            setFlash('error', 'Password must be at least 6 characters.'); redirect('teachers.php?action=add');
        }

        // Subject/class assignments
        $assignments = $_POST['assignments'] ?? [];

        if ($act === 'add') {
            $check = $conn->prepare("SELECT id FROM users WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                setFlash('error', 'Email already exists.');
                redirect('teachers.php?action=add');
            }
            $check->close();

            $hash = password_hash($password ?: 'teacher123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,'teacher',?)");
            $stmt->bind_param("ssss", $name, $email, $hash, $status);
            $stmt->execute();
            $uid = $conn->insert_id;
            $stmt->close();

            $stmt2 = $conn->prepare("INSERT INTO teachers (user_id,phone,qualification,address) VALUES (?,?,?,?)");
            $stmt2->bind_param("isss", $uid, $phone, $qual, $address);
            $stmt2->execute();
            $tid = $conn->insert_id;
            $stmt2->close();

            logAction($conn, $user['id'], 'admin', 'Added Teacher', '', $name);
            setFlash('success', "Teacher '$name' added.");
        } else {
            $tid = (int)$_POST['teacher_id'];
            $uid = (int)$_POST['user_id'];

            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, status=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $status, $uid);
            $stmt->execute(); 
            $stmt->close();

            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $hash, $uid);
                $stmt->execute(); 
                $stmt->close();
            }

            $stmt2 = $conn->prepare("UPDATE teachers SET phone=?, qualification=?, address=? WHERE id=?");
            $stmt2->bind_param("sssi", $phone, $qual, $address, $tid);
            $stmt2->execute(); 
            $stmt2->close();

            // Remove old assignments
            $da = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id=?");
            $da->bind_param("i", $tid);
            $da->execute(); 
            $da->close();

            logAction($conn, $user['id'], 'admin', 'Edited Teacher', '', $name);
            setFlash('success', "Teacher '$name' updated.");
        }

        // Add assignments
        foreach ($assignments as $asgn) {
            [$subId, $clsId] = explode('_', $asgn);
            $ins = $conn->prepare("INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id) VALUES (?,?,?)");
            $ins->bind_param("iii", $tid, $subId, $clsId);
            $ins->execute(); 
            $ins->close();
        }

        redirect('teachers.php');
    }
}

// ── Toggle Status (Enable/Disable) ──
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    
    // Get user_id from teachers table
    $getUser = $conn->prepare("SELECT user_id FROM teachers WHERE id=?");
    $getUser->bind_param("i", $tid);
    $getUser->execute();
    $result = $getUser->get_result();
    $teacher = $result->fetch_assoc();
    $getUser->close();
    
    if ($teacher) {
        $uid = $teacher['user_id'];
        
        // Get current status
        $statusQuery = $conn->prepare("SELECT status FROM users WHERE id=?");
        $statusQuery->bind_param("i", $uid);
        $statusQuery->execute();
        $statusResult = $statusQuery->get_result();
        $userData = $statusResult->fetch_assoc();
        $statusQuery->close();
        
        $newStatus = ($userData['status'] === 'active') ? 'inactive' : 'active';
        
        // Update status
        $update = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $update->bind_param("si", $newStatus, $uid);
        $update->execute();
        $update->close();
        
        // Get teacher name for log
        $getName = $conn->prepare("SELECT name FROM users WHERE id=?");
        $getName->bind_param("i", $uid);
        $getName->execute();
        $teacherName = $getName->get_result()->fetch_assoc()['name'] ?? 'Teacher';
        $getName->close();
        
        $actionText = $newStatus === 'active' ? 'Enabled' : 'Disabled';
        logAction($conn, $user['id'], 'admin', $actionText . ' Teacher', '', $teacherName);
        setFlash('success', "Teacher has been " . ($newStatus === 'active' ? 'enabled' : 'disabled') . " successfully.");
    }
    redirect('teachers.php');
}

if ($action === 'delete' && isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    $r = $conn->prepare("SELECT u.name,u.id as uid FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.id=?");
    $r->bind_param("i", $tid);
    $r->execute();
    $row = $r->get_result()->fetch_assoc();
    $r->close();
    if ($row) {
        $uid = (int)$row['uid'];
        $d = $conn->prepare("DELETE FROM users WHERE id=?");
        $d->bind_param("i", $uid);
        $d->execute(); 
        $d->close();
        logAction($conn, $user['id'], 'admin', 'Deleted Teacher', $row['name'], '');
        setFlash('success', 'Teacher deleted.');
    }
    redirect('teachers.php');
}

$editData = null;
$editAssignments = [];
if ($action === 'edit' && isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT t.*, u.name, u.email, u.status FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.id=?");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $asgn = $conn->prepare("SELECT CONCAT(subject_id,'_',class_id) as key_val FROM teacher_assignments WHERE teacher_id=?");
    $asgn->bind_param("i", $tid);
    $asgn->execute();
    $editAssignments = array_column($asgn->get_result()->fetch_all(MYSQLI_ASSOC), 'key_val');
    $asgn->close();
}

// All subjects with their classes
$subjectsWithClass = $conn->query("SELECT s.id, s.subject_name, s.subject_code, c.id as class_id, c.class_name, c.section FROM subjects s LEFT JOIN classes c ON s.class_id=c.id ORDER BY c.class_name, s.subject_name")->fetch_all(MYSQLI_ASSOC);

// List with pagination + search + status filter
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$offset = ($page-1)*$perPage;

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $like = "%$search%";
    $where .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, [$like, $like]);
    $types .= 'ss';
}
if ($statusFilter) {
    $where .= " AND u.status=?";
    $params[] = $statusFilter;
    $types .= 's';
}

$countStmt = $conn->prepare("SELECT COUNT(*) FROM teachers t JOIN users u ON t.user_id=u.id $where");
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = ceil($total/$perPage);

$listSql = "SELECT t.id, t.phone, t.qualification, u.name, u.email, u.status,
    (SELECT COUNT(DISTINCT class_id) FROM teacher_assignments WHERE teacher_id=t.id) as class_count,
    (SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=t.id) as subject_count
    FROM teachers t JOIN users u ON t.user_id=u.id $where ORDER BY u.name LIMIT ? OFFSET ?";
$lStmt = $conn->prepare($listSql);
$allParams = array_merge($params, [$perPage, $offset]);
if ($params) {
    $lStmt->bind_param($types . 'ii', ...$allParams);
} else {
    $lStmt->bind_param('ii', $perPage, $offset);
}
$lStmt->execute();
$teachers = $lStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lStmt->close();

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chalkboard-teacher" style="color:var(--success)"></i> <?= $action === 'add' ? 'Add New Teacher' : 'Edit Teacher' ?></span>
        <a href="teachers.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="act" value="<?= $action ?>">
            <?php if ($editData): ?>
            <input type="hidden" name="teacher_id" value="<?= $editData['id'] ?>">
            <input type="hidden" name="user_id" value="<?= $editData['user_id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required value="<?= h($editData['name'] ?? '') ?>" placeholder="Teacher full name">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= h($editData['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password <?= $action==='edit'?'(leave blank to keep)':'' ?></label>
                    <input type="text" name="password" placeholder="<?= $action==='add'?'Set password':'Leave blank to keep' ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= h($editData['phone'] ?? '') ?>" placeholder="0300-0000000">
                </div>
                <div class="form-group">
                    <label>Qualification</label>
                    <input type="text" name="qualification" value="<?= h($editData['qualification'] ?? '') ?>" placeholder="e.g. M.Sc Mathematics">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= h($editData['address'] ?? '') ?>" placeholder="Home address">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= ($editData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
            </div>

            <!-- Subject/Class Assignments -->
            <div style="margin-top:20px;">
                <label style="font-size:14px;font-weight:700;margin-bottom:12px;display:block;">
                    <i class="fas fa-chalkboard"></i> Assign Subject(s) & Class(es)
                </label>
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:16px;max-height:300px;overflow-y:auto;">
                    <?php
                    $grouped = [];
                    foreach ($subjectsWithClass as $sub) {
                        $key = $sub['class_name'] . ' ' . $sub['section'];
                        $grouped[$key][] = $sub;
                    }
                    foreach ($grouped as $clsName => $subs):
                    ?>
                    <div style="margin-bottom:16px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-dim);margin-bottom:8px;">
                            📚 <?= h($clsName) ?>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($subs as $sub):
                                $val = $sub['id'] . '_' . $sub['class_id'];
                                $checked = in_array($val, $editAssignments) ? 'checked' : '';
                            ?>
                            <label style="display:flex;align-items:center;gap:6px;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:6px 12px;cursor:pointer;font-size:13px;font-weight:400;">
                                <input type="checkbox" name="assignments[]" value="<?= $val ?>" <?= $checked ?>>
                                <?= h($sub['subject_name']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $action==='add'?'Add Teacher':'Update Teacher' ?></button>
                <a href="teachers.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">All Teachers <span class="badge badge-success"><?= $total ?></span></span>
        <div class="d-flex gap-10">
            <button onclick="exportCSV('teachersTable','teachers')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export CSV</button>
            <a href="teachers.php?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Teacher</a>
        </div>
    </div>

    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search teachers..." value="<?= h($search) ?>" onkeyup="liveSearch()">
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
                <select name="status" onchange="this.form.submit()" style="min-width:120px;">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Disabled</option>
                </select>
                <?php if ($search): ?>
                <input type="hidden" name="search" value="<?= h($search) ?>">
                <?php endif; ?>
                <?php if ($search || $statusFilter): ?>
                <a href="teachers.php" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="teachersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Teacher</th>
                    <th>Phone</th>
                    <th>Qualification</th>
                    <th>Classes</th>
                    <th>Subjects</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h3>No teachers found</h3>
                            <p><?= $search || $statusFilter ? 'Try changing filters.' : 'Add your first teacher.' ?></p>
                        </div>
                    </td>
                </tr>
                <?php else: $sno=($page-1)*$perPage+1; foreach ($teachers as $t): ?>
                <tr class="<?= $t['status'] === 'inactive' ? 'row-disabled' : '' ?>">
                    <td class="text-muted"><?= $sno++ ?></td>
                    <td>
                        <div style="font-weight:600"><?= h($t['name']) ?></div>
                        <small class="text-muted"><?= h($t['email']) ?></small>
                    </td>
                    <td class="text-muted"><?= h($t['phone'] ?: '—') ?></td>
                    <td class="text-muted"><?= h($t['qualification'] ?: '—') ?></td>
                    <td><span class="badge badge-info"><?= $t['class_count'] ?> class(es)</span></td>
                    <td><span class="badge badge-primary"><?= $t['subject_count'] ?> subject(s)</span></td>
                    <td>
                        <span class="badge badge-<?= $t['status']==='active'?'success':'danger' ?>">
                            <?= $t['status'] === 'active' ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex">
                            <a href="teachers.php?action=toggle_status&id=<?= $t['id'] ?>" 
                               class="btn btn-<?= $t['status'] === 'active' ? 'warning' : 'success' ?> btn-xs"
                               data-confirm="Are you sure you want to <?= $t['status'] === 'active' ? 'disable' : 'enable' ?> '<?= h($t['name']) ?>'?"
                               onclick="return confirm(this.getAttribute('data-confirm'))">
                                <i class="fas fa-<?= $t['status'] === 'active' ? 'ban' : 'check-circle' ?>"></i>
                                <?= $t['status'] === 'active' ? 'Disable' : 'Enable' ?>
                            </a>
                            <a href="teachers.php?action=edit&id=<?= $t['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                            <a href="teachers.php?action=delete&id=<?= $t['id'] ?>" class="btn btn-danger btn-xs"
                               data-confirm="Delete teacher '<?= h($t['name']) ?>'? This cannot be undone."
                               onclick="return confirm(this.getAttribute('data-confirm'))">
                               <i class="fas fa-trash"></i>
                            </a>
                        </div>
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
        $qstr = http_build_query(array_filter(['search'=>$search, 'status'=>$statusFilter]));
        for ($i=1;$i<=$totalPages;$i++): 
        ?>
        <a href="teachers.php?page=<?= $i ?><?= $qstr ? '&'.$qstr : '' ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.row-disabled {
    opacity: 0.65;
    background-color: var(--danger-l);
}
.row-disabled:hover {
    opacity: 0.8;
}
</style>

<script>
function liveSearch() {
    const val = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#teachersTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}

function exportCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    let csv = [];
    
    const headers = [];
    const ths = table.querySelectorAll('thead th');
    ths.forEach(th => {
        headers.push('"' + th.innerText.replace(/"/g, '""') + '"');
    });
    csv.push(headers.join(','));
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const rowData = [];
        const cells = row.querySelectorAll('td');
        cells.forEach(cell => {
            let text = cell.innerText;
            if (cell.querySelector('.btn')) {
                text = text.replace(/EnableDisableEditDelete/g, '').trim();
            }
            rowData.push('"' + text.replace(/"/g, '""') + '"');
        });
        if (rowData.length > 0 && rowData.some(cell => cell !== '""')) {
            csv.push(rowData.join(','));
        }
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.download = filename + '_' + new Date().toISOString().slice(0,19) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>