<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

function send_email(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // Using your provided credentials
        $mail->Username   = getenv('SMTP_USER') ?: 'dikshantlama77@gmail.com'; 
        $mail->Password   = getenv('SMTP_PASS') ?: 'dikshant@123'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('dikshantlama77@gmail.com', 'HAMS Admin');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();
    } catch (Exception $e) {
        // Optional: error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function send_doctor_verified_email(string $to, string $doctorName, string $loginId, string $tempPassword): bool
{
    $subject = 'Your account has been verified';
    $body = "Hello Dr. $doctorName,\n\n"
          . "Your account has been verified by our admin team. You can now log in:\n\n"
          . "Login ID: $loginId\n"
          . "Temporary Password: $tempPassword\n\n"
          . "Please log in and change your password immediately.\n";
    return send_email($to, $subject, $body);
}

function send_doctor_rejected_email(string $to, string $doctorName, string $reason): bool
{
    $subject = 'Update on your registration';
    $body = "Hello Dr. $doctorName,\n\n"
          . "We were unable to verify your registration at this time.\n"
          . "Reason: $reason\n\n"
          . "Please contact the hospital admin office for more information.\n";
    return send_email($to, $subject, $body);
}