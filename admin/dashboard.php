<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();

$stats = $db->query(
    "SELECT
        COUNT(*)                                                    AS total_users,
        COUNT(*) FILTER (WHERE role = 'patient')                   AS patients,
        COUNT(*) FILTER (WHERE role = 'doctor')                    AS doctors,
        COUNT(*) FILTER (WHERE role = 'doctor' AND status='active') AS active_doctors,
        COUNT(*) FILTER (WHERE role = 'doctor' AND status='pending') AS pending_doctors,
        COUNT(*) FILTER (WHERE role = 'doctor' AND status='rejected') AS rejected_doctors,
        COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '7 days') AS new_this_week
     FROM users"
)->fetch();

// 5 most recent sign-ups (any role)
$recent = $db->query(
    "SELECT u.id, u.email, u.role, u.status, u.created_at,
            COALESCE(p.name, dp.name, ap.name, 'N/A') AS display_name
     FROM users u
     LEFT JOIN patient_profiles p  ON p.user_id  = u.id AND u.role = 'patient'
     LEFT JOIN doctor_profiles  dp ON dp.user_id = u.id AND u.role = 'doctor'
     LEFT JOIN admin_profiles   ap ON ap.user_id = u.id AND u.role = 'admin'
     ORDER BY u.created_at DESC
     LIMIT 6"
)->fetchAll();

// Latest 3 pending doctor apps for the quick preview
$pendingPreview = $db->query(
    "SELECT dp.name, dp.specialization, dp.experience_years, u.email, u.created_at
     FROM doctor_profiles dp
     JOIN users u ON u.id = dp.user_id
     WHERE dp.verification_status = 'pending' AND u.role = 'doctor'
     ORDER BY u.created_at ASC LIMIT 3"
)->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        /* ── Admin-specific overrides ── */
        :root{--sidebar-bg:#0f2b22;--sidebar-border:rgba(255,255,255,0.07);--sidebar-text:#9ab8a8;--sidebar-active-bg:rgba(255,255,255,0.09);--gold:#e5c16f}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

        body.admin-page{background:#f3f6f4;font-family:var(--font-body);color:var(--text-body);min-height:100vh}

        /* Layout */
        .admin-shell{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}

        /* Sidebar */
        .adm-sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .adm-brand{display:flex;align-items:center;gap:12px;padding:28px 22px 24px;border-bottom:1px solid var(--sidebar-border);text-decoration:none}
        .adm-brand-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--primary);color:#fff;font-size:1.3rem;flex-shrink:0}
        .adm-brand-text strong{display:block;color:#fff;font-family:var(--font-heading);font-size:1.1rem;letter-spacing:-.01em}
        .adm-brand-text small{color:var(--sidebar-text);font-size:.7rem;letter-spacing:.1em;text-transform:uppercase}

        .adm-nav{padding:20px 12px;flex:1;display:grid;gap:4px}
        .adm-nav-label{font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#3d6052;padding:14px 10px 6px;margin-top:8px}
        .adm-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--sidebar-text);font-size:.88rem;font-weight:600;transition:all 150ms ease;position:relative}
        .adm-nav a:hover{background:var(--sidebar-active-bg);color:#fff}
        .adm-nav a.active{background:var(--sidebar-active-bg);color:#fff}
        .adm-nav a .nav-icon{font-size:1rem;width:22px;text-align:center}
        .adm-badge{margin-left:auto;background:var(--gold);color:#0f2b22;font-size:.7rem;font-weight:800;padding:2px 7px;border-radius:99px}

        .adm-sidebar-footer{padding:16px 20px 24px;border-top:1px solid var(--sidebar-border)}
        .adm-sys-status{display:flex;align-items:center;gap:8px;font-size:.78rem;color:var(--sidebar-text);margin-bottom:12px}
        .adm-sys-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;flex-shrink:0}
        .adm-sidebar-footer a{display:block;font-size:.82rem;font-weight:600;color:var(--gold);text-decoration:none;padding:4px 0}
        .adm-sidebar-footer a:hover{color:#fff}

        /* Content area */
        .adm-content{display:flex;flex-direction:column;min-height:100vh}

        /* Topbar */
        .adm-topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 36px;display:flex;align-items:center;justify-content:space-between;height:70px;position:sticky;top:0;z-index:40}
        .adm-topbar-left p{color:var(--text-muted);font-size:.8rem;margin-bottom:2px}
        .adm-topbar-left h1{font-size:1.35rem;font-weight:800;color:var(--text-main);letter-spacing:-.02em}
        .adm-profile{display:flex;align-items:center;gap:12px}
        .adm-avatar{width:38px;height:38px;border-radius:50%;background:var(--gold);color:var(--sidebar-bg);font-weight:800;font-size:.85rem;display:grid;place-items:center;flex-shrink:0}
        .adm-profile-info strong{display:block;font-size:.88rem;color:var(--text-main)}
        .adm-profile-info small{font-size:.74rem;color:var(--text-muted)}

        /* Main body */
        .adm-body{padding:32px 36px;flex:1}

        /* Stat cards */
        .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .stat-box{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px 22px 18px;box-shadow:0 1px 4px rgba(15,43,34,.04);transition:transform 180ms ease}
        .stat-box:hover{transform:translateY(-3px)}
        .stat-box-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
        .stat-box-num{font-family:var(--font-heading);font-size:2.4rem;font-weight:800;line-height:1;color:var(--text-main);margin-bottom:4px}
        .stat-box-sub{font-size:.76rem;color:var(--text-muted)}
        .stat-box.highlight{border-color:rgba(229,193,111,.5);background:linear-gradient(135deg,#fffbf0 0%,#fef6d8 100%)}
        .stat-box.highlight .stat-box-num{color:#92661b}
        .stat-box.patients .stat-box-num{color:var(--primary)}
        .stat-box.doctors  .stat-box-num{color:#0369a1}

        /* 2-column grid */
        .adm-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:22px;margin-bottom:28px}

        /* Panel card */
        .panel{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(15,43,34,.04)}
        .panel-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);gap:12px}
        .panel-head h2{font-size:1rem;font-weight:700;color:var(--text-main)}
        .panel-head small{font-size:.78rem;color:var(--text-muted)}
        .panel-body{padding:0}

        /* Recent users table */
        .adm-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .adm-table th{padding:10px 18px;text-align:left;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);background:#f8faf7;border-bottom:1px solid var(--border)}
        .adm-table td{padding:12px 18px;border-bottom:1px solid var(--border);color:var(--text-body);vertical-align:middle}
        .adm-table tr:last-child td{border-bottom:none}
        .adm-table tr:hover td{background:#f9fbf9}
        .role-tag{display:inline-block;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:capitalize}
        .role-tag.patient{background:#dcfce7;color:var(--primary-dark)}
        .role-tag.doctor {background:#e0f2fe;color:#0369a1}
        .role-tag.admin  {background:#fef3c7;color:#92661b}
        .us-tag{display:inline-block;padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:700}
        .us-active  {background:#dcfce7;color:var(--primary-dark)}
        .us-pending  {background:#fef3c7;color:#92661b}
        .us-rejected {background:#fee2e2;color:#b91c1c}

        /* Pending preview cards */
        .pending-list{display:grid;gap:0}
        .pending-row{display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--border)}
        .pending-row:last-child{border-bottom:none}
        .pending-avatar{width:40px;height:40px;border-radius:50%;background:var(--primary-soft);color:var(--primary-dark);font-weight:800;font-size:.85rem;display:grid;place-items:center;flex-shrink:0}
        .pending-info{flex:1;min-width:0}
        .pending-info strong{display:block;font-size:.88rem;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .pending-info span{font-size:.76rem;color:var(--text-muted)}
        .pending-cta{flex-shrink:0}

        /* CTA banner */
        .cta-banner{background:linear-gradient(120deg,#0f2b22 0%,#0b6e4f 100%);border-radius:14px;padding:28px 32px;display:flex;align-items:center;justify-content:space-between;gap:24px;color:#fff;margin-bottom:28px}
        .cta-banner h2{font-size:1.3rem;margin-bottom:6px;color:#fff}
        .cta-banner p{font-size:.88rem;color:rgba(255,255,255,.78);margin:0}
        .cta-banner-btn{flex-shrink:0;background:var(--gold);color:#0f2b22;font-weight:800;padding:11px 22px;border-radius:10px;text-decoration:none;font-size:.9rem;transition:background 150ms}
        .cta-banner-btn:hover{background:#f0d280}
    </style>
</head>
<body class="admin-page">
<div class="adm-sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<button class="adm-mob-toggle" id="sidebarToggle" onclick="openSidebar()" aria-label="Open navigation">☰</button>
<div class="admin-shell">

    <!-- ===== SIDEBAR ===== -->
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
            <a class="active" href="dashboard.php">
                <span class="nav-icon">⊞</span>Overview
            </a>
            <a href="verify_doctors.php">
                <span class="nav-icon">✦</span>Verify Doctors
                <?php if ((int)$stats['pending_doctors'] > 0): ?>
                    <span class="adm-badge"><?= (int)$stats['pending_doctors'] ?></span>
                <?php endif; ?>
            </a>

            <span class="adm-nav-label">Account</span>
            <a href="../logout.php">
                <span class="nav-icon">↩</span>Sign Out
            </a>
        </nav>

        <div class="adm-sidebar-footer">
            <div class="adm-sys-status">
                <span class="adm-sys-dot"></span>
                All systems operational
            </div>
            <a href="verify_doctors.php">Open Verification Desk →</a>
        </div>
    </aside>

    <!-- ===== CONTENT ===== -->
    <div class="adm-content">

        <!-- Topbar -->
        <header class="adm-topbar">
            <div class="adm-topbar-left">
                <p><?= date('l, F j, Y') ?></p>
                <h1>Administrative Overview</h1>
            </div>
            <div class="adm-profile">
                <div class="adm-avatar">SA</div>
                <div class="adm-profile-info">
                    <strong>Super Admin</strong>
                    <small>System Administrator</small>
                </div>
            </div>
        </header>

        <!-- Body -->
        <div class="adm-body">

            <?php if ($flash): ?>
            <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="stat-row">
                <div class="stat-box highlight">
                    <div class="stat-box-label">⏳ Pending Verification</div>
                    <div class="stat-box-num"><?= (int)$stats['pending_doctors'] ?></div>
                    <div class="stat-box-sub">doctor applications in queue</div>
                </div>
                <div class="stat-box doctors">
                    <div class="stat-box-label">✓ Active Doctors</div>
                    <div class="stat-box-num"><?= (int)$stats['active_doctors'] ?></div>
                    <div class="stat-box-sub">verified &amp; accepting patients</div>
                </div>
                <div class="stat-box patients">
                    <div class="stat-box-label">👤 Registered Patients</div>
                    <div class="stat-box-num"><?= (int)$stats['patients'] ?></div>
                    <div class="stat-box-sub">patient accounts</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">📅 New This Week</div>
                    <div class="stat-box-num"><?= (int)$stats['new_this_week'] ?></div>
                    <div class="stat-box-sub">total sign-ups in last 7 days</div>
                </div>
            </div>

            <!-- CTA banner -->
            <?php if ((int)$stats['pending_doctors'] > 0): ?>
            <div class="cta-banner">
                <div>
                    <h2>🩺 <?= (int)$stats['pending_doctors'] ?> Doctor Application<?= $stats['pending_doctors'] > 1 ? 's' : '' ?> Awaiting Review</h2>
                    <p>Review credentials, approve or reject each application. Approved doctors will receive their login ID and temporary password via email.</p>
                </div>
                <a class="cta-banner-btn" href="verify_doctors.php">Review Now →</a>
            </div>
            <?php endif; ?>

            <!-- 2-col Grid -->
            <div class="adm-grid">

                <!-- Recent Sign-ups -->
                <div class="panel">
                    <div class="panel-head">
                        <h2>Recent Accounts</h2>
                        <small>Latest 6 sign-ups across all roles</small>
                    </div>
                    <div class="panel-body">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Name / Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td>
                                        <strong style="display:block;font-size:.88rem"><?= clean($r['display_name']) ?></strong>
                                        <span style="color:var(--text-muted);font-size:.76rem"><?= clean($r['email']) ?></span>
                                    </td>
                                    <td><span class="role-tag <?= clean($r['role']) ?>"><?= clean(ucfirst($r['role'])) ?></span></td>
                                    <td>
                                        <?php
                                        $sc = ['active'=>'us-active','pending'=>'us-pending','rejected'=>'us-rejected'][$r['status']] ?? 'us-pending';
                                        ?>
                                        <span class="us-tag <?= $sc ?>"><?= clean(ucfirst($r['status'])) ?></span>
                                    </td>
                                    <td style="font-size:.8rem;color:var(--text-muted)"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent)): ?>
                                <tr><td colspan="4" style="text-align:center;padding:28px;color:var(--text-muted)">No accounts registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pending Doctor Preview -->
                <div class="panel">
                    <div class="panel-head">
                        <h2>Pending Doctor Queue</h2>
                        <a href="verify_doctors.php" style="font-size:.8rem;font-weight:700;color:var(--primary)">View All →</a>
                    </div>
                    <div class="panel-body pending-list">
                        <?php if (empty($pendingPreview)): ?>
                        <div style="text-align:center;padding:36px 20px">
                            <div style="font-size:2rem;margin-bottom:8px">🎉</div>
                            <p style="color:var(--text-muted);font-size:.88rem">No pending applications — all clear!</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pendingPreview as $p):
                            $av = strtoupper(mb_substr(trim(preg_replace('/^Dr\.?\s*/i','',$p['name'])),0,2)) ?: 'DR';
                        ?>
                        <div class="pending-row">
                            <div class="pending-avatar"><?= clean($av) ?></div>
                            <div class="pending-info">
                                <strong><?= clean($p['name']) ?></strong>
                                <span><?= clean($p['specialization']) ?> &bull; <?= (int)$p['experience_years'] ?> yrs exp</span>
                            </div>
                            <div class="pending-cta">
                                <a class="btn btn-primary btn-sm" href="verify_doctors.php">Review</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /adm-grid -->

            <!-- System Summary Row -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
                <div class="stat-box" style="padding:18px 20px">
                    <div class="stat-box-label">Total Doctors Registered</div>
                    <div class="stat-box-num" style="font-size:1.8rem"><?= (int)$stats['doctors'] ?></div>
                    <div class="stat-box-sub"><?= (int)$stats['rejected_doctors'] ?> rejected applications on file</div>
                </div>
                <div class="stat-box" style="padding:18px 20px">
                    <div class="stat-box-label">Total System Users</div>
                    <div class="stat-box-num" style="font-size:1.8rem"><?= (int)$stats['total_users'] ?></div>
                    <div class="stat-box-sub">patients + doctors + admins</div>
                </div>
                <div class="stat-box" style="padding:18px 20px">
                    <div class="stat-box-label">Database Status</div>
                    <div class="stat-box-num" style="font-size:1.8rem;color:var(--success)">OK</div>
                    <div class="stat-box-sub">PostgreSQL connection healthy</div>
                </div>
            </div>

        </div><!-- /adm-body -->
    </div><!-- /adm-content -->
</div><!-- /admin-shell -->
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
</body>
</html>