<?php
/* ===================================================================
   about.php — the province itself.

   PHOTOS on this page:
     [ ABOUT 1 ]  banner            uploads/about-banner.jpg
     [ ABOUT 2 ]  world-class beaches   uploads/why-beaches.jpg
     [ ABOUT 3 ]  gold country          uploads/why-heritage.jpg
     [ ABOUT 4 ]  rainforest            uploads/why-waterfalls.jpg
     [ ABOUT 5 ]  map / province shot    uploads/about-map.jpg

   ⚠ THE FACTS BELOW NEED CHECKING. I have kept them to things that
   are structural (twelve towns, capital, region) and marked anything
   specific. Travel times, festival dates, and population figures
   should come from the Provincial Tourism Office before you publish.
   =================================================================== */
$pageTitle = 'About Camarines Norte — Explore Camarines Norte';
$pageDesc  = 'The northernmost province of the Bicol Peninsula: twelve towns, a Pacific-facing coastline, and three centuries of gold.';
require __DIR__ . '/includes/header.php';
?>

<!-- ---------- banner ---------- -->
<header class="page-hero" id="top">
  <div class="gradient-fill"></div>
  <!-- [ ABOUT 1 ] wide landscape, 1920x800 or similar -->
  <!-- ================================================================
       ABOUT BANNER BACKGROUND — video

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
         poster="uploads/about-banner.jpg"
         autoplay muted loop playsinline
         preload="metadata"
         disablepictureinpicture
         disableremoteplayback></video>

  <!-- <img class="photo-layer" src="uploads/about-banner.jpg" alt=""> -->
  <div class="page-hero__scrim"></div>
  <div class="wrap page-hero__inner">
    <span class="page-hero__eyebrow hero-in" style="--in:0ms">About</span>

    <!-- Each line is its own window with the text sitting below it, then
         slid up on load. The <span> wrappers exist only for that — the
         heading still reads as one sentence to a screen reader. Keep the
         line break where it is: "at the top / of the Bicol Peninsula"
         breaks on the sense, and moving it breaks the reveal too. -->
    <h1 class="font-display page-hero__title hero-lines">
      <span class="hero-lines__mask" style="--in:90ms"><span>The province at the top</span></span>
      <span class="hero-lines__mask" style="--in:220ms"><span>of the Bicol Peninsula</span></span>
    </h1>

    <p class="page-hero__lead hero-in" style="--in:430ms">Camarines Norte faces the Pacific from the northern end of Bicol. Twelve towns, one coastline that runs the whole way around, and an interior that climbs into forest within minutes of the road.</p>
  </div>

  <!-- A real link, not a decoration: it goes where it says it goes, so
       it works on a keyboard and means something without JavaScript. -->
  <a class="page-hero__cue hero-in" style="--in:700ms" href="#province">
    <span class="page-hero__cue-text">The province</span>
    <span class="page-hero__cue-rule" aria-hidden="true"></span>
  </a>
</header>

<!-- ---------- the opening ---------- -->
<!-- ====================================================================
     THE OPENING

     Title stack on the page's own background, then the film below it as
     a contained block. The clip is not the backdrop for the words: it
     sits under them with its own edges, so nothing has to be dimmed to
     stay readable and the type is black on paper rather than white on a
     moving picture.

     THE CLIP — swap it here.
       src    the film. One line, marked below.
       poster the still behind it. This is what shows before the file
              loads, if it 404s, and on reduced-motion, so it should be
              a frame out of the clip itself and not a different photo.

     Re-encode before shipping. A phone-sized hero clip has no business
     being a 40MB export:

       ffmpeg -i in.mov -vf scale=1600:-2 -c:v libx264 -crf 26 \
              -preset slow -movflags +faststart -an out.mp4

     The -an drops the audio track. Leave it OFF if you want the sound
     button below to do anything — with -an there is nothing to unmute.

     TO GO BACK TO A PHOTO: delete the <video>, uncomment the <img>
     under it, and delete the sound button.
     ==================================================================== -->
<section class="hero-lead">
  <div class="wrap">

    <span class="hero-lead__eyebrow hero-in" style="--in:0ms">The province</span>

    <!-- h2, not h1. The banner above already carries this page's h1, and
         a document with two of them has no single title as far as a
         screen reader's heading list is concerned.

         Masked, not faded: the line slides up into a window with its
         overflow hidden, which is what the inner <span> is for. It still
         reads as one heading to a screen reader. -->
    <h2 class="font-display hero-lead__title hero-lines">
      <span class="hero-lines__mask" style="--in:90ms"><span>Camarines Norte</span></span>
    </h2>

    <figure class="hero-film hero-in" id="heroFilm" style="--in:260ms">
      <div class="hero-film__frame">
        <!-- a gradient behind the clip, so a missing file shows a field
             of colour rather than a broken-image icon -->
        <div class="gradient-fill"></div>

        <!-- ↓↓↓ THE CLIP. Change this src. ↓↓↓ -->
        <video class="photo-layer hero-film__video" id="heroFilmVideo"
               src="uploads/0727.mp4"
               poster="uploads/about-banner.jpg"
               autoplay muted loop playsinline
               preload="metadata"
               disablepictureinpicture
               disableremoteplayback></video>
        <!-- ↑↑↑ THE CLIP. Change this src. ↑↑↑ -->

        <!-- <img class="photo-layer hero-film__video" src="uploads/about-banner.jpg" alt=""> -->

        <div class="hero-film__scrim" aria-hidden="true"></div>

        <!-- Starts muted because that is the only way a browser will
             autoplay anything. aria-pressed is the state; the CSS swaps
             the icon off it, and the label inside is for screen readers
             only, which is why it is clipped rather than hidden — a
             display:none label is not announced. -->
        <button type="button" class="hero-film__sound" id="heroFilmSound" aria-pressed="false">
          <span class="hero-film__sound-label" id="heroFilmSoundLabel">Turn sound on</span>
          <svg class="hero-film__icon hero-film__icon--off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 5 6 9H3v6h3l5 4z"/><path d="m17 9 4 6"/><path d="m21 9-4 6"/>
          </svg>
          <svg class="hero-film__icon hero-film__icon--on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>
          </svg>
        </button>
      </div>
    </figure>

    <p class="hero-lead__body hero-in" style="--in:430ms">Camarines Norte faces the Pacific from the northern end of Bicol. Twelve towns, one coastline that runs the whole way around, and an interior that climbs into forest within minutes of the road.</p>

  </div>
</section>

<script>
/* --------------------------------------------------------------------
   THE OPENING FILM

   Two jobs: the sound button, and stopping the clip when nobody is
   looking at it.

   The second one matters more than it sounds. This clip autoplays, so
   without this it keeps running — and keeps talking, if the sound was
   turned on — for the whole time the reader is somewhere further down
   the page. Pausing, not clearing: the position is kept, so scrolling
   back does not restart it from the top.

   Under prefers-reduced-motion the clip never starts at all and the
   poster stands in for it, which is why the sound button hides itself
   in that case too — there would be nothing for it to unmute.
   -------------------------------------------------------------------- */
(function () {
  var film  = document.getElementById('heroFilm');
  var video = document.getElementById('heroFilmVideo');
  var sound = document.getElementById('heroFilmSound');
  var label = document.getElementById('heroFilmSoundLabel');
  if (!film || !video) return;

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  var pausedOffscreen = false;

  if (still.matches) {
    video.removeAttribute('autoplay');
    video.pause();
    if (sound) sound.hidden = true;
    return;
  }

  function filmVisible() {
    var box = film.getBoundingClientRect();
    var tall = window.innerHeight || document.documentElement.clientHeight;
    /* the same quarter the observer uses, so the two agree */
    return box.bottom > tall * 0.25 && box.top < tall * 0.75;
  }

  function suspendFilm() {
    if (video.paused) return;
    video.pause();
    pausedOffscreen = true;
  }

  function resumeFilm() {
    if (!pausedOffscreen) return;
    pausedOffscreen = false;
    var playing = video.play();
    if (playing && playing.catch) playing.catch(function () {});
  }

  if ('IntersectionObserver' in window) {
    /* A quarter of the panel, not a pixel of it. A sliver clipping the
       edge of the screen is not somebody watching. */
    new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) resumeFilm(); else suspendFilm();
      });
    }, { threshold: 0.25 }).observe(film);
  } else {
    window.addEventListener('scroll', function () {
      if (filmVisible()) resumeFilm(); else suspendFilm();
    }, { passive: true });
  }

  /* Switching tabs hides it as completely as scrolling past it. Browsers
     throttle a hidden tab anyway, but none of them reliably stop the
     audio, so say it. */
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) suspendFilm();
    else if (filmVisible()) resumeFilm();
  });

  /* Autoplay can still be refused — a metered connection, a data-saver,
     a browser that has decided it does not like this page. The poster
     stays up in that case, so there is nothing to say about it, but the
     sound button should not offer to unmute a clip that never ran. */
  var opening = video.play();
  if (opening && opening.catch) {
    opening.catch(function () { if (sound) sound.hidden = true; });
  }

  if (sound) {
    sound.addEventListener('click', function () {
      video.muted = !video.muted;
      sound.setAttribute('aria-pressed', String(!video.muted));
      if (label) label.textContent = video.muted ? 'Turn sound on' : 'Turn sound off';
      /* Unmuting counts as the gesture browsers wanted, so a clip that
         was refused a moment ago will usually start here. */
      if (video.paused && !pausedOffscreen) {
        var playing = video.play();
        if (playing && playing.catch) playing.catch(function () {});
      }
    });
  }
})();
</script>

<!-- ---------- orientation: the opener ---------- -->
<!-- ====================================================================
     THE OPENER

     Three columns: a statement on the left, a tall piece of media in the
     middle, a second thought on the right. The middle column is the
     anchor — it is taller than the other two and its shape is the thing
     the eye lands on first.

     THE SHAPES are asymmetric on purpose. Both media boxes have three
     generously rounded corners and one square one, which reads as
     deliberate where four equal corners would just read as "rounded".
     The square corner faces INWARD on each side, so the two shapes lean
     towards each other across the middle of the section.

     THE MIDDLE CLIP is uploads/bg.mp4, the same file the banner above
     uses. It is already in the browser cache by the time anyone scrolls
     here, so it costs nothing extra. It is muted, looping and
     non-interactive — decoration, not a video player, which is why
     there is no play button.

     Each media box has a .gradient-fill behind it, so a missing file
     shows a gradient instead of a broken-image icon.
     ==================================================================== -->
<section class="wrap section--lg intro">
  <div class="intro-grid">

    <div class="intro-col intro-col--lede" data-aos="fade-up">
      <h2 class="font-display intro__title"><strong>Where</strong> You Are</h2>
      <p class="intro__body">Camarines Norte sits at the top of the Bicol Peninsula with its back to the mountains and its face to the Pacific. It is a province better known for what it produces — gold, pineapples, fish — than for what it looks like, which is most of the reason the coastline is still as quiet as it is.</p>
      <p class="intro__body">Twelve towns in all, and no part of it is far from any other part.</p>

      <a href="#known-for" class="intro-card">
        <span class="font-display intro-card__title"><strong>Ready to</strong> explore<br>Camarines Norte?</span>
        <span class="intro-card__cta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9 12h6"/><path d="m13 9 3 3-3 3"/></svg>
          Start here
        </span>
      </a>
    </div>

    <div class="intro-col intro-col--media" data-aos="fade-up" data-aos-delay="80">
      <div class="intro-media intro-media--tall">
        <div class="gradient-fill"></div>
        <!-- [ ABOUT 7 ] the banner clip, reused. Swap the src for a
             different one if you want; keep it muted and looping. -->
        <!-- [ ABOUT 7 ] portrait, roughly 3:4 — this slot is tall -->
        <img class="photo-layer" src="uploads/About-Section-Photo/Opener-Pic2.jpg" alt="Camarines Norte coastline">
      </div>
    </div>

    <div class="intro-col intro-col--aside" data-aos="fade-up" data-aos-delay="160">
      <div class="intro-media intro-media--wide">
        <div class="gradient-fill"></div>
        <!-- [ ABOUT 8 ] landscape, roughly 4:3 -->
        <img class="photo-layer" src="uploads/About-Section-Photo/Opener-Pic1.jpg" alt="Coastline in Camarines Norte">
      </div>
      <h3 class="font-display intro__subtitle">Your visit starts<br>in <strong>Daet</strong></h3>
      <p class="intro__body">The capital is where the buses arrive and where most trips begin, and it is close enough to the rest of the province that you can base yourself there for the whole visit if you want to. Bagasbas, its surf beach, is a few minutes out of town.</p>
    </div>

  </div>
</section>

<!-- ---------- the province: map and town index, wired together ---------- -->
<!-- ====================================================================
     THE PROVINCE INDEX

     The map and the list of twelve towns used to be two separate
     sections a long way apart on the page, both carrying the same notes
     ("Daet — capital, surf, Rizal monument") and a comment warning that
     editing one meant editing the other. They are now one control:
     point at a town in either half and the other half answers.

     THE LIST IS THE SOURCE OF TRUTH. The script reads the names and
     notes out of the buttons below and drives the map readout from
     them, so there is only one copy of that text on the page. To change
     what a town is known for, edit its button and nothing else.

     WHAT LINKS THE TWO HALVES is data-town on each button, matched
     against the <title> inside each map <path>. Those strings have to
     agree exactly, including spacing — "Jose Panganiban", not "Jose
     Panganiban " or "José Panganiban". A mismatch fails quietly: the
     button still works as a button, it just stops lighting the map.
     ==================================================================== -->
<section class="wrap section--lg province" id="province">
  <div class="province__intro">
    <div data-aos="fade-up">
      <span class="eyebrow eyebrow--ocean">The map</span>
      <h2 class="font-display about-split__title">Small enough to cross in a day</h2>
      <p class="about-split__body">The coast runs from <strong>Basud</strong> in the south-east around to <strong>Santa Elena</strong> at the northern boundary, and almost every town on that line has a shoreline of its own. Inland, Labo takes up most of the interior and most of the high ground with it.</p>
      <p class="about-split__body">That compactness is the useful part. Two or three destinations in a day is realistic here in a way it is not in larger provinces, and the towns are close enough that a waterfall in the morning and a beach in the afternoon is an ordinary plan rather than an ambitious one.</p>

    </div>

    <div data-aos="fade-up" data-aos-delay="80">
      <dl class="fact-list">
        <div><dt>Region</dt><dd>Bicol (Region V)</dd></div>
        <div><dt>Capital</dt><dd>Daet</dd></div>
        <div><dt>Municipalities</dt><dd>12</dd></div>
        <div><dt>Coastline faces</dt><dd>Philippine Sea / Pacific</dd></div>
      </dl>
      <p class="note">Add land area, population, and the province's founding date here — figures worth taking from the PSA or the Provincial Capitol rather than a travel blog.</p>
    </div>
  </div>

  <div class="province__stage">
    <div class="province__main" data-aos="fade-up">

      <!-- ================================================================
           THE TOWN FILM — one clip per municipality

           ⚠ NO CLIPS ARE IN THE REPOSITORY YET. Every town button below
           already points at a file; none of them exist. That is fine and
           expected — until a file is there the panel shows the resting
           state and says so, and the map and list carry on working. You
           do not have to edit any markup to switch a town on. Drop the
           file at the path the button names and it starts playing.

           WHERE THE FILES GO — twelve clips and twelve poster stills:

             uploads/towns/basud.mp4             + basud.jpg
             uploads/towns/capalonga.mp4         + capalonga.jpg
             uploads/towns/daet.mp4              + daet.jpg
             uploads/towns/jose-panganiban.mp4   + jose-panganiban.jpg
             uploads/towns/labo.mp4              + labo.jpg
             uploads/towns/mercedes.mp4          + mercedes.jpg
             uploads/towns/paracale.mp4          + paracale.jpg
             uploads/towns/san-lorenzo-ruiz.mp4  + san-lorenzo-ruiz.jpg
             uploads/towns/san-vicente.mp4       + san-vicente.jpg
             uploads/towns/santa-elena.mp4       + santa-elena.jpg
             uploads/towns/talisay.mp4           + talisay.jpg
             uploads/towns/vinzons.mp4           + vinzons.jpg

           To change a path, edit that town's data-video / data-poster on
           its button in the list. Nothing else refers to these files.

           ENCODE THEM SMALL. Twelve clips on one page is a lot of
           bandwidth, which is why preload is "none" and nothing is
           fetched until a visitor actually picks a town. Aim for 16:9,
           1280x720, 20-40 seconds, under about 4MB each, no audio track
           unless it earns its place:

             ffmpeg -i in.mov -vf scale=1280:-2 -c:v libx264 -crf 26 \
                    -preset slow -movflags +faststart -an out.mp4

           The poster still is what shows before the clip loads and if
           the clip fails, so it is worth exporting a frame from the clip
           itself rather than using a different photograph.
           ================================================================ -->
      <figure class="town-film" id="townFilm">
        <div class="town-film__frame">
          <div class="gradient-fill"></div>

          <!-- src is set by the script, never in the markup: an empty
               src attribute makes the browser re-request the page. -->
          <video class="town-film__video" id="townFilmVideo"
                 muted loop playsinline preload="none"
                 disablepictureinpicture disableremoteplayback></video>

          <p class="town-film__pending" id="townFilmPending">Pick a town to watch it.</p>

          <span class="town-film__badge" id="townFilmBadge" aria-hidden="true"></span>

          <button type="button" class="town-film__sound" id="townFilmSound" hidden aria-pressed="false">
            <span class="town-film__sound-label">Sound off</span>
          </button>
        </div>

        <figcaption class="map-figure__cap" id="mapReadout" aria-live="polite">
          <span class="map-readout__name">Twelve towns, one coastline</span>
          <span class="map-readout__note">Point at a town on the map or in the list. Daet, the capital, is the one in amber.</span>
        </figcaption>
      </figure>

    <div class="province__map">
      <!-- ================================================================
           [ ABOUT 5 ] — THE MAP, drawn rather than photographed.

           This is a real map, not an illustration: the twelve outlines
           are the actual municipal boundaries, projected from the
           published administrative dataset. Because it is vector it stays
           sharp at any size, weighs a few kilobytes, needs no file in
           uploads/, and can never 404.

           It also themes itself — every colour comes from a CSS variable
           in base.css, so if the palette changes the map follows.

           Hovering a municipality lifts it and shows its name as a native
           tooltip (the <title> inside each path), which screen readers
           read too. No JavaScript involved.

           THE PROJECTION is equirectangular with the longitude scaled by
           cos(latitude), so the province is not stretched east-west the
           way a raw lon/lat plot would be. If you ever regenerate this,
           keep that correction or the shape will look subtly wrong to
           anyone who knows the coastline.

           TO GO BACK TO A PHOTO: delete the <svg> and uncomment the
           <img> below it.
           ================================================================ -->
      <figure class="map-figure">
        <svg class="cn-map" viewBox="4 44 837 499" role="img"
         aria-labelledby="cnMapTitle cnMapDesc" xmlns="http://www.w3.org/2000/svg">
      <title id="cnMapTitle">Map of Camarines Norte</title>
      <desc id="cnMapDesc">The twelve municipalities of Camarines Norte, with Daet, the provincial capital, marked. The coast faces the Philippine Sea to the north and east.</desc>

      <!-- THE viewBox IS CROPPED TO THE DRAWING, not to a round number.
           The province plus its labels and leader lines occupy x 18-827
           and y 58-529; the old 0 0 900 600 box wrapped that in about a
           fifth of empty canvas, which made the map render smaller than
           its column for no reason. This box is those bounds plus a
           small margin. If you move a label or add a leader line, check
           the bounds again (svg.getBBox()) or you will clip it. -->

      <!-- the twelve municipalities, largest first so the small eastern
           towns sit on top and stay clickable -->
      <g class="map__towns">
      <path class="map__town" id="mt-labo" d="M262.0 188.8 L291.1 198.8 L349.6 211.4 L387.5 202.7 L403.7 203.0 L441.8 201.7 L455.0 196.9 L473.7 202.8 L508.9 194.5 L520.9 241.6 L518.8 269.1 L503.8 275.7 L492.4 308.7 L468.2 346.1 L449.2 368.9 L317.4 318.7 L254.4 290.4 L144.5 247.9 L159.9 205.3 L177.7 191.2 L197.2 193.7 L262.0 188.8 Z"><title>Labo</title></path>
      <path class="map__town" id="mt-basud" d="M628.2 294.6 L628.8 301.0 L631.6 302.9 L633.8 303.9 L639.1 306.8 L662.1 320.3 L656.3 331.6 L658.6 354.5 L636.7 368.9 L648.3 386.0 L686.6 423.1 L674.0 464.4 L637.2 490.5 L574.6 442.5 L580.3 410.6 L576.2 397.2 L515.2 374.0 L519.0 368.2 L583.2 312.9 L606.3 293.9 L628.2 294.6 Z"><title>Basud</title></path>
      <path class="map__town" id="mt-capalonga" d="M259.4 89.3 L260.1 103.5 L259.2 121.4 L262.0 188.8 L197.2 193.7 L177.7 191.2 L150.4 166.0 L132.8 165.9 L113.9 153.1 L95.0 163.8 L87.2 145.5 L91.0 125.8 L114.0 124.9 L120.1 100.0 L135.5 108.2 L162.3 86.2 L179.2 86.0 L202.2 71.3 L226.8 75.2 L259.4 89.3 Z"><title>Capalonga</title></path>
      <path class="map__town" id="mt-santa-elena" d="M177.7 191.2 L159.9 205.3 L144.5 247.9 L144.1 256.8 L93.0 314.6 L66.3 324.3 L44.4 325.7 L18.0 318.6 L29.0 287.0 L53.2 284.2 L62.3 266.3 L50.7 240.3 L51.5 216.4 L62.5 178.4 L87.8 183.0 L70.8 159.8 L87.2 145.5 L95.0 163.8 L113.9 153.1 L132.8 165.9 L150.4 166.0 L177.7 191.2 Z"><title>Santa Elena</title></path>
      <path class="map__town" id="mt-jose-panganiban" d="M386.0 99.6 L402.7 151.1 L389.2 166.0 L403.7 203.0 L387.5 202.7 L349.6 211.4 L291.1 198.8 L262.0 188.8 L259.2 121.4 L260.1 103.5 L259.4 89.3 L274.5 93.4 L278.3 108.9 L308.2 125.0 L327.1 106.5 L359.4 118.5 L340.8 84.9 L379.5 75.2 L386.0 99.6 Z"><title>Jose Panganiban</title></path>
      <path class="map__town" id="mt-paracale" d="M504.3 138.9 L511.2 161.0 L508.9 194.5 L473.7 202.8 L455.0 196.9 L441.8 201.7 L403.7 203.0 L389.2 166.0 L402.7 151.1 L386.0 99.6 L399.3 101.4 L409.3 87.3 L433.6 93.2 L446.2 111.1 L447.7 127.9 L469.5 135.4 L478.9 124.4 L498.3 129.0 L504.3 138.9 Z"><title>Paracale</title></path>
      <path class="map__town" id="mt-san-lorenzo-ruiz" d="M583.2 312.9 L519.0 368.2 L515.2 374.0 L576.2 397.2 L580.3 410.6 L574.6 442.5 L506.7 400.7 L449.2 368.9 L506.9 332.6 L518.9 331.6 L536.5 315.7 L540.1 302.5 L563.7 296.1 L583.2 312.9 Z"><title>San Lorenzo Ruiz</title></path>
      <path class="map__town" id="mt-mercedes" d="M637.2 490.5 L674.0 464.4 L686.6 423.1 L648.3 386.0 L636.7 368.9 L658.6 354.5 L680.4 388.2 L703.9 398.4 L704.6 438.1 L714.0 477.5 L689.6 495.5 L682.3 528.7 L637.2 490.5 Z M662.1 320.3 L639.1 306.8 L633.8 303.9 L631.6 302.9 L628.8 301.0 L651.7 279.7 L665.5 290.0 L673.4 318.3 L662.1 320.3 Z M631.4 292.2 L614.0 275.8 L619.6 266.2 L639.4 281.3 L631.4 292.2 Z"><title>Mercedes</title></path>
      <path class="map__town" id="mt-vinzons" d="M574.2 209.7 L557.3 241.8 L540.1 247.8 L524.4 270.8 L518.8 269.1 L520.9 241.6 L508.9 194.5 L511.2 161.0 L504.3 138.9 L512.2 140.6 L527.3 164.6 L554.2 189.1 L555.9 207.4 L574.2 209.7 Z"><title>Vinzons</title></path>
      <path class="map__town" id="mt-san-vicente" d="M449.2 368.9 L468.2 346.1 L492.4 308.7 L503.8 275.7 L518.8 269.1 L524.4 270.8 L540.4 289.6 L540.1 302.5 L536.5 315.7 L518.9 331.6 L506.9 332.6 L449.2 368.9 Z"><title>San Vicente</title></path>
      <path class="map__town map__town--capital" id="mt-daet" d="M619.6 266.2 L614.0 275.8 L631.4 292.2 L628.2 294.6 L606.3 293.9 L583.2 312.9 L563.7 296.1 L540.1 302.5 L540.4 289.6 L604.6 249.4 L619.6 266.2 Z"><title>Daet</title></path>
      <path class="map__town" id="mt-talisay" d="M574.2 209.7 L588.7 234.7 L604.6 249.4 L540.4 289.6 L524.4 270.8 L540.1 247.8 L557.3 241.8 L574.2 209.7 Z"><title>Talisay</title></path>
      </g>

      <!-- labels that fit inside their own municipality -->
      <g class="map__labels">
      <text class="map__label" x="354.9" y="260.9">Labo</text>
      <text class="map__label" x="190.7" y="135.1">Capalonga</text>
      <text class="map__label" x="96.7" y="236.4">Santa Elena</text>
      <text class="map__label" x="332.4" y="147.5">Jose Panganiban</text>
      <text class="map__label" x="451.4" y="148.9">Paracale</text>
      <text class="map__label" x="523.5" y="374.4">San Lorenzo Ruiz</text>
      <text class="map__label" x="619.0" y="398.7">Basud</text>
      <text class="map__label" x="688.5" y="461.8">Mercedes</text>
      </g>

      <!-- the four eastern towns are too small and too close to label in
           place, so they get a dot and a leader line out to the gutter -->
      <g class="map__leaders">
      <polyline class="map__leader" points="533.6,208.2 726.0,150 734.0,150"/>
      <circle class="map__dot" cx="533.6" cy="208.2" r="3.2"/>
      <text class="map__label map__label--gutter" x="742.0" y="154">Vinzons</text>
      <polyline class="map__leader" points="563.1,254.0 726.0,196 734.0,196"/>
      <circle class="map__dot" cx="563.1" cy="254.0" r="3.2"/>
      <text class="map__label map__label--gutter" x="742.0" y="200">Talisay</text>
      <polyline class="map__leader" points="587.1,282.9 726.0,242 734.0,242"/>
      <circle class="map__dot" cx="587.1" cy="282.9" r="3.2"/>
      <text class="map__label map__label--gutter map__label--capital" x="742.0" y="246">Daet</text>
      <polyline class="map__leader" points="507.4,311.4 726.0,288 734.0,288"/>
      <circle class="map__dot" cx="507.4" cy="311.4" r="3.2"/>
      <text class="map__label map__label--gutter" x="742.0" y="292">San Vicente</text>
      </g>

      <text class="map__sea" x="700" y="70">PHILIPPINE SEA</text>
    </svg>
      </figure>
    </div>
    </div>

    <div class="town-index" data-aos="fade-up" data-aos-delay="80">
      <h3 class="town-index__title">The twelve municipalities</h3>
      <p class="town-index__lead">Every one of these has at least two places worth stopping for. Select a town to find it on the map and watch it.</p>

      <ul class="town-index__list">
        <li><button type="button" class="town-index__item" data-town="Basud" data-video="uploads/towns/Basud.mp4" data-poster="uploads/towns/basud.jpg"><span class="town-index__name">Basud</span><span class="town-index__note">Coast and mangrove</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Capalonga" data-video="uploads/towns/Capalonga.mp4" data-poster="uploads/towns/capalonga.jpg"><span class="town-index__name">Capalonga</span><span class="town-index__note">Pilgrimage town</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Daet" data-video="uploads/towns/daet.mp4" data-poster="uploads/towns/daet.jpg"><span class="town-index__name">Daet</span><span class="town-index__note">Capital, surf, Rizal monument</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Jose Panganiban" data-video="uploads/towns/JPANG.mp4" data-poster="uploads/towns/jose-panganiban.jpg"><span class="town-index__name">Jose Panganiban</span><span class="town-index__note">Bay, islands, mining history</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Labo" data-video="uploads/towns/labo.mp4" data-poster="uploads/towns/labo.jpg"><span class="town-index__name">Labo</span><span class="town-index__note">Falls and high ground</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Mercedes" data-video="uploads/towns/MERCEDES.mp4" data-poster="uploads/towns/mercedes.jpg"><span class="town-index__name">Mercedes</span><span class="town-index__note">Fishing port and islets</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Paracale" data-video="uploads/towns/PARACALE.mp4" data-poster="uploads/towns/paracale.jpg"><span class="town-index__name">Paracale</span><span class="town-index__note">Gold country</span></button></li>
        <li><button type="button" class="town-index__item" data-town="San Lorenzo Ruiz" data-video="uploads/towns/SAN_LORENZO_RUIZ.mp4" data-poster="uploads/towns/san-lorenzo-ruiz.jpg"><span class="town-index__name">San Lorenzo Ruiz</span><span class="town-index__note">Uplands and rivers</span></button></li>
        <li><button type="button" class="town-index__item" data-town="San Vicente" data-video="uploads/towns/SAN_VICENTE.mp4" data-poster="uploads/towns/san-vicente.jpg"><span class="town-index__name">San Vicente</span><span class="town-index__note">Waterfalls</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Santa Elena" data-video="uploads/towns/SANTA_ELENA.mp4" data-poster="uploads/towns/santa-elena.jpg"><span class="town-index__name">Santa Elena</span><span class="town-index__note">Northern boundary</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Talisay" data-video="uploads/towns/TALISAY.mp4" data-poster="uploads/towns/talisay.jpg"><span class="town-index__name">Talisay</span><span class="town-index__note">Mangrove park and church</span></button></li>
        <li><button type="button" class="town-index__item" data-town="Vinzons" data-video="uploads/towns/VINZONS.mp4" data-poster="uploads/towns/vinzons.jpg"><span class="town-index__name">Vinzons</span><span class="town-index__note">Calaguas jump-off</span></button></li>
      </ul>

      <a href="destinations.php" class="town-index__link">See what is in each town</a>
    </div>

<script>
/* --------------------------------------------------------------------
   THE PROVINCE INDEX

   Two halves of one control. Pointing at a municipality on the map or
   at a button in the list does the same three things: lifts that shape,
   highlights that row, and writes the town and what it is known for
   into the caption under the map.

   THE LIST IS THE ONLY COPY OF THAT TEXT. This used to hold a NOTES
   object duplicating all twelve notes, which had to be kept in step
   with the list by hand. It now reads them out of the buttons instead,
   so the markup is the single source and this script cannot drift from
   it.

   HOVER PREVIEWS, CLICK STICKS. Moving the pointer away restores
   whatever is pinned, or the resting caption if nothing is. That is
   what makes the thing usable on a touchscreen, where there is no
   hover to preview with — a tap simply pins. Tapping the pinned town
   again, or pressing Escape, lets go.

   This is an enhancement, not a requirement. Without it every map shape
   still carries a <title> that browsers show as a tooltip, and the list
   is still a list. What this adds is the link between them.
   -------------------------------------------------------------------- */
(function () {
  var map = document.querySelector('.cn-map');
  var out = document.getElementById('mapReadout');
  var index = document.querySelector('.town-index');
  if (!map || !out) return;

  var nameEl = out.querySelector('.map-readout__name');
  var noteEl = out.querySelector('.map-readout__note');
  var restName = nameEl.textContent;
  var restNote = noteEl.textContent;

  var shapes = map.querySelectorAll('.map__town');
  var buttons = index ? index.querySelectorAll('.town-index__item') : [];
  var pinned = null;

  /* name -> { shape, button, note }, built from whatever is on the page.
     A town missing from either half just gets a half-entry and is
     skipped by the linking below rather than throwing. */
  var towns = {};

  Array.prototype.forEach.call(shapes, function (el) {
    var t = el.querySelector('title');
    if (!t) return;
    var name = t.textContent.trim();
    towns[name] = towns[name] || {};
    towns[name].shape = el;
  });

  Array.prototype.forEach.call(buttons, function (btn) {
    var name = (btn.getAttribute('data-town') || '').trim();
    var note = btn.querySelector('.town-index__note');
    towns[name] = towns[name] || {};
    towns[name].button = btn;
    towns[name].note = note ? note.textContent.trim() : '';
    towns[name].video = btn.getAttribute('data-video') || '';
    towns[name].poster = btn.getAttribute('data-poster') || '';
  });

  /* ---- the film panel -------------------------------------------------
     Hovering previews; only a CLICK loads a clip. Swapping the video
     source on hover would fire a request every time the pointer crossed
     the list, which is the whole thing preload="none" is there to avoid.

     NOTHING HERE ASSUMES THE FILE EXISTS. No clips are uploaded yet, so
     the normal path through this code today is the failing one: set the
     source, the browser cannot find it, the error handler puts the panel
     back to a still and says so. When a file appears at the path its
     button names, the same code plays it with no edit anywhere.
     -------------------------------------------------------------------- */
  var film = document.getElementById('townFilm');
  var video = document.getElementById('townFilmVideo');
  var pending = document.getElementById('townFilmPending');
  var badge = document.getElementById('townFilmBadge');
  var sound = document.getElementById('townFilmSound');
  var restingPending = pending ? pending.textContent : '';
  var still = window.matchMedia('(prefers-reduced-motion: reduce)');

  function soundLabel(text) {
    var el = sound && sound.querySelector('.town-film__sound-label');
    if (el) el.textContent = text;
  }

  function clearFilm() {
    if (!film) return;
    pausedOffscreen = false;
    film.classList.remove('is-playing', 'is-missing');
    if (video) {
      video.pause();
      /* removeAttribute, not src="". An empty src resolves to the page
         URL and the browser re-requests this document as a video. */
      video.removeAttribute('src');
      video.removeAttribute('poster');
      video.load();
      video.muted = true;
    }
    if (pending) pending.textContent = restingPending;
    if (badge) badge.textContent = '';
    if (sound) { sound.hidden = true; sound.setAttribute('aria-pressed', 'false'); soundLabel('Sound off'); }
    Array.prototype.forEach.call(buttons, function (b) { b.classList.remove('is-filmless'); });
  }

  function loadFilm(name) {
    if (!film || !video) return;
    var t = towns[name];
    if (!t || !t.video) { clearFilm(); return; }

    film.classList.remove('is-missing');
    film.classList.add('is-playing');
    if (badge) badge.textContent = name;
    if (pending) pending.textContent = 'Loading ' + name + '…';

    /* Start every clip muted with the control reading "off". Carrying the
       previous town's unmuted state over means the next film starts
       talking on its own, which nobody asked for. */
    video.muted = true;
    if (sound) { sound.setAttribute('aria-pressed', 'false'); soundLabel('Sound off'); }
    Array.prototype.forEach.call(buttons, function (b) { b.classList.remove('is-filmless'); });

    if (t.poster) video.setAttribute('poster', t.poster);
    video.setAttribute('src', t.video);
    video.load();

    /* Autoplay has to be muted — that is the only kind browsers allow
       without a gesture. The promise can still reject, in which case the
       poster stays up rather than the panel looking broken. */
    if (!still.matches) {
      /* If the panel is not actually on screen yet, do not start it here.
         Mark it as owed a play instead and let the observer below start
         it the moment it scrolls into view. */
      if (filmVisible()) {
        var playing = video.play();
        if (playing && playing.catch) playing.catch(function () {});
      } else {
        pausedOffscreen = true;
      }
    }
  }

  if (video) {
    video.addEventListener('loadeddata', function () {
      film.classList.remove('is-missing');
      if (pending) pending.textContent = '';
      if (sound) sound.hidden = false;
    });

    video.addEventListener('error', function () {
      /* fires once with no src while clearing; ignore that one */
      if (!video.getAttribute('src')) return;
      film.classList.add('is-missing');
      film.classList.remove('is-playing');
      if (pending) pending.textContent = 'Film for this town has not been uploaded yet.';
      if (sound) sound.hidden = true;
      /* the row said "Playing"; there is nothing playing */
      if (pinned && towns[pinned] && towns[pinned].button) {
        towns[pinned].button.classList.add('is-filmless');
      }
    });
  }

  if (sound && video) {
    sound.addEventListener('click', function () {
      video.muted = !video.muted;
      sound.setAttribute('aria-pressed', String(!video.muted));
      soundLabel(video.muted ? 'Sound off' : 'Sound on');
    });
  }

  /* ---- stop the film when nobody is looking at it ---------------------
     A clip still running in a panel the reader has scrolled past is data
     and battery spent on an empty room, and on a phone it is the loudest
     thing on the page if the sound was left on. So: pause when the panel
     leaves the viewport, pick it up from where it stopped when it comes
     back. Pausing, not clearing — the position is kept, so returning to
     it does not restart the clip from the top.

     Only clips this script started ever get resumed. is-playing sits on
     the figure exactly while a town is pinned and its file loaded, so a
     missing film, or a panel Escape has cleared, stays quiet no matter
     how much scrolling happens over it.
     -------------------------------------------------------------------- */
  var pausedOffscreen = false;

  function filmVisible() {
    if (!film) return false;
    var box = film.getBoundingClientRect();
    var tall = window.innerHeight || document.documentElement.clientHeight;
    /* the same quarter the observer uses, so the two agree */
    return box.bottom > tall * 0.25 && box.top < tall * 0.75;
  }

  function suspendFilm() {
    if (!video || video.paused || !video.getAttribute('src')) return;
    video.pause();
    pausedOffscreen = true;
  }

  function resumeFilm() {
    if (!video || !pausedOffscreen) return;
    pausedOffscreen = false;
    /* reduced motion never had it playing; leave the poster alone */
    if (still.matches) return;
    if (!film.classList.contains('is-playing')) return;
    if (!video.getAttribute('src')) return;
    var playing = video.play();
    if (playing && playing.catch) playing.catch(function () {});
  }

  if (video && film) {
    if ('IntersectionObserver' in window) {
      /* A quarter of the panel, not a pixel of it. A sliver clipping the
         bottom edge of the screen is not somebody watching. */
      var filmWatch = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) resumeFilm(); else suspendFilm();
        });
      }, { threshold: 0.25 });
      filmWatch.observe(film);
    } else {
      /* No observer: ask the same question on scroll. passive because
         this never calls preventDefault and the browser should not have
         to wait to find that out. */
      window.addEventListener('scroll', function () {
        if (filmVisible()) resumeFilm(); else suspendFilm();
      }, { passive: true });
      window.addEventListener('resize', function () {
        if (filmVisible()) resumeFilm(); else suspendFilm();
      }, { passive: true });
    }

    /* Switching tabs or minimising hides the panel as completely as
       scrolling past it. Most browsers throttle a hidden tab anyway, but
       none of them reliably stop the audio, so say it explicitly. */
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) suspendFilm();
      else if (filmVisible()) resumeFilm();
    });
  }

  function paint(name) {
    Array.prototype.forEach.call(shapes, function (el) { el.classList.remove('is-active'); });
    Array.prototype.forEach.call(buttons, function (b) {
      b.classList.remove('is-active');
      b.setAttribute('aria-pressed', String(pinned === b.getAttribute('data-town')));
    });

    var t = name && towns[name];
    if (!t) {
      nameEl.textContent = restName;
      noteEl.textContent = restNote;
      map.classList.remove('is-reading');
      return;
    }

    nameEl.textContent = name;
    noteEl.textContent = t.note || '';
    if (t.shape) t.shape.classList.add('is-active');
    if (t.button) t.button.classList.add('is-active');
    map.classList.add('is-reading');
  }

  function preview(name) { paint(name); }
  function release() { paint(pinned); }

  function toggle(name) {
    pinned = (pinned === name) ? null : name;
    paint(pinned);

    if (!pinned) { clearFilm(); return; }

    loadFilm(pinned);

    /* The panel sits above the list, so picking a town from the bottom of
       the list — or from the map on a phone — can load a clip that is
       completely off-screen. Only scroll when it actually is: yanking the
       page around when the panel is already in view is worse than doing
       nothing at all. */
    if (film) {
      var box = film.getBoundingClientRect();
      var tall = window.innerHeight || document.documentElement.clientHeight;
      if (box.bottom < 0 || box.top > tall) {
        film.scrollIntoView({ behavior: still.matches ? 'auto' : 'smooth', block: 'center' });
      }
    }
  }

  Object.keys(towns).forEach(function (name) {
    var t = towns[name];

    if (t.shape) {
      /* the shapes are not focusable until the script makes them so —
         without JS they are decoration with a tooltip, which is honest */
      t.shape.setAttribute('tabindex', '0');
      t.shape.setAttribute('role', 'button');
      t.shape.setAttribute('aria-label', name);
      t.shape.addEventListener('mouseenter', function () { preview(name); });
      t.shape.addEventListener('focus', function () { preview(name); });
      t.shape.addEventListener('blur', release);
      t.shape.addEventListener('click', function () { toggle(name); });
      t.shape.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(name); }
      });
    }

    if (t.button) {
      t.button.setAttribute('aria-pressed', 'false');
      t.button.addEventListener('mouseenter', function () { preview(name); });
      t.button.addEventListener('focus', function () { preview(name); });
      t.button.addEventListener('blur', release);
      t.button.addEventListener('click', function () { toggle(name); });
    }
  });

  map.addEventListener('mouseleave', release);
  if (index) index.addEventListener('mouseleave', release);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && pinned) { pinned = null; paint(null); clearFilm(); }
  });
})();
</script>

  </div>
</section>

<!-- ---------- festivals ---------- -->
<!-- ====================================================================
     CRACKED MOSAIC — four festivals

     Four photos, one per festival in the list beside them, each cut in
     half down the middle AND across the middle so it breaks into four
     quarters that sit apart. Corners are square, not rounded.

     The quarters do not just back away from each other. Each one also
     SLIDES along the seam it sits on, so the right half of a photo
     rides higher than the left and the bottom half sits further right
     than the top. That stagger is what stops the break reading as a
     tidy plus sign drawn over the middle of the picture.

     Each photo answers to its own hover. Point at one and only that one
     closes up; the other three stay broken. That is why the tabindex
     and the aria-label live on the <figure> and not on the grid — each
     figure is its own control, so keyboard users tab through them one
     at a time and get the same behaviour as the mouse.

     THE TRICK, PER PHOTO: all four <img> tags inside one <figure> point
     at the SAME file. Each quarter is a window onto part of it, and the
     photo inside is blown up and shifted so every window lands on a
     different quarter. Line the four up and the photo is whole again.

     So the src must match across all four quarters of a figure. Change
     one, change all four, or that photo will never close up. Different
     figures are completely independent — swap them freely.

     THE ORDER MATTERS. Photo 1 is Bantayog, 2 is Pinyasan, 3 is Pabirik,
     4 is Kadagatan — the same order as the list in the column beside
     them. If you reorder one, reorder the other.

     ⚠ A GREY QUARTER MEANS A BROKEN PATH, NOT A CSS BUG. This section
     used to hardcode all sixteen <img> tags, and swapping a photo meant
     editing four of them by hand. Miss one and that quarter 404s and
     shows the navy gradient — which is exactly what "the picture only
     appears in the upper left" looks like: the top-left tag got the new
     path, the other three kept the old one.

     It is now driven by the $festivals array below. Set 'photo' once
     and the loop writes it into all four quarters, so the four can no
     longer disagree. Photos live in $photoDir.
     ==================================================================== -->
<section class="section--lg crack-band">
  <div class="wrap crack-split">

    <div class="crack-stage" data-aos="fade-up">
      <div class="crack-grid">

        <?php
          /* ------------------------------------------------------------------
             ONE ENTRY PER FESTIVAL. This array drives BOTH the mosaic below
             and the list in the column beside it, so the two can never fall
             out of order and a photo can never be half-swapped.

             TO CHANGE A PHOTO: edit that festival's 'photo' once. The loop
             writes it into all four quarters for you.

             'photo' is relative to $photoDir. Portrait 4:5, 800x1000 minimum,
             and keep the subject off dead centre — both seams cross the middle
             of the frame, so a face parked there gets quartered.

             'wide' => true gives that figure the off-centre 54/46 split
             instead of the even one. Purely visual; vary it so the four
             photos do not all break the same way.
             ------------------------------------------------------------------ */
          $photoDir = 'uploads/About-Section-Photo/';

          $festivals = [
            [
              'name'  => 'Bantayog Festival',
              'town'  => 'Daet',
              'photo' => 'About-Bantayog.jpg',
              'wide'  => false,
              'alt'   => 'Street dancers at the Bantayog Festival in Daet',
              'body'  => 'The province-wide celebration, held alongside the founding anniversary and centred on the first monument raised anywhere in the country to Jose Rizal. Contingents come in from the towns, each dancing its own festival.',
            ],
            [
              'name'  => 'Pinyasan Festival',
              'town'  => 'Daet',
              'photo' => 'Pinyasan-Festival.webp',
              'wide'  => true,
              'alt'   => 'Pineapple floats at the Pinyasan Festival in Daet',
              'body'  => "Daet's biggest week, built around the Formosa queen pineapple the province is known for — street dancing, floats, an agro-industrial fair, and the town's patronal feast running through it.",
            ],
            [
              'name'  => 'Pabirik Festival',
              'town'  => 'Paracale',
              'photo' => 'Pabirik-Festival.jpg',
              'wide'  => true,
              'alt'   => 'Gold-panning dance at the Pabirik Festival in Paracale',
              'body'  => 'Named for the pabirik, the wooden pan gold miners here still use. The dance follows the work itself, which is as close as the province comes to putting three centuries of gold on a stage.',
            ],
            [
              'name'  => 'Kadagatan Festival',
              'town'  => 'Mercedes',
              'photo' => 'Kadagatan-Festival.jpg',
              'wide'  => false,
              'alt'   => 'Decorated fishing boats at the Kadagatan Festival in Mercedes',
              'body'  => "A fishing town's thanksgiving for what the season brought in, held where the province's largest fleet ties up. Boats get decorated; the sea gets the credit.",
            ],
          ];
        ?>

        <?php foreach ($festivals as $f): ?>
          <?php
            $src = htmlspecialchars($photoDir . $f['photo'], ENT_QUOTES);
            $alt = htmlspecialchars($f['alt'], ENT_QUOTES);
          ?>
          <figure class="crack<?= $f['wide'] ? ' crack--wide-left' : '' ?>" tabindex="0"
                  aria-label="<?= $alt ?>. Hover or tap to put it together.">
            <?php foreach (['tl','tr','bl','br'] as $q): ?>
              <div class="crack__shard crack__shard--<?= $q ?>">
                <div class="gradient-fill"></div>
                <img class="crack__img" src="<?= $src ?>" alt="<?= $q === 'tl' ? $alt : '' ?>">
              </div>
            <?php endforeach; ?>
          </figure>
        <?php endforeach; ?>

        <span class="crack__hint">Tap or hover a photo to put it together</span>
      </div>
    </div>

    <div class="crack-copy" data-aos="fade-up" data-aos-delay="80">
      <span class="eyebrow eyebrow--ocean">Festivals</span>
      <h2 class="font-display crack-copy__title">Four reasons to<br>time your visit</h2>
      <div class="crack-copy__rule"></div>
      <p class="crack-copy__body">Nearly every town here keeps a festival of its own, and most of them are built on whatever that town lives off — a fruit, a fishing ground, a seam of gold. These four are the ones worth planning around.</p>

      <!-- Same $festivals array as the mosaic, so the order here always
           matches the order of the photos. Edit the array, not this. -->
      <dl class="fest-list">
        <?php foreach ($festivals as $f): ?>
          <div class="fest">
            <dt class="fest__name"><?= htmlspecialchars($f['name']) ?> <span class="fest__town"><?= htmlspecialchars($f['town']) ?></span></dt>
            <dd class="fest__body"><?= htmlspecialchars($f['body']) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>

      <p class="note">Names and towns are confirmed, but <strong>dates move year to year</strong> — Pinyasan generally falls in June and Bantayog in April, and neither is fixed. Get the current programme from the Provincial Tourism Office before publishing, and put a "last checked" date on the page. Worth a festivals.php of its own once you have the calendar.</p>

      <a href="destinations.php" class="btn btn--orange crack-copy__cta">Plan around a fiesta</a>
    </div>

  </div>
</section>

<script>
/* --------------------------------------------------------------------
   TAP TO PUT IT TOGETHER, TAP AWAY TO BREAK IT APART

   The CSS closes a photo on :hover, which phones and tablets do not
   have. This is the finger's version of the same moment.

     tap or press a photo  ->  that one closes up
     tap anywhere else     ->  it breaks apart again
     tap the same one      ->  same thing, it breaks apart
     Esc                   ->  everything breaks apart

   Press and hold works too, and needs no extra code: the class goes on
   at pointerdown and only comes off when something else is pressed, so
   a held finger shows the photo whole for exactly as long as it is
   held, and a quick tap leaves it whole until you tap away.

   ONE AT A TIME. Opening one closes the other three, which is the same
   rule the mouse follows — you can only point at one photo. To let
   several stand open at once, delete the closeOthers() call below.

   WHAT WAS HERE BEFORE, AND WHY IT IS GONE

   The previous version assembled every photo automatically on touch,
   the first time it scrolled into view, via IntersectionObserver. It
   was well meant — it made sure a phone saw the photos whole — but it
   also meant there was nothing left to tap: by the time you reached the
   collage it had already done the one thing it does. Removed, so the
   phone gets the interaction rather than a spoiler of it.

   pointerdown, not click: it fires the instant the finger lands, which
   is what makes the photo feel like it answers the touch rather than
   the release. Every browser that runs this site has PointerEvent.

   Nothing here is required for the content to be visible. If this never
   runs, all four photos still show in full, just broken into quarters,
   and the mouse still works.
   -------------------------------------------------------------------- */
(function () {
  var photos = document.querySelectorAll('.crack');
  if (!photos.length) return;

  /* TOUCH ONLY. A device with a real pointer already has this behaviour
     through :hover and does not need a second, competing one: a click
     toggle on a desktop would PIN a photo closed up after the mouse had
     moved away, which reads as the collage being stuck rather than as a
     feature. Anything that reports a hover-capable pointer leaves with
     the mouse behaviour exactly as it was.

     Note this also covers the desktop browser's device simulator, which
     usually still reports (hover:hover) even while it draws a phone —
     so test the tap on an actual phone, or the simulator will show you
     the hover version and look like nothing changed. */
  if (window.matchMedia('(hover: hover)').matches) return;

  var grid = document.querySelector('.crack-grid');

  /* Close everything except one photo (pass null to close all).

     The blur matters: .crack carries tabindex="0" and the CSS assembles
     on :focus-within, so a figure left focused after a tap would stay
     closed up no matter what class we remove. Dropping focus is what
     actually lets it break apart again. */
  function closeOthers(keep) {
    Array.prototype.forEach.call(photos, function (p) {
      if (p === keep) return;
      p.classList.remove('is-assembled');
      if (p.contains(document.activeElement)) document.activeElement.blur();
    });
    if (grid) grid.classList.toggle('is-open', !!keep);
  }

  Array.prototype.forEach.call(photos, function (photo) {
    photo.addEventListener('pointerdown', function () {
      var nowOpen = photo.classList.toggle('is-assembled');
      closeOthers(nowOpen ? photo : null);
    });
  });

  /* Anything pressed outside a photo breaks the collage apart. This runs
     for taps on the photos too — it just returns early, because the
     handler above has already dealt with them. */
  document.addEventListener('pointerdown', function (e) {
    if (e.target.closest && e.target.closest('.crack')) return;
    closeOthers(null);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.key === 'Esc') closeOthers(null);
  });
})();
</script>

<!-- ---------- what the province is made of ---------- -->
<!-- ====================================================================
     BEST OF CAM NORTE

     A dark band, then a row of cards that scrolls sideways. It does not
     list what the province HAS; it names what the province IS, four
     times, each with a photograph, a figure and a short paragraph. A
     list of attractions is forgettable; four named characteristics are
     not.

     THE CARDS ARE STAGGERED, not aligned. Each sits at its own height
     and its photo has its own shape, set by two inline properties on
     the article (--drop and --ratio). A row of identical cards at one
     baseline reads as a widget; an uneven row reads as a spread.

     KEEP #known-for ON THIS SECTION. The card at the top of the page
     ("Ready to explore Camarines Norte?") links to it, and so may other
     pages. Renaming the id breaks that link silently.

     THE COUNTERS STILL BELONG TO THE SCRIPT BELOW. The figures keep the
     .beat__count class and the data-beat-count attribute on purpose —
     that script finds them by class and counts each one the first time
     it comes into view. Rename the class here and the numbers sit at
     zero forever.

     ⚠ CLAIMS THAT NEED CHECKING before you publish:
       • 100+ beaches — came off the old counter row, unverified.
       • Freediving in Paracale — I could not find an operator, a named
         site or a depth for this. Paracale is a gold town with boat
         access to Calaguas; confirm with the Provincial Tourism Office
         that someone actually runs dives there, or move the card to
         Quinamanukan in Mercedes, which is a marine sanctuary and does
         check out. Nothing in the copy names a site or a depth, so it
         is safe as written but thin.
     The twelve towns, the four languages, the Manide towns and the
     food are sourced and safe.

     PHOTOS: portrait suits this layout better than landscape — the
     cards are about 27rem wide and the tall ones carry a 3:4 crop.
     900x1200 or better. All six live in uploads/About-Section-Photo/.
     ==================================================================== -->
<section class="section--lg best" id="known-for">

  <div class="wrap best__head" data-aos="fade-up">
    <div class="best__intro">
      <span class="eyebrow eyebrow--muted">Why visit</span>
      <h2 class="font-display best__title">Best of Cam Norte</h2>
      <p class="best__lead">Twenty-five-odd places here are worth the detour, and nearly all of them come out of the same handful of things &mdash; the people who have always been here, the sea, the forest, and what the towns cook and celebrate around them. Start with whichever one you came for.</p>
    </div>

    <div class="best__nav">
      <!-- aria-hidden because it describes a gesture, not the content.
           Keyboard and screen-reader users get the buttons beside it,
           which say what they do. -->
      <p class="best__cue" aria-hidden="true">Scroll to explore <span class="best__cue-arrow">&rarr;</span></p>

      <!-- Real buttons, not decoration. The wheel and the drag are both
           gestures a reader has to discover; these are the only control
           on this row that is visible, reachable by tab, and cannot be
           intercepted by another script on the page. -->
      <div class="best__arrows">
        <button type="button" class="best__arrow" data-best-nav="prev" aria-controls="bestTrack">
          <span class="best__arrow-label">Previous cards</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
        </button>
        <button type="button" class="best__arrow" data-best-nav="next" aria-controls="bestTrack">
          <span class="best__arrow-label">More cards</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>
    </div>
  </div>

  <!-- ================================================================
       THE TRACK

       tabindex="0" is not decoration. A scrolling box that is not
       focusable cannot be scrolled from a keyboard at all, so without
       it the last two cards are unreachable for anyone not using a
       mouse. The label says so out loud, because "group, Best of Cam
       Norte" on its own does not tell you the arrow keys do anything.

       TO ADD OR REORDER CARDS: copy an <article> whole. Two inline
       properties are the only things that vary between them:

         --ratio  the shape of the photo   (3/4 tall, 4/3 wide, 1/1)
         --drop   how far down it sits     (0 to about 5rem)

       Vary BOTH, and do not let two neighbours share a value or the
       stagger stops reading as deliberate and starts looking like a
       row that failed to line up.
       ================================================================ -->
  <div class="best__track" id="bestTrack" tabindex="0" role="group"
       aria-label="Best of Cam Norte. Use the left and right arrow keys to scroll through six cards.">

    <article class="best-card" style="--ratio:1/1; --drop:0rem">
      <div class="best-card__media">
        <div class="gradient-fill"></div>
        <img class="photo-layer" src="uploads/About-Section-Photo/Why-People.jpg" alt="Manide elder in Camarines Norte">
      </div>
      <div class="best-card__copy">
        <span class="best-card__material">People</span>
        <p class="best-card__figure"><span class="best-card__value"><span class="beat__count" data-beat-count="4">0</span></span><span class="best-card__unit">languages here, and one of them almost nowhere else</span></p>
        <h3 class="font-display best-card__title">The Manide were here first</h3>
        <p class="best-card__body">Most of the province speaks Bikol or Tagalog, but the Manide &mdash; a Negrito people who call themselves Kabihug &mdash; kept communities in Labo, Jose Panganiban and Paracale long before either arrived. Their language is counted as threatened, and children are still growing up speaking it.</p>
      </div>
    </article>

    <article class="best-card" style="--ratio:3/4; --drop:2.5rem">
      <div class="best-card__media">
        <div class="gradient-fill"></div>
        <img class="photo-layer" src="uploads/About-Section-Photo/Why-Beaches.jpg" alt="White sand beach in Camarines Norte">
      </div>
      <div class="best-card__copy">
        <span class="best-card__material">Sea</span>
        <p class="best-card__figure"><span class="best-card__value"><span class="beat__count" data-beat-count="100">0</span>+</span><span class="best-card__unit">white-sand beaches and islands</span></p>
        <h3 class="font-display best-card__title">The coast runs the whole way round</h3>
        <p class="best-card__body">Calaguas gets the photographs, but the shoreline barely stops: Bagasbas for surf a few minutes out of Daet, the Mercedes islets for a day on a boat, and long stretches in between with nothing on them at all.</p>
      </div>
    </article>

    <article class="best-card" style="--ratio:1/1; --drop:4rem">
      <div class="best-card__media">
        <div class="gradient-fill"></div>
        <img class="photo-layer" src="uploads/About-Section-Photo/Why-FreeDiving.jpg" alt="Freediver descending off Paracale">
      </div>
      <div class="best-card__copy">
        <span class="best-card__material">Freediving</span>
        <p class="best-card__figure"><span class="best-card__value">One breath</span><span class="best-card__unit">and the reef starts about where the boat stops</span></p>
        <h3 class="font-display best-card__title">Paracale is where you go under</h3>
        <p class="best-card__body">The town sits on calm, sheltered water, and bancas go out from the shore all day &mdash; the same boats that run the Calaguas crossing. No tanks, no certification course, no schedule: you borrow a mask, follow someone who knows the ground, and come up when you need to.</p>
      </div>
    </article>

    <article class="best-card" style="--ratio:3/4; --drop:1.5rem">
      <div class="best-card__media">
        <div class="gradient-fill"></div>
        <img class="photo-layer" src="uploads/About-Section-Photo/Why-Forest.jpg" alt="Mananap Falls">
      </div>
      <div class="best-card__copy">
        <span class="best-card__material">Forest</span>
        <p class="best-card__figure"><span class="best-card__value">Minutes</span><span class="best-card__unit">from the last road to the treeline</span></p>
        <h3 class="font-display best-card__title">The interior climbs fast</h3>
        <p class="best-card__body">Labo takes up most of the high ground, and the change comes quickly. Falls like Mananap sit close enough to the highway that a morning inland and an afternoon on the sand is an ordinary plan here rather than an ambitious one.</p>
      </div>
    </article>

    <article class="best-card" style="--ratio:4/3; --drop:5rem">
      <div class="best-card__media">
        <div class="gradient-fill"></div>
        <img class="photo-layer" src="uploads/About-Section-Photo/Why-Fiesta.jpg" alt="Street dancers at the Bantayog Festival in Daet">
      </div>
      <div class="best-card__copy">
        <span class="best-card__material">Fiesta</span>
        <p class="best-card__figure"><span class="best-card__value"><span class="beat__count" data-beat-count="12">0</span></span><span class="best-card__unit">towns, and about as many fiestas</span></p>
        <h3 class="font-display best-card__title">Every town keeps its own</h3>
        <p class="best-card__body">Most are built on whatever that town lives off &mdash; a pineapple in Daet, a seam of gold in Paracale, a season&rsquo;s catch in Mercedes. Time your visit to one and you get the place at full volume.</p>
      </div>
    </article>

    <article class="best-card" style="--ratio:1/1; --drop:4.5rem">
      <div class="best-card__media">
        <div class="gradient-fill"></div>
        <img class="photo-layer" src="uploads/About-Section-Photo/Why-Food.jpg" alt="Sinantolan cooked in coconut cream">
      </div>
      <div class="best-card__copy">
        <span class="best-card__material">Food</span>
        <p class="best-card__figure"><span class="best-card__value">Gata</span><span class="best-card__unit">in nearly everything, and chili in most of it</span></p>
        <h3 class="font-display best-card__title">Ask for the sinantolan</h3>
        <p class="best-card__body">Shredded cottonfruit cooked down in coconut cream with green chilies &mdash; a Daet dish, and the one people here name first. Mercedes sends out smoked tinapa by the tray, and the Formosa pineapple is sweet enough that Daet built a festival on it.</p>
      </div>
    </article>

  </div>

</section>

<script>
/* --------------------------------------------------------------------
   THE WHEEL DRIVES THE TRACK SIDEWAYS

   A mouse wheel only has one axis, so without this the cards past the
   right edge cannot be reached with a mouse at all. Over the track,
   wheel down moves the row left and wheel up moves it back right.

   WHY THE LISTENER IS ON window AND NOT ON THE TRACK. The first version
   of this bound to the track itself and did nothing on this page. The
   likely reason is another script — a smooth-scroll wrapper, a slider,
   anything that takes the wheel — calling stopPropagation() on the way
   down, which kills every listener below it before the event ever
   reaches the track.

   Capture phase on window is the earliest point in the entire event
   path, so nothing further down can take the event away first. And
   stopPropagation() does NOT silence other listeners bound to the same
   node, so even a library already sitting on window capture cannot
   shut this one out. The cost is having to ask "is this event over the
   track?" ourselves, which is the contains() check.

   THE IMPORTANT PART IS STILL WHERE IT STOPS. The moment the track hits
   either end, the handler stands down and hands the wheel back to the
   page, so the section costs one row of extra scrolling to pass and
   never traps the reader.

   THREE CASES IT DELIBERATELY DOES NOT TOUCH:
     ctrlKey       that is a pinch-zoom, not a scroll
     |deltaX|>|Y|  a trackpad already swiping sideways; leave it alone
     deltaMode>0   Firefox reports lines or pages rather than pixels, so
                   the delta has to be converted before it means anything

   passive:false is what makes preventDefault work. Browsers assume
   passive on wheel now, and a passive handler silently cannot stop the
   page from scrolling underneath us.
   -------------------------------------------------------------------- */
(function () {
  var track = document.getElementById('bestTrack') ||
              document.querySelector('.best__track');

  if (!track) {
    /* Loud on purpose. If the id ever gets renamed or the section is
       edited out, this is the only way anyone finds out. */
    if (window.console) console.warn('[best] track not found — wheel scrolling is off.');
    return;
  }

  var LINE = 16;   /* px per line, near enough for one wheel notch */

  window.addEventListener('wheel', function (e) {
    if (e.ctrlKey) return;
    if (!track.contains(e.target)) return;
    if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

    var step = e.deltaY;
    if (e.deltaMode === 1) step *= LINE;
    else if (e.deltaMode === 2) step *= track.clientWidth;
    if (!step) return;

    var max = track.scrollWidth - track.clientWidth;
    if (max <= 0) return;                 /* everything already fits */

    /* At an end and still pushing that way — the page's turn. The 1px
       slack is for fractional scroll positions on scaled displays,
       where scrollLeft never quite equals max and the track would
       otherwise keep the wheel forever. */
    var at = track.scrollLeft;
    if (step < 0 && at <= 0) return;
    if (step > 0 && at >= max - 1) return;

    e.preventDefault();
    /* Assigned, not scrollBy with smooth behaviour: a wheel wants to
       track the fingers 1:1. Smoothing here feels like lag. */
    track.scrollLeft = at + step;
  }, { passive: false, capture: true });

  /* ---- drag the row, as well --------------------------------------
     Belt and braces. If some other script does manage to swallow the
     wheel on this page, dragging still works, and it is the gesture
     most people try on a row of cards anyway.

     THE 6px THRESHOLD IS WHAT KEEPS CLICKS WORKING. Treating every
     mousedown as a drag would mean a card could never be clicked, and
     any link inside one would stop opening. Nothing happens until the
     pointer has actually travelled.
     ------------------------------------------------------------------ */
  var down = false, dragging = false, startX = 0, startLeft = 0;

  track.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'touch') return;   /* touch already scrolls */
    if (e.button !== 0) return;              /* left button only */
    down = true; dragging = false;
    startX = e.clientX;
    startLeft = track.scrollLeft;
  });

  track.addEventListener('pointermove', function (e) {
    if (!down) return;
    var moved = e.clientX - startX;
    if (!dragging && Math.abs(moved) < 6) return;

    if (!dragging) {
      dragging = true;
      track.classList.add('is-dragging');
      /* Capture, so a fast drag that leaves the track still steers it
         instead of stopping dead at the edge. */
      if (track.setPointerCapture) { try { track.setPointerCapture(e.pointerId); } catch (err) {} }
    }
    e.preventDefault();
    track.scrollLeft = startLeft - moved;
  });

  function release() {
    down = false;
    if (!dragging) return;
    dragging = false;
    track.classList.remove('is-dragging');
  }

  track.addEventListener('pointerup', release);
  track.addEventListener('pointercancel', release);

  /* A drag that ends over a card would otherwise fire a click on it. */
  track.addEventListener('click', function (e) {
    if (dragging) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* ---- the arrows -------------------------------------------------
     A click is a click. Nothing can passive-listener it away, nothing
     can stopPropagation it before it lands, and it is the same control
     for a mouse and for a tab key. If the wheel is being eaten by
     something else on this page, this still works.

     One card and a gap per press, capped at the track's own width so a
     wide screen showing three cards does not leap past two of them.
     ------------------------------------------------------------------ */
  var arrows = document.querySelectorAll('[data-best-nav]');

  function stride() {
    var card = track.querySelector('.best-card');
    var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    var w = card ? card.getBoundingClientRect().width + gap : track.clientWidth * 0.8;
    return Math.min(w, track.clientWidth);
  }

  function nudge(dir) {
    var to = track.scrollLeft + dir * stride();
    /* Smooth here, unlike the wheel: a press is one discrete decision,
       so it should read as one movement rather than a jump. */
    if (track.scrollTo) track.scrollTo({ left: to, behavior: 'smooth' });
    else track.scrollLeft = to;
  }

  /* Greyed out at the ends rather than hidden — a control that vanishes
     makes the row jump as the layout closes up behind it. */
  function paintArrows() {
    var max = track.scrollWidth - track.clientWidth;
    var at = track.scrollLeft;
    Array.prototype.forEach.call(arrows, function (btn) {
      var next = btn.getAttribute('data-best-nav') === 'next';
      btn.disabled = max <= 0 || (next ? at >= max - 1 : at <= 0);
    });
  }

  Array.prototype.forEach.call(arrows, function (btn) {
    btn.addEventListener('click', function () {
      nudge(btn.getAttribute('data-best-nav') === 'next' ? 1 : -1);
    });
  });

  track.addEventListener('scroll', paintArrows, { passive: true });
  window.addEventListener('resize', paintArrows);
  paintArrows();
})();
</script>

<script>
/* --------------------------------------------------------------------
   MISSING PHOTOS FALL BACK TO THE GRADIENT

   Every .photo-layer on this page has a .gradient-fill sitting behind
   it for exactly this case, and every .crack__img has its shard's navy
   backing. That only works if the <img> gets out of the way, though —
   a broken image is not an empty box, it is a broken icon with the alt
   text printed next to it, which is what shows on this page today for
   any file that has not been uploaded yet.

   BOTH CLASSES, NOT JUST ONE. The first version of this covered only
   .photo-layer, so the festival mosaic — which uses .crack__img —
   carried on showing four sets of alt text over its shards.

   THE CLASS IS ADDED ON FAILURE, NEVER BEFORE. Hiding the image up
   front and revealing it on load would blank out photos that were
   going to arrive, just slowly.

   The listener is registered in the capture phase because error does
   not bubble. Without the third argument this never fires.
   -------------------------------------------------------------------- */
(function () {
  function fallback(img) {
    if (img && img.classList) img.classList.add('is-broken');
  }

  document.addEventListener('error', function (e) {
    var el = e.target;
    if (!el || el.tagName !== 'IMG' || !el.classList) return;
    if (el.classList.contains('photo-layer') || el.classList.contains('crack__img')) fallback(el);
  }, true);

  /* Images that failed before this script ran do not fire error again.
     complete with naturalWidth 0 is how you spot one after the fact. */
  var imgs = document.querySelectorAll('img.photo-layer, img.crack__img');
  Array.prototype.forEach.call(imgs, function (img) {
    if (img.complete && img.naturalWidth === 0) fallback(img);
  });
})();
</script>

<script>
/* --------------------------------------------------------------------
   THE FIGURES COUNT UP

   Deliberately on its own attribute (data-beat-count) rather than the
   data-count the old stats band used. If base.js is running a counter
   over every [data-count] on the site, this cannot collide with it and
   no figure gets animated twice.

   Each figure runs once, the first time it comes into view, and then
   stops being observed. Counting again on every scroll past is the kind
   of motion that makes a page feel restless.

   The final value is written from the attribute, so a figure is never
   left showing 0 — if this script never runs, the markup still has to
   show something, which is why the spans start at 0 and not empty.
   -------------------------------------------------------------------- */
(function () {
  var figures = document.querySelectorAll('.beat__count');
  if (!figures.length) return;

  function settle(el) { el.textContent = el.getAttribute('data-beat-count'); }

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (still.matches || !('IntersectionObserver' in window)) {
    Array.prototype.forEach.call(figures, settle);
    return;
  }

  function run(el) {
    var target = parseInt(el.getAttribute('data-beat-count'), 10);
    if (isNaN(target)) return;
    var started = null;
    var span = 1100;

    function step(now) {
      if (started === null) started = now;
      var t = Math.min((now - started) / span, 1);
      /* ease-out: the number slows into its value instead of stopping dead */
      el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3)));
      if (t < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      run(entry.target);
      io.unobserve(entry.target);
    });
  }, { threshold: 0.6 });

  Array.prototype.forEach.call(figures, function (el) { io.observe(el); });
})();
</script>

<!-- ---------- getting here ---------- -->
<!-- ====================================================================
     GETTING HERE

     Three routes, and they are alternatives rather than steps — which
     is why this is a <ul> and the 01/02/03 are decoration marked
     aria-hidden. An <ol> would tell a screen reader to take the bus,
     then the car, then the plane.

     THE OLD .step CLASSES ARE UNTOUCHED. They are base.css components
     and other pages may use them; this section just stopped calling
     them. If nothing else on the site renders a .steps grid, those
     rules and the .step:hover rule further down about.css can go.

     WHAT IS ACTUALLY MISSING HERE IS FACTS. The route line under each
     heading is the only thing on the card that could carry an operator,
     a duration or a fare, and right now it carries a place name because
     that is all the copy below it supports. Fill those in and this
     section stops being three paragraphs of hedging.
     ==================================================================== -->
<section class="section--lg ways">
  <div class="wrap">

    <div class="ways__head">
      <div class="ways__heading">
        <span class="eyebrow eyebrow--muted" data-aos="fade-up">Getting here</span>
        <h2 class="font-display ways__title" data-aos="fade-up">Three ways in</h2>
      </div>
      <p class="ways__lead" data-aos="fade-up" data-aos-delay="70">Everything arrives at Daet, and the province is small enough that whichever way you come in, nothing else is far.</p>
    </div>

    <ul class="ways__grid">

      <li class="way" data-aos="fade-up">
        <span class="way__index" aria-hidden="true">01</span>
        <span class="way__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="13" rx="2"/><path d="M3 10h18"/>
            <path d="M7 17v2"/><path d="M17 17v2"/>
            <circle cx="7.5" cy="13.5" r="1"/><circle cx="16.5" cy="13.5" r="1"/>
          </svg>
        </span>
        <h3 class="way__label">By bus</h3>
        <p class="way__route">Manila &rarr; Daet</p>
        <p class="way__body">Direct services run from Manila to Daet overnight and through the day. Add the operators you want to recommend, the terminals they leave from, and the current travel time.</p>
      </li>

      <li class="way" data-aos="fade-up" data-aos-delay="70">
        <span class="way__index" aria-hidden="true">02</span>
        <span class="way__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 16v-3l2-5a2 2 0 0 1 1.9-1.4h10.2A2 2 0 0 1 19 8l2 5v3z"/>
            <path d="M4 16v2"/><path d="M20 16v2"/>
            <circle cx="7.5" cy="16" r="1.1"/><circle cx="16.5" cy="16" r="1.1"/>
          </svg>
        </span>
        <h3 class="way__label">By car</h3>
        <p class="way__route">Maharlika Highway</p>
        <p class="way__body">Via the Maharlika Highway through Quezon. Note the route you would actually advise, and where it is worth stopping.</p>
      </li>

      <li class="way" data-aos="fade-up" data-aos-delay="140">
        <span class="way__index" aria-hidden="true">03</span>
        <span class="way__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 3 11 14"/><path d="M22 3l-7 18-4-8-8-4z"/>
          </svg>
        </span>
        <h3 class="way__label">By air</h3>
        <p class="way__route">Bagasbas, or Naga</p>
        <p class="way__body">Check the current status of Bagasbas Airport before listing it &mdash; schedules here change. Naga is the nearest alternative if nothing is flying.</p>
      </li>

    </ul>

    <p class="note note--light">Everything in this section is a placeholder. Fares, durations and schedules go out of date faster than anything else on a tourism site, so pull them from the operators directly and put a &ldquo;last checked&rdquo; date on the page.</p>
  </div>
</section>

<!-- ---------- onward ---------- -->
<!-- ====================================================================
     THE END OF THE PAGE

     A centred pill button was doing this job before. The problem with a
     button here is that it is the smallest thing on a page of very
     large things, so the page ends on a whisper — and it says nothing
     about what is on the other side of it beyond a count.

     THE HEADLINE IS THE LINK. Not a heading with a button under it: the
     whole line is one anchor, so the click target is the size of the
     thing you are reading rather than a pill you have to aim at. It is
     also why there is no <h2> here — this is navigation, not a section
     of the document, and a heading would put "All 24 destinations" in
     a screen reader's heading list as though it were content.

     The back link goes to #top, which is the id on the banner at the
     head of this file. Keep them together: delete one and the other
     becomes a link to nowhere.
     ==================================================================== -->
<section class="section--lg onward">
  <div class="wrap onward__inner">

    <div class="onward__main">
      <span class="eyebrow eyebrow--muted" data-aos="fade-up">Continue reading</span>

      <a class="onward__link" href="destinations.php" data-aos="fade-up" data-aos-delay="70">
        <span class="font-display onward__title">All 24 destinations</span>
        <svg class="onward__arrow" viewBox="0 0 68 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M2 12h62"/><path d="M54 3l10 9-10 9"/>
        </svg>
      </a>
    </div>

    <a class="onward__back" href="#top" data-aos="fade-up" data-aos-delay="140">
      <span class="onward__back-arrow" aria-hidden="true">&larr;</span> Back to the top
    </a>

  </div>
</section>
<?php require __DIR__ . '/includes/bud-widget.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>