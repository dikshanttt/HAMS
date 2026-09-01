<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();

// Handle External Hospital Status Updates (Confirmation / Rejection)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $appId  = (int)($_POST['appointment_id'] ?? 0);

    if ($appId > 0) {
        $stmtApp = $db->prepare("
            SELECT a.*,
                   p.name AS patient_name, u_pat.email AS patient_email, p.phone AS patient_phone,
                   dp.name AS doctor_name, dp.specialization,
                   h.name AS hospital_name, h.email AS hospital_email
            FROM appointments a
            JOIN patient_profiles p ON p.user_id = a.patient_id
            JOIN users u_pat ON u_pat.id = a.patient_id
            JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
            JOIN hospitals h ON h.id = a.hospital_id
            WHERE a.id = ?
        ");
        $stmtApp->execute([$appId]);
        $app = $stmtApp->fetch();

        if ($app) {
            if ($action === 'confirm_appointment') {
                $stmtUpd = $db->prepare("UPDATE appointments SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUpd->execute([$appId]);

                // Send email notification to patient
                send_appointment_status_to_patient($app['patient_email'], [
                    'token'            => $app['appointment_token'],
                    'patient_name'     => $app['patient_name'],
                    'doctor_name'      => $app['doctor_name'],
                    'hospital_name'    => $app['hospital_name'],
                    'appointment_date' => date('l, M j, Y', strtotime($app['appointment_date'])),
                    'slot_time'        => format_time_slot($app['slot_time']),
                ], 'confirmed');

                set_flash('success', "Appointment #{$app['appointment_token']} confirmed by hospital and patient notified via email.");
            } elseif ($action === 'reject_appointment') {
                $reason = clean($_POST['rejection_reason'] ?? 'Specialist unavailable due to urgent hospital surgery.');
                $stmtUpd = $db->prepare("
                    UPDATE appointments 
                    SET status = 'rejected_by_hospital', hospital_rejection_reason = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmtUpd->execute([$reason, $appId]);

                // Send rejection email to patient
                send_appointment_status_to_patient($app['patient_email'], [
                    'token'            => $app['appointment_token'],
                    'patient_name'     => $app['patient_name'],
                    'doctor_name'      => $app['doctor_name'],
                    'hospital_name'    => $app['hospital_name'],
                    'appointment_date' => date('l, M j, Y', strtotime($app['appointment_date'])),
                    'slot_time'        => format_time_slot($app['slot_time']),
                ], 'rejected_by_hospital', $reason);

                set_flash('success', "Appointment #{$app['appointment_token']} marked as rejected with reason and patient notified.");
            }
        }
    }
    redirect('/admin/appointments.php');
}

// Fetch all appointments with details
$appointments = $db->query("
    SELECT a.*,
           p.name AS patient_name, p.phone AS patient_phone, p.blood_group,
           dp.name AS doctor_name, dp.specialization,
           h.name AS hospital_name, h.email AS hospital_email
    FROM appointments a
    JOIN patient_profiles p ON p.user_id = a.patient_id
    JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
    JOIN hospitals h ON h.id = a.hospital_id
    ORDER BY a.appointment_date DESC, a.slot_time DESC
")->fetchAll();

$pendingDocs = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
$pendingSched = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending_approval'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Log | HAMS Admin</title>
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
        .panel{background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 1px 4px rgba(15,43,34,.04)}
        .panel-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px}
        .adm-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .adm-table th{padding:10px 18px;text-align:left;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);background:#f8faf7;border-bottom:1px solid var(--border)}
        .adm-table td{padding:14px 18px;border-bottom:1px solid var(--border);color:var(--text-body);vertical-align:middle}
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
            <a href="verify_doctors.php">
                <span class="nav-icon">🩺</span>Verify & Affiliations
                <?php if ($pendingDocs > 0): ?><span class="adm-badge"><?= $pendingDocs ?></span><?php endif; ?>
            </a>
            <a href="schedule_approvals.php">
                <span class="nav-icon">📅</span>Schedule Approvals
                <?php if ($pendingSched > 0): ?><span class="adm-badge"><?= $pendingSched ?></span><?php endif; ?>
            </a>
            <a class="active" href="appointments.php"><span class="nav-icon">📋</span>Appointments</a>

            <span class="adm-nav-label">Account</span>
            <a href="../logout.php"><span class="nav-icon">↩</span>Sign Out</a>
        </nav>
    </aside>

    <!-- Content -->
    <div class="adm-content">
        <header class="adm-topbar">
            <div>
                <p style="color:var(--text-muted);font-size:.8rem">Patient Booking Records</p>
                <h1 style="font-size:1.35rem;font-weight:800;color:var(--text-main)">Hospital Appointments Queue</h1>
            </div>
        </header>

        <div class="adm-body">
            <?php if ($flash): ?>
            <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 style="font-size:1.1rem;font-weight:700">All System Bookings (<?= count($appointments) ?>)</h2>
                        <small style="color:var(--text-muted)">Real-time record of all appointments requested, hospital alert emails, and status responses.</small>
                    </div>
                </div>

                <div class="panel-body" style="overflow-x:auto">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Patient</th>
                                <th>Specialist &amp; Hospital</th>
                                <th>Date &amp; Slot</th>
                                <th>Status</th>
                                <th>Hospital Email Alert</th>
                                <th style="text-align:right">Hospital Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $a): 
                                $statusClass = match($a['status']) {
                                    'confirmed' => 'done',
                                    'rejected_by_hospital' => 'rejected',
                                    default => 'waiting'
                                };
                                $statusLabel = match($a['status']) {
                                    'confirmed' => 'Confirmed ✓',
                                    'rejected_by_hospital' => 'Rejected ✕',
                                    'pending_hospital_approval' => 'Pending Hospital',
                                    default => ucfirst(clean($a['status']))
                                };
                            ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--primary-dark)"><?= clean($a['appointment_token']) ?></strong>
                                </td>
                                <td>
                                    <strong style="display:block;color:var(--text-main)"><?= clean($a['patient_name']) ?></strong>
                                    <small style="color:var(--text-muted)">📞 <?= clean($a['patient_phone']) ?> &bull; <?= clean($a['blood_group'] ?: 'N/A') ?></small>
                                </td>
                                <td>
                                    <strong style="display:block"><?= clean($a['doctor_name']) ?></strong>
                                    <small style="color:var(--text-muted)"><?= clean($a['specialization']) ?> &bull; <?= clean($a['hospital_name']) ?></small>
                                </td>
                                <td>
                                    <strong><?= date('M j, Y', strtotime($a['appointment_date'])) ?></strong>
                                    <small style="display:block;color:var(--primary)"><?= clean(format_time_slot($a['slot_time'])) ?></small>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                    <?php if ($a['status'] === 'rejected_by_hospital' && $a['hospital_rejection_reason']): ?>
                                        <small style="display:block;color:#b91c1c;max-width:180px"><?= clean($a['hospital_rejection_reason']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:.78rem;color:var(--text-muted)">
                                        Sent to: <?= clean($a['hospital_email']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <?php if ($a['status'] === 'pending_hospital_approval'): ?>
                                    <div style="display:inline-flex;gap:6px">
                                        <form method="POST" style="display:inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="confirm_appointment">
                                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return promptAppReject(this)">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject_appointment">
                                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                            <input type="hidden" name="rejection_reason" value="">
                                            <button type="submit" class="btn btn-outline btn-sm">Reject</button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <span style="font-size:.78rem;color:var(--text-muted)">Recorded</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($appointments)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No appointment records in system.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function promptAppReject(form) {
    var reason = prompt('Please enter the hospital rejection reason to email the patient:');
    if (!reason || !reason.trim()) return false;
    form.querySelector('input[name="rejection_reason"]').value = reason.trim();
    return true;
}
function openSidebar(){ document.querySelector(".adm-sidebar").classList.add("open"); document.getElementById("sidebarOverlay").classList.add("open"); }
function closeSidebar(){ document.querySelector(".adm-sidebar").classList.remove("open"); document.getElementById("sidebarOverlay").classList.remove("open"); }
</script>
</body>
</html>