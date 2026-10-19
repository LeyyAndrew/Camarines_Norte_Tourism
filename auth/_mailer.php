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
      (the folder name must be exactly "PHPMailer" - Linux/Render
      cares about capital letters)
   2. LOCAL (XAMPP): put MAIL_USER and MAIL_PASS in Tourism_System/.env
      ON RENDER: add MAIL_USER, MAIL_PASS (and optionally MAIL_NAME)
      in the Render dashboard > your service > Environment.
   3. MAIL_PASS is a Gmail APP PASSWORD, not your normal password.
      Google account > Security > 2-Step Verification (turn on),
      then search "App passwords", create one, paste the 16 letters.

   Never commit .env to GitHub.
   =================================================================== */

/* ---------- read the secrets ----------
   1) from Tourism_System/.env  (local / XAMPP)
   2) otherwise from the server's environment variables (Render)
   The password never lives in this file, so it is safe to commit. */
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

    // 1) .env file
    if (isset($vars[$key]) && $vars[$key] !== '') {
        return $vars[$key];
    }

    // 2) real environment variables (Render)
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    return $default;
}

define('MAIL_USER', auth_env('MAIL_USER'));
define('MAIL_PASS', str_replace(' ', '', auth_env('MAIL_PASS')));   // spaces removed for you
define('MAIL_NAME', auth_env('MAIL_NAME', 'Explore Camarines Norte'));

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/* Returns true if Gmail accepted the message, false otherwise.
   The reason for a failure goes to the error log
   (Apache log locally, the Logs tab on Render). */
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
        $mail->Timeout    = 15;   // give up after 15s instead of hanging if the port is blocked

        /* Gmail only sends "From" the account you logged in with. */
        $mail->setFrom(MAIL_USER, MAIL_NAME);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body    = $body;      // plain text

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('mailer: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        return false;
    }
}