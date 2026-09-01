<?php
/* ===================================================================
   includes/gallery-data.php

   The one place gallery.php gets its photographs from. Returns the
   three chapters, each with its visible photos already in order.

   WHY THERE IS A FALLBACK
   -----------------------
   If MySQL is down, or the tables have not been created yet, or the
   credentials in db.php are wrong, this hands back the twelve photos
   that were hardcoded in the page before — so the gallery still looks
   exactly as it does today rather than showing a stack trace to a
   visitor.

   That means you can drop these files in and the site keeps working
   before you have run gallery.sql. Get the page live first, wire the
   database second. Nothing has a deadline in the wrong order.

   Delete the fallback once the database side is running if you like.
   It costs nothing to leave it.
   =================================================================== */

require_once __DIR__ . '/db.php';

/**
 * @return array<int, array{eyebrow:string, title:string, note:string,
 *                          is_mist:bool, photos:array}>
 */
function gallery_sets(): array
{
    try {
        $pdo = db();

        $sets = $pdo->query(
            'SELECT id, eyebrow, title, note, is_mist::int AS is_mist
               FROM gallery_sets
              ORDER BY sort_order, id'
        )->fetchAll();

        if (!$sets) {
            return gallery_fallback();     // tables exist but are empty
        }

        /* One query for all the photos rather than one per chapter.
           Three queries instead of four is not the point — the point
           is that adding a fourth chapter would not add a query.

           "WHERE is_visible" with no comparison: is_visible is a real
           PostgreSQL boolean, so it IS the condition. Writing
           "is_visible = 1" here would be a type error — booleans and
           integers are different things in Postgres, unlike MySQL
           where a boolean is really just a small number. */
        $photos = $pdo->query(
            'SELECT id, set_id, filename, place, town, alt, ratio
               FROM gallery_photos
              WHERE is_visible
              ORDER BY set_id, sort_order, id'
        )->fetchAll();

        $bySet = [];
        foreach ($photos as $p) {
            $bySet[$p['set_id']][] = $p;
        }

        $out = [];
        foreach ($sets as $s) {
            $out[] = [
                'eyebrow' => $s['eyebrow'],
                'title'   => $s['title'],
                'note'    => $s['note'],
                /* is_mist was cast to int in the query above, so this
                   is 1 or 0. Without that cast PDO's pgsql driver can
                   hand back the string 'f' for false — and (bool)'f'
                   is TRUE in PHP, because any non-empty string is.
                   Every chapter would have got the grey band and the
                   page would have looked wrong with nothing erroring. */
                'is_mist' => (bool) $s['is_mist'],
                'photos'  => $bySet[$s['id']] ?? [],
            ];
        }
        return $out;

    } catch (Throwable $ex) {
        /* A visitor must never see a database error. Log it for
           yourself and show the page as it was. */
        error_log('gallery-data: ' . $ex->getMessage());
        return gallery_fallback();
    }
}

/* -------------------------------------------------------------------
   COUNTS FOR THE BANNER

   These three numbers used to be typed by hand — "12 / 03 / 09" — and
   the comment at the top of gallery.php warned that they start lying
   the moment anyone adds a tile. Now they are counted, so they cannot.

   The towns figure is DISTINCT towns, which is what it always claimed
   to be: nine towns across twelve photographs, because Daet, Mercedes
   and Paracale each appear twice.
   ------------------------------------------------------------------- */

/** @return array{photos:int, chapters:int, towns:int} */
function gallery_counts(array $sets): array
{
    $photos = 0;
    $towns  = [];

    foreach ($sets as $set) {
        foreach ($set['photos'] as $p) {
            $photos++;
            $towns[strtolower(trim($p['town']))] = true;
        }
    }

    /* A chapter with no photographs in it is not a chapter yet. */
    $chapters = count(array_filter($sets, fn($s) => count($s['photos']) > 0));

    return [
        'photos'   => $photos,
        'chapters' => $chapters,
        'towns'    => count($towns),
    ];
}

/** Zero-pad the way the banner has always shown these. */
function gal_pad(int $n): string
{
    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

/* -------------------------------------------------------------------
   THE MASONRY STAGGER

   The animation delays run 0 / 60 / 120 / 60 across each row of four.
   That was written into each tile by hand before, which meant
   reordering photos scrambled the rhythm. Deriving it from position
   means the stagger survives any reorder the admin does.
   ------------------------------------------------------------------- */
function gal_delay(int $index): int
{
    return [0, 60, 120, 60][$index % 4];
}


/* ===================================================================
   FALLBACK — the twelve photos exactly as they were in the markup.
   Only reached if the database cannot be read.
   =================================================================== */
function gallery_fallback(): array
{
    return [
        [
            'eyebrow' => '01 — The coast',
            'title'   => 'Where the province meets the Pacific',
            'note'    => 'The whole eastern side of Camarines Norte faces open water. These four were taken between Vinzons and Paracale.',
            'is_mist' => false,
            'photos'  => [
                ['filename' => 'Calaguass.jpg',   'place' => 'Calaguas',    'town' => 'Vinzons',  'alt' => 'The shoreline at Calaguas, Vinzons', 'ratio' => 'ratio-3x4'],
                ['filename' => 'Bagasbas.jpg',    'place' => 'Bagasbas',    'town' => 'Daet',     'alt' => 'The beach at Bagasbas, Daet',       'ratio' => 'ratio-4x3'],
                ['filename' => 'Islets.jpg',      'place' => 'The islets',  'town' => 'Mercedes', 'alt' => 'The islets off Mercedes',           'ratio' => 'ratio-1x1'],
                ['filename' => 'Pulang-Daga.jpg', 'place' => 'Pulang Daga', 'town' => 'Paracale', 'alt' => 'Pulang Daga beach, Paracale',       'ratio' => 'ratio-4x5'],
            ],
        ],
        [
            'eyebrow' => '02 — Inland',
            'title'   => 'Forest, falls, and higher ground',
            'note'    => 'Turn away from the sand and the road climbs into river country within the hour. Falls, mangrove, and the ground behind Labo.',
            'is_mist' => true,
            'photos'  => [
                ['filename' => 'Mananap.jpg',  'place' => 'Mananap Falls', 'town' => 'San Vicente',      'alt' => 'Mananap Falls, San Vicente',          'ratio' => 'ratio-4x5'],
                ['filename' => 'Mangrove.jpg', 'place' => 'Mangrove park', 'town' => 'Talisay',          'alt' => 'The mangrove park at Talisay',        'ratio' => 'ratio-4x3'],
                ['filename' => 'Bagacay.jpg',  'place' => 'Mt. Bagacay',   'town' => 'Labo',             'alt' => 'Mt. Bagacay, Labo',                   'ratio' => 'ratio-3x4'],
                ['filename' => 'Mampili.jpg',  'place' => 'Mampili River', 'town' => 'San Lorenzo Ruiz', 'alt' => 'The Mampili River, San Lorenzo Ruiz', 'ratio' => 'ratio-1x1'],
            ],
        ],
        [
            'eyebrow' => '03 — The towns',
            'title'   => 'Streets, churches, and working ports',
            'note'    => 'The part of the province people live in rather than visit: goldsmiths\' benches, church doors, and a port that never really stops.',
            'is_mist' => false,
            'photos'  => [
                ['filename' => 'Museum.jpg',   'place' => 'Goldsmith shops',              'town' => 'Paracale',  'alt' => 'Goldsmith shops in Paracale',                'ratio' => 'ratio-4x3'],
                ['filename' => 'Monument.jpg', 'place' => 'First Rizal Monument',         'town' => 'Daet',      'alt' => 'The first Rizal Monument, Daet',             'ratio' => 'ratio-3x4'],
                ['filename' => 'Church.jpg',   'place' => 'Shrine of the Black Nazarene', 'town' => 'Capalonga', 'alt' => 'The Shrine of the Black Nazarene, Capalonga','ratio' => 'ratio-1x1'],
                ['filename' => 'FishPort.jpg', 'place' => 'Fishing port',                 'town' => 'Mercedes',  'alt' => 'The fishing port at Mercedes',               'ratio' => 'ratio-4x5'],
            ],
        ],
    ];
}