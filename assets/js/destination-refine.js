/* ===================================================================
   assets/js/destinations-refine.js

   The arrivals on destinations.php. Pairs with section 14 of
   assets/css/destinations-refine.css.

   It marks a short list of blocks, watches them, and adds .is-in the
   first time each one is on screen. That is the whole file.

   WITHOUT IT the page is complete: the hiding classes are added HERE,
   never in the markup, so a browser with no JavaScript — or no
   IntersectionObserver — never receives them and renders everything
   plainly visible. Hiding is opt-in, by the code that can also
   un-hide.

   IT DOES NOT TOUCH .dest-card. GSAP in homepage.js already reveals
   those, and two scripts animating one element's transform is a fight
   nobody wins. What it takes inside a card is the photograph and the
   text block, which GSAP does not know about — so the picture settles
   into its frame while the card fades in, and the two read as one
   movement rather than two effects.
   =================================================================== */
(function () {

  var still = window.matchMedia &&
              window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* someone who asked for less motion gets the page, and none of the
     travel. Nothing below runs, so nothing is ever hidden. */
  if (still || !('IntersectionObserver' in window)) return;

  var grid = document.getElementById('destGrid');

  /* --- what arrives, and as what ------------------------------------
     Three classes, three jobs. The blocks rise, the photographs settle
     in from slightly too big, the words follow a beat behind. */
  var blocks = [];

  function mark(sel, cls, root) {
    (root || document).querySelectorAll(sel).forEach(function (el) {
      el.classList.add(cls);
      blocks.push(el);
    });
  }

  mark('.dest-atlas', 'dr-in');
  mark('.dest-filters', 'dr-in');
  mark('.dest-result', 'dr-in');
  mark('.dest-more', 'dr-in');
  mark('.dest-outro__inner', 'dr-in');

  if (grid) {
    mark('.dest-card__media', 'dr-shot', grid);
    mark('.dest-card__body', 'dr-words', grid);
  }

  if (!blocks.length) return;

  /* --- the stagger ---------------------------------------------------
     Cards arrive in the order they sit in the row, 55ms apart, capped
     at six steps. Uncapped, the last card in a five-column row on a
     wide screen would wait a third of a second after the first — long
     enough to read as the page being slow rather than as a sequence.

     The delay is written inline and REMOVED once the element is in, so
     a card that scrolls out and back does not sit waiting through the
     stagger a second time. */
  var order = 0;

  function delayFor(el) {
    if (!grid || !grid.contains(el)) return 0;
    var step = order++ % 6;
    return step * 55;
  }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;

      var el = entry.target;
      var d  = delayFor(el);

      if (d) el.style.transitionDelay = d + 'ms';
      el.classList.add('is-in');

      /* it has arrived; it never needs watching again */
      io.unobserve(el);

      window.setTimeout(function () {
        el.style.transitionDelay = '';
      }, d + 1000);
    });
  }, {
    /* -12% at the bottom: the block starts moving once it is properly
       on screen rather than the instant its first pixel is, which is
       what makes the arrival visible instead of already finished. */
    rootMargin: '0px 0px -12% 0px',
    threshold: 0.05
  });

  blocks.forEach(function (el) { io.observe(el); });

  /* --- the safety net -------------------------------------------------
     If anything is still hidden after four seconds — an observer that
     never fired, a block inside a container that never scrolled — it is
     shown regardless. A reveal that fails should cost an animation, not
     the content. */
  window.setTimeout(function () {
    blocks.forEach(function (el) { el.classList.add('is-in'); });
  }, 4000);

})();