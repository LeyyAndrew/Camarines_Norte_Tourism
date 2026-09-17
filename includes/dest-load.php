<?php
/* ===================================================================
   includes/dest-load.php — the one door onto the destination data.

   WHY THIS FILE EXISTS

   includes/destinations-data.php ENDS IN A RETURN, and it declares
   dest_fallback() at the top level. That combination has one sharp
   edge: a file that returns a value cannot be loaded with require_once,
   because require_once hands back the boolean true on the second call
   and the array is lost. So the call sites all used plain require —
   and destinations.php loads it twice in one request:

     destinations.php:20      $destinations = require ...
     destinations.php:2031    require includes/bud-widget.php
       └─ bud-widget.php:131  $rows = require $destFile;   ← same file

   The second load redeclares dest_fallback() and kills the page:

     Fatal error: Cannot redeclare dest_fallback()
     (previously declared in includes/destinations-data.php:138)
     in includes/destinations-data.php on line 287

   138 is where the function opens, 287 is where the duplicate closes.
   It is one file included twice, not two copies of a function.

   AND IT IS NOT CATCHABLE. bud-widget.php wraps its require in
   try/catch (Throwable), which reads like it should contain this. It
   cannot: a redeclare is a compile-time fatal, not an exception, so
   the catch block never runs and the page ends mid-document — which
   is also why the footer never printed, why hero-video.js never
   loaded, and why the banner clip sat frozen on its poster.

   So the guard lives here. The require happens once, inside a
   function, and the result is held in a static. Every caller after
   the first gets the same array without the file being executed
   again. The data files themselves do not change at all.

   USE, from anywhere, as many times as you like:

     require_once __DIR__ . '/dest-load.php';           // from includes/
     require_once __DIR__ . '/includes/dest-load.php';  // from a page

     $destinations = dest_data();
     $destDetails  = dest_details();

   This file only declares functions and returns nothing, so
   require_once is the correct verb for it — the one thing the data
   files could never use.

   A NOTE ON COST. Both data files do real work on load:
   destinations-data.php opens the database and runs a query. Before
   this, the destinations page ran that query twice per request. Now
   it runs once, and the widget in the footer reads the same rows the
   page above it is already displaying — so the cards can no longer
   disagree with the list, which they could if an admin saved between
   the two queries.
   =================================================================== */

if (!function_exists('dest_data')) {
    /**
     * The 24 destinations, in the shape includes/destinations-data.php
     * returns them: id, image, tag, town, name, quote, desc, chips,
     * lat, lng. Loaded on the first call, cached for the rest of the
     * request.
     *
     * @return array  Empty if the data file is missing or returns
     *                something that is not an array. Never null, so
     *                callers can foreach the result without checking.
     */
    function dest_data(): array
    {
        static $data = null;

        if ($data === null) {
            $data = dest_load_file(__DIR__ . '/destinations-data.php');
        }

        return $data;
    }
}

if (!function_exists('dest_details')) {
    /**
     * The long-form fields the map balloon opens into — how to get
     * there, what to eat, who to book with — keyed by destination
     * name, plus the 'fallback' contact. Same contract as above:
     * loaded once, cached, never re-executed.
     *
     * @return array
     */
    function dest_details(): array
    {
        static $details = null;

        if ($details === null) {
            $details = dest_load_file(__DIR__ . '/destination-details.php');
        }

        return $details;
    }
}

if (!function_exists('dest_load_file')) {
    /**
     * require a data file and insist on getting an array back.
     *
     * The is_file() check is what bud-widget.php used to do for itself
     * and is worth keeping: a missing data file should cost the widget
     * its photo cards, not take the page down. A data file that
     * returns a non-array is a broken data file, but a page that
     * fatals on one is worse — destinations.php would die on the
     * foreach.
     *
     * @internal Called only by dest_data() and dest_details().
     */
    function dest_load_file(string $path): array
    {
        if (!is_file($path)) {
            error_log('[dest-load] missing data file: ' . $path);
            return [];
        }

        /* Genuine exceptions only. destinations-data.php already
           catches its own database trouble and falls back, so anything
           arriving here is unexpected — but it is still not worth a
           white screen. */
        try {
            $data = require $path;
        } catch (Throwable $e) {
            error_log('[dest-load] ' . basename($path) . ': ' . $e->getMessage());
            return [];
        }

        return is_array($data) ? $data : [];
    }
}