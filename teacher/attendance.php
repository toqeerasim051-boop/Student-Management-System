<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('teacher');
$pageTitle = 'Mark Attendance';
$breadcrumb = 'Attendance';

$user = getCurrentUser();
$teacherId = $user['entity_id'];

// ── Verify teacher always only sees their own assignments ──
function getTeacherAssignments($conn, $teacherId) {
    $stmt = $conn->prepare(
        "SELECT ta.subject_id, ta.class_id, s.subject_name, c.class_name, c.section
         FROM teacher_assignments ta
         JOIN subjects s ON ta.subject_id=s.id
         JOIN classes c ON ta.class_id=c.id
         WHERE ta.teacher_id=? ORDER BY c.class_name, s.subject_name"
    );
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function teacherOwnsAssignment($conn, $teacherId, $classId, $subjectId) {
    $stmt = $conn->prepare("SELECT id FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=?");
    $stmt->bind_param("iii", $teacherId, $classId, $subjectId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ok;
}

$myAssignments = getTeacherAssignments($conn, $teacherId);

// Group for dropdown
$assignedClasses = [];
$assignedSubjects = [];
foreach ($myAssignments as $a) {
    $cKey = $a['class_id'];
    $assignedClasses[$cKey] = $a['class_name'] . ' - Section ' . $a['section'];
    $assignedSubjects[$cKey][$a['subject_id']] = $a['subject_name'];
}

// Selected filters
$selClass   = (int)($_GET['class_id'] ?? 0);
$selSubject = (int)($_GET['subject_id'] ?? 0);
$selDate    = $_GET['date'] ?? date('Y-m-d');

// Security: ensure teacher owns this class+subject
if ($selClass && $selSubject && !teacherOwnsAssignment($conn, $teacherId, $selClass, $selSubject)) {
    setFlash('error', '⛔ You are not assigned to this class/subject.');
    $selClass = $selSubject = 0;
}

// ── Save/Update Attendance ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    verifyCsrf();
    $postClass   = (int)$_POST['class_id'];
    $postSubject = (int)$_POST['subject_id'];
    $postDate    = $_POST['date'];

    // Strict check
    if (!teacherOwnsAssignment($conn, $teacherId, $postClass, $postSubject)) {
        setFlash('error', '⛔ Access denied. You cannot mark attendance for this class.');
        redirect('attendance.php');
    }

    $attendanceData = $_POST['attendance'] ?? [];
    $saved = 0;
    $updated = 0;
    
    foreach ($attendanceData as $studentId => $status) {
        $studentId = (int)$studentId;
        $status    = in_array($status, ['present','absent','late']) ? $status : 'present';

        // Verify student belongs to this class
        $chk = $conn->prepare("SELECT id FROM students WHERE id=? AND class_id=?");
        $chk->bind_param("ii", $studentId, $postClass);
        $chk->execute();
        $result = $chk->get_result();
        if ($result->num_rows === 0) { 
            $chk->close(); 
            continue; 
        }
        $chk->close();

        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $stmt = $conn->prepare(
            "INSERT INTO attendance (student_id, subject_id, class_id, date, status, marked_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)"
        );
        $stmt->bind_param("iiissi", $studentId, $postSubject, $postClass, $postDate, $status, $user['id']);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows == 1) {
                $saved++;
            } else {
                $updated++;
            }
        }
        $stmt->close();
    }

    $message = [];
    if ($saved > 0) $message[] = "$saved new record(s) saved";
    if ($updated > 0) $message[] = "$updated record(s) updated";
    
    if ($saved > 0 || $updated > 0) {
        logAction($conn, $user['id'], 'teacher', "Marked/Updated attendance for class $postClass subject $postSubject on $postDate", '', "$saved new, $updated updated");
        setFlash('success', "✅ " . implode(', ', $message));
    } else {
        setFlash('warning', "⚠️ No changes were made.");
    }
    
    redirect("attendance.php?class_id=$postClass&subject_id=$postSubject&date=$postDate");
}

// ── Load Students for selected class (ONLY if teacher is assigned) ──
$students = [];
$existingAttendance = [];

if ($selClass && $selSubject && teacherOwnsAssignment($conn, $teacherId, $selClass, $selSubject)) {
    $stmt = $conn->prepare(
        "SELECT s.id, s.roll_no, u.name
         FROM students s 
         JOIN users u ON s.user_id=u.id
         WHERE s.class_id=? AND u.status='active'
         ORDER BY s.roll_no, u.name"
    );
    $stmt->bind_param("i", $selClass);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Existing attendance for this date
    $aStmt = $conn->prepare(
        "SELECT student_id, status FROM attendance
         WHERE subject_id=? AND class_id=? AND date=?"
    );
    $aStmt->bind_param("iis", $selSubject, $selClass, $selDate);
    $aStmt->execute();
    $existingResult = $aStmt->get_result();
    while ($row = $existingResult->fetch_assoc()) {
        $existingAttendance[$row['student_id']] = $row['status'];
    }
    $aStmt->close();
}

// Attendance history for this teacher
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page-1)*$perPage;

$histStmt = $conn->prepare(
    "SELECT COUNT(*) FROM (
        SELECT DISTINCT CONCAT(a.date, a.student_id) 
        FROM attendance a
        JOIN teacher_assignments ta ON ta.subject_id=a.subject_id AND ta.class_id=a.class_id
        WHERE ta.teacher_id=?
    ) as total"
);
$histStmt->bind_param("i", $teacherId);
$histStmt->execute();
$histTotal = $histStmt->get_result()->fetch_row()[0];
$histStmt->close();
$histPages = ceil($histTotal/$perPage);

$histQuery = $conn->prepare(
    "SELECT a.date, a.status, u.name as student_name, s.subject_name, c.class_name, c.section
     FROM attendance a
     JOIN students st ON a.student_id=st.id
     JOIN users u ON st.user_id=u.id
     JOIN subjects s ON a.subject_id=s.id
     JOIN classes c ON a.class_id=c.id
     JOIN teacher_assignments ta ON ta.subject_id=a.subject_id AND ta.class_id=a.class_id
     WHERE ta.teacher_id=?
     ORDER BY a.date DESC, u.name
     LIMIT ? OFFSET ?"
);
$histQuery->bind_param("iii", $teacherId, $perPage, $offset);
$histQuery->execute();
$history = $histQuery->get_result()->fetch_all(MYSQLI_ASSOC);
$histQuery->close();

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Step 1: Select Class + Subject + Date -->
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-filter" style="color:var(--primary)"></i> Step 1: Select Class, Subject & Date</span>
    </div>
    <div class="card-body">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <div class="form-group" style="flex:1;min-width:180px">
                <label>Class *</label>
                <select name="class_id" onchange="this.form.submit()" required>
                    <option value="">— Choose your class —</option>
                    <?php foreach ($assignedClasses as $cId => $cName): ?>
                    <option value="<?= $cId ?>" <?= $selClass==$cId?'selected':'' ?>><?= h($cName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selClass && isset($assignedSubjects[$selClass])): ?>
            <div class="form-group" style="flex:1;min-width:180px">
                <label>Subject *</label>
                <select name="subject_id" onchange="this.form.submit()" required>
                    <option value="">— Choose subject —</option>
                    <?php foreach ($assignedSubjects[$selClass] as $sId => $sName): ?>
                    <option value="<?= $sId ?>" <?= $selSubject==$sId?'selected':'' ?>><?= h($sName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group" style="flex:1;min-width:160px">
                <label>Date *</label>
                <input type="date" name="date" value="<?= h($selDate) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
            </div>
        </form>

        <?php if ($selClass && $selSubject): ?>
        <div class="alert alert-info" style="margin-top:12px;margin-bottom:0">
            <i class="fas fa-info-circle"></i>
            Marking attendance for: <strong><?= h($assignedClasses[$selClass] ?? '') ?></strong> —
            <strong><?= h($assignedSubjects[$selClass][$selSubject] ?? '') ?></strong> —
            <?= date('d M Y', strtotime($selDate)) ?>
            <?php if (!empty($existingAttendance)): ?>
            <span class="badge badge-warning" style="margin-left:8px">
                <i class="fas fa-edit"></i> Editing existing records
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Step 2: Mark Attendance -->
<?php if ($selClass && $selSubject && !empty($students)): ?>
<div class="card" style="margin-bottom:16px">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-users" style="color:var(--success)"></i> 
            Step 2: Mark Attendance (<?= count($students) ?> Students)
        </span>
        <div style="display: flex; gap: 8px;">
            <button type="button" onclick="markAll('present')" class="btn btn-success btn-sm" id="btnAllPresent">
                <i class="fas fa-check-double"></i> All Present
            </button>
            <button type="button" onclick="markAll('absent')" class="btn btn-danger btn-sm" id="btnAllAbsent">
                <i class="fas fa-times-circle"></i> All Absent
            </button>
            <button type="button" onclick="markAll('late')" class="btn btn-warning btn-sm" id="btnAllLate">
                <i class="fas fa-clock"></i> All Late
            </button>
            <button type="button" onclick="resetToSaved()" class="btn btn-secondary btn-sm" id="btnReset">
                <i class="fas fa-undo-alt"></i> Reset
            </button>
        </div>
    </div>

    <form method="POST" id="attendanceForm">
            <?= csrfField() ?>
        <input type="hidden" name="class_id" value="<?= $selClass ?>">
        <input type="hidden" name="subject_id" value="<?= $selSubject ?>">
        <input type="hidden" name="date" value="<?= h($selDate) ?>">
        <input type="hidden" name="save_attendance" value="1">

        <div class="table-wrapper">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <th style="text-align:center">Attendance Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $counter = 1;
                foreach ($students as $st):
                    $currentStatus = $existingAttendance[$st['id']] ?? 'present';
                ?>
                <tr id="row-<?= $st['id'] ?>" data-original-status="<?= $currentStatus ?>">
                    <td class="text-muted"><?= $counter++ ?> </td>
                    <td><span class="badge badge-secondary"><?= h($st['roll_no'] ?: '—') ?></span></td>
                    <td style="font-weight:600"><?= h($st['name']) ?></td>
                    <td style="text-align:center">
                        <input type="hidden" name="attendance[<?= $st['id'] ?>]" id="att_<?= $st['id'] ?>" value="<?= $currentStatus ?>">
                        <div class="btn-group-toggle" style="display: flex; gap: 8px; justify-content: center;">
                            <button type="button" onclick="setStatus(<?= $st['id'] ?>,'present')"
                                class="status-btn status-present <?= $currentStatus === 'present' ? 'active' : '' ?>"
                                data-status="present">
                                <i class="fas fa-check-circle"></i> Present
                            </button>
                            <button type="button" onclick="setStatus(<?= $st['id'] ?>,'absent')"
                                class="status-btn status-absent <?= $currentStatus === 'absent' ? 'active' : '' ?>"
                                data-status="absent">
                                <i class="fas fa-times-circle"></i> Absent
                            </button>
                            <button type="button" onclick="setStatus(<?= $st['id'] ?>,'late')"
                                class="status-btn status-late <?= $currentStatus === 'late' ? 'active' : '' ?>"
                                data-status="late">
                                <i class="fas fa-clock"></i> Late
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="padding:16px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:space-between">
            <div>
                <span class="text-muted" id="changeIndicator" style="display:none;">
                    <i class="fas fa-info-circle"></i> 
                    You have unsaved changes
                </span>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            </div>
        </div>
    </form>
</div>

<?php elseif ($selClass && $selSubject): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-user-graduate"></i>
        <h3>No students in this class</h3>
        <p>Admin needs to assign students to this class.</p>
    </div>
</div>
<?php endif; ?>

<!-- Attendance History -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-history"></i> Attendance History
        </span>
        <button onclick="exportCSV('histTable','attendance_history')" class="btn btn-outline btn-sm">
            <i class="fas fa-file-csv"></i> Export
        </button>
    </div>
    <div class="table-wrapper">
        <table id="histTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($history)): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state" style="padding:30px">
                            <i class="fas fa-calendar"></i>
                            <p>No attendance records found.</p>
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($history as $h): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($h['date'])) ?></td>
                    <td><strong><?= h($h['student_name']) ?></strong></td>
                    <td><?= h($h['class_name'] . ' ' . $h['section']) ?></td>
                    <td><?= h($h['subject_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $h['status'] === 'present' ? 'success' : ($h['status'] === 'absent' ? 'danger' : 'warning') ?>">
                            <?= ucfirst($h['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($histPages > 1): ?>
    <div class="pagination">
        <span class="pagination-info"><?= $histTotal ?> total records</span>
        <?php for ($i = 1; $i <= $histPages; $i++): ?>
        <a href="?page=<?= $i ?>&class_id=<?= $selClass ?>&subject_id=<?= $selSubject ?>&date=<?= urlencode($selDate) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* Modern Attendance Button Styles */
.status-btn {
    padding: 8px 16px;
    border: 2px solid transparent;
    background: var(--surface);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.status-btn i {
    font-size: 14px;
}

.status-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Present button */
.status-present {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}

.status-present.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: #10b981;
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Absent button */
.status-absent {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

.status-absent.active {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-color: #ef4444;
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Late button */
.status-late {
    background: #fffbeb;
    border-color: #fde68a;
    color: #92400e;
}

.status-late.active {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-color: #f59e0b;
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

/* Table row highlight on change */
tr.changed {
    background-color: rgba(59, 130, 246, 0.05);
    transition: background-color 0.3s;
}

/* Alert styles */
.alert-info {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border-left: 4px solid #0284c7;
    padding: 12px 16px;
    border-radius: 8px;
}

.badge-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

/* Button styles */
.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border: none;
    color: white;
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    border: none;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #6b7280;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<script>
// Store original statuses for reset functionality
const originalStatuses = {};

// Initialize original statuses
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="att_"]').forEach(input => {
        const studentId = input.id.replace('att_', '');
        originalStatuses[studentId] = input.value;
    });
});

function setStatus(id, status) {
    // Update hidden input
    const hiddenInput = document.getElementById('att_' + id);
    const oldStatus = hiddenInput.value;
    hiddenInput.value = status;
    
    // Update button styles in this row
    const row = document.getElementById('row-' + id);
    const buttons = row.querySelectorAll('.status-btn');
    
    buttons.forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Find and highlight the selected button
    const targetBtn = row.querySelector(`.status-${status}`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }
    
    // Highlight row if status changed
    if (oldStatus !== status) {
        row.classList.add('changed');
        showChangeIndicator();
    } else {
        // Check if this row has any changes
        const currentValue = hiddenInput.value;
        if (currentValue === originalStatuses[id]) {
            row.classList.remove('changed');
        }
        hideChangeIndicatorIfNoChanges();
    }
}

function markAll(status) {
    const statusText = status === 'present' ? 'PRESENT' : (status === 'absent' ? 'ABSENT' : 'LATE');
    if (confirm(`Are you sure you want to mark ALL students as ${statusText}?`)) {
        document.querySelectorAll('[id^="att_"]').forEach(input => {
            const studentId = input.id.replace('att_', '');
            setStatus(studentId, status);
        });
        
        // Flash feedback
        const btn = document.getElementById(`btnAll${status.charAt(0).toUpperCase() + status.slice(1)}`);
        const originalText = btn.innerHTML;
        btn.innerHTML = `<i class="fas fa-check"></i> All ${statusText}!`;
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 1500);
    }
}

function resetToSaved() {
    if (confirm('Reset all changes to last saved state?')) {
        document.querySelectorAll('[id^="att_"]').forEach(input => {
            const studentId = input.id.replace('att_', '');
            const originalValue = originalStatuses[studentId];
            if (originalValue) {
                setStatus(studentId, originalValue);
            }
        });
        
        // Flash feedback
        const resetBtn = document.getElementById('btnReset');
        const originalText = resetBtn.innerHTML;
        resetBtn.innerHTML = '<i class="fas fa-check"></i> Reset!';
        setTimeout(() => {
            resetBtn.innerHTML = originalText;
        }, 1500);
    }
}

function showChangeIndicator() {
    const indicator = document.getElementById('changeIndicator');
    if (indicator) {
        indicator.style.display = 'block';
        indicator.style.animation = 'fadeIn 0.3s ease';
    }
}

function hideChangeIndicatorIfNoChanges() {
    let hasChanges = false;
    document.querySelectorAll('[id^="att_"]').forEach(input => {
        const studentId = input.id.replace('att_', '');
        if (input.value !== originalStatuses[studentId]) {
            hasChanges = true;
        }
    });
    
    const indicator = document.getElementById('changeIndicator');
    if (indicator && !hasChanges) {
        indicator.style.display = 'none';
    }
}

// Add animation style
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .changed {
        animation: highlight 0.5s ease;
    }
    
    @keyframes highlight {
        0% { background-color: rgba(59, 130, 246, 0.2); }
        100% { background-color: rgba(59, 130, 246, 0.05); }
    }
`;
document.head.appendChild(style);

// Check for unsaved changes before leaving
let formChanged = false;
document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', (e) => {
    let hasChanges = false;
    document.querySelectorAll('[id^="att_"]').forEach(input => {
        const studentId = input.id.replace('att_', '');
        if (input.value !== originalStatuses[studentId]) {
            hasChanges = true;
        }
    });
    
    if (hasChanges) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

document.querySelector('#attendanceForm')?.addEventListener('submit', () => {
    formChanged = false;
});
</script>

<?php require_once '../includes/footer.php'; ?>