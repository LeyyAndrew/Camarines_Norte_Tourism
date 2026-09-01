/**
 * saved-places.js
 *
 * Two jobs:
 *   1. The bookmark button on destination cards / detail pages.
 *   2. The "Saved places" picker used by the trip planner.
 *
 * Markup contract
 * ---------------
 * Bookmark button (anywhere):
 *   <button type="button" class="save-btn" data-save data-destination-id="7"
 *           aria-pressed="false" aria-label="Save this place"> … </button>
 *
 * Picker trigger in plan-trip.php:
 *   <button type="button" id="savedPlacesBtn" data-saved-open>Saved places</button>
 *
 * An itinerary row:
 *   <div class="activity-row">
 *     <input type="hidden" name="destination_id[]" class="js-dest-id">
 *     <button type="button" class="js-dest-pick">
 *       <span class="js-dest-label">Select a destination</span>
 *     </button>
 *   </div>
 *
 * Every row control is optional — if a row doesn't match, the script fires a
 * `savedplaces:add` event on document with { detail: { place, row } } so your
 * own planner code can take over.
 */
(function () {
  'use strict';

  const ENDPOINT = window.SAVED_PLACES_ENDPOINT || 'includes/saved-places.php';

  const state = {
    ids: new Set(),
    items: null,     // cached list, cleared on every change
    targetRow: null, // row waiting to be filled, when opened in pick mode
  };

  /* ------------------------------------------------------------------ api */

  async function call(action, params, method) {
    const opts = { method: method || 'GET', headers: { 'X-Requested-With': 'fetch' } };
    let url = ENDPOINT + '?action=' + encodeURIComponent(action);

    if (opts.method === 'POST') {
      const body = new URLSearchParams({ action, ...params });
      opts.body = body;
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      url = ENDPOINT;
    } else if (params) {
      url += '&' + new URLSearchParams(params);
    }

    const res = await fetch(url, opts);
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('The server sent back something unreadable.');
    }
    if (!data.ok) throw Object.assign(new Error(data.message || 'Request failed'), { code: data.error });
    return data;
  }

  /* ------------------------------------------------------------- feedback */

  let toastTimer;
  function toast(message, tone) {
    let el = document.querySelector('.sp-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'sp-toast';
      /* Confirmation that only
         appears when a stylesheet loaded is confirmation you cannot
         rely on. saved-places.css restyles it; this is the floor. */
      el.style.cssText =
        'position:fixed;left:50%;bottom:1.75rem;transform:translate(-50%,.75rem);' +
        'z-index:2147483000;padding:.7rem 1.15rem;border-radius:999px;' +
        'background:#17212e;color:#fff;font:500 .88rem/1.3 system-ui,sans-serif;' +
        'box-shadow:0 .75rem 2rem rgba(0,0,0,.45);white-space:nowrap;' +
        'opacity:0;pointer-events:none;transition:opacity .18s ease,transform .18s ease;';
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.dataset.tone = tone || 'ok';
    el.classList.add('is-visible');
    el.style.opacity = '1';
    el.style.transform = 'translate(-50%, 0)';
    if (tone === 'error') el.style.background = '#7d2b23';

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      el.classList.remove('is-visible');
      el.style.opacity = '0';
      el.style.transform = 'translate(-50%, .75rem)';
      el.style.background = '#17212e';
    }, 2600);
  }

  /* ------------------------------------------------------- bookmark button */

  function paintButton(btn, saved) {
    btn.classList.toggle('is-saved', saved);

    /* The class does the real styling; these keep the state legible
       even if saved-places.css is missing, so a working save never
       looks like a dead button. */
    btn.style.background = saved ? '#f2a33c' : '';
    btn.style.borderColor = saved ? '#f2a33c' : '';
    btn.style.color = saved ? '#10161f' : '';

    /* THE LABEL IS THE INDICATOR THAT SURVIVES EVERYTHING.

       Colour alone fails for anyone who cannot distinguish it, and a
       filled icon is ambiguous — full or empty, which one means saved?
       A word does not have that problem, and it is also the one signal
       that still reads if a stylesheet never loads. Same verb the
       toast uses, so the button and the confirmation agree. */
    const label = btn.querySelector('.dest-card__save-label');
    if (label) label.textContent = saved ? 'Saved' : 'Save';

    btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
    btn.setAttribute('aria-label', saved
      ? 'Saved. Press to remove from your places.'
      : 'Save this place');

    /* A screen reader gets told what changed, once, at the moment it
       changes — aria-pressed alone is only read when the button is
       focused, and this button is usually clicked with a mouse. */
    if (btn.dataset.painted && btn.dataset.painted !== String(saved)) {
      announce(saved ? 'Saved to your places' : 'Removed from your places');
    }
    btn.dataset.painted = String(saved);
  }

  /* One polite live region for the whole page, created on first use. */
  function announce(text) {
    let live = document.getElementById('sp-live');
    if (!live) {
      live = document.createElement('div');
      live.id = 'sp-live';
      live.setAttribute('role', 'status');
      live.setAttribute('aria-live', 'polite');
      live.style.cssText =
        'position:absolute;width:1px;height:1px;overflow:hidden;' +
        'clip:rect(0 0 0 0);white-space:nowrap;';
      document.body.appendChild(live);
    }
    live.textContent = text;
  }

  function paintAll() {
    document.querySelectorAll('[data-save]').forEach((btn) => {
      paintButton(btn, state.ids.has(Number(btn.dataset.destinationId)));
    });
    document.querySelectorAll('[data-saved-count]').forEach((el) => {
      el.textContent = String(state.ids.size);
      el.hidden = state.ids.size === 0;
    });
  }

  /* ------------------------------------------------- stacking fix

     .dest-card__btn::after in destinations.css stretches the View
     details link across the whole card at z-index 3. A bookmark button
     sitting at or below that never receives a click — it looks solid,
     it highlights on hover, and nothing happens.

     saved-places.css lifts it to 4, but that only helps if the file is
     actually on the page. These are INLINE styles on purpose: an
     element's style attribute beats any stylesheet, so the button is
     clickable even if the CSS never loaded, is cached stale, or landed
     in the wrong folder. Cheap insurance against a silent failure that
     looks identical to a broken backend. */
  /* ---------------------------------------------------- placement

     There used to be a routine here that moved each bookmark out of
     the card body and pinned it to the photo corner, to escape the
     stretched .dest-card__btn::after link.

     IT IS GONE, and its absence is the point. destinations.php now
     renders the button inside .dest-card__actions carrying the
     .dest-card__map class, which already has position:relative and
     z-index:4 — the rule that has always made the Map button
     clickable. The markup is correct on arrival, so moving it at
     runtime could only put it somewhere worse. */

  async function hydrate() {
    try {
      const data = await call('ids');
      state.ids = new Set(data.ids);
      paintAll();
      watchHero();
    } catch (err) {
      if (err.code !== 'not_logged_in') console.warn('[saved-places]', err.message);
    }
  }

  async function toggle(btn) {
    const id = Number(btn.dataset.destinationId);
    if (!id || btn.dataset.busy) return;

    const next = !state.ids.has(id);
    btn.dataset.busy = '1';
    paintButton(btn, next); // optimistic

    try {
      const data = await call('toggle', { destination_id: id }, 'POST');
      data.saved ? state.ids.add(id) : state.ids.delete(id);
      state.items = null;
      paintAll();
      toast(data.message);

      /* A half-second pulse on the button that was actually pressed.
         With 24 identical cards on screen, the toast says WHAT
         happened but not WHERE — this answers that, then gets out of
         the way. */
      btn.classList.add('sp-just-changed');
      setTimeout(() => btn.classList.remove('sp-just-changed'), 600);
    } catch (err) {
      paintButton(btn, !next); // revert
      toast(err.code === 'not_logged_in' ? 'Log in to save places.' : err.message, 'error');
    } finally {
      delete btn.dataset.busy;
    }
  }

  /* ---------------------------------------------------------------- panel */

  function buildPanel() {
    const wrap = document.createElement('div');
    wrap.className = 'sp-panel';
    wrap.hidden = true;
    wrap.innerHTML = `
      <div class="sp-panel__scrim" data-sp-close></div>
      <aside class="sp-panel__sheet" role="dialog" aria-modal="true" aria-labelledby="spTitle">
        <header class="sp-panel__head">
          <h2 id="spTitle">Your saved places</h2>
          <button type="button" class="sp-panel__close" data-sp-close aria-label="Close">&times;</button>
        </header>
        <p class="sp-panel__hint"></p>
        <div class="sp-panel__body" aria-live="polite"></div>
      </aside>`;
    document.body.appendChild(wrap);

    wrap.addEventListener('click', (e) => {
      if (e.target.closest('[data-sp-close]')) closePanel();
    });
    return wrap;
  }

  let panel;
  function panelEl() {
    return panel || (panel = buildPanel());
  }

  function render(items) {
    const body = panelEl().querySelector('.sp-panel__body');

    if (!items.length) {
      body.innerHTML = `
        <div class="sp-empty">
          <p>Nothing saved yet.</p>
          <p>Tap the bookmark on any destination and it lands here, ready to drop into a trip.</p>
          <a class="sp-empty__link" href="destinations.php">Browse destinations</a>
        </div>`;
      return;
    }

    body.innerHTML = '<ul class="sp-list">' + items.map((p) => `
      <li class="sp-item" data-id="${p.id}">
        ${p.image ? `<img class="sp-item__img" src="${p.image}" alt="" loading="lazy">`
                  : '<span class="sp-item__img sp-item__img--blank" aria-hidden="true"></span>'}
        <div class="sp-item__text">
          <span class="sp-item__name">${escapeHtml(p.name)}</span>
          ${p.location ? `<span class="sp-item__meta">${escapeHtml(p.location)}</span>` : ''}
        </div>
        <button type="button" class="sp-item__add" data-sp-add="${p.id}">Add to trip</button>
        <button type="button" class="sp-item__drop" data-sp-remove="${p.id}" aria-label="Remove ${escapeHtml(p.name)} from saved">&times;</button>
      </li>`).join('') + '</ul>';
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, (c) => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
  }

  async function openPanel(row) {
    state.targetRow = row || null;
    const el = panelEl();
    el.hidden = false;
    requestAnimationFrame(() => el.classList.add('is-open'));
    document.body.style.overflow = 'hidden';

    el.querySelector('.sp-panel__hint').textContent = row
      ? 'Pick a place for this stop.'
      : 'Add a place and it drops into the next open stop.';

    const body = el.querySelector('.sp-panel__body');
    if (state.items) {
      render(state.items);
    } else {
      body.innerHTML = '<p class="sp-loading">Loading your places…</p>';
      try {
        const data = await call('list');
        state.items = data.items;
        state.ids = new Set(data.items.map((i) => i.id));
        paintAll();
        render(state.items);
      } catch (err) {
        body.innerHTML = `<p class="sp-error">${escapeHtml(
          err.code === 'not_logged_in' ? 'Log in to see your saved places.' : err.message
        )}</p>`;
      }
    }
    el.querySelector('.sp-panel__close').focus();
  }

  function closePanel() {
    if (!panel) return;
    panel.classList.remove('is-open');
    document.body.style.overflow = '';
    setTimeout(() => { panel.hidden = true; }, 200);
    state.targetRow = null;
  }

  /* ------------------------------------------------- dropping into a row */

  function rowIsEmpty(row) {
    const hidden = row.querySelector('.js-dest-id');
    const select = row.querySelector('select[name*="destination"]');
    if (hidden) return !hidden.value;
    if (select) return !select.value;
    return true;
  }

  function nextEmptyRow() {
    const rows = Array.from(document.querySelectorAll('.activity-row'));
    return rows.find(rowIsEmpty) || rows[rows.length - 1] || null;
  }

  function fillRow(row, place) {
    if (!row) return false;
    let filled = false;

    const hidden = row.querySelector('.js-dest-id');
    if (hidden) { hidden.value = place.id; filled = true; }

    const label = row.querySelector('.js-dest-label');
    if (label) { label.textContent = place.name; label.classList.remove('is-placeholder'); filled = true; }

    const select = row.querySelector('select[name*="destination"]');
    if (select && [...select.options].some((o) => o.value == place.id)) {
      select.value = String(place.id);
      select.dispatchEvent(new Event('change', { bubbles: true }));
      filled = true;
    }

    const text = row.querySelector('input[type="text"][name*="destination"]');
    if (text) { text.value = place.name; text.dispatchEvent(new Event('input', { bubbles: true })); filled = true; }

    if (filled) {
      row.classList.add('is-just-filled');
      setTimeout(() => row.classList.remove('is-just-filled'), 900);
    }
    return filled;
  }

  function addToTrip(id) {
    const place = (state.items || []).find((p) => p.id === Number(id));
    if (!place) return;

    const row = state.targetRow || nextEmptyRow();
    const filled = fillRow(row, place);

    // Always announce it — your planner code can listen and do its own thing.
    document.dispatchEvent(new CustomEvent('savedplaces:add', {
      detail: { place, row, handled: filled },
    }));

    if (filled) {
      toast(place.name + ' added to your trip.');
      closePanel();
    } else {
      toast('Add a stop first, then pick a place for it.', 'error');
    }
  }

  async function removeFromPanel(id) {
    try {
      await call('remove', { destination_id: id }, 'POST');
      state.ids.delete(Number(id));
      state.items = (state.items || []).filter((p) => p.id !== Number(id));
      paintAll();
      render(state.items);
      toast('Removed from your places.');
    } catch (err) {
      toast(err.message, 'error');
    }
  }

  /* ------------------------------------------------------- the hero

     The featured block cycles through the same destinations behind a
     single save button, so that button has to re-point every time the
     slide changes or it saves whichever place happened to load first.

     destinations-hero.js owns the carousel and publishes nothing —
     no event, no global. Rather than edit it and couple two features
     together, this watches the one thing it always rewrites: the text
     of #heroTitle. A MutationObserver on that node fires on every
     change, from any cause — arrow, rail click, drag, autoplay —
     without the hero needing to know this exists.

     The slide list is already on the page as JSON for the hero's own
     use, which is where the ids come from. */
  function watchHero() {
    const btn = document.querySelector('[data-hero-save]');
    const title = document.getElementById('heroTitle');
    const json = document.getElementById('heroSlides');
    if (!btn || !title || !json) return;

    let slides = [];
    try {
      slides = JSON.parse(json.textContent) || [];
    } catch (e) {
      return; // malformed payload: leave the button on its printed id
    }

    const sync = () => {
      const name = title.textContent.trim();
      const slide = slides.find((sl) => (sl.name || '').trim() === name);
      if (!slide || !slide.id) return;

      btn.dataset.destinationId = String(slide.id);
      paintButton(btn, state.ids.has(Number(slide.id)));

      const label = 'Save ' + name + ' to your places';
      btn.setAttribute('aria-label', state.ids.has(Number(slide.id))
        ? 'Saved. Press to remove ' + name + ' from your places.'
        : label);
    };

    new MutationObserver(sync).observe(title, {
      childList: true,
      characterData: true,
      subtree: true,
    });

    sync();
  }

  /* ------------------------------------------------------------- wiring */

  /* ---------------------------------------------- the capture net

     Stacking was the wrong battle to fight. Whatever sits on top of a
     bookmark button — the stretched .dest-card__btn::after link, a
     GSAP-transformed wrapper, anything added later — the click still
     passes through document during the CAPTURE phase, before it
     reaches the element that swallows it.

     So this does not ask what was clicked. It asks whether the click
     landed inside the rectangle of a save button, which is geometry
     and cannot be intercepted by anything painted above.

     e.detail === 0 means keyboard or a scripted .click(), which carry
     no coordinates — those fall through to the normal listener below.
     stopPropagation keeps the card's own link from also firing. */
  function saveButtonAt(x, y) {
    return [...document.querySelectorAll('[data-save]')].find((btn) => {
      if (!btn.offsetParent) return false;              // hidden
      const r = btn.getBoundingClientRect();
      if (!r.width || !r.height) return false;
      return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
    });
  }

  document.addEventListener('click', (e) => {
    if (e.detail === 0) return;
    const hit = saveButtonAt(e.clientX, e.clientY);
    if (hit) {
      e.preventDefault();
      e.stopPropagation();
      toggle(hit);
    }
  }, true);

  document.addEventListener('click', (e) => {
    const save = e.target.closest('[data-save]');
    if (save) { e.preventDefault(); toggle(save); return; }

    const open = e.target.closest('[data-saved-open]');
    if (open) { e.preventDefault(); openPanel(null); return; }

    const pick = e.target.closest('.js-dest-pick');
    if (pick) { e.preventDefault(); openPanel(pick.closest('.activity-row')); return; }

    const add = e.target.closest('[data-sp-add]');
    if (add) { addToTrip(add.dataset.spAdd); return; }

    const drop = e.target.closest('[data-sp-remove]');
    if (drop) { removeFromPanel(drop.dataset.spRemove); }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && panel && !panel.hidden) closePanel();
  });

  document.addEventListener('DOMContentLoaded', hydrate);
  if (document.readyState !== 'loading') hydrate();



  window.SavedPlaces = { open: openPanel, close: closePanel, refresh: hydrate, ids: () => [...state.ids] };
})();