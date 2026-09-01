<?php
/* ===================================================================
   homepage.php — laid out to match the reference screenshots.

   Section order:
     1  hero
     2  about, with squircle photos
     3  what you'll find — four service cards
     4  destination spotlight  (your highlight, unchanged)
     5  three tall experience cards
     6  what visitors say  ⚠ PLACEHOLDER QUOTES, see the note there
     7  floating stats pill
     8  why travel here — squircle art + feature rows
     9  travel notes  ⚠ placeholder titles
     10 gallery, quote band, contact

   ===================================================================
   EVERY PHOTO ON THIS PAGE

   Every photo on this page is a local file. Nothing is linked from
   the internet, so the site works offline and nothing can break
   because someone else took their image down.

   THREE FOLDERS, ONE HELPER EACH
     uploads/Homepage-Photo/  hero, about, service cards, experiences,
                              voices, travel notes, quote and CTA
                              bands.                    $homephoto()
     uploads/Photocards/      the 24 destination photos ONLY, driven
                              by the $spotPhotos list in the spotlight
                              section. The carousel thumbnails read
                              the same files.           $photocard()
     uploads/ITE-SECTION/     the gallery masonry.      $itephoto()

   Still loose in uploads/: bg.mp4, the hero background clip.
   logo.png and lakbai.png are not on this page — see header.php.

   The helpers are defined once, just below the require at the top.
   No <img> on this page writes a folder name itself, so moving a
   whole group is a one-line change up there.

   TO REPLACE A DESTINATION PHOTO
     1. save it into uploads/Photocards/
     2. change that filename in the $spotPhotos list
     3. done — the background AND its thumbnail both follow

   TO REPLACE ANY OTHER PHOTO
     1. save it into the folder its helper points at
     2. change the filename inside that slot's helper call
     3. done

   A slot whose file does not exist yet shows a grey gradient rather
   than a broken-image icon, so the page never looks broken while you
   work. Console 404s are expected until you have added them all.

   WHERE TO GET REAL ONES
     - the Provincial Tourism Office in Daet, who generally share
       photos for projects promoting the province
     - Pexels or Unsplash, free for commercial use, no attribution
       needed — download them, do not hotlink
     - your own phone. Bagasbas is a short trip from Daet and a phone
       camera is more than enough at these sizes

   Search this file for "[ PHOTO" to jump between the slots. The
   numbers run 1 to 50: 1-7 at the top, 8-31 the destinations,
   32-50 the lower half.

   SHAPES — this is the part that matters
     hero, quote, cta, voices background   wide landscape
     the 24 destinations                   landscape 1600x900
     service cards, travel notes           landscape 4:3
     exp-1, exp-2, exp-3                   PORTRAIT and tall
     gallery 1, 4, 5                       PORTRAIT
     gallery 3                             SQUARE
     About + slow travel collages          see the notes at each block

   The PORTRAIT ones matter most. A landscape photo dropped into a tall
   card gets cropped hard from the centre and usually loses its subject.

   =================================================================== */
require __DIR__ . '/includes/header.php';

/* ===================================================================
   PHOTO FOLDERS — every image path on this page comes from here.

   Three folders, one helper each. Change a folder name in ONE place
   below and every image in that group follows.

     $homephoto()   uploads/Homepage-Photo/   hero, about, service
                                              cards, experiences,
                                              voices, travel notes,
                                              quote and CTA bands
     $photocard()   uploads/Photocards/       the 24 destination
                                              photos and their
                                              carousel thumbnails
     $itephoto()    uploads/ITE-SECTION/      the gallery masonry

   NOT in a folder helper: uploads/bg.mp4, the hero background clip.
   It is written out literally so it is obvious it did not move.
   logo.png and lakbai.png are not on this page at all — they live in
   includes/header.php.

   HOW TO USE
     src="<?= $homephoto('hero.jpg') ?>"

   Pass the BARE FILENAME, never a path. The helper adds the folder.

   WHY IT LOOKS LIKE THIS
     rawurlencode lets a filename keep a space in it —
     "Capalonga -Church.jpg" becomes "Capalonga%20-Church.jpg", which
     every browser handles. Without it some servers cut the URL at the
     space. htmlspecialchars then makes the result safe to drop
     straight into an attribute.

   ⚠ The return value is ALREADY HTML-escaped, so it belongs in an
     attribute and nowhere else. Do not run it through
     htmlspecialchars a second time or you will see &amp;amp; in your
     URLs.
   =================================================================== */
$uploadPath = function (string $dir, string $file): string {
    $url = 'uploads/' . ($dir === '' ? '' : $dir . '/') . rawurlencode($file);
    return htmlspecialchars($url, ENT_QUOTES);
};

$homephoto = function (string $f) use ($uploadPath) { return $uploadPath('Homepage-Photo', $f); };
$photocard = function (string $f) use ($uploadPath) { return $uploadPath('Photocards',     $f); };
$itephoto  = function (string $f) use ($uploadPath) { return $uploadPath('ITE-SECTION',    $f); };
?>

<section class="hero" id="hero">
  <div id="heroBg">
    <div class="gradient-fill"></div>

    <!-- ================================================================
         HERO BACKGROUND — photo now, drone video when you have it.

         TO SWITCH TO THE VIDEO:
           1. put your clip in uploads/ as drone-hero.mp4
           2. delete the <img> line below
           3. delete the two "REMOVE THIS LINE" comment markers

         muted and playsinline are required or the browser refuses to
         autoplay. poster shows a still while the clip downloads, so
         nobody stares at a black rectangle. Keep it under about 10 MB
         — a 15-20 second 1080p loop is plenty.
         ================================================================ -->

    <!-- [ PHOTO 1 ] HERO BACKGROUND — full screen, landscape, 1920x1080 or bigger. The most important photo on the site. -->
    <!-- <img class="photo-layer" src="<?= $homephoto('hero.jpg') ?>" alt=""> -->

    <!-- ================================================================
         BACKGROUND VIDEO — keeping it laptop friendly

         The code already pauses this off-screen, skips it entirely on
         phones and metered connections, and slows playback slightly.
         But the file itself is what decides whether a fan spins up.

         RE-ENCODE BEFORE YOU SHIP. In a terminal, with ffmpeg:

           ffmpeg -i bg.mp4 -vf "scale=1280:-2,fps=24" \
                  -c:v libx264 -profile:v main -crf 30 -preset slow \
                  -movflags +faststart -an -t 20 uploads/bg.mp4

         What each part does, and why it matters here:
           scale=1280   720p is plenty. The clip sits at 42% opacity
                        under a dark scrim and a headline — nobody can
                        see 4K detail through that, but the laptop
                        still pays to decode every pixel of it.
           fps=24       halves the decode work versus 60fps footage.
           crf 30       heavier compression. Artefacts vanish under the
                        scrim; the file gets several times smaller.
           -an          strips the audio track. It is muted anyway, so
                        the audio is pure waste.
           -t 20        trims to 20 seconds. A background loop nobody
                        watches end to end does not need to be longer.
           faststart    moves the index to the front so playback can
                        begin before the whole file arrives.

         Target under 5 MB. If it is over 10 MB it is too big.

         preload="metadata" below means the browser fetches only the
         header until it decides to play — not the whole clip.
         ================================================================ -->
    <video class="photo-layer"
           src="uploads/bg.mp4"
           poster="<?= $homephoto('hero.jpg') ?>"
           autoplay muted loop playsinline
           preload="metadata"
           disablepictureinpicture
           disableremoteplayback></video>

  </div>
  <div class="hero__vignette"></div>

  <div class="hero__content">
    <div class="hero__inner" id="heroContent">

      <!-- ============================================================
           KICKER AND PRIMARY BUTTON — PHONE ONLY

           Both are printed at every width and hidden above 767px in
           mobile.css section 25. PHP cannot see a viewport, and this
           page is cached, so the choice cannot be made here.

           THE KICKER gives the headline something to sit under. On a
           phone the hero is one column of centred text on a photograph
           with nothing to fix it in place; a short line of location
           above the title is the cheapest way to say where you are
           before the headline has to.

           THE BUTTON is the part that was missing. The only thing to
           press in the hero was "Explore Now" — a hairline and a
           letter-spaced label at 78% white, which is a whisper on a
           screen this size and in daylight is close to invisible. It
           stays, demoted to what it always was (a scroll cue), and a
           real destination link goes above it.

           It points at destinations.php rather than #destinations: from
           the top of the page, the button a visitor presses first
           should take them to the list of places, not scroll them one
           section down the homepage.
           ============================================================ -->
      <span class="hero__kicker">Bicol Region &middot; Philippines</span>

      <h1 class="font-display hero__title" data-reveal>
        <span class="reveal-line"><span>Welcome to</span></span>
        <span class="reveal-line"><span>Camarines Norte</span></span>
      </h1>
      <p class="hero__motto">&ldquo;Alay sa Diyos, Alay sa Bayan&rdquo;</p>
      <p class="hero__desc">Twelve towns, a coastline facing the open Pacific, and three hundred years of gold worked by hand.</p>
      <div class="hero__actions">
        <a href="destinations.php" class="hero__cta">
          Explore destinations
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1="4" y1="12" x2="18" y2="12"></line>
            <polyline points="12 6 18 12 12 18"></polyline>
          </svg>
        </a>
        <a href="#destinations" class="hero__scroll">
          <span class="hero__scroll-line" aria-hidden="true"></span>
          Explore Now
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ---------- about: copy, tall photo, then a second column ----------
     Three columns, matching the reference: text on the left, a tall
     squircle photo in the middle, and a right column holding a smaller
     photo above its own heading and paragraph.

     PHOTOS
       [ PHOTO 2 ]  the tall middle one   PORTRAIT, about 3:4
       [ PHOTO 3 ]  the small top right   LANDSCAPE, about 4:3
     -->
<section class="wrap story">
  <div class="story__grid">

    <!-- left: heading + copy -->
    <div class="story__body" data-aos="fade-up">
      <h2 class="head-mix">About<br><b>Camarines Norte</b></h2>
      <p>The province sits at the northern end of the Bicol Peninsula, facing the open Pacific. Twelve municipalities, a coastline that runs almost the whole way around, and an interior that climbs into forest within minutes of the road.</p>
      <p>It has stayed just far enough off the trail to keep its beaches empty and its traditions intact &mdash; islands still reachable only by boat, and gold towns working the same trade since before the maps were drawn.</p>
      <p class="story__cta">
        <a href="about.php" class="btn-pill magnetic">Read more</a>
      </p>
    </div>

    <!-- middle: the tall photo -->
    <div class="story__tall squircle" data-aos="fade-up" data-aos-delay="80">
      <img src="<?= $homephoto('photo-2.jpg') ?>" alt="">
    </div>

    <!-- right: small photo, then its own heading and copy -->
    <div class="story__aside" data-aos="fade-up" data-aos-delay="160">
      <div class="story__aside-shot squircle--alt">
        <img src="<?= $homephoto('photo-3.jpg') ?>" alt="">
      </div>
      <h3 class="story__aside-title">Gateway to<br>the northern<br>Bicol coast</h3>
      <p class="story__aside-text">Daet is where almost everyone arrives, and nothing in the province is more than a few hours from it. Two or three destinations in a day is an ordinary plan here rather than an ambitious one &mdash; a waterfall in the morning, a beach by the afternoon.</p>
    </div>

  </div>
</section>

<!-- ---------- what you'll find: the honeycomb -----------------------
     Split section: heading, copy, figures and a button on the left,
     five hexagon tiles nested into a honeycomb on the right.

     The shape is one clip-path, defined once in homepage.css. Nothing
     here is an image mask, so the photos stay ordinary rectangular
     JPEGs — you crop nothing, you export nothing special.

     PHOTOS: uploads/Homepage-Photo/
       photo-4.jpg  photo-5.jpg  photo-6.jpg  photo-7.jpg   (existing)
       photo-8.jpg                                          (NEW — add it)

     photo-8.jpg is the only new file this section needs. Until you
     drop it in, tile 05 shows the grey gradient rather than a broken
     image icon, same as every other empty slot on the page.

     A NOTE ON THE PHOTOS
       A hexagon cuts the four corners off, and text sits over the
       middle. Pick shots whose subject is CENTRED and whose middle
       band isn't bright and busy, or the white title fights it.

     TO ADD A SIXTH TILE
       You'd have to redo the honeycomb positions in homepage.css.
       They are written out one tile at a time, which is what keeps
       the nesting exact — the top row of three and the bottom row of
       two are each placed by hand. Five is what this is built for.
     -->
<section class="hexplore" id="about">

  <!-- faint honeycomb watermark, decorative only -->
  <div class="hexplore__pattern" aria-hidden="true"></div>

  <div class="hexplore__inner">

    <!-- left: the pitch -->
    <div class="hexplore__intro" data-aos="fade-right">
      <span class="hexplore__eyebrow">Explore Camarines Norte</span>
      <h2 class="hexplore__title font-display">What you'll <b>find here</b></h2>
      <span class="hexplore__rule" aria-hidden="true"></span>

      <p class="hexplore__lead">Five kinds of place, spread across twelve towns and never more than a couple of hours apart &mdash; a waterfall in the morning, a beach by the afternoon, and dinner back in Daet.</p>

      <p class="hexplore__sub">Almost everyone arrives through Daet, and nothing in the province sits far from it. Two or three destinations in a day is an ordinary plan here rather than an ambitious one.</p>

      <!-- the figures are real: twelve towns, and the twenty-four
           destinations listed in the spotlight section below -->
      <ul class="hexplore__stats">
        <li class="hexplore__stat">
          <strong class="font-display">12</strong>
          <span>Towns</span>
        </li>
        <li class="hexplore__stat">
          <strong class="font-display">24</strong>
          <span>Destinations</span>
        </li>
        <li class="hexplore__stat">
          <strong class="font-display">2<em>hrs</em></strong>
          <span>Farthest drive</span>
        </li>
      </ul>

      <a class="btn-pill hexplore__cta" href="#destinations">View All Destinations</a>
      <p class="hexplore__note">Hover a tile to see what each one is about.</p>
    </div>

    <!-- right: the comb -->
    <div class="hexplore__comb">

      <article class="hex-cell" data-aos="zoom-in">
        <div class="hex-card">
          <div class="gradient-fill"></div>
          <!-- [ PHOTO 4 ] TILE 1 — Islands &amp; Beaches — centred subject, 800x920 or larger -->
          <img class="hex-card__img" src="<?= $homephoto('photo-4.jpg') ?>" alt="Calaguas coastline">
          <span class="hex-card__veil" aria-hidden="true"></span>
          <span class="hex-card__shine" aria-hidden="true"></span>
          <div class="hex-card__body">
            <span class="hex-card__num">01</span>
            <h3 class="hex-card__title font-display">Islands &amp; Beaches</h3>
            <div class="hex-card__reveal">
              <p>Powder-white sand at Calaguas and Mercedes.</p>
            </div>
          </div>
        </div>
      </article>

      <article class="hex-cell" data-aos="zoom-in" data-aos-delay="80">
        <div class="hex-card">
          <div class="gradient-fill"></div>
          <!-- [ PHOTO 5 ] TILE 2 — Waterfalls &amp; Forest -->
          <img class="hex-card__img" src="<?= $homephoto('photo-5.jpg') ?>" alt="Falls in the province interior">
          <span class="hex-card__veil" aria-hidden="true"></span>
          <span class="hex-card__shine" aria-hidden="true"></span>
          <div class="hex-card__body">
            <span class="hex-card__num">02</span>
            <h3 class="hex-card__title font-display">Waterfalls &amp; Forest</h3>
            <div class="hex-card__reveal">
              <p>Cool green falls, a short walk from the road.</p>
            </div>
          </div>
        </div>
      </article>

      <article class="hex-cell" data-aos="zoom-in" data-aos-delay="160">
        <div class="hex-card">
          <div class="gradient-fill"></div>
          <!-- [ PHOTO 8 ] TILE 3 — Food &amp; Festivals — THIS IS THE NEW ONE -->
          <img class="hex-card__img" src="<?= $homephoto('photo-8.jpg') ?>" alt="Queen pineapples at a Daet market">
          <span class="hex-card__veil" aria-hidden="true"></span>
          <span class="hex-card__shine" aria-hidden="true"></span>
          <div class="hex-card__body">
            <span class="hex-card__num">03</span>
            <h3 class="hex-card__title font-display">Food &amp; Festivals</h3>
            <div class="hex-card__reveal">
              <p>Queen pineapple, and streets full for Pinyasan.</p>
            </div>
          </div>
        </div>
      </article>

      <article class="hex-cell" data-aos="zoom-in" data-aos-delay="240">
        <div class="hex-card">
          <div class="gradient-fill"></div>
          <!-- [ PHOTO 6 ] TILE 4 — Gold Country -->
          <img class="hex-card__img" src="<?= $homephoto('photo-6.jpg') ?>" alt="Panned gold held in a hand">
          <span class="hex-card__veil" aria-hidden="true"></span>
          <span class="hex-card__shine" aria-hidden="true"></span>
          <div class="hex-card__body">
            <span class="hex-card__num">04</span>
            <h3 class="hex-card__title font-display">Gold Country</h3>
            <div class="hex-card__reveal">
              <p>Three centuries of gold, still panned by hand.</p>
            </div>
          </div>
        </div>
      </article>

      <article class="hex-cell" data-aos="zoom-in" data-aos-delay="320">
        <div class="hex-card">
          <div class="gradient-fill"></div>
          <!-- [ PHOTO 7 ] TILE 5 — Surf &amp; Adventure -->
          <img class="hex-card__img" src="<?= $homephoto('photo-7.jpg') ?>" alt="Surfer at Bagasbas Beach">
          <span class="hex-card__veil" aria-hidden="true"></span>
          <span class="hex-card__shine" aria-hidden="true"></span>
          <div class="hex-card__body">
            <span class="hex-card__num">05</span>
            <h3 class="hex-card__title font-display">Surf &amp; Adventure</h3>
            <div class="hex-card__reveal">
              <p>Bagasbas holds a break nearly all year.</p>
            </div>
          </div>
        </div>
      </article>

    </div>
  </div>
</section>

<section class="dest-spotlight" id="destinations">

<?php
/* ===================================================================
   [ PHOTO 5 ] to [ PHOTO 28 ]  —  THE 24 DESTINATION PHOTOS

   Every spotlight photo now lives in ONE list, and one folder.

   WHERE THE FILES GO
     uploads/Photocards/          <- exactly this, capital P

     The folder name is case sensitive on a real Linux host even
     though XAMPP on Windows will forgive you. If it works locally
     and 404s once uploaded, the capital P is the first thing to
     check.

   THE LIST BELOW
     One row per destination:  ['filename.jpg', 'Place name']

     The rows pair BY POSITION with the 24 <article> cards further
     down this section — row 1 goes with card 1, row 2 with card 2.
     Keep the two in the same order, or a photo will show under the
     wrong name.

     The same file is reused as the small carousel thumbnail, so one
     upload covers both. You never add a destination photo twice.

   TO SWAP A PHOTO
     Drop the new file into uploads/Photocards/ and change the
     filename on that row. Nothing else.

   TO ADD A DESTINATION
     Add a row here AND a matching <article> in the same position
     below. The carousel counts itself, so nothing else changes.

   SHAPE
     Landscape, 1600 x 900 or larger. White text sits on top of
     these, so avoid a bright, busy centre.
   =================================================================== */

$spotPhotos = [
  /* ---- Basud ---- */
  ['Basud-Taba.jpg',                 'Taba Taba Beach Resort'],
  ['Basud-LaMaestra.jpg',            'La Maestra Campsite and Resort'],
  /* ---- Capalonga ---- */
  ['Capalonga -Church.jpg',          'Shrine of the Black Nazarene'],
  ['Capalonga-Pulong-Guijanlo.jpg',  'Pulong Guijanlo'],
  /* ---- Daet ---- */
  ['Daet-Bagabas.jpg',               'Bagasbas Beach'],
  ['daet-rizal-monument.jpg',        'First Rizal Monument'],
  /* ---- Jose Panganiban ---- */
  ['Panganiban-Turayog.png',         'Turayog View Deck'],
  ['Panganiban-Parola-Island.jpg',   'Parola Island'],
  /* ---- Labo ---- */
  ['Labo-MalatapFalls.jpg',          'Malatap Falls'],
  ['Labo-TulisPeak.jpg',             'Tulis Peak, Mt. Bagacay'],
  /* ---- Mercedes ---- */
  ['Mercedes-Canimog.jpg',           'Canimog Island'],
  ['Mercedes-Pebble.jpg',            'Pebble Beach'],
  /* ---- Paracale ---- */
  ['Paracale-Macolabo-island.jpg',   'Macolabo Island'],
  ['paracale-gumaus.jpg',            'Gumaus Beach'],
  /* ---- San Lorenzo Ruiz ---- */
  ['SlRuiz-NacaliFalls.jpg',         'Nacali Falls'],
  ['slruiz-mampili.jpg',             'Mampili River'],
  /* ---- San Vicente ---- */
  ['SanVicente-Mananap.jpg',         'Mananap Falls'],
  ['SanVicente-Mananap-Atv.jpg',     'Mananap Falls ATV Adventure'],
  /* ---- Santa Elena ---- */
  ['StaElena-BusayFalls.jpg',        'Busay Falls'],
  ['StaElena-delmoro.jpg',           'Del Moro Park'],
  /* ---- Talisay ---- */
  ['Talisay-Mangroves.jpg',          'Mangrove Eco Tourism Park'],
  ['Talisay-Church.jpg',             'St. Francis of Assisi Parish Church'],
  /* ---- Vinzons ---- */
  ['Vinzons-Calaguas.jpg',           'Calaguas Island'],
  ['Vinzons-Panit.jpg',              'Mt. Panit'],
];
?>

  <!-- Stacked full-bleed images, one per destination. JavaScript fades
       the selected one to the front, so only the first is eager-loaded
       — the other 23 wait until they are actually needed. data-place is
       there so you can tell which is which in devtools. -->
  <div class="dest-spotlight__bgwrap" id="destBgWrap">
<?php foreach ($spotPhotos as $i => [$file, $place]): ?>
    <img class="spot-bg<?= $i === 0 ? ' spot-bg--front' : '' ?>"
         src="<?= $photocard($file) ?>"
         data-place="<?= htmlspecialchars($place, ENT_QUOTES) ?>"
         alt=""
         decoding="async"
         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
<?php endforeach; ?>
  </div>

  <div class="dest-spotlight__overlay"></div>

  <div class="wrap dest-spotlight__inner">
    <div class="dest-spotlight__main" data-aos="fade-up">

      <!-- ==============================================================
           THE 24 DESTINATION CARDS — all text lives here in the HTML.

           One <article> per destination, in the SAME ORDER as the
           images above. Only the one carrying .is-active is visible;
           JavaScript moves that class as you click through.

           Edit any wording directly in these tags. The carousel
           thumbnails read their label and tag straight off these
           cards, so a name only needs changing in one place.

           To ADD a destination: copy an <article> block, and add a
           matching <img> in the same position in the stack above.
           ============================================================== -->
      <div class="spot-content" id="spotContent">

        <!-- ============ BASUD ============ -->
        <article class="spot-item is-active">
          <span class="pill pill--outline">Beach Resort</span>
          <p class="dest-spotlight__loc">Basud, Camarines Norte</p>
          <h3 class="font-display">Taba Taba Beach Resort</h3>
          <p class="dest-spotlight__quote">&ldquo;Where the northern coast begins.&rdquo;</p>
          <p class="dest-spotlight__desc">A beach resort on the Basud shoreline, the first coastal stop heading north out of Daet.</p>
          <div class="chip-row"><span class="chip">Beachfront</span><span class="chip">Day trips</span><span class="chip">Contact for rates</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Campsite</span>
          <p class="dest-spotlight__loc">Basud, Camarines Norte</p>
          <h3 class="font-display">La Maestra Campsite and Resort</h3>
          <p class="dest-spotlight__quote">&ldquo;Pitch a tent, stay the night.&rdquo;</p>
          <p class="dest-spotlight__desc">A campsite and resort in Basud for visitors who would rather sleep outdoors than book a room.</p>
          <div class="chip-row"><span class="chip">Camping</span><span class="chip">Overnight stays</span><span class="chip">Contact for rates</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ CAPALONGA ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Religious Site</span>
          <p class="dest-spotlight__loc">Capalonga, Camarines Norte</p>
          <h3 class="font-display">Shrine of the Black Nazarene</h3>
          <p class="dest-spotlight__quote">&ldquo;The town's oldest standing appointment.&rdquo;</p>
          <p class="dest-spotlight__desc">Capalonga's shrine draws pilgrims from across Bicol, and the town's calendar still turns around its feast.</p>
          <div class="chip-row"><span class="chip">Pilgrimage site</span><span class="chip">Feast celebrations</span><span class="chip">Town centre</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Island</span>
          <p class="dest-spotlight__loc">Capalonga, Camarines Norte</p>
          <h3 class="font-display">Pulong Guijanlo</h3>
          <p class="dest-spotlight__quote">&ldquo;A short crossing, and the mainland is gone.&rdquo;</p>
          <p class="dest-spotlight__desc">A small island off the Capalonga coast, reached by boat and quiet enough that most days you will have the shore to yourself.</p>
          <div class="chip-row"><span class="chip">Boat access</span><span class="chip">Swimming</span><span class="chip">Day trip</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ DAET ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Surf</span>
          <p class="dest-spotlight__loc">Daet, Camarines Norte</p>
          <h3 class="font-display">Bagasbas Beach</h3>
          <p class="dest-spotlight__quote">&ldquo;The north's longest ride.&rdquo;</p>
          <p class="dest-spotlight__desc">The surf capital of the north, with a steady break that holds nearly year-round and a boardwalk that fills once the heat drops.</p>
          <div class="chip-row"><span class="chip">Year-round swell</span><span class="chip">Board rentals</span><span class="chip">Sunset boardwalk</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Monument</span>
          <p class="dest-spotlight__loc">Daet, Camarines Norte</p>
          <h3 class="font-display">First Rizal Monument</h3>
          <p class="dest-spotlight__quote">&ldquo;Raised before the country had a name for itself.&rdquo;</p>
          <p class="dest-spotlight__desc">Built in 1898, the earliest monument to Jose Rizal anywhere, put up by local subscription within two years of his execution.</p>
          <div class="chip-row"><span class="chip">Built 1898</span><span class="chip">National landmark</span><span class="chip">Town centre</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ JOSE PANGANIBAN ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">View Deck</span>
          <p class="dest-spotlight__loc">Jose Panganiban, Camarines Norte</p>
          <h3 class="font-display">Turayog View Deck</h3>
          <p class="dest-spotlight__quote">&ldquo;Climb once, see the whole bay.&rdquo;</p>
          <p class="dest-spotlight__desc">A view deck above Jose Panganiban, reached on foot and best timed for early morning.</p>
          <div class="chip-row"><span class="chip">Viewpoint</span><span class="chip">Short climb</span><span class="chip">Sunrise spot</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Island</span>
          <p class="dest-spotlight__loc">Jose Panganiban, Camarines Norte</p>
          <h3 class="font-display">Parola Island</h3>
          <p class="dest-spotlight__quote">&ldquo;The lighthouse island.&rdquo;</p>
          <p class="dest-spotlight__desc">A small island off the Jose Panganiban coast, reached by boat from the mainland.</p>
          <div class="chip-row"><span class="chip">Boat access</span><span class="chip">Island hopping</span><span class="chip">Lighthouse</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ LABO ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Waterfall</span>
          <p class="dest-spotlight__loc">Labo, Camarines Norte</p>
          <h3 class="font-display">Malatap Falls</h3>
          <p class="dest-spotlight__quote">&ldquo;Cold water at the end of a warm walk.&rdquo;</p>
          <p class="dest-spotlight__desc">A falls in the Labo interior reached by a short trek, popular with locals escaping the worst of the afternoon.</p>
          <div class="chip-row"><span class="chip">Short trek</span><span class="chip">Natural pool</span><span class="chip">Picnic spot</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Peak</span>
          <p class="dest-spotlight__loc">Labo, Camarines Norte</p>
          <h3 class="font-display">Tulis Peak, Mt. Bagacay</h3>
          <p class="dest-spotlight__quote">&ldquo;A morning, not an expedition.&rdquo;</p>
          <p class="dest-spotlight__desc">The summit of Mt. Bagacay, a manageable climb with wide views for anyone who would rather be back down by lunch.</p>
          <div class="chip-row"><span class="chip">Day hike</span><span class="chip">Viewpoint</span><span class="chip">Guided climb</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ MERCEDES ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Island</span>
          <p class="dest-spotlight__loc">Mercedes, Camarines Norte</p>
          <h3 class="font-display">Canimog Island</h3>
          <p class="dest-spotlight__quote">&ldquo;The largest of the seven.&rdquo;</p>
          <p class="dest-spotlight__desc">The biggest of the Mercedes island group, known for its lighthouse and rock formations, and the usual first stop on an island-hopping run out of the fish port.</p>
          <div class="chip-row"><span class="chip">Island hopping</span><span class="chip">Lighthouse</span><span class="chip">Boat access</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Beach</span>
          <p class="dest-spotlight__loc">Mercedes, Camarines Norte</p>
          <h3 class="font-display">Pebble Beach</h3>
          <p class="dest-spotlight__quote">&ldquo;Stones instead of sand.&rdquo;</p>
          <p class="dest-spotlight__desc">A shoreline of smooth stones on the Mercedes coast, a change from the white sand the province is better known for.</p>
          <div class="chip-row"><span class="chip">Pebble shore</span><span class="chip">Swimming</span><span class="chip">Photo stop</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ PARACALE ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Island</span>
          <p class="dest-spotlight__loc">Paracale, Camarines Norte</p>
          <h3 class="font-display">Macolabo Island</h3>
          <p class="dest-spotlight__quote">&ldquo;Off the gold coast, by boat.&rdquo;</p>
          <p class="dest-spotlight__desc">An island off Paracale, the town that has worked gold for three centuries, reached by boat and known for clear water and quiet shoreline.</p>
          <div class="chip-row"><span class="chip">Boat access</span><span class="chip">Snorkelling</span><span class="chip">Day trip</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Beach</span>
          <p class="dest-spotlight__loc">Paracale, Camarines Norte</p>
          <h3 class="font-display">Gumaus Beach</h3>
          <p class="dest-spotlight__quote">&ldquo;Long, open, and quiet.&rdquo;</p>
          <p class="dest-spotlight__desc">A wide stretch of shoreline at Paracale, popular with locals and largely undeveloped.</p>
          <div class="chip-row"><span class="chip">Wide shoreline</span><span class="chip">Local favourite</span><span class="chip">Camping</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ SAN LORENZO RUIZ ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Waterfall</span>
          <p class="dest-spotlight__loc">San Lorenzo Ruiz, Camarines Norte</p>
          <h3 class="font-display">Nacali Falls</h3>
          <p class="dest-spotlight__quote">&ldquo;Upland water, easy reach.&rdquo;</p>
          <p class="dest-spotlight__desc">A falls in the uplands of San Lorenzo Ruiz, a short trip inland from the highway and a standard stop on a day out of Daet.</p>
          <div class="chip-row"><span class="chip">Waterfall</span><span class="chip">Natural pool</span><span class="chip">Day trip</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">River</span>
          <p class="dest-spotlight__loc">San Lorenzo Ruiz, Camarines Norte</p>
          <h3 class="font-display">Mampili River</h3>
          <p class="dest-spotlight__quote">&ldquo;Cold, clear, and running.&rdquo;</p>
          <p class="dest-spotlight__desc">A river in San Lorenzo Ruiz, a local spot for swimming and riverside afternoons.</p>
          <div class="chip-row"><span class="chip">River swimming</span><span class="chip">Shaded</span><span class="chip">Picnic spot</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ SAN VICENTE ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Waterfall</span>
          <p class="dest-spotlight__loc">San Vicente, Camarines Norte</p>
          <h3 class="font-display">Mananap Falls</h3>
          <p class="dest-spotlight__quote">&ldquo;Three drops into jade water.&rdquo;</p>
          <p class="dest-spotlight__desc">A short forest trail leads to a tiered falls, cool enough to cut the midday heat in half.</p>
          <div class="chip-row"><span class="chip">Forest trailhead</span><span class="chip">Natural pool</span><span class="chip">Cool year-round</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Adventure</span>
          <p class="dest-spotlight__loc">San Vicente, Camarines Norte</p>
          <h3 class="font-display">Mananap Falls ATV Adventure</h3>
          <p class="dest-spotlight__quote">&ldquo;The loud way in.&rdquo;</p>
          <p class="dest-spotlight__desc">Guided ATV rides on the trails around Mananap Falls, booked through local operators.</p>
          <div class="chip-row"><span class="chip">ATV rides</span><span class="chip">Guided</span><span class="chip">Book ahead</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ SANTA ELENA ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Waterfall</span>
          <p class="dest-spotlight__loc">Santa Elena, Camarines Norte</p>
          <h3 class="font-display">Busay Falls</h3>
          <p class="dest-spotlight__quote">&ldquo;The far edge of the province, and worth the drive.&rdquo;</p>
          <p class="dest-spotlight__desc">Santa Elena sits at the northern boundary, and Busay is the reason most visitors make the trip out.</p>
          <div class="chip-row"><span class="chip">Waterfall</span><span class="chip">Swimming</span><span class="chip">Day trip</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Park</span>
          <p class="dest-spotlight__loc">Santa Elena, Camarines Norte</p>
          <h3 class="font-display">Del Moro Park</h3>
          <p class="dest-spotlight__quote">&ldquo;Shade, benches, and a slower hour.&rdquo;</p>
          <p class="dest-spotlight__desc">A public park in Santa Elena, an easy stop for anyone breaking the drive north.</p>
          <div class="chip-row"><span class="chip">Public park</span><span class="chip">Shaded</span><span class="chip">Family friendly</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ TALISAY ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Mangrove</span>
          <p class="dest-spotlight__loc">Talisay, Camarines Norte</p>
          <h3 class="font-display">Mangrove Eco Tourism Park</h3>
          <p class="dest-spotlight__quote">&ldquo;Quiet you can actually hear.&rdquo;</p>
          <p class="dest-spotlight__desc">A boardwalk threads through Talisay's mangrove stands, home to herons, mudskippers, and very little noise.</p>
          <div class="chip-row"><span class="chip">Boardwalk trail</span><span class="chip">Bird watching</span><span class="chip">Community-run</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Church</span>
          <p class="dest-spotlight__loc">Talisay, Camarines Norte</p>
          <h3 class="font-display">St. Francis of Assisi Parish Church</h3>
          <p class="dest-spotlight__quote">&ldquo;The centre of town, in every sense.&rdquo;</p>
          <p class="dest-spotlight__desc">Talisay's parish church, the anchor of the town centre and busiest on feast days.</p>
          <div class="chip-row"><span class="chip">Historic church</span><span class="chip">Town centre</span><span class="chip">Feast days</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>

        <!-- ============ VINZONS ============ -->
        <article class="spot-item">
          <span class="pill pill--outline">Island</span>
          <p class="dest-spotlight__loc">Vinzons, Camarines Norte</p>
          <h3 class="font-display">Calaguas Island</h3>
          <p class="dest-spotlight__quote">&ldquo;Where the tide writes the only footprints.&rdquo;</p>
          <p class="dest-spotlight__desc">Mahabang Buhangin's long white shore, reachable only by boat and still best seen with a tent.</p>
          <div class="chip-row"><span class="chip">Powder-white sand</span><span class="chip">Boat access only</span><span class="chip">Beach camping</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
        <article class="spot-item">
          <span class="pill pill--outline">Mountain</span>
          <p class="dest-spotlight__loc">Vinzons, Camarines Norte</p>
          <h3 class="font-display">Mt. Panit</h3>
          <p class="dest-spotlight__quote">&ldquo;Low peak, wide view.&rdquo;</p>
          <p class="dest-spotlight__desc">A climb above Vinzons with views back across the coast and out toward the Calaguas group.</p>
          <div class="chip-row"><span class="chip">Day hike</span><span class="chip">Coastal views</span><span class="chip">Guided climb</span></div>
          <a href="#contact" class="btn btn--outline" data-auth-gate>View Destination</a>
        </article>
      </div>

    </div>
    <div class="dest-spotlight__carousel">
      <!-- ================================================================
           THE ARROWS NOW SIT ON THE STRIP, NOT UNDER IT.

           Previous was unclickable twice in the row below, so it is not
           there any more. These are absolutely positioned over the ends
           of the thumbnail strip — the one region of this column proven
           to receive clicks, because the thumbnails themselves have
           always worked.

           The strip is also no longer re-sorted on every switch, so the
           destination you just left stays put as the tile immediately to
           the left. That is the real answer to "I missed one": you can
           see it and click it directly, without any button.
           ================================================================ -->
      <div class="carousel-viewport">
        <div class="carousel-track" id="carouselTrack"></div>
      </div>

      <!-- ================================================================
           Previous / Next sit UNDER the strip, flanking the dots.

           This is the position that failed twice before. What makes it
           work now is not the placement but three things behind it:

             - .dest-spotlight__main carries pointer-events:none, so the
               text column beside it cannot intercept a click no matter
               how far its headline overflows
             - the row is CENTRED, never space-between, so neither
               button sits on the seam between the two columns
             - homepage.js hit-tests both buttons on every document
               click, so even if something transparent covers one, the
               click still registers

           Do not switch this row back to justify-content:space-between.
           ================================================================ -->
      <div class="carousel-controls">
        <button type="button" class="carousel-nav carousel-nav--prev" id="prevBtn" aria-label="Previous destination">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 6 9 12 15 18"/></svg>
        </button>

        <div class="carousel-dots" id="carouselDots"></div>

        <button type="button" class="carousel-nav carousel-nav--next" id="nextBtn" aria-label="Next destination">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
      </div>
    </div>
  </div>
</section>

<!-- ---------- three tall experience cards ---------- -->
<section class="exp">
  <div class="wrap">
    <h2 class="head-mix head-center" data-aos="fade-up">Discover our best<br><b>travel experiences</b></h2>
    <p class="head-sub" data-aos="fade-up">Three ways people actually spend their days here, from the boat out to Calaguas to the drive up into gold country.</p>

    <div class="exp__grid">
      <!-- PHOTOS: uploads/exp-1.jpg, exp-2.jpg, exp-3.jpg — PORTRAIT, about 3:4.4 -->
      <a class="exp-card" href="destinations.php?type=Island" data-aos="fade-up">
        <!-- [ PHOTO 32 ] EXPERIENCE 1 — island hopping — PORTRAIT, tall, about 3:4.4 -->
        <img src="<?= $homephoto('Exp-Photo-1.webp') ?>" alt="">
        <span class="exp-card__glow" aria-hidden="true"></span>
        <span class="exp-card__view">View</span>
        <div class="exp-card__body">
          <span class="exp-card__kicker">Island hopping</span>
          <h3 class="exp-card__title">Out to Calaguas</h3>
          <p class="exp-card__text">Boat access only, powder-white sand, and camping welcome on the open shore.</p>
        </div>
      </a>

      <a class="exp-card" href="destinations.php?type=Waterfall" data-aos="fade-up" data-aos-delay="80">
        <!-- [ PHOTO 33 ] EXPERIENCE 2 — falls and forest — PORTRAIT, tall, about 3:4.4 -->
        <img src="<?= $photocard('StaElena-BusayFalls.jpg') ?>" alt="">
        <span class="exp-card__glow" aria-hidden="true"></span>
        <span class="exp-card__view">View</span>
        <div class="exp-card__body">
          <span class="exp-card__kicker">Inland</span>
          <h3 class="exp-card__title">Falls and forest</h3>
          <p class="exp-card__text">Short treks to jade pools cool enough to cut the midday heat in half.</p>
        </div>
      </a>

      <a class="exp-card" href="destinations.php#paracale" data-aos="fade-up" data-aos-delay="160">
        <!-- [ PHOTO 34 ] EXPERIENCE 3 — gold country — PORTRAIT, tall, about 3:4.4 -->
        <img src="<?= $homephoto('PhotO-Exp-3.jpg') ?>" alt="">
        <span class="exp-card__glow" aria-hidden="true"></span>
        <span class="exp-card__view">View</span>
        <div class="exp-card__body">
          <span class="exp-card__kicker">Heritage</span>
          <h3 class="exp-card__title">Gold country</h3>
          <p class="exp-card__text">Paracale's goldsmith shops, where the craft still passes hand to hand.</p>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- ===================================================================
     VISITOR REGISTER  —  driven by the database

     Reads the `testimonials` table and prints whatever is in it, so
     the admin side is the only place quotes are ever written.

     IF THE TABLE IS EMPTY OR DOES NOT EXIST YET, THE WHOLE SECTION
     DISAPPEARS. That is deliberate. A heading that says "Loved by
     visitors" above an empty list looks broken; no section at all
     looks finished.

     THE TABLE (run this in pgAdmin or psql):

       CREATE TABLE testimonials (
         id           SERIAL PRIMARY KEY,
         name         VARCHAR(100) NOT NULL,
         hometown     VARCHAR(100),
         rating       SMALLINT NOT NULL DEFAULT 5
                        CHECK (rating BETWEEN 1 AND 5),
         quote        TEXT NOT NULL,
         is_published BOOLEAN NOT NULL DEFAULT false,
         created_at   TIMESTAMP NOT NULL DEFAULT NOW()
       );

     WHY A REGISTER AND NOT CARDS

     Every column this table already has — date, name, hometown,
     rating, remark — is a column in a guest book, which is the thing
     a lodge or a trail head actually keeps. Setting the reviews as
     typeset rows rather than boxes does three things at once:

       - a two-word remark like "beautiful" is normal in a register.
         In a card it left three-quarters of a box empty, which is
         what made the old grid look thin.
       - the date and hometown become part of the design instead of
         metadata crammed into a corner.
       - it scales. Seven rows read as a register; seven cards read
         as a grid that could not be filled.

     THE PAGE SHOWS SIX ROWS. Everything after that is held back for
     the "See the full register" link at the bottom. If six or fewer
     rows are published the link never renders.

     Background photo: uploads/Homepage-Photo/voices-bg.jpg
     =================================================================== -->
<?php
$testimonials = [];

/* How many rows sit on the page, and how many we are willing to pull
   for the modal behind the link. Both live here so there is one place
   to change them. */
const VOICES_VISIBLE = 6;
const VOICES_MAX     = 60;

$dbConfig = __DIR__ . '/config/database.php';

if (is_file($dbConfig)) {
    require_once $dbConfig;

    try {
        $stmt = $pdo->query(
            "SELECT name, hometown, rating, quote, created_at
               FROM testimonials
              WHERE is_published = true
           ORDER BY created_at DESC
              LIMIT " . VOICES_MAX
        );
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        /* No table yet, or the columns differ. Not a reason to take
           the whole homepage down — log it and show nothing. */
        error_log('testimonials query failed: ' . $e->getMessage());
        $testimonials = [];
    }
}

/* Five stars, always printed. The ones above the rating get .off, and
   .half exists for the section average — a 4.6 that draws five solid
   stars is a small lie, and on a review block that is the one thing
   worth being exact about. $rating is a float only for the summary;
   rows pass an int. */
function voiceStars(float $rating, string $extraClass = ''): void {
    $rounded = round($rating * 2) / 2;   /* nearest half star */
    ?>
    <span class="voice-stars<?= $extraClass ? ' ' . $extraClass : '' ?>" aria-hidden="true"><?php
      for ($s = 1; $s <= 5; $s++):
          if ($rounded >= $s)            $class = '';
          elseif ($rounded >= $s - 0.5)  $class = ' class="half"';
          else                           $class = ' class="off"';
      ?><i<?= $class ?>></i><?php endfor;
    ?></span>
    <?php
}

/* One row, printed twice from the same place: once on the page and
   once in the modal. Keeping it in a function means a change to the
   markup can never drift between the two.

   $delay is the AOS stagger, and is passed as null for modal rows —
   those are hidden when the page loads, so the scroll animation would
   have nothing to trigger on and would leave them stuck at opacity 0. */
function renderVoiceRow(array $voice, ?int $delay = null): void {
    $rating   = max(1, min(5, (int) ($voice['rating'] ?? 5)));
    $name     = (string) $voice['name'];
    $quote    = (string) $voice['quote'];
    $hometown = trim((string) ($voice['hometown'] ?? ''));

    /* created_at is a timestamp string from Postgres. If it is missing
       for any reason we print no date rather than 1970. */
    $stamp    = isset($voice['created_at']) ? strtotime((string) $voice['created_at']) : false;
    ?>
    <article class="voice-row"<?= $delay === null ? '' : ' data-aos="fade-up"' . ($delay ? ' data-aos-delay="' . $delay . '"' : '') ?>>

      <?php if ($stamp): ?>
        <time class="voice-row__date" datetime="<?= date('Y-m-d', $stamp) ?>"><?= date('j M Y', $stamp) ?></time>
      <?php else: ?>
        <span class="voice-row__date"></span>
      <?php endif; ?>

      <div class="voice-row__who">
        <span class="voice-row__name"><?= htmlspecialchars($name) ?></span>
        <?php if ($hometown !== ''): ?>
          <span class="voice-row__from"><?= htmlspecialchars($hometown) ?></span>
        <?php endif; ?>
      </div>

      <!-- htmlspecialchars is not optional here. This text comes from a
           form, and without it anything a reviewer types is treated as
           markup by the browser. -->
      <p class="voice-row__text"><?= htmlspecialchars($quote) ?></p>

      <span class="voice-row__rating">
        <?php voiceStars($rating); ?>
        <span class="sr-only"><?= $rating ?> out of 5 stars</span>
      </span>
    </article>
    <?php
}

/* Six on the page, the remainder behind the link. array_slice on an
   already-short array is cheap, and it keeps the two lists impossible
   to get out of sync. */
$voicesVisible = array_slice($testimonials, 0, VOICES_VISIBLE);
$voicesHidden  = array_slice($testimonials, VOICES_VISIBLE);
$voicesTotal   = count($testimonials);

/* The average, computed from the same rows that print below, so the
   headline figure can never disagree with the register under it. */
$voicesSum = 0;
foreach ($testimonials as $t) {
    $voicesSum += max(1, min(5, (int) ($t['rating'] ?? 5)));
}
$voicesAvg = $voicesTotal ? $voicesSum / $voicesTotal : 0;
?>

<?php if ($testimonials): ?>
<section class="voices">
  <!-- [ PHOTO 35 ] VISITOR QUOTES background — wide landscape. Sits at low opacity behind dark, so mood over detail. -->
  <div class="voices__bg"><img src="<?= $homephoto('voices-bg.jpg') ?>" alt=""></div>

  <div class="wrap voices__inner">
    <div class="voices__head">

      <div class="voices__intro">
        <span class="voices__eyebrow">Visitor register</span>
        <h2 class="voices__title" data-aos="fade-up">Loved by visitors,<br><b>recommended</b> across Bicol</h2>
        <p class="voices__sub" data-aos="fade-up">People who have made the trip out to Calaguas, up to the falls, or into the gold towns &mdash; and what they told us afterwards.</p>
      </div>

      <!-- No box around this. It sits on a rule, which is enough to
           separate it from the heading and keeps the section from
           turning into panels inside panels. -->
      <div class="voices__score" data-aos="fade-up">
        <span class="voices__score-num"><?= number_format($voicesAvg, 1) ?></span>
        <span class="voices__score-detail">
          <?php voiceStars($voicesAvg, 'voice-stars--lg'); ?>
          <span class="voices__score-count">Average of <?= (int) $voicesTotal ?> published <?= $voicesTotal === 1 ? 'review' : 'reviews' ?></span>
        </span>
      </div>

    </div>

    <!-- The column labels are the design here: they tell you how to
         read every row underneath without a single box being drawn. -->
    <div class="voices__register" id="voicesRegister">
      <div class="voices__legend" aria-hidden="true">
        <span>Date</span>
        <span>Visitor</span>
        <span>Remark</span>
        <span>Rating</span>
      </div>

      <?php foreach ($voicesVisible as $i => $voice): ?>
        <?php renderVoiceRow($voice, $i * 55); ?>
      <?php endforeach; ?>
    </div>

    <!-- MOBILE ONLY. The page still prints all six rows above — this
         just gives phones a button that reveals rows 3-6 in place,
         instead of showing all six at once on a screen that only
         comfortably fits two. Hidden on desktop by mobile.css.

         If there are more than six published reviews total, the
         existing "See the full register" button below appears once
         this one has been used, so the path to all of them still
         exists on a phone. -->
    <?php if ($voicesTotal > 2): ?>
      <div class="voices__more voices__more--mobile">
        <button type="button"
                class="voices__more-btn"
                data-voices-expand
                aria-expanded="false"
                aria-controls="voicesRegister">
          <span data-voices-expand-label>See more reviews</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($voicesHidden): ?>
      <!-- Only rendered when there is genuinely something more to see.
           A "see all" that opens the same six rows is worse than no
           link at all. -->
      <div class="voices__more voices__more--full">
        <button type="button"
                class="voices__more-btn"
                data-voices-open
                aria-haspopup="dialog"
                aria-controls="voicesModal">
          <span>See the full register &mdash; <?= (int) $voicesTotal ?> entries</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($voicesHidden): ?>
<!-- ===================================================================
     FULL REGISTER — modal

     Printed server-side rather than fetched on click. The rows are
     already in memory from the query above, the payload is a few
     kilobytes of text, and doing it this way means no endpoint to
     write, no loading state to design, and the reviews are still in
     the HTML for anything that does not run JavaScript.

     It sits outside <section class="voices"> on purpose. That section
     is overflow:hidden, and while a position:fixed child is not
     normally clipped by that, keeping the dialog out of any
     transformed or clipped ancestor removes the whole class of
     stacking bugs before it starts.

     The markup is inert until JS adds .is-open — see the VOICES MODAL
     block at the bottom of homepage.js.
     =================================================================== -->
<div class="voices-modal" id="voicesModal" role="dialog" aria-modal="true" aria-labelledby="voicesModalTitle" aria-hidden="true" hidden>
  <div class="voices-modal__backdrop" data-voices-close></div>

  <div class="voices-modal__panel" role="document">
    <header class="voices-modal__head">
      <div>
        <h3 class="voices-modal__title" id="voicesModalTitle">Visitor register</h3>
        <p class="voices-modal__count">
          <span class="voices-modal__avg"><?= number_format($voicesAvg, 1) ?></span>
          <?php voiceStars($voicesAvg); ?>
          <span><?= (int) $voicesTotal ?> published <?= $voicesTotal === 1 ? 'entry' : 'entries' ?></span>
        </p>
      </div>

      <button type="button" class="voices-modal__close" data-voices-close aria-label="Close the register">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </header>

    <div class="voices-modal__body">
      <!-- Every entry, not just the hidden ones. Someone who opens the
           full register expects all of them in one list, not the
           leftovers with the first six missing. -->
      <div class="voices__register voices__register--modal">
        <div class="voices__legend" aria-hidden="true">
          <span>Date</span>
          <span>Visitor</span>
          <span>Remark</span>
          <span>Rating</span>
        </div>

        <?php foreach ($testimonials as $voice): ?>
          <?php renderVoiceRow($voice); ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- ---------- floating stats pill ---------- -->
<section class="statbar">
  <div class="wrap">
    <div class="statbar__box" data-aos="fade-up">
      <div class="statbar__item">
        <span class="statbar__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-7.5 7-12a7 7 0 1 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.3"/></svg></span>
        <div><span class="statbar__num"><span data-count="12">0</span></span><span class="statbar__label">Municipalities</span></div>
      </div>
      <div class="statbar__item">
        <span class="statbar__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/><path d="M2 16c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/></svg></span>
        <div><span class="statbar__num"><span data-count="24">0</span></span><span class="statbar__label">Destinations listed</span></div>
      </div>
      <div class="statbar__item">
        <span class="statbar__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
        <div><span class="statbar__num"><span data-count="300">0</span>+</span><span class="statbar__label">Years of gold trade</span></div>
      </div>
      <div class="statbar__item">
        <span class="statbar__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2s6 7 6 11.5A6 6 0 0 1 6 13.5C6 9 12 2 12 2z"/></svg></span>
        <div><span class="statbar__num">Pacific</span><span class="statbar__label">Facing coastline</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ---------- what you'll eat ---------- -->
<section class="wrap craft">
  <div class="craft__grid">
    <div class="craft__art" data-aos="fade-up">
      <!-- PHOTOS: uploads/craft-1.jpg (portrait) and craft-2.jpg (square) -->
      <!-- [ PHOTO 36 ] WHY TRAVEL HERE — big photo — PORTRAIT, about 4:4.6 -->
      <!-- ==========================================================
           SLOW TRAVEL PHOTOS — also pointing at your existing three
           files for now. Swap when you have better ones:
             big          -> PORTRAIT ~4:4.6
             inset bottom -> SQUARE
             small top    -> SQUARE
           ========================================================== -->

      <!-- [ PHOTO 36 ] big — using nacalifalls.jpg for now -->
      <div class="a squircle"><img src="<?= $homephoto('BGB.jpg') ?>" alt=""></div>
      <!-- [ PHOTO 37 ] inset, bottom right — using bagasbas.jpg for now -->
      <div class="b squircle--soft"><img src="<?= $homephoto('Heritage.jpg') ?>" alt=""></div>

      <!-- [ PHOTO 38 ] small, top right — using black-nazarene.jpg for now -->
      <div class="c squircle--soft"><img src="<?= $homephoto('Burrito.jpg') ?>" alt=""></div>
    </div>

    <div data-aos="fade-up" data-aos-delay="80">
      <h2 class="head-mix">Come hungry,<br><b>leave with pasalubong</b></h2>
      <p class="craft__lead">Eating is half the trip here. Coconut milk and chilli run through nearly every menu, the seafood is priced like it was landed an hour ago, and Daet&rsquo;s caf&eacute;s will keep you sitting well past the afternoon.</p>

      <div class="craft__list">
        <div class="craft__row">
          <span class="craft__row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
          <div>
            <h3>Gata in almost everything</h3>
            <p>Laing, Bicol Express and kinunot, simmered in coconut milk until the sauce turns thick and glossy. Richer and hotter than Filipino food gets elsewhere, and best eaten with more rice than seems sensible.</p>
          </div>
        </div>

        <div class="craft__row">
          <span class="craft__row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 15c1.8 0 1.8-2 3.6-2s1.8 2 3.6 2 1.8-2 3.6-2 1.8 2 3.6 2 1.8-2 3.6-2"/><path d="M12 3v6M9 6l3-3 3 3"/></svg></span>
          <div>
            <h3>Seafood at port prices</h3>
            <p>Mercedes lands one of the biggest catches in the region every morning, so grilled squid, tuna belly and smoked tinapa cost a fraction of what they do inland. The beachfront kitchens at Bagasbas handle the rest.</p>
          </div>
        </div>

        <div class="craft__row">
          <span class="craft__row-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M9 12h6M12 9v6"/></svg></span>
          <div>
            <h3>Coffee, and sweet things to take home</h3>
            <p>Small caf&eacute;s around town pour cold brew and horchata on beach hours, staying open later than you would expect. Daet&rsquo;s pineapple has a June festival named after it, and its jam, tarts and kakanin travel home better than the fruit does.</p>
          </div>
        </div>
      </div>

      <p class="craft__cta">
        <a href="destinations.php" class="btn-pill btn-pill--orange magnetic">Find places to eat</a>
      </p>
    </div>
  </div>
</section>

<!-- ---------- travel notes ----------
     Titles and dates below are placeholders. Point each card at a real
     guide once you write one, or delete the section.
     PHOTOS: uploads/note-1.jpg, note-2.jpg, note-3.jpg — landscape 4:3
     -->
<section class="notes">
  <div class="wrap">
    <h2 class="head-mix head-center" data-aos="fade-up">Travel notes<br>&amp; <b>practical tips</b></h2>
    <p class="head-sub" data-aos="fade-up">The things worth knowing before you go &mdash; boat schedules, what the weather does, and which trips are worth the early start.</p>

    <div class="notes__grid">
      <a class="note-card" href="#" data-aos="fade-up">
        <div class="note-card__media">
          <span class="note-card__tag">Islands</span>
          <!-- [ PHOTO 38 ] TRAVEL NOTE 1 — landscape 4:3 -->
          <img src="<?= $homephoto('Travel-Calaguas.JPG') ?>" alt="">
        </div>
        <div class="note-card__body">
          <h3 class="note-card__title">Getting to Calaguas: boats, timings, and what to bring</h3>
          <span class="note-card__meta">Guide &middot; Add a date</span>
        </div>
      </a>

      <a class="note-card" href="#" data-aos="fade-up" data-aos-delay="80">
        <div class="note-card__media">
          <span class="note-card__tag">Seasons</span>
          <!-- [ PHOTO 39 ] TRAVEL NOTE 2 — landscape 4:3 -->
          <img src="<?= $homephoto('Travel-Quiet-Month.JPG') ?>" alt="">
        </div>
        <div class="note-card__body">
          <h3 class="note-card__title">When to visit: swell, rain, and the quiet months</h3>
          <span class="note-card__meta">Guide &middot; Add a date</span>
        </div>
      </a>

      <a class="note-card" href="#" data-aos="fade-up" data-aos-delay="160">
        <div class="note-card__media">
          <span class="note-card__tag">Heritage</span>
          <!-- [ PHOTO 40 ] TRAVEL NOTE 3 — landscape 4:3 -->
          <img src="<?= $homephoto('Travel-Paracale.jpg') ?>" alt="">
        </div>
        <div class="note-card__body">
          <h3 class="note-card__title">A day in Paracale: goldsmiths, streets, and the shoreline</h3>
          <span class="note-card__meta">Guide &middot; Add a date</span>
        </div>
      </a>
    </div>
  </div>
</section>

<section class="wrap section band-mist" id="gallery">
  <span class="eyebrow eyebrow--ocean" data-aos="fade-up">Gallery</span>
  <h2 class="font-display exp-heading" data-aos="fade-up" data-aos-delay="80">Moments, not itineraries</h2>

  <div class="masonry">

    <div class="media masonry-item ratio-3x4" data-aos="fade-up">
      <div class="gradient-fill"></div>
      <!-- [ PHOTO 29 ]  Gallery 1  —  PORTRAIT, 3:4  (e.g. 900 x 1200) -->
      <!-- [ PHOTO 41 ] GALLERY 1 — PORTRAIT 3:4 -->
      <img class="photo-layer" src="<?= $itephoto('ITENE-CALAGUAS.jpg') ?>" alt="Calaguas">
      <div class="media__label">Calaguas</div>
    </div>

    <div class="media masonry-item ratio-4x3" data-aos="fade-up" data-aos-delay="60">
      <div class="gradient-fill"></div>
      <!-- [ PHOTO 30 ]  Gallery 2  —  LANDSCAPE, 4:3  (e.g. 1200 x 900) -->
      <!-- [ PHOTO 42 ] GALLERY 2 — landscape 4:3 -->
      <img class="photo-layer" src="<?= $itephoto('ITENE-BAGASBAS.jpg') ?>" alt="Bagasbas">
      <div class="media__label">Bagasbas</div>
    </div>

    <div class="media masonry-item ratio-1x1" data-aos="fade-up" data-aos-delay="120">
      <div class="gradient-fill"></div>
      <!-- [ PHOTO 31 ]  Gallery 3  —  SQUARE, 1:1  (e.g. 1000 x 1000) -->
      <!-- [ PHOTO 43 ] GALLERY 3 — SQUARE 1:1 -->
      <img class="photo-layer" src="<?= $itephoto('ITENE-MANANAP.jpg') ?>" alt="Mananap Falls">
      <div class="media__label">Mananap Falls</div>
    </div>

    <div class="media masonry-item ratio-4x5" data-aos="fade-up">
      <div class="gradient-fill"></div>
      <!-- [ PHOTO 32 ]  Gallery 4  —  PORTRAIT, 4:5  (e.g. 1000 x 1250) -->
      <!-- [ PHOTO 44 ] GALLERY 4 — PORTRAIT 4:5 -->
      <img class="photo-layer" src="<?= $photocard('Labo-TulisPeak.jpg') ?>" alt="Mt. Bagacay, Labo">
      <div class="media__label">Mt. Bagacay</div>
    </div>

    <div class="media masonry-item ratio-3x4" data-aos="fade-up" data-aos-delay="60">
      <div class="gradient-fill"></div>
      <!-- [ PHOTO 33 ]  Gallery 5  —  PORTRAIT, 3:4  (e.g. 900 x 1200) -->
      <!-- [ PHOTO 45 ] GALLERY 5 — PORTRAIT 3:4 -->
      <img class="photo-layer" src="<?= $itephoto('ITENE-PARACALE.JPG') ?>" alt="Paracale">
      <div class="media__label">Paracale</div>
    </div>

    <div class="media masonry-item ratio-4x3" data-aos="fade-up" data-aos-delay="120">
      <div class="gradient-fill"></div>
      <!-- [ PHOTO 34 ]  Gallery 6  —  LANDSCAPE, 4:3  (e.g. 1200 x 900) -->
      <!-- [ PHOTO 46 ] GALLERY 6 — landscape 4:3 -->
      <img class="photo-layer" src="<?= $itephoto('ITENE-MERCEDES.jpg') ?>" alt="Mercedes Islands">
      <div class="media__label">Mercedes Islands</div>
    </div>

  </div>
</section>

<section class="quote" id="quote">
  <div class="gradient-fill"></div>
  <!-- ================================================================
       [ PHOTO 35 ]  QUOTE BAND background.
       Full width, landscape, at least 1920 x 1080.

       Large white text sits on top of this, so pick something calm and
       fairly dark: open water, sky, mist. A busy or bright photo will
       make the quote hard to read. There is a black overlay at 45%
       already, and you can raise it in homepage.css under .quote__overlay
       ================================================================ -->
  <!-- [ PHOTO 47 ] QUOTE BAND background — full width landscape. Big white text sits on it, so pick something calm and dark. -->
  <img class="photo-layer" src="<?= $homephoto('photo-bg.jpg') ?>" alt="">
  <div class="quote__overlay"></div>
  <p class="font-display quote__text" data-aos="fade-up">&ldquo;Every journey begins with a single destination. Let Camarines Norte be yours.&rdquo;</p>
</section>

<section class="cta" id="contact">
  <div class="gradient-fill"></div>
  <!-- ================================================================
       [ PHOTO 36 ]  CLOSING CALL TO ACTION background.
       Full width, landscape, at least 1920 x 1080.

       This is the last image on the page, so make it the second best
       one you have after the hero. Sunset shots work well here becaus
       the overlay darkens from the bottom up.
       ================================================================ -->
  <!-- [ PHOTO 48 ] CONTACT BAND background — full width landscape. Last photo on the page, make it a good one. -->
  <img class="photo-layer" src="<?= $homephoto('photo-48.jpg') ?>" alt="">
  <div class="cta__overlay"></div>
  <div class="cta__inner">
    <h2 class="font-display cta__title">Ready to Experience Camarines Norte?</h2>
    <p class="cta__desc">Breathtaking beaches, hidden waterfalls, rich heritage, and unforgettable adventures — all waiting just beyond the horizon.</p>
    <div class="cta__actions">
      <a href="#destinations" class="btn btn--orange">Explore Destinations</a>
      <a href="#" class="btn btn--outline">Contact Tourism Office</a>
    </div>
  </div>
</section>

<!-- The Bud.Ai assistant. Same one line goes in destinations.php,
     about.php and anywhere else it should appear. -->
<?php require __DIR__ . '/includes/bud-widget.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>