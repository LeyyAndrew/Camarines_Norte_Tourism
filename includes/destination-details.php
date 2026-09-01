<?php
/* ===================================================================
   includes/destination-details.php

   THE LONG-FORM FIELDS: how to get there, what to eat, who to book
   with. These are the things the map balloon opens into.

   WHY THIS IS A SEPARATE FILE, and not four more keys inside
   includes/destinations-data.php:

     destinations-data.php is read by homepage.php as well, and the
     homepage does not use any of this. Keeping it here means the
     source-of-truth file stays the short one that both pages share,
     and this file — the one a tourism officer will actually be
     filling in over the next few weeks — is a single flat list they
     can work down without scrolling past photo paths and pull quotes.

     It is keyed by the destination NAME, matched exactly against
     'name' in destinations-data.php. Rename a destination there and
     you must rename it here too, or its details quietly stop
     appearing. That is the one cost of splitting the files.

   ⚠ ALMOST ALL OF THIS IS EMPTY, ON PURPOSE.

     Routes, fares, food and phone numbers are the four things on a
     tourism site that do real damage when they are wrong. Somebody
     rides two hours on a made-up jeepney route; somebody rings a
     number that belongs to a stranger. So nothing is guessed here.
     An entry left as [] renders as an honest "still being confirmed"
     line in the balloon rather than as an invented itinerary.

     ONE entry is filled in — Bagasbas Beach — and it is marked
     SAMPLE. It exists so you can see the shape of a finished entry
     and copy it. Check every line of it against the tourism office
     before you let it go live, and delete the SAMPLE marker when
     you have.

   HOW TO FILL ONE IN — the shape, in full:

     'Some Destination' => [

       'how' => [
         // Ordered steps, one short line each. Start from where a
         // visitor actually is: Daet, or the provincial bus stop.
         'From Daet, take a jeep to X (about 40 minutes).',
         'At the X terminal, tricycles run to the gate for a set fare.',
       ],

       'eat' => [
         // What to eat and where. A dish, a carinderia, a market —
         // whatever is true. Two or three lines is plenty.
         'Grilled fish at the stalls along the shore.',
       ],

       'book' => [
         'org'      => 'Who answers the phone — the name of the
                        association, resort, or municipal office.',
         'phone'    => '+63 9XX XXX XXXX',
         'email'    => 'someone@example.com',
         'fb'       => 'https://facebook.com/theirpage',
         'note'     => 'One line of standing advice: book a day ahead,
                        boats stop at 4pm, bring cash. Optional.',
         'packages' => [
           ['name' => 'Day tour',   'detail' => 'Boat, guide, entrance', 'price' => 'PHP 000 per head'],
           ['name' => 'Overnight',  'detail' => 'Tent pitch and water',  'price' => 'PHP 000 per night'],
         ],
       ],
     ],

   Every key is optional. Leave out what you do not have yet — the
   balloon only draws the sections that have something in them.
   =================================================================== */


/* -------------------------------------------------------------------
   THE FALLBACK CONTACT

   Shown in the booking section of any destination that has no 'book'
   of its own — which, today, is twenty-three of the twenty-four. One
   real contact everywhere beats twenty-three blanks, and it is one
   edit here rather than twenty-three edits below.

   FILL THIS IN FIRST. It is the highest-value line in the file.
   ------------------------------------------------------------------- */
$PROVINCIAL_CONTACT = [
    'org'   => 'Provincial Tourism Office, Camarines Norte',
    'phone' => '',                 /* ← the office trunkline */
    'email' => '',                 /* ← the office address   */
    'fb'    => '',                 /* ← the office page      */
    'note'  => 'Rates, boat arrangements and guides for this destination are still being confirmed. The provincial office can point you to the operator.',
];


return [

    'fallback' => $PROVINCIAL_CONTACT,

    'places' => [

        /* ---------------- BASUD ---------------- */
        'Taba Taba Beach Resort'              => [],
        'La Maestra Campsite and Resort'      => [],

        /* ---------------- CAPALONGA ---------------- */
        'Shrine of the Black Nazarene'        => [],
        'Pulong Guijanlo'                     => [],

        /* ---------------- DAET ---------------- */

        /* ⚠ SAMPLE — the only filled entry in this file.
           It is here to show the shape. VERIFY EVERY LINE with the
           Daet municipal tourism office before publishing, then
           delete this comment. */
        'Bagasbas Beach' => [
            'how' => [
                'SAMPLE — verify: Bagasbas is on the coast about 4km from Daet town centre.',
                'SAMPLE — verify: tricycles run from the Daet public market to the boardwalk.',
                'SAMPLE — verify: the beach road is paved the whole way and parking is on the boardwalk end.',
            ],
            'eat' => [
                'SAMPLE — verify: the food stalls along the boardwalk open in the late afternoon.',
                'SAMPLE — verify: several small cafes face the break on the main beach road.',
            ],
            'book' => [
                'org'      => 'SAMPLE — the surf instructors\' association or municipal tourism desk',
                'phone'    => '',
                'email'    => '',
                'fb'       => '',
                'note'     => 'SAMPLE — verify: board rental and lessons are arranged on the beach itself.',
                'packages' => [
                    ['name' => 'Board rental',  'detail' => 'Per hour, board only',        'price' => 'Ask on site'],
                    ['name' => 'Surf lesson',   'detail' => 'Instructor, board included',  'price' => 'Ask on site'],
                ],
            ],
        ],

        'First Rizal Monument'                => [],

        /* ---------------- JOSE PANGANIBAN ---------------- */
        'Turayog View Deck'                   => [],
        'Parola Island'                       => [],

        /* ---------------- LABO ---------------- */
        'Malatap Falls'                       => [],
        'Tulis Peak, Mt. Bagacay'             => [],

        /* ---------------- MERCEDES ---------------- */
        'Canimog Island'                      => [],
        'Pebble Beach'                        => [],

        /* ---------------- PARACALE ---------------- */
        'Macolabo Island'                     => [],
        'Gumaus Beach'                        => [],

        /* ---------------- SAN LORENZO RUIZ ---------------- */
        'Nacali Falls'                        => [],
        'Mampili River'                       => [],

        /* ---------------- SAN VICENTE ---------------- */
        'Mananap Falls'                       => [],
        'Mananap Falls ATV Adventure'         => [],

        /* ---------------- SANTA ELENA ---------------- */
        'Busay Falls'                         => [],
        'Del Moro Park'                       => [],

        /* ---------------- TALISAY ---------------- */
        'Mangrove Eco Tourism Park'           => [],
        'St. Francis of Assisi Parish Church' => [],

        /* ---------------- VINZONS ---------------- */
        'Calaguas Island'                     => [],
        'Mt. Panit'                           => [],

    ],
];