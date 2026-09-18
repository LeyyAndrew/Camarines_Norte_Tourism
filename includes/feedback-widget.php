<?php
/* ===================================================================
   includes/feedback-widget.php

   The "Send feedback" dialog. Included from footer.php. Renders only
   for signed-in visitors who are not admins (admins read feedback in
   admin/feedback.php). Opened by any element with data-feedback-open.

   The form is a real POST to includes/feedback-submit.php, so it
   still works if feedback.js fails to load.

   Layout: a dark header band in the same near-black as the site nav,
   with the provincial seal, then a white form body, then a footer
   with the privacy line and the two buttons.
   =================================================================== */

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') === 'admin')) { return; }

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$fbE      = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fbStatus = $_GET['feedback'] ?? '';
$fbSeal   = 'uploads/logo.png';

$fbTypes = [
    'general'     => 'General',
    'suggestion'  => 'Suggestion',
    'bug'         => 'Bug',
    'destination' => 'Place info',
    'other'       => 'Other',
];
?>
<div class="fb-modal" id="feedbackModal" hidden>
  <div class="fb-modal__scrim" data-feedback-close></div>

  <div class="fb-modal__box" role="dialog" aria-modal="true" aria-labelledby="fbTitle">

    <!-- ============ HEADER BAND ============ -->
    <header class="fb-head">
      <?php if (is_file(__DIR__ . '/../' . $fbSeal)): ?>
        <img class="fb-head__seal" src="<?= $fbE($fbSeal) ?>" alt="" width="40" height="40">
      <?php endif; ?>
      <div class="fb-head__text">
        <h3 class="fb-head__title" id="fbTitle">Send feedback</h3>
        <p class="fb-head__sub">Report a problem or suggest an improvement to the site administrator.</p>
      </div>
      <button type="button" class="fb-head__close" data-feedback-close aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>

    <!-- ============ FORM ============ -->
    <form class="fb-form" action="includes/feedback-submit.php" method="post" data-feedback-form novalidate>
      <div class="fb-body" data-feedback-body>
        <input type="hidden" name="csrf" value="<?= $fbE($_SESSION['csrf']) ?>">
        <input type="hidden" name="page_url" value="<?= $fbE($_SERVER['REQUEST_URI'] ?? '') ?>">

        <!-- Rating. Printed 5 → 1 and flipped with row-reverse in CSS so
             the ~ selector can light every star up to the hovered one. -->
        <fieldset class="fb-group">
          <legend class="fb-label">Overall experience <span class="fb-label__opt">Optional</span></legend>
          <div class="fb-rate">
            <div class="fb-stars">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="fbStar<?= $i ?>" value="<?= $i ?>">
                <label for="fbStar<?= $i ?>" title="<?= $i ?> out of 5">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.8l2.8 5.8 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 3 1.1-6.3-4.6-4.4 6.3-.9z"/></svg>
                  <span class="fb-sr"><?= $i ?> out of 5</span>
                </label>
              <?php endfor; ?>
            </div>
            <span class="fb-rate__word" data-feedback-rate-word aria-hidden="true">Not rated</span>
          </div>
        </fieldset>

        <fieldset class="fb-group">
          <legend class="fb-label">Category</legend>
          <div class="fb-chips">
            <?php $first = true; foreach ($fbTypes as $value => $label): ?>
              <label class="fb-chip">
                <input type="radio" name="category" value="<?= $fbE($value) ?>"<?= $first ? ' checked' : '' ?>>
                <span><?= $fbE($label) ?></span>
              </label>
            <?php $first = false; endforeach; ?>
          </div>
        </fieldset>

        <div class="fb-group">
          <label class="fb-label" for="fbSubject">Subject</label>
          <input class="fb-input" type="text" id="fbSubject" name="subject" maxlength="120" required
                 placeholder="Summarise the issue in a few words">
        </div>

        <div class="fb-group">
          <div class="fb-label-row">
            <label class="fb-label" for="fbMessage">Details</label>
            <span class="fb-count" data-feedback-count>0 / 2000</span>
          </div>
          <textarea class="fb-input fb-input--area" id="fbMessage" name="message" rows="5" maxlength="2000" required
                    placeholder="Describe what happened, where on the site, and what you expected instead."
                    data-feedback-message></textarea>
        </div>

        <p class="fb-error" role="alert" data-feedback-error hidden></p>
      </div>

      <!-- ============ SUCCESS STATE ============ -->
      <div class="fb-done" data-feedback-done hidden>
        <div class="fb-done__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
        </div>
        <h4 class="fb-done__title">Feedback sent</h4>
        <p class="fb-done__text" data-feedback-done-text>Your message was sent to the site administrator. Thank you.</p>
      </div>

      <footer class="fb-foot">
        <p class="fb-foot__note" data-feedback-note>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/></svg>
          Only the site administrator can read this.
        </p>
        <div class="fb-foot__actions" data-feedback-actions>
          <button type="button" class="fb-btn fb-btn--ghost" data-feedback-close>Cancel</button>
          <button type="submit" class="fb-btn fb-btn--primary" data-feedback-submit>Send feedback</button>
        </div>
        <div class="fb-foot__actions" data-feedback-done-actions hidden>
          <button type="button" class="fb-btn fb-btn--primary" data-feedback-close>Done</button>
        </div>
      </footer>
    </form>
  </div>
</div>

<?php if ($fbStatus === 'sent' || $fbStatus === 'error'): ?>
  <!-- Shown after a normal (no-JavaScript) form post. -->
  <div class="fb-toast<?= $fbStatus === 'error' ? ' fb-toast--error' : '' ?>" role="status">
    <?= $fbStatus === 'sent' ? 'Your feedback was sent to the site administrator.' : 'Your feedback could not be sent. Please try again.' ?>
  </div>
<?php endif; ?>