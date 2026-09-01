<?php
/* ===================================================================
   admin/_bootstrap.php

   Every admin page starts with:

     require __DIR__ . '/_bootstrap.php';

   It opens the session, connects to the database, throws out anyone
   who is not an admin, and defines the few helpers the pages share.

   WHY THE GUARD IS IN ONE FILE. If each page carried its own copy of
   the check, the day you add a page and forget to paste it in is the
   day the admin panel is open to everyone. One file, required first
   thing, cannot be forgotten quietly — a page that does not require
   it has no $pdo either and breaks loudly.
   =================================================================== */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require __DIR__ . '/../config/database.php';

/* Rows come back as associative arrays everywhere without asking.
   The default is BOTH — every row duplicated, once by name and once
   by number — which doubles the memory a list page holds and makes
   a var_dump twice as long as it needs to be while you are reading
   it. */
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* ---------- the guard ----------

   Two checks, not one. Signed in is not the same as being an admin,
   and the role is re-read FROM THE DATABASE on every request rather
   than trusted from the session. If you demote someone while they
   are logged in, they lose access on their next click instead of
   keeping it until they happen to sign out. */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../homepage.php');
    exit;
}

/* ---------- which columns exist ----------

   status and last_login were added to users after the fact, and a
   copy of this project running on a database where the ALTER has
   not been applied should degrade rather than explode. Everything
   that touches those two columns asks here first.

   Read once per request and remembered in a static, so the twenty
   calls a list page makes cost exactly one query.

   information_schema is the portable way to ask. Catching a failed
   SELECT and inferring the column is missing cannot tell "no such
   column" apart from "the database is down". */
function userColumns(PDO $pdo): array
{
    static $columns = null;

    if ($columns !== null) { return $columns; }

    try {
        $columns = $pdo->query(
            "SELECT column_name
               FROM information_schema.columns
              WHERE table_schema = 'public' AND table_name = 'users'"
        )->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('column probe failed: ' . $e->getMessage());
        $columns = [];
    }

    return $columns;
}

function hasUserColumn(PDO $pdo, string $column): bool
{
    return in_array($column, userColumns($pdo), true);
}

/* ---------- who is asking ----------
   The column list is built from what actually exists, so this same
   query works before and after the ALTER. */
$me = null;

$guardCols = ['id', 'firstname', 'lastname', 'email', 'role'];

foreach (['status', 'created_at', 'last_login'] as $optional) {
    if (hasUserColumn($pdo, $optional)) { $guardCols[] = $optional; }
}

try {
    $stmt = $pdo->prepare(
        'SELECT ' . implode(', ', $guardCols) . ' FROM users WHERE id = :id'
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $me = $stmt->fetch();
} catch (PDOException $e) {
    error_log('admin guard query failed: ' . $e->getMessage());
}

if (!$me || $me['role'] !== 'admin') {
    header('Location: ../homepage.php');
    exit;
}

/* A suspended admin is not an admin. Checked here as well as at
   sign-in, because suspending someone who is already signed in has
   to take effect on their next click — otherwise the button in
   users.php does nothing until they happen to log out. */
if (isset($me['status']) && $me['status'] !== 'active') {
    session_destroy();
    header('Location: ../homepage.php?error=suspended');
    exit;
}

/* ---------- helpers ---------- */

/* Short name for htmlspecialchars, because every single value printed
   in the admin panel came out of the database and therefore out of a
   form. Typing the full name forty times is how one gets missed. */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* Initials for the avatar discs.

   mb_substr, not substr — a name starting with Ñ or É gets cut
   mid-character by the single-byte version and prints as a broken
   glyph. Same reasoning as includes/header.php. */
function initials(?string $first, ?string $last = null): string
{
    $value = mb_strtoupper(
        mb_substr(trim((string) $first), 0, 1, 'UTF-8') .
        mb_substr(trim((string) $last), 0, 1, 'UTF-8'),
        'UTF-8'
    );

    return $value === '' ? '?' : $value;
}

/* ---------- dates ----------

   A null timestamp is a real answer, not an error: last_login is
   null for anyone who has registered but never signed in, and it
   should read as "Never", not as 1 Jan 1970. strtotime(null) returns
   today, which is the wrong answer told confidently. */
function fmtDate(?string $timestamp, string $empty = '—'): string
{
    if (!$timestamp) { return $empty; }

    $time = strtotime($timestamp);

    return $time ? date('j M Y', $time) : $empty;
}

function fmtDateTime(?string $timestamp, string $empty = '—'): string
{
    if (!$timestamp) { return $empty; }

    $time = strtotime($timestamp);

    return $time ? date('j M Y, g:ia', $time) : $empty;
}

/* "3 days ago" under the date. The exact date answers "when"; this
   answers "recently?", which is the question you actually have when
   scanning a column of them. */
function fmtAgo(?string $timestamp): string
{
    if (!$timestamp) { return ''; }

    $time = strtotime($timestamp);
    if (!$time) { return ''; }

    $seconds = time() - $time;

    if ($seconds < 60)      { return 'just now'; }
    if ($seconds < 3600)    { return floor($seconds / 60) . 'm ago'; }
    if ($seconds < 86400)   { return floor($seconds / 3600) . 'h ago'; }
    if ($seconds < 2592000) { return floor($seconds / 86400) . 'd ago'; }

    return fmtDate($timestamp);
}

/* ---------- asset paths ----------

   Admin pages live one folder down, so every asset needs a ../ in
   front of it. This builds that prefix once and appends
   ?v=<file modification time> — the same cache-busting the public
   side gets from assetUrl() in includes/header.php. Save admin.css
   and the number changes, so the browser fetches the new file rather
   than serving a stale copy that makes your edits look like they did
   nothing.

   Pass paths relative to the PROJECT ROOT — 'assets/css/admin.css',
   not '../assets/css/admin.css'. The ../ is added here. */
function adminAsset(string $path): string {
    $abs = __DIR__ . '/../' . $path;
    $url = '../' . $path;

    return is_file($abs) ? $url . '?v=' . filemtime($abs) : $url;
}

/* Same idea, but answers "is it there at all" — the sidebar photo
   and the seal are both optional files, and the markup for each is
   skipped entirely rather than left to 404. */
function adminAssetExists(string $path): bool {
    return is_file(__DIR__ . '/../' . $path);
}

/* ---------- CSRF ----------

   A token tying every form to this session.

   Without it, any page on the internet can contain a hidden form that
   posts to your delete endpoint. An admin who is logged in and
   visiting that page silently deletes a row — their browser attaches
   the session cookie automatically, and the request looks exactly
   like a real one. The token is the part an attacker cannot guess or
   read, which is what makes the difference. */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

/* hash_equals compares in constant time. A plain === returns as soon
   as two characters differ, and the time it took leaks how much of
   the guess was right. */
function csrfCheck(): void {
    $sent = $_POST['csrf'] ?? '';

    if (!is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('Bad request. Reload the page and try again.');
    }
}

/* ---------- one-shot messages ----------
   Stored in the session, printed once, then cleared — so a refresh
   after a save does not re-show "Saved" forever. */
function flash(string $message, string $type = 'ok'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function takeFlash(): ?array {
    if (empty($_SESSION['flash'])) return null;

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/* ---------- the unpublished count ----------
   The sidebar shows it on the Comments link from every page, so it
   is fetched here rather than in each page that happens to want it.
   A missing testimonials table is not an error worth a page for —
   the badge simply does not appear. */
function pendingComments(PDO $pdo): int
{
    static $count = null;

    if ($count !== null) { return $count; }

    try {
        $count = (int) $pdo->query(
            'SELECT COUNT(*) FROM testimonials WHERE is_published = false'
        )->fetchColumn();
    } catch (PDOException $e) {
        $count = 0;
    }

    return $count;
}

/* current file name, for marking the active sidebar link */
$adminHere = basename($_SERVER['PHP_SELF']);