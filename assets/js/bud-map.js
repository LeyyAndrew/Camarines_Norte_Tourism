/* ====================================================================
   BUD.AI — MAP VIEW
   assets/bud-map.js

   Turns the 380px chat panel into a two-pane workspace: the
   conversation on the left, a live map of the 24 destinations on the
   right. Loaded after bud.js on every page that includes the widget.

   ---------------------------------------------------------------
   WHY THIS IS A SEPARATE FILE AND NOT AN EDIT TO bud.js

   bud.js works. It is a closed DOMContentLoaded scope with no exports,
   and the parts this needs to hook — a reply arriving, a photo strip
   being drawn — are local functions inside it.

   Rather than reopen that file and risk the chat itself, this watches
   #budLog with a MutationObserver. A message appearing in the log is a
   DOM change, and a DOM change is something any script can see. So:

     - bud.js is untouched. Delete this file and the widget is exactly
       what it was.
     - It does not matter WHO added the message. Preview mode, the real
       api/bud.php, or something you write next year — if it lands in
       the log, the map reacts.
     - There is no shared state to keep in sync, because there is no
       shared state.
   ---------------------------------------------------------------

   LEAFLET IS LOADED ON DEMAND, not on page load. Most visitors never
   open the chat and none of them should pay 42KB for a map they will
   not see. The first click on 'Map view' fetches it; every later one
   is instant. If the site already loads Leaflet (destinations.php
   does), that copy is reused and nothing is fetched at all.

   Tiles are CARTO's dark basemap: no API key, no billing account, and
   close to the dark treatment on the reference design. Attribution is
   required and is in the corner where Leaflet puts it. Leave it there.
   ==================================================================== */
(function () {
  'use strict';

  /* WHERE LEAFLET COMES FROM, in the order it is tried.

     A copy inside the project is first, and if one is there no request
     leaves the machine at all — see the data-leaflet-* attributes that
     includes/bud-widget.php writes when it finds leaflet/leaflet.js in
     any of the folders it already searches for bud.css.

     Then three CDNs rather than one. A single CDN is a single point of
     failure, and the ways it fails on a development machine are dull
     and common: an ad blocker with a broad filter list, a shields
     setting, a school or office network that allows only what it has
     been asked to allow, a firewall, or no internet at all. Any one of
     those turned a working map into a blank pane. All three have to be
     unreachable before that happens now.

     Pinned versions. 'latest' on a CDN means a stranger can change how
     your map behaves on a Tuesday. */
  var LEAFLET_CDNS = [
    { js:  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
      css: 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' },
    { js:  'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
      css: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css' },
    { js:  'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js',
      css: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css' }
  ];

  /* ------------------------------------------------------------------
     WHERE THE MAP IMAGERY COMES FROM

     This used to be CARTO's dark basemap, which was keyless for years.
     In late August 2026 CARTO started stamping 'API KEY REQUIRED'
     across anonymous requests to basemaps.cartocdn.com, and said the
     raster tiles are being retired in favour of vector ones. The
     tiles still arrive — it is a notice, not an outage — but a
     watermark across the map is not something to ship.

     So the default is OpenStreetMap's own tiles, which need no key and
     are not going anywhere. They are drawn for a white page, so
     bud-map.css inverts them into a dark map: the same trick Home
     Assistant uses for its dark mode, and it is applied to the tile
     layer alone so the pins on top keep their real colours.

     If you would rather have CARTO's proper dark cartography, a key is
     free and needs no account — carto.com/basemaps/apikey. Put it in
     your config as BUD_CARTO_KEY and includes/bud-widget.php passes it
     through; this switches over with nothing to change here.
     ------------------------------------------------------------------ */
  var TILES = {
    osm: {
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      subdomains: 'abc',
      maxZoom: 19,
      invert: true          // light cartography, darkened in CSS
    },
    carto: {
      url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key={key}',
      attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> ' +
            '&copy; <a href="https://carto.com/attributions">CARTO</a>',
      subdomains: 'abcd',
      maxZoom: 19,
      invert: false
    }
  };

  /* The opening view is not a hardcoded centre and zoom. It is the
     bounding box of the 24 pins, so it stays right when somebody adds
     a destination in the admin that sits outside today's box. */
  var HOME_PAD = .08;

  /* Somebody who has asked their system not to animate things did not
     mean 'except maps'. flyTo becomes setView for them. */
  var CALM = window.matchMedia &&
             window.matchMedia('(prefers-reduced-motion:reduce)').matches;

  var WIDE_KEY  = 'bud.mapView';    // did they leave it expanded?
  var SAVE_KEY  = 'bud.saved';      // hearted places, by name

  document.addEventListener('DOMContentLoaded', init);

  /* ------------------------------------------------------------------
     CATEGORIES

     The tag on each destination is free text typed by an admin, so it
     is normalised into one of six groups here rather than trusted. An
     unrecognised tag falls into 'towns', which is the group that says
     the least about a place — better than dropping a pin off the map
     because somebody wrote 'Beachfront' instead of 'Beach'.
     ------------------------------------------------------------------ */
  var GROUPS = [
    { id: 'all',     label: 'All 24' },
    { id: 'coast',   label: 'Beaches' },
    { id: 'water',   label: 'Falls & rivers' },
    { id: 'islands', label: 'Islands' },
    { id: 'peaks',   label: 'Peaks & trails' },
    { id: 'towns',   label: 'Towns & parks' }
  ];

  function groupFor(tag) {
    var t = String(tag || '').toLowerCase();
    if (/beach|surf|resort/.test(t))            return 'coast';
    if (/fall|river/.test(t))                   return 'water';
    if (/island/.test(t))                       return 'islands';
    if (/peak|mountain|view|adventure|camp/.test(t)) return 'peaks';
    return 'towns';
  }

  /* One glyph per group. Line art at 24px, drawn in currentColor so a
     pin can invert on selection without a second icon set. */
  var GLYPH = {
    coast:   '<path d="M2 15c2.5 0 2.5 2 5 2s2.5-2 5-2 2.5 2 5 2 2.5-2 5-2"/><path d="M2 19c2.5 0 2.5 2 5 2s2.5-2 5-2 2.5 2 5 2 2.5-2 5-2"/><circle cx="12" cy="7" r="3.5"/>',
    water:   '<path d="M12 3c3 4.2 5 7 5 9.6A5 5 0 0 1 7 12.6C7 10 9 7.2 12 3z"/>',
    islands: '<path d="M12 4c-2 1.6-3 3.4-3 5.4M12 4c2 1.6 3 3.4 3 5.4M12 4v11"/><path d="M3 18c2.5 0 2.5 2 5 2s2.5-2 5-2 2.5 2 5 2 2.5-2 5-2"/>',
    peaks:   '<path d="M3 19 10 6l4 7 2-3 5 9z"/>',
    towns:   '<path d="M12 3v6M9 6h6"/><path d="M6 21V12l6-3 6 3v9"/><path d="M10 21v-4h4v4"/>'
  };

  /* ------------------------------------------------------------------ */

  function init() {
    var bud   = document.getElementById('bud');
    var panel = document.getElementById('budPanel');
    var log   = document.getElementById('budLog');
    var input = document.getElementById('budInput');
    var send  = document.getElementById('budSend');
    var head  = panel && panel.querySelector('.bud__head');

    if (!bud || !panel || !log || !head) return;

    /* The destination list bud-widget.php prints. Same element bud.js
       reads; parsing it twice is cheaper than sharing it. */
    var places = [];
    try {
      var el = document.getElementById('budPlaces');
      if (el) places = JSON.parse(el.textContent) || [];
    } catch (e) { places = []; }

    /* Coordinates can arrive as strings ("13.95") depending on how the
       destination data was written. A string failed the typeof test
       below and silently dropped the place from the map AND from reply
       matching, so convert first. */
    places.forEach(function (p) {
      var la = parseFloat(p.la), lo = parseFloat(p.lo);
      p.la = isFinite(la) ? la : null;
      p.lo = isFinite(lo) ? lo : null;
    });

    /* Only places with coordinates can go on a map. A destination with
       no pin is left out of the map and stays in the chat, which is
       where its photograph and description already live. */
    var pinned = places.filter(function (p) {
      return typeof p.la === 'number' && typeof p.lo === 'number';
    });

    if (window.console && places.length !== pinned.length) {
      console.warn('Bud.Ai map: no coordinates for',
        places.filter(function (p) { return p.la === null; })
              .map(function (p) { return p.n; }));
    }

    if (!pinned.length) return;   // nothing to map; leave the widget alone

    /* ------------------------------------------------------------------
       ALIASES

       Bud (the model) rarely repeats a destination's full database name.
       It says "Bagasbas" for "Bagasbas Beach" and "Calaguas Islands" for
       "Calaguas Island". Matching only the exact name meant those
       replies found nothing, so the map never moved.

       Every place therefore gets a list of names it answers to:
         1. its full name,
         2. a short form with the generic trailing word removed
            ("Bagasbas Beach" -> "Bagasbas"), used ONLY when no other
            place would also claim that short form,
         3. a plural/singular variant, and
         4. hand-written extras below, for names that are not a simple
            trim ("Tulis Peak, Mt. Bagacay", "Mt. Panit").
       ------------------------------------------------------------------ */
    var EXTRA_ALIASES = {
      'Calaguas Island':          ['Calaguas Islands', 'Calaguas'],
      'Bagasbas Beach':           ['Bagasbas'],
      'Tulis Peak, Mt. Bagacay':  ['Tulis Peak', 'Mt. Bagacay', 'Mt Bagacay', 'Mount Bagacay', 'Tulis'],
      'Mt. Panit':                ['Mt Panit', 'Mount Panit', 'Panit'],
      'Shrine of the Black Nazarene': ['Black Nazarene Shrine'],
      'First Rizal Monument':     ['Rizal Monument'],
      'Turayog View Deck':        ['Turayog'],
      'Parola Island':            ['Parola'],
      'Pulong Guijanlo':          ['Guijanlo'],
      'Taba Taba Beach Resort':   ['Taba Taba', 'Taba-Taba'],
      'La Maestra Campsite and Resort': ['La Maestra'],
      'Mangrove Eco Tourism Park': ['Mangrove Park', 'Mangrove Eco Park'],
      'St. Francis of Assisi Parish Church': ['St. Francis of Assisi Church', 'San Francisco de Asis']
    };
    var GENERIC_TAIL = /\s+(beach|islands?|falls|peak|park|resort|festival|feast|river|view deck|monument|church|campsite)$/i;

    function aliasesFor(p, all) {
      var list = [p.n];
      (EXTRA_ALIASES[p.n] || []).forEach(function (a) { list.push(a); });
      var short = p.n.replace(GENERIC_TAIL, '');
      if (short !== p.n && short.length > 3) list.push(short);
      if (/island$/i.test(p.n)) list.push(p.n + 's');
      /* First word alone ("Calaguas Group of Islands" -> "Calaguas"),
         for names that are not a simple trim. Skipped for titles and
         filler words; ambiguous ones are dropped later anyway. */
      var first = p.n.split(/[\s,]+/)[0];
      if (first.length >= 5 && !/^(first|shrine|mount|mangrove|pulong|black|st\.?)$/i.test(first)) list.push(first);
      return list;
    }

    var aliasList = [];            // { text, lower, place }
    (function () {
      var claims = {};             // alias(lower) -> number of places claiming it
      var raw = places.map(function (p) {
        /* De-duplicate within ONE place first. 'Bagasbas' is produced
           three ways for Bagasbas Beach (extras, trimmed name, first
           word); counting each as a separate claim made it look
           ambiguous and threw it away. */
        var seenHere = {}, l = [];
        aliasesFor(p, places).forEach(function (a) {
          var k = a.toLowerCase();
          if (seenHere[k]) return;
          seenHere[k] = true;
          l.push(a);
        });
        l.forEach(function (a, i) {
          if (i === 0) return;
          var k = a.toLowerCase();
          claims[k] = (claims[k] || 0) + 1;
        });
        return { p: p, l: l };
      });
      var fullNames = {};
      places.forEach(function (p) { fullNames[p.n.toLowerCase()] = true; });
      raw.forEach(function (r) {
        r.l.forEach(function (a, i) {
          var k = a.toLowerCase();
          // An alias shared by two places is ambiguous; an alias equal to
          // another place's full name belongs to that place.
          if (i > 0 && (claims[k] > 1 || fullNames[k])) return;
          aliasList.push({ text: a, lower: k, place: r.p });
        });
      });
      // Longest first so 'Mananap Falls ATV Adventure' beats 'Mananap Falls'.
      aliasList.sort(function (a, b) { return b.text.length - a.text.length; });
    })();

    /* Find 'needle' in 'hay' only as a whole word, so 'Panit' does not
       match inside 'Panitikan'. */
    function indexOfWord(hay, needle, from) {
      var at = from;
      while ((at = hay.indexOf(needle, at)) !== -1) {
        var before = at === 0 ? '' : hay.charAt(at - 1);
        var after  = hay.charAt(at + needle.length);
        if (!/[a-z0-9]/.test(before) && !/[a-z0-9]/.test(after)) return at;
        at += 1;
      }
      return -1;
    }

    var saved = loadSaved();

    /* ---------- restructure the panel ----------

       The panel was header / log / compose stacked in a column. The map
       needs the log and the compose box to travel together as one
       column, so they move into a .bud__chat wrapper and the map pane
       becomes their sibling.

       Moving a node does not invalidate a reference to it, so every
       handler bud.js bound to #budLog, #budInput and #budSend keeps
       working across this. */
    var compose = panel.querySelector('.bud__compose');
    var chat = document.createElement('div');
    chat.className = 'bud__chat';
    panel.insertBefore(chat, log);
    chat.appendChild(log);
    if (compose) chat.appendChild(compose);

    var split = document.createElement('div');
    split.className = 'bud__split';
    panel.insertBefore(split, chat);
    split.appendChild(chat);

    var mapPane = buildMapPane();
    split.appendChild(mapPane);

    var canvas = mapPane.querySelector('.budmap__canvas');

    /* ---------- header controls ---------- */

    var closeBtn = head.querySelector('.bud__close');

    /* Chat | Map, for screens too narrow to show both at once. Present
       in the markup at every width and hidden by CSS above 900px,
       rather than built and destroyed on resize. */
    var seg = document.createElement('div');
    seg.className = 'bud__seg';
    seg.innerHTML =
      '<button type="button" class="bud__seg-btn is-on" data-pane="chat">Chat</button>' +
      '<button type="button" class="bud__seg-btn" data-pane="map">Map</button>';
    head.insertBefore(seg, closeBtn);

    var wideBtn = document.createElement('button');
    wideBtn.type = 'button';
    wideBtn.className = 'bud__wide-btn';
    wideBtn.setAttribute('aria-pressed', 'false');
    wideBtn.setAttribute('aria-label', 'Open map view');
    wideBtn.title = 'Open map view';
    wideBtn.innerHTML = icon('expand');
    head.insertBefore(wideBtn, closeBtn);

    seg.addEventListener('click', function (e) {
      var b = e.target.closest('.bud__seg-btn');
      if (!b) return;
      seg.querySelectorAll('.bud__seg-btn').forEach(function (x) {
        x.classList.toggle('is-on', x === b);
      });
      bud.classList.toggle('show-map', b.dataset.pane === 'map');
      if (b.dataset.pane === 'map') refresh();
    });

    wideBtn.addEventListener('click', function () {
      setWide(!bud.classList.contains('is-wide'));
    });

    /* ---------- PHONES: the map lives under the chat ----------

       On a phone the two panes are stacked and both are on screen, so
       there is nothing to switch between and no Map button to press.
       That matters here because ensureMap() is only ever called by
       that button — without this the map pane would be visible and
       permanently empty.

       Watching the class rather than hooking openPanel(): that
       function is private to bud.js and this file has no handle on it.
       The class is the contract between them, and it is already how
       every other piece of styling here knows the panel is open.

       Leaflet then measures itself through the ResizeObserver on the
       canvas, which fires as soon as the pane has a real size. */
    var phone = window.matchMedia('(max-width:600px)');

    /* CHANGED: on a phone the map no longer loads just because the
       panel opened. It belongs to the fullscreen (is-wide) view only —
       the chat opens on its own, and the map appears when the visitor
       taps the fullscreen button. setWide() already calls ensureMap(),
       so this only has to cover a panel that opens already wide. */
    function mapUnderChat() {
      if (!phone.matches) return;
      if (!bud.classList.contains('is-open')) return;
      if (!bud.classList.contains('is-wide')) return;
      ensureMap();
    }

    new MutationObserver(mapUnderChat).observe(bud, {
      attributes: true,
      attributeFilter: ['class'],
    });

    /* Rotating a phone into landscape crosses the breakpoint. Whichever
       side it lands on, the pane that is now visible needs measuring. */
    if (phone.addEventListener) {
      phone.addEventListener('change', function () {
        mapUnderChat();
        refresh();
      });
    }

    mapUnderChat();   // already open when this script ran

    /* Escape collapses the map view before it closes the whole panel.
       Capture phase because bud.js already listens on document for the
       same key, and one press should do one thing. */
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (!bud.classList.contains('is-wide')) return;
      if (!bud.classList.contains('is-open')) return;
      e.stopImmediatePropagation();
      setWide(false);
    }, true);

    /* Remembered per tab. Somebody who opened the map once is usually
       planning a route and wants it again on the next page. */
    /* Not on phones: there the chat should always open on its own,
       with the map one tap away, rather than reopening fullscreen. */
    try {
      if (!phone.matches && sessionStorage.getItem(WIDE_KEY) === '1') setWide(true, true);
    } catch (e) {}

    function setWide(on, quiet) {
      bud.classList.toggle('is-wide', on);
      wideBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      wideBtn.setAttribute('aria-label', on ? 'Back to chat only' : 'Open map view');
      wideBtn.title = on ? 'Back to chat only' : 'Open map view';
      wideBtn.innerHTML = icon(on ? 'collapse' : 'expand');

      if (!quiet) {
        try { sessionStorage.setItem(WIDE_KEY, on ? '1' : '0'); } catch (e) {}
      }

      if (on) ensureMap();
      else bud.classList.remove('show-map');
    }

    /* ================================================================
       THE MAP

       Everything below is inert until ensureMap() is called, which
       happens on the first click of 'Map view' and never again.
       ================================================================ */

    var map = null;
    var markers = {};        // name -> { marker, el, place }
    var loading = false;
    var activeGroup = 'all';
    var savedOnly = false;
    var pending = null;      // a place asked for before the map existed
    var queued = null;       // places a reply named before the map existed
    var home = null;         // the all-24 view, to return to
    /* False until the map has been aimed at something on purpose —
       by a reply, by a click, or by the visitor dragging it. Until
       then, every re-measure re-frames the whole province, because a
       map created inside a hidden panel is measured at the wrong size
       and its first fit is always slightly wrong. */
    var settled = false;

    function ensureMap() {
      if (map || loading) { refresh(); return; }
      loading = true;
      canvas.classList.add('is-loading');

      loadLeaflet(bud)
        .then(buildMap)
        .catch(function (err) { showFailure(err); })
        .then(function () { loading = false; });
    }

    /* A dead end with no way out of it is the worst version of this
       screen. It says which of the two likely causes it is, and it
       offers the one action that fixes the transient one. */
    function showFailure(err) {
      canvas.classList.remove('is-loading');
      canvas.classList.add('is-failed');
      canvas.textContent = '';

      var box = document.createElement('div');
      box.className = 'budmap__fail';

      var p = document.createElement('p');
      p.textContent = navigator.onLine === false
        ? 'The map needs an internet connection and this computer is offline. ' +
          'The chat still works.'
        : 'The map library could not be downloaded. An ad blocker or a ' +
          'firewall is the usual reason on a local server.';
      box.appendChild(p);

      var again = document.createElement('button');
      again.type = 'button';
      again.className = 'budmap__retry';
      again.textContent = 'Try again';
      again.addEventListener('click', function () {
        canvas.classList.remove('is-failed');
        canvas.textContent = '';
        ensureMap();
      });
      box.appendChild(again);

      var hint = document.createElement('p');
      hint.className = 'budmap__fail-hint';
      hint.textContent = 'To make it work offline, put Leaflet\u2019s dist ' +
                         'folder in your project as assets/leaflet/.';
      box.appendChild(hint);

      canvas.appendChild(box);
      console.error('Bud.Ai map:', err);
    }

    function buildMap() {
      canvas.classList.remove('is-loading');
      canvas.textContent = '';

      map = L.map(canvas, {
        zoomControl: false,

        /* The province is about 80km across. Below this the map is
           showing ocean and a repeated world, which is never the
           answer to a question about Camarines Norte — and it caps how
           wrong the framing can look if a fit ever lands badly again. */
        minZoom: 7,
        attributionControl: true,
        /* The chat is the primary surface. A scroll gesture aimed at
           the conversation should not zoom the map out from under it,
           so the wheel only zooms once the map has been clicked. */
        scrollWheelZoom: false
      });

      var key  = bud.getAttribute('data-carto-key') || '';
      var tile = key ? TILES.carto : TILES.osm;

      L.tileLayer(tile.url.replace('{key}', encodeURIComponent(key)), {
        attribution: tile.attr,
        subdomains: tile.subdomains,
        maxZoom: tile.maxZoom
      }).addTo(map);

      /* The filter lives on the pane, not the map, so it reaches the
         tiles and nothing else. Markers, labels and popups are in
         their own panes above it and come through untouched. */
      canvas.classList.toggle('budmap--invert', tile.invert);

      L.control.zoom({ position: 'bottomright' }).addTo(map);

      /* Reset sits just above the zoom buttons (a later control in a
         bottom corner stacks on top of the earlier ones). It is a real
         button, so it is reachable from the keyboard and announced. */
      var ResetControl = L.Control.extend({
        options: { position: 'bottomright' },
        onAdd: function () {
          var b = L.DomUtil.create('button', 'leaflet-control budmap__reset');
          b.type = 'button';
          b.title = 'Reset map';
          b.setAttribute('aria-label', 'Reset map: clear pins and show all destinations');
          b.innerHTML = icon('reset') + '<span>Reset</span>';
          /* Without this a click on the button is also a click on the
             map underneath it, which would arm wheel-zoom and close
             the popup it just closed. */
          L.DomEvent.disableClickPropagation(b);
          L.DomEvent.on(b, 'click', resetMap);
          return b;
        }
      });
      new ResetControl().addTo(map);

      /* Off the bottom-right corner, where the zoom buttons are. Two
         controls in one corner means one of them is unreadable. */
      map.attributionControl.setPosition('bottomleft');

      /* Wheel zoom is armed by a click on the map and disarmed when the
         pointer leaves it, so a scroll aimed at the conversation can
         never zoom the map instead. */
      canvas.addEventListener('click', function () { map.scrollWheelZoom.enable(); });
      canvas.addEventListener('mouseleave', function () { map.scrollWheelZoom.disable(); });

      pinned.forEach(addPin);

      /* Computed from the coordinates, not read back off the map, so
         it is right whatever size the container happened to be. */
      home = L.latLngBounds(pinned.map(function (p) {
        return [p.la, p.lo];
      })).pad(HOME_PAD);

      map.fitBounds(home, { animate: false });
      /* Real gestures only.

         This used to listen for Leaflet's own 'dragstart zoomstart',
         which was wrong: invalidateSize() fires those too, so the
         widget's own re-measure looked exactly like the visitor
         grabbing the map. The flag went up on the first re-measure and
         the corrective fit never ran — leaving whatever framing the
         map happened to compute while the panel was still opening,
         which is the whole world at minimum zoom.

         A pointer going down on the canvas, or a wheel turning over
         it, cannot be caused by this file. */
      canvas.addEventListener('pointerdown', function () { settled = true; });
      canvas.addEventListener('wheel',       function () { settled = true; });

      /* Two timeouts were a guess at how long the panel takes to open.
         This is the fact itself: the browser says when the pane has a
         size, including sizes nobody predicted — a window resize, the
         Chat/Map switch on a phone, devtools opening.

         Leaflet is measured against a box that is genuinely there
         rather than one that might be by now. */
      if (window.ResizeObserver) {
        new ResizeObserver(function () {
          map.invalidateSize();
          var s = map.getSize();
          if (s.x < 80 || s.y < 80) return;
          if (!settled && home) map.fitBounds(home, { animate: false });
        }).observe(canvas);
      }

      applyFilter();
      refresh();

      /* Somebody clicked a place name while Leaflet was still
         downloading. Their click is honoured now rather than dropped. */
      if (pending) { var p = pending; pending = null; focusPlace(p); }
      else if (queued) { var q = queued; queued = null; highlight(q); fitTo(q); }

      /* A plan that was built before the map existed. Applied last so
         it wins over a reply that only named one of its stops. */
      if (planStops && !p) applyPlan();
    }

    function addPin(p) {
      var group = groupFor(p.g);

      var wrap = document.createElement('div');
      wrap.className = 'budpin__in budpin--' + group;
      wrap.innerHTML =
        '<span class="budpin__dot">' + svg(GLYPH[group] || GLYPH.towns) + '</span>' +
        '<span class="budpin__label"></span>';
      wrap.querySelector('.budpin__label').textContent = p.n;

      var marker = L.marker([p.la, p.lo], {
        icon: L.divIcon({ className: 'budpin', html: wrap.outerHTML, iconSize: null }),
        keyboard: true,
        title: p.n,
        riseOnHover: true
      }).addTo(map);

      marker.on('click', function () { openCard(p, marker); });

      markers[p.n] = { marker: marker, place: p, group: group };
    }

    /* The popup is built from the same fields the chat cards use, so a
       place looks the same wherever it is met. */
    function openCard(p, marker) {
      var box = document.createElement('div');
      box.className = 'budcard';

      if (p.img) {
        var img = document.createElement('img');
        img.className = 'budcard__img';
        img.src = p.img;
        img.alt = '';
        img.addEventListener('error', function () { img.remove(); });
        box.appendChild(img);
      }

      var body = document.createElement('div');
      body.className = 'budcard__body';

      var h = document.createElement('strong');
      h.className = 'budcard__name';
      h.textContent = p.n;
      body.appendChild(h);

      if (p.t) {
        var t = document.createElement('span');
        t.className = 'budcard__town';
        t.textContent = p.t;
        body.appendChild(t);
      }

      var ask = document.createElement('button');
      ask.type = 'button';
      ask.className = 'budcard__ask';
      ask.textContent = 'Ask Bud about this';
      ask.addEventListener('click', function () {
        askAbout(p.n);
        map.closePopup();
      });
      body.appendChild(ask);

      box.appendChild(body);

      marker.bindPopup(box, {
        className: 'budpop',
        closeButton: true,
        maxWidth: 240,
        offset: [0, -6]
      }).openPopup();
    }

    /* Leaflet measures the container when it is created. Created inside
       a panel that was display:none, every tile lands in the wrong
       place until it is told to measure again. */
    function refresh() {
      if (!map) return;
      remeasure(60);
      remeasure(420);          // and again once the panel has finished opening
    }

    function remeasure(after) {
      setTimeout(function () {
        map.invalidateSize();

        /* A container that is still hidden, or mid-transition, has
           nothing worth framing against — fitting to it is what
           produced the world-zoom in the first place. Skip, and let
           the later pass do it once the panel has finished opening. */
        var size = map.getSize();
        if (size.x < 80 || size.y < 80) return;

        if (!settled && home) map.fitBounds(home, { animate: false });
      }, after);
    }

    /* ---------- filters ---------- */

    var chipBar = mapPane.querySelector('.budmap__chips');

    GROUPS.forEach(function (g) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'budmap__chip' + (g.id === 'all' ? ' is-on' : '');
      b.dataset.group = g.id;
      b.textContent = g.label;
      chipBar.appendChild(b);
    });

    chipBar.addEventListener('click', function (e) {
      var b = e.target.closest('.budmap__chip');
      if (!b) return;
      activeGroup = b.dataset.group;
      savedOnly = false;
      chipBar.querySelectorAll('.budmap__chip').forEach(function (x) {
        x.classList.toggle('is-on', x === b);
      });
      savedBtn.classList.remove('is-on');
      applyFilter();

      /* Filtering is also how you get back out of a place you flew to.
         Pull the view back to whatever is now on the map. */
      var shown = Object.keys(markers).filter(function (n) {
        return activeGroup === 'all' || markers[n].group === activeGroup;
      });
      highlight([]);
      settled = activeGroup !== 'all';
      if (activeGroup === 'all' && home) map.flyToBounds(home, { duration: .7 });
      else fitTo(shown);
    });

    var savedBtn = mapPane.querySelector('.budmap__saved');
    savedBtn.addEventListener('click', function () {
      savedOnly = !savedOnly;
      savedBtn.classList.toggle('is-on', savedOnly);
      if (savedOnly) {
        activeGroup = 'all';
        chipBar.querySelectorAll('.budmap__chip').forEach(function (x) {
          x.classList.toggle('is-on', x.dataset.group === 'all');
        });
      }
      applyFilter();
      if (savedOnly) fitTo(Object.keys(markers).filter(isSaved));
    });

    function applyFilter() {
      if (!map) return;
      Object.keys(markers).forEach(function (name) {
        var m = markers[name];
        var show = (savedOnly ? isSaved(name) : true) &&
                   (activeGroup === 'all' || m.group === activeGroup);
        var el = m.marker.getElement();
        if (el) el.classList.toggle('is-hidden', !show);
      });
    }

    /* ---------- saving ---------- */

    function loadSaved() {
      try { return JSON.parse(localStorage.getItem(SAVE_KEY)) || []; }
      catch (e) { return []; }
    }

    function isSaved(name) { return saved.indexOf(name) !== -1; }

    function toggleSaved(name) {
      var i = saved.indexOf(name);
      if (i === -1) saved.push(name);
      else saved.splice(i, 1);
      try { localStorage.setItem(SAVE_KEY, JSON.stringify(saved)); } catch (e) {}
      paintSaved();
      return isSaved(name);
    }

    function paintSaved() {
      var n = saved.length;
      savedBtn.querySelector('.budmap__saved-n').textContent = n;
      savedBtn.hidden = n === 0;
      savedBtn.setAttribute('aria-label',
        n === 1 ? '1 saved place' : n + ' saved places');

      /* Any card already on screen for a place that was just unsaved
         elsewhere. Cheap, and keeps two views of one fact in step. */
      panel.querySelectorAll('[data-bud-place]').forEach(function (card) {
        var on = isSaved(card.dataset.budPlace);
        var b = card.querySelector('.bud__place-save');
        if (b) {
          b.classList.toggle('is-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
      });
    }

    paintSaved();

    /* ---------- focusing a place ---------- */

    function focusPlace(name) {
      /* Phone, chat-only view: the map is hidden, so a tapped place
         name opens the fullscreen view first, or the tap would move a
         map nobody can see. */
      if (phone.matches && !bud.classList.contains('is-wide')) setWide(true);
      if (!map) { pending = name; ensureMap(); return; }
      var m = markers[name];
      if (!m) return;

      bud.classList.add('show-map');
      seg.querySelectorAll('.bud__seg-btn').forEach(function (x) {
        x.classList.toggle('is-on', x.dataset.pane === 'map');
      });

      highlight([name]);
      moveTo([m.place.la, m.place.lo], 12);
      m.marker.fire('click');
    }

    function highlight(names) {
      Object.keys(markers).forEach(function (n) {
        var el = markers[n].marker.getElement();
        if (el) el.classList.toggle('is-live', names.indexOf(n) !== -1);
      });
    }

    /* ---------- reset ----------

       Puts the map back the way it was when it first opened: no pin
       picked out, no popup, the All 24 filter, saved-only off, and the
       whole province in view. Anything a reply or a tap changed is
       undone in one go.

       Saved places themselves are NOT touched. They are the
       visitor's own list, kept on purpose, and a view reset should
       never delete them. */
    function resetMap() {
      if (!map) return;

      map.closePopup();
      pending = null;
      queued  = null;

      highlight([]);

      activeGroup = 'all';
      savedOnly   = false;
      chipBar.querySelectorAll('.budmap__chip').forEach(function (x) {
        x.classList.toggle('is-on', x.dataset.group === 'all');
      });
      savedBtn.classList.remove('is-on');
      applyFilter();

      if (home) {
        if (CALM) map.fitBounds(home, { animate: false });
        else map.flyToBounds(home, { duration: .8 });
      }

      /* Back to "not yet touched", so a window resize re-frames the
         whole province again rather than whatever was last zoomed to.
         The next real gesture on the map sets it again. */
      settled = false;
    }

    function fitTo(names) {
      if (!map || !names.length) return;

      if (names.length === 1) {
        var m = markers[names[0]];
        if (m) moveTo([m.place.la, m.place.lo], 12);
        return;
      }

      settled = true;

      var pts = names.map(function (n) {
        return markers[n] ? [markers[n].place.la, markers[n].place.lo] : null;
      }).filter(Boolean);

      if (pts.length) {
        var b = L.latLngBounds(pts).pad(.35);
        if (CALM) map.fitBounds(b, { animate: false, maxZoom: 12 });
        else map.flyToBounds(b, { duration: .9, maxZoom: 12 });
      }
    }

    function moveTo(latlng, zoom) {
      settled = true;
      if (CALM) map.setView(latlng, zoom, { animate: false });
      else map.flyTo(latlng, zoom, { duration: .8 });
    }

    /* ================================================================
       A PLAN ON THE MAP

       bud.js announces a finished plan with every stop and its
       coordinates. Each stop is matched to an existing pin by name,
       then by position. A stop with neither gets a pin of its own, so
       every place in the plan is on the map, lit, and in view.
       ================================================================ */
    var planStops = null;     // stops announced before the map existed
    var planKeys  = {};       // normalised stop name -> marker key

    function norm(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }

    document.addEventListener('bud:plan', function (e) {
      var stops = (e && e.detail && e.detail.stops) || [];
      planStops = stops.length ? stops : null;
      if (!planStops) return;
      if (map) applyPlan();
      /* No map yet: buildMap() applies it once Leaflet has loaded. */
    });

    function addExtraPlace(s) {
      var p = { n: s.n, t: s.t || '', g: s.g || '', la: s.la, lo: s.lo };
      places.push(p);
      aliasList.push({ text: p.n, lower: p.n.toLowerCase(), place: p });
      aliasList.sort(function (a, b) { return b.text.length - a.text.length; });
      addPin(p);
      return p.n;
    }

    function applyPlan() {
      if (!map || !planStops) return;

      var stops = planStops;
      planStops = null;
      planKeys  = {};
      var names = [];

      stops.forEach(function (s) {
        if (!s || !s.n) return;
        var la = parseFloat(s.la), lo = parseFloat(s.lo);
        var hasPos = isFinite(la) && isFinite(lo) && !(la === 0 && lo === 0);
        var key = null;

        // 1. a pin with the same name
        Object.keys(markers).some(function (n) {
          if (norm(n) === norm(s.n)) { key = n; return true; }
          return false;
        });

        // 2. a pin in the same spot, under a different name
        if (!key && hasPos) {
          Object.keys(markers).some(function (n) {
            var p = markers[n].place;
            if (Math.abs(p.la - la) < 0.002 && Math.abs(p.lo - lo) < 0.002) { key = n; return true; }
            return false;
          });
        }

        // 3. no pin at all: give the stop its own
        if (!key && hasPos) {
          key = addExtraPlace({ n: s.n, t: s.t, g: s.g, la: la, lo: lo });
        }

        if (key) {
          planKeys[norm(s.n)] = key;
          if (names.indexOf(key) === -1) names.push(key);
        }
      });

      if (!names.length) return;

      /* A category filter or the saved-only view could be hiding some
         of them. Show everything so the whole plan is visible. */
      if (activeGroup !== 'all' || savedOnly) {
        activeGroup = 'all';
        savedOnly   = false;
        chipBar.querySelectorAll('.budmap__chip').forEach(function (x) {
          x.classList.toggle('is-on', x.dataset.group === 'all');
        });
        savedBtn.classList.remove('is-on');
      }
      applyFilter();

      highlight(names);
      fitTo(names);
    }

    /* Tapping a stop in the plan shows it on the map. */
    panel.addEventListener('click', function (e) {
      var li = e.target.closest && e.target.closest('li[data-bud-stop]');
      if (!li || e.target.closest('.bud__ref, a, button')) return;
      var key = planKeys[norm(li.dataset.budStop)];
      if (!key) return;
      if (!bud.classList.contains('is-wide')) setWide(true);
      focusPlace(key);
    });

    /* ================================================================
       WATCHING THE CONVERSATION

       Every reply that names a destination moves the map to it. The
       names are matched against the same fixed list of 24 the photo
       cards use, so a reply can never point the map at somewhere that
       is not in the database.
       ================================================================ */

    new MutationObserver(function (records) {
      records.forEach(function (rec) {
        Array.prototype.forEach.call(rec.addedNodes, function (node) {
          if (node.nodeType !== 1) return;

          /* The typing indicator is a bot message too. It has no text
             worth reading and it is about to be removed. */
          if (node.querySelector && node.querySelector('.bud__typing')) return;

          /* bud.js does not append a bot bubble to the log directly:
             mountBot() wraps it in a .bud__row (avatar + bubble) and
             appends the ROW. So the added node is usually the row, and
             the message is inside it. Check the node itself and its
             descendants, or replies are never seen. */
          var msgs = [];
          if (node.matches('.bud__msg--bot')) msgs.push(node);
          else if (node.querySelectorAll) {
            msgs = Array.prototype.slice.call(
              node.querySelectorAll('.bud__msg--bot'));
          }
          msgs = msgs.filter(function (m) {
            return !m.classList.contains('bud__msg--typing');
          });

          if (msgs.length) {
            msgs.forEach(handleReply);
            return;
          }

          /* A photo strip added straight to the log rather than inside
             a message — the server-driven path in bud.js. */
          if (node.classList.contains('bud__places')) {
            node.querySelectorAll('.bud__place').forEach(upgradeCard);
          }
        });
      });
    }).observe(log, { childList: true, subtree: true });

    function handleReply(msg) {
      var found = findPlaces(msg.textContent, 6);

      /* Photo cards bud.js drew inside this message get the save and
         locate buttons, whether or not the map has ever been opened. */
      msg.querySelectorAll('.bud__place').forEach(upgradeCard);

      /* A day-by-day plan is mapped from its stops (see 'bud:plan'
         below), all of them at once. Spotting names in its text found
         only some, highlighted just those, and turned stray words in
         the descriptions into links. */
      if (msg.querySelector('.bud__plan-day')) return;

      if (!found.length) return;

      linkNames(msg, found);

      var names = found.map(function (p) { return p.n; });
      if (!map) { queued = names; return; }   // aim the map at these once it exists
      queued = null;
      highlight(names);
      fitTo(names);
    }

    function findPlaces(text, limit) {
      var hay = text.toLowerCase();
      var hits = [], taken = [], seen = {};

      for (var i = 0; i < aliasList.length && hits.length < limit; i++) {
        var al = aliasList[i];
        if (seen[al.place.n]) continue;
        var at = indexOfWord(hay, al.lower, 0);
        if (at === -1) continue;

        var overlaps = taken.some(function (r) {
          return at < r[1] && (at + al.lower.length) > r[0];
        });
        if (overlaps) continue;

        taken.push([at, at + al.lower.length]);
        seen[al.place.n] = true;
        if (typeof al.place.la === 'number' && typeof al.place.lo === 'number') hits.push(al.place);
      }
      return hits;
    }

    /* Turn each mentioned name in the reply into a button that moves
       the map. Walks text nodes and rebuilds them out of createElement
       and textContent — the reply is still never treated as markup. */
    function linkNames(msg, found) {
      var wanted = {};
      found.forEach(function (p) { wanted[p.n] = true; });
      var names = aliasList.filter(function (al) { return wanted[al.place.n]; });

      var walker = document.createTreeWalker(msg, NodeFilter.SHOW_TEXT, null);
      var targets = [];
      var node;
      while ((node = walker.nextNode())) {
        if (node.parentNode.closest('.bud__places, .bud__ref')) continue;
        targets.push(node);
      }

      targets.forEach(function (text) {
        var value = text.nodeValue;
        var lower = value.toLowerCase();

        var cuts = [];
        names.forEach(function (al) {
          var from = 0, at;
          while ((at = indexOfWord(lower, al.lower, from)) !== -1) {
            var end = at + al.lower.length;
            var clash = cuts.some(function (c) {
              return at < c.end && end > c.start;
            });
            if (!clash) cuts.push({ start: at, end: end, name: al.place.n });
            from = end;
          }
        });

        if (!cuts.length) return;
        cuts.sort(function (a, b) { return a.start - b.start; });

        var frag = document.createDocumentFragment();
        var at = 0;

        cuts.forEach(function (c) {
          if (c.start > at) {
            frag.appendChild(document.createTextNode(value.slice(at, c.start)));
          }
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'bud__ref';
          b.dataset.budRef = c.name;
          b.title = 'Show ' + c.name + ' on the map';
          b.innerHTML = svg(GLYPH[groupFor(placeByName(c.name).g)] || GLYPH.towns);
          b.appendChild(document.createTextNode(value.slice(c.start, c.end)));
          frag.appendChild(b);
          at = c.end;
        });

        if (at < value.length) {
          frag.appendChild(document.createTextNode(value.slice(at)));
        }

        text.parentNode.replaceChild(frag, text);
      });
    }

    function placeByName(n) {
      for (var i = 0; i < places.length; i++) if (places[i].n === n) return places[i];
      return {};
    }

    /* One listener on the panel rather than one per name. */
    panel.addEventListener('click', function (e) {
      var ref = e.target.closest('.bud__ref');
      if (ref) {
        if (!bud.classList.contains('is-wide')) setWide(true);
        focusPlace(ref.dataset.budRef);
      }
    });

    /* ---------- photo cards ----------

       bud.js draws the card and the caption. This adds the two controls
       from the reference design: save it, or show it on the map. */
    function upgradeCard(card) {
      if (card.dataset.budPlace) return;    // already done

      var nameEl = card.querySelector('.bud__place-name');
      if (!nameEl) return;
      var name = nameEl.textContent.trim();

      card.dataset.budPlace = name;

      var tools = document.createElement('div');
      tools.className = 'bud__place-tools';

      var save = document.createElement('button');
      save.type = 'button';
      save.className = 'bud__place-save' + (isSaved(name) ? ' is-on' : '');
      save.setAttribute('aria-pressed', isSaved(name) ? 'true' : 'false');
      save.setAttribute('aria-label', 'Save ' + name);
      save.innerHTML = icon('heart');
      save.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleSaved(name);
      });

      var locate = document.createElement('button');
      locate.type = 'button';
      locate.className = 'bud__place-locate';
      locate.setAttribute('aria-label', 'Show ' + name + ' on the map');
      locate.innerHTML = icon('pin');
      locate.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!bud.classList.contains('is-wide')) setWide(true);
        focusPlace(name);
      });

      tools.appendChild(save);
      if (markers[name] || placeByName(name).la != null) tools.appendChild(locate);
      card.appendChild(tools);
    }

    /* ---------- asking on the visitor's behalf ---------- */

    function askAbout(name) {
      if (!input || !send) return;
      input.value = 'Tell me about ' + name + '.';
      send.click();
      bud.classList.remove('show-map');
      seg.querySelectorAll('.bud__seg-btn').forEach(function (x) {
        x.classList.toggle('is-on', x.dataset.pane === 'chat');
      });
    }

    /* ---------- pane markup ---------- */

    function buildMapPane() {
      var pane = document.createElement('div');
      pane.className = 'budmap';
      pane.innerHTML =
        '<div class="budmap__bar">' +
          '<div class="budmap__chips" role="group" aria-label="Filter the map"></div>' +
          '<button type="button" class="budmap__saved" hidden>' +
            icon('heart') + '<span class="budmap__saved-n">0</span>' +
          '</button>' +
        '</div>' +
        '<div class="budmap__canvas" role="application" aria-label="Map of Camarines Norte"></div>';
      return pane;
    }
  }

  /* ------------------------------------------------------------------
     LOADING LEAFLET

     Resolves immediately if something else on the page already loaded
     it. destinations.php does, so opening the map there costs nothing.
     ------------------------------------------------------------------ */
  var leafletPromise = null;

  function loadLeaflet(bud) {
    if (window.L && window.L.map) return Promise.resolve();
    if (leafletPromise) return leafletPromise;

    /* The vendored copy, if bud-widget.php found one, then the CDNs. */
    var sources = [];
    var localJs  = bud.getAttribute('data-leaflet-js');
    var localCss = bud.getAttribute('data-leaflet-css');
    if (localJs) sources.push({ js: localJs, css: localCss || null });
    sources = sources.concat(LEAFLET_CDNS);

    leafletPromise = sources.reduce(function (chain, src) {
      return chain.catch(function () { return tryOne(src); });
    }, Promise.reject())
    .catch(function (err) {
      /* Let the next attempt start clean. Holding on to a rejected
         promise means the retry button would replay the failure
         without ever going back to the network. */
      leafletPromise = null;
      throw err;
    });

    return leafletPromise;
  }

  function tryOne(src) {
    return new Promise(function (resolve, reject) {
      if (window.L && window.L.map) { resolve(); return; }

      /* The stylesheet is not waited on. If it 404s the map still
         works, only unstyled, and blocking the whole map on it would
         trade a working map for a tidier failure.

         It is tagged with its own source and taken back out again if
         that source turns out to be unreachable. Leaving it behind
         would satisfy the 'is there already one?' check on the next
         attempt, and the map would come up on a CDN that works
         wearing a stylesheet from one that does not. */
      var css = null;
      if (src.css && !document.querySelector('link[data-bud-leaflet]')) {
        css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = src.css;
        css.setAttribute('data-bud-leaflet', '');
        document.head.appendChild(css);
      }

      var js = document.createElement('script');
      js.src = src.js;
      js.async = true;

      /* A blocked request can hang rather than error. Without this the
         panel sits on 'Loading the map' for as long as the visitor is
         willing to wait, and the next source is never reached. */
      var timer = setTimeout(function () {
        fail(new Error('timed out: ' + src.js));
      }, 8000);

      function done() {
        clearTimeout(timer);
        js.onload = js.onerror = null;
      }

      function fail(err) {
        done();
        if (js.parentNode) js.parentNode.removeChild(js);
        if (css && css.parentNode) css.parentNode.removeChild(css);
        reject(err);
      }

      js.onload = function () {
        done();
        /* onload also fires when a blocker answers with an empty body
           or a 404 page, so the check is for Leaflet itself, not for
           the request having finished. */
        if (window.L && window.L.map) resolve();
        else fail(new Error('not Leaflet: ' + src.js));
      };

      js.onerror = function () { fail(new Error('blocked: ' + src.js)); };

      document.head.appendChild(js);
    });
  }

  /* ---------- small helpers ---------- */

  function svg(inner) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
           'stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" ' +
           'aria-hidden="true">' + inner + '</svg>';
  }

  function icon(name) {
    var paths = {
      expand:   '<path d="M9 3H3v6M15 3h6v6M9 21H3v-6M15 21h6v-6"/>',
      collapse: '<path d="M3 9h6V3M21 9h-6V3M3 15h6v6M21 15h-6v6"/>',
      heart:    '<path d="M12 20.7C12 20.7 4 15.4 4 9.9A4.4 4.4 0 0 1 12 7.3 4.4 4.4 0 0 1 20 9.9c0 5.5-8 10.8-8 10.8z"/>',
      pin:      '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
      reset:    '<path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5"/>'
    };
    return svg(paths[name] || '');
  }
}());