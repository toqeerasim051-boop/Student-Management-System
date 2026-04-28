<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard';

// Stats with error handling
$stats = [];
$stats['students'] = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0] ?? 0;
$stats['teachers'] = $conn->query("SELECT COUNT(*) FROM teachers")->fetch_row()[0] ?? 0;
$stats['classes'] = $conn->query("SELECT COUNT(*) FROM classes")->fetch_row()[0] ?? 0;
$stats['subjects'] = $conn->query("SELECT COUNT(*) FROM subjects")->fetch_row()[0] ?? 0;

// Today's attendance summary with error handling
$today = date('Y-m-d');
$attResult = $conn->query("SELECT status, COUNT(*) as cnt FROM attendance WHERE date='$today' GROUP BY status");
$att = $attResult ? $attResult->fetch_all(MYSQLI_ASSOC) : [];
$attMap = ['present'=>0, 'absent'=>0, 'late'=>0];
$totalAttendance = 0;
foreach ($att as $a) {
    if (isset($attMap[$a['status']])) {
        $attMap[$a['status']] = $a['cnt'];
        $totalAttendance += $a['cnt'];
    }
}

// Recent activity logs
$logsResult = $conn->query("SELECT l.*, u.name FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 8");
$logs = $logsResult ? $logsResult->fetch_all(MYSQLI_ASSOC) : [];

// Recent students
$recentStudentsResult = $conn->query("SELECT u.name, u.email, c.class_name, s.roll_no, u.created_at FROM students s JOIN users u ON s.user_id=u.id LEFT JOIN classes c ON s.class_id=c.id ORDER BY u.created_at DESC LIMIT 5");
$recentStudents = $recentStudentsResult ? $recentStudentsResult->fetch_all(MYSQLI_ASSOC) : [];

// Announcements
$announcementsResult = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
$announcements = $announcementsResult ? $announcementsResult->fetch_all(MYSQLI_ASSOC) : [];

require_once '../includes/header.php';
?>

<?= showFlash() ?>

<!-- Modern Stats Cards -->
<div class="stats-grid">
    <div class="stat-card modern-stat" data-color="blue">
        <div class="stat-icon">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Students</div>
            <div class="stat-value">
                <span class="counter" data-target="<?= $stats['students'] ?>">0</span>
            </div>
            <div class="stat-sub">Currently Enrolled</div>
        </div>
    </div>
    
    <div class="stat-card modern-stat" data-color="green">
        <div class="stat-icon">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Teachers</div>
            <div class="stat-value">
                <span class="counter" data-target="<?= $stats['teachers'] ?>">0</span>
            </div>
            <div class="stat-sub">Active Staff Members</div>
        </div>
    </div>
    
    <div class="stat-card modern-stat" data-color="yellow">
        <div class="stat-icon">
            <i class="fas fa-school"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Classes</div>
            <div class="stat-value">
                <span class="counter" data-target="<?= $stats['classes'] ?>">0</span>
            </div>
            <div class="stat-sub">Active Class Sections</div>
        </div>
    </div>
    
    <div class="stat-card modern-stat" data-color="purple">
        <div class="stat-icon">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Subjects</div>
            <div class="stat-value">
                <span class="counter" data-target="<?= $stats['subjects'] ?>">0</span>
            </div>
            <div class="stat-sub">Active Subjects</div>
        </div>
    </div>
</div>

<!-- Today's Attendance & Quick Actions -->
<div class="two-column-grid">
    
    <!-- Today's Attendance - Clean Simple View -->
    <div class="card modern-card">
        <div class="card-header">
            <span class="card-title">
                <i class="fas fa-calendar-check" style="color:var(--primary)"></i> 
                Today's Attendance
            </span>
            <span class="date-badge"><?= date('l, d M Y') ?></span>
        </div>
        <div class="card-body">
            <?php if ($totalAttendance > 0): ?>
            <div class="attendance-cards">
                <div class="attendance-stat present">
                    <div class="attendance-value"><?= $attMap['present'] ?></div>
                    <div class="attendance-label">
                        <i class="fas fa-check-circle"></i> Present
                    </div>
                    <div class="attendance-percent"><?= $totalAttendance > 0 ? round(($attMap['present'] / $totalAttendance) * 100) : 0 ?>%</div>
                </div>
                <div class="attendance-stat absent">
                    <div class="attendance-value"><?= $attMap['absent'] ?></div>
                    <div class="attendance-label">
                        <i class="fas fa-times-circle"></i> Absent
                    </div>
                    <div class="attendance-percent"><?= $totalAttendance > 0 ? round(($attMap['absent'] / $totalAttendance) * 100) : 0 ?>%</div>
                </div>
                <div class="attendance-stat late">
                    <div class="attendance-value"><?= $attMap['late'] ?></div>
                    <div class="attendance-label">
                        <i class="fas fa-clock"></i> Late
                    </div>
                    <div class="attendance-percent"><?= $totalAttendance > 0 ? round(($attMap['late'] / $totalAttendance) * 100) : 0 ?>%</div>
                </div>
            </div>
            <div class="attendance-footer">
                <div class="total-count">Total Students Today: <strong><?= $totalAttendance ?></strong></div>
                <a href="attendance.php?date=<?= $today ?>" class="btn btn-outline btn-sm">View Details</a>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No attendance marked for today</p>
                <a href="attendance.php" class="btn btn-primary btn-sm">Mark Attendance</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card modern-card">
        <div class="card-header">
            <span class="card-title">
                <i class="fas fa-bolt" style="color:var(--warning)"></i> 
                Quick Actions
            </span>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <a href="students.php?action=add" class="quick-action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Add Student</div>
                        <div class="action-sub">Register new student</div>
                    </div>
                </a>
                <a href="teachers.php?action=add" class="quick-action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Add Teacher</div>
                        <div class="action-sub">Hire new teacher</div>
                    </div>
                </a>
                <a href="attendance.php" class="quick-action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Take Attendance</div>
                        <div class="action-sub">Mark today's attendance</div>
                    </div>
                </a>
                <a href="announcements.php?action=add" class="quick-action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">New Announcement</div>
                        <div class="action-sub">Post announcement</div>
                    </div>
                </a>
                <a href="classes.php" class="quick-action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Manage Classes</div>
                        <div class="action-sub">Setup class sections</div>
                    </div>
                </a>
                <a href="subjects.php" class="quick-action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%);">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Manage Subjects</div>
                        <div class="action-sub">Setup subjects</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Students & Activity Logs -->
<div class="two-column-grid">
    
    <!-- Recent Students -->
    <div class="card modern-card">
        <div class="card-header">
            <span class="card-title">
                <i class="fas fa-users" style="color:var(--primary)"></i> 
                Recently Added Students
            </span>
            <a href="students.php" class="btn btn-outline btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="student-list">
            <?php if (empty($recentStudents)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <p>No students added yet</p>
                </div>
            <?php else: foreach ($recentStudents as $s): ?>
            <div class="student-item">
                <div class="student-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="student-details">
                    <div class="student-name"><?= h($s['name']) ?></div>
                    <div class="student-meta">
                        <span class="student-email"><?= h($s['email']) ?></span>
                        <?php if (!empty($s['class_name'])): ?>
                        <span class="student-class">• <?= h($s['class_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($s['roll_no'])): ?>
                        <span class="student-roll">• Roll: <?= h($s['roll_no']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="student-join-date">
                    <?= date('d M', strtotime($s['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Activity Logs -->
    <div class="card modern-card">
        <div class="card-header">
            <span class="card-title">
                <i class="fas fa-history" style="color:var(--primary)"></i> 
                Recent Activity
            </span>
            <a href="logs.php" class="btn btn-outline btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="activity-list">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No activity yet</p>
                </div>
            <?php else: foreach ($logs as $log): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">
                        <strong><?= h($log['name'] ?? 'System') ?></strong> 
                        <?= h($log['action'] ?? 'No action') ?>
                    </div>
                    <div class="activity-time"><?= date('d M, h:i a', strtotime($log['created_at'] ?? 'now')) ?></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Announcements -->
<?php if (!empty($announcements)): ?>
<div class="card modern-card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-bullhorn" style="color:var(--warning)"></i> 
            Latest Announcements
        </span>
        <a href="announcements.php" class="btn btn-outline btn-sm">Manage <i class="fas fa-cog"></i></a>
    </div>
    <div class="announcement-list">
        <?php foreach ($announcements as $ann): ?>
        <div class="announcement-item">
            <div class="announcement-badge badge-<?= ($ann['target_role'] ?? 'all') === 'all' ? 'primary' : (($ann['target_role'] ?? '') === 'student' ? 'info' : 'success') ?>">
                <?= ucfirst($ann['target_role'] ?? 'All') ?>
            </div>
            <div class="announcement-title"><?= h($ann['title'] ?? 'Untitled') ?></div>
            <div class="announcement-content"><?= h(substr($ann['content'] ?? '', 0, 100)) ?>...</div>
            <div class="announcement-date">
                <i class="far fa-calendar-alt"></i> <?= date('d M Y', strtotime($ann['created_at'] ?? 'now')) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
/* Modern Dashboard CSS */
.two-column-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .two-column-grid {
        grid-template-columns: 1fr;
    }
}

/* Modern Stat Cards */
.modern-stat {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.modern-stat:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
}

.modern-stat .stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
}

.modern-stat[data-color="blue"] .stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.modern-stat[data-color="green"] .stat-icon {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.modern-stat[data-color="yellow"] .stat-icon {
    background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
}

.modern-stat[data-color="purple"] .stat-icon {
    background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%);
}

.modern-stat .stat-value {
    font-size: 32px;
    font-weight: 800;
    color: var(--text);
}

.modern-stat .stat-label {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.modern-stat .stat-sub {
    font-size: 11px;
    color: var(--text-muted);
}

/* Attendance Cards */
.attendance-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.attendance-stat {
    text-align: center;
    padding: 16px;
    border-radius: 12px;
    transition: transform 0.2s;
}

.attendance-stat:hover {
    transform: scale(1.02);
}

.attendance-stat.present {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.attendance-stat.absent {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.attendance-stat.late {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.attendance-value {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 8px;
}

.attendance-stat.present .attendance-value { color: #10b981; }
.attendance-stat.absent .attendance-value { color: #ef4444; }
.attendance-stat.late .attendance-value { color: #f59e0b; }

.attendance-label {
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
}

.attendance-percent {
    font-size: 12px;
    opacity: 0.8;
}

.attendance-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    font-size: 13px;
}

.total-count {
    color: var(--text-muted);
}

.date-badge {
    background: var(--surface2);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    color: var(--text-muted);
}

/* Quick Actions */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.quick-action-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: var(--surface);
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.quick-action-card:hover {
    transform: translateX(4px);
    background: var(--surface2);
    border-color: var(--primary);
}

.action-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.action-text {
    flex: 1;
}

.action-title {
    font-weight: 600;
    color: var(--text);
    font-size: 14px;
    margin-bottom: 2px;
}

.action-sub {
    font-size: 11px;
    color: var(--text-muted);
}

/* Student List */
.student-list {
    max-height: 350px;
    overflow-y: auto;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.student-item:hover {
    background: var(--surface2);
}

.student-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.student-details {
    flex: 1;
}

.student-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
}

.student-meta {
    font-size: 11px;
    color: var(--text-muted);
}

.student-join-date {
    font-size: 11px;
    color: var(--text-muted);
    flex-shrink: 0;
}

/* Activity List */
.activity-list {
    max-height: 350px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.activity-item:hover {
    background: var(--surface2);
}

.activity-icon {
    width: 35px;
    height: 35px;
    background: var(--surface2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-text {
    font-size: 13px;
    margin-bottom: 4px;
    line-height: 1.4;
}

.activity-time {
    font-size: 10px;
    color: var(--text-muted);
}

/* Announcement List */
.announcement-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.announcement-item {
    padding: 12px;
    background: var(--surface2);
    border-radius: 10px;
    border-left: 3px solid var(--primary);
}

.announcement-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-bottom: 8px;
}

.announcement-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 6px;
}

.announcement-content {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 8px;
    line-height: 1.4;
}

.announcement-date {
    font-size: 10px;
    color: var(--text-muted);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state p {
    margin-bottom: 16px;
}

/* Counter Animation */
.counter {
    display: inline-block;
}
</style>

<script>
// Animated Counter Function
function animateCounter(element) {
    const target = parseInt(element.getAttribute('data-target'));
    let current = 0;
    const increment = Math.ceil(target / 60);
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = current;
        }
    }, 16);
}

// Intersection Observer for counters
const observerOptions = {
    threshold: 0.3,
    rootMargin: '0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const counters = entry.target.querySelectorAll('.counter');
            counters.forEach(counter => {
                const currentValue = parseInt(counter.textContent);
                if (currentValue === 0) {
                    animateCounter(counter);
                }
            });
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe the stats grid
document.addEventListener('DOMContentLoaded', () => {
    const statsGrid = document.querySelector('.stats-grid');
    if (statsGrid) observer.observe(statsGrid);
});
</script>

<?php require_once '../includes/footer.php'; ?>