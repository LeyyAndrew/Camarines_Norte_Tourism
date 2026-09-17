<?php
/* ===================================================================
   search.php  —  the full results page

   Where the header form submits, and where Enter in the dropdown
   lands. It runs the same search_site() the dropdown does, so what a
   visitor previews is what they get here.

   No JavaScript is involved in producing this page. If search.js fails
   to load, the header form still submits and this page still answers —
   which is the whole reason the overlay is built around a real <form>
   instead of a div and a keypress handler.
   =================================================================== */

require_once __DIR__ . '/includes/search.php';

/* ---------- NAME MATCHING ----------
   These two belong in includes/search.php next to search_exact_target()
   the moment anything else needs them; they sit here so this page is
   one file to read. function_exists() so moving them later cannot
   collide. */

if (!function_exists('search_fold')) {
    /* Lowercase, strip accents and punctuation, squeeze the spaces — so
       "Bagasbas Beach", "bagasbas beach" and "bagasbas-beach" are one
       thing to compare against. */
    function search_fold(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');

        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
            if ($t !== false) { $s = $t; }
        }

        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}

if (!function_exists('search_sole_place')) {

    /* The one destination this query names, or null.

       search_exact_target() only fires on the whole name, which is what
       stops it guessing — and also what stops "bagasbas" reaching
       "Bagasbas Beach". This widens the rule by one step without
       loosening it: the query still has to leave exactly one winner.

       Reads the results already fetched rather than searching again, and
       only considers rows pointing at a destination anchor — a matching
       event or dish must not send anyone to the map. */
    function search_sole_place(string $q, array $all): ?string
    {
        $needle = search_fold($q);

        /* Under three characters the query is a fragment, not a name.
           "da" would otherwise pick a winner out of Daet by prefix. */
        if (mb_strlen($needle) < 3) { return null; }

        /* A query made only of these is asking to browse, and the chips
           on this page do that better than dropping the visitor on
           whichever single beach happened to match. static so the list
           is built once no matter how often this runs. */
        static $generic = [
            'beach', 'beaches', 'island', 'islands', 'falls', 'fall', 'river',
            'rivers', 'church', 'park', 'resort', 'cove', 'bay', 'mountain',
            'hill', 'heritage', 'spring', 'springs', 'lake', 'cave', 'trail',
            'view', 'point',
        ];

        $named = false;
        foreach (explode(' ', $needle) as $w) {
            if (!in_array($w, $generic, true)) { $named = true; break; }
        }
        if (!$named) { return null; }

        $best = 0;
        $hits = [];

        foreach ($all as $r) {
            if (!preg_match('/#dest-[a-z0-9\\-]+$/i', (string) $r['url'])) { continue; }

            $title = search_fold((string) $r['title']);
            $n     = preg_quote($needle, '/');

            /* 4 — the whole name:                 "bagasbas beach"
               3 — the name starts with it:        "bagasbas"  -> Bagasbas Beach
               2 — a whole word further in:        "bagasbas"  -> Daet Bagasbas Blvd
               1 — a prefix landing mid-word:      "bagas"

               3 has to outrank 2 or the two Bagasbas rows tie and
               nothing jumps at all — which is the case this whole
               function exists for. Anything below 1 is a substring
               buried mid-name, a coincidence more often than an
               intention. */
            if ($title === $needle) {
                $score = 4;
            } elseif (preg_match('/^' . $n . '(?: |$)/', $title)) {
                $score = 3;
            } elseif (preg_match('/ ' . $n . '(?: |$)/', $title)) {
                $score = 2;
            } elseif (strpos($title, $needle) === 0) {
                $score = 1;
            } else {
                continue;
            }

            if ($score > $best) { $best = $score; $hits = []; }
            if ($score === $best) { $hits[] = (string) $r['url']; }
        }

        /* Two places tied at the top means the query did not pick one of
           them, so the results page keeps them and the visitor chooses. */
        return count($hits) === 1 ? $hits[0] : null;
    }
}

$q    = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$kind = isset($_GET['kind']) ? trim((string) $_GET['kind']) : '';

/* ---------- THE GATE ----------
   A screen rather than a redirect, on purpose. A redirect throws away
   what the visitor typed and drops them somewhere they did not ask to
   go — they sign in, land on the homepage, and have to remember the
   word and start again. This keeps the query in the page, in the
   sign-in link, and in the field they come back to.

   The query itself is safe to show while signed out: they typed it.
   What is withheld is the results. */
$searchLocked = !search_allowed();

/* ---------- THE JUMP ----------
   The query names one place, so go there instead of showing a page of
   one result and making them click it. The destination anchor carries
   the slug, and dest-focus in search.js opens that pin on the map at
   the other end.

   Both rules return null unless exactly one place is left standing,
   which is what stops this firing on a guess.

   302, not 301: this is a decision about today's content, not a
   permanent move. A 301 gets cached by the browser, and if the place
   is ever renamed, visitors keep getting bounced to a URL that no
   longer exists with no way to clear it.

   ?list=1 is the way back out: the "show all results instead" link on
   the destination end, and how you check the ranking for a query that
   would otherwise always jump. */
/* The search runs before the jump so search_sole_place() can read the
   results that were going to be fetched anyway rather than hitting the
   index a second time to answer the same question. */
$all = (!$searchLocked && $q !== '') ? search_site($q, 60) : [];

if (!$searchLocked && $q !== '' && $kind === '' && !isset($_GET['list'])) {
    $jump = search_exact_target($q);

    /* Exact name first, sole match second. Order matters: an exact name
       should still win even where a longer name also contains it. */
    if ($jump === null) {
        $jump = search_sole_place($q, $all);
    }

    if ($jump !== null) {
        header('Location: ' . $jump, true, 302);
        exit;
    }
}

/* Count every kind BEFORE filtering, so the chips can show real totals
   and stay visible while one of them is active. */
$kinds = [];
foreach ($all as $r) {
    $kinds[$r['kind']] = ($kinds[$r['kind']] ?? 0) + 1;
}

$results = $kind === '' ? $all : array_values(array_filter($all, function ($r) use ($kind) {
    return $r['kind'] === $kind;
}));

$pageTitle = $q !== ''
    ? 'Search: ' . $q . ' — Explore Camarines Norte'
    : 'Search — Explore Camarines Norte';
$pageDesc  = 'Search destinations, towns, food and events across Camarines Norte.';

require __DIR__ . '/includes/header.php';
?>

<main id="main" class="searchpage">
  <div class="wrap">

    <header class="searchpage__head">
      <p class="searchpage__eyebrow">Search</p>

      <!-- No field while locked. A search box that visibly does
           nothing is worse than no search box: it invites the visitor
           to type a second time and fail a second time. The gate
           below carries the only action there is.

           Autofocused only when it is empty. Landing on a page of
           results with the cursor yanked into the box scrolls the
           results out of view on a phone, which hides the very thing
           the visitor asked for. -->
      <?php if (!$searchLocked): ?>
      <form class="searchpage__form" action="search.php" method="get" role="search">
        <svg class="searchpage__form-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><line x1="15.3" y1="15.3" x2="20.5" y2="20.5"/></svg>
        <input type="search" name="q" class="searchpage__input"
               value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
               placeholder="Beaches, falls, Bicol Express…"
               aria-label="Search the site"
               <?= $q === '' ? 'autofocus' : '' ?>>
        <button type="submit" class="searchpage__submit">Search</button>
      </form>
      <?php endif; ?>

      <?php if ($searchLocked): ?>
        <!-- No count, no filters: there are no results to count, and
             saying "0 results" would be a lie about the province
             rather than the truth about the account. -->
      <?php elseif ($q !== ''): ?>
        <p class="searchpage__count" role="status">
          <?php if (!$results): ?>
            No matches for <strong><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></strong>
          <?php else: ?>
            <strong><?= count($results) ?></strong>
            <?= count($results) === 1 ? 'result' : 'results' ?>
            for <strong><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></strong>
          <?php endif; ?>
        </p>

        <?php if (count($kinds) > 1): ?>
          <div class="searchpage__filters">
            <a class="searchpage__chip<?= $kind === '' ? ' is-on' : '' ?>"
               href="search.php?q=<?= urlencode($q) ?>">All <span><?= count($all) ?></span></a>
            <?php foreach ($kinds as $k => $n): ?>
              <a class="searchpage__chip<?= $kind === $k ? ' is-on' : '' ?>"
                 href="search.php?q=<?= urlencode($q) ?>&amp;kind=<?= urlencode($k) ?>">
                <?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?> <span><?= $n ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </header>

    <?php if ($searchLocked): ?>

      <!-- Says what happened, why, and what to do about it — in that
           order, and without apologising. The link carries ?next= so
           signing in returns them here with the query intact. -->
      <div class="searchpage__gate">
        <span class="searchpage__gate-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/><circle cx="12" cy="15.5" r="1.2"/></svg>
        </span>

        <p class="searchpage__empty-lead">Sign in to search</p>
        <p class="searchpage__empty-sub">
          <?php if ($q !== ''): ?>
            Your search for <strong><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></strong> is saved — sign in and we&rsquo;ll run it.
          <?php else: ?>
            Searching the province needs an account. It takes a minute.
          <?php endif; ?>
        </p>

        <div class="searchpage__gate-actions">
          <a class="searchpage__submit" href="<?= htmlspecialchars(
                search_login_link('search.php' . ($q !== '' ? '?q=' . rawurlencode($q) : '')),
                ENT_QUOTES, 'UTF-8') ?>">Sign in</a>
          <a class="searchpage__chip" href="destinations.php">Browse destinations instead</a>
        </div>

        <!-- Nothing here is behind the gate, so the way out is never a
             dead end: a signed-out visitor can still reach the whole
             province by hand. -->
        <div class="searchpage__suggest">
          <a class="searchpage__chip" href="<?= htmlspecialchars(search_url_filter('cat', 'Beaches & Islands'), ENT_QUOTES, 'UTF-8') ?>">Beaches &amp; Islands</a>
          <a class="searchpage__chip" href="<?= htmlspecialchars(search_url_filter('cat', 'Falls & Rivers'), ENT_QUOTES, 'UTF-8') ?>">Falls &amp; Rivers</a>
          <a class="searchpage__chip" href="<?= htmlspecialchars(search_url_filter('cat', 'Heritage'), ENT_QUOTES, 'UTF-8') ?>">Heritage</a>
          <a class="searchpage__chip" href="gallery.php">Gallery</a>
        </div>
      </div>

    <?php elseif ($q === ''): ?>

      <!-- An empty screen is an invitation to act, so it offers the
           six categories rather than apologising for having no
           results yet. -->
      <div class="searchpage__empty">
        <p class="searchpage__empty-lead">Start anywhere.</p>
        <div class="searchpage__suggest">
          <?php foreach (['Calaguas', 'Bagasbas', 'falls', 'Daet', 'island', 'hiking'] as $s): ?>
            <a class="searchpage__chip" href="search.php?q=<?= urlencode($s) ?>"><?= $s ?></a>
          <?php endforeach; ?>
        </div>
      </div>

    <?php elseif (!$results): ?>

      <!-- Says what happened and what to do about it. No apology, no
           dead end. -->
      <div class="searchpage__empty">
        <p class="searchpage__empty-lead">Nothing here matches that yet.</p>
        <p class="searchpage__empty-sub">Try a shorter word, a town name, or browse the province instead.</p>
        <div class="searchpage__suggest">
          <a class="searchpage__chip" href="destinations.php">All 24 destinations</a>
          <a class="searchpage__chip" href="<?= htmlspecialchars(search_url_filter('cat', 'Beaches & Islands'), ENT_QUOTES, 'UTF-8') ?>">Beaches &amp; Islands</a>
          <a class="searchpage__chip" href="<?= htmlspecialchars(search_url_filter('cat', 'Falls & Rivers'), ENT_QUOTES, 'UTF-8') ?>">Falls &amp; Rivers</a>
          <a class="searchpage__chip" href="gallery.php">Gallery</a>
        </div>
      </div>

    <?php else: ?>

      <ol class="searchpage__list">
        <?php foreach ($results as $r): ?>
          <?php $img = search_image_url($r['image']); ?>
          <li class="searchpage__row">
            <a class="searchpage__card" href="<?= htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') ?>">

              <?php if ($img): ?>
                <img class="searchpage__thumb" src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
              <?php else: ?>
                <span class="searchpage__thumb searchpage__thumb--none" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17c2 0 2 1.6 4 1.6s2-1.6 4-1.6 2 1.6 4 1.6 2-1.6 4-1.6"/><path d="M12 3v10"/><path d="M12 6c2.5-2 5-1.5 6.5 0-2 1.5-4.5 1.5-6.5 0z"/></svg>
                </span>
              <?php endif; ?>

              <span class="searchpage__text">
                <span class="searchpage__kind"><?= htmlspecialchars($r['kind'], ENT_QUOTES, 'UTF-8') ?></span>
                <strong class="searchpage__title"><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (!empty($r['meta'])): ?>
                  <span class="searchpage__meta"><?= htmlspecialchars($r['meta'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($r['snippet'] !== ''): ?>
                  <span class="searchpage__snip"><?= htmlspecialchars($r['snippet'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </span>

              <svg class="searchpage__go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>

    <?php endif; ?>

  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>