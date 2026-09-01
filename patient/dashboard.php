<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/db.php';
require_login(['patient']);

$db  = getDB();
$uid = current_user_id();

// Patient Profile
$stmt = $db->prepare("
    SELECT u.email, u.created_at,
           p.name, p.phone, p.date_of_birth, p.gender,
           p.blood_group, p.address,
           p.emergency_contact_name, p.emergency_contact_phone
    FROM users u
    LEFT JOIN patient_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$uid]);
$pat = $stmt->fetch();

$name     = $pat['name']       ?? 'Patient';
$initials = strtoupper(mb_substr(trim($name), 0, 2)) ?: 'PT';
$blood    = $pat['blood_group'] ? clean($pat['blood_group']) : null;
$dob      = $pat['date_of_birth'] ? date('M j, Y', strtotime($pat['date_of_birth'])) : null;
$gender   = $pat['gender']      ? ucfirst($pat['gender']) : null;
$phone    = $pat['phone']       ?? null;
$ecName   = $pat['emergency_contact_name']  ?? null;
$ecPhone  = $pat['emergency_contact_phone'] ?? null;
$email    = $pat['email']       ?? '';
$joined   = $pat['created_at']  ? date('M Y', strtotime($pat['created_at'])) : '—';

$flash = get_flash();

// Handle Appointment Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'book_appointment') {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $hospId   = (int)($_POST['hospital_id'] ?? 0);
        $schedId  = (int)($_POST['schedule_id'] ?? 0);
        $appDate  = clean($_POST['appointment_date'] ?? '');
        $slotTime = clean($_POST['slot_time'] ?? '');
        $reason   = clean($_POST['reason'] ?? 'General Consultation');

        if (!$doctorId || !$hospId || !$appDate || !$slotTime) {
            set_flash('error', 'Please select a doctor, hospital, appointment date, and time slot.');
        } else {
            // Generate unique Token
            $token = generate_appointment_token($db);

            // Insert appointment
            $stmtIns = $db->prepare("
                INSERT INTO appointments (appointment_token, patient_id, doctor_id, hospital_id, schedule_id, appointment_date, slot_time, reason, status, hospital_notified_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_hospital_approval', CURRENT_TIMESTAMP)
            ");
            $stmtIns->execute([$token, $uid, $doctorId, $hospId, $schedId ?: null, $appDate, $slotTime, $reason]);

            // Fetch hospital and doctor details for email
            $hosp = $db->prepare("SELECT name, email, phone FROM hospitals WHERE id = ?");
            $hosp->execute([$hospId]);
            $hospData = $hosp->fetch();

            $doc = $db->prepare("SELECT name, specialization FROM doctor_profiles WHERE user_id = ?");
            $doc->execute([$doctorId]);
            $docData = $doc->fetch();

            // Dispatch Email to Hospital Administration
            if (!empty($hospData['email'])) {
                send_appointment_request_to_hospital($hospData['email'], [
                    'token'             => $token,
                    'patient_name'      => $name,
                    'patient_phone'     => $phone ?: 'Not provided',
                    'patient_gender'    => $gender ?: 'N/A',
                    'patient_dob'       => $dob ?: 'N/A',
                    'blood_group'       => $blood ?: 'N/A',
                    'emergency_contact' => $ecName ? ($ecName . ' (' . $ecPhone . ')') : 'N/A',
                    'doctor_name'       => $docData['name'] ?? 'Doctor',
                    'specialization'    => $docData['specialization'] ?? '',
                    'appointment_date'  => date('l, M j, Y', strtotime($appDate)),
                    'slot_time'         => format_time_slot($slotTime),
                    'reason'            => $reason,
                ]);
            }

            set_flash('success', "Appointment request #$token submitted! An email notification has been dispatched to {$hospData['name']}. You will receive a confirmation once reviewed by the hospital.");
            redirect('/patient/dashboard.php');
        }
    }
}

// Fetch Active Appointment for this patient
$stmtApp = $db->prepare("
    SELECT a.*, dp.name AS doctor_name, dp.specialization,
           h.name AS hospital_name, h.address AS hospital_address, h.phone AS hospital_phone, h.email AS hospital_email
    FROM appointments a
    JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
    JOIN hospitals h ON h.id = a.hospital_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.slot_time DESC
    LIMIT 1
");
$stmtApp->execute([$uid]);
$activeApp = $stmtApp->fetch();

// Fetch Verified Doctors with their active hospital affiliations and active schedules
$docs = $db->query("
    SELECT d.user_id, d.name, d.specialization, d.qualification, d.experience_years, d.image_path,
           h.id AS hospital_id, h.name AS hospital_name, h.phone AS hospital_phone
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN doctor_hospital dh ON dh.doctor_id = d.user_id AND dh.status = 'active'
    LEFT JOIN hospitals h ON h.id = dh.hospital_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    ORDER BY d.experience_years DESC, d.name ASC
")->fetchAll();

// Fetch Active Schedules for booking dropdowns
$schedules = $db->query("
    SELECT s.*, h.name AS hospital_name
    FROM schedules s
    JOIN hospitals h ON h.id = s.hospital_id
    WHERE s.status = 'active'
    ORDER BY s.doctor_id, s.start_time ASC
")->fetchAll();

$schedulesByDoc = [];
foreach ($schedules as $s) {
    $schedulesByDoc[$s['doctor_id']][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal | HAMS Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        .profile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-top:16px}
        .pinfo{background:rgba(255,255,255,0.16);border-radius:10px;padding:10px 14px;backdrop-filter:blur(4px)}
        .pinfo small{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.72;margin-bottom:3px}
        .pinfo span{font-weight:700;font-size:.92rem}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,43,34,0.6);backdrop-filter:blur(4px);z-index:100;place-items:center;padding:20px}
        .modal-overlay.open{display:grid}
        .modal-box{background:#fff;border-radius:16px;width:min(100%,540px);box-shadow:0 20px 40px rgba(0,0,0,0.2);overflow:hidden}
        .modal-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--primary-surface)}
        .modal-body{padding:24px;display:grid;gap:14px}
    </style>
</head>
<body class="portal-page">

<!-- Emergency Strip -->
<aside class="emergency-strip">
    <div class="container wide">
        <span class="emergency-pill">🚨 <strong>24/7 Emergency:</strong> <a class="emergency-phone" href="tel:102">Ambulance — Dial 102</a></span>
        <span>Reception Helpline: <a class="emergency-phone" href="tel:+97714200000">+977 1 4200000</a></span>
    </div>
</aside>

<!-- Topbar -->
<header class="portal-topbar">
    <div class="portal-topbar-container">
        <a class="brand" href="dashboard.php">
            <span class="brand-icon">✚</span>
            <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
        </a>
        <div style="display:flex;align-items:center;gap:12px">
            <div class="portal-user-chip">
                <div class="portal-avatar"><?= clean($initials) ?></div>
                <div class="portal-user-info">
                    <span class="portal-user-name"><?= clean($name) ?></span>
                    <span class="portal-user-role">Patient Account</span>
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

    <!-- Welcome Hero -->
    <section class="welcome-hero">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
            <div>
                <h1>Hello, <?= clean($name) ?> 👋</h1>
                <p style="margin-top:6px">Schedule appointments with verified specialists across partner hospitals in Kathmandu.</p>
            </div>
            <?php if ($blood): ?>
            <div style="text-align:center;flex-shrink:0">
                <div style="font-size:.7rem;opacity:.75;margin-bottom:4px;font-weight:700;text-transform:uppercase">Blood Group</div>
                <div style="font-size:2rem;font-weight:800;background:rgba(255,255,255,0.2);padding:6px 18px;border-radius:10px"><?= $blood ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="profile-grid">
            <?php if ($dob): ?><div class="pinfo"><small>Date of Birth</small><span><?= clean($dob) ?></span></div><?php endif; ?>
            <?php if ($gender): ?><div class="pinfo"><small>Gender</small><span><?= clean($gender) ?></span></div><?php endif; ?>
            <?php if ($phone): ?><div class="pinfo"><small>Phone</small><span><?= clean($phone) ?></span></div><?php endif; ?>
            <div class="pinfo"><small>Member Since</small><span><?= clean($joined) ?></span></div>
        </div>

        <div class="quick-search-box" style="margin-top:20px">
            <span style="font-size:1.1rem;margin-right:6px">🔍</span>
            <input type="text" id="docSearch" placeholder="Search specialist by name, specialty or hospital...">
        </div>
    </section>

    <!-- 2-column Grid -->
    <div class="portal-grid-3" style="margin-bottom:32px">

        <!-- Left: Appointment Status Card -->
        <section class="dashboard-card">
            <h2 class="dashboard-card-title">Your Appointment Status</h2>

            <?php if ($activeApp): 
                $appDate = date('D, M j, Y', strtotime($activeApp['appointment_date']));
                $appTime = format_time_slot($activeApp['slot_time']);
                $tokenNo = $activeApp['appointment_token'];
                $stClass = match($activeApp['status']) {
                    'confirmed' => 'done',
                    'rejected_by_hospital' => 'rejected',
                    default => 'waiting'
                };
                $stLabel = match($activeApp['status']) {
                    'confirmed' => 'Confirmed ✓',
                    'rejected_by_hospital' => 'Rejected ✕',
                    'pending_hospital_approval' => 'Pending Hospital Email Approval ⏳',
                    default => ucfirst(clean($activeApp['status']))
                };
            ?>
                <div style="background:var(--primary-surface);border:1px solid var(--border-accent);border-radius:12px;padding:20px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                        <strong style="font-size:1.15rem;color:var(--primary-dark)">#<?= clean($tokenNo) ?></strong>
                        <span class="status-badge <?= $stClass ?>"><?= $stLabel ?></span>
                    </div>

                    <h3 style="font-size:1.1rem;margin-bottom:4px"><?= clean($activeApp['doctor_name']) ?></h3>
                    <p style="font-size:.86rem;color:var(--text-muted);margin-bottom:12px">
                        <?= clean($activeApp['specialization']) ?> &bull; 🏥 <?= clean($activeApp['hospital_name']) ?>
                    </p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:10px;background:#fff;border-radius:8px;border:1px solid var(--border);margin-bottom:12px">
                        <div>
                            <small style="font-size:.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase">Date</small>
                            <strong style="display:block;font-size:.86rem"><?= clean($appDate) ?></strong>
                        </div>
                        <div>
                            <small style="font-size:.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase">Slot</small>
                            <strong style="display:block;font-size:.86rem;color:var(--primary)"><?= clean($appTime) ?></strong>
                        </div>
                    </div>

                    <?php if ($activeApp['status'] === 'pending_hospital_approval'): ?>
                        <p style="font-size:.8rem;color:var(--text-muted);background:rgba(255,255,255,0.7);padding:8px 10px;border-radius:6px">
                            📧 Notification dispatched to <strong><?= clean($activeApp['hospital_name']) ?></strong>. They will verify room availability and confirm.
                        </p>
                    <?php elseif ($activeApp['status'] === 'rejected_by_hospital'): ?>
                        <p style="font-size:.82rem;color:#b91c1c;background:#fee2e2;padding:8px 10px;border-radius:6px">
                            Reason: <?= clean($activeApp['hospital_rejection_reason'] ?: 'Slot unavailable') ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background:var(--bg);border:1px dashed var(--border-hover);border-radius:12px;padding:32px 20px;text-align:center">
                    <div style="font-size:2.2rem;margin-bottom:8px">📋</div>
                    <h3 style="font-size:1rem;margin-bottom:4px">No Active Appointments</h3>
                    <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:14px">Browse our verified doctors below to request a consultation slot.</p>
                    <a class="btn btn-primary btn-sm" href="#doctorSection">Browse Specialists</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Right: Quick Actions -->
        <section class="dashboard-card">
            <h2 class="dashboard-card-title">Quick Actions</h2>
            <div class="quick-actions-grid">
                <a class="action-card-btn" href="#doctorSection" style="text-decoration:none;color:inherit">
                    <span class="action-icon">🩺</span>
                    <strong>Book Doctor</strong>
                    <span>Select date &amp; slot</span>
                </a>
                <a class="action-card-btn" href="tel:102" style="text-decoration:none;color:inherit">
                    <span class="action-icon">🚑</span>
                    <strong>Ambulance</strong>
                    <span>Dial 102 — Free</span>
                </a>
            </div>
            <div style="margin-top:16px;padding:14px;background:var(--primary-surface);border:1px solid var(--border-accent);border-radius:10px;font-size:.84rem">
                <strong style="color:var(--primary-dark);display:block;margin-bottom:4px">Connected Account</strong>
                <span style="color:var(--text-muted)"><?= clean($email) ?></span>
            </div>
        </section>

    </div>

    <!-- Available Specialists -->
    <section id="doctorSection" class="dashboard-card" style="margin-bottom:48px">
        <div class="section-head flex-between" style="margin-bottom:20px">
            <div>
                <span class="section-tag">Verified Specialists</span>
                <h2 class="section-title" style="font-size:1.4rem">Available Doctors &amp; Consultation Hours</h2>
            </div>
            <span style="font-size:.84rem;color:var(--text-muted)"><?= count($docs) ?> specialist<?= count($docs) !== 1 ? 's' : '' ?> available</span>
        </div>

        <div class="cards-grid doctors-grid" id="doctorGrid">
            <?php foreach ($docs as $d): 
                $av = strtoupper(mb_substr(trim(preg_replace('/^Dr\.?\s*/i', '', $d['name'])), 0, 2)) ?: 'DR';
                $docSchedules = $schedulesByDoc[$d['user_id']] ?? [];
            ?>
            <article class="modern-card doctor-item"
                     data-name="<?= clean(strtolower($d['name'])) ?>"
                     data-spec="<?= clean(strtolower($d['specialization'])) ?>"
                     data-hosp="<?= clean(strtolower($d['hospital_name'] ?? '')) ?>">
                <div class="doctor-card-top">
                    <?php if (!empty($d['image_path']) && file_exists(__DIR__ . '/../' . $d['image_path'])): ?>
                        <img src="../<?= clean($d['image_path']) ?>" alt="<?= clean($d['name']) ?>" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
                    <?php else: ?>
                        <div class="doctor-avatar-circle"><?= clean($av) ?></div>
                    <?php endif; ?>
                    <div>
                        <h3 class="doctor-name"><?= clean($d['name']) ?></h3>
                        <span class="dept-tag"><?= clean($d['specialization']) ?></span>
                        <p class="hospital-sub"><?= clean($d['hospital_name'] ?: 'Partner Hospital') ?> &bull; <?= (int)$d['experience_years'] ?> yrs exp</p>
                    </div>
                </div>

                <div class="slots-box" style="margin-bottom:14px">
                    <span class="slots-header">Available Weekly Hours:</span>
                    <div class="slot-list">
                        <?php if (empty($docSchedules)): ?>
                            <span class="time-pill">Hours on request</span>
                        <?php else: ?>
                            <?php foreach (array_slice($docSchedules, 0, 2) as $ds): ?>
                                <span class="time-pill"><?= clean($ds['day_of_week']) ?>: <?= clean(format_time_slot($ds['start_time'])) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-primary btn-full"
                        onclick='openBookingModal(<?= json_encode($d) ?>, <?= json_encode($docSchedules) ?>)'>
                    Book Appointment
                </button>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

</div>
</main>

<!-- Modal: Book Appointment -->
<div class="modal-overlay" id="bookModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 style="font-size:1.1rem;font-weight:700">Book Specialist Consultation</h3>
            <button onclick="closeBookingModal()" style="border:none;background:none;font-size:1.2rem;cursor:pointer">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="book_appointment">
            <input type="hidden" name="doctor_id" id="modal_doctor_id">
            <input type="hidden" name="hospital_id" id="modal_hospital_id">
            <input type="hidden" name="schedule_id" id="modal_schedule_id">

            <div class="modal-body">
                <div style="padding:10px 14px;background:var(--primary-surface);border-radius:8px;border:1px solid var(--border-accent)">
                    <strong id="modal_doc_name" style="font-size:.95rem;color:var(--primary-dark)">Doctor Name</strong>
                    <small id="modal_hosp_name" style="display:block;color:var(--text-muted)">Hospital Name</small>
                </div>

                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Select Consultation Date *</label>
                    <input type="date" name="appointment_date" id="modal_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                </div>

                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Select Available Time Slot *</label>
                    <select name="slot_time" id="modal_slot_select" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
                        <option value="14:30:00">02:30 PM</option>
                        <option value="15:00:00">03:00 PM</option>
                        <option value="15:30:00">03:30 PM</option>
                        <option value="16:00:00">04:00 PM</option>
                        <option value="16:30:00">04:30 PM</option>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:.8rem;font-weight:700;margin-bottom:4px">Reason for Consultation / Symptoms</label>
                    <textarea name="reason" rows="2" placeholder="Briefly describe your symptoms or visit reason..." style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px"></textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closeBookingModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Confirm &amp; Send to Hospital</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openBookingModal(doc, schedules) {
    document.getElementById('modal_doctor_id').value = doc.user_id;
    document.getElementById('modal_hospital_id').value = doc.hospital_id || 1;
    document.getElementById('modal_doc_name').textContent = doc.name + ' (' + doc.specialization + ')';
    document.getElementById('modal_hosp_name').textContent = '🏥 ' + (doc.hospital_name || 'Partner Hospital');

    var slotSelect = document.getElementById('modal_slot_select');
    slotSelect.innerHTML = '';

    if (schedules && schedules.length > 0) {
        document.getElementById('modal_schedule_id').value = schedules[0].id;
        schedules.forEach(function(s) {
            var opt = document.createElement('option');
            opt.value = s.start_time;
            opt.textContent = s.day_of_week + ' - ' + s.start_time;
            slotSelect.appendChild(opt);
        });
    } else {
        var defaultSlots = ['14:00:00', '14:30:00', '15:00:00', '15:30:00', '16:00:00'];
        defaultSlots.forEach(function(st) {
            var opt = document.createElement('option');
            opt.value = st;
            opt.textContent = st;
            slotSelect.appendChild(opt);
        });
    }

    document.getElementById('bookModal').classList.add('open');
}

function closeBookingModal() {
    document.getElementById('bookModal').classList.remove('open');
}

var searchBox = document.getElementById('docSearch');
if (searchBox) {
    searchBox.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('#doctorGrid .doctor-item').forEach(function (c) {
            var t = (c.dataset.name || '') + ' ' + (c.dataset.spec || '') + ' ' + (c.dataset.hosp || '');
            c.style.display = (!q || t.includes(q)) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>