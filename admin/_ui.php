<?php
/* ===================================================================
   admin/_ui.php — the shared interface layer.

   Required once by _footer.php, so every admin page gets all of it
   and no page has to remember anything.

   Three things live here:
     1. the toast that confirms a save
     2. the drawer controller (add / edit panels)
     3. the confirmation dialog, pulled in from _confirm.php

   ---------------------------------------------------------------
   WHY THE FLASH BECAME A TOAST

   The flash used to print as a strip at the top of the page. Two
   problems with that: it pushed the entire layout down by a line
   every time you saved anything, and after a save-and-redirect your
   eye is on the list, not the top of the page — so the confirmation
   appeared exactly where you were not looking.

   A toast appears at the bottom right, stays long enough to read, and
   removes itself. The layout never moves.

   The wording rule: a toast uses the same verb as the button that
   caused it. "Add destination" produces "Destination added." A button
   that says one thing and a message that says another makes people
   check whether the right thing happened.
   =================================================================== */

$uiFlash = $uiFlash ?? null;   /* set by _header.php from takeFlash() */
?>

<div class="adm-toasts" id="admToasts" role="status" aria-live="polite"></div>

<?php require __DIR__ . '/_confirm.php'; ?>

<script>
/* ===================================================================
   TOASTS
   =================================================================== */
window.admToast = (function () {
  var host = document.getElementById('admToasts');

  var ICON_OK  = '<path d="M20 6 9 17l-5-5"/>';
  var ICON_BAD = '<circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/>';

  function show(message, kind) {
    if (!host || !message) return;

    var bad = kind === 'bad';

    var el = document.createElement('div');
    el.className = 'adm-toast' + (bad ? ' adm-toast--bad' : '');

    var icon = document.createElement('span');
    icon.className = 'adm-toast__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '<svg viewBox="0 0 24 24">' + (bad ? ICON_BAD : ICON_OK) + '</svg>';

    /* textContent, not innerHTML — the message can contain a place
       name somebody typed, and a name with a < in it should print as
       a < rather than open a tag. */
    var text = document.createElement('span');
    text.className = 'adm-toast__text';
    text.textContent = message;

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'adm-toast__x';
    close.setAttribute('aria-label', 'Dismiss');
    close.textContent = '\u00d7';

    el.appendChild(icon);
    el.appendChild(text);
    el.appendChild(close);
    host.appendChild(el);

    var timer = setTimeout(dismiss, bad ? 9000 : 5000);

    /* An error stays nearly twice as long as a success. A success is
       "yes, that worked" and you already knew; an error has something
       to read and possibly to act on. */

    function dismiss() {
      clearTimeout(timer);
      el.classList.add('is-going');
      setTimeout(function () { el.remove(); }, 220);
    }

    close.addEventListener('click', dismiss);

    /* Stop the clock while the pointer is on it — you may be halfway
       through reading a long message. */
    el.addEventListener('mouseenter', function () { clearTimeout(timer); });
    el.addEventListener('mouseleave', function () {
      timer = setTimeout(dismiss, 2500);
    });
  }

  return show;
})();

<?php if ($uiFlash): ?>
/* The message from the last request, handed over by _header.php.
   json_encode does the escaping — a name with a quote or an apostrophe
   in it would otherwise end the string early and break the script. */
admToast(<?= json_encode($uiFlash['message'], JSON_UNESCAPED_UNICODE) ?>,
         <?= json_encode($uiFlash['type'] ?? 'ok') ?>);
<?php endif; ?>


/* ===================================================================
   DRAWERS

   Any button with data-drawer="someId" opens <div id="someId">.
   Anything inside with data-drawer-close closes it, as do Escape and
   a click on the scrim.

   Progressive enhancement: with no JS the drawer markup is still on
   the page and .adm-drawer is visibility:hidden, so nothing shows and
   nothing breaks — but the form inside would be unreachable. Each
   page therefore keeps its form working by opening the drawer from
   PHP when ?edit= is present, via the is-open class in the markup.
   =================================================================== */
(function () {
  var openDrawer = null;
  var lastFocus  = null;

  function open(el) {
    if (!el) return;
    lastFocus = document.activeElement;

    el.classList.add('is-open');
    el.removeAttribute('aria-hidden');
    document.body.classList.add('adm-locked');
    openDrawer = el;

    /* Focus the first real field, not the close button — you opened
       this to type something. */
    var first = el.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
    if (first) {
      first.focus({ preventScroll: true });
    } else {
      var x = el.querySelector('[data-drawer-close]');
      if (x) x.focus();
    }
  }

  function close(el) {
    el = el || openDrawer;
    if (!el) return;

    el.classList.remove('is-open');
    el.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('adm-locked');
    openDrawer = null;

    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest ? e.target.closest('[data-drawer]') : null;
    if (opener) {
      e.preventDefault();
      open(document.getElementById(opener.getAttribute('data-drawer')));
      return;
    }

    var closer = e.target.closest ? e.target.closest('[data-drawer-close]') : null;
    if (closer) {
      e.preventDefault();
      close(closer.closest('.adm-drawer'));
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || !openDrawer) return;

    /* If the confirm dialog is up, Escape belongs to it — closing the
       drawer out from under an open dialog would leave the dialog
       floating over nothing. */
    var dlg = document.getElementById('admConfirm');
    if (dlg && dlg.open) return;

    close();
  });

  /* Keep Tab inside an open drawer. Without this you tab straight out
     into the page behind, which is still there and still clickable as
     far as the keyboard is concerned. */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab' || !openDrawer) return;

    var focusable = openDrawer.querySelectorAll(
      'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) return;

    var first = focusable[0];
    var last  = focusable[focusable.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  });

  /* A drawer rendered already open — the ?edit= case — still needs
     the body lock, which the click handler never ran for. */
  var preOpen = document.querySelector('.adm-drawer.is-open');
  if (preOpen) {
    openDrawer = preOpen;
    document.body.classList.add('adm-locked');
  }

  /* Exposed so a page can fill a drawer before showing it — the
     destination tiles do this, pouring the clicked place into one
     shared panel rather than the page carrying twenty-four hidden
     copies of the same markup.

     Published rather than left private because the alternative is
     every page re-implementing the scrim, the Escape key and the
     focus trap, and one of those copies eventually getting it
     wrong. */
  window.admDrawer = { open: open, close: close };
})();

/* ===================================================================
   THE SIDEBAR COLLAPSE

   Narrows the panel to its icons and remembers the choice.

   WHY localStorage AND NOT A SESSION VALUE: this is a preference
   about the shape of the window, not about the account. Storing it in
   the session would mean a round trip to the server to change the
   width of a panel, and it would follow the person onto a different
   machine with a different screen, where it is probably the wrong
   answer.

   WHY THE CLASS IS SET BEFORE THIS RUNS: see the small script in
   _header.php. Adding it here, at the foot of the page, would let the
   wide sidebar paint first and then jump — a visible flicker on every
   single page load.
   =================================================================== */
(function () {
  var btn  = document.getElementById('admMini');
  var side = document.querySelector('.adm-side');
  if (!btn || !side) return;

  var KEY = 'admSidebarMini';

  /* The head script put a class on <html> before paint to stop the
     sidebar flickering. Hand it over to the sidebar properly and take
     it off the root, so there is exactly one source of truth from
     here on. */
  if (document.documentElement.classList.contains('adm-pre-mini')) {
    side.classList.add('adm-side--mini');
    document.documentElement.classList.remove('adm-pre-mini');
  }

  function apply(mini, save) {
    side.classList.toggle('adm-side--mini', mini);
    btn.setAttribute('aria-pressed', mini ? 'true' : 'false');
    btn.setAttribute('title', mini ? 'Expand the sidebar' : 'Collapse the sidebar');

    var label = btn.querySelector('span');
    if (label) label.textContent = mini ? 'Expand' : 'Collapse';

    /* Collapsed, the links are icons with no text. title gives each
       one its name back on hover — without it the panel becomes a
       column of shapes you have to learn. */
    document.querySelectorAll('.adm-nav__link, .adm-side__link').forEach(function (a) {
      if (mini) {
        if (!a.dataset.label) {
          a.dataset.label = (a.textContent || '').trim().replace(/\s+/g, ' ');
        }
        a.setAttribute('title', a.dataset.label);
      } else {
        a.removeAttribute('title');
      }
    });

    if (save) {
      try { localStorage.setItem(KEY, mini ? '1' : '0'); } catch (e) { /* private mode */ }
    }
  }

  /* The class may already be on from the head script; read the DOM
     rather than storage so the two can never disagree. */
  apply(side.classList.contains('adm-side--mini'), false);

  btn.addEventListener('click', function () {
    apply(!side.classList.contains('adm-side--mini'), true);
  });
})();
</script>