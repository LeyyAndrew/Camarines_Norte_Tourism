<?php
/* ===================================================================
   includes/destinations-data.php

   THE 24 DESTINATIONS — still the single source of truth, still read
   by both homepage.php and destinations.php. It now returns rows from
   the database instead of an array typed into this file, and they are
   managed at admin/destinations.php.

   THE SHAPE OF WHAT IT RETURNS HAS NOT CHANGED. Same keys, same
   order, same types:

     image  full path, uploads/Destination-Photo/<file>
     tag    the small label on the card and thumbnail
     town   the municipality, used to group the destinations page
     name   the heading
     quote  the one-line pull quote
     desc   one or two sentences
     chips  three short facts

   ...plus two that are new:

     lat    decimal degrees, or null
     lng    decimal degrees, or null

   destinations.php builds its own $mapPoints, $heroSlides and
   $railPicks out of this array, so all of those follow with no edit —
   and so does the map, the hover balloon and the detail sheet, since
   they read $mapPoints. That is the whole reason this was worth
   doing: one seam, six consumers.

   ⚠ THE $coords ARRAY IN destinations.php IS NOW DEAD. Its own
   comment said the lookup "already prefers the data file wherever
   those keys exist", and lat/lng now always exist. Delete it, and the
   lookup that reads it. Leaving it in place is harmless but it will
   go stale the first time somebody moves a pin in the admin and then
   wonders why the array still says the old number.

   ⚠ 'image' IS A PATH, NOT A BARE FILENAME. The database stores only
   the filename; the prefix is added here, in one place. So a stored
   value can never point outside the photo folder, and moving that
   folder is still a one-line change — exactly what the old comment at
   the top of this file promised.
   =================================================================== */

require_once __DIR__ . '/db.php';

/** Where the photographs live, relative to the project root. */
const DEST_PHOTO_DIR = 'uploads/Destination-Photo/';

try {
    $rows = db()->query(
        'SELECT filename, name, town, tag, quote, descr,
                chip1, chip2, chip3, lat, lng
           FROM destinations
          WHERE is_visible
       ORDER BY sort_order, id'
    )->fetchAll();

    if (!$rows) {
        return dest_fallback();          // table exists but is empty
    }

    $out = [];

    foreach ($rows as $r) {
        /* Three columns back into the three-item list the pages
           expect. array_values so the keys are 0,1,2 even when a chip
           in the middle is blank — array_filter alone would leave
           gaps, and the card loops by position. */
        $chips = array_values(array_filter([
            trim($r['chip1']),
            trim($r['chip2']),
            trim($r['chip3']),
        ], static fn($c) => $c !== ''));

        $out[] = [
            /* A row with no photo yet returns '' rather than a path
               to nothing. Every page already draws the gradient
               placeholder when the file is missing, and an empty src
               is what triggers it cleanly. */
            'image' => $r['filename'] !== '' ? DEST_PHOTO_DIR . $r['filename'] : '',
            'tag'   => $r['tag'],
            'town'  => $r['town'],
            'name'  => $r['name'],
            'quote' => $r['quote'],
            'desc'  => $r['descr'],
            'chips' => $chips,

            /* Cast, because PDO returns NUMERIC as a string. The map
               JSON would otherwise carry "14.136808" in quotes, and
               Leaflet wants numbers. null stays null — a destination
               with no pin is skipped rather than dropped at 0,0. */
            'lat'   => $r['lat'] !== null ? (float) $r['lat'] : null,
            'lng'   => $r['lng'] !== null ? (float) $r['lng'] : null,
        ];
    }

    return $out;

} catch (Throwable $e) {
    /* A visitor must never see a database error, and destinations.php
       would fatal on a non-array. Log it and serve the last known
       good copy. */
    error_log('destinations-data: ' . $e->getMessage());
    return dest_fallback();
}


/* ===================================================================
   FALLBACK

   The 24 entries exactly as they were written in this file, including
   the coordinates that used to sit in destinations.php. Reached only
   if the database cannot be read — a wrong password, a table not yet
   created, Postgres not running.

   It means you can drop these files in and browse the site before you
   have run destinations.sql. Nothing has a deadline in the wrong
   order.

   THIS COPY DOES NOT UPDATE ITSELF. Once the database is live, this
   is a snapshot of how things looked today, not a mirror. It is here
   so an outage degrades to "slightly out of date" instead of "white
   screen". Delete it if you would rather see failures loudly — the
   catch above will still stop the fatal.
   =================================================================== */
function dest_fallback(): array
{
    $d = static fn(string $file, string $tag, string $town, string $name,
                   string $quote, string $desc, array $chips,
                   ?float $lat, ?float $lng): array => [
        'image' => $file !== '' ? DEST_PHOTO_DIR . $file : '',
        'tag'   => $tag,
        'town'  => $town,
        'name'  => $name,
        'quote' => $quote,
        'desc'  => $desc,
        'chips' => $chips,
        'lat'   => $lat,
        'lng'   => $lng,
    ];

    return [
        /* ---------------- BASUD ---------------- */
        $d('Taba-Beach.jpg', 'Beach Resort', 'Basud', 'Taba Taba Beach Resort',
           'Where the northern coast begins.',
           'A beach resort on the Basud shoreline, the first coastal stop heading north out of Daet.',
           ['Beachfront', 'Day trips', 'Contact for rates'], 14.056862, 123.029751),

        $d('La-Maestra.jpg', 'Campsite', 'Basud', 'La Maestra Campsite and Resort',
           'Pitch a tent, stay the night.',
           'A campsite and resort in Basud for visitors who would rather sleep outdoors than book a room.',
           ['Camping', 'Overnight stays', 'Contact for rates'], 14.011700, 122.995805),

        /* ---------------- CAPALONGA ---------------- */
        $d('Capalonga-Church.jpg', 'Religious Site', 'Capalonga', 'Shrine of the Black Nazarene',
           "The town's oldest standing appointment.",
           "Capalonga's shrine draws pilgrims from across Bicol, and the town's calendar still turns around its feast.",
           ['Pilgrimage site', 'Feast celebrations', 'Town centre'], 14.331900, 122.494152),

        $d('Capalonga-Pulong-Guijanlo.jpg', 'Island', 'Capalonga', 'Pulong Guijanlo',
           'Off the Capalonga coast.',
           'A small island off Capalonga, reached by boat from the mainland.',
           ['Boat access', 'Island hopping', 'Arrange locally'], 14.339568, 122.445773),

        /* ---------------- DAET ---------------- */
        $d('Daet-Bagabas.jpg', 'Surf', 'Daet', 'Bagasbas Beach',
           "The north's longest ride.",
           'The surf capital of the north, with a steady break that holds nearly year-round and a boardwalk that fills once the heat drops.',
           ['Year-round swell', 'Board rentals', 'Sunset boardwalk'], 14.136808, 122.983065),

        $d('daet-rizal-monument.jpg', 'Monument', 'Daet', 'First Rizal Monument',
           'Raised before the country had a name for itself.',
           'Built in 1898, the earliest monument to Jose Rizal anywhere, put up by local subscription within two years of his execution.',
           ['Built 1898', 'National landmark', 'Town centre'], 14.113631, 122.954421),

        /* ---------------- JOSE PANGANIBAN ---------------- */
        $d('Panganiban-Turayog.jpg', 'View Deck', 'Jose Panganiban', 'Turayog View Deck',
           'Climb once, see the whole bay.',
           'A view deck above Jose Panganiban, reached on foot and best timed for early morning.',
           ['Viewpoint', 'Short climb', 'Sunrise spot'], 14.299185, 122.703033),

        $d('Panganiban-Parola-Island.jpg', 'Island', 'Jose Panganiban', 'Parola Island',
           'The lighthouse island.',
           'A small island off the Jose Panganiban coast, reached by boat from the mainland.',
           ['Boat access', 'Island hopping', 'Lighthouse'], 14.461685, 122.670138),

        /* ---------------- LABO ---------------- */
        $d('Labo-MalatapFalls.jpg', 'Waterfall', 'Labo', 'Malatap Falls',
           'Cold water at the end of a warm walk.',
           'A falls in Labo, reached by a short trek inland from the highway.',
           ['Short trek', 'Natural pool', 'Local guides'], 14.167221, 122.619140),

        $d('Labo-TulisPeak.jpg', 'Peak', 'Labo', 'Tulis Peak, Mt. Bagacay',
           'A morning, not an expedition.',
           'The summit of Mt. Bagacay, a manageable climb with wide views for anyone who would rather be back down by lunch.',
           ['Day hike', 'Viewpoint', 'Guided climb'], 14.212503, 122.831774),

        /* ---------------- MERCEDES ---------------- */
        $d('Mercedes-Canimog.jpg', 'Island', 'Mercedes', 'Canimog Island',
           'One of the islands off Mercedes.',
           'An island off the Mercedes coast, reached by boat from the town.',
           ['Boat access', 'Island hopping', 'Day trip'], 14.124068, 123.060661),

        $d('Mercedes-Pebble.jpg', 'Beach', 'Mercedes', 'Pebble Beach',
           'Stones instead of sand.',
           'A pebble shoreline on the Mercedes coast, a different beach from the white sand the province is known for.',
           ['Pebble shore', 'Swimming', 'Photo stop'], 13.963047, 123.082744),

        /* ---------------- PARACALE ---------------- */
        $d('Paracale-Macolabo-island.jpg', 'Island', 'Paracale', 'Macolabo Island',
           'Off the gold coast.',
           'An island off Paracale, reached by boat from the town that has worked gold for three centuries.',
           ['Boat access', 'Island hopping', 'Gold country'], 14.404361, 122.810427),

        $d('paracale-gumaus.jpg', 'Beach', 'Paracale', 'Gumaus Beach',
           'Long, open, and quiet.',
           'A wide stretch of shoreline at Paracale, popular with locals and largely undeveloped.',
           ['Wide shoreline', 'Local favourite', 'Camping'], 14.313018, 122.727897),

        /* ---------------- SAN LORENZO RUIZ ---------------- */
        $d('SlRuiz-NacaliFalls.jpg', 'Waterfall', 'San Lorenzo Ruiz', 'Nacali Falls',
           'Water off the uplands.',
           'A falls in the uplands of San Lorenzo Ruiz, a short way off the road inland.',
           ['Forest trail', 'Natural pool', 'Local guides'], 14.054017, 122.856969),

        $d('slruiz-mampili.jpg', 'River', 'San Lorenzo Ruiz', 'Mampili River',
           'Cold, clear, and running.',
           'A river in San Lorenzo Ruiz, a local spot for swimming and riverside afternoons.',
           ['River swimming', 'Shaded', 'Picnic spot'], 13.993317, 122.842741),

        /* ---------------- SAN VICENTE ---------------- */
        $d('SanVicente-MananapFalls.jpg', 'Waterfall', 'San Vicente', 'Mananap Falls',
           'Three drops into jade water.',
           'A short forest trail leads to a tiered falls, cool enough to cut the midday heat in half.',
           ['Forest trailhead', 'Natural pool', 'Cool year-round'], 14.054172, 122.827185),

        $d('SanVicente-Mananap-Atv.jpg', 'Adventure', 'San Vicente', 'Mananap Falls ATV Adventure',
           'The loud way in.',
           'Guided ATV rides on the trails around Mananap Falls, booked through local operators.',
           ['ATV rides', 'Guided', 'Book ahead'], 14.075198, 122.866770),

        /* ---------------- SANTA ELENA ---------------- */
        $d('StaElena-BusayFalls.jpg', 'Waterfall', 'Santa Elena', 'Busay Falls',
           'The far edge of the province, and worth the drive.',
           'Santa Elena sits at the northern boundary, and Busay is the reason most visitors make the trip out.',
           ['Waterfall', 'Swimming', 'Day trip'], 14.201250, 122.418152),

        $d('StaElena-delmoro.jpg', 'Park', 'Santa Elena', 'Del Moro Park',
           'Shade, benches, and a slower hour.',
           'A public park in Santa Elena, an easy stop for anyone breaking the drive north.',
           ['Public park', 'Shaded', 'Family friendly'], 14.180128, 122.391870),

        /* ---------------- TALISAY ---------------- */
        $d('Talisay-Mangroves.jpg', 'Mangrove', 'Talisay', 'Mangrove Eco Tourism Park',
           'Quiet you can actually hear.',
           "A boardwalk threads through Talisay's mangrove stands, home to herons, mudskippers, and very little noise.",
           ['Boardwalk trail', 'Bird watching', 'Community-run'], 14.287434, 122.919670),

        $d('Talisay-Church.jpg', 'Church', 'Talisay', 'St. Francis of Assisi Parish Church',
           'The centre of town, in every sense.',
           "Talisay's parish church, the anchor of the town centre and busiest on feast days.",
           ['Historic church', 'Town centre', 'Feast days'], 14.146563, 122.925093),

        /* ---------------- VINZONS ---------------- */
        $d('Vinzons-Calaguas.jpg', 'Island', 'Vinzons', 'Calaguas Island',
           'Where the tide writes the only footprints.',
           "Mahabang Buhangin's long white shore, reachable only by boat and still best seen with a tent.",
           ['Powder-white sand', 'Boat access only', 'Beach camping'], 14.473603, 122.937878),

        $d('Vinzons-Panit.jpg', 'Mountain', 'Vinzons', 'Mt. Panit',
           'Low peak, wide view.',
           'A climb above Vinzons with views back across the coast and out toward the Calaguas group.',
           ['Day hike', 'Coastal views', 'Guided climb'], 14.264201, 122.861659),
    ];
}