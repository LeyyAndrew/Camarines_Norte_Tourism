<?php
/* ===================================================================
   terms.php — Terms of Use

   A TEMPLATE, NOT LEGAL ADVICE. Written for a tourism guide site with
   accounts, a visitor register, a map and the Bud.Ai assistant. Fill
   in includes/legal-config.php and have someone qualified read it
   before the site goes public.
   =================================================================== */
require __DIR__ . '/../includes/legal-config.php';

/* ?fragment=1 is how the popup in includes/footer.php asks for this
   page: it gets ONLY the <article> below — no header, banner or footer —
   and drops it into the modal. Without it, this is a normal full page,
   which is what anyone without JavaScript (or opening the link in a new
   tab) still gets. */
$legalFragment = isset($_GET['fragment']);

if (!$legalFragment) {
    $pageTitle = 'Terms of Use — ' . $legalSite;
    $pageDesc  = 'The rules for using ' . $legalSite . ', its accounts, visitor register and travel assistant.';
    require __DIR__ . '/../includes/header.php';
}

$legalEyebrow = 'Terms of Use';
$legalTitle   = 'The ground rules';
$legalLead    = 'What you can expect from this site, and what we ask of you in return.';
$legalCurrent = 'terms';
$legalToc = [
    'accept'   => 'Accepting these terms',
    'guide'    => 'A guide, not a booking service',
    'safety'   => 'Your safety outdoors',
    'accounts' => 'Accounts',
    'reviews'  => 'Visitor reviews',
    'conduct'  => 'Acceptable use',
    'bud'      => 'The Bud.Ai assistant',
    'links'    => 'Maps and other websites',
    'ip'       => 'Photos and content',
    'liability'=> 'Limits of liability',
    'law'      => 'Governing law',
    'changes'  => 'Changes',
    'contact'  => 'Contact',
];
if (!$legalFragment) require __DIR__ . '/../includes/legal-hero.php';
?>
  <article class="legal-doc">
    <p class="legal-doc__updated">Last updated <?= $legalUpdated ?></p>
    <p class="legal-doc__intro">
      These Terms of Use apply to everyone who visits <?= htmlspecialchars($legalSite) ?>.
      The site was built by <?= htmlspecialchars($legalOwner) ?>, with
      <?= htmlspecialchars($legalTeamText) ?>, for the
      <?= htmlspecialchars($legalOffice) ?> as a <?= htmlspecialchars($legalProject) ?>
      (“we”, “us”). Please read them — they are short on purpose.
    </p>

    <section id="accept">
      <h2>1. Accepting these terms</h2>
      <p>By using this site you agree to these terms and to our <a href="privacy.php">Privacy Policy</a>.
      If you do not agree, please do not use the site. You can keep browsing without an account;
      the terms still apply to that browsing.</p>
    </section>

    <section id="guide">
      <h2>2. A guide, not a booking service</h2>
      <p>This site is an information guide to destinations, food and experiences in Camarines Norte.
      We do not sell tours, take bookings or accept payments, and we are not the operator of any
      resort, campsite, boat service or attraction listed here.</p>
      <p>Opening hours, entrance fees, rates, road conditions and boat schedules change often.
      <strong>Always confirm details directly with the operator or the <?= htmlspecialchars($legalOffice) ?> before you travel.</strong>
      Where a card says “Contact for rates”, that is exactly what we recommend.</p>
    </section>

    <section id="safety">
      <h2>3. Your safety outdoors</h2>
      <p>Many places on this site — waterfalls, islands, surf breaks, mountain trails, rivers and mangroves —
      carry real risks. Weather, tides and trail conditions can change quickly, especially during
      typhoon season.</p>
      <ul>
        <li>Follow instructions from local guides, barangay officials, PAGASA advisories and the Coast Guard.</li>
        <li>Hire accredited guides where they are required or recommended.</li>
        <li>Do not swim, surf or cross by boat in conditions you are not sure about.</li>
      </ul>
      <p>You visit any destination at your own risk and are responsible for your own safety and that of anyone travelling with you.</p>
    </section>

    <section id="accounts">
      <h2>4. Accounts</h2>
      <p>You can browse the whole site without an account. A free account lets you do two things:</p>
      <ul>
        <li><strong>save places</strong> to your own list, so it is there on your next visit; and</li>
        <li><strong>use the Bud.Ai assistant</strong>, which is only available to signed-in users.</li>
      </ul>
      <p>You must give accurate information, keep your password private, and tell us if you think someone
      else has used your account. You are responsible for activity under your account.</p>
      <p>You must be at least 13 years old to create an account. If you are under 18, please use the site
      with a parent or guardian’s permission.</p>
      <p>You can ask us to delete your account at any time (see <a href="#contact">Contact</a>).
      We may suspend or remove accounts that misuse the site.</p>
    </section>

    <section id="reviews">
      <h2>5. Visitor reviews</h2>
      <p>Visitors cannot post on this site. The reviews and ratings shown in the visitor register on the
      homepage are added by the site administrator. They are shown as a guide to what other visitors
      thought, not as a guarantee of any place.</p>
      <p>If a review names you and you would like it corrected or removed, contact us and we will deal
      with it promptly.</p>
    </section>

    <section id="conduct">
      <h2>6. Acceptable use</h2>
      <p>Please do not:</p>
      <ul>
        <li>create an account in someone else’s name, or share your account with others;</li>
        <li>try to break, overload, scrape or gain unauthorised access to the site, its accounts or its admin area;</li>
        <li>use Bud.Ai to produce harmful, abusive or illegal content, or to flood it with automated requests;</li>
        <li>use the site in a way that breaks Philippine law, including the Cybercrime Prevention Act of 2012.</li>
      </ul>
    </section>

    <section id="bud">
      <h2>7. The Bud.Ai assistant</h2>
      <p>Bud.Ai is an automated assistant powered by an artificial intelligence model, available to
      signed-in users. It answers from the site’s own information but <strong>can still be wrong, incomplete or out of date</strong>.
      Treat its answers as a starting point, not as confirmed facts — especially for prices, schedules,
      safety and directions.</p>
      <p>Do not share passwords, ID numbers, financial details or health information in the chat.
      See the <a href="privacy.php#bud">Privacy Policy</a> for how chat messages are handled.</p>
    </section>

    <section id="links">
      <h2>8. Maps and other websites</h2>
      <p>Our maps use OpenStreetMap data. Pin locations are approximate and are not a substitute for local
      directions. Links to other websites are provided for convenience; we do not control them and are
      not responsible for their content or practices.</p>
    </section>

    <section id="ip">
      <h2>9. Photos and content</h2>
      <p>The text, design and photographs on this site belong to us, to the <?= htmlspecialchars($legalOffice) ?>,
      or to the people who took them and let us use them. You may share links and quote short passages with
      credit. Please ask before reusing photographs or copying larger parts of the site. If you believe
      something here uses your work without permission, contact us and we will look into it promptly.</p>
    </section>

    <section id="liability">
      <h2>10. Limits of liability</h2>
      <p>The site is provided “as is”. We try to keep it accurate and available, but we cannot promise it
      will always be correct, complete or online. To the extent the law allows, we are not liable for any
      loss, injury, cost or disappointment arising from your use of the site or from relying on its
      information, including information from Bud.Ai or from third-party businesses.</p>
      <p>Nothing in these terms limits any right you have that cannot be limited under Philippine law.</p>
    </section>

    <section id="law">
      <h2>11. Governing law</h2>
      <p>These terms are governed by the laws of the Republic of the Philippines. Any dispute will be brought
      before the proper courts of Camarines Norte, unless the law requires otherwise.</p>
    </section>

    <section id="changes">
      <h2>12. Changes</h2>
      <p>We may update these terms. The date at the top will change when we do, and continuing to use the
      site after an update means you accept the new version.</p>
    </section>

    <section id="contact">
      <h2>13. Contact</h2>
      <div class="legal-contact">
        <p><strong><?= htmlspecialchars($legalOwner) ?></strong></p>
        <p>Developer, <?= htmlspecialchars($legalSite) ?></p>
        <?php if ($legalAddress !== ''): ?><p><?= htmlspecialchars($legalAddress) ?></p><?php endif; ?>
        <p>Email: <a href="mailto:<?= htmlspecialchars($legalEmail) ?>"><?= htmlspecialchars($legalEmail) ?></a></p>
      </div>
    </section>
  </article>
<?php if (!$legalFragment): ?>
</div>

<?php require __DIR__ . '/../includes/bud-widget.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>