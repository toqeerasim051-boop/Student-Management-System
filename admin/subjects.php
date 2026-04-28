<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');
$pageTitle = 'Subjects';
$breadcrumb = 'Subjects';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $name = trim($_POST['subject_name']);
        $code = trim($_POST['subject_code']);
        $classId = (int)$_POST['class_id'];
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name,subject_code,class_id) VALUES (?,?,?)");
        $stmt->bind_param("ssi", $name, $code, $classId);
        $stmt->execute(); $stmt->close();
        setFlash('success', "Subject '$name' added.");
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['subject_name']);
        $code = trim($_POST['subject_code']);
        $classId = (int)$_POST['class_id'];
        $stmt = $conn->prepare("UPDATE subjects SET subject_name=?,subject_code=?,class_id=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $code, $classId, $id);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Subject updated.');
    }
    redirect('subjects.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $d = $conn->prepare("DELETE FROM subjects WHERE id=?");
    $d->bind_param("i", $id);
    $d->execute(); $d->close();
    setFlash('success', 'Subject deleted.');
    redirect('subjects.php');
}

$classes = $conn->query("SELECT * FROM classes ORDER BY class_name,section")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT s.*,c.class_name,c.section FROM subjects s LEFT JOIN classes c ON s.class_id=c.id ORDER BY c.class_name,s.subject_name")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<div style="display:grid;grid-template-columns:350px 1fr;gap:16px;align-items:start;">
<div class="card">
    <div class="card-header"><span class="card-title">Add New Subject</span></div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="act" value="add">
            <div class="form-group">
                <label>Subject Name *</label>
                <input type="text" name="subject_name" required placeholder="e.g. Mathematics">
            </div>
            <div class="form-group" style="margin-top:12px">
                <label>Subject Code</label>
                <input type="text" name="subject_code" placeholder="e.g. MATH-9A">
            </div>
            <div class="form-group" style="margin-top:12px">
                <label>Assign to Class</label>
                <select name="class_id">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= h($c['class_name']) ?> - <?= h($c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Add Subject</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Subjects <span class="badge badge-purple"><?= count($subjects) ?></span></span>
        <button onclick="exportCSV('subjectTable','subjects')" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Export</button>
    </div>
    <div class="table-wrapper">
        <table id="subjectTable">
            <thead><tr><th>#</th><th>Subject Name</th><th>Code</th><th>Class</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($subjects)): ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fas fa-book"></i><h3>No subjects added</h3></div></td></tr>
            <?php else: $n=1; foreach ($subjects as $s): ?>
            <tr>
                <td><?= $n++ ?></td>
                <td style="font-weight:600"><?= h($s['subject_name']) ?></td>
                <td><span class="badge badge-secondary"><?= h($s['subject_code'] ?: '—') ?></span></td>
                <td><?= $s['class_name'] ? h($s['class_name']).' '.h($s['section']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <div class="d-flex">
                        <button onclick="openSubjectEdit(<?= $s['id'] ?>,'<?= h(addslashes($s['subject_name'])) ?>','<?= h(addslashes($s['subject_code'])) ?>',<?= $s['class_id'] ?? 0 ?>)" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></button>
                        <a href="subjects.php?delete=<?= $s['id'] ?>" class="btn btn-danger btn-xs" data-confirm="Delete this subject?"><i class="fas fa-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="modal-overlay" id="editSubModal">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span class="modal-title">Edit Subject</span>
            <button class="modal-close" onclick="closeModal('editSubModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="act" value="edit">
            <input type="hidden" name="id" id="esId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="subject_name" id="esName" required>
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" id="esCode">
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label>Class</label>
                    <select name="class_id" id="esClass">
                        <option value="">— None —</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= h($c['class_name']) ?> - <?= h($c['section']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editSubModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSubjectEdit(id, name, code, classId) {
    document.getElementById('esId').value = id;
    document.getElementById('esName').value = name;
    document.getElementById('esCode').value = code;
    document.getElementById('esClass').value = classId || '';
    openModal('editSubModal');
}
</script>

<?php require_once '../includes/footer.php'; ?>
