<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Application | HAMS</title>
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
    <main class="page-shell">
        <section class="form-panel">
            <div class="form-card">
                <div class="form-header">
                    <p class="eyebrow">Doctor Application</p>
                    <h1>Apply for a doctor account</h1>
                    <p>Provide your personal and professional details for admin review.</p>
                </div>

                <div class="info-banner">
                    <p>Doctor accounts require admin verification. After approval, login credentials will be provided.</p>
                </div>

                <form id="doctorForm" class="register-form" novalidate>
                    <div class="section-label">Personal Information</div>
                    <div class="form-row">
                        <label for="doctorName">Full Name</label>
                        <input id="doctorName" name="doctorName" type="text" placeholder="Dr. Alex Morgan" required>
                    </div>
                    <div class="form-row">
                        <label for="doctorEmail">Email Address</label>
                        <input id="doctorEmail" name="doctorEmail" type="email" placeholder="alex@example.com" required>
                    </div>
                    <div class="form-row">
                        <label for="doctorPhone">Phone Number</label>
                        <input id="doctorPhone" name="doctorPhone" type="tel" placeholder="(123) 456-7890" required>
                    </div>

                    <div class="section-label">Professional Information</div>
                    <div class="form-row">
                        <label for="doctorLicense">Medical License Number</label>
                        <input id="doctorLicense" name="doctorLicense" type="text" placeholder="LIC-123456" required>
                    </div>
                    <div class="form-row">
                        <label for="doctorSpecialization">Specialization</label>
                        <input id="doctorSpecialization" name="doctorSpecialization" type="text" placeholder="Cardiology" required>
                    </div>
                    <div class="form-row form-grid-2">
                        <div>
                            <label for="doctorQualification">Qualification</label>
                            <input id="doctorQualification" name="doctorQualification" type="text" placeholder="MD, MBBS" required>
                        </div>
                        <div>
                            <label for="doctorExperience">Years of Experience</label>
                            <input id="doctorExperience" name="doctorExperience" type="number" min="0" placeholder="10" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="doctorHospital">Hospital Affiliation</label>
                        <input id="doctorHospital" name="doctorHospital" type="text" placeholder="City General Hospital" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Submit Application</button>
                </form>
            </div>
        </section>
    </main>

    <script src="../assets/js/register.js"></script>
</body>
</html>
