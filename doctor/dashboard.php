<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login(['doctor']);

$db  = getDB();
$uid = current_user_id();

$stmt = $db->prepare(
    'SELECT u.doctor_login_id, u.email,
            d.name, d.specialization, d.license_no,
            d.qualification, d.experience_years, d.image_path,
            d.verification_status
     FROM users u
     LEFT JOIN doctor_profiles d ON u.id = d.user_id
     WHERE u.id = ?'
);
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

// ---- Simulated queue data (replace with real DB query when appointments table exists) ----
$queue = [
    ['id'=>'401','patient'=>'Ramesh Sharma','age'=>42,'gender'=>'M','slot'=>'01:30 PM','phone'=>'+977 9841234567','status'=>'done'],
    ['id'=>'404','patient'=>'Pooja Thapa',  'age'=>29,'gender'=>'F','slot'=>'02:00 PM','phone'=>'+977 9812345678','status'=>'done'],
    ['id'=>'408','patient'=>'Sita Adhikari','age'=>35,'gender'=>'F','slot'=>'02:30 PM','phone'=>'+977 9801122334','status'=>'current'],
    ['id'=>'412','patient'=>'Binod KC',     'age'=>54,'gender'=>'M','slot'=>'03:15 PM','phone'=>'+977 9849988776','status'=>'waiting'],
    ['id'=>'416','patient'=>'Anita Gurung', 'age'=>24,'gender'=>'F','slot'=>'04:00 PM','phone'=>'+977 9823456789','status'=>'scheduled'],
    ['id'=>'421','patient'=>'Suresh Lamsal','age'=>61,'gender'=>'M','slot'=>'04:45 PM','phone'=>'+977 9867543210','status'=>'scheduled'],
];
$done    = count(array_filter($queue, fn($r) => $r['status'] === 'done'));
$waiting = count(array_filter($queue, fn($r) => in_array($r['status'], ['waiting','current'])));
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
                    <?= clean($qual) ?> &bull; <?= $exp ?> years clinical experience
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
            <div class="metric-card-label">Today's Schedule</div>
            <div class="metric-card-num"><?= $total ?></div>
            <div class="metric-card-sub">appointments booked</div>
        </article>
        <article class="metric-card">
            <div class="metric-card-label">Waiting / In Consult</div>
            <div class="metric-card-num stat-color-warn" id="waitCount"><?= $waiting ?></div>
            <div class="metric-card-sub">patients in lobby/room</div>
        </article>
        <article class="metric-card">
            <div class="metric-card-label">Completed Today</div>
            <div class="metric-card-num stat-color-ok" id="doneCount"><?= $done ?></div>
            <div class="metric-card-sub">consultations finished</div>
        </article>
        <article class="metric-card">
            <div class="metric-card-label">Avg Duration</div>
            <div class="metric-card-num stat-color-pri">14<span style="font-size:1rem">min</span></div>
            <div class="metric-card-sub">per consultation today</div>
        </article>
    </section>

    <!-- ===== PATIENT QUEUE ===== -->
    <section class="dashboard-card" style="margin-bottom:48px">
        <div class="section-head flex-between" style="margin-bottom:20px">
            <div>
                <span class="section-tag">Live Queue</span>
                <h2 class="section-title" style="font-size:1.45rem">Today's Patient Consultation Queue</h2>
            </div>
            <button class="btn btn-outline btn-sm" onclick="location.reload()">↻&nbsp;Refresh</button>
        </div>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Patient</th>
                        <th>Time Slot</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody id="queueTbody">
                <?php foreach ($queue as $row):
                    $isCurrent  = $row['status'] === 'current';
                    $isDone     = $row['status'] === 'done';
                    $isWaiting  = $row['status'] === 'waiting';
                    $isScheduled= $row['status'] === 'scheduled';
                    $badgeClass = $isDone ? 'done' : ($isCurrent ? 'consulting' : 'waiting');
                    $badgeLabel = $isDone ? 'Completed ✓' : ($isCurrent ? 'In Room 304' : ($isWaiting ? 'In Lobby' : 'Scheduled'));
                ?>
                <tr id="row-<?= $row['id'] ?>" class="<?= $isCurrent ? 'row-current' : '' ?>">
                    <td><strong style="color:var(--primary-dark)">#TK-<?= $row['id'] ?></strong></td>
                    <td>
                        <strong><?= clean($row['patient']) ?></strong>
                        <br><small style="color:var(--text-muted)"><?= $row['gender'] === 'M' ? 'Male' : 'Female' ?>, <?= $row['age'] ?> yrs</small>
                    </td>
                    <td><?= clean($row['slot']) ?><?= $isCurrent ? ' <strong style="color:var(--primary)">(Now)</strong>' : '' ?></td>
                    <td><a href="tel:<?= clean(str_replace(' ','',$row['phone'])) ?>" style="color:var(--primary)"><?= clean($row['phone']) ?></a></td>
                    <td>
                        <span class="status-badge <?= $badgeClass ?>" id="badge-<?= $row['id'] ?>">
                            <?= $badgeLabel ?>
                        </span>
                    </td>
                    <td style="text-align:right">
                        <?php if ($isDone): ?>
                            <button class="btn btn-outline btn-sm" onclick="alert('Consultation summary for <?= clean(addslashes($row['patient'])) ?> saved.')">Summary</button>
                        <?php elseif ($isCurrent): ?>
                            <button class="btn btn-primary btn-sm" id="btn-<?= $row['id'] ?>"
                                onclick="markDone('<?= $row['id'] ?>','<?= clean(addslashes($row['patient'])) ?>')">Mark Done</button>
                        <?php elseif ($isWaiting): ?>
                            <button class="btn btn-secondary btn-sm" id="btn-<?= $row['id'] ?>"
                                onclick="callIn('<?= $row['id'] ?>','<?= clean(addslashes($row['patient'])) ?>')">Call In</button>
                        <?php else: ?>
                            <button class="btn btn-ghost btn-sm"
                                onclick="alert('Notified <?= clean(addslashes($row['patient'])) ?> to arrive 10 min before their slot.')">Notify</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
        if (badge) { badge.className = 'status-badge consulting'; badge.textContent = 'In Room 304'; }
        if (row)   { row.classList.add('row-current'); }
        if (btn)   { btn.className = 'btn btn-primary btn-sm'; btn.textContent = 'Mark Done'; btn.onclick = function(){ markDone(id, name); }; }
        alert('Patient ' + name + ' (Token #' + id + ') called into Consultation Room 304.');
    }
</script>
</body>
</html>