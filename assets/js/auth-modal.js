/* ===================================================================
   assets/js/auth-modal.js

   Every button on the sign-in card, and the function behind it.

   Load it with defer, next to nav.js in includes/footer.php:

     <script src="<?= htmlspecialchars(assetUrl('assets/js/auth-modal.js')) ?>" defer></script>

   REPLACES assets/js/auth-modal.js. Overwrite that file with this one.
   The <script> tag in footer.php already points here, so there is
   nothing to add.

   homepage.js and auth-gate.js keep loading and keep working. Between
   them they open the card, close it, and handle Escape; everything
   they do is also in this file now, but nothing breaks while both are
   running — see the MutationObserver near the bottom for the one
   collision that had to be guarded. When you want to tidy up, delete
   auth-gate.js and the open/close block in homepage.js.

   WHAT YOUR OLD FILE DID THAT THIS ONE STILL DOES
     - setMode() is switchMode(), and still syncs [data-auth-tab].
     - The eye toggle reads the same [data-toggle-pw] hook.
     - ?error= / ?registered= still reopens the card and is still
       stripped from the address bar with replaceState.
     - .auth-tabs / .auth-tabs__btn are gone from the markup, so the
       loop that synced them is gone too. base.css already hides both
       classes; nothing to clean up.

   THE BUTTONS, AND WHAT RUNS

     [data-auth-gate]      openModal()        the nav's person icon, the drawer rows
     [data-modal-close]    closeModal()       the ×, the backdrop
     Esc                   closeModal()
     [data-auth-tab]       switchMode()       create account / sign in / forgot password
     [data-toggle-pw]      togglePassword()   the eye, on all four password fields
     [data-slide-to]       showSlide()        the dots on the photograph
     [data-guest-browse]   browseAsGuest()    keep browsing without an account
     [data-remember]       rememberEmail()    keep me signed in
     form submit           submitForm()       all three forms

   NOTHING HERE IS SECURITY. Every check in this file is a courtesy to
   the visitor — it catches a typo before a round trip to the server.
   auth/login_process.php and auth/register_process.php must validate
   everything again, because anyone can turn JavaScript off.
   =================================================================== */
(function () {
  'use strict';

  var modal = document.getElementById('authModal');
  if (!modal) { return; }                     /* page without the card: nothing to wire */

  var box       = document.getElementById('authBox');
  var panel     = modal.querySelector('.auth-panel');
  var errorBox  = modal.querySelector('[data-auth-error]');
  var errorText = modal.querySelector('[data-auth-error-text]');
  var noticeBox = modal.querySelector('[data-auth-notice]');

  var lastFocused = null;                     /* who to give the focus back to */
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ===================================================================
     OPEN AND CLOSE
     =================================================================== */

  /* mode is 'signin', 'register' or 'reset'. Anything else falls back
     to signin rather than opening a card with all three panes hidden. */
  function openModal(mode) {
    if (modal.classList.contains('is-open')) { return; }

    lastFocused = document.activeElement;

    switchMode(mode || 'signin', { focus: false });

    modal.removeAttribute('inert');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');

    startSlideshow();

    /* One frame's wait. Focusing an element inside something that is
       still opacity:0 makes some browsers jump the scroll position to
       it, and you see the page lurch behind the card. */
    requestAnimationFrame(function () {
      var first = currentPane() && currentPane().querySelector('input:not([type="hidden"]):not([tabindex="-1"])');
      if (first) { first.focus(); }
    });
  }

  function closeModal() {
    if (!modal.classList.contains('is-open')) { return; }

    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');

    stopSlideshow();

    /* inert and aria-hidden go back on AFTER the fade, not during it.
       Both remove the card from the tab order, and doing that while
       the focus is still inside drops the focus onto <body> — the next
       Tab then starts from the top of the page. */
    window.setTimeout(function () {
      if (modal.classList.contains('is-open')) { return; }   /* re-opened mid-fade */
      modal.setAttribute('inert', '');
      modal.setAttribute('aria-hidden', 'true');
    }, reduceMotion ? 0 : 300);

    /* Back to the button that opened it. Without this the visitor is
       returned to the top of the document and has to find their place
       again. */
    if (lastFocused && document.contains(lastFocused)) {
      lastFocused.focus();
    }

    clearMessages();
  }

  /* ===================================================================
     THE THREE PANES
     =================================================================== */

  function currentPane() {
    var mode = box.getAttribute('data-mode') || 'signin';
    return modal.querySelector('.auth-pane--' + mode);
  }

  function switchMode(mode, opts) {
    var valid = ['signin', 'register', 'reset'];
    if (valid.indexOf(mode) === -1) { mode = 'signin'; }

    box.setAttribute('data-mode', mode);
    clearMessages();

    /* The panel scrolls on its own. Switching from a long register
       pane to a short sign-in one otherwise leaves it scrolled halfway
       down a form that is now three inches tall. */
    if (panel) { panel.scrollTop = 0; }

    /* Carry the address across. Someone who typed their email, found
       out they have no account, and clicked "create an account" should
       not have to type it again. */
    carryEmail(mode);

    if (!opts || opts.focus !== false) {
      var pane = currentPane();
      var first = pane && pane.querySelector('input:not([type="hidden"]):not([tabindex="-1"])');
      if (first) { first.focus(); }
    }
  }

  function carryEmail(toMode) {
    var typed = '';
    modal.querySelectorAll('[data-auth-email]').forEach(function (input) {
      if (input.value.trim() && input.offsetParent !== null) { typed = input.value.trim(); }
    });

    /* offsetParent is null for the panes that just became hidden, so
       read before the switch or you get nothing. This runs after, so
       fall back to any filled field on the card. */
    if (!typed) {
      modal.querySelectorAll('[data-auth-email]').forEach(function (input) {
        if (input.value.trim()) { typed = input.value.trim(); }
      });
    }

    if (!typed) { return; }

    var pane = modal.querySelector('.auth-pane--' + toMode);
    var target = pane && pane.querySelector('[data-auth-email]');
    if (target && !target.value.trim()) { target.value = typed; }
  }

  /* ===================================================================
     MESSAGES
     =================================================================== */

  function showError(message) {
    if (!errorBox || !errorText) { return; }
    errorText.textContent = message;
    errorBox.hidden = false;
    if (noticeBox) { noticeBox.hidden = true; }
    if (panel) { panel.scrollTop = 0; }
  }

  function clearMessages() {
    if (errorBox && !errorBox.hasAttribute('data-server')) { errorBox.hidden = true; }
    if (noticeBox) { noticeBox.hidden = true; }
    modal.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
  }

  /* The banner rendered by PHP is a server message and should survive
     a pane switch — it is the reason the card is open. Mark it once,
     on load, so clearMessages() leaves it alone. */
  if (errorBox && !errorBox.hidden) { errorBox.setAttribute('data-server', ''); }

  function markInvalid(input, message) {
    input.classList.add('is-invalid');
    showError(message);
    input.focus();
  }

  /* ===================================================================
     THE PASSWORD EYE
     =================================================================== */

  function togglePassword(btn) {
    var input = btn.closest('.auth-field__wrap').querySelector('input');
    if (!input) { return; }

    var showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';

    btn.classList.toggle('is-visible', !showing);
    btn.setAttribute('aria-pressed', String(!showing));
    btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');

    /* Changing type moves the caret to the front in Safari. Put it
       back at the end so typing carries on where it left off. */
    var end = input.value.length;
    input.focus();
    try { input.setSelectionRange(end, end); } catch (e) { /* not all inputs allow it */ }
  }

  /* ===================================================================
     THE PHOTOGRAPH
     =================================================================== */

  var slides = Array.prototype.slice.call(modal.querySelectorAll('[data-auth-slide]'));
  var dots   = Array.prototype.slice.call(modal.querySelectorAll('[data-slide-to]'));
  var slideIndex = 0;
  var slideTimer = null;

  /* The plate is rewritten rather than cross-faded, so the text for
     each photo travels on the photo's own element as data-place,
     data-coords and data-note. PHP writes them out of $authSlides —
     add a row to that array and the caption comes with it, with no
     edit here. */
  var plate = {
    place:   modal.querySelector('[data-slide-place]'),
    eyebrow: modal.querySelector('[data-slide-eyebrow]'),
    coords:  modal.querySelector('[data-slide-coords]'),
    note:    modal.querySelector('[data-slide-note]')
  };

  function showSlide(index) {
    if (!slides.length) { return; }
    slideIndex = (index + slides.length) % slides.length;

    slides.forEach(function (s, i) { s.classList.toggle('is-active', i === slideIndex); });

    dots.forEach(function (d, i) {
      var on = i === slideIndex;
      d.classList.toggle('is-active', on);
      if (on) { d.setAttribute('aria-current', 'true'); }
      else    { d.removeAttribute('aria-current'); }
    });

    /* A missing attribute leaves the previous text alone rather than
       blanking the line — a half-filled $authSlides row should look
       incomplete, not broken. */
    var el = slides[slideIndex];
    if (plate.place   && el.dataset.place)   { plate.place.textContent   = el.dataset.place; }
    if (plate.eyebrow && el.dataset.eyebrow) { plate.eyebrow.textContent = el.dataset.eyebrow; }
    if (plate.coords  && el.dataset.coords)  { plate.coords.textContent  = el.dataset.coords; }
    if (plate.note    && el.dataset.note)    { plate.note.textContent    = el.dataset.note; }
  }

  function startSlideshow() {
    stopSlideshow();
    if (slides.length < 2 || reduceMotion) { return; }
    slideTimer = window.setInterval(function () { showSlide(slideIndex + 1); }, 7000);
  }

  function stopSlideshow() {
    if (slideTimer) { window.clearInterval(slideTimer); slideTimer = null; }
  }

  /* A dot that is clicked and then overridden by the timer two seconds
     later feels broken. Any manual choice restarts the clock. */
  function selectSlide(index) {
    showSlide(index);
    startSlideshow();
  }

  /* ===================================================================
     THE OTHER WAYS IN

     Google, Facebook and the email-link button were here. They are
     gone from footer.php, and startOAuth()/sendMagicLink() went with
     them — a handler for a button that does not exist is a handler
     nobody will ever read again.

     IF YOU ADD THEM BACK, the pieces you need are auth/oauth.php,
     auth/oauth_callback.php and auth/oauth_config.php, plus a
     data-ready attribute on each button. Ask and I will put the
     handlers back.
     =================================================================== */

  /* No caller left in this file — sendMagicLink() was the only one.
     Kept because footer.php still renders the notice banner (the
     "reset link is on its way" message after ?sent=1), and anything
     you add later that needs to say something reassuring should write
     through here rather than touching the DOM directly. */
  function showNotice(message) {
    if (!noticeBox) { return; }
    var text = noticeBox.querySelector('[data-auth-notice-text]');
    if (text) { text.textContent = message; }
    noticeBox.hidden = false;
    if (errorBox) { errorBox.hidden = true; }
  }

  /* Close the card and leave them on the page they were reading. The
     nav's search icon will still ask them to sign in — that gate is
     the server's, not this file's. */
  function browseAsGuest() {
    closeModal();
  }

  /* ===================================================================
     KEEP ME SIGNED IN
     The checkbox itself is handled by PHP. This only remembers the
     ADDRESS, so the next visit starts with the email filled and the
     cursor in the password field. Never the password.
     =================================================================== */

  var STORE_KEY = 'cn_auth_email';

  function rememberEmail(checkbox) {
    var pane  = checkbox.closest('.auth-pane');
    var input = pane.querySelector('[data-auth-email]');

    try {
      if (checkbox.checked && input && input.value.trim()) {
        window.localStorage.setItem(STORE_KEY, input.value.trim());
      } else {
        window.localStorage.removeItem(STORE_KEY);
      }
    } catch (e) {
      /* Private mode, or storage full, or a browser that refuses.
         Nothing here is load-bearing, so fail quietly. */
    }
  }

  function restoreEmail() {
    var stored;
    try { stored = window.localStorage.getItem(STORE_KEY); } catch (e) { return; }
    if (!stored) { return; }

    var input = modal.querySelector('.auth-pane--signin [data-auth-email]');
    var check = modal.querySelector('[data-remember]');

    if (input && !input.value.trim()) { input.value = stored; }
    if (check) { check.checked = true; }
  }

  /* ===================================================================
     PASSWORD STRENGTH
     Length first, because it is the only factor that matters much, and
     variety second. Four levels, named in words as well as colour.
     =================================================================== */

  function scorePassword(pw) {
    if (!pw) { return 0; }
    var score = 0;
    if (pw.length >= 8)  { score++; }
    if (pw.length >= 12) { score++; }
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) { score++; }
    if (/\d/.test(pw) && /[^A-Za-z0-9]/.test(pw)) { score++; }

    /* Eight identical characters is not a password however long it is. */
    if (/^(.)\1+$/.test(pw)) { score = 1; }

    return Math.min(score, 4);
  }

  function updateStrength(input) {
    var wrap = input.closest('.auth-field').querySelector('[data-strength]');
    if (!wrap) { return; }

    var text = wrap.querySelector('[data-strength-text]');
    var pw = input.value;

    wrap.hidden = pw.length === 0;

    var level = scorePassword(pw);
    wrap.setAttribute('data-level', String(level));

    var words = ['', 'Too easy to guess', 'Getting there', 'Good', 'Strong'];
    if (text) { text.textContent = pw.length < 8 ? 'At least 8 characters' : words[level]; }
  }

  /* ===================================================================
     CAPS LOCK
     =================================================================== */

  function checkCapsLock(event) {
    var field = event.target.closest('.auth-field');
    var warn = field && field.querySelector('[data-caps-warning]');
    if (!warn || typeof event.getModifierState !== 'function') { return; }
    warn.hidden = !event.getModifierState('CapsLock');
  }

  /* ===================================================================
     SUBMITTING
     The forms post normally — no fetch, no JSON. This only checks the
     obvious before the round trip and stops a second click.
     =================================================================== */

  function submitForm(form, event) {
    var kind = form.getAttribute('data-auth-form');
    var btn = form.querySelector('[data-auth-submit]');

    clearMessages();

    /* auth/forgot_process.php does not exist yet, so submitting the
       reset form would give a 404 — which reads as a broken site
       rather than an unfinished feature. Say so instead. Set
       data-ready="1" on the form once the file is there. */
    if (form.getAttribute('data-ready') === '0') {
      event.preventDefault();
      showError('Password resets are not switched on yet. Add auth/forgot_process.php, or email tourism@camarinesnorte.gov.ph and the office can reset it for you.');
      return;
    }

    var problem = validate(form, kind);
    if (problem) {
      event.preventDefault();
      markInvalid(problem.field, problem.message);
      return;
    }

    setBusy(btn, true);

    /* Belt and braces: if the page is still here in eight seconds the
       navigation failed, and a permanently spinning button is worse
       than a clickable one. */
    window.setTimeout(function () { setBusy(btn, false); }, 8000);
  }

  function validate(form, kind) {
    var email = form.querySelector('[data-auth-email]');

    if (email && !isEmail(email.value.trim())) {
      return { field: email, message: 'That does not look like an email address. Check for a typo.' };
    }

    if (kind === 'reset') { return null; }

    var pw = form.querySelector('input[type="password"], input[data-strength-input]');

    if (kind === 'signin') {
      if (!pw.value) {
        return { field: pw, message: 'Type your password to sign in.' };
      }
      return null;
    }

    /* register */
    var first = form.querySelector('[data-first]');
    var last  = form.querySelector('[data-last]');

    if (first && !first.value.trim()) {
      return { field: first, message: 'We need a first name to greet you by.' };
    }
    if (last && !last.value.trim()) {
      return { field: last, message: 'Add a last name to finish the account.' };
    }

    var pass = form.querySelector('[data-strength-input]');
    if (pass && pass.value.length < 8) {
      return { field: pass, message: 'Passwords need at least 8 characters.' };
    }

    var confirm = form.querySelector('[data-confirm-input]');
    if (confirm && confirm.value !== pass.value) {
      return { field: confirm, message: 'The two passwords do not match.' };
    }

    /* name="terms", which is what register_process.php reads. */
    var terms = form.querySelector('[data-terms]');
    if (terms && !terms.checked) {
      return { field: terms, message: 'Tick the box to accept the terms before creating an account.' };
    }

    return null;
  }

  function setBusy(btn, busy) {
    if (!btn) { return; }
    btn.classList.toggle('is-busy', busy);
    btn.disabled = busy;
  }

  /* ===================================================================
     SMALL HELPERS
     =================================================================== */

  /* Deliberately loose. A regex cannot tell a real address from a
     plausible one — only the confirmation email can. This catches the
     missing @ and the trailing comma, and lets everything else through
     rather than rejecting somebody's perfectly valid address. */
  function isEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  }

  function csrfToken() {
    var field = modal.querySelector('input[name="csrf"]');
    return field ? field.value : '';
  }

  /* ===================================================================
     WIRING

     One click listener on the document rather than a listener per
     button. The drawer and the nav are re-rendered on some pages, and
     a button that appears after this file has run would otherwise be
     dead — delegation means it works whenever it arrives.
     =================================================================== */

  document.addEventListener('click', function (e) {
    var el;

    /* --- open: the nav person icon, the drawer rows, anything else
           you put data-auth-gate on --- */
    el = e.target.closest('[data-auth-gate]');
    if (el) {
      e.preventDefault();
      openModal(el.getAttribute('data-auth-mode') || 'signin');
      return;
    }

    /* Everything below only applies inside the card. */
    if (!e.target.closest('#authModal')) { return; }

    /* --- close: the × and the backdrop --- */
    if (e.target.closest('[data-modal-close]')) {
      e.preventDefault();
      closeModal();
      return;
    }

    /* --- switch pane --- */
    el = e.target.closest('[data-auth-tab]');
    if (el) {
      e.preventDefault();
      switchMode(el.getAttribute('data-auth-tab'));
      return;
    }

    /* --- the eye --- */
    el = e.target.closest('[data-toggle-pw]');
    if (el) { togglePassword(el); return; }

    /* --- the dots --- */
    el = e.target.closest('[data-slide-to]');
    if (el) { selectSlide(parseInt(el.getAttribute('data-slide-to'), 10)); return; }

    /* --- keep browsing --- */
    if (e.target.closest('[data-guest-browse]')) { browseAsGuest(); return; }
  });

  document.addEventListener('change', function (e) {
    if (e.target.matches('[data-remember]')) { rememberEmail(e.target); }
  });

  document.addEventListener('input', function (e) {
    if (e.target.matches('[data-strength-input]')) { updateStrength(e.target); }

    /* Clear the red ring as soon as they start fixing it. A field that
       stays red while you correct it is arguing with you. */
    if (e.target.classList.contains('is-invalid')) {
      e.target.classList.remove('is-invalid');
    }
  });

  document.addEventListener('keyup', function (e) {
    if (e.target.matches('.auth-field--pw input')) { checkCapsLock(e); }
  });

  modal.querySelectorAll('[data-auth-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) { submitForm(form, e); });
  });

  /* --- Esc, and the focus trap ---
     Tab from the last control has to come back to the first, or the
     next Tab lands on the page behind the card, which is still there
     and still scrollable. */
  document.addEventListener('keydown', function (e) {
    if (!modal.classList.contains('is-open')) { return; }

    if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }

    if (e.key !== 'Tab') { return; }

    var focusables = modal.querySelectorAll(
      'button:not([disabled]), a[href], input:not([type="hidden"]):not([disabled]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])'
    );
    var visible = Array.prototype.filter.call(focusables, function (el) {
      return el.offsetParent !== null;        /* skips the two hidden panes */
    });
    if (!visible.length) { return; }

    var first = visible[0];
    var last  = visible[visible.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  });

  /* ===================================================================
     SHARING THE CARD WITH homepage.js

     homepage.js opens the modal by putting .is-open on it directly,
     without going through openModal(). That is fine as far as it goes,
     but this file also puts `inert` on the card while it is closed —
     and an inert element ignores every click and every Tab. A card
     opened by the other file would appear on screen and then refuse to
     be typed into, which is the worst failure mode available.

     So: watch the class. If .is-open arrives and we did not put it
     there, finish the job — take the inert off, unlock the focus, and
     start the photographs. The same in reverse on close.

     THE REAL FIX IS TO DELETE THE OTHER COPY. Take the open/close/Esc
     block out of homepage.js and out of auth-gate.js and let this file
     own it — two scripts opening one dialog is two sets of focus
     calls fighting over the same field. This observer is here so the
     site is not broken in the meantime, not so you can leave it.
     =================================================================== */
  new MutationObserver(function () {
    var open = modal.classList.contains('is-open');

    if (open && modal.hasAttribute('inert')) {
      modal.removeAttribute('inert');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
      startSlideshow();

      var pane  = currentPane();
      var first = pane && pane.querySelector('input:not([type="hidden"]):not([tabindex="-1"])');
      if (first) { window.setTimeout(function () { first.focus(); }, 150); }
    }

    if (!open && !modal.hasAttribute('inert')) {
      stopSlideshow();
      modal.setAttribute('inert', '');
      modal.setAttribute('aria-hidden', 'true');
    }
  }).observe(modal, { attributes: true, attributeFilter: ['class'] });

  /* ===================================================================
     REOPEN AFTER A REDIRECT

     The process files in auth/ never print. They redirect back to the
     page the form came from carrying ?error=CODE or ?registered=1, and
     auth-modal.php has already turned that into the banner above.

     But a banner inside a closed card is a banner nobody reads. The
     visitor lands back on the homepage having seen nothing at all,
     which looks exactly like the form failing silently. So if the URL
     is carrying a message, put the card back on screen.

     THE MODE IS ALREADY DECIDED. auth-modal.php reads ?mode= and the
     error code and writes the answer into data-mode, so a failed
     registration comes back to the registration pane. This only has to
     honour it — do not pass a mode here and override that.
     =================================================================== */
  /* ===================================================================
     CLOSED PROPERLY, FROM NOW ON

     The markup does not carry `inert`, deliberately — if this file
     ever fails to load, a card that ignores every click would be a
     worse failure than the one inert prevents. So it goes on here, at
     runtime, the moment we know the script is alive.

     Without it the closed card is still in the tab order: a keyboard
     visitor tabs off the nav and lands in an invisible sign-in form
     with no way to tell where they are.
     =================================================================== */
  if (!modal.classList.contains('is-open')) {
    modal.setAttribute('inert', '');
    modal.setAttribute('aria-hidden', 'true');
  }

  restoreEmail();
  showSlide(0);

  var params = new URLSearchParams(window.location.search);
  var carryingMessage = params.has('error') ||
                        params.has('registered') ||
                        params.has('sent');

  /* Belt and braces: if PHP wrote a banner for any other reason, that
     is still a reason to be open. */
  var bannerShowing = (errorBox && !errorBox.hidden) ||
                      (noticeBox && !noticeBox.hidden);

  if (carryingMessage || bannerShowing) {
    openModal(box.getAttribute('data-mode') || 'signin');

    /* Strip the flags. Without this a refresh replays the message and
       the card springs open a second time over an account that already
       exists. replaceState leaves no history entry, so Back still goes
       where it should.

       email and mode go too — they were only ever instructions for
       this one render, and an address left in the address bar is an
       address left in the browser history. */
    ['error', 'registered', 'sent', 'email', 'mode'].forEach(function (key) {
      params.delete(key);
    });

    var query = params.toString();
    window.history.replaceState(
      {}, '',
      window.location.pathname + (query ? '?' + query : '') + window.location.hash
    );
  }

  /* #signin / #register / #reset still work, so a link from anywhere
     on the site can open a particular pane:  <a href="about.php#register"> */
  var hash = window.location.hash.replace('#', '');
  if (['signin', 'register', 'reset'].indexOf(hash) !== -1) {
    openModal(hash);
    window.history.replaceState(null, '', window.location.pathname + window.location.search);
  }

  /* Anything else on the site that needs to open this — a "save this
     place" heart, a booking button — can call it without importing
     anything:  window.CNAuth.open('register')  */
  window.CNAuth = { open: openModal, close: closeModal, mode: switchMode };
})();