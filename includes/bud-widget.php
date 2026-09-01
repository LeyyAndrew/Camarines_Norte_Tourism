<?php
/* ===================================================================
   includes/bud-widget.php

   The Bud.Ai assistant: launcher, greeting bubble and chat panel.
   Drop one line into any page, just before the footer include:

       <?php require __DIR__ . '/includes/bud-widget.php'; ?>

   That is the whole integration. The CSS and JS are pulled in from
   here, so a page does not need its own <link> or <script>.

   ---------------------------------------------------------------
   WHY THE PATHS ARE BUILT AND NOT WRITTEN OUT

   'uploads/chatbot.png' and 'api/bud.php' are relative to the PAGE
   that is being viewed, not to this file. They resolve correctly from
   /destinations.php and silently break from /pages/destinations.php —
   the logo turns into a broken image and every message fails.

   $budAsset() below works out the path back to the site root once,
   from this file's own location on disk, so the widget behaves the
   same wherever the including page happens to live.
   ---------------------------------------------------------------

   ON EVERY PAGE, NOT JUST THE HOMEPAGE
   The launcher waits for the hero to scroll past before appearing. On
   a page with no #hero — destinations, about — there is nothing to
   wait for, so it appears immediately. That is deliberate: those pages
   have no hero for it to compete with.
   =================================================================== */

/* Guard against a double include. Two launchers in one corner is the
   kind of bug that only shows up on the one page that includes both a
   header and a footer that each pull this in. */
if (defined('BUD_WIDGET_RENDERED')) {
    return;
}
define('BUD_WIDGET_RENDERED', true);

/**
 * Build a URL to something at the site root, from wherever this page is.
 *
 * Works it out from the filesystem: this file is always in
 * <root>/includes/, so the root is one level up, and the difference
 * between that and the current page's directory is the prefix needed.
 */
$budAsset = function (string $path): string {
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $here = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? $root));

    $prefix = '';
    if ($root !== '' && $here !== '' && strpos($here, $root) === 0) {
        $depth = substr_count(trim(substr($here, strlen($root)), '/'), '/');
        $rel   = trim(substr($here, strlen($root)), '/');
        if ($rel !== '') {
            $prefix = str_repeat('../', $depth + 1);
        }
    }

    return htmlspecialchars($prefix . ltrim($path, '/'), ENT_QUOTES);
};

/**
 * Find bud.css / bud.js wherever they actually are.
 *
 * The first version of this file hardcoded 'assets/bud.css'. If the
 * files get dropped next to the site's other stylesheets instead —
 * css/, js/, or the site root — that link 404s, and a 404 stylesheet
 * fails SILENTLY: the widget still renders, at browser defaults, so
 * you get a 386px robot sitting in the middle of the page rather than
 * an error telling you what is wrong.
 *
 * So: check the likely locations on disk and use whichever exists.
 * Put the files wherever suits your project and this keeps working.
 */
$budFind = function (string $file) use ($budAsset): ?string {
    $root = realpath(__DIR__ . '/..');

    $candidates = [
        'assets/'     . $file,
        'assets/css/' . $file,
        'assets/js/'  . $file,
        'css/'        . $file,
        'js/'         . $file,
        'styles/'     . $file,
        'static/'     . $file,
        'public/'     . $file,
        'asset/'      . $file,
        $file,                    // site root
        'includes/'   . $file,    // next to this file
    ];

    foreach ($candidates as $rel) {
        if (is_file($root . '/' . $rel)) {
            return $budAsset($rel);
        }
    }

    return null;   // caller decides what to say about it
};

$budCss = $budFind('bud.css');
$budJs  = $budFind('bud.js');

/* Is there a backend yet?

   Until api/bud.php exists there is nothing to POST to, and pointing
   at it anyway means every message ends in "I could not reach the
   server" — which looks like a bug to anyone you show the design to.

   Empty endpoint puts the widget in preview mode: it replies with a
   short note saying it is not connected yet. Drop api/bud.php in and
   this flips to the real thing on the next page load, with nothing to
   change here. */
$budApi = is_file(realpath(__DIR__ . '/..') . '/api/bud.php')
    ? $budAsset('api/bud.php')
    : '';
?>

<?php if ($budCss): ?>
<link rel="stylesheet" href="<?= $budCss ?>">
<?php else: ?>
<!-- Bud.Ai: bud.css NOT FOUND.

     The widget below will render unstyled — a full-size robot in the
     page flow instead of a launcher in the corner. Put bud.css in any
     of: assets/  assets/css/  css/  styles/  static/  public/  js/
     the site root  includes/
     and this comment is replaced by the stylesheet link. -->
<?php endif; ?>

<div class="bud" id="bud" data-endpoint="<?= $budApi ?>">

  <div class="bud__launcher">

    <!-- The greeting is a separate control, not part of the button.
         Clicking it opens the panel, its own × dismisses just the
         greeting, and a screen reader announcing the whole sentence as
         the button's name would bury the actual action. -->
    <div class="bud__greet" id="budGreet" hidden>
      <button type="button" class="bud__greet-open" data-bud-open>Hi, how can I help you today!</button>
      <button type="button" class="bud__greet-x" data-bud-dismiss aria-label="Dismiss greeting">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <button type="button"
            class="bud__btn"
            id="budToggle"
            aria-label="Open Bud.Ai"
            aria-expanded="false"
            aria-controls="budPanel">

      <!-- Decorative rings. pointer-events:none in the CSS, or they
           would swallow clicks meant for the page behind them, since
           they spill well past the button box. -->
      <span class="bud__rings" aria-hidden="true"><i></i><i></i><i></i></span>

      <span class="bud__disc">
        <img class="bud__avatar" src="<?= $budAsset('uploads/chatbot.png') ?>" alt="" width="386" height="390" draggable="false">
      </span>

      <!-- The bars are decoration, not information — the whole badge is
           already aria-hidden, so a screen reader never meets them. -->
      <span class="bud__badge" aria-hidden="true">
        Bud<span>.Ai</span>
        <span class="bud__wave"><i></i><i></i><i></i><i></i></span>
      </span>

      <!-- swaps in when the panel is open; CSS crossfades the two -->
      <svg class="bud__btn-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <section class="bud__panel" id="budPanel" aria-labelledby="budPanelTitle" hidden>
    <header class="bud__head">
      <img class="bud__head-avatar" src="<?= $budAsset('uploads/chatbot.png') ?>" alt="" width="386" height="390" draggable="false">
      <div>
        <h3 class="bud__head-name" id="budPanelTitle">Bud.Ai</h3>
        <p class="bud__head-role">Your Camarines Norte guide</p>
      </div>
      <button type="button" class="bud__close" data-bud-close aria-label="Close Bud.Ai">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </header>

    <!-- aria-live so replies are announced as they arrive; polite, not
         assertive, or it interrupts whatever is being read out. -->
    <div class="bud__log" id="budLog" role="log" aria-live="polite">
      <div class="bud__msg bud__msg--bot">
        <p>Hi, I&rsquo;m Bud. Ask me about the beaches, the falls, or how to get around Camarines Norte.</p>
      </div>
    </div>

    <!-- Deliberately not a <form>. A form inside a page that already has
         one submits the wrong thing on Enter; the JS handles Enter and
         the button together. -->
    <div class="bud__compose">
      <label class="sr-only" for="budInput">Message Bud.Ai</label>
      <input type="text" id="budInput" class="bud__input" placeholder="Ask about Camarines Norte&hellip;" autocomplete="off">
      <button type="button" class="bud__send" id="budSend" aria-label="Send message">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/></svg>
      </button>
    </div>
  </section>
</div>

<?php if ($budJs): ?>
<!-- defer: the widget is not needed before the page has painted, and a
     blocking script here would delay everything below it. -->
<script src="<?= $budJs ?>" defer></script>
<?php else: ?>
<!-- Bud.Ai: bud.js NOT FOUND. The launcher will render but do nothing
     when clicked. Put bud.js alongside bud.css. -->
<?php endif; ?>