<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();
$flash = get_flash();
$adminId = current_user_id();

// Handle Schedule Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $schedId = (int)($_POST['schedule_id'] ?? 0);

    if ($schedId > 0) {
        if ($action === 'approve_schedule') {
            // Get requested schedule details
            $stmt = $db->prepare("SELECT * FROM schedules WHERE id = ?");
            $stmt->execute([$schedId]);
            $req = $stmt->fetch();

            if ($req) {
                // Archive any previous active schedule for this doctor on this day
                $stmtArch = $db->prepare("
                    UPDATE schedules 
                    SET status = 'archived' 
                    WHERE doctor_id = ? AND day_of_week = ? AND status = 'active' AND id != ?
                ");
                $stmtArch->execute([$req['doctor_id'], $req['day_of_week'], $schedId]);

                // Approve and activate this schedule
                $stmtApp = $db->prepare("
                    UPDATE schedules 
                    SET status = 'active', approved_at = CURRENT_TIMESTAMP, approved_by_admin_id = ? 
                    WHERE id = ?
                ");
                $stmtApp->execute([$adminId, $schedId]);
                set_flash('success', 'Doctor schedule request approved and activated successfully.');
            }
        } elseif ($action === 'reject_schedule') {
            $reason = clean($_POST['reject_reason'] ?? 'Schedule conflicts with departmental staffing.');
            $stmtRej = $db->prepare("
                UPDATE schedules 
                SET status = 'rejected', change_reason = CONCAT(COALESCE(change_reason, ''), ' | Admin Note: ', ?), approved_at = CURRENT_TIMESTAMP, approved_by_admin_id = ?
                WHERE id = ?
            ");
            $stmtRej->execute([$reason, $adminId, $schedId]);
            set_flash('success', 'Doctor schedule change request rejected.');
        }
    }
    redirect('/admin/schedule_approvals.php');
}

// Fetch pending schedule requests
$pendingSchedules = $db->query("
    SELECT s.*,
           dp.name AS doctor_name, dp.specialization,
           h.name AS hospital_name,
           u.doctor_login_id
    FROM schedules s
    JOIN doctor_profiles dp ON dp.user_id = s.doctor_id
    JOIN users u ON u.id = s.doctor_id
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.status = 'pending_approval'
    ORDER BY s.requested_at ASC
")->fetchAll();

// Fetch currently active schedules
$activeSchedules = $db->query("
    SELECT s.*,
           dp.name AS doctor_name, dp.specialization,
           h.name AS hospital_name,
           u.doctor_login_id
    FROM schedules s
    JOIN doctor_profiles dp ON dp.user_id = s.doctor_id
    JOIN users u ON u.id = s.doctor_id
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.status = 'active'
    ORDER BY s.doctor_id ASC, CASE s.day_of_week
        WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7
    END ASC
")->fetchAll();

$pendingDocs = (int)$db->query("SELECT COUNT(*) FROM doctor_profiles WHERE verification_status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Approvals | HAMS Admin</title>
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
            <a class="active" href="schedule_approvals.php">
                <span class="nav-icon">📅</span>Schedule Approvals
                <?php if (count($pendingSchedules) > 0): ?><span class="adm-badge"><?= count($pendingSchedules) ?></span><?php endif; ?>
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
                <p style="color:var(--text-muted);font-size:.8rem">Doctor Consultation Hours</p>
                <h1 style="font-size:1.35rem;font-weight:800;color:var(--text-main)">Doctor Schedule Approvals</h1>
            </div>
        </header>

        <div class="adm-body">
            <?php if ($flash): ?>
            <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
                <?= clean($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Section 1: Pending Schedule Requests -->
            <div class="panel">
                <div class="panel-head" style="background:#fffbf0">
                    <div>
                        <h2 style="font-size:1.1rem;font-weight:700;color:#92661b">
                            ⏳ Pending Schedule Requests (<?= count($pendingSchedules) ?>)
                        </h2>
                        <small style="color:var(--text-muted)">Doctors must request admin approval when setting or modifying their once-a-day schedule.</small>
                    </div>
                </div>

                <div class="panel-body" style="overflow-x:auto">
                    <?php if (empty($pendingSchedules)): ?>
                        <div style="padding:36px 20px;text-align:center;color:var(--text-muted)">
                            <div style="font-size:2rem;margin-bottom:6px">✓</div>
                            <p>No pending schedule requests to review. All doctor schedules are up to date!</p>
                        </div>
                    <?php else: ?>
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Hospital</th>
                                <th>Requested Day &amp; Slot</th>
                                <th>Doctor's Reason</th>
                                <th>Requested Date</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingSchedules as $ps): ?>
                            <tr>
                                <td>
                                    <strong style="display:block;color:var(--text-main)"><?= clean($ps['doctor_name']) ?></strong>
                                    <small style="color:var(--text-muted)"><?= clean($ps['doctor_login_id']) ?> &bull; <?= clean($ps['specialization']) ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--primary-dark)"><?= clean($ps['hospital_name']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge-tag" style="background:#e0f2fe;color:#0369a1;font-weight:700">
                                        <?= clean($ps['day_of_week']) ?>: <?= clean(format_time_slot($ps['start_time'])) ?> – <?= clean(format_time_slot($ps['end_time'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:.82rem;color:var(--text-body);max-width:280px;display:inline-block">
                                        <?= clean($ps['change_reason'] ?: 'New consultation slot registration') ?>
                                    </span>
                                </td>
                                <td>
                                    <small style="color:var(--text-muted)"><?= human_time_diff($ps['requested_at']) ?></small>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;gap:6px">
                                        <form method="POST" style="display:inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve_schedule">
                                            <input type="hidden" name="schedule_id" value="<?= $ps['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return promptReject(this)">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject_schedule">
                                            <input type="hidden" name="schedule_id" value="<?= $ps['id'] ?>">
                                            <input type="hidden" name="reject_reason" id="rej_reason_<?= $ps['id'] ?>" value="">
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

            <!-- Section 2: Active Schedules Table -->
            <div class="panel">
                <div class="panel-head">
                    <div>
                        <h2 style="font-size:1.1rem;font-weight:700">Live Active Doctor Schedules (<?= count($activeSchedules) ?>)</h2>
                        <small style="color:var(--text-muted)">Consultation slots currently visible to patients booking appointments.</small>
                    </div>
                </div>

                <div class="panel-body" style="overflow-x:auto">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Hospital</th>
                                <th>Day of Week</th>
                                <th>Active Consultation Hours</th>
                                <th>Slot Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeSchedules as $as): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--text-main)"><?= clean($as['doctor_name']) ?></strong>
                                    <small style="display:block;color:var(--text-muted)"><?= clean($as['specialization']) ?></small>
                                </td>
                                <td><strong><?= clean($as['hospital_name']) ?></strong></td>
                                <td><span class="status-badge waiting"><?= clean($as['day_of_week']) ?></span></td>
                                <td>
                                    <strong style="color:var(--primary)">
                                        <?= clean(format_time_slot($as['start_time'])) ?> – <?= clean(format_time_slot($as['end_time'])) ?>
                                    </strong>
                                </td>
                                <td><?= (int)$as['slot_duration_minutes'] ?> min slots</td>
                                <td><span class="status-badge done">Active ✓</span></td>
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
function promptReject(form) {
    var reason = prompt('Please enter reason for rejecting this schedule request:');
    if (!reason || !reason.trim()) return false;
    form.querySelector('input[name="reject_reason"]').value = reason.trim();
    return true;
}
function openSidebar(){ document.querySelector(".adm-sidebar").classList.add("open"); document.getElementById("sidebarOverlay").classList.add("open"); }
function closeSidebar(){ document.querySelector(".adm-sidebar").classList.remove("open"); document.getElementById("sidebarOverlay").classList.remove("open"); }
</script>
</body>
</html>