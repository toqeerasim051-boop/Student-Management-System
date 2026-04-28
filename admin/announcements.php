<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');
$pageTitle = 'Announcements';
$breadcrumb = 'Announcements';
$action = $_GET['action'] ?? 'list';
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['act'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target  = $_POST['target_role'] ?? 'all';

    // Input validation
    if (strlen($title) < 3 || strlen($title) > 200) {
        setFlash('error', 'Title must be between 3 and 200 characters.'); redirect('announcements.php');
    }
    if (strlen($content) < 5) {
        setFlash('error', 'Content is too short.'); redirect('announcements.php');
    }
    if (!in_array($target, ['all', 'student', 'teacher'])) { $target = 'all'; }

    if ($act === 'add') {
        $stmt = $conn->prepare("INSERT INTO announcements (title,content,target_role,created_by) VALUES (?,?,?,?)");
        $stmt->bind_param("sssi", $title, $content, $target, $user['id']);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Announcement posted.');
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE announcements SET title=?,content=?,target_role=? WHERE id=?");
        $stmt->bind_param("sssi", $title, $content, $target, $id);
        $stmt->execute(); $stmt->close();
        setFlash('success', 'Announcement updated.');
    }
    redirect('announcements.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $d = $conn->prepare("DELETE FROM announcements WHERE id=?");
    $d->bind_param("i", $id); $d->execute(); $d->close();
    setFlash('success', 'Announcement deleted.');
    redirect('announcements.php');
}

$editData = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id=?");
    $stmt->bind_param("i", (int)$_GET['id']);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$announcements = $conn->query("SELECT a.*,u.name as author FROM announcements a LEFT JOIN users u ON a.created_by=u.id ORDER BY a.created_at DESC")->fetch_all(MYSQLI_ASSOC);

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<div style="display:grid;grid-template-columns:380px 1fr;gap:16px;align-items:start;">

<!-- Form -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $editData ? 'Edit Announcement' : 'New Announcement' ?></span>
        <?php if ($editData): ?><a href="announcements.php" class="btn btn-outline btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="act" value="<?= $editData ? 'edit' : 'add' ?>">
            <?php if ($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required value="<?= h($editData['title'] ?? '') ?>" placeholder="Announcement title">
            </div>
            <div class="form-group" style="margin-top:12px">
                <label>Content *</label>
                <textarea name="content" required placeholder="Write announcement details..."><?= h($editData['content'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="margin-top:12px">
                <label>Visible To</label>
                <select name="target_role">
                    <option value="all" <?= ($editData['target_role']??'all')==='all'?'selected':'' ?>>Everyone (All)</option>
                    <option value="student" <?= ($editData['target_role']??'')==='student'?'selected':'' ?>>Students Only</option>
                    <option value="teacher" <?= ($editData['target_role']??'')==='teacher'?'selected':'' ?>>Teachers Only</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-bullhorn"></i> <?= $editData ? 'Update' : 'Post Announcement' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header">
        <span class="card-title">All Announcements <span class="badge badge-warning"><?= count($announcements) ?></span></span>
    </div>
    <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
        <?php if (empty($announcements)): ?>
        <div class="empty-state"><i class="fas fa-bullhorn"></i><h3>No announcements yet</h3></div>
        <?php else: foreach ($announcements as $ann):
            $targetColors = ['all'=>'primary','student'=>'info','teacher'=>'success'];
            $tc = $targetColors[$ann['target_role']] ?? 'secondary';
        ?>
        <div style="background:var(--surface2);border-radius:10px;padding:16px;border-left:3px solid var(--<?= $tc==='primary'?'primary':($tc==='info'?'info':'success') ?>)">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:8px;">
                <div>
                    <div style="font-weight:700;margin-bottom:2px;"><?= h($ann['title']) ?></div>
                    <div style="font-size:12px;color:var(--text-dim)">
                        By <?= h($ann['author'] ?? 'Admin') ?> &bull; <?= date('d M Y, h:i a', strtotime($ann['created_at'])) ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
                    <span class="badge badge-<?= $tc ?>"><?= ucfirst($ann['target_role']) ?></span>
                    <a href="announcements.php?action=edit&id=<?= $ann['id'] ?>" class="btn btn-warning btn-xs"><i class="fas fa-edit"></i></a>
                    <a href="announcements.php?delete=<?= $ann['id'] ?>" class="btn btn-danger btn-xs" data-confirm="Delete this announcement?"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <p style="font-size:13px;color:var(--text-muted);line-height:1.6"><?= nl2br(h($ann['content'])) ?></p>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>
</div>

<?php require_once '../includes/footer.php'; ?>
