<?php
/* ===================================================================
   gallery.php — the photo page.

   WHAT CHANGED
   ------------
   The twelve tiles used to be typed out one by one in this file. They
   now come from the database, through includes/gallery-data.php, and
   are managed at pages/admin-gallery.php. Everything else on the page
   — the banner, the chapter layout, the closing strip, the lightbox
   and its script — is untouched.

   The page looks identical to before. It is only the source of the
   twelve photographs that moved.

   WHAT IS STILL HARDCODED, ON PURPOSE
     uploads/bg.mp4                  the banner clip
     uploads/gallery-banner.jpg      its poster still
     uploads/Gallery-Photo/outro.jpg the closing strip background

   Those three are layout, not content: they are chosen for how the
   text sits on top of them, and swapping one for a portrait phone
   photo would wreck the banner without erroring. Replace them by
   overwriting the file, keeping the same name.

   THE COUNTS in the banner (12 / 03 / 09) are now counted rather than
   typed, so they can no longer drift out of date. See gallery_counts()
   in includes/gallery-data.php.

   CLICKING A PHOTO opens it in a lightbox. Everything that drives it
   is in the block of script at the foot of this file and section 5 of
   gallery.css — nothing else on the site is involved, and the removal
   instructions are in that stylesheet if you want the page plain.
   =================================================================== */
$pageTitle = 'Gallery — Explore Camarines Norte';
$pageDesc  = 'The coast, the interior, and the towns of Camarines Norte in photographs.';
require __DIR__ . '/includes/header.php';

require_once __DIR__ . '/includes/media-guard.php';   // e(), gallery_url()
require_once __DIR__ . '/includes/gallery-data.php';

$sets   = gallery_sets();
$counts = gallery_counts($sets);
?>

<!-- ---------- banner ---------- -->
<header class="page-hero page-hero--short">
  <div class="gradient-fill"></div>
  <!-- ================================================================
       GALLERY BANNER BACKGROUND — video

       This is the SAME file the homepage hero uses. That is on
       purpose: it is already in the browser cache by the time anyone
       reaches this page, so it costs nothing extra to load.

       Want a different clip here? Drop it in uploads/ and change the
       src below. The poster stays per-page either way.

       The JS pauses this off-screen, skips it on phones and metered
       connections, and falls back to the poster still. Re-encode
       before shipping — see the ffmpeg command in homepage.php.

       TO GO BACK TO A PHOTO: delete the <video> and uncomment the
       <img> line below it.
       ================================================================ -->
  <video class="photo-layer"
         src="uploads/bg.mp4"
         poster="uploads/gallery-banner.jpg"
         autoplay muted loop playsinline
         preload="metadata"
         disablepictureinpicture
         disableremoteplayback></video>

  <!-- <img class="photo-layer" src="uploads/gallery-banner.jpg" alt=""> -->
  <div class="page-hero__scrim"></div>
  <div class="wrap page-hero__inner">
    <span class="page-hero__eyebrow">Gallery</span>
    <h1 class="font-display page-hero__title">Moments, not itineraries</h1>
    <p class="page-hero__lead">The province as it actually looks on an ordinary day, from the shoreline to the forest to the streets in between.</p>

    <!-- the counts rail: answers "how big is this page" before anyone
         has to scroll to find out. Counted from the data now, so
         adding a photo in the admin updates all three by itself. -->
    <div class="gal-meta">
      <span class="gal-meta__item"><span class="gal-meta__num"><?= gal_pad($counts['photos']) ?></span> photograph<?= $counts['photos'] === 1 ? '' : 's' ?></span>
      <span class="gal-meta__item"><span class="gal-meta__num"><?= gal_pad($counts['chapters']) ?></span> chapter<?= $counts['chapters'] === 1 ? '' : 's' ?></span>
      <span class="gal-meta__item"><span class="gal-meta__num"><?= gal_pad($counts['towns']) ?></span> town<?= $counts['towns'] === 1 ? '' : 's' ?></span>
    </div>
  </div>
</header>

<?php
/* ===================================================================
   THE CHAPTERS

   One loop, three passes. The markup inside is character for
   character what each of the three sections used to hold — only the
   values are filled in from the row instead of typed.

   The grey band on the middle chapter is the is_mist flag on the set,
   not the loop position, so the page still reads white / grey / white
   if the chapters are ever reordered.

   A chapter with no visible photographs is skipped entirely rather
   than rendering an empty heading over nothing.
   =================================================================== */
foreach ($sets as $set):
    if (!$set['photos']) {
        continue;
    }
    $count = count($set['photos']);
?>
<section class="wrap section gal-set<?= $set['is_mist'] ? ' gal-set--mist' : '' ?>">
  <header class="gal-set__head" data-aos="fade-up">
    <div>
      <span class="eyebrow eyebrow--ocean"><?= e($set['eyebrow']) ?></span>
      <h2 class="font-display gal-set__title"><?= e($set['title']) ?></h2>
    </div>
    <div>
      <p class="gal-set__note"><?= e($set['note']) ?></p>
      <span class="gal-set__count"><?= $count ?> photograph<?= $count === 1 ? '' : 's' ?></span>
    </div>
  </header>

  <div class="masonry">
    <?php foreach ($set['photos'] as $i => $photo):
        $delay = gal_delay($i);
    ?>
    <div class="media masonry-item <?= e($photo['ratio']) ?>" data-aos="fade-up"<?= $delay ? ' data-aos-delay="' . $delay . '"' : '' ?>>
      <div class="gradient-fill"></div>
      <img class="photo-layer"
           src="<?= gallery_url($photo['filename']) ?>"
           alt="<?= e($photo['alt']) ?>"
           <?= $i === 0 ? '' : 'loading="lazy" ' ?>decoding="async">
      <span class="media__zoom" aria-hidden="true"></span>
      <div class="media__label">
        <span class="media__place"><?= e($photo['place']) ?></span>
        <span class="media__town"><?= e($photo['town']) ?></span>
      </div>
      <button class="media__open" type="button"
              aria-label="Open the photograph of <?= e($photo['place']) ?>, <?= e($photo['town']) ?> at full size"></button>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<?php if ($counts['photos'] === 0): ?>
<!-- Nothing to show yet. Says so plainly instead of rendering three
     empty headings and a lot of white space. -->
<section class="wrap section gal-set">
  <p class="gal-set__note">No photographs have been published yet. Check back soon.</p>
</section>
<?php endif; ?>

<!-- ---------- closing strip ---------- -->
<section class="gal-end gal-end--photo" data-aos="fade-up">
  <img class="gal-end__bg" src="uploads/Gallery-Photo/outro.jpg"
       alt="" aria-hidden="true" loading="lazy" decoding="async">
  <div class="wrap gal-end__inner">
    <span class="eyebrow eyebrow--light">Where to next?</span>
    <h2 class="font-display gal-end__title">Every one of these is a place you can stand in</h2>
    <p class="gal-end__note">The destinations page has the directions, the fare, and the time of year each of these is at its best.</p>
    <a href="destinations.php" class="btn btn--orange">See where these are</a>
  </div>
</section>

<!-- ===================================================================
     LIGHTBOX

     One of these for the whole page — the script swaps the photo in
     and out of it. It starts hidden, and stays hidden if the script
     never runs, so a browser with JS off simply gets the page as it
     was before.
     =================================================================== -->
<div class="lightbox" id="galLightbox" role="dialog" aria-modal="true" aria-label="Photograph" hidden>
  <div class="lightbox__backdrop" data-lb="close"></div>

  <div class="lightbox__stage">
    <img class="lightbox__img" src="" alt="">
    <div class="lightbox__caption">
      <span class="media__place" data-lb="place"></span>
      <span class="media__town" data-lb="town"></span>
    </div>
  </div>

  <button class="lightbox__btn lightbox__btn--close" type="button" data-lb="close" aria-label="Close">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <path d="M5 5l14 14M19 5L5 19"/>
    </svg>
  </button>

  <button class="lightbox__btn lightbox__btn--prev" type="button" data-lb="prev" aria-label="Previous photograph">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M15 5l-7 7 7 7"/>
    </svg>
  </button>

  <button class="lightbox__btn lightbox__btn--next" type="button" data-lb="next" aria-label="Next photograph">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M9 5l7 7-7 7"/>
    </svg>
  </button>

  <span class="lightbox__counter" data-lb="counter"></span>
</div>

<script>
/* ===================================================================
   LIGHTBOX — no library, nothing shared with the rest of the site.

   It reads the tiles rather than being handed a list, so a thirteenth
   photograph added through the admin panel is picked up with no edit
   here — the counter, the wrap-around at the ends and the arrows all
   follow on their own. That property is why this file needed no
   changes when the photos moved into the database.

   WANT THE BIG VERSION TO BE A DIFFERENT FILE?
   Put the path on the button:

     <button class="media__open" data-full="uploads/gal-coast-1-large.jpg" ...>

   With no data-full it opens the same file the tile is showing, which
   is fine as long as your tiles are not tiny crops.
   =================================================================== */
(function () {
  var box = document.getElementById('galLightbox');
  if (!box) return;

  var tiles = Array.prototype.slice.call(document.querySelectorAll('.media__open'));
  if (!tiles.length) return;

  var img     = box.querySelector('.lightbox__img'),
      place   = box.querySelector('[data-lb="place"]'),
      town    = box.querySelector('[data-lb="town"]'),
      counter = box.querySelector('[data-lb="counter"]'),
      controls = box.querySelectorAll('.lightbox__btn'),
      index = -1,
      lastFocus = null;

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  function text(tile, cls) {
    var el = tile.querySelector('.' + cls);
    return el ? el.textContent.trim() : '';
  }

  function show(i) {
    index = (i + tiles.length) % tiles.length;

    var btn  = tiles[index],
        tile = btn.parentNode,
        photo = tile.querySelector('img.photo-layer');

    img.classList.remove('is-ready');
    img.src = btn.getAttribute('data-full') || (photo ? photo.currentSrc || photo.src : '');
    img.alt = photo ? photo.alt : '';

    place.textContent = text(tile, 'media__place');
    town.textContent  = text(tile, 'media__town');
    counter.textContent = pad(index + 1) + ' / ' + pad(tiles.length);
  }

  img.addEventListener('load', function () { img.classList.add('is-ready'); });

  function open(i) {
    lastFocus = document.activeElement;
    show(i);
    box.hidden = false;
    /* one frame between un-hiding and the class, or the browser has
       nothing to fade FROM and the backdrop snaps in */
    requestAnimationFrame(function () { box.classList.add('is-open'); });
    /* the page behind must not scroll while this is up */
    document.documentElement.style.overflow = 'hidden';
    controls[0].focus();
  }

  function close() {
    box.classList.remove('is-open');
    box.hidden = true;
    document.documentElement.style.overflow = '';
    if (lastFocus) lastFocus.focus();
  }

  tiles.forEach(function (btn, i) {
    btn.addEventListener('click', function () { open(i); });
  });

  box.addEventListener('click', function (e) {
    var hit = e.target.closest ? e.target.closest('[data-lb]') : null;
    if (!hit) return;
    var act = hit.getAttribute('data-lb');
    if (act === 'close') close();
    if (act === 'prev')  show(index - 1);
    if (act === 'next')  show(index + 1);
  });

  document.addEventListener('keydown', function (e) {
    if (!box.classList.contains('is-open')) return;

    if (e.key === 'Escape')     { close(); }
    if (e.key === 'ArrowLeft')  { show(index - 1); }
    if (e.key === 'ArrowRight') { show(index + 1); }

    /* keep Tab inside the three buttons — a dialog you can tab out of
       behind is worse than no dialog at all */
    if (e.key === 'Tab') {
      var first = controls[0],
          last  = controls[controls.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/bud-widget.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>