<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();

// Aggregated Stats
$totalHospitals = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE is_active = TRUE")->fetchColumn();
$activeDoctors  = (int)$db->query("SELECT COUNT(DISTINCT d.user_id) FROM doctor_profiles d JOIN users u ON d.user_id = u.id WHERE d.verification_status = 'verified' AND u.status = 'active'")->fetchColumn();
$pendingDoctors = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched   = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
$pendingApps    = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending_hospital_approval'")->fetchColumn();
$totalPatients  = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();

// 5 most recent sign-ups
$recentUsers = $db->query("
    SELECT u.id, u.email, u.role, u.status, u.created_at,
           COALESCE(p.name, dp.name, ap.name, 'N/A') AS display_name
    FROM users u
    LEFT JOIN patient_profiles p  ON p.user_id  = u.id AND u.role = 'patient'
    LEFT JOIN doctor_profiles  dp ON dp.user_id = u.id AND u.role = 'doctor'
    LEFT JOIN admin_profiles   ap ON ap.user_id = u.id AND u.role = 'admin'
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll();

// Pending schedule requests preview
$pendingSchedPreview = $db->query("
    SELECT s.*, dp.name AS doctor_name, h.name AS hospital_name
    FROM schedules s
    JOIN doctor_profiles dp ON dp.user_id = s.doctor_id
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.status = 'pending_approval'
    ORDER BY s.requested_at ASC
    LIMIT 3
")->fetchAll();

// Recent appointments preview
$recentAppsPreview = $db->query("
    SELECT a.*, p.name AS patient_name, dp.name AS doctor_name, h.name AS hospital_name
    FROM appointments a
    JOIN patient_profiles p ON p.user_id = a.patient_id
    JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
    JOIN hospitals h ON h.id = a.hospital_id
    ORDER BY a.created_at DESC
    LIMIT 5
")->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Operations | HAMS Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        :root{--sidebar-bg:#0f2b22;--sidebar-border:rgba(255,255,255,0.07);--sidebar-text:#9ab8a8;--sidebar-active-bg:rgba(255,255,255,0.09);--gold:#e5c16f}
        body.admin-page{background:#f3f6f4;font-family:var(--font-body);color:var(--text-body);min-height:100vh}
        .admin-shell{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}
        .adm-sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .adm-brand{display:flex;align-items:center;gap:12px;padding:28px 22px 24px;border-bottom:1px solid var(--sidebar-border);text-decoration:none}
        .adm-brand-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--primary);color:#fff;font-size:1.3rem;flex-shrink:0}
        .adm-brand-text strong{display:block;color:#fff;font-family:var(--font-heading);font-size:1.1rem}
        .adm-brand-text small{color:var(--sidebar-text);font-size:.7rem;letter-spacing:.1em;text-transform:uppercase}
        .adm-nav{padding:20px 12px;flex:1;display:grid;gap:4px}
        .adm-nav-label{font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#3d6052;padding:14px 10px 6px;margin-top:8px}
        .adm-nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--sidebar-text);font-size:.88rem;font-weight:600;transition:all 150ms}
        .adm-nav a:hover, .adm-nav a.active{background:var(--sidebar-active-bg);color:#fff}
        .adm-badge{margin-left:auto;background:var(--gold);color:#0f2b22;font-size:.7rem;font-weight:800;padding:2px 7px;border-radius:99px}
        .adm-content{display:flex;flex-direction:column;min-height:100vh}
        .adm-topbar{background:#fff;border-bottom:1px solid var(--border);padding:0 36px;display:flex;align-items:center;justify-content:space-between;height:70px;position:sticky;top:0;z-index:40}
        .adm-body{padding:32px 36px;flex:1}
        .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .stat-box{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px 22px 18px;box-shadow:0 1px 4px rgba(15,43,34,.04)}
        .stat-box-label{font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px}
        .stat-box-num{font-family:var(--font-heading);font-size:2.2rem;font-weight:800;line-height:1;color:var(--text-main);margin-bottom:4px}
        .stat-box-sub{font-size:.76rem;color:var(--text-muted)}
        .adm-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:22px;margin-bottom:28px}
        .panel{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(15,43,34,.04)}
        .panel-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);gap:12px}
        .adm-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .adm-table th{padding:10px 18px;text-align:left;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);background:#f8faf7;border-bottom:1px solid var(--border)}
        .adm-table td{padding:12px 18px;border-bottom:1px solid var(--border);color:var(--text-body);vertical-align:middle}
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
            <span class="adm-nav-label">Hospital Network</span>
            <a class="active" href="dashboard.php"><span class="nav-icon">⊞</span>Overview</a>
            <a href="hospitals.php"><span class="nav-icon">🏥</span>Manage Hospitals</a>
            <a href="verify_doctors.php">
                <span class="nav-icon">🩺</span>Verify &amp; Affiliations
                <?php if ($pendingDoctors > 0): ?><span class="adm-badge"><?= $pendingDoctors ?></span><?php endif; ?>
            </a>
            <a href="schedule_approvals.php">
                <span class="nav-icon">📅</span>Schedule Approvals
                <?php if ($pendingSched > 0): ?><span class="adm-badge"><?= $pendingSched ?></span><?php endif; ?>
            </a>
            <a href="appointments.php">
                <span class="nav-icon">📋</span>Appointments
                <?php if ($pendingApps > 0): ?><span class="adm-badge" style="background:#0369a1;color:#fff"><?= $pendingApps ?></span><?php endif; ?>
            </a>

            <span class="adm-nav-label">Account</span>
            <a href="../logout.php"><span class="nav-icon">↩</span>Sign Out</a>
        </nav>
    </aside>

    <!-- Content -->
    <div class="adm-content">
        <header class="adm-topbar">
            <div>
                <p style="color:var(--text-muted);font-size:.8rem"><?= date('l, F j, Y') ?></p>
                <h1 style="font-size:1.35rem;font-weight:800;color:var(--text-main)">Super Admin Operations Center</h1>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
                <span class="status-badge done">All Services Online ✓</span>
            </div>
        </header>

        <div class="adm-body">
            <?php if ($flash): ?>
            <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="stat-row">
                <div class="stat-box">
                    <div class="stat-box-label">🏥 Partner Hospitals</div>
                    <div class="stat-box-num" style="color:var(--primary-dark)"><?= $totalHospitals ?></div>
                    <div class="stat-box-sub">accredited medical facilities</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">🩺 Verified Doctors</div>
                    <div class="stat-box-num" style="color:#0369a1"><?= $activeDoctors ?></div>
                    <div class="stat-box-sub"><?= $pendingDoctors ?> applications awaiting review</div>
                </div>
                <div class="stat-box" style="border-color:rgba(229,193,111,.5);background:linear-gradient(135deg,#fffbf0 0%,#fef6d8 100%)">
                    <div class="stat-box-label">⏳ Schedule Requests</div>
                    <div class="stat-box-num" style="color:#92661b"><?= $pendingSched ?></div>
                    <div class="stat-box-sub">doctor schedule change requests</div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">📋 Pending Bookings</div>
                    <div class="stat-box-num" style="color:#0f766e"><?= $pendingApps ?></div>
                    <div class="stat-box-sub">awaiting hospital email confirmation</div>
                </div>
            </div>

            <!-- Quick Action Banners -->
            <?php if ($pendingSched > 0 || $pendingDoctors > 0): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:28px">
                <?php if ($pendingSched > 0): ?>
                <div style="background:linear-gradient(135deg,#0f2b22 0%,#0b6e4f 100%);color:#fff;border-radius:12px;padding:20px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <strong style="display:block;font-size:1.05rem">📅 <?= $pendingSched ?> Schedule Change<?= $pendingSched > 1 ? 's' : '' ?></strong>
                        <small style="color:rgba(255,255,255,0.8)">Doctors requested new consultation slots.</small>
                    </div>
                    <a href="schedule_approvals.php" class="btn btn-sm" style="background:var(--gold);color:#0f2b22;font-weight:800">Review</a>
                </div>
                <?php endif; ?>

                <?php if ($pendingDoctors > 0): ?>
                <div style="background:linear-gradient(135deg,#0369a1 0%,#0284c7 100%);color:#fff;border-radius:12px;padding:20px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <strong style="display:block;font-size:1.05rem">🩺 <?= $pendingDoctors ?> Doctor Application<?= $pendingDoctors > 1 ? 's' : '' ?></strong>
                        <small style="color:rgba(255,255,255,0.8)">Awaiting medical license verification.</small>
                    </div>
                    <a href="verify_doctors.php" class="btn btn-sm" style="background:#fff;color:#0369a1;font-weight:800">Verify</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 2-column Grid -->
            <div class="adm-grid">

                <!-- Recent Appointments -->
                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <h2 style="font-size:1rem;font-weight:700">Recent Hospital Appointment Requests</h2>
                            <small style="color:var(--text-muted)">Latest bookings and hospital alert status</small>
                        </div>
                        <a href="appointments.php" style="font-size:.82rem;font-weight:700;color:var(--primary)">View All →</a>
                    </div>
                    <div class="panel-body">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Token / Patient</th>
                                    <th>Doctor &amp; Hospital</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAppsPreview as $app): 
                                    $stClass = match($app['status']) {
                                        'confirmed' => 'done',
                                        'rejected_by_hospital' => 'rejected',
                                        default => 'waiting'
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <strong style="display:block;color:var(--primary-dark)"><?= clean($app['appointment_token']) ?></strong>
                                        <small style="color:var(--text-muted)"><?= clean($app['patient_name']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= clean($app['doctor_name']) ?></strong>
                                        <small style="display:block;color:var(--text-muted)"><?= clean($app['hospital_name']) ?></small>
                                    </td>
                                    <td><?= date('M j', strtotime($app['appointment_date'])) ?></td>
                                    <td><span class="status-badge <?= $stClass ?>" style="font-size:.72rem"><?= ucfirst(clean($app['status'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Sign-ups -->
                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <h2 style="font-size:1rem;font-weight:700">Latest Registered Users</h2>
                            <small style="color:var(--text-muted)">Patient, Doctor &amp; Admin registrations</small>
                        </div>
                    </div>
                    <div class="panel-body">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $u): ?>
                                <tr>
                                    <td>
                                        <strong style="display:block;font-size:.86rem"><?= clean($u['display_name']) ?></strong>
                                        <small style="color:var(--text-muted)"><?= clean($u['email']) ?></small>
                                    </td>
                                    <td><span class="badge-tag" style="background:#e0f2fe;color:#0369a1"><?= ucfirst(clean($u['role'])) ?></span></td>
                                    <td><span class="status-badge <?= $u['status']==='active'?'done':'waiting' ?>"><?= ucfirst(clean($u['status'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function openSidebar(){ document.querySelector(".adm-sidebar").classList.add("open"); document.getElementById("sidebarOverlay").classList.add("open"); }
function closeSidebar(){ document.querySelector(".adm-sidebar").classList.remove("open"); document.getElementById("sidebarOverlay").classList.remove("open"); }
</script>
</body>
</html>