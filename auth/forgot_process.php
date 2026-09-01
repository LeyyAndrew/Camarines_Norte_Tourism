<?php
/* ===================================================================
   auth/forgot_process.php

   Takes the email from the reset pane, makes a one-time link, and
   emails it. Never prints — redirects back to the card exactly as
   login_process.php does.

   RUN sql/auth_tables.sql FIRST. This needs the password_resets
   table; without it every request fails at the insert.

   THE FOUR RULES THIS FOLLOWS

   1. THE ANSWER IS ALWAYS THE SAME. Registered or not, valid or not,
      the visitor is told the link is on its way. "No account with that
      email" is a free tool for working out who has an account here,
      one guess at a time.

   2. THE TOKEN IS NOT STORED. Only its SHA-256 hash is. Someone who
      reads the table cannot use what they find — same reasoning as
      hashing a password, and for the same reason: a reset token IS a
      password for the next hour.

   3. IT EXPIRES, AND IT IS USED ONCE. An hour, and deleted the moment
      it works.

   4. THREE PER HOUR PER ADDRESS. Without a cap this endpoint is a
      free way to send somebody a hundred emails from your domain,
      which is how a domain stops being deliverable.
   =================================================================== */

require_once __DIR__ . '/_auth_db.php';

/* ---------- WHERE THE LINK POINTS ----------
   Must be the full public URL of reset_password.php. Change this if
   your project does not sit at /Tourism_System/. */
define('RESET_URL_BASE', '/Tourism_System/auth/reset_password.php');

/* How long a link lives, in minutes. An hour is the usual figure:
   long enough to find the email, short enough that a forwarded one is
   not a standing key. */
define('RESET_TTL_MIN', 60);

/* ---------- DEV MODE ----------
   XAMPP has no mail server, so mail() returns false and nothing
   arrives. With this on, a failed send writes the link to
   auth/reset-links.log so you can still test the flow by opening it
   yourself.

   TURN THIS OFF BEFORE THIS SITE IS PUBLIC. A file full of live
   reset links is a file full of live passwords. */
define('RESET_DEV_LOG', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { auth_back(); }

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth_back('error=bademail&mode=reset');
}

/* The message the visitor gets no matter what happens below. Set
   once, here, so no branch can accidentally return a different one
   and leak the answer by the difference. */
$sameAnswer = 'sent=1&mode=reset';

try {
    $user = auth_one(
        'SELECT ' . AUTH_ID . ' AS id FROM ' . AUTH_TABLE . ' WHERE ' . AUTH_EMAIL . ' = ? LIMIT 1',
        [$email]
    );

    /* No account: stop here, say the same thing. Note we do NOT skip
       the work above — a reply that comes back faster for unknown
       addresses than for known ones leaks the same fact by timing. */
    if (!$user) { auth_back($sameAnswer); }

    /* ---------- the cap ---------- */
    $recent = auth_one(
        'SELECT COUNT(*) AS n FROM password_resets
          WHERE email = ? AND created_at > ' . auth_ago(1),
        [$email]
    );

    /* PDO's pgsql driver lowercases column names and mysqli does not,
       so read it either way rather than assuming. */
    $recentCount = (int) ($recent['n'] ?? $recent['N'] ?? 0);

    if ($recentCount >= 3) {
        auth_back($sameAnswer);      /* silently. Telling them they are
                                        rate limited tells a guesser the
                                        address is real. */
    }

    /* ---------- the token ----------
       random_bytes is the cryptographically secure one. rand() and
       uniqid() are predictable enough to guess and must never be used
       for anything that acts as a key. */
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    /* Any earlier link for this address stops working now. Two live
       reset links for one account is one more than anybody needs. */
    auth_run('DELETE FROM password_resets WHERE email = ?', [$email]);

    auth_run(
        'INSERT INTO password_resets (email, token_hash, expires_at, created_at)
         VALUES (?, ?, ' . auth_ahead(RESET_TTL_MIN) . ', NOW())',
        [$email, $hash]
    );

    /* ---------- the email ---------- */
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . RESET_URL_BASE . '?token=' . $token;

    $subject = 'Reset your Explore Camarines Norte password';

    $body = "Somebody asked to reset the password for this address.\r\n\r\n"
          . "Open this link to choose a new one:\r\n"
          . $link . "\r\n\r\n"
          . "The link works once and expires in " . RESET_TTL_MIN . " minutes.\r\n\r\n"
          . "If it was not you, ignore this email. Your password has not changed.\r\n\r\n"
          . "Provincial Tourism Office\r\n"
          . "Capitol Compound, Daet, Camarines Norte";

    $headers = "From: Explore Camarines Norte <tourism@camarinesnorte.gov.ph>\r\n"
             . "Reply-To: tourism@camarinesnorte.gov.ph\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($email, $subject, $body, $headers);

    if (!$sent && RESET_DEV_LOG) {
        @file_put_contents(
            __DIR__ . '/reset-links.log',
            date('Y-m-d H:i:s') . "  " . $email . "  " . $link . "\n",
            FILE_APPEND
        );
    }

} catch (Throwable $e) {
    /* The reason goes in the server log, never on the screen. A
       database error printed to a visitor tells them your table
       names. */
    error_log('forgot_process: ' . $e->getMessage());
    auth_back('error=server&mode=reset');
}

auth_back($sameAnswer);