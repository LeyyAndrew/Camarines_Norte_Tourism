<?php
/* ===================================================================
   save-itinerary.php — the endpoint plan-trip.js already posts to.

   It sits at the project root because that is where plan-trip.js
   looks: saveUrl is the bare string 'save-itinerary.php', resolved
   against the page's own URL.

   Four actions, all JSON, all requiring a session — the same shape as
   includes/saved-places.php, so there is one convention in this
   project rather than two:

     POST (no action)      save a trip. The body is exactly what
                           collect() in plan-trip.js builds.
     GET  ?action=list     this user's trips, newest first.
     GET  ?action=get&id=  one trip, in the same shape the builder
                           sends, so hydrate() can load it straight back.
     POST action=delete    remove one.

   IT ACCEPTS BUD'S PLANS TOO. Bud produces the identical payload, so
   nothing here is aware of which made it beyond the source field. One
   endpoint, one validator, one renderer.
   =================================================================== */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/* A stray blank line in an include would make the JSON unparseable
   and the browser would report a syntax error at character 1 with no
   clue where it came from. Buffer, and discard at respond(). */
ob_start();

require_once __DIR__ . '/includes/db.php';

/* mbstring is not installed everywhere — the same trap that took
   api/bud.php down on a host without it. Unguarded, mb_substr() is a
   fatal error and the visitor gets a blank 500 instead of a saved
   trip. substr() alone is not a safe fallback: it cuts by bytes and
   can slice a Tagalog character in half, leaving a string json_encode
   then refuses to encode. PCRE's /u mode cuts by character. */
function it_cut(string $s, int $n): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $n);
    }
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    return $chars === false ? substr($s, 0, $n) : implode('', array_slice($chars, 0, $n));
}

function it_respond(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- who is asking ----------
   The session, never a parameter. A user id in the request would let
   anyone read or delete anyone else's trips by changing a number. */
$userId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
    ? (int) $_SESSION['user_id']
    : null;

if ($userId === null) {
    it_respond([
        'ok'      => false,
        'error'   => 'not_logged_in',
        'message' => 'Sign in to save your itinerary.',
    ], 401);
}

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('save-itinerary: ' . $e->getMessage());
    it_respond([
        'ok'      => false,
        'error'   => 'no_db',
        'message' => 'The database is not reachable right now.',
    ], 500);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ---------- slug -> destination id ----------
   plan-trip.js speaks slugs, the database speaks ids, and the slug is
   built the same way in plan-trip.php: lowercase "town name", every
   run of non-alphanumerics collapsed to a hyphen.

   Rebuilt HERE from the destinations table rather than trusting the
   slug the browser sent. That way a stop can only ever point at a
   real, visible destination, whatever arrives in the request. */
function it_slug(string $town, string $name): string
{
    $s = strtolower($town . '-' . $name);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}

function it_slug_map(PDO $pdo): array
{
    $map = [];
    foreach ($pdo->query('SELECT id, name, town FROM destinations WHERE is_visible')->fetchAll() as $r) {
        $map[it_slug($r['town'], $r['name'])] = (int) $r['id'];
    }
    return $map;
}

/* ===================================================================
   LIST
   =================================================================== */
if ($action === 'list') {
    try {
        $st = $pdo->prepare(
            'SELECT i.id, i.name, i.start_date, i.end_date, i.travelers,
                    i.source, i.created_at,
                    COUNT(t.id) FILTER (WHERE t.dest_slug IS NOT NULL) AS stops,
                    COALESCE(MAX(t.day_number), 0) AS days
               FROM itineraries i
          LEFT JOIN itinerary_items t ON t.itinerary_id = i.id
              WHERE i.user_id = ?
           GROUP BY i.id
           ORDER BY i.created_at DESC
              LIMIT 50'
        );
        $st->execute([$userId]);

        $items = [];
        foreach ($st->fetchAll() as $r) {
            $items[] = [
                'id'        => (int) $r['id'],
                'name'      => $r['name'],
                'start'     => $r['start_date'],
                'end'       => $r['end_date'],
                'travelers' => (int) $r['travelers'],
                'source'    => $r['source'],
                'days'      => (int) $r['days'],
                'stops'     => (int) $r['stops'],
                'created'   => $r['created_at'],
            ];
        }
        it_respond(['ok' => true, 'items' => $items, 'count' => count($items)]);

    } catch (Throwable $e) {
        error_log('save-itinerary list: ' . $e->getMessage());
        it_respond(['ok' => false, 'error' => 'server_error',
                    'message' => 'Could not load your trips.'], 500);
    }
}

/* ===================================================================
   GET ONE — returned in the exact shape collect() produces, so
   hydrate() in plan-trip.js can load it with no translation.
   =================================================================== */
if ($action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        it_respond(['ok' => false, 'error' => 'bad_id',
                    'message' => 'No itinerary was given.'], 400);
    }

    try {
        /* user_id in the WHERE, not checked afterwards. A trip that is
           not yours simply does not exist as far as this query is
           concerned. */
        $st = $pdo->prepare(
            'SELECT id, name, start_date, end_date, travelers, source, brief
               FROM itineraries WHERE id = ? AND user_id = ?'
        );
        $st->execute([$id, $userId]);
        $trip = $st->fetch();

        if (!$trip) {
            it_respond(['ok' => false, 'error' => 'not_found',
                        'message' => 'That itinerary was not found.'], 404);
        }

        $st = $pdo->prepare(
            'SELECT day_number, day_date, start_time, dest_slug, note
               FROM itinerary_items
              WHERE itinerary_id = ?
           ORDER BY day_number, sort_order, id'
        );
        $st->execute([$id]);

        /* Rows back into the nested days/items array the builder uses.
           Days are keyed by number first so a gap — day 1 and day 3
           with nothing in day 2 — still produces three days rather
           than silently renumbering the third to second. */
        $byDay = [];
        foreach ($st->fetchAll() as $r) {
            $n = (int) $r['day_number'];
            if (!isset($byDay[$n])) {
                $byDay[$n] = ['date' => $r['day_date'] ?? '', 'items' => []];
            }
            $byDay[$n]['items'][] = [
                'time'   => $r['start_time'] !== null ? substr($r['start_time'], 0, 5) : '',
                'destId' => $r['dest_slug'] ?? '',
                'note'   => $r['note'] ?? '',
            ];
        }

        $maxDay = $byDay ? max(array_keys($byDay)) : 0;
        $days = [];
        for ($n = 1; $n <= $maxDay; $n++) {
            $days[] = $byDay[$n] ?? ['date' => '', 'items' => []];
        }

        it_respond([
            'ok'   => true,
            'trip' => [
                'id'        => (int) $trip['id'],
                'name'      => $trip['name'],
                'start'     => $trip['start_date'] ?? '',
                'end'       => $trip['end_date'] ?? '',
                'travelers' => (int) $trip['travelers'],
                'source'    => $trip['source'],
                'brief'     => $trip['brief'] !== null ? json_decode($trip['brief'], true) : null,
                'days'      => $days,
            ],
        ]);

    } catch (Throwable $e) {
        error_log('save-itinerary get: ' . $e->getMessage());
        it_respond(['ok' => false, 'error' => 'server_error',
                    'message' => 'Could not load that trip.'], 500);
    }
}

/* ===================================================================
   DELETE
   =================================================================== */
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        it_respond(['ok' => false, 'error' => 'method_not_allowed',
                    'message' => 'That action has to be posted.'], 405);
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        it_respond(['ok' => false, 'error' => 'bad_id',
                    'message' => 'No itinerary was given.'], 400);
    }

    try {
        /* The items go with it through ON DELETE CASCADE. */
        $st = $pdo->prepare('DELETE FROM itineraries WHERE id = ? AND user_id = ?');
        $st->execute([$id, $userId]);

        it_respond([
            'ok'      => true,
            'deleted' => $st->rowCount() > 0,
            'message' => $st->rowCount() > 0 ? 'Itinerary deleted.' : 'It was already gone.',
        ]);
    } catch (Throwable $e) {
        error_log('save-itinerary delete: ' . $e->getMessage());
        it_respond(['ok' => false, 'error' => 'server_error',
                    'message' => 'Could not delete that trip.'], 500);
    }
}

/* ===================================================================
   SAVE — the default POST

   The body is what collect() builds:

     { name, start, end, travelers,
       days: [ { date, items: [ { time, destId, note } ] } ] }

   Bud posts the same thing plus source and brief.
   =================================================================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    it_respond(['ok' => false, 'error' => 'method_not_allowed',
                'message' => 'Send this endpoint a POST request.'], 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode((string) $raw, true);

if (!is_array($body)) {
    it_respond(['ok' => false, 'error' => 'bad_body',
                'message' => 'Could not read that itinerary.'], 400);
}

/* ---------- validate ----------
   Everything is bounded. This endpoint writes rows on a public site,
   and an unbounded days array is an unbounded number of INSERTs. */

$name = trim((string) ($body['name'] ?? ''));
if ($name === '') {
    $name = 'Untitled trip';
}
$name = it_cut($name, 160);

/** '' and nonsense both become null rather than a bad DATE. */
function it_date($v): ?string
{
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

$start = it_date($body['start'] ?? '');
$end   = it_date($body['end'] ?? '');

$travelers = (int) ($body['travelers'] ?? 1);
if ($travelers < 1)  { $travelers = 1; }
if ($travelers > 60) { $travelers = 60; }

$source = ($body['source'] ?? 'manual') === 'bud' ? 'bud' : 'manual';

$brief = isset($body['brief']) && is_array($body['brief'])
    ? json_encode($body['brief'], JSON_UNESCAPED_UNICODE)
    : null;

$days = $body['days'] ?? [];
if (!is_array($days) || $days === []) {
    it_respond(['ok' => false, 'error' => 'no_days',
                'message' => 'That itinerary has no days in it.'], 400);
}
if (count($days) > 30) {
    it_respond(['ok' => false, 'error' => 'too_many_days',
                'message' => 'An itinerary can cover at most 30 days.'], 400);
}

$slugMap = it_slug_map($pdo);

/* Flatten to rows, dropping anything empty. A stop with no
   destination and no note is a blank line in the builder, not
   something worth a database row. */
$rows = [];
$kept = 0;
$dropped = 0;

foreach (array_values($days) as $i => $day) {
    $dayNo   = $i + 1;
    $dayDate = it_date($day['date'] ?? '');
    $items   = is_array($day['items'] ?? null) ? $day['items'] : [];

    if (count($items) > 20) {
        $items = array_slice($items, 0, 20);
    }

    foreach (array_values($items) as $pos => $item) {
        if (!is_array($item)) {
            continue;
        }

        $slug = trim((string) ($item['destId'] ?? ''));
        $note = trim((string) ($item['note'] ?? ''));
        $time = trim((string) ($item['time'] ?? ''));

        if ($slug === '' && $note === '') {
            continue;
        }

        /* The slug must name a real, visible destination. An unknown
           one is dropped rather than stored: a stop pointing at
           nothing renders as a blank card later, and the count
           returned below tells the caller it happened. */
        $destId = null;
        if ($slug !== '') {
            if (!isset($slugMap[$slug])) {
                $dropped++;
                if ($note === '') {
                    continue;
                }
                $slug = '';
            } else {
                $destId = $slugMap[$slug];
            }
        }

        $rows[] = [
            'day'   => $dayNo,
            'date'  => $dayDate,
            'sort'  => $pos,
            'slug'  => $slug !== '' ? it_cut($slug, 160) : null,
            'dest'  => $destId,
            'time'  => preg_match('/^\d{2}:\d{2}$/', $time) ? $time : null,
            'note'  => $note !== '' ? it_cut($note, 2000) : null,
        ];
        $kept++;
    }
}

if ($kept === 0) {
    it_respond(['ok' => false, 'error' => 'empty',
                'message' => 'Add at least one destination before saving.'], 400);
}

/* ---------- write ----------
   In a transaction: a trip with half its stops is worse than no trip,
   because it looks saved. */
try {
    $pdo->beginTransaction();

    $st = $pdo->prepare(
        'INSERT INTO itineraries
            (user_id, name, start_date, end_date, travelers, source, brief)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         RETURNING id'
    );
    $st->execute([$userId, $name, $start, $end, $travelers, $source, $brief]);
    $itinId = (int) $st->fetch()['id'];

    $ins = $pdo->prepare(
        'INSERT INTO itinerary_items
            (itinerary_id, day_number, day_date, sort_order,
             dest_slug, destination_id, start_time, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($rows as $r) {
        $ins->execute([
            $itinId, $r['day'], $r['date'], $r['sort'],
            $r['slug'], $r['dest'], $r['time'], $r['note'],
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('save-itinerary save: ' . $e->getMessage());
    it_respond(['ok' => false, 'error' => 'server_error',
                'message' => 'Something went wrong saving that. Try again.'], 500);
}

it_respond([
    'ok'      => true,
    'id'      => $itinId,
    'stops'   => $kept,
    /* Reported rather than hidden: if a plan referenced a place that
       has since been renamed or hidden, the caller should be able to
       say so instead of the visitor silently losing a stop. */
    'dropped' => $dropped,
    'message' => 'Itinerary saved to your account.',
]);