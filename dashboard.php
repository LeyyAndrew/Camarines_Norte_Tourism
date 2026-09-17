<?php
/* ===================================================================
   dashboard.php

   The page a signed-in user lands on. Same shell as every other page
   — includes/header.php, includes/footer.php — so the nav, the
   footer, the modal and the scroll motion all come along for free.

   WHY THIS IS IN THE PROJECT ROOT AND NOT IN users/

   header.php and footer.php write their paths as "assets/css/base.css",
   "uploads/logo.png", "auth/login_process.php" — all relative to the
   page being viewed. From users/dashboard.php the browser would look
   for users/assets/css/base.css and users/uploads/logo.png, and every one
   of them would 404: no styles, no logo, a broken sign-in form.

   Putting the page beside homepage.php makes every one of those paths
   correct with no changes to the shared files. If you later want it
   under users/, the fix is a $base variable in header.php prefixed to
   every path — worth doing once you have several pages down there,
   not worth it for one.

   THE STYLES load themselves. header.php looks for
   assets/css/<page name>.css and includes it if it exists, so
   assets/css/dashboard.css is picked up automatically. Nothing to link.
   =================================================================== */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ---------- the guard ----------
   This has to run before header.php prints a single byte, or the
   redirect fails with "headers already sent". Anyone not signed in
   is bounced to the homepage — a signed-out visitor typing this URL
   should never see the inside of an account. */
if (!isset($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit;
}

$firstname = $_SESSION['firstname'] ?? 'there';

/* The name they registered with. Change the key if your login_process.php
   stores it under something else. Falls back to the first name. */
$username  = $_SESSION['username'] ?? $firstname;

/* ---------- the welcome pop-up ----------
   Shown once per sign-in. The flag lives in the session, so it resets
   by itself when logout.php destroys the session — the next sign-in
   gets greeted again, but refreshing the dashboard does not. */
$showWelcome = empty($_SESSION['welcome_shown']);
$_SESSION['welcome_shown'] = true;

/* Bud's picture. Point this at the same image bud-widget.php uses.
   If the file is not found, a drawn Bud is used instead. */
$budAvatar = 'uploads/bud.png';

$pageTitle = 'Your account — Explore Camarines Norte';
$pageDesc  = 'Your saved places and trip planning for Camarines Norte.';

require __DIR__ . '/includes/header.php';
?>

<?php if ($showWelcome): ?>
<!-- ===================================================================
     WELCOME POP-UP

     A native <dialog>: showModal() traps focus, Esc closes it, and the
     page behind is inert — no library needed.
     =================================================================== -->
<dialog class="welcome" id="welcomeDialog" aria-labelledby="welcomeTitle">
  <div class="welcome__top">
    <div class="welcome__avatar">
      <?php if (is_file(__DIR__ . '/' . $budAvatar)): ?>
        <img src="<?= htmlspecialchars($budAvatar) ?>" alt="Bud.Ai">
      <?php else: ?>
        <svg viewBox="0 0 64 64" aria-hidden="true">
          <line x1="32" y1="6" x2="32" y2="14" stroke="#16191C" stroke-width="2.5" stroke-linecap="round"/>
          <circle cx="32" cy="6" r="3.5" fill="#F0A32C"/>
          <rect x="10" y="14" width="44" height="36" rx="14" fill="#fff" stroke="#16191C" stroke-width="2.5"/>
          <rect x="16" y="21" width="32" height="20" rx="9" fill="#16191C"/>
          <circle cx="25" cy="31" r="4" fill="#5FD4E8"/>
          <circle cx="39" cy="31" r="4" fill="#5FD4E8"/>
          <path d="M27 44.5q5 3 10 0" fill="none" stroke="#16191C" stroke-width="2.5" stroke-linecap="round"/>
          <rect x="5" y="26" width="5" height="12" rx="2.5" fill="#F0A32C"/>
          <rect x="54" y="26" width="5" height="12" rx="2.5" fill="#F0A32C"/>
        </svg>
      <?php endif; ?>
    </div>
    <p class="welcome__from">Bud<span>.Ai</span></p>
  </div>

  <div class="welcome__body">
    <h2 id="welcomeTitle" class="font-display welcome__title">
      Welcome, <?= htmlspecialchars($firstname) ?>!
    </h2>

    <p class="welcome__status">
      <span class="welcome__dot" aria-hidden="true"></span>
      Signed in as <strong><?= htmlspecialchars($username) ?></strong>
    </p>

    <p class="welcome__text">
      I'm Bud, your guide to Camarines Norte. Beaches, waterfalls and
      heritage towns across all twelve municipalities are one click
      away. If you get stuck planning, tap me in the corner and ask.
    </p>

    <div class="welcome__actions">
      <button type="button" class="btn btn--orange" data-welcome-close>Start exploring</button>
      <a href="destinations.php" class="welcome__link">See destinations</a>
    </div>
  </div>

  <button type="button" class="welcome__x" aria-label="Close" data-welcome-close>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
  </button>
</dialog>

<script>
(function () {
  var d = document.getElementById('welcomeDialog');
  if (!d || typeof d.showModal !== 'function') return;

  function close() {
    d.classList.add('is-closing');
    setTimeout(function () { d.close(); d.classList.remove('is-closing'); }, 220);
  }

  d.querySelectorAll('[data-welcome-close]').forEach(function (b) {
    b.addEventListener('click', close);
  });
  /* click on the dimmed backdrop */
  d.addEventListener('click', function (e) { if (e.target === d) close(); });
  d.addEventListener('cancel', function (e) { e.preventDefault(); close(); });

  /* a short pause so the page has painted before Bud appears */
  setTimeout(function () { d.showModal(); }, 350);
})();
</script>
<?php endif; ?>


<!-- ===================================================================
     WHERE TO NEXT — the same .door cards the homepage uses
     =================================================================== -->
<section class="section dash-start">
  <div class="wrap">

    <span class="eyebrow eyebrow--ocean">Where to next</span>
    <h2 class="font-display dash-heading">Start somewhere</h2>

    <div class="doors dash-doors">

      <a href="destinations.php" class="door">
        <span class="door__eyebrow">24 spots</span>
        <h3 class="font-display door__title">Destinations</h3>
        <p class="door__text">
          Every beach, waterfall and heritage site across the twelve
          municipalities, mapped and photographed.
        </p>
        <span class="door__go">Browse destinations</span>
      </a>

      <a href="gallery.php" class="door">
        <span class="door__eyebrow">Photography</span>
        <h3 class="font-display door__title">Gallery</h3>
        <p class="door__text">
          The province as it actually looks — coastline, canopy and
          the long road in between.
        </p>
        <span class="door__go">Open the gallery</span>
      </a>

      <a href="about.php" class="door door--accent">
        <span class="door__eyebrow">Camarines Norte</span>
        <h3 class="font-display door__title">The province</h3>
        <p class="door__text">
          Where it is, how to get there, and what the coast is like
          before the crowds find it.
        </p>
        <span class="door__go">Read about it</span>
      </a>

    </div>
  </div>
</section>


<!-- ===================================================================
     SAVED PLACES — PLACEHOLDER

     There is no saved-places table yet, so this is an honest empty
     state rather than a fake list. When you build the feature you
     will want a `saved` table along the lines of

       id | user_id | destination_id | created_at

     then query it here for $_SESSION['user_id'] and loop the results
     into .door cards.

     If saved places are not part of the project, DELETE this whole
     <section>. An empty box that never fills is worse than no box.
     =================================================================== -->
<section class="section dash-saved">
  <div class="wrap">

    <span class="eyebrow eyebrow--ocean">Saved</span>
    <h2 class="font-display dash-heading">Your places</h2>

    <div class="dash-empty">
      <p class="dash-empty__text">
        You haven't saved anywhere yet. Open a destination and save it
        to find it here later.
      </p>
      <a href="destinations.php" class="btn btn--orange">Find somewhere</a>
    </div>

  </div>
</section>


<!-- ===================================================================
     ACCOUNT
     =================================================================== -->
<section class="section dash-account">
  <div class="wrap">

    <div class="dash-account__row">
      <div>
        <span class="eyebrow eyebrow--ocean">Account</span>
        <p class="dash-account__name">
          Signed in as <strong><?= htmlspecialchars($firstname) ?></strong>
        </p>
      </div>

      <a href="auth/logout.php" class="btn btn--outline-dark">Sign out</a>
    </div>

  </div>
</section>
<?php require __DIR__ . '/includes/bud-widget.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>