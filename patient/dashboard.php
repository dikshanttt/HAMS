<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login(['patient']);

$db  = getDB();
$uid = current_user_id();

// Patient profile + account info
$stmt = $db->prepare(
    'SELECT u.email, u.created_at,
            p.name, p.phone, p.date_of_birth, p.gender,
            p.blood_group, p.address,
            p.emergency_contact_name, p.emergency_contact_phone
     FROM users u
     LEFT JOIN patient_profiles p ON u.id = p.user_id
     WHERE u.id = ?'
);
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

// Verified active doctors
$docs = $db->query(
    "SELECT d.user_id, d.name, d.specialization, d.qualification,
            d.experience_years, d.image_path, u.doctor_login_id
     FROM doctor_profiles d
     JOIN users u ON d.user_id = u.id
     WHERE d.verification_status = 'verified' AND u.status = 'active'
     ORDER BY d.name ASC LIMIT 6"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | HAMS Patient Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime('../assets/css/style.css') ?>">
    <style>
        .profile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-top:16px}
        .pinfo{background:rgba(255,255,255,0.16);border-radius:10px;padding:10px 14px;backdrop-filter:blur(4px)}
        .pinfo small{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;opacity:.72;margin-bottom:3px}
        .pinfo span{font-weight:700;font-size:.92rem;word-break:break-word}
        .ec-bar{margin-top:14px;padding:11px 16px;border-radius:10px;background:rgba(255,255,255,0.13);border:1px solid rgba(255,255,255,0.22);font-size:.86rem;display:flex;align-items:center;flex-wrap:wrap;gap:8px}
        .empty-state{text-align:center;padding:36px 20px}
        .empty-state .es-icon{font-size:2.4rem;margin-bottom:10px}
        .empty-state h3{font-size:1.1rem;margin-bottom:6px}
        .empty-state p{font-size:.88rem;color:var(--text-muted);max-width:320px;margin:0 auto}

        @media(max-width:768px){
            .portal-grid-3{grid-template-columns:1fr !important}
            .quick-actions-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:480px){
            .welcome-hero{padding:20px}
            .welcome-hero h1{font-size:1.5rem}
            .quick-actions-grid{grid-template-columns:1fr}
            .doctors-grid{grid-template-columns:1fr !important}
        }
    </style>
</head>
<body class="portal-page">

<!-- Emergency Strip -->
<aside class="emergency-strip">
    <div class="container wide">
        <span class="emergency-pill">🚨 <strong>24/7 Emergency:</strong>
            <a class="emergency-phone" href="tel:102">Ambulance — Dial 102</a>
        </span>
        <span>Reception: <a class="emergency-phone" href="tel:+97714200000">+977 1 4200000</a></span>
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

    <!-- Welcome Hero -->
    <section class="welcome-hero">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
            <div>
                <h1>Hello, <?= clean($name) ?> 👋</h1>
                <p style="margin-top:6px">Find verified specialists and manage your healthcare visits — all from one place.</p>
            </div>
            <?php if ($blood): ?>
            <div style="text-align:center;flex-shrink:0">
                <div style="font-size:.7rem;opacity:.75;margin-bottom:4px;font-weight:700;text-transform:uppercase;letter-spacing:.07em">Blood Group</div>
                <div style="font-size:2rem;font-weight:800;background:rgba(255,255,255,0.2);padding:6px 18px;border-radius:10px;letter-spacing:.05em"><?= $blood ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Profile info chips -->
        <div class="profile-grid">
            <?php if ($dob): ?>
            <div class="pinfo"><small>Date of Birth</small><span><?= clean($dob) ?></span></div>
            <?php endif; ?>
            <?php if ($gender): ?>
            <div class="pinfo"><small>Gender</small><span><?= clean($gender) ?></span></div>
            <?php endif; ?>
            <?php if ($phone): ?>
            <div class="pinfo"><small>Phone</small><span><?= clean($phone) ?></span></div>
            <?php endif; ?>
            <div class="pinfo"><small>Member Since</small><span><?= clean($joined) ?></span></div>
        </div>

        <?php if ($ecName): ?>
        <div class="ec-bar">
            🆘 <strong>Emergency Contact:</strong> <?= clean($ecName) ?>
            <?php if ($ecPhone): ?>
            — <a href="tel:<?= clean($ecPhone) ?>" style="color:#fff;font-weight:700"><?= clean($ecPhone) ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Search -->
        <div class="quick-search-box" style="margin-top:20px">
            <span style="font-size:1.1rem;margin-right:6px;flex-shrink:0">🔍</span>
            <input type="text" id="docSearch" placeholder="Search by doctor name or specialty (e.g. Cardiology)…">
        </div>
    </section>

    <!-- Main 2-col Grid -->
    <div class="portal-grid-3" style="margin-bottom:32px">

        <!-- Left: Appointment Status -->
        <section class="dashboard-card">
            <h2 class="dashboard-card-title">Your Appointment Status</h2>

            <!-- No appointments table yet — show clear empty state -->
            <div style="background:var(--bg);border:1px dashed var(--border-hover);border-radius:var(--radius-md);padding:32px 20px;text-align:center">
                <div style="font-size:2.2rem;margin-bottom:10px">📋</div>
                <h3 style="font-size:1rem;margin-bottom:6px;color:var(--text-main)">No Active Appointments</h3>
                <p style="font-size:.86rem;color:var(--text-muted);max-width:280px;margin:0 auto 18px">
                    You don't have any upcoming appointments right now. Browse the verified doctors below and book a consultation.
                </p>
                <a class="btn btn-primary btn-sm"
                   href="#doctorSection"
                   onclick="document.getElementById('doctorSection').scrollIntoView({behavior:'smooth'});return false;">
                    Browse Doctors
                </a>
            </div>

            <!-- Account info -->
            <div style="margin-top:16px;padding:14px 16px;background:var(--primary-surface);border:1px solid var(--border-accent);border-radius:var(--radius-md);font-size:.84rem">
                <strong style="color:var(--primary-dark);display:block;margin-bottom:4px">Account Details</strong>
                <span style="color:var(--text-muted)"><?= clean($email) ?></span>
            </div>
        </section>

        <!-- Right: Quick Actions -->
        <section class="dashboard-card">
            <h2 class="dashboard-card-title">Quick Actions</h2>
            <div class="quick-actions-grid">
                <div class="action-card-btn"
                     onclick="document.getElementById('doctorSection').scrollIntoView({behavior:'smooth'})">
                    <span class="action-icon">🩺</span>
                    <strong>Find a Doctor</strong>
                    <span>Browse verified specialists</span>
                </div>
                <div class="action-card-btn"
                     onclick="alert('Appointment history will be available once the scheduling system is set up.')">
                    <span class="action-icon">📋</span>
                    <strong>Visit History</strong>
                    <span>Past consultations</span>
                </div>
                <a class="action-card-btn" href="tel:102" style="text-decoration:none;color:inherit">
                    <span class="action-icon">🚑</span>
                    <strong>Ambulance</strong>
                    <span>Dial 102 — Free</span>
                </a>
                <div class="action-card-btn"
                     onclick="alert('Billing information will appear here after your first consultation.')">
                    <span class="action-icon">💳</span>
                    <strong>Billing</strong>
                    <span>No hidden charges</span>
                </div>
            </div>

            <?php if ($ecName || $ecPhone): ?>
            <div style="margin-top:16px;padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-md);font-size:.84rem">
                <strong style="color:#991b1b;display:block;margin-bottom:4px">🆘 Emergency Contact on File</strong>
                <span style="color:#7f1d1d"><?= clean($ecName ?: '') ?><?= $ecPhone ? ' — ' . clean($ecPhone) : '' ?></span>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Available Doctors -->
    <section id="doctorSection" class="dashboard-card" style="margin-bottom:48px">
        <div class="section-head flex-between" style="margin-bottom:22px">
            <div>
                <span class="section-tag">Verified Specialists</span>
                <h2 class="section-title" style="font-size:1.45rem">Available Doctors</h2>
            </div>
            <span style="font-size:.82rem;color:var(--text-muted)"><?= count($docs) ?> verified specialist<?= count($docs) !== 1 ? 's' : '' ?> on the system</span>
        </div>

        <?php if (empty($docs)): ?>
        <div style="background:var(--bg);border:1px dashed var(--border-hover);border-radius:var(--radius-md);padding:48px 24px;text-align:center">
            <div style="font-size:2.4rem;margin-bottom:10px">🩺</div>
            <h3 style="font-size:1.05rem;margin-bottom:6px">No Verified Doctors Yet</h3>
            <p style="font-size:.86rem;color:var(--text-muted);max-width:320px;margin:0 auto">
                Doctors become visible here once they are verified by the hospital administrator. Please check back soon.
            </p>
        </div>
        <?php else: ?>
        <div class="cards-grid doctors-grid" id="doctorGrid">
            <?php foreach ($docs as $d):
                $av = strtoupper(mb_substr(trim(preg_replace('/^Dr\.?\s*/i', '', $d['name'])), 0, 2)) ?: 'DR';
            ?>
            <article class="modern-card doctor-item"
                     data-name="<?= clean(strtolower($d['name'])) ?>"
                     data-spec="<?= clean(strtolower($d['specialization'])) ?>">
                <div class="doctor-card-top">
                    <?php if (!empty($d['image_path']) && file_exists(__DIR__ . '/../' . $d['image_path'])): ?>
                        <img src="../<?= clean($d['image_path']) ?>" alt="<?= clean($d['name']) ?>"
                             style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0">
                    <?php else: ?>
                        <div class="doctor-avatar-circle"><?= clean($av) ?></div>
                    <?php endif; ?>
                    <div>
                        <h3 class="doctor-name"><?= clean($d['name']) ?></h3>
                        <span class="dept-tag"><?= clean($d['specialization']) ?></span>
                        <p class="hospital-sub"><?= clean($d['qualification']) ?> &bull; <?= (int)$d['experience_years'] ?> yrs exp</p>
                    </div>
                </div>
                <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:14px;padding:10px 12px;background:var(--bg);border-radius:var(--radius-sm)">
                    📞 Contact reception to book a consultation with this specialist.
                </p>
                <a class="btn btn-primary btn-full" href="tel:+97714200000">
                    Contact Hospital Reception
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

</div>
</main>

<footer class="site-footer" style="padding-top:40px">
    <div class="footer-bottom">
        <div class="container footer-bottom-flex">
            <p>&copy; <?= date('Y') ?> HAMS Care — Patient Portal. All health data is encrypted and protected.</p>
            <div class="footer-bottom-links">
                <a href="../logout.php">Sign Out</a>
                <a href="tel:102">🚑 Ambulance 102</a>
            </div>
        </div>
    </div>
</footer>

<script>
    var searchBox = document.getElementById('docSearch');
    if (searchBox) {
        searchBox.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('#doctorGrid .doctor-item').forEach(function (c) {
                var t = (c.dataset.name || '') + ' ' + (c.dataset.spec || '');
                c.style.display = (!q || t.includes(q)) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>