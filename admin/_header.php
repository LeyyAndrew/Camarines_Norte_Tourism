<?php
/* ===================================================================
   admin/_header.php — the shell every admin page opens with.

   Set $adminTitle before requiring it. $adminEyebrow, $adminSub and
   $adminAction are optional; $adminAction is raw HTML for the primary
   button that sits at the top right of the page head.

   ---------------------------------------------------------------
   THE SIDEBAR IS AN INVENTORY, NOT A MENU

   Every nav row carries the number of things behind it. From any page
   you can see there are 24 destinations, 12 photographs live, and 3
   comments nobody has published. That is the idea the whole panel is
   built around, and it is why the decorative photograph that used to
   sit back here is gone — a status board is a better use of the most
   valuable column on the screen than a picture with a gradient over
   it.

   Counts are quiet grey. The ONE count that needs a decision —
   unpublished comments — is a filled gold pill instead. There is at
   most one urgent thing in this sidebar, and it should be the only
   thing that looks it.

   EVERY COUNT IS WRAPPED IN ITS OWN try/catch. A copy of this project
   where the gallery tables have not been created yet should show a
   nav row with no number, not a fatal error on every single page.
   ---------------------------------------------------------------

   THE LOGO. uploads/lakbai.png, checked for before it is written. If
   it is missing you get a lettered mark instead — never a broken
   image icon. Change $brandLogo below to use a different file.
   =================================================================== */

$adminTitle   = $adminTitle   ?? 'Admin';
$adminEyebrow = $adminEyebrow ?? null;
$adminSub     = $adminSub     ?? null;
$adminAction  = $adminAction  ?? null;

/* Handed to _ui.php at the foot of the page, which shows it as a
   toast. Taken here because takeFlash() clears it and it must only be
   read once per request. */
$uiFlash = takeFlash();

/* Two marks, and the sidebar shows both — the same pairing the public
   nav uses. Either can be missing without breaking anything: no seal
   falls back to a lettered disc, no wordmark falls back to the
   province name in type. */
$brandSeal = 'uploads/logo.png';       /* the provincial seal   */
$brandLogo = 'uploads/lakbai.png';     /* the Lakbai wordmark   */
$sidePhoto = 'uploads/admin-side.jpg'; /* optional, very faint  */

/* ---------- the inventory ----------
   Small, cheap counts. Each one is allowed to fail on its own. */
function admCount(PDO $pdo, string $sql): ?int
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        return null;      /* table not there yet — show no number */
    }
}

$navCounts = [
    'gallery'      => admCount($pdo, 'SELECT COUNT(*) FROM gallery_photos WHERE is_visible'),
    'destinations' => admCount($pdo, 'SELECT COUNT(*) FROM destinations WHERE is_visible'),
    'users'        => admCount($pdo, 'SELECT COUNT(*) FROM users'),
];

$pending = pendingComments($pdo);
$quotes  = admCount($pdo, 'SELECT COUNT(*) FROM testimonials');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($adminTitle) ?> — Explore Camarines Norte</title>

<?php if (adminAssetExists($brandSeal)): ?>
<link rel="icon" href="<?= e(adminAsset($brandSeal)) ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- Archivo carries every piece of interface furniture and every
     number; Inter carries prose. Two faces split by role rather than
     by size, so a figure in a table and a figure in the sidebar are
     recognisably the same kind of thing. -->
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(adminAsset('assets/css/admin.css')) ?>">

<script>
/* The sidebar width preference, applied before the page paints.

   This is deliberately inline, deliberately in the <head>, and
   deliberately not deferred. Doing it at the foot of the page with
   the rest of the script would let the wide sidebar paint first and
   then snap narrow — a visible jump on every single page load.

   Wrapped in try/catch because localStorage throws rather than
   returning null in some private-browsing modes, and a preference
   about panel width must never stop a page rendering. */
try {
  if (localStorage.getItem('admSidebarMini') === '1') {
    document.documentElement.classList.add('adm-pre-mini');
  }
} catch (e) {}
</script>
<style>
  /* The class lands on <html> because <body> does not exist yet at
     this point in the parse. This hands it to the sidebar. */
  .adm-pre-mini .adm-side{ flex-basis:74px; }
</style>
</head>
<body>

<div class="adm">

  <aside class="adm-side">

    <?php if (adminAssetExists($sidePhoto)): ?>
      <!-- A faint texture at the foot of the panel, well below the
           nav. Nothing is ever written on top of it, so it cannot
           cost anyone legibility. -->
      <div class="adm-side__photo" style="background-image:url('<?= e(adminAsset($sidePhoto)) ?>')" role="presentation"></div>
    <?php endif; ?>

    <!-- THE SEAL AND THE WORDMARK, side by side. The seal anchors the
         left edge and survives the collapsed state on its own; the
         wordmark carries the brand and hides when the panel narrows.

         alt="" on both: the link is labelled below, and a screen
         reader should not read the province name three times. -->
    <a class="adm-brand" href="../homepage.php" aria-label="Explore Camarines Norte, view the site">
      <?php if (adminAssetExists($brandSeal)): ?>
        <img class="adm-brand__seal" src="<?= e(adminAsset($brandSeal)) ?>" alt="">
      <?php else: ?>
        <span class="adm-brand__mark">CN</span>
      <?php endif; ?>

      <span class="adm-brand__marks">
        <?php if (adminAssetExists($brandLogo)): ?>
          <img class="adm-brand__logo" src="<?= e(adminAsset($brandLogo)) ?>" alt="">
          <span class="adm-brand__text">Tourism content</span>
        <?php else: ?>
          <span class="adm-brand__text">
            <b>Camarines Norte</b>
            Tourism content
          </span>
        <?php endif; ?>
      </span>
    </a>

    <nav class="adm-nav">
      <a href="index.php" class="adm-nav__link<?= $adminHere === 'index.php' ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        Overview
      </a>

      <a href="destinations.php" class="adm-nav__link<?= $adminHere === 'destinations.php' ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
        Destinations
        <?php if ($navCounts['destinations'] !== null): ?>
          <span class="adm-nav__count"><?= $navCounts['destinations'] ?></span>
        <?php endif; ?>
      </a>

      <a href="gallery.php" class="adm-nav__link<?= $adminHere === 'gallery.php' ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-4.5-4.5L3 21"/></svg>
        Gallery
        <?php if ($navCounts['gallery'] !== null): ?>
          <span class="adm-nav__count"><?= $navCounts['gallery'] ?></span>
        <?php endif; ?>
      </a>

      <a href="testimonials.php" class="adm-nav__link<?= $adminHere === 'testimonials.php' ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.6-.7L3 21l1.9-5a8.2 8.2 0 0 1-.9-3.7A8.4 8.4 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8Z"/></svg>
        Comments
        <?php if ($pending): ?>
          <!-- the only filled count in the sidebar: things waiting on
               a decision, visible from every page. A queue you have to
               open a page to discover is a queue that gets forgotten. -->
          <span class="adm-nav__flag" title="<?= $pending ?> waiting to be published"><?= $pending ?></span>
        <?php elseif ($quotes !== null): ?>
          <span class="adm-nav__count"><?= $quotes ?></span>
        <?php endif; ?>
      </a>

      <a href="users.php" class="adm-nav__link<?= $adminHere === 'users.php' ? ' is-active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.6a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7.5" r="3.5"/><path d="M22 20v-1.6a4 4 0 0 0-3-3.8"/><path d="M16.5 4.2a4 4 0 0 1 0 6.6"/></svg>
        Users
        <?php if ($navCounts['users'] !== null): ?>
          <span class="adm-nav__count"><?= $navCounts['users'] ?></span>
        <?php endif; ?>
      </a>
    </nav>

    <div class="adm-side__foot">
      <!-- Narrows the panel to its icons. Sits with the account and
           sign-out controls rather than floating on the edge, because
           it belongs with the other things that are about the panel
           itself and not about the site. -->
      <button type="button" class="adm-mini" id="admMini" aria-pressed="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        <span>Collapse</span>
      </button>

      <div class="adm-who">
        <span class="adm-who__avatar" aria-hidden="true"><?= e(initials($me['firstname'], $me['lastname'])) ?></span>
        <span class="adm-who__body">
          <span class="adm-who__label">Signed in</span>
          <span class="adm-who__name"><?= e($me['firstname'] . ' ' . $me['lastname']) ?></span>
        </span>
      </div>

      <div class="adm-side__links">
        <a href="../homepage.php" class="adm-side__link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
          View the site
        </a>

        <a href="../auth/logout.php" class="adm-side__link">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/></svg>
          Sign out
        </a>
      </div>
    </div>
  </aside>

  <main class="adm-main">