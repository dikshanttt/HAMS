<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/db.php';

require_login(['admin']);

$db = getDB();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    /*
     * Find the doctor only if:
     * - user exists
     * - user is a doctor
     * - doctor is still pending
     */
    $stmt = $db->prepare(
        'SELECT
            u.id,
            u.email,
            dp.name
         FROM users u
         JOIN doctor_profiles dp ON dp.user_id = u.id
         WHERE u.id = ?
           AND u.role = ?
           AND dp.verification_status = ?'
    );
    $stmt->execute([
        $userId,
        'doctor',
        'pending'
    ]);

    $doctor = $stmt->fetch();
    if (!$doctor) {

        $message = 'Doctor not found or already processed.';
        $messageType = 'error';
    } elseif ($action === 'approve') {
        /*
         * Generate doctor's login credentials.
         */
        $loginId = generate_doctor_login_id($db);
        $tempPassword = generate_temp_password();
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);
        try {
            $db->beginTransaction();
            /*
             * Activate doctor account and assign login credentials.
             */
            $stmt = $db->prepare(
                'UPDATE users
                 SET doctor_login_id = ?,
                     password_hash = ?,
                     status = ?,
                     force_password_change = TRUE
                 WHERE id = ?'
            );
            $stmt->execute([
                $loginId,
                $hash,
                'active',
                $userId
            ]);
            /*
             * Mark doctor as verified.
             */
            $stmt = $db->prepare(
                'UPDATE doctor_profiles
                 SET verification_status = ?,
                     verified_at = CURRENT_TIMESTAMP,
                     verified_by_admin_id = ?
                 WHERE user_id = ?'
            );
            $stmt->execute([
                'verified',
                current_user_id(),
                $userId
            ]);
            /*
             * Commit database changes first.
             */
            $db->commit();
            /*
             * Send credentials after successful database commit.
             */
            try {
                send_doctor_verified_email(
                    $doctor['email'],
                    $doctor['name'],
                    $loginId,
                    $tempPassword
                );
                $message = "Doctor approved successfully. Login ID {$loginId} has been emailed to {$doctor['email']}.";
                $messageType = 'success';
            } catch (Throwable $e) {
                /*
                 * Doctor is already approved even if email fails.
                 */
                $message = "Doctor approved successfully, but the email could not be sent. Login ID: {$loginId}";
                $messageType = 'error';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = 'Something went wrong while approving the doctor.';
            $messageType = 'error';
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $reason = 'Not specified';
        }
        try {
            $db->beginTransaction();
            /*
             * Reject the user account.
             */
            $stmt = $db->prepare(
                'UPDATE users
                 SET status = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                'rejected',
                $userId
            ]);
            /*
             * Reject the doctor application and save reason.
             */
            $stmt = $db->prepare(
                'UPDATE doctor_profiles
                 SET verification_status = ?,
                     rejection_reason = ?,
                     verified_at = CURRENT_TIMESTAMP,
                     verified_by_admin_id = ?
                 WHERE user_id = ?'
            );
            $stmt->execute([
                'rejected',
                $reason,
                current_user_id(),
                $userId
            ]);
            $db->commit();

            /*
             * Send rejection email after database update.
             */
            try {
                send_doctor_rejected_email(
                    $doctor['email'],
                    $doctor['name'],
                    $reason
                );
                $message = "Registration rejected for Dr. {$doctor['name']}.";
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = "Registration rejected for Dr. {$doctor['name']}, but the email could not be sent.";
                $messageType = 'error';
            }
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = 'Something went wrong while rejecting the doctor.';
            $messageType = 'error';
        }
    }
}
/*
 * Get all pending doctor registrations.
 */
$stmt = $db->query(
    "SELECT
        u.id,
        u.email,
        dp.name,
        dp.specialization,
        dp.license_no,
        dp.phone,
        dp.qualification,
        dp.experience_years,
        dp.image_path
     FROM users u
     JOIN doctor_profiles dp ON dp.user_id = u.id
     WHERE dp.verification_status = 'pending'
       AND u.role = 'doctor'
     ORDER BY u.created_at ASC"
);

$pendingDoctors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Doctors | HMS Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-page verification-page">
    <div class="navbar">
        <strong>HMS Admin</strong>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container wide">
        <h1>Pending Doctor Verifications</h1>
        <?php if ($message): ?>
            <div class="<?= $messageType === 'error' ? 'error-message' : 'success' ?>">
                <?= clean($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pendingDoctors)): ?>
            <p>No pending doctor registrations right now.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialization</th>
                        <th>License No.</th>
                        <th>Qualification</th>
                        <th>Experience</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($pendingDoctors as $doc): ?>
                        <tr>
                            <td>
                                <?php if (!empty($doc['image_path'])): ?>
                                    <img
                                        src="../<?= clean($doc['image_path']) ?>"
                                        alt="Doctor photo"
                                        width="50">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>

                            </td>
                            <td>
                                <?= clean($doc['name']) ?>
                            </td>
                            <td>
                                <?= clean($doc['email']) ?>
                            </td>
                            <td>
                                <?= clean($doc['phone']) ?>
                            </td>
                            <td>
                                <?= clean($doc['specialization']) ?>
                            </td>
                            <td>
                                <?= clean($doc['license_no']) ?>
                            </td>
                            <td>
                                <?= clean($doc['qualification']) ?>
                            </td>
                            <td>
                                <?= (int)$doc['experience_years'] ?> yrs
                            </td>
                            <td>

                                <!-- APPROVE -->
                                <form method="POST" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)$doc['id'] ?>">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve">
                                    <button type="submit">
                                        Approve
                                    </button>
                                </form>

                                <!-- REJECT -->
                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return addReason(this);">
                                    <?= csrf_field() ?>
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int)$doc['id'] ?>">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject">
                                    <input
                                        type="hidden"
                                        name="reason"
                                        class="reason-field">
                                    <button
                                        type="submit"
                                        class="danger">
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    </div>


    <script>
        function addReason(form) {
            const reason = prompt('Reason for rejection:');
            if (reason === null || reason.trim() === '') {
                return false;
            }
            form.querySelector('.reason-field').value = reason.trim();
            return true;
        }
    </script>

</body>

</html>