/* ===================================================================
   assets/js/legal-modal.js

   Opens Terms of Use, Disclosure and Privacy Policy in a popup instead
   of leaving the page. Any link carrying data-legal="terms" /
   "disclosure" / "privacy" opens it. The markup is in
   includes/footer.php; the look is in assets/css/legal-style.css.

   The text is fetched from the real pages with ?fragment=1, so there
   is only ONE copy of each document — edit legal/terms.php and the
   popup changes too. If JavaScript fails, the links are ordinary
   links and still open the full page.

   -------------------------------------------------------------------
   WHY THE X AND TABS USED TO BE DEAD

   assets/js/auth-gate.js cancels every click on a link or button that
   is not in .nav, .footer or .auth-modal and opens the sign-in card
   instead. This popup is its own element, so its X and tabs were
   being cancelled. auth-gate.js now lists .legal-modal as free —
   THAT is the fix.

   As a second line of defence, this file also handles its clicks on
   `window` in the capture phase, the first stop a click makes, so
   even a future gate on `document` cannot swallow them. It is loaded
   without `defer` in footer.php so it is registered first.
   =================================================================== */
(function () {
  var modal = document.getElementById('legalModal');
  if (!modal || !window.fetch) return;

  var body     = modal.querySelector('.legal-modal__body');      // scrolls
  var content  = modal.querySelector('.legal-modal__content');   // holds the text
  var title    = modal.querySelector('.legal-modal__title');
  var metaDate = modal.querySelector('[data-legal-updated]');
  var metaRead = modal.querySelector('[data-legal-read]');
  var toc      = modal.querySelector('.legal-modal__toc');
  var bar      = modal.querySelector('.legal-modal__progress span');
  var currentUrl = '';   // the open document's full-page address, for Print
  var accept   = modal.querySelector('[data-legal-accept]');
  var closeBt  = modal.querySelector('.legal-modal__close');
  var tabs     = Array.prototype.slice.call(modal.querySelectorAll('.legal-modal__tab'));

  var cache = {};          // key -> ready-made HTML, so tabs switch instantly
  var current = null;
  var returnFocus = null;
  var agreeBox = null;     // the sign-up "I agree" checkbox, when opened from it
  var isOpen = false;

  function tabFor(key) {
    for (var i = 0; i < tabs.length; i++) {
      if (tabs[i].getAttribute('data-legal-key') === key) return tabs[i];
    }
    return null;
  }

  /* The fetched text is written for legal/terms.php, so its links and
     ids need two adjustments before it can live inside another page:
       - relative links ("privacy.php#bud") are made absolute against
         the legal page's own URL, not the page the popup is open on
       - ids get a "legal-" prefix, because the homepage already has
         its own #contact and would be scrolled to instead */
  function prepare(html, pageUrl) {
    var box = document.createElement('div');
    box.innerHTML = html;

    Array.prototype.forEach.call(box.querySelectorAll('[id]'), function (el) {
      el.id = 'legal-' + el.id;
    });

    Array.prototype.forEach.call(box.querySelectorAll('a[href]'), function (a) {
      var raw = a.getAttribute('href');
      if (/^(mailto:|tel:)/i.test(raw)) return;
      if (raw.charAt(0) === '#') { a.setAttribute('href', '#legal-' + raw.slice(1)); return; }
      var abs = new URL(raw, pageUrl);
      a.setAttribute('href', abs.href);
      if (abs.origin !== location.origin) { a.target = '_blank'; a.rel = 'noopener'; }
    });

    return box.innerHTML;
  }

  /* ---- the document header and the table of contents ---- */
  function decorate() {
    var updated = content.querySelector('.legal-doc__updated');
    metaDate.textContent = updated ? updated.textContent.replace(/^Last updated\s*/i, 'Effective ') : '';

    var words = (content.textContent || '').trim().split(/\s+/).length;
    metaRead.textContent = words > 50 ? Math.max(1, Math.round(words / 220)) + ' min read' : '';

    toc.innerHTML = '';
    Array.prototype.forEach.call(content.querySelectorAll('section[id] > h2'), function (h) {
      var li = document.createElement('li');
      var a  = document.createElement('a');
      a.href = '#' + h.parentNode.id;
      /* "7. The Bud.Ai assistant" -> "The Bud.Ai assistant" */
      a.textContent = h.textContent.replace(/^\s*\d+\.\s*/, '');
      li.appendChild(a);
      toc.appendChild(li);
    });
    spy();
  }

  /* progress bar + which heading is on screen */
  function spy() {
    var max = body.scrollHeight - body.clientHeight;
    bar.style.width = (max > 0 ? Math.min(100, body.scrollTop / max * 100) : 0) + '%';

    var top = body.getBoundingClientRect().top + 90;
    var sections = content.querySelectorAll('section[id]');
    var active = null;
    for (var i = 0; i < sections.length; i++) {
      if (sections[i].getBoundingClientRect().top <= top) active = sections[i].id;
    }
    if (max > 0 && body.scrollTop >= max - 4 && sections.length) active = sections[sections.length - 1].id;

    Array.prototype.forEach.call(toc.querySelectorAll('a'), function (a) {
      a.classList.toggle('is-active', a.getAttribute('href') === '#' + active);
    });
  }
  body.addEventListener('scroll', spy, { passive: true });

  function show(key, anchor) {
    var tab = tabFor(key);
    if (!tab) return;
    current = key;

    tabs.forEach(function (t) { t.setAttribute('aria-pressed', t === tab ? 'true' : 'false'); });
    title.textContent = tab.getAttribute('data-legal-title') || tab.textContent.trim();
    currentUrl = tab.getAttribute('data-legal-url');

    function place(html) {
      if (current !== key) return;        // another tab was clicked meanwhile
      content.innerHTML = html;
      body.style.scrollBehavior = 'auto';
      var target = anchor && content.querySelector('#legal-' + anchor);
      if (target) target.scrollIntoView({ block: 'start' });
      else body.scrollTop = 0;
      body.style.scrollBehavior = '';
      decorate();
    }

    if (cache[key]) { place(cache[key]); return; }

    content.innerHTML = '<p class="legal-modal__status">Loading…</p>';
    metaDate.textContent = metaRead.textContent = '';
    toc.innerHTML = '';
    var url = tab.getAttribute('data-legal-url');
    fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'fragment=1', { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
      .then(function (html) {
        cache[key] = prepare(html, new URL(url, location.href).href);
        place(cache[key]);
      })
      .catch(function () {
        place('<p class="legal-modal__status">This could not be loaded. ' +
              '<a href="' + url + '">Open it as a page instead</a>.</p>');
      });
  }

  /* Opened from the sign-up form? Then the main button is "I agree"
     and ticks the terms checkbox, the way sign-up forms elsewhere do. */
  function findAgreeBox(opener) {
    var form = opener && opener.closest ? opener.closest('form') : null;
    return form ? form.querySelector('input[type="checkbox"][data-terms], input[type="checkbox"][name="terms"]') : null;
  }

  function open(key, anchor, opener) {
    returnFocus = opener || document.activeElement;
    agreeBox = findAgreeBox(opener);
    accept.textContent = agreeBox ? 'I agree' : 'I understand';

    isOpen = true;
    modal.hidden = false;
    document.documentElement.classList.add('legal-modal-open');
    /* next frame, so the fade-in transition actually runs */
    requestAnimationFrame(function () { modal.classList.add('is-open'); });
    show(key, anchor);
    try { closeBt.focus({ preventScroll: true }); } catch (err) {}
  }

  function close() {
    if (!isOpen) return;
    isOpen = false;
    modal.classList.remove('is-open');
    document.documentElement.classList.remove('legal-modal-open');
    setTimeout(function () {
      if (isOpen) return;                 // reopened during the fade-out
      modal.hidden = true;
    }, 250);
    if (returnFocus && returnFocus.focus) {
      try { returnFocus.focus({ preventScroll: true }); } catch (err) {}
    }
  }

  function agree() {
    if (agreeBox && !agreeBox.checked) {
      agreeBox.checked = true;
      /* so auth-modal.js's own checkbox logic (the submit button
         unlocking, the error message clearing) hears about it */
      agreeBox.dispatchEvent(new Event('input',  { bubbles: true }));
      agreeBox.dispatchEvent(new Event('change', { bubbles: true }));
    }
    close();
  }

  /* Print just the document, on a clean page of its own. */
  function print() {
    var w = window.open('', '_blank');
    if (!w) { window.open(currentUrl, '_blank'); return; }
    var date = metaDate.textContent ? '<p class="meta">' + metaDate.textContent + '</p>' : '';
    w.document.write(
      '<!doctype html><html><head><meta charset="utf-8"><title>' + title.textContent + '</title>' +
      '<style>body{font:15px/1.7 Georgia,serif;color:#111;max-width:44rem;margin:2.5rem auto;padding:0 1.5rem}' +
      'h1{font:700 1.9rem/1.2 system-ui,sans-serif;margin:0 0 .3rem}h2{font:700 1.1rem system-ui,sans-serif;margin:1.8rem 0 .5rem}' +
      'h3{font:700 .8rem system-ui,sans-serif;text-transform:uppercase;letter-spacing:.08em;color:#555}' +
      '.meta{color:#555;margin:0 0 1.5rem;font-family:system-ui,sans-serif;font-size:.85rem}' +
      '.legal-doc__updated{display:none}.legal-callout,.legal-contact,.legal-doc__intro{border-left:3px solid #999;padding:.2rem 1rem}' +
      'a{color:#111}</style></head><body>' +
      '<p class="meta">Explore Camarines Norte — Legal Center</p><h1>' + title.textContent + '</h1>' + date +
      content.innerHTML + '</body></html>'
    );
    w.document.close();
    w.focus();
    setTimeout(function () { w.print(); }, 250);
  }

  /* which document a link points at: data-legal first, else its URL */
  function keyFromLink(a) {
    if (a.getAttribute('data-legal')) return a.getAttribute('data-legal');
    var m = /\/legal\/(terms|disclosure|privacy)\.php/.exec(a.href || '');
    return m ? m[1] : null;
  }

  /* stop a click completely: no default action, no other script */
  function swallow(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }

  /* ---------------------------------------------------------------
     THE ONE CLICK HANDLER — window, capture phase, registered first.
     --------------------------------------------------------------- */
  window.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;

    /* ---- clicks INSIDE the open popup ---- */
    if (isOpen && modal.contains(t)) {

      if (t.closest('[data-legal-close]'))  { swallow(e); close(); return; }
      if (t.closest('[data-legal-accept]')) { swallow(e); agree(); return; }
      if (t.closest('[data-legal-print]'))  { swallow(e); print(); return; }

      var tab = t.closest('.legal-modal__tab');
      if (tab) { swallow(e); show(tab.getAttribute('data-legal-key')); return; }

      var link = t.closest('a');
      if (link) {
        var href = link.getAttribute('href') || '';

        /* #section inside the popup (the table of contents too):
           scroll the popup, not the page */
        if (href.indexOf('#legal-') === 0) {
          swallow(e);
          var sec = content.querySelector(href);
          if (sec) sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
          return;
        }

        /* a link to another legal document: switch tabs */
        var k = keyFromLink(link);
        if (k && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
          swallow(e);
          show(k, (link.hash || '').replace(/^#/, ''));
          return;
        }

        /* any other link (mailto, outside sites):
           let the browser follow it, but keep other scripts out */
        e.stopPropagation();
        e.stopImmediatePropagation();
        return;
      }

      /* a click on plain text in the popup: nobody else's business */
      e.stopPropagation();
      e.stopImmediatePropagation();
      return;
    }

    /* ---- a Terms / Disclosure / Privacy link anywhere on the page ---- */
    var opener = t.closest('a[data-legal]');
    if (opener && !isOpen) {
      /* ctrl/cmd/shift-click and middle-click keep opening a real tab */
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
      swallow(e);
      open(opener.getAttribute('data-legal'), '', opener);
    }
  }, true);

  /* KEYBOARD.
     Escape closes ONLY this popup — auth-modal.js would otherwise
     close the sign-in card underneath as well.
     Tab loops inside the popup. auth-modal.js has its own Tab loop for
     the sign-in card, and without this one, tabbing past the last
     button here would drop the focus onto the page behind. */
  window.addEventListener('keydown', function (e) {
    if (!isOpen) return;

    if (e.key === 'Escape' || e.key === 'Esc') {
      swallow(e);
      close();
      return;
    }

    if (e.key !== 'Tab') return;
    e.stopPropagation();
    e.stopImmediatePropagation();

    var items = Array.prototype.filter.call(
      modal.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])'),
      function (el) { return el.offsetParent !== null; }
    );
    if (!items.length) return;

    var first = items[0], last = items[items.length - 1];
    var inside = modal.contains(document.activeElement);

    if (e.shiftKey && (document.activeElement === first || !inside)) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && (document.activeElement === last || !inside)) {
      e.preventDefault(); first.focus();
    }
  }, true);
}());