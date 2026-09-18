/* ===================================================================
   assets/js/feedback.js

   Opens/closes the feedback dialog and sends the form with fetch().
   If this file never loads, the form still posts normally and
   feedback-submit.php redirects back with a notice.
   =================================================================== */
(function () {
  'use strict';

  var modal = document.getElementById('feedbackModal');
  if (!modal) return; // signed out, or an admin: the dialog was not rendered

  var form        = modal.querySelector('[data-feedback-form]');
  var body        = modal.querySelector('[data-feedback-body]');
  var done        = modal.querySelector('[data-feedback-done]');
  var doneText    = modal.querySelector('[data-feedback-done-text]');
  var note        = modal.querySelector('[data-feedback-note]');
  var actions     = modal.querySelector('[data-feedback-actions]');
  var doneActions = modal.querySelector('[data-feedback-done-actions]');
  var errorBox    = modal.querySelector('[data-feedback-error]');
  var submitBtn   = modal.querySelector('[data-feedback-submit]');
  var message     = modal.querySelector('[data-feedback-message]');
  var counter     = modal.querySelector('[data-feedback-count]');
  var rateWord    = modal.querySelector('[data-feedback-rate-word]');
  var lastFocus   = null;

  var WORDS = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Very good', 5: 'Excellent' };

  function setRateWord(value) {
    if (rateWord) rateWord.textContent = value ? WORDS[value] : 'Not rated';
  }

  function showForm() {
    body.hidden = false;
    done.hidden = true;
    if (note) note.hidden = false;
    actions.hidden = false;
    doneActions.hidden = true;
  }

  function showDone(text) {
    doneText.textContent = text;
    body.hidden = true;
    done.hidden = false;
    if (note) note.hidden = true;
    actions.hidden = true;
    doneActions.hidden = false;
    var btn = doneActions.querySelector('button');
    if (btn) btn.focus();
  }

  function resetForm() {
    form.reset();
    errorBox.hidden = true;
    if (counter) counter.textContent = '0 / 2000';
    setRateWord(0);
  }

  function open(e) {
    if (e) e.preventDefault();
    lastFocus = document.activeElement;

    // If the phone drawer is open, close it first.
    var drawerClose = document.querySelector('#navDrawer:not([hidden]) [data-drawer-close]');
    if (drawerClose) drawerClose.click();

    showForm();
    modal.hidden = false;
    document.documentElement.style.overflow = 'hidden';

    var first = form.querySelector('#fbSubject');
    if (first) setTimeout(function () { first.focus(); }, 60);
  }

  function close() {
    // After a successful send, start clean next time.
    if (!done.hidden) resetForm();
    modal.hidden = true;
    document.documentElement.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function showError(text) {
    errorBox.textContent = text;
    errorBox.hidden = false;
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-feedback-open]')) open(e);
    else if (e.target.closest('[data-feedback-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (modal.hidden) return;

    if (e.key === 'Escape') { close(); return; }

    // keep Tab inside the dialog
    if (e.key === 'Tab') {
      var items = Array.prototype.filter.call(
        modal.querySelectorAll('button, input:not([type="hidden"]), textarea'),
        function (el) { return !el.disabled && el.offsetParent !== null; }
      );
      if (!items.length) return;
      var firstEl = items[0], lastEl = items[items.length - 1];
      if (e.shiftKey && document.activeElement === firstEl) { e.preventDefault(); lastEl.focus(); }
      else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); firstEl.focus(); }
    }
  });

  form.addEventListener('change', function (e) {
    if (e.target.name === 'rating') setRateWord(e.target.value);
  });

  if (message && counter) {
    message.addEventListener('input', function () {
      counter.textContent = message.value.length + ' / 2000';
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errorBox.hidden = true;

    var subject = form.subject.value.trim();
    var text    = form.message.value.trim();

    if (subject.length < 3) { showError('Please add a subject of at least 3 characters.'); form.subject.focus(); return; }
    if (text.length < 10)   { showError('Please add a few more details (at least 10 characters).'); form.message.focus(); return; }

    // Record the current page, including any filters in the URL.
    form.page_url.value = location.pathname + location.search;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    })
      .then(function (res) {
        return res.json().catch(function () {
          return { ok: false, message: 'Unexpected server response. Please try again.' };
        });
      })
      .then(function (data) {
        if (data.ok) {
          showDone(data.message || 'Your message was sent to the site administrator. Thank you.');
        } else {
          showError(data.message || 'Your feedback could not be sent.');
        }
      })
      .catch(function () {
        showError('Network error. Check your connection and try again.');
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send feedback';
      });
  });

  // Tidy ?feedback=sent out of the address bar after a no-JS post.
  if (/[?&]feedback=/.test(location.search) && window.history && history.replaceState) {
    var url = new URL(location.href);
    url.searchParams.delete('feedback');
    history.replaceState(null, '', url.pathname + url.search + url.hash);
  }
})();