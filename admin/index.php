<?php
/* ===================================================================
   admin/index.php — the landing page.

   Three layers, from most to least urgent:
     1. the band at the top: who you are, what is waiting on you, and
        how visitors rate the province right now
     2. one quiet row of numbers
     3. the site's content as a register — one row per thing you can
        manage — then the latest feedback and the newest accounts

   Every query has its own try/catch: a table that does not exist yet
   gives a zero, never a broken page.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';

$counts = ['users' => 0, 'admins' => 0, 'quotes' => 0, 'pending' => 0, 'new' => 0,
           'photos' => 0, 'places' => 0, 'feedback' => 0, 'fb_new' => 0];

try {
    $counts['users']  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $counts['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    if (hasUserColumn($pdo, 'created_at')) {
        $counts['new'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL '7 days'"
        )->fetchColumn();
    }
} catch (PDOException $e) {
    error_log('admin overview users count failed: ' . $e->getMessage());
}

try {
    $counts['quotes']  = (int) $pdo->query('SELECT COUNT(*) FROM testimonials')->fetchColumn();
    $counts['pending'] = (int) $pdo->query('SELECT COUNT(*) FROM testimonials WHERE is_published = false')->fetchColumn();
} catch (PDOException $e) {
    error_log('admin overview testimonials count failed: ' . $e->getMessage());
}

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

try {
    $counts['feedback'] = (int) $pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
    $counts['fb_new']   = newFeedback($pdo);
} catch (PDOException $e) {
    error_log('admin overview feedback count failed: ' . $e->getMessage());
}

/* ---------- the score ----------
   The same number the homepage shows: the average of PUBLISHED
   reviews only, so what you see here is what visitors see. */
$score      = null;
$scoreCount = 0;
try {
    $row = $pdo->query(
        'SELECT AVG(rating) AS avg, COUNT(*) AS n FROM testimonials WHERE is_published = true'
    )->fetch();
    $scoreCount = (int) ($row['n'] ?? 0);
    if ($scoreCount > 0) { $score = round((float) $row['avg'], 1); }
} catch (PDOException $e) {
    error_log('admin overview score failed: ' . $e->getMessage());
}

/* ---------- the newest accounts ---------- */
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

/* ---------- the latest feedback ---------- */
$latestFb = [];
try {
    $latestFb = $pdo->query(
        'SELECT f.id, f.subject, f.rating, f.status, f.created_at,
                u.firstname, u.lastname
           FROM feedback f
      LEFT JOIN users u ON u.id = f.user_id
       ORDER BY f.created_at DESC
          LIMIT 5'
    )->fetchAll();
} catch (PDOException $e) {
    /* no feedback table yet — the panel shows its empty state */
}

/* ---------- the greeting ----------
   In Bikol. Philippine time, whatever timezone the server is set to,
   so a server abroad does not say "Marhay na aga" at night.
     before 12:00  Marhay na aga
     12:00-17:59   Marhay na hapon
     18:00 on      Marhay na banggi */
$phNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
$hour  = (int) $phNow->format('G');
$hello = $hour < 12 ? 'Marhay na aga' : ($hour < 18 ? 'Marhay na hapon' : 'Marhay na banggi');

/* ---------- what is waiting ----------
   Only things that need a decision. Each becomes a link straight to
   the filtered list. */
$todo = [];
if ($counts['pending']) {
    $todo[] = ['href' => 'testimonials.php',
               'text' => $counts['pending'] . ($counts['pending'] === 1 ? ' comment to publish' : ' comments to publish')];
}
if ($counts['fb_new']) {
    $todo[] = ['href' => 'feedback.php?status=new',
               'text' => $counts['fb_new'] . ' unread feedback'];
}

/* ---------- the band photograph ----------
   First one that exists is used, under a heavy green wash so the
   text stays readable. None found: the plain green band. */
$heroPhoto = '';
foreach (['uploads/admin-hero.jpg', 'uploads/Homepage-Photo/Travel-Calaguas-2.jpg',
          'uploads/nav-icons/Calaguas-Nav.jpg', 'uploads/admin-side.jpg'] as $candidate) {
    if (adminAssetExists($candidate)) { $heroPhoto = adminAsset($candidate); break; }
}

/* Small helper for the star strings. */
function ovStars(float $value): string
{
    $full = (int) round($value);
    return '<span class="ov-stars" aria-hidden="true">'
         . str_repeat('★', $full)
         . '<span class="ov-stars__off">' . str_repeat('★', 5 - $full) . '</span></span>';
}

/* The register rows. Order: things with work waiting first. */
$register = [
    [
        'href'  => 'testimonials.php',
        'title' => 'Comments',
        'text'  => $counts['pending']
                    ? ($counts['pending'] === 1 ? 'One comment is' : $counts['pending'] . ' comments are') . ' waiting to be published on the homepage.'
                    : 'Visitor reviews on the homepage. Nothing appears until you publish it.',
        'num'   => $counts['quotes'],
        'unit'  => $counts['quotes'] === 1 ? 'review' : 'reviews',
        'flag'  => $counts['pending'],
        'go'    => $counts['pending'] ? 'Review' : 'Manage',
        'icon'  => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.6-.7L3 21l1.9-5a8.2 8.2 0 0 1-.9-3.7A8.4 8.4 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8Z"/>',
    ],
    [
        'href'  => 'feedback.php' . ($counts['fb_new'] ? '?status=new' : ''),
        'title' => 'Feedback',
        'text'  => $counts['fb_new']
                    ? 'Signed-in visitors sent ' . $counts['fb_new'] . ' ' . ($counts['fb_new'] === 1 ? 'message' : 'messages') . ' you have not opened yet.'
                    : 'Bugs, ideas and corrections from signed-in visitors. Good ones can go on the homepage.',
        'num'   => $counts['feedback'],
        'unit'  => $counts['feedback'] === 1 ? 'message' : 'messages',
        'flag'  => $counts['fb_new'],
        'go'    => $counts['fb_new'] ? 'Read' : 'Open',
        'icon'  => '<path d="M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-9l-5 4v-4H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/><path d="M8 9h8"/><path d="M8 12.5h5"/>',
    ],
    [
        'href'  => 'destinations.php',
        'title' => 'Destinations',
        'text'  => 'The places on the destinations page, the homepage rail and the map.',
        'num'   => $counts['places'],
        'unit'  => $counts['places'] === 1 ? 'place live' : 'places live',
        'flag'  => 0,
        'go'    => 'Manage',
        'icon'  => '<path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/>',
    ],
    [
        'href'  => 'gallery.php',
        'title' => 'Gallery',
        'text'  => 'Photographs on the gallery page. Add, recaption and reorder them.',
        'num'   => $counts['photos'],
        'unit'  => $counts['photos'] === 1 ? 'photo live' : 'photos live',
        'flag'  => 0,
        'go'    => 'Manage',
        'icon'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-4.5-4.5L3 21"/>',
    ],
    [
        'href'  => 'users.php',
        'title' => 'Users',
        'text'  => 'Everyone who has registered. Edit details, suspend an account, choose who is an admin.',
        'num'   => $counts['users'],
        'unit'  => $counts['users'] === 1 ? 'account' : 'accounts',
        'flag'  => 0,
        'go'    => 'Manage',
        'icon'  => '<path d="M16 20v-1.6a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7.5" r="3.5"/><path d="M22 20v-1.6a4 4 0 0 0-3-3.8"/><path d="M16.5 4.2a4 4 0 0 1 0 6.6"/>',
    ],
];
usort($register, fn($a, $b) => ($b['flag'] > 0) <=> ($a['flag'] > 0));

$adminTitle   = 'Overview';
$adminEyebrow = 'Dashboard';
require __DIR__ . '/_header.php';
?>

<style>
/* ===================================================================
   OVERVIEW — page-only styles. Colours follow the sidebar: forest
   green for the band, the panel's leaf green for links, gold only for
   things that are waiting on a decision.
   =================================================================== */
.ov{
  --ov-forest:#0F2A1E;
  --ov-forest-2:#16392A;
  --ov-leaf:#1E8A55;
  --ov-gold:#E0A93B;
  --ov-ink:var(--adm-ink,#151A17);
  --ov-muted:var(--adm-muted,#69736C);
  --ov-line:rgba(21,26,23,.09);
  --ov-panel:var(--adm-panel,#fff);
  --ov-display:var(--adm-font-display,'Archivo',Arial,sans-serif);
  display:flex; flex-direction:column; gap:1.6rem;
}

/* ---------- 1. the band ---------- */
.ov-band{
  position:relative; overflow:hidden;
  display:grid; grid-template-columns:minmax(0,1fr) auto; gap:2rem; align-items:end;
  padding:2.2rem 2.4rem 2.1rem;
  border-radius:18px;
  background:var(--ov-forest);
  color:#fff;
  isolation:isolate;
}
.ov-band__photo{
  position:absolute; inset:0; z-index:-2;
  background-size:cover; background-position:center 60%;
  opacity:.38;
}
.ov-band::before{          /* the wash that keeps text readable */
  content:""; position:absolute; inset:0; z-index:-1;
  background:linear-gradient(100deg, var(--ov-forest) 30%, rgba(15,42,30,.72) 60%, rgba(15,42,30,.55));
}
.ov-band__date{ margin:0 0 .6rem; font-size:.85rem; color:rgba(255,255,255,.62); }
.ov-band__hello{
  margin:0; font-family:var(--ov-display);
  font-size:clamp(1.9rem, 3.4vw, 2.8rem); font-weight:700;
  line-height:1.05; letter-spacing:-.025em;
}
.ov-band__hello span{ display:block; color:rgba(255,255,255,.55); font-weight:500; }

.ov-todo{ display:flex; flex-wrap:wrap; gap:.5rem; margin:1.4rem 0 0; padding:0; list-style:none; }
.ov-todo a{
  display:inline-flex; align-items:center; gap:.5rem;
  padding:.5rem .95rem .5rem .75rem; border-radius:999px;
  background:var(--ov-gold); color:var(--ov-forest);
  font-weight:600; font-size:.86rem; text-decoration:none;
  transition:background .2s ease;
}
.ov-todo a:hover{ background:#F0BC52; }
.ov-todo a::before{
  content:""; width:.5rem; height:.5rem; border-radius:50%;
  background:var(--ov-forest); opacity:.75;
}
.ov-todo__clear{
  display:inline-flex; align-items:center; gap:.5rem;
  padding:.5rem .95rem; border-radius:999px;
  background:rgba(255,255,255,.1); color:rgba(255,255,255,.85); font-size:.86rem;
}
.ov-todo__clear svg{ width:16px; height:16px; color:#7FD3A5; }

.ov-score{
  text-align:right; padding-left:2rem;
  border-left:1px solid rgba(255,255,255,.16);
}
.ov-score__num{
  display:block; font-family:var(--ov-display);
  font-size:clamp(3rem, 6vw, 4.6rem); font-weight:700;
  line-height:.9; letter-spacing:-.04em;
}
.ov-score .ov-stars{ display:block; margin:.55rem 0 .3rem; font-size:1.05rem; letter-spacing:.12em; color:var(--ov-gold); }
.ov-score .ov-stars__off{ color:rgba(255,255,255,.2); }
.ov-score__label{ font-size:.8rem; color:rgba(255,255,255,.6); }
.ov-score--empty .ov-score__num{ color:rgba(255,255,255,.35); }

/* ---------- 2. the numbers ---------- */
.ov-nums{
  display:grid; grid-template-columns:repeat(5, minmax(0,1fr));
  background:var(--ov-panel); border:1px solid var(--ov-line); border-radius:14px;
}
.ov-num{ padding:1.1rem 1.3rem; border-left:1px solid var(--ov-line); }
.ov-num:first-child{ border-left:0; }
.ov-num__value{
  display:block; font-family:var(--ov-display);
  font-size:1.9rem; font-weight:700; line-height:1; letter-spacing:-.02em; color:var(--ov-ink);
}
.ov-num__label{ display:block; margin-top:.35rem; font-size:.84rem; color:var(--ov-muted); }
.ov-num--up .ov-num__value{ color:var(--ov-leaf); }

/* ---------- 3. the register ---------- */
.ov-block{ background:var(--ov-panel); border:1px solid var(--ov-line); border-radius:14px; overflow:hidden; }
.ov-block__head{
  display:flex; align-items:baseline; justify-content:space-between; gap:1rem;
  padding:1.05rem 1.4rem; border-bottom:1px solid var(--ov-line);
}
.ov-block__title{ margin:0; font-family:var(--ov-display); font-size:1.02rem; font-weight:700; color:var(--ov-ink); }
.ov-block__more{ font-size:.84rem; font-weight:600; color:var(--ov-leaf); text-decoration:none; }
.ov-block__more:hover{ text-decoration:underline; }

.ov-reg{ margin:0; padding:0; list-style:none; }
.ov-reg li + li{ border-top:1px solid var(--ov-line); }
.ov-reg a{
  display:grid; grid-template-columns:44px minmax(0,1fr) 150px 96px; gap:1.2rem; align-items:center;
  padding:1rem 1.4rem; color:inherit; text-decoration:none;
  transition:background .15s ease;
}
.ov-reg a:hover{ background:rgba(30,138,85,.045); }
.ov-reg a:focus-visible{ outline:2px solid var(--ov-leaf); outline-offset:-2px; }

.ov-reg__icon{
  width:44px; height:44px; border-radius:12px;
  display:grid; place-items:center;
  background:rgba(30,138,85,.1); color:var(--ov-leaf);
}
.ov-reg__icon svg{ width:21px; height:21px; }
.ov-reg__name{ display:flex; align-items:center; gap:.55rem; font-weight:600; color:var(--ov-ink); }
.ov-reg__text{ display:block; margin-top:.2rem; font-size:.86rem; line-height:1.45; color:var(--ov-muted); max-width:58ch; }
.ov-reg__flag{
  padding:.12rem .5rem; border-radius:999px;
  background:var(--ov-gold); color:var(--ov-forest);
  font-size:.72rem; font-weight:700;
}
.ov-reg__count{ text-align:right; }
.ov-reg__count b{
  display:block; font-family:var(--ov-display);
  font-size:1.5rem; font-weight:700; line-height:1; color:var(--ov-ink);
}
.ov-reg__count span{ font-size:.78rem; color:var(--ov-muted); }
.ov-reg__go{
  justify-self:end; display:inline-flex; align-items:center; gap:.3rem;
  padding:.45rem .8rem; border-radius:999px;
  border:1px solid var(--ov-line);
  font-size:.82rem; font-weight:600; color:var(--ov-ink);
  transition:border-color .15s ease, background .15s ease, color .15s ease;
}
.ov-reg__go svg{ width:14px; height:14px; transition:transform .15s ease; }
.ov-reg a:hover .ov-reg__go{ border-color:var(--ov-leaf); color:var(--ov-leaf); }
.ov-reg a:hover .ov-reg__go svg{ transform:translateX(2px); }
.ov-reg li.is-flagged .ov-reg__icon{ background:rgba(224,169,59,.18); color:#9A6A0C; }
.ov-reg li.is-flagged .ov-reg__go{ background:var(--ov-forest); border-color:var(--ov-forest); color:#fff; }

/* ---------- 4. the two lists ---------- */
.ov-split{ display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1.6rem; }

.ov-list{ margin:0; padding:0; list-style:none; }
.ov-list li + li{ border-top:1px solid var(--ov-line); }
.ov-list__row{
  display:flex; align-items:center; gap:.85rem;
  padding:.8rem 1.4rem; color:inherit; text-decoration:none;
}
a.ov-list__row:hover{ background:rgba(30,138,85,.045); }
.ov-list__avatar{
  flex:none; width:36px; height:36px; border-radius:50%;
  display:grid; place-items:center;
  background:#E8EEEA; color:var(--ov-forest);
  font-family:var(--ov-display); font-size:.78rem; font-weight:700;
}
.ov-list__avatar--admin{ background:var(--ov-forest); color:#fff; }
.ov-list__body{ flex:1; min-width:0; }
.ov-list__main{ display:block; font-weight:600; font-size:.9rem; color:var(--ov-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ov-list__sub{ display:block; font-size:.8rem; color:var(--ov-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.ov-list__side{ flex:none; text-align:right; font-size:.78rem; color:var(--ov-muted); }
.ov-list__side .ov-stars{ display:block; color:var(--ov-gold); letter-spacing:.06em; font-size:.8rem; }
.ov-list__side .ov-stars__off{ color:rgba(21,26,23,.15); }
.ov-list__dot{
  display:inline-block; width:.5rem; height:.5rem; margin-right:.35rem;
  border-radius:50%; background:var(--ov-gold); vertical-align:middle;
}
.ov-empty{ margin:0; padding:2rem 1.4rem; text-align:center; font-size:.88rem; color:var(--ov-muted); }

/* ---------- the one orchestrated moment ---------- */
@media (prefers-reduced-motion:no-preference){
  .ov-band{ animation:ovRise .5s cubic-bezier(.16,.84,.44,1) both; }
  .ov-score__num{ animation:ovRise .6s .12s cubic-bezier(.16,.84,.44,1) both; }
}
@keyframes ovRise{ from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:none; } }

/* ---------- smaller screens ---------- */
@media (max-width:1100px){
  .ov-nums{ grid-template-columns:repeat(3, minmax(0,1fr)); }
  .ov-num:nth-child(4){ border-left:0; }
  .ov-num:nth-child(n+4){ border-top:1px solid var(--ov-line); }
  .ov-split{ grid-template-columns:1fr; }
}
@media (max-width:760px){
  .ov-band{ grid-template-columns:1fr; padding:1.6rem 1.4rem; }
  .ov-score{ text-align:left; padding:1.2rem 0 0; border-left:0; border-top:1px solid rgba(255,255,255,.16); }
  .ov-nums{ grid-template-columns:repeat(2, minmax(0,1fr)); }
  .ov-num{ border-left:0 !important; border-top:1px solid var(--ov-line); }
  .ov-num:nth-child(-n+2){ border-top:0; }
  .ov-num:nth-child(even){ border-left:1px solid var(--ov-line) !important; }
  .ov-reg a{ grid-template-columns:40px minmax(0,1fr); }
  .ov-reg__count, .ov-reg__go{ grid-column:2; justify-self:start; text-align:left; }
  .ov-reg__count b{ display:inline; font-size:1.1rem; margin-right:.3rem; }
}
</style>

<div class="ov">

  <!-- 1. THE BAND -->
  <section class="ov-band" aria-label="Summary">
    <?php if ($heroPhoto): ?>
      <div class="ov-band__photo" style="background-image:url('<?= e($heroPhoto) ?>')" role="presentation"></div>
    <?php endif; ?>

    <div>
      <p class="ov-band__date"><?= e($phNow->format('l, j F Y')) ?></p>
      <h1 class="ov-band__hello">
        <span><?= e($hello) ?>,</span>
        <?= e($me['firstname']) ?>
      </h1>

      <ul class="ov-todo">
        <?php if ($todo): ?>
          <?php foreach ($todo as $t): ?>
            <li><a href="<?= e($t['href']) ?>"><?= e($t['text']) ?></a></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="ov-todo__clear">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            Nothing is waiting on you
          </li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="ov-score<?= $score === null ? ' ov-score--empty' : '' ?>">
      <?php if ($score !== null): ?>
        <span class="ov-score__num"><?= e(number_format($score, 1)) ?></span>
        <?= ovStars($score) ?>
        <span class="ov-score__label">Visitor rating from <?= $scoreCount ?> published <?= $scoreCount === 1 ? 'review' : 'reviews' ?></span>
      <?php else: ?>
        <span class="ov-score__num">–</span>
        <span class="ov-score__label">No published reviews yet</span>
      <?php endif; ?>
    </div>
  </section>

  <!-- 2. THE NUMBERS -->
  <section class="ov-nums" aria-label="Totals">
    <div class="ov-num">
      <span class="ov-num__value"><?= $counts['places'] ?></span>
      <span class="ov-num__label">Destinations live</span>
    </div>
    <div class="ov-num">
      <span class="ov-num__value"><?= $counts['photos'] ?></span>
      <span class="ov-num__label">Gallery photos</span>
    </div>
    <div class="ov-num">
      <span class="ov-num__value"><?= $counts['quotes'] - $counts['pending'] ?></span>
      <span class="ov-num__label">Reviews on the homepage</span>
    </div>
    <div class="ov-num">
      <span class="ov-num__value"><?= $counts['users'] ?></span>
      <span class="ov-num__label"><?= $counts['admins'] ?> of them <?= $counts['admins'] === 1 ? 'is an admin' : 'are admins' ?></span>
    </div>
    <?php if (hasUserColumn($pdo, 'created_at')): ?>
      <div class="ov-num<?= $counts['new'] ? ' ov-num--up' : '' ?>">
        <span class="ov-num__value"><?= $counts['new'] ? '+' . $counts['new'] : '0' ?></span>
        <span class="ov-num__label">New accounts this week</span>
      </div>
    <?php else: ?>
      <div class="ov-num">
        <span class="ov-num__value"><?= $counts['feedback'] ?></span>
        <span class="ov-num__label">Feedback received</span>
      </div>
    <?php endif; ?>
  </section>

  <!-- 3. THE REGISTER -->
  <section class="ov-block">
    <div class="ov-block__head">
      <h2 class="ov-block__title">Site content</h2>
    </div>

    <ul class="ov-reg">
      <?php foreach ($register as $r): ?>
        <li class="<?= $r['flag'] ? 'is-flagged' : '' ?>">
          <a href="<?= e($r['href']) ?>">
            <span class="ov-reg__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $r['icon'] ?></svg>
            </span>

            <span>
              <span class="ov-reg__name">
                <?= e($r['title']) ?>
                <?php if ($r['flag']): ?>
                  <span class="ov-reg__flag"><?= (int) $r['flag'] ?> waiting</span>
                <?php endif; ?>
              </span>
              <span class="ov-reg__text"><?= e($r['text']) ?></span>
            </span>

            <span class="ov-reg__count">
              <b><?= (int) $r['num'] ?></b>
              <span><?= e($r['unit']) ?></span>
            </span>

            <span class="ov-reg__go">
              <?= e($r['go']) ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- 4. THE TWO LISTS -->
  <div class="ov-split">
    <section class="ov-block">
      <div class="ov-block__head">
        <h2 class="ov-block__title">Latest feedback</h2>
        <a class="ov-block__more" href="feedback.php">Open inbox</a>
      </div>

      <?php if (!$latestFb): ?>
        <p class="ov-empty">No feedback yet. It shows up here when a signed-in visitor sends some.</p>
      <?php else: ?>
        <ul class="ov-list">
          <?php foreach ($latestFb as $f):
            $who = trim(($f['firstname'] ?? '') . ' ' . ($f['lastname'] ?? ''));
          ?>
            <li>
              <a class="ov-list__row" href="feedback.php?open=<?= (int) $f['id'] ?>#fb-<?= (int) $f['id'] ?>">
                <span class="ov-list__avatar" aria-hidden="true"><?= e($who !== '' ? initials($f['firstname'], $f['lastname']) : '?') ?></span>
                <span class="ov-list__body">
                  <span class="ov-list__main">
                    <?php if ($f['status'] === 'new'): ?><span class="ov-list__dot" title="Unread"></span><?php endif; ?>
                    <?= e($f['subject']) ?>
                  </span>
                  <span class="ov-list__sub"><?= e($who !== '' ? $who : 'Deleted account') ?></span>
                </span>
                <span class="ov-list__side">
                  <?php if ($f['rating']): ?><?= ovStars((float) $f['rating']) ?><?php endif; ?>
                  <?= e(fmtAgo($f['created_at'])) ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="ov-block">
      <div class="ov-block__head">
        <h2 class="ov-block__title">Newest accounts</h2>
        <a class="ov-block__more" href="users.php">See all</a>
      </div>

      <?php if (!$recent): ?>
        <p class="ov-empty">No accounts to show yet.</p>
      <?php else: ?>
        <ul class="ov-list">
          <?php foreach ($recent as $row): ?>
            <li>
              <a class="ov-list__row" href="users.php?edit=<?= (int) $row['id'] ?>">
                <span class="ov-list__avatar<?= $row['role'] === 'admin' ? ' ov-list__avatar--admin' : '' ?>" aria-hidden="true"><?= e(initials($row['firstname'], $row['lastname'])) ?></span>
                <span class="ov-list__body">
                  <span class="ov-list__main"><?= e($row['firstname'] . ' ' . $row['lastname']) ?></span>
                  <span class="ov-list__sub"><?= e($row['email']) ?></span>
                </span>
                <span class="ov-list__side">
                  <?php if ($row['role'] === 'admin'): ?><span class="adm-badge adm-badge--admin">admin</span><br><?php endif; ?>
                  <?= e(fmtAgo($row['created_at'])) ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

</div>

<?php require __DIR__ . '/_footer.php'; ?>