<?php
/* ===================================================================
   privacy.php — Privacy Policy

   Written around the Data Privacy Act of 2012 (Republic Act 10173).
   A TEMPLATE, NOT LEGAL ADVICE. Check every item under "What we
   collect" against what your code actually stores — if a line is not
   true, delete it; if the site stores something not listed, add it.
   =================================================================== */
require __DIR__ . '/../includes/legal-config.php';

/* ?fragment=1 is how the popup in includes/footer.php asks for this
   page: it gets ONLY the <article> below — no header, banner or footer —
   and drops it into the modal. Without it, this is a normal full page,
   which is what anyone without JavaScript (or opening the link in a new
   tab) still gets. */
$legalFragment = isset($_GET['fragment']);

if (!$legalFragment) {
    $pageTitle = 'Privacy Policy — ' . $legalSite;
    $pageDesc  = 'What personal information ' . $legalSite . ' collects, why, and your rights under the Data Privacy Act of 2012.';
    require __DIR__ . '/../includes/header.php';
}

$legalEyebrow = 'Privacy Policy';
$legalTitle   = 'Your information, handled carefully';
$legalLead    = 'What we collect, why we need it, who else sees it, and how to have it removed.';
$legalCurrent = 'privacy';
$legalToc = [
    'summary'  => 'The short version',
    'collect'  => 'What we collect',
    'why'      => 'Why we use it',
    'bud'      => 'Bud.Ai chat messages',
    'share'    => 'Who else receives it',
    'cookies'  => 'Cookies and browser storage',
    'keep'     => 'How long we keep it',
    'security' => 'Security',
    'rights'   => 'Your rights',
    'children' => 'Children',
    'changes'  => 'Changes',
    'contact'  => 'Privacy contact',
];
if (!$legalFragment) require __DIR__ . '/../includes/legal-hero.php';
?>
  <article class="legal-doc">
    <p class="legal-doc__updated">Last updated <?= $legalUpdated ?></p>
    <p class="legal-doc__intro">
      <?= htmlspecialchars($legalSite) ?> is a <?= htmlspecialchars($legalProject) ?> built by
      <?= htmlspecialchars($legalOwner) ?>, with <?= htmlspecialchars($legalTeamText) ?>, for the
      <?= htmlspecialchars($legalOffice) ?>. <?= htmlspecialchars($legalOwner) ?> is responsible
      for the personal information collected through it, and handles it in line with the Data Privacy Act
      of 2012 (Republic Act No. 10173) and the rules of the National Privacy Commission.
    </p>

    <section id="summary">
      <h2>1. The short version</h2>
      <div class="legal-callout">
        You can use this site without an account. If you create one, we keep only what we need to save your
        places and let you use Bud.Ai. We do not sell your information, and you can ask us to delete it at any time.
      </div>
    </section>

    <section id="collect">
      <h2>2. What we collect</h2>
      <h3>If you create an account</h3>
      <ul>
        <li>Your first name, last name and email address.</li>
        <li>Your password — stored only in scrambled (hashed) form, never as plain text.</li>
        <li>That you accepted these terms, and when your account was created.</li>
      </ul>
      <h3>While you use your account</h3>
      <ul>
        <li>The places you save to your list.</li>
        <li>The messages you type to Bud.Ai and the replies you receive (see <a href="#bud">section 4</a>).</li>
      </ul>
      <h3>Automatically, from every visit</h3>
      <ul>
        <li>Standard server logs: IP address, browser type, pages requested and the time of the request.</li>
        <li>A session cookie that keeps you signed in, and a “remember me” cookie if you tick that box.</li>
      </ul>
      <p>Visitors cannot post reviews or comments, so we collect nothing of that kind from you. The reviews
      on the homepage are added by the administrator.</p>
      <p>We do not ask for government ID numbers, payment details or health information, and we ask you not to
      enter them anywhere on the site, including in the chat.</p>
    </section>

    <section id="why">
      <h2>3. Why we use it</h2>
      <ul>
        <li><strong>To run your account</strong> — signing you in, keeping your saved places, and resetting your
        password when you ask. (Basis: providing the service you asked for.)</li>
        <li><strong>To answer your questions through Bud.Ai</strong>. (Basis: providing the service you asked for.)</li>
        <li><strong>To keep the site secure and working</strong> — stopping abuse and fixing errors. (Basis: our legitimate interest.)</li>
        <li><strong>To reply to you</strong> when you contact us.</li>
      </ul>
      <p>We do not sell your information, use it for advertising, or send you marketing emails. The only emails
      the site sends are ones you trigger yourself, such as a password reset link.</p>
    </section>

    <section id="bud">
      <h2>4. Bud.Ai chat messages</h2>
      <p>Bud.Ai is only available when you are signed in. When you send it a message, the message is passed
      to a third-party AI provider so it can generate a reply. That provider may process it on servers outside
      the Philippines.</p>
      <p>Please do not type personal or sensitive information into the chat.</p>
    </section>

    <section id="share">
      <h2>5. Who else receives it</h2>
      <p>We only share information with services we need to run the site:</p>
      <ul>
        <li><strong>Web hosting</strong> — the service that stores the site and its database.</li>
        <li><strong>AI provider</strong> — for Bud.Ai messages, as described above.</li>
        <li><strong>Email delivery</strong> — to send password reset links.</li>
        <li><strong>Map tiles</strong> — our maps load images from OpenStreetMap servers, which receive your IP address when the map is shown.</li>
        <li><strong>Code and font libraries</strong> — some files load from public content networks, which also receive your IP address.</li>
      </ul>
      <p>As the site was made for the <?= htmlspecialchars($legalOffice) ?>, account information may be shared
      with that office if it takes over running the site; we will update this policy before that happens.
      We will also disclose information if the law requires it, for example under a valid court order.</p>
    </section>

    <section id="cookies">
      <h2>6. Cookies and browser storage</h2>
      <p>We use a session cookie to keep you signed in, a “remember me” cookie only if you choose it, and your
      browser’s local storage for small conveniences such as the chat window’s state. We do not use advertising
      or tracking cookies. Clearing your browser data removes all of these.</p>
    </section>

    <section id="keep">
      <h2>7. How long we keep it</h2>
      <ul>
        <li>Account information and saved places: until you delete your account, then removed within 30 days.</li>
        <li>Server logs: up to 90 days.</li>
        <li>Messages you send us: as long as needed to deal with them, and no longer than one year.</li>
      </ul>
    </section>

    <section id="security">
      <h2>8. Security</h2>
      <p>We use hashed passwords, encrypted connections (HTTPS) where the host supports them, and limited access
      to the database and admin area. No website is perfectly secure, but if a breach ever puts your information
      at real risk, we will notify you and the National Privacy Commission as the law requires.</p>
    </section>

    <section id="rights">
      <h2>9. Your rights</h2>
      <p>Under the Data Privacy Act you have the right to:</p>
      <ul>
        <li><strong>be informed</strong> about how your information is used — which is what this page is for;</li>
        <li><strong>access</strong> the information we hold about you;</li>
        <li><strong>correct</strong> anything that is wrong;</li>
        <li><strong>object</strong> to processing, or withdraw consent you have given;</li>
        <li><strong>have it erased or blocked</strong>, including deleting your account;</li>
        <li><strong>receive a copy</strong> in a common electronic format (data portability);</li>
        <li><strong>claim damages</strong> if you are harmed by unlawful processing; and</li>
        <li><strong>file a complaint</strong> with the National Privacy Commission at
          <a href="https://privacy.gov.ph" rel="noopener">privacy.gov.ph</a>.</li>
      </ul>
      <p>To use any of these rights, email us from the address on your account. We will reply within 15 working days.</p>
    </section>

    <section id="children">
      <h2>10. Children</h2>
      <p>Accounts are for people aged 13 and over. If you believe a child under 13 has created an account,
      contact us and we will delete it.</p>
    </section>

    <section id="changes">
      <h2>11. Changes</h2>
      <p>If we change this policy, we will update the date at the top. For significant changes affecting
      account holders, we will also tell you by email or on the site.</p>
    </section>

    <section id="contact">
      <h2>12. Privacy contact</h2>
      <div class="legal-contact">
        <p><strong><?= htmlspecialchars($legalOwner) ?></strong></p>
        <p>Developer and privacy contact, <?= htmlspecialchars($legalSite) ?></p>
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