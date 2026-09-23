<?php
/* ===================================================================
   disclosure.php — Disclosure

   Says plainly who runs the site, how places are chosen, whether
   anyone paid to be listed, and where the AI and photos come from.
   EDIT THE BRACKETED PARTS and delete any statement that is not true
   for you — a disclosure that is wrong is worse than none.
   =================================================================== */
require __DIR__ . '/../includes/legal-config.php';

/* ?fragment=1 is how the popup in includes/footer.php asks for this
   page: it gets ONLY the <article> below — no header, banner or footer —
   and drops it into the modal. Without it, this is a normal full page,
   which is what anyone without JavaScript (or opening the link in a new
   tab) still gets. */
$legalFragment = isset($_GET['fragment']);

if (!$legalFragment) {
    $pageTitle = 'Disclosure — ' . $legalSite;
    $pageDesc  = 'Who runs ' . $legalSite . ', how destinations are chosen, and how the site uses AI and photographs.';
    require __DIR__ . '/../includes/header.php';
}

$legalEyebrow = 'Disclosure';
$legalTitle   = 'Who is behind this site';
$legalLead    = 'How places end up on these pages, and what does and does not influence that.';
$legalCurrent = 'disclosure';
$legalToc = [
    'who'      => 'Who runs this site',
    'official' => 'Official status',
    'listings' => 'How places are chosen',
    'money'    => 'Payments and sponsorship',
    'ai'       => 'Use of artificial intelligence',
    'photos'   => 'Photographs',
    'accuracy' => 'Accuracy',
    'contact'  => 'Corrections and contact',
];
if (!$legalFragment) require __DIR__ . '/../includes/legal-hero.php';
?>
  <article class="legal-doc">
    <p class="legal-doc__updated">Last updated <?= $legalUpdated ?></p>
    <p class="legal-doc__intro">
      We want you to know exactly what you are reading, so here is the plain version.
    </p>

    <section id="who">
      <h2>1. Who runs this site</h2>
      <p><?= htmlspecialchars($legalSite) ?> was designed and built as a <?= htmlspecialchars($legalProject) ?>,
      made for the <?= htmlspecialchars($legalOffice) ?>. The team:</p>
      <ul>
        <li><strong><?= htmlspecialchars($legalOwner) ?></strong> — project lead. He manages the site’s content,
        its user accounts and the Bud.Ai assistant, and is the contact for anything on the site.</li>
        <?php foreach ($legalTeam as $member): ?>
        <li><strong><?= htmlspecialchars($member) ?></strong> — team member, who helped build the site.</li>
        <?php endforeach; ?>
      </ul>
      <p>Questions go to <?= htmlspecialchars($legalOwner) ?> directly — see
      <a href="#contact">Corrections and contact</a>.</p>
    </section>

    <section id="official">
      <h2>2. Official status</h2>
      <p>This site is a student project prepared for the <?= htmlspecialchars($legalOffice) ?>. It is not
      a website of the Department of Tourism, and it does not replace the Provincial Tourism Office’s own
      official announcements, advisories or services. For official matters — permits, accreditation,
      advisories and events — please contact the Provincial Tourism Office directly.</p>
    </section>

    <section id="listings">
      <h2>3. How places are chosen</h2>
      <p>Destinations and dishes are included because we think visitors would want to know about them.
      Descriptions are written in our own words. Being listed does not mean a place is accredited,
      inspected or endorsed, and not being listed does not mean a place is not worth visiting.</p>
      <p>The visitor reviews on the homepage are added by the site administrator; visitors cannot post
      reviews themselves.</p>
    </section>

    <section id="money">
      <h2>4. Payments and sponsorship</h2>
      <p>No business has paid to be listed, featured or ranked on this site. We do not use affiliate links,
      we do not run advertising, and nobody earns anything if you book or buy from a place we mention.</p>
    </section>

    <section id="ai">
      <h2>5. Use of artificial intelligence</h2>
      <p>The Bud.Ai chat assistant, available to signed-in users, is powered by an AI model from a
      third-party provider. Its replies are generated automatically and are not checked by a person
      before you see them, so they may contain mistakes.</p>
      <p>AI tools were also used to help draft parts of this site’s text and code. Everything was reviewed
      by the developer before it was published.</p>
    </section>

    <section id="photos">
      <h2>6. Photographs</h2>
      <p>Photos are our own, come from the <?= htmlspecialchars($legalOffice) ?>, or are used with
      permission from their owners. Some may show a place in ideal conditions or in a different season
      from your visit. If you own a photo on this site and want it credited or removed, contact us.</p>
    </section>

    <section id="accuracy">
      <h2>7. Accuracy</h2>
      <div class="legal-callout">
        Rates, schedules and access change without notice. Please confirm with the operator or the
        <?= htmlspecialchars($legalOffice) ?> before you travel.
      </div>
    </section>

    <section id="contact">
      <h2>8. Corrections and contact</h2>
      <p>If you run a place listed here, or spot something wrong or out of date, tell us and we will fix it.</p>
      <div class="legal-contact">
        <p><strong><?= htmlspecialchars($legalOwner) ?></strong></p>
        <p>Developer, <?= htmlspecialchars($legalSite) ?></p>
        <p>Email: <a href="mailto:<?= htmlspecialchars($legalEmail) ?>"><?= htmlspecialchars($legalEmail) ?></a></p>
      </div>
    </section>
  </article>
<?php if (!$legalFragment): ?>
</div>

<?php require __DIR__ . '/../includes/bud-widget.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>