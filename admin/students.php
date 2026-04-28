<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Students';
$breadcrumb = 'Students';
$action = $_GET['action'] ?? 'list';
$user = getCurrentUser();

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'add' || $act === 'edit') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $classId  = (int)($_POST['class_id'] ?? 0);
        $rollNo   = trim($_POST['roll_no'] ?? '');
        $phone    = preg_replace('/[^0-9+\-\s]/', '', trim($_POST['phone'] ?? ''));
        $parentName  = trim($_POST['parent_name'] ?? '');
        $parentPhone = preg_replace('/[^0-9+\-\s]/', '', trim($_POST['parent_phone'] ?? ''));
        $address  = trim($_POST['address'] ?? '');
        $status   = $_POST['status'] ?? 'active';

        // Input validation
        if (strlen($name) < 2 || strlen($name) > 100) {
            setFlash('error', 'Name must be between 2 and 100 characters.'); redirect('students.php?action=' . ($act === 'add' ? 'add' : 'edit'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.'); redirect('students.php?action=' . ($act === 'add' ? 'add' : 'edit'));
        }
        if ($act === 'add' && strlen($password) > 0 && strlen($password) < 6) {
            setFlash('error', 'Password must be at least 6 characters.'); redirect('students.php?action=add');
        }

        if ($act === 'add') {
            // Check email unique
            $check = $conn->prepare("SELECT id FROM users WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                setFlash('error', 'Email already exists.');
                redirect('students.php?action=add');
            }
            $check->close();

            $hash = password_hash($password ?: 'student123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,status) VALUES (?,?,?,'student',?)");
            $stmt->bind_param("ssss", $name, $email, $hash, $status);
            $stmt->execute();
            $newUserId = $conn->insert_id;
            $stmt->close();

            $stmt2 = $conn->prepare("INSERT INTO students (user_id,class_id,roll_no,phone,parent_name,parent_phone,address) VALUES (?,?,?,?,?,?,?)");
            $stmt2->bind_param("iisssss", $newUserId, $classId, $rollNo, $phone, $parentName, $parentPhone, $address);
            $stmt2->execute();
            $stmt2->close();

            logAction($conn, $user['id'], 'admin', 'Added Student', '', $name);
            setFlash('success', "Student '$name' added successfully.");
        } else {
            $sid = (int)$_POST['student_id'];
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

            $stmt2 = $conn->prepare("UPDATE students SET class_id=?,roll_no=?,phone=?,parent_name=?,parent_phone=?,address=? WHERE id=?");
            $stmt2->bind_param("isssssi", $classId, $rollNo, $phone, $parentName, $parentPhone, $address, $sid);
            $stmt2->execute();
            $stmt2->close();

            logAction($conn, $user['id'], 'admin', 'Edited Student', '', $name);
            setFlash('success', "Student '$name' updated.");
        }
        redirect('students.php');
    }
}

// ── Toggle Status (Enable/Disable) ──
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    
    // Get user_id from students table
    $getUser = $conn->prepare("SELECT user_id FROM students WHERE id=?");
    $getUser->bind_param("i", $sid);
    $getUser->execute();
    $result = $getUser->get_result();
    $student = $result->fetch_assoc();
    $getUser->close();
    
    if ($student) {
        $uid = $student['user_id'];
        
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
        
        $actionText = $newStatus === 'active' ? 'Enabled' : 'Disabled';
        logAction($conn, $user['id'], 'admin', $actionText . ' Student', '', 'Student ID: ' . $sid);
        setFlash('success', "Student has been " . ($newStatus === 'active' ? 'enabled' : 'disabled') . " successfully.");
    }
    redirect('students.php');
}

// ── Delete ──
if ($action === 'delete' && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $r = $conn->prepare("SELECT u.name, u.id as uid FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
    $r->bind_param("i", $sid);
    $r->execute();
    $row = $r->get_result()->fetch_assoc();
    $r->close();
    
    if ($row) {
        // Delete from students table first (due to foreign key constraint)
        $deleteStudent = $conn->prepare("DELETE FROM students WHERE id=?");
        $deleteStudent->bind_param("i", $sid);
        $deleteStudent->execute();
        $deleteStudent->close();
        
        // Then delete from users table
        $uid = (int)$row['uid'];
        $d = $conn->prepare("DELETE FROM users WHERE id=?");
        $d->bind_param("i", $uid);
        $d->execute();
        $d->close();
        
        logAction($conn, $user['id'], 'admin', 'Deleted Student', $row['name'], '');
        setFlash('success', "Student deleted.");
    }
    redirect('students.php');
}

// ── Edit load ──
$editData = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT s.*, u.name, u.email, u.status FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── Classes for dropdowns ──
$classes = $conn->query("SELECT * FROM classes ORDER BY class_name, section")->fetch_all(MYSQLI_ASSOC);

// ── List with pagination + search ──
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$search = trim($_GET['search'] ?? '');
$classFilter = (int)($_GET['class_id'] ?? 0);
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = '';
if ($search) {
    $like = "%$search%";
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR s.roll_no LIKE ?)";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($classFilter) {
    $where .= " AND s.class_id=?";
    $params[] = $classFilter;
    $types .= 'i';
}
if ($statusFilter) {
    $where .= " AND u.status=?";
    $params[] = $statusFilter;
    $types .= 's';
}

$baseSql = "FROM students s JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id $where";

$countStmt = $conn->prepare("SELECT COUNT(*) $baseSql");
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_row()[0];
$countStmt->close();
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$listSql = "SELECT s.id, s.roll_no, s.phone, s.parent_name, u.name, u.email, u.id as uid, u.status, c.class_name, c.section $baseSql ORDER BY u.name LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$allParams = array_merge($params, [$perPage, $offset]);
if ($params) {
    $listStmt->bind_param($types . 'ii', ...$allParams);
} else {
    $listStmt->bind_param('ii', $perPage, $offset);
}
$listStmt->execute();
$students = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ADD / EDIT FORM -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-<?= $action === 'add' ? 'user-plus' : 'edit' ?>" style="color:var(--primary)"></i>
            <?= $action === 'add' ? 'Add New Student' : 'Edit Student' ?>
        </span>
        <a href="students.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
    <div class="card-body">
        <form method="POST" id="studentForm">
            <?= csrfField() ?>
            <input type="hidden" name="act" value="<?= $action ?>">
            <?php if ($editData): ?>
            <input type="hidden" name="student_id" value="<?= $editData['id'] ?>">
            <input type="hidden" name="user_id" value="<?= $editData['user_id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required value="<?= h($editData['name'] ?? '') ?>" placeholder="Student full name">
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required value="<?= h($editData['email'] ?? '') ?>" placeholder="student@email.com">
                </div>
                <div class="form-group">
                    <label>Password <?= $action === 'edit' ? '(leave blank to keep)' : '*' ?></label>
                    <input type="text" name="password" <?= $action === 'add' ? 'required' : '' ?> placeholder="<?= $action === 'add' ? 'Min 6 characters' : 'Leave blank to keep current' ?>">
                </div>
                <div class="form-group">
                    <label>Class *</label>
                    <select name="class_id" required>
                        <option value="">— Select Class —</option>
                        <?php foreach ($classes as $cls): ?>
                        <option value="<?= $cls['id'] ?>" <?= ($editData['class_id'] ?? '') == $cls['id'] ? 'selected' : '' ?>>
                            <?= h($cls['class_name']) ?> - Section <?= h($cls['section']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Roll Number</label>
                    <input type="text" name="roll_no" value="<?= h($editData['roll_no'] ?? '') ?>" placeholder="e.g. 9A-001">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= h($editData['phone'] ?? '') ?>" placeholder="0300-0000000">
                </div>
                <div class="form-group">
                    <label>Parent Name</label>
                    <input type="text" name="parent_name" value="<?= h($editData['parent_name'] ?? '') ?>" placeholder="Father/Guardian name">
                </div>
                <div class="form-group">
                    <label>Parent Phone</label>
                    <input type="text" name="parent_phone" value="<?= h($editData['parent_phone'] ?? '') ?>" placeholder="0300-0000000">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= ($editData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editData['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <textarea name="address" placeholder="Home address"><?= h($editData['address'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $action === 'add' ? 'Add Student' : 'Update Student' ?></button>
                <a href="students.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- STUDENTS LIST -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Students <span class="badge badge-primary"><?= $total ?></span></span>
        <div class="d-flex gap-10">
            <button onclick="exportCSV('studentsTable','students')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export CSV</button>
            <a href="students.php?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Student</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name, email, roll no..." 
                   value="<?= h($search) ?>" onkeyup="liveSearch()">
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
                <input type="hidden" name="page" value="1">
                <select name="class_id" onchange="this.form.submit()" style="min-width:160px;">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $cls): ?>
                    <option value="<?= $cls['id'] ?>" <?= $classFilter == $cls['id'] ? 'selected' : '' ?>>
                        <?= h($cls['class_name']) ?> <?= h($cls['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" onchange="this.form.submit()" style="min-width:120px;">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Disabled</option>
                </select>
                <?php if ($search): ?>
                <input type="hidden" name="search" value="<?= h($search) ?>">
                <?php endif; ?>
                <?php if ($classFilter || $search || $statusFilter): ?>
                <a href="students.php" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="studentsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Roll No</th>
                    <th>Phone</th>
                    <th>Parent</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr><td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No students found</h3>
                        <p><?= $search || $classFilter || $statusFilter ? 'Try changing filters.' : 'Add your first student.' ?></p>
                    </div>
                </tr>
                <?php else: $sno = ($page-1)*$perPage+1; foreach ($students as $s): ?>
                <tr class="<?= $s['status'] === 'inactive' ? 'row-disabled' : '' ?>">
                    <td class="text-muted"><?= $sno++ ?></td>
                    <td>
                        <div style="font-weight:600"><?= h($s['name']) ?></div>
                        <small class="text-muted"><?= h($s['email']) ?></small>
                    </td>
                    <td><?= $s['class_name'] ? h($s['class_name']) . ' ' . h($s['section']) : '<span class="text-muted">—</span>' ?></td>
                    <td><span class="badge badge-info"><?= h($s['roll_no'] ?: '—') ?></span></td>
                    <td class="text-muted"><?= h($s['phone'] ?: '—') ?></td>
                    <td>
                        <div style="font-size:13px"><?= h($s['parent_name'] ?: '—') ?></div>
                    </td>
                    <td>
                        <span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= $s['status'] === 'active' ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex">
                            <a href="students.php?action=toggle_status&id=<?= $s['id'] ?>" 
                               class="btn btn-<?= $s['status'] === 'active' ? 'warning' : 'success' ?> btn-xs"
                               data-confirm="Are you sure you want to <?= $s['status'] === 'active' ? 'disable' : 'enable' ?> '<?= h($s['name']) ?>'?"
                               onclick="return confirm(this.getAttribute('data-confirm'))">
                                <i class="fas fa-<?= $s['status'] === 'active' ? 'ban' : 'check-circle' ?>"></i>
                                <?= $s['status'] === 'active' ? 'Disable' : 'Enable' ?>
                            </a>
                            <a href="students.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                            <a href="students.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-danger btn-xs"
                               data-confirm="Delete student '<?= h($s['name']) ?>'? This cannot be undone."
                               onclick="return confirm(this.getAttribute('data-confirm'))">
                               <i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info">Showing <?= min(($page-1)*$perPage+1, $total) ?>–<?= min($page*$perPage, $total) ?> of <?= $total ?></span>
        <?php
        $qstr = http_build_query(array_filter(['search'=>$search,'class_id'=>$classFilter,'status'=>$statusFilter]));
        for ($i = 1; $i <= $totalPages; $i++):
            $active = $i == $page ? 'active' : '';
        ?>
        <a href="students.php?page=<?= $i ?>&<?= $qstr ?>" class="page-btn <?= $active ?>"><?= $i ?></a>
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
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    rows.forEach(tr => {
        if (tr.cells && tr.cells.length > 0) {
            const text = tr.textContent.toLowerCase();
            tr.style.display = text.includes(val) ? '' : 'none';
        }
    });
}

// Export CSV function
function exportCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    let csv = [];
    
    // Get headers
    const headers = [];
    const ths = table.querySelectorAll('thead th');
    ths.forEach(th => {
        headers.push('"' + th.innerText.replace(/"/g, '""') + '"');
    });
    csv.push(headers.join(','));
    
    // Get data rows
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const rowData = [];
        const cells = row.querySelectorAll('td');
        cells.forEach(cell => {
            let text = cell.innerText;
            // Remove action buttons text but keep numbers
            if (cell.querySelector('.btn')) {
                text = text.replace(/EditDelete/g, '').trim();
            }
            rowData.push('"' + text.replace(/"/g, '""') + '"');
        });
        if (rowData.length > 0 && rowData.some(cell => cell !== '""')) {
            csv.push(rowData.join(','));
        }
    });
    
    // Download
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