<?php
/* ===================================================================
   auth/login_process.php

   Two forms post here: auth/login.php and the sign-in modal in
   includes/footer.php.

   The old version ended a failed login with `echo "Invalid Email or
   Password";` — which leaves the user staring at those four words on
   a blank white page with no way back. It now sends them to the page
   they came from with ?error=badlogin in the URL, which is what the
   modal's .auth-error banner is waiting for.
   =================================================================== */

session_start();

require '../config/database.php';

function back_with_error(string $message, string $code): void
{
    $_SESSION['auth_error'] = $message;

    $fallback = '../homepage.php';
    $referer  = $_SERVER['HTTP_REFERER'] ?? '';
    $target   = $fallback;

    if ($referer !== '') {
        $host = parse_url($referer, PHP_URL_HOST);
        if ($host === null || $host === ($_SERVER['HTTP_HOST'] ?? '')) {
            $target = strtok($referer, '?');
        }
    }

    header('Location: ' . $target . '?error=' . $code);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    back_with_error('Please enter your email and password.', 'badlogin');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* One message for both a wrong email and a wrong password, on
   purpose. Two different messages tell a stranger which addresses
   have accounts here. */
if (!$user || !password_verify($password, $user['password'])) {
    back_with_error('That email and password do not match.', 'badlogin');
}

/* ---------- suspended accounts ----------

   CHECKED AFTER THE PASSWORD, NOT BEFORE. Answering "that account is
   suspended" to anyone who types an email address confirms the
   address is registered here — the exact leak the shared error
   message above exists to prevent. Only someone who has already
   proven they own the account gets told why they are being turned
   away.

   isset() rather than a bare read: a database where the ALTER has
   not been run has no status column, and every login would otherwise
   die on an undefined index. */
if (isset($user['status']) && $user['status'] !== 'active') {
    back_with_error('This account has been suspended. Contact the tourism office.', 'suspended');
}

/* ---------- signed in ----------
   session_regenerate_id swaps the session cookie for a fresh one now
   that the session means something. It costs one line and closes off
   session fixation, where an attacker sets the cookie beforehand and
   rides in on it. */
session_regenerate_id(true);

$_SESSION['user_id']   = $user['id'];
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname']  = $user['lastname'] ?? '';
$_SESSION['role']      = $user['role'] ?? 'user';

/* ---------- stamp the login ----------

   Wrapped in its own try/catch and deliberately ignored on failure.
   A missing last_login column is a reason for the admin panel to
   show a dash; it is not a reason to refuse someone entry to the
   site. Anything that is not essential to signing in must not be
   able to break signing in.

   array_key_exists, not isset — a column that exists but is null,
   which is every account before its first login, is exactly the case
   that needs the UPDATE most, and isset() returns false for null. */
if (array_key_exists('last_login', $user)) {
    try {
        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
            ->execute([':id' => $user['id']]);
    } catch (PDOException $e) {
        error_log('last_login stamp failed: ' . $e->getMessage());
    }
}

/* ---------- where to send them ----------
   auth-gate.js writes a hidden "next" field when someone is bounced
   here from a link they clicked. Sending them back to it is the
   difference between "sign in, carry on" and "sign in, now go find
   that page again yourself".

   THE PATTERN IS THE SECURITY. Without it this is an open redirect:
   a link to your login page carrying next=//somewhere-else lets an
   attacker forward people off your site immediately after a real
   sign-in on the real domain, which is exactly when they are least
   suspicious. Only a bare filename in the project root gets through
   — no slashes, no protocol, no going up a directory. Anything else
   falls back to the dashboard. */
$next = $_POST['next'] ?? '';

if (!preg_match('~^[A-Za-z0-9_-]+\.php(\?[^\s"\'<>]*)?(#[^\s"\'<>]*)?$~', $next)) {
    /* No specific destination: admins land in the panel, everyone
       else on their dashboard. A "next" the visitor was actually
       trying to reach always wins over both — someone who clicked a
       destination wants the destination, not a control panel. */
    $next = ($_SESSION['role'] === 'admin') ? 'admin/index.php' : 'dashboard.php';
}

header('Location: ../' . $next);
exit;