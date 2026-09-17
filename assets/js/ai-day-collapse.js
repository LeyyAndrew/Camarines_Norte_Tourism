/* ===================================================================
   assets/js/ai-day-collapse.js

   Turns each day of an AI-built itinerary into a section that can be
   folded shut. A 4-day plan with getting-there notes and fee tables
   runs to several screens; folded, it is four lines you can scan.

   PROGRESSIVE. includes/ai-itineraries.php still prints every day
   open. This script only adds the toggle once it runs, so if it fails
   to load the panel is exactly what it was before — complete, just
   long.

   Expects the markup the panel already prints:
     .ai-days > .ai-day > .ai-day-h (.ai-day-n, .ai-day-count)
                        + ol.ai-stops > li.ai-stop-row
   =================================================================== */
(function () {
  'use strict';

  var uid = 0;

  /* Day 1 open, the rest folded: you land on where the trip starts,
     and can see at a glance how many days follow. */
  function startsOpen(index) { return index === 0; }

  function txt(el) { return el ? el.textContent.replace(/\s+/g, ' ').trim() : ''; }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  /* What a folded day still tells you: when it runs, and where. */
  function buildPeek(stops) {
    var times = [], names = [];
    stops.querySelectorAll('.ai-stop-row').forEach(function (row) {
      var t = txt(row.querySelector('.ai-time'));
      var n = txt(row.querySelector('.ai-stop-top b'));
      if (t) times.push(t);
      if (n) names.push(n);
    });

    var peek = el('span', 'ai-day-peek');
    if (times.length) {
      var span = times.length > 1 ? times[0] + '\u2013' + times[times.length - 1] : times[0];
      peek.appendChild(el('span', 'ai-day-span', span));
    }
    if (names.length) {
      var shown = names.slice(0, 2).join(', ');
      var more = names.length > 2 ? ', +' + (names.length - 2) + ' more' : '';
      var list = el('span', 'ai-day-names', shown + more);
      list.title = names.join(', ');
      peek.appendChild(list);
    }
    return peek;
  }

  function setOpen(day, open) {
    var btn = day.querySelector(':scope > .ai-day-h .ai-day-toggle');
    var body = day.querySelector(':scope > .ai-day-body');
    if (!btn || !body) return;
    day.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    /* inert keeps folded links and buttons out of the tab order. */
    if (open) body.removeAttribute('inert'); else body.setAttribute('inert', '');
  }

  function syncToolbar(wrap) {
    var tool = wrap.querySelector(':scope > .ai-days-tools .ai-days-all');
    if (!tool) return;
    var days = wrap.querySelectorAll(':scope > .ai-day[data-collapsible]');
    var allOpen = Array.prototype.every.call(days, function (d) { return d.classList.contains('is-open'); });
    tool.textContent = allOpen ? 'Collapse all days' : 'Expand all days';
    tool.dataset.next = allOpen ? 'close' : 'open';
  }

  function enhanceDay(day, index) {
    var head = day.querySelector(':scope > .ai-day-h');
    var stops = day.querySelector(':scope > .ai-stops');
    if (!head || !stops) return false;

    var bodyId = 'ai-day-body-' + (++uid);

    var btn = el('button', 'ai-day-toggle');
    btn.type = 'button';
    btn.setAttribute('aria-controls', bodyId);

    var chev = el('span', 'ai-day-chev');
    chev.setAttribute('aria-hidden', 'true');
    chev.innerHTML = '<svg viewBox="0 0 12 12" width="12" height="12"><path d="M4 2.5 7.5 6 4 9.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    btn.appendChild(chev);

    while (head.firstChild) btn.appendChild(head.firstChild);
    btn.appendChild(buildPeek(stops));
    head.appendChild(btn);

    /* Everything after the header folds, not just the stops, in case
       the panel ever prints a per-day note under them. */
    var body = el('div', 'ai-day-body');
    body.id = bodyId;
    var inner = el('div', 'ai-day-inner');
    body.appendChild(inner);
    while (head.nextSibling) inner.appendChild(head.nextSibling);
    day.appendChild(body);

    day.dataset.collapsible = '1';
    setOpen(day, startsOpen(index));
    return true;
  }

  function enhanceWrap(wrap) {
    var days = wrap.querySelectorAll(':scope > .ai-day:not([data-collapsible])');
    if (!days.length) return;

    days.forEach(function (d, i) { enhanceDay(d, i); });

    if (wrap.querySelectorAll(':scope > .ai-day').length > 1 &&
        !wrap.querySelector(':scope > .ai-days-tools')) {
      var bar = el('div', 'ai-days-tools');
      var all = el('button', 'ai-days-all');
      all.type = 'button';
      bar.appendChild(all);
      wrap.insertBefore(bar, wrap.firstChild);
    }
    syncToolbar(wrap);
  }

  function scan(root) {
    (root || document).querySelectorAll('.ai-days').forEach(enhanceWrap);
  }

  /* One listener for every plan on the page. */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.ai-day-toggle');
    if (toggle) {
      var day = toggle.closest('.ai-day');
      setOpen(day, !day.classList.contains('is-open'));
      syncToolbar(day.parentElement);
      return;
    }
    var all = e.target.closest('.ai-days-all');
    if (all) {
      var wrap = all.closest('.ai-days');
      var open = all.dataset.next === 'open';
      wrap.querySelectorAll(':scope > .ai-day[data-collapsible]').forEach(function (d) { setOpen(d, open); });
      syncToolbar(wrap);
    }
  });

  /* Printing from plan-trip.php should give the whole trip, not
     whichever days happened to be open. */
  var printed = [];
  window.addEventListener('beforeprint', function () {
    printed = [];
    document.querySelectorAll('.ai-day[data-collapsible]:not(.is-open)').forEach(function (d) {
      printed.push(d); setOpen(d, true);
    });
  });
  window.addEventListener('afterprint', function () {
    printed.forEach(function (d) { setOpen(d, false); });
    printed = [];
  });

  function start() {
    scan();
    /* If the panel is ever re-rendered by script (after a delete, say),
       pick up the new days too. */
    var pending = false;
    new MutationObserver(function () {
      if (pending) return;
      pending = true;
      requestAnimationFrame(function () { pending = false; scan(); });
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();