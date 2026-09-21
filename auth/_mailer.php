<?php
/* ===================================================================
   auth/_mailer.php

   Sends email through Gmail's SMTP server using PHPMailer.

   WHY: PHP's mail() needs a mail server on the machine. XAMPP has
   none, so mail() just returns false and nothing is sent.

   SETUP
   1. Download PHPMailer: https://github.com/PHPMailer/PHPMailer
      (Code > Download ZIP). Copy its "src" folder to:
          auth/PHPMailer/src/
   2. Put MAIL_USER and MAIL_PASS in Tourism_System/.env
      (see .env.example). Nothing to edit in this file.
   3. MAIL_PASS is a Gmail APP PASSWORD, not your normal password.
      Google account > Security > 2-Step Verification (turn on),
      then search "App passwords", create one, paste the 16 letters
      into .env.

   Never commit .env to GitHub.
   =================================================================== */

/* ---------- read the secrets from Tourism_System/.env ----------
   The password lives in .env, never in this file, so this file is
   safe to commit. .env must be listed in .gitignore. */
function auth_env($key, $default = '') {
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $file = __DIR__ . '/../.env';

        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) { continue; }

                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim(trim($v), "\"'");   // allow quotes around values
            }
        }
    }

    return $vars[$key] ?? $default;
}

define('MAIL_USER', auth_env('MAIL_USER'));
define('MAIL_PASS', str_replace(' ', '', auth_env('MAIL_PASS')));   // spaces removed for you
define('MAIL_NAME', auth_env('MAIL_NAME', 'Explore Camarines Norte'));

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/* Returns true if Gmail accepted the message, false otherwise.
   The reason for a failure goes to the Apache error log. */
function auth_send_mail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        /* Gmail only sends "From" the account you logged in with. */
        $mail->setFrom(MAIL_USER, MAIL_NAME);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body    = $body;      // plain text

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('mailer: ' . $mail->ErrorInfo);
        return false;
    }
}