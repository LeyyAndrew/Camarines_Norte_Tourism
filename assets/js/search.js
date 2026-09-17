/* ===================================================================
   assets/js/search.js  —  the header search overlay

   Loaded with defer from includes/header.php.

   THIS FILE IS AN ENHANCEMENT, NOT THE FEATURE. The overlay is built
   around a real <form action="search.php" method="get">. If this file
   fails to load, the icon is still a submit-capable form, Enter still
   reaches search.php, and search.php still answers. Nothing here is
   load-bearing — it only saves the visitor a page load.
   =================================================================== */
(function () {
  'use strict';

  /* Resolved from this file's own URL (assets/js/search.js -> site
     root), not from the page. A bare 'api/search.php' breaks on any
     page inside a subfolder such as auth/. */
  var ME       = document.currentScript && document.currentScript.src;
  var API_URL  = ME ? new URL('../../api/search.php', ME).href : 'api/search.php';

  var panel = document.getElementById('siteSearch');
  if (!panel) { return; }

  var form    = panel.querySelector('[data-search-form]');
  var input   = panel.querySelector('[data-search-input]');
  var list    = panel.querySelector('[data-search-results]');
  var live    = panel.querySelector('[data-search-live]');
  var quick   = panel.querySelector('[data-search-quick]');
  var opener  = null;            // who opened it, so focus can go back
  var timer   = null;
  var pending = null;            // in-flight request, so a slow old one
                                 // cannot overwrite a fast new one
  var cursor  = -1;              // index of the arrow-key selection
  var shownQ  = null;            // the query whose results are on screen
  var failed  = false;           // last request failed -> let Enter
                                 // fall back to the full results page
  var wantGo  = null;            // Enter was pressed before results
                                 // arrived: open the top one when
                                 // they do
  var picked  = false;           // true only when the ARROW KEYS chose
                                 // cursor. A mouse hover must never
                                 // decide where Enter goes.

  /* ---------- open / close ---------- */

  function open(from) {
    if (!panel.hidden) { return; }
    opener = from || document.activeElement;

    /* Opened from the phone drawer? Close the drawer first, or two
       aria-modal dialogs are open at once and a screen reader has to
       pick one. Clicking the drawer's own close button reuses nav.js's
       logic instead of reimplementing it here — this file does not
       need to know how that drawer works, only how to ask it to
       leave. */
    var drawer = document.getElementById('navDrawer');
    if (drawer && !drawer.hidden && drawer.contains(opener)) {
      var x = drawer.querySelector('[data-drawer-close]');
      if (x) { x.click(); }
      opener = document.querySelector('.nav__icons [data-search-open]') || null;
    }

    panel.hidden = false;
    document.body.classList.add('ss-open');

    document.querySelectorAll('[data-search-open]').forEach(function (b) {
      b.setAttribute('aria-expanded', 'true');
    });

    /* rAF, not a bare focus(): the element is display:none until the
       class lands, and focusing a hidden element silently does
       nothing on Safari. */
    requestAnimationFrame(function () { input.focus(); input.select(); });
  }

  function close() {
    if (panel.hidden) { return; }
    panel.hidden = true;
    document.body.classList.remove('ss-open');

    document.querySelectorAll('[data-search-open]').forEach(function (b) {
      b.setAttribute('aria-expanded', 'false');
    });

    if (pending) { pending.abort(); pending = null; }
    clearTimeout(timer);

    /* Focus goes back where it came from. Without this it lands on
       <body> and the next Tab starts from the top of the page, which
       for a keyboard user is the whole nav all over again. */
    if (opener && document.contains(opener)) { opener.focus(); }
    opener = null;
  }

  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-search-open]');
    if (o) { e.preventDefault(); open(o); return; }
    if (e.target.closest('[data-search-close]')) { e.preventDefault(); close(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !panel.hidden) { close(); return; }

    /* "/" and ⌘K open it from anywhere — but not while the visitor is
       typing into some other field, where "/" is just a slash. */
    var t = e.target;
    var typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);

    if (e.key === '/' && !typing && panel.hidden) { e.preventDefault(); open(t); }
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); open(t); }
  });

  /* ---------- typing ---------- */

  input.addEventListener('input', function () {
    clearTimeout(timer);

    /* The list on screen now belongs to the OLD text. Without this, an
       arrow-key pick from a previous query survives the typing, and
       Enter follows a result for something the visitor no longer
       wants. */
    clearPick();
    wantGo = null;
    var q = input.value.trim();

    if (q.length < 2) {
      render(null, q);
      return;
    }
    /* 180ms: long enough that a normal typist fires one request per
       word instead of one per letter, short enough that the results
       still feel like they are keeping up. */
    timer = setTimeout(function () { run(q); }, 180);
  });

  function run(q) {
    clearTimeout(timer);
    if (pending) { pending.abort(); }
    pending = new AbortController();
    failed  = false;
    list.innerHTML = '<p class="sitesearch__msg">Searching…</p>';
    if (quick) { quick.hidden = true; }

    fetch(API_URL + '?q=' + encodeURIComponent(q) + '&limit=8', {
      signal: pending.signal,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'        /* send the session cookie */
    })
      .then(function (r) { pending = null; return r.json(); })
      .then(function (data) {
        /* The session ended while this overlay was open — expired, or
           signed out in another tab. The page still shows a signed-in
           header because it was rendered before that happened, so
           without this the visitor just watches results stop
           appearing and has no idea why. */
        if (data.auth === false) {
          list.innerHTML = '<p class="sitesearch__msg">' + esc(data.message || 'Sign in to search.') +
                           '</p><a class="sitesearch__more" href="' + esc(data.login || 'auth/login.php') +
                           '">Sign in →</a>';
          live.textContent = 'Sign in to search';
          if (quick) { quick.hidden = true; }
          return;
        }
        /* The visitor may have typed on while this was in the air. If
           the answer is for an older query, drop it. */
        if (data.q.trim() !== input.value.trim()) { return; }
        render(data, q);
      })
      .catch(function (err) {
        if (err.name === 'AbortError') { return; }
        pending = null;
        failed  = true;
        wantGo  = null;
        /* A network failure is not a dead end: the form still works,
           so say so rather than showing an empty box. */
        list.innerHTML = '<p class="sitesearch__msg">Could not load suggestions. ' +
                         'Press Enter to search anyway.</p>';
      });
  }

  /* ---------- drawing ---------- */

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* Wraps the typed text where it appears in a result. Escaping happens
     BEFORE the <mark> goes in — a destination named <script> must not
     become one on the way through here. */
  function mark(text, q) {
    var safe = esc(text);
    if (!q) { return safe; }
    var needle = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safe.replace(new RegExp('(' + needle + ')', 'ig'), '<mark>$1</mark>');
  }

  /* ---------- going to a result ----------
     Results point at destinations.php#dest-<slug>, the photo card.
     Already on destinations.php (unfiltered), only the #hash changes,
     so the page does not reload — destinations.php listens for that
     and scrolls to the card. Same card twice in a row fires no
     hashchange, so it is called directly. */
  function follow(href) {
    var to = new URL(href, location.href);
    var samePage = to.pathname === location.pathname && to.search === location.search;

    if (samePage && to.hash) {
      close();
      if (to.hash === location.hash && typeof window.destShowCard === 'function') {
        window.destShowCard(to.hash.slice(1));
      } else {
        location.hash = to.hash;
      }
      return;
    }
    window.location.href = to.href;
  }

  function clearPick() {
    cursor = -1;
    picked = false;
    list.querySelectorAll('.sitesearch__item.is-on').forEach(function (el) {
      el.classList.remove('is-on');
    });
  }

  function render(data, q) {
    clearPick();

    shownQ = data ? q : null;

    if (!data) {                       // fewer than two characters
      wantGo = null;
      list.innerHTML = '';
      if (quick) { quick.hidden = false; }
      live.textContent = '';
      return;
    }

    if (quick) { quick.hidden = true; }

    if (!data.results.length) {
      wantGo = null;
      list.innerHTML = '<p class="sitesearch__msg">No matches for “' + esc(q) +
                       '”. Try a shorter word or another spelling.</p>';
      live.textContent = 'No results';
      return;
    }

    var html = data.results.map(function (r) {
      var thumb = r.image
        ? '<img class="sitesearch__thumb" src="' + esc(r.image) + '" alt="" loading="lazy">'
        : '<span class="sitesearch__thumb" aria-hidden="true">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" ' +
          'stroke-linecap="round" stroke-linejoin="round"><path d="M3 17c2 0 2 1.6 4 1.6s2-1.6 4-1.6 ' +
          '2 1.6 4 1.6 2-1.6 4-1.6"/><path d="M12 3v10"/></svg></span>';

      return '<a class="sitesearch__item" href="' + esc(r.url) + '">' + thumb +
             '<span class="sitesearch__body">' +
             '<span class="sitesearch__kind">' + esc(r.kind) + '</span>' +
             '<span class="sitesearch__name">' + mark(r.title, q) + '</span>' +
             (r.meta ? '<span class="sitesearch__meta">' + esc(r.meta) + '</span>' : '') +
             (r.snippet ? '<span class="sitesearch__snip">' + mark(r.snippet, q) + '</span>' : '') +
             '</span></a>';
    }).join('');

    html += '<a class="sitesearch__more" href="search.php?q=' + encodeURIComponent(q) +
            '">See all results for “' + esc(q) + '” →</a>';

    list.innerHTML = html;
    live.textContent = data.count + (data.count === 1 ? ' result' : ' results');

    /* Enter was pressed while this was loading: open the top match. */
    if (wantGo !== null && wantGo === q) {
      wantGo = null;
      follow(data.results[0].url);
    }
  }

  /* ---------- arrow keys ---------- */

  input.addEventListener('keydown', function (e) {
    var items = list.querySelectorAll('.sitesearch__item');

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!items.length) { return; }
      e.preventDefault();
      cursor += (e.key === 'ArrowDown' ? 1 : -1);

      /* Past either end, the highlight comes off entirely and the
         caret is back in the field. Wrapping straight from the last
         result to the first hides the fact that you have reached the
         bottom. */
      if (cursor >= items.length) { cursor = -1; }
      if (cursor < -1)            { cursor = items.length - 1; }
      picked = cursor > -1;

      items.forEach(function (el, i) { el.classList.toggle('is-on', i === cursor); });
      if (cursor > -1) { items[cursor].scrollIntoView({ block: 'nearest' }); }
      return;
    }

    /* Enter is NOT handled here. It reaches the form's submit event
       below, the same place the search button goes, so the two can
       never behave differently. */
  });

  /* The mouse and the keyboard fight over the highlight otherwise:
     you arrow down, the page has not moved, and a stale hover two
     rows up is still lit. */
  list.addEventListener('mousemove', function (e) {
    var item = e.target.closest('.sitesearch__item');
    if (!item) { return; }
    /* Visual only. This used to set cursor, so a pointer merely
       resting where the dropdown appeared made Enter open that
       result instead of searching. */
    list.querySelectorAll('.sitesearch__item').forEach(function (el) {
      el.classList.toggle('is-on', el === item);
    });
    cursor = -1;
    picked = false;
  });

  /* A result on the page you are already on only changes the hash, so no
     navigation happens and the overlay stays parked on top of the very
     thing it just found. Closing is the navigation, in that case. */
  list.addEventListener('click', function (e) {
    var a = e.target.closest('.sitesearch__item, .sitesearch__more');
    if (!a) { return; }

    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) { return; }
    e.preventDefault();
    follow(a.getAttribute('href'));
  });

  /* ---------- the suggested words ---------- */

  if (quick) {
    quick.addEventListener('click', function (e) {
      /* The Popular chips are links (so they still work without JS),
         but clicking one searches in place like typing it would,
         instead of leaving the page. Ctrl/Cmd/middle-click still opens
         the link in a new tab. */
      var tag = e.target.closest('[data-search-term], .sitesearch__tag');
      if (!tag) { return; }
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) { return; }
      e.preventDefault();
      var term = tag.getAttribute('data-search-term') || tag.textContent;
      input.value = term.replace(/\s+/g, ' ').trim();
      input.focus();
      clearPick();
      run(input.value);
    });
  }

  /* Blank submissions would land on an empty results page for no
     reason. Keep the visitor here and let them finish the word. */
  /* ---------- Enter and the search button ----------
     Both land here, on every page.

       arrow-key pick        -> open that result
       results already shown -> open the TOP result
       still loading         -> open the top result when it arrives
       no results            -> stay, the box says "No matches"
       request failed        -> fall back to the full results page */
  form.addEventListener('submit', function (e) {
    var q = input.value.trim();

    if (failed && q.length >= 2) { return; }   // native submit
    e.preventDefault();

    var items = list.querySelectorAll('.sitesearch__item');
    if (picked && cursor > -1 && items[cursor]) {
      follow(items[cursor].getAttribute('href'));
      return;
    }

    if (q.length < 2) { input.focus(); render(null, q); return; }

    if (q === shownQ && !pending) {
      if (items.length) { follow(items[0].getAttribute('href')); }
      return;
    }

    wantGo = q;
    run(q);
  });

  /* Enter pressed while an IME (Japanese, Chinese, etc.) is still
     composing only confirms the word; it must not submit. */
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.isComposing) { e.preventDefault(); }
  });
})();

/* The old "dest-focus" block that used to live here is gone. It
   scrolled to the MAP on arrival, which pulled the page away from the
   photo card. Arriving at #dest-<slug> is now handled at the foot of
   destinations.php (window.destShowCard). */