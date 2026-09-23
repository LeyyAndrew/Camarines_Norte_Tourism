<?php
/* Shared banner + side nav for the three legal pages.
   Expects $legalEyebrow, $legalTitle, $legalLead, $legalCurrent
   ('terms' | 'disclosure' | 'privacy') and $legalToc (id => label). */
$legalLinks = [
    'terms'      => ['terms.php',      'Terms of Use'],
    'disclosure' => ['disclosure.php', 'Disclosure'],
    'privacy'    => ['privacy.php',    'Privacy Policy'],
];
?>
<header class="page-hero legal-hero">
  <img class="photo-layer" src="<?= $legalRoot ?>uploads/dest-banner.jpg" alt="">
  <div class="page-hero__scrim"></div>
  <div class="wrap page-hero__inner">
    <span class="page-hero__eyebrow"><?= htmlspecialchars($legalEyebrow) ?></span>
    <h1 class="font-display page-hero__title"><?= htmlspecialchars($legalTitle) ?></h1>
    <p class="page-hero__lead"><?= htmlspecialchars($legalLead) ?></p>
  </div>
</header>

<div class="wrap section legal">
  <nav class="legal-nav" aria-label="On this page">
    <span class="legal-nav__label">On this page</span>
    <ol>
      <?php foreach ($legalToc as $id => $label): ?>
        <li><a href="#<?= $id ?>"><?= htmlspecialchars($label) ?></a></li>
      <?php endforeach; ?>
    </ol>
    <div class="legal-pages">
      <?php foreach ($legalLinks as $key => [$href, $label]): ?>
        <a href="<?= $href ?>"<?= $key === $legalCurrent ? ' aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </nav>