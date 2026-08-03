<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration | HAMS</title>
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
    <main class="page-shell">
        <section class="form-panel">
            <div class="form-card">
                <div class="form-header">
                    <p class="eyebrow">Patient Registration</p>
                    <h1>Create your patient account</h1>
                    <p>Enter your details to book appointments and manage your healthcare journey.</p>
                </div>

                <form id="patientForm" class="register-form" novalidate>
                    <div class="form-row">
                        <label for="patientName">Full Name</label>
                        <input id="patientName" name="patientName" type="text" placeholder="Jane Doe" required>
                    </div>
                    <div class="form-row">
                        <label for="patientEmail">Email Address</label>
                        <input id="patientEmail" name="patientEmail" type="email" placeholder="jane@example.com" required>
                    </div>
                    <div class="form-row">
                        <label for="patientPhone">Phone Number</label>
                        <input id="patientPhone" name="patientPhone" type="tel" placeholder="(123) 456-7890" required>
                    </div>
                    <div class="form-row form-grid-2">
                        <div>
                            <label for="patientDob">Date of Birth</label>
                            <input id="patientDob" name="patientDob" type="date" required>
                        </div>
                        <div>
                            <label for="patientGender">Gender</label>
                            <select id="patientGender" name="patientGender" required>
                                <option value="">Select gender</option>
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                                <option value="other">Other</option>
                                <option value="prefer_not">Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="patientPassword">Password</label>
                        <input id="patientPassword" name="patientPassword" type="password" placeholder="Create a password" required>
                    </div>
                    <div class="form-row">
                        <label for="patientConfirmPassword">Confirm Password</label>
                        <input id="patientConfirmPassword" name="patientConfirmPassword" type="password" placeholder="Confirm password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Create Patient Account</button>
                </form>

                <p class="form-footer">Already have an account? <a href="../login.php">Login</a></p>
            </div>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>
</html>
