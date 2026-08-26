<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_login(['admin']);

$db = getDB();
$stats = $db->query(
    "SELECT
        COUNT(*) FILTER (WHERE role = 'patient') AS patients,
        COUNT(*) FILTER (WHERE role = 'doctor') AS doctors,
        COUNT(*) FILTER (WHERE role = 'doctor' AND status = 'active') AS active_doctors,
        COUNT(*) FILTER (WHERE role = 'doctor' AND status = 'pending') AS pending_doctors
     FROM users"
)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="dashboard.php">
                <span class="brand-mark">+</span>
                <span><strong>HMS</strong><small>Administration</small></span>
            </a>
            <nav class="admin-nav" aria-label="Admin navigation">
                <a class="active" href="dashboard.php"><span>01</span>Overview</a>
                <a href="verify_doctors.php"><span>02</span>Verify doctors</a>
            </nav>
            <div class="sidebar-footer">
                <span class="status-dot"></span>
                <span>System operational</span>
                <a href="../logout.php">Log out</a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <p class="admin-kicker">Hospital operations / <?= date('F j, Y') ?></p>
                    <h1>Good morning, Admin.</h1>
                    <p class="admin-subtitle">Here is the latest activity across your hospital network.</p>
                </div>
                <div class="admin-profile" aria-label="Signed in as administrator">
                    <span class="admin-avatar">SA</span>
                    <span><strong>Super Admin</strong><small>Administrator</small></span>
                </div>
            </header>

            <section class="admin-stats" aria-label="Hospital overview">
                <article class="admin-stat admin-stat-highlight">
                    <span class="stat-label">Needs your attention</span>
                    <strong><?= (int)$stats['pending_doctors'] ?></strong>
                    <span class="stat-note">doctor applications pending</span>
                </article>
                <article class="admin-stat">
                    <span class="stat-label">Active doctors</span>
                    <strong><?= (int)$stats['active_doctors'] ?></strong>
                    <span class="stat-note">verified and ready to serve</span>
                </article>
                <article class="admin-stat">
                    <span class="stat-label">All doctors</span>
                    <strong><?= (int)$stats['doctors'] ?></strong>
                    <span class="stat-note">including pending applications</span>
                </article>
                <article class="admin-stat">
                    <span class="stat-label">Registered patients</span>
                    <strong><?= (int)$stats['patients'] ?></strong>
                    <span class="stat-note">active patient accounts</span>
                </article>
            </section>

            <section class="admin-focus">
                <div class="focus-copy">
                    <span class="section-label">Verification desk</span>
                    <h2>Keep the care team moving.</h2>
                    <p>Review new doctor registrations, validate their credentials, and grant access to the hospital platform.</p>
                    <a class="btn admin-primary-action" href="verify_doctors.php">Review applications <span aria-hidden="true">-&gt;</span></a>
                </div>
                <div class="focus-figure" aria-hidden="true">
                    <span class="figure-ring figure-ring-one"></span>
                    <span class="figure-ring figure-ring-two"></span>
                    <span class="figure-cross">+</span>
                    <span class="figure-caption">CARE<br>COORDINATED</span>
                </div>
            </section>

            <section class="admin-footer-note">
                <div>
                    <span class="section-label">Quick access</span>
                    <h2>One clear place to manage your team.</h2>
                </div>
                <a class="text-link" href="verify_doctors.php">Open doctor verification <span aria-hidden="true">-&gt;</span></a>
            </section>
        </main>
    </div>
</body>
</html>
