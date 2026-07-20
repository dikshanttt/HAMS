<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/db.php';

require_login(['admin']);

$db = getDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $db->prepare(
        'SELECT u.id, u.email, dp.name
         FROM users u JOIN doctor_profiles dp ON dp.user_id = u.id
         WHERE u.id = ? AND u.role = ? AND dp.verification_status = ?'
    );
    $stmt->execute([$userId, 'doctor', 'pending']);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        $message = 'Doctor not found or already processed.';
    } elseif ($action === 'approve') {
        $loginId = generate_doctor_login_id($db);
        $tempPassword = generate_temp_password();
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'UPDATE users SET doctor_login_id = ?, password_hash = ?, status = ?, force_password_change = TRUE WHERE id = ?'
            );
            $stmt->execute([$loginId, $hash, 'active', $userId]);

            $stmt = $db->prepare(
                'UPDATE doctor_profiles SET verification_status = ?, verified_at = CURRENT_TIMESTAMP WHERE user_id = ?'
            );
            $stmt->execute(['verified', $userId]);

            $db->commit();

            send_doctor_verified_email($doctor['email'], $doctor['name'], $loginId, $tempPassword);
            $message = "Approved. Login ID {$loginId} emailed to {$doctor['email']}.";
        } catch (Exception $e) {
            $db->rollBack();
            $message = 'Something went wrong while approving.';
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? 'Not specified');

        $stmt = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute(['rejected', $userId]);

        $stmt = $db->prepare(
            'UPDATE doctor_profiles SET verification_status = ?, rejection_reason = ? WHERE user_id = ?'
        );
        $stmt->execute(['rejected', $reason, $userId]);

        send_doctor_rejected_email($doctor['email'], $doctor['name'], $reason);
        $message = "Rejected registration for Dr. {$doctor['name']}.";
    }
}

$stmt = $db->query(
    "SELECT u.id, u.email, dp.name, dp.specialization, dp.license_no, dp.qualification,
            dp.experience_years, dp.image_path
     FROM users u JOIN doctor_profiles dp ON dp.user_id = u.id
     WHERE dp.verification_status = 'pending'
     ORDER BY u.created_at ASC"
);
$pendingDoctors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Doctors</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <strong>HMS Admin</strong>
        <div><a href="dashboard.php">Dashboard</a><a href="../logout.php">Logout</a></div>
    </div>
    <div class="container wide">
        <h1>Pending Doctor Verifications</h1>

        <?php if ($message): ?>
            <div class="success"><?= clean($message) ?></div>
        <?php endif; ?>

        <?php if (empty($pendingDoctors)): ?>
            <p>No pending doctor registrations right now.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Photo/ID</th><th>Name</th><th>Email</th><th>Specialization</th>
                    <th>License No.</th><th>Qualification</th><th>Experience</th><th>Action</th>
                </tr>
                <?php foreach ($pendingDoctors as $doc): ?>
                    <tr>
                        <td>
                            <?php if ($doc['image_path']): ?>
                                <img src="../<?= clean($doc['image_path']) ?>" alt="" width="50">
                            <?php endif; ?>
                        </td>
                        <td><?= clean($doc['name']) ?></td>
                        <td><?= clean($doc['email']) ?></td>
                        <td><?= clean($doc['specialization']) ?></td>
                        <td><?= clean($doc['license_no']) ?></td>
                        <td><?= clean($doc['qualification']) ?></td>
                        <td><?= (int)$doc['experience_years'] ?> yrs</td>
                        <td>
                            <form method="POST" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$doc['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit">Approve</button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return addReason(this);">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$doc['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="reason" class="reason-field">
                                <button type="submit" class="danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <script>
        function addReason(form) {
            const reason = prompt('Reason for rejection:');
            if (reason === null || reason.trim() === '') return false;
            form.querySelector('.reason-field').value = reason;
            return true;
        }
    </script>
</body>
</html>
