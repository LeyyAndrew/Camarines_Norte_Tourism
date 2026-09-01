/* ===================================================================
   assets/js/destinations-map.js

   The Leaflet map on destinations.php: pins, the hover balloon, the
   detail sheet, "Show on map", the reset button, and the re-measure
   that stops the map from painting grey tiles.

   TWO THINGS HAPPEN AT A PIN NOW, and they are deliberately different
   sizes:

     HOVER  a small balloon — photograph, tag, town, name. Enough to
            recognise the place without committing to anything. It
            follows the mouse in and out and leaves nothing behind.

     CLICK  the detail sheet: the same photograph large, the
            description, how to get there, what to eat, and who to
            book with. This is the panel that answers "so how do I
            actually go?", which is the question a pin on a map
            always raises and almost never answers.

   The old behaviour — click a pin, scroll to its card — is still
   here, moved to a button at the bottom of the sheet. Nothing that
   worked before stopped working.
   =================================================================== */
(function () {

  /* =================================================================
     THE DATA

     Printed into the page by destinations.php as a JSON data island —
     see #destMapPoints near the bottom of that file. Reading it from
     the DOM is what lets this stay a plain cacheable .js asset
     instead of a block of PHP-generated JavaScript.
     ================================================================= */
  var tag    = document.getElementById('destMapPoints');
  var POINTS = [];
  try { POINTS = tag ? JSON.parse(tag.textContent) : []; }
  catch (e) { POINTS = []; }

  var BY_SLUG = {};
  POINTS.forEach(function (p, i) { p._n = i + 1; BY_SLUG[p.slug] = p; });

  /* Anything printed into HTML below goes through this first. The
     copy in destinations-data.php is ours, but it is also the file a
     tourism officer edits, and an apostrophe in "Mananap's" should
     print as an apostrophe rather than end an attribute. */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* Several of the photographs in uploads/ are still missing or
     misnamed. A broken <img> renders as an icon and a filename, which
     looks like a bug; the graded panel underneath looks intentional.
     So a failed load hides the image and lets the panel show. */
  function guardImages(root) {
    root.querySelectorAll('img[data-guard]').forEach(function (img) {
      img.addEventListener('error', function () {
        img.remove();
        var holder = root.querySelector('.' + img.getAttribute('data-guard'));
        if (holder) holder.classList.add('is-noimg');
      });
    });
  }


  /* =================================================================
     THE DETAIL SHEET

     Built once, from JavaScript, and appended to <body>. Not printed
     by PHP, for two reasons: twenty-four hidden dialogs in the markup
     is twenty-four copies of every photograph in the DOM on first
     paint, and a dialog nested inside .dest-layout inherits that
     section's stacking context and ends up trapped under the map.

     It is deliberately built BEFORE the Leaflet check below. If the
     CDN is down the map is gone, but the sheet still opens from
     anything carrying data-detail — so the cards keep working.
     ================================================================= */
  var sheet, panel, lastFocus = null, openSlug = null;

  function buildSheet() {
    sheet = document.createElement('div');
    sheet.className = 'dest-sheet';
    sheet.id = 'destSheet';
    sheet.hidden = true;
    sheet.innerHTML =
      '<div class="dest-sheet__scrim" data-sheet-close></div>' +
      '<div class="dest-sheet__panel" role="dialog" aria-modal="true"' +
      '     aria-labelledby="destSheetName" tabindex="-1">' +
      '  <button type="button" class="dest-sheet__x" data-sheet-close aria-label="Close">' +
      '    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '      <line x1="6" y1="6" x2="18" y2="18"></line>' +
      '      <line x1="18" y1="6" x2="6" y2="18"></line>' +
      '    </svg>' +
      '  </button>' +
      '  <div class="dest-sheet__scroll"></div>' +
      '</div>';
    document.body.appendChild(sheet);
    panel = sheet.querySelector('.dest-sheet__panel');

    sheet.addEventListener('click', function (e) {
      if (e.target.closest('[data-sheet-close]')) closeSheet();
    });
  }

  /* one section of the sheet: an orange-ruled label and a list.
     Returns '' when there is nothing to show, so a destination with
     no food notes simply has no food heading rather than an empty
     one — an empty heading reads as a page that failed to load. */
  function section(label, items, cls) {
    if (!items || !items.length) return '';
    return '<section class="dest-sheet__block' + (cls ? ' ' + cls : '') + '">' +
           '<h3 class="dest-sheet__label">' + esc(label) + '</h3>' +
           '<ul class="dest-sheet__list">' +
           items.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') +
           '</ul></section>';
  }

  /* The booking block. Falls back to the provincial contact set in
     includes/destination-details.php whenever a destination has no
     operator of its own, which is most of them today. */
  function bookingBlock(p) {
    var b = p.book || {};
    var rows = [];

    if (b.org)   rows.push('<li class="dest-sheet__who">' + esc(b.org) + '</li>');
    if (b.phone) rows.push('<li><a href="tel:' + esc(b.phone.replace(/[^\d+]/g, '')) + '">' + esc(b.phone) + '</a></li>');
    if (b.email) rows.push('<li><a href="mailto:' + esc(b.email) + '">' + esc(b.email) + '</a></li>');
    if (b.fb)    rows.push('<li><a href="' + esc(b.fb) + '" target="_blank" rel="noopener">Facebook page</a></li>');

    var packs = '';
    if (b.packages && b.packages.length) {
      packs = '<ul class="dest-sheet__packs">' + b.packages.map(function (k) {
        return '<li>' +
               '<span class="dest-sheet__pack-name">' + esc(k.name || '') + '</span>' +
               (k.detail ? '<span class="dest-sheet__pack-detail">' + esc(k.detail) + '</span>' : '') +
               (k.price  ? '<span class="dest-sheet__pack-price">' + esc(k.price)  + '</span>' : '') +
               '</li>';
      }).join('') + '</ul>';
    }

    if (!rows.length && !packs && !b.note) return '';

    return '<section class="dest-sheet__block dest-sheet__block--book">' +
           '<h3 class="dest-sheet__label">Booking and contact</h3>' +
           (rows.length ? '<ul class="dest-sheet__contact">' + rows.join('') + '</ul>' : '') +
           packs +
           (b.note ? '<p class="dest-sheet__note">' + esc(b.note) + '</p>' : '') +
           '</section>';
  }

  /* Shown in place of a section that has no data yet. Says which
     section is missing and who is expected to supply it, because
     "coming soon" tells a visitor nothing and tells the tourism
     office nothing either. */
  function pending(what) {
    return '<section class="dest-sheet__block dest-sheet__block--pending">' +
           '<h3 class="dest-sheet__label">' + esc(what) + '</h3>' +
           '<p class="dest-sheet__pendtext">Being confirmed with the municipal tourism office. ' +
           'Ask the provincial office below before you travel.</p>' +
           '</section>';
  }

  function fillSheet(p) {
    var scroll = sheet.querySelector('.dest-sheet__scroll');
    var dirs   = (p.lat && p.lng)
      ? 'https://www.google.com/maps/dir/?api=1&destination=' + p.lat + ',' + p.lng
      : '';

    scroll.innerHTML =

      /* --- the photograph, full width of the panel --- */
      '<figure class="dest-sheet__shot">' +
      (p.image ? '<img class="dest-sheet__img" data-guard="dest-sheet__shot" src="' + esc(p.image) + '" alt="">' : '') +
      '  <figcaption class="dest-sheet__caption">' +
      '    <span class="dest-sheet__tag">' + esc(p.tag || '') + '</span>' +
      '    <span class="dest-sheet__town">' + esc(p.town || '') + ', Camarines Norte</span>' +
      '  </figcaption>' +
      '</figure>' +

      '<div class="dest-sheet__text">' +

      '  <h2 class="font-display dest-sheet__name" id="destSheetName">' + esc(p.name) + '</h2>' +
      (p.quote ? '<p class="dest-sheet__quote">' + esc(p.quote) + '</p>' : '') +
      (p.desc  ? '<p class="dest-sheet__desc">'  + esc(p.desc)  + '</p>' : '') +

      (p.chips && p.chips.length
        ? '<ul class="dest-sheet__facts">' +
          p.chips.map(function (c) { return '<li>' + esc(c) + '</li>'; }).join('') +
          '</ul>'
        : '') +

      (p.how && p.how.length ? section('Getting there', p.how) : pending('Getting there')) +
      (p.eat && p.eat.length ? section('What to eat',   p.eat) : pending('What to eat')) +
      bookingBlock(p) +

      /* --- the two ways out of the sheet ---
         "Read the full card" is the old pin behaviour, kept: it closes
         the sheet and scrolls the matching card into view. */
      '  <div class="dest-sheet__actions">' +
      '    <button type="button" class="dest-sheet__go" data-sheet-card="' + esc(p.slug) + '">' +
      '      Read the full card' +
      '    </button>' +
      (dirs ? '<a class="dest-sheet__dirs" href="' + esc(dirs) + '" target="_blank" rel="noopener">Directions</a>' : '') +
      '  </div>' +

      /* The coordinates are exact now — see the $coords block in
         destinations.php. What is still worth saying, right where
         somebody is about to press Directions, is that a correct pin
         and a drivable road are two different promises. */
      (dirs ? '<p class="dest-sheet__fineprint">This pin is the destination itself. The last stretch to some falls and islands is unpaved or by boat &mdash; ask locally about conditions before you set out.</p>' : '') +

      '</div>';

    guardImages(scroll);
  }

  function openSheet(slug) {
    var p = BY_SLUG[slug];
    if (!p || !sheet) return;

    lastFocus = document.activeElement;
    openSlug  = slug;
    fillSheet(p);

    sheet.hidden = false;
    /* a frame's gap so the opening transition has a state to move from */
    requestAnimationFrame(function () { sheet.classList.add('is-open'); });

    document.body.classList.add('dest-sheet-open');
    panel.scrollTop = 0;
    panel.focus();
  }

  function closeSheet() {
    if (!sheet || sheet.hidden) return;
    sheet.classList.remove('is-open');
    document.body.classList.remove('dest-sheet-open');
    openSlug = null;

    /* hide only once the fade has finished, or the panel disappears
       instantly and the transition never plays */
    setTimeout(function () {
      sheet.hidden = true;
      sheet.querySelector('.dest-sheet__scroll').innerHTML = '';
    }, 220);

    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  buildSheet();

  /* Escape closes, and Tab is kept inside the panel while it is open —
     a dialog that lets the keyboard wander behind it is a dialog only
     the mouse can use. */
  document.addEventListener('keydown', function (e) {
    if (sheet.hidden) return;
    if (e.key === 'Escape') { closeSheet(); return; }
    if (e.key !== 'Tab') return;

    var f = panel.querySelectorAll('a[href], button:not([disabled])');
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  /* ANY element with data-detail="slug" opens the sheet. That is the
     hook to put on a card if you want "View details" to open this
     panel instead of the auth gate — see the note in the reply. */
  document.addEventListener('click', function (e) {
    var hook = e.target.closest ? e.target.closest('[data-detail]') : null;
    if (!hook) return;
    e.preventDefault();
    openSheet(hook.getAttribute('data-detail'));
  });

  /* "Read the full card" inside the sheet */
  document.addEventListener('click', function (e) {
    var go = e.target.closest ? e.target.closest('[data-sheet-card]') : null;
    if (!go) return;
    var slug = go.getAttribute('data-sheet-card');
    closeSheet();
    setTimeout(function () { highlight(slug, true); }, 240);
  });

  /* the card flash, unchanged from before */
  function highlight(slug, scroll) {
    var card = document.getElementById('dest-' + slug);
    if (!card) return;
    document.querySelectorAll('.dest-card.is-located').forEach(function (c) {
      c.classList.remove('is-located');
    });
    card.classList.add('is-located');
    if (scroll) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(function () { card.classList.remove('is-located'); }, 2600);
  }


  /* =================================================================
     THE MAP

     Everything from here down is defensive on purpose. Leaflet loads
     from a CDN, and if that request fails the rest of the page has to
     carry on working — a destinations index that renders nothing
     because a map library timed out is worse than one with no map.
     ================================================================= */
  var host = document.getElementById('destMap');
  if (!host) return;

  if (typeof L === 'undefined') {
    host.innerHTML = '<p class="dest-map__fail">The map could not load. Everything else on this page still works.</p>';
    return;
  }
  if (!POINTS.length) {
    host.innerHTML = '<p class="dest-map__fail">No mapped destinations in this filter.</p>';
    return;
  }

  var map = L.map(host, {
    scrollWheelZoom: false,   /* otherwise the map eats the page scroll */
    zoomControl: true
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  /* =================================================================
     THE PIN

     This was a 26px round dot anchored at its own centre, which is a
     lie: the centre of a circle marks nothing, so a dot straddling a
     coastline could be read as the beach or the water and a reader had
     no way to tell which. A teardrop has a point, the point is the
     coordinate, and the anchor below puts that point exactly on it.

     Everything the pin needs is in this file — the SVG, the gradient,
     and the rules that hold it together. Nothing here waits on a
     stylesheet, so the pin cannot half-load into a white square if
     destinations.css is cached old. Move it into the stylesheet
     whenever you like; the class names are already namespaced for it.
     ================================================================= */
  /* GEOMETRY, so nobody has to reverse-engineer the path below.

     The classic pin is not a teardrop drawn by feel. It is a circle
     with the two tangent lines that run to the tip, which is why the
     spike meets the head without a seam and why the sides look
     straight without being straight. Head is r14 at (16,16), tip at
     (16,42), so the tip sits 26 from the centre. The tangent points
     fall out of that: cos(t) = 14/26, giving (4.2, 23.54) and its
     mirror, and an arc of 245.2 degrees over the top — hence the
     large-arc flag in the path.

     CHANGE ANY ONE OF THESE AND THE PATH IS WRONG. The tangent points
     are baked in as numbers because Leaflet builds this string
     twenty-four times and trigonometry per pin is silly, but that
     means the four values move together or not at all. Resizing is
     therefore not a matter of editing PIN_W: recompute the tangents
     from the new radius, or scale every number here by the same
     factor. This set is the 38x52 original at 0.824.

     WHY SMALLER. At 38 wide the pins were winning an argument they
     should not have been in — the map is a locator above the card
     grid, not the subject of the page, and seven overlapping pins
     around San Vicente read as one red mass. Below about 30 the
     two-digit numbers stop being legible, so this is close to the
     floor rather than a midpoint. */
  var PIN_W = 32, PIN_H = 44, PIN_TIP = 42;   /* 2px below the tip for the shadow */
  var PIN_PATH = 'M16 42 L4.2 23.54 A14 14 0 1 1 27.8 23.54 Z';

  (function pinAssets() {
    /* the gradient, defined once and referenced by every pin. A hidden
       0x0 SVG rather than one <defs> per marker: twenty-four copies of
       the same gradient is twenty-four ids to keep unique for no gain. */
    var defs = document.createElement('div');
    defs.setAttribute('aria-hidden', 'true');
    defs.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
    defs.innerHTML =
      '<svg xmlns="http://www.w3.org/2000/svg"><defs>' +
      '<linearGradient id="destPinGrad" x1="0" y1="0" x2="0" y2="1">' +
      /* barely a ramp. The reference is one flat red; a strong
         gradient here reads as a glossy button rather than a pin. */
      '<stop offset="0" stop-color="#EF2A33"/><stop offset="1" stop-color="#D5121C"/>' +
      '</linearGradient></defs></svg>';
    document.body.appendChild(defs);

    var css = document.createElement('style');
    css.textContent = [
      /* Leaflet's own .leaflet-marker-icon and any older .dest-pin rule
         both land on this element, so the box is reset before it is
         rebuilt. !important is load-order insurance, not taste. */
      '.leaflet-marker-icon.dest-pin--drop{',
      'background:none!important;border:0!important;border-radius:0!important;',
      'box-shadow:none!important;width:' + PIN_W + 'px!important;height:' + PIN_H + 'px!important;',
      'line-height:normal!important;text-align:left!important;color:inherit!important;',
      'display:block!important;padding:0!important;margin:0!important;}',

      /* the lift lives on the inner SVG, never on the icon element —
         Leaflet owns that element's transform to position it, and a
         second transform there would fight the map on every pan. */
      '.dest-pin--drop svg{display:block;transform-origin:50% 100%;',
      'transition:transform .16s ease-out;',
      'filter:drop-shadow(0 1.5px 2.5px rgba(11,26,44,.45));}',
      '.dest-pin--drop:hover svg,.dest-pin--drop:focus svg{transform:scale(1.18);}',
      '.dest-pin--drop:focus{outline:none;}',
      '.dest-pin--drop:focus-visible svg{transform:scale(1.18);}',

      /* fill as a CSS property beats the presentation attribute on the
         path, so the active state needs no second icon and no re-render */
      '.dest-pin--drop.is-active svg{transform:scale(1.26);}',
      '.dest-pin--drop.is-active .dest-pin__shape{fill:#0E2A3D;}',
      /* the number is inside the knockout, so it recolours with the
         body or it turns into brown-on-white over a navy pin */
      '.dest-pin--drop.is-active .dest-pin__no{fill:#0E2A3D;}',

      /* THE BALLOON ARROW follows the horizontal clamp in placeTip.
         translateX composes with Leaflet's own margin-left:-6px rather
         than replacing it, so the arrow stays a triangle and simply
         slides back over the pin when the balloon has been pushed off
         centre by a map edge. If dest-tip draws its own arrow under a
         different selector, add that selector here. */
      '.dest-tip.leaflet-tooltip-top:before,.dest-tip.leaflet-tooltip-bottom:before{',
      'transform:translateX(var(--dest-tip-nudge,0px));}',
      '.dest-tip.leaflet-tooltip-left:before,.dest-tip.leaflet-tooltip-right:before{',
      'transform:translateY(var(--dest-tip-nudge-y,0px));}',
      /* it is measured before it is shown, so it must not be mid-fade
         while that happens — see placeTip */
      /* ================================================================
         WHY THESE ARE !important, WHICH IS NOT USUALLY AN ARGUMENT WORTH
         MAKING BUT IS HERE.

         The balloon kept rendering in the map's top-left corner no
         matter which pin was hovered. Not a DIFFERENT wrong place each
         time — the SAME one, which is the giveaway: bad arithmetic
         lands you somewhere bad, but discarded arithmetic lands you at
         the pane's origin, and the pane's origin is that corner.

         Leaflet positions a tooltip by writing a normal-priority inline
         transform. In the CSS cascade, animation declarations outrank
         normal inline styles. So any @keyframes on .dest-tip that
         touches transform — a slide-in, a pop, a scale — silently
         throws Leaflet's position away on every open. A stylesheet
         transform marked !important does the same, and so does
         .dest-tip overriding position to anything but absolute.

         Author !important sits ABOVE animations in the cascade, which
         is why the position is written that way below and why these
         rules are pinned here. Only the geometry is claimed: colour,
         padding, radius, shadow and opacity are left to your
         stylesheet, so a fade-in still fades. A transform-based
         entrance animation will no longer move it, because that is
         precisely the thing that was breaking it.
         ================================================================ */
      '.dest-tip{position:absolute!important;margin:0!important;',
      'left:0!important;top:0!important;right:auto!important;bottom:auto!important;',
      'transition:none!important;}',

      /* the point itself, for anyone checking alignment against a
         coastline at zoom 17 — a hairline, off by default */
      '.dest-map--crosshair .dest-pin__tip{opacity:.9;}',
      '.dest-pin__tip{opacity:0;}'
    ].join('');
    document.head.appendChild(css);
  })();

  /* THE KNOCKOUT IS FILLED WHITE, NOT TRANSPARENT, and that is a
     decision rather than a shortcut. A true hole — one path, two
     subpaths, fill-rule evenodd — is what the reference icon does,
     and it looks right until you put a number in it: the number then
     sits on whatever OSM tile happens to be underneath, which is pale
     green over farmland, mid-blue over water, and a road label often
     enough to matter. Twenty-four pins that are legible on some tiles
     is worse than a solid disc.

     If the numbers ever go away, swap the <circle> for a second
     subpath on the shape and add fill-rule="evenodd" — the geometry is
     already correct for it. */
  function pinFor(i) {
    return L.divIcon({
      className: 'dest-pin dest-pin--drop',
      html:
        '<svg viewBox="0 0 ' + PIN_W + ' ' + PIN_H + '" width="' + PIN_W + '" height="' + PIN_H + '"' +
        ' xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
        /* the white outline is not decoration: an orange pin over the
           orange-brown of a built-up OSM tile loses its edge without it */
        '<path class="dest-pin__shape" fill="url(#destPinGrad)" stroke="#FFFFFF"' +
        ' stroke-width="1.5" stroke-linejoin="round" d="' + PIN_PATH + '"/>' +
        /* r6.8, not the 6.59 that scaling 8 would give: two digits at
           font-size 9 sit tighter in the knockout than one, and the
           extra fifth of a pixel is what keeps 24 off the edge. */
        '<circle class="dest-pin__hole" cx="16" cy="16" r="6.8" fill="#FFFFFF"/>' +
        '<text class="dest-pin__no" x="16" y="16" dy="0.35em" text-anchor="middle"' +
        ' fill="#B3131C" font-size="9" font-weight="700"' +
        ' font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif">' + (i + 1) + '</text>' +
        '<circle class="dest-pin__tip" cx="16" cy="' + PIN_TIP + '" r="1.2" fill="#0E2A3D"/>' +
        '</svg>',
      iconSize:      [PIN_W, PIN_H],
      /* THE WHOLE POINT: the anchor is the tip, not the middle. */
      iconAnchor:    [PIN_W / 2, PIN_TIP],
      /* clears the head so the balloon does not sit on the number */
      /* ZERO ON PURPOSE. Leaflet adds tooltipAnchor to whatever the
         tooltip works out, in every direction, which made the balloon
         maths a negotiation between two formulas. Placement is owned
         outright in placeTip now, so this must contribute nothing. */
      tooltipAnchor: [0, 0]
    });
  }

  /* One pin at a time carries .is-active. Leaflet keeps the icon
     element on the marker, so this is a class swap rather than a
     rebuild — the pin does not flicker when the sheet opens. */
  var activeMarker = null;
  function setActivePin(m) {
    if (activeMarker && activeMarker._icon) activeMarker._icon.classList.remove('is-active');
    activeMarker = m || null;
    if (activeMarker && activeMarker._icon) activeMarker._icon.classList.add('is-active');
  }

  /* THE HOVER BALLOON.

     A Leaflet tooltip rather than a popup: a popup is a click object
     with a close button and it pans the map to fit itself, both of
     which are wrong for something that should appear and vanish with
     the mouse. The tooltip carries a photograph, which is the whole
     point — twenty-four numbered dots tell you nothing about which
     one is the white sand.

     THE PHOTO ONLY LOADS ON FIRST HOVER. Building the HTML here means
     the <img> is not created until Leaflet asks for the tooltip
     content, so a visitor who never touches the map never downloads
     twenty-four photographs a second time. */
  function balloon(p) {
    return '' +
      '<span class="dest-tip__shot">' +
      (p.image ? '<img class="dest-tip__img" data-guard="dest-tip__shot" src="' + esc(p.image) + '" alt="" loading="lazy">' : '') +
      '  <span class="dest-tip__no">' + p._n + '</span>' +
      '</span>' +
      '<span class="dest-tip__body">' +
      '  <span class="dest-tip__meta">' + esc(p.tag || '') + ' &middot; ' + esc(p.town || '') + '</span>' +
      '  <span class="dest-tip__name">' + esc(p.name) + '</span>' +
      '  <span class="dest-tip__cue">Click for details</span>' +
      '</span>';
  }

  /* =================================================================
     WHERE THE BALLOON GOES

     Previous versions of this asked Leaflet nicely: set
     options.direction, set options.offset, then call the private
     _updatePosition and hope Leaflet's own formula reproduced the
     intent. It did not, and the failure mode was the balloon parked
     in the top-left corner of the map with no relation to the pin,
     because three separate things were adding to the final number —
     Leaflet's direction maths, the icon's tooltipAnchor, and our
     offset — and only one of them was ours.

     So the arithmetic is no longer shared. This function IS
     Leaflet's positioner: it is grafted onto the tooltip instance as
     _setPosition, so every route that repositions a tooltip — open,
     zoom, pan, our own re-place after the photo loads — runs exactly
     this code and nothing else. There is no second formula left to
     disagree with.

     It works in container pixels, which is what the flip and the
     clamp are actually about (is this pin near the top of the panel,
     is it near the left edge), and converts to a layer point only at
     the very last line, because that is what Leaflet's DOM helper
     expects. Everything above that line is checkable by hand.
     ================================================================= */
  var TIP_GAP = 10;   /* breathing room between pin and balloon */
  var TIP_PAD = 8;    /* keep this much balloon inside the map edge */
  var openTip = null; /* the marker whose balloon is currently open */

  function positionerFor(m) {
    return function () {
      var el = this._container;
      if (!el || !this._map) return;

      var w = el.offsetWidth;
      var h = el.offsetHeight;
      var pt = map.latLngToContainerPoint(m.getLatLng());  /* the pin TIP */
      var size = map.getSize();

      /* FOUR PLACEMENTS, TRIED IN ORDER, and the order is the whole
         design. Above is the default because that is where a reader
         expects a label. Below is the flip. Beside is the one that
         matters here and that a naive flip does not have:

         THIS MAP IS SHORT. The panel is about 355 tall and a balloon
         carrying a photo is around 230. Clearing the pin needs
         h + gap + PIN_TIP going up, or h + gap going down, so above
         only fits for pins low in the panel and below only for pins
         high in it. Every pin in the middle band fits NEITHER, which
         is most of them. A two-way flip has nothing left to choose
         and picks one anyway, and the balloon hangs off the edge —
         that is the negative top the simulation caught.

         So when neither vertical placement fits, the balloon goes
         beside the pin instead, level with it. It stays attached to
         the thing being hovered, which is the point, and it cannot
         leave the panel. */
      var fitsAbove = (pt.y - PIN_TIP - TIP_GAP - h) >= TIP_PAD;
      var fitsBelow = (pt.y + TIP_GAP + h) <= (size.y - TIP_PAD);

      var side = '';   /* '' means a vertical placement won */
      var top, left;

      if (fitsAbove || fitsBelow) {
        var below = !fitsAbove;
        side = below ? 'bottom' : 'top';
        top  = below ? (pt.y + TIP_GAP) : (pt.y - PIN_TIP - TIP_GAP - h);

        /* centre on the pin, then push back inside the panel. The
           wider-than-map case is checked last and wins: without it a
           balloon too wide to fit satisfies the left test, gets pinned
           to TIP_PAD, and jams in the corner — which is what the
           screenshot showed. */
        left = pt.x - w / 2;
        if (left < TIP_PAD) left = TIP_PAD;
        else if (left + w > size.x - TIP_PAD) left = size.x - TIP_PAD - w;
        if (w > size.x - 2 * TIP_PAD) left = (size.x - w) / 2;
      } else {
        /* BESIDE. Level with the head of the pin rather than its tip,
           so the balloon reads as belonging to the round part a
           visitor is actually pointing at. */
        var head = pt.y - PIN_TIP + PIN_H / 2;
        var rightLeft = pt.x + PIN_W / 2 + TIP_GAP;
        var leftLeft  = pt.x - PIN_W / 2 - TIP_GAP - w;

        if (rightLeft + w <= size.x - TIP_PAD)  { side = 'right'; left = rightLeft; }
        else if (leftLeft >= TIP_PAD)           { side = 'left';  left = leftLeft;  }
        else {
          /* no room anywhere: overlap is now unavoidable, so choose the
             larger gap and clamp. Better a balloon over a pin than a
             balloon half outside the panel. */
          side = (pt.y > size.y / 2) ? 'top' : 'bottom';
          left = Math.max(TIP_PAD, Math.min(pt.x - w / 2, size.x - TIP_PAD - w));
        }
        top = head - h / 2;
      }

      /* final vertical clamp, belt and braces. Nothing above should be
         able to produce an out-of-panel top, but this is the one line
         that makes that a guarantee rather than an argument. */
      top = Math.max(TIP_PAD, Math.min(top, size.y - TIP_PAD - h));
      if (h > size.y - 2 * TIP_PAD) top = (size.y - h) / 2;

      /* THE ARROW sits at the middle of whichever edge it is drawn on,
         so it has to travel back by however far the clamp moved that
         middle away from the pin. Both axes, because a beside-placed
         balloon clamps vertically for the same reasons. */
      el.style.setProperty('--dest-tip-nudge',   (pt.x - (left + w / 2)) + 'px');
      el.style.setProperty('--dest-tip-nudge-y', ((pt.y - PIN_TIP + PIN_H / 2) - (top + h / 2)) + 'px');

      /* Leaflet's own direction classes drive which edge the arrow is
         drawn on, so they are kept in step by hand — the base class
         does not, and cannot, know about any of the above. */
      el.classList.remove('leaflet-tooltip-top', 'leaflet-tooltip-bottom',
                          'leaflet-tooltip-left', 'leaflet-tooltip-right');
      el.classList.add('leaflet-tooltip-' + side);

      /* DomUtil.setPosition first, so Leaflet's own bookkeeping
         (_leaflet_pos, which getPosition and the animation code read)
         stays truthful — then the same transform again at !important,
         which is the only priority a keyframe animation cannot
         outrank. Doing just the second would leave Leaflet's internal
         idea of where this element is permanently stale. */
      var lp = map.containerPointToLayerPoint(L.point(left, top));
      L.DomUtil.setPosition(el, lp);
      el.style.setProperty('transform',
        'translate3d(' + Math.round(lp.x) + 'px,' + Math.round(lp.y) + 'px,0)', 'important');

      /* Set window.DEST_TIP_DEBUG = true in the console, then hover a
         pin. If "wanted" and "got" disagree, something in the
         stylesheet is still overriding the position and the numbers
         will say by how much. */
      if (window.DEST_TIP_DEBUG) {
        var mb = map.getContainer().getBoundingClientRect();
        var tb = el.getBoundingClientRect();
        console.log('[dest-tip]', side,
          'wanted', Math.round(left) + ',' + Math.round(top),
          'got', Math.round(tb.left - mb.left) + ',' + Math.round(tb.top - mb.top),
          'size', Math.round(tb.width) + 'x' + Math.round(tb.height),
          'pin', Math.round(pt.x) + ',' + Math.round(pt.y));
      }
    };
  }

  /* Grafted on at open, once per tooltip. The guard matters: bindTooltip
     reuses one instance per marker, so without it every hover would wrap
     the previous wrapper. */
  function ownPlacement(m, tip) {
    if (tip._destOwned) return;
    tip._setPosition = positionerFor(m);
    tip._destOwned = true;
  }

  function placeTip(m) {
    var tip = m && m.getTooltip();
    if (!tip || !tip._map || !tip._container) return;
    /* _updatePosition, NOT the public update(). update() re-runs the
       content function, which rebuilds the <img> and restarts the load,
       which fires this again — a loop that also re-downloads the photo.
       _updatePosition just calls the positioner above. */
    if (typeof tip._updatePosition === 'function') tip._updatePosition();
  }

  /* THE PHOTO IS WHY THIS NEEDS RE-RUNNING AT ALL. It is lazily loaded
     with no reserved height, so at the instant the balloon opens it is a
     zero-height box and h is measured short. The photo then arrives and
     the balloon grows, but nothing moves it. That is also why the old
     bug looked random rather than merely wrong: a cached photo measured
     correctly and landed properly, a cold one did not. Same pin, two
     results, depending on the browser cache.

     error is handled as well as load, or a photo that 404s leaves the
     balloon stuck at its zero-height placement forever. */
  function replaceOnLoad(m, el) {
    var imgs = el.querySelectorAll('img');
    for (var i = 0; i < imgs.length; i++) {
      if (imgs[i].complete && imgs[i].naturalHeight) continue;
      imgs[i].addEventListener('load',  function () { placeTip(m); }, { once: true });
      imgs[i].addEventListener('error', function () { placeTip(m); }, { once: true });
    }
  }

  var markers = {};
  var group   = [];

  POINTS.forEach(function (p, i) {
    var m = L.marker([p.lat, p.lng], {
      icon: pinFor(i),
      title: p.name,          /* the native tooltip, for keyboard and screen readers */
      riseOnHover: true       /* a hovered pin comes out from under its neighbours */
    }).addTo(map);

    m.bindTooltip(function () { return balloon(p); }, {
      /* INERT. The positioner grafted on at open ignores both of
         these — they are here only because Leaflet reads them before
         the graft happens on the very first hover. Tuning them has no
         effect; edit TIP_GAP and TIP_PAD instead. */
      direction: 'top',
      offset: [0, 0],
      opacity: 1,
      className: 'dest-tip',
      /* Leaflet keeps a tooltip pinned to the pin by default, which is
         what we want — it should not chase the cursor around inside
         the balloon and flicker. */
      sticky: false
    });

    m.on('tooltipopen', function (e) {
      var el = e.tooltip.getElement();
      ownPlacement(m, e.tooltip); /* take over positioning, before anything else */
      guardImages(el);            /* the balloon's own images */
      openTip = m;
      placeTip(m);                /* place it properly, before paint */
      replaceOnLoad(m, el);       /* and again when the photo lands */
    });
    m.on('tooltipclose', function () { if (openTip === m) openTip = null; });

    /* CLICK OPENS THE SHEET. On a phone the same tap would otherwise
       open the tooltip too and leave it sitting there behind the
       panel, so it is closed on the way through. */
    m.on('click', function () {
      m.closeTooltip();
      setActivePin(m);
      openSheet(p.slug);
    });

    markers[p.slug] = m;
    group.push([p.lat, p.lng]);
  });

  var home = L.latLngBounds(group).pad(0.15);
  map.fitBounds(home);

  /* "Show on map" on a card: centre its pin and show its balloon */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-focus]') : null;
    if (!btn) return;
    var m = markers[btn.getAttribute('data-focus')];
    if (!m) return;
    /* 15, not 12. At 12 a pin is a dot on a province and the accuracy
       gained by moving these coordinates off the town centres is
       invisible; at 15 you can see which side of the river it is on. */
    map.setView(m.getLatLng(), 15, { animate: true });
    setActivePin(m);

    /* THE MAP IS ABOVE THE GRID ON EVERY SCREEN, not just on a phone,
       so this always has to bring it back into view — otherwise the
       balloon opens somewhere off the top of the window and the
       button looks like it did nothing.

       This used to be wrapped in matchMedia('(max-width: 979px)'),
       from when the map was a sticky sidebar already on screen on a
       desktop. That sidebar is gone; the media query was the only
       thing in this file that knew about it. */
    host.scrollIntoView({ behavior: 'smooth', block: 'start' });

    /* Opened after the scroll and the pan have STARTED but placed
       again after they have finished — Leaflet measures against the
       viewport as it is at that instant, and a smooth scroll means
       that instant is not the one the visitor ends up looking at. */
    setTimeout(function () {
      m.openTooltip();
      placeTip(m);
    }, 400);
    setTimeout(function () { if (openTip === m) placeTip(m); }, 900);
  });

  var reset = document.getElementById('mapReset');
  if (reset) reset.addEventListener('click', function () {
    map.closePopup();
    Object.keys(markers).forEach(function (k) { markers[k].closeTooltip(); });
    setActivePin(null);
    map.fitBounds(home);
  });

  /* A pin sitting comfortably mid-panel can be hard against an edge
     after a zoom or a pan, so the flip and the clamp are re-decided
     rather than left at whatever was true when the balloon opened.
     zoomend/moveend only — zoom fires continuously and re-measuring
     the balloon on every frame of an animation is wasted work. */
  map.on('zoomend moveend resize', function () { if (openTip) placeTip(openTip); });

  /* wheel zoom stays off so the map cannot hijack the page, but once
     someone has clicked into it, zooming is exactly what they want */
  map.on('focus', function () { map.scrollWheelZoom.enable(); });
  map.on('blur',  function () { map.scrollWheelZoom.disable(); });

  /* Leaflet is often laid out before it is visible, which leaves it
     holding the wrong dimensions and painting grey gaps where tiles
     should be. This forces a re-measure once the browser has settled,
     and again on resize. */
  setTimeout(function () { map.invalidateSize(); }, 300);
  window.addEventListener('resize', function () { map.invalidateSize(); });
})();