<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login(['doctor']);

$db  = getDB();
$uid = current_user_id();

$stmt = $db->prepare(
    "SELECT u.doctor_login_id, u.email,
            d.name, d.specialization, d.license_no,
            d.qualification, d.experience_years, d.image_path,
            d.verification_status,
            h.name AS hospital_name
     FROM users u
     LEFT JOIN doctor_profiles d ON u.id = d.user_id
     LEFT JOIN doctor_hospital dh ON dh.doctor_id = d.user_id AND dh.status = 'active'
     LEFT JOIN hospitals h ON h.id = dh.hospital_id
     WHERE u.id = ?"
);
$stmt->execute([$uid]);
$doc = $stmt->fetch();

$docName  = $doc['name']            ?? 'Doctor';
$loginId  = $doc['doctor_login_id'] ?? 'DOC-XXXX';
$spec     = $doc['specialization']  ?? 'Specialist';
$qual     = $doc['qualification']   ?? 'MBBS, MD';
$exp      = (int)($doc['experience_years'] ?? 0);
$license  = $doc['license_no']      ?? 'N/A';
$hospName = $doc['hospital_name']   ?? 'Accredited Hospital';
$rawInit  = preg_replace('/^Dr\.?\s*/i', '', $docName);
$initials = strtoupper(mb_substr(trim($rawInit), 0, 2)) ?: 'DR';

$flash = get_flash();

// Query real appointments for this doctor from database
$stmtQueue = $db->prepare("
    SELECT a.id, a.appointment_token, a.slot_time, a.appointment_date, a.status, a.reason,
           p.name AS patient_name, p.gender, p.phone, p.date_of_birth
    FROM appointments a
    JOIN patient_profiles p ON a.patient_id = p.user_id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date ASC, a.slot_time ASC
");
$stmtQueue->execute([$uid]);
$queue = $stmtQueue->fetchAll();

$done    = count(array_filter($queue, fn($r) => $r['status'] === 'completed'));
$waiting = count(array_filter($queue, fn($r) => in_array($r['status'], ['pending', 'confirmed', 'in_consultation'])));
$total   = count($queue);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Desk | HAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        .stat-color-warn { color: var(--warning) !important; }
        .stat-color-ok   { color: var(--success) !important; }
        .stat-color-pri  { color: var(--primary) !important; }
        .row-current td  { background: var(--primary-surface) !important; }
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

    <!-- ===== DOCTOR PROFILE CARD ===== -->
    <section class="doctor-hero-profile" style="margin-bottom:28px">
        <div class="doctor-profile-block">
            <?php if (!empty($doc['image_path']) && file_exists(__DIR__.'/../'.$doc['image_path'])): ?>
                <img src="../<?= clean($doc['image_path']) ?>" alt="<?= clean($docName) ?>"
                     class="doctor-avatar-lg" style="object-fit:cover">
            <?php else: ?>
                <div class="doctor-avatar-lg"><?= clean($initials) ?></div>
            <?php endif; ?>
            <div>
                <h1 style="font-size:1.5rem;margin-bottom:4px"><?= clean($docName) ?></h1>
                <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:6px">
                    <?= clean($qual) ?> &bull; <?= $exp ?> years clinical experience &bull; <?= clean($hospName) ?>
                </p>
                <div class="doctor-meta-tags">
                    <span class="badge-tag verified">Verified ✓</span>
                    <span class="badge-tag license">License: <?= clean($license) ?></span>
                    <span class="badge-tag" style="background:var(--bg);color:var(--text-body)"><?= clean($loginId) ?></span>
                    <span class="badge-tag" style="background:#e0f2fe;color:#0369a1"><?= clean($spec) ?></span>
                </div>
            </div>
        </div>

        <!-- Practice status toggle -->
        <div class="status-toggle-box" id="statusBox" onclick="toggleStatus()" style="cursor:pointer;user-select:none"
             title="Click to toggle practice status">
            <span class="pulse-dot" id="statusDot"></span>
            <span id="statusText">Accepting Patients (Available)</span>
        </div>
    </section>

    <!-- ===== METRICS ===== -->
    <section class="doctor-metrics-row" style="margin-bottom:30px">
        <article class="metric-card">
            <div class="metric-card-label">Upcoming Visits</div>
            <div class="metric-card-num"><?= $total ?></div>
            <div class="metric-card-sub">appointments scheduled</div>
        </article>
        <article class="metric-card">
            <div class="metric-card-label">Waiting / In Consult</div>
            <div class="metric-card-num stat-color-warn" id="waitCount"><?= $waiting ?></div>
            <div class="metric-card-sub">patients in queue</div>
        </article>
        <article class="metric-card">
            <div class="metric-card-label">Completed</div>
            <div class="metric-card-num stat-color-ok" id="doneCount"><?= $done ?></div>
            <div class="metric-card-sub">consultations finished</div>
        </article>
        <article class="metric-card">
            <div class="metric-card-label">Avg Duration</div>
            <div class="metric-card-num stat-color-pri">15<span style="font-size:1rem">min</span></div>
            <div class="metric-card-sub">per scheduled consultation</div>
        </article>
    </section>

    <!-- ===== PATIENT QUEUE ===== -->
    <section class="dashboard-card" style="margin-bottom:48px">
        <div class="section-head flex-between" style="margin-bottom:20px">
            <div>
                <span class="section-tag">Live Queue</span>
                <h2 class="section-title" style="font-size:1.45rem">Patient Consultation Queue</h2>
            </div>
            <button class="btn btn-outline btn-sm" onclick="location.reload()">↻&nbsp;Refresh</button>
        </div>

        <?php if (empty($queue)): ?>
            <div style="background:var(--bg);border:1px dashed var(--border-hover);border-radius:var(--radius-md);padding:40px 20px;text-align:center">
                <div style="font-size:2.2rem;margin-bottom:10px">📅</div>
                <h3 style="font-size:1.05rem;margin-bottom:6px">No Appointments Booked Yet</h3>
                <p style="font-size:0.86rem;color:var(--text-muted)">Patient consultations booked for your department will appear here automatically.</p>
            </div>
        <?php else: ?>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Patient</th>
                        <th>Date & Time</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody id="queueTbody">
                <?php foreach ($queue as $row):
                    $isCurrent  = $row['status'] === 'in_consultation';
                    $isDone     = $row['status'] === 'completed';
                    $isWaiting  = in_array($row['status'], ['pending', 'confirmed']);
                    $badgeClass = $isDone ? 'done' : ($isCurrent ? 'consulting' : 'waiting');
                    $badgeLabel = $isDone ? 'Completed ✓' : ($isCurrent ? 'In Consultation' : ucfirst(clean($row['status'])));
                    
                    $age = '—';
                    if (!empty($row['date_of_birth'])) {
                        $age = (int)date_diff(date_create($row['date_of_birth']), date_create('today'))->y . ' yrs';
                    }
                    $slotFormatted = format_time_slot($row['slot_time']);
                    $dateFormatted = date('M j', strtotime($row['appointment_date']));
                ?>
                <tr id="row-<?= $row['id'] ?>" class="<?= $isCurrent ? 'row-current' : '' ?>">
                    <td><strong style="color:var(--primary-dark)"><?= clean($row['appointment_token']) ?></strong></td>
                    <td>
                        <strong><?= clean($row['patient_name']) ?></strong>
                        <br><small style="color:var(--text-muted)"><?= ucfirst(clean($row['gender'] ?? 'Patient')) ?>, <?= $age ?></small>
                    </td>
                    <td><?= clean($dateFormatted) ?> &bull; <?= clean($slotFormatted) ?><?= $isCurrent ? ' <strong style="color:var(--primary)">(Now)</strong>' : '' ?></td>
                    <td><a href="tel:<?= clean(str_replace(' ','',$row['phone'])) ?>" style="color:var(--primary)"><?= clean($row['phone']) ?></a></td>
                    <td>
                        <span class="status-badge <?= $badgeClass ?>" id="badge-<?= $row['id'] ?>">
                            <?= $badgeLabel ?>
                        </span>
                    </td>
                    <td style="text-align:right">
                        <?php if ($isDone): ?>
                            <button class="btn btn-outline btn-sm" onclick="alert('Consultation record for <?= clean(addslashes($row['patient_name'])) ?> is archived.')">Summary</button>
                        <?php elseif ($isCurrent): ?>
                            <button class="btn btn-primary btn-sm" id="btn-<?= $row['id'] ?>"
                                onclick="markDone('<?= $row['id'] ?>','<?= clean(addslashes($row['patient_name'])) ?>')">Mark Done</button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" id="btn-<?= $row['id'] ?>"
                                onclick="callIn('<?= $row['id'] ?>','<?= clean(addslashes($row['patient_name'])) ?>')">Call In</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div>
</main>

<footer class="site-footer" style="padding-top:40px">
    <div class="footer-bottom">
        <div class="container footer-bottom-flex">
            <p>&copy; <?= date('Y') ?> HAMS Doctor Desk &mdash; Verified Medical Practitioner Portal</p>
            <div class="footer-bottom-links">
                <a href="../logout.php">Sign Out</a>
                <a href="../change-password.php">Security Settings</a>
            </div>
        </div>
    </div>
</footer>

<script>
    var statusActive = true;

    function toggleStatus() {
        statusActive = !statusActive;
        var box  = document.getElementById('statusBox');
        var dot  = document.getElementById('statusDot');
        var text = document.getElementById('statusText');
        if (statusActive) {
            dot.style.background = 'var(--primary-light)';
            text.textContent = 'Accepting Patients (Available)';
            box.style.borderColor = 'rgba(11,110,79,0.2)';
        } else {
            dot.style.background = 'var(--warning)';
            text.textContent = 'In Consultation (Temporarily Busy)';
            box.style.borderColor = 'rgba(217,119,6,0.3)';
        }
    }

    function markDone(id, name) {
        var badge = document.getElementById('badge-' + id);
        var btn   = document.getElementById('btn-'   + id);
        var row   = document.getElementById('row-'   + id);
        if (badge) { badge.className = 'status-badge done'; badge.textContent = 'Completed ✓'; }
        if (row)   { row.classList.remove('row-current'); }
        if (btn)   { btn.className = 'btn btn-outline btn-sm'; btn.textContent = 'Summary'; btn.onclick = function(){ alert('Consultation summary for ' + name + ' saved.'); }; }
        var done = document.getElementById('doneCount');
        var wait = document.getElementById('waitCount');
        if (done) done.textContent = parseInt(done.textContent) + 1;
        if (wait) wait.textContent = Math.max(0, parseInt(wait.textContent) - 1);
    }

    function callIn(id, name) {
        var badge = document.getElementById('badge-' + id);
        var btn   = document.getElementById('btn-'   + id);
        var row   = document.getElementById('row-'   + id);
        if (badge) { badge.className = 'status-badge consulting'; badge.textContent = 'In Consultation'; }
        if (row)   { row.classList.add('row-current'); }
        if (btn)   { btn.className = 'btn btn-primary btn-sm'; btn.textContent = 'Mark Done'; btn.onclick = function(){ markDone(id, name); }; }
        alert('Patient ' + name + ' called into consultation room.');
    }
</script>
</body>
</html>