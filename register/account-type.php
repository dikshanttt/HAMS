<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Account Type | HAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/register.css?v=<?= filemtime('../assets/css/register.css'); ?>">
</head>
<body>
    <header class="auth-header">
        <a class="brand" href="../index.php">
            <span class="brand-icon">✚</span>
            <span class="brand-text">HAMS<span class="brand-sub">Care</span></span>
        </a>
        <a class="back-link" href="../index.php">← Back to Home</a>
    </header>

    <main class="page-shell">
        <section class="form-panel">
            <div class="form-header">
                <span class="eyebrow">Get Started</span>
                <h1>Select your account type</h1>
                <p>Choose whether you want to book medical appointments as a patient or join our verified medical specialist network as a doctor.</p>
            </div>

            <div class="card-grid" role="list">
                <button type="button" class="select-card active" data-account="patient" aria-pressed="true">
                    <div class="card-top">
                        <div class="card-icon">👤</div>
                        <div class="card-radio-indicator"></div>
                    </div>
                    <div class="card-copy">
                        <h2>Patient Account</h2>
                        <p>Instant access to find doctors, schedule appointments, view digital tokens, and track visits.</p>
                    </div>
                </button>

                <button type="button" class="select-card" data-account="doctor" aria-pressed="false">
                    <div class="card-top">
                        <div class="card-icon">🩺</div>
                        <div class="card-radio-indicator"></div>
                    </div>
                    <div class="card-copy">
                        <h2>Doctor Account</h2>
                        <p>Apply for verified specialist credentials, manage daily consultation queues, and patient schedules.</p>
                    </div>
                </button>
            </div>

            <div class="action-row">
                <button id="continueBtn" class="btn btn-primary btn-lg">
                    <span>Continue to Registration</span>
                    <span>→</span>
                </button>
            </div>

            <p class="form-footer">Already have an account? <a href="../login.php">Sign In</a></p>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>
</html>
