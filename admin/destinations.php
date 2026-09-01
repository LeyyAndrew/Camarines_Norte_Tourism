<?php
/* ===================================================================
   admin/destinations.php — CRUD for the 24 destinations.

   Same shape as testimonials.php and gallery.php: POST handler first,
   one form for both add and edit via ?edit=, redirect after every
   write.

   WHAT EDITING ONE OF THESE TOUCHES. More than it looks. Both
   homepage.php and destinations.php read
   includes/destinations-data.php, and destinations.php builds its
   hero slides, its rail, and the $mapPoints JSON out of that same
   array. So one save here updates:

     the card on the destinations page
     the grouping by municipality
     the hero rail and its slides
     the map pin
     the hover balloon on that pin
     the detail sheet the pin opens
     the homepage's destination section

   That is a lot of surface for one form, which is exactly why the
   validation below is stricter than the gallery's.

   WHAT IT DOES NOT MANAGE
     includes/destination-details.php — the how / eat / book sections
       inside the detail sheet. Still a PHP file, still keyed by name.
       See the warning next to the name field before renaming
       anything.
     the two introduction photographs and the outro background on
       destinations.php, which are set by filename in that page.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../includes/media-guard.php';

/* Destination photos live in their own folder, not the gallery's. */
const DEST_DIR_NAME = 'Destination-Photo';

function dest_dir(): string
{
    return dirname(__DIR__) . '/uploads/' . DEST_DIR_NAME;
}

function dest_url(string $filename, string $prefix = ''): string
{
    return e($prefix . 'uploads/' . DEST_DIR_NAME . '/' . rawurlencode(basename($filename)));
}

/* ===================================================================
   WHERE TO SEND THEM BACK TO

   Every action ends in a redirect. Carrying ?town= through it means
   saving a place in Labo returns you to Labo rather than to the top
   of the province — which matters, because you are almost never
   editing exactly one thing.

   The value is echoed back into a URL, so it is urlencoded here and
   checked against the real list by the script on arrival. A town that
   does not exist simply shows all twelve.
   =================================================================== */
function back_to(): string
{
    $town = trim($_POST['town_ctx'] ?? $_GET['town'] ?? '');

    return 'destinations.php' . ($town !== '' ? '?town=' . rawurlencode($town) : '');
}

/* ===================================================================
   THE MUNICIPAL SEAL

   uploads/town-logo/<slug>.<ext>, where the slug is the town name
   lowercased with hyphens for spaces — the same dest_slug() the rest
   of this page uses:

     labo.png
     jose-panganiban.png
     san-lorenzo-ruiz.png
     santa-elena.png

   Four extensions are tried, so a PNG seal with a transparent
   background needs no converting. Nothing found means the card shows
   the town's initials instead — never a broken image.

   NO PHOTOGRAPH FALLBACK. An earlier version borrowed a picture from
   one of the town's own destinations so the grid never looked empty.
   That is the wrong instinct once a seal goes here: a seal and a
   photograph in the same slot fight, and the seal loses.
   =================================================================== */
/* ONE PLACE THE FOLDER IS NAMED. Deliberately not uploads/towns/,
   which already exists in this project and holds photographs the
   public side uses — a seal and a photograph of a town are different
   things and should not share a folder where one can overwrite the
   other. Move the folder and this line is the only edit. */
const TOWN_LOGO_DIR = 'uploads/town-logo/';

function town_seal(string $town, string $prefix = ''): ?string
{
    $slug = dest_slug($town);
    $dir  = dirname(__DIR__) . '/' . TOWN_LOGO_DIR;

    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        if (is_file($dir . $slug . '.' . $ext)) {
            return e($prefix . TOWN_LOGO_DIR . rawurlencode($slug . '.' . $ext));
        }
    }

    return null;
}

/** Absolute path to the seal folder on disk. */
function towns_dir(): string
{
    return rtrim(dirname(__DIR__) . '/' . TOWN_LOGO_DIR, '/');
}

/**
 * Save an uploaded municipal seal as uploads/town-logo/<slug>.<ext>.
 *
 * WHY THIS DOES NOT JUST CALL save_uploaded_image() AND STOP. That
 * function deliberately invents a random filename, which is exactly
 * right for a photograph — nothing of the original name survives, and
 * two uploads can never collide. But a seal has to be findable by
 * town_seal(), which looks up "labo.png" and nothing else. So the
 * name has to be predictable.
 *
 * The order matters, and it is the whole trick:
 *
 *   1. save_uploaded_image() runs FIRST, in full. Every check happens
 *      exactly as it does for a destination photograph — is it really
 *      an image, is it under 8 MB, did PHP itself put it there.
 *   2. Only after it has passed all of that, and is already on disk
 *      under a random name, do we rename it.
 *
 * So the predictable name is applied to a file that has already been
 * proven safe. Nothing from the browser ever reaches the filesystem:
 * the slug comes from the town name in our own database, and the
 * extension comes from the image's own bytes.
 */
function save_town_seal(array $file, string $town): string
{
    $slug = dest_slug($town);

    if ($slug === '') {
        throw new RuntimeException('That municipality has no usable name.');
    }

    /* full validation, random name, already written to towns/ */
    $tmpName = save_uploaded_image($file, towns_dir());
    $ext     = pathinfo($tmpName, PATHINFO_EXTENSION);

    /* Clear every other extension for this town first, or uploading a
       PNG over an old JPG leaves both and town_seal() keeps finding
       the stale one — png is checked before jpg. */
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $old) {
        $path = towns_dir() . '/' . $slug . '.' . $old;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    if (!rename(towns_dir() . '/' . $tmpName, towns_dir() . '/' . $slug . '.' . $ext)) {
        /* Leave nothing behind if the rename fails. */
        delete_gallery_file($tmpName, towns_dir());
        throw new RuntimeException('The seal could not be saved. Check the folder permissions.');
    }

    return $slug . '.' . $ext;
}

/* ===================================================================
   WHAT EACH MUNICIPALITY IS

   One line per town, so a card is a place rather than a label. Every
   line is drawn from the destinations already in that municipality —
   nothing here claims anything the site does not already say.

   ⚠ EDIT THESE FREELY. They are the one piece of copy in this panel
   that is not in the database, because they describe the province
   rather than the content, and they will not change when someone adds
   a photograph. A town with no line here simply shows its count.
   =================================================================== */
const TOWN_LINES = [
    'Basud'            => 'The first coastal stop heading north out of Daet.',
    'Capalonga'        => 'A pilgrim town on the western coast, with islands offshore.',
    'Daet'             => 'The capital: the surf break at Bagasbas and the first Rizal monument.',
    'Jose Panganiban'  => 'A working bay, a view deck above it, and a lighthouse island.',
    'Labo'             => 'Inland country. Falls off the highway and the climb up Mt. Bagacay.',
    'Mercedes'         => 'The fishing port, and the islands lying off it.',
    'Paracale'         => 'Gold country, with a long shoreline and an island offshore.',
    'San Lorenzo Ruiz' => 'Upland falls, and a river people swim in.',
    'San Vicente'      => 'Mananap Falls, reached on foot or by ATV.',
    'Santa Elena'      => 'The far northern edge of the province.',
    'Talisay'          => 'A mangrove boardwalk, and the parish church at the town centre.',
    'Vinzons'          => 'Calaguas, and the climb above the coast that looks out to it.',
];

/* The slug destinations-map.js and the card anchors use. Copied from
   destSlug() in destinations.php so the admin can show you the slug a
   name will produce before you commit to it. */
function dest_slug(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
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
        $name  = trim($_POST['name'] ?? '');
        $town  = trim($_POST['town'] ?? '');
        $tag   = trim($_POST['tag'] ?? '');
        $quote = trim($_POST['quote'] ?? '');
        $descr = trim($_POST['descr'] ?? '');

        $chips = [
            trim($_POST['chip1'] ?? ''),
            trim($_POST['chip2'] ?? ''),
            trim($_POST['chip3'] ?? ''),
        ];

        /* Coordinates. Blank is allowed and means no pin — the map
           skips it. Anything else has to be a number, because a
           half-typed coordinate saved as 0 puts the pin in the
           Atlantic without complaining. */
        $latRaw = trim($_POST['lat'] ?? '');
        $lngRaw = trim($_POST['lng'] ?? '');

        if (($latRaw === '') !== ($lngRaw === '')) {
            flash('A pin needs both a latitude and a longitude, or neither.', 'bad');
            header('Location: ' . ($id ? 'destinations.php?edit=' . $id : back_to()));
            exit;
        }

        $lat = $lng = null;

        if ($latRaw !== '') {
            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                flash('Coordinates must be numbers, like 14.136808 and 122.983065.', 'bad');
                header('Location: ' . ($id ? 'destinations.php?edit=' . $id : back_to()));
                exit;
            }

            $lat = (float) $latRaw;
            $lng = (float) $lngRaw;

            /* The same box as the CHECK constraint. Caught here so the
               admin reads a sentence instead of a constraint name. */
            if ($lat < 13.5 || $lat > 15.0 || $lng < 122.0 || $lng > 123.5) {
                flash('That pin is outside Camarines Norte. Check the latitude and longitude are not swapped.', 'bad');
                header('Location: ' . ($id ? 'destinations.php?edit=' . $id : back_to()));
                exit;
            }
        }

        if ($name === '' || $town === '' || $tag === '') {
            flash('Name, municipality and label are all required.', 'bad');
            header('Location: ' . ($id ? 'destinations.php?edit=' . $id : back_to()));
            exit;
        }

        if (dest_slug($name) === '') {
            flash('That name has no letters or numbers in it, so it cannot be linked to.', 'bad');
            header('Location: ' . ($id ? 'destinations.php?edit=' . $id : back_to()));
            exit;
        }

        try {
            /* Name must be unique — the slug is derived from it, and
               two destinations with the same slug would collide in the
               detail sheet and in the card anchors. Checked here for a
               readable message; the unique index is what guarantees
               it. */
            $dupe = $pdo->prepare(
                'SELECT COUNT(*) FROM destinations WHERE lower(name) = lower(:name) AND id <> :id'
            );
            $dupe->execute([':name' => $name, ':id' => $id]);

            if ($dupe->fetchColumn()) {
                flash('There is already a destination called ' . $name . '.', 'bad');
                header('Location: ' . ($id ? 'destinations.php?edit=' . $id : back_to()));
                exit;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT filename, name FROM destinations WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $current = $stmt->fetch();

                if (!$current) {
                    flash('That destination no longer exists.', 'bad');
                    header('Location: ' . back_to());
                    exit;
                }

                $filename = $current['filename'];

                if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $filename = save_uploaded_image($_FILES['photo'], dest_dir());

                    /* The old file goes only after the new one is
                       safely written. If the upload had thrown we
                       would still have the original. */
                    if ($current['filename'] !== '') {
                        delete_gallery_file($current['filename'], dest_dir());
                    }
                }

                $stmt = $pdo->prepare(
                    'UPDATE destinations
                        SET filename = :filename, name = :name, town = :town, tag = :tag,
                            quote = :quote, descr = :descr,
                            chip1 = :chip1, chip2 = :chip2, chip3 = :chip3,
                            lat = :lat, lng = :lng, updated_at = NOW()
                      WHERE id = :id'
                );
                $stmt->execute([
                    ':filename' => $filename,
                    ':name'     => $name,
                    ':town'     => $town,
                    ':tag'      => $tag,
                    ':quote'    => $quote,
                    ':descr'    => $descr,
                    ':chip1'    => $chips[0],
                    ':chip2'    => $chips[1],
                    ':chip3'    => $chips[2],
                    ':lat'      => $lat,
                    ':lng'      => $lng,
                    ':id'       => $id,
                ]);

                /* Renaming breaks the link to the long-form content,
                   which is keyed by name in a PHP file this panel does
                   not touch. Say so rather than let it fail quietly
                   weeks later. */
                if ($current['name'] !== $name) {
                    flash('Saved. NOTE: you renamed this, so update the key "' . $current['name']
                        . '" to "' . $name . '" in includes/destination-details.php or its '
                        . 'how-to-get-there and booking sections will stop appearing.', 'bad');
                } else {
                    flash('Saved ' . $name . '.');
                }

            } else {
                $filename = '';

                /* A photo is optional on create — every page already
                   draws the gradient placeholder when there is none,
                   and "add the place now, photo when it arrives" is a
                   real workflow. */
                if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $filename = save_uploaded_image($_FILES['photo'], dest_dir());
                }

                $next = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM destinations');

                $stmt = $pdo->prepare(
                    'INSERT INTO destinations
                            (filename, name, town, tag, quote, descr,
                             chip1, chip2, chip3, lat, lng, sort_order, is_visible)
                     VALUES (:filename, :name, :town, :tag, :quote, :descr,
                             :chip1, :chip2, :chip3, :lat, :lng, :sort_order, true)'
                );
                $stmt->execute([
                    ':filename'   => $filename,
                    ':name'       => $name,
                    ':town'       => $town,
                    ':tag'        => $tag,
                    ':quote'      => $quote,
                    ':descr'      => $descr,
                    ':chip1'      => $chips[0],
                    ':chip2'      => $chips[1],
                    ':chip3'      => $chips[2],
                    ':lat'        => $lat,
                    ':lng'        => $lng,
                    ':sort_order' => (int) $next->fetchColumn(),
                ]);

                flash('Added ' . $name . '. It is on the destinations page and the map now.');
            }

        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'bad');
        } catch (PDOException $e) {
            error_log('destination save failed: ' . $e->getMessage());
            flash('Could not save that. Check the destinations table exists.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- reorder ---------- */
    if ($action === 'move') {
        $id  = (int) ($_POST['id'] ?? 0);
        $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';

        try {
            $stmt = $pdo->prepare('SELECT sort_order FROM destinations WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $ord = $stmt->fetchColumn();

            if ($ord !== false) {
                $sql = $dir === 'up'
                    ? 'SELECT id, sort_order FROM destinations
                        WHERE sort_order < :ord OR (sort_order = :ord2 AND id < :id)
                        ORDER BY sort_order DESC, id DESC LIMIT 1'
                    : 'SELECT id, sort_order FROM destinations
                        WHERE sort_order > :ord OR (sort_order = :ord2 AND id > :id)
                        ORDER BY sort_order ASC, id ASC LIMIT 1';

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':ord' => $ord, ':ord2' => $ord, ':id' => $id]);
                $other = $stmt->fetch();

                if ($other) {
                    $pdo->beginTransaction();
                    $swap = $pdo->prepare('UPDATE destinations SET sort_order = :ord WHERE id = :id');
                    $swap->execute([':ord' => (int) $other['sort_order'], ':id' => $id]);
                    $swap->execute([':ord' => (int) $ord, ':id' => (int) $other['id']]);
                    $pdo->commit();
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('destination move failed: ' . $e->getMessage());
            flash('Could not move that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- show / hide ---------- */
    /* ---------- the municipal seal ---------- */
    if ($action === 'seal') {
        $town = trim($_POST['town'] ?? '');

        /* The town must be one we actually have. Without this, a
           crafted post could name anything and write a file called
           after it into the seal folder. dest_slug() strips the
           dangerous characters anyway, but checking the name against
           the real list is the answer that does not depend on that
           being perfect. */
        $check = $pdo->prepare('SELECT COUNT(*) FROM destinations WHERE town = :t');
        $check->execute([':t' => $town]);

        if (!$check->fetchColumn()) {
            flash('That municipality does not exist.', 'bad');
            header('Location: ' . back_to());
            exit;
        }

        try {
            /* Remove, rather than replace. */
            if (isset($_POST['remove'])) {
                $slug = dest_slug($town);

                foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
                    $path = towns_dir() . '/' . $slug . '.' . $ext;
                    if (is_file($path)) { @unlink($path); }
                }

                flash('Seal removed from ' . $town . '.');
            } else {
                save_town_seal($_FILES['seal'] ?? [], $town);
                flash('Seal updated for ' . $town . '.');
            }

        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'bad');
        } catch (Throwable $e) {
            error_log('town seal failed: ' . $e->getMessage());
            flash('Could not save that seal.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare('UPDATE destinations SET is_visible = NOT is_visible WHERE id = :id');
            $stmt->execute([':id' => $id]);
            flash('Visibility changed.');
        } catch (PDOException $e) {
            error_log('destination toggle failed: ' . $e->getMessage());
            flash('Could not change that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare('SELECT filename, name FROM destinations WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            if ($row) {
                $stmt = $pdo->prepare('DELETE FROM destinations WHERE id = :id');
                $stmt->execute([':id' => $id]);

                if ($row['filename'] !== '') {
                    delete_gallery_file($row['filename'], dest_dir());
                }

                flash('Deleted ' . $row['name'] . '. Its entry in destination-details.php is now orphaned '
                    . 'and can be removed too.');
            }
        } catch (PDOException $e) {
            error_log('destination delete failed: ' . $e->getMessage());
            flash('Could not delete that.', 'bad');
        }

        header('Location: ' . back_to());
        exit;
    }
}

/* ---------------------------------------------------------------
   READ
   --------------------------------------------------------------- */
$rows      = [];
$tableGone = false;

try {
    $rows = $pdo->query(
        'SELECT id, filename, name, town, tag, quote, descr,
                chip1, chip2, chip3, lat, lng, sort_order,
                is_visible::int AS is_visible
           FROM destinations
       ORDER BY sort_order, id'
    )->fetchAll();
} catch (PDOException $e) {
    error_log('destination list failed: ' . $e->getMessage());
    $tableGone = true;
}

$editing = null;
$editId  = (int) ($_GET['edit'] ?? 0);

if ($editId) {
    foreach ($rows as $row) {
        if ((int) $row['id'] === $editId) { $editing = $row; break; }
    }
}

/* Group by municipality for display, keeping the stored order. This
   mirrors what destinations.php does, so the panel reads in the same
   order as the page it controls. */
$byTown  = [];
$live    = 0;
$noPin   = 0;
$noPhoto = 0;

foreach ($rows as $row) {
    $byTown[$row['town']][] = $row;

    if ($row['is_visible']) { $live++; }
    if ($row['lat'] === null) { $noPin++; }
    if ($row['filename'] === '') { $noPhoto++; }
}

/* Existing values, so the admin picks from what is already in use
   rather than inventing a thirteenth spelling of a municipality. */
$towns = array_keys($byTown);
sort($towns);

$tags = [];
foreach ($rows as $row) { $tags[$row['tag']] = true; }
$tags = array_keys($tags);
sort($tags);

$adminTitle   = 'Destinations';
$adminEyebrow = 'Site content';
require __DIR__ . '/_header.php';
?>



<header class="adm-head">
  <div>
    <span class="adm-eyebrow">Site content</span>
    <h1 class="adm-title">Destinations</h1>
    <p class="adm-sub">The places on the destinations page, the homepage rail, and the map. One save updates all three.</p>
  </div>

  <div class="adm-head__actions">
    <!-- The one primary action on this screen, in the same place it
         sits on every other screen. It opens the drawer; the form
         itself is no longer parked permanently at the top of the
         page, where it pushed the list you came to read below the
         fold on every visit. -->
    <button type="button" class="adm-btn" data-drawer="destDrawer">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Add destination
    </button>
  </div>

</header>

<?php if ($tableGone): ?>
  <p class="adm-flash adm-flash--bad">
    The destinations table does not exist yet. Run database/destinations.sql in pgAdmin first.
  </p>
<?php endif; ?>

<div class="adm-stats">
  <div class="adm-stat">
    <span class="adm-stat__num"><?= $live ?></span>
    <span class="adm-stat__label">On the site</span>
  </div>

  <div class="adm-stat<?= count($rows) - $live ? ' adm-stat--flag' : '' ?>">
    <span class="adm-stat__num"><?= count($rows) - $live ?></span>
    <span class="adm-stat__label">Hidden</span>
  </div>

  <div class="adm-stat">
    <span class="adm-stat__num"><?= count($byTown) ?></span>
    <span class="adm-stat__label">Municipalities</span>
  </div>

  <div class="adm-stat<?= $noPin ? ' adm-stat--flag' : '' ?>">
    <span class="adm-stat__num"><?= $noPin ?></span>
    <span class="adm-stat__label">No map pin</span>
  </div>

  <div class="adm-stat<?= $noPhoto ? ' adm-stat--flag' : '' ?>">
    <span class="adm-stat__num"><?= $noPhoto ?></span>
    <span class="adm-stat__label">No photo</span>
  </div>
</div>

<!-- ============ THE ADD / EDIT DRAWER ============

     One form for both, exactly as before — a hidden id decides which.
     What changed is where it lives: a panel that slides in from the
     right instead of a form permanently occupying the top of the
     page.

     WHY A DRAWER AND NOT A CENTRED MODAL. The list stays visible
     behind it. When you are adding several places in a row, or
     checking what you already called something, the list is exactly
     what you need to see. A centred box covers it.

     IT OPENS ITSELF WHEN ?edit= IS SET. The is-open class is printed
     by PHP, not added by JavaScript, so arriving from an Edit link
     lands you in the form with no flash of an empty page — and the
     form is still reachable if the script never runs.
     ================================================================ -->
<div class="adm-drawer<?= $editing ? ' is-open' : '' ?>" id="destDrawer"
     role="dialog" aria-modal="true" aria-labelledby="destDrawerTitle"
     <?= $editing ? '' : 'aria-hidden="true"' ?>>
  <div class="adm-drawer__scrim" data-drawer-close></div>

  <div class="adm-drawer__panel">
    <form method="post" class="adm-form" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">
      <!-- Which municipality you were looking at, so the redirect
           after saving puts you back there rather than at the top of
           the province. -->
      <input type="hidden" name="town_ctx" value="<?= e($_GET['town'] ?? ($editing['town'] ?? '')) ?>">

      <div class="adm-drawer__head">
        <h2 class="adm-drawer__title" id="destDrawerTitle"><?= $editing ? 'Edit destination' : 'Add a destination' ?></h2>
        <?php if ($editing): ?>
          <span class="adm-count">#<?= (int) $editing['id'] ?></span>
        <?php endif; ?>
        <button type="button" class="adm-drawer__x" data-drawer-close aria-label="Close">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
      </div>

      <div class="adm-drawer__body">

        <!-- ---------- the photograph ----------
             Full-bleed across the top, at roughly the size it will be
             seen at. It is the field most likely to be wrong and the
             one you are most often here to change, so it leads rather
             than sitting beside a grey "Choose File" button.

             The real file input is still there — laid over the whole
             area at zero opacity, so clicking anywhere opens the
             picker, keyboard focus lands on a genuine form control,
             and the form submits exactly as it did before. -->
        <div class="adm-drop" id="destDrop">
          <?php if ($editing && $editing['filename'] !== ''): ?>
            <img class="adm-drop__img" id="destDropImg"
                 src="<?= dest_url($editing['filename'], '../') ?>" alt=""
                 onerror="this.remove()">
          <?php else: ?>
            <img class="adm-drop__img" id="destDropImg" alt="" hidden>
          <?php endif; ?>

          <div class="adm-drop__empty" id="destDropEmpty"
               <?= $editing && $editing['filename'] !== '' ? 'hidden' : '' ?>>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-4.5-4.5L3 21"/></svg>
            No photograph yet
          </div>

          <div class="adm-drop__hint">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            <b id="destDropLabel"><?= $editing && $editing['filename'] !== '' ? 'Replace photograph' : 'Add a photograph' ?></b>
            <span>Landscape, 1600&times;900 or larger</span>
          </div>

          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                 aria-label="<?= $editing ? 'Replace the photograph' : 'Choose a photograph' ?>">
        </div>

        <!-- ---------- what it is called ---------- -->
        <div class="adm-sect">
          <h3 class="adm-sect__title">What it is called</h3>

          <div class="adm-grid adm-grid--wide">
            <label class="adm-field">
              <span class="adm-field__label">Name</span>
              <input type="text" name="name" maxlength="160" required
                     placeholder="Bagasbas Beach"
                     value="<?= $editing ? e($editing['name']) : '' ?>">
            </label>

            <label class="adm-field">
              <span class="adm-field__label">Municipality</span>
              <input type="text" name="town" maxlength="120" required list="townList"
                     placeholder="Daet"
                     value="<?= $editing ? e($editing['town']) : '' ?>">
            </label>
          </div>

          <label class="adm-field">
            <span class="adm-field__label">Label <em>the small tag on the card</em></span>
            <input type="text" name="tag" maxlength="60" required list="tagList"
                   placeholder="Surf"
                   value="<?= $editing ? e($editing['tag']) : '' ?>">
          </label>

        </div>

        <!-- ---------- how it reads ---------- -->
        <div class="adm-sect">
          <h3 class="adm-sect__title">How it reads</h3>

          <label class="adm-field">
            <span class="adm-field__label">Pull quote <em>one line, italic on the card</em></span>
            <input type="text" name="quote" maxlength="255"
                   placeholder="The north's longest ride."
                   value="<?= $editing ? e($editing['quote']) : '' ?>">
          </label>

          <label class="adm-field">
            <span class="adm-field__label">Description <em>one or two sentences</em></span>
            <textarea name="descr" rows="3"><?= $editing ? e($editing['descr']) : '' ?></textarea>
          </label>
        </div>

        <!-- ---------- the three facts ----------
             One control, three rows. They are a set of three that
             appear together on the card, not three unrelated fields
             that happen to be adjacent. -->
        <div class="adm-sect">
          <h3 class="adm-sect__title">Three quick facts</h3>

          <div class="adm-fieldset">
            <div class="adm-fieldset__row">
              <span class="adm-fieldset__key">First</span>
              <input type="text" name="chip1" maxlength="60" placeholder="Year-round swell"
                     aria-label="First fact"
                     value="<?= $editing ? e($editing['chip1']) : '' ?>">
            </div>
            <div class="adm-fieldset__row">
              <span class="adm-fieldset__key">Second</span>
              <input type="text" name="chip2" maxlength="60" placeholder="Board rentals"
                     aria-label="Second fact"
                     value="<?= $editing ? e($editing['chip2']) : '' ?>">
            </div>
            <div class="adm-fieldset__row">
              <span class="adm-fieldset__key">Third</span>
              <input type="text" name="chip3" maxlength="60" placeholder="Sunset boardwalk"
                     aria-label="Third fact"
                     value="<?= $editing ? e($editing['chip3']) : '' ?>">
            </div>
          </div>

          <span class="adm-hint">Shown as three small pills under the description. Leave any of them blank to drop it.</span>
        </div>

        <!-- ---------- where it is ----------
             Latitude and longitude are one coordinate, so they are one
             control split down the middle rather than two boxes that
             happen to sit side by side. -->
        <div class="adm-sect">
          <h3 class="adm-sect__title">Where it is</h3>

          <div class="adm-fieldset adm-fieldset--split">
            <div class="adm-fieldset__row">
              <span class="adm-fieldset__key">Lat</span>
              <input type="text" name="lat" inputmode="decimal" placeholder="14.136808"
                     aria-label="Latitude"
                     value="<?= $editing && $editing['lat'] !== null ? e($editing['lat']) : '' ?>">
            </div>
            <div class="adm-fieldset__row">
              <span class="adm-fieldset__key">Lng</span>
              <input type="text" name="lng" inputmode="decimal" placeholder="122.983065"
                     aria-label="Longitude"
                     value="<?= $editing && $editing['lng'] !== null ? e($editing['lng']) : '' ?>">
            </div>
          </div>
        </div>

        <!-- Free text with suggestions rather than a fixed dropdown:
             the twelve municipalities will not change, but the labels
             grow as places are added, and a <select> would need
             editing in two places every time. -->
        <datalist id="townList">
          <?php foreach ($towns as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
        </datalist>
        <datalist id="tagList">
          <?php foreach ($tags as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
        </datalist>

      </div><!-- /drawer body -->

      <!-- The actions sit outside the scrolling area, so Save is
           never the thing you have to hunt for at the bottom of a
           long form. Cancel is a link on an edit because leaving
           edit mode is a change of URL, and a button when adding
           because there is nothing to go back to. -->
      <div class="adm-drawer__foot">
        <?php if ($editing): ?>
          <a href="<?= e(back_to()) ?>" class="adm-btn adm-btn--ghost">Cancel</a>
        <?php else: ?>
          <button type="button" class="adm-btn adm-btn--ghost" data-drawer-close>Cancel</button>
        <?php endif; ?>
        <button type="submit" class="adm-btn"><?= $editing ? 'Save changes' : 'Add destination' ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ============ ONE PANEL PER MUNICIPALITY ============ -->
<!-- ============ THE GRID ============

     Twelve collapsible tables became one grid of photographs.

     WHY. A destination is a place with a picture, and the table led
     with a 104px thumbnail on a page that is fundamentally about
     pictures. It also carried six columns — half of them one word
     each — and repeated its header row twelve times, once per
     municipality.

     ONLY WHAT IS WRONG IS MARKED. The table put a green "Live" badge
     on all twenty-four rows, and twenty-four green badges say
     nothing. Here a tile with no mark is in order; a mark means it
     wants you. Three states are worth interrupting for: hidden, no
     photograph, no map pin.

     THE MUNICIPALITY BECAME A FILTER, not twelve panels. Twelve
     headings for twenty-four places was a heading for every two.
     ================================================================ -->
<!-- ============ LEVEL ONE: THE MUNICIPALITIES ============
     Twelve cards, two places each. Click one and its places open in
     a panel over the middle of the screen, with the grid still
     visible and blurred behind it — so it is obvious you have stepped
     into one town rather than gone somewhere new.

     THE GOLD PILL counts what needs attention in that town: hidden,
     no map pin, or no photograph. A town with nothing to fix carries
     no mark, so a marked card is worth crossing the room for.
     ================================================================ -->
<div class="adm-towns" id="destTowns">
  <?php foreach ($byTown as $town => $list):
      $seal  = town_seal($town, '../');
      $needs = 0;

      foreach ($list as $pl) {
          if (!$pl['is_visible'] || $pl['lat'] === null || $pl['filename'] === '') {
              $needs++;
          }
      }

      /* "Jose Panganiban" -> JP, "Labo" -> LA. Two letters either
         way, so every fallback card carries the same weight. */
      $words   = preg_split('/\s+/', trim($town));
      $initial = count($words) > 1
          ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
          : strtoupper(substr($town, 0, 2));
  ?>
  <button type="button" class="adm-town" data-town="<?= e($town) ?>">
    <?php if ($seal): ?>
      <img class="adm-town__seal" src="<?= $seal ?>" alt="" onerror="this.remove()">
    <?php else: ?>
      <span class="adm-town__initial"><?= e($initial) ?></span>
    <?php endif; ?>

    <span class="adm-town__name"><?= e($town) ?></span>

    <?php if (isset(TOWN_LINES[$town])): ?>
      <span class="adm-town__desc"><?= e(TOWN_LINES[$town]) ?></span>
    <?php endif; ?>

    <span class="adm-town__foot">
      <?= count($list) ?> place<?= count($list) === 1 ? '' : 's' ?>
      <?php if ($needs): ?>
        <span class="adm-town__flag" title="<?= $needs ?> need attention"><?= $needs ?></span>
      <?php endif; ?>
    </span>
  </button>
  <?php endforeach; ?>
</div>


<!-- ============ LEVEL TWO: THE PLACES ============

     Every place in the province is rendered once, here, and the
     script shows only the two belonging to the municipality you
     clicked. Stepping between towns costs no page load.

     Each card carries everything you would otherwise open a second
     panel to read — quote, description, facts, pin — and its three
     actions. One panel, no stacking.
     ================================================================ -->
<div class="adm-glass" id="destGlass" role="dialog" aria-modal="true"
     aria-labelledby="destGlassTitle" aria-hidden="true">
  <div class="adm-glass__scrim" data-glass-close></div>

  <div class="adm-glass__panel">
    <div class="adm-glass__head">
      <img class="adm-glass__seal" id="destGlassSeal" alt="" hidden>
      <div>
        <h2 class="adm-glass__title" id="destGlassTitle">Municipality</h2>
        <span class="adm-glass__sub" id="destGlassSub"></span>
      </div>
      <button type="button" class="adm-glass__x" data-glass-close aria-label="Close">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <div class="adm-glass__body">

      <!-- ---------- the municipal seal ----------
           Upload lives here rather than on the card, because a card in
           a grid of twelve should be one thing you click, not two.

           The <label> IS the button. A file input styled to look like
           a button is a fight you lose in every browser; a label
           pointing at a hidden input is the same click, fully
           styleable, and still reaches the real control by keyboard.

           It submits the moment you choose a file — an upload with a
           separate Save step is a step people forget, and there is
           nothing else on this form to fill in. -->
      <form method="post" enctype="multipart/form-data" class="adm-seal" id="destSealForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="seal">
        <input type="hidden" name="town" id="destSealTown" value="">
        <input type="hidden" name="town_ctx" id="destSealCtx" value="">

        <span class="adm-seal__text" id="destSealText">No seal uploaded for this municipality.</span>

        <label class="adm-btn adm-btn--sm adm-btn--ghost adm-seal__pick">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
          <span id="destSealVerb">Upload seal</span>
          <input type="file" name="seal" accept="image/png,image/jpeg,image/webp"
                 id="destSealInput" class="adm-seal__input">
        </label>

        <button type="submit" name="remove" value="1"
                class="adm-btn adm-btn--sm adm-btn--danger adm-seal__drop"
                id="destSealRemove" hidden>Remove</button>
      </form>
      <div class="adm-pair" id="destPair">
        <?php foreach ($rows as $i => $row):
            $hasPin = $row['lat'] !== null;
            $hasImg = $row['filename'] !== '';
            $facts  = array_filter([$row['chip1'], $row['chip2'], $row['chip3']]);
        ?>
        <article class="adm-place<?= $row['is_visible'] ? '' : ' adm-place--off' ?>"
                 data-town="<?= e($row['town']) ?>" hidden>

          <div class="adm-place__shot">
            <?php if ($hasImg): ?>
              <img src="<?= dest_url($row['filename'], '../') ?>" alt="" loading="lazy"
                   onerror="this.remove()">
            <?php else: ?>
              <span class="adm-place__none">No photograph</span>
            <?php endif; ?>

            <span class="adm-place__marks">
              <?php if (!$row['is_visible']): ?>
                <span class="adm-place__mark adm-place__mark--off">Hidden</span>
              <?php endif; ?>
              <?php if (!$hasPin): ?>
                <span class="adm-place__mark adm-place__mark--warn">No pin</span>
              <?php endif; ?>
            </span>
          </div>

          <div class="adm-place__body">
            <span class="adm-place__name"><?= e($row['name']) ?></span>

            <?php if ($row['quote'] !== ''): ?>
              <span class="adm-place__quote"><?= e($row['quote']) ?></span>
            <?php endif; ?>

            <?php if ($row['descr'] !== ''): ?>
              <p class="adm-place__desc"><?= e($row['descr']) ?></p>
            <?php endif; ?>

            <?php if ($facts): ?>
              <ul class="adm-place__facts">
                <?php foreach ($facts as $f): ?>
                  <li><?= e($f) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <span class="adm-place__pin">
              <?= $hasPin
                    ? e($row['lat']) . ', ' . e($row['lng'])
                    : 'No pin — this place is off the map' ?>
            </span>
          </div>

          <div class="adm-place__foot">
            <a class="adm-btn adm-btn--sm"
               href="destinations.php?edit=<?= (int) $row['id'] ?>&town=<?= rawurlencode($row['town']) ?>">Edit</a>

            <form method="post" class="adm-inline">
              <?= csrfField() ?>
              <input type="hidden" name="town_ctx" value="<?= e($row['town']) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost">
                <?= $row['is_visible'] ? 'Hide' : 'Show' ?>
              </button>
            </form>

            <!-- Reordering. It moves a place within the WHOLE list, not
                 within its municipality — sort_order is one sequence
                 across all twenty-four, and the arrows swap with the
                 true neighbour wherever that sits. Disabled at the two
                 ends, where there is nothing to swap with. -->
            <form method="post" class="adm-inline">
              <?= csrfField() ?>
              <input type="hidden" name="town_ctx" value="<?= e($row['town']) ?>">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="dir" value="up">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost"
                      <?= $i === 0 ? 'disabled' : '' ?>
                      title="Move earlier" aria-label="Move <?= e($row['name']) ?> earlier">&uarr;</button>
            </form>

            <form method="post" class="adm-inline">
              <?= csrfField() ?>
              <input type="hidden" name="town_ctx" value="<?= e($row['town']) ?>">
              <input type="hidden" name="action" value="move">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <input type="hidden" name="dir" value="down">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost"
                      <?= $i === count($rows) - 1 ? 'disabled' : '' ?>
                      title="Move later" aria-label="Move <?= e($row['name']) ?> later">&darr;</button>
            </form>

            <form method="post" class="adm-inline"
                  data-confirm
                  data-confirm-title="Delete <?= e($row['name']) ?>?"
                  data-confirm-body="It comes off the destinations page, the homepage rail and the map. To take it off the site but keep everything, use Hide instead."
                  data-confirm-note="Its photograph is deleted from the server, and its entry in destination-details.php is left orphaned."
                  data-confirm-action="Delete permanently">
              <?= csrfField() ?>
              <input type="hidden" name="town_ctx" value="<?= e($row['town']) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button type="submit" class="adm-btn adm-btn--sm adm-btn--danger">Delete</button>
            </form>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!$rows && !$tableGone): ?>
  <p class="adm-empty">No destinations yet. Use Add destination to put the first one on the map.</p>
<?php endif; ?>

<!-- ============ THE DETAIL SHEET ============
     One panel for all twenty-four, filled by JavaScript from the tile
     you clicked. Every form inside carries its CSRF token from PHP;
     only the row id is written in, so nothing that matters for
     security depends on the script. -->
<script>
/* ===================================================================
   THE TWO LEVELS

   Twelve municipality cards; clicking one opens the glass panel with
   that town's places in it.

   EVERYTHING IS RENDERED ONCE. The panel holds every place in the
   province and shows only the matching ones, so stepping between
   municipalities costs nothing and no data has to be fetched.

   NOTHING SECURITY-RELATED HAPPENS HERE. Every form in the panel
   already carries its CSRF token, its action and its row id from PHP.
   This script only decides what is visible. If it never runs you lose
   the panel, not your safety.
   =================================================================== */
(function () {
  var towns = document.getElementById('destTowns');
  var glass = document.getElementById('destGlass');
  if (!towns || !glass) return;

  var pair  = glass.querySelectorAll('.adm-place');
  var title = document.getElementById('destGlassTitle');
  var sub   = document.getElementById('destGlassSub');
  var seal  = document.getElementById('destGlassSeal');

  var lastCard = null;

  function open(card) {
    var town = card.getAttribute('data-town');
    var n = 0;

    pair.forEach(function (el) {
      var match = el.getAttribute('data-town') === town;
      el.hidden = !match;
      if (match) n++;
    });

    title.textContent = town;
    sub.textContent   = n === 1 ? '1 place' : n + ' places';

    /* Carry the town's own seal into the panel header, so the panel
       is visibly about the card you clicked. */
    var img = card.querySelector('.adm-town__seal');
    var has = !!img;

    if (has) { seal.src = img.getAttribute('src'); seal.hidden = false; }
    else     { seal.hidden = true; }

    /* Point the seal form at this municipality, and say what it can
       do — the wording changes because "Upload" and "Replace" are
       different promises and the button should keep the right one. */
    var sealTown   = document.getElementById('destSealTown');
    var sealCtx    = document.getElementById('destSealCtx');
    var sealText   = document.getElementById('destSealText');
    var sealVerb   = document.getElementById('destSealVerb');
    var sealRemove = document.getElementById('destSealRemove');

    if (sealTown) {
      sealTown.value = town;
      sealCtx.value  = town;

      sealText.textContent = has
        ? 'Municipal seal for ' + town + '.'
        : 'No seal yet for ' + town + '. The card shows its initials instead.';

      sealVerb.textContent = has ? 'Replace seal' : 'Upload seal';
      sealRemove.hidden    = !has;
    }

    lastCard = card;

    glass.classList.add('is-open');
    glass.removeAttribute('aria-hidden');
    document.body.classList.add('adm-locked');

    setTown(town);
    glass.querySelector('[data-glass-close]').focus();
  }

  function close() {
    glass.classList.remove('is-open');
    glass.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('adm-locked');

    setTown(null);
    if (lastCard) lastCard.focus();
  }

  /* Choosing a file submits immediately. There is nothing else on
     this form to fill in, and an upload with a separate Save step is
     a step people forget — they pick the file, see the name appear,
     and walk away thinking it is done. */
  var sealInput = document.getElementById('destSealInput');
  var sealForm  = document.getElementById('destSealForm');

  if (sealInput && sealForm) {
    sealInput.addEventListener('change', function () {
      if (sealInput.files && sealInput.files[0]) sealForm.submit();
    });
  }

  /* The URL remembers which municipality is open, so saving a place
     and coming back through the redirect reopens the same town rather
     than dropping you at the top of the province. */
  function setTown(town) {
    if (!window.history || !history.replaceState) return;

    var url = new URL(location.href);
    if (town) { url.searchParams.set('town', town); }
    else      { url.searchParams.delete('town'); }

    history.replaceState({}, '', url.pathname + url.search);
  }

  towns.addEventListener('click', function (e) {
    var card = e.target.closest('.adm-town');
    if (card) open(card);
  });

  glass.addEventListener('click', function (e) {
    if (e.target.closest('[data-glass-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || !glass.classList.contains('is-open')) return;

    /* The confirm dialog sits on top of this panel. Escape belongs to
       whichever is highest, or closing this one would leave the
       dialog floating over nothing. */
    var dlg = document.getElementById('admConfirm');
    if (dlg && dlg.open) return;

    close();
  });

  /* Reopen whatever the URL names, if it is a real municipality.

     ⚠ NOT WHEN ?edit= IS ALSO SET. Arriving from an Edit link, PHP has
     already printed is-open on the edit drawer — and this panel sits
     at z-index 950 against the drawer's 900, so opening it here put
     the two place cards straight over the top of the form you had
     just asked for. It looked as though Edit opened the wrong thing;
     the form was there the whole time, underneath.

     One at a time. Editing wins, because it is the thing you clicked
     most recently, and closing the form drops you back to the
     municipality anyway. */
  var params = new URLSearchParams(location.search);
  var wanted = params.get('town');

  if (wanted && !params.get('edit')) {
    var card = towns.querySelector('.adm-town[data-town="' + wanted.replace(/"/g, '\\"') + '"]');
    if (card) open(card);
  }
})();


/* ===================================================================
   THE PHOTOGRAPH DROP ZONE

   Two jobs, both about seeing what you picked before you commit.

   PREVIEW ON SELECT. Choosing a file used to change a filename in
   grey text and nothing else, so you found out what you had actually
   picked after saving. Now the panel shows it immediately, at the
   shape the card will use.

   DRAG AND DROP. The DataTransfer object is assigned straight to the
   input's .files, so the dropped file goes through the same input and
   the same POST as a picked one. Nothing about the upload path
   changes, which matters — every check in media-guard.php still runs
   on it exactly as before.

   Without this script the field is still a working file input. It
   just does not preview.
   =================================================================== */
(function () {
  var drop = document.getElementById('destDrop');
  if (!drop) return;

  var input = drop.querySelector('input[type=file]');
  var img   = document.getElementById('destDropImg');
  var empty = document.getElementById('destDropEmpty');
  var label = document.getElementById('destDropLabel');
  if (!input) return;

  function preview(file) {
    if (!file || !/^image\//.test(file.type)) return;

    /* An object URL rather than a FileReader: no base64 string of a
       6 MB photograph in memory, and it is one line to release. */
    var url = URL.createObjectURL(file);

    img.src = url;
    img.hidden = false;
    img.onload = function () { URL.revokeObjectURL(url); };

    if (empty) empty.hidden = true;
    if (label) label.textContent = 'Replace photograph';
  }

  input.addEventListener('change', function () {
    if (input.files && input.files[0]) preview(input.files[0]);
  });

  /* dragover has to be prevented or the browser navigates away to
     open the file, which loses everything typed into the form. */
  ['dragenter', 'dragover'].forEach(function (type) {
    drop.addEventListener(type, function (e) {
      e.preventDefault();
      drop.classList.add('is-drag');
    });
  });

  ['dragleave', 'drop'].forEach(function (type) {
    drop.addEventListener(type, function () { drop.classList.remove('is-drag'); });
  });

  drop.addEventListener('drop', function (e) {
    e.preventDefault();

    var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (!file) return;

    /* Put the dropped file into the real input so it submits with the
       form. DataTransfer is the only way to write to .files, and it
       is why a dropped file and a picked one are indistinguishable by
       the time PHP sees them. */
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      preview(file);
    } catch (err) {
      /* Old browser with no DataTransfer constructor. Say so rather
         than silently doing nothing — the picker still works. */
      if (window.admToast) admToast('Dragging is not supported here. Click to choose a file instead.', 'bad');
    }
  });
})();
</script>

<!-- ============ THE SIGN-OFF ============
     A page that ends on a grid ends on nothing. Two columns: the
     greeting on the left, the three things worth remembering beside
     it, so the panel uses its width instead of leaving half of it
     blank. -->
<section class="adm-panel adm-signoff">
  <div class="adm-panel__body">

    <div>
      <h2 class="adm-signoff__title">That is the whole province, <?= e($me['firstname']) ?>.</h2>
      <p class="adm-signoff__text">
        <?= count($rows) ?> destination<?= count($rows) === 1 ? '' : 's' ?> across
        <?= count($byTown) ?> municipalit<?= count($byTown) === 1 ? 'y' : 'ies' ?>, and every one
        of them is yours to change. Anything you save is live straight away — there is no
        publish step and nothing waiting for approval.
      </p>
    </div>

    <dl class="adm-signoff__notes">
      <div class="adm-signoff__note">
        <dt>Hide, then decide</dt>
        <dd>Hide takes a place off the site and keeps everything. Delete does not.
            If you are unsure, hide it and come back to it.</dd>
      </div>

      <div class="adm-signoff__note">
        <dt>One save, four places</dt>
        <dd>Editing a destination changes its card, the homepage rail, the map pin and
            the panel that pin opens. All at once.</dd>
      </div>

      <div class="adm-signoff__note">
        <dt>Watch the shape</dt>
        <dd>A photograph in the wrong shape is the one mistake nothing warns you about.
            Look at the public page after adding one.</dd>
      </div>
    </dl>

    <p class="adm-signoff__links">
      <a href="../destinations.php" target="_blank" rel="noopener">See the page as visitors do</a>
      <a href="gallery.php">Gallery photographs</a>
      <a href="index.php">Back to the overview</a>
    </p>
  </div>
</section>

<?php require __DIR__ . '/_footer.php'; ?>