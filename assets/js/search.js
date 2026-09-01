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
    if (pending) { pending.abort(); }
    pending = new AbortController();

    fetch('api/search.php?q=' + encodeURIComponent(q) + '&limit=8', {
      signal: pending.signal,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'        /* send the session cookie */
    })
      .then(function (r) { return r.json(); })
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

  function render(data, q) {
    cursor = -1;

    if (!data) {                       // fewer than two characters
      list.innerHTML = '';
      if (quick) { quick.hidden = false; }
      live.textContent = '';
      return;
    }

    if (quick) { quick.hidden = true; }

    if (!data.results.length) {
      list.innerHTML = '<p class="sitesearch__msg">No matches for “' + esc(q) +
                       '”. Press Enter to search the whole site.</p>';
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

      items.forEach(function (el, i) { el.classList.toggle('is-on', i === cursor); });
      if (cursor > -1) { items[cursor].scrollIntoView({ block: 'nearest' }); }
      return;
    }

    /* Enter on a highlighted result follows it. Enter with nothing
       highlighted submits the form, which is the default — so it is
       left alone. */
    if (e.key === 'Enter' && cursor > -1 && items[cursor]) {
      e.preventDefault();
      window.location.href = items[cursor].getAttribute('href');
    }
  });

  /* The mouse and the keyboard fight over the highlight otherwise:
     you arrow down, the page has not moved, and a stale hover two
     rows up is still lit. */
  list.addEventListener('mousemove', function (e) {
    var item = e.target.closest('.sitesearch__item');
    if (!item) { return; }
    var items = Array.prototype.slice.call(list.querySelectorAll('.sitesearch__item'));
    cursor = items.indexOf(item);
    items.forEach(function (el, i) { el.classList.toggle('is-on', i === cursor); });
  });

  /* ---------- the suggested words ---------- */

  if (quick) {
    quick.addEventListener('click', function (e) {
      var tag = e.target.closest('[data-search-term]');
      if (!tag) { return; }
      input.value = tag.getAttribute('data-search-term');
      input.focus();
      input.dispatchEvent(new Event('input'));   // same path as typing it
    });
  }

  /* Blank submissions would land on an empty results page for no
     reason. Keep the visitor here and let them finish the word. */
  form.addEventListener('submit', function (e) {
    if (input.value.trim() === '') { e.preventDefault(); input.focus(); }
  });
})();