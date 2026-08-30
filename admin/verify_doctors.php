<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $db->prepare(
        'SELECT u.id, u.email, dp.name
         FROM users u
         JOIN doctor_profiles dp ON dp.user_id = u.id
         WHERE u.id = ? AND u.role = ? AND dp.verification_status = ?'
    );
    $stmt->execute([$userId, 'doctor', 'pending']);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        $message = 'Application not found or already processed.';
        $messageType = 'error';
    } elseif ($action === 'approve') {
        $loginId = generate_doctor_login_id($db);
        $tempPw  = generate_temp_password();
        $hash    = password_hash($tempPw, PASSWORD_DEFAULT);

        try {
            $db->beginTransaction();
            $db->prepare('UPDATE users SET doctor_login_id=?,password_hash=?,status=?,force_password_change=TRUE WHERE id=? AND status=?')
               ->execute([$loginId, $hash, 'active', $userId, 'pending']);
            $db->prepare('UPDATE doctor_profiles SET verification_status=?,verified_at=CURRENT_TIMESTAMP,verified_by_admin_id=? WHERE user_id=? AND verification_status=?')
               ->execute(['verified', current_user_id(), $userId, 'pending']);
            $db->commit();

            try {
                $sent = send_doctor_verified_email($doctor['email'], $doctor['name'], $loginId, $tempPw);
                $message = "Dr. {$doctor['name']} approved. Login ID <strong>{$loginId}</strong> " . ($sent ? "emailed to {$doctor['email']}." : "(email could not be sent — share ID manually).");
            } catch (Throwable $e) {
                $message = "Dr. {$doctor['name']} approved. Login ID: <strong>{$loginId}</strong>";
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message     = 'Something went wrong while approving. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? 'Application does not meet requirements.');
        try {
            $db->beginTransaction();
            $db->prepare('UPDATE users SET status=? WHERE id=? AND status=?')
               ->execute(['rejected', $userId, 'pending']);
            $db->prepare('UPDATE doctor_profiles SET verification_status=?,rejection_reason=?,verified_at=CURRENT_TIMESTAMP,verified_by_admin_id=? WHERE user_id=? AND verification_status=?')
               ->execute(['rejected', $reason, current_user_id(), $userId, 'pending']);
            $db->commit();
            try { send_doctor_rejected_email($doctor['email'], $doctor['name'], $reason); } catch (Throwable $e) {}
            $message = "Application for Dr. {$doctor['name']} has been rejected.";
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message     = 'Something went wrong while rejecting. Please try again.';
            $messageType = 'error';
        }
    }
}

$pending = $db->query(
    "SELECT u.id, u.email, u.created_at,
            dp.name, dp.specialization, dp.license_no, dp.phone,
            dp.qualification, dp.experience_years, dp.image_path
     FROM users u
     JOIN doctor_profiles dp ON dp.user_id = u.id
     WHERE dp.verification_status = 'pending' AND u.role = 'doctor'
     ORDER BY u.created_at ASC"
)->fetchAll();

$stats = $db->query(
    "SELECT
        COUNT(*) FILTER (WHERE role='doctor' AND status='pending') AS pending,
        COUNT(*) FILTER (WHERE role='doctor' AND status='active')  AS active,
        COUNT(*) FILTER (WHERE role='doctor' AND status='rejected') AS rejected
     FROM users"
)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Verification Desk | HAMS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        :root{--sidebar-bg:#0f2b22;--sidebar-border:rgba(255,255,255,0.07);--sidebar-text:#9ab8a8;--sidebar-active-bg:rgba(255,255,255,0.09);--gold:#e5c16f}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body.admin-page{background:#f3f6f4;font-family:var(--font-body);color:var(--text-body);min-height:100vh}
        .admin-shell{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}

        .adm-sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .adm-brand{display:flex;align-items:center;gap:12px;padding:28px 22px 24px;border-bottom:1px solid var(--sidebar-border);text-decoration:none}
        .adm-brand-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--primary);color:#fff;font-size:1.3rem;flex-shrink:0}
        .adm-brand-text strong{display:block;color:#fff;font-family:var(--font-heading);font-size:1.1rem}
        .adm-brand-text small{color:var(--sidebar-text);font-size:.7rem;letter-spacing:.1em;text-transform:uppercase}
        .adm-nav{padding:20px 12px;flex:1;display:grid;gap:4px}
        .adm-nav-label{font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#3d6052;padding:14px 10px 6px;margin-top:8px}
        .adm-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--sidebar-text);font-size:.88rem;font-weight:600;transition:all 150ms ease}
        .adm-nav a:hover,.adm-nav a.active{background:var(--sidebar-active-bg);color:#fff}
        .adm-nav a .nav-icon{font-size:1rem;width:22px;text-align:center}
        .adm-badge{margin-left:auto;background:var(--gold);color:#0f2b22;font-size:.7rem;font-weight:800;padding:2px 7px;border-radius:99px}
        .adm-sidebar-footer{padding:16px 20px 24px;border-top:1px solid var(--sidebar-border)}
        .adm-sys-status{display:flex;align-items:center;gap:8px;font-size:.78rem;color:var(--sidebar-text);margin-bottom:12px}
        .adm-sys-dot{width:7px;height:7px;border-radius:50%;background:#22c55e}
        .adm-sidebar-footer a{display:block;font-size:.82rem;font-weight:600;color:var(--gold);text-decoration:none;padding:4px 0}
        .adm-sidebar-footer a:hover{color:#fff}

        .adm-content{display:flex;flex-direction:column;min-height:100vh}
        .adm-topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 36px;display:flex;align-items:center;justify-content:space-between;height:70px;position:sticky;top:0;z-index:40}
        .adm-topbar-left p{color:var(--text-muted);font-size:.8rem;margin-bottom:2px}
        .adm-topbar-left h1{font-size:1.35rem;font-weight:800;color:var(--text-main);letter-spacing:-.02em}
        .adm-profile{display:flex;align-items:center;gap:12px}
        .adm-avatar{width:38px;height:38px;border-radius:50%;background:var(--gold);color:var(--sidebar-bg);font-weight:800;font-size:.85rem;display:grid;place-items:center}
        .adm-profile-info strong{display:block;font-size:.88rem;color:var(--text-main)}
        .adm-profile-info small{font-size:.74rem;color:var(--text-muted)}
        .adm-body{padding:32px 36px;flex:1}

        /* Mini stat strip */
        .mini-stat-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px}
        .mini-stat{background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px}
        .mini-stat-icon{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0}
        .mini-stat-icon.warn{background:#fef3c7;color:#92661b}
        .mini-stat-icon.ok  {background:#dcfce7;color:var(--primary-dark)}
        .mini-stat-icon.err {background:#fee2e2;color:#b91c1c}
        .mini-stat-num{font-family:var(--font-heading);font-size:1.6rem;font-weight:800;line-height:1;color:var(--text-main)}
        .mini-stat-label{font-size:.74rem;color:var(--text-muted);margin-top:2px}

        /* Doctor review cards */
        .doctor-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px}
        .doc-card{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(15,43,34,.04)}
        .doc-card-head{background:linear-gradient(120deg,var(--primary-surface) 0%,#daf4e5 100%);padding:20px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border)}
        .doc-card-avatar{width:52px;height:52px;border-radius:50%;background:var(--primary-soft);color:var(--primary-dark);font-weight:800;font-size:1.05rem;display:grid;place-items:center;flex-shrink:0;border:2px solid rgba(11,110,79,.2)}
        .doc-card-avatar img{width:52px;height:52px;border-radius:50%;object-fit:cover}
        .doc-card-head-info h3{font-size:1rem;font-weight:700;color:var(--text-main);margin-bottom:3px}
        .doc-card-head-info span{font-size:.78rem;color:var(--primary);font-weight:700;background:var(--primary-soft);padding:2px 8px;border-radius:99px}

        .doc-card-body{padding:18px 20px;display:grid;gap:8px}
        .doc-field{display:flex;align-items:flex-start;gap:10px;font-size:.84rem}
        .doc-field-label{color:var(--text-muted);font-weight:600;width:88px;flex-shrink:0}
        .doc-field-val{color:var(--text-main);font-weight:500;word-break:break-word}
        .doc-field-val code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.8rem;color:var(--primary-dark)}

        .doc-card-actions{padding:16px 20px;border-top:1px solid var(--border);display:flex;gap:10px}
        .doc-card-actions .btn{flex:1;justify-content:center}

        /* Rejection modal overlay */
        .modal-overlay{position:fixed;inset:0;background:rgba(15,43,34,.5);display:none;align-items:center;justify-content:center;z-index:200;padding:20px}
        .modal-overlay.open{display:flex}
        .modal-box{background:#fff;border-radius:18px;padding:32px;width:min(100%,480px);box-shadow:0 24px 60px rgba(0,0,0,.2)}
        .modal-box h3{font-size:1.3rem;margin-bottom:8px;color:var(--text-main)}
        .modal-box p{font-size:.9rem;color:var(--text-muted);margin-bottom:18px}
        .modal-box textarea{width:100%;min-height:100px;border:1.5px solid var(--border);border-radius:10px;padding:12px;font-family:var(--font-body);font-size:.9rem;resize:vertical;outline:none;transition:border 150ms}
        .modal-box textarea:focus{border-color:var(--primary)}
        .modal-actions{display:flex;gap:10px;margin-top:16px;justify-content:flex-end}
    </style>
</head>
<body class="admin-page">
<div class="adm-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<button class="adm-mob-toggle" id="sidebarToggle" onclick="openSidebar()" aria-label="Open navigation">☰</button>
<div class="admin-shell">

    <!-- Sidebar -->
    <aside class="adm-sidebar">
        <a class="adm-brand" href="dashboard.php">
            <span class="adm-brand-icon">✚</span>
            <div class="adm-brand-text">
                <strong>HAMS</strong>
                <small>Admin Console</small>
            </div>
        </a>
        <nav class="adm-nav">
            <span class="adm-nav-label">Hospital System</span>
            <a href="dashboard.php"><span class="nav-icon">⊞</span>Overview</a>
            <a class="active" href="verify_doctors.php">
                <span class="nav-icon">✦</span>Verify Doctors
                <?php if ((int)$stats['pending'] > 0): ?>
                    <span class="adm-badge"><?= (int)$stats['pending'] ?></span>
                <?php endif; ?>
            </a>
            <span class="adm-nav-label">Account</span>
            <a href="../logout.php"><span class="nav-icon">↩</span>Sign Out</a>
        </nav>
        <div class="adm-sidebar-footer">
            <div class="adm-sys-status"><span class="adm-sys-dot"></span>All systems operational</div>
            <a href="dashboard.php">← Back to Overview</a>
        </div>
    </aside>

    <!-- Content -->
    <div class="adm-content">
        <header class="adm-topbar">
            <div class="adm-topbar-left">
                <p>Doctor Accreditation / <?= date('F j, Y') ?></p>
                <h1>Verification Desk</h1>
            </div>
            <div class="adm-profile">
                <div class="adm-avatar">SA</div>
                <div class="adm-profile-info">
                    <strong>Super Admin</strong>
                    <small>System Administrator</small>
                </div>
            </div>
        </header>

        <div class="adm-body">

            <?php if ($message): ?>
            <div class="<?= $messageType==='error'?'error-message':'success-message' ?>" style="margin-bottom:24px">
                <?= $message /* already contains safe HTML */ ?>
            </div>
            <?php endif; ?>

            <!-- Mini stats -->
            <div class="mini-stat-strip">
                <div class="mini-stat">
                    <div class="mini-stat-icon warn">⏳</div>
                    <div><div class="mini-stat-num"><?= (int)$stats['pending'] ?></div><div class="mini-stat-label">Awaiting Review</div></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-icon ok">✓</div>
                    <div><div class="mini-stat-num"><?= (int)$stats['active'] ?></div><div class="mini-stat-label">Verified & Active</div></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-icon err">✕</div>
                    <div><div class="mini-stat-num"><?= (int)$stats['rejected'] ?></div><div class="mini-stat-label">Applications Rejected</div></div>
                </div>
            </div>

            <?php if (empty($pending)): ?>
            <div style="background:#fff;border:1px solid var(--border);border-radius:14px;text-align:center;padding:60px 24px">
                <div style="font-size:3rem;margin-bottom:12px">🎉</div>
                <h3 style="font-size:1.3rem;margin-bottom:8px">All Caught Up!</h3>
                <p style="color:var(--text-muted);font-size:.95rem">No pending doctor applications right now. New applications will appear here when submitted.</p>
                <a class="btn btn-primary" href="dashboard.php" style="margin-top:22px">Return to Dashboard</a>
            </div>
            <?php else: ?>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
                <div>
                    <span class="section-tag">Pending Review</span>
                    <h2 style="font-size:1.3rem;margin-top:4px"><?= count($pending) ?> Application<?= count($pending)!==1?'s':'' ?> Waiting</h2>
                </div>
                <p style="font-size:.84rem;color:var(--text-muted)">Approve to auto-generate login credentials &amp; email the doctor.</p>
            </div>

            <div class="doctor-cards">
                <?php foreach ($pending as $d):
                    $av = strtoupper(mb_substr(trim(preg_replace('/^Dr\.?\s*/i','',$d['name'])),0,2)) ?: 'DR';
                    $submittedAgo = human_time_diff($d['created_at']);
                ?>
                <div class="doc-card">
                    <div class="doc-card-head">
                        <div class="doc-card-avatar">
                            <?php if (!empty($d['image_path']) && file_exists(__DIR__.'/../'.$d['image_path'])): ?>
                                <img src="../<?= clean($d['image_path']) ?>" alt="<?= clean($d['name']) ?>">
                            <?php else: ?>
                                <?= clean($av) ?>
                            <?php endif; ?>
                        </div>
                        <div class="doc-card-head-info">
                            <h3><?= clean($d['name']) ?></h3>
                            <span><?= clean($d['specialization']) ?></span>
                        </div>
                    </div>

                    <div class="doc-card-body">
                        <div class="doc-field">
                            <span class="doc-field-label">Email</span>
                            <span class="doc-field-val"><?= clean($d['email']) ?></span>
                        </div>
                        <div class="doc-field">
                            <span class="doc-field-label">Phone</span>
                            <span class="doc-field-val"><?= clean($d['phone']) ?></span>
                        </div>
                        <div class="doc-field">
                            <span class="doc-field-label">License</span>
                            <span class="doc-field-val"><code><?= clean($d['license_no']) ?></code></span>
                        </div>
                        <div class="doc-field">
                            <span class="doc-field-label">Qualification</span>
                            <span class="doc-field-val"><?= clean($d['qualification']) ?></span>
                        </div>
                        <div class="doc-field">
                            <span class="doc-field-label">Experience</span>
                            <span class="doc-field-val"><?= (int)$d['experience_years'] ?> years</span>
                        </div>
                        <div class="doc-field">
                            <span class="doc-field-label">Applied</span>
                            <span class="doc-field-val"><?= clean($submittedAgo) ?></span>
                        </div>
                    </div>

                    <div class="doc-card-actions">
                        <!-- Approve -->
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$d['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-primary btn-sm"
                                onclick="return confirm('Approve Dr. <?= clean(addslashes($d['name'])) ?>? An auto-generated login ID and temporary password will be emailed to them.')">
                                ✓ Approve
                            </button>
                        </form>

                        <!-- Reject (opens modal) -->
                        <button type="button" class="btn btn-danger btn-sm"
                            onclick="openRejectModal(<?= (int)$d['id'] ?>, '<?= clean(addslashes($d['name'])) ?>')">
                            ✕ Reject
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3>Reject Doctor Application</h3>
        <p id="rejectModalDesc">Please provide a clear reason for rejection. This will be sent to the doctor via email.</p>
        <form method="POST" id="rejectForm">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" id="rejectUserId">
            <input type="hidden" name="action" value="reject">
            <textarea name="reason" id="rejectReason" placeholder="e.g. License number could not be verified with the Nepal Medical Council. Please reapply with correct credentials."></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"
                    onclick="if(!document.getElementById('rejectReason').value.trim()){alert('Please enter a reason.');return false;}">
                    Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSidebar(){
    document.querySelector(".adm-sidebar").classList.add("open");
    document.getElementById("sidebarOverlay").classList.add("open");
}
function closeSidebar(){
    document.querySelector(".adm-sidebar").classList.remove("open");
    document.getElementById("sidebarOverlay").classList.remove("open");
}
</script>
<script>
    function openRejectModal(userId, name) {
        document.getElementById('rejectUserId').value = userId;
        document.getElementById('rejectModalDesc').textContent =
            'Provide a clear reason for rejecting Dr. ' + name + '. This will be emailed to them.';
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectModal').classList.add('open');
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.remove('open');
    }
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) closeRejectModal();
    });
</script>
</body>
</html>