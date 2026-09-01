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

$live = 0;
foreach ($rows as $row) {
    if ($row['is_published']) { $live++; }
}

$adminTitle = 'Comments';
require __DIR__ . '/_header.php';
?>

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

  <p class="adm-head__aside"><?= $live ?> live · <?= count($rows) - $live ?> waiting</p>
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

<!-- ============ THE LIST ============ -->
<section class="adm-panel">
  <div class="adm-panel__head">
    <h2 class="adm-panel__title">All comments <span class="adm-count"><?= count($rows) ?></span></h2>
  </div>

  <?php if (!$rows): ?>
    <p class="adm-empty">No comments yet. Add the first one using the form above.</p>
  <?php else: ?>
    <div class="adm-panel__body adm-panel__body--flush">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Quote</th>
            <th>Rating</th>
            <th>Status</th>
            <th class="adm-table__right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <strong><?= e($row['name']) ?></strong>
                <?php if ($row['hometown']): ?>
                  <span class="adm-muted"><?= e($row['hometown']) ?></span>
                <?php endif; ?>
                <span class="adm-muted"><?= e(fmtDate($row['created_at'])) ?></span>
              </td>

              <td class="adm-table__quote"><?= e($row['quote']) ?></td>

              <td class="adm-stars" title="<?= (int) $row['rating'] ?> out of 5"><?= str_repeat('★', (int) $row['rating']) ?></td>

              <td>
                <span class="adm-badge<?= $row['is_published'] ? ' adm-badge--live' : '' ?>">
                  <?= $row['is_published'] ? 'Published' : 'Hidden' ?>
                </span>
              </td>

              <td class="adm-table__right">
                <div class="adm-actions">
                  <a href="testimonials.php?edit=<?= (int) $row['id'] ?>" class="adm-btn adm-btn--sm adm-btn--ghost">Edit</a>

                  <form method="post" class="adm-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost">
                      <?= $row['is_published'] ? 'Hide' : 'Publish' ?>
                    </button>
                  </form>

                  <!-- The confirm() is the only thing between a misclick
                       and a row that is gone for good. There is no undo
                       here and no soft delete. -->
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
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>