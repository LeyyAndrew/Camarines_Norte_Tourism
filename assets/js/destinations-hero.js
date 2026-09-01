/* ===================================================================
   assets/destinations-hero.js

   The featured-place section under the banner on destinations.php.
   Changing which destination it shows, three ways: a card in the rail,
   the previous / next arrows, or nothing at all with JavaScript off.
   Pairs with assets/destinations-hero.css.

   The slides are printed into the page by destinations.php as a JSON
   data island (#heroSlides), the same pattern the map uses, so this
   stays a plain cacheable file with no PHP in it.

   WITHOUT THIS FILE the section still works: every card is a real
   #anchor to its card in the list below, and the first destination is
   already rendered by PHP. Only the arrows go dead, which is why they
   are <button>s rather than links. Nothing here is required for the
   page to be usable.
   =================================================================== */
(function () {

  /* ---- the missing-photo net, first ----------------------------------
     Bound before anything else so a photo that 404s during the initial
     load is still caught. A broken-image glyph on a dark card reads as
     a rendering fault rather than a missing file; .is-photoless hides
     the <img> and leaves the graded panel underneath. The section keeps
     the video underneath, so it needs no panel of its own.

     This is a NET, not a fix. If you are seeing it, the paths in
     includes/destinations-data.php do not match what is in uploads/. */
  function netFor(img) {
    if (!img) return;
    var frame = img.closest('.hero-feature, .hero-rail__card, .dest-card__media, .media') || img.parentNode;
    if (img.complete && img.naturalWidth === 0) frame.classList.add('is-photoless');
    img.addEventListener('error', function () { frame.classList.add('is-photoless'); });
    img.addEventListener('load', function () {
      if (img.naturalWidth > 0) frame.classList.remove('is-photoless');
    });
  }

  document.querySelectorAll('.hero-feature__photo, .hero-rail__card .photo-layer').forEach(netFor);

  /* ---- how big a CARD name is allowed to be, and how much of the town
          line is worth printing ------------------------------------------

     THE PARTNER OF SECTION 17.2 IN THE STYLESHEET, and the same idea as
     fitTitle() below at one size down: the names in the rail run 13 to
     35 characters through one fixed font-size, so "Parola Island" gets
     one line and "St. Francis of Assisi Parish Church" gets four, and
     four lines of caption is most of a 12rem card.

     THE LINE COUNT IS WHAT STAYS CONSTANT. This writes data-fit from
     the character count and section 17.2 picks the size, the leading
     and the tracking off it.

     It runs BEFORE the early returns below on purpose. Those are about
     the switch — no slides, no rail, nothing to switch between — and
     the cards still need sizing whether or not anything can switch
     them.

     THE TOWN LINE: every destination in the set is in Camarines Norte,
     so ", Camarines Norte" on a 10rem caption is seventeen characters
     of nothing, and it is what pushes the town itself into an ellipsis
     — "Jose Panganiban, Camarin...". Dropped here rather than in
     destinations.php so the markup keeps the full string for anything
     that reads the page without this script. DELETE THE ONE LINE
     MARKED BELOW to put the province back.

     Both are one pass over static markup at load. Nothing re-runs them,
     because nothing rewrites the cards. */
  (function fitRail() {
    document.querySelectorAll('.hero-rail__name').forEach(function (el) {
      var n = (el.textContent || '').replace(/\s+/g, ' ').trim().length;
      el.setAttribute(
        'data-fit',
        n <= 15 ? 'lg' :
        n <= 22 ? 'md' :
        n <= 30 ? 'sm' : 'xs'
      );
    });

    document.querySelectorAll('.hero-rail__loc').forEach(function (el) {
      /* delete this line to keep ", Camarines Norte" on every card */
      el.textContent = (el.textContent || '').replace(/\s*,\s*Camarines\s+Norte\s*$/i, '').trim();
    });
  })();


  /* ---- the strip down the right edge ---------------------------------

     SECTION 18.1 OF THE STYLESHEET IS THE OTHER HALF OF THIS.

     The symptom: a band of flat navy about 33px wide down the right of
     the section, full height, the photograph stopping dead at its left
     edge. Section 16.8 found the same thing happening to the scrim and
     covered it by bleeding the scrim past the edge; 17.1 tried the
     same 3rem guess on the photo and it did not take.

     A GUESS IS THE WRONG SHAPE OF FIX. This measures instead: how wide
     the photo's box actually is, against how wide it would have to be
     to reach the right of the section AND the right of the window.
     Whatever the gap turns out to be, the bleed is that plus 16px.

     IT WRITES THE WIDTH INLINE AND MARKS IT !important. An inline
     declaration with !important is the top of the cascade — no
     stylesheet beats it, including the older copy of this one that is
     very probably still being served (see the note at the top of the
     stylesheet, and the title size in the screenshot, which no version
     of these rules can produce).

     offsetWidth, NOT getBoundingClientRect: the live layer is scaled
     by a transform, and a client rect includes the transform. The box
     is what needs measuring, not the picture painted from it.

     IF THE BAND SURVIVES THIS the photo is not what is short, and
     __heroSeal() prints the table that says what is — every ancestor
     of the section with its left, right and width against the window.
     Two numbers that do not match is the answer. */
  (function () {
    var section = document.querySelector('.hero-feature');
    if (!section) return;

    /* ---- DESKTOP ONLY. THIS BLOCK IS NOT SAFE ON A PHONE -------------

       Everything below was written for one desktop symptom: a black
       band down the right of the section, caused by something in the
       page shell being narrower than the window. The repair is to
       measure every ancestor and STRETCH the short ones — including
       <body> and <html> — with inline width and max-width:none, both
       marked !important.

       On a phone that repair is the disease. mobile.css holds the
       document at max-width:100vw; an inline max-width:none!important
       is the top of the cascade and beats it, so <html> ends up wider
       than the screen. spillRight() then takes overflow:hidden off the
       section, so the oversized photo layers are no longer clipped and
       the extra width is real. The page is now horizontally scrollable
       — invisibly, because overflow-x:hidden removed the scrollbar but
       not the ability to scroll.

       The next scrollIntoView() then slides the whole page left and
       nothing can bring it back. That is the bug in the screenshot:
       the fixed header and the fixed chat bubble sit correctly while
       every other thing in the section is 50px off the left edge.

       1100px matches the stylesheet's own first breakpoint, where the
       rail leaves the corner and the layout stops being the one this
       block was measured against.

       matchMedia is checked live, not cached: a rotation or a resize
       past the breakpoint should get the desktop behaviour back. */
    var WIDE = '(min-width:1101px)';
    function isWide() {
      return !window.matchMedia || window.matchMedia(WIDE).matches;
    }
    if (!isWide()) return;

    var layers = section.querySelectorAll('.hero-feature__photo');
    if (!layers.length) return;

    /* ---- HOW WIDE THE WINDOW ACTUALLY IS ------------------------------

       THIS IS THE ONE THAT WAS WRONG. Both earlier passes measured
       against document.documentElement.clientWidth, and if the <html>
       or <body> box is itself the short one — a width, a max-width, a
       padding on the page shell — then that number IS the short number.
       The code compared the section against a window it believed to be
       42px narrower than it is, found nothing wrong, and did nothing.

       The site header proves the real width: it is position:fixed, it
       spans the whole window, and its orange bottom rule crosses the
       black band in the screenshot. A fixed box is measured against the
       viewport, NOT against <html>, so it is immune to whatever is
       squeezing the page.

       So: put a fixed, invisible, zero-height box at width:100%, read
       it, throw it away. That is the number everything below aims at. */
    function windowWidth() {
      var probe = document.createElement('div');
      probe.setAttribute('style',
        'position:fixed!important;left:0!important;top:0!important;' +
        'width:100%!important;height:0!important;margin:0!important;' +
        'padding:0!important;border:0!important;visibility:hidden!important;' +
        'pointer-events:none!important');
      document.body.appendChild(probe);
      var w = probe.getBoundingClientRect().width;
      probe.parentNode.removeChild(probe);
      return Math.max(Math.round(w), document.documentElement.clientWidth);
    }

    function sealRight() {
      /* measure clean: last pass's patch would otherwise be measured
         as if it were the page's own geometry */
      layers.forEach(function (el) { el.style.removeProperty('width'); });

      var box  = section.getBoundingClientRect();
      var want = Math.max(section.clientWidth, windowWidth() - box.left);
      var have = layers[0].offsetWidth;
      var gap  = Math.max(0, Math.ceil(want - have));

      section.style.setProperty('--hero-bleed', (gap + 16) + 'px');

      layers.forEach(function (el) {
        /* base.css caps every <img> at max-width:100%. Until that is
           lifted, no width set here can take effect — see section 21
           of the stylesheet. */
        el.style.setProperty('max-width', 'none', 'important');
        el.style.setProperty('left', '0', 'important');
        el.style.setProperty('right', 'auto', 'important');
        el.style.setProperty('width', (have + gap + 16) + 'px', 'important');
        el.style.setProperty('height', '100%', 'important');
        el.style.setProperty('object-fit', 'cover', 'important');
      });
    }

    /* the diagnostic. Run __heroSeal() in the console. */
    window.__heroSeal = function () {
      var rows = [];
      var el = section;
      while (el && el !== document.documentElement) {
        var r = el.getBoundingClientRect();
        rows.push({
          node:  el.tagName.toLowerCase() +
                 (el.className && typeof el.className === 'string'
                    ? '.' + el.className.trim().split(/\s+/).join('.') : ''),
          left:  Math.round(r.left),
          right: Math.round(r.right),
          width: Math.round(r.width)
        });
        el = el.parentElement;
      }
      rows.push({ node: '\u2014 <html> reports \u2014', left: 0,
                  right: document.documentElement.clientWidth,
                  width: document.documentElement.clientWidth });
      rows.push({ node: '\u2014 real window \u2014', left: 0,
                  right: windowWidth(), width: windowWidth() });
      if (console.table) console.table(rows); else console.log(rows);
      return rows;
    };

    sealRight();
    /* ---- when it is not the photo that is short -----------------------

       Two passes at widening the photograph have not closed the band,
       and that rules the photograph out. If the box were short, making
       it wider would have moved the edge; it did not, so the clip is
       further out — the section, or something above it in the tree, is
       narrower than the window, and everything inside it is being cut
       at that line. The band is whatever the page paints behind it.

       THIS WALKS UP AND MEASURES. From the section to <body>, any box
       that is most of the width of the window but stops short of its
       right edge gets stretched inline, with !important, by exactly the
       distance it is short. Anything narrow is skipped — a centred
       .wrap is meant to stop short, and this must not touch it.

       IT ALSO SAYS WHAT IT PATCHED. Check the console: the warning
       names the element, and that name is the actual bug. A section of
       a page being 37px narrower than the window is a page-shell
       problem — a stray padding-right, a max-width, a margin — and it
       is affecting every full-width block on the page, not just this
       one. Fix it there and this pass finds nothing to do. */
    function widenShortAncestors() {
      var vw = windowWidth();
      var el = section;
      var guard = 0;

      /* section, then every box above it, INCLUDING <body> and <html> —
         the previous version stopped at <html> and that is very likely
         where the squeeze is. */
      while (el && guard++ < 12) {
        var r = el.getBoundingClientRect();
        var short = vw - r.right;

        /* wide enough to be a page-level box, and short by an amount
           that is a fault rather than a deliberate gutter */
        if (r.width > vw * 0.6 && short > 1 && short < vw * 0.25) {
          el.style.setProperty('box-sizing', 'border-box', 'important');
          el.style.setProperty('max-width', 'none', 'important');
          el.style.setProperty('margin-right', '0', 'important');
          el.style.setProperty('padding-right', '0', 'important');
          el.style.setProperty('border-right-width', '0', 'important');
          el.style.setProperty('width', Math.ceil(r.width + short) + 'px', 'important');

          if (window.console && console.warn) {
            console.warn('[hero] ' + el.tagName.toLowerCase() +
              (el.className && typeof el.className === 'string'
                 ? '.' + el.className.trim().split(/\s+/).join('.') : '') +
              ' was ' + Math.round(short) + 'px short of the window and has been ' +
              'stretched. THAT ELEMENT is the bug — fix its width in the ' +
              'stylesheet and this patch stops firing.');
          }
        }

        if (el === document.documentElement) break;
        el = el.parentElement;
      }
    }

    widenShortAncestors();
    sealRight();          /* re-measure the photo against the new width */

    /* ---- last resort: let the picture spill --------------------------

       If the section still does not reach the right of the window after
       that, it is being CLIPPED rather than sized — .hero-feature is
       overflow:hidden, so a photo widened inside a short section is cut
       at exactly the line the band starts on.

       So the clip comes off and the photograph is allowed to paint past
       the section's own right edge, all the way to the window. The
       scrim goes with it, or the strip would be the only unscrimmed
       part of the picture.

       transform:none here, not scale(1.02): with the clip gone, a 2%
       overscale would hang the photo below the section and print a
       sliver of it over whatever comes next. The width is measured
       exactly now, so the overscan is not needed.

       ONLY IF NEEDED. On a page where the shell is not squeezed, none
       of this runs. */
    function spillRight() {
      var vw   = windowWidth();
      var box  = section.getBoundingClientRect();
      var over = vw - box.right;
      if (over <= 1) return;

      section.style.setProperty('overflow', 'visible', 'important');

      layers.forEach(function (el) {
        el.style.setProperty('max-width', 'none', 'important');
        el.style.setProperty('transform', 'none', 'important');
        el.style.setProperty('width', Math.ceil(box.width + over + 2) + 'px', 'important');
      });

      var scrim = section.querySelector('.hero-feature__scrim');
      if (scrim) {
        scrim.style.setProperty('right', '-' + Math.ceil(over + 2) + 'px', 'important');
        scrim.style.setProperty('width', 'auto', 'important');
      }

      if (window.console && console.warn) {
        console.warn('[hero] the section is ' + Math.round(over) +
          'px short of the window and is clipping. The photo now spills ' +
          'past it. The band was never the photo — something above ' +
          '.hero-feature is narrower than the window; run __heroSeal().');
      }
    }

    spillRight();

    /* ---- if the band is STILL there, say why, on the page ------------

       Four passes have not closed it, which means the cause is not
       where any of them are looking. The console warnings have gone
       unread — fair enough, so this puts the numbers on the screen
       instead. It appears ONLY while a gap remains, and it disappears
       the moment the page is measuring correctly.

       WHAT TO DO WITH IT: screenshot it. The row that does not reach
       the window width is the element with the bad rule in it, and
       almost certainly the bad rule is in base.css or the page shell
       rather than anywhere in the hero. */
    function report() {
      var id = 'heroGapReport';
      var old = document.getElementById(id);
      if (old) old.parentNode.removeChild(old);

      var vw  = windowWidth();
      var box = section.getBoundingClientRect();
      if (vw - box.right <= 1) return;          /* nothing wrong: say nothing */

      var lines = ['window (fixed probe): ' + vw + 'px'];
      var el = section, guard = 0, culprit = null;

      while (el && guard++ < 12) {
        var r = el.getBoundingClientRect();
        var name = el.tagName.toLowerCase() +
          (el.className && typeof el.className === 'string'
             ? '.' + el.className.trim().split(/\s+/)[0] : '');
        var short = Math.round(vw - r.right);
        lines.push(name + ': width ' + Math.round(r.width) +
                   ', right ' + Math.round(r.right) +
                   (short > 1 ? '  \u2190 SHORT by ' + short : ''));
        if (short > 1) culprit = name;
        if (el === document.documentElement) break;
        el = el.parentElement;
      }

      lines.push('html.clientWidth reports: ' + document.documentElement.clientWidth);
      if (culprit) lines.push('outermost short box: ' + culprit);

      var badge = document.createElement('div');
      badge.id = id;
      badge.setAttribute('style',
        'position:fixed!important;left:8px!important;bottom:8px!important;' +
        'z-index:99999!important;max-width:22rem!important;' +
        'padding:.6rem .7rem!important;border-radius:.5rem!important;' +
        'background:rgba(120,12,12,.92)!important;color:#fff!important;' +
        'font:12px/1.45 ui-monospace,Menlo,Consolas,monospace!important;' +
        'white-space:pre!important;pointer-events:none!important');
      badge.textContent = 'HERO GAP\n' + lines.join('\n');
      document.body.appendChild(badge);
    }

    report();

    /* ---- and it holds still ------------------------------------------

       Section 20 of the stylesheet turned the drift off and the photo
       kept moving, which rules out the stylesheet: a rule that is
       being applied cannot be ignored. Something else is writing a
       transform onto these layers — an older copy of this file with
       its own !important, a keyframe animation, or a scroll effect in
       another script setting element.style.transform directly.

       ALL THREE LOSE TO THIS. An inline declaration marked !important
       is the top of the cascade, above any stylesheet and above any
       animation, and the observer below puts it back if a script
       overwrites it.

       scale(1.02) rather than none: the 2% is the overscan that keeps
       a hairline of the dark fill from showing along an edge. Same
       reason as section 20.

       THE OBSERVER CANNOT LOOP. It only writes when the value it finds
       is not the one it wants, so its own write does not trigger
       another. It watches the style attribute alone — swapPhoto()
       touches opacity and transition on these elements every switch,
       and neither is any of its business. */
    function holdStill() {
      layers.forEach(function (el) {
        var want = el.style.getPropertyValue('transform') === 'none' ? 'none' : 'scale(1.02)';
        if (el.style.getPropertyValue('transform') !== want) {
          el.style.setProperty('transform', want, 'important');
        }
        if (el.style.getPropertyValue('animation') !== 'none') {
          el.style.setProperty('animation', 'none', 'important');
        }
      });
    }

    holdStill();

    if (window.MutationObserver) {
      var watch = new MutationObserver(holdStill);
      layers.forEach(function (el) {
        watch.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
      });
    }

    /* the window resizing changes every number above */
    var t;
    window.addEventListener('resize', function () {
      clearTimeout(t);
      t = setTimeout(function () {
        /* Dragging a desktop window narrow, or rotating a tablet, must
           not leave the phone with a stretched <html> that was correct
           at the width it was written at. Hand the inline patches back
           before standing down. */
        if (!isWide()) { unseal(); return; }
        widenShortAncestors(); sealRight(); spillRight(); report();
      }, 150);
    });

    /* Everything this block writes, in one place, so it can all be
       taken off again. Inline properties only — no stylesheet rule is
       touched anywhere above, so removing these is a complete undo. */
    function unseal() {
      var badge = document.getElementById('heroGapReport');
      if (badge && badge.parentNode) badge.parentNode.removeChild(badge);

      section.style.removeProperty('overflow');
      section.style.removeProperty('--hero-bleed');

      layers.forEach(function (el) {
        ['max-width', 'left', 'right', 'width', 'height', 'object-fit']
          .forEach(function (p) { el.style.removeProperty(p); });
      });

      var scrim = section.querySelector('.hero-feature__scrim');
      if (scrim) {
        scrim.style.removeProperty('right');
        scrim.style.removeProperty('width');
      }

      var el = section, guard = 0;
      while (el && guard++ < 12) {
        ['box-sizing', 'max-width', 'margin-right', 'padding-right',
         'border-right-width', 'width']
          .forEach(function (p) { el.style.removeProperty(p); });
        if (el === document.documentElement) break;
        el = el.parentElement;
      }
    }
  })();


  /* ---- the switch ---------------------------------------------------- */
  var tag  = document.getElementById('heroSlides');
  var rail = document.getElementById('heroRail');
  if (!tag || !rail) return;

  var SLIDES = [];
  try { SLIDES = JSON.parse(tag.textContent) || []; } catch (e) { return; }
  if (SLIDES.length < 2) return;

  /* TWO photo layers, not one. They take turns: one holds the picture
     you are looking at while the other has the next one decoded into it
     and fades up over the top. See the crossfade note in
     assets/destinations-hero.css.

     photoB is optional. If the markup has not been updated it will be
     null, and everything below falls back to the old single-layer fade
     — worse looking, but not broken. */
  var photoA = document.getElementById('heroPhoto');
  var photoB = document.getElementById('heroPhotoB');
  var live   = photoA;

  var text  = document.querySelector('.hero-feature__text');
  var chip  = document.getElementById('heroTag');
  var loc   = document.getElementById('heroLoc');
  var title = document.getElementById('heroTitle');
  var desc  = document.getElementById('heroDesc');
  var cta   = document.getElementById('heroCta');
  var index = document.getElementById('heroIndex');
  var fill  = document.getElementById('heroFill');
  var prev  = document.getElementById('heroPrev');
  var next  = document.getElementById('heroNext');
  if (!photoA || !title) return;

  var cards   = rail.querySelectorAll('.hero-rail__card');

  /* ---- CENTRE A CARD, AND MOVE NOTHING ELSE --------------------------

     This replaces card.scrollIntoView({inline:'center'}).

     scrollIntoView is the wrong tool here and it was the bug. It does
     not scroll one element — it walks the whole ancestor chain and
     scrolls EVERY scroll container it finds until the target is where
     it was asked for. An overflow:hidden box is still a scroll
     container; hidden only takes away the user's scrollbar, not the
     browser's ability to scroll it. So the request to centre a card
     went past the rail and into the document, and the page slid
     sideways with no way back.

     This is the same job done arithmetically. Measure how far the
     card's centre is from the rail's centre, add that to the rail's
     own scrollLeft, clamp it to the scrollable range. Nothing above
     the rail is asked for anything.

     getBoundingClientRect, not offsetLeft: offsetLeft is measured from
     the nearest POSITIONED ancestor, and the rail is position:absolute
     above 1100px but position:static below it — so offsetLeft silently
     changes what it is relative to at the breakpoint. Client rects are
     viewport-relative for both, and the subtraction cancels the
     viewport out.

     scroll-behavior on .hero-rail is already auto in the stylesheet,
     deliberately, so behavior here is honoured. See the note there
     before setting smooth in CSS again. */
  function centreCard(card) {
    if (!card) return;

    var cr = card.getBoundingClientRect();
    var rr = rail.getBoundingClientRect();
    var max  = rail.scrollWidth - rail.clientWidth;
    var want = rail.scrollLeft +
               (cr.left + cr.width / 2) - (rr.left + rr.width / 2);

    want = Math.max(0, Math.min(want, max));

    if (rail.scrollTo) {
      rail.scrollTo({ left: want, behavior: still ? 'auto' : 'smooth' });
    } else {
      rail.scrollLeft = want;
    }
  }
  var current = 0;   /* PHP renders SLIDES[0] and marks its card */
  var busy    = false;

  /* Someone who has asked their system for less motion gets the words
     and the picture, and none of the travel. The stagger, the drift and
     the crossfade are all skipped rather than shortened — a fast
     version of an effect is still the effect. */
  var still = window.matchMedia &&
              window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var FADE = still ? 0 : 280;   /* how long the words are away for. Moves
                                   with the .24s exit in section 18.2. */

  function pad(n) { return n < 10 ? '0' + n : String(n); }

  /* --- how big the name is allowed to be ----------------------------
     destinations-hero.css section 8 sets ONE font-size for the title
     and leaves it there. That works for "Bagasbas Beach" and falls
     apart on "St. Francis of Assisi Parish Church" — same size, four
     lines instead of one, and the block grows up through the search
     box.

     THE SIZE IS NOT WHAT SHOULD STAY CONSTANT. THE LINE COUNT IS.

     This writes data-fit onto the heading from the character count.
     Section 16.2 of the stylesheet picks the size, the leading AND the
     tracking off that attribute, so a long name is set smaller and
     still lands on two lines.

     The buckets are the real name lengths in destinations-data.php:

       14  Bagasbas Beach                        xxl
       20  First Rizal Monument                  xl
       29  La Maestra Campsite and Falls         lg
       35  St. Francis of Assisi Parish Church   md

     Add a name longer than 42 characters and it lands in sm, which is
     the floor — there is nothing below it, so check that one by eye.

     It reads textContent rather than taking the name as an argument,
     so it is correct whether it is called after a swap or on the
     heading PHP rendered. */
  function fitTitle() {
    var n = (title.textContent || '').replace(/\s+/g, ' ').trim().length;
    title.setAttribute(
      'data-fit',
      n <= 14 ? 'xxl' :
      n <= 20 ? 'xl'  :
      n <= 30 ? 'lg'  :
      n <= 42 ? 'md'  : 'sm'
    );
  }

  /* the first photo is already on screen, so it starts as the live
     layer and the spare starts out of the way */
  if (photoB) {
    photoA.classList.add('is-live');
    photoB.classList.add('is-idle');
  }

  fitTitle();      /* PHP rendered the first name; size it before paint */
  setProgress(0);

  function setProgress(i) {
    if (fill) fill.style.width = ((i + 1) / SLIDES.length * 100) + '%';
  }

  /* everything the block SAYS, rewritten in place. The elements are
     never replaced, only their contents — so focus survives a switch,
     which it would not if the block were rebuilt. */
  function writeText(i, s) {
    if (chip)  chip.textContent  = s.tag;
    title.textContent = s.name;
    fitTitle();                  /* the new name may be a different length */
    if (desc)  desc.textContent  = s.desc;
    if (loc) {
      var span = loc.querySelector('span');
      if (span) span.textContent = s.town + ', Camarines Norte';
    }
    if (cta) cta.setAttribute('href', '#dest-' + s.slug);
  }

  /* the crossfade itself. The new photo is decoded BEFORE either layer
     moves, so the fade is never showing a half-painted image — and if
     the file is slow or missing, the 600ms cap means the block carries
     on rather than sitting still waiting for it. */
  function swapPhoto(src) {
    if (!photoB) {                       /* old single-layer fallback */
      photoA.src = src;
      return;
    }

    var incoming = (live === photoA) ? photoB : photoA;
    var outgoing = live;

    /* START FROM NOTHING, ALWAYS. The outgoing layer holds at full
       opacity underneath for the length of the fade (see 18.2), so a
       second click landing before that finishes would otherwise find
       the incoming layer still fully opaque and snap the new picture
       in with no fade at all. Transitions off, opacity to 0, one
       forced reflow so the browser commits it, transitions back. */
    incoming.style.transition = 'none';
    incoming.style.opacity = '0';
    void incoming.offsetWidth;
    incoming.style.removeProperty('transition');
    incoming.style.removeProperty('opacity');

    incoming.src = src;

    function cross() {
      incoming.classList.remove('is-idle');
      incoming.classList.add('is-live');
      outgoing.classList.remove('is-live');
      outgoing.classList.add('is-idle');
    }

    /* DECODED ON THE ELEMENT ITSELF, not on a spare Image with the same
       src. The warm-up in show() only guarantees the file is in the
       cache; this guarantees THIS <img> has it painted and ready. The
       difference is a frame of the previous-but-one photograph at the
       start of the fade, on the first pass through the set. */
    if (incoming.decode) {
      incoming.decode().then(cross).catch(cross);
    } else {
      cross();
    }

    live = incoming;
  }

  function show(want) {
    if (busy) return;

    /* wrap at both ends. A disabled arrow on a set this size is a dead
       control most of the time, and there is no first or last
       destination in any real sense. */
    var i = (want % SLIDES.length + SLIDES.length) % SLIDES.length;
    if (i === current) return;

    var s = SLIDES[i];
    if (!s) return;

    busy = true;

    /* the ring, the rail's scroll and the progress bar move NOW, not
       after the fade. The click was on the rail, so the rail is where
       the eye is — waiting a quarter second to acknowledge it reads as
       a dropped click. */
    var card = cards[i];
    var lit  = rail.querySelector('.hero-rail__card.is-current');
    if (lit) lit.classList.remove('is-current');
    if (card) {
      card.classList.add('is-current');
      centreCard(card);
    }
    setProgress(i);

    /* the counter moves with the bar, not with the words. They are the
       same fact in two forms and they sit next to each other — a bar
       already at 02 beside a counter still reading 01 for a quarter
       second is the kind of small wrongness people notice without being
       able to say what they noticed. */
    if (index) index.textContent = pad(i + 1);

    /* current moves NOW. Everything below is presentation; the block is
       already committed to this destination, and a second click landing
       mid-swap should be measured from where we are going rather than
       from where we were. */
    current = i;

    /* ---- the words ----
       On their own timer, NOT chained to the photograph. This is the
       whole reason the two are separated:

       A cached photo decodes in the same tick as the click. If the text
       swap hangs off that, .is-swapping goes on and comes back off
       inside one frame, the browser never paints the faded-out state,
       and the stagger silently does not happen — on exactly the photos
       that load fastest, which is most of them after the first pass.
       The effect would appear to work on a cold load and never again.

       So: the words always take FADE to leave and always come back
       after it, whatever the network is doing. */
    if (text) text.classList.add('is-swapping');

    setTimeout(function () {
      writeText(i, s);
      if (text) text.classList.remove('is-swapping');
    }, FADE);

    /* ---- the photograph ----
       Decode first, then cross it. Whichever of the load, the error or
       the 600ms cap comes back first wins; the guard stops it running
       twice. The cap is what keeps a slow or missing file from holding
       the picture on the last destination indefinitely. */
    var done = false;
    var warm = new Image();

    function go() {
      if (done) return;
      done = true;
      clearTimeout(cap);

      swapPhoto(s.image);
      var section = live.closest ? live.closest('.hero-feature') : null;
      if (section) section.classList.remove('is-photoless');
    }

    warm.onload  = go;
    warm.onerror = go;          /* a 404 should not freeze the section */
    warm.src = s.image;

    var cap = setTimeout(go, 600);
    if (warm.complete) go();    /* already in cache */

    /* held until the words are back, so a fast second click cannot
       start a new swap on top of one already mid-flight. Not until the
       PHOTO settles — that is .8s, and locking the arrows for most of a
       second makes the stepper feel stuck. The crossfade is happy to be
       interrupted; the text swap is not. */
    setTimeout(function () { busy = false; }, FADE + 60);
  }

  /* ===================================================================
     THE ROW ITSELF

     Three ways to move it, on top of the arrows and the arrow keys:
     the wheel, a drag, and the cards arriving as they scroll in. This
     row is the highlights of the whole province, and a horizontal
     scroller with a hidden scrollbar is very easy to not realise is
     scrollable at all.
     =================================================================== */

  /* ---- cards arrive as they scroll in --------------------------------
     .has-reveal is added HERE rather than sitting in the markup, and
     that ordering is the safety net: the class that hides the cards is
     only ever applied by the code that can also un-hide them. No
     script, no observer, no class — every card renders plainly.

     The observer's root is the RAIL, not the viewport, so the trigger
     is a card scrolling into the row rather than the row scrolling into
     the page. Those are two different movements and only the first one
     is the one being answered.

     Cards are unobserved once seen. A card that fades back out when it
     leaves and in again when it returns turns a browse into a
     flickering strip. */
  if ('IntersectionObserver' in window && !still) {
    rail.classList.add('has-reveal');

    var seen = new IntersectionObserver(function (rows) {
      rows.forEach(function (row) {
        if (!row.isIntersecting) return;
        row.target.classList.add('is-in');
        seen.unobserve(row.target);
      });
    }, {
      root: rail,
      /* a card starts arriving just before its edge clears the rail, so
         it is already settled by the time it is fully in view */
      rootMargin: '0px 15% 0px 0px',
      threshold: 0.01
    });

    var items = rail.querySelectorAll('li');
    items.forEach(function (li, n) {
      /* the cards already on screen at load come in as a run rather
         than all together — capped, so a filtered page of three does
         not wait on a stagger built for twenty-four */
      li.style.transitionDelay = (Math.min(n, 8) * 0.05) + 's';
      seen.observe(li);
    });

    /* the delays are for the first pass only. Left in place they would
       also apply to the hover lift and to anything else that ever
       transitions on these rows. */
    setTimeout(function () {
      items.forEach(function (li) { li.style.transitionDelay = ''; });
    }, 1600);
  }

  /* ---- the wheel scrolls it sideways ---------------------------------
     A trackpad or a mouse over a horizontal row: the natural gesture is
     to scroll, and the natural result is the page moving instead while
     the row you are pointing at sits still.

     THE ROW GIVES THE PAGE ITS SCROLL BACK AT THE ENDS. Once the row
     has nothing left in the direction being asked for, the event is
     left alone and the page takes over — otherwise the rail becomes a
     trap you have to steer around to get down the page.

     Horizontal wheel input (deltaX, from a trackpad swipe) is left
     entirely alone. The browser already does the right thing with it,
     and intercepting it doubles the distance travelled. */
  rail.addEventListener('wheel', function (e) {
    if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
    if (!e.deltaY) return;

    var max  = rail.scrollWidth - rail.clientWidth;
    var atEnd = (e.deltaY > 0 && rail.scrollLeft >= max - 1) ||
                (e.deltaY < 0 && rail.scrollLeft <= 0);
    if (atEnd) return;                 /* hand it back to the page */

    e.preventDefault();
    /* behavior:'auto' explicitly — the stylesheet sets smooth on this
       element, and smoothing a wheel makes it feel like it is catching
       up rather than tracking */
    rail.scrollBy({ left: e.deltaY, behavior: 'auto' });
  }, { passive: false });

  /* ---- drag to scroll -------------------------------------------------
     Pointer events, so one code path covers mouse, pen and touch —
     except that touch already scrolls the row natively, and taking that
     over would replace a momentum scroll with a worse one. So touch is
     left to the browser.

     THE SLOP THRESHOLD IS WHAT KEEPS THE CARDS CLICKABLE. Every card is
     a link, and a click is a press and release with a bit of movement
     in between. Under 5px is a click; past it the gesture has committed
     to being a drag, and the click that follows on release is
     swallowed. */
  var dragging = false, dragged = false, startX = 0, startScroll = 0;

  rail.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'touch' || e.button !== 0) return;
    dragging = true;
    dragged  = false;
    startX = e.clientX;
    startScroll = rail.scrollLeft;
  });

  rail.addEventListener('pointermove', function (e) {
    if (!dragging) return;
    var dx = e.clientX - startX;

    if (!dragged && Math.abs(dx) > 5) {
      dragged = true;
      rail.classList.add('is-dragging');
      /* the pointer keeps reporting to the rail even when it leaves it,
         so a drag that runs off the edge of the row does not just stop
         dead halfway */
      if (rail.setPointerCapture) {
        try { rail.setPointerCapture(e.pointerId); } catch (err) {}
      }
    }

    if (dragged) {
      e.preventDefault();
      rail.scrollLeft = startScroll - dx;
    }
  });

  function endDrag() {
    if (!dragging) return;
    dragging = false;
    rail.classList.remove('is-dragging');
    /* dragged stays true until the click it produced has been eaten */
  }

  rail.addEventListener('pointerup', endDrag);
  rail.addEventListener('pointercancel', endDrag);
  rail.addEventListener('pointerleave', endDrag);

  /* ---- the click a drag leaves behind --------------------------------
     Capture phase, so this runs before the delegated handler below and
     before the anchor's own default. A drag that ends on top of a card
     would otherwise both scroll the row AND change the destination. */
  rail.addEventListener('click', function (e) {
    if (!dragged) return;
    dragged = false;
    e.preventDefault();
    e.stopPropagation();
  }, true);

  /* delegated: twenty-four cards, one listener */
  rail.addEventListener('click', function (e) {
    var card = e.target.closest ? e.target.closest('.hero-rail__card') : null;
    if (!card) return;

    var i = parseInt(card.getAttribute('data-slide'), 10);
    if (isNaN(i) || !SLIDES[i]) return;

    /* Clicking the card that is ALREADY showing means the person wants
       the thing the block is offering, so let the #anchor run and take
       them to it. Every other card switches the section instead. */
    if (i === current) return;

    e.preventDefault();
    show(i);
  });

  if (prev) prev.addEventListener('click', function () { show(current - 1); });
  if (next) next.addEventListener('click', function () { show(current + 1); });

  /* left and right arrow keys, but only once the rail or a stepper
     button has focus — binding them to the document would hijack the
     arrow keys for the whole page, including the map below. */
  function onKey(e) {
    if (e.key === 'ArrowLeft')  { e.preventDefault(); show(current - 1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); }
  }
  rail.addEventListener('keydown', onKey);
  if (prev) prev.addEventListener('keydown', onKey);
  if (next) next.addEventListener('keydown', onKey);
})();