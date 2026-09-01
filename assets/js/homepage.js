document.addEventListener('DOMContentLoaded', function(){

  gsap.registerPlugin(ScrollTrigger);

  /* AOS is switched off on purpose. Everything it did — and a good
     deal more — is handled by the SCROLL MOTION block further down,
     which varies the animation by what the element actually is instead
     of fading everything upward the same way.

     The data-aos attributes are left in the HTML: this code reads them
     to know what to animate and how long to wait. */
  if (window.AOS) { AOS.init({ disable: true }); }

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var nav = document.getElementById('mainNav');
  /* Scroll events fire far more often than the screen refreshes, and
     touching classList inside one forces a style recalculation. Coalesce
     to one check per frame, and only write when the state actually
     flips. */
  var navTicking = false, navScrolled = false;
  window.addEventListener('scroll', function(){
    if (navTicking) return;
    navTicking = true;
    requestAnimationFrame(function(){
      navTicking = false;
      var want = window.scrollY > 20;
      if (want === navScrolled) return;
      navScrolled = want;
      nav.classList.toggle('scrolled', want);
    });
  }, { passive: true });

  /* Underline the nav link for whichever section is in view.

     Both thresholds sit at the same line (45% down the viewport), so a
     section takes over exactly when the previous one gives it up. With
     mismatched values a short section can fail to claim the highlight
     at all and the underline appears to skip over it. */
  var navLinks = document.querySelectorAll('.nav__links a[href^="#"]');
  navLinks.forEach(function(link){
    var id = link.getAttribute('href').slice(1);
    var target = id ? document.getElementById(id) : null;
    if (!target) return;
    ScrollTrigger.create({
      trigger: target,
      start: 'top 45%',
      end: 'bottom 45%',
      onEnter: function(){ setActiveLink(link); },
      onEnterBack: function(){ setActiveLink(link); }
    });
  });
  function setActiveLink(activeLink){
    navLinks.forEach(function(l){ l.classList.remove('is-active'); });
    activeLink.classList.add('is-active');
  }

  // --- hero: only on pages that have one ---
  if (document.getElementById('heroContent')) {
    gsap.to('#heroContent', { opacity: 1, y: 0, duration: 1.2, ease: 'power3.out', delay: .2 });

  // slow zoom on the hero as you scroll away from it.
  // this works on the drone video too — it scales #heroBg, which is the
  // wrapper around whichever one you're using, image or video.
    gsap.timeline({
      scrollTrigger: { trigger: '#hero', start: 'top top', end: 'bottom top', scrub: true }
    })
      /* Scaling a wrapper that contains a <video> forces the browser to
       recomposite every decoded frame at a new size, which is a real
       cost on a laptop. Pull the zoom back when a video is in there. */
    .to('#heroBg', {
      scale: document.querySelector('#heroBg video') ? 1.06 : 1.15,
      ease: 'none'
    }, 0)
    .to('#heroContent', { opacity: 0, y: -60, ease: 'none' }, 0);
  }

  // continuous, subtle zoom on the destination background as you scroll through it —
  // layered on top of (not instead of) the fast crossfade that happens on click
  if (document.getElementById('destBgWrap')) {
    gsap.to('#destBgWrap', {
      scale: 1.05,   /* was 1.12 — a big upscale visibly softens the photo */
      ease: 'none',
      scrollTrigger: { trigger: '#destinations', start: 'top bottom', end: 'bottom top', scrub: true }
    });
  }

  gsap.to('#horizonPath', {
    strokeDashoffset: 0,
    ease: 'none',
    scrollTrigger: { trigger: document.body, start: 'top top', end: 'bottom bottom', scrub: true }
  });

  document.querySelectorAll('[data-count]').forEach(function(el){
    var target = +el.getAttribute('data-count');
    ScrollTrigger.create({
      trigger: el,
      start: 'top 85%',
      once: true,
      onEnter: function(){
        gsap.to(el, {
          textContent: target,
          duration: 1.6,
          ease: 'power2.out',
          snap: { textContent: 1 },
          onUpdate: function(){ el.textContent = Math.floor(el.textContent); }
        });
      }
    });
  });

  /* ===================================================================
     DESTINATION SPOTLIGHT + CAROUSEL

     There is NO list of destinations in this file. Everything — the
     photos, the names, the descriptions — lives in homepage.php.

       the images  -> <img class="spot-bg"> inside #destBgWrap
       the text    -> <article class="spot-item"> inside #spotContent

     They are paired by position, so the 3rd image belongs to the 3rd
     card. This code just moves two classes around: .spot-bg--front on
     the image, and .is-active on the card. Thumbnails are built by
     reading the src, the pill, and the heading off those elements.

     Add a destination in the HTML and it appears here automatically.
     =================================================================== */
  var carouselReady = document.getElementById('destBgWrap') && document.getElementById('carouselTrack');
  if (carouselReady) {

  var bgLayers    = document.querySelectorAll('#destBgWrap .spot-bg');
  var items       = document.querySelectorAll('#spotContent .spot-item');
  var total       = items.length;
  var track       = document.getElementById('carouselTrack');
  var dotsWrap    = document.getElementById('carouselDots');
  var spotContent = document.getElementById('spotContent');

  /* ------------------------------------------------------------------
     WHY THE OLD VERSION STUTTERED, AND WHAT CHANGED

     1. IT FOUGHT THE BROWSER FOR THE SCROLL. The strip used native
        scroll-behavior:smooth via scrollTo(), then a setTimeout fired
        620ms later to silently jump the strip back by one copy. If the
        native scroll had not finished — and its duration is decided by
        the browser, not by us — the jump landed mid-glide and showed as
        the hitch you get at the end of the row. The scroll is now driven
        by requestAnimationFrame with one fixed easing curve, so EVERY
        move takes exactly SCROLL_MS and the reposition happens on the
        frame the motion actually ends. Not on a guess.

     2. THE TARGET MOVED WHILE IT TRAVELLED. The active tile used to grow
        from 212px to 256px over 0.4s. Every tile after it therefore slid
        right while the strip was still scrolling toward one of them, so
        the destination was never where the code aimed. Tiles are now all
        the same size and the active one is emphasised with transform:
        scale() instead — a transform costs no layout, so the geometry
        the scroll is aiming at holds still.

     3. TWO COPIES WERE NOT ENOUGH RUNWAY. With the strip rendered twice,
        reaching the far end left nothing to scroll into and the position
        had to be clamped. Three copies mean the active tile is always
        parked in the middle one, with a full copy spare on each side, so
        the wrap has room to happen invisibly in either direction.

     4. THE STRIP MOVED 260ms AFTER EVERYTHING ELSE. syncStrip() was
        called inside the text-swap timer, so photo, text and thumbnails
        started at three different moments. They now start together.
     ------------------------------------------------------------------ */

  var COPIES       = 3;
  var activeIndex  = 0;
  var pendingIndex = 0;
  var slot         = total;   /* position in the tripled strip; start mid */
  var swapTimer    = null;
  var hopTimer     = null;
  var autoTimer    = null;
  var autoPaused   = false;

  /* --- HOW MANY PHOTOS ARE ALLOWED TO EXIST AT ONCE ------------------
     There are 24 full-screen background photographs in this section and
     72 tiles in the strip. Left alone the browser eventually holds a
     decoded bitmap for every one of them, and a 1600x900 JPEG costs
     about 5.7MB decoded regardless of how small it is drawn — roughly
     140MB for the set. That is the memory pressure behind the frame
     drops, and no CSS property can relieve it.

     Both are now windowed around wherever you are. Anything outside the
     window has its src removed, which releases the bitmap; stepping back
     toward it reloads from cache. The windows are deliberately wider
     than the visible area so the next photo is always already decoded
     before it is needed. */
  var BG_WINDOW    = 2;   /* backgrounds kept live either side of active */
  var THUMB_WINDOW = 5;   /* destinations whose tiles keep their image */

  var AUTO_MS   = 6500;   /* how long each destination holds */
  var SCROLL_MS = 620;    /* one duration for every strip movement */
  var SWAP_MS   = 250;    /* text out -> card changes -> text back in */

  var stillPlease = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  if (!total) { track.innerHTML = ''; } else {

  // pulls the label straight off the card so nothing is duplicated
  function labelOf(i){
    var card = items[i];
    if (!card) return { tag: '', name: '' };
    return {
      tag:  card.querySelector('.pill') ? card.querySelector('.pill').textContent : '',
      name: card.querySelector('h3')    ? card.querySelector('h3').textContent    : ''
    };
  }

  /* ---------- the strip, rendered three times ---------- */
  function renderThumbs(){
    var html = '';
    for (var c = 0; c < COPIES; c++){
      for (var i = 0; i < total; i++){
        var L = labelOf(i);
        /* bgLayers[i] may be missing if the number of <img class="spot-bg">
           and <article class="spot-item"> ever fall out of step. Reading it
           defensively keeps the whole strip from failing to render. */
        var layer = bgLayers[i];
        /* The strip draws these at about 214px but they are the same
           1600px+ files as the full-screen backgrounds, so the browser
           decodes each one at full size and downscales it on every
           raster. That is the largest remaining cost in this section.
           An optional data-thumb on the background image lets you point
           the strip at a small export without touching this file; it
           falls back to src, so nothing breaks if it is absent. */
        /* data-src FIRST. The init block below hands every background's
           URL over to data-src and strips the src attribute, and it runs
           before this does — so reading src alone finds an empty string
           on 23 of the 24 layers and the whole strip renders blank. */
        var src   = layer ? (layer.getAttribute('data-thumb') ||
                             layer.getAttribute('data-src')  ||
                             layer.getAttribute('src') || '') : '';
        var pos   = c * total + i;
        html += '<button type="button" class="thumb' + (pos === slot ? ' thumb--active' : '') +
                '" data-index="' + i + '" data-slot="' + pos + '"' +
                (c === 1 ? '' : ' tabindex="-1" aria-hidden="true"') +
                ' aria-label="' + L.name + '">' +
                '<img class="thumb__visual" data-src="' + src + '" alt="" decoding="async">' +
                '<span class="thumb__sheen" aria-hidden="true"></span>' +
                '<span class="thumb__no" aria-hidden="true">' + (i + 1 < 10 ? '0' : '') + (i + 1) + '</span>' +
                '<div class="thumb__label"><span class="pill pill--outline">' + L.tag + '</span>' +
                '<strong>' + L.name + '</strong></div>' +
                '</button>';
      }
    }
    track.innerHTML = html;

    /* a photo that 404s used to leave a broken-image glyph sitting in the
       corner of the tile. Marking the tile instead lets the CSS gradient
       stand in for it, so a missing file looks deliberate.

       The guard matters now that tiles are deliberately unloaded: an
       image with no src is not a broken image, it is one we released. */
    Array.prototype.forEach.call(track.querySelectorAll('.thumb__visual'), function(img){
      img.addEventListener('error', function(){
        if (img.getAttribute('src') && img.parentNode) {
          img.parentNode.classList.add('thumb--noimg');
        }
      });
    });
  }

  function renderDots(){
    var html = '';
    for (var i = 0; i < total; i++){
      html += '<button type="button" class="dot' + (i === activeIndex ? ' active' : '') +
              '" data-index="' + i + '" aria-label="Go to ' + labelOf(i).name + '"></button>';
    }
    dotsWrap.innerHTML = html;
  }

  /* ---------- one easing curve for every movement ---------- */
  var rafId = null;

  function stopScroll(){
    if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
  }

  function ease(t){
    /* easeInOutCubic — starts and finishes at zero velocity, which is the
       whole reason the wrap is invisible: the strip is barely moving at
       the moment the copy swap happens. */
    return t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
  }

  function scrollTrackTo(left, ms, done){
    stopScroll();
    var max  = track.scrollWidth - track.clientWidth;
    var to   = Math.max(0, Math.min(left, max));
    var from = track.scrollLeft;
    var d    = to - from;

    if (!ms || stillPlease || Math.abs(d) < 1){
      track.scrollLeft = to;
      if (done) done();
      return;
    }

    var t0 = null;
    rafId = requestAnimationFrame(function step(now){
      if (t0 === null) t0 = now;
      var p = Math.min(1, (now - t0) / ms);
      track.scrollLeft = from + d * ease(p);
      if (p < 1) { rafId = requestAnimationFrame(step); }
      else { rafId = null; track.scrollLeft = to; if (done) done(); }
    });
  }

  /* ---------- only nearby photographs are allowed to exist ----------
     Distance is measured in DESTINATION index, not tile position, so all
     three copies of a given destination load and release together. If it
     were measured by tile position instead, the copy swap at the end of
     the row would land on tiles that had never loaded and the strip
     would flash empty at exactly the moment it is meant to be seamless.

     Circular distance, so tile 23 counts as adjacent to tile 0. */
  function circularGap(a, b){
    var d = Math.abs(a - b);
    return Math.min(d, total - d);
  }

  function windowThumbs(){
    var here = ((slot % total) + total) % total;
    var kids = track.children;
    for (var n = 0; n < kids.length; n++){
      var tile = kids[n];
      var img  = tile.querySelector('.thumb__visual');
      if (!img) continue;
      var want = circularGap(n % total, here) <= THUMB_WINDOW;
      var url  = img.getAttribute('data-src') || '';
      var cur  = img.getAttribute('src') || '';
      if (want && url && cur !== url) {
        tile.classList.remove('thumb--noimg');
        img.setAttribute('src', url);
      } else if (!want && cur) {
        img.removeAttribute('src');
      }
    }
  }

  function windowBackgrounds(){
    bgLayers.forEach(function(img, n){
      var url = img.getAttribute('data-src');
      if (!url) return;
      /* whatever is on screen is never released, however far the window
         has moved on — that would blank the section */
      var want = circularGap(n, pendingIndex) <= BG_WINDOW ||
                 img.classList.contains('spot-bg--front');
      var cur  = img.getAttribute('src') || '';
      if (want && cur !== url) {
        img.classList.remove('spot-bg--noimg');
        img.setAttribute('src', url);
      } else if (!want && cur) {
        img.removeAttribute('src');
      }
    });
  }

  /* ---------- the invisible loop ---------- */
  function copyWidth(){
    var kids = track.children;
    if (!kids[total] || !kids[0]) return 0;
    return kids[total].offsetLeft - kids[0].offsetLeft;
  }

  /* Moves the whole strip by n copies with nothing visibly happening.
     Copy 1, 2 and 3 are pixel-identical, so adding a copy's width to
     scrollLeft while adding `total` to `slot` leaves the screen exactly
     as it was. This is the entire trick behind the seamless wrap. */
  function shiftCopies(n){
    var w = copyWidth();
    if (!w) return;
    slot += n * total;
    stopScroll();
    track.scrollLeft += n * w;
  }

  /* called the instant a movement finishes, never on a timer */
  function normalise(){
    if (slot >= total * 2)  shiftCopies(-1);
    else if (slot < total)  shiftCopies(1);
    markActive();
    /* released once the movement has finished, so nothing is torn out
       from under a frame that is still being drawn */
    windowThumbs();
    windowBackgrounds();
  }

  /* keeps a requested slot inside the tripled strip, shifting first if
     the step would fall off either end */
  function resolveSlot(want){
    var guard = 0;
    while (want < 0 && guard++ < COPIES)               { shiftCopies(1);  want += total; }
    guard = 0;
    while (want >= COPIES * total && guard++ < COPIES) { shiftCopies(-1); want -= total; }
    return Math.max(0, Math.min(want, COPIES * total - 1));
  }

  /* --- the position readout ------------------------------------------
     24 dots say "there are a lot of these" and nothing else. A count
     says exactly where you are, and gives the column a heading. */
  var counterNow = null, counterBar = null;

  function buildCounter(){
    var col = document.querySelector('.dest-spotlight__carousel');
    var vp  = document.querySelector('.carousel-viewport');
    if (!col || !vp || document.querySelector('.spot-count')) return;
    var head = document.createElement('div');
    head.className = 'spot-count';
    head.innerHTML =
      '<span class="spot-count__label">Explore the province</span>' +
      '<span class="spot-count__nums"><b class="spot-count__now">01</b>' +
      '<i class="spot-count__rule"><em></em></i>' +
      '<span class="spot-count__all">' + (total < 10 ? '0' : '') + total + '</span></span>';
    col.insertBefore(head, vp);
    counterNow = head.querySelector('.spot-count__now');
    counterBar = head.querySelector('.spot-count__rule em');
  }

  function updateCounter(){
    if (!counterNow) return;
    var n = pendingIndex + 1;
    counterNow.textContent = (n < 10 ? '0' : '') + n;
    if (counterBar) counterBar.style.transform = 'scaleX(' + (n / total).toFixed(4) + ')';
  }

  function markActive(){
    var kids = track.children;
    for (var n = 0; n < kids.length; n++){
      kids[n].classList.toggle('thumb--active', n === slot);
    }
    var dots = dotsWrap.children;
    for (var d = 0; d < dots.length; d++){
      dots[d].classList.toggle('active', d === pendingIndex);
    }
  }

  /* THE ACTIVE TILE NOW SITS AT THE LEFT EDGE.

     It used to scroll to the tile BEFORE the active one so the previous
     destination stayed on screen. That is why a card you had already
     seen was always parked on the left. The active photo now leads the
     row and everything still to come runs off to the right, which is
     also the direction the carousel travels. */
  var HOP_TILES = 4;   /* further than this and we cut rather than scroll */

  function syncStrip(instant){
    markActive();
    var el = track.children[slot];
    if (!el) return;

    var padL = parseFloat(getComputedStyle(track).paddingLeft) || 0;
    var to   = el.offsetLeft - padL;

    if (instant) { scrollTrackTo(to, 0, normalise); return; }

    var stride = (copyWidth() / total) || 1;
    var far    = Math.abs(to - track.scrollLeft) > stride * HOP_TILES;

    if (far) {
      /* A dot can be twenty tiles away. Scrolling that whole distance
         would streak the strip past tiles whose photos have deliberately
         been released, so they would flick past as empty boxes — and it
         is a long, unfocused movement even when they are all loaded.

         Cutting under a short fade is both cheaper and how a jump of
         this size is normally handled. Steps of four tiles or fewer,
         which is everything the arrows and autoplay ever do, still get
         the full scroll. */
      stopScroll();
      clearTimeout(hopTimer);
      track.classList.add('is-hopping');
      hopTimer = setTimeout(function(){
        var max = track.scrollWidth - track.clientWidth;
        track.scrollLeft = Math.max(0, Math.min(to, max));
        normalise();
        requestAnimationFrame(function(){ track.classList.remove('is-hopping'); });
      }, 165);
      return;
    }

    scrollTrackTo(to, SCROLL_MS, normalise);
  }

  function setActive(i, wantSlot){
    var next = ((i % total) + total) % total;

    var target;
    if (typeof wantSlot === 'number') {
      /* an arrow, a key or autoplay: keep travelling the way it asked,
         so direction never reverses mid-sequence */
      target = resolveSlot(wantSlot);
    } else {
      /* a dot or a thumbnail: take whichever of the three copies is
         nearest, so a jump never scrolls further than it has to */
      var best = slot, bestD = Infinity;
      for (var c = 0; c < COPIES; c++){
        var cand = next + c * total;
        var dist = Math.abs(cand - slot);
        if (dist < bestD) { bestD = dist; best = cand; }
      }
      target = best;
    }

    if (next === pendingIndex && target === slot) return;

    pendingIndex = next;
    slot = target;

    clearTimeout(swapTimer);

    /* every switch restarts the clock, so a destination the user chose
       gets its full turn rather than the remainder of the last one */
    restartAuto();

    /* the incoming photo has to be decoded BEFORE it is faded to the
       front, or the crossfade starts on an empty layer */
    windowBackgrounds();

    // the matching background photo crossfades to the front
    bgLayers.forEach(function(layer, n){
      if (layer) layer.classList.toggle('spot-bg--front', n === next);
    });

    updateCounter();

    /* photo, text and strip all start on the same frame — the strip used
       to wait for the text swap, which is what made the three parts of
       the transition look like three separate events */
    syncStrip();

    spotContent.classList.add('is-switching');
    swapTimer = setTimeout(function(){
      activeIndex = next;
      items.forEach(function(card, n){
        card.classList.toggle('is-active', n === activeIndex);
      });
      spotContent.classList.remove('is-switching');
    }, SWAP_MS);
  }

  /* Thumbnails and dots are bound by DELEGATION on their containers, so
     the handlers survive any re-render of the buttons inside. */
  track.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.thumb') : null;
    if (!btn) return;
    /* the tile actually clicked, not its twin in another copy */
    setActive(+btn.getAttribute('data-index'), +btn.getAttribute('data-slot'));
  });

  dotsWrap.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.dot') : null;
    if (!btn) return;
    setActive(+btn.getAttribute('data-index'));
  });

  /* ===================================================================
     AUTOPLAY

     Four things pause it, because an autoplaying carousel that ignores
     the user is worse than no autoplay at all:

       - a finger touching the carousel, which holds it still for 8s
         after the finger lifts
       - the pointer resting anywhere on the carousel column, so a
         thumbnail cannot slide out from under the cursor mid-click
       - keyboard focus inside it, for anyone tabbing through
       - the section being scrolled off screen, or the tab being hidden
     =================================================================== */

  function advance(){
    if (autoPaused || document.hidden) return;
    setActive(pendingIndex + 1, slot + 1);
  }

  function restartAuto(){
    clearInterval(autoTimer);
    if (stillPlease) return;   /* reduced motion: never start the clock */
    autoTimer = setInterval(advance, AUTO_MS);
  }

  function stopAuto(){ clearInterval(autoTimer); autoTimer = null; }

  var resumeTimer = null;
  var RESUME_MS   = 8000;

  function pauseAuto(){
    clearTimeout(resumeTimer);
    autoPaused = true;
  }

  function resumeAuto(delay){
    clearTimeout(resumeTimer);
    resumeTimer = setTimeout(function(){ autoPaused = false; }, delay || 0);
  }

  var carouselCol = document.querySelector('.dest-spotlight__carousel');
  if (carouselCol) {
    carouselCol.addEventListener('mouseenter', function(){ pauseAuto(); });
    carouselCol.addEventListener('mouseleave', function(){ resumeAuto(0); });
    carouselCol.addEventListener('focusin',  function(){ pauseAuto(); });
    carouselCol.addEventListener('focusout', function(){ resumeAuto(0); });
    carouselCol.addEventListener('touchstart',  function(){ pauseAuto(); }, { passive: true });
    carouselCol.addEventListener('touchend',    function(){ resumeAuto(RESUME_MS); }, { passive: true });
    carouselCol.addEventListener('touchcancel', function(){ resumeAuto(RESUME_MS); }, { passive: true });
  }

  /* a hand on the strip wins over the animation every time */
  track.addEventListener('pointerdown', function(){ stopScroll(); });
  track.addEventListener('wheel', function(){ stopScroll(); }, { passive: true });

  document.addEventListener('visibilitychange', function(){
    /* a backgrounded tab throttles rAF, so an in-flight scroll would
       otherwise sit half-finished until the tab came back */
    if (document.hidden) { stopAuto(); stopScroll(); } else { restartAuto(); }
  });

  if (window.IntersectionObserver) {
    var sec = document.getElementById('destinations');
    if (sec) {
      new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if (entry.isIntersecting) { restartAuto(); } else { stopAuto(); }
        });
      }, { threshold: 0.15 }).observe(sec);
    }
  }

  /* --- Previous / Next -----------------------------------------------
     Both null-checked: one getElementById returning null used to throw
     before anything rendered and left the whole column blank.

     They step from pendingIndex rather than activeIndex, so two quick
     presses move two destinations. */
  var prevBtn = document.getElementById('prevBtn');
  var nextBtn = document.getElementById('nextBtn');

  if (prevBtn) prevBtn.addEventListener('click', function(){ setActive(pendingIndex - 1, slot - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function(){ setActive(pendingIndex + 1, slot + 1); });

  /* --- HIT-TEST FALLBACK ---------------------------------------------
     Previous has been unclickable twice, in two different positions,
     because something transparent sat over it. This listens on the
     document and checks whether the click landed inside either arrow's
     rectangle — true whether or not the arrow received the event. */
  document.addEventListener('click', function(e){
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('.carousel-nav')) return;   /* the button got it */
    if (t.closest('.thumb') || t.closest('.dot')) return;

    function inside(btn){
      if (!btn || !btn.offsetParent) return false;
      var r = btn.getBoundingClientRect();
      return e.clientX >= r.left - 10 && e.clientX <= r.right + 10 &&
             e.clientY >= r.top  - 10 && e.clientY <= r.bottom + 10;
    }

    if (inside(prevBtn))      { setActive(pendingIndex - 1, slot - 1); }
    else if (inside(nextBtn)) { setActive(pendingIndex + 1, slot + 1); }
  });

  [prevBtn, nextBtn].forEach(function(btn){
    if (!btn) return;
    btn.addEventListener('touchend', function(){ resumeAuto(RESUME_MS); }, { passive: true });
  });

  /* Left and Right still step through the spotlight while it is on
     screen and the user is not typing. */
  document.addEventListener('keydown', function(e){
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    var t = e.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return;
    var box = document.getElementById('destinations');
    if (!box) return;
    var r = box.getBoundingClientRect();
    if (r.bottom < 120 || r.top > window.innerHeight - 120) return;
    var d = (e.key === 'ArrowLeft' ? -1 : 1);
    setActive(pendingIndex + d, slot + d);
  });

  /* tile widths are fixed in CSS but the breakpoints change them, so
     re-seat the strip after a resize instead of leaving it half a tile
     out of alignment */
  var resizeTimer = null;
  window.addEventListener('resize', function(){
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function(){ syncStrip(true); }, 140);
  });

  /* Hand every background's URL over to data-src so the windowing code
     owns loading from here on. The first one keeps its src: it is
     already in flight from the HTML and cancelling it would only make
     the section slower to appear. */
  bgLayers.forEach(function(img, n){
    var url = img.getAttribute('src');
    if (url) img.setAttribute('data-src', url);
    if (n !== 0 && url) img.removeAttribute('src');
    img.addEventListener('error', function(){
      if (img.getAttribute('src')) img.classList.add('spot-bg--noimg');
    });
  });

  renderThumbs();
  renderDots();
  buildCounter();
  updateCounter();
  windowThumbs();
  windowBackgrounds();
  /* seat the strip on the next frame, once the browser has laid the
     tiles out and offsetLeft means something */
  requestAnimationFrame(function(){ syncStrip(true); });
  restartAuto();

  } // end total guard
  } // end carousel guard

  /* ===================================================================
     THE THREE EXPERIENCE CARDS

     Pointer-reactive tilt plus a coloured light that tracks the cursor.
     The heavy lifting is all in CSS section 35 — this only writes four
     custom properties and lets the stylesheet decide what they mean,
     which keeps the look editable without touching JavaScript.

     Devices without a pointer get .is-near instead, applied to whichever
     card is currently centred in the viewport, so the section responds
     to scrolling rather than sitting dead on a phone.
     =================================================================== */
  (function(){
    var expCards = document.querySelectorAll('.exp-card');
    if (!expCards.length) return;

    var mq       = window.matchMedia;
    var expStill = !!(mq && mq('(prefers-reduced-motion: reduce)').matches);
    var canHover = !!(mq && mq('(hover: hover)').matches);

    if (canHover && !expStill) {
      expCards.forEach(function(card){
        /* A trackpad can deliver well over 100 pointermove events a
           second. Two of these custom properties drive a radial-gradient
           position, which is a PAINT, so the old handler was repainting
           the card several times per frame and throwing most of it away.
           Store the sample, write once per frame. */
        var pending = null, queued = false;
        card.addEventListener('pointermove', function(e){
          var r = card.getBoundingClientRect();
          if (!r.width || !r.height) return;
          pending = {
            px: (e.clientX - r.left) / r.width,   /* 0 at left, 1 at right */
            py: (e.clientY - r.top)  / r.height
          };
          if (queued) return;
          queued = true;
          requestAnimationFrame(function(){
            queued = false;
            if (!pending) return;
            var px = pending.px, py = pending.py;
            card.style.setProperty('--mx', (px * 100).toFixed(1) + '%');
            card.style.setProperty('--my', (py * 100).toFixed(1) + '%');
            /* 7deg is deliberately shallow. Anything more and the text at
               the base starts to look skewed rather than tilted. */
            card.style.setProperty('--ry', ((px - 0.5) * 7).toFixed(2) + 'deg');
            card.style.setProperty('--rx', ((0.5 - py) * 7).toFixed(2) + 'deg');
          });
        }, { passive: true });

        card.addEventListener('pointerleave', function(){
          card.style.setProperty('--rx', '0deg');
          card.style.setProperty('--ry', '0deg');
        });
      });
    }

    if (!canHover && window.IntersectionObserver) {
      /* the negative margins shrink the observed band to the middle
         third of the screen, so only the card you are actually looking
         at lights up — not every card that happens to be on screen */
      var expObs = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          entry.target.classList.toggle('is-near', entry.isIntersecting);
        });
      }, { rootMargin: '-35% 0px -35% 0px' });

      expCards.forEach(function(card){ expObs.observe(card); });
    }
  })();

  /* ===================================================================
     HOMEPAGE MOTION

     Every block checks its element exists first, so this same file
     keeps working on about.php, destinations.php and gallery.php.
     Nothing here touches the photo cards or the spotlight.
     =================================================================== */

  /* --- background video: keep it cheap ------------------------------

     A looping video decodes every frame whether or not anyone can see
     it, which is what spins up a laptop fan. Four things happen here:

       1. it does not play at all on phones, on a metered connection,
          or if the visitor asked their OS to reduce motion — the
          poster image shows instead
       2. it pauses the moment it scrolls out of view
       3. it pauses when the tab is hidden (browsers do not reliably
          do this on their own for muted video)
       4. playback rate drops slightly, which cuts decode work and is
          imperceptible on a slow landscape shot

     The single biggest saving is not here though — it is re-encoding
     the file. See the note above the <video> tag in homepage.php.
     ------------------------------------------------------------------ */
  var bgVideos = document.querySelectorAll('video.photo-layer');

  if (bgVideos.length) {
    var conn      = navigator.connection || {};
    var saveData  = conn.saveData === true;
    var slowLink  = /2g|slow-2g/.test(conn.effectiveType || '');
    var lessMotion  = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* smallScreen used to be part of this too, blanket-skipping the
       video on anything under 820px — every phone, full stop, no
       matter how good the connection. That is why it only ever showed
       a frozen first frame on mobile. Phones are just as capable of
       playing a small, well-encoded muted loop as a laptop is; what
       actually costs someone money or battery is a slow connection or
       a data-saver setting, which are still respected below, and
       reduced-motion, which is an accessibility preference and stays
       untouched. Screen size alone is no longer one of the reasons to
       skip it.

       fewCores used to be part of this too. navigator.hardwareConcurrency
       reports 2 on plenty of ordinary laptops, so it was disabling the
       video on machines that handle it perfectly well. Dropped. */
    var skipVideo = saveData || slowLink || lessMotion;

    bgVideos.forEach(function (v) {
      if (skipVideo) {
        /* Pause it, but DO NOT remove the src.

           The old version stripped the source so the file would never
           download, relying on poster= to show a still instead. That
           only works if the poster file exists — and when it does not,
           you get a blank rectangle with no video and no image, which
           is exactly what happened here: poster points at hero.jpg,
           which is not in uploads/.

           Keeping the src means the first frame still renders even
           when playback is suppressed. preload="metadata" on the tag
           means only the header is fetched, so this costs almost
           nothing. */
        v.removeAttribute('autoplay');
        v.pause();
        return;
      }
      v.playbackRate = 0.85;
    });

    if (!skipVideo && 'IntersectionObserver' in window) {
      var safePlay = function (v) {
        var p = v.play();
        if (p && p.catch) { p.catch(function () {}); }
      };

      var vidObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { safePlay(entry.target); }
          else { entry.target.pause(); }
        });
      }, { threshold: 0.1 });

      bgVideos.forEach(function (v) { vidObserver.observe(v); });

      document.addEventListener('visibilitychange', function () {
        bgVideos.forEach(function (v) {
          if (document.hidden) { v.pause(); return; }
          var r = v.getBoundingClientRect();
          if (r.bottom > 0 && r.top < window.innerHeight) { safePlay(v); }
        });
      });
    }
  }

  /* ===================================================================
     SCROLL MOTION

     Each kind of element gets motion that suits it rather than one
     fade for everything:

       headings      lift and unblur
       body text     drifts up, slightly later
       card grids    stagger in one after another, not all at once
       tall cards    rise further, with a small scale
       photos        drift slowly inside their frame as you scroll
                     (parallax, tied to scroll position rather than a
                     timer, so it tracks how fast you move)
       collage art   the squircle blocks counter-rotate a touch
       feature rows  slide in from the left in sequence

     Everything is `once` except the parallax, so nothing re-triggers
     and jitters if you scroll back and forth.
     =================================================================== */

  if (!reduceMotion) {

    /* Animate a group of elements with a stagger, once, as their
       container scrolls in. Takes the container and the child selector
       separately so there is no string-splitting to get wrong. */
    function reveal(containerSel, childSel, vars, stagger) {
      gsap.utils.toArray(containerSel).forEach(function (container) {
        var group = gsap.utils.toArray(container.querySelectorAll(childSel));
        if (!group.length) return;
        gsap.from(group, Object.assign({
          scrollTrigger: { trigger: container, start: 'top 82%', once: true },
          stagger: stagger || 0.09,
          ease: 'power3.out'
        }, vars));
      });
    }

    // --- headings: lift and sharpen ---
    gsap.utils.toArray('.head-mix, .why-visit__head h2, .voices__title, .exp-heading, .notes h2')
      .forEach(function (el) {
        gsap.from(el, {
          y: 34, opacity: 0, duration: 1,   /* blur(6px) removed: repaints every frame */
          ease: 'power3.out',
          scrollTrigger: { trigger: el, start: 'top 86%', once: true }
        });
      });

    // --- supporting copy: a beat behind the heading ---
    gsap.utils.toArray('.head-sub, .story__body p, .craft__lead, .voices__sub, .why-visit__head p')
      .forEach(function (el) {
        gsap.from(el, {
          y: 22, opacity: 0, duration: .85, delay: .08,
          ease: 'power3.out',
          scrollTrigger: { trigger: el, start: 'top 88%', once: true }
        });
      });

    /* --- card grids: pieces come in from alternating sides and
       assemble into the row.

       Odd items enter from the left, even from the right, each one
       slightly rotated and scaled down so it reads as a piece dropping
       into place rather than a block fading in. The stagger means they
       land in sequence, left to right. --- */
    function assemble(containerSel, childSel, opts) {
      opts = opts || {};
      var dist  = opts.distance || 90;
      var tilt  = opts.tilt || 4;
      var stag  = opts.stagger || 0.11;
      gsap.utils.toArray(containerSel).forEach(function (container) {
        var group = gsap.utils.toArray(container.querySelectorAll(childSel));
        if (!group.length) return;
        group.forEach(function (el, i) {
          var fromLeft = (i % 2 === 0);
          gsap.from(el, {
            x: fromLeft ? -dist : dist,
            y: opts.lift || 30,
            rotate: fromLeft ? -tilt : tilt,
            scale: .92,
            opacity: 0,
            duration: opts.duration || .95,
            delay: i * stag,
            ease: 'power3.out',
            scrollTrigger: { trigger: container, start: 'top 82%', once: true },
            // GSAP leaves its own inline transform on the element once
            // this finishes. That inline style outranks any CSS :hover
            // rule (e.g. .photo-card:hover), which is what was silently
            // blocking the hover lift on some cards. Clearing it hands
            // transform control back to the stylesheet.
            onComplete: function () { gsap.set(el, { clearProps: 'transform' }); }
          });
        });
      });
    }

    assemble('.why-visit__grid', '.photo-card', { distance: 80, tilt: 3 });
    assemble('.exp__grid',       '.exp-card',   { distance: 110, tilt: 5, lift: 40, duration: 1.05 });
    assemble('.notes__grid',     '.note-card',  { distance: 90, tilt: 4 });

    // the register is a list, so the rows rise straight up in sequence
    // rather than tilting in — a tilt on a typeset row breaks the
    // alignment that the whole layout depends on
    assemble('.voices__register', '.voice-row', { distance: 26, tilt: 0, lift: 0, duration: .7, stagger: .055 });

    // the stats sit in one pill — they slide in from the sides toward
    // the middle rather than tilting
    gsap.utils.toArray('.statbar__box').forEach(function (box) {
      var items = gsap.utils.toArray(box.querySelectorAll('.statbar__item'));
      items.forEach(function (el, i) {
        var mid = (items.length - 1) / 2;
        gsap.from(el, {
          x: (i - mid) * 42,
          opacity: 0, duration: .8, delay: Math.abs(i - mid) * .07,
          ease: 'power3.out',
          scrollTrigger: { trigger: box, start: 'top 85%', once: true }
        });
      });
    });

    // --- feature rows: alternate sides as they stack up ---
    gsap.utils.toArray('.craft__list').forEach(function (list) {
      gsap.utils.toArray(list.querySelectorAll('.craft__row')).forEach(function (el, i) {
        gsap.from(el, {
          x: i % 2 ? 46 : -46,
          opacity: 0, duration: .8, delay: i * .1,
          ease: 'power3.out',
          scrollTrigger: { trigger: list, start: 'top 84%', once: true }
        });
      });
    });

    /* --- the two collage blocks assemble from opposite sides ---
       In About, the tall photo swings in from the left while the two
       stacked ones come from the right. Same idea in the slow-travel
       block, so both read as pieces meeting in the middle. */
    var collages = [
      { wrap: '.story__grid',  pieces: ['.story__body', '.story__tall', '.story__aside'] },
      { wrap: '.craft__art',   pieces: ['.a', '.b', '.c'] }
    ];
    collages.forEach(function (c) {
      var wrap = document.querySelector(c.wrap);
      if (!wrap) return;
      c.pieces.forEach(function (sel, i) {
        var el = wrap.querySelector(sel);
        if (!el) return;
        gsap.from(el, {
          x: i === 0 ? -70 : 70,
          y: i === 2 ? 40 : 0,
          rotate: i === 0 ? -3 : 3,
          scale: .9,
          opacity: 0,
          duration: 1.05,
          delay: i * .13,
          ease: 'power3.out',
          scrollTrigger: { trigger: wrap, start: 'top 80%', once: true }
        });
      });
    });

    /* --- the honeycomb: the pitch slides in, then the comb builds ---

       This section had nothing. The two blocks either side of it — the
       About collage above and the spotlight below — both animate, so
       the honeycomb was the one place on the page where a whole screen
       simply appeared, which reads as a stutter between two things that
       move.

       THE PITCH goes one child at a time from the left, following the
       data-aos="fade-right" already on the container. Animating the
       children rather than the block is what makes the eyebrow, the
       heading, the figures and the button arrive in reading order
       instead of as one slab.

       THE COMB builds tile by tile. They scale up from the centre of
       their own hexagon rather than sliding, because a hexagon sliding
       into a nest of other hexagons reads as the layout settling — the
       tiles look like they are still deciding where to go. Growing in
       place keeps the interlock visible the whole time.

       ⚠ clearProps AT THE END IS NOT OPTIONAL HERE.
         GSAP leaves its final transform inline on the element, and an
         inline transform outranks every CSS rule. .hex-cell has
         :hover and .is-open states, and the tiles are positioned by
         negative margin precisely BECAUSE a transform on them gets
         clobbered (there is a note about that in homepage.css). Hand
         transform back to the stylesheet the moment this finishes or
         the tiles stop responding to hover. Same reason assemble()
         does it. */
    var hexIntro = document.querySelector('.hexplore__intro');
    if (hexIntro && hexIntro.children.length) {
      gsap.from(hexIntro.children, {
        x: -52, opacity: 0,
        duration: .8, stagger: .07,
        ease: 'power3.out',
        scrollTrigger: { trigger: hexIntro, start: 'top 80%', once: true },
        onComplete: function () { gsap.set(hexIntro.children, { clearProps: 'transform' }); }
      });
    }

    var comb = document.querySelector('.hexplore__comb');
    if (comb) {
      var tiles = gsap.utils.toArray(comb.querySelectorAll('.hex-cell'));
      if (tiles.length) {
        gsap.from(tiles, {
          scale: .82, opacity: 0,
          duration: .7, stagger: .08,
          ease: 'back.out(1.4)',
          /* the comb sits lower than its own heading, so it triggers on
             itself rather than the section, or the tiles would have
             finished before you had scrolled far enough to see them */
          scrollTrigger: { trigger: comb, start: 'top 84%', once: true },
          onComplete: function () { gsap.set(tiles, { clearProps: 'transform' }); }
        });
      }
    }

    /* --- destination spotlight: the two columns arrive apart -------
       The text column and the carousel come in from opposite sides,
       a beat apart, the same way the collages meet in the middle.

       Both are animated AS WHOLE BLOCKS on purpose. The carousel builds
       its thumbnails in JavaScript and the text column swaps .is-active
       between 24 stacked <article>s; animating anything inside either
       one would collide with a card that is mid-swap. Moving the
       container leaves all of that alone. */
    var spotMain = document.querySelector('.dest-spotlight__main');
    var spotRail = document.querySelector('.dest-spotlight__carousel');

    if (spotMain) {
      gsap.from(spotMain, {
        x: -56, opacity: 0, duration: .95, ease: 'power3.out',
        scrollTrigger: { trigger: spotMain, start: 'top 80%', once: true },
        onComplete: function () { gsap.set(spotMain, { clearProps: 'transform' }); }
      });
    }

    if (spotRail) {
      gsap.from(spotRail, {
        x: 56, opacity: 0, duration: .95, delay: .12, ease: 'power3.out',
        scrollTrigger: { trigger: spotRail, start: 'top 84%', once: true },
        onComplete: function () { gsap.set(spotRail, { clearProps: 'transform' }); }
      });
    }

    /* ===================================================================
       INTERIOR PAGES

       about.php, destinations.php and gallery.php have their own
       components. Each block below no-ops on pages where its elements
       are absent, so this one file drives all four pages.
       =================================================================== */

    // --- page banners: title lifts, photo drifts behind it ---
    gsap.utils.toArray('.page-hero').forEach(function (band) {
      var inner = band.querySelector('.page-hero__inner');
      if (inner) {
        gsap.from(inner.children, {
          y: 40, opacity: 0, duration: 1, stagger: .12, ease: 'power3.out', delay: .15
        });
      }
      var media = band.querySelector('img.photo-layer, video.photo-layer');
      if (media) {
        gsap.to(media, {
          yPercent: 10, ease: 'none',
          scrollTrigger: { trigger: band, start: 'top top', end: 'bottom top', scrub: true }
        });
      }
    });

    /* ---------- about.php ---------- */

    // the two-column block: copy from the left, photo from the right
    gsap.utils.toArray('.about-split').forEach(function (row) {
      var body  = row.querySelector('.about-split__body');
      var media = row.querySelector('.about-split__media');
      if (body)  gsap.from(body,  { x: -60, opacity: 0, duration: 1, ease: 'power3.out',
                    scrollTrigger: { trigger: row, start: 'top 80%', once: true } });
      if (media) gsap.from(media, { x: 60, opacity: 0, rotate: 2, scale: .93, duration: 1.05,
                    delay: .12, ease: 'power3.out',
                    scrollTrigger: { trigger: row, start: 'top 80%', once: true } });
    });

    // fact rows: each line wipes in from the left, like a list filling up
    gsap.utils.toArray('.fact-list').forEach(function (list) {
      gsap.from(list.querySelectorAll('div'), {
        x: -28, opacity: 0, duration: .6, stagger: .08, ease: 'power3.out',
        scrollTrigger: { trigger: list, start: 'top 86%', once: true }
      });
    });

    // the twelve towns: alternate columns so the list assembles inward
    gsap.utils.toArray('.town-grid').forEach(function (grid) {
      gsap.utils.toArray(grid.querySelectorAll('li')).forEach(function (li, i) {
        gsap.from(li, {
          x: i % 2 ? 38 : -38, opacity: 0, duration: .65, delay: (i % 6) * .06,
          ease: 'power3.out',
          scrollTrigger: { trigger: li, start: 'top 92%', once: true }
        });
      });
    });

    // getting here: three steps rise in sequence, each a beat later
    gsap.utils.toArray('.steps').forEach(function (row) {
      gsap.from(row.querySelectorAll('.step'), {
        y: 50, opacity: 0, duration: .9, stagger: .14, ease: 'power3.out',
        scrollTrigger: { trigger: row, start: 'top 82%', once: true }
      });
    });

    /* ---------- destinations.php ---------- */

    // each town heading slides in and its rule draws across
    gsap.utils.toArray('.town-block__head').forEach(function (head) {
      gsap.from(head.children, {
        x: -34, opacity: 0, duration: .8, stagger: .1, ease: 'power3.out',
        scrollTrigger: { trigger: head, start: 'top 88%', once: true }
      });
      // the rule underneath draws across — done by adding a class so
      // the CSS animates the ::after, since scaling the header itself
      // would squash the text inside it
      ScrollTrigger.create({
        trigger: head, start: 'top 88%', once: true,
        onEnter: function () { head.classList.add('is-drawn'); }
      });
    });

    /* THE INDEX CARDS.

       This was written when destinations.php was twelve town blocks of
       two cards, and it broke the moment that became one grid of 24:

         - the trigger was the GRID, not the card. With a two-card grid
           that is the same thing. With a grid several screens tall it
           means every card starts animating when the TOP of the grid
           appears, so most of them run while off screen and are already
           finished — or worse, still mid-flight — by the time you reach
           them. That is the cards sitting low and spilling over the
           section edge.

         - `delay: i * .1` staggered by index across the whole grid, so
           the 24th card waited 2.3 seconds before it moved at all.

         - x: ±80 with a rotation assumed two cards per row leaning away
           from each other. At three across it is just noise.

       Each card now triggers on itself, rises a short distance, and
       staggers only against the others in its own row. */
    gsap.utils.toArray('.dest-grid').forEach(function (grid) {
      gsap.utils.toArray(grid.querySelectorAll('.dest-card')).forEach(function (card, i) {
        gsap.from(card, {
          y: 26, opacity: 0, scale: .97,
          duration: .6, delay: (i % 3) * .07, ease: 'power2.out',
          scrollTrigger: { trigger: card, start: 'top 92%', once: true }
        });
      });
    });

    // the jump bar chips flick in one after another
    gsap.utils.toArray('.town-jump__inner').forEach(function (bar) {
      gsap.from(bar.querySelectorAll('a'), {
        y: -14, opacity: 0, duration: .5, stagger: .04, ease: 'power2.out',
        scrollTrigger: { trigger: bar, start: 'top 95%', once: true }
      });
    });

    /* ---------- gallery.php ---------- */

    // each set heading arrives before its photos
    gsap.utils.toArray('.gal-set__head').forEach(function (head) {
      gsap.from(head.children, {
        y: 30, opacity: 0, duration: .9, stagger: .1,   /* blur removed */
        ease: 'power3.out',
        scrollTrigger: { trigger: head, start: 'top 86%', once: true }
      });
    });

    // --- gallery: tiles alternate sides as the masonry knits together ---
    gsap.utils.toArray('.masonry').forEach(function (grid) {
      gsap.utils.toArray(grid.querySelectorAll('.masonry-item')).forEach(function (el, i) {
        gsap.from(el, {
          x: i % 2 ? 60 : -60,
          y: 34, rotate: i % 2 ? 2.5 : -2.5, scale: .94, opacity: 0,
          duration: .9, delay: (i % 3) * .1,
          ease: 'power3.out',
          scrollTrigger: { trigger: el, start: 'top 88%', once: true }
        });
      });
    });

    /* --- the quote band: words rise one after another ---

       Each word is wrapped in a masked span and lifts up from behind
       it, so the sentence assembles rather than fading in whole. The
       mask is what makes it read as type revealing itself instead of
       text sliding around. --- */
    var quoteText = document.querySelector('.quote__text');
    if (quoteText) {
      var words = quoteText.textContent.trim().split(/\s+/);
      quoteText.innerHTML = words.map(function (w) {
        return '<span class="qw"><span>' + w + '</span></span>';
      }).join(' ');

      gsap.from(quoteText.querySelectorAll('.qw > span'), {
        yPercent: 115,
        duration: .9,
        stagger: .045,
        ease: 'power3.out',
        scrollTrigger: { trigger: quoteText, start: 'top 82%', once: true }
      });
    }

    /* --- closing band: heading lifts, then copy, then the buttons
       arrive from opposite sides and settle --- */
    var ctaInner = document.querySelector('.cta__inner');
    if (ctaInner) {
      var ctaTL = gsap.timeline({
        scrollTrigger: { trigger: ctaInner, start: 'top 82%', once: true }
      });
      ctaTL
        .from(ctaInner.querySelector('.cta__title'), {
          y: 46, opacity: 0, duration: 1, ease: 'power3.out'   /* blur removed */
        })
        .from(ctaInner.querySelector('.cta__desc'), {
          y: 24, opacity: 0, duration: .8, ease: 'power3.out'
        }, '-=0.6')
        .from(ctaInner.querySelectorAll('.cta__actions > *'), {
          x: function (i) { return i % 2 ? 50 : -50; },
          y: 20, scale: .9, opacity: 0,
          duration: .8, stagger: .1, ease: 'back.out(1.6)'
        }, '-=0.45');
    }

    // --- full-bleed bands: the photo drifts behind the text ---
    gsap.utils.toArray('.quote, .cta, .voices').forEach(function (band) {
      var media = band.querySelector('img, video');
      if (!media) return;
      gsap.fromTo(media,
        { yPercent: -8 },
        {
          yPercent: 8, ease: 'none',
          scrollTrigger: { trigger: band, start: 'top bottom', end: 'bottom top', scrub: true }
        });
    });

  } else {
    // reduced motion: make sure nothing is left mid-animation
    gsap.utils.toArray('[data-aos]').forEach(function (el) {
      el.style.opacity = 1;
      el.style.transform = 'none';
    });
  }

  // --- headline + section reveals ---
  document.querySelectorAll('[data-reveal]').forEach(function (el) {
    ScrollTrigger.create({
      trigger: el, start: 'top 88%', once: true,
      onEnter: function () { el.classList.add('is-revealed'); }
    });
  });
  // the hero headline is above the fold, so fire it immediately
  var heroTitle = document.querySelector('.hero__title[data-reveal]');
  if (heroTitle) { setTimeout(function(){ heroTitle.classList.add('is-revealed'); }, 250); }

  // --- buttons lean toward the cursor, and spring back ---
  if (window.matchMedia('(hover:hover)').matches) {
    document.querySelectorAll('.magnetic').forEach(function (el) {
      /* one tween per frame, not one per input sample */
      var magPending = null, magQueued = false;
      el.addEventListener('mousemove', function (e) {
        var r = el.getBoundingClientRect();
        magPending = {
          x: (e.clientX - (r.left + r.width  / 2)) * 0.25,
          y: (e.clientY - (r.top  + r.height / 2)) * 0.25
        };
        if (magQueued) return;
        magQueued = true;
        requestAnimationFrame(function () {
          magQueued = false;
          if (!magPending) return;
          gsap.to(el, {
            x: magPending.x, y: magPending.y,
            duration: .5, ease: 'power3.out', overwrite: 'auto'
          });
        });
      }, { passive: true });
      el.addEventListener('mouseleave', function () {
        gsap.to(el, { x: 0, y: 0, duration: .7, ease: 'elastic.out(1,0.4)' });
      });
    });
  }

  // --- the honeycomb tiles: tap to open the description on touch.
  //
  // Hover is handled entirely in CSS now (see homepage.css section 50),
  // because the tiles no longer float under the cursor and there is
  // nothing left for :hover to lose a race with. A phone has no hover,
  // though, so a tap toggles .is-open and opening one closes the rest.
  //
  // The tiles are not links, so a stray tap costs nothing — no click
  // is being swallowed here.
  var hexCells = document.querySelectorAll('.hexplore__comb .hex-cell');
  hexCells.forEach(function (cell) {
    cell.addEventListener('touchstart', function () {
      hexCells.forEach(function (other) {
        if (other !== cell) other.classList.remove('is-open');
      });
      cell.classList.toggle('is-open');
    }, { passive: true });
  });

  // tapping the background closes whichever tile is open
  document.addEventListener('touchstart', function (e) {
    if (e.target.closest && e.target.closest('.hexplore__comb')) return;
    hexCells.forEach(function (cell) { cell.classList.remove('is-open'); });
  }, { passive: true });

  /* ------------------------------------------------------------------
     THE SIGN-IN MODAL — closing only.

     OPENING now lives in assets/auth-gate.js, which loads after this
     file. It listens once on the document in the capture phase and
     decides what is gated, so the per-element [data-auth-gate] loop
     that used to sit here was dead code: the capture listener stops
     the event before it could ever reach it.

     Closing stays here because nothing else does it — auth-gate.js
     only opens the modal. Backdrop, the X, and Escape all run through
     closeAuthModal below.
     ------------------------------------------------------------------ */
  var authModal = document.getElementById('authModal');
  if (!authModal) return;

  function closeAuthModal(){
    authModal.classList.remove('is-open');
    authModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }

  authModal.querySelectorAll('[data-modal-close]').forEach(function(el){
    el.addEventListener('click', closeAuthModal);
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && authModal.classList.contains('is-open')) closeAuthModal();
  });

});
/* ====================================================================
   VOICES MODAL — "see all reviews"

   Deliberately its own DOMContentLoaded listener rather than more code
   appended to the big one above. That handler ends with

       var authModal = document.getElementById('authModal');
       if (!authModal) return;

   so anything added after it inside the same function silently stops
   running on any page without the sign-in modal. A separate listener
   cannot be short-circuited by an early return somewhere else.

   Everything is guarded on the trigger existing, so this is inert on
   pages with no reviews section — the PHP omits the whole thing when
   six or fewer reviews are published.
   ==================================================================== */
document.addEventListener('DOMContentLoaded', function () {

  var modal   = document.getElementById('voicesModal');
  var openers = document.querySelectorAll('[data-voices-open]');
  if (!modal || !openers.length) return;

  var panel     = modal.querySelector('.voices-modal__panel');
  var body      = modal.querySelector('.voices-modal__body');
  var lastFocus = null;

  /* Pending cleanup from a close that has not finished yet. Both are
     cancelled on open — see the note in closeModal for why leaving
     them running breaks the next open. */
  var closeTimer   = null;
  var closeHandler = null;

  /* Every focusable thing inside the panel, read fresh each time —
     the list is short and reading it on open avoids holding a stale
     reference if the cards are ever re-rendered. */
  function focusables() {
    return Array.prototype.filter.call(
      panel.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'),
      function (el) { return el.offsetParent !== null; }
    );
  }

  function openModal() {
    lastFocus = document.activeElement;

    /* Tear down anything the previous close left running. If a close
       is still mid-fade when the visitor clicks again, its cleanup
       would land a moment later and hide a modal that is now opening. */
    if (closeTimer)   { clearTimeout(closeTimer); closeTimer = null; }
    if (closeHandler) { modal.removeEventListener('transitionend', closeHandler); closeHandler = null; }

    /* [hidden] has to come off before the class goes on, and the two
       need to land in different frames. Removing display:none and
       adding the transition class in the same frame gives the browser
       nothing to animate from, and the panel snaps in instead of
       easing. */
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');

    requestAnimationFrame(function () {
      modal.classList.add('is-open');
      document.body.classList.add('modal-open');

      var first = modal.querySelector('.voices-modal__close');
      if (first) first.focus();
    });
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');

    /* Wait for the fade before pulling it out of the layout, or the
       closing animation never gets shown.

       done() has to be safe to call twice, and safe to call late. Two
       things can trigger it — the transition ending, and a timeout for
       when it does not — and setting hidden cancels any transition
       still running, so whichever fires first stops the other from
       ever arriving. The is-open check is what makes a late call
       harmless: if the visitor has reopened the modal in the meantime,
       stale cleanup must not hide it out from under them. */
    var done = function () {
      if (modal.classList.contains('is-open')) return;

      if (closeTimer)   { clearTimeout(closeTimer); closeTimer = null; }
      if (closeHandler) { modal.removeEventListener('transitionend', closeHandler); closeHandler = null; }

      modal.hidden = true;
      if (body) body.scrollTop = 0;
    };

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) {
      done();
    } else {
      closeHandler = function (e) {
        // transitionend bubbles up from the cards and the panel too
        if (e.target !== modal || e.propertyName !== 'opacity') return;
        done();
      };
      modal.addEventListener('transitionend', closeHandler);

      // if the transition is interrupted or never runs, clean up anyway
      closeTimer = setTimeout(done, 450);
    }

    // send focus back where the visitor left it
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  Array.prototype.forEach.call(openers, function (btn) {
    btn.addEventListener('click', openModal);
  });

  modal.querySelectorAll('[data-voices-close]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', function (e) {
    if (modal.hidden) return;

    if (e.key === 'Escape') {
      closeModal();
      return;
    }

    /* Focus trap. Without it, tabbing past the last card walks into
       the page behind the backdrop, which for a screen reader means
       the dialog has quietly stopped existing. */
    if (e.key !== 'Tab') return;

    var list = focusables();
    if (!list.length) return;

    var first = list[0];
    var last  = list[list.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });
});