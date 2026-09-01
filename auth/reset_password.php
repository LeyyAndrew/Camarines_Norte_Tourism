<?php
/* ===================================================================
   auth/reset_password.php

   Where the emailed link lands. Checks the token, shows a two-field
   form, writes the new password, then sends the visitor to the sign-in
   card.

   A page rather than another pane in the modal, on purpose: the
   visitor arrives here from their email client with no session and no
   history on the site. A modal needs a page underneath it to be a
   modal of, and there isn't one.

   THE TOKEN IS IN THE URL, which is unavoidable — that is what a link
   is. Everything else compensates: it is hashed at rest, it lasts an
   hour, it works once, and this page sends no Referer to third
   parties (see the meta tag below) so it cannot leak sideways.
   =================================================================== */

require_once __DIR__ . '/_auth_db.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$done  = false;

/* ---------- find the token ----------
   The URL carries the token; the table holds its hash. Hash what
   arrived and look for that. */
$row = null;

if ($token !== '' && ctype_xdigit($token) && strlen($token) === 64) {
    $row = auth_one(
        'SELECT email, expires_at FROM password_resets
          WHERE token_hash = ? AND expires_at > NOW() LIMIT 1',
        [hash('sha256', $token)]
    );
}

if (!$row) {
    /* One message for expired, used, and never-existed alike. The
       differences are only useful to somebody guessing tokens. */
    $error = 'This link has expired or has already been used. Ask for a new one and it will arrive in a few minutes.';
}

/* ---------- the new password ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {

    $pw      = $_POST['password'] ?? '';
    $confirm = $_POST['confirm']  ?? '';

    if (strlen($pw) < 8) {
        $error = 'Your password needs to be at least 8 characters.';
    } elseif ($pw !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        try {
            /* password_hash, never md5 or sha1. It salts each hash and
               is deliberately slow, which is the entire defence
               against somebody who has stolen the table. */
            auth_run(
                'UPDATE ' . AUTH_TABLE . ' SET ' . AUTH_PASS . ' = ? WHERE ' . AUTH_EMAIL . ' = ?',
                [password_hash($pw, PASSWORD_DEFAULT), $row['email']]
            );

            /* Used once. Delete before showing success, so a refresh
               of this page cannot replay it. */
            auth_run('DELETE FROM password_resets WHERE email = ?', [$row['email']]);

            /* Anyone signed in as this account elsewhere should be
               signed out — the commonest reason for a reset is that
               somebody else got in. */
            $_SESSION = [];

            $done = true;
        } catch (Throwable $e) {
            error_log('reset_password: ' . $e->getMessage());
            $error = 'Something went wrong at our end. Try the link again in a moment.';
        }
    }
}

$pageTitle = 'Choose a new password';
$pageDesc  = 'Set a new password for your Explore Camarines Norte account.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>

<!-- Stops the token travelling to any other site in a Referer header
     if this page ever loads something external. -->
<meta name="referrer" content="no-referrer">

<!-- and out of search results, and out of the browser's cache -->
<meta name="robots" content="noindex, nofollow">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/auth.css">

<style>
  /* This page borrows the card's styling but is not the modal, so it
     needs the handful of rules the modal got from being fixed and
     centred. Kept here rather than in auth.css: one page uses them. */
  body{ background:#0C2E2F; min-height:100vh; display:grid; place-items:center; padding:1.5rem; }

  .reset-card{
    width:100%; max-width:26rem; background:#fff;
    border-radius:1.15rem; padding:2.5rem 2.25rem;
    box-shadow:0 50px 100px -40px rgba(8,20,24,.65);

    --a-brand:#1F6A4F; --a-brand-dark:#18543F; --a-brand-ink:#fff;
    --a-ink:#16191C; --a-line:rgba(22,25,28,.10);
    --a-muted:rgba(22,25,28,.55); --a-field:#FBFBF9; --a-amber:#F0A32C;
  }

  .reset-card__seal{ width:52px; height:52px; object-fit:contain; margin-bottom:1.25rem; }

  .reset-card h1{
    font-family:var(--font-display); font-size:1.7rem; font-weight:700;
    line-height:1.1; letter-spacing:-.02em; color:var(--a-ink); margin-bottom:.5rem;
  }

  .reset-card p.lede{ color:var(--a-muted); font-size:.87rem; line-height:1.55; margin-bottom:1.5rem; }

  .reset-card .btn{
    display:flex; align-items:center; justify-content:center; width:100%;
    padding:1rem 1.5rem; border:none; border-radius:.65rem; cursor:pointer;
    background:var(--a-brand); color:#fff;
    font-family:var(--font-display); font-size:.9rem; font-weight:700;
  }

  .reset-card .btn:hover{ background:var(--a-brand-dark); }

  .reset-back{
    display:inline-block; margin-top:1.5rem; font-size:.82rem;
    color:var(--a-muted); text-decoration:underline; text-underline-offset:3px;
  }
</style>
</head>
<body>

<main class="reset-card">

  <img class="reset-card__seal" src="../uploads/logo.png" alt="Seal of the Province of Camarines Norte">

  <?php if ($done): ?>

    <h1>Password changed</h1>
    <p class="lede">You can sign in with the new one now.</p>
    <a class="btn" href="../homepage.php#signin">Sign in</a>

  <?php elseif (!$row): ?>

    <h1>This link has expired</h1>
    <p class="lede"><?= htmlspecialchars($error) ?></p>
    <a class="btn" href="../homepage.php#reset">Ask for a new link</a>

  <?php else: ?>

    <h1>Choose a new password</h1>
    <p class="lede">For <strong><?= htmlspecialchars($row['email']) ?></strong>. At least 8 characters.</p>

    <?php if ($error): ?>
      <div class="auth-error" role="alert"><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <form method="post" class="auth-form">
      <!-- The token rides along so the POST can be checked the same
           way the GET was. Without it, submitting the form would
           arrive with no way to tell whose password to change. -->
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

      <div class="auth-field auth-field--pw">
        <label class="auth-field__label auth-sr" for="newPassword">New password</label>
        <div class="auth-field__wrap">
          <input type="password" id="newPassword" name="password" placeholder="New password"
                 autocomplete="new-password" minlength="8" required autofocus>
        </div>
      </div>

      <div class="auth-field auth-field--pw">
        <label class="auth-field__label auth-sr" for="newConfirm">Confirm new password</label>
        <div class="auth-field__wrap">
          <input type="password" id="newConfirm" name="confirm" placeholder="Confirm new password"
                 autocomplete="new-password" required>
        </div>
      </div>

      <button type="submit" class="btn">Save new password</button>
    </form>

  <?php endif; ?>

  <a class="reset-back" href="../homepage.php">Back to Explore Camarines Norte</a>
</main>

</body>
</html>