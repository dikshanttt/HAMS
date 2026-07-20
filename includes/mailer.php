<?php
/**
 * Minimal email wrapper.
 *
 * Uses PHP's built-in mail() as a placeholder so the flow works out of the box.
 * In practice most servers block/throttle mail(), so swap send_email()'s body
 * for PHPMailer + SMTP (Gmail, SendGrid, your college's SMTP, etc.) once you
 * pick a provider — nothing else in the codebase needs to change since every
 * caller just calls send_email($to, $subject, $body).
 *
 * composer require phpmailer/phpmailer   <-- only external dependency needed,
 * this is a library, not a framework, so it fits the "plain PHP" constraint.
 */

function send_email(string $to, string $subject, string $body): bool
{
    $headers = "From: no-reply@example.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // mail() returns false on most local dev setups without a configured MTA.
    // That's expected during development — check error logs, don't block the flow on it.
    return @mail($to, $subject, $body, $headers);
}

function send_doctor_verified_email(string $to, string $doctorName, string $loginId, string $tempPassword): void
{
    $subject = 'Your account has been verified';
    $body = "Hello Dr. $doctorName,\n\n"
          . "Your account has been verified by our admin team. You can now log in:\n\n"
          . "Login ID: $loginId\n"
          . "Temporary Password: $tempPassword\n\n"
          . "Please log in and change your password immediately.\n";
    send_email($to, $subject, $body);
}

function send_doctor_rejected_email(string $to, string $doctorName, string $reason): void
{
    $subject = 'Update on your registration';
    $body = "Hello Dr. $doctorName,\n\n"
          . "We were unable to verify your registration at this time.\n"
          . "Reason: $reason\n\n"
          . "Please contact the hospital admin office for more information.\n";
    send_email($to, $subject, $body);
}
