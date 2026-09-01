<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_login(['doctor']);

$db  = getDB();
$uid = current_user_id();

// Fetch doctor details
$stmt = $db->prepare("
    SELECT u.doctor_login_id, u.email,
           d.name, d.specialization, d.license_no,
           d.qualification, d.experience_years, d.image_path,
           d.verification_status
    FROM users u
    LEFT JOIN doctor_profiles d ON u.id = d.user_id
    WHERE u.id = ?
");
$stmt->execute([$uid]);
$doc = $stmt->fetch();

$docName  = $doc['name']            ?? 'Doctor';
$loginId  = $doc['doctor_login_id'] ?? 'DOC-XXXX';
$spec     = $doc['specialization']  ?? 'Specialist';
$qual     = $doc['qualification']   ?? 'MBBS, MD';
$exp      = (int)($doc['experience_years'] ?? 0);
$license  = $doc['license_no']      ?? 'N/A';
$rawInit  = preg_replace('/^Dr\.?\s*/i', '', $docName);
$initials = strtoupper(mb_substr(trim($rawInit), 0, 2)) ?: 'DR';

$flash = get_flash();

// Handle Schedule Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'submit_schedule_request') {
        $hospId    = (int)($_POST['hospital_id'] ?? 0);
        $dayOfWeek = clean($_POST['day_of_week'] ?? '');
        $startTime = clean($_POST['start_time'] ?? '');
        $endTime   = clean($_POST['end_time'] ?? '');
        $reason    = clean($_POST['reason'] ?? '');
        $slotMins  = (int)($_POST['slot_duration'] ?? 15);

        if (!$hospId || !$dayOfWeek || !$startTime || !$endTime || !$reason) {
            set_flash('error', 'Hospital, day of week, start time, end time, and reason for change are required.');
        } else {
            // Check if there is already a pending request for this day
            $checkPending = $db->prepare("SELECT 1 FROM schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 'pending_approval'");
            $checkPending->execute([$uid, $dayOfWeek]);
            if ($checkPending->fetch()) {
                set_flash('error', "You already have a pending schedule request for $dayOfWeek awaiting admin approval.");
            } else {
                $stmtIns = $db->prepare("
                    INSERT INTO schedules (doctor_id, hospital_id, day_of_week, start_time, end_time, slot_duration_minutes, status, change_reason, requested_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending_approval', ?, CURRENT_TIMESTAMP)
                ");
                $stmtIns->execute([$uid, $hospId, $dayOfWeek, $startTime, $endTime, $slotMins, $reason]);
                set_flash('success', "Schedule request for $dayOfWeek submitted successfully. It will become active once reviewed by the administrator.");
            }
        }
        redirect('/doctor/dashboard.php');
    }
}

// Fetch Affiliated Hospitals
$affiliatedHospitals = $db->prepare("
    SELECT h.id, h.name, h.address, h.phone, dh.status, dh.join_date
    FROM doctor_hospital dh
    JOIN hospitals h ON h.id = dh.hospital_id
    WHERE dh.doctor_id = ? AND dh.status = 'active'
    ORDER BY h.name ASC
");
$affiliatedHospitals->execute([$uid]);
$myHospitals = $affiliatedHospitals->fetchAll();

// Fetch Doctor Schedules (Active & Pending)
$stmtSchedules = $db->prepare("
    SELECT s.*, h.name AS hospital_name
    FROM schedules s
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.doctor_id = ? AND s.status IN ('active', 'pending_approval', 'rejected')
    ORDER BY CASE s.day_of_week
        WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 WHEN 'Sunday' THEN 7
    END ASC
");
$stmtSchedules->execute([$uid]);
$mySchedules = $stmtSchedules->fetchAll();

// Fetch Appointments Queue
$stmtQueue = $db->prepare("
    SELECT a.*,
           p.name AS patient_name, p.gender, p.phone, p.date_of_birth, p.blood_group,
           h.name AS hospital_name
    FROM appointments a
    JOIN patient_profiles p ON a.patient_id = p.user_id
    JOIN hospitals h ON a.hospital_id = h.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date ASC, a.slot_time ASC
");
$stmtQueue->execute([$uid]);
$queue = $stmtQueue->fetchAll();

$confirmedCount = count(array_filter($queue, fn($r) => in_array($r['status'], ['confirmed', 'in_consultation'])));
$completedCount = count(array_filter($queue, fn($r) => $r['status'] === 'completed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Desk | HAMS Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,43,34,0.6);backdrop-filter:blur(4px);z-index:100;place-items:center;padding:20px}
        .modal-overlay.open{display:grid}
        .modal-box{background:#fff;border-radius:16px;width:min(100%,540px);box-shadow:0 20px 40px rgba(0,0,0,0.2);overflow:hidden}
        .modal-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--primary-surface)}
        .modal-body{padding:24px;display:grid;gap:14px}
    </style>
</head>
<body class="portal-page">

<!-- Topbar -->
<header class="portal-topbar">
    <div class="portal-topbar-container">
        <a class="brand" href="dashboard.php">
            <span class="brand-icon">✚</span>
            <span class="brand-text">HAMS<span class="brand-sub">Doctor Desk</span></span>
        </a>
        <div style="display:flex;align-items:center;gap:14px">
            <div class="portal-user-chip">
                <div class="portal-avatar"><?= clean($initials) ?></div>
                <div class="portal-user-info">
                    <span class="portal-user-name"><?= clean($docName) ?></span>
                    <span class="portal-user-role"><?= clean($loginId) ?> &bull; <?= clean($spec) ?></span>
                </div>
            </div>
            <a class="btn btn-outline btn-sm" href="../logout.php">Sign Out</a>
        </div>
    </div>
</header>

<main class="portal-main">
<div class="container wide">

    <?php if ($flash): ?>
    <div class="<?= $flash['type']==='error'?'error-message':'success-message' ?>" style="margin-bottom:20px">
        <?= clean($flash['message']) ?>
    </div>
    <?php endif; ?>

    <!-- Doctor Hero Card -->
    <section class="doctor-hero-profile" style="margin-bottom:28px">
        <div class="doctor-profile-block">
            <?php if (!empty($doc['image_path']) && file_exists(__DIR__.'/../'.$doc['image_path'])): ?>
                <img src="../<?= clean($doc['image_path']) ?>" alt="<?= clean($docName) ?>" class="doctor-avatar-lg" style="object-fit:cover">
            <?php else: ?>
                <div class="doctor-avatar-lg"><?= clean($initials) ?></div>
            <?php endif; ?>
            <div>
                <h1 style="font-size:1.5rem;margin-bottom:4px"><?= clean($docName) ?></h1>
                <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:6px">
                    <?= clean($qual) ?> &bull; <?= $exp ?> years experience &bull; <?= clean($spec) ?>
                </p>
                <div class="doctor-meta-tags">
                    <span class="badge-tag verified">Verified Specialist ✓</span>
                    <span class="badge-tag license">Lic: <?= clean($license) ?></span>
                    <span class="badge-tag" style="background:var(--bg);color:var(--text-body)"><?= clean($loginId) ?></span>
                </div>
            </div>
        </div>

        <div style="text-align:right">
            <button class="btn btn-primary btn-sm" onclick="openScheduleModal()" <?= empty($myHospitals) ? 'disabled' : '' ?>>
                📅&nbsp;Request / Modify Schedule
            </button>
            <?php if (empty($myHospitals)): ?>
                <small style="display:block;color:#b91c1c;margin-top:4px">Awaiting hospital assignment by Admin</small>
            <?php endif; ?>
        </div>
    </section>

    <!-- Affiliated Hospitals Strip -->
    <section class="dashboard-card" style="margin-bottom:28px">
        <h2 class="dashboard-card-title">My Affiliated Medical Facilities</h2>
        <?php if (empty($myHospitals)): ?>
            <p style="color:var(--text-muted);font-size:.88rem">You are not currently affiliated with any partner hospital. The administrator will assign your facility soon.</p>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
                <?php foreach ($myHospitals as $h): ?>
                <div style="padding:14px 18px;background:var(--primary-surface);border:1px solid var(--border-accent);border-radius:10px">
                    <strong style="display:block;color:var(--primary-dark);font-size:.95rem">🏥 <?= clean($h['name']) ?></strong>
                    <small style="display:block;color:var(--text-muted);margin:4px 0">📍 <?= clean($h['address']) ?></small>
                    <span class="status-badge done" style="font-size:.72rem">Active Practitioner ✓</span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- My Weekly Consultation Schedules -->
    <section class="dashboard-card" style="margin-bottom:28px">
        <div class="section-head flex-between" style="margin-bottom:16px">
            <div>
                <span class="section-tag">Consultation Hours</span>
                <h2 class="section-title" style="font-size:1.3rem">My Weekly Schedule Slots</h2>
            </div>
            <button class="btn btn-outline btn-sm" onclick="openScheduleModal()">+ Change Schedule</button>
        </div>

        <?php if (empty($mySchedules)): ?>
            <div style="text-align:center;padding:32px;background:var(--bg);border:1px dashed var(--border-hover);border-radius:12px">
                <div style="font-size:2rem;margin-bottom:8px">📅</div>
                <h3 style="font-size:1rem;margin-bottom:4px">No Schedule Configured</h3>
                <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:14px">Submit your consultation hours so patients can book appointments with you.</p>
                <button class="btn btn-primary btn-sm" onclick="openScheduleModal()">Set Schedule Now</button>
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Day of Week</th>
                            <th>Hospital</th>
                            <th>Consultation Hours</th>
                            <th>Slot Duration</th>
                            <th>Status / Admin Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mySchedules as $s): 
                            $badgeClass = match($s['status']) {
                                'active' => 'done',
                                'pending_approval' => 'waiting',
                                'rejected' => 'rejected',
                                default => 'waiting'
                            };
                            $badgeLabel = match($s['status']) {
                                'active' => 'Active ✓',
                                'pending_approval' => 'Pending Admin Approval ⏳',
                                'rejected' => 'Rejected ✕',
                                default => ucfirst(clean($s['status']))
                            };
                        ?>
                        <tr>
                            <td><strong style="color:var(--text-main)"><?= clean($s['day_of_week']) ?></strong></td>
                            <td><?= clean($s['hospital_name']) ?></td>
                            <td>
                                <strong style="color:var(--primary)">
                                    <?= clean(format_time_slot($s['start_time'])) ?> – <?= clean(format_time_slot($s['end_time'])) ?>
                                </strong>
                            </td>
                            <td><?= (int)$s['slot_duration_minutes'] ?> mins</td>
                            <td>
                                <span class="status-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                <?php if ($s['status'] === 'pending_approval'): ?>
                                    <small style="display:block;color:var(--text-muted);margin-top:2px">Reason: <?= clean($s['change_reason'] ?: '') ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Patient Consultation Queue -->
    <section class="dashboard-card" style="margin-bottom:48px">
        <div class="section-head flex-between" style="margin-bottom:18px">
            <div>
                <span class="section-tag">Patient Queue</span>
                <h2 class="section-title" style="font-size:1.3rem">Upcoming Appointments &amp; Patients</h2>
            </div>
            <span style="font-size:.84rem;color:var(--text-muted)"><?= $confirmedCount ?> confirmed consultation<?= $confirmedCount !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($queue)): ?>
            <div style="text-align:center;padding:32px;background:var(--bg);border:1px dashed var(--border-hover);border-radius:12px">
                <p style="color:var(--text-muted);font-size:.88rem">No patient appointments booked yet.</p>
            </div>
        <?php else: ?>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Patient Name</th>
                            <th>Hospital</th>
                            <th>Date &amp; Time</th>
                            <th>Contact</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queue as $q): 
                            $qBadge = match($q['status']) {
                                'confirmed' => 'done',
                                'in_consultation' => 'consulting',
                                'completed' => 'done',
                                default => 'waiting'
                            };
                        ?>
                        <tr>
                            <td><strong style="color:var(--primary-dark)"><?= clean($q['appointment_token']) ?></strong></td>
                            <td>
                                <strong><?= clean($q['patient_name']) ?></strong>
                                <small style="display:block;color:var(--text-muted)"><?= ucfirst(clean($q['gender'])) ?> &bull; Blood: <?= clean($q['blood_group'] ?: 'N/A') ?></small>
                            </td>
                            <td><?= clean($q['hospital_name']) ?></td>
                            <td>
                                <strong><?= date('M j, Y', strtotime($q['appointment_date'])) ?></strong>
                                <small style="display:block;color:var(--primary)"><?= clean(format_time_slot($q['slot_time'])) ?></small>
                            </td>
                            <td><a href="tel:<?= clean(str_replace(' ','',$q['phone'])) ?>" style="color:var(--primary)"><?= clean($q['phone']) ?></a></td>
                            <td><span class="status-badge <?= $qBadge ?>"><?= ucfirst(clean($q['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
</main>

<!-- Modal: Request / Change Schedule -->
<div class="modal-overlay" id="scheduleModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 style="font-size:1.1rem;font-weight:700">Submit Schedule Change Request</h3>
            <button onclick="closeScheduleModal()" style="border:none;background:none;font-size:1.2rem;cursor:pointer">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_schedule_request">
            <div class="modal-body">
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Affiliated Hospital *</label>
                    <select name="hospital_id" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                        <?php foreach ($myHospitals as $mh): ?>
                            <option value="<?= $mh['id'] ?>"><?= clean($mh['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Day of Week *</label>
                    <select name="day_of_week" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Start Time *</label>
                        <input type="time" name="start_time" required value="14:00" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                    </div>
                    <div>
                        <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">End Time *</label>
                        <input type="time" name="end_time" required value="18:00" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                    </div>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Consultation Duration</label>
                    <select name="slot_duration" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                        <option value="15">15 Minutes</option>
                        <option value="20">20 Minutes</option>
                        <option value="30" selected>30 Minutes</option>
                        <option value="45">45 Minutes</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Reason for Setting / Changing Schedule *</label>
                    <textarea name="reason" rows="2" required placeholder="Explain reason for this schedule request (e.g. Clinic hours update, patient demand)..." style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeScheduleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Submit Request to Admin</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openScheduleModal() { document.getElementById('scheduleModal').classList.add('open'); }
function closeScheduleModal() { document.getElementById('scheduleModal').classList.remove('open'); }
</script>
</body>
</html>