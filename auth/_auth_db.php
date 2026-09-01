<?php
/* ===================================================================
   auth/_auth_db.php

   The new auth files need to talk to your database, and I have not
   seen your connection file. Rather than guess, this finds it and
   normalises whatever it exposes into three functions:

       auth_one($sql, $params)   first row, or null
       auth_all($sql, $params)   every row
       auth_run($sql, $params)   insert/update/delete -> insert id

   All three take ? placeholders and an array of values. They are
   always prepared statements — no value is ever pasted into SQL.

   IF IT CANNOT FIND YOUR CONNECTION it says so plainly instead of
   dying with "undefined variable", and the fix is the one line at
   AUTH_DB_FILE below.
   =================================================================== */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* -------------------------------------------------------------------
   1. WHERE YOUR CONNECTION LIVES

   Set from auth/login_process.php, which does:

       require '../config/database.php';

   and works with $pdo afterwards. The path here is relative to THIS
   file, so the leading /.. climbs out of auth/ before descending into
   config/.

   If you move the connection file, this is the only line to change —
   the four new auth files all come through here.
   ------------------------------------------------------------------- */
define('AUTH_DB_FILE', '/../config/database.php');

/* -------------------------------------------------------------------
   2. YOUR USERS TABLE

   Change these if your column names differ. This is the only place
   any of the new files name a column, so a mismatch is one edit and
   not a hunt through five files.
   ------------------------------------------------------------------- */
/* Confirmed against login_process.php, which reads id, email,
   password, firstname, lastname and role off the row it fetches. */
define('AUTH_TABLE',  'users');
define('AUTH_ID',     'id');
define('AUTH_EMAIL',  'email');
define('AUTH_PASS',   'password');
define('AUTH_FIRST',  'firstname');
define('AUTH_LAST',   'lastname');
define('AUTH_ROLE',   'role');

/* Your users table also has an optional status column — 'active' or
   otherwise — which login_process.php checks after the password.
   A reset link must respect that too: letting a suspended account set
   a new password would be a way straight back in through a door the
   tourism office has closed.

   Left as '' if you have not run that ALTER. reset_password.php
   checks the column only when this is set. */
define('AUTH_STATUS', 'status');

/* ===================================================================
   FINDING THE CONNECTION
   =================================================================== */
if (!function_exists('auth_db')) {

function auth_db() {
    static $db = null;
    if ($db !== null) { return $db; }

    if (AUTH_DB_FILE !== '') {
        $tries = [__DIR__ . AUTH_DB_FILE];
    } else {
        /* The usual names, in the usual folders. Two levels of guess:
           a list of likely filenames crossed with a list of likely
           directories, rather than a hand-written list of every
           combination. */
        $dirs  = ['/../includes/', '/../config/', '/../', '/', '/../assets/', '/../admin/'];
        $names = ['db.php', 'database.php', 'connection.php', 'connect.php',
                  'config.php', 'dbconfig.php', 'db_connect.php', 'conn.php',
                  'koneksyon.php', 'init.php', 'bootstrap.php'];

        $tries = [];
        foreach ($dirs as $d) {
            foreach ($names as $n) { $tries[] = __DIR__ . $d . $n; }
        }
    }

    /* ---------- already loaded? ----------
       Checked BEFORE anything is included. If the page that called us
       has already required config/database.php this request,
       include_once below would return true without running the file,
       and $pdo would never appear in the local scope — so the include
       branch alone would report "not found" on exactly the pages
       where the connection is most definitely present.

       include_once rather than include is deliberate: a connection
       file that defines a constant or a function is a fatal error the
       second time it runs. */
    foreach (['pdo', 'conn', 'mysqli', 'link', 'db'] as $name) {
        if (isset($GLOBALS[$name])) {
            $candidate = $GLOBALS[$name];
            if ($candidate instanceof PDO) {
                $candidate->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                return $db = ['driver' => 'pdo', 'h' => $candidate];
            }
            if ($candidate instanceof mysqli) {
                return $db = ['driver' => 'mysqli', 'h' => $candidate];
            }
        }
    }

    foreach ($tries as $file) {
        if (!is_file($file)) { continue; }

        /* Included inside a function so the connection file's own
           variables land in this scope and can be picked up below,
           rather than polluting the global namespace of whatever page
           happened to call us. */
        include_once $file;

        foreach (['pdo', 'conn', 'mysqli', 'link', 'db', 'connection'] as $name) {
            if (isset($$name)) {
                $candidate = $$name;
                if ($candidate instanceof PDO) {
                    $candidate->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    return $db = ['driver' => 'pdo', 'h' => $candidate];
                }
                if ($candidate instanceof mysqli) {
                    return $db = ['driver' => 'mysqli', 'h' => $candidate];
                }
                /* pg_connect() returns a resource on PHP 7 and a
                   PgSql\Connection object on PHP 8.1+. Test for both,
                   or this works on your machine and not the next one. */
                if ((is_resource($candidate) && get_resource_type($candidate) === 'pgsql link')
                    || (is_object($candidate) && get_class($candidate) === 'PgSql\\Connection')) {
                    return $db = ['driver' => 'pgsql', 'h' => $candidate];
                }
            }
        }

        /* Some connection files expose it as a global instead. */
        foreach (['pdo', 'conn', 'mysqli', 'link', 'db'] as $name) {
            if (isset($GLOBALS[$name])) {
                $candidate = $GLOBALS[$name];
                if ($candidate instanceof PDO)    { return $db = ['driver' => 'pdo',    'h' => $candidate]; }
                if ($candidate instanceof mysqli) { return $db = ['driver' => 'mysqli', 'h' => $candidate]; }
            }
        }
    }

    /* A blank page with a PHP notice is the worst way to learn this.
       Say what went wrong and where the fix is. */
    http_response_code(500);

    /* Naming the files it opened turns "not found" from a dead end
       into a checklist — usually the answer is visible in this list,
       one folder off. */
    $looked = [];
    foreach ($tries as $t) { if (is_file($t)) { $looked[] = $t; } }

    echo 'Database connection not found.' . "\n\n";
    echo "Open auth/login_process.php, see which file it requires, and put that path in\n";
    echo "AUTH_DB_FILE near the top of auth/_auth_db.php — relative to the auth folder,\n";
    echo "for example: define('AUTH_DB_FILE', '/../includes/koneksyon.php');\n\n";

    if ($looked) {
        echo "These files were opened but exposed no connection this script recognised:\n";
        foreach ($looked as $t) { echo '  ' . $t . "\n"; }
        echo "\nIf your connection is in one of them, it is probably created inside a\n";
        echo "function or class rather than left in a variable. Say so and it can be handled.\n";
    } else {
        echo "No connection file was found in includes/, config/, admin/ or the project root.\n";
    }
    exit;
}

/* ===================================================================
   THE THREE FUNCTIONS

   mysqli's bind_param wants a type string. Everything here is bound
   as a string, which MySQL casts on the way in — correct for ids,
   emails and hashes alike, and it keeps the call sites simple.
   =================================================================== */

/* pg_query_params numbers its placeholders — $1, $2 — where PDO and
   mysqli both take a plain ?. Every query in these files is written
   with ?, so the native-Postgres path rewrites them on the way
   through.

   This is a positional swap, not a parser: it would also rewrite a ?
   sitting inside a quoted string. No query in these files has one,
   and none should — a literal that contains a question mark belongs
   in a bound parameter anyway. */
function auth_pg_placeholders($sql) {
    $i = 0;
    return preg_replace_callback('/\?/', function () use (&$i) {
        return '$' . (++$i);
    }, $sql);
}

function auth_all($sql, $params = []) {
    $db = auth_db();

    if ($db['driver'] === 'pgsql') {
        $res = pg_query_params($db['h'], auth_pg_placeholders($sql), array_values($params));
        if ($res === false) { throw new RuntimeException('SQL failed: ' . pg_last_error($db['h'])); }
        return pg_fetch_all($res, PGSQL_ASSOC) ?: [];
    }

    if ($db['driver'] === 'pdo') {
        $st = $db['h']->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $st = $db['h']->prepare($sql);
    if (!$st) { throw new RuntimeException('SQL prepare failed: ' . $db['h']->error); }

    if ($params) {
        $st->bind_param(str_repeat('s', count($params)), ...array_values($params));
    }
    $st->execute();

    $res = $st->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
}

function auth_one($sql, $params = []) {
    $rows = auth_all($sql, $params);
    return $rows ? $rows[0] : null;
}

function auth_run($sql, $params = []) {
    $db = auth_db();

    if ($db['driver'] === 'pgsql') {
        $res = pg_query_params($db['h'], auth_pg_placeholders($sql), array_values($params));
        if ($res === false) { throw new RuntimeException('SQL failed: ' . pg_last_error($db['h'])); }
        return 0;   /* no caller reads the insert id */
    }

    if ($db['driver'] === 'pdo') {
        $st = $db['h']->prepare($sql);
        $st->execute($params);

        /* Postgres throws here when the statement touched no sequence
           — an UPDATE or a DELETE, both of which this function is
           used for. No caller reads the return value, so a failure to
           produce one must not fail the write. */
        try {
            return (int) $db['h']->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }

    $st = $db['h']->prepare($sql);
    if (!$st) { throw new RuntimeException('SQL prepare failed: ' . $db['h']->error); }

    if ($params) {
        $st->bind_param(str_repeat('s', count($params)), ...array_values($params));
    }
    $st->execute();

    $id = $db['h']->insert_id;
    $st->close();
    return (int) $id;
}

/* ===================================================================
   WHICH SQL DIALECT

   MySQL and PostgreSQL disagree about how to write "an hour ago", and
   there is no spelling that works in both. Rather than pick one and
   leave the other broken, the two places that need a date interval
   ask this first.

   Returns 'pgsql' or 'mysql'. Anything unrecognised is treated as
   MySQL, which is the commoner case for a project like this.
   =================================================================== */
function auth_dialect() {
    static $d = null;
    if ($d !== null) { return $d; }

    $db = auth_db();

    if ($db['driver'] === 'mysqli') { return $d = 'mysql'; }
    if ($db['driver'] === 'pgsql')  { return $d = 'pgsql'; }

    try {
        $name = $db['h']->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $e) {
        return $d = 'mysql';
    }

    return $d = ($name === 'pgsql') ? 'pgsql' : 'mysql';
}

/* "NOW() minus N hours" / "NOW() plus N minutes", spelled for
   whichever database is actually there.

   $n is cast to int at the call site and again here. It is never a
   bound parameter because an interval literal cannot be one in
   Postgres — so the cast is what keeps it safe, and it has to be
   airtight. */
function auth_ago($hours) {
    $n = (int) $hours;
    return auth_dialect() === 'pgsql'
        ? "NOW() - INTERVAL '{$n} hours'"
        : "DATE_SUB(NOW(), INTERVAL {$n} HOUR)";
}

function auth_ahead($minutes) {
    $n = (int) $minutes;
    return auth_dialect() === 'pgsql'
        ? "NOW() + INTERVAL '{$n} minutes'"
        : "DATE_ADD(NOW(), INTERVAL {$n} MINUTE)";
}

/* ===================================================================
   SENDING BACK TO THE CARD

   The process files never print. They redirect to the page the form
   came from and let footer.php turn the code into a sentence — the
   convention your login_process.php already follows.
   =================================================================== */
function auth_back($query = '') {
    /* Where the form was submitted from. Checked, not trusted: an
       absolute URL here would let a phishing link borrow your
       domain's good name by bouncing a visitor off your own site. */
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    $path = '';

    if ($ref !== '') {
        $parts = parse_url($ref);
        $host  = $parts['host'] ?? '';
        if ($host === '' || $host === ($_SERVER['HTTP_HOST'] ?? '')) {
            $path = $parts['path'] ?? '';
        }
    }

    if ($path === '' || substr($path, 0, 1) !== '/') {
        $path = '/Tourism_System/homepage.php';   /* the one fallback */
    }

    header('Location: ' . $path . ($query ? '?' . $query : ''));
    exit;
}

} /* end function_exists guard */