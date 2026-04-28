/* ============================================
   EduManage SMS - Main JavaScript
   ============================================ */

// ============ THEME TOGGLE ============
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    if (html.getAttribute('data-theme') === 'dark') {
        html.setAttribute('data-theme', 'light');
        if (icon) { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
        localStorage.setItem('sms-theme', 'light');
    } else {
        html.setAttribute('data-theme', 'dark');
        if (icon) { icon.classList.remove('fa-sun'); icon.classList.add('fa-moon'); }
        localStorage.setItem('sms-theme', 'dark');
    }
}

// ============ SIDEBAR TOGGLE ============
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}

// ============ MODAL HELPERS ============
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('open');
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('open');
}

// ============ DELETE CONFIRM ============
function confirmDelete(url, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?\nThis action cannot be undone.')) {
        window.location.href = url;
    }
}

// ============ ATTENDANCE BUTTONS ============
function setAttendance(studentId, status) {
    const btn = document.querySelector('[data-student="' + studentId + '"]');
    const input = document.getElementById('att_' + studentId);
    if (input) input.value = status;

    // Update UI
    const row = btn ? btn.closest('tr') : null;
    if (row) {
        row.querySelectorAll('.att-btn').forEach(b => {
            b.classList.remove('present', 'absent', 'late');
        });
        if (btn) btn.classList.add(status);
    }
}

function markAllPresent() {
    document.querySelectorAll('.att-status').forEach(input => {
        const id = input.dataset.student;
        input.value = 'present';
        const row = input.closest('tr');
        if (row) {
            row.querySelectorAll('.att-btn').forEach(b => b.classList.remove('present','absent','late'));
            const presBtn = row.querySelector('.btn-present');
            if (presBtn) presBtn.classList.add('present');
        }
    });
}

// ============ SEARCH FILTER ============
function searchTable(inputId, tableId) {
    const val = document.getElementById(inputId)?.value?.toLowerCase() || '';
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}

// ============ CSV EXPORT ============
function exportCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) { alert('No data to export.'); return; }
    const rows = table.querySelectorAll('tr');
    let csv = [];
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = [];
        cols.forEach(col => {
            // Skip action columns (last if has buttons)
            let text = col.innerText.replace(/[\r\n]+/g, ' ').trim();
            text = '"' + text.replace(/"/g, '""') + '"';
            rowData.push(text);
        });
        csv.push(rowData.join(','));
    });
    // Remove last column (Actions) from header if present
    const csvString = csv.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', filename + '_' + new Date().toISOString().slice(0,10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ============ INIT ============
document.addEventListener('DOMContentLoaded', () => {
    // Restore theme
    const savedTheme = localStorage.getItem('sms-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const icon = document.getElementById('themeIcon');
    if (icon) {
        if (savedTheme === 'light') { icon.classList.remove('fa-moon'); icon.classList.add('fa-sun'); }
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Auto-dismiss alerts after 4s
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 4000);
    });

    // Confirm delete buttons
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
});

// ============ FORM VALIDATION ============
function validateRequired(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    let valid = true;
    form.querySelectorAll('[required]').forEach(field => {
        field.style.borderColor = '';
        if (!field.value.trim()) {
            field.style.borderColor = 'var(--danger)';
            valid = false;
        }
    });
    if (!valid) { alert('Please fill in all required fields.'); }
    return valid;
}

// ============ PRINT ============
function printContent(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const w = window.open('', '_blank');
    w.document.write('<html><head><title>Print</title>');
    w.document.write('<link rel="stylesheet" href="' + window.location.origin + '/sms-project/assets/css/style.css">');
    w.document.write('</head><body style="background:#fff;color:#000;padding:20px;">');
    w.document.write(el.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.print();
}
