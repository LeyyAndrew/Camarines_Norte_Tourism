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

$pageTitle = 'Your account — Explore Camarines Norte';
$pageDesc  = 'Your saved places and trip planning for Camarines Norte.';

require __DIR__ . '/includes/header.php';
?>

<!-- ===================================================================
     THE GREETING BAND

     Same shape as the .page-hero used by about.php and destinations
     .php, minus the photo — the colour band is in dashboard.css. Drop
     a photo in behind it later by adding an <img> above the scrim,
     exactly like the other pages do.
     =================================================================== -->
<header class="page-hero dash-hero">
  <div class="page-hero__scrim"></div>

  <div class="wrap page-hero__inner">
    <span class="page-hero__eyebrow">Your account</span>
    <h1 class="font-display page-hero__title">
      Hi, <?= htmlspecialchars($firstname) ?>
    </h1>
    <p class="page-hero__lead">
      Everything you need to plan a trip across the province. Pick a
      direction below, or head back to the homepage to keep browsing.
    </p>
  </div>
</header>


<!-- ===================================================================
     WHERE TO NEXT — the same .door cards the homepage uses
     =================================================================== -->
<section class="section">
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