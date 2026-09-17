<?php
/* ===================================================================
   itinerary-print.php — one trip, laid out for paper.

   Opened from the Print button on plan-trip.php:

       itinerary-print.php?id=12

   WHY PRINT RATHER THAN A GENERATED PDF

   Every browser can already turn a page into a PDF, and does it
   better than a PHP library would: real fonts, selectable text,
   correct page breaks. A library would mean a dependency to install,
   a second layout to maintain, and output nobody can copy from.

   So this is a plain page with print CSS. Ctrl+P, or Share → Print on
   a phone, then "Save as PDF". It also prints properly on actual
   paper, which matters where a phone has no signal — which is most of
   the way to Calaguas.

   LIGHT, NOT DARK. The site is dark; paper is not. A dark page prints
   as a black rectangle or, with backgrounds off, as grey text on
   white. This one is built light from the start.
   =================================================================== */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

$userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
    ? (int) $_SESSION['user_id'] : null;

$id = (int) ($_GET['id'] ?? 0);

$trip  = null;
$byDay = [];
$error = null;

if ($userId === null) {
    $error = 'Sign in to open your itinerary.';
} elseif ($id <= 0) {
    $error = 'No itinerary was given.';
} else {
    try {
        /* user_id in the WHERE, not checked afterwards: a trip that is
           not yours does not exist as far as this page is concerned. */
        $st = db()->prepare(
            'SELECT id, name, travelers, source, brief, start_date, end_date, created_at
               FROM itineraries WHERE id = ? AND user_id = ?'
        );
        $st->execute([$id, $userId]);
        $trip = $st->fetch();

        if (!$trip) {
            $error = 'That itinerary was not found.';
        } else {
            $st = db()->prepare(
                'SELECT t.day_number, t.start_time, t.note, t.dest_slug,
                        d.name AS dest_name, d.town AS dest_town
                   FROM itinerary_items t
              LEFT JOIN destinations d ON d.id = t.destination_id
                  WHERE t.itinerary_id = ?
               ORDER BY t.day_number, t.sort_order, t.id'
            );
            $st->execute([$id]);
            foreach ($st->fetchAll() as $r) {
                $byDay[(int) $r['day_number']][] = $r;
            }
        }
    } catch (Throwable $e) {
        error_log('itinerary-print: ' . $e->getMessage());
        $error = 'Could not load that itinerary.';
    }
}

/* ---------- the collected fares and fees ----------
   Same source as the panel on plan-trip.php: includes/bud-data.php.
   A printed plan with no prices on it would be the one thing a
   traveller cannot look up once they have left signal behind. */
$fees = [];
$routes = [];

$budData = __DIR__ . '/includes/bud-data.php';
if ($trip && is_file($budData)) {
    try {
        $bd = require $budData;
        foreach ($bd['destinations'] ?? [] as $d) {
            if (!empty($d['fees']))   { $fees[$d['name']]   = $d['fees']; }
            if (!empty($d['routes'])) { $routes[$d['name']] = $d['routes']; }
        }
    } catch (Throwable $e) {
        error_log('itinerary-print fees: ' . $e->getMessage());
    }
}

/* A typo lived here briefly: an assignment to $trief inside the
   condition. It parsed, it even worked by accident, and it would have
   sat there forever. */
$brief = ($trip && !empty($trip['brief']))
    ? json_decode((string) $trip['brief'], true)
    : null;

function pr(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** "P600" and "P2500 to P3000" -> peso signs, for print. */
function peso(string $s): string
{
    return str_replace('P', '&#8369;', htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $trip ? pr($trip['name']) : 'Itinerary' ?> &mdash; Camarines Norte</title>
<style>
  :root{ --ink:#16191c; --mid:#5a6068; --line:#d8dbdf; --accent:#c05600; }

  *{ box-sizing:border-box; }

  body{
    margin:0;
    padding:2rem 1.25rem 3rem;
    background:#f4f4f2;
    color:var(--ink);
    font:15px/1.6 "Helvetica Neue", Arial, sans-serif;
  }

  .sheet{
    max-width:44rem;
    margin:0 auto;
    background:#fff;
    padding:2.4rem 2.6rem 2.8rem;
    border-radius:6px;
    box-shadow:0 2px 14px rgba(0,0,0,.08);
  }

  .toolbar{
    max-width:44rem;
    margin:0 auto 1rem;
    display:flex;
    gap:.6rem;
    justify-content:flex-end;
  }

  .btn{
    padding:.55rem 1.05rem;
    border-radius:8px;
    border:1px solid var(--line);
    background:#fff;
    color:var(--ink);
    font:inherit;
    font-size:.88rem;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
  }

  .btn--go{ background:var(--accent); border-color:var(--accent); color:#fff; }

  h1{ margin:0 0 .2rem; font-size:1.6rem; letter-spacing:-.01em; }

  .sub{ color:var(--mid); font-size:.9rem; margin:0 0 .3rem; }

  .rule{ border:0; border-top:2px solid var(--ink); margin:1.1rem 0 1.4rem; }

  .day{ margin-bottom:1.6rem; }

  .day-h{
    font-size:.78rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.1em;
    color:var(--accent);
    margin:0 0 .5rem;
    padding-bottom:.25rem;
    border-bottom:1px solid var(--line);
  }

  .stop{ display:flex; gap:1rem; padding:.6rem 0; border-bottom:1px solid #eceef0; }
  .stop:last-child{ border-bottom:0; }

  /* Fixed width and tabular figures so the times form a column the eye
     can run down the page. */
  .time{
    flex:0 0 3.6rem;
    font-variant-numeric:tabular-nums;
    font-weight:700;
    font-size:.92rem;
  }

  .body{ flex:1; min-width:0; }
  .name{ font-weight:700; font-size:1rem; }
  .town{ color:var(--mid); font-size:.82rem; }
  .note{ margin:.15rem 0 0; font-size:.9rem; }

  .sec{
    margin:.5rem 0 .15rem;
    font-size:.68rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.09em;
    color:var(--mid);
  }

  .legs{ margin:0; padding-left:1.1rem; font-size:.86rem; color:#33383e; }
  .legs li{ margin:.1rem 0; }
  .legs em{ font-style:normal; color:var(--mid); font-size:.8rem; display:block; }

  table.fees{ width:100%; border-collapse:collapse; font-size:.86rem; margin-top:.2rem; }
  table.fees td{ padding:.2rem 0; border-bottom:1px solid #f0f1f3; vertical-align:top; }
  table.fees tr:last-child td{ border-bottom:0; }
  /* Widths, not just padding. The amount column was shrink-to-fit and
     right-aligned, so it butted straight against the unit — "FreePer
     person". Giving both a width puts a real gutter between them and
     keeps the amounts in a column down the page. */
  /* table.fees td above sets padding:.2rem 0 and, being a class plus
     two element selectors, outranks a bare .fee-amt — so the gutter
     silently never applied. Matched at the same strength here. */
  table.fees td.fee-amt{
    width:7.5rem;
    text-align:right;
    padding-right:1rem;
    font-variant-numeric:tabular-nums;
    font-weight:700;
    white-space:nowrap;
  }

  table.fees td.fee-unit{
    width:9rem;
    color:var(--mid);
    font-size:.78rem;
    white-space:nowrap;
  }

  .foot{
    margin-top:1.6rem;
    padding-top:1rem;
    border-top:2px solid var(--ink);
    font-size:.8rem;
    color:var(--mid);
    line-height:1.6;
  }

  .warn{
    margin:1.2rem 0 0;
    padding:.8rem 1rem;
    border-left:3px solid var(--accent);
    background:#fdf6f0;
    font-size:.84rem;
  }

  /* ---------- paper ----------
     The toolbar is a screen control and has no meaning on paper. The
     shadow and the grey surround waste ink. And a day should not be
     split across a page break when it can be helped. */
  @media print{
    body{ background:#fff; padding:0; font-size:11.5pt; }
    .sheet{ box-shadow:none; border-radius:0; max-width:none; padding:0; }
    .toolbar{ display:none; }
    .day{ break-inside:avoid; page-break-inside:avoid; }
    .stop{ break-inside:avoid; page-break-inside:avoid; }
    a{ color:inherit; text-decoration:none; }
  }
</style>
</head>
<body>

<?php if ($error): ?>
  <div class="sheet"><h1>Itinerary</h1><p class="sub"><?= pr($error) ?></p>
  <p><a class="btn" href="plan-trip.php">Back to Plan your trip</a></p></div>
  </body></html><?php exit; ?>
<?php endif; ?>

<div class="toolbar">
  <a class="btn" href="plan-trip.php">Back</a>
  <button class="btn btn--go" onclick="window.print()">Print or save as PDF</button>
</div>

<div class="sheet">

  <h1><?= pr($trip['name']) ?></h1>
  <p class="sub">
    Camarines Norte &middot;
    <?= (int) $trip['travelers'] ?> traveller<?= (int) $trip['travelers'] === 1 ? '' : 's' ?>
    <?php if (!empty($trip['start_date'])): ?>
      &middot; <?= date('j M Y', strtotime($trip['start_date'])) ?><?php
        if (!empty($trip['end_date']) && $trip['end_date'] !== $trip['start_date']) {
            echo ' to ' . date('j M Y', strtotime($trip['end_date']));
        } ?>
    <?php endif; ?>
    <?php if (!empty($brief['interests']) && is_array($brief['interests'])): ?>
      &middot; <?= pr(implode(', ', $brief['interests'])) ?>
    <?php endif; ?>
  </p>
  <p class="sub">
    <?= $trip['source'] === 'bud' ? 'Suggested by Bud.Ai' : 'Planned by you' ?>,
    <?= date('j M Y', strtotime($trip['created_at'])) ?>
  </p>

  <hr class="rule">

  <?php $totalMin = 0.0; $noFee = 0; $withFee = 0; ?>

  <?php foreach ($byDay as $dayNo => $items): ?>
    <div class="day">
      <div class="day-h">Day <?= (int) $dayNo ?></div>

      <?php foreach ($items as $it): ?>
        <?php
          $label   = $it['dest_name'] ?? $it['dest_slug'] ?? 'Removed place';
          $stopFee = $fees[$label] ?? [];
          $stopRt  = $routes[$label] ?? [];

          /* Same floor as the panel: cheapest travel option plus the
             genuinely per-head charges. See the note there — the fee
             data mixes alternatives with charges, so a total is a
             floor and never a forecast. */
          $travelWords   = ['boat', 'charter', 'joiner', 'tour', 'van', 'ferry'];
          $optionalWords = ['advanced', 'rental', 'lesson', 'activity', 'activities'];
          $travelOpts = []; $perHead = []; $priced = false;

          foreach ($stopFee as $f) {
              if (empty($f['amount'])) { continue; }
              if (!preg_match_all('/\d[\d,]*/', $f['amount'], $m)) { continue; }
              $nums = array_map(static fn($x) => (float) str_replace(',', '', $x), $m[0]);
              $nums = array_filter($nums, static fn($n) => $n > 0);
              if (!$nums) { continue; }
              $priced = true;

              $l = strtolower($f['label']);
              foreach ($optionalWords as $w) { if (strpos($l, $w) !== false) { continue 2; } }

              $isTravel = false;
              foreach ($travelWords as $w) { if (strpos($l, $w) !== false) { $isTravel = true; break; } }
              $isPer = stripos($f['unit'] ?? '', 'per person') !== false;

              if ($isTravel && $isPer)      { $travelOpts[] = min($nums); }
              elseif (!$isTravel && $isPer) { $perHead[]    = min($nums); }
          }

          if ($travelOpts) { $totalMin += min($travelOpts); }
          if ($perHead)    { $totalMin += array_sum($perHead); }
          $priced ? $withFee++ : $noFee++;
        ?>

        <div class="stop">
          <div class="time"><?= $it['start_time'] !== null
              ? pr(substr($it['start_time'], 0, 5)) : '&mdash;' ?></div>

          <div class="body">
            <div class="name"><?= pr($label) ?>
              <?php if (!empty($it['dest_town'])): ?>
                <span class="town">&middot; <?= pr($it['dest_town']) ?></span>
              <?php endif; ?>
            </div>

            <?php if (!empty($it['note'])): ?>
              <p class="note"><?= pr($it['note']) ?></p>
            <?php endif; ?>

            <?php if ($stopRt): ?>
              <?php
                $legsBy = [];
                foreach ($stopRt as $r) {
                    if (!empty($r['origin'])) { $legsBy[$r['origin']][] = $r; }
                }
              ?>
              <?php foreach ($legsBy as $from => $legs): ?>
                <div class="sec">Getting there from <?= pr((string) $from) ?></div>
                <ol class="legs">
                  <?php foreach ($legs as $lg): ?>
                    <li>
                      <?php
                        $bits = [];
                        if (!empty($lg['mode'])) { $bits[] = pr($lg['mode']); }
                        if (!empty($lg['time'])) { $bits[] = pr($lg['time']); }
                        if (!empty($lg['fare'])) { $bits[] = peso((string) $lg['fare']); }
                        echo implode(' &middot; ', $bits);
                      ?>
                      <?php if (!empty($lg['notes'])): ?>
                        <em><?= pr($lg['notes']) ?></em>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($stopFee): ?>
              <div class="sec">What it costs</div>
              <table class="fees">
                <?php foreach (array_slice($stopFee, 0, 10) as $f): ?>
                  <tr>
                    <td><?= pr($f['label'] ?? '') ?></td>
                    <td class="fee-amt">
                      <?php
                        $amt = (string) ($f['amount'] ?? '');
                        if ($amt === '') {
                            echo '<span class="town">not recorded</span>';
                        } elseif (preg_match('/^\s*P?\s*0(\.0+)?\s*$/i', $amt)) {
                            echo 'Free';
                        } else {
                            echo peso($amt);
                        }
                      ?>
                    </td>
                    <td class="fee-unit"><?= pr($f['unit'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($totalMin > 0): ?>
    <div class="warn">
      <b>Rough cost:</b> from &#8369;<?= number_format($totalMin) ?> per person for entrance
      and on-site fees<?php if ((int) $trip['travelers'] > 1): ?>,
      about &#8369;<?= number_format($totalMin * (int) $trip['travelers']) ?> for
      <?= (int) $trip['travelers'] ?> travellers<?php endif; ?>.
      Cheapest option at each stop, so treat it as a floor.
      <?php if ($noFee): ?>
        <?= (int) $noFee ?> of <?= (int) ($noFee + $withFee) ?> stops have no fee data yet.
      <?php endif; ?>
      Transport between towns, food and accommodation are not included.
    </div>
  <?php endif; ?>

  <div class="foot">
    <b>None of these figures is confirmed.</b> They were collected from published
    guides and local knowledge, not from the operators, and prices change.
    Check fares, boat schedules and opening hours with the Camarines Norte
    Provincial Tourism Office before you travel. Boat trips to the islands
    depend on weather and Coast Guard clearance and can be cancelled on the day.
  </div>

</div>

<script>
/* ?print=1 opens the print dialog straight away, so the button on
   plan-trip.php is one click rather than two. Deliberately not the
   default: arriving at a page that immediately opens a system dialog
   is startling when you only wanted to read it. */
if (new URLSearchParams(location.search).get('print') === '1') {
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
}
</script>
</body>
</html>