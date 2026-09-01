# Security Practices in HAMS

## Session Management
- **Regenerate session IDs on login** — Prevents session fixation attacks
- **30-minute inactivity timeout** — Logs users out of shared devices
- **HttpOnly + Secure + SameSite cookies** — Prevents JavaScript access and cross-site attacks

## Password Security
- **Bcrypt hashing** — Industry standard, slow by design (password_hash with PASSWORD_DEFAULT)
- **Minimum 8 characters, maximum 72** — Respects bcrypt's 72-byte limit
- **Temporary passwords for doctors** — Generated securely, forced change on first login
- **Timing-safe password verification** — Uses password_verify() to prevent timing attacks

## CSRF Protection
- **Unique token per form** — Generated on each page load
- **Timing-safe comparison** — Uses hash_equals() to prevent timing attacks
- **Token verified before processing** — No state-changing operation without valid token

## File Uploads
- **Server-side MIME type validation** — Uses finfo, not file extension
- **Size limits** — Maximum 2MB per image
- **Random filenames** — Prevents path traversal attacks (e.g., ../../../etc/passwd)
- **Separate upload directory** — Images stored outside web root access

## Database Security
- **PDO prepared statements** — Parameters separated from SQL, no concatenation
- **No raw SQL concatenation** — Every query uses placeholders (?)
- **Database transactions** — Atomic operations, rollback on failure
- **Multiple database support** — Works with PostgreSQL, MySQL, MSSQL via PDO abstraction

## Input Validation
- **Server-side validation** — Never trust client-side checks
- **Email validation** — Uses filter_var with FILTER_VALIDATE_EMAIL
- **Phone number validation** — Regex pattern matching before database
- **Date validation** — DateTime parsing before database insertion

## What's NOT Yet Implemented
These are planned improvements (see GitHub issues):
- [ ] Rate limiting on login attempts
- [ ] Credentials via environment variables (.env)
- [ ] Security event logging (admin actions, suspicious activity)
- [ ] Database migration system
- [ ] X-Frame-Options headers (clickjacking protection)

## Known Limitations
- This is an educational project, not yet deployed to production
- Before production deployment, implement all "Not Yet Implemented" items above
- Requires HTTPS in production (for secure cookies to work)

## Reporting Security Issues
If you find a vulnerability, please email me privately rather than opening a public issue.

---

**Last Updated**: August 2026
