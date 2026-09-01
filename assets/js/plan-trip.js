/* ===================================================================
   assets/js/plan-trip.js

   Loaded with defer from plan-trip.php, the same way nav.js is loaded
   from the header. It reads window.PT_DATA, which plan-trip.php
   prints just above the tag that loads this file — the 24 entries
   come from includes/destinations-data.php, normalised there.

   The itinerary is never held in a JS object that has to be kept in
   step with the page. The DOM is the state: collect() reads the rows
   whenever a total is needed. That means typing a note can never be
   interrupted by a re-render, which is the usual way a builder like
   this eats a half-written sentence.
   =================================================================== */
/* ===================================================================
   Rotating hero word — cycles the amber word in the hero headline
   between "Adventure" and "Itinerary".

   It is deliberately its own IIFE, ahead of the builder below rather
   than inside it. The builder bails on "if (!daysEl) return;" a few
   lines in, and anything sharing that function would die with it.

   No change to plan-trip.php is required. The <em> already in the
   headline is the element this drives; if that <em> is given
   id="ptRotate" and a data-pt-words list, those win instead.
   =================================================================== */
(function () {
  'use strict';

  /* The id first, for when the markup names one, then the <em> that
     the hero has had all along. */
  var el = document.getElementById('ptRotate') ||
           document.querySelector('.pt-hero h1 em');

  if (!el) {
    /* Better a line in the console than a headline that silently
       never moves. */
    if (window.console) {
      console.warn('[plan-trip] rotating word: no ".pt-hero h1 em" in the page.');
    }
    return;
  }

  /* The stylesheet hangs off this class; the markup need not carry it. */
  el.classList.add('pt-rotate');

  var attr = el.getAttribute('data-pt-words');
  var words = [];
  if (attr) {
    attr.split('|').forEach(function (w) {
      w = w.replace(/^\s+|\s+$/g, '');
      if (w !== '') words.push(w);
    });
  } else {
    /* Whatever the headline says now stays first, so the page reads
       the same at rest as it did before. */
    words = [(el.textContent || 'Adventure').replace(/^\s+|\s+$/g, ''), 'Itinerary'];
  }
  if (words.length < 2) return;

  /* INTERVAL is the gap between swaps, not the length of one. In wait
     mode a swap runs exit-then-enter, roughly 1.4s on a nine-letter
     word, so 2600 leaves the finished word still for about a second.
     Drop it towards 2000 for something more insistent. */
  var INTERVAL = 2600;
  var DUR      = 520;   /* keep in step with --pt-rotate-dur */
  var STAGGER  = 25;    /* per letter, counted from the last one */

  var reduced = window.matchMedia &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var sr = document.createElement('span');
  sr.className = 'pt-rotate-sr';
  sr.textContent = words[0];

  var clip = document.createElement('span');
  clip.className = 'pt-rotate-clip';
  clip.setAttribute('aria-hidden', 'true');

  el.textContent = '';
  el.appendChild(sr);
  el.appendChild(clip);

  /* Letters split with Intl.Segmenter where it exists, so an accented
     or composed character stays one box instead of splitting into a
     letter and a floating diacritic. */
  function letters(word) {
    if (typeof Intl !== 'undefined' && Intl.Segmenter) {
      var seg = new Intl.Segmenter('en', { granularity: 'grapheme' });
      return Array.prototype.map.call(
        Array.from(seg.segment(word)), function (s) { return s.segment; }
      );
    }
    return word.split('');
  }

  function build(word) {
    var w = document.createElement('span');
    w.className = 'pt-rotate-word';
    var cs = letters(word), n = cs.length, i, c;
    for (i = 0; i < n; i++) {
      c = document.createElement('span');
      c.className = 'pt-rotate-ch';
      c.textContent = cs[i];
      /* staggerFrom "last": the final letter moves first */
      c.style.transitionDelay = ((n - 1 - i) * STAGGER) + 'ms';
      w.appendChild(c);
    }
    return w;
  }

  function span(word) { return DUR + (letters(word).length - 1) * STAGGER; }

  function reveal(w, instant) {
    var ch = w.children, i;
    for (i = 0; i < ch.length; i++) {
      if (instant) ch[i].style.transition = 'none';
      ch[i].classList.add('is-in');
    }
    if (instant) {
      /* Give the transitions back a frame later, or the first word
         would also leave without animating. */
      window.requestAnimationFrame(function () {
        for (var j = 0; j < ch.length; j++) ch[j].style.transition = '';
      });
    }
  }

  var idx = 0;
  var cur = build(words[0]);
  clip.appendChild(cur);
  reveal(cur, true);                       /* on load the word is simply there */

  /* Sets the clip to the current word's real width with no animation.
     Needed twice over: the hero is font-size:clamp(38px,6.4vw,64px),
     so every resize changes the width, and Archivo arrives from the
     header after first paint, so the width measured on load can be a
     fallback face's — which would crop the word for good. */
  function resize() {
    var was = clip.style.transition;
    clip.style.transition = 'none';
    clip.style.width = cur.offsetWidth + 'px';
    void clip.offsetWidth;                 /* flush, or the restore below is free */
    clip.style.transition = was;
  }

  resize();

  if (document.fonts && document.fonts.ready && document.fonts.ready.then) {
    document.fonts.ready.then(resize);
  }

  var rt;
  window.addEventListener('resize', function () {
    window.clearTimeout(rt);
    rt = window.setTimeout(resize, 120);
  });

  function swap() {
    /* Nothing to look at on a background tab, and a backgrounded
       browser coalesces timers anyway — every word would land at once
       on return. */
    if (document.hidden) return;

    idx = (idx + 1) % words.length;
    var word = words[idx];
    var out  = cur;

    sr.textContent = word;

    var next = build(word);

    /* Measured out of flow, so the reading is the incoming word's own
       width rather than the two of them side by side. */
    next.style.position = 'absolute';
    next.style.visibility = 'hidden';
    clip.appendChild(next);
    var width = next.offsetWidth;
    next.style.position = '';
    next.style.visibility = '';

    cur = next;

    if (reduced) {
      /* The stylesheet drops every transition under this setting, so
         the sequencing below would leave letters parked mid-flight.
         Swap outright and keep the word legible. */
      clip.removeChild(out);
      clip.style.width = width + 'px';
      reveal(next, true);
      return;
    }

    out.classList.add('is-out');
    clip.style.width = width + 'px';

    var leaving = span(out.textContent);
    window.setTimeout(function () {
      if (out.parentNode) out.parentNode.removeChild(out);
    }, leaving + 80);

    /* Wait mode: the old word is gone before the new one starts, which
       is what the reference config does. Pass 0 instead and the two
       cross in the middle. */
    window.setTimeout(function () { reveal(next, false); }, leaving);
  }

  window.setInterval(swap, INTERVAL);
})();

(function () {
  'use strict';

  var D = window.PT_DATA || {};
  var DESTS = D.destinations || [];
  var TYPES = D.types || [];
  var TOWNS = D.towns || [];
  var SAVE_URL = D.saveUrl || 'save-itinerary.php';
  var SIGNED_IN = !!D.signedIn;

  var BY_ID = {};
  DESTS.forEach(function (d) { BY_ID[d.id] = d; });

  /* ---------- SAVED PLACES ----------
     The ids in saved_destinations are the destinations table's primary
     keys, while everything here is keyed on the town-name slug, so the
     match happens through d.dest_id, printed alongside the slug by
     plan-trip.php.

     Fetched once, on load, rather than each time the Saved chip is
     pressed: it is a short list of integers and the picker should not
     wait on the network to redraw. A failure leaves the set empty and
     the chip simply shows nothing — the other two axes are unaffected,
     which is the point of keeping this to one variable. */
  var SAVED = null;   /* null = not loaded yet, Set = loaded */

  function loadSaved(then) {
    fetch('includes/saved-places.php?action=ids', { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        SAVED = new Set((data && data.ids) || []);
        paintSavedCount();
        if (then) then();
      })
      .catch(function () { SAVED = new Set(); if (then) then(); });
  }

  function paintSavedCount() {
    var n = SAVED ? SAVED.size : 0;
    [].forEach.call(document.querySelectorAll('[data-saved-count]'), function (el) {
      el.textContent = n;
      el.hidden = n === 0;
    });
  }

  loadSaved();

  /* A colour and a glyph per type, so an entry whose photo is missing
     from uploads/Destination-Photo/ still reads as a waterfall rather
     than as a grey box. Amber goes to the beaches and the surf: it is
     the site's own accent and the coast is what the province is for. */
  var TYPE = {
    'Beach':         { g: 'linear-gradient(135deg,#F2A93B,#D97A25)', i: 'M2 18c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M6 13a6 6 0 0 1 12 0' },
    'Beach Resort':  { g: 'linear-gradient(135deg,#F2A93B,#C96A2A)', i: 'M12 21V9M12 9c-4 0-7 2.5-7 5h14c0-2.5-3-5-7-5ZM12 9V5' },
    'Surf':          { g: 'linear-gradient(135deg,#FFC163,#E0812C)', i: 'M3 17c4 0 6-11 12-11 3 0 6 2 6 5M3 20h18' },
    'Island':        { g: 'linear-gradient(135deg,#2E8FA8,#155F72)', i: 'M3 19h18M12 4v10M12 6c-3 0-5 2-5 4h10c0-2-2-4-5-4' },
    'Waterfall':     { g: 'linear-gradient(135deg,#35A98C,#166B5C)', i: 'M7 3v11a5 5 0 0 0 10 0V3M7 8h10M10 19c0 1.5 1 2 2 2s2-.5 2-2' },
    'River':         { g: 'linear-gradient(135deg,#3BA0A8,#146A72)', i: 'M2 8c4 0 4 4 8 4s4-4 8-4 4 4 4 4M2 16c4 0 4 3 8 3s4-3 8-3' },
    'Mangrove':      { g: 'linear-gradient(135deg,#3FBF87,#1E7A55)', i: 'M12 21V11M12 11 6 6M12 11l6-5M6 21h12' },
    'Park':          { g: 'linear-gradient(135deg,#5FB55E,#2C7A3C)', i: 'M12 3 5 14h14L12 3ZM12 14v7' },
    'Mountain':      { g: 'linear-gradient(135deg,#6E8794,#3A4E5A)', i: 'm3 19 6-11 4 7 2-3 6 7H3Z' },
    'Peak':          { g: 'linear-gradient(135deg,#7E96A3,#42565F)', i: 'm4 20 8-15 8 15H4ZM9 11h6' },
    'View Deck':     { g: 'linear-gradient(135deg,#8A9DA8,#4A5D68)', i: 'M3 20h18M6 20V9l6-4 6 4v11M10 20v-6h4v6' },
    'Monument':      { g: 'linear-gradient(135deg,#B98B45,#7A5522)', i: 'M12 3v14M8 17h8M6 21h12M9 7h6' },
    'Church':        { g: 'linear-gradient(135deg,#A98246,#6E5027)', i: 'M12 2v6M9 5h6M5 21V11l7-4 7 4v10M10 21v-5h4v5' },
    'Religious Site':{ g: 'linear-gradient(135deg,#C09150,#7C5A28)', i: 'M12 2v7M9 5h6M6 21V12l6-3 6 3v9M10 21v-4h4v4' },
    'Adventure':     { g: 'linear-gradient(135deg,#E2593F,#A82F1B)', i: 'm4 12 16-7-7 16-2-7-7-2Z' },
    'Campsite':      { g: 'linear-gradient(135deg,#D96F3C,#94401C)', i: 'm12 4 8 16H4l8-16ZM12 12l4 8M12 12l-4 8' }
  };
  function glyph(t, s) {
    var d = (TYPE[t] || TYPE.Island).i;
    return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' + d + '"/></svg>';
  }
  function grad(t) { return (TYPE[t] || TYPE.Island).g; }

  var PIN = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
    'stroke-width="2.6"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/>' +
    '<circle cx="12" cy="10" r="2.3"/></svg>';

  var $ = function (s) { return document.querySelector(s); };
  var daysEl = $('#ptDays');
  if (!daysEl) return;

  var activePick = null;
  var scope = 'type';   /* which axis the chips filter on */
  var chosen = 'All';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function fmtDate(d, long) {
    if (!d) return '—';
    return d.toLocaleDateString('en-PH', long
      ? { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }
      : { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function dayDate(i) {
    var s = $('#ptStart').value;
    if (!s) return null;
    var d = new Date(s + 'T00:00:00');
    if (isNaN(d)) return null;
    d.setDate(d.getDate() + i);
    return d;
  }

  function fmtTime(t) {
    if (!t) return '--:--';
    var p = t.split(':'), h = +p[0];
    return (((h + 11) % 12) + 1) + ':' + p[1] + ' ' + (h >= 12 ? 'PM' : 'AM');
  }

  var toastTimer;
  /* action is optional: {label, onClick}. Given one, the toast holds
     longer, because a message you are meant to act on should not
     vanish while you are still reading it. */
  function toast(html, action) {
    var t = $('#ptToast');
    t.innerHTML = html;
    if (action) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pt-toast-act';
      b.textContent = action.label;
      b.addEventListener('click', function () {
        t.classList.remove('is-up');
        action.onClick();
      });
      t.appendChild(b);
    }
    t.classList.add('is-up');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      t.classList.remove('is-up');
    }, action ? 9000 : 3400);
  }

  /* ---------- confirm dialog ----------
     Built and torn down per call rather than left in the markup, so
     there is never a stale one to collide with. The keydown listener
     is registered in the capture phase so Escape resolves this
     promise before the page-wide handler sees the key and closes the
     dialog without an answer. */
  function ptConfirm(o) {
    return new Promise(function (resolve) {
      var el = document.createElement('div');
      el.className = 'pt-modal pt-confirm is-open';
      el.setAttribute('role', 'alertdialog');
      el.setAttribute('aria-modal', 'true');
      el.innerHTML =
        '<div class="pt-veil"></div>' +
        '<div class="pt-sheet">' +
          '<div class="pt-sheet-h">' +
            '<span class="pt-label">' + esc(o.eyebrow || 'Confirm') + '</span>' +
            '<h2>' + esc(o.title) + '</h2>' +
          '</div>' +
          '<div class="pt-confirm-b"><p>' + esc(o.body) + '</p>' +
            (o.loss ? '<div class="pt-loss">' + o.loss + '</div>' : '') +
          '</div>' +
          '<div class="pt-confirm-f">' +
            '<button type="button" class="pt-btn pt-btn--ghost" data-no>' + esc(o.cancel || 'Cancel') + '</button>' +
            '<button type="button" class="pt-btn pt-btn--solid" data-yes>' + esc(o.confirm) + '</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(el);

      var previous = document.activeElement;
      var wasLocked = document.body.style.overflow === 'hidden';
      document.body.style.overflow = 'hidden';
      /* focus lands on Cancel, not on the destructive button */
      el.querySelector('[data-no]').focus();

      function done(answer) {
        document.removeEventListener('keydown', onKey, true);
        el.remove();
        if (!wasLocked) document.body.style.overflow = '';
        if (previous && previous.focus) previous.focus();
        resolve(answer);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.stopPropagation(); done(false); }
      }
      document.addEventListener('keydown', onKey, true);
      el.querySelector('[data-yes]').addEventListener('click', function () { done(true); });
      el.querySelector('[data-no]').addEventListener('click', function () { done(false); });
      el.querySelector('.pt-veil').addEventListener('click', function () { done(false); });
    });
  }

  /* the small square on a filled slot: the real photo when the file is
     on disk, the type gradient when it is not */
  function thumbHtml(d) {
    return '<span class="pt-thumb" style="background:' + grad(d.category) + '">' +
      (d.image ? '<img src="' + esc(d.image) + '" alt="">' : glyph(d.category, 19)) + '</span>';
  }
  function pickHtml(d) {
    return thumbHtml(d) + '<span class="pt-pt"><b>' + esc(d.name) + '</b><span>' +
      esc(d.municipality) + ' · ' + esc(d.category) + '</span></span>';
  }

  /* ---------- building ---------- */
  function makeDay(times) {
    var el = document.createElement('section');
    el.className = 'pt-day';
    el.innerHTML =
      '<div class="pt-day-h">' +
        '<div class="pt-day-no"></div>' +
        '<div class="pt-day-t"><b></b><span></span></div>' +
        '<button type="button" class="pt-chip" data-remove-day>Remove day</button>' +
      '</div>' +
      '<div class="pt-acts"></div>' +
      '<button type="button" class="pt-add-act" data-add-act>' +
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>' +
        'Add activity</button>';
    daysEl.appendChild(el);
    (times || []).forEach(function (t) { addRow(el.querySelector('.pt-acts'), t); });
    renumber();
    return el;
  }

  function addRow(acts, time) {
    var row = document.createElement('div');
    row.className = 'pt-act';
    row.innerHTML =
      '<div class="pt-time"><input type="time" value="' + (time || '') + '" aria-label="Time"></div>' +
      '<button type="button" class="pt-pick">' +
        '<span class="pt-thumb" style="background:rgba(255,255,255,.06)">' +
          '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#868E94" stroke-width="1.9"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>' +
        '</span><span class="pt-ph">Select a destination</span>' +
      '</button>' +
      '<textarea class="pt-note" rows="1" placeholder="Activity or notes…" aria-label="Activity or notes"></textarea>' +
      '<button type="button" class="pt-del" aria-label="Remove activity">' +
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>' +
      '</button>';
    acts.appendChild(row);
    sync();
    return row;
  }

  function renumber() {
    var kids = daysEl.children;
    for (var i = 0; i < kids.length; i++) {
      var date = dayDate(i);
      kids[i].querySelector('.pt-day-no').textContent = (i + 1 < 10 ? '0' : '') + (i + 1);
      kids[i].querySelector('.pt-day-t b').textContent = 'Day ' + (i + 1);
      kids[i].querySelector('.pt-day-t span').textContent =
        date ? fmtDate(date, true) : 'Set a start date to fill this in';
      kids[i].querySelector('[data-remove-day]').style.display = kids.length > 1 ? '' : 'none';
    }
    sync();
  }

  /* ---------- read the page ---------- */
  function collect() {
    var days = [].map.call(daysEl.children, function (d, i) {
      var dt = dayDate(i);
      return {
        date: dt ? dt.getFullYear() + '-' + ('0' + (dt.getMonth() + 1)).slice(-2) +
              '-' + ('0' + dt.getDate()).slice(-2) : '',
        items: [].map.call(d.querySelectorAll('.pt-act'), function (r) {
          return {
            time: r.querySelector('input[type=time]').value,
            destId: r.querySelector('.pt-pick').dataset.id || '',
            note: r.querySelector('.pt-note').value.trim()
          };
        })
      };
    });
    return {
      name: $('#ptName').value.trim() || 'Untitled trip',
      start: $('#ptStart').value,
      end: $('#ptEnd').value,
      travelers: +$('#ptTravelers').value || 1,
      days: days
    };
  }

  /* ---------- summary ---------- */
  function sync() {
    var t = collect();
    $('#ptSumName').textContent = t.name;
    $('#ptSumTravelers').textContent = t.travelers + (t.travelers === 1 ? ' traveller' : ' travellers');
    $('#ptStatDays').textContent = t.days.length;
    $('#ptStatDest').textContent = t.days.reduce(function (n, d) {
      return n + d.items.filter(function (i) { return i.destId; }).length;
    }, 0);
    $('#ptSumStart').textContent = fmtDate(dayDate(0));
    $('#ptSumEnd').textContent = t.days.length ? fmtDate(dayDate(t.days.length - 1)) : '—';

    var filled = t.days.map(function (d, i) {
      return { i: i, items: d.items.filter(function (x) { return x.destId || x.note; }) };
    }).filter(function (d) { return d.items.length; });

    $('#ptOverview').innerHTML = filled.length ? filled.map(function (d) {
      var rows = d.items.sort(function (a, b) {
        return (a.time || '99').localeCompare(b.time || '99');
      }).map(function (x) {
        var dd = BY_ID[x.destId];
        return '<div class="pt-ov-item"><time>' + fmtTime(x.time) + '</time><i>' +
          (dd ? esc(dd.name) : esc(x.note)) +
          (dd ? '<small>' + esc(dd.municipality) + (x.note ? ' · ' + esc(x.note) : '') + '</small>' : '') +
          '</i></div>';
      }).join('');
      return '<div class="pt-ov-day"><b>Day ' + (d.i + 1) + ' <span>· ' +
        (dayDate(d.i) ? fmtDate(dayDate(d.i)) : 'no date') + '</span></b>' + rows + '</div>';
    }).join('')
      : '<div class="pt-ov-empty">Nothing planned yet.<br>Add a destination and it shows up here.</div>';
  }

  /* ---------- events on the builder ---------- */
  daysEl.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add-act]');
    if (add) { addRow(add.previousElementSibling); return; }

    var rmDay = e.target.closest('[data-remove-day]');
    if (rmDay) {
      var day = rmDay.closest('.pt-day');
      day.classList.add('is-going');
      setTimeout(function () { day.remove(); renumber(); }, 260);
      return;
    }

    var del = e.target.closest('.pt-del');
    if (del) {
      var row = del.closest('.pt-act'), acts = row.parentElement;
      row.classList.add('is-going');
      setTimeout(function () {
        row.remove();
        if (!acts.children.length) addRow(acts, '09:00');
        sync();
      }, 260);
      return;
    }

    var pick = e.target.closest('.pt-pick');
    if (pick) {
      activePick = pick;
      openModal('#ptDestModal');
      $('#ptSearch').focus();
    }
  });
  daysEl.addEventListener('input', sync);

  ['#ptName', '#ptStart', '#ptEnd', '#ptTravelers'].forEach(function (s) {
    $(s).addEventListener('input', renumber);
  });

  /* End date generates the days between it and the start. Capped, so a
     mistyped year cannot spawn four thousand cards and lock the tab. */
  $('#ptEnd').addEventListener('change', function () {
    var s = $('#ptStart').value, e = $('#ptEnd').value;
    if (!s || !e) return;
    var span = Math.round((new Date(e) - new Date(s)) / 864e5) + 1;
    if (span < 1) { toast('That end date is before the start date.'); return; }
    if (span > 30) { toast('That is over 30 days. Shorten the range, or add days by hand.'); return; }
    while (daysEl.children.length < span) makeDay(['09:00']);
    while (daysEl.children.length > span) daysEl.lastElementChild.remove();
    renumber();
  });

  $('#ptAddDay').addEventListener('click', function () {
    makeDay(['09:00']).scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  /* ---------- destination picker ---------- */
  function drawChips() {
    /* Saved is not an axis you subdivide: it is already the narrowest
       view of the 24. The chip row is emptied rather than hidden with
       CSS, so nothing is left focusable behind an invisible bar. */
    if (scope === 'saved') { $('#ptFilters').innerHTML = ''; return; }

    var list = scope === 'type' ? TYPES : TOWNS;
    $('#ptFilters').innerHTML = ['All'].concat(list).map(function (c) {
      return '<button type="button" class="pt-f' + (c === chosen ? ' is-on' : '') +
        '" data-val="' + esc(c) + '">' + esc(c) + '</button>';
    }).join('');
  }

  $('#ptScope').addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b || b.dataset.scope === scope) return;
    scope = b.dataset.scope;
    chosen = 'All';
    [].forEach.call(this.children, function (x) { x.classList.toggle('is-on', x === b); });
    drawChips();

    /* Bookmarks are made on another page, often in another tab, so the
       set is re-read every time this axis is opened rather than trusted
       from page load. drawDests runs either way: stale-then-correct
       beats an empty grid while the request is in flight. */
    if (scope === 'saved') loadSaved(drawDests);
    drawDests();
  });

  $('#ptFilters').addEventListener('click', function (e) {
    var b = e.target.closest('.pt-f');
    if (!b) return;
    chosen = b.dataset.val;
    [].forEach.call(this.children, function (x) { x.classList.toggle('is-on', x === b); });
    drawDests();
  });

  $('#ptSearch').addEventListener('input', drawDests);

  function drawDests() {
    var q = $('#ptSearch').value.trim().toLowerCase();
    var list = DESTS.filter(function (d) {
      /* Saved ignores the chips entirely — there are none — and matches
         on the database key rather than the slug. */
      if (scope === 'saved') {
        if (!SAVED || !d.dest_id || !SAVED.has(d.dest_id)) return false;
        var hs = (d.name + ' ' + d.municipality + ' ' + d.category + ' ' + d.blurb).toLowerCase();
        return !q || hs.indexOf(q) > -1;
      }

      var okChip = chosen === 'All' ||
        (scope === 'type' ? d.category === chosen : d.municipality === chosen);
      var hay = (d.name + ' ' + d.municipality + ' ' + d.category + ' ' + d.blurb).toLowerCase();
      return okChip && (!q || hay.indexOf(q) > -1);
    });

    $('#ptCount').textContent = list.length + (list.length === 1 ? ' place' : ' places');

    $('#ptDestGrid').innerHTML = list.length ? list.map(function (d) {
      return '<div class="pt-dcard" role="button" tabindex="0" data-id="' + esc(d.id) +
        '" aria-label="Add ' + esc(d.name) + ', ' + esc(d.municipality) + '">' +
        '<div class="pt-shot" style="background:' + grad(d.category) + '">' +
          (d.image ? '<img src="' + esc(d.image) + '" alt="" loading="lazy">' : glyph(d.category, 40)) +
          '<span class="pt-tag">' + esc(d.category) + '</span>' +
          '<span class="pt-add-hint">' +
            '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 5v14M5 12h14"/></svg>Add</span>' +
        '</div>' +
        '<div class="pt-db">' +
          '<span class="pt-town">' + PIN + esc(d.municipality) + '</span>' +
          '<b>' + esc(d.name) + '</b>' +
          '<span class="pt-d">' + esc(d.blurb) + '</span>' +
        '</div></div>';
    }).join('')
      : (scope === 'saved'
          ? '<p class="pt-none">Nothing saved yet.<br>Tap the bookmark on any destination and it lands here, ready to drop into a day.</p>'
          : '<p class="pt-none">Nothing matches that.<br>Try a municipality — Daet, Mercedes, Vinzons, Labo, Paracale — or clear the filter.</p>');
  }
  drawChips();
  drawDests();

  $('#ptDestGrid').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var c = e.target.closest('.pt-dcard');
    if (!c) return;
    e.preventDefault();
    c.click();
  });

  $('#ptDestGrid').addEventListener('click', function (e) {
    var c = e.target.closest('.pt-dcard');
    if (!c || !activePick) return;
    var d = BY_ID[c.dataset.id];
    activePick.dataset.id = d.id;
    activePick.classList.add('is-filled');
    activePick.closest('.pt-act').classList.add('is-set');
    activePick.innerHTML = pickHtml(d);
    closeModal();
    sync();
  });

  /* ---------- modal plumbing ---------- */
  var lastFocus = null;
  function openModal(sel) {
    lastFocus = document.activeElement;
    document.querySelector(sel).classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    [].forEach.call(document.querySelectorAll('.pt-modal.is-open'), function (m) {
      m.classList.remove('is-open');
    });
    document.body.style.overflow = '';
    if (lastFocus) { lastFocus.focus(); lastFocus = null; }
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-pt-close]')) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.querySelector('.pt-modal.is-open')) closeModal();
  });

  /* ---------- preview ---------- */
  $('#ptPreview').addEventListener('click', function () {
    var t = collect();
    var body = t.days.map(function (d, i) {
      var items = d.items.filter(function (x) { return x.destId || x.note; })
        .sort(function (a, b) { return (a.time || '99').localeCompare(b.time || '99'); });
      if (!items.length) return '';
      return '<div class="pt-prev-day"><h4>Day ' + (i + 1) + ' — ' +
        (dayDate(i) ? fmtDate(dayDate(i), true) : 'date not set') + '</h4>' +
        items.map(function (x) {
          var dd = BY_ID[x.destId];
          return '<div class="pt-prev-row"><time>' + fmtTime(x.time) + '</time><div><b>' +
            (dd ? esc(dd.name) : esc(x.note)) + '</b>' +
            (dd ? '<small>' + esc(dd.municipality) + ' · ' + esc(dd.category) +
                  (x.note ? ' — ' + esc(x.note) : '') + '</small>' : '') +
            '</div></div>';
        }).join('') + '</div>';
    }).join('');

    $('#ptPrevBody').innerHTML =
      '<div class="pt-prev-h"><span class="pt-label">Itinerary</span><h3>' + esc(t.name) + '</h3><p>' +
      fmtDate(dayDate(0), true) + ' → ' + fmtDate(dayDate(t.days.length - 1), true) +
      ' · ' + t.travelers + ' traveller' + (t.travelers === 1 ? '' : 's') +
      ' · ' + t.days.length + ' day' + (t.days.length === 1 ? '' : 's') + '</p></div>' +
      (body || '<p class="pt-none">Your itinerary is still empty. Add a destination first.</p>');
    openModal('#ptPrevModal');
  });

  /* ---------- draft storage ----------
     Wrapped, because a browser in private mode throws on the first
     localStorage read rather than returning null. Falling back to a
     variable keeps the draft alive for the session even then. */
  var memory = null;
  var store = {
    set: function (v) { try { localStorage.setItem('lakbai_itinerary_draft', v); } catch (e) { memory = v; } },
    get: function () { try { return localStorage.getItem('lakbai_itinerary_draft'); } catch (e) { return memory; } },
    clear: function () { try { localStorage.removeItem('lakbai_itinerary_draft'); } catch (e) { memory = null; } }
  };

  $('#ptSaveDraft').addEventListener('click', function () {
    store.set(JSON.stringify(collect()));
    toast('Draft saved on this device. <b>It will be here when you come back.</b>');
  });

  $('#ptClear').addEventListener('click', function () {
    var before = collect();
    var days = before.days.length;
    var stops = before.days.reduce(function (n, d) {
      return n + d.items.filter(function (i) { return i.destId; }).length;
    }, 0);
    var notes = before.days.reduce(function (n, d) {
      return n + d.items.filter(function (i) { return !i.destId && i.note; }).length;
    }, 0);

    /* Nothing to lose, nothing to ask about. */
    if (!stops && !notes && days <= 1) {
      toast('The itinerary is already empty.');
      return;
    }

    var parts = [days + (days === 1 ? ' day' : ' days')];
    if (stops) parts.push(stops + (stops === 1 ? ' destination' : ' destinations'));
    if (notes) parts.push(notes + (notes === 1 ? ' note' : ' notes'));

    ptConfirm({
      eyebrow: 'Clear itinerary',
      title: 'Clear ' + (before.name === 'Untitled trip' ? 'this itinerary' : '\u201C' + before.name + '\u201D') + '?',
      body: 'This empties the whole planner and starts you on a blank day. Your saved draft on this device is removed too.',
      loss: 'You will lose <b>' + parts.join('</b>, <b>') + '</b>.',
      cancel: 'Keep it',
      confirm: 'Clear itinerary'
    }).then(function (yes) {
      if (!yes) return;
      store.clear();
      daysEl.innerHTML = '';
      $('#ptName').value = '';
      $('#ptStart').value = '';
      $('#ptEnd').value = '';
      $('#ptTravelers').value = 2;
      makeDay(['08:00', '12:00', '15:00']);
      toast('Itinerary cleared.', {
        label: 'Undo',
        onClick: function () {
          hydrate(before);
          store.set(JSON.stringify(before));
          toast('Itinerary restored.');
        }
      });
    });
  });

  $('#ptSave').addEventListener('click', function () {
    var t = collect();
    var hasDest = t.days.some(function (d) {
      return d.items.some(function (i) { return i.destId; });
    });
    if (!hasDest) { toast('Add at least one destination before saving.'); return; }

    store.set(JSON.stringify(t));

    if (!SIGNED_IN) {
      toast('Saved on this device. <b>Sign in to keep it on your account.</b>');
      return;
    }

    fetch(SAVE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(t)
    }).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      return r.json();
    }).then(function () {
      toast('Itinerary saved to your account.');
    }).catch(function () {
      toast('Saved on this device — <b>save-itinerary.php did not answer.</b>');
    });
  });

  /* ---------- restore, or start fresh ---------- */
  /* Named, because undo rebuilds the page from a snapshot the same
     way a saved draft does. One function, one set of bugs. */
  function hydrate(t) {
    daysEl.innerHTML = '';
    $('#ptName').value = (t.name && t.name !== 'Untitled trip') ? t.name : '';
    $('#ptStart').value = t.start || '';
    $('#ptEnd').value = t.end || '';
    $('#ptTravelers').value = t.travelers || 2;
    (t.days || []).forEach(function (d) {
      var acts = makeDay([]).querySelector('.pt-acts');
      (d.items && d.items.length ? d.items : [{ time: '09:00' }]).forEach(function (it) {
        var row = addRow(acts, it.time);
        row.querySelector('.pt-note').value = it.note || '';
        var dd = BY_ID[it.destId];
        if (dd) {
          var p = row.querySelector('.pt-pick');
          p.dataset.id = dd.id;
          p.classList.add('is-filled');
          row.classList.add('is-set');
          p.innerHTML = pickHtml(dd);
        }
      });
    });
    if (!daysEl.children.length) makeDay(['08:00', '12:00', '15:00']);
    renumber();
  }

  var raw = store.get();
  if (raw) {
    try {
      hydrate(JSON.parse(raw));
      toast('Picked up the draft you left here.');
    } catch (err) {
      daysEl.innerHTML = '';
      makeDay(['08:00', '12:00', '15:00']);
    }
  } else {
    makeDay(['08:00', '12:00', '15:00']);
  }
})();