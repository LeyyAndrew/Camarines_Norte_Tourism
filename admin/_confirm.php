<?php
/* ===================================================================
   admin/_confirm.php — the confirmation dialog for destructive actions.

   Required once by _footer.php, so it is on every admin page and no
   page has to think about it.

   ---------------------------------------------------------------
   HOW TO USE IT

   Put the attributes on the FORM, not the button:

     <form method="post"
           data-confirm
           data-confirm-title="Delete Bagasbas Beach?"
           data-confirm-body="It comes off the destinations page and the map."
           data-confirm-note="Its photo is removed from the server."
           data-confirm-action="Delete permanently">
       ...
     </form>

   Only data-confirm is required. Everything else falls back to
   sensible wording. data-confirm-note is drawn as a warning strip and
   is for the consequence people do not expect — a file leaving the
   server, something else breaking.
   ---------------------------------------------------------------

   WHY NOT confirm()

   The browser's confirm() works, and for a long time that was a fine
   answer. It is being replaced because:

     it looks like a browser error, not part of your admin panel — a
       grey box at the top of the screen with the word "localhost" on it
     it cannot show structure, so "this also deletes the file from the
       server" reads at exactly the same weight as everything else
     the buttons say OK and Cancel. OK is not a word anybody thinks
       when they mean "yes, destroy this" — a button that says Delete
       is one more chance to notice what you are about to do
     Chrome and Firefox both let a page suppress further dialogs after
       a few in a row, so on the fifth delete the confirm can silently
       stop appearing
     it blocks the whole browser tab while it is open

   WHY <dialog> AND NOT A DIV

   ::backdrop, Escape to close, the top layer above every z-index on
   the page, focus moved in and returned to where it came from when it
   closes — all of that is the browser's job here rather than two
   hundred lines of ours. Supported everywhere since 2022.

   THE FALLBACK IS THE POINT. If <dialog> is missing or the script has
   not run, the submit handler is never attached and the form posts
   normally — the delete still works, just without the confirmation
   step. A confirmation that breaks the button it guards would be
   worse than no confirmation.
   =================================================================== */
?>
<dialog class="adm-confirm" id="admConfirm" aria-labelledby="admConfirmTitle">
  <form method="dialog" class="adm-confirm__box">
    <div class="adm-confirm__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
           stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>

    <h2 class="adm-confirm__title" id="admConfirmTitle">Are you sure?</h2>
    <p class="adm-confirm__body" data-confirm-body>This cannot be undone.</p>

    <!-- hidden unless the form supplies a note -->
    <p class="adm-confirm__note" data-confirm-note hidden></p>

    <div class="adm-confirm__actions">
      <!-- Cancel is the default: it is first in the DOM, so it takes
           focus when the dialog opens and Enter chooses it. The safe
           answer should be the one you get by doing nothing. -->
      <button type="submit" value="cancel" class="adm-btn adm-btn--ghost" data-confirm-cancel>Cancel</button>
      <button type="button" class="adm-btn adm-btn--danger" data-confirm-go>Delete</button>
    </div>
  </form>
</dialog>

<style>
/* ===================================================================
   Tokens come from admin.css, so this follows the panel automatically
   if you ever change the palette.
   =================================================================== */
.adm-confirm{
  padding:0;
  border:0;
  border-radius:var(--adm-radius-lg,.6rem);
  background:transparent;
  max-width:min(27rem, calc(100vw - 2rem));
  color:var(--adm-ink,#151A17);
  font-family:var(--adm-font-body,'Inter',Arial,sans-serif);
}

/* ::backdrop is the browser's own overlay behind a modal dialog. It
   cannot be styled through the element, only through this. */
.adm-confirm::backdrop{
  background:rgba(14,36,25,.55);
  backdrop-filter:blur(2px);
}

.adm-confirm__box{
  padding:1.4rem 1.4rem 1.15rem;
  background:var(--adm-panel,#fff);
  border-radius:var(--adm-radius-lg,.6rem);
  box-shadow:0 24px 60px -20px rgba(14,36,25,.55);
}

.adm-confirm__icon{
  display:grid; place-items:center;
  width:2.6rem; height:2.6rem;
  margin-bottom:.85rem;
  border-radius:999px;
  background:rgba(178,59,48,.10);
  color:var(--adm-danger,#B23B30);
}
.adm-confirm__icon svg{ width:1.35rem; height:1.35rem; }

.adm-confirm__title{
  margin:0 0 .4rem;
  font-family:var(--adm-font-display,'Archivo',Arial,sans-serif);
  font-size:1.12rem; font-weight:700; line-height:1.25;
  letter-spacing:-.01em;
}

.adm-confirm__body{
  margin:0;
  font-size:.88rem; line-height:1.55;
  color:var(--adm-muted,#69736C);
}

/* The consequence people do not expect. Given its own strip because a
   sentence in the same grey as everything else is a sentence that
   gets skimmed. */
.adm-confirm__note{
  margin:.8rem 0 0;
  padding:.6rem .7rem;
  border-left:3px solid var(--adm-danger,#B23B30);
  border-radius:0 .35rem .35rem 0;
  background:rgba(178,59,48,.06);
  font-size:.82rem; line-height:1.5;
  color:var(--adm-ink,#151A17);
}

.adm-confirm__actions{
  display:flex; justify-content:flex-end; gap:.5rem;
  margin-top:1.25rem;
}

/* Opening animation. Reduced-motion users get the dialog with no
   movement — it still appears, it just does not travel. */
.adm-confirm[open]{ animation:admConfirmIn .16s ease-out; }
.adm-confirm[open]::backdrop{ animation:admBackdropIn .16s ease-out; }

@keyframes admConfirmIn{
  from{ opacity:0; transform:translateY(6px) scale(.985); }
  to  { opacity:1; transform:none; }
}
@keyframes admBackdropIn{ from{ opacity:0 } to{ opacity:1 } }

@media (prefers-reduced-motion:reduce){
  .adm-confirm[open], .adm-confirm[open]::backdrop{ animation:none; }
}

@media (max-width:480px){
  .adm-confirm__actions{ flex-direction:column-reverse; }
  .adm-confirm__actions .adm-btn{ width:100%; text-align:center; }
}
</style>

<script>
/* ===================================================================
   One listener on the document, so it covers forms that are on the
   page now and any that appear later.
   =================================================================== */
(function () {
  var dlg = document.getElementById('admConfirm');

  /* No <dialog> support, or the element is missing? Attach nothing.
     Every form then submits normally — the guard is gone but the
     button still works, which is the right way round to fail. */
  if (!dlg || typeof dlg.showModal !== 'function') return;

  var titleEl  = dlg.querySelector('.adm-confirm__title');
  var bodyEl   = dlg.querySelector('[data-confirm-body]');
  var noteEl   = dlg.querySelector('[data-confirm-note]');
  var goBtn    = dlg.querySelector('[data-confirm-go]');
  var cancelEl = dlg.querySelector('[data-confirm-cancel]');

  var pending = null;      /* the form waiting on an answer */

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.hasAttribute || !form.hasAttribute('data-confirm')) return;

    /* Already answered yes — let this one through. Without the flag
       the programmatic submit below would come straight back here and
       reopen the dialog forever. */
    if (form.dataset.confirmed === 'yes') {
      delete form.dataset.confirmed;
      return;
    }

    e.preventDefault();
    pending = form;

    titleEl.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
    bodyEl.textContent  = form.getAttribute('data-confirm-body')  || 'This cannot be undone.';

    var note = form.getAttribute('data-confirm-note');
    noteEl.textContent = note || '';
    noteEl.hidden = !note;

    goBtn.textContent = form.getAttribute('data-confirm-action') || 'Delete';

    dlg.showModal();

    /* Focus Cancel, not Delete. The dialog appears under the pointer
       and a second stray click or a reflexive Enter should land on the
       harmless button. */
    cancelEl.focus();
  });

  goBtn.addEventListener('click', function () {
    var form = pending;
    dlg.close('confirm');
    if (!form) return;

    form.dataset.confirmed = 'yes';

    /* requestSubmit, not submit. submit() skips validation AND skips
       the submit event, which would defeat the flag above; and it
       loses which button was pressed. requestSubmit behaves exactly
       like a real click. Every browser that has <dialog> has it. */
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  });

  /* Escape and Cancel both land here. Clearing pending matters —
     otherwise the next Delete press could act on the previous form. */
  dlg.addEventListener('close', function () { pending = null; });
})();
</script>