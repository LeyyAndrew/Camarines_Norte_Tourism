<?php
/* ===================================================================
   admin/gallery.php — CRUD for the photographs on the public gallery.

   Same shape as testimonials.php: the POST handler runs FIRST, before
   any HTML, because every action ends in a redirect and a redirect
   after output is a fatal error. One form at the top handles both add
   and edit — a hidden id decides which.

   WHAT IT MANAGES
     the twelve photographs on gallery.php: upload, replace, recaption,
     reorder, hide, delete
     the wording of the three chapters

   WHAT IT DELIBERATELY DOES NOT
     add or remove a chapter — the page is built around three, and the
       white / grey / white rhythm breaks with four
     the banner clip, the banner poster, the closing strip image —
       those are layout, picked for how the headline sits on top of
       them. A portrait phone photo in the banner slot would wreck it
       without erroring. Overwrite the file, keep the name.

   THE ONLY THING HERE THAT testimonials.php DOES NOT ALSO DO is the
   file handling, and all of that lives in includes/media-guard.php.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../includes/media-guard.php';

/* ===================================================================
   THE CHAPTER MARK

   Each chapter card carries an icon rather than a number.

   WHY NOT THE NUMBER IT USED TO SHOW. "01" only said the chapter was
   first, which the card's position already said — and it was sliced
   off the front of the eyebrow text, so rewording "01 — The coast"
   turned the mark into whatever the first two characters happened to
   be. A picture of open water says something the order cannot.

   KEYED ON slug, NOT ON THE TITLE. The slug is the one field the
   admin panel cannot change, so rewording a heading can never orphan
   its icon. A chapter with an unfamiliar slug falls back to a plain
   frame rather than to nothing.

   ADDING A CHAPTER LATER: add its slug here. Nothing else needs to
   know about it.
   =================================================================== */
function chapter_icon(string $slug): string
{
    $paths = [
        /* open water — three swells, the sea from the shore */
        'coast'  => '<path d="M2 7.5c2.2 0 2.2 1.6 4.4 1.6S8.6 7.5 10.8 7.5s2.2 1.6 4.4 1.6S17.4 7.5 19.6 7.5"/>'
                  . '<path d="M2 12.5c2.2 0 2.2 1.6 4.4 1.6s2.2-1.6 4.4-1.6 2.2 1.6 4.4 1.6 2.2-1.6 4.4-1.6"/>'
                  . '<path d="M2 17.5c2.2 0 2.2 1.6 4.4 1.6s2.2-1.6 4.4-1.6 2.2 1.6 4.4 1.6 2.2-1.6 4.4-1.6"/>',

        /* higher ground — two peaks and the fall between them */
        'inland' => '<path d="M3 19h18"/><path d="m6 19 5-9 3 5.2"/><path d="m12.6 14 3.4-6 5 11"/>'
                  . '<path d="M11 4v3"/>',

        /* the towns — rooftops and a door */
        'towns'  => '<path d="M3 20h18"/><path d="M5 20V9l5-4 5 4v11"/><path d="M15 20v-7l4-2v9"/>'
                  . '<path d="M9 20v-4h2v4"/>',
    ];

    $d = $paths[$slug] ?? '<rect x="4" y="4" width="16" height="16" rx="3"/>';

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* ===================================================================
   WHERE TO SEND THEM BACK TO

   Every action ends in a redirect. Carrying ?set= through it means
   hiding a photograph in the coast chapter returns you to the coast
   chapter, not to the top of the page. You are almost never editing
   exactly one thing.

   Cast to int, so what goes back into the URL can only ever be a
   number — and the script checks it against the real chapters on
   arrival anyway. A set that does not exist simply shows all three.
   =================================================================== */
function back_to(): string
{
    $set = (int) ($_POST['set_ctx'] ?? $_GET['set'] ?? 0);

    return 'gallery.php' . ($set > 0 ? '?set=' . $set : '');
}


/* ---------------------------------------------------------------
   WRITE ACTIONS
   --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $action = $_POST['action'] ?? '';

    /* ---------- create / update ---------- */
    if ($action === 'save') {
        $id    = (int) ($_POST['id'] ?? 0);
        $setId = (int) ($_POST['set_id'] ?? 0);
        $place = trim($_POST['place'] ?? '');
        $town  = trim($_POST['town'] ?? '');
        $alt   = trim($_POST['alt'] ?? '');
        $ratio = $_POST['ratio'] ?? 'ratio-4x3';

        /* Checked against the list rather than trusted. An unknown
           value would render a tile with no aspect ratio at all — the
           database CHECK would catch it, but as a fatal error rather
           than a form message. */
        if (!isset(GALLERY_RATIOS[$ratio])) { $ratio = 'ratio-4x3'; }

        if ($place === '' || $town === '') {
            flash('A place and a town are both required.', 'bad');
            header('Location: gallery.php' . ($id ? '?edit=' . $id : ''));
            exit;
        }

        try {
            /* The chapter has to exist. The foreign key would catch it
               anyway, but a clear sentence beats a database error. */
            $check = $pdo->prepare('SELECT COUNT(*) FROM gallery_sets WHERE id = :id');
            $check->execute([':id' => $setId]);

            if (!$check->fetchColumn()) {
                flash('Choose a chapter for the photograph.', 'bad');
                header('Location: gallery.php' . ($id ? '?edit=' . $id : ''));
                exit;
            }

            if ($id > 0) {
                /* ---- editing an existing row ---- */
                $stmt = $pdo->prepare(
                    'SELECT filename FROM gallery_photos WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);
                $current = $stmt->fetchColumn();

                if ($current === false) {
                    flash('That photograph no longer exists.', 'bad');
                    header('Location: ' . back_to());
                    exit;
                }

                $filename = $current;

                /* A file was chosen as well — swap it, keep the row. */
                if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $filename = save_uploaded_image($_FILES['photo']);

                    /* Only now is the old one removed. Had the upload
                       thrown, the original would still be there —
                       never delete before the replacement is safely
                       on disk. */
                    delete_gallery_file($current);
                }

                $stmt = $pdo->prepare(
                    'UPDATE gallery_photos
                        SET set_id = :set_id, filename = :filename, place = :place,
                            town = :town, alt = :alt, ratio = :ratio
                      WHERE id = :id'
                );
                $stmt->execute([
                    ':set_id'   => $setId,
                    ':filename' => $filename,
                    ':place'    => $place,
                    ':town'     => $town,
                    ':alt'      => $alt,
                    ':ratio'    => $ratio,
                    ':id'       => $id,
                ]);

                flash('Saved ' . $place . '.');

            } else {
                /* ---- a new one ---- */
                $filename = save_uploaded_image($_FILES['photo'] ?? []);

                /* Goes to the end of its chapter. */
                $next = $pdo->prepare(
                    'SELECT COALESCE(MAX(sort_order), 0) + 1
                       FROM gallery_photos WHERE set_id = :set_id'
                );
                $next->execute([':set_id' => $setId]);

                $stmt = $pdo->prepare(
                    'INSERT INTO gallery_photos
                            (set_id, filename, place, town, alt, ratio, sort_order, is_visible)
                     VALUES (:set_id, :filename, :place, :town, :alt, :ratio, :sort_order, true)'
                );
                $stmt->execute([
                    ':set_id'     => $setId,
                    ':filename'   => $filename,
                    ':place'      => $place,
                    ':town'       => $town,
                    ':alt'        => $alt,
                    ':ratio'      => $ratio,
                    ':sort_order' => (int) $next->fetchColumn(),
                ]);

                flash('Published ' . $place . '. It is on the gallery page now.');
            }

        } catch (RuntimeException $e) {
            /* Ours, from media-guard — already written for a reader. */
            flash($e->getMessage(), 'bad');

        } catch (PDOException $e) {
            error_log('gallery save failed: ' . $e->getMessage());
            flash('Could not save that. Check the gallery tables exist.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- reorder within a chapter ---------- */
    if ($action === 'move') {
        $id  = (int) ($_POST['id'] ?? 0);
        $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';

        try {
            $stmt = $pdo->prepare('SELECT set_id, sort_order FROM gallery_photos WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $me_row = $stmt->fetch();

            if ($me_row) {
                /* Find the single neighbour on that side and trade
                   places. Ordering by sort_order then id keeps this
                   correct even if two rows share a sort_order. */
                $sql = $dir === 'up'
                    ? 'SELECT id, sort_order FROM gallery_photos
                        WHERE set_id = :set_id
                          AND (sort_order < :ord OR (sort_order = :ord2 AND id < :id))
                        ORDER BY sort_order DESC, id DESC LIMIT 1'
                    : 'SELECT id, sort_order FROM gallery_photos
                        WHERE set_id = :set_id
                          AND (sort_order > :ord OR (sort_order = :ord2 AND id > :id))
                        ORDER BY sort_order ASC, id ASC LIMIT 1';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':set_id' => $me_row['set_id'],
                    ':ord'    => $me_row['sort_order'],
                    ':ord2'   => $me_row['sort_order'],
                    ':id'     => $id,
                ]);
                $other = $stmt->fetch();

                if ($other) {
                    /* Two UPDATEs that must both happen. Doing one and
                       not the other would leave two photographs
                       claiming the same position. */
                    $pdo->beginTransaction();

                    $swap = $pdo->prepare('UPDATE gallery_photos SET sort_order = :ord WHERE id = :id');
                    $swap->execute([':ord' => (int) $other['sort_order'], ':id' => $id]);
                    $swap->execute([':ord' => (int) $me_row['sort_order'], ':id' => (int) $other['id']]);

                    $pdo->commit();
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('gallery move failed: ' . $e->getMessage());
            flash('Could not move that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- show / hide ---------- */
    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            /* NOT is_visible, not 1 - is_visible: this is a real
               PostgreSQL boolean and arithmetic on one is a type
               error. Same as the testimonials toggle. */
            $stmt = $pdo->prepare('UPDATE gallery_photos SET is_visible = NOT is_visible WHERE id = :id');
            $stmt->execute([':id' => $id]);

            flash('Visibility changed.');
        } catch (PDOException $e) {
            error_log('gallery toggle failed: ' . $e->getMessage());
            flash('Could not change that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            /* Look the filename up rather than accepting one from the
               form. A delete handler that takes a filename from the
               browser is how directory traversal happens. */
            $stmt = $pdo->prepare('SELECT filename, place FROM gallery_photos WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            if ($row) {
                $stmt = $pdo->prepare('DELETE FROM gallery_photos WHERE id = :id');
                $stmt->execute([':id' => $id]);

                delete_gallery_file($row['filename']);

                flash('Deleted ' . $row['place'] . '. The image file is gone too.');
            }
        } catch (PDOException $e) {
            error_log('gallery delete failed: ' . $e->getMessage());
            flash('Could not delete that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- chapter wording ---------- */
    if ($action === 'chapter') {
        $id      = (int) ($_POST['id'] ?? 0);
        $eyebrow = trim($_POST['eyebrow'] ?? '');
        $title   = trim($_POST['title'] ?? '');
        $note    = trim($_POST['note'] ?? '');

        if ($title === '') {
            flash('A chapter needs a heading.', 'bad');
            header('Location: ' . back_to());
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE gallery_sets
                    SET eyebrow = :eyebrow, title = :title, note = :note
                  WHERE id = :id'
            );
            $stmt->execute([
                ':eyebrow' => $eyebrow,
                ':title'   => $title,
                ':note'    => $note,
                ':id'      => $id,
            ]);

            flash('Chapter updated.');
        } catch (PDOException $e) {
            error_log('gallery chapter save failed: ' . $e->getMessage());
            flash('Could not save that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }
}

/* ---------------------------------------------------------------
   READ
   --------------------------------------------------------------- */
$sets      = [];
$photos    = [];
$tableGone = false;

try {
    $sets = $pdo->query(
        'SELECT id, slug, eyebrow, title, note, is_mist::int AS is_mist, sort_order
           FROM gallery_sets
       ORDER BY sort_order, id'
    )->fetchAll();

    /* is_visible::int rather than the raw boolean. PDO's pgsql driver
       hands booleans back in more than one shape depending on the
       build, and one of those shapes is the string 'f' — which is
       TRUE in PHP, because every non-empty string is. Casting in the
       query means every row arrives as 1 or 0 and there is nothing to
       get wrong. */
    $photos = $pdo->query(
        'SELECT id, set_id, filename, place, town, alt, ratio, sort_order,
                is_visible::int AS is_visible, uploaded_at
           FROM gallery_photos
       ORDER BY set_id, sort_order, id'
    )->fetchAll();

} catch (PDOException $e) {
    error_log('gallery list failed: ' . $e->getMessage());
    $tableGone = true;
}

/* Group the photos under their chapter, so the loop below is one pass
   over an array rather than a query per chapter. */
$bySet = [];
foreach ($photos as $row) {
    $bySet[$row['set_id']][] = $row;
}

/* Edit mode, exactly the ?edit= idiom testimonials.php uses. */
$editing = null;
$editId  = (int) ($_GET['edit'] ?? 0);

if ($editId) {
    foreach ($photos as $row) {
        if ((int) $row['id'] === $editId) { $editing = $row; break; }
    }
}

$live   = 0;
$towns  = [];
foreach ($photos as $row) {
    if ($row['is_visible']) {
        $live++;
        $towns[strtolower(trim($row['town']))] = true;
    }
}
$hidden = count($photos) - $live;

$adminTitle   = 'Gallery';
$adminEyebrow = 'Site content';
require __DIR__ . '/_header.php';
?>

<style>
/* Only what is specific to this page. The thumbnail, its crop
   variants and the missing-file note now live in admin.css, because
   the destinations edit drawer needs the same three things and a rule
   two pages need should not sit inside one of them.

   THE CROP IS STILL THE ONE IDEA HERE: the thumbnail is drawn in the
   shape the tile will actually use, so picking "Tall 3:4" reshapes it
   in front of you. Crop is the easiest thing to get wrong and the
   hardest to notice afterwards. */
.adm-shot{ width:78px; }                        /* tighter than the default 104 */
.adm-row--off .adm-shot img{ opacity:.35; }
.adm-file__now{ font-size:.78rem; line-height:1.4; }
.adm-order{ display:flex; gap:.25rem; }
.adm-order .adm-btn{ min-width:2rem; text-align:center; }
</style>

<header class="adm-head">
  <div>
    <span class="adm-eyebrow">Site content</span>
    <h1 class="adm-title">Gallery</h1>
    <p class="adm-sub">Photographs on the public gallery page. Hidden ones stay on the server but come off the page.</p>
  </div>

  <div class="adm-head__actions">
    <button type="button" class="adm-btn" data-drawer="galDrawer">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Add photograph
    </button>
  </div>

</header>

<?php if ($tableGone): ?>
  <p class="adm-flash adm-flash--bad">
    The gallery tables do not exist yet. Run database/gallery.sql in pgAdmin first.
  </p>
<?php endif; ?>

<div class="adm-stats">
  <div class="adm-stat">
    <span class="adm-stat__num"><?= $live ?></span>
    <span class="adm-stat__label">On the page</span>
  </div>

  <div class="adm-stat<?= $hidden ? ' adm-stat--flag' : '' ?>">
    <span class="adm-stat__num"><?= $hidden ?></span>
    <span class="adm-stat__label">Hidden</span>
  </div>

  <div class="adm-stat">
    <span class="adm-stat__num"><?= count($sets) ?></span>
    <span class="adm-stat__label">Chapters</span>
  </div>

  <div class="adm-stat">
    <span class="adm-stat__num"><?= count($towns) ?></span>
    <span class="adm-stat__label">Towns shown</span>
  </div>
</div>

<!-- ============ THE ADD / EDIT DRAWER ============

     One form for both, a hidden id decides which: empty means a new
     photograph, filled means an update. The file input is required
     for a new one and optional for an edit, because an edit that
     changes only the caption should not make you find the original
     file again.

     It slides in from the right rather than sitting at the top of the
     page, so the chapters stay visible behind it — useful when you
     are adding several and want to see where they are landing.

     The is-open class is printed by PHP when ?edit= is set, so an
     Edit link opens straight into the form with no flash of an empty
     page, and the form stays reachable if the script never runs.
     ================================================================ -->
<div class="adm-drawer<?= $editing ? ' is-open' : '' ?>" id="galDrawer"
     role="dialog" aria-modal="true" aria-labelledby="galDrawerTitle"
     <?= $editing ? '' : 'aria-hidden="true"' ?>>
  <div class="adm-drawer__scrim" data-drawer-close></div>

  <div class="adm-drawer__panel">
    <form method="post" class="adm-form" enctype="multipart/form-data">
      <div class="adm-drawer__head">
        <h2 class="adm-drawer__title" id="galDrawerTitle"><?= $editing ? 'Edit photograph' : 'Add a photograph' ?></h2>
        <?php if ($editing): ?>
          <span class="adm-count">#<?= (int) $editing['id'] ?></span>
        <?php endif; ?>
        <button type="button" class="adm-drawer__x" data-drawer-close aria-label="Close">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
      </div>

      <div class="adm-drawer__body">
      <?= csrfField() ?>
      <input type="hidden" name="set_ctx" value="<?= (int) ($_GET['set'] ?? ($editing['set_id'] ?? 0)) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">

      <label class="adm-field">
        <span class="adm-field__label">
          Image file<?= $editing ? ' <em>optional — leave empty to keep the current one</em>' : '' ?>
        </span>

        <?php if ($editing): ?>
          <div class="adm-file">
            <div class="adm-shot adm-shot--<?= e($editing['ratio']) ?>">
              <span class="adm-shot__missing">Missing</span>
              <img src="<?= gallery_url($editing['filename'], '../') ?>" alt=""
                   onerror="this.style.display='none'">
            </div>
            <div class="adm-file__now">
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
              <span class="adm-muted">Currently <?= e($editing['filename']) ?></span>
            </div>
          </div>
        <?php else: ?>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
        <?php endif; ?>
      </label>

      <div class="adm-form__row">
        <label class="adm-field">
          <span class="adm-field__label">Place</span>
          <input type="text" name="place" maxlength="120" required
                 placeholder="Calaguas"
                 value="<?= $editing ? e($editing['place']) : '' ?>">
        </label>

        <label class="adm-field">
          <span class="adm-field__label">Town</span>
          <input type="text" name="town" maxlength="120" required
                 placeholder="Vinzons"
                 value="<?= $editing ? e($editing['town']) : '' ?>">
        </label>

        <label class="adm-field adm-field--narrow">
          <span class="adm-field__label">Crop</span>
          <select name="ratio">
            <?php foreach (GALLERY_RATIOS as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= ($editing ? $editing['ratio'] : 'ratio-4x3') === $key ? ' selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="adm-field adm-field--narrow">
          <span class="adm-field__label">Chapter</span>
          <select name="set_id" required>
            <?php foreach ($sets as $set): ?>
              <option value="<?= (int) $set['id'] ?>"<?= $editing && (int) $editing['set_id'] === (int) $set['id'] ? ' selected' : '' ?>>
                <?= e($set['eyebrow']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label class="adm-field">
        <span class="adm-field__label">Describe the photograph <em>for screen readers</em></span>
        <input type="text" name="alt" maxlength="255"
               placeholder="Fishing boats drawn up on the sand at first light"
               value="<?= $editing ? e($editing['alt']) : '' ?>">
      </label>

      </div><!-- /drawer body -->

      <div class="adm-drawer__foot">
        <?php if ($editing): ?>
          <a href="<?= e(back_to()) ?>" class="adm-btn adm-btn--ghost">Cancel</a>
        <?php else: ?>
          <button type="button" class="adm-btn adm-btn--ghost" data-drawer-close>Cancel</button>
        <?php endif; ?>
        <button type="submit" class="adm-btn"><?= $editing ? 'Save changes' : 'Add photograph' ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ============ LEVEL ONE: THE CHAPTERS ============

     Three cards. Click one and its photographs open in a panel over
     the middle of the screen.

     The same shape as the municipalities on the destinations page,
     and for the same reason: the chapter is how the work is
     organised. You open this panel because the coast section needs a
     new picture, not because the gallery in general does.

     NO COVER IMAGE ON THE CARD. The chapter already has a heading and
     a standfirst written for it — real words that say what the set
     is. A photograph borrowed from inside it would say "this chapter
     is that picture", which is exactly what a chapter is not.
     ================================================================ -->
<div class="adm-towns" id="galSets">
  <?php foreach ($sets as $set):
      $list  = $bySet[$set['id']] ?? [];
      $needs = 0;

      foreach ($list as $ph) {
          if (!$ph['is_visible'] || $ph['filename'] === '') { $needs++; }
      }
  ?>
  <button type="button" class="adm-town" data-set="<?= (int) $set['id'] ?>">
    <span class="adm-town__initial adm-town__initial--icon"><?= chapter_icon($set['slug']) ?></span>

    <span class="adm-town__body">
      <span class="adm-town__name"><?= e($set['title']) ?></span>
      <span class="adm-town__desc"><?= e($set['note']) ?></span>

      <span class="adm-town__foot">
        <?= count($list) ?> photograph<?= count($list) === 1 ? '' : 's' ?>
        <?php if ($set['is_mist']): ?>
          <span class="adm-badge">Grey band</span>
        <?php endif; ?>
        <?php if ($needs): ?>
          <span class="adm-town__flag" title="<?= $needs ?> need attention"><?= $needs ?></span>
        <?php endif; ?>
      </span>
    </span>
  </button>
  <?php endforeach; ?>
</div>


<!-- ============ LEVEL TWO: THE PHOTOGRAPHS ============
     One frosted panel for all three chapters, filtered by the script.
     Every photograph is rendered once; choosing a chapter shows its
     own and hides the rest, so moving between them costs no page
     load. -->
<div class="adm-glass" id="galGlass" role="dialog" aria-modal="true"
     aria-labelledby="galGlassTitle" aria-hidden="true">
  <div class="adm-glass__scrim" data-glass-close></div>

  <div class="adm-glass__panel">
    <div class="adm-glass__head">
      <div>
        <h2 class="adm-glass__title" id="galGlassTitle">Chapter</h2>
        <span class="adm-glass__sub" id="galGlassSub"></span>
      </div>
      <button type="button" class="adm-glass__x" data-glass-close aria-label="Close">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <div class="adm-glass__body">

      <!-- ---------- the chapter's own wording ----------
           It lives here rather than behind its own button in the page
           head, because this is the only screen where you are already
           thinking about this chapter. Three chapters is the design —
           the page reads white, grey, white — so there are fields to
           reword them and no button to make a fourth. -->
      <?php foreach ($sets as $set): ?>
        <form method="post" class="adm-form adm-chapform" data-set="<?= (int) $set['id'] ?>" hidden>
          <?= csrfField() ?>
          <input type="hidden" name="set_ctx" value="<?= (int) $set['id'] ?>">
          <input type="hidden" name="action" value="chapter">
          <input type="hidden" name="id" value="<?= (int) $set['id'] ?>">

          <div class="adm-grid adm-grid--wide">
            <label class="adm-field">
              <span class="adm-field__label">Heading</span>
              <input type="text" name="title" maxlength="160" required value="<?= e($set['title']) ?>">
            </label>

            <label class="adm-field">
              <span class="adm-field__label">Eyebrow</span>
              <input type="text" name="eyebrow" maxlength="60" value="<?= e($set['eyebrow']) ?>">
            </label>
          </div>

          <label class="adm-field">
            <span class="adm-field__label">Standfirst</span>
            <textarea name="note" rows="2"><?= e($set['note']) ?></textarea>
          </label>

          <div class="adm-form__actions">
            <button type="submit" class="adm-btn adm-btn--sm">Save wording</button>
          </div>
        </form>
      <?php endforeach; ?>

      <!-- ---------- the photographs ---------- -->
      <div class="adm-pair" id="galPair">
        <?php foreach ($photos as $i => $row):
            $hasImg = $row['filename'] !== '';
            $order  = $bySet[$row['set_id']] ?? [];

            /* Position within its own chapter — the arrows only ever
               move a photograph inside its own set, so the ends that
               matter are the chapter's ends, not the whole gallery's. */
            $pos = 0;
            foreach ($order as $k => $o) { if ($o['id'] === $row['id']) { $pos = $k; break; } }
        ?>
        <article class="adm-place<?= $row['is_visible'] ? '' : ' adm-place--off' ?>"
                 data-set="<?= (int) $row['set_id'] ?>" hidden>

          <div class="adm-place__shot">
            <?php if ($hasImg): ?>
              <img src="<?= gallery_url($row['filename'], '../') ?>" alt="" loading="lazy"
                   onerror="this.remove()">
            <?php else: ?>
              <span class="adm-place__none">No photograph</span>
            <?php endif; ?>

            <span class="adm-place__marks">
              <?php if (!$row['is_visible']): ?>
                <span class="adm-place__mark adm-place__mark--off">Hidden</span>
              <?php endif; ?>
              <span class="adm-place__mark"><?= e(GALLERY_RATIOS[$row['ratio']] ?? $row['ratio']) ?></span>
            </span>
          </div>

          <div class="adm-place__body">
            <span class="adm-place__name"><?= e($row['place']) ?></span>
            <span class="adm-place__quote"><?= e($row['town']) ?></span>

            <?php if ($row['alt'] !== ''): ?>
              <p class="adm-place__desc"><?= e($row['alt']) ?></p>
            <?php else: ?>
              <p class="adm-place__desc">No description — screen readers get nothing for this one.</p>
            <?php endif; ?>

            <span class="adm-place__pin"><?= e($row['filename']) ?></span>
          </div>

          <div class="adm-place__foot">
            <a class="adm-btn adm-btn--sm" href="gallery.php?edit=<?= (int) $row['id'] ?>&set=<?= (int) $row['set_id'] ?>">Edit</a>

            <form method="post" class="adm-inline">
              <?= csrfField() ?>
              <input type="hidden" name="set_ctx" value="<?= (int) ($_GET['set'] ?? 0) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost">
                <?= $row['is_visible'] ? 'Hide' : 'Show' ?>
              </button>
            </form>

            <form method="post" class="adm-inline">
              <?= csrfField() ?>
              <input type="hidden" name="set_ctx" value="<?= (int) ($_GET['set'] ?? 0) ?>">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="dir" value="up">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost"
                      <?= $pos === 0 ? 'disabled' : '' ?>
                      title="Move earlier" aria-label="Move <?= e($row['place']) ?> earlier">&uarr;</button>
            </form>

            <form method="post" class="adm-inline">
              <?= csrfField() ?>
              <input type="hidden" name="set_ctx" value="<?= (int) ($_GET['set'] ?? 0) ?>">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="dir" value="down">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost"
                      <?= $pos === count($order) - 1 ? 'disabled' : '' ?>
                      title="Move later" aria-label="Move <?= e($row['place']) ?> later">&darr;</button>
            </form>

            <form method="post" class="adm-inline"
                  data-confirm
                  data-confirm-title="Delete <?= e($row['place']) ?>?"
                  data-confirm-body="It comes off the gallery page. To take it off the page but keep it, use Hide instead."
                  data-confirm-note="The image file is deleted from the server."
                  data-confirm-action="Delete permanently">
              <?= csrfField() ?>
              <input type="hidden" name="set_ctx" value="<?= (int) ($_GET['set'] ?? 0) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--danger">Delete</button>
            </form>
          </div>
        </article>
        <?php endforeach; ?>

        <p class="adm-empty" id="galEmpty" hidden>Nothing in this chapter yet. Use Add photograph and pick it.</p>
      </div>
    </div>
  </div>
</div>


<!-- ============ THE SIGN-OFF ============
     Matches admin/destinations.php. The page ends on something for
     the person doing the work rather than on a technical footnote. -->
<section class="adm-panel adm-signoff">
  <div class="adm-panel__body">

    <div>
      <h2 class="adm-signoff__title">Nicely done, <?= e($me['firstname']) ?>.</h2>
      <p class="adm-signoff__text">
        <?= $live ?> photograph<?= $live === 1 ? '' : 's' ?> live across
        <?= count($sets) ?> chapter<?= count($sets) === 1 ? '' : 's' ?>.
        Anything you save is on the page straight away — there is no publish step and
        nothing waiting for approval.
      </p>
    </div>

    <dl class="adm-signoff__notes">
      <div class="adm-signoff__note">
        <dt>Hide, then decide</dt>
        <dd>Hide takes a photograph off the page and keeps the file. Delete does not.
            If it might come back, hide it.</dd>
      </div>

      <div class="adm-signoff__note">
        <dt>Check the crop</dt>
        <dd>The thumbnail is drawn in the shape the tile will use. If it looks cut off
            there, it will look cut off on the page.</dd>
      </div>

      <div class="adm-signoff__note">
        <dt>Not managed here</dt>
        <dd>The banner clip and the closing strip are chosen for how the headline sits
            on them. Overwrite the file in <code>uploads/</code>, same name.</dd>
      </div>
    </dl>

    <p class="adm-signoff__links">
      <a href="../gallery.php" target="_blank" rel="noopener">See the page as visitors do</a>
      <a href="destinations.php">Destinations</a>
      <a href="index.php">Back to the overview</a>
    </p>
  </div>
</section>



<script>
/* ===================================================================
   THE CHAPTER PANEL

   Choosing a chapter shows its photographs and its wording form, and
   hides the other two chapters'. Everything is rendered once, so
   stepping between chapters never costs a page load.

   NOTHING SECURITY-RELATED HAPPENS HERE. Every form already carries
   its CSRF token and a fixed action from PHP; the script only decides
   what is visible. If it never runs you lose the panel, not your
   safety.
   =================================================================== */
(function () {
  var grid  = document.getElementById('galSets');
  var glass = document.getElementById('galGlass');
  if (!grid || !glass) return;

  var cards  = Array.prototype.slice.call(grid.querySelectorAll('.adm-town'));
  var shots  = Array.prototype.slice.call(glass.querySelectorAll('.adm-place'));
  var forms  = Array.prototype.slice.call(glass.querySelectorAll('.adm-chapform'));
  var title  = document.getElementById('galGlassTitle');
  var sub    = document.getElementById('galGlassSub');
  var empty  = document.getElementById('galEmpty');

  var last = null;

  function open(card) {
    var id = card.getAttribute('data-set');
    var n  = 0;

    shots.forEach(function (el) {
      var match = el.getAttribute('data-set') === id;
      el.hidden = !match;
      if (match) n++;
    });

    /* the wording form for this chapter, and only this one */
    forms.forEach(function (f) {
      f.hidden = f.getAttribute('data-set') !== id;
    });

    title.textContent = card.querySelector('.adm-town__name').textContent;
    sub.textContent   = n === 1 ? '1 photograph' : n + ' photographs';
    if (empty) empty.hidden = n > 0;

    last = card;

    glass.classList.add('is-open');
    glass.removeAttribute('aria-hidden');
    document.body.classList.add('adm-locked');

    setSet(id);
    glass.querySelector('[data-glass-close]').focus();
  }

  function close() {
    glass.classList.remove('is-open');
    glass.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('adm-locked');

    setSet(null);
    if (last) last.focus();
  }

  /* The URL remembers which chapter is open, so saving a photograph
     and coming back through the redirect lands you in the chapter you
     were working in rather than at the top. */
  function setSet(id) {
    if (!window.history || !history.replaceState) return;

    var url = new URL(location.href);
    if (id) { url.searchParams.set('set', id); }
    else    { url.searchParams.delete('set'); }

    history.replaceState({}, '', url.pathname + url.search);
  }

  grid.addEventListener('click', function (e) {
    var card = e.target.closest('.adm-town');
    if (card) open(card);
  });

  glass.addEventListener('click', function (e) {
    if (e.target.closest('[data-glass-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || !glass.classList.contains('is-open')) return;

    /* If the confirm dialog or the edit drawer is up, Escape belongs
       to whichever of those is on top. */
    var dlg = document.getElementById('admConfirm');
    if (dlg && dlg.open) return;
    if (document.querySelector('.adm-drawer.is-open')) return;

    close();
  });

  /* Reopen the chapter the URL names — but never while the edit
     drawer is already open, or this panel would sit on top of the
     form you asked for. Same rule as the destinations page. */
  var params = new URLSearchParams(location.search);
  var wanted = params.get('set');

  if (wanted && !params.get('edit')) {
    var card = grid.querySelector('.adm-town[data-set="' + wanted.replace(/"/g, '\\"') + '"]');
    if (card) open(card);
  }
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>