<?php
/* Temporary test file. DELETE it after testing.
   Put it in:  auth/test_mail.php
   Open:       http://localhost/Tourism_System/auth/test_mail.php */

require_once __DIR__ . '/_mailer.php';

use PHPMailer\PHPMailer\PHPMailer;

echo '<pre>';
echo 'PHP version : ' . PHP_VERSION . "\n";
echo 'php.ini     : ' . (php_ini_loaded_file() ?: 'NONE') . "\n";
echo 'openssl     : ' . (extension_loaded('openssl') ? 'ON' : 'OFF  <-- must be ON') . "\n";
echo 'MAIL_USER   : ' . (MAIL_USER ?: 'EMPTY  <-- .env not read') . "\n";
echo 'MAIL_PASS   : length ' . strlen(MAIL_PASS) . " (should be 16)\n";
echo "-----------------------------------------\n";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug   = 2;
    $mail->Debugoutput = 'echo';
    $mail->isSMTP();
    $mail->Host        = 'smtp.gmail.com';
    $mail->SMTPAuth    = true;
    $mail->Username    = MAIL_USER;
    $mail->Password    = MAIL_PASS;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = 587;
    $mail->Timeout     = 15;
    $mail->setFrom(MAIL_USER, MAIL_NAME);
    $mail->addAddress(MAIL_USER);      // sends to yourself
    $mail->Subject = 'Test';
    $mail->Body    = 'It works!';
    $mail->send();
    echo "\nSENT!";
} catch (Throwable $e) {
    echo "\nFAILED: " . $e->getMessage();
}
echo '</pre>';