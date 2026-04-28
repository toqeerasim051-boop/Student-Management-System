# EduManage SMS — Student Management System

A full-stack PHP/MySQL web application for managing students, teachers, classes, attendance, and grades across a school. Built with role-based access control for three user types: Admin, Teacher, and Student.

---

## Screenshots

> Place screenshots in a `/screenshots` folder and link them here after setup.

---

## Features

**Admin**
- Dashboard with live stats (students, teachers, classes, subjects)
- Manage students, teachers, classes, and subjects (full CRUD)
- View and mark attendance across all classes
- View all grades and generate reports
- Post announcements targeted at all users, students only, or teachers only
- Activity log tracking every action with old/new values

**Teacher**
- Personal dashboard showing assigned classes and subjects
- Mark and update attendance per subject/class/date
- Enter and update student grades with term/exam-type support
- View student roster for assigned classes

**Student**
- Personal dashboard with attendance summary and recent grades
- View full attendance history per subject
- View grade breakdown by term and exam type
- Profile page with class, subjects, and teacher info

**Security**
- CSRF tokens on all POST forms
- Session ID regenerated on login (prevents session fixation)
- All DB queries use prepared statements (no SQL injection)
- Passwords hashed with bcrypt (`password_hash`)
- Role-based route protection — users can only access their own section
- Input validation with length checks and `filter_var` for emails
- Output escaped with `htmlspecialchars` everywhere

---

## Tech Stack

- **Backend:** PHP 8.x
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** Vanilla HTML/CSS/JS, Font Awesome icons
- **Server:** Apache or Nginx (XAMPP / WAMP / LAMP)

---

## Setup

### Requirements
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite` (included in XAMPP/WAMP)

### Installation

1. **Clone the repo**
   ```bash
   git clone https://github.com/your-username/sms-project.git
   cd sms-project
   ```

2. **Place in web root**
   - XAMPP: Copy to `C:/xampp/htdocs/sms-project/`
   - WAMP: Copy to `C:/wamp64/www/sms-project/`
   - Linux: Copy to `/var/www/html/sms-project/`

3. **Configure the database**
   ```bash
   cp config/config.example.php config/config.local.php
   ```
   Edit `config/config.local.php` with your DB credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'sms_db');
   ```

4. **Import the database**
   - Open phpMyAdmin → Import → select `setup.sql`
   - Or via CLI: `mysql -u root -p < setup.sql`

5. **Generate secure passwords** *(recommended)*
   ```bash
   php generate_passwords.php
   ```
   Copy the output SQL into `setup.sql` before importing, then delete `generate_passwords.php`.

6. **Open in browser**
   ```
   http://localhost/sms-project/
   ```

---

## Demo Accounts

| Role    | Email             | Password     |
|---------|-------------------|--------------|
| Admin   | admin@sms.com     | Admin@123    |
| Teacher | ali@sms.com       | Teacher@123  |
| Teacher | sara@sms.com      | Teacher@123  |
| Student | ahmed@sms.com     | Student@123  |

> Change these passwords immediately after first login in a real deployment.

---

## Project Structure

```
sms-project/
├── admin/              # Admin panel pages
│   ├── dashboard.php
│   ├── students.php
│   ├── teachers.php
│   ├── classes.php
│   ├── subjects.php
│   ├── attendance.php
│   ├── grades.php
│   ├── announcements.php
│   └── logs.php
├── teacher/            # Teacher portal pages
│   ├── dashboard.php
│   ├── attendance.php
│   ├── grades.php
│   └── students.php
├── student/            # Student portal pages
│   ├── dashboard.php
│   ├── attendance.php
│   ├── grades.php
│   └── profile.php
├── auth/
│   ├── login.php
│   └── logout.php
├── config/
│   ├── db.php              # DB connection + helpers
│   ├── config.local.php    # Your credentials (git-ignored)
│   └── config.example.php  # Template to copy
├── includes/
│   ├── auth.php            # Auth, CSRF, session helpers
│   ├── header.php
│   └── footer.php
├── assets/
│   ├── css/style.css
│   └── js/script.js
├── setup.sql               # Database schema + seed data
├── generate_passwords.php  # One-time password hash generator
├── .gitignore
└── README.md
```

---

## Security Notes

- `config/config.local.php` is in `.gitignore` — never commit it
- All forms include CSRF tokens verified server-side
- `session_regenerate_id(true)` is called on every successful login
- Phone fields are sanitized to digits/symbols only
- Email fields are validated with `filter_var(FILTER_VALIDATE_EMAIL)`

---

## License

MIT — free to use and modify.
