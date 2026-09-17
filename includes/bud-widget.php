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
            /* ?v=<last modified> — a cache buster.

               Without it, editing bud.js or bud-map.css and reloading
               can serve you the previous version out of the browser
               cache, and you debug a file the browser is not running.
               That costs hours and it is impossible to see.

               The number only changes when the file does, so a visitor
               still gets a properly cached copy between edits. */
            return $budAsset($rel) . '?v=' . filemtime($root . '/' . $rel);
        }
    }

    return null;   // caller decides what to say about it
};

/* ---- the destination list, for photo cards ----

   Read from includes/destinations-data.php, which is the site's own
   source of truth: database-backed, admin-managed, and carrying the
   photo filename and coordinates that the workbook snapshot does not.

   Only what the cards need goes to the browser - name, photo, town,
   lat, lng. Not the descriptions, not the chips; the reply text
   already covers those and every extra byte is on every page load.

   The JS matches destination names in Bud's reply against this list.
   That is deliberate: no dependence on the model emitting a special
   marker, and no card can ever appear for a place that is not one of
   the 24. */
$budPlaces = [];

/* THROUGH THE LOADER, NOT A DIRECT require OF THE DATA FILE.

   This used to be:

       $destFile = realpath(__DIR__ . '/..') . '/includes/destinations-data.php';
       $rows = require $destFile;

   which was fine on the homepage and fatal on destinations.php. That
   page requires the same data file at its line 20 and this widget at
   its line 2031, and destinations-data.php declares dest_fallback() at
   the top level — so the second require redeclared it and took the
   bottom off the page, footer and all.

   THE try/catch BELOW COULD NOT HAVE STOPPED IT. A redeclare is a
   compile-time fatal, not a Throwable, so the catch never ran. It is
   kept because it still covers the things it was written for.

   includes/dest-load.php runs each data file once per request and
   hands back the cached array after that. is_file() lives in there
   now, so a missing data file still costs this widget its photo cards
   and nothing more. */
require_once __DIR__ . '/dest-load.php';

try {
    foreach (dest_data() as $r) {
        if (empty($r['name'])) {
            continue;
        }
        $budPlaces[] = [
            'n'   => $r['name'],
            'img' => $r['image'] !== '' ? $budAsset($r['image']) : '',
            't'   => $r['town'] ?? '',
            /* The tag, so the map can group a pin and draw the
               right glyph on it. Four to twelve characters —
               the one field worth the extra bytes, because
               without it every pin looks the same. */
            'g'   => $r['tag'] ?? '',
            'la'  => $r['lat'] ?? null,
            'lo'  => $r['lng'] ?? null,
        ];
    }
} catch (Throwable $e) {
    /* No cards is a fine outcome; a broken widget is not. */
    error_log('[bud-widget] destination list unavailable: ' . $e->getMessage());
}

$budCss = $budFind('bud.css');
$budJs  = $budFind('bud.js');

/* The map view. Optional by design: these two are looked up the same
   way as the pair above, and when they are not on disk the widget is
   the chat panel it has always been. Nothing else in this file knows
   the difference. */
$budMapCss = $budFind('bud-map.css');
$budMapJs  = $budFind('bud-map.js');

/* A copy of Leaflet inside the project, if there is one.

   bud-map.js falls back to three CDNs, which is fine on a machine with
   plain internet access and no ad blocker. It is not fine on a locked
   down network, behind a broad filter list, or offline — and 'the map
   pane is empty on my laptop but not on yours' is a miserable bug.

   Drop Leaflet's dist folder in as assets/leaflet/ and this finds it,
   the same way it finds bud.css. The pins and the panel then work with
   no internet at all; only the map imagery behind them needs a
   connection. */
$budLeafletJs = $budLeafletCss = null;

/* The zip from leafletjs.com unpacks to a dist/ folder, so that is the
   layout most people end up with; the npm package puts it in the same
   place. Both are checked, and the stylesheet is taken from whichever
   folder the script was found in rather than looked up on its own —
   two halves of Leaflet from two different copies is a bug nobody
   would enjoy tracking down. */
foreach (['leaflet/', 'leaflet/dist/', 'vendor/leaflet/', 'vendor/leaflet/dist/'] as $budLeafletDir) {
    $budLeafletTry = $budFind($budLeafletDir . 'leaflet.js');
    if ($budLeafletTry !== null) {
        $budLeafletJs  = $budLeafletTry;
        $budLeafletCss = $budFind($budLeafletDir . 'leaflet.css');
        break;
    }
}

/* OPTIONAL: a CARTO basemap key.

   The map draws on OpenStreetMap tiles by default, darkened in CSS.
   They need no key and nothing to sign up for.

   CARTO's own dark cartography is cleaner to read at province zoom. It
   stopped being keyless in August 2026, but a key is still free, needs
   no account, and covers 5 million tiles a month:
   https://carto.com/basemaps/apikey

   To use it, define the constant anywhere that runs before this file —
   your config include is the sensible place, not here, so the key is
   not sitting in a file you would paste into a chat:

       define('BUD_CARTO_KEY', 'your-key');

   Note that CARTO have said the raster tiles are being retired, so
   this is the nicer option rather than the safer one. */
$budCartoKey = defined('BUD_CARTO_KEY') ? (string) BUD_CARTO_KEY : '';

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
<?php if ($budMapCss): ?>
<!-- After bud.css, always: every rule in it is written to layer on top. -->
<link rel="stylesheet" href="<?= $budMapCss ?>">
<?php endif; ?>
<?php else: ?>
<!-- Bud.Ai: bud.css NOT FOUND.

     The widget below will render unstyled — a full-size robot in the
     page flow instead of a launcher in the corner. Put bud.css in any
     of: assets/  assets/css/  css/  styles/  static/  public/  js/
     the site root  includes/
     and this comment is replaced by the stylesheet link. -->
<?php endif; ?>

<?php if ($budPlaces): ?>
<!-- The 24, for the photo cards. JSON_HEX_* so the payload can never
     close this script tag or open another, whatever a name contains. -->
<script type="application/json" id="budPlaces"><?= json_encode($budPlaces,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>

<div class="bud" id="bud" data-endpoint="<?= $budApi ?>"
<?php if ($budLeafletJs): ?>
     data-leaflet-js="<?= $budLeafletJs ?>"
     data-leaflet-css="<?= $budLeafletCss ?>"
<?php endif; ?>
<?php if ($budCartoKey !== ''): ?>
     data-carto-key="<?= htmlspecialchars($budCartoKey, ENT_QUOTES) ?>"
<?php endif; ?>
     >

  <div class="bud__launcher">

    <!-- The greeting is a separate control, not part of the button.
         Clicking it opens the panel, its own × dismisses just the
         greeting, and a screen reader announcing the whole sentence as
         the button's name would bury the actual action. -->
    <div class="bud__greet" id="budGreet" hidden>
      <button type="button" class="bud__greet-open" data-bud-open><b class="bud__marhai">MarhAi!</b> &#128075; Planning a trip to Camarines Norte? I can make your itinerary.</button>
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
        <img class="bud__avatar" src="<?= $budAsset('uploads/chatbot.png') ?>" alt="" width="386" height="390">
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
      <!-- Avatar plus a live dot, so the header reads as someone who is
           here now rather than a static label. -->
      <span class="bud__head-pic">
        <img class="bud__head-avatar" src="<?= $budAsset('uploads/chatbot.png') ?>" alt="" width="386" height="390" draggable="false">
        <span class="bud__online" aria-hidden="true"></span>
      </span>
      <div>
        <h3 class="bud__head-name" id="budPanelTitle">Bud.Ai</h3>
        <p class="bud__head-role"><span class="bud__head-status">Online</span> Your Camarines Norte travel buddy</p>
      </div>
      <button type="button" class="bud__close" data-bud-close aria-label="Close Bud.Ai">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </header>

    <!-- aria-live so replies are announced as they arrive; polite, not
         assertive, or it interrupts whatever is being read out. -->
    <div class="bud__log" id="budLog" role="log" aria-live="polite">
      <!-- Messenger-style row: a small Bud next to his own bubbles.
           bud.js builds the same markup for every later reply. -->
      <!-- Two short bubbles rather than one long one: a hello, then what
           Bud can do. Reads like someone typing, not a notice. -->
      <div class="bud__row bud__row--bot">
        <img class="bud__row-avatar" src="<?= $budAsset('uploads/chatbot.png') ?>" alt="" width="386" height="390" draggable="false">
        <div class="bud__msg bud__msg--bot">
          <p><b class="bud__marhai">MarhAi!</b> &#128075; Maogmang pag-abot &mdash; welcome to Camarines Norte! I&rsquo;m Bud, your travel buddy.</p>
        </div>
      </div>
      <div class="bud__row bud__row--bot">
        <img class="bud__row-avatar" src="<?= $budAsset('uploads/chatbot.png') ?>" alt="" width="386" height="390" draggable="false">
        <div class="bud__msg bud__msg--bot">
          <p>I can plan a day-by-day itinerary for you &#128506;&#65039;, help you get to Calaguas and the falls, and tell you what to pack. Dios mabalos for dropping by &mdash; where do we start?</p>
        </div>
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
<?php if ($budMapJs): ?>
<!-- Also deferred, and AFTER bud.js. Two deferred scripts run in the
     order they appear, so bud.js has built the panel before this one
     goes looking for it.

     Leaflet is NOT loaded here. bud-map.js fetches it the first time
     somebody actually opens the map, so a visitor who never does pays
     nothing for it. -->
<script src="<?= $budMapJs ?>" defer></script>
<?php endif; ?>
<?php else: ?>
<!-- Bud.Ai: bud.js NOT FOUND. The launcher will render but do nothing
     when clicked. Put bud.js alongside bud.css. -->
<?php endif; ?>