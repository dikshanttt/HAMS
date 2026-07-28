<?php
require_once __DIR__ . '/includes/auth.php';
 
if (current_user_id()) {
    redirect('/' . current_role() . '/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hospital Appointment Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="home-page">
    <header class="navbar">
        <a class="brand" href="#home">Hospital Appointment Management System</a>
        <nav class="site-nav">
            <a href="#about">About</a>
            <a href="#features">What We Do</a>
            <a href="#help">Help</a>
            <a href="login.php" class="btn-nav" style="color:white">Login</a>
        </nav>
    </header>
 
    <div class="pulse-strip" aria-hidden="true">
        <svg viewBox="0 0 1180 46" preserveAspectRatio="none">
            <line class="pulse-baseline" x1="0" y1="23" x2="1180" y2="23" />
            <path d="M0,23 L470,23 L500,23 L520,6 L545,40 L570,14 L590,23 L1180,23" />
        </svg>
    </div>
 
    <main class="page-shell" id="home">
        <section class="hero-section" id="about">
            <div class="hero-content">
                <p class="eyebrow">Hospital Management</p>
                <h1>Register first to start using HMS.</h1>
                <p>Doctors must register and wait for verification before login access is allowed. Patients can register immediately to request appointments and stay connected with their care team.</p>
                <div class="hero-actions">
                    <a href="register/doctor.php" class="btn btn-main">Register as Doctor</a>
                    <a href="register/patient.php" class="btn btn-secondary">Register as Patient</a>
                </div>
                <div class="hero-note">
                    <p><strong>Doctors:</strong> Register now and wait for admin verification. Only verified doctors can login.</p>
                    <p><strong>Patients:</strong> Register now to book a doctor appointment and keep your visit details in one place.</p>
                </div>
            </div>
            <div class="hero-sidecard">
                <div class="hero-card">
                    <h2>Doctor verification</h2>
                    <p>Doctor accounts require verification after registration. Once approved, doctors can login to manage patients and appointments.</p>
                    <a href="register/doctor.php" class="btn btn-card">Register as Doctor</a>
                </div>
            </div>
        </section>
 
        <section class="features-section" id="features">
            <div class="section-header">
                <h2>What We Do</h2>
                <p>Keep hospital workflows simple with care coordination, appointment booking, and secure patient record management.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <h3>Doctor access</h3>
                    <p>Secure login for doctors to manage schedules, view patient histories, and write notes.</p>
                </div>
                <div class="feature-card">
                    <h3>Patient booking</h3>
                    <p>Patient registration makes it easy to request appointments and keep treatment details organized.</p>
                </div>
                <div class="feature-card">
                    <h3>Support ready</h3>
                    <p>Our support team helps patients register and assists doctors with account setup.</p>
                </div>
            </div>
        </section>
 
        <section class="help-section" id="help">
            <div class="help-card">
                <h2>Need help getting started?</h2>
                <p>If you are a patient, register to book an appointment with a doctor. If you're a doctor, login to your dashboard to manage your schedule and patients.</p>
                <a href="mailto:support@hms.local" class="btn btn-main">Contact Support</a>
            </div>
        </section>
    </main>
    <!-- Main page footer to go right after </main> -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <span class="logo-text">HMS</span>
                <p>Secure Hospital Management Platform</p>
            </div>
            
            <div class="footer-meta">
                <ul class="compliance-links">
                    <li><a href="#privacy">Privacy Policy</a></li>
                    <li><a href="#terms">Terms of Service</a></li>
                    <li><a href="#hipaa">HIPAA Compliance</a></li>
                </ul>
                <p class="copyright">&copy; <?php echo date('Y'); ?> HMS. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>