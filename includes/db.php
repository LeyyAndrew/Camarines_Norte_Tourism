<?php
/* ===================================================================
   includes/db.php — reuses the connection you already have.

   Your config/database.php already opens a PDO connection to
   PostgreSQL and leaves it in $pdo. So this file does not open a
   second one and does not hold a second copy of your password. It
   just fetches yours and turns on two settings.

   ONE SET OF CREDENTIALS, IN ONE FILE. If you ever change the
   password, you change config/database.php and nothing else in the
   project needs to know.
   =================================================================== */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    /* Two ways your connection might already exist, and one way to
       make it if it does not.

       If some page earlier in this request already did
       require config/database.php at the top level, $pdo is sitting in
       the global scope and we reuse it. This matters: gallery.php
       includes header.php, and header.php may well pull in the config
       before we get here.

       Reaching for $GLOBALS is not something to do casually, but this
       is exactly the case it exists for — your config file publishes
       its connection as a global, so that is where we look for it.

       What we must NOT do is require_once here and hope. If the file
       was already loaded, require_once does nothing at all, $pdo never
       gets set inside this function, and you get a confusing null. And
       a plain require would run the file a second time and open a
       second connection to the same database. Checking first avoids
       both. */
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        /* Not loaded yet. Your file runs its own try/catch and calls
           die() on failure, so there is nothing to catch here. */
        require __DIR__ . '/../config/database.php';
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('config/database.php did not provide a $pdo connection.');
    }

    /* --- two settings your file does not set -----------------------

       ERRMODE_EXCEPTION your file already sets. These two it does not,
       and both matter here.

       FETCH_ASSOC: without it PDO returns every column twice, once by
       name and once by number. Everything still works, it just doubles
       the size of every result set for no reason.

       EMULATE_PREPARES false: this is the important one. It makes PDO
       send the query and the values to PostgreSQL separately, so a
       value can never be read as part of the query. That is what makes
       SQL injection impossible rather than merely unlikely — and it is
       worth turning on for the whole project, not just the gallery. */
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}

/* ===================================================================
   USING IT ELSEWHERE

   Any page in the project can now do:

       require_once __DIR__ . '/includes/db.php';
       $rows = db()->query('SELECT ...')->fetchAll();

   Your existing pages that use $pdo directly keep working exactly as
   they do — this is the same object, not a new connection. Nothing
   has to be converted.
   =================================================================== */