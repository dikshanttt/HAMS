# Hospital Appointment Management System (HAMS)

A production-grade healthcare platform built from first principles.

## 🎯 Purpose

HAMS demonstrates full-stack engineering: secure authentication, role-based access control, clean architecture, and proper security practices. Built as an academic project but engineered like production software.

## ✨ Key Features

### Authentication & Authorization
- Three-tier role system: Patient (auto-approved) → Doctor (approval queue) → Admin
- Bcrypt password hashing with bcrypt algorithm
- CSRF token protection on all forms
- Session fixation prevention (session_regenerate_id)
- 30-minute inactivity timeout
- HttpOnly + Secure + SameSite cookies

### Security
- PDO prepared statements (no SQL injection)
- Server-side file MIME type validation (not extension-based)
- Database transactions for data consistency
- Comprehensive input validation before database operations

### Architecture
- Plain PHP (no framework) + Vanilla JavaScript
- PDO database abstraction layer (PostgreSQL / MSSQL compatible)
- Proper separation of concerns (config, includes, auth, roles)
- Reusable utility functions (CSRF, mailer, file uploads)

## 🛠️ Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 7.4+ |
| Frontend | HTML5, Vanilla CSS, Vanilla JavaScript |
| Database | PostgreSQL, MySQL, MSSQL (via PDO) |
| Email | PHPMailer (SMTP-ready) |

## 📋 What This Teaches

This project is useful if you want to learn:
- How to build authentication systems *securely* (not just "working")
- PDO + prepared statements for database access
- CSRF protection implementation
- File upload validation best practices
- Session management patterns
- Multi-role authorization

**Not** a framework tutorial. This is closer to what production looks like.

## 🚀 Quick Start

### Requirements
- PHP 7.4+
- PostgreSQL 12+ (or MySQL 5.7+, or MSSQL)
- Composer

### Setup

1. **Clone and install dependencies**
```bash
   git clone https://github.com/dikshanttt/HAMS.git
   cd HAMS
   composer install
```

2. **Configure database**
   Edit `config/db.php`:
```php
   const DB_DRIVER = 'pgsql'; // or 'mysql' or 'sqlsrv'
   const DB_HOST = 'localhost';
   const DB_NAME = 'hams';
   const DB_USER = 'postgres';
   const DB_PASS = 'your_password';
```

3. **Create database and schema**
```bash
   createdb hams
   psql hams < schema.sql
```

4. **Seed an admin user** (optional)
```php
   php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT);"
```
   Then insert the hash into the `users` table via the SQL file commented section.

5. **Start development server**
```bash
   php -S localhost:8000 -t .
```

   Visit `http://localhost:8000/` and login with your admin credentials.

## 🔐 Security Features Explained

### Session Management
- Sessions regenerate on login → prevents session fixation attacks
- 30-minute inactivity timeout → prevents unauthorized access on shared devices
- Session data is validated on every request → detects tampering

### CSRF Protection
Every form includes a unique token:
```html
<input type="hidden" name="csrf_token" value="...">
```
The server verifies it with a timing-safe comparison (`hash_equals()`), preventing cross-site request forgery.

### File Uploads
Doctor profile images are validated by actual file content, not extension:
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']); // Returns real MIME type
```
Prevents uploading `malware.php` with a fake `.jpg` extension.

### Database
All queries use prepared statements:
```php
$stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]); // Parameters separated from query
```
No string concatenation = no SQL injection.

## 🎓 Role-Based Workflows

### Patient Workflow
1. Register with email + password
2. Account is immediately active
3. Login and access patient dashboard
4. Book appointments with doctors

### Doctor Workflow
1. Register with professional details + image
2. Account sits in "pending" status
3. Admin reviews and approves
4. Doctor receives email with auto-generated login ID + temporary password
5. Doctor must change password on first login
6. Can now access doctor dashboard

### Admin Workflow
1. Set up in database (see seed section above)
2. Access admin dashboard
3. Review and approve/reject doctors
4. View system analytics

## 📊 Database Schema

Three main tables:
- `users` — Authentication + role/status
- `patient_profiles` — Patient-specific data (name, DOB, phone, etc.)
- `doctor_profiles` — Doctor-specific data (specialization, license, etc.)

Full schema in `schema.sql`.

## 🚧 What's Next

Current phase: **Authentication module** (complete)

Planned dashboards:
- [ ] Patient: Browse doctors, book appointments
- [ ] Doctor: View appointments, prescriptions
- [ ] Admin: System analytics, user management

## ⚠️ Before Production Deployment

This is an *educational* project. Before deploying to production:

- [ ] Move database credentials to environment variables (not hardcoded)
- [ ] Enable HTTPS (required for secure cookies)
- [ ] Implement rate limiting on login attempts
- [ ] Set up error logging (not var_dump() in production)
- [ ] Add database migration system
- [ ] Restrict admin registration to existing admins only
- [ ] Add X-Frame-Options and other security headers

See [SECURITY.md](SECURITY.md) for detailed security practices.

## 🤝 Contributing

This is a portfolio project, but feedback is welcome! If you spot security issues or have suggestions, open an issue.

## 📝 License

Public for learning and reference.

---

**Built with**: PHP, security best practices, and way too much coffee. ☕
