<?php
/* ===================================================================
   admin/index.php — the landing page.

   Counts only. Everything you can actually change lives on the other
   two pages; this is the "is anything waiting for me" screen.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';

$counts = ['users' => 0, 'admins' => 0, 'quotes' => 0, 'pending' => 0, 'new' => 0,
           'photos' => 0, 'places' => 0];

try {
    $counts['users']  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $counts['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    /* Signups in the last week. Only asked for if created_at is
       there — see the column probe in _bootstrap.php. */
    if (hasUserColumn($pdo, 'created_at')) {
        $counts['new'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL '7 days'"
        )->fetchColumn();
    }
} catch (PDOException $e) {
    error_log('admin overview users count failed: ' . $e->getMessage());
}

/* Separate try/catch on purpose. If testimonials does not exist yet,
   the user counts above should still show rather than the whole page
   dying over a table you have not created. */
try {
    $counts['quotes']  = (int) $pdo->query('SELECT COUNT(*) FROM testimonials')->fetchColumn();
    $counts['pending'] = (int) $pdo->query('SELECT COUNT(*) FROM testimonials WHERE is_published = false')->fetchColumn();
} catch (PDOException $e) {
    error_log('admin overview testimonials count failed: ' . $e->getMessage());
}

/* Site content counts. Separate try/catch again, and both default to
   0 — a copy of this project where the gallery or destinations tables
   have not been created yet should still show the rest of the page. */
try {
    $counts['photos'] = (int) $pdo->query('SELECT COUNT(*) FROM gallery_photos WHERE is_visible')->fetchColumn();
} catch (PDOException $e) {
    error_log('admin overview gallery count failed: ' . $e->getMessage());
}

try {
    $counts['places'] = (int) $pdo->query('SELECT COUNT(*) FROM destinations WHERE is_visible')->fetchColumn();
} catch (PDOException $e) {
    error_log('admin overview destinations count failed: ' . $e->getMessage());
}

/* ---------- the newest accounts ----------
   Five is enough to answer "has anyone joined lately" without
   turning the overview into a second users page. */
$recent = [];

if (hasUserColumn($pdo, 'created_at')) {
    try {
        $recent = $pdo->query(
            'SELECT id, firstname, lastname, email, role, created_at
               FROM users
           ORDER BY created_at DESC, id DESC
              LIMIT 5'
        )->fetchAll();
    } catch (PDOException $e) {
        error_log('recent users failed: ' . $e->getMessage());
    }
}

$adminTitle   = 'Overview';
$adminEyebrow = 'Dashboard';
require __DIR__ . '/_header.php';
?>

<header class="adm-head">
  <div>
    <span class="adm-eyebrow">Dashboard</span>
    <h1 class="adm-title">Hi, <?= e($me['firstname']) ?></h1>
    <p class="adm-sub">Everything that is live on the site, and everything waiting on you.</p>
  </div>

  <p class="adm-head__aside"><?= e(date('l j F Y')) ?></p>
</header>

<!-- The unpublished card is the only one that ever changes colour,
     and it does so only when the number is not zero. A dashboard
     where something is always highlighted has nothing left to say
     when something is actually wrong. -->
<div class="adm-stats">
  <div class="adm-stat">
    <span class="adm-stat__num"><?= $counts['users'] ?></span>
    <span class="adm-stat__label">Accounts</span>
  </div>

  <div class="adm-stat">
    <span class="adm-stat__num"><?= $counts['admins'] ?></span>
    <span class="adm-stat__label">Admins</span>
  </div>

  <div class="adm-stat">
    <span class="adm-stat__num"><?= $counts['quotes'] ?></span>
    <span class="adm-stat__label">Comments</span>
  </div>

  <div class="adm-stat<?= $counts['pending'] ? ' adm-stat--flag' : '' ?>">
    <span class="adm-stat__num"><?= $counts['pending'] ?></span>
    <span class="adm-stat__label">Unpublished</span>
  </div>

  <?php if (hasUserColumn($pdo, 'created_at')): ?>
    <div class="adm-stat">
      <span class="adm-stat__num"><?= $counts['new'] ?></span>
      <span class="adm-stat__label">New this week</span>
    </div>
  <?php endif; ?>
</div>

<div class="adm-cards">
  <a class="adm-card" href="testimonials.php">
    <h2 class="adm-card__title">Comments</h2>
    <p class="adm-card__text">
      <?= $counts['pending']
            ? 'There ' . ($counts['pending'] === 1 ? 'is 1 comment' : 'are ' . $counts['pending'] . ' comments') . ' nobody has published yet.'
            : 'Add, edit and delete visitor quotes. Nothing appears on the homepage until you publish it.' ?>
    </p>
    <span class="adm-card__go"><?= $counts['pending'] ? 'Review them' : 'Manage comments' ?></span>
  </a>

  <a class="adm-card" href="gallery.php">
    <h2 class="adm-card__title">Gallery</h2>
    <p class="adm-card__text">
      <?= $counts['photos']
            ? $counts['photos'] . ' photographs are on the gallery page. Add, recaption and reorder them.'
            : 'Add, recaption and reorder the photographs on the gallery page.' ?>
    </p>
    <span class="adm-card__go">Manage photographs</span>
  </a>

  <a class="adm-card" href="destinations.php">
    <h2 class="adm-card__title">Destinations</h2>
    <p class="adm-card__text">
      <?= $counts['places']
            ? $counts['places'] . ' places on the destinations page, the homepage rail and the map.'
            : 'The places on the destinations page, the homepage rail and the map.' ?>
    </p>
    <span class="adm-card__go">Manage destinations</span>
  </a>

  <a class="adm-card" href="users.php">
    <h2 class="adm-card__title">Users</h2>
    <p class="adm-card__text">See who has registered, change their details, suspend an account, and set who is an admin.</p>
    <span class="adm-card__go">Manage users</span>
  </a>
</div>

<?php if ($recent): ?>
<section class="adm-panel" style="margin-top:1.4rem">
  <div class="adm-panel__head">
    <h2 class="adm-panel__title">Newest accounts</h2>
    <a href="users.php" class="adm-btn adm-btn--sm adm-btn--ghost" style="margin-left:auto">See all</a>
  </div>

  <div class="adm-panel__body adm-panel__body--flush">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Person</th>
          <th>Role</th>
          <th>Joined</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td>
              <div class="adm-person">
                <span class="adm-person__avatar<?= $row['role'] === 'admin' ? ' adm-person__avatar--admin' : '' ?>" aria-hidden="true"><?= e(initials($row['firstname'], $row['lastname'])) ?></span>
                <span class="adm-person__body">
                  <span class="adm-person__name"><?= e($row['firstname'] . ' ' . $row['lastname']) ?></span>
                  <span class="adm-person__mail"><?= e($row['email']) ?></span>
                </span>
              </div>
            </td>

            <td>
              <span class="adm-badge<?= $row['role'] === 'admin' ? ' adm-badge--admin' : '' ?>"><?= e($row['role']) ?></span>
            </td>

            <td>
              <?= e(fmtDate($row['created_at'])) ?>
              <span class="adm-muted"><?= e(fmtAgo($row['created_at'])) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>