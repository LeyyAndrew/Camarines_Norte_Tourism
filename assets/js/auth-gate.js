/* ===================================================================
   assets/auth-gate.js

   Nav stays open. Almost everything else asks for a sign-in.

   Home / About / Destinations / Gallery stay clickable, so a first
   time visitor can look around. Click anything INSIDE a page —
   links AND buttons — and the modal opens instead.

   ---------------------------------------------------------------
   THE EXEMPTION LIST IS THE IMPORTANT PART OF THIS FILE

   "Gate every button" taken literally breaks the page rather than
   protecting it. The spotlight carousel arrows, its thumbnails and
   its dots, and the hex tiles in "What you'll find here" are all
   buttons — but they are not content, they are how you LOOK at the
   content. A visitor who cannot advance the carousel does not sign
   up; they leave. Every selector in FREE below was read out of
   homepage.js, where these same controls are bound:

     .carousel-nav   prev / next arrows      (homepage.js line ~653)
     .thumb          carousel thumbnails     (~549)
     .dot            carousel dots           (~556)
     .hexplore__comb the hex tile grid       (~1305)

   IF YOU ADD A NEW INTERFACE CONTROL, add it here or tag it in the
   markup with data-auth-free. If you want something gated that is
   not caught automatically, tag it data-auth-gate.

   TO GATE ABSOLUTELY EVERYTHING, empty the FREE array. The page will
   feel broken to signed-out visitors. It is your call, not mine.
   ---------------------------------------------------------------

   HOW IT DECIDES, in order:

     1. [data-auth-gate] is gated wherever it sits — including the
        sign-in icon in the nav.
     2. Anything in .nav, .footer or .auth-modal is left alone. Menus
        and the modal's own buttons must keep working or a signed-out
        visitor is trapped with no way to navigate.
     3. Anything matching FREE, or carrying data-auth-free, is left
        alone.
     4. Every other <a href> or <button> in page content is gated.

   AFTER SIGNING IN the visitor lands back on whatever they clicked.
   The destination goes into a hidden "next" field on both modal
   forms; the two process files in auth/ read and validate it.

   ON THE OLD GATE IN homepage.js: it is still there, near the bottom,
   attached to each [data-auth-gate] element individually. It never
   fires now — this file listens in the capture phase and stops the
   event before it reaches those handlers. Harmless either way, and
   left alone so homepage.js stays as you had it.

   LOAD ORDER: after homepage.js, which sets window.isLoggedIn.
   =================================================================== */

(function () {
  var modal = document.getElementById('authModal');
  var box   = document.getElementById('authBox');

  if (!modal) return;

  /* interface controls — see the long note above before editing */
  var FREE = [
    '.carousel-nav',      /* spotlight prev / next            */
    '#prevBtn', '#nextBtn',
    '.thumb',             /* carousel thumbnails              */
    '.dot',               /* carousel dots                    */
    '.hexplore__comb',    /* the hex tiles                    */
    '[data-auth-free]',   /* your own opt-out                 */
    '[data-modal-close]',
    '[data-toggle-pw]',
    '[data-auth-tab]'
  ].join(', ');

  /* The === true is not fussiness. If footer.php's inline script ever
     fails to render, this is undefined, and `!undefined` would gate a
     signed-in user out of their own site. Undefined has to mean
     "treat as signed out" — worst case someone sees a sign-in box
     they did not need, which is the survivable direction. */
  function signedIn() {
    return window.isLoggedIn === true;
  }

  /* ---------------------------------------------------------------
     Where to send them back to.

     Reduced to a bare filename plus query and hash —
     "destinations.php", "homepage.php#contact". The process files
     live in auth/, so they prefix "../" and land in the project root.

     Anything off-site returns null and is not remembered. A "next"
     that can point anywhere is an open redirect: someone circulates
     a link to your login page carrying next=another-site, the victim
     signs in on the REAL site, and gets forwarded on at the exact
     moment they have stopped being suspicious. Checked here for
     tidiness and again in PHP, which is the check that counts.
     --------------------------------------------------------------- */
  function nextFrom(el) {
    var raw = el.getAttribute ? el.getAttribute('href') : null;

    /* No href at all means this was a BUTTON, not a link to
       somewhere — the sign-in icon in the nav is the case that
       matters. There is no page to return to, so remember nothing
       and let login_process.php decide where they land: admins to
       admin/index.php, everyone else to dashboard.php.

       Returning the current page here instead, as this used to, made
       "next" always point at the homepage, and a next always beats
       the default — which is why an admin signing in from the nav
       ended up back on the homepage. */
    if (!raw) return null;

    var url;
    try { url = new URL(raw, location.href); } catch (e) { return null; }

    if (url.origin !== location.origin) return null;

    var file = url.pathname.split('/').pop();
    if (!file) return null;

    return file + url.search + url.hash;
  }

  /* Written into both forms as a hidden field, created on the fly so
     footer.php needs no editing. */
  function rememberNext(next) {
    if (!next) return;

    modal.querySelectorAll('form').forEach(function (form) {
      var field = form.querySelector('input[name="next"]');

      if (!field) {
        field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'next';
        form.appendChild(field);
      }

      field.value = next;
    });
  }

  function openModal(next) {
    if (box) box.dataset.mode = 'signin';

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    rememberNext(next);
  }

  /* ---------------------------------------------------------------
     One listener on the document, in the CAPTURE phase.

     Capture runs on the way DOWN to the clicked element, before any
     handler attached to the element itself. The link's own handler
     never fires, the browser never navigates, and homepage.js's
     document-level listeners further down never see the event.
     Listening in the normal bubble phase would let all of those run
     first, and the page would already be leaving as the modal opened.
     --------------------------------------------------------------- */
  /* ---------------------------------------------------------------
     REOPEN THE MODAL AFTER A FAILED ATTEMPT

     login_process.php redirects back here with ?error=CODE, and
     footer.php prints the matching message inside the modal. But the
     modal starts closed, so without this the visitor lands on the
     homepage and sees nothing at all — the message is sitting in the
     markup, invisible, and it looks like the button did nothing.

     replaceState then strips ?error= from the address bar. Otherwise
     the message survives every refresh and every bookmark of that
     URL, long after it stopped being true.
     --------------------------------------------------------------- */
  (function reopenOnError() {
    if (!/[?&]error=/.test(location.search)) return;
    if (!modal.querySelector('.auth-error')) return;

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    if (window.history && history.replaceState) {
      var url = new URL(location.href);
      url.searchParams.delete('error');
      history.replaceState({}, '', url.pathname + url.search + url.hash);
    }
  })();

  document.addEventListener('click', function (e) {
    var target = e.target;
    if (!target || !target.closest) return;

    /* 1 — explicitly gated, wherever it sits */
    var gated = target.closest('[data-auth-gate]');

    /* ---------------------------------------------------------------
       SIGNED IN — nothing is gated, but we cannot simply return.

       THE BUG THIS FIXES. Read the note at the top of this file about
       the old gate in homepage.js: "it never fires now — this file
       listens in the capture phase and stops the event before it
       reaches those handlers."

       That was only ever true for a SIGNED-OUT visitor. The
       stopPropagation that suppresses those handlers sits at the
       bottom of this function, and a bare `return` here jumped over
       it — so for a signed-IN visitor the event carried on down to
       the element, homepage.js's old per-element handler got it, and
       opened the sign-in modal. Which is why the modal appeared only
       AFTER signing in: logging in is what stopped this file from
       suppressing the stale one.

       So: for [data-auth-gate] elements, stop the event here too.

       stopPropagation but NOT preventDefault — the two do different
       jobs. stopPropagation keeps the event away from the old
       handler; preventDefault would also stop the browser following
       the link, and a signed-in visitor clicking a real link should
       go where it points.

       THIS IS A GUARD, NOT THE REPAIR. The actual repair is to delete
       the [data-auth-gate] block near the bottom of homepage.js,
       which this file has fully replaced. Two implementations of one
       rule is what caused this in the first place. Once that block is
       gone, these four lines do nothing and can go too.
       --------------------------------------------------------------- */
    if (signedIn()) {
      if (gated) e.stopPropagation();
      return;
    }

    if (!gated) {
      /* 2 — chrome is always free.

         .nav-drawer added here — it does NOT match .nav. Class
         matching is exact-token, and the drawer's root element is
         <div class="nav-drawer">, a different class from the desktop
         <nav class="nav">, not a variant of it. They are siblings in
         the markup (the drawer is "a copy of the same links, not a
         moved copy" — see the top of this file), so closest('.nav')
         from inside the drawer was never going to find it by walking
         up, either. Without this, every drawer control that isn't in
         FREE — the close button, Home, About, Gallery — fell through
         to step 4 and got gated like ordinary page content. */
      if (target.closest('.nav, .nav-drawer, .footer, .auth-modal')) return;

      /* 3 — interface controls are free */
      if (target.closest(FREE)) return;

      /* 4 — content links and buttons */
      var el = target.closest('a[href], button');
      if (!el) return;

      if (el.tagName === 'A') {
        var href = el.getAttribute('href');

        /* not pages to come back to — let them through untouched */
        if (/^(mailto:|tel:|javascript:)/i.test(href)) return;
        if (el.hasAttribute('download')) return;
        if (el.target === '_blank') return;
      }

      gated = el;
    }

    e.preventDefault();
    e.stopPropagation();

    openModal(nextFrom(gated));
  }, true);
})();