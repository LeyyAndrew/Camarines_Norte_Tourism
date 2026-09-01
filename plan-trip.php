<?php
/* ===================================================================
   plan-trip.php

   Sits at the project root, beside destinations.php and gallery.php.
   It cannot live in a subfolder: assetUrl() in includes/header.php
   returns paths like "assets/css/base.css" with no leading slash, and
   a relative href resolves against the URL of the page holding it.
   From /pages/plan-trip.php the browser would ask for
   /pages/assets/css/base.css and every stylesheet, both logos and
   nav.js would 404 together.

   The 24 destinations come from includes/destinations-data.php — the
   same file homepage.php and destinations.php read. Add a place there
   and it appears in the picker here without this file being touched.

   $pageTitle must be set before the header require. The stylesheet
   needs no registering: the header derives assets/css/plan-trip.css
   from this file's own name.
   =================================================================== */

/* ---------- TEMPORARY: DELETE THESE TWO LINES ONCE THE PAGE LOADS ----------
   XAMPP ships with display_errors off in some builds, which turns any
   fatal into a blank white page with nothing to go on. Take them out
   before this is public — an error message can name file paths. */
ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- FINDING THE INCLUDES ----------
   The header's docblock calls itself includes/header.php, but a
   docblock is a comment. Rather than requiring one hard-coded path
   and dying with no output at all — which is what a blank page IS —
   this looks in the usual places and says where it looked. */
function pt_find($file) {
    foreach (['includes', 'inc', 'partials', 'template', 'templates', 'layout', ''] as $dir) {
        $path = __DIR__ . '/' . ($dir === '' ? '' : $dir . '/') . $file;
        if (is_file($path)) return $path;
    }
    return null;
}

$ptHeader = pt_find('header.php');
$ptFooter = pt_find('footer.php');
$ptData   = pt_find('destinations-data.php');

if ($ptHeader === null) {
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "plan-trip.php cannot find header.php.\n\n" .
        "It looked in these folders, relative to " . __DIR__ . " :\n" .
        "  includes/  inc/  partials/  template/  templates/  layout/  and this folder\n\n" .
        "Open destinations.php and copy the exact path from its require line\n" .
        "into the list in pt_find() above."
    );
}

$pageTitle = 'Plan your trip — Explore Camarines Norte';
$pageDesc  = 'Build your own day-by-day itinerary around Camarines Norte — pick the stops, set the hours, keep the pace.';

/* ---------- NORMALISING THE DESTINATIONS ----------
   destinations-data.php is written for the cards on homepage.php and
   destinations.php, so its keys are named for that job: tag, town,
   desc. The picker wants category, municipality, blurb. Rather than
   renaming keys in the shared file — which would break both pages —
   the translation happens here, in the one file that needs it.

   The id is built from the town and the name because the shared file
   has no id column. It is what a saved itinerary stores, so renaming
   a destination in that file will orphan itineraries that referenced
   it. If this page gets a database table later, give destinations a
   real primary key and use that instead.

   Photos are checked against the disk here rather than in the
   browser. The data file's own notes say several images may not be in
   uploads/Destination-Photo/ yet; a card that falls back to its type
   gradient looks deliberate, whereas a broken-image icon looks like
   the page is failing. */
$destinations = [];
if ($ptData !== null) {
    foreach (require $ptData as $d) {
        $slug = strtolower(($d['town'] ?? '') . '-' . ($d['name'] ?? ''));
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
        if ($slug === '') continue;

        $img = $d['image'] ?? '';
        if ($img !== '' && !is_file(__DIR__ . '/' . $img)) $img = '';

        $destinations[] = [
            'id'           => $slug,
            /* THE DATABASE KEY, alongside the slug.

               The slug above is what a saved itinerary stores and what
               BY_ID is keyed on — that does not change. This is the
               destinations table's primary key, and it is the only
               thing saved_destinations rows can be matched against, so
               the Saved filter in the picker needs it. null for a row
               served from the fallback list, which has no key. */
            'dest_id'      => $d['id'] ?? null,
            'name'         => $d['name'] ?? '',
            'municipality' => $d['town'] ?? '',
            'category'     => $d['tag'] ?? '',
            'blurb'        => $d['desc'] ?? ($d['quote'] ?? ''),
            'image'        => $img,
        ];
    }
}

/* Both filter axes are read off the data, so a new tag or a new
   municipality appears in the picker on its own. */
$types = array_values(array_unique(array_column($destinations, 'category')));
$towns = array_values(array_unique(array_column($destinations, 'municipality')));
sort($types);
sort($towns);

/* The introduction photograph from destinations.php, reused as the
   banner. Checked, so a missing file leaves the gradient rather than
   a broken panel. */
$heroPhoto = 'uploads/Destination-Photo/calaguas.jpg';
if (!is_file(__DIR__ . '/' . $heroPhoto)) $heroPhoto = '';

require $ptHeader;

/* ---------- THE GATE ----------
   header.php swaps its "Plan your trip" CTA for a data-auth-gate
   button when there is no session, but that only stops the click.
   Anyone can still type this URL, so the page checks for itself.

   It renders rather than redirects: there is no login PAGE to send
   anyone to — the site signs people in through the prompt that
   data-auth-gate opens — so a redirect would have nowhere to go.
   The hero stays, the builder is replaced. */
$ptSignedIn = isset($_SESSION['user_id']);
?>

<main id="main" class="pt">

  <!-- ---------- HERO ----------
       The coordinates in the corner are the pair already printed in
       the footer. Repeating them here is the cheapest way to make a
       new page feel like it belongs to the same site. -->
  <section class="pt-hero">
    <?php if ($heroPhoto !== ''): ?>
      <div class="pt-hero-bg" style="background-image:url('<?= htmlspecialchars(assetUrl($heroPhoto), ENT_QUOTES, 'UTF-8') ?>')"></div>
    <?php endif; ?>
    <div class="pt-wrap">
      <span class="pt-coords">14.11&deg; N 122.95&deg; E</span>
      <span class="pt-label">Build it yourself</span>
      <h1>Create Your Own <em>Adventure</em></h1>
      <p>Build your personalized itinerary and plan your perfect trip around Camarines Norte — your stops, your hours, your pace.</p>
      <div class="pt-figures">
        <div><b><?= count($towns) ?></b>Municipalities</div>
        <div><b><?= count($destinations) ?></b>Places to add</div>
        <div><b><?= count($types) ?></b>Kinds of trip</div>
      </div>
    </div>
  </section>

  <?php if (!$ptSignedIn): ?>

  <div class="pt-wrap pt-board">
    <section class="pt-card pt-gate">
      <span class="pt-label">Members only</span>
      <h2>Sign in to build your itinerary</h2>
      <p>Your days, your stops and your notes are saved to your account, so a trip you start
         today is still here the next time you open this page.</p>
      <!-- The site's one sign-in prompt, the same hook the header uses.
           Not a second login form invented for this page. -->
      <button type="button" class="pt-btn pt-btn--primary" data-auth-gate>
        Sign in or create an account
      </button>
    </section>
  </div>

  <?php else: ?>

  <div class="pt-wrap pt-board">

    <!-- ---------- TRIP DETAILS ---------- -->
    <section class="pt-card">
      <div class="pt-card-h">
        <h2>Trip details</h2>
        <span class="pt-sub">Start here &mdash; the dates build your days for you</span>

      </div>
      <div class="pt-form">
        <div class="pt-field">
          <label for="ptName">Trip name</label>
          <input id="ptName" type="text" placeholder="Bicol long weekend" autocomplete="off">
        </div>
        <div class="pt-field">
          <label for="ptStart">Start date</label>
          <input id="ptStart" type="date">
        </div>
        <div class="pt-field">
          <label for="ptEnd">End date</label>
          <input id="ptEnd" type="date">
        </div>
        <div class="pt-field">
          <label for="ptTravelers">Travellers</label>
          <input id="ptTravelers" type="number" min="1" max="60" value="2">
        </div>
      </div>
    </section>

    <div class="pt-grid">

      <!-- ---------- THE BUILDER ---------- -->
      <div>
        <div class="pt-days" id="ptDays"></div>

        <button type="button" class="pt-add-day" id="ptAddDay">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          Add another day
        </button>

        <!-- Save draft and Clear on the left, the two that finish the
             job on the right, with the destructive one kept away from
             the primary. -->
        <div class="pt-actions">
          <button type="button" class="pt-btn pt-btn--ghost" id="ptSaveDraft">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
            Save draft
          </button>
          <button type="button" class="pt-btn pt-btn--danger" id="ptClear">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>
            Clear itinerary
          </button>
          <span class="pt-spacer"></span>
          <button type="button" class="pt-btn pt-btn--ghost" id="ptPreview">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M2 12s3.8-7 10-7 10 7 10 7-3.8 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            Preview itinerary
          </button>
          <button type="button" class="pt-btn pt-btn--primary" id="ptSave">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            Save my itinerary
          </button>
        </div>
      </div>

      <!-- ---------- SUMMARY ----------
           Sticky from 1080px up, static below, where the grid falls to
           one column and it simply lands under the builder. -->
      <aside class="pt-summary">
        <div class="pt-card">
          <div class="pt-sum-h">
            <span class="pt-label">Summary</span>
            <h2 id="ptSumName">Your itinerary</h2>
            <p id="ptSumTravelers">2 travellers</p>
          </div>
          <div class="pt-figs">
            <div class="pt-fig"><b id="ptStatDays">0</b><span>Days</span></div>
            <div class="pt-fig"><b id="ptStatDest">0</b><span>Stops</span></div>
          </div>
          <div class="pt-dates">
            <div><span>Starts</span><b id="ptSumStart">&mdash;</b></div>
            <div><span>Ends</span><b id="ptSumEnd">&mdash;</b></div>
          </div>
          <div class="pt-ov" id="ptOverview"></div>
        </div>
      </aside>

    </div>
  </div>

  <?php endif; ?>
</main>

<!-- ---------- DESTINATION PICKER ----------
     Outside <main> so the overlay is never inside the element it
     covers, and above the nav's z-index so a sticky header cannot
     show through it. -->
<div class="pt-modal" id="ptDestModal" role="dialog" aria-modal="true" aria-labelledby="ptDestTitle">
  <div class="pt-veil" data-pt-close></div>
  <div class="pt-sheet">
    <div class="pt-sheet-h">
      <span class="pt-label">Destinations</span>
      <h2 id="ptDestTitle">Choose a destination</h2>
      <p><span id="ptCount"><?= count($destinations) ?> places</span> across <?= count($towns) ?> municipalities. Pick one and it drops into your day.</p>
      <div class="pt-search">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#868E94" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="ptSearch" type="search" placeholder="Search a beach, island, falls or town…" autocomplete="off" aria-label="Search destinations">
      </div>
      <button type="button" class="pt-close" data-pt-close aria-label="Close">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="pt-fbar">
      <div class="pt-scope" id="ptScope" role="group" aria-label="Filter by">
        <button type="button" data-scope="type" class="is-on">Type</button>
        <button type="button" data-scope="town">Town</button>
        <!-- The bookmarks made on destinations.php, as a third axis
             beside Type and Town. It belongs in this group rather than
             in a panel of its own: it answers the same question the
             other two do — which of the 24 am I choosing from — and
             picking one has to go through the same click handler that
             fills the day. -->
        <button type="button" data-scope="saved">Saved<span data-saved-count hidden>0</span></button>
      </div>
      <div class="pt-filters" id="ptFilters"></div>
    </div>
    <div class="pt-dests" id="ptDestGrid"></div>
  </div>
</div>

<!-- ---------- PREVIEW ---------- -->
<div class="pt-modal" id="ptPrevModal" role="dialog" aria-modal="true" aria-labelledby="ptPrevTitle">
  <div class="pt-veil" data-pt-close></div>
  <div class="pt-sheet">
    <div class="pt-sheet-h">
      <span class="pt-label">Preview</span>
      <h2 id="ptPrevTitle">Your trip, end to end</h2>
      <p>Print this page to keep a copy.</p>
      <button type="button" class="pt-close" data-pt-close aria-label="Close">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="pt-prev" id="ptPrevBody"></div>
  </div>
</div>

<div class="pt-toast" id="ptToast" role="status" aria-live="polite"></div>

<?php
/* The list is printed as data rather than as markup so filtering and
   searching happen without a round trip. HEX_TAG matters: a place
   name containing </script> would otherwise close this block early. */
?>
<script>
window.PT_DATA = {
  destinations: <?= json_encode($destinations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  types:        <?= json_encode($types, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
  towns:        <?= json_encode($towns, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
  saveUrl:      'save-itinerary.php',
  signedIn:     <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>
};
</script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/plan-trip.js')) ?>" defer></script>

<?php
/* A missing footer is survivable — everything above it has already
   been sent — so close the document by hand rather than fataling on
   the last line and truncating what the visitor is reading. */
if ($ptFooter !== null) {
    require $ptFooter;
} else {
    echo "\n</body>\n</html>\n";
}