<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();
$adminId = current_user_id();

// Handle Actions (Approve, Reject, Add Affiliation, Remove/Left Affiliation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action   = $_POST['action'] ?? '';
    $doctorId = (int)($_POST['doctor_id'] ?? 0);

    if ($doctorId > 0) {
        if ($action === 'approve_doctor') {
            $loginId  = generate_doctor_login_id($db);
            $tempPass = generate_temp_password(10);
            $hash     = password_hash($tempPass, PASSWORD_BCRYPT);

            // Fetch doctor email and name
            $stmt = $db->prepare("
                SELECT u.email, dp.name
                FROM users u
                JOIN doctor_profiles dp ON dp.user_id = u.id
                WHERE u.id = ?
            ");
            $stmt->execute([$doctorId]);
            $doc = $stmt->fetch();

            if ($doc) {
                // Update User & Doctor Profile
                $db->prepare("
                    UPDATE users 
                    SET doctor_login_id = ?, password_hash = ?, status = 'active', force_password_change = TRUE 
                    WHERE id = ?
                ")->execute([$loginId, $hash, $doctorId]);

                $db->prepare("
                    UPDATE doctor_profiles 
                    SET verification_status = 'verified', verified_at = CURRENT_TIMESTAMP, verified_by_admin_id = ?
                    WHERE user_id = ?
                ")->execute([$adminId, $doctorId]);

                // Default hospital affiliation if selected
                $hospId = (int)($_POST['hospital_id'] ?? 0);
                if ($hospId > 0) {
                    $db->prepare("
                        INSERT INTO doctor_hospital (doctor_id, hospital_id, status, join_date)
                        VALUES (?, ?, 'active', CURRENT_DATE)
                    ")->execute([$doctorId, $hospId]);
                }

                // Send email
                send_doctor_verified_email($doc['email'], $doc['name'], $loginId, $tempPass);
                set_flash('success', "Doctor {$doc['name']} approved! Login ID: $loginId. Temporary credentials sent via email.");
            }
        } elseif ($action === 'reject_doctor') {
            $reason = clean($_POST['rejection_reason'] ?? 'Credentials could not be validated.');
            $stmt = $db->prepare("SELECT u.email, dp.name FROM users u JOIN doctor_profiles dp ON dp.user_id = u.id WHERE u.id = ?");
            $stmt->execute([$doctorId]);
            $doc = $stmt->fetch();

            if ($doc) {
                $db->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$doctorId]);
                $db->prepare("UPDATE doctor_profiles SET verification_status = 'rejected', rejection_reason = ? WHERE user_id = ?")->execute([$reason, $doctorId]);
                send_doctor_rejected_email($doc['email'], $doc['name'], $reason);
                set_flash('success', "Doctor {$doc['name']} application rejected and notification sent.");
            }
        } elseif ($action === 'add_affiliation') {
            $hospId = (int)($_POST['hospital_id'] ?? 0);
            if ($hospId > 0) {
                // Check if already active
                $check = $db->prepare("SELECT 1 FROM doctor_hospital WHERE doctor_id = ? AND hospital_id = ? AND status = 'active'");
                $check->execute([$doctorId, $hospId]);
                if ($check->fetch()) {
                    set_flash('error', 'Doctor is already actively affiliated with this hospital.');
                } else {
                    $db->prepare("
                        INSERT INTO doctor_hospital (doctor_id, hospital_id, status, join_date, status_update)
                        VALUES (?, ?, 'active', CURRENT_DATE, CURRENT_TIMESTAMP)
                    ")->execute([$doctorId, $hospId]);
                    set_flash('success', 'Doctor successfully affiliated with hospital.');
                }
            }
        } elseif ($action === 'mark_left_hospital') {
            $affId = (int)($_POST['affiliation_id'] ?? 0);
            if ($affId > 0) {
                $db->prepare("
                    UPDATE doctor_hospital 
                    SET status = 'left', leave_date = CURRENT_DATE, status_update = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ")->execute([$affId]);
                set_flash('success', 'Doctor marked as left hospital with departure date recorded.');
            }
        }
    }
    redirect('/admin/verify_doctors.php');
}

// Fetch Pending Doctors
$pending = $db->query("
    SELECT u.id, u.email, u.created_at,
           dp.name, dp.specialization, dp.license_no, dp.phone, dp.qualification, dp.experience_years, dp.image_path
    FROM users u
    JOIN doctor_profiles dp ON dp.user_id = u.id
    WHERE dp.verification_status = 'pending' AND u.role = 'doctor'
    ORDER BY u.created_at ASC
")->fetchAll();

// Fetch Verified Doctors with their affiliations
$verified = $db->query("
    SELECT u.id, u.email, u.doctor_login_id, u.status,
           dp.name, dp.specialization, dp.license_no, dp.qualification, dp.experience_years, dp.image_path, dp.verified_at
    FROM users u
    JOIN doctor_profiles dp ON dp.user_id = u.id
    WHERE dp.verification_status = 'verified'
    ORDER BY dp.name ASC
")->fetchAll();

// Fetch Active Hospitals for dropdown
$allHospitals = $db->query("SELECT id, name FROM hospitals WHERE is_active = TRUE ORDER BY name ASC")->fetchAll();

// Fetch all doctor-hospital records
$affiliations = $db->query("
    SELECT dh.*, h.name AS hospital_name, dp.name AS doctor_name
    FROM doctor_hospital dh
    JOIN hospitals h ON h.id = dh.hospital_id
    JOIN doctor_profiles dp ON dp.user_id = dh.doctor_id
    ORDER BY dh.status_update DESC
")->fetchAll();

$affByDoc = [];
foreach ($affiliations as $aff) {
    $affByDoc[$aff['doctor_id']][] = $aff;
}

$pendingSched = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Verification &amp; Affiliations | HAMS Admin</title>
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
        .panel{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(15,43,34,.04);margin-bottom:28px}
        .panel-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px}
        .adm-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .adm-table th{padding:10px 18px;text-align:left;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);background:#f8faf7;border-bottom:1px solid var(--border)}
        .adm-table td{padding:14px 18px;border-bottom:1px solid var(--border);color:var(--text-body);vertical-align:middle}
        .hosp-tag-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:6px;font-size:.76rem;margin-right:4px;margin-bottom:4px}
        .hosp-tag-active{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
        .hosp-tag-left{background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;text-decoration:line-through}
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
            <a href="dashboard.php"><span class="nav-icon">⊞</span>Overview</a>
            <a href="hospitals.php"><span class="nav-icon">🏥</span>Manage Hospitals</a>
            <a class="active" href="verify_doctors.php">
                <span class="nav-icon">🩺</span>Verify &amp; Affiliations
                <?php if (count($pending) > 0): ?><span class="adm-badge"><?= count($pending) ?></span><?php endif; ?>
            </a>
            <a href="schedule_approvals.php">
                <span class="nav-icon">📅</span>Schedule Approvals
                <?php if ($pendingSched > 0): ?><span class="adm-badge"><?= $pendingSched ?></span><?php endif; ?>
            </a>
            <a href="appointments.php"><span class="nav-icon">📋</span>Appointments</a>

            <span class="adm-nav-label">Account</span>
            <a href="../logout.php"><span class="nav-icon">↩</span>Sign Out</a>
        </nav>
    </aside>

    <!-- Content -->
    <div class="adm-content">
        <header class="adm-topbar">
            <div>
                <p style="color:var(--text-muted);font-size:.8rem">Medical Practitioners &amp; Affiliations</p>
                <h1 style="font-size:1.35rem;font-weight:800;color:var(--text-main)">Doctor Verification &amp; Hospital Assignments</h1>
            </div>
        </header>

        <div class="adm-body">
            <?php if ($flash): ?>
            <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Section 1: Pending Doctor Approvals -->
            <div class="panel">
                <div class="panel-head" style="background:#fffbf0">
                    <div>
                        <h2 style="font-size:1.1rem;font-weight:700;color:#92661b">
                            ⏳ Pending Doctor Verification Applications (<?= count($pending) ?>)
                        </h2>
                        <small style="color:var(--text-muted)">Verify medical license number, credentials, and approve with an assigned hospital.</small>
                    </div>
                </div>

                <div class="panel-body" style="overflow-x:auto">
                    <?php if (empty($pending)): ?>
                        <div style="padding:32px 20px;text-align:center;color:var(--text-muted)">
                            <div style="font-size:2rem;margin-bottom:6px">🎉</div>
                            <p>No pending doctor applications. All registrations are verified!</p>
                        </div>
                    <?php else: ?>
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Doctor Details</th>
                                <th>Specialization &amp; License</th>
                                <th>Experience / Contact</th>
                                <th>Assign Initial Hospital</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $p): ?>
                            <tr>
                                <td>
                                    <strong style="display:block;font-size:.95rem;color:var(--text-main)"><?= clean($p['name']) ?></strong>
                                    <small style="color:var(--text-muted)"><?= clean($p['email']) ?> &bull; <?= clean($p['qualification']) ?></small>
                                </td>
                                <td>
                                    <span class="badge-tag" style="background:#e0f2fe;color:#0369a1;font-weight:700"><?= clean($p['specialization']) ?></span>
                                    <small style="display:block;color:var(--text-muted);margin-top:4px">Lic: <?= clean($p['license_no']) ?></small>
                                </td>
                                <td>
                                    <strong><?= (int)$p['experience_years'] ?> years exp</strong>
                                    <small style="display:block;color:var(--text-muted)">📞 <?= clean($p['phone']) ?></small>
                                </td>
                                <td>
                                    <form id="approve_form_<?= $p['id'] ?>" method="POST" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="approve_doctor">
                                        <input type="hidden" name="doctor_id" value="<?= $p['id'] ?>">
                                        <select name="hospital_id" style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:.82rem">
                                            <option value="">-- Select Hospital --</option>
                                            <?php foreach ($allHospitals as $h): ?>
                                                <option value="<?= $h['id'] ?>"><?= clean($h['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;gap:6px">
                                        <button type="submit" form="approve_form_<?= $p['id'] ?>" class="btn btn-primary btn-sm">Approve</button>
                                        <form method="POST" style="display:inline" onsubmit="return promptDocReject(this)">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject_doctor">
                                            <input type="hidden" name="doctor_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button type="submit" class="btn btn-outline btn-sm">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 2: Verified Doctors & Multi-Hospital Affiliations -->
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 style="font-size:1.1rem;font-weight:700">Verified Specialists &amp; Affiliated Hospitals (<?= count($verified) ?>)</h2>
                        <small style="color:var(--text-muted)">Manage multi-hospital affiliations, add doctors to new hospitals, or mark them as left.</small>
                    </div>
                </div>

                <div class="panel-body" style="overflow-x:auto">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Specialist</th>
                                <th>Login ID &amp; License</th>
                                <th>Current Hospital Affiliations</th>
                                <th>Assign Additional Hospital</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verified as $v): 
                                $docAffs = $affByDoc[$v['id']] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--text-main);font-size:.95rem"><?= clean($v['name']) ?></strong>
                                    <small style="display:block;color:var(--text-muted)"><?= clean($v['specialization']) ?> &bull; <?= clean($v['qualification']) ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--primary-dark)"><?= clean($v['doctor_login_id']) ?></strong>
                                    <small style="display:block;color:var(--text-muted)"><?= clean($v['license_no']) ?></small>
                                </td>
                                <td>
                                    <?php if (empty($docAffs)): ?>
                                        <span style="font-size:.8rem;color:var(--text-muted)">No active hospital affiliations</span>
                                    <?php else: ?>
                                        <?php foreach ($docAffs as $da): ?>
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;background:#f8faf7;padding:5px 10px;border-radius:6px;border:1px solid var(--border)">
                                                <div>
                                                    <strong style="font-size:.82rem"><?= clean($da['hospital_name']) ?></strong>
                                                    <small style="display:block;color:var(--text-muted);font-size:.72rem">
                                                        Joined: <?= date('M j, Y', strtotime($da['join_date'])) ?>
                                                        <?= $da['status'] === 'left' ? ' &bull; Left: ' . date('M j, Y', strtotime($da['leave_date'] ?? 'now')) : '' ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <?php if ($da['status'] === 'active'): ?>
                                                        <form method="POST" style="display:inline" onsubmit="return confirm('Mark <?= clean(addslashes($v['name'])) ?> as left from <?= clean(addslashes($da['hospital_name'])) ?>?')">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="mark_left_hospital">
                                                            <input type="hidden" name="doctor_id" value="<?= $v['id'] ?>">
                                                            <input type="hidden" name="affiliation_id" value="<?= $da['id'] ?>">
                                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#b91c1c;padding:2px 6px;font-size:.72rem">Mark Left</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="status-badge waiting" style="font-size:.68rem">Left</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:flex;gap:6px">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add_affiliation">
                                        <input type="hidden" name="doctor_id" value="<?= $v['id'] ?>">
                                        <select name="hospital_id" required style="padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:.82rem">
                                            <option value="">-- Add Hospital --</option>
                                            <?php foreach ($allHospitals as $h): ?>
                                                <option value="<?= $h['id'] ?>"><?= clean($h['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-outline btn-sm">Assign</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function promptDocReject(form) {
    var reason = prompt('Please enter the reason for rejection:');
    if (!reason || !reason.trim()) return false;
    form.querySelector('input[name="rejection_reason"]').value = reason.trim();
    return true;
}
function openSidebar(){ document.querySelector(".adm-sidebar").classList.add("open"); document.getElementById("sidebarOverlay").classList.add("open"); }
function closeSidebar(){ document.querySelector(".adm-sidebar").classList.remove("open"); document.getElementById("sidebarOverlay").classList.remove("open"); }
</script>
</body>
</html>