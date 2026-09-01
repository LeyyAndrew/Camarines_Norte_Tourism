<?php
/* ===================================================================
   admin/users.php — see who has registered, edit them, set roles.

   WHAT THIS PAGE DELIBERATELY CANNOT DO

   It cannot show or change a password. The column holds a bcrypt
   hash, which cannot be reversed — there is nothing to display. And
   an admin who can silently set someone else's password can sign in
   as them, which is a bigger power than this panel should hand out
   for a school project. If you want password resets, build them as
   an emailed one-time link the USER completes.

   THE THREE LOCKS

   You cannot change your own role, suspend yourself, or delete
   yourself. All three are the same failure: an admin panel with
   nobody able to reach it. Recovering means editing the database by
   hand, which is a bad afternoon.

   SUSPEND VS DELETE

   Suspending keeps the row and blocks the login. Deleting is final
   and takes the person's history with it. Suspend is the one you
   almost always want — it is reversible, and an account you deleted
   in error cannot be un-deleted from this panel or any other.
   =================================================================== */

require __DIR__ . '/_bootstrap.php';

/* Which optional columns this database actually has. Everything
   below reads these rather than assuming — see _bootstrap.php. */
$hasStatus  = hasUserColumn($pdo, 'status');
$hasLast    = hasUserColumn($pdo, 'last_login');
$hasCreated = hasUserColumn($pdo, 'created_at');
$hasPhone   = hasUserColumn($pdo, 'phone');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    /* ---------- edit details ---------- */
    if ($action === 'save') {
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname  = trim($_POST['lastname']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $role      = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $status    = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';

        if ($firstname === '' || $lastname === '' || $email === '') {
            flash('Name and email cannot be empty.', 'bad');
            header('Location: users.php?edit=' . $id);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('That email address does not look right.', 'bad');
            header('Location: users.php?edit=' . $id);
            exit;
        }

        /* locks 1 and 2 — your own role and your own status are not
           yours to change here. Enforced on the POST, not just hidden
           in the form: a disabled field is a suggestion, and anyone
           can re-enable it in their browser in four seconds. */
        if ($id === (int) $me['id']) {
            $role   = $me['role'];
            $status = $me['status'] ?? 'active';
        }

        /* The SET list is built from the columns that exist, so this
           runs identically before and after the ALTER. */
        $sets   = ['firstname = :firstname', 'lastname = :lastname', 'email = :email', 'role = :role'];
        $params = [
            ':firstname' => $firstname,
            ':lastname'  => $lastname,
            ':email'     => $email,
            ':role'      => $role,
            ':id'        => $id,
        ];

        if ($hasPhone) {
            $sets[] = 'phone = :phone';
            $params[':phone'] = $phone !== '' ? $phone : null;
        }

        if ($hasStatus) {
            $sets[] = 'status = :status';
            $params[':status'] = $status;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id'
            );
            $stmt->execute($params);

            /* the nav greeting reads the session, so keep it in step
               when an admin edits their own name */
            if ($id === (int) $me['id']) {
                $_SESSION['firstname'] = $firstname;
                $_SESSION['lastname']  = $lastname;
            }

            flash('Account updated.');
        } catch (PDOException $e) {
            /* 23505 is Postgres for a unique violation — the email
               column has a UNIQUE constraint on it */
            if ($e->getCode() === '23505') {
                flash('Another account already uses that email.', 'bad');
            } else {
                error_log('user save failed: ' . $e->getMessage());
                flash('Could not save that account.', 'bad');
            }
        }

        header('Location: users.php');
        exit;
    }

    /* ---------- promote / demote from the list ---------- */
    if ($action === 'role') {
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if ($id === (int) $me['id']) {
            flash('You cannot change your own role.', 'bad');
            header('Location: users.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            $stmt->execute([':role' => $role, ':id' => $id]);

            flash($role === 'admin' ? 'Account is now an admin.' : 'Admin access removed.');
        } catch (PDOException $e) {
            error_log('role change failed: ' . $e->getMessage());
            flash('Could not change that role.', 'bad');
        }

        header('Location: users.php');
        exit;
    }

    /* ---------- suspend / restore ----------
       The row stays. login_process.php is what actually turns the
       flag into a locked door; this only sets it. */
    if ($action === 'status' && $hasStatus) {
        $status = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';

        if ($id === (int) $me['id']) {
            flash('You cannot suspend your own account.', 'bad');
            header('Location: users.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $id]);

            flash($status === 'suspended'
                ? 'Account suspended. They cannot sign in until you restore it.'
                : 'Account restored.');
        } catch (PDOException $e) {
            error_log('status change failed: ' . $e->getMessage());
            flash('Could not change that account.', 'bad');
        }

        header('Location: users.php');
        exit;
    }

    /* ---------- delete ---------- */
    if ($action === 'delete') {
        /* lock 3 */
        if ($id === (int) $me['id']) {
            flash('You cannot delete your own account.', 'bad');
            header('Location: users.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);

            flash('Account deleted.');
        } catch (PDOException $e) {
            error_log('user delete failed: ' . $e->getMessage());
            flash('Could not delete that account.', 'bad');
        }

        header('Location: users.php');
        exit;
    }
}

/* ---------------------------------------------------------------
   READ — note the column list. SELECT * would drag the password
   hash into the page's memory and into any var_dump you write
   while debugging. Ask for what you need.
   --------------------------------------------------------------- */
$cols = ['id', 'firstname', 'lastname', 'email', 'role'];

if ($hasPhone)   { $cols[] = 'phone'; }
if ($hasStatus)  { $cols[] = 'status'; }
if ($hasCreated) { $cols[] = 'created_at'; }
if ($hasLast)    { $cols[] = 'last_login'; }

$rows = [];

try {
    /* Admins first, then newest, so the people who can change things
       are never buried under a page of ordinary accounts. */
    $order = $hasCreated
        ? "ORDER BY (role = 'admin') DESC, created_at DESC, id DESC"
        : "ORDER BY (role = 'admin') DESC, id DESC";

    $rows = $pdo->query(
        'SELECT ' . implode(', ', $cols) . ' FROM users ' . $order
    )->fetchAll();
} catch (PDOException $e) {
    error_log('user list failed: ' . $e->getMessage());
    flash('Could not read the users table.', 'bad');
}

$editing = null;
$editId  = (int) ($_GET['edit'] ?? 0);

if ($editId) {
    foreach ($rows as $row) {
        if ((int) $row['id'] === $editId) { $editing = $row; break; }
    }
}

/* how many are suspended, for the head line */
$suspended = 0;

if ($hasStatus) {
    foreach ($rows as $row) {
        if (($row['status'] ?? 'active') !== 'active') { $suspended++; }
    }
}

$adminTitle = 'Users';
require __DIR__ . '/_header.php';
?>

<header class="adm-head">
  <div>
    <span class="adm-eyebrow">People</span>
    <h1 class="adm-title">Users</h1>
    <p class="adm-sub">Everyone who has registered. Passwords are stored hashed and cannot be shown or set here.</p>
  </div>

  <p class="adm-head__aside">
    <?= count($rows) ?> <?= count($rows) === 1 ? 'account' : 'accounts' ?><?= $suspended ? ' · ' . $suspended . ' suspended' : '' ?>
  </p>
</header>

<?php if (!$hasStatus || !$hasLast): ?>
  <!-- Said once, plainly, with the fix in it. A column that silently
       does not appear is a bug report waiting to be written. -->
  <p class="adm-flash adm-flash--bad">
    Some account fields are missing from the database, so this page is hiding them.
    Run this in pgAdmin to switch them on:
    <?php if (!$hasStatus): ?>
      <code>ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active';</code>
    <?php endif; ?>
    <?php if (!$hasLast): ?>
      <code>ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL;</code>
    <?php endif; ?>
  </p>
<?php endif; ?>

<?php if ($editing): ?>
<?php
  $isSelf     = (int) $editing['id'] === (int) $me['id'];
  $editStatus = $editing['status'] ?? 'active';
?>
<section class="adm-panel">
  <div class="adm-panel__head">
    <h2 class="adm-panel__title">
      <span class="adm-person__avatar<?= $editing['role'] === 'admin' ? ' adm-person__avatar--admin' : '' ?>" aria-hidden="true"><?= e(initials($editing['firstname'], $editing['lastname'])) ?></span>
      <?= e($editing['firstname'] . ' ' . $editing['lastname']) ?>
    </h2>
  </div>

  <!-- ============ THE RECORD ============
       The facts you can only read, above the fields you can change.
       Registration date and last login are set by the system, not by
       anyone typing — putting them in the form as disabled inputs
       would imply they are editable and greyed out for now, which is
       a different and untrue statement. -->
  <div class="adm-panel__body" style="border-bottom:1px solid var(--adm-line-soft)">
    <dl class="adm-meta">
      <div class="adm-meta__item">
        <dt class="adm-meta__key">Name</dt>
        <dd class="adm-meta__value"><?= e($editing['firstname'] . ' ' . $editing['lastname']) ?></dd>
      </div>

      <div class="adm-meta__item">
        <dt class="adm-meta__key">Email</dt>
        <dd class="adm-meta__value"><?= e($editing['email']) ?></dd>
      </div>

      <div class="adm-meta__item">
        <dt class="adm-meta__key">Role</dt>
        <dd class="adm-meta__value">
          <span class="adm-badge<?= $editing['role'] === 'admin' ? ' adm-badge--admin' : '' ?>"><?= e($editing['role']) ?></span>
        </dd>
      </div>

      <?php if ($hasStatus): ?>
        <div class="adm-meta__item">
          <dt class="adm-meta__key">Account status</dt>
          <dd class="adm-meta__value">
            <span class="adm-badge <?= $editStatus === 'active' ? 'adm-badge--active' : 'adm-badge--off' ?>"><?= e($editStatus) ?></span>
          </dd>
        </div>
      <?php endif; ?>

      <?php if ($hasCreated): ?>
        <div class="adm-meta__item">
          <dt class="adm-meta__key">Registered</dt>
          <dd class="adm-meta__value">
            <?= e(fmtDateTime($editing['created_at'])) ?>
            <span class="adm-meta__sub"><?= e(fmtAgo($editing['created_at'])) ?></span>
          </dd>
        </div>
      <?php endif; ?>

      <?php if ($hasLast): ?>
        <div class="adm-meta__item">
          <dt class="adm-meta__key">Last login</dt>
          <dd class="adm-meta__value<?= $editing['last_login'] ? '' : ' adm-meta__value--none' ?>">
            <?php if ($editing['last_login']): ?>
              <?= e(fmtDateTime($editing['last_login'])) ?>
              <span class="adm-meta__sub"><?= e(fmtAgo($editing['last_login'])) ?></span>
            <?php else: ?>
              Never signed in
              <span class="adm-meta__sub">Registered but has not used the account</span>
            <?php endif; ?>
          </dd>
        </div>
      <?php endif; ?>
    </dl>
  </div>

  <div class="adm-panel__body">
    <form method="post" class="adm-form">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">

      <div class="adm-form__row">
        <label class="adm-field">
          <span class="adm-field__label">First name</span>
          <input type="text" name="firstname" maxlength="100" required value="<?= e($editing['firstname']) ?>">
        </label>

        <label class="adm-field">
          <span class="adm-field__label">Last name</span>
          <input type="text" name="lastname" maxlength="100" required value="<?= e($editing['lastname']) ?>">
        </label>
      </div>

      <div class="adm-form__row">
        <label class="adm-field">
          <span class="adm-field__label">Email</span>
          <input type="email" name="email" maxlength="255" required value="<?= e($editing['email']) ?>">
        </label>

        <?php if ($hasPhone): ?>
          <label class="adm-field">
            <span class="adm-field__label">Phone <em>optional</em></span>
            <input type="text" name="phone" maxlength="20" value="<?= e($editing['phone'] ?? '') ?>">
          </label>
        <?php endif; ?>

        <label class="adm-field adm-field--narrow">
          <span class="adm-field__label">Role</span>
          <?php if ($isSelf): ?>
            <input type="text" value="admin (you)" disabled>
            <input type="hidden" name="role" value="admin">
          <?php else: ?>
            <select name="role">
              <option value="user"<?=  $editing['role'] === 'user'  ? ' selected' : '' ?>>user</option>
              <option value="admin"<?= $editing['role'] === 'admin' ? ' selected' : '' ?>>admin</option>
            </select>
          <?php endif; ?>
        </label>

        <?php if ($hasStatus): ?>
          <label class="adm-field adm-field--narrow">
            <span class="adm-field__label">Status</span>
            <?php if ($isSelf): ?>
              <input type="text" value="active (you)" disabled>
              <input type="hidden" name="status" value="active">
            <?php else: ?>
              <select name="status">
                <option value="active"<?=    $editStatus === 'active'    ? ' selected' : '' ?>>active</option>
                <option value="suspended"<?= $editStatus === 'suspended' ? ' selected' : '' ?>>suspended</option>
              </select>
            <?php endif; ?>
          </label>
        <?php endif; ?>
      </div>

      <div class="adm-form__actions">
        <button type="submit" class="adm-btn">Save changes</button>
        <a href="users.php" class="adm-btn adm-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
</section>
<?php endif; ?>

<section class="adm-panel">
  <div class="adm-panel__head">
    <h2 class="adm-panel__title">All accounts <span class="adm-count"><?= count($rows) ?></span></h2>
  </div>

  <?php if (!$rows): ?>
    <p class="adm-empty">No accounts yet. The first person to register becomes the admin.</p>
  <?php else: ?>
    <div class="adm-panel__body adm-panel__body--flush">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Person</th>
            <th>Role</th>
            <?php if ($hasStatus):  ?><th>Status</th><?php endif; ?>
            <?php if ($hasCreated): ?><th>Registered</th><?php endif; ?>
            <?php if ($hasLast):    ?><th>Last login</th><?php endif; ?>
            <th class="adm-table__right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
              $isMe   = (int) $row['id'] === (int) $me['id'];
              $status = $row['status'] ?? 'active';
              $off    = $status !== 'active';

              $rowClass = $isMe ? 'adm-row--me' : ($off ? 'adm-row--off' : '');
            ?>
            <tr<?= $rowClass ? ' class="' . $rowClass . '"' : '' ?>>
              <td>
                <div class="adm-person">
                  <span class="adm-person__avatar<?= $row['role'] === 'admin' ? ' adm-person__avatar--admin' : '' ?>" aria-hidden="true"><?= e(initials($row['firstname'], $row['lastname'])) ?></span>
                  <span class="adm-person__body">
                    <span class="adm-person__name">
                      <?= e($row['firstname'] . ' ' . $row['lastname']) ?><?php if ($isMe): ?> <span class="adm-muted" style="display:inline">you</span><?php endif; ?>
                    </span>
                    <span class="adm-person__mail"><?= e($row['email']) ?></span>
                  </span>
                </div>
              </td>

              <td>
                <span class="adm-badge<?= $row['role'] === 'admin' ? ' adm-badge--admin' : '' ?>"><?= e($row['role']) ?></span>
              </td>

              <?php if ($hasStatus): ?>
                <td>
                  <span class="adm-badge <?= $off ? 'adm-badge--off' : 'adm-badge--active' ?>"><?= e($status) ?></span>
                </td>
              <?php endif; ?>

              <?php if ($hasCreated): ?>
                <td>
                  <?= e(fmtDate($row['created_at'])) ?>
                  <span class="adm-muted"><?= e(fmtAgo($row['created_at'])) ?></span>
                </td>
              <?php endif; ?>

              <?php if ($hasLast): ?>
                <td>
                  <?php if ($row['last_login']): ?>
                    <?= e(fmtDate($row['last_login'])) ?>
                    <span class="adm-muted"><?= e(fmtAgo($row['last_login'])) ?></span>
                  <?php else: ?>
                    <span class="adm-muted" style="display:inline">Never</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>

              <td class="adm-table__right">
                <div class="adm-actions">
                  <a href="users.php?edit=<?= (int) $row['id'] ?>" class="adm-btn adm-btn--sm adm-btn--ghost">Edit</a>

                  <?php if (!$isMe): ?>
                    <form method="post" class="adm-inline">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="role">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="role" value="<?= $row['role'] === 'admin' ? 'user' : 'admin' ?>">
                      <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost">
                        <?= $row['role'] === 'admin' ? 'Make user' : 'Make admin' ?>
                      </button>
                    </form>

                    <?php if ($hasStatus): ?>
                      <form method="post" class="adm-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="status" value="<?= $off ? 'active' : 'suspended' ?>">
                        <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost">
                          <?= $off ? 'Restore' : 'Suspend' ?>
                        </button>
                      </form>
                    <?php endif; ?>

                    <!-- The confirm() is the only thing between a
                         misclick and a row that is gone for good.
                         There is no undo here and no soft delete —
                         which is exactly why Suspend sits next to it. -->
                    <form method="post" class="adm-inline"
                          data-confirm
                          data-confirm-title="Delete <?= e($row['firstname'] . ' ' . $row['lastname']) ?>?"
                          data-confirm-body="Their account and sign-in are removed. Suspending is reversible; this is not."
                          data-confirm-note="Anything the account is linked to may be affected."
                          data-confirm-action="Delete account">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <button type="submit" class="adm-btn adm-btn--sm adm-btn--danger">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>