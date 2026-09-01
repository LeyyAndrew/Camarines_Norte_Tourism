<?php
/* ===================================================================
   includes/footer.php

   The footer, the sign-in modal, and every script. Included at the
   bottom of each page so there is only one copy of any of it.

   The modal was the sliding-cover kind. It is now a single centred
   card with a logo slot. Everything the JS hooks into is unchanged —
   see the note above the modal itself.
   =================================================================== */

/* footer.php is included by pages that may not define one, so this
   is guarded. Same job as htmlspecialchars, shorter to type. */
if (!function_exists('e_auth')) {
    function e_auth(?string $v): string {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>

<?php
/* ===================================================================
   THE FOOTER — the four things you will actually want to change

   $footerSeal   the round provincial seal, left of the wordmark.
                 Set it to '' and the seal disappears cleanly; the
                 wordmark just slides left. Point it at whatever file
                 header.php uses for .nav__logo-mark.
   $footerWord   the LAKBAI wordmark. Same file the nav uses.
   $footerPhone  a tourist assistance line. LEFT EMPTY ON PURPOSE —
                 the row does not render until you put a real number
                 in it. A tourism footer with a phone number that
                 rings nowhere is worse than a footer with none.
   $footerHours  when the office answers. Same rule: '' hides it.

   The coordinates are the province's, and they are real. The auth
   modal already prints coordinates under its photograph, so the
   footer picking the habit up makes the two read as one site.
   =================================================================== */
$footerSeal   = 'uploads/logo.png';
$footerWord   = 'uploads/lakbai.png';
$footerPhone  = '';
$footerHours  = 'Monday to Friday, 8:00 AM to 5:00 PM';
$footerCoords = '14.11° N   122.95° E';
?>

<footer class="footer">
  <div class="wrap footer__inner">

    <!-- The strip above the columns. Province on the left, its
         coordinates on the right, a hairline under both. -->
    <div class="footer__meta">
      <span class="footer__meta-place">Province of Camarines Norte</span>
      <span class="footer__meta-coords"><?= e_auth($footerCoords) ?></span>
    </div>

    <div class="footer__top">

      <!-- ============ BRAND ============
           IF THE WORDMARK VANISHES HERE it is dark artwork on a dark
           band — add the class footer__word--invert to the <img> and
           it flips to white. Nothing else needs changing. -->
      <div class="footer__brand">
        <div class="footer__brand-mark">
          <?php if ($footerSeal): ?>
            <img class="footer__seal" src="<?= e_auth($footerSeal) ?>"
                 alt="Seal of the Province of Camarines Norte" width="56" height="56">
          <?php endif; ?>
          <img class="footer__word" src="<?= e_auth($footerWord) ?>" alt="LAKBAI">
        </div>

        <p class="footer__blurb">
          Islands, waterfalls and a coastline that runs the length of the
          province. Plan the trip, save the places you like, and go.
        </p>

        <ul class="footer__contact">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
            <span>Provincial Tourism Office<br>Capitol Compound, Daet, Camarines Norte 4600</span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 7l9 6 9-6"/></svg>
            <a href="mailto:tourism@camarinesnorte.gov.ph">tourism@camarinesnorte.gov.ph</a>
          </li>
          <?php if ($footerPhone): ?>
            <li>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3.5h3.2l1.6 4-2 1.4a12.5 12.5 0 0 0 5.8 5.8l1.4-2 4 1.6V19a1.6 1.6 0 0 1-1.7 1.6A15.6 15.6 0 0 1 3.4 5.2 1.6 1.6 0 0 1 5 3.5z"/></svg>
              <a href="tel:<?= e_auth(preg_replace('/[^0-9+]/', '', $footerPhone)) ?>"><?= e_auth($footerPhone) ?></a>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- ============ EXPLORE ============ -->
      <div class="footer__col">
        <h4 class="footer__head">Explore</h4>
        <ul class="footer__list">
          <li><a class="footer__link" href="destinations.php">Destinations</a></li>
          <li><a class="footer__link" href="gallery.php">Gallery</a></li>
          <li><a class="footer__link" href="homepage.php#quote">Stories</a></li>
        </ul>
      </div>

      <!-- ============ ABOUT ============ -->
      <div class="footer__col">
        <h4 class="footer__head">About</h4>
        <ul class="footer__list">
          <li><a class="footer__link" href="about.php">Our Province</a></li>
          <li><a class="footer__link" href="#">Tourism Office</a></li>
        </ul>
      </div>

      <!-- ============ FOLLOW ============
           Two icons because two accounts exist. To add a third, copy
           one <li> and swap the <svg> — the circle sizes itself. -->
      <div class="footer__col">
        <h4 class="footer__head">Follow</h4>

        <ul class="footer__social">
          <li>
            <a href="#" aria-label="Camarines Norte tourism on Instagram">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>
            </a>
          </li>
          <li>
            <a href="#" aria-label="Camarines Norte tourism on Facebook">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.3-1.5 1.6-1.5h1.6V3.6c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.4-4 4.1v2.3H7.6V13h2.7v8h3.2z"/></svg>
            </a>
          </li>
        </ul>

        <?php if ($footerHours): ?>
          <p class="footer__hours">
            <strong>Office hours</strong>
            <?= e_auth($footerHours) ?>
          </p>
        <?php endif; ?>
      </div>

    </div><!-- /.footer__top -->

    <div class="footer__rule"></div>

    <div class="footer__bottom">
      <span class="footer__copy">&copy; <?= date('Y') ?> Explore Camarines Norte. All rights reserved.</span>

      <nav class="footer__legal" aria-label="Legal">
        <a href="#">Terms of Use</a>
        <a href="#">Privacy Policy</a>
        <a href="#">Accessibility</a>
      </nav>

      <span class="footer__sign">Beyond the horizon.</span>

      <p class="footer__official">
        An official tourism site of the Provincial Government of Camarines Norte.
      </p>
    </div>
  </div>
</footer>


<?php
/* ===================================================================
   SIGN-IN MODAL — photograph + form

   WHAT CHANGED FROM THE OLD BLOCK

   1. THE FORM COLUMN HAD NO BACKGROUND. .auth-panel was transparent,
      so the homepage behind the card showed through the fields — that
      is the green-and-blue wash in your screenshot, not a gradient
      anyone chose. assets/css/auth.css makes it opaque.

   2. THE PROVINCIAL SEAL is on the photograph now, top-left, with the
      government named beside it. You already have the file: the footer
      above uses uploads/logo.png for exactly this.

   3. THE SOCIAL BUTTONS WERE DECORATIVE. Your own comment said so.
      They do something now — see data-ready below.

   4. "Forgot password?" WAS href="#". It opens a third pane.

   5. MORE THAN ONE PHOTOGRAPH, if you want it. $authSlides below.

   EVERY HOOK YOUR JAVASCRIPT USES IS UNCHANGED:
     id="authModal"   id="authBox"   data-mode        data-modal-close
     data-auth-tab    data-toggle-pw  .auth-pane--signin / --register
     id="authSignin"  id="authRegister"

   FIELD NAMES ARE UNCHANGED TOO — firstname, lastname, email,
   password, remember, terms. auth/login_process.php and
   auth/register_process.php need no edits.
   =================================================================== */

/* ---------- THE PHOTOGRAPHS ----------
   $authPhoto was one absolute path. It is a list now, and the dots
   only render when there is more than one row — so this works exactly
   as before while you have a single photo, and becomes a slideshow the
   moment you add a second.

   The folder is yours, unchanged. Add a row, drop the file in
   /Tourism_System/uploads/Homepage-Photo/, and it appears.

   COORDINATES ARE REAL, and must stay that way. The caption's only
   value is that a visitor could check it against a map. */
$authPhotoDir = '/Tourism_System/uploads/Homepage-Photo/';

$authSlides = [
    [
        'file'   => 'Travel-Calaguas-2.jpg',
        'region' => 'Camarines Norte',
        'place'  => 'Calaguas Islands',
        'coords' => '14.50° N   122.87° E',
        'note'   => 'Mahabang Buhangin, Vinzons',
    ],
    /* To add a second, copy the block above and change the five lines.
       Check the filename matches what is really in the folder — a
       missing file leaves the lagoon-green gradient showing, which
       looks deliberate and is therefore easy to miss.

    ['file' => 'Bagasbas.jpg', 'region' => 'Camarines Norte',
     'place' => 'Bagasbas Beach', 'coords' => '14.13° N   122.97° E',
     'note' => 'The surf break at Daet'],
    */
];

/* ---------- THE SEAL ----------
   The same file the footer above already uses. Relative, like the
   footer's, so it resolves against whichever page included this.
   Set to '' and the crest disappears cleanly. */
$authSeal = 'uploads/logo.png';

/* ===================================================================
   THE SIGN-IN MESSAGES

   Your map, unchanged, plus three codes the new panes can produce.
   The reasoning in your original comment still holds and is worth
   restating: codes in the URL rather than sentences, because a URL
   carrying its own error text is a URL anyone can edit into saying
   whatever they like on your page.

   One message for a wrong email and a wrong password, still — "no
   account with that email" confirms which addresses are registered
   here to anybody who asks one guess at a time.
   =================================================================== */
$authMessages = [
    'badlogin'   => 'We could not sign you in. Check your email and password, then try again.',
    'missing'    => 'Please fill in every field.',
    'bademail'   => 'That email address does not look right.',
    'shortpw'    => 'Your password needs to be at least 8 characters.',
    'emailtaken' => 'An account already uses that email. Sign in instead, or use another address.',
    'server'     => 'Something went wrong at our end. Please try again in a moment.',

    /* login_process.php sends this after a CORRECT password on an
       account whose status is not 'active'. It was missing here, so
       a suspended user got a blank banner and no idea why they were
       turned away. */
    'suspended'  => 'This account has been suspended. Contact the tourism office on (054) 721-1111.',

    /* NEW. Nothing sends these yet — they are here so that when
       auth/forgot_process.php exists it has somewhere to report to. */
    'mismatch'   => 'The two passwords do not match.',
    'terms'      => 'Tick the box to accept the terms before creating an account.',
    'expired'    => 'That reset link has expired. Ask for a new one below.',
];

$authCode = $_GET['error'] ?? '';
$authMsg  = $authMessages[$authCode] ?? '';

/* ---------- the one success message ----------
   register_process.php sends ?registered=1 and deliberately does not
   log anybody in. Registering makes an account; signing in is a
   separate act. So the visitor needs the sign-in form with a line
   telling them the account is there.

   Kept out of $authMessages, as before: that array is errors, and
   anything in it inherits .auth-error styling. */
$authOk = isset($_GET['registered'])
        ? 'Account created. Sign in below with the password you just chose.'
        : '';

/* ---------- the reset confirmation ----------
   forgot_process.php answers every request with ?sent=1, whether or
   not the address is registered — deliberately, so the reply cannot
   be used to work out who has an account here.

   It needs its own message on its own pane. Without this the redirect
   lands back on the page with nothing showing, and the visitor cannot
   tell the difference between "the link is on its way" and "the
   button did nothing". */
$authSent = isset($_GET['sent'])
          ? 'If that address is registered, the reset link is on its way. It expires in an hour.'
          : '';

/* ---------- IS THE RESET FLOW LIVE? ----------
   The form posts for real once the file that answers it exists.
   Absent, and auth-modal.js intercepts the submit and explains
   rather than letting it 404. */
$authResetReady = is_file(__DIR__ . '/../auth/forgot_process.php');

/* which pane the message belongs on */
$authPane = in_array($authCode, ['emailtaken', 'missing', 'bademail', 'shortpw', 'server', 'mismatch', 'terms'], true)
          ? 'register'
          : ($authCode === 'expired' ? 'reset' : 'signin');
?>
<!-- ===================================================================
     NOTE ON inert: there isn't one, deliberately.

     An inert element ignores every click and every keystroke. Putting
     it in the markup would mean that if auth-modal.js ever fails to
     load, the card opens and then refuses to be typed into — a worse
     failure than the one it prevents. The JS adds it on load instead,
     so a broken script leaves you with the old behaviour rather than
     an unusable form.
     =================================================================== -->
<div class="auth-modal" id="authModal" aria-hidden="true">
  <div class="auth-modal__backdrop" data-modal-close></div>

  <div class="auth-modal__box" id="authBox" data-mode="<?= $authSent ? 'reset' : ($authMsg ? $authPane : 'signin') ?>"
       role="dialog" aria-modal="true" aria-label="Sign in or create an account">

    <button type="button" class="auth-modal__close" data-modal-close aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>

    <!-- ============ THE PHOTOGRAPH ============
         Each slide is its own layer, stacked, cross-faded on opacity.
         One layer with a swapped background-image flashes empty for as
         long as the next file takes to arrive.

         A missing file still degrades to the lagoon-green gradient
         underneath, exactly as before. -->
    <aside class="auth-media">
      <?php foreach ($authSlides as $i => $s): ?>
        <div class="auth-media__img<?= $i === 0 ? ' is-active' : '' ?>"
             data-auth-slide="<?= $i ?>"
             data-region="<?= e_auth($s['region']) ?>"
             data-place="<?= e_auth($s['place']) ?>"
             data-coords="<?= e_auth($s['coords']) ?>"
             data-note="<?= e_auth($s['note']) ?>"
             style="--auth-photo:url('<?= e_auth($authPhotoDir . $s['file']) ?>')"
             role="presentation"></div>
      <?php endforeach; ?>

      <div class="auth-media__scrim" role="presentation"></div>

      <!-- The badge on the door: the first thing seen, before anything
           is asked for. A white disc sits behind it in the CSS,
           because a full-colour seal straight onto a photograph is
           mud. -->
      <?php if ($authSeal): ?>
        <div class="auth-media__crest">
          <img class="auth-media__seal" src="<?= e_auth($authSeal) ?>"
               alt="Seal of the Province of Camarines Norte" width="46" height="46">
          <span class="auth-media__crest-text">
            <strong>Provincial Government of</strong>
            Camarines Norte
          </span>
        </div>
      <?php endif; ?>

      <div class="auth-media__plate">
        <p class="auth-media__eyebrow" data-slide-region><?= e_auth($authSlides[0]['region']) ?></p>
        <p class="auth-media__place"  data-slide-place><?= e_auth($authSlides[0]['place']) ?></p>
        <p class="auth-media__note"   data-slide-note><?= e_auth($authSlides[0]['note']) ?></p>
        <p class="auth-media__coords" data-slide-coords><?= e_auth($authSlides[0]['coords']) ?></p>

        <!-- Only with something to switch between. One dot under one
             photograph is a control that lies about having options. -->
        <?php if (count($authSlides) > 1): ?>
          <div class="auth-media__dots" role="group" aria-label="Choose a photograph">
            <?php foreach ($authSlides as $i => $s): ?>
              <button type="button" class="auth-media__dot<?= $i === 0 ? ' is-active' : '' ?>"
                      data-slide-to="<?= $i ?>"
                      aria-label="Show <?= e_auth($s['place']) ?>"
                      <?= $i === 0 ? 'aria-current="true"' : '' ?>></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </aside>

    <div class="auth-panel">

      <div class="auth-logo">
        <img src="uploads/lakbai.png" alt="LAKBAI">
      </div>

      <!-- ============ SIGN IN ============ -->
      <div class="auth-pane auth-pane--signin">

        <h3 class="font-display auth-modal__title" id="authModalTitle">Welcome back</h3>
        <p class="auth-modal__desc">Your saved places are where you left them.</p>

        <!-- Rendered empty and hidden rather than left out, so the JS
             has somewhere to write a client-side message without
             building a node — and so a message never appears in a
             different place depending on who put it there. -->
        <div class="auth-error" data-auth-error role="alert" <?= ($authMsg && $authPane === 'signin') ? '' : 'hidden' ?>>
          <span data-auth-error-text><?= e_auth($authPane === 'signin' ? $authMsg : '') ?></span>

          <!-- The way out of THIS error, not a fixed button. Offering
               "create an account" to somebody whose password was
               simply mistyped is noise. -->
          <?php if ($authCode === 'badlogin'): ?>
            <button type="button" class="auth-error__link" data-auth-tab="reset">Reset your password</button>
          <?php endif; ?>
        </div>

        <!-- role="status", not alert. Both announce; alert interrupts
             whatever a screen reader is mid-sentence on. Good news
             does not warrant an interruption. -->
        <div class="auth-notice" data-auth-notice role="status" <?= $authOk ? '' : 'hidden' ?>>
          <span data-auth-notice-text><?= e_auth($authOk) ?></span>
        </div>

        <!-- novalidate: the browser's own bubbles cannot be styled and
             land in a different place in every browser. auth-modal.js
             checks first and writes into the banner above, so there is
             one error voice on this card instead of two. The server
             still checks everything again. -->
        <form class="auth-form" id="authSignin" method="post" action="auth/login_process.php" novalidate data-auth-form="signin">

          <!-- Where the visitor was heading before the gate stopped
               them. auth-gate.js fills this in; login_process.php
               reads it and sends them on, which is the difference
               between "sign in, carry on" and "sign in, now go find
               that page again yourself".

               Left empty on purpose. login_process.php only accepts a
               bare filename in the project root and falls back to the
               dashboard for anything else, so an empty value is the
               safe default rather than a broken one. -->
          <input type="hidden" name="next" value="">

          <div class="auth-field">
            <label class="auth-field__label auth-sr" for="signinEmail">Email address</label>
            <div class="auth-field__wrap">
              <input type="email" id="signinEmail" name="email" autocomplete="email"
                     placeholder="Email address" required data-auth-email>
            </div>
          </div>

          <div class="auth-field auth-field--pw">
            <label class="auth-field__label auth-sr" for="signinPassword">Password</label>
            <div class="auth-field__wrap">
              <input type="password" id="signinPassword" name="password" autocomplete="current-password"
                     placeholder="Password" required>
              <button type="button" class="auth-field__toggle" data-toggle-pw aria-label="Show password" aria-pressed="false">
                <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/></svg>
                <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 6.2A9.6 9.6 0 0 1 12 5.5c6.4 0 10 6.5 10 6.5a17 17 0 0 1-3.3 4"/><path d="M6.4 7.8A16.6 16.6 0 0 0 2 12s3.6 6.5 10 6.5a9.7 9.7 0 0 0 3.9-.8"/><line x1="3.5" y1="3.5" x2="20.5" y2="20.5"/></svg>
              </button>
            </div>

            <!-- Caps Lock is the commonest reason a correct password is
                 rejected. Hidden until the key is actually down. -->
            <span class="auth-field__caps" data-caps-warning hidden>Caps Lock is on</span>
          </div>

          <div class="auth-form__row">
            <label class="auth-check">
              <input type="checkbox" name="remember" value="1" data-remember>
              <span>Keep me signed in</span>
            </label>

            <!-- A button, not href="#". It opens the reset pane rather
                 than navigating, and a link that does not link breaks
                 middle-click and Ctrl+click for no reason. -->
            <button type="button" class="auth-link" data-auth-tab="reset">Forgot password?</button>
          </div>

          <button type="submit" class="btn btn--orange" data-auth-submit>
            <span class="btn__label">Sign in</span>
            <span class="btn__spinner" aria-hidden="true"></span>
          </button>
        </form>

        <p class="auth-switch">
          <span>New to Camarines Norte?</span>
          <button type="button" class="auth-switch__btn" data-auth-tab="register">Create an account</button>
        </p>

        <!-- A tourism site that offers only a locked door loses the
             visitor who came to look. -->
        <button type="button" class="auth-guest" data-guest-browse>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
          Keep browsing without an account
        </button>

        <p class="auth-terms">
          <a href="#">Terms of Use</a>
          <a href="#">Disclosure</a>
          <a href="#">Privacy Policy</a>
        </p>
      </div>

      <!-- ============ CREATE ACCOUNT ============ -->
      <div class="auth-pane auth-pane--register">

        <h3 class="font-display auth-modal__title">Start exploring</h3>
        <p class="auth-modal__desc">Free to join. We only email you about the places you save.</p>

        <div class="auth-error" data-auth-error role="alert" <?= ($authMsg && $authPane === 'register') ? '' : 'hidden' ?>>
          <span data-auth-error-text><?= e_auth($authPane === 'register' ? $authMsg : '') ?></span>
          <?php if ($authCode === 'emailtaken'): ?>
            <button type="button" class="auth-error__link" data-auth-tab="signin">Sign in instead</button>
          <?php endif; ?>
        </div>

        <form class="auth-form" id="authRegister" method="post" action="auth/register_process.php" novalidate data-auth-form="register">

          <!-- The honeypot. Invisible to a person, irresistible to the
               simpler bots. Have register_process.php drop any
               submission where this arrives non-empty — silently, with
               a normal-looking result, so the bot does not learn to
               leave it alone:

                 if (!empty($_POST['website'])) {
                     header('Location: ../homepage.php?registered=1'); exit;
                 }

               Positioned off-screen rather than display:none, because
               some bots skip anything hidden that way. -->
          <div class="auth-hp" aria-hidden="true">
            <label for="regWebsite">Leave this field empty</label>
            <input type="text" id="regWebsite" name="website" tabindex="-1" autocomplete="off">
          </div>

          <!-- TWO fields, not one. register_process.php reads
               $_POST['firstname'] and $_POST['lastname'], and both
               columns are NOT NULL. A single name="name" field makes
               the insert fail. -->
          <div class="auth-field-row">
            <div class="auth-field">
              <label class="auth-field__label auth-sr" for="regFirstname">First name</label>
              <div class="auth-field__wrap">
                <input type="text" id="regFirstname" name="firstname" autocomplete="given-name"
                       placeholder="First name" required data-first>
              </div>
            </div>

            <div class="auth-field">
              <label class="auth-field__label auth-sr" for="regLastname">Last name</label>
              <div class="auth-field__wrap">
                <input type="text" id="regLastname" name="lastname" autocomplete="family-name"
                       placeholder="Last name" required data-last>
              </div>
            </div>
          </div>

          <div class="auth-field">
            <label class="auth-field__label auth-sr" for="regEmail">Email address</label>
            <div class="auth-field__wrap">
              <input type="email" id="regEmail" name="email" autocomplete="email"
                     placeholder="Email address" required data-auth-email>
            </div>
          </div>

          <div class="auth-field auth-field--pw">
            <label class="auth-field__label auth-sr" for="regPassword">Password</label>
            <div class="auth-field__wrap">
              <input type="password" id="regPassword" name="password" autocomplete="new-password"
                     placeholder="Password" minlength="8" required data-strength-input>
              <button type="button" class="auth-field__toggle" data-toggle-pw aria-label="Show password" aria-pressed="false">
                <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/></svg>
                <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 6.2A9.6 9.6 0 0 1 12 5.5c6.4 0 10 6.5 10 6.5a17 17 0 0 1-3.3 4"/><path d="M6.4 7.8A16.6 16.6 0 0 0 2 12s3.6 6.5 10 6.5a9.7 9.7 0 0 0 3.9-.8"/><line x1="3.5" y1="3.5" x2="20.5" y2="20.5"/></svg>
              </button>
            </div>

            <!-- Four segments that fill as the password improves, with
                 the judgement written out underneath. Colour alone is
                 not a message anyone can act on, and a good share of
                 visitors cannot see the difference between the red bar
                 and the green one. -->
            <div class="auth-strength" data-strength hidden>
              <div class="auth-strength__bars" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
              </div>
              <span class="auth-strength__text" data-strength-text role="status"></span>
            </div>
          </div>

          <!-- NEW FIELD. register_process.php does not read
               $_POST['confirm'] and does not need to — the check is
               here, and an extra POST key it ignores costs nothing.
               Worth having: a mistyped password on a form with no
               confirm field locks somebody out of an account they
               just made, and neither of you can tell why. -->
          <div class="auth-field auth-field--pw">
            <label class="auth-field__label auth-sr" for="regConfirm">Confirm password</label>
            <div class="auth-field__wrap">
              <input type="password" id="regConfirm" name="confirm" autocomplete="new-password"
                     placeholder="Confirm password" required data-confirm-input>
              <button type="button" class="auth-field__toggle" data-toggle-pw aria-label="Show password" aria-pressed="false">
                <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.8"/></svg>
                <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 6.2A9.6 9.6 0 0 1 12 5.5c6.4 0 10 6.5 10 6.5a17 17 0 0 1-3.3 4"/><path d="M6.4 7.8A16.6 16.6 0 0 0 2 12s3.6 6.5 10 6.5a9.7 9.7 0 0 0 3.9-.8"/><line x1="3.5" y1="3.5" x2="20.5" y2="20.5"/></svg>
              </button>
            </div>
          </div>

          <label class="auth-check auth-check--terms">
            <input type="checkbox" name="terms" value="1" required data-terms>
            <span>I agree to the <a href="#">Terms of Use</a> and <a href="#">Privacy Policy</a>.</span>
          </label>

          <button type="submit" class="btn btn--orange" data-auth-submit>
            <span class="btn__label">Create account</span>
            <span class="btn__spinner" aria-hidden="true"></span>
          </button>
        </form>

        <p class="auth-switch">
          <span>Already have an account?</span>
          <button type="button" class="auth-switch__btn" data-auth-tab="signin">Sign in</button>
        </p>

        <p class="auth-terms">
          <a href="#">Terms of Use</a>
          <a href="#">Disclosure</a>
          <a href="#">Privacy Policy</a>
        </p>
      </div>

      <!-- ============ RESET PASSWORD ============
           New. "Forgot password?" was href="#".

           data-ready follows auth/forgot_process.php: present means
           1 and the form posts for real, absent means 0 and
           auth-modal.js intercepts the submit rather than letting it
           404. Nothing to set by hand. -->
      <div class="auth-pane auth-pane--reset">

        <h3 class="font-display auth-modal__title">Reset your password</h3>
        <p class="auth-modal__desc">Give us the address on the account and we will send a link to set a new one.</p>

        <div class="auth-error" data-auth-error role="alert" <?= ($authMsg && $authPane === 'reset') ? '' : 'hidden' ?>>
          <span data-auth-error-text><?= e_auth($authPane === 'reset' ? $authMsg : '') ?></span>
        </div>

        <div class="auth-notice" data-auth-notice role="status" <?= $authSent ? '' : 'hidden' ?>>
          <span data-auth-notice-text><?= e_auth($authSent) ?></span>
        </div>

        <form class="auth-form" id="authReset" method="post" action="auth/forgot_process.php" novalidate
              data-auth-form="reset" data-ready="<?= $authResetReady ? '1' : '0' ?>">

          <div class="auth-field">
            <label class="auth-field__label auth-sr" for="resetEmail">Email address</label>
            <div class="auth-field__wrap">
              <input type="email" id="resetEmail" name="email" autocomplete="email"
                     placeholder="Email address" required data-auth-email>
            </div>

            <!-- Says the same thing whether or not the address is
                 registered, for the reason your own comment gives
                 above about not confirming which emails exist. -->
            <span class="auth-field__hint">If the address is registered, the link arrives within a few minutes.</span>
          </div>

          <button type="submit" class="btn btn--orange" data-auth-submit>
            <span class="btn__label">Send reset link</span>
            <span class="btn__spinner" aria-hidden="true"></span>
          </button>
        </form>

        <p class="auth-switch">
          <span>Remembered it?</span>
          <button type="button" class="auth-switch__btn" data-auth-tab="signin">Back to sign in</button>
        </p>
      </div>

    </div><!-- /.auth-panel -->
  </div>
</div>

<!-- ===================================================================
     THE SIGN-IN CARD'S STYLES

     A stylesheet in the body, which is not where stylesheets belong.
     It is here so that installing this is one file swap and not two
     — the modal is opacity:0 until it opens, so the usual penalty
     for a late stylesheet (a flash of unstyled content) has nothing
     to flash.

     THE TIDY VERSION, once you have this working: cut this line and
     put it in includes/header.php under the search.css link, where
     the other four live. It must come before responsive.css and
     mobile.css — those two are meant to have the last word.
     =================================================================== -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/auth.css')) ?>">

<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.1/aos.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

<!-- The only inline script left on the site. It is one line of server
     state, not behaviour: PHP has to print it, so it cannot live in a
     static .js file. homepage.js reads window.isLoggedIn to decide
     whether a [data-auth-gate] click opens the modal or follows the
     link. Keep it above homepage.js. -->
<script>
  window.isLoggedIn = <?php echo (isset($_SESSION['user_id']) ? 'true' : 'false'); ?>;
</script>

<!-- site behaviour: nav, scroll motion, the spotlight carousel,
     and CLOSING the modal. Opening lives in auth-gate.js below.

     assetUrl() is the same helper header.php uses on the stylesheets:
     it appends ?v=<file modification time>, so the moment you save a
     .js file the browser fetches the new one. Without it a cached
     script can sit there for days while the file on disk is perfectly
     correct — which looks exactly like "the code doesn't work". -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/homepage.js')) ?>"></script>

<!-- the modal's own internals: tabs, password toggle, focus -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/auth-modal.js')) ?>"></script>

<!-- the sign-in gate: nav links stay open, content links ask for a
     sign-in first. Loads last because it needs window.isLoggedIn and
     the modal markup above to already exist. -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/auth-gate.js')) ?>"></script>


</body>
</html>