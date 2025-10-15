<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '/home/notesao/vendor/autoload.php';  // Composer autoloader
require_once '/home/notesao/global_config.php';    // SMTP constants

function send_email($to, $subject, $html, $alt='') {
    $mail = new PHPMailer(true);
    try {
        /* SMTP server */
        $mail->isSMTP();
        $mail->Host       = smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = smtp_user;
        $mail->Password   = smtp_pass;
        $mail->SMTPSecure = smtp_secure;
        $mail->Port       = smtp_port;

        /* Recipients */
        $mail->setFrom(smtp_from, smtp_from_name);
        $mail->addAddress($to);

        /* Content */
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $alt ?: strip_tags($html);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: {$mail->ErrorInfo}");
        return false;
    }
}

