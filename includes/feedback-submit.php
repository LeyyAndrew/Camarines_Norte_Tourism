<?php
/* ===================================================================
   includes/feedback-submit.php

   Receives the "Send feedback" form. Works two ways:
     - feedback.js posts with fetch() and gets JSON back
     - with JavaScript off, the form posts normally and is redirected
       back to the page it came from with ?feedback=sent (or =error)

   Only signed-in users can send. The session is checked HERE, not
   just by hiding the button — anyone can post to a URL.
   =================================================================== */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/db.php';

$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

function feedbackReply(bool $ok, string $message, int $code = 200): void
{
    global $wantsJson;

    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }

    /* No-JS fallback: back to the page they were on. Only a same-site
       path starting with a single "/" is accepted, so this cannot be
       abused to redirect someone to another website. */
    $back = $_POST['page_url'] ?? '';
    if (!is_string($back) || $back === '' || $back[0] !== '/' || preg_match('#^//|^/\\\\#', $back)) {
        $back = '../homepage.php';
    }
    $back = preg_replace('/([?&])feedback=[^&]*&?/', '$1', $back);
    $back = rtrim($back, '?&');
    $back .= (strpos($back, '?') === false ? '?' : '&') . 'feedback=' . ($ok ? 'sent' : 'error');

    header('Location: ' . $back);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    feedbackReply(false, 'Method not allowed.', 405);
}

if (!isset($_SESSION['user_id'])) {
    feedbackReply(false, 'Please sign in to send feedback.', 401);
}

/* Same CSRF token the admin panel uses ($_SESSION['csrf']). */
$sent = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || !is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
    feedbackReply(false, 'Your session expired. Reload the page and try again.', 400);
}

/* ---------- validate ---------- */
$categories = ['general', 'bug', 'suggestion', 'destination', 'other'];

$category = $_POST['category'] ?? 'general';
if (!is_string($category) || !in_array($category, $categories, true)) { $category = 'general'; }

$rating = $_POST['rating'] ?? '';
$rating = (is_string($rating) && ctype_digit($rating)) ? (int) $rating : null;
if ($rating !== null && ($rating < 1 || $rating > 5)) { $rating = null; }

$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$pageUrl = mb_substr((string) ($_POST['page_url'] ?? ''), 0, 255, 'UTF-8');

$subLen = mb_strlen($subject, 'UTF-8');
$msgLen = mb_strlen($message, 'UTF-8');

if ($subLen < 3 || $subLen > 120) {
    feedbackReply(false, 'Subject should be between 3 and 120 characters.', 422);
}
if ($msgLen < 10 || $msgLen > 2000) {
    feedbackReply(false, 'Message should be between 10 and 2000 characters.', 422);
}

try {
    $pdo = db();

    /* ---------- flood guard ----------
       Five messages in ten minutes is plenty for a real person and
       stops a stuck button or a script filling the admin inbox. */
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM feedback
          WHERE user_id = :uid AND created_at > NOW() - INTERVAL '10 minutes'"
    );
    $stmt->execute([':uid' => (int) $_SESSION['user_id']]);
    if ((int) $stmt->fetchColumn() >= 5) {
        feedbackReply(false, 'You have sent several messages just now. Please wait a few minutes.', 429);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO feedback (user_id, category, rating, subject, message, page_url)
         VALUES (:uid, :cat, :rating, :subject, :message, :page)'
    );
    $stmt->bindValue(':uid', (int) $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':cat', $category);
    $stmt->bindValue(':rating', $rating, $rating === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':subject', $subject);
    $stmt->bindValue(':message', $message);
    $stmt->bindValue(':page', $pageUrl !== '' ? $pageUrl : null, $pageUrl !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();
} catch (Throwable $e) {
    error_log('feedback insert failed: ' . $e->getMessage());
    feedbackReply(false, 'Something went wrong on our side. Please try again later.', 500);
}

feedbackReply(true, 'Your message was sent to the site administrator. Thank you.');