<?php
/* ===================================================================
   admin/testimonials.php — CRUD for the visitor comments.

   One page does all four operations. The POST handler runs FIRST,
   before any HTML is printed, because every action finishes with a
   redirect and a redirect after output is a fatal error.

   THE REDIRECT AFTER EVERY SAVE IS NOT DECORATION. Without it the
   browser sits on the result of a POST, and a refresh re-submits it —
   the same quote inserted twice, the same row deleted twice. Save,
   redirect, then render the fresh list: the pattern is called
   post/redirect/get and it exists precisely to stop that.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';

/* ---------------------------------------------------------------
   WRITE ACTIONS
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $action = $_POST['action'] ?? '';

    /* ---------- create / update ---------- */
    if ($action === 'save') {
        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $hometown = trim($_POST['hometown'] ?? '');
        $quote    = trim($_POST['quote'] ?? '');
        $rating   = (int) ($_POST['rating'] ?? 5);
        $publish  = isset($_POST['is_published']);

        /* Clamped rather than rejected. The database has a CHECK
           constraint on 1-5, and a value outside it would be a fatal
           error rather than a form message. */
        if ($rating < 1) $rating = 1;
        if ($rating > 5) $rating = 5;

        if ($name === '' || $quote === '') {
            flash('A name and a quote are both required.', 'bad');
            header('Location: testimonials.php' . ($id ? '?edit=' . $id : ''));
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE testimonials
                        SET name = :name, hometown = :hometown, rating = :rating,
                            quote = :quote, is_published = :published
                      WHERE id = :id'
                );
                $stmt->execute([
                    ':name'      => $name,
                    ':hometown'  => $hometown !== '' ? $hometown : null,
                    ':rating'    => $rating,
                    ':quote'     => $quote,
                    ':published' => $publish ? 'true' : 'false',
                    ':id'        => $id,
                ]);

                flash('Comment updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO testimonials (name, hometown, rating, quote, is_published)
                     VALUES (:name, :hometown, :rating, :quote, :published)'
                );
                $stmt->execute([
                    ':name'      => $name,
                    ':hometown'  => $hometown !== '' ? $hometown : null,
                    ':rating'    => $rating,
                    ':quote'     => $quote,
                    ':published' => $publish ? 'true' : 'false',
                ]);

                flash($publish ? 'Comment added and published.' : 'Comment added. It is not on the homepage until you publish it.');
            }
        } catch (PDOException $e) {
            error_log('testimonial save failed: ' . $e->getMessage());
            flash('Could not save that. Check the testimonials table exists.', 'bad');
        }

        header('Location: testimonials.php');
        exit;
    }

    /* ---------- publish toggle ---------- */
    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare('UPDATE testimonials SET is_published = NOT is_published WHERE id = :id');
            $stmt->execute([':id' => $id]);

            flash('Visibility changed.');
        } catch (PDOException $e) {
            error_log('testimonial toggle failed: ' . $e->getMessage());
            flash('Could not change that.', 'bad');
        }

        header('Location: testimonials.php');
        exit;
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare('DELETE FROM testimonials WHERE id = :id');
            $stmt->execute([':id' => $id]);

            flash('Comment deleted.');
        } catch (PDOException $e) {
            error_log('testimonial delete failed: ' . $e->getMessage());
            flash('Could not delete that.', 'bad');
        }

        header('Location: testimonials.php');
        exit;
    }
}

/* ---------------------------------------------------------------
   READ
   --------------------------------------------------------------- */
$editing = null;
$editId  = (int) ($_GET['edit'] ?? 0);

$rows      = [];
$tableGone = false;

try {
    /* Unpublished first. The list is a queue as much as an archive,
       and the rows waiting on a decision are the reason you opened
       the page. */
    $rows = $pdo->query(
        'SELECT id, name, hometown, rating, quote, is_published, created_at
           FROM testimonials
       ORDER BY is_published ASC, created_at DESC'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('testimonial list failed: ' . $e->getMessage());
    $tableGone = true;
}

if ($editId) {
    foreach ($rows as $row) {
        if ((int) $row['id'] === $editId) { $editing = $row; break; }
    }
}

$live   = 0;
$starSum = 0;
foreach ($rows as $row) {
    if ($row['is_published']) { $live++; }
    $starSum += (int) $row['rating'];
}
$waiting = count($rows) - $live;
$avg     = $rows ? round($starSum / count($rows), 1) : 0;

/* Initials and a steady avatar colour per name, so the same visitor
   always gets the same disc. Colours are from the admin's own greens
   and earth tones, not a rainbow. */
function tx_initials(string $name): string
{
    $w = preg_split('/\s+/', trim($name)) ?: [''];
    $i = mb_substr($w[0], 0, 1) . (isset($w[1]) ? mb_substr($w[1], 0, 1) : '');
    return mb_strtoupper($i !== '' ? $i : '?');
}

function tx_tone(string $name): int
{
    return abs(crc32(mb_strtolower(trim($name)))) % 5;
}

$adminTitle = 'Comments';
require __DIR__ . '/_header.php';
?>

<style>
/* ===================================================================
   COMMENTS — SUMMARY STRIP, FILTER TABS AND QUOTE CARDS
   Same system as destinations (.dx) and gallery (.gx). A comment is a
   quote, so the card leads with the words; who said it sits beneath.
   Only the state that needs you is marked: a waiting comment gets a
   gold edge and a Publish button up front.
   =================================================================== */
.tx {
  --tx-ink: #14231d; --tx-mute: #5b6b64; --tx-line: #e2e8e4; --tx-card: #fff;
  --tx-green: #1f7a55; --tx-green-soft: #e8f3ed;
  --tx-gold: #94640f; --tx-gold-soft: #fbf0d9; --tx-star: #d69a1f;
}

/* ---------- summary ---------- */
.tx-summary {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
  gap: 16px 32px; margin: 0 0 32px; padding: 20px 24px;
  background: var(--tx-card); border: 1px solid var(--tx-line); border-radius: 14px;
}
.tx-summary__lead { display: flex; align-items: center; gap: 14px; margin: 0; }
.tx-summary__num {
  font-size: 46px; font-weight: 700; line-height: 1; letter-spacing: -.02em;
  color: var(--tx-ink); font-variant-numeric: tabular-nums;
}
.tx-summary__txt { font-size: 15px; font-weight: 600; line-height: 1.35; color: var(--tx-ink); }
.tx-summary__txt span { font-weight: 400; color: var(--tx-mute); }
.tx-summary__checks { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
.tx-check {
  display: inline-flex; align-items: center; gap: 7px; padding: 7px 13px 7px 10px;
  border-radius: 999px; background: var(--tx-green-soft); color: var(--tx-green);
  font-size: 13.5px; font-weight: 500;
}
.tx-check svg { width: 16px; height: 16px; flex: none; }
.tx-check--flag { background: var(--tx-gold-soft); color: var(--tx-gold); font-weight: 600; }
.tx-check--plain { background: #eef1ef; color: var(--tx-ink); }
.tx-check--plain b { color: var(--tx-star); font-weight: 600; }

/* ---------- toolbar + tabs ---------- */
.tx-toolbar {
  display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
  gap: 12px 24px; margin: 0 0 16px;
}
.tx-toolbar__title { margin: 0; font-size: 19px; font-weight: 700; color: var(--tx-ink); }
.tx-toolbar__hint { margin: 3px 0 0; font-size: 14px; color: var(--tx-mute); }
.tx-tabs {
  display: inline-flex; gap: 2px; padding: 3px;
  background: #e9eeeb; border-radius: 10px;
}
.tx-tab {
  display: inline-flex; align-items: center; gap: 6px; margin: 0; padding: 7px 12px;
  border: 0; border-radius: 8px; background: transparent; cursor: pointer;
  font: inherit; font-size: 13.5px; font-weight: 600; color: var(--tx-mute);
}
.tx-tab span {
  min-width: 18px; padding: 0 5px; border-radius: 999px; background: rgba(0,0,0,.06);
  font-size: 12px; text-align: center; font-variant-numeric: tabular-nums;
}
.tx-tab[aria-pressed="true"] { background: #fff; color: var(--tx-ink); box-shadow: 0 1px 3px rgba(0,0,0,.12); }
.tx-tab:focus-visible { outline: 2px solid var(--tx-green); outline-offset: 1px; }

/* ---------- search ---------- */
.tx-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.tx-search { position: relative; width: 260px; max-width: 100%; }
.tx-search svg {
  position: absolute; left: 11px; top: 50%; width: 16px; height: 16px;
  transform: translateY(-50%); color: var(--tx-mute); pointer-events: none;
}
.tx .tx-search input {
  box-sizing: border-box; width: 100%; height: 38px; margin: 0; padding: 0 12px 0 34px;
  border: 1px solid var(--tx-line); border-radius: 10px; background: #fff;
  font: inherit; font-size: 14px; color: var(--tx-ink);
}
.tx .tx-search input:focus { outline: none; border-color: var(--tx-green); box-shadow: 0 0 0 3px rgba(31,122,85,.18); }

/* ---------- the list: one compact row per comment ----------
   Rows, not cards, so fifty comments stay scannable. The quote is
   clamped to two lines and opens in place; pages keep the list short. */
.tx-board {
  margin: 0 0 40px; overflow: hidden;
  background: var(--tx-card); border: 1px solid var(--tx-line); border-radius: 14px;
}
.tx-list { margin: 0; padding: 0; list-style: none; }
.tx-row {
  display: grid; align-items: center; gap: 8px 20px;
  grid-template-columns: minmax(170px, 220px) minmax(0, 1fr) 84px 100px 216px;
  padding: 14px 20px; border-top: 1px solid var(--tx-line);
}
.tx-row:first-child { border-top: 0; }
.tx-row[hidden] { display: none; }
.tx-row:hover { background: #fafbfa; }
.tx-row--waiting { background: #fffcf4; box-shadow: inset 4px 0 0 #e6b54a; }
.tx-row--waiting:hover { background: #fff8e8; }

.tx-who { display: flex; align-items: center; gap: 11px; min-width: 0; }
.tx-who > span:last-child { min-width: 0; }
.tx-avatar {
  display: grid; place-items: center; flex: none; width: 34px; height: 34px;
  border-radius: 50%; font-size: 13px; font-weight: 700; color: #fff;
}
.tx-avatar--0 { background: #1f7a55; }
.tx-avatar--1 { background: #2e6f8e; }
.tx-avatar--2 { background: #9a6a1c; }
.tx-avatar--3 { background: #6b5a8e; }
.tx-avatar--4 { background: #3f5a3a; }
.tx-who__name, .tx-who__meta { display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.tx-who__name { font-size: 14.5px; font-weight: 700; color: var(--tx-ink); }
.tx-who__meta { font-size: 12.5px; color: var(--tx-mute); }

.tx-quote { min-width: 0; }
.tx-quote__text {
  display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
  margin: 0; font-size: 14.5px; line-height: 1.5; color: var(--tx-ink); overflow-wrap: anywhere;
}
.tx-quote.is-open .tx-quote__text { display: block; -webkit-line-clamp: unset; }
.tx-quote__more {
  margin: 2px 0 0; padding: 0; border: 0; background: none; cursor: pointer;
  font: inherit; font-size: 12.5px; font-weight: 600; color: var(--tx-green);
}
.tx-quote__more:focus-visible { outline: 2px solid var(--tx-green); outline-offset: 2px; }

.tx-stars { display: inline-flex; gap: 1px; font-size: 14px; line-height: 1; white-space: nowrap; }
.tx-stars i { font-style: normal; color: var(--tx-star); }
.tx-stars i.is-empty { color: #dfe3e0; }

.tx-status {
  display: inline-flex; align-items: center; gap: 6px; justify-self: start; padding: 3px 10px;
  border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap;
  background: var(--tx-green-soft); color: var(--tx-green);
}
.tx-status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.tx-status--waiting { background: var(--tx-gold-soft); color: var(--tx-gold); }

.tx-actions { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.tx-actions .adm-inline { margin: 0; }
.tx-actions .adm-btn { white-space: nowrap; }

/* ---------- pager ---------- */
.tx-pager {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
  padding: 12px 20px; border-top: 1px solid var(--tx-line); background: #fafbfa;
  font-size: 13.5px; color: var(--tx-mute);
}
.tx-pager[hidden] { display: none; }
.tx-pager__pages { display: flex; flex-wrap: wrap; gap: 4px; }
.tx-pg {
  min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px; cursor: pointer;
  border: 1px solid var(--tx-line); background: #fff; color: var(--tx-ink);
  font: inherit; font-size: 13px; font-weight: 600; font-variant-numeric: tabular-nums;
}
.tx-pg:hover:not(:disabled) { border-color: #b9c9c0; }
.tx-pg[aria-current="page"] { background: var(--tx-green); border-color: var(--tx-green); color: #fff; }
.tx-pg:disabled { opacity: .4; cursor: default; }
.tx-pg--gap { border: 0; background: none; cursor: default; min-width: 16px; padding: 0; }
.tx-pg:focus-visible { outline: 2px solid var(--tx-green); outline-offset: 1px; }

.tx-empty { margin: 0; padding: 36px; text-align: center; color: var(--tx-mute); }
.tx-board > .tx-empty:only-child { border: 0; }

/* Narrow screens: the row folds into a small stacked block. */
@media (max-width: 1100px) {
  .tx-row { grid-template-columns: minmax(0, 1fr) auto; }
  .tx-who     { grid-column: 1; grid-row: 1; }
  .tx-status  { grid-column: 2; grid-row: 1; justify-self: end; }
  .tx-quote   { grid-column: 1 / -1; }
  .tx-stars   { grid-column: 1; }
  .tx-actions { grid-column: 2; }
}
@media (max-width: 560px) {
  .tx-summary { padding: 18px; }
  .tx-summary__num { font-size: 38px; }
  .tx-search { width: 100%; }
  .tx-row { padding: 14px 16px; }
}
</style>

<header class="adm-head">
  <div>
    <span class="adm-eyebrow">Homepage</span>
    <h1 class="adm-title">Comments</h1>
    <p class="adm-sub">Visitor quotes shown on the homepage. Nothing appears there until you tick Published.</p>
  </div>

  <div class="adm-head__actions">
    <button type="button" class="adm-btn" data-drawer="quoteDrawer">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Add comment
    </button>
  </div>
</header>

<?php if ($tableGone): ?>
  <p class="adm-flash adm-flash--bad">
    The testimonials table does not exist yet. Run admin-setup.sql in pgAdmin first.
  </p>
<?php endif; ?>

<!-- ============ THE FORM ============
     One form for both add and edit. A hidden id decides which: empty
     means INSERT, filled means UPDATE. Two near-identical forms would
     be two places to fix every future change. -->
<div class="adm-drawer<?= $editing ? ' is-open' : '' ?>" id="quoteDrawer"
     role="dialog" aria-modal="true" aria-labelledby="quoteDrawerTitle"
     <?= $editing ? '' : 'aria-hidden="true"' ?>>
  <div class="adm-drawer__scrim" data-drawer-close></div>

  <div class="adm-drawer__panel">
    <form method="post" class="adm-form">
      <div class="adm-drawer__head">
        <h2 class="adm-drawer__title" id="quoteDrawerTitle"><?= $editing ? 'Edit comment' : 'Add a comment' ?></h2>
        <?php if ($editing): ?>
          <span class="adm-count">#<?= (int) $editing['id'] ?></span>
        <?php endif; ?>
        <button type="button" class="adm-drawer__x" data-drawer-close aria-label="Close">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
      </div>

      <div class="adm-drawer__body">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">

      <div class="adm-form__row">
        <label class="adm-field">
          <span class="adm-field__label">Name</span>
          <input type="text" name="name" maxlength="100" required
                 value="<?= $editing ? e($editing['name']) : '' ?>">
        </label>

        <label class="adm-field">
          <span class="adm-field__label">Home town <em>optional</em></span>
          <input type="text" name="hometown" maxlength="100"
                 value="<?= $editing ? e($editing['hometown']) : '' ?>">
        </label>

        <label class="adm-field adm-field--narrow">
          <span class="adm-field__label">Rating</span>
          <select name="rating">
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <option value="<?= $s ?>"<?= $editing && (int) $editing['rating'] === $s ? ' selected' : '' ?>>
                <?= str_repeat('★', $s) . str_repeat('☆', 5 - $s) ?>
              </option>
            <?php endfor; ?>
          </select>
        </label>
      </div>

      <label class="adm-field">
        <span class="adm-field__label">Quote</span>
        <textarea name="quote" rows="4" required><?= $editing ? e($editing['quote']) : '' ?></textarea>
      </label>

      <label class="adm-check">
        <input type="checkbox" name="is_published" value="1"
               <?= $editing && $editing['is_published'] ? 'checked' : '' ?>>
        <span>Published — show this on the homepage</span>
      </label>

      </div><!-- /drawer body -->

      <div class="adm-drawer__foot">
        <?php if ($editing): ?>
          <a href="testimonials.php" class="adm-btn adm-btn--ghost">Cancel</a>
        <?php else: ?>
          <button type="button" class="adm-btn adm-btn--ghost" data-drawer-close>Cancel</button>
        <?php endif; ?>
        <button type="submit" class="adm-btn"><?= $editing ? 'Save changes' : 'Add comment' ?></button>
      </div>
    </form>
  </div>
</div>

<div class="tx">

<!-- ============ SUMMARY ============ -->
<section class="tx-summary" aria-label="Summary">
  <p class="tx-summary__lead">
    <span class="tx-summary__num"><?= $live ?></span>
    <span class="tx-summary__txt">
      comment<?= $live === 1 ? '' : 's' ?> on the homepage<br>
      <span><?= count($rows) ?> in total</span>
    </span>
  </p>

  <ul class="tx-summary__checks">
    <li class="tx-check<?= $waiting ? ' tx-check--flag' : '' ?>">
      <?= $waiting ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.8 2.8L16.5 9.5"/></svg>' ?>
      <?= $waiting ? $waiting . ' waiting for a decision' : 'Nothing waiting' ?>
    </li>
    <?php if ($rows): ?>
      <li class="tx-check tx-check--plain">
        <b>&#9733;</b> <?= number_format($avg, 1) ?> average rating
      </li>
    <?php endif; ?>
  </ul>
</section>

<!-- ============ THE LIST ============
     Cards instead of a table: a comment is a quote first, so the words
     lead and the name sits under them, the way the homepage shows it.
     Waiting comments are sorted first by the query and carry a gold
     edge, with Publish as their first button.

     The tabs only filter what is already on the page. Without the
     script every card simply shows. -->
<div class="tx-toolbar">
  <div>
    <h2 class="tx-toolbar__title">All comments</h2>
    <p class="tx-toolbar__hint">Waiting comments are listed first.</p>
  </div>

  <?php if ($rows): ?>
    <div class="tx-tools">
      <label class="tx-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" id="txFind" placeholder="Search name, town or words" aria-label="Search comments" autocomplete="off">
      </label>

      <div class="tx-tabs" role="group" aria-label="Filter comments">
        <button type="button" class="tx-tab" data-filter="all" aria-pressed="true">All <span><?= count($rows) ?></span></button>
        <button type="button" class="tx-tab" data-filter="waiting" aria-pressed="false">Waiting <span><?= $waiting ?></span></button>
        <button type="button" class="tx-tab" data-filter="live" aria-pressed="false">Published <span><?= $live ?></span></button>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="tx-board">
<?php if (!$rows): ?>
  <p class="tx-empty">No comments yet. Use Add comment to put the first one on the homepage.</p>
<?php else: ?>
  <ul class="tx-list" id="txList">
    <?php foreach ($rows as $row):
        $isLive = (bool) $row['is_published'];
        $stars  = max(0, min(5, (int) $row['rating']));
        $find   = mb_strtolower($row['name'] . ' ' . ($row['hometown'] ?? '') . ' ' . $row['quote']);
    ?>
    <li class="tx-row<?= $isLive ? '' : ' tx-row--waiting' ?>"
        data-status="<?= $isLive ? 'live' : 'waiting' ?>" data-find="<?= e($find) ?>">

      <div class="tx-who">
        <span class="tx-avatar tx-avatar--<?= tx_tone($row['name']) ?>" aria-hidden="true"><?= e(tx_initials($row['name'])) ?></span>
        <span>
          <span class="tx-who__name" title="<?= e($row['name']) ?>"><?= e($row['name']) ?></span>
          <span class="tx-who__meta">
            <?php if ($row['hometown']): ?><?= e($row['hometown']) ?>, <?php endif; ?><?= e(fmtDate($row['created_at'])) ?>
          </span>
        </span>
      </div>

      <div class="tx-quote">
        <p class="tx-quote__text"><?= e($row['quote']) ?></p>
        <button type="button" class="tx-quote__more" aria-expanded="false" hidden>Show all</button>
      </div>

      <span class="tx-stars" role="img" aria-label="<?= $stars ?> out of 5 stars">
        <?php for ($k = 1; $k <= 5; $k++): ?><i class="<?= $k <= $stars ? '' : 'is-empty' ?>" aria-hidden="true">&#9733;</i><?php endfor; ?>
      </span>

      <span class="tx-status<?= $isLive ? '' : ' tx-status--waiting' ?>">
        <?= $isLive ? 'Published' : 'Waiting' ?>
      </span>

      <div class="tx-actions">
        <form method="post" class="adm-inline">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
          <button type="submit" class="adm-btn adm-btn--sm<?= $isLive ? ' adm-btn--ghost' : '' ?>">
            <?= $isLive ? 'Hide' : 'Publish' ?>
          </button>
        </form>

        <a href="testimonials.php?edit=<?= (int) $row['id'] ?>" class="adm-btn adm-btn--sm adm-btn--ghost">Edit</a>

        <!-- No undo and no soft delete, so the confirm dialog is the only
             thing between a misclick and a lost row. -->
        <form method="post" class="adm-inline"
              data-confirm
              data-confirm-title="Delete this comment?"
              data-confirm-body="It comes off the homepage. To take it down but keep it, use Hide instead."
              data-confirm-note="There is no undo and no soft delete for comments."
              data-confirm-action="Delete permanently">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
          <button type="submit" class="adm-btn adm-btn--sm adm-btn--danger">Delete</button>
        </form>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>

  <p class="tx-empty" id="txNone" hidden>No comments match. Try another tab or search.</p>

  <nav class="tx-pager" id="txPager" aria-label="Comment pages" hidden>
    <span id="txRange"></span>
    <span class="tx-pager__pages" id="txPages"></span>
  </nav>
<?php endif; ?>
</div>

<script>
/* ===================================================================
   FILTER, SEARCH, PAGES, "SHOW ALL"
   All client-side over rows PHP already printed. Without the script
   every row shows and each quote is clamped — nothing breaks.
   =================================================================== */
(function () {
  var list = document.getElementById('txList');
  if (!list) return;

  var PER   = 10;
  var rows  = Array.prototype.slice.call(list.querySelectorAll('.tx-row'));
  var tabs  = document.querySelectorAll('.tx-tab');
  var find  = document.getElementById('txFind');
  var none  = document.getElementById('txNone');
  var pager = document.getElementById('txPager');
  var range = document.getElementById('txRange');
  var pages = document.getElementById('txPages');

  var filter = 'all';
  var page   = 1;

  function matches(r) {
    var q = find ? find.value.trim().toLowerCase() : '';
    return (filter === 'all' || r.getAttribute('data-status') === filter)
        && (!q || (r.getAttribute('data-find') || '').indexOf(q) !== -1);
  }

  function pgButton(label, target, opts) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'tx-pg';
    b.textContent = label;
    if (opts && opts.label) b.setAttribute('aria-label', opts.label);
    if (opts && opts.current) b.setAttribute('aria-current', 'page');
    if (opts && opts.disabled) b.disabled = true;
    b.addEventListener('click', function () {
      page = target;
      render();
      list.closest('.tx-board').scrollIntoView({ block: 'nearest' });
    });
    return b;
  }

  function gap() {
    var s = document.createElement('span');
    s.className = 'tx-pg tx-pg--gap';
    s.textContent = '…';
    return s;
  }

  function render() {
    var hits  = rows.filter(matches);
    var total = Math.max(1, Math.ceil(hits.length / PER));
    if (page > total) page = total;

    var from = (page - 1) * PER;
    var to   = from + PER;

    rows.forEach(function (r) { r.hidden = true; });
    hits.slice(from, to).forEach(function (r) { r.hidden = false; });

    none.hidden  = hits.length !== 0;
    pager.hidden = hits.length <= PER;

    if (!pager.hidden) {
      range.textContent = 'Showing ' + (from + 1) + '–' + Math.min(to, hits.length) + ' of ' + hits.length;
      pages.textContent = '';
      pages.appendChild(pgButton('‹', page - 1, { label: 'Previous page', disabled: page === 1 }));

      /* First, last, and two either side of the current page. */
      var last = 0;
      for (var p = 1; p <= total; p++) {
        if (p === 1 || p === total || Math.abs(p - page) <= 2) {
          if (last && p - last > 1) pages.appendChild(gap());
          pages.appendChild(pgButton(String(p), p, { current: p === page, label: 'Page ' + p }));
          last = p;
        }
      }

      pages.appendChild(pgButton('›', page + 1, { label: 'Next page', disabled: page === total }));
    }

    checkClamps();
  }

  /* "Show all" appears only on quotes actually cut off at two lines. */
  function checkClamps() {
    rows.forEach(function (r) {
      if (r.hidden) return;
      var box  = r.querySelector('.tx-quote');
      var text = box.querySelector('.tx-quote__text');
      var more = box.querySelector('.tx-quote__more');
      if (box.classList.contains('is-open')) { more.hidden = false; return; }
      more.hidden = text.scrollHeight <= text.clientHeight + 1;
    });
  }

  list.addEventListener('click', function (e) {
    var more = e.target.closest('.tx-quote__more');
    if (!more) return;
    var box  = more.parentNode;
    var open = box.classList.toggle('is-open');
    more.setAttribute('aria-expanded', open ? 'true' : 'false');
    more.textContent = open ? 'Show less' : 'Show all';
  });

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      filter = tab.getAttribute('data-filter');
      page = 1;
      tabs.forEach(function (t) { t.setAttribute('aria-pressed', t === tab ? 'true' : 'false'); });
      render();
    });
  });

  if (find) {
    find.addEventListener('input', function () { page = 1; render(); });
  }

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(checkClamps, 150);
  });

  render();
})();
</script>

</div><!-- /.tx -->


<?php require __DIR__ . '/_footer.php'; ?>