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

  var toggle = document.getElementById('budToggle');
  var panel  = document.getElementById('budPanel');
  var log    = document.getElementById('budLog');
  var input  = document.getElementById('budInput');
  var send   = document.getElementById('budSend');

  if (!toggle || !panel || !log || !input || !send) return;

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
  function addMessage(text, who) {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--' + (who === 'user' ? 'user' : 'bot');

    var p = document.createElement('p');
    p.textContent = text;

    wrap.appendChild(p);
    log.appendChild(wrap);
    scrollLog();
    return wrap;
  }

  function addTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'bud__msg bud__msg--bot';
    wrap.innerHTML = '<span class="bud__typing"><i></i><i></i><i></i></span>';
    log.appendChild(wrap);
    scrollLog();
    return wrap;
  }

  /* ---------- sending ---------- */

  function submit() {
    var text = input.value.trim();
    if (!text || busy) return;

    busy = true;
    send.disabled = true;

    addMessage(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';

    var typing = addTyping();

    askBud(text)
      .then(function (reply) {
        typing.remove();
        addMessage(reply, 'bot');
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
          resolve(
            'This is a design preview \u2014 I am not connected to an AI ' +
            'yet, so I cannot answer that properly. Once api/bud.php is ' +
            'set up I will be answering from the tourism database.'
          );
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
          return data.reply;
        });
    });
  }
});