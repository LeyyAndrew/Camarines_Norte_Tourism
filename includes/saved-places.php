<?php
/* ===================================================================
   includes/saved-places.php — the bookmark feature's one endpoint.

   Four actions, all JSON, all requiring a session:

     GET  ?action=ids                    which places this user saved,
                                         as bare ids. The card buttons
                                         paint themselves from this.
     GET  ?action=list                   the same places with the
                                         columns the picker draws.
     POST action=toggle&destination_id=  save if not saved, unsave if
                                         it is. What the button sends.
     POST action=remove&destination_id=  unsave, unconditionally.

   IT USES db(), so there is no second connection and no second copy
   of the password — same reasoning as includes/destinations-data.php.
   PDO is already set to FETCH_ASSOC and real prepares by that file.

   COLUMN NAMES ARE FROZEN HERE, not configured. They are read from
   the same destinations table destinations-data.php reads, and if
   that table changes both files have to change together anyway.
   Keeping the names in two places would only mean two chances to
   disagree.

   ⚠ THIS FILE IS FETCHED DIRECTLY by assets/js/saved-places.js. It is
   not included into a page. If includes/ ever gets an .htaccess deny
   or an index.php guard, this needs an exception.
   =================================================================== */

declare(strict_types=1);

/* header.php starts the session, but this file is reached without it
   — the browser asks for this URL on its own — so it starts its own.
   Guarded, in case a future include order changes that. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* Anything printed before the JSON makes the JSON unparseable, and
   the browser reports it as a syntax error at character 1 with no
   clue where it came from. A stray blank line after ?> in an include
   is enough. Buffer it and throw it away at respond(). */
ob_start();

/** Where the photographs live. Same constant, same value, as
 *  includes/destinations-data.php — a relative path, because every
 *  page that loads this feature sits at the project root. */
const SP_PHOTO_DIR = 'uploads/Destination-Photo/';

require_once __DIR__ . '/db.php';

/**
 * Send JSON and stop. Every exit from this file goes through here, so
 * the shape is the same whether it worked or not: ok, plus either the
 * data or an error code and a message written for a person.
 */
function sp_respond(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- WHO IS ASKING ----------
   The session, never a parameter. A user id in the request would let
   anyone read or empty anyone else's list by changing a number. */
$userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : null;

if ($userId === null) {
    sp_respond([
        'ok'      => false,
        'error'   => 'not_logged_in',
        'message' => 'Log in to save places.',
    ], 401);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('saved-places: ' . $e->getMessage());
    sp_respond([
        'ok'      => false,
        'error'   => 'no_db',
        'message' => 'The database is not reachable right now.',
    ], 500);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$destId = (int) ($_POST['destination_id'] ?? $_GET['destination_id'] ?? 0);

/** How many places this user has saved, for the badge on the trigger. */
function sp_count(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) AS n FROM saved_destinations WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetch()['n'];
}

try {
    switch ($action) {

        /* ---------------------------------------------------------
           IDS — the cheapest call, and the one every page makes on
           load. Ids only: the card already has the name and photo
           printed into it, so sending them again would be a second
           copy of the page's own content.
           --------------------------------------------------------- */
        case 'ids': {
            $st = $pdo->prepare('SELECT destination_id FROM saved_destinations WHERE user_id = ?');
            $st->execute([$userId]);

            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            sp_respond(['ok' => true, 'ids' => $ids, 'count' => count($ids)]);
        }

        /* ---------------------------------------------------------
           LIST — what the picker draws.

           is_visible is checked here as well as in
           destinations-data.php. A place the admin has hidden should
           not reappear in someone's saved list; the row stays in the
           table, so unhiding brings it back rather than losing it.

           The filename gets its folder prefix here, exactly as
           destinations-data.php does it, so a stored value can never
           point outside the photo folder.
           --------------------------------------------------------- */
        case 'list': {
            $st = $pdo->prepare(
                'SELECT d.id, d.name, d.town, d.tag, d.filename, s.saved_at
                   FROM saved_destinations s
                   JOIN destinations d ON d.id = s.destination_id
                  WHERE s.user_id = ?
                    AND d.is_visible
               ORDER BY s.saved_at DESC'
            );
            $st->execute([$userId]);

            $items = [];
            foreach ($st->fetchAll() as $r) {
                $items[] = [
                    'id'       => (int) $r['id'],
                    'name'     => $r['name'],
                    'location' => $r['town'],
                    'category' => $r['tag'],
                    'image'    => $r['filename'] !== '' ? SP_PHOTO_DIR . $r['filename'] : '',
                    'saved_at' => $r['saved_at'],
                ];
            }

            sp_respond(['ok' => true, 'items' => $items, 'count' => count($items)]);
        }

        /* ---------------------------------------------------------
           TOGGLE / SAVE / REMOVE

           POST only. A GET that changes data can be triggered by an
           <img src>, and browsers prefetch links.
           --------------------------------------------------------- */
        case 'toggle':
        case 'save':
        case 'remove': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sp_respond([
                    'ok'      => false,
                    'error'   => 'method_not_allowed',
                    'message' => 'That action has to be posted.',
                ], 405);
            }

            if ($destId <= 0) {
                sp_respond([
                    'ok'      => false,
                    'error'   => 'bad_destination',
                    'message' => 'No destination was given.',
                ], 400);
            }

            /* Checked before the insert rather than left to the
               foreign key, so a missing place comes back as a
               sentence instead of a constraint violation. */
            $st = $pdo->prepare('SELECT 1 FROM destinations WHERE id = ? LIMIT 1');
            $st->execute([$destId]);
            if (!$st->fetch()) {
                sp_respond([
                    'ok'      => false,
                    'error'   => 'not_found',
                    'message' => 'That destination no longer exists.',
                ], 404);
            }

            $st = $pdo->prepare(
                'SELECT 1 FROM saved_destinations WHERE user_id = ? AND destination_id = ? LIMIT 1'
            );
            $st->execute([$userId, $destId]);
            $isSaved = (bool) $st->fetch();

            $shouldSave = $action === 'save'   ? true
                        : ($action === 'remove' ? false : !$isSaved);

            if ($shouldSave) {
                /* ON CONFLICT rather than an if: two fast clicks can
                   both pass the check above before either inserts,
                   and the unique constraint would then throw on the
                   second. This makes the second a no-op. */
                $st = $pdo->prepare(
                    'INSERT INTO saved_destinations (user_id, destination_id)
                          VALUES (?, ?)
                     ON CONFLICT (user_id, destination_id) DO NOTHING'
                );
            } else {
                $st = $pdo->prepare(
                    'DELETE FROM saved_destinations WHERE user_id = ? AND destination_id = ?'
                );
            }
            $st->execute([$userId, $destId]);

            sp_respond([
                'ok'      => true,
                'saved'   => $shouldSave,
                'count'   => sp_count($pdo, $userId),
                'message' => $shouldSave ? 'Saved to your places.' : 'Removed from your places.',
            ]);
        }

        default:
            sp_respond([
                'ok'      => false,
                'error'   => 'unknown_action',
                'message' => 'That is not something this endpoint does.',
            ], 400);
    }

} catch (Throwable $e) {
    /* The message goes to the log, not to the browser: a PDO error
       names the table, the column and sometimes the query. */
    error_log('saved-places: ' . $e->getMessage());
    sp_respond([
        'ok'      => false,
        'error'   => 'server_error',
        'message' => 'Something went wrong saving that. Try again.',
    ], 500);
}