/* ===================================================================
   assets/js/nav.js

   The header, and only the header. Loaded with defer from
   includes/header.php.

   WHAT THIS FILE IS NOT RESPONSIBLE FOR
   The desktop mega-menu. That opens on :hover and :focus-within in
   nav.css with no JavaScript at all, which is why it still works if
   this file 404s, fails to parse, or is blocked. Four things live
   here and nothing else:

     1. the phone drawer
     2. the Destinations accordion inside it
     3. .scrolled on the nav
     4. .is-menu-open on the nav, purely as a fallback for browsers
        without :has()

   Everything below is written to be harmless if the element it wants
   is not on the page — every lookup is guarded. Dropping this file
   into a site whose header has not been updated yet does nothing
   rather than throwing on load and taking the rest of your scripts
   down with it.
   =================================================================== */
(function () {
  'use strict';

  var nav     = document.getElementById('mainNav');
  var burger  = document.getElementById('navBurger');
  var drawer  = document.getElementById('navDrawer');

  /* -----------------------------------------------------------------
     1. SCROLLED STATE

     If you already have a script adding .scrolled, this one agreeing
     with it costs nothing — classList.add on a class already present
     is a no-op, and both scripts compute the same answer from the same
     scroll position. Delete whichever copy you prefer; do not delete
     both.

     rAF-throttled: scroll fires far more often than the screen
     repaints, and writing a class on every one of those is how a
     header ends up janky on a mid-range phone.
     ----------------------------------------------------------------- */
  if (nav) {
    var ticking = false;

    var applyScrolled = function () {
      nav.classList.toggle('scrolled', window.scrollY > 24);
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(applyScrolled);
      }
    }, { passive: true });

    applyScrolled();   /* a reload halfway down a page must not start
                          the header in its transparent state */
  }

  /* -----------------------------------------------------------------
     2. .is-menu-open — the :has() fallback

     nav.css uses :has() to make the bar go solid behind an open
     mega-menu. Every current browser supports it. This mirrors the
     same state onto a plain class so anything older gets the solid
     bar too, instead of a white panel hanging off a transparent strip.

     Pointer events only. A keyboard user reaching the menu triggers
     :focus-within, which needs no help.
     ----------------------------------------------------------------- */
  var megaItem = nav && nav.querySelector('.nav__item--mega');

  if (nav && megaItem) {
    megaItem.addEventListener('mouseenter', function () {
      nav.classList.add('is-menu-open');
    });

    megaItem.addEventListener('mouseleave', function () {
      nav.classList.remove('is-menu-open');
    });
  }

  /* -----------------------------------------------------------------
     3. THE DRAWER
     ----------------------------------------------------------------- */
  if (burger && drawer) {
    var panel = drawer.querySelector('.nav-drawer__panel');

    var openDrawer = function () {
      /* hidden comes off first. The browser has to have laid the panel
         out before .is-open can animate it — set both in the same tick
         and there is no "before" for the transform to travel from, so
         the drawer appears instead of sliding. rAF is the wait. */
      drawer.hidden = false;

      window.requestAnimationFrame(function () {
        drawer.classList.add('is-open');
      });

      document.body.classList.add('nav-open');
      burger.setAttribute('aria-expanded', 'true');
      burger.setAttribute('aria-label', 'Close menu');

      var first = drawer.querySelector('.nav-drawer__close');
      if (first) { first.focus(); }
    };

    var closeDrawer = function (returnFocus) {
      drawer.classList.remove('is-open');
      document.body.classList.remove('nav-open');
      burger.setAttribute('aria-expanded', 'false');
      burger.setAttribute('aria-label', 'Open menu');

      /* hidden goes back on only after the slide-out has finished,
         otherwise the panel vanishes mid-animation. transitionend on
         the panel is the honest signal; the timer is the backstop for
         when the transition never runs — reduced-motion, a background
         tab, transitions disabled entirely. */
      var restore = function () {
        if (!drawer.classList.contains('is-open')) { drawer.hidden = true; }
      };

      if (panel) { panel.addEventListener('transitionend', restore, { once: true }); }
      window.setTimeout(restore, 450);

      /* Focus must come back to the button that opened it. Skip it when
         the drawer is closing because a link was followed — moving
         focus during a navigation fights the browser. */
      if (returnFocus !== false) { burger.focus(); }
    };

    burger.addEventListener('click', function () {
      if (drawer.hidden) { openDrawer(); } else { closeDrawer(); }
    });

    /* the X and the scrim both carry data-drawer-close */
    drawer.querySelectorAll('[data-drawer-close]').forEach(function (el) {
      el.addEventListener('click', function () { closeDrawer(); });
    });

    /* Following a link closes the drawer. It would close on its own when
       the next page paints, but on a slow connection you would otherwise
       stare at an open menu for a second wondering whether the tap
       registered. data-auth-gate is excluded — that button opens the
       sign-in modal on this same page, and the modal handles itself. */
    drawer.querySelectorAll('a[href]').forEach(function (link) {
      link.addEventListener('click', function () { closeDrawer(false); });
    });

    var gate = drawer.querySelector('[data-auth-gate]');
    if (gate) { gate.addEventListener('click', function () { closeDrawer(false); }); }

    /* Escape closes it. Expected of anything with aria-modal on it. */
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !drawer.hidden) { closeDrawer(); }
    });

    /* Rotating a phone into landscape, or dragging a desktop window
       narrow and back, can leave the drawer open at a width where the
       full nav is showing — two menus at once, and a body that can no
       longer scroll. 1080px is the same breakpoint nav.css uses. */
    var wide = window.matchMedia('(min-width:1080px)');

    var onWide = function (e) {
      if (e.matches && !drawer.hidden) { closeDrawer(false); }
    };

    if (wide.addEventListener) {
      wide.addEventListener('change', onWide);
    } else if (wide.addListener) {
      wide.addListener(onWide);      /* Safari before 14 */
    }
  }

  /* -----------------------------------------------------------------
     4. THE ACCORDION

     Written as a loop over [data-acc] rather than one hard-coded
     block, so a second collapsible section in the drawer later needs
     no changes here — give it the same three attributes and it works.
     ----------------------------------------------------------------- */
  document.querySelectorAll('[data-acc]').forEach(function (acc) {
    var btn  = acc.querySelector('[data-acc-btn]');
    var body = acc.querySelector('[data-acc-body]');

    if (!btn || !body) { return; }

    btn.addEventListener('click', function () {
      var open = acc.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

})();