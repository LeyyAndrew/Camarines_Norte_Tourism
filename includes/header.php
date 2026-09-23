<?php
/* ===================================================================
   includes/header.php

   Every page starts with this and ends with includes/footer.php.
   Set $pageTitle / $pageDesc before including it if you want a custom
   title, otherwise the defaults below are used.

     <?php $pageTitle = 'About'; require 'includes/header.php'; ?>
     ... page content ...
     <?php require 'includes/footer.php'; ?>

   Styles: every page loads assets/css/base.css, then assets/css/nav.css,
   then assets/css/<page>.css if that file exists. Scripts are loaded by
   includes/footer.php — except assets/js/nav.js, which is loaded here
   with defer because the header is the only thing that needs it.
   =================================================================== */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* ---------- CONTENT SECURITY POLICY ----------
   A fresh random nonce on every page load. Every inline <script> on the
   site carries nonce="<?= $cspNonce ?>", so the browser runs our own
   scripts and refuses anything an attacker manages to inject.

   Report-only for now: violations are logged in the console (F12) but
   nothing is blocked. Once the console is clean on every page, change
   "Content-Security-Policy-Report-Only" to "Content-Security-Policy". */
if (!isset($cspNonce)) {
    $cspNonce = base64_encode(random_bytes(16));
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'nonce-$cspNonce' https://cdnjs.cloudflare.com https://unpkg.com https://player.vimeo.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: https://unpkg.com https://*.tile.openstreetmap.org",
        "frame-src https://player.vimeo.com",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ]);
    header('Content-Security-Policy-Report-Only: ' . $csp);
}

/* Pulled in for search_allowed(), which is the single switch governing
   whether search needs an account (SEARCH_REQUIRE_LOGIN in that file).

   Testing isset($_SESSION['user_id']) directly here instead would mean
   turning search public required edits in two files — and forgetting
   the second leaves an icon that opens a box the server then refuses,
   which looks like a bug rather than a setting. */
require_once __DIR__ . '/search.php';

$pageTitle = $pageTitle ?? 'Explore Camarines Norte — Beyond The Horizon';
$pageDesc  = $pageDesc  ?? 'Untouched islands, hidden waterfalls, gold-country heritage, and a coastline still off the beaten path.';

$here = basename($_SERVER['PHP_SELF']);
function navOn($file) {
    global $here;
    return $here === $file ? ' class="nav__link is-active"' : ' class="nav__link"';
}

/* ---------- THE DESTINATIONS MENU ----------
   One array drives three things: the desktop mega-menu, the phone
   drawer, and nothing else. Add a row here and it shows up in both
   places — you never edit the markup twice and they can never drift
   apart.

     label   — what the traveller reads
     desc    — the one line under it. Say what they will actually find,
               not what the category is called.
     href    — where it goes. These point at destinations.php with a
               ?cat= filter; change them to match however your
               destinations page filters.
     icon    — the key into $navIcons below. Used until iconImg is set.
     iconImg — path to an uploaded icon image for this category, e.g.
               'uploads/nav-icons/island.png'. Leave as '' to keep the
               line-drawing icon. Drop a file at that path and it takes
               over automatically — nothing else here needs to change.
               Square, at least 96×96, transparent background works
               best; it is shown at 24×24 in the menu and 20×20 in the
               phone drawer, so anything smaller will look soft.

   The copy here is placeholder-accurate, not researched. Check the
   place names against your own content before this goes live. */
$navDestinations = [
    /* FIVE FILTERED VIEWS OF destinations.php, ONE REAL PAGE.

       Every row except Food is the SAME page under a ?cat= filter.
       Nobody is sent to a page that has to be built and kept in step
       with the data file — add a destination to
       includes/destinations-data.php and it appears under whichever of
       these five it belongs to, with no edit here.

       THE ?cat= VALUES ARE NOT FREE TEXT. destinations.php compares
       them against the KEYS of its $categories array (around line 151)
       with a plain !==, so they have to match those labels character
       for character:

           Beaches & Islands   Falls & Rivers   Peaks & Views
           Heritage            Parks & Nature   Stay & Adventure

       They were 'islands', 'falls', 'heritage' before, which matched
       none of the above. A cat that matches nothing filters the list
       down to zero, and an empty list takes the featured block and the
       map down with it — that is the blank page you were getting, not
       a broken map.

       THE ENCODING IN THE href IS LOAD-BEARING. %26 is the & and + is
       the space. A raw & would be read as the start of the next query
       parameter and cat would arrive as "Beaches ". Rename a category
       in destinations.php and you must re-encode it here — or swap the
       literal for destUrl('', '', 'Your Label'), which does it for
       you.

       #destFilters IS THE POINT OF ALL THIS. Without it the browser loads
       the filtered page at the very top — banner, video, the whole
       introduction — and the visitor scrolls past an answer they
       already gave. The fragment drops them on the filter bar
       instead, with their chip already lit and the cards under it.

       It is an id on .dest-filters in destinations.php, with the
       offset for the fixed nav set by scroll-margin-top on that same
       rule in destinations.css. Rename it and these links keep working
       but stop scrolling; a missing #fragment fails silently. */
    ['label' => 'Islands & Beaches',   'desc' => 'Calaguas, Bagasbas, Apuao Grande',      'href' => 'destinations.php?cat=Beaches+%26+Islands#destFilters', 'icon' => 'island',   'iconImg' => 'uploads/nav-icons/island.png'],
    ['label' => 'Waterfalls & Springs','desc' => 'Cold falls an hour off the highway',    'href' => 'destinations.php?cat=Falls+%26+Rivers#destFilters',    'icon' => 'falls',    'iconImg' => 'uploads/nav-icons/falls.png'],
    ['label' => 'Heritage & Gold Towns','desc' => 'Paracale, Daet, the Rizal monument',   'href' => 'destinations.php?cat=Heritage#destFilters',            'icon' => 'heritage', 'iconImg' => 'uploads/nav-icons/heritage.png'],

    /* The odd one out: a page of its own, because a dish is not a
       destination and has no row in destinations-data.php to filter
       to. If your file is named differently, this is the line to
       change. */
    ['label' => 'Food & Delicacies',   'desc' => 'Bicol Express, laing, pili, pineapple', 'href' => 'food.php',                                 'icon' => 'food',      'iconImg' => 'uploads/nav-icons/food.png'],

    /* Stay & Adventure, not type=Surf. The tag "Surf" is a single
       destination — a menu row that lands on one card reads as a
       broken filter. This category is campsites, farm resorts and
       adventure, which is what "island hopping, treks" promises.
       For the surf spots alone: destinations.php?type=Surf */
    ['label' => 'Surf & Adventure',    'desc' => 'Beach breaks, island hopping, treks',   'href' => 'destinations.php?cat=Stay+%26+Adventure#destFilters',  'icon' => 'surf',      'iconImg' => 'uploads/nav-icons/surf.png'],

    /* Festivals & Events removed. There is no festival data anywhere in
       this site, so the row could only ever point at an empty filter or
       a page that does not exist. To bring it back you need real
       content behind it first; the 'festival' icon is still in
       $navIcons below, waiting. */
];

/* The icons live apart from the data so the array above stays readable.
   All six are 24×24, stroke-only, so they inherit colour and weight
   from CSS instead of carrying their own. */
$navIcons = [
    'island'   => '<path d="M12 3v10"/><path d="M12 6c2.5-2 5-1.5 6.5 0-2 1.5-4.5 1.5-6.5 0z"/><path d="M12 9c-2-1.6-4-1.2-5.2 0 1.6 1.2 3.6 1.2 5.2 0z"/><path d="M3 17c2 0 2 1.6 4 1.6s2-1.6 4-1.6 2 1.6 4 1.6 2-1.6 4-1.6"/><path d="M3 21c2 0 2 1.4 4 1.4"/>',
    'falls'    => '<path d="M5 3v9a3 3 0 0 0 6 0V3"/><path d="M13 3v6a3 3 0 0 0 6 0V3"/><path d="M4 17.5c1.6 0 1.6 1.5 3.2 1.5s1.6-1.5 3.2-1.5 1.6 1.5 3.2 1.5 1.6-1.5 3.2-1.5"/><path d="M4 21c1.6 0 1.6 1.2 3.2 1.2"/>',
    'heritage' => '<path d="M3 21h18"/><path d="M5 21V10l7-5 7 5v11"/><path d="M10 21v-6h4v6"/><path d="M9 10.5h6"/>',
    'food'     => '<path d="M4 3v7a3 3 0 0 0 6 0V3"/><path d="M7 10v11"/><path d="M17 3c-1.6 1.4-2.4 3.2-2.4 5.4 0 1.7.9 2.8 2.4 3.1V21"/><path d="M19.4 3c1.6 1.4 2.4 3.2 2.4 5.4"/>',
    'surf'     => '<path d="M3 19c2 0 2 1.5 4 1.5s2-1.5 4-1.5 2 1.5 4 1.5 2-1.5 4-1.5"/><path d="M6.5 16C5 11 8.5 5.5 14 3c2.5 4.5 2 10-2.5 13"/><circle cx="16.5" cy="7.5" r="1.4"/>',
    'festival' => '<path d="M12 3v3"/><path d="M12 6 5 20h14L12 6z"/><path d="M8 15h8"/><circle cx="12" cy="3" r="1"/>',
];

/* Used in three places below: the mega-menu, the featured card inside
   it, and the phone drawer's accordion. One check here instead of
   three copies of isset($_SESSION['user_id']) that could drift. */
$navSignedIn = isset($_SESSION['user_id']);

/* Same reasoning as $navSignedIn just above: computed once, here,
   unconditionally — not only inside the signed-in branch further
   down where it used to live. That block never runs for a signed-out
   visitor, so $isAdmin was undefined by the time the drawer (which
   renders for everyone) tried to read it. ?? '' keeps this safe even
   in the signed-out case, where $_SESSION['role'] does not exist at
   all. */
$isAdmin = $navSignedIn && (($_SESSION['role'] ?? '') === 'admin');

/* FEEDBACK is for visitors, not admins — admins read it in
   admin/feedback.php. Used by the nav icon, the drawer row, and
   includes/feedback-widget.php. */
$navFeedback = $navSignedIn && !$isAdmin;

/* ---------- THE CATEGORY ICON ----------
   Prints an uploaded image if $d['iconImg'] points at a real file,
   otherwise falls back to the inline SVG from $navIcons — the same
   graceful-degrade $navDestinations already documents for the
   featured photo below. $size is the pixel box the icon sits in;
   pass 24 for the mega-menu, 20 for the phone drawer, so the file
   only ever needs to be uploaded once at a decent resolution and is
   scaled down for the smaller spot. */
/* The uploaded picture icons are full-colour and clash with each other
   in the menu. Line icons read as one set. Set this to true to go back
   to the uploaded images. */
$navUseIconImages = false;

function navIconMarkup($d, $navIcons, $size = 24) {
    global $navUseIconImages;
    $imgPath = $navUseIconImages ? ($d['iconImg'] ?? '') : '';
    if ($imgPath !== '' && is_file(__DIR__ . '/../' . $imgPath)) {
        return '<img src="' . htmlspecialchars(assetUrl($imgPath)) . '" alt="" width="' . (int)$size . '" height="' . (int)$size . '">';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($navIcons[$d['icon']] ?? '') . '</svg>';
}

/* ---------- MAKING A PATH ABSOLUTE FROM THE DOMAIN ROOT ----------
   Every other path in this file (assets/css/base.css, uploads/logo.png,
   the nav icons above) is written relative, and that works BECAUSE it
   is only ever read from an attribute — href, src — on an element
   that sits directly in this page's HTML. The browser resolves a
   relative path in an attribute against the page's own URL, so
   "uploads/logo.png" on Tourism_System/homepage.php correctly becomes
   Tourism_System/uploads/logo.png.

   THE FEATURED PHOTO IS DIFFERENT. Its path travels through a CSS
   custom property (--img) and is only turned into an actual url() by
   a rule written inside assets/css/nav.css — and a relative url()
   inside a stylesheet resolves against THAT STYLESHEET's location,
   not the page's. "uploads/nav-icons/calaguas.jpg" becomes
   assets/css/uploads/nav-icons/calaguas.jpg, a folder that does not
   exist. This is a real, if obscure, CSS rule — not a bug in your
   folder layout, and not the same failure as a plain 404.

   The fix is to hand the stylesheet a path that means the same thing
   no matter what resolves it: one that starts with / and is anchored
   to the domain root rather than to whichever file is doing the
   resolving. This function builds that path by comparing this file's
   real location on disk to the web server's document root, so it is
   correct whether the site lives at localhost/ or at
   localhost/Tourism_System/ without that folder name being typed in
   here by hand. */
function siteUrl($path) {
    static $prefix = null;
    if ($prefix === null) {
        $projectRoot = realpath(__DIR__ . '/..');
        $docRoot     = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $prefix = ($projectRoot && $docRoot && strpos($projectRoot, $docRoot) === 0)
            ? str_replace('\\', '/', substr($projectRoot, strlen($docRoot)))
            : '';
    }
    return $prefix . '/' . ltrim($path, '/');
}

/* ---------- THE FEATURED PHOTO ----------
   Same idea as navIconMarkup() above, but for the single "Most asked
   about" card rather than a loop: checked against the disk so a
   missing file gives the plain gradient (see nav.css) instead of a
   silently broken background, run through assetUrl() so the photo
   isn't stuck behind a stale browser cache the day you swap it for a
   better one, and run through siteUrl() so it survives being read
   from inside nav.css instead of from this page — see the block
   above.

   CHANGE THE PATH ON THIS ONE LINE to push a different photo or move
   it to a different folder — nothing else below needs to change. */
$navFeatureImgPath = 'uploads/nav-icons/Calaguas-Nav.jpg';
$navFeatureImg = is_file(__DIR__ . '/../' . $navFeatureImgPath)
    ? siteUrl(assetUrl($navFeatureImgPath))
    : '';

/* Signed out, a menu card is a button wired to the site's sign-in
   prompt (data-auth-gate); signed in, a plain link. One helper so the
   two versions of each card cannot drift apart. */
function navCardOpen($class, $href, $signedIn, $extra = '') {
    return $signedIn
        ? '<a class="' . $class . '" href="' . htmlspecialchars($href) . '"' . $extra . '>'
        : '<button type="button" class="' . $class . '" data-auth-gate' . $extra . '>';
}
function navCardClose($signedIn) {
    return $signedIn ? '</a>' : '</button>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDesc) ?>" />

<!-- Favicon: the small icon on the browser tab. The files sit in
     assets/favicon/. siteUrl() keeps the path right
     both on localhost/Tourism_System/ and on Render. -->
<link rel="icon" href="<?= htmlspecialchars(siteUrl('assets/favicon/favicon.ico')) ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars(siteUrl('assets/favicon/favicon-32x32.png')) ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars(siteUrl('assets/favicon/apple-touch-icon.png')) ?>">

<!-- preconnect opens the TCP + TLS handshake to these hosts while the
     HTML is still being parsed, so the first request to each does not
     pay for it. Worth roughly 200-400 ms on a phone connection here in
     PH. cdnjs was missing from this list and serves AOS on every page. -->
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Shared styles first, then the header, then whatever this page adds
     on top. The page file is named after the page — about.php loads
     assets/css/about.css — so a new page only needs a matching .css
     file to pick one up. Set $pageCss before including this file to
     point somewhere else.

     Each link carries ?v=<file modification time>. The browser treats a
     different query string as a different file, so the moment you save
     a stylesheet the number changes and the old cached copy is dropped
     — but as long as you don't touch it, the number holds and the file
     stays cached. No hard-refresh, no cache clearing, nothing to bump
     by hand.

     Without this, an edited stylesheet can sit unused behind a cached
     copy indefinitely, and the page looks like the CSS "isn't working"
     when the file on disk is perfectly fine. -->
<?php
/* returns "assets/css/foo.css?v=1737250912", or the plain path if the file
   is missing so a typo still produces a working (if 404ing) link */
function assetUrl($path) {
    $abs = __DIR__ . '/../' . $path;
    return is_file($abs) ? $path . '?v=' . filemtime($abs) : $path;
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/base.css')) ?>">

<!-- nav.css sits BETWEEN base.css and the page file on purpose: it has
     to beat the older nav rules inside base.css, and a page file has to
     still be able to beat it. Nothing in base.css was deleted. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/nav.css')) ?>">

<!-- The search overlay is opened from the nav, so its styles have to
     be on every page, not just search.php. It sits here rather than in
     nav.css so the whole feature stays in files with "search" in the
     name — delete four files and the feature is gone, with nothing
     left behind in a shared stylesheet. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/search.css')) ?>">
<?php
$pageCss = $pageCss ?? 'assets/css/' . pathinfo($here, PATHINFO_FILENAME) . '.css';
/* search.php's page-named stylesheet IS the file just loaded above.
   The second !== keeps it from being requested twice on that one page. */
if ($pageCss !== 'assets/css/search.css' && is_file(__DIR__ . '/../' . $pageCss)): ?>
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl($pageCss)) ?>">
<?php endif; ?>

<!-- The bookmark button and its picker are opened from a destination
     card and from plan-trip.php, so the styles have to be on every page
     that shows a card — which is most of them. Same reasoning as
     search.css above, and the same shape: everything for the feature
     lives in files with "saved-places" in the name. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/saved-places.css')) ?>">

<!-- FEEDBACK. The dialog is opened from the nav, so its styles are on
     every page. Everything for the feature has "feedback" in its name. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/feedback.css')) ?>">

<!-- LAST, on purpose. responsive.css corrects things the files above
     have already declared, so it has to be able to win against all of
     them. It is the only stylesheet that loads after the page file.

     Keep it last if you add more links here. -->
<?php if (is_file(__DIR__ . '/../assets/css/responsive.css')): /* skip the link if the file is missing, so it can't 404 */ ?>
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/responsive.css')) ?>">
<?php endif; ?>

<!-- and mobile.css after it. responsive.css fixes what was broken on a
     phone; mobile.css changes what was merely desktop-shaped. Every
     rule in it is inside a max-width:767px or a (hover:none) query, so
     it cannot affect a desktop layout. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/mobile.css')) ?>">

<!-- defer, so it runs after the markup exists but before DOMContentLoaded
     fires. The desktop mega-menu opens on hover in pure CSS and does not
     need this file at all — nav.js is only the phone drawer, the
     accordion, and the shrink-on-scroll class. If it fails to load, the
     desktop nav is untouched and the phone nav falls back to plain
     links. -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/nav.js')) ?>" defer></script>

<!-- Same reasoning as nav.js, and the same fallback: the overlay is a
     real GET form pointed at search.php, so if this file never
     arrives, the icon still opens nothing but Enter in the field still
     works and the results page still answers. -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/search.js')) ?>" defer></script>

<!-- Opens and sends the feedback dialog (includes/feedback-widget.php).
     Exits quietly when signed out, because the dialog is not rendered. -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/feedback.js')) ?>" defer></script>
</head>
<body>

<!-- fixed full-screen overlay; sizing lives in base.css under #horizonLine -->
<svg id="horizonLine" viewBox="0 0 100 100" preserveAspectRatio="none">
  <path id="horizonPath" pathLength="1" d="M -5 62 Q 25 58, 50 62 T 105 60" stroke="#F4ECDD" stroke-width="0.12" fill="none" vector-effect="non-scaling-stroke" opacity="0.5"/>
</svg>

<!-- Skip link. First thing in the tab order, invisible until focused.
     A nav with a mega-menu is a lot of stops to tab through before you
     reach the page itself. -->
<a class="skip-link" href="#main">Skip to content</a>

<nav class="nav" id="mainNav">

  <!-- Dims the page under the open Destinations menu, so the panel
       reads as a layer above the page. Purely visual. -->
  <div class="nav__backdrop" aria-hidden="true"></div>

  <!-- ---------- MAIN BAR ---------- -->
  <div class="wrap nav__inner">

    <!-- Brand: the wave mark (uploads/new-logo.png) and the name set as
         live text. lakbai.png is no longer linked; the file can stay in
         uploads/ or be deleted. The name is real text rather than an
         image, so it stays sharp at any size and a screen reader reads
         it without needing alt text. -->
    <a href="homepage.php" class="nav__brand" aria-label="CamNorte — home">
      <img class="nav__logo-mark" src="uploads/new-logo.png" alt="">
      <span class="nav__logo-text" aria-hidden="true">CamNorte<span class="nav__logo-dot">.</span></span>
    </a>

    <!-- ul rather than a bare div: a screen reader announces "list, 4
         items" and the traveller knows how far the nav goes before
         tabbing into it. -->
    <ul class="nav__links" id="navLinks">
      <li class="nav__item"><a href="homepage.php"<?= navOn('homepage.php') ?>>Home</a></li>
      <li class="nav__item"><a href="about.php"<?= navOn('about.php') ?>>About</a></li>

      <!-- ---------- THE MEGA-MENU ----------
           Opens on hover AND on keyboard focus — :focus-within in
           nav.css does the second one, which is the part hover-only
           menus always forget. The link itself still goes to
           destinations.php, so the panel is a shortcut and never the
           only way in. Somebody on a touch screen who taps it lands on
           the full page rather than fighting a menu that needs a
           hover it cannot produce.

           aria-expanded is left off deliberately. It would have to be
           kept truthful in JS, and this opens in CSS with no JS
           involved — a permanently-false aria-expanded lies to a
           screen reader, which is worse than not having one. -->
      <li class="nav__item nav__item--mega">
        <a href="destinations.php"<?= navOn('destinations.php') ?>>
          Destinations
          <svg class="nav__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </a>

        <div class="mega">
          <div class="wrap">
            <div class="mega__inner">

              <div class="mega__main">
                <div class="mega__head">
                  <p class="mega__title">Explore by interest</p>
                  <p class="mega__sub">Twelve towns, from island beaches to gold-country heritage.</p>
                </div>

                <div class="mega__grid">
                  <?php foreach ($navDestinations as $d): ?>
                    <?= navCardOpen('mega__card', $d['href'], $navSignedIn) ?>
                      <span class="mega__icon" aria-hidden="true"><?= navIconMarkup($d, $navIcons, 24) ?></span>
                      <span class="mega__text">
                        <strong><?= htmlspecialchars($d['label']) ?></strong>
                        <span><?= htmlspecialchars($d['desc']) ?></span>
                      </span>
                    <?= navCardClose($navSignedIn) ?>
                  <?php endforeach; ?>

                  <!-- The sixth cell: fills the grid evenly and gives the
                       panel an obvious "see everything" exit. Not gated,
                       because the Destinations link itself is not. -->
                  <a class="mega__card mega__card--all" href="destinations.php">
                    <span class="mega__icon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
                    </span>
                    <span class="mega__text">
                      <strong>All destinations</strong>
                      <span>Every place, on one map</span>
                    </span>
                    <span class="mega__go" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></span>
                  </a>
                </div>
              </div>

              <!-- The featured card. --img is set here; the gradient under
                   it in nav.css shows if the photo is missing. -->
              <?= navCardOpen('mega__feature', 'destinations.php?cat=Beaches+%26+Islands#destFilters', $navSignedIn,
                    $navFeatureImg !== '' ? ' style="--img:url(\'' . htmlspecialchars($navFeatureImg, ENT_QUOTES) . '\')"' : '') ?>
                <span class="mega__feature-tag">Most visited</span>
                <span class="mega__feature-body">
                  <span class="mega__feature-title">The Calaguas Islands</span>
                  <span class="mega__feature-sub">Four hours from Manila, then a boat. Worth every minute.</span>
                  <span class="mega__feature-go">See the islands <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></span>
                </span>
              <?= navCardClose($navSignedIn) ?>

            </div>
          </div>
        </div>
      </li>

      <li class="nav__item"><a href="gallery.php"<?= navOn('gallery.php') ?>>Gallery</a></li>

      <?php if ($navFeedback): ?>
        <!-- FEEDBACK, beside Gallery. Visitors only — see $navFeedback.
             Opens the dialog in includes/feedback-widget.php. -->
        <li class="nav__item">
          <button type="button" class="nav__link nav__feedback" data-feedback-open
                  aria-controls="feedbackModal" aria-label="Send feedback" title="Send feedback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/><path d="M8.5 10.5h7"/><path d="M8.5 13.8h4.5"/></svg>
          </button>
        </li>
      <?php endif; ?>
    </ul>

    <div class="nav__icons">
      <!-- Searching needs an account, so signed out this becomes the
           same sign-in prompt the account button uses — data-auth-gate
           is your existing hook, not a new one, so there is one
           sign-in experience on this site and not two.

           THE BUTTON IS NOT THE GATE. Swapping it is a courtesy: it
           stops a signed-out visitor opening a box that was only ever
           going to refuse them. api/search.php and search.php are what
           actually withhold the results, and they check the session
           themselves — see SEARCH_REQUIRE_LOGIN in includes/search.php.

           aria-controls + aria-expanded rather than nothing: unlike
           the mega-menu below, this really is opened by JS, so the
           state can be kept truthful. search.js flips it on both this
           button and the drawer's. -->
      <button type="button" class="icon-btn"
              aria-label="<?= search_allowed() ? 'Search' : 'Sign in to search' ?>"
              <?= search_allowed()
                    ? 'data-search-open aria-controls="siteSearch" aria-expanded="false"'
                    : 'data-auth-gate' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.3" y1="15.3" x2="20.5" y2="20.5"/></svg>
      </button>

      <?php if (isset($_SESSION['user_id'])): ?>

        <?php
        /* ---------- THE GREETING ----------
           login_process.php and register_process.php both put the
           first name in $_SESSION['firstname'], so it is here on
           every page without another database query. lastname is
           taken only if it happens to be there — see below.

           WHY THIS USED TO READ "Hi!Lei Andrew" WITH NO SPACE:
           .nav__greet is display:flex, and a flex container turns a
           bare text node into its own anonymous flex item, stripping
           the whitespace on either side. The space in the markup was
           real; flex ate it. The gap in base.css is what actually
           holds the two apart now, which is why this cannot regress.

           THE INITIALS:
           mb_substr, not substr — a name starting with Ñ, É or any
           non-Latin character gets cut mid-character by the
           single-byte version and prints as a broken glyph.

           lastname may never have been stored; only the first letter
           of firstname is guaranteed. One letter in the disc is fine.
           The '?' fallback means a missing name shows an obviously
           wrong disc rather than a silently empty circle you would
           never think to look into.

           htmlspecialchars is not optional. Someone who registers
           with the name <script>...</script> would otherwise have it
           run in the browser of everyone who sees this nav. */
        $navFirst = trim($_SESSION['firstname'] ?? '');
        $navLast  = trim($_SESSION['lastname']  ?? '');

        $navInitials = mb_strtoupper(
            mb_substr($navFirst, 0, 1, 'UTF-8') . mb_substr($navLast, 0, 1, 'UTF-8'),
            'UTF-8'
        );

        if ($navInitials === '') { $navInitials = '?'; }

        $navName = trim($navFirst . ' ' . $navLast);
        if ($navName === '') { $navName = 'there'; }
        ?>

        <?php
        /* ---------- THE ADMIN SHORTCUT ----------
           login_process.php puts the role in the session on sign-in,
           so this needs no extra query. register_process.php gives the
           very first account 'admin' and everyone after it 'user'.

           $isAdmin itself is computed once, up near $navSignedIn at
           the top of this file — not here — so the drawer further
           down (which renders whether or not anyone is signed in) can
           read it safely too.

           THIS IS CONVENIENCE, NOT ACCESS CONTROL. Hiding the link
           hides nothing — anyone can type the path. admin/_bootstrap.php
           is what actually keeps non-admins out, and it has to stay
           that way even though this exists. */
        ?>

        <?php if ($isAdmin): ?>
          <!-- .icon-btn carries the shape and the transition; .nav__admin
               only overrides what differs. title= rather than aria-label
               here because the word is visible above 900px — a label
               would override text a sighted user can already read. -->
          <a href="admin/index.php" class="icon-btn nav__admin" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span class="nav__admin-text">Dashboard</span>
          </a>
        <?php endif; ?>

        <!-- aria-hidden on the disc: it spells the initials of a name
             written right beside it, so a screen reader announcing
             both says the same person twice.

             Below 900px .nav__greet-text is display:none, which takes
             it out of the accessibility tree as well as off the
             screen — hence the .auth-sr line, which is always
             announced and never seen. -->
        <div class="nav__greet">
          <span class="nav__avatar" aria-hidden="true"><?= htmlspecialchars($navInitials, ENT_QUOTES, 'UTF-8') ?></span>

          <span class="nav__greet-text">
            Hi, <strong><?= htmlspecialchars($navName, ENT_QUOTES, 'UTF-8') ?></strong>
          </span>

          <span class="auth-sr">Signed in as <?= htmlspecialchars($navName, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <a href="auth/logout.php" class="icon-btn" aria-label="Sign out" title="Sign out">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/></svg>
        </a>

      <?php else: ?>
        <button type="button" class="icon-btn" aria-label="Sign in" data-auth-gate>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8.3" r="3.2"/><path d="M5 20c1.2-3.6 4-5.4 7-5.4s5.8 1.8 7 5.4"/></svg>
        </button>
      <?php endif; ?>

      <!-- The one thing the old header had no room for. A tourism site
           exists to send somebody somewhere, and until now nothing on
           the page said so out loud. Hidden below 1080px, where the
           drawer carries it instead.

           Building an itinerary needs an account, so signed out this
           becomes data-auth-gate — the same hook the search icon and
           the mega-menu cards already use, so clicking it opens the
           site's one sign-in prompt rather than a second one invented
           here. The wording stays "Plan your trip" either way: the
           invitation is the point, and signing in is a step on the way
           to it rather than a different destination.

           THE BUTTON IS NOT THE GATE. plan-trip.php checks the session
           itself, because this only stops the click, not the URL. -->
      <?php
      /* NOT ON THE PAGE IT POINTS AT. On plan-trip.php this is an
         invitation to go where you already are, and clicking it throws
         away whatever has been typed into the builder — the page holds
         its state in memory, so a reload is a blank itinerary.

         Hidden rather than disabled: a greyed-out control still asks to
         be read and explained. The gap it leaves is the point, and the
         nav's own is-active state already says where you are. */
      ?>
      <?php if ($here !== 'plan-trip.php'): ?>
        <?php if ($navSignedIn): ?>
          <a href="plan-trip.php" class="nav__cta">Plan your trip</a>
        <?php else: ?>
          <button type="button" class="nav__cta" style="border:0;font-family:inherit;-webkit-appearance:none;appearance:none;cursor:pointer" data-auth-gate>Plan your trip</button>
        <?php endif; ?>
      <?php endif; ?>

      <!-- The burger. Hidden from 1080px up. -->
      <button type="button" class="nav__burger" id="navBurger"
              aria-label="Open menu" aria-expanded="false" aria-controls="navDrawer">
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
      </button>
    </div>
  </div>
</nav>

<!-- ---------- THE SEARCH OVERLAY ----------
     A sibling of <nav>, for the same reason the drawer is one: it has
     to sit above the page rather than inside the nav's stacking
     context.

     The form is real and its method is GET, so the field submits to
     search.php on its own. assets/js/search.js only intercepts the
     typing to preview results — it is never what makes the search
     work. Turn JavaScript off and this is a plain search box that
     still finds things.

     hidden, not a class: it keeps the field out of the tab order and
     out of the accessibility tree while closed, so a keyboard user
     does not tab into an invisible input.

     Not rendered at all while signed out. Nothing can open it in that
     state, so shipping the markup would only leave a dead dialog in
     the page for a screen reader to find. search.js checks for the
     element and exits quietly when it is absent. -->
<?php if (search_allowed()): ?>
<div class="sitesearch" id="siteSearch" hidden>
  <div class="sitesearch__scrim" data-search-close></div>

  <div class="sitesearch__panel" role="dialog" aria-modal="true" aria-label="Search">
    <div class="sitesearch__inner">

      <form class="sitesearch__form" action="search.php" method="get" role="search" data-search-form>
        <!-- A real submit button. It was a bare <svg> before, which
             looks clickable but does nothing, so the form had no
             submit control at all. -->
        <button type="submit" class="sitesearch__submit" aria-label="Search">
          <svg class="sitesearch__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.3" y1="15.3" x2="20.5" y2="20.5"/></svg>
        </button>

        <!-- autocomplete off: the browser's own history dropdown would
             cover the live results with a second, unrelated list. -->
        <input type="search" name="q" class="sitesearch__input" data-search-input
               placeholder="Beaches, falls, Bicol Express…"
               aria-label="Search Camarines Norte"
               autocomplete="off" autocapitalize="off" spellcheck="false">

        <button type="button" class="sitesearch__close" data-search-close aria-label="Close search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </form>

      <!-- The keyboard hint line that used to sit here is gone.
           Arrow keys, Enter and Escape all still work — that handling
           is in search.js and none of it was touched. What went away
           is the caption telling you so. The matching CSS rule in
           search.css is now display:none as a belt-and-braces measure
           for any cached copy of this file. -->

      <!-- ---------- THE QUICK TAGS ----------
           Shown until the second character is typed, hidden after. An
           empty box that says nothing teaches the visitor nothing
           about what is in here.

           THESE USED TO BE BUTTONS THAT TYPED THEIR OWN TEXT INTO THE
           FIELD. They are links now, and the two kinds go to different
           places:

             place  — one destination, so it goes to that destination's
                      card and detail sheet.
             theme  — covers several, so it goes to the filtered grid.
                      Sending "Waterfalls" to a single falls would mean
                      picking a favourite for the visitor and hiding
                      the rest.

           <a href> and not <button>: these now go somewhere, so they
           should be middle-clickable into a new tab, copyable, and
           announced by a screen reader as links. A button that calls
           location.assign() is a link in costume and loses all three.

           ⚠ data-search-term IS DELIBERATELY GONE from these. That is
           the hook search.js uses to catch a tag click and type it
           into the box. Leave it on and the handler fires, the anchor
           never navigates, and this whole change does nothing. If the
           tags still refuse to navigate after this edit, search.js is
           binding to .sitesearch__tag or to [data-search-quick] a
           instead — that is the listener to narrow.

           data-search-quick stays on the wrapper. search.js uses it to
           hide this row once you start typing, which is still wanted.
      -->
      <div class="sitesearch__quick" data-search-quick>

        <p class="sitesearch__quick-label">Popular</p>

        <?php
        /* ⚠ THE THREE 'slug' VALUES BELOW ARE GUESSES. I have not seen
           includes/destinations-data.php. Open it, find the real slug
           for each place, and paste it in. If slugs are derived from
           the name rather than stored, call that same function here so
           the two cannot drift apart.

           A wrong slug fails silently: the page loads at the top of an
           unfiltered grid with no card highlighted, which looks like a
           slow link rather than a broken one. Check all three.

           The 'href' values for themes are NOT guesses — they are the
           same category URLs the mega-menu above already uses, encoding
           and #destFilters included. See the long note at the top of
           this file for why %26 and + are load-bearing there. */
        $searchQuickLinks = [

            ['label' => 'Calaguas', 'kind' => 'place', 'slug' => 'calaguas-islands'],
            ['label' => 'Bagasbas', 'kind' => 'place', 'slug' => 'bagasbas-beach'],
            ['label' => 'Paracale', 'kind' => 'place', 'slug' => 'paracale'],

            ['label' => 'Waterfalls',    'kind' => 'theme', 'href' => 'destinations.php?cat=Falls+%26+Rivers#destFilters'],

            /* food.php, not a cat= filter. A dish is not a destination
               and has no row in destinations-data.php to filter to —
               same reasoning as the Food & Delicacies row in the nav. */
            ['label' => 'Bicol Express', 'kind' => 'theme', 'href' => 'food.php'],

            /* type=Surf, not cat=Stay+%26+Adventure. The nav row is the
               broad category because a menu needs to be broad; a tag
               that says "Surfing" should land on the surf spots. */
            ['label' => 'Surfing',       'kind' => 'theme', 'href' => 'destinations.php?type=Surf#destFilters'],
        ];

        foreach ($searchQuickLinks as $t):
            $isPlace = ($t['kind'] === 'place');

            /* destinations.php#dest-<slug> is the scheme search results
               already use, and search.css is already set up for the
               arrival: .dest-card has scroll-margin-top so the card
               clears the fixed nav, and .dest-card:target rings it for
               a couple of seconds so it is obvious which of twenty-four
               cards was meant.

               ⚠ IF destinations.php can open the detail balloon
               straight from the URL — a ?dest= parameter it reads on
               load — that is the better target and this is the one
               line to change. Without it the visitor lands on the right
               card and taps once to open the details. */
            $href = $isPlace
                  ? 'destinations.php#dest-' . rawurlencode($t['slug'])
                  : $t['href'];

            /* The visible label is "Calaguas". What a screen reader
               should hear is where it goes — six bare place names in a
               row say nothing about what pressing one does. */
            $aria = 'Search for ' . $t['label'];
        ?>
          <a class="sitesearch__tag"
             data-search-term="<?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?>"
             href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
             aria-label="<?= htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($isPlace): ?>
              <!-- aria-hidden: the pin says "one specific place" to the
                   eye. The aria-label already carries that in words, so
                   announcing the icon too is a stutter. -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true" focusable="false">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
              </svg>
            <?php endif; ?>
            <?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>

      </div>

      <div class="sitesearch__results" data-search-results></div>

      <!-- polite, not assertive: the count updates on every keystroke,
           and assertive would interrupt the visitor mid-word, every
           word. -->
      <p class="sitesearch__sr" role="status" aria-live="polite" data-search-live></p>

    </div>
  </div>
</div>
<?php endif; ?>

<!-- ---------- THE PHONE DRAWER ----------
     A sibling of <nav>, not a child, so it is not trapped inside the
     nav's stacking context and can sit above everything on the page.

     It is a copy of the same links, not a moved copy — the desktop nav
     stays in the DOM at every width. Moving nodes around on resize is
     where menus lose focus, lose state, and start announcing
     themselves twice. -->
<div class="nav-drawer" id="navDrawer" hidden>
  <div class="nav-drawer__scrim" data-drawer-close></div>

  <aside class="nav-drawer__panel" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="nav-drawer__top">
      <span class="nav-drawer__label">Menu</span>
      <button type="button" class="nav-drawer__close" data-drawer-close aria-label="Close menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>

    <nav class="nav-drawer__nav">
      <?php if ($isAdmin): ?>
        <!-- Same $isAdmin check that gates the top-bar dashboard icon,
             already computed above. On a phone that icon is now
             hidden (mobile.css) in favour of this row — one place to
             reach the dashboard on mobile, not two competing for the
             same cramped top bar. -->
        <a href="admin/index.php" class="nav-drawer__link nav-drawer__link--admin">Dashboard</a>
      <?php endif; ?>
      <a href="homepage.php" class="nav-drawer__link<?= $here === 'homepage.php' ? ' is-active' : '' ?>">Home</a>
      <a href="about.php" class="nav-drawer__link<?= $here === 'about.php' ? ' is-active' : '' ?>">About</a>

      <!-- Plain link, same as Home / About / Gallery. The phone menu no
           longer has a Destinations dropdown; the categories are on the
           destinations page itself. -->
      <a href="destinations.php" class="nav-drawer__link<?= $here === 'destinations.php' ? ' is-active' : '' ?>">Destinations</a>

      <a href="gallery.php" class="nav-drawer__link<?= $here === 'gallery.php' ? ' is-active' : '' ?>">Gallery</a>

      <!-- The search icon in the top bar is hidden at drawer widths, so
           without this row there is no way to search on a phone.
           data-search-open is the same hook the icon uses; search.js
           closes the drawer for it.

           Signed out it swaps to data-auth-gate, exactly as the icon
           does. The label changes with it — a row that says "Search"
           and opens a login form has lied about what it does. -->
      <?php if (search_allowed()): ?>
        <button type="button" class="nav-drawer__link" data-search-open
                aria-controls="siteSearch" aria-expanded="false">
          Search
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.3" y1="15.3" x2="20.5" y2="20.5"/></svg>
        </button>
      <?php else: ?>
        <button type="button" class="nav-drawer__link" data-auth-gate>
          Sign in to search
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.3" y1="15.3" x2="20.5" y2="20.5"/></svg>
        </button>
      <?php endif; ?>

      <?php if ($navFeedback): ?>
        <!-- Same data-feedback-open hook as the nav icon. feedback.js
             closes the drawer before opening the dialog. -->
        <button type="button" class="nav-drawer__link" data-feedback-open aria-controls="feedbackModal">
          Send feedback
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/></svg>
        </button>
      <?php endif; ?>
    </nav>

    <div class="nav-drawer__foot">
      <?php /* Same reasoning as the desktop CTA above. */ ?>
      <?php if ($here !== 'plan-trip.php'): ?>
        <?php if ($navSignedIn): ?>
          <a href="plan-trip.php" class="nav__cta nav__cta--block">Plan your trip</a>
        <?php else: ?>
          <button type="button" class="nav__cta nav__cta--block" style="border:0;font-family:inherit;-webkit-appearance:none;appearance:none;cursor:pointer" data-auth-gate>Plan your trip</button>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (isset($_SESSION['user_id'])): ?>
        <p class="nav-drawer__who">Signed in as <strong><?= htmlspecialchars($navName, ENT_QUOTES, 'UTF-8') ?></strong></p>
        <a href="auth/logout.php" class="nav-drawer__quiet">Sign out</a>
      <?php else: ?>
        <button type="button" class="nav-drawer__quiet" data-auth-gate>Sign in or create an account</button>
      <?php endif; ?>
    </div>
  </aside>
</div>