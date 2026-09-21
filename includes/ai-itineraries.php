<?php
/* ===================================================================
   includes/ai-itineraries.php

   The "AI-built itineraries" panel for plan-trip.php. One line adds
   it, just inside the signed-in branch, above the Trip details card:

       <?php require __DIR__ . '/includes/ai-itineraries.php'; ?>

   WHY SERVER-RENDERED, NOT ANOTHER FETCH

   plan-trip.js is a closed IIFE: hydrate(), collect() and the day
   builder are private to it, and there is no hook to hand a trip to
   from outside. Reaching in would mean editing that file and owning
   its bugs.

   So this reads the rows itself and prints them. The panel is
   complete on first paint — no spinner, no second round trip, and
   nothing to go wrong if a script fails to load. The only JavaScript
   is the toggle for each plan and the delete button.

   It shows source = 'bud' only. Trips built by hand in the builder
   below are that builder's business.
   =================================================================== */

require_once __DIR__ . '/db.php';

/* ---------- the collected fees ----------

   Loaded from includes/bud-data.php — the same figures Bud is
   grounded on — and attached to each stop below.

   THE MODEL IS NEVER ASKED FOR A PRICE. It is told not to put one in
   an itinerary at all, precisely so that every peso amount a visitor
   sees here came from somebody who collected it. Attaching them at
   render time also means a corrected fee shows up on plans that were
   generated before the correction.

   Keyed by name because that is what the two files share; the id
   lives in one and not the other. */
$aiFees   = [];
$aiRoutes = [];

$budDataFile = __DIR__ . '/bud-data.php';
if (is_file($budDataFile)) {
    try {
        $bd = require $budDataFile;
        foreach ($bd['destinations'] ?? [] as $d) {
            $rows = [];
            foreach ($d['fees'] ?? [] as $f) {
                /* A fee with a label and no amount is a known unknown
                   — "cottage, ask on arrival". Worth showing, because
                   it warns of a cost; worth marking, because it is not
                   a number. */
                $rows[] = [
                    'label'  => $f['label'] ?? '',
                    'amount' => $f['amount'] ?? '',
                    'unit'   => $f['unit'] ?? '',
                ];
            }
            if ($rows) {
                $aiFees[$d['name']] = $rows;
            }

            /* How to get there, from the same collected data. Grouped
               by where you start, because "from Manila" and "from
               Daet" are different journeys, not alternative first
               legs of one. */
            $legs = [];
            foreach ($d['routes'] ?? [] as $r) {
                $from = $r['origin'] ?? '';
                if ($from === '') {
                    continue;
                }
                $legs[$from][] = $r;
            }
            if ($legs) {
                $aiRoutes[$d['name']] = $legs;
            }
        }
    } catch (Throwable $e) {
        error_log('ai-itineraries fees: ' . $e->getMessage());
    }
}

/**
 * Pull a peso range out of the strings the worksheet produced:
 * "P600", "P2500 to P3000", "20-30", "250 500 to 250-1,500".
 * Returns [min, max] or null when there is no number to find.
 *
 * Deliberately forgiving about format and strict about inventing:
 * anything it cannot read returns null and is counted as unknown
 * rather than guessed at.
 */
function ai_money(string $s): ?array
{
    $nums = [];
    if (preg_match_all('/\d[\d,]*/', $s, $m)) {
        foreach ($m[0] as $x) {
            $n = (float) str_replace(',', '', $x);
            if ($n > 0) {
                $nums[] = $n;
            }
        }
    }
    if (!$nums) {
        return null;
    }
    return [min($nums), max($nums)];
}

$aiTrips = [];

if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    try {
        $st = db()->prepare(
            /* BOTH SOURCES. This used to filter to source='bud',
               which meant a trip built by hand in the builder below
               saved successfully and then appeared nowhere — the
               visitor had no way to know it had worked. One list, with
               a badge saying which made it, is one place to look. */
            'SELECT i.id, i.name, i.travelers, i.brief, i.source,
                    i.start_date, i.end_date, i.created_at,
                    COALESCE(MAX(t.day_number), 0) AS days,
                    COUNT(t.id) AS stops
               FROM itineraries i
          LEFT JOIN itinerary_items t ON t.itinerary_id = i.id
              WHERE i.user_id = ?
           GROUP BY i.id
           ORDER BY i.created_at DESC
              LIMIT 20'
        );
        $st->execute([(int) $_SESSION['user_id']]);
        $aiTrips = $st->fetchAll();

        /* The stops for all of them in ONE query rather than one per
           trip. Twenty plans would otherwise be twenty-one queries to
           draw a panel that is usually collapsed. */
        if ($aiTrips) {
            $ids = array_column($aiTrips, 'id');
            $in  = implode(',', array_fill(0, count($ids), '?'));

            $st = db()->prepare(
                "SELECT t.itinerary_id, t.day_number, t.start_time, t.note,
                        t.dest_slug, d.name AS dest_name, d.town AS dest_town
                   FROM itinerary_items t
              LEFT JOIN destinations d ON d.id = t.destination_id
                  WHERE t.itinerary_id IN ($in)
               ORDER BY t.itinerary_id, t.day_number, t.sort_order, t.id"
            );
            $st->execute($ids);

            $byTrip = [];
            foreach ($st->fetchAll() as $r) {
                $byTrip[(int) $r['itinerary_id']][(int) $r['day_number']][] = $r;
            }
            foreach ($aiTrips as $i => $t) {
                $aiTrips[$i]['byDay'] = $byTrip[(int) $t['id']] ?? [];
            }
        }
    } catch (Throwable $e) {
        /* The builder below is the point of this page; a panel that
           cannot load its rows should not take the page with it. */
        error_log('ai-itineraries: ' . $e->getMessage());
        $aiTrips = [];
    }
}
?>

<section class="pt-card ai-itins">
  <div class="pt-card-h">
    <h2>Your saved itineraries</h2>
    <span class="pt-sub">Everything you have saved &mdash; open one to see the days, the fares and what it costs</span>
  </div>

  <?php if (!$aiTrips): ?>

    <!-- Empty state that says what to DO, not just that there is
         nothing. Someone reaching this panel with no plans needs to
         know where Bud is, not that the list is empty. -->
    <div class="ai-empty">
      <p>Nothing saved yet.</p>
      <p class="ai-empty-how">
        Build a trip below and press <b>Save my itinerary</b>, or open the
        assistant &mdash; the robot in the bottom-right corner &mdash; and choose
        <b>Create me an itinerary</b>. Either way it lands here.
      </p>
    </div>

  <?php else: ?>

    <ul class="ai-list">
      <?php foreach ($aiTrips as $t): ?>
        <?php
          $brief = $t['brief'] !== null ? json_decode($t['brief'], true) : null;
          $bits  = [];
          $bits[] = (int) $t['days'] . ' day' . ((int) $t['days'] === 1 ? '' : 's');
          $bits[] = (int) $t['stops'] . ' stop' . ((int) $t['stops'] === 1 ? '' : 's');
          $bits[] = (int) $t['travelers'] . ' traveller' . ((int) $t['travelers'] === 1 ? '' : 's');

          /* Bud's plans have no dates — it plans in days, before
             anyone has picked a weekend. Hand-built ones usually do,
             and when they do it is the most useful thing on the row. */
          if (!empty($t['start_date'])) {
              $bits[] = date('j M', strtotime($t['start_date']))
                  . (!empty($t['end_date']) && $t['end_date'] !== $t['start_date']
                      ? ' to ' . date('j M', strtotime($t['end_date'])) : '');
          }
          if (!empty($brief['budget'])) {
            $bits[] = ['budget' => 'backpacker', 'mid' => 'middle',
                       'comfortable' => 'comfortable'][$brief['budget']] ?? $brief['budget'];
          }
        ?>
        <li class="ai-item">
          <!-- <details> rather than a click handler: the browser
               already knows how to open and close a disclosure, it
               works with the keyboard, and it needs no script. -->
          <details>
            <summary>
              <span class="ai-name"><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?></span>
              <!-- Which made it. Bud's plans and hand-built ones sit
                   in the same list, and the difference matters: one
                   was suggested, the other chosen. -->
              <span class="ai-src ai-src--<?= $t['source'] === 'bud' ? 'bud' : 'you' ?>">
                <?= $t['source'] === 'bud' ? 'Bud' : 'By you' ?>
              </span>
              <!-- Each part escaped, then joined with the separator.
                   Escaping the joined string turned the &middot; into
                   a literal "&middot;" on the page. -->
              <span class="ai-meta"><?= implode(' &middot; ', array_map(
                  static fn($b) => htmlspecialchars((string) $b, ENT_QUOTES, 'UTF-8'), $bits)) ?></span>
              <?php if (!empty($brief['interests']) && is_array($brief['interests'])): ?>
                <span class="ai-tags">
                  <?php foreach (array_slice($brief['interests'], 0, 4) as $x): ?>
                    <em><?= htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8') ?></em>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
            </summary>

            <div class="ai-days">
              <?php if (!$t['byDay']): ?>
                <p class="ai-none">This plan has no stops left &mdash; the places may have been removed.</p>
              <?php endif; ?>

              <?php
                /* Running totals for the budget box. Per-person and
                   per-group are kept apart: a P600 boat seat and a
                   P2,500 boat hire cannot be added together and then
                   multiplied by the number of travellers. */
                $sumMin = 0.0; $sumMax = 0.0;
                $groupMin = 0.0; $groupMax = 0.0;
                $noFeeData = 0; $feeStops = 0; $groupOpts = [];
                $travellers = max(1, (int) $t['travelers']);
              ?>

              <?php foreach ($t['byDay'] as $dayNo => $items): ?>
                <div class="ai-day">
                  <div class="ai-day-h">
                    <span class="ai-day-n">Day <?= (int) $dayNo ?></span>
                    <span class="ai-day-count"><?= count($items) ?> stop<?= count($items) === 1 ? '' : 's' ?></span>
                  </div>

                  <ol class="ai-stops">
                    <?php foreach ($items as $it): ?>
                      <?php
                        $label = $it['dest_name'] ?? $it['dest_slug'] ?? 'Removed place';
                        $fees  = $aiFees[$label] ?? [];

                        /* Only fees with a readable amount count toward
                           the estimate. The rest are still listed, so a
                           cost with no number is visible rather than
                           quietly missing. */
                        $priced = [];
                        foreach ($fees as $f) {
                            $r = $f['amount'] !== '' ? ai_money($f['amount']) : null;
                            if ($r) { $priced[] = $f + ['range' => $r]; }
                        }

                        if ($priced) {
                            $feeStops++;

                            /* WHY THIS IS NOT A SUM.
                               A stop's fees are a mix of two things
                               the worksheet does not distinguish:
                               ALTERNATIVES you choose between, and
                               CHARGES you all pay. Calaguas lists a
                               DIY boat seat, a small charter, a large
                               charter and three joiner tours — nobody
                               pays for more than one — alongside an
                               environmental fee everybody pays.

                               Adding them produced a P31,587 estimate
                               for three people on a three-day trip,
                               which is worse than showing nothing.

                               So: cheapest of the travel options, plus
                               the fees that are genuinely per head,
                               and no upper bound at all. A floor is
                               defensible; a ceiling built from
                               alternatives is not. */
                            $travelWords = ['boat', 'charter', 'joiner', 'tour', 'van', 'ferry'];
                            $optionalWords = ['advanced', 'rental', 'lesson', 'activity', 'activities'];

                            $travelOpts = [];
                            $perHead    = [];

                            foreach ($priced as $f) {
                                $l = strtolower($f['label']);

                                $isOptional = false;
                                foreach ($optionalWords as $w) {
                                    if (strpos($l, $w) !== false) { $isOptional = true; break; }
                                }
                                /* Lessons and rentals are things a
                                   visitor may want, not things the
                                   trip costs. Listed above, left out
                                   of the floor. */
                                if ($isOptional) { continue; }

                                $isTravel = false;
                                foreach ($travelWords as $w) {
                                    if (strpos($l, $w) !== false) { $isTravel = true; break; }
                                }

                                $isPerPerson = stripos($f['unit'], 'per person') !== false;

                                if ($isTravel) {
                                    /* Cheapest wins, and only if it is
                                       priced per person — a per-boat
                                       hire is a shared cost. */
                                    if ($isPerPerson) { $travelOpts[] = $f['range'][0]; }
                                    else              { $groupOpts[]  = $f['range'][0]; }
                                } elseif ($isPerPerson) {
                                    $perHead[] = $f['range'][0];
                                }
                            }

                            if ($travelOpts) { $sumMin += min($travelOpts); }
                            if ($perHead)    { $sumMin += array_sum($perHead); }
                            if (!empty($groupOpts)) {
                                $groupMin += min($groupOpts);
                                $groupOpts = [];
                            }
                        } else {
                            $noFeeData++;
                        }
                      ?>
                      <?php
                        /* Split the fees before drawing them. A row
                           reading "not recorded" three times in a
                           column headed AMOUNT is three rows of noise;
                           as one line at the foot it still warns of
                           the cost without pretending to be data. */
                        $known = []; $unknown = [];
                        foreach (array_slice($fees, 0, 10) as $f) {
                            if ($f['amount'] !== '') { $known[] = $f; }
                            else { $unknown[] = $f['label']; }
                        }
                      ?>
                      <li class="ai-stop-row">
                        <span class="ai-time"><?= $it['start_time'] !== null
                              ? htmlspecialchars(substr($it['start_time'], 0, 5)) : '&mdash;' ?></span>

                        <div class="ai-stop">
                          <div class="ai-stop-top">
                            <b><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></b>
                            <?php if (!empty($it['dest_town'])): ?>
                              <span class="ai-town"><?= htmlspecialchars($it['dest_town'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                          </div>

                          <?php if (!empty($it['note'])): ?>
                            <span class="ai-note"><?= htmlspecialchars($it['note'], ENT_QUOTES, 'UTF-8') ?></span>
                          <?php endif; ?>

                          <?php $routes = $aiRoutes[$label] ?? []; ?>
                          <?php if ($routes): ?>
                            <!-- GETTING THERE. Collected route data,
                                 the same figures Bud answers chat
                                 questions from. Shown per stop because
                                 that is where the question is asked —
                                 nobody wonders how to reach a place
                                 until they have decided to go. -->
                            <div class="ai-route">
                              <span class="ai-sec">Getting there</span>
                              <?php foreach ($routes as $from => $legs): ?>
                                <div class="ai-route-from">
                                  <span class="ai-route-o">From <?= htmlspecialchars((string) $from, ENT_QUOTES, 'UTF-8') ?></span>
                                  <ol>
                                    <?php foreach ($legs as $lg): ?>
                                      <li>
                                        <?php
                                          /* Mode, duration and fare, in
                                             that order and only where
                                             recorded. A leg with a mode
                                             and nothing else is still
                                             worth showing: it tells you
                                             a jeep exists. */
                                          $bits = array_filter([
                                              $lg['mode'] ?? null,
                                              $lg['time'] ?? null,
                                              !empty($lg['fare'])
                                                  ? str_replace('P', '&#8369;', htmlspecialchars(
                                                        (string) $lg['fare'], ENT_QUOTES, 'UTF-8'))
                                                  : null,
                                          ]);
                                          /* mode and time are escaped here; fare was
                                             escaped above before the peso swap. */
                                          $out = [];
                                          foreach ($bits as $i => $bv) {
                                              $out[] = $i === 2 ? $bv
                                                  : htmlspecialchars((string) $bv, ENT_QUOTES, 'UTF-8');
                                          }
                                          echo implode(' &middot; ', $out);
                                        ?>
                                        <?php if (!empty($lg['notes'])): ?>
                                          <em><?= htmlspecialchars($lg['notes'], ENT_QUOTES, 'UTF-8') ?></em>
                                        <?php endif; ?>
                                      </li>
                                    <?php endforeach; ?>
                                  </ol>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>

                          <?php if ($known): ?>
                            <!-- NO HEADER ROW. It said COST / AMOUNT /
                                 PER above every stop — nine times on a
                                 three-day plan — to label three things
                                 that are obvious from the content. The
                                 unit now sits under its own label,
                                 which is where it belongs anyway: it
                                 qualifies the cost, not the number. -->
                            <span class="ai-sec">What it costs</span>
                            <ul class="ai-fees">
                              <?php foreach ($known as $f): ?>
                                <?php
                                  /* A fee of exactly zero is not a
                                     price, it is the absence of one.
                                     "Free" says that; "P0" makes the
                                     reader work it out. */
                                  $isFree = preg_match('/^\s*P?\s*0(\.0+)?\s*$/i', $f['amount']) === 1;
                                ?>
                                <li>
                                  <span class="ai-fee-l">
                                    <?= htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($f['unit'] !== ''): ?>
                                      <em><?= htmlspecialchars($f['unit'], ENT_QUOTES, 'UTF-8') ?></em>
                                    <?php endif; ?>
                                  </span>
                                  <span class="ai-fee-a<?= $isFree ? ' is-free' : '' ?>">
                                    <?= $isFree ? 'Free' : str_replace('P', '&#8369;',
                                        htmlspecialchars($f['amount'], ENT_QUOTES, 'UTF-8')) ?>
                                  </span>
                                </li>
                              <?php endforeach; ?>
                            </ul>
                          <?php endif; ?>

                          <?php if ($unknown): ?>
                            <p class="ai-fee-x">
                              No rate recorded yet for
                              <?= htmlspecialchars(strtolower(implode(', ', $unknown)), ENT_QUOTES, 'UTF-8') ?>.
                            </p>
                          <?php elseif (!$known): ?>
                            <p class="ai-fee-x">No fees recorded for this stop yet.</p>
                          <?php endif; ?>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ol>
                </div>
              <?php endforeach; ?>

              <!-- ---------- BUDGET ----------
                   Built from the collected fees, not from the model,
                   and honest about what it leaves out. An estimate
                   that hides its gaps is worse than no estimate: a
                   visitor budgets to it and comes up short. -->
              <!-- ---------- BUDGET ----------
                   A FLOOR, not a range. See the note in the loop
                   above: the fee data mixes alternatives with charges,
                   so an upper bound built from it is meaningless.
                   "From X" is something a visitor can act on; "X to
                   thirty-one thousand" is not. -->
              <div class="ai-budget">
                <div class="ai-budget-h">What this trip costs, at least</div>

                <?php if ($sumMin > 0 || $groupMin > 0): ?>
                  <div class="ai-budget-rows">
                    <?php if ($sumMin > 0): ?>
                      <div>
                        <span>Entrance and fees, per person</span>
                        <b>from &#8369;<?= number_format($sumMin) ?></b>
                      </div>
                      <?php if ($travellers > 1): ?>
                        <div>
                          <span>&times; <?= $travellers ?> travellers</span>
                          <b>from &#8369;<?= number_format($sumMin * $travellers) ?></b>
                        </div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($groupMin > 0): ?>
                      <div>
                        <span>Shared, whatever the group size (boat hire, cottages)</span>
                        <b>from &#8369;<?= number_format($groupMin) ?></b>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <p class="ai-budget-none">No fees are recorded yet for any stop on this plan.</p>
                <?php endif; ?>

                <?php if ($noFeeData): ?>
                  <p class="ai-budget-gap"><?= (int) $noFeeData ?> of <?= (int) ($noFeeData + $feeStops) ?> stops have no fee data yet.</p>
                <?php endif; ?>

                <!-- The fine print, folded. On a phone six lines of
                     disclaimer outweighed the one line of actual answer
                     above it. Opened automatically on wider screens by
                     the script at the foot of this file. -->
                <details class="ai-budget-more">
                  <summary>What this figure includes</summary>
                  <p class="ai-budget-note">
                    Cheapest option at each stop, so treat it as a floor rather than a
                    forecast. Lessons, rentals and tour packages are listed above but
                    left out of this figure. Nothing here covers transport between towns,
                    food or accommodation, and none of it is confirmed &mdash; check with
                    the Provincial Tourism Office before you travel.
                  </p>
                </details>
              </div>

              <div class="ai-actions">
                <!-- Opens in a new tab rather than replacing this one:
                     the print dialog appears over the new tab, and
                     closing it leaves the visitor where they were
                     instead of on a page built for paper. -->
                <a class="ai-btn ai-btn--ghost"
                   href="itinerary-print.php?id=<?= (int) $t['id'] ?>&amp;print=1"
                   target="_blank" rel="noopener">Save as PDF or print</a>

                <button type="button" class="ai-btn ai-btn--danger ai-del"
                        data-itin="<?= (int) $t['id'] ?>">Delete this plan</button>
              </div>
            </div>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>

  <?php endif; ?>
</section>

<!-- ---------- CONFIRM DIALOG ----------
     The browser's confirm() blocks the whole page, cannot be styled,
     and says "localhost says" above the question — which on a public
     site reads like something has gone wrong. This is the same
     question in the site's own voice.

     <dialog> rather than a div: the browser gives focus trapping,
     Escape to close and the backdrop for free, and they behave the
     same way as every other dialog the visitor has met. -->
<dialog class="ai-confirm" id="aiConfirm">
  <form method="dialog">
    <h3>Delete this itinerary?</h3>
    <p id="aiConfirmName"></p>
    <p class="ai-confirm-note">This cannot be undone. The plan and all its stops are removed from your account.</p>
    <div class="ai-confirm-actions">
      <button value="cancel" class="ai-btn ai-btn--ghost">Keep it</button>
      <button value="delete" class="ai-btn ai-btn--danger">Delete</button>
    </div>
  </form>
</dialog>

<div class="ai-toast" id="aiToast" role="status" aria-live="polite" hidden></div>

<script>
/* Deleting posts to the same endpoint Bud saves through, so one place
   owns itineraries rather than two. The row is removed on success
   rather than reloading, which would lose unsaved work in the builder
   below. */
(function () {
  var dlg   = document.getElementById('aiConfirm');
  var nameEl= document.getElementById('aiConfirmName');
  var toast = document.getElementById('aiToast');
  var pending = null;

  /* The budget fine print is folded on phones only. Wider screens have
     the room, so it opens there on load. */
  if (window.matchMedia && window.matchMedia('(min-width: 641px)').matches) {
    document.querySelectorAll('.ai-budget-more').forEach(function (d) { d.open = true; });
  }

  function say(msg, bad) {
    if (!toast) return;
    toast.textContent = msg;
    toast.hidden = false;
    toast.className = 'ai-toast' + (bad ? ' is-bad' : '');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toast.hidden = true; }, 4000);
  }

  document.querySelectorAll('.ai-del').forEach(function (btn) {
    btn.addEventListener('click', function () {
      pending = btn;

      var item = btn.closest('.ai-item');
      var nm = item ? item.querySelector('.ai-name') : null;
      nameEl.textContent = nm ? nm.textContent : '';

      /* showModal() rather than show(): it is the modal behaviour that
         brings the backdrop and the focus trap. Older browsers without
         <dialog> fall back to confirm() so the button still works. */
      if (typeof dlg.showModal === 'function') {
        dlg.showModal();
      } else if (confirm('Delete this itinerary? This cannot be undone.')) {
        doDelete(btn);
      }
    });
  });

  dlg.addEventListener('close', function () {
    if (dlg.returnValue === 'delete' && pending) {
      doDelete(pending);
    }
    pending = null;
  });

  function doDelete(btn) {
    var body = new FormData();
    body.append('action', 'delete');
    body.append('id', btn.dataset.itin);

    btn.disabled = true;

    fetch('save-itinerary.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.message || 'That did not work.');

        var li = btn.closest('.ai-item');
        if (li) {
          /* Fade out rather than vanish. A row disappearing under the
             cursor leaves you unsure which one went. */
          li.style.transition = 'opacity .25s ease';
          li.style.opacity = '0';
          setTimeout(function () {
            li.remove();
            if (!document.querySelectorAll('.ai-item').length) location.reload();
          }, 250);
        }
        say('Itinerary deleted.');
      })
      .catch(function (e) {
        btn.disabled = false;
        say(e.message || 'Could not delete that plan.', true);
      });
  }
})();
</script>