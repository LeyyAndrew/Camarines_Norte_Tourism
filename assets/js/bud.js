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

  /* The bar of pre-written questions above the text box (Plan my trip,
     How do I get to Calaguas?, ...). Off. Set to true to bring it back.
     The follow-up buttons under Bud's replies (Check the prices, Make it
     cheaper, ...) have been removed. Only "Try again" after an error is
     left. */
  var SHOW_SUGGESTIONS   = false;

  /* The server refuses anything over 1000 characters with a 413. Stop
     the box at the same number instead of failing after the send. */
  if (!(input.maxLength > 0)) input.maxLength = 1000;

  /* Styles for the pieces added below (inline place links, quick-reply
     chips, the reveal animation). Injected from here so this file does
     not depend on a matching edit in bud.css. Existing classes such as
     .bud__chip are reused, not redefined. */
  (function () {
    if (document.getElementById('budPolishCss')) return;
    var pin = encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 22s8-6.4 8-12a8 8 0 1 0-16 0c0 5.6 8 12 8 12Z"/></svg>');
    var st = document.createElement('style');
    st.id = 'budPolishCss';
    st.textContent =
      '.bud__pl{display:inline;font:inherit;font-weight:600;color:inherit;background:none;border:0;' +
        'border-bottom:1.5px solid #F2A93B;border-radius:0;padding:0 0 1px;margin:0;cursor:pointer;' +
        'text-align:left;white-space:normal;line-height:inherit}' +
      '.bud__pl::before{content:"";display:inline-block;width:.78em;height:.78em;margin-right:.26em;' +
        'vertical-align:-.06em;background:#F2A93B;' +
        '-webkit-mask:url("data:image/svg+xml,' + pin + '") center/contain no-repeat;' +
        'mask:url("data:image/svg+xml,' + pin + '") center/contain no-repeat}' +
      /* Place links hold plain text only. Anything another script or
         stylesheet injects into one (a category icon, a badge) is hidden
         so it cannot push the link onto its own line mid-sentence. */
      '.bud__pl>*{display:none!important}' +
      '.bud__pl{vertical-align:baseline;max-width:100%;overflow-wrap:anywhere;-webkit-tap-highlight-color:transparent}' +
      '.bud__pl:hover,.bud__pl:focus-visible{color:#F2A93B;outline:none}' +
      '.bud__pl:focus-visible{border-bottom-width:2px}' +
      '.bud__msg strong{font-weight:700}' +
      '.bud__msg em{font-style:italic}' +
      '.bud__msg code{font-size:.92em;padding:.05em .35em;border-radius:5px;background:rgba(255,255,255,.1)}' +
      '.bud__plan-time{white-space:nowrap}' +
      '.bud__n{opacity:.65;margin-right:.4em;font-variant-numeric:tabular-nums}' +
      '.bud__qr{display:flex;flex-wrap:wrap;gap:.45rem;margin:.15rem 0 .5rem 2.5rem}' +
      '.bud__typing-label{margin-left:.65rem;font-size:.82em;opacity:.7;align-self:center}' +
      '.bud-old{opacity:.55}' +
      '.bud-old .bud__swap{display:none}' +
      '.bud__swap{display:block;margin-top:.3rem}' +
      '.bud__swap-btn{font:inherit;font-size:.78em;font-weight:600;color:#F2A93B;background:none;' +
        'border:1px solid rgba(242,169,59,.55);border-radius:99px;padding:.1em .7em;cursor:pointer}' +
      '.bud__swap-btn:hover,.bud__swap-btn:focus-visible{background:rgba(242,169,59,.16);outline:none}' +
      '.bud__swap-sel{font:inherit;font-size:.85em;margin-left:.5rem;max-width:100%;padding:.2em .4em;border-radius:8px;' +
        'color:inherit;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.25)}' +
      '.bud__swap-sel option{color:#111;background:#fff}' +
      '.bud__old-note{font-size:.78em;opacity:.85;margin:0 0 .4rem}' +
      '.bud__tag{display:inline-block;margin-left:.5em;padding:.05em .55em;border-radius:99px;font-size:.72em;' +
        'font-weight:600;background:#F2A93B;color:#1b1b1b;vertical-align:.12em}' +
      '.bud__new{background:none;border:0;color:inherit;opacity:.8;cursor:pointer;padding:.35rem;margin:0 .2rem;' +
        'border-radius:8px;line-height:0}' +
      '.bud__new:hover,.bud__new:focus-visible{opacity:1;background:rgba(255,255,255,.14);outline:none}' +
      '.bud__new svg{width:18px;height:18px}' +
      '.bud-reveal>*{animation:budRise .38s cubic-bezier(.2,.7,.2,1) both}' +
      '.bud-still.bud-reveal>*{animation:none}' +
      '@keyframes budRise{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}' +
      '@media (prefers-reduced-motion:reduce){.bud-reveal>*{animation:none}}';
    document.head.appendChild(st);
  })();

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

  /* Kept across page loads (this tab only) so the chat is not wiped by
     clicking to another page. See saveSession() / restoreSession(). */
  var SESSION_KEY         = 'bud.session.v1.0';   // becomes bud.session.v1.<userId> once the server says who is signed in
  var USER_ID             = 0;                    // 0 = guest. Set from the SERVER, never from the page.
  var identityReady       = false;                // nothing is saved until we know whose chat this is
  var serverTimer         = null;
  var SESSION_MAX_ENTRIES = 80;
  var transcript   = [];     // what is on screen, as data: messages and trip cards
  var restoring    = false;  // true while the saved chat is being drawn again
  var lastSavedId  = null;   // the saved-trip id from save-itinerary.php, if it sent one
  var lastPlanRow  = null;   // the newest trip card, so an older one can be marked as replaced
  var initialNodes = Array.prototype.slice.call(log.children);   // the page's own opening, kept on New chat

  /* ---------- open / close ---------- */

  function openPanel() {
    if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }

    /* No opening animation: freeze the messages already in the log so
       they do not all slide in again when the panel appears. */
    log.querySelectorAll('.bud__row, .bud__msg').forEach(function (el) {
      el.classList.add('bud-still');
    });

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
    }, 180);   /* the length of the closing fade in bud.css */

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
    var MARGIN    = 24;   // px kept clear of the viewport edge (was 12 — too tight on a laptop)
    var THRESHOLD = 6;    // px of travel before a press becomes a drag
    var KEY       = 'bud.pos';

    /* The offset currently applied, in px, from wherever the CSS puts
       the widget. This is the only piece of state that matters. */
    var dx = 0, dy = 0;

    var pressing = false, dragging = false, moved = false;
    var startX = 0, startY = 0;     // where the press landed
    var grabX  = 0, grabY  = 0;     // offset of the press inside the widget
    var baseX  = 0, baseY  = 0;     // where the widget sits at dx=dy=0

    /* clientWidth/clientHeight, NOT innerWidth/innerHeight. The inner
       sizes include the page scrollbar, so on a laptop "12px from the
       right edge" landed underneath the scrollbar and the widget — and
       the chat panel lined up with it — was cut off on the right. */
    function vw() { return document.documentElement.clientWidth  || window.innerWidth; }
    function vh() { return document.documentElement.clientHeight || window.innerHeight; }
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
    var m = /^\s*[A-Z][.)]\s+(.{2,60})$/.exec(line);
    if (m) return m[1].trim();
    m = /^\s*#{1,4}\s+(.{2,60})$/.exec(line);            // ### Heading
    if (m) return m[1].trim();
    m = /^\s*\*\*([^*]{2,50})\*\*:?\s*$/.exec(line);      // a line that is only **bold**
    return m ? m[1].trim() : null;
  }

  /* "1. Take a jeep to Labo" is a list item, not a heading. It used to
     be caught by headingText and rendered as a heading. */
  function numberedText(line) {
    var m = /^\s*(\d{1,2})[.)]\s+(.+)$/.exec(line);
    return m ? { n: m[1], text: m[2].trim() } : null;
  }

  /* ---------- inline formatting ----------
     **bold**, *italic* and `code` become real elements, and a known
     destination name becomes a tappable link that asks Bud about it.
     Still no innerHTML: every node is built with createElement and
     createTextNode, so model output cannot inject markup. */
  function escRe(x) { return x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  var placeRe = null;
  function placeRegex() {
    if (placeRe !== null) return placeRe;
    var names = budPlaces.map(function (p) { return p.n; }).filter(Boolean);
    placeRe = names.length ? new RegExp('(' + names.map(escRe).join('|') + ')', 'i') : false;
    return placeRe;
  }

  function placeChip(text) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'bud__pl';
    b.textContent = text;
    b.setAttribute('data-place', text);
    b.title = 'Ask Bud about ' + text;
    return b;
  }

  function addText(parent, str, seen) {
    var re = placeRegex();
    while (str) {
      var m = re ? re.exec(str) : null;
      if (!m) { parent.appendChild(document.createTextNode(str)); return; }
      if (m.index) parent.appendChild(document.createTextNode(str.slice(0, m.index)));
      var key = m[1].toLowerCase();
      if (seen[key]) {
        parent.appendChild(document.createTextNode(m[1]));   // only the first mention is a link
      } else {
        seen[key] = 1;
        parent.appendChild(placeChip(m[1]));
      }
      str = str.slice(m.index + m[1].length);
    }
  }

  var INLINE_TOK = /\*\*([^*\n]+?)\*\*|`([^`\n]+)`|\*([^*\s](?:[^*\n]*[^*\s])?)\*/g;
  function inline(parent, text, seen) {
    var last = 0, m, el;
    INLINE_TOK.lastIndex = 0;
    while ((m = INLINE_TOK.exec(text))) {
      if (m.index > last) addText(parent, text.slice(last, m.index), seen);
      if (m[1] !== undefined) { el = document.createElement('strong'); addText(el, m[1], seen); }
      else if (m[2] !== undefined) { el = document.createElement('code'); el.textContent = m[2]; }
      else { el = document.createElement('em'); addText(el, m[3], seen); }
      parent.appendChild(el);
      last = INLINE_TOK.lastIndex;
    }
    if (last < text.length) addText(parent, text.slice(last), seen);
  }

  /* Tapping a place name in a reply asks about it. */
  log.addEventListener('click', function (e) {
    var chip = e.target.closest ? e.target.closest('.bud__pl') : null;
    if (!chip || busy) return;
    input.value = 'Tell me more about ' + chip.getAttribute('data-place');
    submit();
  });

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

  /* A markdown table, however it arrived, becomes a tidy list.

     The model sometimes sends a whole table on ONE line
     ("| A | B | C | |---|---|---| | x | y | z | ..."), so rows cannot be
     found by splitting on newlines. Instead every real cell is gathered
     in order and cut into rows of N, where N is the number of divider
     cells (---). Each body row then reads:

         Bantayog Festival
         Where: Daet  \u00b7  When: March\u2013May

     No stylesheet change is needed: it is a normal bud__list. */
  function tableToList(cells, dividers, firstLineCount, into) {
    var n = dividers || firstLineCount || 3;
    if (n < 1) n = 1;
    var rows = [];
    for (var i = 0; i < cells.length; i += n) rows.push(cells.slice(i, i + n));
    if (!rows.length) return;

    var head = rows.shift();
    if (!rows.length) {                 // header only: show it as plain text
      var p = document.createElement('p');
      p.textContent = plain(head.join(', '));
      into.appendChild(p);
      return;
    }

    var ul = document.createElement('ul');
    ul.className = 'bud__list';
    rows.forEach(function (r) {
      var li = document.createElement('li');
      var strong = document.createElement('strong');
      strong.textContent = plain(r[0]);
      li.appendChild(strong);

      var rest = [];
      for (var c = 1; c < r.length; c++) {
        var label = head[c] ? plain(head[c]) + ': ' : '';
        rest.push(label + plain(r[c]));
      }
      if (rest.length) {
        li.appendChild(document.createElement('br'));
        li.appendChild(document.createTextNode(rest.join('  \u00b7  ')));
      }
      ul.appendChild(li);
    });
    into.appendChild(ul);
  }

  function renderRich(text, into) {
    var lines = String(text).split('\n');
    var prose = [];
    var list  = null;
    var seen  = {};   // place names already linked in this message

    function flushProse() {
      if (!prose.length) return;
      var p = document.createElement('p');
      inline(p, prose.join(' ').trim(), seen);
      if (p.textContent) into.appendChild(p);
      prose = [];
    }

    function flushList() {
      list = null;   // the <ul> is already in the DOM; just stop adding
    }

    /* Table cells collected from consecutive pipe lines. */
    var tCells = [], tDividers = 0, tFirst = 0;
    function flushTable() {
      if (!tCells.length) { tDividers = 0; tFirst = 0; return; }
      flushProse(); flushList();
      tableToList(tCells, tDividers, tFirst, into);
      tCells = []; tDividers = 0; tFirst = 0;
    }

    function listItem(content, num) {
      flushProse();
      if (!list) {
        list = document.createElement('ul');
        list.className = 'bud__list';
        into.appendChild(list);
      }
      var li = document.createElement('li');
      if (num) {
        var n = document.createElement('span');
        n.className = 'bud__n';
        n.textContent = num + '.';
        li.appendChild(n);
      }
      inline(li, content, seen);
      list.appendChild(li);
    }

    lines.forEach(function (raw) {
      var line = String(raw);

      if (line.indexOf('|') !== -1) {
        var parts = line.split('|').map(function (c) { return c.trim(); });
        var real = [];
        parts.forEach(function (c) {
          if (c === '') return;
          if (/^:?-{2,}:?$/.test(c)) { tDividers++; return; }
          real.push(c);
        });
        if (!tFirst) tFirst = real.length;
        tCells = tCells.concat(real);
        return;
      }
      flushTable();

      if (line.trim() === '') {
        flushProse(); flushList(); flushTable();
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
      if (bullet) { listItem(bullet, null); return; }

      var numd = numberedText(line);
      if (numd) { listItem(numd.text, numd.n); return; }

      flushList();
      prose.push(line.trim());
    });

    flushTable();
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

  function addMessage(text, who, still) {
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
      /* Blocks rise in one after another instead of the whole reply
         appearing at once. */
      wrap.classList.add('bud-reveal');
      [].forEach.call(wrap.children, function (c, i) {
        c.style.animationDelay = Math.min(i, 6) * 70 + 'ms';
      });
    }

    if (still) wrap.classList.add('bud-still');
    var node = who === 'user' ? (log.appendChild(wrap), wrap) : mountBot(wrap);
    if (!restoring) record({ k: 'm', w: who === 'user' ? 'user' : 'bot', x: text });
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

  function addTyping(label) {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot';
    wrap.classList.add('bud__msg--typing');
    wrap.setAttribute('aria-label', label || 'Bud is typing');
    wrap.innerHTML = '<span class="bud__typing"><i></i><i></i><i></i></span>';
    if (label) {
      /* Building a plan takes a while; say what is happening. */
      wrap.style.display = 'flex';
      wrap.style.alignItems = 'center';
      var lb = document.createElement('span');
      lb.className = 'bud__typing-label';
      lb.textContent = label;
      wrap.appendChild(lb);
    }
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
    if (!SHOW_SUGGESTIONS || suggestionsShown) return;
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
          startPlanning();
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
     ITINERARY CHAT

     No buttons, no fixed questions. The visitor types in their own
     words ("3 of us, 2 nights, we love beaches and food") and Bud
     works out what is still missing, asks for just that, and builds
     the plan once it has enough. Whatever they already said is never
     asked again.

     The conversation goes to api/bud.php with mode 'plan_chat'. That
     replies with { reply, ready, cancel, brief }. When ready is true
     the brief goes straight into the existing generate-then-save
     path below, so the plan is built, shown, and saved to Plan your
     trip exactly as before.

     Chatting is cheap; only the final generation is a big request.
     ================================================================== */

  var planning    = false;   // true while Bud is interviewing in chat
  var planHistory = [];      // the planning conversation only
  var planKnown   = null;    // what the server last said it had worked out
  var planRebuild = false;   // true when the visitor is changing a trip that was already built
  var currentPlan = null;    // the trip on screen, so the chat can talk about it
  var PLAN_HISTORY_MAX = 20;
  var DEFAULT_PLACEHOLDER = input.getAttribute('placeholder') || '';

  /* While planning, the text box says what to do. Put back afterwards. */
  function setPlanning(on) {
    planning = on;
    if (on) {
      input.setAttribute('placeholder', 'Tell Bud about your trip\u2026');
    } else {
      input.setAttribute('placeholder', DEFAULT_PLACEHOLDER);
      planHistory = [];
      planKnown   = null;
      planRebuild = false;
    }
  }

  /* A failed plan must not lose the trip. Re-open the planning chat with the
     brief as its first turn, so "2 nights instead" is read as a change to
     THIS trip rather than a fresh question. */
  function reopenPlanning(brief, asEdit) {
    if (!brief) return;
    var bits = [];
    if (brief.startDate) bits.push('start date ' + brief.startDate);
    if (brief.endDate)   bits.push('end date ' + brief.endDate);
    if (brief.nights)    bits.push(brief.nights + ' nights');
    if (brief.travellers) bits.push(brief.travellers + ' travellers');
    if (brief.budgetAmount) {
      bits.push('budget ' + brief.budgetAmount + ' pesos ('
        + (brief.budgetBasis === 'perPerson' ? 'per person'
        : brief.budgetBasis === 'perPersonPerDay' ? 'per person per day' : 'total') + ')');
    } else if (brief.budget) {
      bits.push('budget ' + brief.budget);
    }
    if (brief.dayStart && brief.dayEnd) bits.push('daily hours ' + brief.dayStart + ' to ' + brief.dayEnd);
    if (brief.perDay) bits.push(brief.perDay + ' places per day');
    if (brief.interests && brief.interests.length) bits.push('interests ' + brief.interests.join(', '));
    if (brief.places && brief.places.length) bits.push('must include ' + brief.places.join(', '));
    if (typeof brief.name === 'string') bits.push(brief.name ? 'trip name "' + brief.name + '"' : 'trip name: skip');
    setPlanning(true);
    planKnown = brief;   // so a long chat after a failed plan still knows the trip
    planRebuild = !!asEdit;   // a failed EDIT is retried as an edit of the trip still on screen
    planHistory = [
      { role: 'user', content: 'My trip so far: ' + bits.join('; ') + '.' },
      { role: 'assistant', content: 'I have your trip details, but the plan did not come out. What would you like to change before I build it again?' }
    ];
  }

  function releaseInput() {
    busy = false;
    send.disabled = false;
    input.focus();
  }

  /* The line Bud opens with when the visitor taps the "Plan my trip"
     chip. Ten of them, so it does not sound like a recording.

     Each is a short reaction, then ONE question. Every question asks
     for the start date and nothing else, because the planning chat is
     told that is what the visitor has just been asked, and reads their
     next message as the answer. If you add one, keep it that way: no
     second question, no place names, no facts, at most one emoji.
     "Madya" is Bicol for "come on" / "let's go"; "Dios mabalos" is
     "thank you". */
  /* One of several lines for a fixed message, never the same one twice
     running. Memory only: it is about not repeating inside one
     conversation, so nothing here needs to outlive the page. */
  var lastLine = {};
  function pickLine(key, lines) {
    var i = Math.floor(Math.random() * lines.length);
    if (i === lastLine[key]) i = (i + 1) % lines.length;
    lastLine[key] = i;
    return lines[i];
  }

  /* The fixed messages. Each line in a pool says the same thing, so any
     of them is safe to show. Add more freely, but keep what each one
     has to tell the visitor: that the plan is still above, that the
     connection failed, where the saved trip is. */
  var NET_LINES = [
    'I could not reach the server just now. Check your connection and try again.',
    'Hmm, I lost the connection for a moment. Could you check your signal and try again?',
    'That did not go through, probably the connection. Try once more?',
    'Sorry, I could not reach the server just then. Give it another go in a moment.'
  ];
  var PLAN_FAIL_LINES = [
    'I could not build a plan just then. Try again?',
    'That plan did not come together. Want me to try again?',
    'Something went wrong while I was building that one. Shall I try again?',
    'I got stuck putting that plan together. Let\u2019s try it once more?'
  ];
  var SIGN_IN_LINES = [
    'Sign in and I will keep this on your account.',
    'Want to keep this one? Sign in and I\u2019ll save it to your account.',
    'Sign in and I\u2019ll hold on to this plan for you.',
    'This one\u2019s worth keeping. Sign in and I\u2019ll save it to your account.'
  ];
  var SAVED_LINES = [
    'Saved! Dios mabalos \uD83D\uDE4F You will find it under Plan your trip.',
    'All saved. You\u2019ll find it under Plan your trip.',
    'Done, it\u2019s on your account now. Look for it under Plan your trip.',
    'Saved! You can open it any time from Plan your trip.',
    'Kept it for you. It\u2019s waiting under Plan your trip. \uD83C\uDF34',
    'Saved! Dios mabalos for planning with me. It\u2019s under Plan your trip.'
  ];
  var SAVE_FAIL_LEADS = [
    'I could not save this one \u2014 ',
    'This one did not save \u2014 ',
    'Saving did not work this time \u2014 '
  ];
  var SAVE_NET_LINES = [
    'I could not reach the server to save that. Your plan is above, so nothing is lost.',
    'The save did not go through, probably the connection. Your plan is above, so nothing is lost.',
    'I lost the connection while saving. Your plan is above, so nothing is lost.'
  ];

  var OPENERS = [
    ['I\u2019d love to map that out for you! \uD83E\uDDF3',          'When does your trip start?'],
    ['Madya, let\u2019s build you a trip! \uD83C\uDF34',             'What day are you starting?'],
    ['Happy to plan this with you.',                               'Which date does the trip begin?'],
    ['Let\u2019s put a trip together, one step at a time. \u2728', 'First, what date do you start?'],
    ['Sounds like a plan! \uD83D\uDDFA\uFE0F',                      'What is the first day of your trip?'],
    ['Let\u2019s get you out exploring. \uD83C\uDF0A',              'When would you like to start?'],
    ['I\u2019m in. Let\u2019s do it properly, day by day.',        'What date are you heading out?'],
    ['Dios mabalos for asking! I\u2019ll help you plan it. \uD83E\uDD65', 'When does your trip begin?'],
    ['A trip is easier to plan once the date is set.',            'What day will you start?'],
    ['Let\u2019s make it a good one! \u2600\uFE0F',                  'Which day does your trip start?']
  ];
  var OPENER_KEY = 'budLastOpener';

  /* Random, but never the one used last time. The last pick is kept in
     localStorage so it holds across visits too; if storage is blocked
     it simply picks at random. */
  function pickOpener() {
    var last = -1;
    try { last = parseInt(localStorage.getItem(OPENER_KEY), 10); } catch (e) {}

    var i = Math.floor(Math.random() * OPENERS.length);
    if (i === last) i = (i + 1) % OPENERS.length;

    try { localStorage.setItem(OPENER_KEY, String(i)); } catch (e) {}
    return OPENERS[i][0] + '\n\n' + OPENERS[i][1];
  }

  /* Tapped the "Plan my trip" chip. Bud's opening is written by the
     model, the same as every other turn, so it is not a recording and it
     can already be reacting to the page the visitor is on. The canned
     openers above are only the fallback: if the model cannot be reached
     (or this is the design preview) the chip still opens a conversation
     instead of landing on an error. */
  function startPlanning() {
    setPlanning(true);

    if (previewMode) {
      addMessage(pickOpener(), 'bot');
      input.focus();
      return;
    }
    planTurn('Can you make me an itinerary?', true);
  }

  /* The trip on screen, cut down to what the chat needs to talk about it:
     the dates, the group, the budget and each day's stops. */
  function compactPlan(plan) {
    if (!plan) return null;
    return {
      name: plan.name, startDate: plan.startDate, endDate: plan.endDate,
      nights: plan.nights, travelers: plan.travelers, brief: plan.brief,
      days: (plan.days || []).map(function (d) {
        return { date: d.date, items: (d.items || []).map(function (it) {
          return { time: it.time, name: it.name, town: it.town };
        }) };
      })
    };
  }

  /* The chat model decided this message is a request to build or change a
     trip ("include it on my itinerary", "make it cheaper"). Hand it to the
     planner WITH the conversation so far, so "it" means the place that was
     just discussed, and with the trip on screen if there is one, so a
     change is applied to that trip instead of starting an interview over. */
  function handOffToPlanner(text) {
    planHistory = history.slice(-6).map(function (t) {
      return { role: t.role, content: t.content };
    });
    setPlanning(true);
    planKnown   = currentPlan ? currentPlan.brief : null;
    planRebuild = !!currentPlan;
    planTurn(text);
  }

  /* One turn of the planning chat. */
  function planTurn(text, fromChip) {
    if (previewMode) {
      setPlanning(false);
      addMessage('I need to be connected to the assistant before I can plan a trip. '
               + 'This is a design preview.', 'bot');
      return;
    }

    busy = true;
    send.disabled = true;
    clearQuickReplies();

    planHistory.push({ role: 'user', content: text });
    var typing = addTyping();
    var wasRebuild = planRebuild && !!currentPlan;   // changing the trip on screen

    postJSON(endpoint, {
      mode: 'plan_chat',
      message: text,
      history: planHistory.slice(0, -1),  // everything before this turn
      known: planKnown,                   // what has already been worked out
      rebuild: planRebuild,               // changing a trip that already exists
      plan: wasRebuild ? compactPlan(currentPlan) : null   // so Bud can see which day is which
    }, 100000)
    .then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (d) {
        if (!res.ok || !d.reply) {
          var err = new Error('plan_chat HTTP ' + res.status);
          if (d && d.error) err.budMessage = d.error;
          throw err;
        }
        return d;
      });
    })
    .then(function (d) {
      typing.remove();
      addMessage(d.reply, 'bot');
      if (d.known) { planKnown = d.known; }

      planHistory.push({ role: 'assistant', content: d.reply });
      if (planHistory.length > PLAN_HISTORY_MAX) {
        planHistory = planHistory.slice(-PLAN_HISTORY_MAX);
      }

      releaseInput();

      if (d.cancel) {
        setPlanning(false);
      } else if (d.ready && d.brief && wasRebuild && currentPlan && swapFromPlanner(text, d.brief)) {
        /* The model got a swap sentence. Do it as a direct one-for-one swap
           anyway: the model tends to add the new place without removing the old. */
        setPlanning(false);
        var sw = swapFromPlanner(text, d.brief);
        var b2 = {}, src2 = currentPlan.brief || {};
        Object.keys(src2).forEach(function (k) { b2[k] = src2[k]; });
        b2.places = (b2.places || []).filter(function (n) { return String(n).toLowerCase() !== sw.from.toLowerCase(); });
        b2.places.push(sw.to);
        planRebuild = true;
        generatePlan(b2, {
          scope: 'days', days: [], removeDays: [], removePlaces: [sw.from],
          swap: { from: sw.from, to: sw.to, day: sw.day },
          note: 'swap ' + sw.from + ' for ' + sw.to
        });
      } else if (d.ready && d.brief) {
        setPlanning(false);
        /* For an edit, say which days the change touches so every other
           day is kept exactly as it is. */
        generatePlan(d.brief, wasRebuild ? (d.edit || { scope: '' }) : null);
      }
      saveSession();
    })
    .catch(function (err) {
      typing.remove();

      /* Drop the failed turn so a retry does not send it twice. */
      planHistory.pop();

      if (fromChip && !(err && err.budMessage)) {
        /* The chip should never land on an error: open with a canned
           line, and remember both sides so the visitor's answer to it
           is read in context. */
        var fallback = pickOpener();
        addMessage(fallback, 'bot');
        planHistory.push({ role: 'user', content: text }, { role: 'assistant', content: fallback });
      } else {
        addMessage((err && err.budMessage) || pickLine('net', NET_LINES), 'bot');
      }
      console.error('Bud.Ai planning chat failed:', err);
      releaseInput();
    });
  }

  /* ==================================================================
     SESSION MEMORY, NEW CHAT, TIMEOUTS, AND READING AN EDIT

     A real chat does not forget when the page changes. The conversation,
     the trip on screen and what the planner has worked out are kept in
     sessionStorage (this tab only, gone when it closes) and put back when
     the next page loads, so "make day 2 cheaper" still works after the
     visitor clicked through to another page.
     ================================================================== */

  /* fetch with a time limit, so a stalled connection ends in an error
     message and a Try again button instead of dots that never stop. */
  function postJSON(url, body, ms) {
    var ctl   = ('AbortController' in window) ? new AbortController() : null;
    var timer = ctl ? setTimeout(function () { ctl.abort(); }, ms || 90000) : null;
    var opts  = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    };
    if (ctl) opts.signal = ctl.signal;
    return fetch(url, opts).then(
      function (res) { if (timer) clearTimeout(timer); return res; },
      function (err) { if (timer) clearTimeout(timer); throw err; }
    );
  }

  function record(entry) {
    transcript.push(entry);
    if (transcript.length > SESSION_MAX_ENTRIES) {
      transcript = transcript.slice(-SESSION_MAX_ENTRIES);
    }
    saveSession();
  }

  function buildSession() {
    /* Only the two newest trip cards are kept in full; older ones would
       eat the storage quota for no benefit. */
    var plans = 0, out = [];
    for (var i = transcript.length - 1; i >= 0; i--) {
      var e = transcript[i];
      if (e.k === 'p' && ++plans > 2) {
        out.unshift({ k: 'm', w: 'bot', x: '(An earlier version of the trip was shown here.)' });
      } else {
        out.unshift(e);
      }
    }
    return {
      v: 1, t: out,
      history: history, planHistory: planHistory, planKnown: planKnown,
      planning: planning, planRebuild: planRebuild,
      currentPlan: currentPlan, lastSavedId: lastSavedId, lastOffer: lastOffer
    };
  }

  function saveSession(now) {
    if (restoring || !identityReady) return;
    var data;
    try {
      data = buildSession();
      sessionStorage.setItem(SESSION_KEY, JSON.stringify(data));
    } catch (e) { /* storage full or blocked: the chat still works */ }

    /* Signed in: also keep it on the account. Debounced so a burst of
       updates is one request; on page unload it is sent at once. */
    if (USER_ID > 0 && data && !previewMode) {
      clearTimeout(serverTimer);
      var send = function () {
        var body = JSON.stringify({ mode: 'session', session: data });
        if (now && navigator.sendBeacon) {
          try { navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' })); return; } catch (e) {}
        }
        postJSON(endpoint, { mode: 'session', session: data }, 20000).catch(function () {});
      };
      if (now) send(); else serverTimer = setTimeout(send, 800);
    }
  }

  function applySession(s) {
    if (!s || s.v !== 1 || !Array.isArray(s.t)) return;

    restoring = true;
    try {
      var t = s.t.slice(), pending = '';

      /* The page changed while a question was in flight. Put the question
         back in the box rather than leave it sitting with no answer. */
      if (t.length && t[t.length - 1].k === 'm' && t[t.length - 1].w === 'user') {
        pending = String(t.pop().x || '');
      }

      t.forEach(function (e) {
        if (e.k === 'm' && typeof e.x === 'string') {
          addMessage(e.x, e.w === 'user' ? 'user' : 'bot', true);
        } else if (e.k === 'p' && e.plan && Array.isArray(e.plan.days)) {
          renderPlan(e.plan, e.changed, true);
        }
      });
      transcript = t;

      function dropTrailingUser(a) {
        a = Array.isArray(a) ? a.slice() : [];
        if (a.length && a[a.length - 1].role === 'user') a.pop();
        return a;
      }
      history      = dropTrailingUser(s.history);
      planHistory  = dropTrailingUser(s.planHistory);
      planKnown    = s.planKnown && typeof s.planKnown === 'object' ? s.planKnown : null;
      planRebuild  = !!s.planRebuild;
      currentPlan  = s.currentPlan && Array.isArray(s.currentPlan.days) ? s.currentPlan : null;
      lastSavedId  = s.lastSavedId || null;
      lastOffer    = !!s.lastOffer;
      if (s.planning) input.setAttribute('placeholder', 'Tell Bud about your trip\u2026');
      planning = !!s.planning;

      if (pending) input.value = pending;
    } finally {
      restoring = false;
    }
    saveSession();
  }

  /* Back to a blank conversation. The static opening (if the page has
     one) stays; everything added since goes. */
  function resetChat() {
    if (busy) return;
    clearQuickReplies();
    hideSuggestions();
    Array.prototype.slice.call(log.children).forEach(function (n) {
      if (initialNodes.indexOf(n) === -1) n.remove();
    });
    transcript = []; history = []; planHistory = []; planKnown = null;
    planRebuild = false; currentPlan = null; lastSavedId = null;
    lastOffer = false; lastPlanRow = null;
    setPlanning(false);
    try { sessionStorage.removeItem(SESSION_KEY); } catch (e) {}
    clearTimeout(serverTimer);
    if (USER_ID > 0) postJSON(endpoint, { mode: 'session', clear: true }, 15000).catch(function () {});
    try { document.dispatchEvent(new CustomEvent('bud:plan-clear')); } catch (e) {}
    if (!log.children.length) {
      addMessage('Fresh start. What would you like to know about Camarines Norte?', 'bot');
    }
    input.value = '';
    scrollLog();
  }

  /* A small "new chat" button beside the close button in the panel
     header. If the header markup is not what is expected nothing is
     added, and typing /new does the same job. */
  (function addNewChatButton() {
    var closeBtn = panel.querySelector('button[data-bud-close]');
    if (!closeBtn || !closeBtn.parentNode || panel.querySelector('.bud__new')) return;
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'bud__new';
    b.title = 'New chat';
    b.setAttribute('aria-label', 'Start a new chat');
    b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
                  'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                  '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>';
    b.addEventListener('click', resetChat);
    closeBtn.parentNode.insertBefore(b, closeBtn);
  }());

  /* ---------- editing a trip that is already on screen ---------- */

  /* The trip as the server needs it back: only what identifies each stop.
     Names, towns and coordinates are looked up again there. */
  function previousPayload(plan) {
    return {
      name: plan.name, summary: plan.summary,
      startDate: plan.startDate, endDate: plan.endDate,
      nights: plan.nights, brief: plan.brief,
      days: (plan.days || []).map(function (d) {
        return { date: d.date, items: (d.items || []).map(function (it) {
          return { destId: it.destId, time: it.time, end: it.end, note: it.note };
        }) };
      })
    };
  }

  /* [2] -> "Day 2", [1,3] -> "Days 1 and 3", [1,2,4] -> "Days 1, 2 and 4" */
  function dayWord(list) {
    return (list.length === 1 ? 'Day ' : 'Days ') +
           list.join(', ').replace(/, (\d+)$/, ' and $1');
  }

  /* What an edit actually did, in a sentence or two. The server reports
     which days it rewrote and which it left alone. */
  function editSummary(info) {
    var out = [], c = info.changed || [], k = info.kept || [], f = info.failed || [];

    if (info.scope === 'all') {
      out.push('I reworked the whole trip to fit.');
    } else if (c.length) {
      out.push('Updated ' + dayWord(c) + (info.scope === 'days' && k.length ? '.' : ' to fit.'));
    } else if (info.scope === 'dates') {
      out.push('Done. The stops are the same; only the dates or times moved.');
    } else {
      out.push('Done.');
    }
    if (f.length) {
      out.push('I could not change ' + dayWord(f) + ', so ' +
               (f.length === 1 ? 'it is' : 'they are') + ' as before.');
    }
    if (info.scope === 'days' && k.length) {
      out.push(dayWord(k) + (k.length === 1 ? ' is' : ' are') + ' exactly as you had ' +
               (k.length === 1 ? 'it.' : 'them.'));
    }
    return out.join(' ');
  }

  /* The old card stays in the chat but is dimmed and labelled, so the
     visitor can tell which one is current. */
  function retirePlanRow(row) {
    if (!row || row.classList.contains('bud-old')) return;
    row.classList.add('bud-old');
    var msg = row.querySelector('.bud__msg');
    if (msg) {
      var n = document.createElement('p');
      n.className = 'bud__old-note';
      n.textContent = 'Earlier version';
      msg.insertBefore(n, msg.firstChild);
    }
  }

  /* save-itinerary.php may answer with the id it stored the trip under;
     remembering it lets the next edit say which saved trip it replaces. */
  function savedIdOf(d) {
    if (!d || typeof d !== 'object') return null;
    return d.id || d.planId || d.itineraryId || d.tripId || null;
  }

  /* ---------- generate, then save ---------- */

  function generatePlan(brief, edit) {
    if (previewMode) {
      addMessage('I need to be connected to the assistant before I can build a plan. '
               + 'This is a design preview.', 'bot');
      return;
    }

    /* An edit sends the trip on screen back, plus what the change touches.
       The server keeps every day that is not touched and writes only the
       rest. Without that, any change reshuffled the whole trip. */
    var previous = (edit && currentPlan) ? previousPayload(currentPlan) : null;
    var body = { mode: 'itinerary', brief: brief };
    if (previous) { body.previous = previous; body.edit = edit; }

    busy = true;
    send.disabled = true;
    var typing = addTyping(previous ? 'Updating your itinerary\u2026' : 'Building your itinerary\u2026');

    postJSON(endpoint, body, 100000)
    .then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (d) {
        if (!res.ok || !d.plan) {
          var err = new Error('itinerary HTTP ' + res.status);
          if (d && d.error) err.budMessage = d.error;
          throw err;
        }
        return d;
      });
    })
    .then(function (d) {
      var plan = d.plan;
      var info = previous ? d.edit : null;
      typing.remove();
      currentPlan = plan;
      if (info) addMessage(editSummary(info), 'bot');
      renderPlan(plan, info ? info.changed : null);
      savePlan(plan, !!info);   // a trip the server treated as brand new replaces nothing
      saveSession();
    })
    .catch(function (err) {
      typing.remove();
      addMessage((err && err.budMessage) || pickLine('planfail', PLAN_FAIL_LINES), 'bot');
      console.error('Bud.Ai itinerary failed:', err);
      reopenPlanning(brief, !!previous);
    })
    .then(function () {
      busy = false;
      send.disabled = false;
    });
  }

  /* "2026-11-13" -> a local Date. Built from the parts, never from
     new Date('2026-11-13'), which is read as UTC midnight and shows
     the previous day anywhere behind UTC. */
  function parseYmd(v) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
    return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
  }

  function fmtDate(v, withWeekday, withYear) {
    var d = parseYmd(v);
    if (!d) return '';
    var o = { day: 'numeric', month: 'short' };
    if (withWeekday) o.weekday = 'short';
    if (withYear) o.year = 'numeric';
    return d.toLocaleDateString('en-PH', o);
  }

  /* "09:00" <-> minutes, for working out how long a stop lasts. */
  function toMin(t) {
    var m = /^(\d{1,2}):(\d{2})$/.exec(t || '');
    return m ? (+m[1]) * 60 + (+m[2]) : null;
  }
  function fromMin(n) {
    return ('0' + Math.floor(n / 60)).slice(-2) + ':' + ('0' + (n % 60)).slice(-2);
  }

  /* When the visit ends. Uses the end the planner gave. If there is none
     (an older plan, or a stop added by code), it is worked out: up to
     half an hour before the next stop, or three hours for the last one
     of the day, never later than 20:00. */
  function visitEnd(it, next, cap) {
    var start = toMin(it.time);
    if (start === null) return '';
    var given = toMin(it.end);
    if (given !== null && given > start) return fromMin(given);

    var nxt = next ? toMin(next.time) : null;
    var end;
    if (nxt !== null && nxt > start) {
      end = nxt - start >= 90 ? nxt - 30 : nxt;
    } else {
      /* Never run past the visitor's own end of day when they gave one. */
      end = Math.min(start + 180, (cap !== null && cap !== undefined && cap > start) ? cap : 20 * 60);
    }
    return end > start ? fromMin(end) : '';
  }

  /* ---------- swap one stop directly ----------
     A "Swap" button beside each stop. Pick another destination and the
     trip is rebuilt with only that day touched. It does not go through
     the chat model, so it cannot misread which day or place is meant. */
  function swapStop(row, dayNo, oldName, newName, said) {
    if (busy || !currentPlan || (row && row.classList.contains('bud-old'))) return;
    var b = {};
    var src = currentPlan.brief || {};
    Object.keys(src).forEach(function (k) { b[k] = src[k]; });
    var places = (b.places || []).filter(function (n) {
      return String(n).toLowerCase() !== oldName.toLowerCase();
    });
    places.push(newName);
    b.places = places;
    addMessage(said || ('Swap ' + oldName + ' for ' + newName), 'user');
    planRebuild = true;
    generatePlan(b, {
      scope: 'days', days: [], removeDays: [], removePlaces: [oldName],
      swap: { from: oldName, to: newName, day: dayNo },
      note: 'swap ' + oldName + ' for ' + newName
    });
  }

  /* ---------- swap by typing ----------
     "replace Bagasbas with the Capalonga shrine" does the same direct swap
     as the button: same time slot, nothing else moves. Only taken when
     BOTH places are recognised unambiguously and the message is nothing
     but the swap; anything else goes to the chat as before. */
  var SW_STOP = { the: 1, of: 1, a: 1, an: 1, at: 1, in: 1 };
  function swTokens(x) {
    return String(x || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim().split(' ')
      .filter(function (w) { return w && !SW_STOP[w]; });
  }
  function swHas(hay, w) {
    for (var i = 0; i < hay.length; i++) {
      if (hay[i] === w) return true;
      if (w.length >= 4 && hay[i].length >= 4 && (hay[i].indexOf(w) === 0 || w.indexOf(hay[i]) === 0)) return true;
    }
    return false;
  }
  function swResolve(query, list) {
    var q = swTokens(query);
    if (!q.length) return null;
    var qKey = q.join(' ');
    var best = [], bestExtra = 1e9;
    list.forEach(function (c) {
      var nameT = swTokens(c.n), hay = nameT.concat(swTokens(c.t));
      var ok = q.every(function (w) { return swHas(hay, w); });
      if (!ok) return;
      /* An exact name always wins over a longer name that merely contains it. */
      var extra = (nameT.join(' ') === qKey) ? -1 : nameT.length - q.length;
      if (extra < bestExtra) { bestExtra = extra; best = [c]; }
      else if (extra === bestExtra) best.push(c);
    });
    /* Same place listed twice (same name) is not ambiguous. */
    if (best.length > 1) {
      var first = swTokens(best[0].n).join(' ');
      if (best.every(function (c) { return swTokens(c.n).join(' ') === first; })) best = [best[0]];
    }
    return best.length === 1 ? best[0] : null;
  }

  var SWAP_RE = /^\s*(?:(?:can|could|would|will)\s+you\s+|please\s+|pls\s+)*(?:swap|replace|change|switch|substitute)\s+(?:out\s+)?(?:the\s+)?(.+?)\s+(?:with|to|for|into|by)\s+(?:the\s+)?(.+?)[\s.!?]*$/i;

  function parseTypedSwap(text) {
    if (!currentPlan || !currentPlan.days || !budPlaces.length || previewMode) return null;
    if (/[,;]|\b(?:then|also|and\s+(?:then|i|start|make))\b/i.test(text)) return null;
    var m = SWAP_RE.exec(text);
    if (!m) return null;

    var inPlan = [], used = {};
    currentPlan.days.forEach(function (d, di) {
      (d.items || []).forEach(function (it) {
        if (!it.name) return;
        used[it.name.toLowerCase()] = true;
        inPlan.push({ n: it.name, t: it.town || '', day: di + 1 });
      });
    });
    var from = swResolve(m[1], inPlan);
    var to = swResolve(m[2], budPlaces.filter(function (p) { return p.n && !used[p.n.toLowerCase()]; }));
    if (!(from && to)) {
      try { console.debug('Bud typed swap not taken:', { from: m[1], to: m[2], fromFound: from && from.n, toFound: to && to.n }); } catch (e) {}
    }
    return (from && to) ? { from: from.n, to: to.n, day: from.day } : null;
  }

  /* Fallback for a swap sentence that reached the planner: find the old stop
     on the trip, and take the new one from what the model asked for
     (brief.places) or from the destination list. */
  function swapFromPlanner(text, brief) {
    if (!currentPlan || !currentPlan.days) return null;
    var m = SWAP_RE.exec(text);
    if (!m || /[,;]/.test(text)) return null;
    var inPlan = [], inPlanNames = {};
    currentPlan.days.forEach(function (d, di) {
      (d.items || []).forEach(function (it) {
        if (!it.name) return;
        inPlanNames[it.name.toLowerCase()] = true;
        inPlan.push({ n: it.name, t: it.town || '', day: di + 1 });
      });
    });
    var from = swResolve(m[1], inPlan);
    if (!from) return null;
    var cand = budPlaces.filter(function (p) { return p.n && !inPlanNames[p.n.toLowerCase()]; });
    var to = swResolve(m[2], cand);
    if (!to && brief && brief.places) {
      var fresh = brief.places.filter(function (n) { return !inPlanNames[String(n).toLowerCase()]; });
      var hit = swResolve(m[2], fresh.map(function (n) { return { n: String(n), t: '' }; }));
      if (hit) to = budPlaces.filter(function (p) { return p.n && p.n.toLowerCase() === hit.n.toLowerCase(); })[0] || null;
    }
    return to ? { from: from.n, to: to.n, day: from.day } : null;
  }

  function swapControl(plan, dayNo, oldName) {
    var wrap = document.createElement('span');
    wrap.className = 'bud__swap';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'bud__swap-btn';
    btn.textContent = 'Swap';
    btn.title = 'Replace ' + oldName + ' with another destination';
    btn.addEventListener('click', function () {
      var open = wrap.querySelector('select');
      if (open) { open.remove(); return; }

      var used = {};
      plan.days.forEach(function (d) {
        (d.items || []).forEach(function (x) { used[String(x.name || '').toLowerCase()] = true; });
      });

      var sel = document.createElement('select');
      sel.className = 'bud__swap-sel';
      sel.setAttribute('aria-label', 'Swap ' + oldName + ' for');
      var ph = document.createElement('option');
      ph.value = ''; ph.textContent = 'Swap for\u2026';
      sel.appendChild(ph);
      budPlaces.slice().sort(function (a, c) { return a.n.localeCompare(c.n); }).forEach(function (pl) {
        if (!pl.n || used[pl.n.toLowerCase()]) return;
        var o = document.createElement('option');
        o.value = pl.n;
        o.textContent = pl.n + (pl.t ? ' \u00B7 ' + pl.t : '');
        sel.appendChild(o);
      });
      sel.addEventListener('change', function () {
        if (!sel.value) return;
        var row = wrap.closest ? wrap.closest('.bud__row') : null;
        var chosen = sel.value;
        sel.remove();
        swapStop(row, dayNo, oldName, chosen);
      });
      wrap.appendChild(sel);
      sel.focus();
    });
    wrap.appendChild(btn);
    return wrap;
  }

  function renderPlan(plan, changed, still) {
    /* A newer trip replaces the one above it. */
    if (lastPlanRow) retirePlanRow(lastPlanRow);

    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot bud__msg--wide';

    var h = document.createElement('div');
    h.className = 'bud__plan-name';
    h.textContent = plan.name;
    wrap.appendChild(h);

    /* The trip at a glance: dates, length, party size. */
    var facts = [];
    if (plan.startDate && plan.endDate) {
      facts.push(fmtDate(plan.startDate, true, false) + ' \u2192 ' + fmtDate(plan.endDate, true, true));
    }
    var nDays = plan.days.length;
    facts.push(nDays + (nDays === 1 ? ' day' : ' days')
             + (plan.nights ? ', ' + plan.nights + (plan.nights === 1 ? ' night' : ' nights') : ''));
    if (plan.travelers) {
      facts.push(plan.travelers + (plan.travelers === 1 ? ' traveller' : ' travellers'));
    }
    var meta = document.createElement('p');
    meta.className = 'bud__plan-note';
    meta.textContent = facts.join(' \u00B7 ');
    wrap.appendChild(meta);

    if (plan.summary) {
      var p = document.createElement('p');
      p.textContent = plan.summary;
      wrap.appendChild(p);
    }

    var dayCap = (plan.brief && plan.brief.dayEnd) ? toMin(plan.brief.dayEnd) : null;

    plan.days.forEach(function (day, i) {
      var dh = document.createElement('div');
      dh.className = 'bud__h';
      dh.textContent = 'Day ' + (i + 1)
        + (day.date ? ' \u00B7 ' + fmtDate(day.date, true, false) : '');
      if (changed && changed.indexOf(i + 1) !== -1) {
        var tg = document.createElement('span');
        tg.className = 'bud__tag';
        tg.textContent = 'updated';
        dh.appendChild(tg);
      }
      wrap.appendChild(dh);

      var ul = document.createElement('ul');
      ul.className = 'bud__plan-day';

      day.items.forEach(function (it, ii) {
        var li = document.createElement('li');
        li.dataset.budStop = it.name || '';   // lets the map focus this stop on a tap

        if (it.time) {
          var t = document.createElement('span');
          t.className = 'bud__plan-time';
          var endAt = visitEnd(it, day.items[ii + 1], dayCap);
          t.textContent = endAt ? it.time + ' \u2013 ' + endAt : it.time;
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
        if (budPlaces.length && !previewMode && it.name) {
          body.appendChild(swapControl(plan, i + 1, it.name));
        }
        ul.appendChild(li);
      });

      wrap.appendChild(ul);
    });

    var row = mountBot(wrap);
    lastPlanRow = row;
    if (!restoring) record({ k: 'p', plan: plan, changed: changed || null });
    scrollLog();

    /* A trip drawn again after a page change does not re-send itself to the map. */
    if (still) return row;

    /* Hand the map EVERY stop of the plan, with its coordinates. The
       map used to find places by spotting their names in the reply
       text, which caught one stop and missed the rest. Sent after the
       current tick so it lands after the map's own reply handling. */
    var stops = [];
    plan.days.forEach(function (day, di) {
      (day.items || []).forEach(function (it) {
        stops.push({ n: it.name || '', t: it.town || '', g: it.tag || '',
                     la: it.lat, lo: it.lng, day: di + 1 });
      });
    });
    setTimeout(function () {
      try {
        document.dispatchEvent(new CustomEvent('bud:plan', { detail: { stops: stops } }));
      } catch (e) { /* the map is a bonus; never break the chat over it */ }
    }, 0);
    return row;
  }

  /* Saving is separate from generating on purpose. A visitor who is
     not signed in still gets to SEE their plan; only keeping it needs
     an account. Losing the plan at the sign-in prompt would be the
     rudest possible moment to ask. */
  function savePlan(plan, isEdit) {
    /* An edit says which saved trip it replaces, when the server told us,
       so changing a trip does not leave the old version saved beside it. */
    var payload = {};
    Object.keys(plan).forEach(function (k) { payload[k] = plan[k]; });
    if (isEdit && lastSavedId) payload.replaceId = lastSavedId;
    lastSavedId = null;

    postJSON('save-itinerary.php', payload, 30000)
    .then(function (res) {
      return res.json().catch(function () { return {}; })
        .then(function (d) { return { status: res.status, data: d }; });
    })
    .then(function (r) {
      if (r.status === 401) {
        addSaveNotice(pickLine('signin', SIGN_IN_LINES), true);
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

        addSaveNotice(pickLine('savefail', SAVE_FAIL_LEADS) + why
                    + ' Your plan is above, so nothing is lost.', false);
        console.error('Bud.Ai save failed:', r.status, r.data);
        return;
      }

      lastSavedId = savedIdOf(r.data);
      saveSession();
      addSaveNotice(pickLine('saved', SAVED_LINES), false, 'plan-trip.php');
    })
    .catch(function (e) {
      addSaveNotice(pickLine('savenet', SAVE_NET_LINES), false);
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

  /* ---------- quick replies ----------
     A row of tappable follow-ups under Bud's last message. The visitor
     taps instead of typing, and a tap goes through the same submit()
     path as typed text, so there is one place the intent is read. They
     disappear as soon as anything is sent. */
  var qrEl = null;

  function clearQuickReplies() {
    if (qrEl) { qrEl.remove(); qrEl = null; }
  }

  function showQuickReplies(items) {
    clearQuickReplies();
    if (!items || !items.length) return;

    var row = document.createElement('div');
    row.className = 'bud__qr';
    row.setAttribute('role', 'group');
    row.setAttribute('aria-label', 'Suggested replies');

    items.slice(0, 4).forEach(function (it) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bud__chip';
      b.textContent = it.label;
      b.addEventListener('click', function () {
        if (busy) return;
        clearQuickReplies();
        if (it.run) { it.run(); return; }
        input.value = it.say;
        submit();
      });
      row.appendChild(b);
    });

    qrEl = row;
    log.appendChild(row);
    scrollLog();
  }

  /* ---------- reading what the visitor means ----------
     Most of this used to be left to the model alone, which meant a
     plain request like "make it cheaper" was answered with advice, and
     a bare "yes" to "want me to plan it?" was answered with "yes to
     what?". Obvious cases are now recognised here, before any request
     is made; everything else still goes to the model. */
  function norm(t) { return String(t).toLowerCase().replace(/\s+/g, ' ').trim(); }

  /* Takes off the politeness so the verb is what is left:
     "can you please add Malatap to my trip?" -> "add malatap to my trip" */
  function stripPolite(t) {
    return t.replace(/^(?:hey|hi|hello|uy|hoy)?[\s,]*(?:bud(?:\.ai)?)?[\s,]*/, '')
            .replace(/^(?:can|could|would|will)\s+(?:you|u)\s+(?:please\s+|pls\s+)?/, '')
            .replace(/^(?:please|pls|paki)\s+/, '')
            .replace(/[?!.\s]+$/, '')
            .trim();
  }

  function questionLike(t) {
    return /^(?:what|how|where|when|which|why|who|is|are|does|do|did|should|ano|paano|saan|kailan|magkano)\b/.test(t);
  }

  var REQ_VERB = /\b(?:make|create|build|generate|plan|prepare|put together|set up|draft|give|suggest|want|need|wanna|looking for|gusto|gawa|gumawa)\b/;
  var PLAN_NOUN = /\b(?:itin\w*|iten\w*|itern\w*|trip|plan|schedule)\b/;

  /* Someone typing the request rather than tapping the chip. Loose on
     the NOUN on purpose: people type "itineraryr", "itinerery" and
     "iternary", and a typo must not drop them into ordinary chat. But
     a question ("how do I plan a trip?") and advice ("tips to make the
     trip easier") are not requests to build one. */
  function looksLikeItineraryRequest(text) {
    var t = stripPolite(norm(text));
    if (questionLike(t)) return false;
    if (/\b(?:tips?|advice|pack|bring|wear)\b/.test(t) && !/\b(?:itin\w*|iten\w*|itern\w*)\b/.test(t)) return false;
    return REQ_VERB.test(t) && PLAN_NOUN.test(t);
  }

  var EDIT_RES = [
    /\b(?:make|keep|get)\s+(?:it|this|that)\b.*\b(?:cheap\w*|short\w*|long\w*|slow\w*|relax\w*|chill\w*|pack\w*|fit|afford\w*|budget|\d+\s*(?:days?|nights?|pax|people))/,
    /\b(?:add|include|put|isama|idagdag|dagdag\w*)\b.*\b(?:day|night|stop)\b/,
    /\b(?:swap|replace|palitan|remove|delete|drop|alisin|tanggalin|take out|cancel)\b.*\b(?:day|stop|place|activity|falls|beach|island|church|park|monument|first|second|third|last|morning|afternoon)\b/,
    /\b(?:one more|another|an extra|extra|extend)\b.*\b(?:day|night)s?\b/,
    /\b(?:start|begin|move|push)\b.*\b(?:later|earlier|a day|next)\b/,
    /\bmake it fit\b|\b(?:fit|within|under)\b.*\bbudget\b/,
    /* a day named by number: "change day 2", "day 3 is too packed", "skip day 1" */
    /\b(?:change|edit|update|replace|swap|redo|rework|remove|delete|drop|skip|palitan|alisin|tanggalin)\b.*\bday\s*\d/,
    /\bday\s*\d.*\b(?:change|replace|swap|instead|different|remove|skip|too\s+\w+|palitan|alisin|tanggalin)\b/
  ];

  /* A request to build or change the trip, as opposed to asking about it. */
  function looksLikePlanEdit(text) {
    var t = stripPolite(norm(text));
    if (questionLike(t)) return false;

    /* "include it on my itinerary", "add Malatap Falls to my trip" - works
       with or without a trip already on screen. */
    if (/\b(?:include|add|put|isama|idagdag|dagdag\w*)\b.*\b(?:itinerary|itin\w*|plan|trip|schedule)\b/.test(t)) return true;

    if (!currentPlan) return false;
    return EDIT_RES.some(function (re) { return re.test(t); });
  }

  /* The visitor is pointing at the trip they already have: "edit my previous
     itinerary", "change the plan", or describing a change to "my itinerary"
     (hours, stops per day). With a trip on screen this is an edit, never a
     fresh interview. A request that says new/another/from scratch is not. */
  var NEW_PLAN_RE = /\b(?:new|another|different|fresh|from scratch|start over|brand new)\b/;
  var EXISTING_RE = /\b(?:edit|change|update|modify|revise|adjust|redo|fix|rework|tweak)\b.*\b(?:itin\w*|iten\w*|plan|trip)\b|\b(?:previous|last|existing|current|earlier|same)\s+(?:itin\w*|iten\w*|plan|trip)\b|\b(?:itin\w*|iten\w*)\b.*\b(?:range|hours?|per day|a day|each day|daily|starts? from)\b/;

  function refersToExistingPlan(text) {
    if (!currentPlan) return false;
    var t = stripPolite(norm(text));
    if (NEW_PLAN_RE.test(t)) return false;
    return EXISTING_RE.test(t) || looksLikePlanEdit(text);
  }

  /* Switch a planning chat that is already under way over to editing the
     trip on screen. The trip's own details are the truth; only the hours
     and stops-per-day worked out in this chat are carried across. */
  function switchToRebuild() {
    var src = currentPlan.brief || {}, base = {};
    Object.keys(src).forEach(function (k) { base[k] = src[k]; });
    ['dayStart', 'dayEnd', 'perDay'].forEach(function (k) {
      if (planKnown && planKnown[k]) base[k] = planKnown[k];
    });
    planKnown   = base;
    planRebuild = true;
  }

  /* A bare "yes" only means something if Bud just offered to plan. */
  var lastOffer = false;
  var YES_RE = /^(?:yes|yeah|yep|yup|ya|oo|opo|sige|ok|okay|sure|go|please|pls|sounds good|do it|go ahead|let'?s go|let'?s do it)(?:\s+(?:please|pls|po|na|sige|do it|go ahead|thanks|thank you))?[\s!.]*$/;
  function offeredPlan(reply) {
    var r = String(reply).trim();
    return /\?\s*$/.test(r) &&
      /\b(?:want|like|shall|should)\b[^.?!]{0,50}\b(?:build|plan|put together|map out|make|create|set up|add)\b/i.test(r);
  }

  function submit() {
    var text = input.value.trim();
    if (!text || busy) return;

    /* "/new", "/clear", "new chat": start over with a blank conversation. */
    if (/^(?:\/(?:new|clear|reset)|(?:new|clear|reset)\s+chat)$/i.test(text)) {
      resetChat();
      return;
    }

    var typedSwap = parseTypedSwap(text);
    if (typedSwap) {
      input.value = '';
      hideSuggestions();
      clearQuickReplies();
      swapStop(lastPlanRow, typedSwap.day, typedSwap.from, typedSwap.to, text);
      return;
    }

    hideSuggestions();
    clearQuickReplies();

    /* Mid-planning: every message is an answer to Bud's questions. */
    if (planning) {
      addMessage(text, 'user');
      input.value = '';
      if (currentPlan && !planRebuild && refersToExistingPlan(text)) switchToRebuild();
      planTurn(text);
      return;
    }

    var t = norm(text);

    /* A trip is already on screen and they are talking about it. */
    if (refersToExistingPlan(text)) {
      addMessage(text, 'user');
      input.value = '';
      lastOffer = false;
      handOffToPlanner(text);
      return;
    }

    /* Typed the request instead of tapping the chip. Send what they
       wrote as the first turn - "plan a 3 night trip for 2, beaches"
       already answers most of it, and Bud will only ask for the rest. */
    if (looksLikeItineraryRequest(text)) {
      addMessage(text, 'user');
      input.value = '';
      planHistory = [];
      lastOffer = false;
      setPlanning(true);
      planTurn(text);
      return;
    }

    /* "make it cheaper", "add Malatap to my trip", or a plain "yes" to
       Bud's offer to plan: straight to the planner, with the chat so far
       as its background. */
    if ((lastOffer && YES_RE.test(t)) || looksLikePlanEdit(text)) {
      addMessage(text, 'user');
      input.value = '';
      lastOffer = false;
      handOffToPlanner(text);
      return;
    }

    addMessage(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';
    runChat(text);
  }

  /* One ordinary question and its answer. Split from submit() so
     "Try again" can run it again without showing the question twice. */
  function runChat(text) {
    busy = true;
    send.disabled = true;

    var typing = addTyping();
    var handedOff = false;

    askBud(text)
      .then(function (result) {
        var reply  = result.reply;
        var places = result.places;

        /* The model said this is for the planner. Its own text is not
           shown: the planner answers in its place. */
        if (result.plan) {
          typing.remove();
          history.pop();          // the planner owns this turn now
          handedOff = true;
          lastOffer = false;
          handOffToPlanner(text);
          return;
        }

        typing.remove();
        var botRow = addMessage(reply, 'bot');

        /* addMessage already puts photo cards inside the bubble when it
           finds a destination name in the reply. The server sends its
           own list too, and drawing both showed every photo twice. The
           server's list is now the fallback: used only when the bubble
           got no cards of its own. */
        if (!botRow.querySelector('.bud__places')) {
          addPlaces(places);
        }
        history.push({ role: 'assistant', content: reply });
        if (history.length > HISTORY_MAX) {
          history = history.slice(-HISTORY_MAX);
        }

        lastOffer = offeredPlan(reply);
        saveSession();
      })
      .catch(function (err) {
        typing.remove();

        /* The server writes messages meant to be read by a visitor, so
           show its text when there is some. Drop the failed question
           from the history - keeping a turn the model never answered
           leaves a gap that confuses the next reply. */
        var row = addMessage(
          (err && err.budMessage) || pickLine('net', NET_LINES),
          'bot'
        );

        history = history.filter(function (t) {
          return !(t.role === 'user' && t.content === text);
        });

        showQuickReplies([{
          label: '\u21BB Try again',
          retry: true,
          run: function () {
            row.remove();
            history.push({ role: 'user', content: text });
            runChat(text);
          }
        }]);

        console.error('Bud.Ai request failed:', err);
      })
      .then(function () {
        if (handedOff) return;     // the planner is mid-request and releases the input itself
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

  /* Pick the conversation up where it was left, and keep it saved. */
  /* Ask the SERVER who is signed in, then load that person's chat.
     Signed in: the chat comes from their account. Guest: from this tab.
     Chats saved in this tab under any OTHER user id are deleted, so
     signing out and into another account never shows the old one. */
  function readLocal() {
    try { return JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null'); } catch (e) { return null; }
  }

  function bootSession() {
    if (previewMode) { USER_ID = 0; SESSION_KEY = 'bud.session.v1.0'; identityReady = true; applySession(readLocal()); return; }
    function finish(serverSession) {
      SESSION_KEY = 'bud.session.v1.' + USER_ID;
      try {
        Object.keys(sessionStorage).forEach(function (k) {
          if (k.indexOf('bud.session.v1') === 0 && k !== SESSION_KEY) sessionStorage.removeItem(k);
        });
      } catch (e) {}

      var saved = serverSession;
      if (!saved) {
        try { saved = JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null'); } catch (e) { saved = null; }
      }
      identityReady = true;
      applySession(saved);
    }

    postJSONGet(endpoint + (endpoint.indexOf('?') < 0 ? '?' : '&') + 'session=1', 10000).then(function (d) {
      USER_ID = (d && d.user > 0) ? parseInt(d.user, 10) : 0;
      finish(USER_ID > 0 ? d.session : null);
    }, function () {
      USER_ID = 0;      // server unreachable: behave as a guest
      finish(null);
    });
  }

  function postJSONGet(url, ms) {
    var ctl = ('AbortController' in window) ? new AbortController() : null;
    var timer = ctl ? setTimeout(function () { ctl.abort(); }, ms) : null;
    var opts = { method: 'GET', credentials: 'same-origin', cache: 'no-store' };
    if (ctl) opts.signal = ctl.signal;
    return fetch(url, opts).then(function (r) {
      if (timer) clearTimeout(timer);
      return r.json();
    }, function (e) { if (timer) clearTimeout(timer); throw e; });
  }

  bootSession();
  window.addEventListener('pagehide', function () { saveSession(true); });

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

    return postJSON(endpoint, {
      message: message,
      history: history.slice(0, -1),  // drop the turn just pushed
      plan: compactPlan(currentPlan)  // the trip on screen, if there is one
    }, 90000)
    .then(function (res) {
      return res.json()
        .catch(function () { return {}; })   // HTML error page, not JSON
        .then(function (data) {
          if (!res.ok || (!data.reply && !data.plan)) {
            var err = new Error('bud.php HTTP ' + res.status);
            if (data && data.error) {
              err.budMessage = data.error;
            }
            throw err;
          }
          return { reply: data.reply || '', places: data.places || [], plan: !!data.plan };
        });
    });
  }
});