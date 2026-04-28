<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');
$pageTitle = 'Classes';
$breadcrumb = 'Classes';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $name = trim($_POST['class_name'] ?? '');
        $sec  = trim($_POST['section'] ?? '');
        if (strlen($name) < 1 || strlen($name) > 100) { setFlash('error', 'Class name required (max 100 chars).'); redirect('classes.php'); }
        if (strlen($sec) < 1 || strlen($sec) > 10)    { setFlash('error', 'Section required (max 10 chars).'); redirect('classes.php'); }
        $stmt = $conn->prepare("INSERT INTO classes (class_name,section) VALUES (?,?)");
        $stmt->bind_param("ss", $name, $sec);
        $stmt->execute(); $stmt->close();
        logAction($conn, $user['id'], 'admin', 'Added Class', '', "$name $sec");
        setFlash('success', "Class '$name $sec' added.");
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['class_name']);
        $sec  = trim($_POST['section']);
        $stmt = $conn->prepare("UPDATE classes SET class_name=?,section=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $sec, $id);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Class updated.');
    }
    redirect('classes.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $d = $conn->prepare("DELETE FROM classes WHERE id=?");
    $d->bind_param("i", $id);
    $d->execute(); $d->close();
    setFlash('success', 'Class deleted.');
    redirect('classes.php');
}

$classes = $conn->query("SELECT c.*,(SELECT COUNT(*) FROM students WHERE class_id=c.id) as student_count FROM classes c ORDER BY c.class_name,c.section")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<div style="display:grid;grid-template-columns:350px 1fr;gap:16px;align-items:start;">

<!-- Add Form -->
<div class="card">
    <div class="card-header"><span class="card-title">Add New Class</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="act" value="add">
            <div class="form-group">
                <label>Class Name *</label>
                <input type="text" name="class_name" required placeholder="e.g. Class 9">
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label>Section *</label>
                <input type="text" name="section" required placeholder="e.g. A, B, Science">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add Class</button>
            </div>
        </form>
    </div>
</div>

<!-- Classes List -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Classes <span class="badge badge-yellow"><?= count($classes) ?></span></span>
        <button onclick="exportCSV('classTable','classes')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export</button>
    </div>
    <div class="table-wrapper">
        <table id="classTable">
            <thead><tr><th>#</th><th>Class</th><th>Section</th><th>Students</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($classes)): ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fas fa-school"></i><h3>No classes added yet</h3></div></td></tr>
            <?php else: $n=1; foreach ($classes as $c): ?>
            <tr>
                <td><?= $n++ ?></td>
                <td style="font-weight:600"><?= h($c['class_name']) ?></td>
                <td><span class="badge badge-info"><?= h($c['section']) ?></span></td>
                <td><span class="badge badge-primary"><?= $c['student_count'] ?> student(s)</span></td>
                <td>
                    <div class="d-flex">
                        <button onclick="openEditModal(<?= $c['id'] ?>,'<?= h(addslashes($c['class_name'])) ?>','<?= h(addslashes($c['section'])) ?>')" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></button>
                        <?php if ($c['student_count'] > 0): ?>
                        <a href="classes.php?delete=<?= $c['id'] ?>"
                           class="btn btn-danger btn-xs"
                           onclick="return confirm('WARNING: This class has <?= $c['student_count'] ?> enrolled student(s).\nDeleting it will also remove all related data.\n\nAre you sure you want to delete \'<?= h(addslashes($c['class_name'])) ?> <?= h(addslashes($c['section'])) ?>\'?')">
                           <i class="fas fa-trash"></i>
                        </a>
                        <?php else: ?>
                        <a href="classes.php?delete=<?= $c['id'] ?>"
                           class="btn btn-danger btn-xs"
                           onclick="return confirm('Delete class \'<?= h(addslashes($c['class_name'])) ?> <?= h(addslashes($c['section'])) ?>\'?')">
                           <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:360px">
        <div class="modal-header">
            <span class="modal-title">Edit Class</span>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="act" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Class Name</label>
                    <input type="text" name="class_name" id="editName" required>
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label>Section</label>
                    <input type="text" name="section" id="editSection" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, section) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editSection').value = section;
    openModal('editModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>