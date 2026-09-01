<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

if (current_user_id()) {
    redirect('/' . current_role() . '/dashboard.php');
}

$db = getDB();

// 1. Live Stats
$totalHospitals = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE is_active = TRUE")->fetchColumn();
$totalDoctors   = (int)$db->query("
    SELECT COUNT(DISTINCT d.user_id) 
    FROM doctor_profiles d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.verification_status = 'verified' AND u.status = 'active'
")->fetchColumn();
$totalAppointments = (int)$db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$avgRating = (float)$db->query("SELECT ROUND(AVG(rating), 1) FROM hospitals WHERE is_active = TRUE")->fetchColumn();
if ($avgRating <= 0) {
    $avgRating = 4.9;
}

// 2. Hero Featured Doctor
$heroDoctor = $db->query("
    SELECT d.user_id, d.name, d.specialization, d.qualification, d.experience_years, d.image_path,
           h.name AS hospital_name, h.address AS hospital_address
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN doctor_hospital dh ON dh.doctor_id = d.user_id AND dh.status = 'active'
    LEFT JOIN hospitals h ON h.id = dh.hospital_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    ORDER BY d.experience_years DESC, d.user_id ASC
    LIMIT 1
")->fetch();

$heroSlots = [];
if ($heroDoctor) {
    $heroSlots = $db->query("
        SELECT start_time, end_time, day_of_week
        FROM schedules
        WHERE doctor_id = " . (int)$heroDoctor['user_id'] . " AND is_active = TRUE
        ORDER BY start_time ASC
        LIMIT 3
    ")->fetchAll();
}

// 3. Partner Hospitals (with search filter)
$hospQuery = trim($_GET['query'] ?? '');
if ($hospQuery !== '') {
    $hospStmt = $db->prepare("
        SELECT h.*,
               COUNT(DISTINCT dh.doctor_id) AS active_doctors_count
        FROM hospitals h
        LEFT JOIN doctor_hospital dh ON dh.hospital_id = h.id AND dh.status = 'active'
        WHERE h.is_active = TRUE AND (
            h.name ILIKE ? OR
            h.departments ILIKE ? OR
            h.address ILIKE ?
        )
        GROUP BY h.id
        ORDER BY h.rating DESC, h.name ASC
    ");
    $likeParam = '%' . $hospQuery . '%';
    $hospStmt->execute([$likeParam, $likeParam, $likeParam]);
    $hospitals = $hospStmt->fetchAll();
} else {
    $hospitals = $db->query("
        SELECT h.*,
               COUNT(DISTINCT dh.doctor_id) AS active_doctors_count
        FROM hospitals h
        LEFT JOIN doctor_hospital dh ON dh.hospital_id = h.id AND dh.status = 'active'
        WHERE h.is_active = TRUE
        GROUP BY h.id
        ORDER BY h.rating DESC, h.name ASC
    ")->fetchAll();
}

// 4. Featured Doctors (with hospital affiliations and active schedules)
$doctors = $db->query("
    SELECT d.user_id, d.name, d.specialization, d.qualification, d.experience_years, d.image_path,
           h.id AS hospital_id, h.name AS hospital_name, h.slug AS hospital_slug,
           u.doctor_login_id
    FROM doctor_profiles d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN doctor_hospital dh ON dh.doctor_id = d.user_id AND dh.status = 'active'
    LEFT JOIN hospitals h ON h.id = dh.hospital_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
    ORDER BY d.experience_years DESC, d.name ASC
")->fetchAll();

// Distinct Specialties for Filter Pills
$specialties = array_values(array_unique(array_filter(array_column($doctors, 'specialization'))));
sort($specialties);

// Fetch Schedules per Doctor
$doctorSchedules = [];
$schedRows = $db->query("
    SELECT doctor_id, day_of_week, start_time, end_time
    FROM schedules
    WHERE is_active = TRUE
    ORDER BY start_time ASC
")->fetchAll();
foreach ($schedRows as $s) {
    $doctorSchedules[$s['doctor_id']][] = $s;
}

// Color banner palette cycling for hospitals
$bannerColors = ['banner-emerald', 'banner-teal', 'banner-forest'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAMS | Modern Hospital Appointment Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css'); ?>">
    <style>
        .no-results-box {
            text-align: center;
            padding: 48px 24px;
            background: var(--surface);
            border: 1px dashed var(--border-hover);
            border-radius: var(--radius-lg);
            grid-column: 1 / -1;
        }
        .doctor-card-top-inner {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }
        .hero-slot-preview {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,0.15);
        }
    </style>
</head>
<body class="home-page">

    <!-- Header Navigation -->
    <header class="site-header" id="siteHeader">
        <div class="header-container">
            <a class="brand" href="#home">
                <span class="brand-icon">✚</span>
                <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
            </a>

            <!-- Desktop Nav -->
            <nav class="desktop-nav" aria-label="Primary navigation">
                <a href="#home" class="nav-link active">Home</a>
                <a href="#hospitals" class="nav-link">Hospitals</a>
                <a href="#doctors" class="nav-link">Doctors</a>
                <a href="#how-it-works" class="nav-link">How It Works</a>
                <a href="#faq" class="nav-link">FAQ</a>
                <a href="#contact" class="nav-link">Contact</a>
            </nav>

            <!-- Nav Actions -->
            <div class="header-actions">
                <a class="btn btn-ghost" href="login.php">Login</a>
                <a class="btn btn-primary btn-pill" href="register/account-type.php">Book Appointment</a>
                <button class="mobile-toggle" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </button>
            </div>
        </div>

        <!-- Mobile Drawer Menu -->
        <div class="mobile-drawer" id="mobileDrawer">
            <nav class="mobile-nav" aria-label="Mobile navigation">
                <a href="#home" class="mobile-nav-link">Home</a>
                <a href="#hospitals" class="mobile-nav-link">Hospitals</a>
                <a href="#doctors" class="mobile-nav-link">Doctors</a>
                <a href="#how-it-works" class="mobile-nav-link">How It Works</a>
                <a href="#faq" class="mobile-nav-link">FAQ</a>
                <a href="#contact" class="mobile-nav-link">Contact</a>
                <div class="mobile-auth-actions">
                    <a class="btn btn-outline btn-full" href="login.php">Login</a>
                    <a class="btn btn-primary btn-full" href="register/account-type.php">Book Appointment</a>
                </div>
            </nav>
        </div>
    </header>

    <main id="home">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container hero-grid">
                <div class="hero-content">
                    <div class="hero-badge">
                        <span class="pulse-dot"></span>
                        <span>Trusted Digital Healthcare Network</span>
                    </div>
                    <h1 class="hero-title">Book top hospital appointments <span class="gradient-text">without the wait</span></h1>
                    <p class="hero-description">
                        Connect directly with verified specialists and accredited hospitals. Compare doctors, select preferred consultation slots, and manage your visits easily from anywhere.
                    </p>
                    <div class="hero-cta-group">
                        <a class="btn btn-primary btn-lg" href="register/account-type.php">
                            <span>Book Appointment</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                        <a class="btn btn-secondary btn-lg" href="#hospitals">
                            <span>Browse Hospitals</span>
                        </a>
                    </div>
                    <ul class="hero-perks">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Same-day bookings</span>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>100% verified doctors</span>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Zero booking fees</span>
                        </li>
                    </ul>
                </div>

                <div class="hero-visual">
                    <div class="visual-card-main">
                        <?php if ($heroDoctor): 
                            $heroInitials = strtoupper(mb_substr(trim(preg_replace('/^Dr\.?\s*/i', '', $heroDoctor['name'])), 0, 2)) ?: 'DR';
                        ?>
                        <div class="doctor-highlight-header">
                            <div class="doctor-profile-lead">
                                <?php if (!empty($heroDoctor['image_path']) && file_exists(__DIR__ . '/' . $heroDoctor['image_path'])): ?>
                                    <img src="<?= clean($heroDoctor['image_path']) ?>" alt="<?= clean($heroDoctor['name']) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #fff">
                                <?php else: ?>
                                    <div class="avatar-lead">
                                        <span><?= clean($heroInitials) ?></span>
                                        <span class="online-indicator"></span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="lead-name"><?= clean($heroDoctor['name']) ?></h4>
                                    <p class="lead-spec"><?= clean($heroDoctor['specialization']) ?> • <?= clean($heroDoctor['hospital_name'] ?: 'Verified Specialist') ?></p>
                                </div>
                            </div>
                            <div class="rating-badge">
                                <span>★ 4.9</span>
                            </div>
                        </div>

                        <div class="slot-picker-preview">
                            <span class="preview-title">Available Slots:</span>
                            <div class="slot-buttons">
                                <?php if (!empty($heroSlots)): ?>
                                    <?php foreach ($heroSlots as $idx => $slot): ?>
                                        <span class="slot-chip <?= $idx === 0 ? 'active' : '' ?>">
                                            <?= clean(format_time_slot($slot['start_time'])) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="slot-chip active">02:30 PM</span>
                                    <span class="slot-chip">04:00 PM</span>
                                    <span class="slot-chip">05:30 PM</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="doctor-highlight-header">
                            <div class="doctor-profile-lead">
                                <div class="avatar-lead">
                                    <span>DR</span>
                                    <span class="online-indicator"></span>
                                </div>
                                <div>
                                    <h4 class="lead-name">Dr. Sophia Patel</h4>
                                    <p class="lead-spec">Chief Cardiologist • City Care Hospital</p>
                                </div>
                            </div>
                            <div class="rating-badge">
                                <span>★ 4.9</span>
                            </div>
                        </div>
                        <div class="slot-picker-preview">
                            <span class="preview-title">Available Slots Today:</span>
                            <div class="slot-buttons">
                                <span class="slot-chip active">02:30 PM</span>
                                <span class="slot-chip">04:00 PM</span>
                                <span class="slot-chip">05:30 PM</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mini-appointment-card">
                            <div class="check-icon-circle">✓</div>
                            <div class="appointment-details">
                                <strong>Appointment Confirmed</strong>
                                <span>Token #TK-2026-8402 • Room 304</span>
                            </div>
                            <span class="status-pill-green">Confirmed</span>
                        </div>

                        <div class="floating-badge badge-hospital">
                            <span class="hospital-icon">🏥</span>
                            <div>
                                <strong><?= $totalHospitals ?>+ Partner Hospitals</strong>
                                <small>Live real-time queue</small>
                            </div>
                        </div>

                        <div class="floating-badge badge-security">
                            <span class="secure-icon">🛡️</span>
                            <div>
                                <strong>Verified Access</strong>
                                <small>Encrypted Medical Records</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Counter Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-num"><?= $totalHospitals ?><span>+</span></div>
                        <div class="stat-text">Partner Hospitals</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num"><?= $totalDoctors ?><span>+</span></div>
                        <div class="stat-text">Verified Specialists</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num"><?= max($totalAppointments, 50) ?><span>+</span></div>
                        <div class="stat-text">Appointments Booked</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num"><?= number_format($avgRating, 1) ?><span>★</span></div>
                        <div class="stat-text">Patient Satisfaction</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Hospitals Section -->
        <section class="section hospitals-section" id="hospitals">
            <div class="container">
                <div class="section-head">
                    <div>
                        <span class="section-tag">Find Care Fast</span>
                        <h2 class="section-title">Explore partner hospitals & clinics</h2>
                    </div>
                    <form class="hospital-search-bar" action="#hospitals" method="GET">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" name="query" value="<?= clean($hospQuery) ?>" placeholder="Search hospital name, address or department...">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                    </form>
                </div>

                <div class="cards-grid hospitals-grid">
                    <?php if (empty($hospitals)): ?>
                        <div class="no-results-box">
                            <div style="font-size:2.2rem;margin-bottom:8px">🏥</div>
                            <h3 style="font-size:1.1rem;margin-bottom:6px">No Hospitals Found</h3>
                            <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:16px">
                                <?= $hospQuery ? 'No hospitals matched your search "<strong>' . clean($hospQuery) . '</strong>".' : 'No partner hospitals registered yet.' ?>
                            </p>
                            <?php if ($hospQuery): ?>
                                <a href="index.php#hospitals" class="btn btn-secondary btn-sm">Clear Search</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($hospitals as $i => $hosp): 
                            $bannerClass = $bannerColors[$i % count($bannerColors)];
                            $deptList = explode(',', $hosp['departments'] ?? '');
                            $deptPreview = trim($deptList[0] ?? 'Multi-Specialty');
                        ?>
                        <article class="modern-card hospital-item">
                            <div class="card-banner <?= $bannerClass ?>">
                                <span class="hospital-badge"><?= clean($deptPreview) ?></span>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title"><?= clean($hosp['name']) ?></h3>
                                <p class="card-desc"><?= clean($hosp['address']) ?> • <?= clean($hosp['departments'] ?? 'General Care') ?></p>
                                <div class="card-meta">
                                    <span class="meta-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> 
                                        <?= (int)$hosp['active_doctors_count'] ?> Doctor<?= (int)$hosp['active_doctors_count'] !== 1 ? 's' : '' ?>
                                    </span>
                                    <span class="meta-item">📞 <?= clean($hosp['phone']) ?></span>
                                    <span class="rating-chip"><?= number_format((float)($hosp['rating'] ?? 4.8), 1) ?> ★</span>
                                </div>
                                <a href="register/account-type.php" class="btn btn-outline btn-full">Book at <?= clean($hosp['name']) ?> →</a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="section process-section bg-subtle" id="how-it-works">
            <div class="container">
                <div class="section-head text-center">
                    <span class="section-tag">Simple & Fast</span>
                    <h2 class="section-title">How to book your visit in 4 steps</h2>
                    <p class="section-subtitle">No paperwork, no waiting queues. Schedule verified medical appointments seamlessly.</p>
                </div>

                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-badge">01</div>
                        <h3 class="step-title">Select Hospital</h3>
                        <p class="step-desc">Pick from accredited hospitals and clinics in your vicinity.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-badge">02</div>
                        <h3 class="step-title">Choose Department</h3>
                        <p class="step-desc">Choose from Cardiology, Pediatrics, Orthopedics, and more.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-badge">03</div>
                        <h3 class="step-title">Select Specialist</h3>
                        <p class="step-desc">Review doctor qualifications, experience, and patient ratings.</p>
                    </div>

                    <div class="step-card">
                        <div class="step-badge">04</div>
                        <h3 class="step-title">Confirm & Visit</h3>
                        <p class="step-desc">Select your preferred date/time slot and receive your digital pass.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Doctors Section -->
        <section class="section doctors-section" id="doctors">
            <div class="container">
                <div class="section-head flex-between">
                    <div>
                        <span class="section-tag">Expert Care</span>
                        <h2 class="section-title">Meet our certified specialists</h2>
                    </div>
                    <div class="filter-pills" id="deptFilter">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <?php foreach ($specialties as $spec): ?>
                            <button class="filter-btn" data-filter="<?= clean(strtolower(preg_replace('/[^a-z0-9]/i', '', $spec))) ?>">
                                <?= clean($spec) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cards-grid doctors-grid">
                    <?php if (empty($doctors)): ?>
                        <div class="no-results-box">
                            <div style="font-size:2.2rem;margin-bottom:8px">🩺</div>
                            <h3 style="font-size:1.1rem;margin-bottom:6px">No Verified Doctors Registered Yet</h3>
                            <p style="font-size:0.88rem;color:var(--text-muted)">Specialists will appear here once approved by the administrator.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($doctors as $doc): 
                            $cleanCat = strtolower(preg_replace('/[^a-z0-9]/i', '', $doc['specialization']));
                            $docInitials = strtoupper(mb_substr(trim(preg_replace('/^Dr\.?\s*/i', '', $doc['name'])), 0, 2)) ?: 'DR';
                            $docSlots = $doctorSchedules[$doc['user_id']] ?? [];
                        ?>
                        <article class="modern-card doctor-item" data-category="<?= clean($cleanCat) ?>">
                            <div class="card-content">
                                <div class="doctor-card-top-inner">
                                    <?php if (!empty($doc['image_path']) && file_exists(__DIR__ . '/' . $doc['image_path'])): ?>
                                        <img src="<?= clean($doc['image_path']) ?>" alt="<?= clean($doc['name']) ?>"
                                             style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--border);flex-shrink:0">
                                    <?php else: ?>
                                        <div class="doctor-avatar-circle"><?= clean($docInitials) ?></div>
                                    <?php endif; ?>
                                    <div class="doctor-header-info">
                                        <h3 class="doctor-name" style="font-size:1.1rem"><?= clean($doc['name']) ?></h3>
                                        <span class="dept-tag"><?= clean($doc['specialization']) ?></span>
                                        <p class="hospital-sub" style="font-size:0.82rem;color:var(--text-muted)">
                                            <?= clean($doc['hospital_name'] ?: 'Accredited Hospital') ?> • <?= (int)$doc['experience_years'] ?> yrs exp
                                        </p>
                                    </div>
                                </div>

                                <div class="doctor-rating-row" style="margin-bottom:12px">
                                    <span class="stars">★★★★★</span>
                                    <span class="review-count">4.9 • <?= clean($doc['qualification']) ?></span>
                                </div>

                                <div class="slots-box" style="margin-bottom:18px">
                                    <span class="slots-header">Available Consultation Hours:</span>
                                    <div class="slot-list">
                                        <?php if (!empty($docSlots)): ?>
                                            <?php foreach (array_slice($docSlots, 0, 3) as $ds): ?>
                                                <span class="time-pill">
                                                    <?= clean($ds['day_of_week']) ?>: <?= clean(format_time_slot($ds['start_time'])) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="time-pill">Mon-Fri 02:00 PM - 05:00 PM</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <a class="btn btn-primary btn-full" href="register/account-type.php">Book Appointment</a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Benefits Grid -->
        <section class="section benefits-section bg-subtle" id="about">
            <div class="container">
                <div class="section-head text-center">
                    <span class="section-tag">Why Choose Us</span>
                    <h2 class="section-title">Designed for modern patient care</h2>
                    <p class="section-subtitle">Experience high-efficiency healthcare coordination tailored for speed and convenience.</p>
                </div>

                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon">📱</div>
                        <h3 class="benefit-title">Instant Digital Booking</h3>
                        <p class="benefit-desc">Select timeslots and get confirmed booking tokens instantly without phone hold delays.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">🏥</div>
                        <h3 class="benefit-title">Multi-Hospital Access</h3>
                        <p class="benefit-desc">Compare available specialists and locations across the city from one single dashboard.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">📋</div>
                        <h3 class="benefit-title">Digital Record History</h3>
                        <p class="benefit-desc">Track past appointments, follow-ups, and verified doctors with zero paper trails.</p>
                    </div>

                    <div class="benefit-card">
                        <div class="benefit-icon">⏱️</div>
                        <h3 class="benefit-title">Zero Queue Time</h3>
                        <p class="benefit-desc">Skip reception line bottlenecks and arrive right at your scheduled consultation time.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Traditional vs HAMS Comparison -->
        <section class="section comparison-section">
            <div class="container">
                <div class="comparison-grid">
                    <div class="comparison-panel panel-traditional">
                        <div class="panel-header">
                            <span class="comp-badge old">Traditional Hospital Visit</span>
                            <h3 class="panel-title">Slow & Frustrating</h3>
                        </div>
                        <ul class="comp-list">
                            <li><span class="cross">✕</span> Hours wasted standing in registration queues</li>
                            <li><span class="cross">✕</span> Unclear doctor schedule and sudden cancellations</li>
                            <li><span class="cross">✕</span> Endless physical paperwork on every visit</li>
                            <li><span class="cross">✕</span> No central history for upcoming appointments</li>
                        </ul>
                    </div>

                    <div class="comparison-panel panel-hams">
                        <div class="panel-header">
                            <span class="comp-badge new">HAMS Digital Platform</span>
                            <h3 class="panel-title">Fast & Effortless</h3>
                        </div>
                        <ul class="comp-list">
                            <li><span class="check">✓</span> Confirmed booking within 60 seconds online</li>
                            <li><span class="check">✓</span> Live schedule visibility and verified doctor profiles</li>
                            <li><span class="check">✓</span> Digital passes and zero paperwork on arrival</li>
                            <li><span class="check">✓</span> Easy dashboard to reschedule or cancel anytime</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="section faq-section bg-subtle" id="faq">
            <div class="container max-w-md">
                <div class="section-head text-center">
                    <span class="section-tag">Help Center</span>
                    <h2 class="section-title">Frequently Asked Questions</h2>
                </div>

                <div class="faq-accordion">
                    <details class="faq-card" open>
                        <summary class="faq-question">
                            <span>Is there any extra booking fee on HAMS?</span>
                            <span class="faq-icon"></span>
                        </summary>
                        <div class="faq-answer">
                            <p>No. Using HAMS to schedule appointments is 100% free for all patients. You only pay standard consultation charges at the hospital reception or during your consultation.</p>
                        </div>
                    </details>

                    <details class="faq-card">
                        <summary class="faq-question">
                            <span>Can I reschedule or cancel my appointment?</span>
                            <span class="faq-icon"></span>
                        </summary>
                        <div class="faq-answer">
                            <p>Yes. You can manage, reschedule, or cancel your upcoming appointments directly from your patient dashboard at any time prior to your slot.</p>
                        </div>
                    </details>

                    <details class="faq-card">
                        <summary class="faq-question">
                            <span>What do I need to present at the hospital?</span>
                            <span class="faq-icon"></span>
                        </summary>
                        <div class="faq-answer">
                            <p>Simply present your digital booking confirmation token from your phone (accessible in your dashboard or email notification) when arriving at the reception desk.</p>
                        </div>
                    </details>

                    <details class="faq-card">
                        <summary class="faq-question">
                            <span>How are doctors verified on the platform?</span>
                            <span class="faq-icon"></span>
                        </summary>
                        <div class="faq-answer">
                            <p>Every doctor undergoes strict administrative credential verification (medical license number, qualification, experience checks) before their profile is approved on HAMS.</p>
                        </div>
                    </details>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="section cta-section" id="contact">
            <div class="container">
                <div class="cta-card">
                    <div class="cta-content">
                        <h2 class="cta-title">Ready for hassle-free healthcare?</h2>
                        <p class="cta-desc">Join thousands of patients saving time with verified doctor bookings across top hospital departments.</p>
                        <div class="cta-actions">
                            <a class="btn btn-white btn-lg" href="register/account-type.php">Get Started Now</a>
                            <a class="btn btn-ghost-white btn-lg" href="login.php">Sign In to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-col brand-col">
                <a class="brand" href="#home">
                    <span class="brand-icon">✚</span>
                    <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
                </a>
                <p class="footer-about">
                    Empowering patients and doctors with seamless digital appointment management and zero waiting room delays.
                </p>
            </div>

            <div class="footer-col">
                <h4 class="footer-title">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#hospitals">Hospitals</a></li>
                    <li><a href="#doctors">Featured Doctors</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-title">Portals</h4>
                <ul class="footer-links">
                    <li><a href="login.php">Patient Login</a></li>
                    <li><a href="login.php">Doctor Login</a></li>
                    <li><a href="register/account-type.php">Patient Registration</a></li>
                    <li><a href="register/doctor-registration.php">Doctor Application</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4 class="footer-title">Contact & Support</h4>
                <ul class="footer-links">
                    <li><span>📍 Kathmandu / Partner Clinics</span></li>
                    <li><a href="mailto:support@hams.local">support@hams.local</a></li>
                    <li><span>📞 +977 1 4200000 / 24/7 Care</span></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container footer-bottom-flex">
                <p>&copy; <?= date('Y') ?> HAMS - Hospital Appointment Management System. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive Scripts (Vanilla JS Only) -->
    <script>
        // Mobile Navigation Drawer Toggle
        const menuBtn = document.getElementById('mobileMenuBtn');
        const drawer = document.getElementById('mobileDrawer');
        const header = document.getElementById('siteHeader');

        if (menuBtn && drawer) {
            menuBtn.addEventListener('click', function () {
                const isOpen = drawer.classList.toggle('open');
                menuBtn.classList.toggle('active');
                menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            // Close mobile menu when a nav link is clicked
            document.querySelectorAll('.mobile-nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    drawer.classList.remove('open');
                    menuBtn.classList.remove('active');
                    menuBtn.setAttribute('aria-expanded', 'false');
                });
            });
        }

        // Header shadow on scroll
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Doctor Specialty Filter
        const filterBtns = document.querySelectorAll('#deptFilter .filter-btn');
        const doctorCards = document.querySelectorAll('.doctor-item');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                doctorCards.forEach(card => {
                    if (filter === 'all' || card.dataset.category === filter) {
                        card.style.display = 'block';
                        setTimeout(() => { card.style.opacity = '1'; }, 10);
                    } else {
                        card.style.opacity = '0';
                        setTimeout(() => { card.style.display = 'none'; }, 200);
                    }
                });
            });
        });
    </script>
</body>
</html>