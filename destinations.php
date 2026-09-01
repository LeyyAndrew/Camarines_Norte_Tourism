<?php
/* ===================================================================
   destinations.php — the full index, all 24 in one scrollable page.

   This is deliberately NOT the homepage carousel. The carousel browses
   one at a time; this page is for comparing, and for finding a
   specific town. Same 24 places, different job.

   All content comes from includes/destinations-data.php, the same file
   the homepage reads. Edit a name, photo or description there and both
   pages change together — there is no second copy to keep in sync.

   PHOTO unique to this page:
     uploads/dest-banner.jpg   wide landscape, 1920x800 or similar
   =================================================================== */
$pageTitle = 'Destinations — Explore Camarines Norte';
$pageDesc  = '24 places across the twelve municipalities of Camarines Norte: islands, waterfalls, heritage towns, and the coastline in between.';
require __DIR__ . '/includes/header.php';

$destinations = require __DIR__ . '/includes/destinations-data.php';

/* The long-form fields the map balloon opens into: how to get there,
   what to eat, who to book with. A separate file from the data above
   because homepage.php reads that one and does not use any of this —
   see the header of includes/destination-details.php. */
$destDetails = require __DIR__ . '/includes/destination-details.php';


/* ===================================================================
   COORDINATES

   These used to be a $coords array typed into this page, with a
   comment saying: "TO MOVE THEM PROPERLY: add 'lat' and 'lng' to each
   entry in includes/destinations-data.php and delete this array."

   That is now done. The verified numbers moved into the destinations
   table, seeded from that exact array, and the admin edits them at
   admin/destinations.php. The array is gone so there is only one
   place a pin can be wrong.

   null means no pin. The destination is left off the map rather than
   dropped at 0,0, which is a real spot in the Atlantic.
   =================================================================== */
function destLatLng(array $d) {
    if (!isset($d['lat'], $d['lng'])) return null;
    return [(float) $d['lat'], (float) $d['lng']];
}

/* a stable id for linking a map pin to its card and back again */
function destSlug($name) {
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/* ===================================================================
   FILTERING

   Both filters are query parameters so a filtered view is a real URL
   people can bookmark and share. The homepage trip finder already
   sends ?type=Beach, and that keeps working untouched.

   Town is now a FILTER rather than a page section. The index used to
   be twelve headed blocks, which meant twelve separate grids of two —
   the eye never got a row to scan. One continuous grid reads far
   better, and anyone after a specific municipality picks it from the
   sidebar.
   =================================================================== */
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$town = isset($_GET['town']) ? trim($_GET['town']) : '';
$cat  = isset($_GET['cat'])  ? trim($_GET['cat'])  : '';

/* ?q= is the banner search box. It is a plain substring match across
   name, town, tag and description — 24 rows, so there is nothing to
   index and nothing to optimise. It is a query parameter like the
   others, which means a search result is a shareable URL too. */
$q    = isset($_GET['q'])    ? trim($_GET['q'])    : '';

$allPlaces = $destinations;   /* the unfiltered 24, for counts */

/* ===================================================================
   CATEGORIES

   The raw tags in destinations-data.php are far too fine-grained to
   filter by. Seventeen of them across 24 places means most tags return
   one or two results, and worse, they split things a visitor thinks of
   as the same:

     Capalonga's Shrine of the Black Nazarene  ->  tag "Religious Site"
     Talisay's St. Francis of Assisi Church    ->  tag "Church"

   So "Church" returned Talisay and nothing else, which reads as a
   broken filter rather than a tagging decision. Nobody browsing wants
   the distinction between a shrine and a parish church — they want
   churches.

   These groups are what the sidebar filters by. The tags themselves are
   untouched and still show on each card, so nothing in the data file
   has to change.

   ADDING A TAG LATER: put it in whichever group fits. Anything not
   listed here is collected under "Other" automatically rather than
   silently becoming unreachable.
   =================================================================== */
$categories = [
    'Beaches & Islands' => ['Beach', 'Beach Resort', 'Island', 'Surf'],
    'Falls & Rivers'    => ['Waterfall', 'River'],
    'Peaks & Views'     => ['Mountain', 'Peak', 'View Deck'],
    'Heritage'          => ['Church', 'Religious Site', 'Monument'],
    'Parks & Nature'    => ['Mangrove', 'Park'],
    'Stay & Adventure'  => ['Campsite', 'Farm Resort', 'Adventure'],
];

/* tag -> category, so a lookup is one array access rather than a scan */
$tagCat = [];
foreach ($categories as $label => $tags) {
    foreach ($tags as $t) { $tagCat[strtolower($t)] = $label; }
}
function catOf($tag, $tagCat) {
    return $tagCat[strtolower($tag)] ?? 'Other';
}

$destinations = array_values(array_filter($destinations, function ($d) use ($type, $town, $cat, $q, $tagCat) {
    /* ?type= is an exact tag match. The homepage trip finder still sends
       it, so it has to keep working exactly as it did. */
    if ($type !== '' && strcasecmp($d['tag'], $type) !== 0) return false;
    if ($cat  !== '' && catOf($d['tag'], $tagCat) !== $cat) return false;
    if ($town !== '' && strcasecmp($d['town'], $town) !== 0) return false;
    if ($q !== '') {
        $hay = strtolower($d['name'] . ' ' . $d['town'] . ' ' . $d['tag'] . ' ' . $d['desc']);
        if (strpos($hay, strtolower($q)) === false) return false;
    }
    return true;
}));

/* builds a link that changes one filter and keeps the others.

   EVERY ONE OF THESE ENDS IN #destFilters. A chip is a real link, so
   clicking one is a full page load — and without a fragment the new
   page opens at the very top, with the banner and the introduction
   between you and the filter bar you just used. You pick "Falls &
   Rivers", the page reloads, and the chips are gone off-screen.

   With the fragment the reload lands back on the bar, the chip you
   chose lit, the new count and cards under it — so a chip behaves like
   a control on the page rather than a link off it, while staying a
   plain <a href> that works with JavaScript off and is still
   bookmarkable.

   It is appended HERE rather than at each call site so nothing can be
   missed: the category chips, the town chips, "Widen this" and every
   future caller all get it from this one return. */
function destUrl($type, $town, $cat = '', $q = '') {
    $params = array_filter(
        ['type' => $type, 'cat' => $cat, 'town' => $town, 'q' => $q],
        function ($v) { return $v !== ''; }
    );
    return 'destinations.php'
        . ($params ? '?' . http_build_query($params) : '')
        . '#destFilters';
}

/* counts for the sidebar, from the unfiltered set */
$catCounts  = [];
$townCounts = [];
foreach ($allPlaces as $d) {
    $c = catOf($d['tag'], $tagCat);
    $catCounts[$c]          = ($catCounts[$c] ?? 0) + 1;
    $townCounts[$d['town']] = ($townCounts[$d['town']] ?? 0) + 1;
}

/* keep the declared order, then anything that fell through to Other */
$ordered = [];
foreach (array_keys($categories) as $label) {
    if (!empty($catCounts[$label])) $ordered[$label] = $catCounts[$label];
}
if (!empty($catCounts['Other'])) $ordered['Other'] = $catCounts['Other'];
$catCounts = $ordered;

/* WHAT A PIN CARRIES NOW.

   It used to be five keys — enough to drop a dot and label it. The
   hover balloon needs the photograph and the tag, and the panel a
   click opens needs the description, the three facts, and the three
   long-form fields out of includes/destination-details.php. All of it
   is printed once, here, into the same JSON island the map already
   read, so assets/js/destinations-map.js stays a plain cacheable file
   with no PHP in it.

   THE PAYLOAD IS ABOUT 8KB for all 24. That is smaller than one of
   the photographs and it removes any need for a second request when
   somebody opens a panel, which is the alternative.

   BOOKING FALLS BACK to the provincial contact at the top of
   destination-details.php whenever a destination has no operator of
   its own — which today is twenty-three of the twenty-four. One real
   contact on every panel beats twenty-three blanks. */
$mapPoints = [];
foreach ($destinations as $d) {
    $ll = destLatLng($d);
    if (!$ll) continue;

    $x = $destDetails['places'][$d['name']] ?? [];

    $mapPoints[] = [
        'slug'  => destSlug($d['name']),
        'name'  => $d['name'],
        'town'  => $d['town'],
        'tag'   => $d['tag'],
        'lat'   => $ll[0],
        'lng'   => $ll[1],

        /* for the balloon and the top of the panel */
        'image' => $d['image'],
        'quote' => $d['quote'] ?? '',
        'desc'  => $d['desc']  ?? '',
        'chips' => array_slice($d['chips'] ?? [], 0, 3),

        /* the three sections of the panel */
        'how'   => $x['how'] ?? [],
        'eat'   => $x['eat'] ?? [],
        'book'  => $x['book'] ?? ($destDetails['fallback'] ?? []),
    ];
}

/* ===================================================================
   THE RAIL

   EVERY destination gets a card. All 24 unfiltered, fewer once a
   filter is on, because $destinations is the FILTERED set — the rail
   never advertises a beach on a page currently showing waterfalls.

   THE ORDER IS NOT THE DATA FILE'S ORDER. One pass takes the first
   place from each municipality, then a second pass adds everything
   left over. So the front of the row is twelve different towns rather
   than the two in Basud, the two in Capalonga, the two in Daet — the
   part of the row you see without scrolling shows the spread of the
   province instead of the top of an alphabet.

   No cap. There used to be one, $railMax = 7, from when the rail was
   a teaser strip in the corner of a banner and only had room for six
   cards beside the featured one. The rail is the section's whole
   bottom half now and scrolls, so a cap would only be hiding
   destinations behind nothing.
   =================================================================== */
$railPicks = [];
$railTowns = [];
foreach ($destinations as $d) {
    if (in_array($d['town'], $railTowns, true)) continue;
    $railTowns[] = $d['town'];
    $railPicks[] = $d;
}
$picked = array_column($railPicks, 'name');
foreach ($destinations as $d) {
    if (in_array($d['name'], $picked, true)) continue;
    $railPicks[] = $d;
}

/* ===================================================================
   THE FEATURED PLACE

   The section names ONE destination above the rail — its town, its
   name in display caps, its own description — and clicking any card
   changes which one that is.

   IT IS ALSO IN THE RAIL, unlike the old arrangement where the
   featured place was held back so it could not appear twice. It had to
   be held back then because the banner showed its PHOTO full bleed and
   the same picture twice on one screen reads as a bug. There is no
   photo behind the text now, so the card is the only place that
   picture appears and the rail can simply hold everything.

   The first pick is what loads. Everything the switch needs is printed
   once as JSON below, the same data-island pattern the map already
   uses, so assets/js/destinations-hero.js stays a plain cacheable file
   with no PHP in it.
   =================================================================== */
$featured = $railPicks[0] ?? ($destinations[0] ?? null);

$heroSlides = [];
foreach ($railPicks as $d) {
    $heroSlides[] = [
        /* the primary key, so the hero's save button can re-point at
           whichever destination is currently showing */
        'id'    => $d['id'] ?? null,
        'slug'  => destSlug($d['name']),
        'name'  => $d['name'],
        'town'  => $d['town'],
        'tag'   => $d['tag'],
        'desc'  => $d['desc'],
        'image' => $d['image'],
    ];
}

/* ===================================================================
   THE INTRODUCTION

   Two photographs and about a hundred words between the banner and the
   featured block, so the page says what the province IS before it
   starts asking which part of it you want.

   NOTHING IS HARD-CODED. The photographs, their names and their towns
   all come out of includes/destinations-data.php, and the four numbers
   in the fact row are counted from the same arrays the sidebar counts
   from. Add a thirteenth municipality to the data file and this block
   says thirteen without being touched.

   CHOOSING THE TWO PHOTOGRAPHS: by name, so it is one edit to change
   them, with a positional fallback so a renamed destination downgrades
   to a different picture rather than to an empty box. Pick a wide
   landscape for the tall slot and something closer in for the short
   one — two wide horizons side by side read as the same photograph
   twice.
   =================================================================== */
$introByName = [];
foreach ($allPlaces as $d) { $introByName[$d['name']] = $d; }

$introBig   = $introByName['Calaguas Island'] ?? ($allPlaces[0] ?? null);
$introSmall = $introByName['Mananap Falls']   ?? ($allPlaces[1] ?? null);

/* ---------- THE INTRODUCTION'S OWN PHOTOGRAPHS ----------

   These two do NOT come from the destinations table, unlike every
   other photo on the page.

   The rows above still supply the caption — the name and the town
   under the tall shot — so renaming a destination in the admin still
   updates this block's text. Only the pictures are fixed, because this
   is the page's opening spread and it is cropped for this layout: the
   tall one is a portrait shape and the wide one a landscape, while the
   card photos are all 4:3. Pointing them at the card images meant one
   of the two was always badly cropped.

   Both are checked against the disk. A missing file leaves the empty
   string, which is what .gradient-fill in base.css draws over — the
   graded panel rather than a broken-image glyph. */
$introBigPhoto   = 'uploads/Destination-Photo/calaguas.jpg';
$introSmallPhoto = 'uploads/Destination-Photo/small-calaguas.jpg';

if (!is_file(__DIR__ . '/' . $introBigPhoto))   $introBigPhoto   = '';
if (!is_file(__DIR__ . '/' . $introSmallPhoto)) $introSmallPhoto = '';

/* ONLY ON THE UNFILTERED PAGE.

   Someone who has searched, or picked a category, or arrived from the
   homepage trip finder, has already told the site what they came for.
   Putting an essay about the province above their results makes them
   scroll past an answer they did not ask for. The banner and the
   featured block still cover the arrival case.

   SET TO true, so it is on every view now — filtered or not. The page
   reads the same however you arrived, and the menu links carry
   #destFilters so anyone who already picked a category is scrolled
   past this rather than made to scroll past it themselves.

   TO HIDE IT ON FILTERED VIEWS AGAIN, put the condition back:
   $showIntro = ($type === '' && $town === '' && $cat === '' && $q === ''); */
$showIntro = true;
?>

<!-- Leaflet: the map library. OpenStreetMap tiles, so there is no API
     key to obtain and no billing account to attach. Switching to Google
     Maps later would require both. -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<!-- The banner's own stylesheet, loaded HERE rather than from
     includes/header.php.

     header.php is shared by every page. A <link> added there would be
     downloaded by Home, About, Gallery and Contact too, none of which
     have this banner — dead weight on four pages out of five, and one
     shared file that breaks all of them when it is edited carelessly.

     Loading it from the page that uses it keeps the whole banner in one
     place: this file, assets/css/destinations-hero.css, and
     assets/js/destinations-hero.js. Nothing is left behind in a shared
     file if the page is ever removed.

     It sits below the header's own stylesheets, so it loads last and
     wins any tie with base.css or destinations.css. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/destinations-hero.css')) ?>">

<!-- The introduction block below the banner. Shared with the About
     band on about.php, which is why it is not called
     destinations-something — it is one component used on two pages,
     and both pages link it from inside the body like this one. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/intro-split.css')) ?>">

<!-- ===================================================================
     THE BANNER

     One destination, full bleed, with the rail of switchable places
     along the bottom right.

     THIS ABSORBED THE OLD .dest-strip SECTION. The strip used to be its
     own band below the banner, on the reasoning that a headline over a
     video and a row of cards were two jobs for one element. That held
     while the banner was a generic headline. It stopped holding once
     the banner became a place: the rail is not decoration hanging off
     the title any more, it is the control that changes what the title
     says. Splitting the two would put a section border between a
     control and the thing it controls.

     If you bring the separate strip back, the CSS for it is gone too —
     see section 46 in destinations.css.

     IT IS THE SHARED .page-hero COMPONENT NOW, the same one about.php
     uses, from section 15 of assets/css/base.css. That is what makes this
     banner the same height and the same shape as the interior pages
     rather than something maintained on its own — change .page-hero
     and every banner on the site moves together.

     Its own bespoke shell (.hero-feature, its left-weighted scrim, its
     search box, its featured-place text block) is gone. Two pieces of
     it were kept because .page-hero has no equivalent:
       .hero-feature__photo   the crossfade the strip below drives
       .hero-feature__down    the scroll cue

     THE VIDEO IS THE BANNER. There is no photo layer over it any more.

     There used to be one: the first destination's photo, full bleed,
     swapped by the strip below. Two things killed it. It covered the
     video completely, so the video only ever appeared when the photo
     404'd — which read as the video being broken rather than as a
     design. And the banner now says something about the whole
     province, so pinning it to one destination's picture was the
     banner contradicting its own heading.

     LAYERS, bottom to top:
       1  video.photo-layer      the video, or the poster if it fails
       2  .page-hero__scrim      dark at the bottom so the text reads
       3  .page-hero__inner      eyebrow, heading, lead

     NOT INSIDE if ($featured). The banner is about Camarines Norte,
     not about a destination, so it has nothing to say only when one
     exists. A filter that matches nothing used to take the whole
     banner down with it and drop you straight onto an empty grid.
     =================================================================== -->
<header class="page-hero" id="heroFeature">

  <!-- Same uploads/bg.mp4 the homepage hero uses, so it is usually
       already cached by the time anyone reaches this page. The poster
       is what shows if it is not, or if the browser refuses autoplay
       — never a blank panel. -->
  <video class="photo-layer"
         src="uploads/bg.mp4"
         poster="uploads/dest-banner.jpg"
         autoplay muted loop playsinline
         preload="metadata"
         disablepictureinpicture
         disableremoteplayback></video>

  <div class="page-hero__scrim"></div>

  <div class="wrap page-hero__inner">
    <span class="page-hero__eyebrow">Destinations</span>
    <h1 class="font-display page-hero__title">Twenty-four places<br>across twelve towns</h1>
    <p class="page-hero__lead">The whole of Camarines Norte in one list — coastline and islands, waterfalls in the interior, heritage in the town centres. Filter by the kind of place you are after, or by the municipality you are heading to.</p>
  </div>

  <!-- ---- scroll cue ----
       Skips the banner and lands on the list. It is a link rather than
       a scroll script so it works with JavaScript off and shows the
       target in the status bar on hover. -->
  <a class="hero-feature__down" href="#destList" aria-label="Skip to the list of destinations">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <polyline points="6 10 12 16 18 10"></polyline>
    </svg>
  </a>

</header>

<?php if ($showIntro): ?>
<!-- ===================================================================
     THE INTRODUCTION

     Between the banner and the featured block, and the only light band
     in the top half of the page: dark video above, grey here, dark
     photograph below. Three sections that all read at a glance as
     different things.

     WHAT EACH COLUMN IS FOR
       1  the prose      what the province is, and how to use the page
       2  the photograph the argument for reading further
       3  the aside      the one thing worth knowing before choosing

     It is the same component as the About band, from
     assets/css/intro-split.css. Change the type scale or the photo shape
     there and both pages move together — that is the whole reason it
     is not written inline here.

     NO data-aos ANYWHERE IN HERE, for the reason spelled out above
     .dest-card further down: the fade-up transform sticks on anything
     that never receives .aos-animate and would park this block 100px
     low. Reveals on this page are GSAP's job.
     =================================================================== -->
<section class="intro-split" aria-labelledby="introTitle">
  <div class="wrap">

    <div class="intro-split__grid">

      <!-- ---- 1. the prose ---- -->
      <div class="intro-split__lede">

        <span class="intro-split__eyebrow">Start here</span>

        <!-- h2, not h1 — the page's h1 is the banner heading above.
             The <b> is the second half of the sentence, set heavy: the
             two-weight headline from section 19 of base.css. -->
        <h2 class="font-display intro-split__title" id="introTitle">
          Small enough to cross in a morning,
          <b>varied enough to fill a week.</b>
        </h2>

        <p class="intro-split__body">
          Camarines Norte sits at the northern end of the Bicol Peninsula,
          facing the open Pacific. The places on this page are spread
          across all twelve of its municipalities &mdash; surf at Bagasbas,
          islands off Vinzons, waterfalls an hour inland, and gold towns
          that have worked the same trade since before the maps were drawn.
        </p>

        <p class="intro-split__body">
          Almost everyone arrives through Daet, and most of the province is
          a short drive from it. Two or three destinations in a day is an
          ordinary plan here rather than an ambitious one.
        </p>

        <!-- a real anchor to the list, so it works with JavaScript off
             and shows its target on hover -->
        <a class="intro-link" href="#destList">
          <span>See all <?= count($allPlaces) ?> destinations</span>
          <span class="intro-link__dot" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
              <line x1="4" y1="12" x2="19" y2="12"></line>
              <polyline points="13 6 19 12 13 18"></polyline>
            </svg>
          </span>
        </a>

      </div>

      <!-- ---- 2. the anchor photograph ----
           alt is empty ON PURPOSE: the caption card below names the
           place in real text, so alt here would have a screen reader
           announce it twice. .gradient-fill is the fallback that shows
           instead of a broken-image glyph if the file is missing. -->
      <?php if ($introBig): ?>
      <figure class="intro-split__figure">
        <div class="media intro-shot intro-shot--tall">
          <div class="gradient-fill"></div>
          <?php if ($introBigPhoto !== ''): ?>
          <img class="photo-layer" src="<?= htmlspecialchars(assetUrl($introBigPhoto)) ?>"
               alt="" loading="lazy">
          <?php endif; ?>
        </div>

        <!-- OUTSIDE the .media box, not inside it. <figcaption> has to
             be a direct child of its <figure> to be valid, and .media
             clips to its own rounded corners — a caption in there is
             one radius change away from being trimmed. -->
        <figcaption class="intro-shot__cap">
          <strong><?= htmlspecialchars($introBig['name']) ?></strong>
          <span><?= htmlspecialchars($introBig['town']) ?></span>
        </figcaption>
      </figure>
      <?php endif; ?>

      <!-- ---- 3. the aside ---- -->
      <div class="intro-split__aside">

        <?php if ($introSmall): ?>
        <figure class="intro-split__figure">
          <div class="media intro-shot intro-shot--wide">
            <div class="gradient-fill"></div>
            <?php if ($introSmallPhoto !== ''): ?>
            <img class="photo-layer" src="<?= htmlspecialchars(assetUrl($introSmallPhoto)) ?>"
                 alt="" loading="lazy">
            <?php endif; ?>
          </div>
        </figure>
        <?php endif; ?>

        <!-- the heading and the paragraph are wrapped together so the
             aside can turn sideways at tablet width — photo on one
             side, both pieces of text on the other — without the two
             of them landing in separate grid rows with a gap between
             them. -->
        <div class="intro-split__aside-text">
          <h3 class="font-display intro-split__sub">Three landscapes,<br>one province</h3>

          <p class="intro-split__body">
            The coastline runs almost the whole way around. The interior
            climbs into forest within minutes of the highway. The heritage
            sits in the town centres in between &mdash; and most trips here
            end up taking in all three without setting out to.
          </p>
        </div>

      </div>
    </div>

    <!-- ---- the fact row ----
         Counted, not typed. Three of these four come straight from the
         arrays the sidebar already builds, so they cannot disagree with
         the filters below them.

         THE FOURTH IS THE ONE TO CHECK before this goes live: the dry
         months are a judgement about the province, not a number in the
         data file. Bicol is wettest from around June through November.
         Edit the two lines below if the tourism office words it
         differently. -->
    <ul class="intro-facts">
      <li>
        <strong class="intro-fact__n"><?= count($allPlaces) ?></strong>
        <span class="intro-fact__l">Destinations listed</span>
      </li>
      <li>
        <strong class="intro-fact__n"><?= count($townCounts) ?></strong>
        <span class="intro-fact__l">Municipalities</span>
      </li>
      <li>
        <strong class="intro-fact__n"><?= count($catCounts) ?></strong>
        <span class="intro-fact__l">Kinds of place</span>
      </li>
      <li>
        <strong class="intro-fact__n">Nov&ndash;May</strong>
        <span class="intro-fact__l">Driest months</span>
      </li>
    </ul>

  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<!-- ===================================================================
     THE FEATURED PLACE

     A section of its own, directly under the banner. The banner says
     what the page is; this says where to start.

     ONE destination filling the block — its photo full bleed, its name
     in display caps, its own description — with every other
     destination as a card along the bottom right.

     THREE WAYS TO CHANGE IT, all doing the same thing:
       click a card
       the previous / next arrows under the text
       (with JavaScript off) the cards are plain #anchors and jump to
       the matching card in the list below instead

     The photo crossfades and the name, town, description and Explore
     link are rewritten in place rather than the block being rebuilt,
     so focus survives the switch.

     Filters narrow this. Pick "Falls & Rivers" and the section is
     waterfalls, because $railPicks comes from the FILTERED set.

     LAYERS, bottom to top:
       1  .hero-feature__fallback   the video, seen only through gaps
       2  .hero-feature__photo      the destination photo, crossfaded
       3  .hero-feature__scrim      dark left for the text, dark bottom
                                    for the rail captions
       4  .hero-search + __inner    search, name, buttons, stepper
       5  .hero-rail                the cards

     NO data-aos anywhere in here. AOS's fade-up transform sticks on
     anything that never receives .aos-animate, and it would park the
     whole block 100px low. GSAP in homepage.js handles reveals.
     =================================================================== -->
<section class="hero-feature">

  <!-- The video is the FLOOR, not the picture. Same uploads/bg.mp4 the
       banner above uses, so it is already decoded and costs nothing
       here. It only becomes visible where a destination photo is
       missing. -->
  <video class="hero-feature__fallback"
         src="uploads/bg.mp4"
         poster="uploads/dest-banner.jpg"
         autoplay muted loop playsinline
         preload="metadata"
         disablepictureinpicture
         disableremoteplayback></video>

  <!-- alt is empty ON PURPOSE: the name is in the heading directly
       below, and a screen reader should not read the same place
       twice. -->
  <!-- TWO layers, taking turns. The old photo holds while the new one
       fades up over it, so the section is never showing neither — see
       the crossfade note in assets/css/destinations-hero.css.

       Layer B carries the SAME src as A to begin with. An <img> with no
       src is a broken image in some browsers, and since it is sitting
       at opacity:0 behind a copy of itself, the duplicate costs one
       cache hit and nothing else. The script overwrites it on the first
       switch and it is never the same picture twice again. -->
  <img class="hero-feature__photo photo-layer"
       id="heroPhoto"
       src="<?= htmlspecialchars($featured['image']) ?>" alt="">

  <img class="hero-feature__photo photo-layer is-idle"
       id="heroPhotoB"
       src="<?= htmlspecialchars($featured['image']) ?>" alt="" aria-hidden="true">

  <div class="hero-feature__scrim"></div>

  <!-- ---- search ----
       A GET form, so a search is a URL: destinations.php?q=falls can be
       bookmarked and sent to someone. The hidden inputs carry whatever
       filters are already on, otherwise searching would silently throw
       away the sidebar selection. -->
  <form class="hero-search" method="get" action="destinations.php" role="search">
    <?php if ($type !== ''): ?><input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>"><?php endif; ?>
    <?php if ($cat  !== ''): ?><input type="hidden" name="cat"  value="<?= htmlspecialchars($cat) ?>"><?php endif; ?>
    <?php if ($town !== ''): ?><input type="hidden" name="town" value="<?= htmlspecialchars($town) ?>"><?php endif; ?>
    <label class="visually-hidden" for="destSearch">Search destinations</label>
    <input class="hero-search__field"
           id="destSearch" type="search" name="q"
           value="<?= htmlspecialchars($q) ?>"
           placeholder="Search tourist spots" autocomplete="off">
    <button class="hero-search__go" type="submit" aria-label="Search">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <circle cx="11" cy="11" r="7"></circle>
        <line x1="16.5" y1="16.5" x2="21" y2="21"></line>
      </svg>
    </button>
  </form>

  <div class="hero-feature__inner">

    <!-- Every one of these carries an id: destinations-hero.js writes
         into them when the destination changes.

         h2, not h1 — the page's h1 is the banner heading above. -->
    <div class="hero-feature__text">

      <!-- what KIND of place this is. The block could tell you its
           name, its town and a sentence about it, and still leave you
           to work out from the prose whether it was a beach or a
           church. -->
      <span class="hero-feature__tag" id="heroTag"><?= htmlspecialchars($featured['tag']) ?></span>

      <p class="hero-feature__loc" id="heroLoc">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 2a7.4 7.4 0 0 0-7.4 7.4C4.6 15 12 22.5 12 22.5s7.4-7.5 7.4-13.1A7.4 7.4 0 0 0 12 2zm0 10.1a2.7 2.7 0 1 1 0-5.4 2.7 2.7 0 0 1 0 5.4z"></path>
        </svg>
        <span><?= $featured['town'] ?>, Camarines Norte</span>
      </p>

      <h2 class="font-display hero-feature__title" id="heroTitle"><?= $featured['name'] ?></h2>

      <p class="hero-feature__desc" id="heroDesc"><?= $featured['desc'] ?></p>

      <div class="hero-feature__actions">
        <!-- a real anchor to the matching card further down the page,
             rewritten by the JS when the destination changes -->
        <a class="hero-feature__cta" id="heroCta"
           href="#dest-<?= destSlug($featured['name']) ?>">Explore</a>

        <!-- SIGNED IN this is a working bookmark; signed out it stays
             the auth gate it has always been.

             data-destination-id starts on the featured destination and
             is rewritten by assets/js/saved-places.js each time the
             hero changes slide — the block cycles through 24 places
             behind one button, so a fixed id would save the wrong one
             from the second slide onwards. -->
        <?php if (isset($_SESSION['user_id']) && !empty($featured['id'])): ?>
        <button type="button" class="hero-feature__save" data-save data-hero-save
                data-destination-id="<?= (int) $featured['id'] ?>"
                aria-pressed="false"
                aria-label="Save this destination">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-4-6.5 4v-16a1 1 0 0 1 1-1z"></path>
          </svg>
        </button>
        <?php else: ?>
        <button type="button" class="hero-feature__save" data-auth-gate
                aria-label="Save this destination">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6.5 3.5h11a1 1 0 0 1 1 1v16l-6.5-4-6.5 4v-16a1 1 0 0 1 1-1z"></path>
          </svg>
        </button>
        <?php endif; ?>
      </div>

      <!-- ---- previous / next ----
           Printed by PHP, not built by the JS, so the count is right
           before a single script runs and the buttons are focusable
           straight away. They do nothing without JavaScript — which is
           why the cards are anchors and these are <button>s: a control
           that cannot work without a script should not look like a
           link that can.

           aria-live on the counter so a screen reader hears the move.
           It is polite, not assertive: stepping quickly through six
           should not queue six interruptions. -->
      <?php if (count($railPicks) > 1): ?>
      <div class="hero-stepper">
        <button type="button" class="hero-stepper__btn" id="heroPrev" aria-label="Previous destination">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <polyline points="14 6 8 12 14 18"></polyline>
          </svg>
        </button>
        <button type="button" class="hero-stepper__btn" id="heroNext" aria-label="Next destination">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <polyline points="10 6 16 12 10 18"></polyline>
          </svg>
        </button>
        <!-- the same fact as the counter, in a form you do not have to
             read. Decorative to a screen reader, which already has the
             counter announced to it below. -->
        <span class="hero-stepper__track" aria-hidden="true">
          <span class="hero-stepper__fill" id="heroFill"></span>
        </span>

        <p class="hero-stepper__count" aria-live="polite">
          <strong id="heroIndex"><?= str_pad('1', 2, '0', STR_PAD_LEFT) ?></strong>
          / <?= str_pad((string) count($railPicks), 2, '0', STR_PAD_LEFT) ?>
        </p>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- ---- the rail: one card per destination ----
       Each card is a plain #anchor to its card in the list below, so
       with JavaScript off this is a working set of links straight to
       all of them. The JS upgrades the click into a switch instead. -->
  <?php if (count($railPicks) > 1): ?>
  <ul class="hero-rail" id="heroRail" aria-label="All destinations">
    <?php foreach ($railPicks as $i => $d): $rslug = destSlug($d['name']); ?>
    <li>
      <a class="hero-rail__card<?= $i === 0 ? ' is-current' : '' ?>"
         href="#dest-<?= $rslug ?>"
         data-slide="<?= $i ?>">
        <span class="hero-rail__shade"></span>
        <img class="photo-layer" src="<?= htmlspecialchars($d['image']) ?>" alt="" loading="lazy">
        <span class="hero-rail__body">
          <span class="hero-rail__loc"><?= $d['town'] ?>, Camarines Norte</span>
          <span class="font-display hero-rail__name"><?= $d['name'] ?></span>
        </span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

</section>

<!-- what the switch needs, printed once. Same data-island pattern as
     the map points below — no PHP inside the JS file. -->
<script id="heroSlides" type="application/json"><?= json_encode($heroSlides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/destinations-hero.js')) ?>" defer></script>
<?php endif; ?>

<div class="wrap section dest-layout" id="destList">

  <!-- ===================================================================
       THE ATLAS BAND

       THE MAP IS THE HEADING OF THIS SECTION NOW, full width across the
       top, with the filters as a chip bar under it and the grid under
       that. It used to be a 20rem sidebar down the left.

       WHY THE SIDEBAR WENT: it forced the grid into three narrow
       columns and left the bottom two thirds of the left column empty
       on every screen taller than the filter list, which is every
       screen. A page of 24 photographs was spending a fifth of its
       width on a permanently half-empty column.

       Full width also gives the map something to be. At 20rem across it
       could only ever show that Camarines Norte is a shape with dots on
       it; across the whole page the twelve municipalities are far
       enough apart to actually read, which is the one thing a province
       map is for.

       THE IDs ARE UNCHANGED — #destMap and #mapReset — so
       assets/js/destinations-map.js still finds both and needed no
       edit beyond always scrolling the map into view, which is now
       correct on a desktop too because the map is always above the
       card you clicked.
       =================================================================== -->
  <section class="dest-atlas" aria-label="Map of destinations">

    <div class="dest-atlas__bar">
      <div class="dest-atlas__title">
        <span class="dest-atlas__label">Province map</span>
        <p class="dest-atlas__hint">
          <?= count($mapPoints) ?> of <?= count($destinations) ?> mapped &mdash; every pin sits on the spot itself. Tap one for details.
        </p>
      </div>

      <div class="dest-atlas__tools">
        <!-- the status line moved out of its own sidebar panel and onto
             the map bar. It is one sentence of standing information and
             it does not need a box of its own. -->
        <span class="dest-atlas__status">Open for tourism</span>
        <button type="button" class="dest-atlas__reset" id="mapReset">Reset map</button>
      </div>
    </div>

    <div id="destMap" class="dest-map" role="application"
         aria-label="Map of destinations across Camarines Norte"></div>

  </section>

  <!-- ===================================================================
       THE FILTER BAR

       The same two lists as the old sidebar, laid out as rows of chips
       instead of as a stacked column. Twelve municipalities down a
       column was 12 rows tall; across a bar it is two lines.

       Each chip is still a plain link to a real URL with the filters as
       query parameters, so a filtered view is still bookmarkable and
       this still works with JavaScript off.

       On a phone each row scrolls sideways rather than wrapping to five
       lines — see .dest-chips in destinations.css.
       =================================================================== -->
  <!-- id: the target the Destinations menu links to, so a filtered
       link lands here rather than at the top of the banner. -->
  <div class="dest-filters" id="destFilters">

    <div class="dest-filters__row">
      <span class="dest-filters__label">What kind of place</span>
      <ul class="dest-chips">
        <li>
          <a class="<?= ($cat === '' && $type === '') ? 'is-on' : '' ?>"
             href="<?= destUrl('', $town, '', $q) ?>">
            <span>Everything</span><em><?= count($allPlaces) ?></em>
          </a>
        </li>
        <?php foreach ($catCounts as $label => $n): ?>
          <li>
            <a class="<?= $cat === $label ? 'is-on' : '' ?>"
               href="<?= destUrl('', $town, $cat === $label ? '' : $label, $q) ?>">
              <span><?= htmlspecialchars($label) ?></span><em><?= $n ?></em>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- THE MUNICIPALITY ROW IS GONE. It was thirteen more chips under
         the six above — two extra lines that pushed the results further
         down on every screen, to sort a set of 24 places by a
         distinction most visitors do not have an opinion about. The
         town is on every card and the map is directly above, which is
         where people look for "where is this" anyway.

         ?town= STILL WORKS. Nothing was removed from the filtering
         itself — destinations.php?town=Daet filters exactly as before,
         and the homepage or a bookmark can still send it. This only
         removes the row of buttons. $townCounts is still built above
         and still feeds the "Municipalities" number in the intro.

         TO BRING IT BACK: restore the .dest-filters__row that stood
         here, looping $townCounts the same way the category row loops
         $catCounts. -->

    <?php if ($type !== ''): ?>
      <!-- someone arrived from the homepage trip finder, which sends a
           single exact tag. Show it so the count makes sense. -->
      <p class="dest-filters__note">
        Showing the exact tag <strong><?= htmlspecialchars($type) ?></strong>.
        <a href="<?= destUrl('', $town, '', $q) ?>">Widen this</a>
      </p>
    <?php endif; ?>

  </div>

  <div class="dest-main">

    <!-- one line saying what is on screen, and how to get back to
         everything. -->
    <div class="dest-result">
      <p class="dest-result__count">
        <strong><?= count($destinations) ?></strong>
        <?= count($destinations) === 1 ? 'destination' : 'destinations' ?>
        <?php if ($type !== '' || $town !== '' || $cat !== '' || $q !== ''): ?>
          &mdash; <?= htmlspecialchars(trim(($q !== '' ? 'matching “' . $q . '”' : (($cat !== '' ? $cat : $type) . ' ' . ($town !== '' ? 'in ' . $town : ''))))) ?>
        <?php else: ?>
          across twelve municipalities
        <?php endif; ?>
      </p>
      <?php if ($type !== '' || $town !== '' || $cat !== '' || $q !== ''): ?>
        <!-- #destFilters for the same reason as the chips: clearing is a
             filter change, so it should leave you looking at the bar you
             cleared, not at the top of the page. -->
        <a class="dest-result__clear" href="destinations.php#destFilters">Clear filters</a>
      <?php endif; ?>
    </div>

    <?php if (empty($destinations)): ?>
      <p class="dest-empty">Nothing matches that<?= $q !== '' ? ' search' : ' combination' ?>.
        <a href="destinations.php#destFilters">Browse all 24 destinations</a></p>
    <?php endif; ?>

    <!-- ONE continuous grid. The index used to be twelve headed blocks
         of two cards each, which never gave the eye a full row to scan.
         Town is a chip in the filter bar above, not a section heading. -->
    <div class="dest-grid" id="destGrid">
      <?php foreach ($destinations as $d):
        $slug = destSlug($d['name']);
        $ll   = destLatLng($d);
      ?>
      <!-- NO data-aos HERE, deliberately.

           translate3d(0, 100px, 0) is the AOS library's default offset
           for fade-up, and something on this site is still loading AOS —
           check includes/header.php and includes/footer.php. Its
           transform was sticking on these cards because the element
           never received .aos-animate, which is what pushed every card
           100px down inside its own grid: a gap above the first row, and
           the last row spilling over the section below.

           homepage.css already neutralises AOS's opacity with
           [data-aos]{opacity:1} — that is why the cards were visible
           rather than invisible — but nothing was undoing the transform.

           These cards are revealed by GSAP in homepage.js, which targets
           .dest-card directly and does not read this attribute, so
           removing it costs nothing and takes AOS out of the picture. -->
      <article class="dest-card"
               id="dest-<?= $slug ?>"
               data-slug="<?= $slug ?>"
               <?php if ($ll): ?>data-lat="<?= $ll[0] ?>" data-lng="<?= $ll[1] ?>"<?php endif; ?>>
        <div class="media dest-card__media">
          <div class="gradient-fill"></div>
          <!-- alt is empty ON PURPOSE. The destination name sits in the
               <h3> directly below, so alt text here would make a screen
               reader announce it twice — and while the photos are
               missing, a filled alt renders as stray text across the
               top of every card. -->
          <img class="photo-layer" src="<?= htmlspecialchars($d['image']) ?>" alt="" loading="lazy">
          <span class="dest-card__tag"><?= $d['tag'] ?></span>

          <!-- ===================================================
               SAVE THIS PLACE

               Sits on the photograph, opposite the tag, because it
               acts on the destination as a whole rather than on
               anything in the body text.

               It renders only when the row carries an id. The
               fallback list in includes/destinations-data.php has
               none — it is a snapshot served when the database is
               unreachable, and there is nothing to save against.

               Signed out it falls through to data-auth-gate, the
               same hook View details uses below. The two attributes
               are never printed together, for the reason given
               there: one click, one answer.
               ==================================================== -->
          <?php if (!empty($d['id'])): ?>
            <?php if (isset($_SESSION['user_id'])): ?>
              <button type="button" class="save-btn dest-card__save" data-save
                      data-destination-id="<?= (int) $d['id'] ?>"
                      aria-pressed="false"
                      aria-label="Save <?= htmlspecialchars($d['name']) ?> to your places">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M6 2h12a1 1 0 0 1 1 1v18l-7-4.2L5 21V3a1 1 0 0 1 1-1z"></path>
                </svg>
              </button>
            <?php else: ?>
              <button type="button" class="save-btn dest-card__save" data-auth-gate
                      aria-label="Sign in to save <?= htmlspecialchars($d['name']) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <path d="M6 2h12a1 1 0 0 1 1 1v18l-7-4.2L5 21V3a1 1 0 0 1 1-1z"></path>
                </svg>
              </button>
            <?php endif; ?>
          <?php endif; ?>

          <!-- THE TOWN MOVED ONTO THE PHOTOGRAPH. It used to sit under
               the name as a third line of grey text, which made the
               body three paragraphs deep before it said anything, and
               left the photo carrying no information at all.

               It still reads when the photo is missing: .media::after
               in base.css lays a dark gradient across the bottom, and
               under that is either the picture or the graded panel. -->
          <span class="dest-card__loc">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 2a7.4 7.4 0 0 0-7.4 7.4C4.6 15 12 22.5 12 22.5s7.4-7.5 7.4-13.1A7.4 7.4 0 0 0 12 2zm0 10.1a2.7 2.7 0 1 1 0-5.4 2.7 2.7 0 0 1 0 5.4z"></path>
            </svg>
            <?= $d['town'] ?>
          </span>
        </div>

        <div class="dest-card__body">
          <h3 class="font-display dest-card__name"><?= $d['name'] ?></h3>

          <!-- THE PULL QUOTE. It has always been in
               includes/destinations-data.php as 'quote' and this page
               was dropping it, which left every card as a name and a
               sentence — a directory listing rather than a card that
               says anything. It is one line of writing per
               destination and it is the only voice on the card. -->
          <?php if (!empty($d['quote'])): ?>
            <p class="dest-card__quote"><?= $d['quote'] ?></p>
          <?php endif; ?>

          <p class="dest-card__desc"><?= $d['desc'] ?></p>

          <!-- THE THREE FACTS, also already in the data file as
               'chips' and also being dropped here.

               NOT class="dest-card__chips". base.css carries a rule of
               that name for the homepage carousel's card, and reusing
               it would pull in a layout built for a different shape.
               The class here is __facts and it is styled in
               destinations.css section 7.

               htmlspecialchars because these are short factual labels
               that a tourism officer will eventually be editing, and
               an ampersand in "Rates & booking" should print as an
               ampersand rather than break the markup. -->
          <?php if (!empty($d['chips'])): ?>
            <ul class="dest-card__facts">
              <?php foreach (array_slice($d['chips'], 0, 3) as $chip): ?>
                <li><?= htmlspecialchars($chip) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="dest-card__actions">
            <!-- ===================================================
                 VIEW DETAILS

                 data-detail is the hook destinations-map.js exposes:
                 "ANY element with data-detail='slug' opens the sheet"
                 (destinations-map.js, line ~270). The sheet was built
                 and listening the whole time; the card simply never
                 carried the attribute, so the button had nothing to
                 do and fell through to the sign-in gate instead.

                 SIGNED OUT it keeps data-auth-gate, so the sheet is
                 still behind an account. Both attributes are never
                 printed at once: data-auth-gate wins in auth-gate.js
                 rule 1, and a button that opens the panel AND asks
                 you to sign in is two answers to one click.

                 The href stays "#" because the sheet is a panel on
                 this page, not another page. The map JS calls
                 preventDefault, so the "#" is never followed.
                 ==================================================== -->
            <a href="#" class="dest-card__btn"
               <?= isset($_SESSION['user_id'])
                     ? 'data-detail="' . $slug . '"'
                     : 'data-auth-gate' ?>>
              View details
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <line x1="4" y1="12" x2="18" y2="12"></line>
                <polyline points="12 6 18 12 12 18"></polyline>
              </svg>
            </a>
            <?php if ($ll): ?>
            <!-- aria-label carries the destination name because the
                 visible label is the same three words on all 24 cards,
                 and a screen reader reading the page's links out of
                 context would hear "Show on map" twenty-four times. -->
            <button type="button" class="dest-card__map"
                    data-focus="<?= $slug ?>"
                    aria-label="Show <?= htmlspecialchars($d['name']) ?> on the map">
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 2a7.4 7.4 0 0 0-7.4 7.4C4.6 15 12 22.5 12 22.5s7.4-7.5 7.4-13.1A7.4 7.4 0 0 0 12 2zm0 10.1a2.7 2.7 0 1 1 0-5.4 2.7 2.7 0 0 1 0 5.4z"></path>
              </svg>
              <span>Map</span>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <!-- ================================================================
         SHOW SIX, THEN THE REST — PHONES ONLY

         Twenty-four cards in two columns is twelve rows, and on a phone
         that is a long way to scroll past to reach the closing section
         or the footer. Six is three rows: enough to show what the grid
         IS and that it continues, without committing the visitor to all
         of it before they have decided they want it.

         Rendered, not hidden, when there are six or fewer — a control
         that reveals nothing is worse than no control. $destinations is
         the FILTERED set, so a filter that returns five never shows this
         button, and one that returns nine says "Show all 9".

         Printed at every width and hidden above 767px in mobile.css,
         rather than only printed for phones: PHP cannot see the
         viewport, and a cached page served to a phone and a laptop has
         to be right for both. The cap that hides cards 7+ lives in the
         same media query, so the two can never disagree.

         NO hidden ATTRIBUTE and no inline display:none. If the CSS
         somewhere fails to load, the worst case is a button that says
         "Show all 24" next to 24 already-visible cards. The opposite
         default — hiding cards from PHP — would mean a CSS failure took
         eighteen destinations off the page with no way back.
         ================================================================ -->
    <?php if (count($destinations) > 6): ?>
      <button type="button" class="dest-more" id="destMore"
              aria-controls="destGrid" aria-expanded="false">
        <span class="dest-more__label">Show all <?= count($destinations) ?> destinations</span>
        <svg class="dest-more__chev" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </button>
    <?php endif; ?>

  </div>
</div>

<!-- ---------- closing ---------- -->
<section class="dest-outro dest-outro--photo">
  <img class="dest-outro__bg"
       src="<?= htmlspecialchars(assetUrl('uploads/Destination-Photo/Destination-Outro.jpg')) ?>"
       alt="" aria-hidden="true" loading="lazy" decoding="async">
  <div class="wrap dest-outro__inner">
    <span class="eyebrow eyebrow--muted">Planning a route?</span>
    <h2 class="font-display dest-outro__title">Save the ones you want and build a trip around them.</h2>
    <p class="dest-outro__text">An account keeps your list across visits, so you are not starting from a blank map every time.</p>
    <?php if (!isset($_SESSION['user_id'])): ?>

    <?php endif; ?>
  </div>
</section>

<!-- The hover balloon and the detail panel. Loaded here rather than in
     includes/header.php because destinations.php is the only page with a
     map on it, and a stylesheet in the shared header is a request every
     other page pays for and never uses. -->
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/destinations-detail.css')) ?>">

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<!-- The pin data, printed by PHP for assets/js/destinations-map.js to read.
     A JSON data island rather than generated JavaScript, so the map code
     itself can stay a plain cacheable file. -->
<script id="destMapPoints" type="application/json"><?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/destinations-map.js')) ?>"></script>

<!-- ===================================================================
     RE-AIM THE #destFilters JUMP AFTER THE PAGE SETTLES

     The browser handles a #fragment the instant it parses the markup,
     which on this page is far too early to be accurate. Everything
     above the filter bar is still growing at that point:

       the banner video, which has no height until it has metadata
       the featured photo, and the rail of pictures beside it
       the intro block's two lazy-loaded photographs
       the map, which Leaflet sizes itself

     Each one that finishes loading pushes the filter bar further down
     the page, and the window stays where it was — so the jump lands
     short and you end up somewhere in the introduction instead of on
     the chips. That is the "it still goes to the wrong place" you were
     seeing, not a broken link.

     So: scroll once on load, when the images are in and the heights
     are final. scrollIntoView respects the scroll-margin-top set on
     .dest-filters in destinations.css, so the offset for the fixed nav
     is not duplicated here — that CSS line is still the one dial.

     ONLY when the URL actually carries #destFilters, so a normal visit
     to destinations.php is never scrolled anywhere. 'auto' rather than
     'smooth' because this is a correction, not a journey: the visitor
     should not watch the page glide after it has already loaded.
     =================================================================== -->
<script>
(function () {
  if (window.location.hash !== '#destFilters') return;

  window.addEventListener('load', function () {
    var target = document.getElementById('destFilters');
    if (!target) return;

    /* one frame later: 'load' fires before the browser has laid out
       the last of what it just loaded. */
    requestAnimationFrame(function () {
      target.scrollIntoView({ behavior: 'auto', block: 'start' });
    });
  });
}());
</script>
<!-- ===================================================================
     THE "SHOW ALL" BUTTON

     mobile.css hides .dest-card:nth-child(n+7) below 768px and shows
     them again once .dest-grid carries .is-expanded. All this does is
     put that class on and take it off.

     WHY THE COUNT IS COUNTED HERE TOO, and not just printed by PHP:
     collapsing needs a label and PHP's is already spent on the other
     one. Reading it off the DOM keeps the two labels in step with each
     other and with whatever the filter left on screen.

     COLLAPSING SCROLLS BACK TO THE GRID. Fold twenty-four cards down to
     six from the bottom of the page and the ground under you disappears
     — the page shortens by about eighteen rows and the browser leaves
     you standing in the footer. Scrolling to the top of the grid is
     where you were when you pressed the button the first time.

     nth-child counts every card in the grid, and PHP has already
     filtered them, so nothing here has to know anything about filters.
     =================================================================== -->
<script>
(function () {
  var grid = document.getElementById('destGrid');
  var btn  = document.getElementById('destMore');
  if (!grid || !btn) return;

  var label = btn.querySelector('.dest-more__label');
  var total = grid.querySelectorAll('.dest-card').length;

  btn.addEventListener('click', function () {
    var open = grid.classList.toggle('is-expanded');

    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (label) label.textContent = open ? 'Show fewer' : 'Show all ' + total + ' destinations';

    if (!open) grid.scrollIntoView({ behavior: 'auto', block: 'start' });
  });
}());
</script>
<?php require __DIR__ . '/includes/bud-widget.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>