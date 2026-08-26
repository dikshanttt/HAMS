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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HAMS | Online Hospital Appointment Booking</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..800;1,9..144,400..800&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime('assets/css/style.css'); ?>">
</head>
<body class="home-page">

    <header class="topbar navbar">
        <a class="brand" href="#home">
            <span class="brand-mark">✚</span>
            <span>HAMS</span>
        </a>
        <nav class="nav-links site-nav" aria-label="Primary navigation">
            <a href="#home">Home</a>
            <a href="#hospitals">Hospitals</a>
            <a href="#doctors">Doctors</a>
            <a href="#appointments">Appointments</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
        </nav>
        <div class="nav-actions">
            <a class="login-link" href="login.php">Login</a>
            <a class="btn btn-primary btn-nav" href="register/account-type.php">Book Appointment</a>
        </div>
    </header>

    <main id="home" class="page-shell">
        <section class="hero-section">
            <div class="hero-copy">
                <p class="eyebrow">Trusted digital care platform</p>
                <h1>Book your hospital appointment without waiting in long queues</h1>
                <p>Find hospitals, choose departments, select doctors, and book appointments easily online.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="register/account-type.php">Book Appointment</a>
                    <a class="btn btn-secondary" href="#hospitals">Browse Hospitals</a>
                </div>
                <ul class="hero-highlights">
                    <li>Same-day appointment options</li>
                    <li>Verified doctors and hospitals</li>
                    <li>Secure booking from home</li>
                </ul>
            </div>

            <div class="hero-visual">
                <div class="hero-illustration">
                    <svg viewBox="0 0 480 380" role="img" aria-label="Doctor and patient illustration">
                        <rect x="40" y="40" width="400" height="300" rx="32" fill="#f2fbf5" />
                        <rect x="78" y="78" width="180" height="120" rx="20" fill="#ffffff" stroke="#dcf7e5" stroke-width="2" />
                        <rect x="96" y="102" width="56" height="56" rx="16" fill="#16a34a" />
                        <rect x="160" y="104" width="76" height="18" rx="9" fill="#d9fbe7" />
                        <rect x="160" y="132" width="60" height="14" rx="7" fill="#e8f8ef" />
                        <rect x="270" y="90" width="122" height="96" rx="18" fill="#ffffff" stroke="#dcf7e5" stroke-width="2" />
                        <rect x="286" y="108" width="56" height="44" rx="12" fill="#0f766e" />
                        <rect x="352" y="108" width="22" height="44" rx="8" fill="#16a34a" />
                        <circle cx="170" cy="250" r="58" fill="#fef3c7" />
                        <circle cx="168" cy="248" r="40" fill="#1f2937" />
                        <path d="M142 248c8-32 40-46 56-24 8 11 10 24 4 36-10 20-34 32-52 24-14-6-20-20-8-36Z" fill="#16a34a" />
                        <rect x="132" y="286" width="74" height="26" rx="13" fill="#16a34a" />
                        <circle cx="318" cy="256" r="47" fill="#dcfce7" />
                        <circle cx="318" cy="254" r="32" fill="#1f2937" />
                        <rect x="292" y="286" width="54" height="24" rx="12" fill="#0f766e" />
                        <path d="M220 250h52" stroke="#16a34a" stroke-width="8" stroke-linecap="round" />
                        <path d="M272 178l32 24" stroke="#16a34a" stroke-width="8" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="floating-card confirmed">
                    <strong>Appointment Confirmed</strong>
                    <span>Dr. Nisha • 2:30 PM</span>
                </div>
                <div class="floating-card hospitals">
                    <strong>Available Hospitals</strong>
                    <span><span class="status-dot"></span> 24 nearby clinics</span>
                </div>
                <div class="floating-card availability">
                    <strong>Doctor Availability</strong>
                    <span>Open today till 8 PM</span>
                </div>
            </div>
        </section>

        <!-- Stats Bar -->
        <div class="stats-banner">
            <div class="stat-item">
                <strong>15+</strong>
                <span>Partner Hospitals</span>
            </div>
            <div class="stat-item">
                <strong>120+</strong>
                <span>Verified Doctors</span>
            </div>
            <div class="stat-item">
                <strong>50,000+</strong>
                <span>Patients Served</span>
            </div>
            <div class="stat-item">
                <strong>4.9 ★</strong>
                <span>User Rating</span>
            </div>
        </div>

        <section class="search-section" id="hospitals">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Find care fast</p>
                    <h2>Search hospitals by name or location</h2>
                </div>
                <form class="search-box" action="#hospitals" method="GET">
                    <input type="text" name="query" placeholder="Search hospitals by name or location">
                    <button type="submit">Search</button>
                </form>
            </div>

            <div class="hospital-grid">
                <article class="hospital-card">
                    <div class="card-media green">
                        <span>City Care</span>
                    </div>
                    <div class="card-body">
                        <h3>City Care Hospital</h3>
                        <p>Downtown • Cardiology, Neurology</p>
                        <div class="meta-row">
                            <span>18 doctors</span>
                            <span>📍 1.2 km away</span>
                            <span>4.9 ★</span>
                        </div>
                        <a href="register/account-type.php" class="text-link">View Details & Book →</a>
                    </div>
                </article>
                <article class="hospital-card">
                    <div class="card-media teal">
                        <span>Greenview</span>
                    </div>
                    <div class="card-body">
                        <h3>Greenview Medical Center</h3>
                        <p>North Avenue • Pediatrics, ENT</p>
                        <div class="meta-row">
                            <span>12 doctors</span>
                            <span>📍 3.5 km away</span>
                            <span>4.8 ★</span>
                        </div>
                        <a href="register/account-type.php" class="text-link">View Details & Book →</a>
                    </div>
                </article>
                <article class="hospital-card">
                    <div class="card-media lime">
                        <span>LifeLine</span>
                    </div>
                    <div class="card-body">
                        <h3>LifeLine Hospital</h3>
                        <p>West End • Orthopedics, General</p>
                        <div class="meta-row">
                            <span>24 doctors</span>
                            <span>📍 4.1 km away</span>
                            <span>4.7 ★</span>
                        </div>
                        <a href="register/account-type.php" class="text-link">View Details & Book →</a>
                    </div>
                </article>
            </div>
        </section>

        <section class="process-section" id="appointments">
            <div class="section-heading centered">
                <p class="eyebrow">How it works</p>
                <h2>Book in four simple steps</h2>
            </div>
            <div class="steps-grid">
                <article class="step-card">
                    <span class="step-number">01</span>
                    <h3>Select Hospital</h3>
                    <p>Choose from trusted hospitals near you.</p>
                </article>
                <article class="step-card">
                    <span class="step-number">02</span>
                    <h3>Choose Department</h3>
                    <p>Pick the department that matches your need.</p>
                </article>
                <article class="step-card">
                    <span class="step-number">03</span>
                    <h3>Select Doctor</h3>
                    <p>Review doctors, ratings, and availability.</p>
                </article>
                <article class="step-card">
                    <span class="step-number">04</span>
                    <h3>Pick Date & Time</h3>
                    <p>Confirm your visit in seconds and receive updates.</p>
                </article>
            </div>
        </section>

        <section class="doctors-section" id="doctors">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Featured doctors</p>
                    <h2>Meet specialists ready to help</h2>
                </div>
                <div class="filter-tabs">
                    <button class="tab-btn active" type="button">All</button>
                    <button class="tab-btn" type="button">Cardiology</button>
                    <button class="tab-btn" type="button">Pediatrics</button>
                    <button class="tab-btn" type="button">Orthopedics</button>
                </div>
            </div>
            <div class="doctor-grid">
                <article class="doctor-card">
                    <div class="doctor-avatar">DR</div>
                    <h3>Dr. Asha Rao</h3>
                    <p class="dept">Cardiology</p>
                    <span>City Care Hospital</span>
                    <div class="meta-row">
                        <span>★★★★★</span>
                        <span>4.9 (120 reviews)</span>
                    </div>
                    <div class="slots-preview">
                        <span class="slots-label">Today's Available Slots:</span>
                        <div class="slot-pills">
                            <span class="slot-pill">02:30 PM</span>
                            <span class="slot-pill">04:00 PM</span>
                            <span class="slot-pill">05:30 PM</span>
                        </div>
                    </div>
                    <a class="btn btn-primary small" href="register/account-type.php">Book Appointment</a>
                </article>
                <article class="doctor-card">
                    <div class="doctor-avatar">DR</div>
                    <h3>Dr. Meera Patel</h3>
                    <p class="dept">Pediatrics</p>
                    <span>Greenview Medical Center</span>
                    <div class="meta-row">
                        <span>★★★★★</span>
                        <span>4.8 (98 reviews)</span>
                    </div>
                    <div class="slots-preview">
                        <span class="slots-label">Today's Available Slots:</span>
                        <div class="slot-pills">
                            <span class="slot-pill">03:00 PM</span>
                            <span class="slot-pill">06:15 PM</span>
                        </div>
                    </div>
                    <a class="btn btn-primary small" href="register/account-type.php">Book Appointment</a>
                </article>
                <article class="doctor-card">
                    <div class="doctor-avatar">DR</div>
                    <h3>Dr. Rohan Singh</h3>
                    <p class="dept">Orthopedics</p>
                    <span>LifeLine Hospital</span>
                    <div class="meta-row">
                        <span>★★★★★</span>
                        <span>4.7 (85 reviews)</span>
                    </div>
                    <div class="slots-preview">
                        <span class="slots-label">Today's Available Slots:</span>
                        <div class="slot-pills">
                            <span class="slot-pill">01:45 PM</span>
                            <span class="slot-pill">05:00 PM</span>
                        </div>
                    </div>
                    <a class="btn btn-primary small" href="register/account-type.php">Book Appointment</a>
                </article>
            </div>
        </section>

        <section class="benefits-section" id="about">
            <div class="section-heading centered">
                <p class="eyebrow">Why patients love HAMS</p>
                <h2>A smarter way to manage care</h2>
            </div>
            <div class="benefits-grid">
                <article class="benefit-card">
                    <h3>Book from home</h3>
                    <p>Schedule your visit from your phone or laptop in minutes.</p>
                </article>
                <article class="benefit-card">
                    <h3>Multiple hospitals</h3>
                    <p>Compare care options, departments, and doctors in one place.</p>
                </article>
                <article class="benefit-card">
                    <h3>Appointment history</h3>
                    <p>Keep track of visits and follow-ups without paper forms.</p>
                </article>
                <article class="benefit-card">
                    <h3>Save time</h3>
                    <p>Skip the queues and arrive prepared with your confirmed slot.</p>
                </article>
            </div>
        </section>

        <section class="comparison-section">
            <div class="comparison-card">
                <h3>Traditional hospital visit</h3>
                <ul>
                    <li>Long waiting lines</li>
                    <li>Limited availability info</li>
                    <li>Repeated paperwork</li>
                </ul>
            </div>
            <div class="comparison-card highlighted">
                <h3>Online appointment system</h3>
                <ul>
                    <li>Instant booking confirmation</li>
                    <li>Flexible doctor choices</li>
                    <li>Simple, digital follow-up</li>
                </ul>
            </div>
        </section>

        <!-- Dynamic FAQ Accordion -->
        <section class="faq-section" id="faq">
            <div class="section-heading centered">
                <p class="eyebrow">Got questions?</p>
                <h2>Frequently Asked Questions</h2>
            </div>
            <div class="faq-grid">
                <details class="faq-item" open>
                    <summary>Is there an extra fee for booking online?</summary>
                    <p>No! Booking through HAMS is completely free for patients. You only pay standard consultation charges at the hospital.</p>
                </details>
                <details class="faq-item">
                    <summary>Can I reschedule or cancel my booking?</summary>
                    <p>Yes, you can easily manage, reschedule, or cancel your upcoming appointments directly from your patient dashboard.</p>
                </details>
                <details class="faq-item">
                    <summary>What documents do I need to bring to the hospital?</summary>
                    <p>Bring your digital booking token (sent to your account/email) and any previous medical records or doctor prescriptions relevant to your visit.</p>
                </details>
            </div>
        </section>

        <section class="cta-section" id="contact">
            <h2>Ready to book your appointment?</h2>
            <p>Start your care journey with HAMS and secure your next visit in a few clicks.</p>
            <a class="btn btn-primary" href="register/account-type.php">Get Started</a>
        </section>
    </main>

    <footer class="site-footer">
        <div>
            <a class="brand footer-brand" href="#home">
                <span class="brand-mark">✚</span>
                <span>HAMS</span>
            </a>
            <p>Modern healthcare booking for patients who value convenience and trust.</p>
        </div>
        <div>
            <h3>Quick links</h3>
            <ul>
                <li><a href="#home">Home</a></li>
                <li><a href="#hospitals">Hospitals</a></li>
                <li><a href="#doctors">Doctors</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>
        </div>
        <div>
            <h3>Contact</h3>
            <ul>
                <li><a href="mailto:support@hams.local">support@hams.local</a></li>
                <li>+91 98765 43210</li>
                <li>24/7 Care Support</li>
            </ul>
        </div>
    </footer>
</body>
</html>