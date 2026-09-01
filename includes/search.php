<?php
/* ===================================================================
   includes/search.php  —  the search engine

   One file does the searching. Three files call it:

     api/search.php       the live dropdown in the header (JSON)
     search.php           the full results page (HTML)
     includes/header.php  to decide if the icon opens search or sign-in

   ---------- WHAT IT SEARCHES ----------
   The same includes/destinations-data.php that destinations.php and
   homepage.php read. There is no second copy of the content and no
   index to rebuild — edit a destination there and search knows about
   it on the next request.

   ---------- WHERE RESULTS GO ----------
   There is no per-destination page on this site. Every place is a card
   on destinations.php carrying id="dest-<slug>", and the filters are
   query parameters. So search produces four shapes of link, all of
   them pointing at that one page:

     a named place   destinations.php#dest-calaguas-island
     a category      destinations.php?cat=Falls+%26+Rivers#destFilters
     a town          destinations.php?town=Labo#destFilters
     a tag           destinations.php?type=Waterfall#destFilters

   Every one is a URL destinations.php already understands. Search adds
   no new route and no new page — it is a shortcut into the index that
   was already there, which is why nothing here can fall out of step
   with it.
   =================================================================== */

/* ---------- 0. WHO IS ALLOWED TO SEARCH ----------
   Searching requires an account. The rule is written once, here, and
   everything that enforces it calls the same function.

   IT HAS TO BE ENFORCED SERVER-SIDE. Hiding the header icon is not a
   gate — api/search.php is a URL anyone can type. The header check is
   convenience; the checks in api/search.php and search.php are the
   gate. Set this to false to make search public again; nothing else
   needs to change. */
define('SEARCH_REQUIRE_LOGIN', true);

/* Where to send someone who needs to sign in. */
define('SEARCH_LOGIN_URL', 'auth/login.php');

/* Jump straight to the place when the query names one exactly.
   Someone who types "Bagasbas Beach" in full has already chosen; a
   page of results asking them to choose again is a step they did not
   need. Anything less than an exact name still lists. Set false to
   always show the results page. */
define('SEARCH_JUMP_ON_EXACT', true);

/* At file scope, guarded the same way header.php guards its own call.
   A session cannot start once output has begun, so starting it lazily
   — whenever something first asks who the visitor is — risks that
   moment drifting past the first byte of HTML, after which the session
   silently fails and everyone looks signed out. */
if (session_status() === PHP_SESSION_NONE && !headers_sent()) { session_start(); }

/* 'user_id' is the key login_process.php sets and the same one
   header.php tests to choose between the greeting and the sign-in
   button, so search can never disagree with the nav about who is
   signed in. */
function search_signed_in() {
    return isset($_SESSION['user_id']);
}

function search_allowed() {
    return !SEARCH_REQUIRE_LOGIN || search_signed_in();
}

/* Carries where the visitor was going, so signing in returns them to
   it. The value is read back through a whitelist on the other side —
   an unchecked ?next= that accepts any URL is an open redirect, which
   is how a phishing link gets to wear your domain. */
function search_login_link($next = '') {
    $url = SEARCH_LOGIN_URL;
    if ($next !== '') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'next=' . rawurlencode($next);
    }
    return $url;
}

/* ===================================================================
   1. THE CONTENT
   =================================================================== */

/* The 24 destinations, read once per request. Same file, same array,
   same fields destinations.php uses: name, town, tag, desc, image. */
function search_destinations() {
    static $rows = null;
    if ($rows !== null) { return $rows; }

    $file = __DIR__ . '/destinations-data.php';
    if (!is_file($file)) { return $rows = []; }

    $data = require $file;
    return $rows = is_array($data) ? $data : [];
}

/* MUST produce the same string as destSlug() in destinations.php, or
   search links land on anchors that do not exist.

   It is a separate function under a different name rather than a
   shared one because destinations.php declares destSlug() at file
   scope and includes this file through header.php — two functions of
   the same name is a fatal error, and the page would stop loading
   entirely. If you ever change destSlug(), change this to match. */
function search_dest_slug($name) {
    $s = strtolower(trim((string) $name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/* ---------- CATEGORIES ----------
   A COPY of $categories in destinations.php. Keep the two identical:
   these labels are what ?cat= is compared against, so a label that
   drifts here produces a search result linking to a filter that
   matches nothing.

   The honest fix is to move this array into its own file and have both
   read it — worth doing if you touch categories often. */
function search_categories() {
    return [
        'Beaches & Islands' => ['Beach', 'Beach Resort', 'Island', 'Surf'],
        'Falls & Rivers'    => ['Waterfall', 'River'],
        'Peaks & Views'     => ['Mountain', 'Peak', 'View Deck'],
        'Heritage'          => ['Church', 'Religious Site', 'Monument'],
        'Parks & Nature'    => ['Mangrove', 'Park'],
        'Stay & Adventure'  => ['Campsite', 'Farm Resort', 'Adventure'],
    ];
}

function search_category_of($tag) {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (search_categories() as $label => $tags) {
            foreach ($tags as $t) { $map[strtolower($t)] = $label; }
        }
    }
    return $map[strtolower((string) $tag)] ?? 'Other';
}

/* ---------- WORDS THAT MEAN A CATEGORY ----------
   "falls" already appears inside "Falls & Rivers", so substring
   matching finds that one on its own. These are the ones it cannot:
   nobody types "Peaks & Views", they type "hiking".

   Without this, a search for "church" returns the two churches by name
   but never offers the Heritage filter holding both — and "swimming"
   returns nothing at all. */
function search_category_aliases() {
    return [
        'Beaches & Islands' => ['beach', 'beaches', 'island', 'islands', 'sand', 'swim', 'swimming', 'snorkel', 'snorkelling', 'surf', 'surfing', 'sea', 'coast', 'sandbar'],
        'Falls & Rivers'    => ['falls', 'fall', 'waterfall', 'waterfalls', 'river', 'rivers', 'stream', 'dip'],
        'Peaks & Views'     => ['peak', 'peaks', 'mountain', 'mountains', 'hike', 'hiking', 'trek', 'trekking', 'climb', 'view', 'views', 'viewpoint', 'sunrise', 'lookout'],
        'Heritage'          => ['heritage', 'church', 'churches', 'shrine', 'monument', 'history', 'historic', 'historical', 'religious'],
        'Parks & Nature'    => ['park', 'parks', 'nature', 'mangrove', 'mangroves', 'eco', 'ecotourism', 'garden'],
        'Stay & Adventure'  => ['stay', 'stays', 'sleep', 'camp', 'camping', 'campsite', 'resort', 'farm', 'adventure', 'atv', 'zipline', 'overnight'],
    ];
}

/* ---------- PAGES THAT ARE NOT DESTINATIONS ---------- */
function search_pages() {
    return [
        ['title' => 'Home',                  'url' => 'homepage.php',
         'text'  => 'Explore Camarines Norte, beyond the horizon, start here'],
        ['title' => 'About Camarines Norte', 'url' => 'about.php',
         'text'  => 'The province at the top of the Bicol Peninsula. Twelve towns, one coastline, an interior that climbs into forest.'],
        ['title' => 'All destinations',      'url' => 'destinations.php',
         'text'  => 'All 24 places across the twelve municipalities, with a map and filters'],

        /* Food is a page, not a category — a dish has no row in
           destinations-data.php to filter to, which is why the nav
           sends this one to food.php while its five neighbours send
           ?cat=. Search has to know that too: without this row,
           "bicol express" and "laing" find nothing at all, even though
           the menu offers them one click away.

           DELETE THIS ROW IF food.php DOES NOT EXIST YET. A result
           that 404s is worse than no result — it reads as a broken
           site rather than a page still to come. */
        ['title' => 'Food & Delicacies',     'url' => 'food.php',
         'text'  => 'Bicol Express, laing, pili nuts, pineapple, kinunot, sili, local markets and delicacies'],
        ['title' => 'Gallery',               'url' => 'gallery.php',
         'text'  => 'Photos from around the province'],
    ];
}

/* ---------- OPTIONAL: EXTRA TABLES ----------
   Destinations are a file, not a table, so this is empty. If you later
   add something that IS in the database — events, articles, gallery
   rows — describe it here and it joins the same results. Every table
   and column named is checked against the real database first, so a
   typo or a table you have not built yet is skipped rather than fatal.

     ['table' => 'events', 'kind' => 'Event', 'id' => 'id',
      'title' => 'title', 'body' => ['description','location'],
      'image' => 'image', 'where' => "status = 'published'",
      'url'   => 'event.php?id={id}'],

   'where' is glued on with AND — use it to keep drafts out of search.
   Without it a stranger typing a place name finds your unpublished
   rows, which appear nowhere else on the site. */
$SEARCH_SOURCES = [];

/* The connection, required at file scope: variables assigned inside a
   function are local to it, so $conn would vanish on return. Only
   needed if you add a source above. */
$__searchDbCandidates = [
    __DIR__ . '/db.php',      __DIR__ . '/dbcon.php',    __DIR__ . '/config.php',
    __DIR__ . '/connect.php', __DIR__ . '/database.php', __DIR__ . '/db_connect.php',
    __DIR__ . '/../db.php',   __DIR__ . '/../config.php',
];
foreach ($__searchDbCandidates as $__f) {
    if (is_file($__f)) { require_once $__f; break; }
}

function search_db() {
    static $db = null;
    if ($db !== null) { return $db; }
    foreach (['conn', 'connection', 'mysqli', 'link', 'db', 'pdo', 'dbh'] as $name) {
        if (isset($GLOBALS[$name])) {
            $c = $GLOBALS[$name];
            if ($c instanceof mysqli || $c instanceof PDO) { return $db = $c; }
        }
    }
    return $db = false;
}

/* ===================================================================
   2. THE LINKS

   All four point into destinations.php. Nothing here invents a page.
   =================================================================== */

/* A place. The anchor is the card's own id, so the browser lands on it
   inside the full grid — no filter to clear afterwards, and the other
   23 are still there to keep browsing.

   #dest-<slug> also lights the card: see .dest-card:target in
   search.css. Landing halfway down a grid of 24 with nothing marked
   leaves the visitor hunting for the thing they just clicked. */
function search_url_destination($name) {
    return 'destinations.php#dest-' . search_dest_slug($name);
}

/* A category, town or tag: a filtered view of the index.

   #destFilters for exactly the reason destUrl() in destinations.php
   appends it — without the fragment the page opens at the top, with
   the banner between the visitor and the filtered grid they asked
   for. */
function search_url_filter($key, $value) {
    return 'destinations.php?' . $key . '=' . rawurlencode($value) . '#destFilters';
}

/* ===================================================================
   3. MACHINERY
   =================================================================== */

/* % and _ are wildcards inside LIKE. A visitor searching "100%" would
   otherwise match every row. Backslash first, or escaping the others
   re-breaks it. */
function search_like_escape($s) {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function search_safe_ident($name) {
    return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
}

function search_table_columns($table) {
    static $cache = [];
    if (isset($cache[$table])) { return $cache[$table]; }

    $db = search_db();
    if (!$db || !search_safe_ident($table)) { return $cache[$table] = []; }

    $cols = [];
    try {
        if ($db instanceof mysqli) {
            $prev = mysqli_report(MYSQLI_REPORT_OFF);   // a missing table is expected
            $res  = @$db->query("SHOW COLUMNS FROM `$table`");
            mysqli_report($prev);
            if ($res) {
                while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
                $res->free();
            }
        } else {
            foreach ($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[] = $row['Field'];
            }
        }
    } catch (Throwable $e) { $cols = []; }
    return $cache[$table] = $cols;
}

function search_query($sql, $params) {
    $db = search_db();
    if (!$db) { return []; }
    try {
        if ($db instanceof mysqli) {
            $stmt = $db->prepare($sql);
            if (!$stmt) { return []; }
            if ($params) { $stmt->bind_param(str_repeat('s', count($params)), ...$params); }
            $stmt->execute();
            $res  = $stmt->get_result();
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
            return $rows;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

/* Trim a description to a line, centred on the first match so the
   visitor can see why the result came back. Plain text — escaping and
   highlighting happen where it is displayed. */
function search_snippet($text, $needle, $len = 150) {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
    if ($text === '') { return ''; }
    if (mb_strlen($text) <= $len) { return $text; }

    $pos = $needle !== '' ? mb_stripos($text, $needle) : false;
    if ($pos === false || $pos < 40) { return mb_substr($text, 0, $len) . '…'; }
    return '…' . mb_substr($text, max(0, $pos - 40), $len) . '…';
}

/* every term present somewhere in the haystack */
function search_matches_all($haystack, array $terms) {
    foreach ($terms as $t) {
        if (mb_stripos($haystack, $t) === false) { return false; }
    }
    return true;
}

/* ===================================================================
   4. THE SEARCH

   Ranking, highest first. Named places beat the filters that contain
   them, EXCEPT when the query is a category word — someone typing
   "falls" wants all four waterfalls, and the fastest way to all four
   is the filter, so it goes on top with the individual falls beneath.

     exact destination name          150
     category word ("falls")     110–120
     destination name starts with q  100
     exact town name                  95
     destination name contains q      85
     exact tag                        80
     category label contains q        75
     page                          10– 85
     match in town/tag/description    30
   =================================================================== */
function search_site($q, $limit = 40) {
    global $SEARCH_SOURCES;

    /* The gate, one layer below every caller — so a page added later
       by someone who has not read this file cannot leak results by
       forgetting to ask. */
    if (!search_allowed()) { return []; }

    $q = trim(preg_replace('/\s+/u', ' ', (string) $q));
    if (mb_strlen($q) < 2) { return []; }

    $terms = array_values(array_filter(explode(' ', $q), function ($t) {
        return mb_strlen($t) >= 2;
    }));
    if (!$terms) { $terms = [$q]; }
    $terms      = array_slice($terms, 0, 6);
    $qLower     = mb_strtolower($q);
    $termsLower = array_map('mb_strtolower', $terms);

    $out   = [];
    $towns = [];
    $tags  = [];
    $namedHits = [];   /* indexes of results matched on their own name */

    /* ---------- destinations ---------- */
    foreach (search_destinations() as $d) {
        $name = (string) ($d['name'] ?? '');
        if ($name === '') { continue; }

        $town = (string) ($d['town'] ?? '');
        $tag  = (string) ($d['tag']  ?? '');
        $desc = (string) ($d['desc'] ?? '');

        /* collected while already walking the array, so the town and
           tag results below cost no second pass */
        if ($town !== '') { $towns[$town] = true; }
        if ($tag  !== '') { $tags[$tag]   = true; }

        if (!search_matches_all("$name $town $tag $desc", $terms)) { continue; }

        $byName = mb_stripos($name, $q) !== false;

        if     (mb_strtolower($name) === $qLower)  { $score = 150; }
        elseif (mb_stripos($name, $q) === 0)       { $score = 100; }
        elseif ($byName)                           { $score = 85;  }
        else                                       { $score = 30;  }

        if ($byName) { $namedHits[] = count($out); }

        $out[] = [
            'title'   => $name,
            'kind'    => 'Destination',
            'url'     => search_url_destination($name),
            'image'   => (string) ($d['image'] ?? ''),
            'snippet' => search_snippet($desc !== '' ? $desc : $town, $q),
            'meta'    => trim($tag . ($tag !== '' && $town !== '' ? ' · ' : '') . $town),
            'score'   => $score,
        ];
    }

    /* ---------- ONE NAMED PLACE BEATS ITS CATEGORY ----------
       How many destination NAMES contain the query is the whole
       signal here.

       Several, and the category is the faster route: "falls" matches
       five names, and the visitor wants all five, so "Falls & Rivers"
       belongs on top with the five underneath.

       Exactly one, and the query is a proper noun wearing a category's
       clothes. "rizal" is inside the Heritage alias list, so without
       this the First Rizal Monument — the only thing on the site by
       that name, and unmistakably what was being asked for — came
       second to a filter holding four other places. Same for
       "mangrove".

       The 4-character floor keeps short fragments out of it: "mt"
       matches one name today and would start jumping the queue for a
       query far too vague to deserve it. */
    if (count($namedHits) === 1 && mb_strlen($q) >= 4) {
        $i = $namedHits[0];
        $out[$i]['score'] = max($out[$i]['score'], 140);
    }

    /* ---------- categories ----------
       Two routes in: the label itself ("heritage" is inside
       "Heritage"), and the alias list ("hiking" is inside nothing). */
    $aliases = search_category_aliases();

    foreach (search_categories() as $label => $catTags) {
        $score = 0;
        if (mb_stripos($label, $q) !== false) { $score = 75; }

        foreach ($aliases[$label] ?? [] as $word) {
            if ($qLower === $word)                          { $score = max($score, 120); }
            elseif (in_array($word, $termsLower, true))      { $score = max($score, 110); }
        }
        if (!$score) { continue; }

        /* how many places are actually behind this filter — a chip
           leading to an empty page is worse than no chip */
        $n = 0;
        foreach (search_destinations() as $d) {
            if (search_category_of($d['tag'] ?? '') === $label) { $n++; }
        }
        if ($n === 0) { continue; }

        $out[] = [
            'title'   => $label,
            'kind'    => 'Category',
            'url'     => search_url_filter('cat', $label),
            'image'   => '',
            'snippet' => $n . ($n === 1 ? ' place' : ' places') . ' in this category',
            'meta'    => 'Filter',
            'score'   => $score,
        ];
    }

    /* ---------- towns ---------- */
    foreach (array_keys($towns) as $town) {
        if (mb_stripos($town, $q) === false) { continue; }

        $n = 0;
        foreach (search_destinations() as $d) {
            if (strcasecmp((string) ($d['town'] ?? ''), $town) === 0) { $n++; }
        }

        $out[] = [
            'title'   => $town,
            'kind'    => 'Town',
            'url'     => search_url_filter('town', $town),
            'image'   => '',
            'snippet' => $n . ($n === 1 ? ' place' : ' places') . ' in ' . $town,
            'meta'    => 'Municipality',
            'score'   => mb_strtolower($town) === $qLower ? 95 : 60,
        ];
    }

    /* ---------- tags ----------
       Finer than a category: "Waterfall" rather than "Falls & Rivers".
       Skipped when its own category already matched, so "falls" does
       not return the group and the tag as two near-identical rows. */
    $haveCat = [];
    foreach ($out as $r) {
        if ($r['kind'] === 'Category') { $haveCat[$r['title']] = true; }
    }

    foreach (array_keys($tags) as $tag) {
        if (mb_stripos($tag, $q) === false) { continue; }
        if (isset($haveCat[search_category_of($tag)])) { continue; }

        $n = 0;
        foreach (search_destinations() as $d) {
            if (strcasecmp((string) ($d['tag'] ?? ''), $tag) === 0) { $n++; }
        }

        $out[] = [
            'title'   => $tag,
            'kind'    => 'Type',
            'url'     => search_url_filter('type', $tag),
            'image'   => '',
            'snippet' => $n . ($n === 1 ? ' place' : ' places') . ' tagged ' . $tag,
            'meta'    => 'Filter',
            'score'   => mb_strtolower($tag) === $qLower ? 80 : 55,
        ];
    }

    /* ---------- pages ---------- */
    foreach (search_pages() as $p) {
        if (!search_matches_all($p['title'] . ' ' . $p['text'], $terms)) { continue; }

        if     (mb_strtolower($p['title']) === $qLower) { $score = 85; }
        elseif (mb_stripos($p['title'], $q) === 0)      { $score = 70; }
        elseif (mb_stripos($p['title'], $q) !== false)  { $score = 50; }
        else                                            { $score = 10; }

        $out[] = [
            'title'   => $p['title'],
            'kind'    => 'Page',
            'url'     => $p['url'],
            'image'   => '',
            'snippet' => search_snippet($p['text'], $q),
            'meta'    => '',
            'score'   => $score,
        ];
    }

    /* ---------- optional database sources ---------- */
    foreach ($SEARCH_SOURCES as $src) {
        $table = $src['table'] ?? '';
        if (!search_safe_ident($table)) { continue; }

        $have = search_table_columns($table);
        if (!$have) { continue; }

        $idCol    = in_array($src['id'] ?? '', $have, true)    ? $src['id']    : null;
        $titleCol = in_array($src['title'] ?? '', $have, true) ? $src['title'] : null;
        if (!$idCol || !$titleCol) { continue; }

        $imgCol = (!empty($src['image']) && in_array($src['image'], $have, true)) ? $src['image'] : null;

        $bodyCols = [];
        foreach (($src['body'] ?? []) as $c) {
            if (in_array($c, $have, true) && search_safe_ident($c)) { $bodyCols[] = $c; }
        }

        $qLike  = '%' . search_like_escape($q) . '%';
        $params = [];

        $score = "(CASE WHEN `$titleCol` = ? THEN 150 WHEN `$titleCol` LIKE ? THEN 100"
               . " WHEN `$titleCol` LIKE ? THEN 85 ELSE 30 END)";
        $params[] = $q;
        $params[] = search_like_escape($q) . '%';
        $params[] = $qLike;

        $where = [];
        foreach ($terms as $t) {
            $tLike = '%' . search_like_escape($t) . '%';
            $ors   = [];
            foreach (array_merge([$titleCol], $bodyCols) as $c) {
                $ors[]    = "`$c` LIKE ?";
                $params[] = $tLike;
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
        if (!empty($src['where'])) { $where[] = '(' . $src['where'] . ')'; }

        $select  = "`$idCol` AS _id, `$titleCol` AS _title";
        $select .= $imgCol   ? ", `$imgCol` AS _img"         : ", '' AS _img";
        $select .= $bodyCols ? ", `{$bodyCols[0]}` AS _body" : ", '' AS _body";
        $select .= ", $score AS _score";

        $sql = "SELECT $select FROM `$table` WHERE " . implode(' AND ', $where)
             . " ORDER BY _score DESC LIMIT " . (int) $limit;

        foreach (search_query($sql, $params) as $row) {
            $out[] = [
                'title'   => (string) $row['_title'],
                'kind'    => $src['kind'] ?? 'Result',
                'url'     => str_replace(['{id}', '{title}'],
                                         [rawurlencode($row['_id']), rawurlencode($row['_title'])],
                                         $src['url'] ?? '#'),
                'image'   => (string) $row['_img'],
                'snippet' => search_snippet($row['_body'], $q),
                'meta'    => '',
                'score'   => (int) $row['_score'],
            ];
        }
    }

    /* Alphabetical inside a tie, so two equally-good hits do not
       shuffle between page loads. */
    usort($out, function ($a, $b) {
        return $b['score'] <=> $a['score'] ?: strcasecmp($a['title'], $b['title']);
    });

    return array_slice($out, 0, $limit);
}

/* ---------- THE JUMP ----------
   Returns a URL when the query names one thing exactly, otherwise
   null.

   Deliberately strict: an exact destination name, an exact town, or an
   exact category — never a partial. "Bagasbas Beach" jumps; "bagasbas"
   lists, because that could just as well mean the town's other places.
   Guessing wrong here sends someone somewhere they did not ask to go,
   with no idea what happened or how to get back. */
function search_exact_target($q) {
    if (!SEARCH_JUMP_ON_EXACT || !search_allowed()) { return null; }

    $q = mb_strtolower(trim((string) $q));
    if ($q === '') { return null; }

    foreach (search_destinations() as $d) {
        if (mb_strtolower((string) ($d['name'] ?? '')) === $q) {
            return search_url_destination($d['name']);
        }
    }
    foreach (search_categories() as $label => $tags) {
        if (mb_strtolower($label) === $q) { return search_url_filter('cat', $label); }
    }
    foreach (search_destinations() as $d) {
        $town = (string) ($d['town'] ?? '');
        if ($town !== '' && mb_strtolower($town) === $q) {
            return search_url_filter('town', $town);
        }
    }
    return null;
}

/* Thumbnails. destinations-data.php stores paths like uploads/foo.jpg
   already, so most hit the first branch. Returns '' when the file is
   missing and the card falls back to its icon rather than a broken
   image. */
function search_image_url($file) {
    $file = trim((string) $file);
    if ($file === '') { return ''; }
    if (preg_match('#^(https?:)?//#', $file) || $file[0] === '/') { return $file; }

    $root = __DIR__ . '/../';
    if (is_file($root . $file)) { return $file; }

    foreach (['uploads/', 'assets/img/', 'images/', 'img/'] as $dir) {
        if (is_file($root . $dir . $file)) { return $dir . $file; }
    }
    return '';
}