/* ====================================================================
   BUD.AI — launcher, panel, and the one place to plug in your model.
   assets/bud.js

   Loaded by includes/bud-widget.php, on every page that includes it.
   Standalone: it shares no variables with homepage.js and can be
   loaded on a page that does not have homepage.js at all.

   ► TO CONNECT YOUR AI: there is exactly one function to change,
     askBud() near the bottom. Everything above it is plumbing —
     rendering bubbles, scrolling, the typing dots, open and close —
     and none of it needs to know where the answer came from.
   ==================================================================== */
document.addEventListener('DOMContentLoaded', function () {

  var bud = document.getElementById('bud');
  if (!bud) return;

  /* Where to POST. Read from the markup rather than hardcoded,
     because 'api/bud.php' is relative to the PAGE, not to this file —
     it resolves correctly from /destinations.php and breaks from
     /pages/destinations.php. includes/bud-widget.php works out the
     right value once and writes it into the data attribute.

     EMPTY means there is no api/bud.php on the server yet. The widget
     then runs as a design preview — see askBud() at the bottom. The
     include sets this automatically; there is no switch to remember to
     flip when the backend arrives. */
  var endpoint = bud.getAttribute('data-endpoint') || '';
  var previewMode = endpoint === '';

  /* Image paths come back as 'uploads/Destination-Photo/x.jpg', which
     is relative to the SITE ROOT. The page might be a level down, so
     the same prefix the include worked out for the endpoint is reused:
     strip 'api/bud.php' off the end and what remains is the way back
     to the root. */
  var assetBase = endpoint.replace(/api\/bud\.php$/, '');

  var toggle = document.getElementById('budToggle');
  var panel  = document.getElementById('budPanel');
  var log    = document.getElementById('budLog');
  var input  = document.getElementById('budInput');
  var send   = document.getElementById('budSend');

  if (!toggle || !panel || !log || !input || !send) return;

  /* The logo used beside Bud's messages. Taken from the header image,
     whose path bud-widget.php has already resolved for this page. */
  var headAvatar = panel.querySelector('.bud__head-avatar');
  var avatarSrc  = headAvatar ? headAvatar.getAttribute('src') : '';

  /* ---------- Messenger-style rows ----------

     Every bot bubble goes into a row with a small Bud beside it. The
     CSS shows the avatar only on the LAST bubble of a run, like
     Messenger, so a three-part reply has one face, not three. */
  function mountBot(el) {
    var row = document.createElement('div');
    row.className = 'bud__row bud__row--bot';

    if (avatarSrc) {
      var av = document.createElement('img');
      av.className = 'bud__row-avatar';
      av.src = avatarSrc;
      av.alt = '';
      av.draggable = false;
      av.addEventListener('error', function () { av.style.visibility = 'hidden'; });
      row.appendChild(av);
    }

    row.appendChild(el);
    log.appendChild(row);
    return row;
  }

  var busy       = false;   // a request is in flight
  var closeTimer = null;

  /* What has been said so far, sent back with each question so Bud can
     follow "how much is the boat from there?". Capped on the server
     too — never trust the browser to have kept it short. */
  var history = [];
  var HISTORY_MAX = 16;   // 8 exchanges

  /* ---------- open / close ---------- */

  function openPanel() {
    if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }

    panel.hidden = false;

    /* Same two-frame dance as the reviews modal: removing display:none
       and adding the class together gives the browser no start state
       to animate from, so the panel snaps instead of easing. */
    requestAnimationFrame(function () {
      bud.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Close Bud.Ai');

      /* Focus the input, not the panel. Someone who opened a chat
         wants to type. Skipped on touch, where focusing summons the
         keyboard over the conversation before there is anything in it. */
      if (window.matchMedia('(hover:hover)').matches) input.focus();

      showSuggestions();
      scrollLog();
    });
  }

  function closePanel() {
    bud.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open Bud.Ai');

    /* Hide after the fade. Guarded on is-open so a quick close-then-
       open cannot have this land late and hide a panel that is on its
       way back in. */
    closeTimer = setTimeout(function () {
      if (!bud.classList.contains('is-open')) panel.hidden = true;
      closeTimer = null;
    }, 320);

    toggle.focus();
  }

  toggle.addEventListener('click', function () {
    if (bud.classList.contains('is-open')) closePanel();
    else openPanel();
  });

  /* ---------- reveal after the hero ----------

     The widget stays out of the way until the hero has been scrolled
     past. Over a full-bleed hero it competes with the headline and the
     Plan your trip button; below the fold it is the only thing offering
     help, which is where it earns its place.

     IntersectionObserver on the hero rather than a scroll listener with
     a hard-coded pixel value: the hero is 100vh, so any number I picked
     would be wrong on the next screen size. This tracks the element
     itself and costs nothing per frame.

     If there is no hero on the page, or the browser is too old for the
     observer, the widget simply shows — failing to a visible assistant
     is better than failing to an invisible one. */
  var hero = document.getElementById('hero') || document.querySelector('.hero');

  /* Real implementation is installed further down, once the greeting
     element is known to exist. Declared here as a no-op so revealBud()
     is safe to call on a page that has no greeting bubble. */
  var startGreeting = function () {};
  var cancelGreeting = function () {};

  function revealBud() {
    if (bud.classList.contains('is-ready')) return;
    bud.classList.add('is-ready');
    startGreeting();
  }

  function hideBud() {
    bud.classList.remove('is-ready');
    /* Scrolling back to the hero cancels the countdown. Without this
       the timer fires against a hidden widget, the guard below refuses
       to show the bubble, and the greeting is retired for the rest of
       the page load without ever having been seen. */
    cancelGreeting();
    if (bud.classList.contains('is-open')) closePanel();
  }

  if (hero && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        /* Show once the hero is essentially gone, hide again on the way
           back up so returning to the top gives you a clean hero. */
        if (entry.intersectionRatio < 0.12) {
          revealBud();
        } else {
          hideBud();
        }
      });
    }, { threshold: [0, 0.12, 0.5] }).observe(hero);
  } else {
    revealBud();
  }

  /* ---------- greeting bubble ----------

     Held back a couple of seconds rather than shown on load. Arriving
     at the same moment as the page makes it part of the furniture and
     it gets ignored; arriving just after the visitor has settled is
     what makes it read as someone offering to help.

     Once dismissed or used it does not come back for the rest of the
     visit — sessionStorage, so it returns on the next visit but does
     not nag on every page of this one. */
  var greet      = document.getElementById('budGreet');
  var greetTimer = null;

  if (greet) {
    var GREET_KEY = 'bud.greetSeen';

    /* ► TESTING THE BUBBLE?
       Set this to false and the greeting returns on every reveal
       instead of retiring after the first dismiss. Handy while you are
       styling it; put it back to true before you ship, or every scroll
       past the hero nags the same visitor again.

       The other way to get it back is a new tab — the flag lives in
       sessionStorage, so it clears when the tab closes. */
    var GREET_REMEMBER = true;

    var seen = false;

    /* Private browsing throws on storage access in some browsers, and
       a greeting bubble is not worth taking the widget down over. */
    if (GREET_REMEMBER) {
      try { seen = sessionStorage.getItem(GREET_KEY) === '1'; } catch (e) {}
    }

    function hideGreet() {
      greet.hidden = true;
      cancelGreeting();
      if (GREET_REMEMBER) {
        seen = true;
        try { sessionStorage.setItem(GREET_KEY, '1'); } catch (e) {}
      }
    }

    /* Installed over the no-op above. Fires when the widget is
       revealed, not on a page-load timer — on a long hero that timer
       would expire while the visitor is still reading, and the bubble
       would be waiting already-shown behind the fold. */
    startGreeting = function () {
      if (seen) return;

      /* Reschedule rather than bail out if a countdown is already
         pending — every reveal gets a fresh one, so scrolling up and
         back down still produces the greeting. */
      clearTimeout(greetTimer);
      greetTimer = setTimeout(function () {
        greetTimer = null;
        if (!bud.classList.contains('is-open') && bud.classList.contains('is-ready')) {
          greet.hidden = false;
        }
      }, 1400);
    };

    cancelGreeting = function () {
      clearTimeout(greetTimer);
      greetTimer = null;
    };

    greet.querySelectorAll('[data-bud-dismiss]').forEach(function (el) {
      el.addEventListener('click', hideGreet);
    });

    greet.querySelectorAll('[data-bud-open]').forEach(function (el) {
      el.addEventListener('click', function () {
        hideGreet();
        openPanel();
      });
    });

    // opening the panel any other way retires it too
    toggle.addEventListener('click', hideGreet);
  }

  panel.querySelectorAll('[data-bud-close]').forEach(function (el) {
    el.addEventListener('click', closePanel);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && bud.classList.contains('is-open')) closePanel();
  });

  /* ==================================================================
     DRAG THE LAUNCHER — CHAT-HEAD BEHAVIOUR

     Hold it, it follows the finger. Let go, it flies to the nearest
     side and stays at the height you left it. A press that does not
     move still opens the chat.

     ---------------------------------------------------------------
     THIS IS THE SECOND ATTEMPT. The first one set .left and .top on
     .bud and listened for pointermove on the window. It moved nothing.
     Three things about that were fragile, and this version removes all
     three rather than guessing which one was fatal:

       1. It captured the pointer only AFTER the finger had travelled
          past the threshold. Everything before that depended on the
          move events bubbling all the way up to window — one
          stopPropagation anywhere in between and the drag never
          starts. setPointerCapture now happens on pointerdown, before
          anything can intervene, and the listeners sit on the button
          itself. After capture, every move for that finger is
          delivered here whatever else the page is doing.

       2. It wrote .left/.top and set right/bottom to auto, fighting
          .bud's own corner anchoring (bud.css:39) and the phone
          override at bud.css:561. This version never touches those
          four properties. It writes a transform, which is a pure
          visual offset from wherever the CSS has decided the widget
          lives — so the CSS keeps owning the corner and the two can
          never disagree.

       3. The panel is a CHILD of the thing being moved, so opening the
          chat resized the box being positioned. Now the panel goes
          position:absolute once the widget has been moved (see
          bud.css), which takes it out of the flow entirely: the
          launcher cannot be pushed anywhere by the panel opening.
     ---------------------------------------------------------------

     THE THRESHOLD is why a tap still opens the chat. A finger never
     presses cleanly — a tap on a 64px target routinely moves three or
     four pixels — so under 6px of travel this code does nothing at all
     and the existing click handler runs untouched. Past it, the click
     that follows the release is swallowed in the capture phase, which
     is the only phase that can stop the two click listeners already
     bound to this button.
     ================================================================== */
  (function () {
    var MARGIN    = 12;   // px kept clear of the viewport edge
    var THRESHOLD = 6;    // px of travel before a press becomes a drag
    var KEY       = 'bud.pos';

    /* The offset currently applied, in px, from wherever the CSS puts
       the widget. This is the only piece of state that matters. */
    var dx = 0, dy = 0;

    var pressing = false, dragging = false, moved = false;
    var startX = 0, startY = 0;     // where the press landed
    var grabX  = 0, grabY  = 0;     // offset of the press inside the widget
    var baseX  = 0, baseY  = 0;     // where the widget sits at dx=dy=0

    function vw() { return window.innerWidth; }
    function vh() { return window.innerHeight; }
    function clamp(n, lo, hi) { return n < lo ? lo : (n > hi ? hi : n); }

    function apply() {
      bud.style.transform = 'translate3d(' + dx + 'px,' + dy + 'px,0)';
      bud.classList.add('bud--moved');
    }

    /* Where the widget would be with no offset. Measured rather than
       assumed, because it differs by breakpoint — 2rem/2.75rem from
       the corner on a desktop, 1rem/2.4rem on a phone. */
    function measureBase() {
      var r = bud.getBoundingClientRect();
      baseX = r.left - dx;
      baseY = r.top  - dy;
      return r;
    }

    function sideFor(left, width) {
      return left + width / 2 < vw() / 2 ? 'left' : 'right';
    }

    /* Park at a resting place, given a top-left in viewport pixels. */
    function settle(left, top, animate) {
      var r = bud.getBoundingClientRect();

      if (animate) bud.classList.add('is-snapping');

      dx = left - baseX;
      dy = top  - baseY;
      apply();

      bud.classList.toggle('bud--left', sideFor(left, r.width) === 'left');
      bud.classList.toggle('bud--top',  top + r.height / 2 < vh() / 2);

      if (animate) {
        setTimeout(function () { bud.classList.remove('is-snapping'); }, 280);
      }
    }

    /* Saved as RATIOS of the viewport. A position stored in portrait is
       meaningless in landscape, and one stored on a desktop is
       off-screen on a phone; a side and a height survive both. */
    function save(left, top) {
      var r = bud.getBoundingClientRect();
      try {
        localStorage.setItem(KEY, JSON.stringify({
          side: sideFor(left, r.width),
          y:    (top + r.height / 2) / vh()
        }));
      } catch (e) {}    /* private browsing throws. Not worth failing over. */
    }

    function restore() {
      var raw = null;
      try { raw = localStorage.getItem(KEY); } catch (e) {}
      if (!raw) return;

      var pos;
      try { pos = JSON.parse(raw); } catch (e) { return; }
      if (!pos || typeof pos.y !== 'number') return;

      var r = measureBase();
      settle(
        pos.side === 'left' ? MARGIN : vw() - r.width - MARGIN,
        clamp(pos.y * vh() - r.height / 2, MARGIN, vh() - r.height - MARGIN),
        false
      );
    }

    /* ---- the gesture ---- */

    toggle.addEventListener('pointerdown', function (e) {
      /* Not while the chat is open — the launcher is a close button at
         that point. */
      if (bud.classList.contains('is-open')) return;
      if (e.button) return;                       // right / middle click

      var r = measureBase();

      pressing = true;
      dragging = false;
      moved    = false;
      startX   = e.clientX;
      startY   = e.clientY;
      grabX    = e.clientX - r.left;
      grabY    = e.clientY - r.top;

      /* IMMEDIATELY, not after the threshold. This is what guarantees
         every subsequent move and the release arrive here — even once
         the finger has left the button, which on a 64px target happens
         within the first centimetre. */
      if (toggle.setPointerCapture) {
        try { toggle.setPointerCapture(e.pointerId); } catch (err) {}
      }
    });

    toggle.addEventListener('pointermove', function (e) {
      if (!pressing) return;

      if (!dragging) {
        if (Math.abs(e.clientX - startX) < THRESHOLD &&
            Math.abs(e.clientY - startY) < THRESHOLD) return;

        dragging = true;
        moved    = true;
        bud.classList.add('is-dragging');
      }

      /* Cancels the browser's own idea of what this gesture is for —
         text selection on a desktop, scrolling on a phone. */
      e.preventDefault();

      var r = bud.getBoundingClientRect();

      dx = clamp(e.clientX - grabX, MARGIN, vw() - r.width  - MARGIN) - baseX;
      dy = clamp(e.clientY - grabY, MARGIN, vh() - r.height - MARGIN) - baseY;
      apply();
    });

    function release() {
      if (!pressing) return;
      pressing = false;

      if (!dragging) return;        // a tap. Leave the click alone.
      dragging = false;
      bud.classList.remove('is-dragging');

      var r = bud.getBoundingClientRect();
      var left = sideFor(r.left, r.width) === 'left'
                   ? MARGIN
                   : vw() - r.width - MARGIN;
      var top  = clamp(r.top, MARGIN, vh() - r.height - MARGIN);

      settle(left, top, true);
      save(left, top);
    }

    toggle.addEventListener('pointerup', release);
    toggle.addEventListener('lostpointercapture', release);

    toggle.addEventListener('pointercancel', function () {
      pressing = false;
      dragging = false;
      bud.classList.remove('is-dragging');
    });

    /* An image can be dragged out of a page by itself, and that native
       drag is a separate mechanism that beats pointer events: it fires
       pointercancel and leaves a translucent copy following the cursor
       while the real widget stays put. bud.css turns it off with
       user-drag; this covers Firefox, which has no such property. */
    toggle.addEventListener('dragstart', function (e) { e.preventDefault(); });

    /* The click after a drag has to be swallowed or letting go opens
       the chat every time. Capture phase on the window: the two click
       listeners already bound to this button run in the bubble phase,
       and nothing added later there could stop them. */
    window.addEventListener('click', function (e) {
      if (!moved) return;
      moved = false;

      if (toggle === e.target || toggle.contains(e.target)) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);

    /* A rotation or a resized window can leave the widget off-screen.
       Re-clamped against the new viewport, keeping the side and the
       height the visitor chose. */
    var resizeTimer = null;
    window.addEventListener('resize', function () {
      if (!bud.classList.contains('bud--moved')) return;   // CSS still owns it
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        dx = dy = 0;
        bud.style.transform = '';
        measureBase();
        restore();
      }, 120);
    });

    restore();
  }());


  /* ---------- rendering ---------- */

  function scrollLog() { log.scrollTop = log.scrollHeight; }

  /* textContent, never innerHTML. Whatever comes back from a model is
     untrusted text; assigning it as HTML is how a chat widget becomes
     an XSS hole. */
  /* ---------- rendering a reply ----------

     STILL NO innerHTML. Model output is untrusted text, and it echoes
     the visitor's own words back inside it. Everything below is built
     with createElement and textContent, so there is no path from a
     reply to executable markup.

     Tables are gone. In a 380px panel three columns left cells too
     narrow to read, and the model kept emitting the whole table on one
     line, which reached the visitor as a row of pipe characters. The
     shape now is: a sentence or two, then lettered sections.

         Malatap Falls is a short trek inland from the highway.

         A. How to get there
         - Jeep, Daet to Malatap, 1.5-2 hours
         - Walk to the falls, 15 minutes

         B. Budget
         - Environmental fee not confirmed
  */

  /* Markdown emphasis arrives as **bold**. The asterisks are stripped
     rather than rendered — a stray ** looks like a mistake, and bold
     inside a 380px panel adds nothing. */
  function plain(s) {
    return String(s).replace(/\*\*/g, '').trim();
  }

  /* "A. How to get there" or "1. Getting there" — a heading, not prose. */
  function headingText(line) {
    var m = /^\s*([A-Z]|\d{1,2})[.)]\s+(.{2,60})$/.exec(line);
    return m ? m[2].trim() : null;
  }

  function bulletText(line) {
    var m = /^\s*[-*\u2022]\s+(.+)$/.exec(line);
    return m ? m[1].trim() : null;
  }

  /* A divider row from a markdown table: |---|---|  or | :--- | */
  function isTableDivider(line) {
    return /\|/.test(line) && /^[\s|:\-]+$/.test(line) && line.indexOf('-') !== -1;
  }

  /* The model still reaches for a table sometimes, despite the prompt.
     Rather than print raw pipes, flatten the row into readable text.
     Not pretty, but never broken. */
  function flattenRow(line) {
    return line.split('|')
               .map(function (c) { return c.trim(); })
               .filter(function (c) {
                 /* Drop empties AND divider cells. On a one-line table
                    the dividers sit mid-line, so the whole-line check
                    above never sees them and they would otherwise come
                    through as "- ----- - -----". */
                 return c !== '' && !/^:?-{2,}:?$/.test(c);
               })
               .join(' - ');
  }

  function renderRich(text, into) {
    var lines = String(text).split('\n');
    var prose = [];
    var list  = null;

    function flushProse() {
      if (!prose.length) return;
      var p = document.createElement('p');
      p.textContent = prose.join(' ').trim();
      if (p.textContent) into.appendChild(p);
      prose = [];
    }

    function flushList() {
      list = null;   // the <ul> is already in the DOM; just stop adding
    }

    lines.forEach(function (raw) {
      var line = String(raw);

      if (isTableDivider(line)) return;          // drop it silently

      if (line.indexOf('|') !== -1) {
        line = flattenRow(line);
        if (line === '') return;
      }

      if (line.trim() === '') {
        flushProse(); flushList();
        return;
      }

      var head = headingText(line);
      if (head) {
        flushProse(); flushList();
        var h = document.createElement('div');
        h.className = 'bud__h';
        h.textContent = plain(head);
        into.appendChild(h);
        return;
      }

      var bullet = bulletText(line);
      if (bullet) {
        flushProse();
        if (!list) {
          list = document.createElement('ul');
          list.className = 'bud__list';
          into.appendChild(list);
        }
        var li = document.createElement('li');
        li.textContent = plain(bullet);
        list.appendChild(li);
        return;
      }

      flushList();
      prose.push(plain(line));
    });

    flushProse();

    if (!into.childNodes.length) {
      var p = document.createElement('p');
      p.textContent = text;
      into.appendChild(p);
    }
  }

  /* ---------- photo cards ----------

     The 24 destinations, injected by includes/bud-widget.php from the
     site's own database. When a reply names one, its photograph is
     shown underneath.

     Matching is done HERE against a fixed list, not by asking the
     model to tag its own output. Two reasons: the model does not have
     to cooperate for cards to appear, and a card can never show a
     place that is not one of the 24 - the list is the whitelist. */
  var budPlaces = [];
  try {
    var placesEl = document.getElementById('budPlaces');
    if (placesEl) budPlaces = JSON.parse(placesEl.textContent) || [];
  } catch (e) {
    budPlaces = [];   // cards are a bonus; never break the chat over them
  }

  /* Longest names first, so "Mananap Falls ATV Adventure" wins over
     "Mananap Falls" when both appear in the same sentence. */
  budPlaces.sort(function (a, b) { return b.n.length - a.n.length; });

  function findPlaces(text, limit) {
    var hay = text.toLowerCase();
    var hits = [];
    var taken = [];

    for (var i = 0; i < budPlaces.length && hits.length < limit; i++) {
      var name = budPlaces[i].n.toLowerCase();
      var at = hay.indexOf(name);
      if (at === -1) continue;

      /* Skip a name sitting inside one already matched, or
         "Mananap Falls" would card twice off the ATV entry. */
      var overlaps = taken.some(function (r) {
        return at < r[1] && (at + name.length) > r[0];
      });
      if (overlaps) continue;

      taken.push([at, at + name.length]);
      hits.push(budPlaces[i]);
    }
    return hits;
  }

  function addPlaceCards(text, into) {
    /* Two at most. A reply listing six beaches would otherwise bury
       its own text under a wall of photographs. */
    var found = findPlaces(text, 2);
    if (!found.length) return;

    var strip = document.createElement('div');
    strip.className = 'bud__places';

    found.forEach(function (pl) {
      var card = document.createElement('div');
      card.className = 'bud__place';

      if (pl.img) {
        var img = document.createElement('img');
        img.className = 'bud__place-img';
        img.src = pl.img;
        img.alt = '';
        img.loading = 'lazy';
        /* A missing photo should leave a tidy card, not a broken-image
           icon. The CSS gradient behind it becomes the picture. */
        img.addEventListener('error', function () { img.remove(); });
        card.appendChild(img);
      }

      var body = document.createElement('div');
      body.className = 'bud__place-body';

      var nm = document.createElement('span');
      nm.className = 'bud__place-name';
      nm.textContent = pl.n;
      body.appendChild(nm);

      if (pl.t) {
        var tw = document.createElement('span');
        tw.className = 'bud__place-town';
        tw.textContent = pl.t;
        body.appendChild(tw);
      }

      /* Coordinates open a normal Google Maps link. No API key, no
         billing, no map library - and it hands off to the app the
         visitor already has directions set up in. */
      if (pl.la !== null && pl.lo !== null && pl.la !== undefined) {
        var a = document.createElement('a');
        a.className = 'bud__place-map';
        a.href = 'https://www.google.com/maps/search/?api=1&query='
               + encodeURIComponent(pl.la + ',' + pl.lo);
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = 'View on map';
        body.appendChild(a);
      }

      card.appendChild(body);
      strip.appendChild(card);
    });

    into.appendChild(strip);
  }

  function addMessage(text, who) {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--' + (who === 'user' ? 'user' : 'bot');

    if (who === 'user') {
      /* The visitor's own words are never parsed for markup. */
      var p = document.createElement('p');
      p.textContent = text;
      wrap.appendChild(p);
    } else {
      renderRich(text, wrap);
      addPlaceCards(text, wrap);
      if (wrap.querySelector('.bud__h, .bud__list, .bud__places')) {
        wrap.classList.add('bud__msg--wide');
      }
    }

    var node = who === 'user' ? (log.appendChild(wrap), wrap) : mountBot(wrap);
    scrollLog();
    return node;
  }

  /* ---------- destination photographs ----------

     The server decides which places a reply mentions and sends them
     back; this only draws them. Nothing here parses the reply text,
     so there is no way for a reply to point at an arbitrary file. */
  function addPlaces(places) {
    if (!places || !places.length) return;

    var strip = document.createElement('div');
    strip.className = 'bud__places';

    places.forEach(function (p) {
      if (!p || !p.image) return;

      var card = document.createElement('div');
      card.className = 'bud__place';

      var img = document.createElement('img');
      img.className = 'bud__place-img';
      img.src = assetBase + p.image;
      img.alt = '';
      img.loading = 'lazy';

      /* A missing photo should leave a tidy gap, not a broken-image
         icon inside a chat bubble. */
      img.addEventListener('error', function () { card.remove(); });

      var cap = document.createElement('div');
      cap.className = 'bud__place-cap';

      var nm = document.createElement('span');
      nm.className = 'bud__place-name';
      nm.textContent = p.name;
      cap.appendChild(nm);

      if (p.town) {
        var tw = document.createElement('span');
        tw.className = 'bud__place-town';
        tw.textContent = p.town;
        cap.appendChild(tw);
      }

      card.appendChild(img);
      card.appendChild(cap);
      strip.appendChild(card);
    });

    if (strip.childNodes.length) {
      log.appendChild(strip);
      scrollLog();
    }
  }

  function addTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot';
    wrap.classList.add('bud__msg--typing');
    wrap.setAttribute('aria-label', 'Bud is typing');
    wrap.innerHTML = '<span class="bud__typing"><i></i><i></i><i></i></span>';
    /* The row is returned, so typing.remove() takes the avatar too. */
    var row = mountBot(wrap);
    scrollLog();
    return row;
  }

  /* ---------- the opening suggestions ----------

     Printed under the greeting, once, on first open. An empty chat
     box asks the visitor to think of something; three chips tell them
     what this thing is actually for — and the first one is the
     feature worth finding. */
  var SUGGESTIONS = [
    { label: '\u2728 Plan my trip for me', itinerary: true },
    { label: '\uD83C\uDFDD\uFE0F How do I get to Calaguas?', ask: 'How do I get to Calaguas?' },
    { label: '\uD83C\uDF92 What should I pack?', ask: 'What should I pack?' },
    { label: '\uD83D\uDCA7 Best waterfalls to visit', ask: 'What are the best waterfalls to visit?' }
  ];

  var suggestionsShown = false;
  var suggestEl = null;

  /* The strip goes as soon as the conversation starts, whichever way it
     starts — a chip, a typed question, or the itinerary builder. */
  function hideSuggestions() {
    if (suggestEl) { suggestEl.remove(); suggestEl = null; }
  }

  function showSuggestions() {
    if (suggestionsShown) return;
    suggestionsShown = true;

    /* A sideways strip just above the text box, not a stack inside the
       conversation: the chips sit where the visitor is about to type,
       and they never push Bud's messages around. */
    var wrap = document.createElement('div');
    wrap.className = 'bud__suggest';
    wrap.setAttribute('role', 'group');
    wrap.setAttribute('aria-label', 'Suggested questions');
    suggestEl = wrap;

    SUGGESTIONS.forEach(function (sg) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bud__chip' + (sg.itinerary ? ' bud__chip--go' : '');
      b.textContent = sg.label;

      b.addEventListener('click', function () {
        hideSuggestions();
        var said = sg.ask || sg.label;

        if (sg.itinerary) {
          addMessage('Can you make me an itinerary?', 'user');
          startWizard();
        } else {
          /* The other two are ordinary questions — put the text in and
             go through the same path a typed message takes, so there
             is one send path rather than two. */
          input.value = said;
          submit();
        }
      });

      wrap.appendChild(b);
    });

    /* Inserted next to the text box through the box's OWN parent. The
       map view can wrap the panel's contents in another element, so the
       compose bar is not always a direct child of the panel — and
       panel.insertBefore() throws in that case, which is why the chips
       silently never appeared. */
    wrap.classList.add('bud__suggest--bar');

    /* The bar: the scrolling row plus an arrow at each end. The arrows
       are for a mouse, which has no sideways swipe; a finger just
       swipes the row. */
    var bar = document.createElement('div');
    bar.className = 'bud__suggest-bar';

    function arrow(dir) {
      var a = document.createElement('button');
      a.type = 'button';
      a.className = 'bud__suggest-arrow bud__suggest-arrow--' + dir;
      a.setAttribute('aria-label', dir === 'prev' ? 'Previous suggestions' : 'More suggestions');
      a.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'
                  + (dir === 'prev' ? 'M15 18l-6-6 6-6' : 'M9 18l6-6-6-6') + '"/></svg>';
      a.addEventListener('click', function () {
        wrap.scrollBy({ left: (dir === 'prev' ? -1 : 1) * wrap.clientWidth * 0.7, behavior: 'smooth' });
      });
      return a;
    }

    bar.appendChild(arrow('prev'));
    bar.appendChild(wrap);
    bar.appendChild(arrow('next'));
    suggestEl = bar;

    /* Which ends still have something hidden. Drives the arrows and the
       edge fades, so neither shows when there is nothing to scroll to. */
    function updateEnds() {
      var max = wrap.scrollWidth - wrap.clientWidth;
      bar.classList.toggle('can-prev', wrap.scrollLeft > 2);
      bar.classList.toggle('can-next', wrap.scrollLeft < max - 2);
    }
    wrap.addEventListener('scroll', updateEnds, { passive: true });
    window.addEventListener('resize', updateEnds);

    /* A mouse wheel scrolls up and down. Over this row, turn that into
       sideways movement — but only while there is room to move, so the
       wheel still works normally once the row is at its end. */
    wrap.addEventListener('wheel', function (e) {
      if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;   // trackpad already sideways
      var max = wrap.scrollWidth - wrap.clientWidth;
      if (max <= 0) return;
      if ((e.deltaY < 0 && wrap.scrollLeft <= 0) ||
          (e.deltaY > 0 && wrap.scrollLeft >= max)) return;
      e.preventDefault();
      wrap.scrollLeft += e.deltaY;
    }, { passive: false });

    /* Click-and-drag with a mouse, like swiping. A drag of more than a
       few pixels swallows the click, so letting go over a chip does not
       send it. Touch is left to the browser's own swipe. */
    var dragX = null, dragStart = 0, dragged = false;
    wrap.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse' || e.button) return;
      dragX = e.clientX; dragStart = wrap.scrollLeft; dragged = false;
    });
    wrap.addEventListener('pointermove', function (e) {
      if (dragX === null) return;
      var d = e.clientX - dragX;
      if (!dragged && Math.abs(d) > 5) {
        dragged = true;
        wrap.classList.add('is-dragging');
        try { wrap.setPointerCapture(e.pointerId); } catch (err) {}
      }
      if (dragged) wrap.scrollLeft = dragStart - d;
    });
    function endDrag() {
      dragX = null;
      wrap.classList.remove('is-dragging');
    }
    wrap.addEventListener('pointerup', endDrag);
    wrap.addEventListener('pointercancel', endDrag);
    wrap.addEventListener('click', function (e) {
      if (dragged) { e.preventDefault(); e.stopPropagation(); dragged = false; }
    }, true);

    var compose = panel.querySelector('.bud__compose');
    if (compose && compose.parentNode) {
      compose.parentNode.insertBefore(bar, compose);
    } else {
      log.appendChild(bar);
    }

    /* Measured after it is in the page, and again once the chips have
       finished animating in and have their real widths. */
    updateEnds();
    setTimeout(updateEnds, 450);
  }

  /* ==================================================================
     ITINERARY BUILDER

     Four questions, then a plan. The questions are BUTTONS, not
     typing: on a phone, tapping "3-4 people" beats spelling it out,
     and a fixed set of answers means the server never has to guess
     what somebody meant by "a few of us".

     No model call happens until all four are answered, so the whole
     interview is free. Only the generation costs a request, which
     matters on a 500-a-day quota.
     ================================================================== */

  var STEPS = [
    { key: 'travellers', q: 'How many of you are travelling?',
      opts: [ { v: 1, l: 'Just me' }, { v: 2, l: '2 of us' },
              { v: 4, l: '3-4' },     { v: 8, l: '5 or more' } ] },

    { key: 'nights', q: 'How many nights are you staying?',
      opts: [ { v: 1, l: '1 night' }, { v: 2, l: '2 nights' },
              { v: 3, l: '3 nights' }, { v: 5, l: '5 or more' } ] },

    { key: 'perDay', q: 'How many places per day?',
      opts: [ { v: 1, l: 'One, take it slow' },
              { v: 2, l: 'Two' },
              { v: 3, l: 'Three' } ] },

    { key: 'budget', q: 'What kind of budget?',
      opts: [ { v: 'budget', l: 'Backpacker' },
              { v: 'mid', l: 'Middle' },
              { v: 'comfortable', l: 'Comfortable' } ] },

    /* The only multi-choice step, so it needs a Done button — the
       others advance on the tap itself. */
    { key: 'interests', q: 'What are you most interested in?',
      multi: true,
      opts: [ { v: 'beaches', l: 'Beaches' },   { v: 'waterfalls', l: 'Waterfalls' },
              { v: 'islands', l: 'Islands' },   { v: 'hiking', l: 'Hiking' },
              { v: 'heritage', l: 'Heritage' }, { v: 'food', l: 'Food' },
              { v: 'relaxed', l: 'Take it easy' }, { v: 'adventure', l: 'Adventure' } ] },

    /* The only typed answer. A name cannot be a set of buttons, and
       "Lei's birthday trip" is worth more to the person who wrote it
       than anything a model would name it. Skippable, because being
       made to name a thing before seeing it is a poor trade. */
    { key: 'name', q: 'What would you like to call this trip?',
      text: true, placeholder: 'Bicol long weekend', skip: 'Let Bud name it' }
  ];

  var wizard = null;   // null when not building

  function startWizard() {
    wizard = { step: 0, brief: { interests: [] } };

    /* A friendly lead-in before the first question, so the interview
       feels like Bud getting to know the trip, not a form. */
    /* "Madya" is Bicol for "come on" / "let's go". */
    addMessage('Madya, let\u2019s plan your trip! \uD83E\uDDF3 A few quick taps and I\u2019ll put together a day-by-day plan for you.', 'bot');
    askStep();
  }

  function askStep() {
    var step = STEPS[wizard.step];

    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot bud__msg--wide';

    var p = document.createElement('p');
    p.textContent = step.q;
    wrap.appendChild(p);

    var opts = document.createElement('div');
    opts.className = 'bud__opts';

    /* ---- the typed step ---- */
    if (step.text) {
      var field = document.createElement('input');
      field.type = 'text';
      field.className = 'bud__opt-text';
      field.placeholder = step.placeholder || '';
      field.maxLength = 60;

      var go = document.createElement('button');
      go.type = 'button';
      go.className = 'bud__opt bud__opt--go';
      go.textContent = 'Build my plan';

      var skip = document.createElement('button');
      skip.type = 'button';
      skip.className = 'bud__opt';
      skip.textContent = step.skip || 'Skip';

      function finishText(value) {
        wizard.brief[step.key] = value;
        lockOptions(opts, value || (step.skip || 'Skipped'));
        nextStep();
      }

      go.addEventListener('click', function () {
        if (!wizard) return;
        finishText(field.value.trim());
      });

      /* Enter submits, so nobody has to reach for the button after
         typing. The panel's own send box is a different control and
         must not fire here. */
      field.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); go.click(); }
      });

      skip.addEventListener('click', function () {
        if (!wizard) return;
        finishText('');
      });

      opts.appendChild(field);
      opts.appendChild(go);
      opts.appendChild(skip);
      wrap.appendChild(opts);
      mountBot(wrap);
      scrollLog();
      if (window.matchMedia('(hover:hover)').matches) field.focus();
      return;
    }

    step.opts.forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bud__opt';
      b.textContent = o.l;

      b.addEventListener('click', function () {
        if (!wizard) return;

        if (step.multi) {
          /* Toggle, and let them keep choosing. aria-pressed rather
             than a class alone, so the state is announced. */
          var on = b.getAttribute('aria-pressed') === 'true';
          b.setAttribute('aria-pressed', on ? 'false' : 'true');
          var list = wizard.brief.interests;
          if (on) {
            list.splice(list.indexOf(o.v), 1);
          } else if (list.indexOf(o.v) === -1) {
            list.push(o.v);
          }
          done.disabled = list.length === 0;
          return;
        }

        wizard.brief[step.key] = o.v;
        lockOptions(opts, o.l);
        nextStep();
      });

      if (step.multi) b.setAttribute('aria-pressed', 'false');
      opts.appendChild(b);
    });

    var done = null;
    if (step.multi) {
      done = document.createElement('button');
      done.type = 'button';
      done.className = 'bud__opt bud__opt--go';
      done.textContent = 'Build my plan';
      done.disabled = true;
      done.addEventListener('click', function () {
        if (!wizard || !wizard.brief.interests.length) return;
        lockOptions(opts, wizard.brief.interests.length + ' chosen');
        nextStep();
      });
      opts.appendChild(done);
    }

    wrap.appendChild(opts);
    mountBot(wrap);
    scrollLog();
  }

  /* Once answered, the buttons stop being buttons. Leaving them live
     invites a second tap that would answer a question already gone. */
  function lockOptions(opts, chosenLabel) {
    opts.innerHTML = '';
    var tag = document.createElement('span');
    tag.className = 'bud__opt-picked';
    tag.textContent = chosenLabel;
    opts.appendChild(tag);
  }

  function nextStep() {
    wizard.step++;
    if (wizard.step < STEPS.length) {
      askStep();
    } else {
      generatePlan();
    }
  }

  /* ---------- generate, then save ---------- */

  function generatePlan() {
    var brief = wizard.brief;
    wizard = null;

    if (previewMode) {
      addMessage('I need to be connected to the assistant before I can build a plan. '
               + 'This is a design preview.', 'bot');
      return;
    }

    busy = true;
    send.disabled = true;
    var typing = addTyping();

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ mode: 'itinerary', brief: brief })
    })
    .then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (d) {
        if (!res.ok || !d.plan) {
          var err = new Error('itinerary HTTP ' + res.status);
          if (d && d.error) err.budMessage = d.error;
          throw err;
        }
        return d.plan;
      });
    })
    .then(function (plan) {
      typing.remove();
      renderPlan(plan);
      savePlan(plan);
    })
    .catch(function (err) {
      typing.remove();
      addMessage((err && err.budMessage)
        || 'I could not build a plan just then. Try again?', 'bot');
      console.error('Bud.Ai itinerary failed:', err);
    })
    .then(function () {
      busy = false;
      send.disabled = false;
    });
  }

  function renderPlan(plan) {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot bud__msg--wide';

    var h = document.createElement('div');
    h.className = 'bud__plan-name';
    h.textContent = plan.name;
    wrap.appendChild(h);

    if (plan.summary) {
      var p = document.createElement('p');
      p.textContent = plan.summary;
      wrap.appendChild(p);
    }

    plan.days.forEach(function (day, i) {
      var dh = document.createElement('div');
      dh.className = 'bud__h';
      dh.textContent = 'Day ' + (i + 1);
      wrap.appendChild(dh);

      var ul = document.createElement('ul');
      ul.className = 'bud__plan-day';

      day.items.forEach(function (it) {
        var li = document.createElement('li');

        if (it.time) {
          var t = document.createElement('span');
          t.className = 'bud__plan-time';
          t.textContent = it.time;
          li.appendChild(t);
        }

        var body = document.createElement('span');
        body.className = 'bud__plan-body';

        var nm = document.createElement('b');
        nm.textContent = it.name || it.destId;
        body.appendChild(nm);

        if (it.town) {
          var tw = document.createElement('span');
          tw.className = 'bud__plan-town';
          tw.textContent = it.town;
          body.appendChild(tw);
        }

        if (it.note) {
          var nt = document.createElement('span');
          nt.className = 'bud__plan-note';
          nt.textContent = it.note;
          body.appendChild(nt);
        }

        li.appendChild(body);
        ul.appendChild(li);
      });

      wrap.appendChild(ul);
    });

    mountBot(wrap);
    scrollLog();
  }

  /* Saving is separate from generating on purpose. A visitor who is
     not signed in still gets to SEE their plan; only keeping it needs
     an account. Losing the plan at the sign-in prompt would be the
     rudest possible moment to ask. */
  function savePlan(plan) {
    fetch('save-itinerary.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(plan)
    })
    .then(function (res) {
      return res.json().catch(function () { return {}; })
        .then(function (d) { return { status: res.status, data: d }; });
    })
    .then(function (r) {
      if (r.status === 401) {
        addSaveNotice('Sign in and I will keep this on your account.', true);
        return;
      }

      if (!r.data || !r.data.ok) {
        /* SHOW WHAT THE SERVER SAID. The first version of this printed
           one generic line for every failure, which meant a missing
           file, a database error and a validation problem all looked
           identical — and left nothing to act on. The endpoint writes
           its messages for a person to read, so they are worth
           showing. */
        var why = (r.data && r.data.message) ? r.data.message : '';

        if (r.status === 404) {
          why = 'save-itinerary.php is not on the server yet.';
        } else if (!why) {
          why = 'The server answered with ' + r.status + '.';
        }

        addSaveNotice('I could not save this one — ' + why
                    + ' Your plan is above, so nothing is lost.', false);
        console.error('Bud.Ai save failed:', r.status, r.data);
        return;
      }

      addSaveNotice('Saved! Dios mabalos \uD83D\uDE4F You will find it under Plan your trip.', false, 'plan-trip.php');
    })
    .catch(function (e) {
      addSaveNotice('I could not reach the server to save that. Your plan is above, so nothing is lost.', false);
      console.error('Bud.Ai save failed:', e);
    });
  }

  function addSaveNotice(text, needsSignIn, link) {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot';

    var p = document.createElement('p');
    p.textContent = text;
    wrap.appendChild(p);

    if (needsSignIn) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bud__opt bud__opt--go';
      b.textContent = 'Sign in';
      /* The site's own sign-in prompt, the same hook the header and
         plan-trip.php use. Not a second login invented here. */
      b.setAttribute('data-auth-gate', '');
      wrap.appendChild(b);
    }

    if (link) {
      var a = document.createElement('a');
      a.className = 'bud__opt bud__opt--go';
      a.href = link;
      a.textContent = 'Open Plan your trip';
      wrap.appendChild(a);
    }

    mountBot(wrap);
    scrollLog();
  }

  /* ---------- sending ---------- */

  /* Someone typing the request rather than tapping the chip. Kept
     narrow deliberately: "itinerary" or "plan my trip" starts the
     builder, but "what is on the itinerary for Calaguas" is a
     question and should stay one. */
  function looksLikeItineraryRequest(t) {
    t = t.toLowerCase();
    return /(create|make|build|plan|generate|gawa|gumawa)\b/.test(t)
        && /(itinerary|itenerary|trip|plan)\b/.test(t);
  }

  function submit() {
    var text = input.value.trim();
    if (!text || busy) return;

    hideSuggestions();

    if (!wizard && looksLikeItineraryRequest(text)) {
      addMessage(text, 'user');
      input.value = '';
      startWizard();
      return;
    }

    busy = true;
    send.disabled = true;

    addMessage(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';

    var typing = addTyping();

    askBud(text)
      .then(function (result) {
        var reply  = result.reply;
        var places = result.places;

        typing.remove();
        addMessage(reply, 'bot');
        addPlaces(places);
        history.push({ role: 'assistant', content: reply });
        if (history.length > HISTORY_MAX) {
          history = history.slice(-HISTORY_MAX);
        }
      })
      .catch(function (err) {
        typing.remove();

        /* The server writes messages meant to be read by a visitor, so
           show its text when there is some. Drop the failed question
           from the history — keeping a turn the model never answered
           leaves a gap that confuses the next reply. */
        addMessage(
          (err && err.budMessage) ||
          'I could not reach the server just now. Check your connection and try again.',
          'bot'
        );

        history = history.filter(function (t) {
          return !(t.role === 'user' && t.content === text);
        });

        console.error('Bud.Ai request failed:', err);
      })
      .then(function () {
        busy = false;
        send.disabled = false;
        input.focus();
      });
  }

  send.addEventListener('click', submit);

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      submit();
    }
  });

  /* ==================================================================
     askBud(message) — talks to api/bud.php, which talks to OpenAI.

     The key is NOT here and must never be. Everything in this file is
     downloaded by every visitor, so a key in front-end code is public
     the moment you deploy — see the setup notes at the top of
     api/bud.php.

     Returns a Promise of the reply text. Errors carry a budMessage
     property when the server sent something worth showing the visitor
     (rate limited, message too long); anything else falls back to the
     generic line in the catch above.
     ================================================================== */
  function askBud(message) {

    /* No backend yet. Reply with something that says so plainly rather
       than failing, so the design can be demonstrated end to end.

       Deliberately does NOT answer the travel question. A preview that
       invents a boat fare to look impressive is the one thing this
       whole widget is built to avoid — and a canned answer is far more
       likely to be believed than a stated limitation. */
    if (previewMode) {
      return new Promise(function (resolve) {
        setTimeout(function () {
          resolve({ places: [], reply:
            'This is a design preview \u2014 I am not connected to an AI ' +
            'yet, so I cannot answer that properly. Once api/bud.php is ' +
            'set up I will be answering from the tourism database.'
          });
        }, 750);   // stand-in for network latency, so the dots show
      });
    }

    return fetch(endpoint, {
      method : 'POST',
      headers: { 'Content-Type': 'application/json' },

      /* Same-origin, so the session cookie the rate limiter counts
         against rides along automatically. */
      credentials: 'same-origin',

      body: JSON.stringify({
        message: message,
        history: history.slice(0, -1)   // drop the turn just pushed
      })
    })
    .then(function (res) {
      return res.json()
        .catch(function () { return {}; })   // HTML error page, not JSON
        .then(function (data) {
          if (!res.ok || !data.reply) {
            var err = new Error('bud.php HTTP ' + res.status);
            if (data && data.error) {
              err.budMessage = data.error;
            }
            throw err;
          }
          return { reply: data.reply, places: data.places || [] };
        });
    });
  }
});