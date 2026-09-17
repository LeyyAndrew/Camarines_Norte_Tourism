<?php
/* ===================================================================
   includes/welcome-popup.php

   Bud's welcome pop-up. Shown ONCE per sign-in (login_process.php
   clears the flag). Styles: assets/css/welcome-popup.css, linked
   below only when the pop-up is shown.

   No card: Bud floats over the dimmed page and the welcome plays out
   as a short chat —
     Bud:     Hello, <name>!  (signed in as <username>)
     Bud:     Ready to start exploring Camarines Norte?
     Visitor: Let's go!       <- the button that closes it
   =================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!empty($_SESSION['user_id']) && empty($_SESSION['welcome_shown'])):

$_SESSION['welcome_shown'] = true;

$firstname = $_SESSION['firstname'] ?? 'there';
$username  = $_SESSION['username']  ?? $firstname;
$budAvatar = 'uploads/chatbot.png';

$welcomeCss = 'assets/css/welcome-popup.css';
$welcomeVer = @filemtime(dirname(__DIR__) . '/' . $welcomeCss) ?: '1';
?>
<link rel="stylesheet" href="<?= $welcomeCss ?>?v=<?= $welcomeVer ?>">

<dialog class="welcome" id="welcomeDialog" aria-labelledby="welcomeTitle" tabindex="-1">

  <button type="button" class="welcome__x" aria-label="Close" data-welcome-close>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
  </button>

  <div class="welcome__scene">
    <button type="button" class="welcome__bud" aria-label="Ask Bud for Camarines Norte trivia">
      <img src="<?= htmlspecialchars($budAvatar) ?>" alt="">
      <span class="welcome__hint" aria-hidden="true"><span class="welcome__hint-text">Click me for <b>trivia!</b></span> <span aria-hidden="true">👇</span></span>
    </button>

    <span class="bubble bubble--dots bubble--blue b-dots" aria-hidden="true"><i></i><i></i><i></i></span>

    <div class="bubble bubble--teal b-hello">
      <small>Bud.Ai</small>
      <h2 id="welcomeTitle" class="font-display">Hello, <?= htmlspecialchars($firstname) ?>! <span class="wave" aria-hidden="true">👋</span></h2>
      <span class="b-hello__user">
        <span class="welcome__dot" aria-hidden="true"></span>
        Signed in as <strong><?= htmlspecialchars($username) ?></strong>
      </span>
    </div>

    <p class="bubble bubble--blue b-explore" aria-live="polite">
      <small class="b-explore__label" hidden>Did you know?</small>
      <span class="b-explore__text">Ready to start exploring Camarines Norte? I'll be in the corner if you need me.</span>
    </p>

    <button type="button" class="bubble bubble--reply b-reply" data-welcome-close>
      Let's go!
    </button>
  </div>
</dialog>

<script>
(function () {
  var d = document.getElementById('welcomeDialog');
  if (!d || typeof d.showModal !== 'function') return;

  function close() {
    d.classList.add('is-closing');
    setTimeout(function () { d.close(); d.classList.remove('is-closing'); }, 200);
  }

  var calm  = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var scene = d.querySelector('.welcome__scene');

  /* ---------- particle bursts (sparkles and confetti) ---------- */
  var colors = ['#F0A32C', '#3FC6D0', '#5A8FDB', '#FFFFFF', '#7CF0A8'];
  function burst(fromEl, count, kind) {
    if (calm) return;
    var s = scene.getBoundingClientRect();
    var r = fromEl.getBoundingClientRect();
    var cx = r.left + r.width / 2 - s.left;
    var cy = r.top + r.height / 2 - s.top;
    for (var k = 0; k < count; k++) {
      var p = document.createElement('span');
      p.className = 'particle particle--' + kind;
      var angle = (Math.PI * 2 * k) / count + Math.random() * .5;
      var dist  = (kind === 'confetti' ? 70 : 90) + Math.random() * 50;
      p.style.left = cx + 'px';
      p.style.top  = cy + 'px';
      p.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
      p.style.setProperty('--dy', Math.sin(angle) * dist + (kind === 'confetti' ? 40 : 0) + 'px');
      p.style.setProperty('--spin', (Math.random() * 540 - 270) + 'deg');
      p.style.background = colors[k % colors.length];
      scene.appendChild(p);
      p.addEventListener('animationend', function () { this.remove(); });
    }
  }

  d.querySelector('.welcome__x').addEventListener('click', close);

  var leaving = false;
  d.querySelector('.b-reply').addEventListener('click', function () {
    if (leaving) return;
    leaving = true;
    burst(this, 22, 'confetti');
    setTimeout(function () { close(); leaving = false; }, calm ? 0 : 480);
  });
  /* click anywhere that isn't Bud or a bubble */
  d.addEventListener('click', function (e) {
    if (e.target === d || e.target.classList.contains('welcome__scene')) close();
  });
  d.addEventListener('cancel', function (e) { e.preventDefault(); close(); });

  /* ---------- tap Bud for Cam Norte trivia ----------
     Shuffled once per visit, then shown in order, so nothing repeats
     until every fact has been seen. Add more lines to the list. */
  var bud    = d.querySelector('.welcome__bud');
  var bubble = d.querySelector('.b-explore');
  var label  = d.querySelector('.b-explore__label');
  var text   = d.querySelector('.b-explore__text');
  var hint   = d.querySelector('.welcome__hint-text');
  var touch  = !window.matchMedia('(hover: hover)').matches;
  var verb   = touch ? 'Tap' : 'Click';
  hint.innerHTML = verb + ' me for <b>trivia!</b>';

  var trivia = [
    "Daet is home to the first monument to Jose Rizal in the Philippines, finished in 1898.",
    "The name Camarines comes from the camarines, or rice granaries, the Spanish found across the region.",
    "Vinzons used to be called Indan. It was renamed after Wenceslao Vinzons, a World War II guerrilla leader born there.",
    "Jose Panganiban was once known as Mambulao, and it is named after Bicolano propagandist Jose Maria Panganiban.",
    "Camarines Norte became its own province again on March 3, 1919, through Act No. 2809.",
    "The province has twelve municipalities, and Daet is its capital.",
    "Paracale and Jose Panganiban have been known for gold since before the Spanish arrived.",
    "Daet's Pinyasan Festival celebrates the province's sweet Formosa pineapple."
  ];
  for (var j = trivia.length - 1; j > 0; j--) {
    var r = Math.floor(Math.random() * (j + 1));
    var tmp = trivia[j]; trivia[j] = trivia[r]; trivia[r] = tmp;
  }
  var n = -1;

  var typing = false;
  bud.addEventListener('click', function () {
    if (typing) return;
    typing = true;
    n = (n + 1) % trivia.length;

    burst(bud, 10, 'sparkle');
    bud.classList.add('was-tapped');

    /* Bud "types" for a moment, then the fact pops in */
    label.hidden = true;
    text.innerHTML = '<span class="typing"><i></i><i></i><i></i></span>';
    hint.textContent = 'Thinking…';

    setTimeout(function () {
      label.hidden = false;
      label.textContent = 'Did you know? ' + (n + 1) + '/' + trivia.length;
      text.textContent = trivia[n];
      bubble.classList.remove('is-new');
      void bubble.offsetWidth;
      bubble.classList.add('is-new');
      hint.innerHTML = (n === trivia.length - 1)
        ? verb + ' to <b>start over</b>'
        : verb + ' for <b>another one!</b>';
      typing = false;
    }, calm ? 0 : 650);
  });

  setTimeout(function () {
    d.showModal();
    d.focus();
    /* once the chat has played in, Bud shows his hint by himself */
    setTimeout(function () { bud.classList.add('is-inviting'); }, 2700);
    setTimeout(function () { bud.classList.remove('is-inviting'); }, 3300);
  }, 400);
})();
</script>
<?php endif; ?>