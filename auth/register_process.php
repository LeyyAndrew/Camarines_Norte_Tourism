<?php
/* ===================================================================
   auth/register_process.php

   Two forms post here: auth/register.php (the standalone page) and
   the sign-in modal in includes/footer.php. Both send the same four
   fields — firstname, lastname, email, password — so this file does
   not care which one it came from.

   ON FAILURE it sends the user back to whichever page they came from
   rather than printing an error onto a blank screen. It sets the
   error two ways, because the two forms read it differently:

     $_SESSION['register_error']   read by auth/register.php
     ?error=CODE in the URL        read by the footer modal

   Nothing here echoes anything. A process file that prints is a
   process file the user gets stranded on.
   =================================================================== */

session_start();

require '../config/database.php';

/* -------------------------------------------------------------------
   Send the user back where they came from, carrying the message.

   HTTP_REFERER is the page that submitted the form. It is stripped of
   any existing query string first, otherwise a second failed attempt
   would append ?error=... twice. It is also checked against our own
   host, so a crafted referer cannot bounce someone off to another
   site. If it is missing entirely — some browsers omit it — the
   fallback is the register page.
   ------------------------------------------------------------------- */
function back_with_error(string $message, string $code): void
{
    $_SESSION['register_error'] = $message;

    $fallback = 'register.php';
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

/* -------------------------------------------------------------------
   Read the fields.

   ?? '' matters: without it, a missing field is a PHP warning AND a
   null that the database then rejects with a much uglier error. This
   way a missing field is simply an empty string, caught below.
   ------------------------------------------------------------------- */
$firstname = trim($_POST['firstname'] ?? '');
$lastname  = trim($_POST['lastname']  ?? '');
$email     = trim($_POST['email']     ?? '');
$password  = $_POST['password'] ?? '';

/* ---------- validation ---------- */
if ($firstname === '' || $lastname === '' || $email === '' || $password === '') {
    back_with_error('Please fill in every field.', 'missing');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_with_error('That email address does not look right.', 'bademail');
}

if (strlen($password) < 8) {
    back_with_error('Your password needs at least 8 characters.', 'shortpw');
}

/* ---------- is the email already taken? ----------
   Checked here for a friendly message. The database still has the
   final say — see the catch below — because two people could submit
   the same address in the gap between this check and the insert. */
$check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$check->execute([':email' => $email]);

if ($check->fetch()) {
    back_with_error('An account already uses that email.', 'emailtaken');
}

/* ---------- create the account ---------- */
$hash = password_hash($password, PASSWORD_DEFAULT);

/* ---------- the first account is the admin ----------
   An empty users table means this is the very first signup, so that
   person gets the admin role and can reach admin/. Everyone after
   them is an ordinary user.

   Counting rows is fine for a project this size. On a busy site two
   simultaneous first signups could both read 0 and both become admin
   — the real fix there is to seed the admin row when you install,
   rather than infer it. */
$existing = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$role     = $existing === 0 ? 'admin' : 'user';

$sql = "INSERT INTO users (firstname, lastname, email, password, role)
        VALUES (:firstname, :lastname, :email, :password, :role)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':firstname' => $firstname,
        ':lastname'  => $lastname,
        ':email'     => $email,
        ':password'  => $hash,
        ':role'      => $role,
    ]);
} catch (PDOException $e) {
    /* 23505 is Postgres for "unique constraint violated" — someone
       took the email between the check above and this insert. Every
       other database error is a real fault: log it, do not show it.
       An uncaught PDOException prints the whole failing row to the
       screen, password hash included. */
    if ($e->getCode() === '23505') {
        back_with_error('An account already uses that email.', 'emailtaken');
    }

    error_log('register insert failed: ' . $e->getMessage());
    back_with_error('Something went wrong creating your account. Please try again.', 'server');
}

/* ---------- signed up: send them to sign in ----------
   No session is set here on purpose. Registering creates the account;
   it does not sign anyone in. The new user types the password they
   just chose, once, on the sign-in form — which also proves to them
   that it works before they ever need it.

   Where they go back to depends on which form they used:

     the footer modal   the referer is an ordinary page, so return to
                        it and let the modal reopen on the sign-in side
     auth/register.php  a standalone page with nothing to return to,
                        so send them to auth/login.php instead

   ?registered=1 is what both of those read to show the confirmation.
   No message text in the URL, for the same reason the errors use
   codes — see the note in includes/footer.php. */

/* a failed attempt earlier in the same session would otherwise leave
   its message sitting there for the next page that looks */
unset($_SESSION['register_error']);

$target  = 'login.php';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

if ($referer !== '') {
    $host = parse_url($referer, PHP_URL_HOST);

    /* same own-host check as back_with_error: a crafted referer must
       not be able to bounce anyone off to another site */
    if ($host === null || $host === ($_SERVER['HTTP_HOST'] ?? '')) {
        $path   = strtok($referer, '?');
        $target = (basename($path) === 'register.php') ? 'login.php' : $path;
    }
}

header('Location: ' . $target . '?registered=1');
exit;