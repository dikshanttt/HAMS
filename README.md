# Hospital Management System — Auth Module (Step 1)

Plain PHP (no framework), PDO for DB access so it works with PostgreSQL
or MSSQL without code changes elsewhere.

## Setup

1. Create the database and run `schema.sql` against it.
2. Edit `config/db.php`:
   - Set `DB_DRIVER` to `pgsql` or `sqlsrv`
   - Set host/port/name/user/password
   - If using MSSQL, you also need the `sqlsrv` + `pdo_sqlsrv` PHP extensions
     installed (Microsoft's official drivers).
3. Seed one admin so you have a way in. Generate a hash:
   ```
   php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT);"
   ```
   Then run the commented INSERT statements at the bottom of `schema.sql`
   with that hash — or just register one via `register/admin.php` (see note
   in that file about locking that page down later).
4. Serve the `hms/` folder with PHP's built-in server for local testing:
   ```
   php -S localhost:8000 -t hms
   ```
5. For real outgoing email, swap `includes/mailer.php`'s `send_email()` body
   for PHPMailer + SMTP credentials. Everything else calls `send_email()`
   so nothing else needs to change.

## How the roles work

- **Patient / Admin**: register with email + password → account is
  `active` immediately → log in with email.
- **Doctor**: registers with professional details + an ID/photo image,
  but is NOT given a password at registration. Account sits as
  `status = pending` until an admin reviews it.
  - Admin approves → system generates a `doctor_login_id` (e.g. `DOC-1042`)
    and a temporary password, emails both to the doctor.
  - Admin rejects → account marked `rejected`, doctor is emailed the reason.
  - Doctors log in with their **login ID**, not email.

## What's next (dashboards)

This step only covers auth + the doctor verification queue. Each
`*/dashboard.php` is a placeholder guarded by `require_login([...role])` —
build out real dashboard features there next.

## Security already in place

- PDO prepared statements everywhere (no raw SQL concatenation)
- `password_hash()` / `password_verify()` (bcrypt)
- CSRF tokens on every form
- Session ID regenerated on login (session fixation protection)
- Uploaded doctor images validated by real MIME type (not extension),
  size-capped, renamed to random filenames on save

## Still worth doing before a real deployment

- Rate-limit login attempts
- Move admin registration behind an existing-admin-only gate
- HTTPS + secure session cookie flags
- Move `config/db.php` credentials to environment variables, not hardcoded
