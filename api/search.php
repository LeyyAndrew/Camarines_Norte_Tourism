<?php
/* ===================================================================
   api/search.php  —  what the header dropdown talks to

   GET api/search.php?q=calaguas   ->   {"q":"...","count":3,"results":[...]}

   Always returns JSON with HTTP 200, including when nothing matched
   and when something broke. A dropdown that gets a 500 with an HTML
   error page in it dies on JSON.parse and shows the visitor nothing at
   all; one that gets {"results":[]} shows "No matches", which is the
   truth and is useful.
   =================================================================== */

require_once __DIR__ . '/../includes/search.php';

/* Errors go to the log, never into the response body — a PHP notice
   printed above the JSON is invalid JSON, and the dropdown goes blank
   for a problem the visitor cannot see or fix. */
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$q     = isset($_GET['q']) ? (string) $_GET['q'] : '';
$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 8;

/* ---------- THE GATE ----------
   THIS is what actually keeps search behind a login. The hidden
   button in the header is only tidiness — this file is a URL, and
   anyone can type it.

   401, not 200: a signed-out request is a refusal, not an empty
   result, and the two must not look alike to anything reading this
   endpoint. The body is still JSON so the dropdown can read the
   reason and say something useful instead of failing to parse.

   It matters more than it looks. A visitor can be signed in when the
   overlay opens and signed out by the time they finish typing — an
   expired session, a sign-out in another tab. This is the moment that
   gets caught. */
if (!search_allowed()) {
    http_response_code(401);
    echo json_encode([
        'q'       => $q,
        'count'   => 0,
        'results' => [],
        'auth'    => false,
        'message' => 'Sign in to search.',
        'login'   => search_login_link('search.php' . ($q !== '' ? '?q=' . rawurlencode($q) : '')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$out = ['q' => $q, 'count' => 0, 'results' => [], 'auth' => true];

if (mb_strlen(trim($q)) >= 2) {
    try {
        foreach (search_site($q, $limit) as $r) {
            $out['results'][] = [
                'title'   => $r['title'],
                'kind'    => $r['kind'],
                'url'     => $r['url'],
                'snippet' => $r['snippet'],
                'meta'    => $r['meta'] ?? '',
                'image'   => search_image_url($r['image']),
            ];
        }
    } catch (Throwable $e) {
        error_log('search api: ' . $e->getMessage());
    }
    $out['count'] = count($out['results']);
}

/* UNESCAPED_UNICODE so Ñ and é travel as themselves rather than as
   \u escapes — smaller payload, and readable when you are debugging
   this in a browser tab. */
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);