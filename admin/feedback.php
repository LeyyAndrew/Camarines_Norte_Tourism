<?php
/* ===================================================================
   admin/feedback.php — the feedback inbox.

   Everything signed-in visitors sent with the "Send feedback" button.
   Filter by status, open a message (which marks it read), mark it
   new or read, publish it to the homepage, or delete it.

   PUBLISHING copies the message into the testimonials table — the
   same table admin/testimonials.php (Comments) manages and the
   homepage reads. So once published it can also be hidden, edited or
   deleted from Comments, exactly like any other comment.

   All changes are POST + CSRF, then redirect back (Post/Redirect/Get),
   so refreshing never repeats an action.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';

$statuses   = ['new' => 'New', 'read' => 'Read', 'resolved' => 'Resolved'];
$categories = [
    'general'     => 'General',
    'suggestion'  => 'Suggestion',
    'bug'         => 'Bug',
    'destination' => 'Destination info',
    'other'       => 'Other',
];

/* ---------- can this database publish? ----------
   Needs the testimonial_id column from sql/feedback-publish.sql.
   Without it the Publish button simply does not appear. */
function feedbackCanPublish(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) { return $ok; }
    try {
        $stmt = $pdo->query(
            "SELECT 1 FROM information_schema.columns
              WHERE table_schema = 'public' AND table_name = 'feedback'
                AND column_name = 'testimonial_id'"
        );
        $ok = (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}
$canPublish = feedbackCanPublish($pdo);

/* ---------- where to go back to ---------- */
$filter = $_GET['status'] ?? 'all';
if ($filter !== 'all' && !isset($statuses[$filter])) { $filter = 'all'; }

$search = trim((string) ($_GET['q'] ?? ''));
$page   = max(1, (int) ($_GET['page'] ?? 1));

function feedbackBackUrl(string $filter, string $search, int $page): string
{
    $q = [];
    if ($filter !== 'all') { $q['status'] = $filter; }
    if ($search !== '')    { $q['q'] = $search; }
    if ($page > 1)         { $q['page'] = $page; }
    return 'feedback.php' . ($q ? '?' . http_build_query($q) : '');
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        if ($id <= 0) { throw new RuntimeException('Missing feedback id.'); }

        switch ($action) {
            case 'status':
                $to = $_POST['to'] ?? '';
                if (!isset($statuses[$to])) { throw new RuntimeException('Unknown status.'); }
                $stmt = $pdo->prepare('UPDATE feedback SET status = :s, updated_at = NOW() WHERE id = :id');
                $stmt->execute([':s' => $to, ':id' => $id]);
                flash('Feedback marked ' . strtolower($statuses[$to]) . '.');
                break;

            case 'publish':
                if (!$canPublish) { throw new RuntimeException('Run sql/feedback-publish.sql first.'); }

                $stmt = $pdo->prepare(
                    'SELECT f.id, f.rating, f.message, f.created_at, f.testimonial_id,
                            u.firstname
                       FROM feedback f
                  LEFT JOIN users u ON u.id = f.user_id
                      WHERE f.id = :id'
                );
                $stmt->execute([':id' => $id]);
                $fb = $stmt->fetch();
                if (!$fb) { throw new RuntimeException('Feedback not found.'); }

                $pdo->beginTransaction();

                if ($fb['testimonial_id']) {
                    /* Published before, then hidden: just show it again,
                       keeping any edits made in Comments. */
                    $stmt = $pdo->prepare('UPDATE testimonials SET is_published = true WHERE id = :tid');
                    $stmt->execute([':tid' => $fb['testimonial_id']]);
                } else {
                    /* First name only, like the rest of the homepage
                       register. No rating given counts as 5 because the
                       testimonials table requires one (1-5). */
                    $name = trim((string) $fb['firstname']);
                    if ($name === '') { $name = 'Visitor'; }
                    $name = mb_substr($name, 0, 100, 'UTF-8');

                    $rating = (int) ($fb['rating'] ?? 0);
                    if ($rating < 1 || $rating > 5) { $rating = 5; }

                    $stmt = $pdo->prepare(
                        'INSERT INTO testimonials (name, hometown, rating, quote, is_published, created_at)
                         VALUES (:name, NULL, :rating, :quote, true, :created)
                      RETURNING id'
                    );
                    $stmt->execute([
                        ':name'    => $name,
                        ':rating'  => $rating,
                        ':quote'   => $fb['message'],
                        ':created' => $fb['created_at'],
                    ]);
                    $tid = (int) $stmt->fetchColumn();

                    $stmt = $pdo->prepare('UPDATE feedback SET testimonial_id = :tid, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([':tid' => $tid, ':id' => $id]);
                }

                $pdo->commit();
                flash('Feedback published to the homepage.');
                break;

            case 'unpublish':
                if (!$canPublish) { throw new RuntimeException('Run sql/feedback-publish.sql first.'); }

                /* Hidden, not deleted — same as Hide in Comments, so
                   publishing again brings back the same row. */
                $stmt = $pdo->prepare(
                    'UPDATE testimonials SET is_published = false
                      WHERE id = (SELECT testimonial_id FROM feedback WHERE id = :id)'
                );
                $stmt->execute([':id' => $id]);
                flash('Feedback removed from the homepage.');
                break;

            case 'delete':
                $stmt = $pdo->prepare('DELETE FROM feedback WHERE id = :id');
                $stmt->execute([':id' => $id]);
                flash('Feedback deleted.');
                break;

            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('feedback admin action failed: ' . $e->getMessage());
        flash('That did not work. Please try again.', 'bad');
    }

    header('Location: ' . feedbackBackUrl($filter, $search, $page));
    exit;
}

/* ---------- opening a message marks it read ----------
   ?open=ID from the list. Only "new" becomes "read" — opening a
   resolved one must not reopen it. */
$openId = (int) ($_GET['open'] ?? 0);
if ($openId > 0) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE feedback SET status = 'read', updated_at = NOW() WHERE id = :id AND status = 'new'"
        );
        $stmt->execute([':id' => $openId]);
    } catch (PDOException $e) {
        error_log('feedback mark-read failed: ' . $e->getMessage());
    }
}

/* ---------- counts for the tabs ---------- */
$tabCounts = ['all' => 0, 'new' => 0, 'read' => 0, 'resolved' => 0];
$avgRating = null;
$tableMissing = false;

try {
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM feedback GROUP BY status') as $row) {
        $tabCounts[$row['status']] = (int) $row['n'];
        $tabCounts['all'] += (int) $row['n'];
    }
    $avg = $pdo->query('SELECT AVG(rating) FROM feedback WHERE rating IS NOT NULL')->fetchColumn();
    $avgRating = $avg !== null && $avg !== false ? round((float) $avg, 1) : null;
} catch (PDOException $e) {
    $tableMissing = true;
    error_log('feedback counts failed: ' . $e->getMessage());
}

/* ---------- the list ---------- */
$perPage = 20;
$rows    = [];
$total   = 0;

if (!$tableMissing) {
    $where  = [];
    $params = [];

    if ($filter !== 'all') {
        $where[] = 'f.status = :status';
        $params[':status'] = $filter;
    }
    if ($search !== '') {
        /* ILIKE is PostgreSQL's case-insensitive LIKE. The % and _
           in the user's own text are escaped so they are searched
           for literally. */
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(f.subject ILIKE :q1 OR f.message ILIKE :q2 OR u.email ILIKE :q3
                     OR (u.firstname || \' \' || u.lastname) ILIKE :q4)';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = $like;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback f LEFT JOIN users u ON u.id = f.user_id $whereSql");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($page, $pages);

        $stmt = $pdo->prepare(
            "SELECT f.id, f.category, f.rating, f.subject, f.message,
                    f.status, f.created_at, f.updated_at,
                    u.id AS uid, u.firstname, u.lastname, u.email,
                    " . ($canPublish ? 't.is_published AS on_home' : 'NULL AS on_home') . "
               FROM feedback f
          LEFT JOIN users u ON u.id = f.user_id
               " . ($canPublish ? 'LEFT JOIN testimonials t ON t.id = f.testimonial_id' : '') . "
               $whereSql
           ORDER BY CASE f.status WHEN 'new' THEN 0 WHEN 'read' THEN 1 ELSE 2 END,
                    f.created_at DESC
              LIMIT $perPage OFFSET " . (($page - 1) * $perPage)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('feedback list failed: ' . $e->getMessage());
    }
}
$pages = max(1, (int) ceil($total / $perPage));

$adminTitle   = 'Feedback';
$adminEyebrow = 'Inbox';
require __DIR__ . '/_header.php';

function stars(?int $n): string
{
    if (!$n) { return '<span class="adm-muted">No rating</span>'; }
    return '<span class="fbA-stars" title="' . $n . ' out of 5" aria-label="' . $n . ' out of 5">'
         . str_repeat('★', $n) . '<span class="fbA-stars__off">' . str_repeat('★', 5 - $n) . '</span></span>';
}
?>

<style>
  /* Page-only styles. Everything else comes from admin.css (adm-*). */
  .fbA-tabs{ display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; margin:0 0 1rem; }
  .fbA-tab{ font-family:var(--adm-font-display,'Archivo',Arial,sans-serif); padding:.45rem .9rem; border-radius:999px; font-size:.82rem; font-weight:600;
            text-decoration:none; color:inherit; background:rgba(21,26,23,.06); }
  .fbA-tab.is-on{ background:var(--adm-ink,#151A17); color:#fff; }
  .fbA-tab b{ margin-left:.3rem; font-weight:700; opacity:.65; }
  .fbA-search{ margin-left:auto; display:flex; gap:.4rem; }
  .fbA-search input{ padding:.45rem .8rem; border-radius:999px; border:1px solid rgba(21,26,23,.15); font:inherit; font-size:.85rem; min-width:220px; }
  .fbA-item{ border-bottom:1px solid rgba(21,26,23,.08); }
  .fbA-item:last-child{ border-bottom:0; }
  .fbA-item > summary{ list-style:none; cursor:pointer; display:grid;
        grid-template-columns:minmax(180px,1.1fr) minmax(0,2fr) auto auto; gap:1rem;
        align-items:center; padding:.95rem 1.2rem; }
  .fbA-item > summary::-webkit-details-marker{ display:none; }
  .fbA-item > summary:hover{ background:rgba(21,26,23,.03); }
  .fbA-item.is-new > summary{ box-shadow:inset 3px 0 0 var(--adm-gold,#D9A23A); }
  .fbA-item.is-new .fbA-subject{ font-weight:700; }
  .fbA-subject{ display:block; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .fbA-preview{ display:block; font-size:.82rem; opacity:.6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .fbA-stars{ color:var(--adm-gold,#D9A23A); letter-spacing:.05em; white-space:nowrap; }
  .fbA-stars__off{ color:rgba(21,26,23,.18); }
  .fbA-status{ text-transform:capitalize; }
  .fbA-badges{ display:flex; gap:.35rem; flex-wrap:wrap; justify-content:flex-end; }
  .fbA-status--new{ background:#FFF1DA; color:#8A5A00; }
  .fbA-body{ padding:0 1.2rem 1.3rem; display:grid; grid-template-columns:minmax(0,2fr) minmax(220px,1fr); gap:1.4rem; }
  .fbA-msg{ white-space:pre-wrap; line-height:1.6; background:rgba(21,26,23,.04); padding:1rem 1.1rem; border-radius:12px; margin:0; }
  .fbA-meta{ margin-top:1rem; }
  .fbA-meta code{ word-break:break-all; font-size:.8em; }
  .fbA-side form{ margin:0 0 .7rem; }
  .fbA-actions{ display:flex; flex-wrap:wrap; gap:.4rem; }
  .fbA-pager{ display:flex; gap:.4rem; justify-content:center; padding:1rem; }
  @media (max-width:860px){
    .fbA-item > summary{ grid-template-columns:1fr auto; }
    .fbA-item > summary .fbA-col-rating{ display:none; }
    .fbA-body{ grid-template-columns:1fr; }
    .fbA-search{ margin-left:0; width:100%; }
    .fbA-search input{ flex:1; min-width:0; }
  }
</style>

<header class="adm-head">
  <div>
    <span class="adm-eyebrow">Inbox</span>
    <h1 class="adm-title">Feedback</h1>
    <p class="adm-sub">What signed-in visitors told you with the "Send feedback" button.</p>
  </div>
  <?php if ($avgRating !== null): ?>
    <p class="adm-head__aside">Average rating <strong><?= e((string) $avgRating) ?></strong> / 5</p>
  <?php endif; ?>
</header>

<?php if ($tableMissing): ?>
  <p class="adm-flash adm-flash--bad">
    The feedback table does not exist yet. Run sql/feedback.sql in pgAdmin first.
  </p>
<?php else: ?>

<?php if (!$canPublish): ?>
  <p class="adm-flash adm-flash--bad">
    To publish feedback on the homepage, run sql/feedback-publish.sql in pgAdmin once.
  </p>
<?php endif; ?>

<div class="fbA-tabs">
  <?php foreach (['all' => 'All'] + $statuses as $key => $label): ?>
    <a class="fbA-tab<?= $filter === $key ? ' is-on' : '' ?>" href="<?= e(feedbackBackUrl($key, $search, 1)) ?>">
      <?= e($label) ?><b><?= $tabCounts[$key] ?></b>
    </a>
  <?php endforeach; ?>

  <form class="fbA-search" method="get" action="feedback.php">
    <?php if ($filter !== 'all'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search subject, message, name, email">
    <button class="adm-btn adm-btn--sm adm-btn--ghost" type="submit">Search</button>
  </form>
</div>

<section class="adm-panel">
  <div class="adm-panel__body adm-panel__body--flush">
    <?php if (!$rows): ?>
      <p class="adm-empty"><?= $search !== '' ? 'Nothing matches that search.' : ($filter === 'all' ? 'No feedback yet. It appears here when a signed-in visitor presses Send feedback.' : 'Nothing ' . strtolower($statuses[$filter]) . ' right now.') ?></p>
    <?php endif; ?>

    <?php foreach ($rows as $r):
      $name = trim(($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
      $isOpen = $openId === (int) $r['id'];
    ?>
      <details class="fbA-item<?= $r['status'] === 'new' ? ' is-new' : '' ?>" id="fb-<?= (int) $r['id'] ?>"<?= $isOpen ? ' open' : '' ?>>
        <summary data-open-url="<?= e(feedbackBackUrl($filter, $search, $page)) ?>" data-id="<?= (int) $r['id'] ?>">
          <div class="adm-person">
            <span class="adm-person__avatar" aria-hidden="true"><?= e($r['uid'] ? initials($r['firstname'], $r['lastname']) : '?') ?></span>
            <span class="adm-person__body">
              <span class="adm-person__name"><?= e($r['uid'] ? $name : 'Deleted account') ?></span>
              <span class="adm-person__mail"><?= e(fmtAgo($r['created_at'])) ?></span>
            </span>
          </div>

          <div style="min-width:0">
            <span class="fbA-subject"><?= e($r['subject']) ?></span>
            <span class="fbA-preview"><?= e($categories[$r['category']] ?? $r['category']) ?> · <?= e(mb_substr($r['message'], 0, 120, 'UTF-8')) ?></span>
          </div>

          <div class="fbA-col-rating"><?= stars($r['rating'] !== null ? (int) $r['rating'] : null) ?></div>

          <span class="fbA-badges">
          <?php if (!empty($r['on_home'])): ?>
            <span class="adm-badge adm-badge--live" title="Shown in the homepage reviews">On homepage</span>
          <?php endif; ?>
          <span class="adm-badge fbA-status<?= $r['status'] === 'resolved' ? ' adm-badge--live' : ($r['status'] === 'new' ? ' fbA-status--new' : '') ?>"><?= e($r['status']) ?></span>
          </span>
        </summary>

        <div class="fbA-body">
          <div>
            <p class="fbA-msg"><?= e($r['message']) ?></p>

            <dl class="adm-meta fbA-meta">
              <div class="adm-meta__item">
                <dt class="adm-meta__key">From</dt>
                <dd class="adm-meta__value">
                  <?php if ($r['uid']): ?>
                    <?= e($name) ?>
                    <span class="adm-meta__sub"><a href="mailto:<?= e($r['email']) ?>?subject=<?= rawurlencode('Re: ' . $r['subject']) ?>"><?= e($r['email']) ?></a></span>
                  <?php else: ?>
                    Account no longer exists
                  <?php endif; ?>
                </dd>
              </div>
              <div class="adm-meta__item">
                <dt class="adm-meta__key">Type</dt>
                <dd class="adm-meta__value"><?= e($categories[$r['category']] ?? $r['category']) ?></dd>
              </div>
              <div class="adm-meta__item">
                <dt class="adm-meta__key">Rating</dt>
                <dd class="adm-meta__value"><?= stars($r['rating'] !== null ? (int) $r['rating'] : null) ?></dd>
              </div>
              <div class="adm-meta__item">
                <dt class="adm-meta__key">Sent</dt>
                <dd class="adm-meta__value">
                  <?= e(fmtDateTime($r['created_at'])) ?>
                  <span class="adm-meta__sub"><?= e(fmtAgo($r['created_at'])) ?></span>
                </dd>
              </div>
            </dl>
          </div>

          <div class="fbA-side">
            <div class="fbA-actions">
              <?php /* Mark new / Mark read. "Mark resolved" was removed on purpose. */ ?>
              <?php foreach (['new' => 'Mark new', 'read' => 'Mark read'] as $key => $label): if ($key === $r['status']) continue; ?>
                <form method="post" class="adm-inline" action="<?= e(feedbackBackUrl($filter, $search, $page)) ?>">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="to" value="<?= e($key) ?>">
                  <button class="adm-btn adm-btn--sm adm-btn--ghost" type="submit"><?= e($label) ?></button>
                </form>
              <?php endforeach; ?>

              <?php if ($canPublish): ?>
                <?php $onHome = !empty($r['on_home']); ?>
                <form method="post" class="adm-inline" action="<?= e(feedbackBackUrl($filter, $search, $page)) ?>">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="<?= $onHome ? 'unpublish' : 'publish' ?>">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <button class="adm-btn adm-btn--sm<?= $onHome ? ' adm-btn--ghost' : '' ?>" type="submit"
                          title="<?= $onHome ? 'Hide it from the homepage reviews' : 'Show this message in the homepage reviews' ?>">
                    <?= $onHome ? 'Unpublish' : 'Publish' ?>
                  </button>
                </form>
              <?php endif; ?>

              <form method="post" class="adm-inline" action="<?= e(feedbackBackUrl($filter, $search, $page)) ?>"
                    data-confirm
                    data-confirm-title="Delete this feedback?"
                    data-confirm-body="<?= e('“' . mb_strimwidth($r['subject'], 0, 60, '…', 'UTF-8') . '” is removed from the inbox.') ?>"
                    data-confirm-note="There is no undo. If it is on the homepage it stays there — remove it from Comments."
                    data-confirm-action="Delete permanently">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="adm-btn adm-btn--sm adm-btn--danger" type="submit">Delete</button>
              </form>
            </div>
          </div>
        </div>
      </details>
    <?php endforeach; ?>

    <?php if ($pages > 1): ?>
      <nav class="fbA-pager" aria-label="Pages">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <a class="fbA-tab<?= $p === $page ? ' is-on' : '' ?>" href="<?= e(feedbackBackUrl($filter, $search, $p)) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>

<script>
  /* Opening a "new" message marks it read on the server, without a
     page reload. The ?open= link does the same if this never runs. */
  document.querySelectorAll('.fbA-item.is-new > summary').forEach(function (s) {
    s.addEventListener('click', function () {
      var item = s.parentElement;
      if (item.open || !item.classList.contains('is-new')) return;
      fetch('feedback.php?open=' + encodeURIComponent(s.dataset.id), { credentials: 'same-origin' });
      item.classList.remove('is-new');
      var badge = s.querySelector('.fbA-status');
      if (badge) { badge.textContent = 'read'; badge.className = 'adm-badge fbA-status'; }
    });
  });
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>