<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account | HAMS</title>
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
    <main class="page-shell">
        <section class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">Create Your Account</p>
                <h1>Choose your account type to continue</h1>
                <p class="hero-text">Start your hospital appointment experience with the right access — patient booking or doctor application.</p>
            </div>
        </section>

        <section class="select-panel">
            <div class="card-grid" role="list">
                <button type="button" class="select-card active" data-account="patient" aria-pressed="true">
                    <div class="card-icon">
                        <span class="icon user-icon" aria-hidden="true">👤</span>
                    </div>
                    <div class="card-copy">
                        <h2>Patient Account</h2>
                        <p>Book hospital appointments, manage visits, and track appointment history.</p>
                    </div>
                </button>
                <button type="button" class="select-card" data-account="doctor" aria-pressed="false">
                    <div class="card-icon">
                        <span class="icon doctor-icon" aria-hidden="true">🩺</span>
                    </div>
                    <div class="card-copy">
                        <h2>Doctor Account</h2>
                        <p>Apply to join the platform and provide healthcare services.</p>
                    </div>
                </button>
            </div>

            <div class="action-row">
                <button id="continueBtn" class="btn btn-primary">Continue</button>
            </div>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>
</html>
