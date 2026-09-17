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

   ═══════════════════════════════════════════════════════════════════
   ⚠ READ THIS BEFORE YOU PUBLISH — the state of this draft
   ═══════════════════════════════════════════════════════════════════

   The entries below were drafted from published travel writing,
   TripAdvisor entries and tourism-adjacent blogs, not from the
   tourism office. They are here so the detail sheet stops reading as
   twenty-four blanks. THEY ARE NOT YET VERIFIED.

   Every entry carries a confidence marker in its comment:

     ✅ WELL SOURCED   several independent accounts agree; the route
                       and the general shape are almost certainly
                       right. Confirm the numbers, not the shape.

     ⚠ THIN           one or two accounts, or accounts that disagree.
                       Treat the whole entry as a question for the
                       municipal office.

     ○ STRUCTURAL     nothing specific was findable. What is written
                       is only what is certain from the map — which
                       town it is in, that it is reached by boat, that
                       arrangements are made locally. It is honest and
                       it is thin. Fill it properly when you can.

   ═══════════════════════════════════════════════════════════════════
   ⚠ WHY EVERY 'phone', 'email' AND 'fb' IS STILL EMPTY
   ═══════════════════════════════════════════════════════════════════

     Routes and fares that are a year out of date cost a visitor an
     hour and some pesos. A phone number that is a year out of date
     rings a stranger's handset, forever, in public, at scale. The two
     are not the same kind of wrong, so they are not held to the same
     standard here.

     Numbers found in search are recorded in comments beside the entry
     they belong to, with the year of the source. Ring each one, and
     only then move it up into the array. A single afternoon of calls
     turns this whole file from a draft into a publishable one.

   ═══════════════════════════════════════════════════════════════════

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

   ⚠ A NOTE ON PRICES. Where a price is written below it is worded as
   a range and dated ("around PHP 400, 2024 rates"), never as a flat
   figure. A range that is slightly stale reads as guidance. A flat
   figure that is slightly stale reads as a promise the boatman did
   not make.
   =================================================================== */


/* -------------------------------------------------------------------
   THE FALLBACK CONTACT

   Shown in the booking section of any destination that has no 'book'
   of its own. One real contact everywhere beats a screen of blanks,
   and it is one edit here rather than twenty-three edits below.

   FILL THIS IN FIRST. It is still the highest-value line in the file.

   ⚠ CANDIDATES FOUND, NOT YET CONFIRMED — ring before pasting in:

       (054) 721-3087   listed as the Provincial Tourism Office,
                        Daet, on a 2017 travel page. Oldest and most
                        specific of the numbers found; most likely to
                        be the right desk and most likely to have
                        changed.

       (054) 721-2167   listed against a Daet tourism address on a
                        2012 guide. The same number also appears as a
                        hotel trunkline elsewhere, which is a reason
                        to be careful with it, not a reason to use it.

       pgcamarinesnorte@gmail.com
                        published on the provincial government site as
                        the general address. Not the tourism desk, but
                        it is a live provincial address and it will
                        reach someone who can forward.

       camsnorte.com    the official provincial site — the safest
                        thing to link if the phone cannot be confirmed
                        in time.

   The 'org' and 'note' below are safe to ship as they are. They
   promise nothing and they are true.
   ------------------------------------------------------------------- */
$PROVINCIAL_CONTACT = [
    'org'   => 'Provincial Tourism Office, Camarines Norte',
    'phone' => '',                 /* ← the office trunkline */
    'email' => '',                 /* ← the office address   */
    'fb'    => '',                 /* ← the office page      */
    'note'  => 'Rates, boat arrangements and guides for this destination are still being confirmed. The provincial office can point you to the operator, and the municipal tourism desk in the town itself is usually the faster call.',
];


return [

    'fallback' => $PROVINCIAL_CONTACT,

    'places' => [

        /* ================= BASUD ================= */

        /* ○ STRUCTURAL. Nothing specific published. Basud is the
           first town north of Daet on the coast road, which is the
           one thing worth telling a visitor. */
        'Taba Taba Beach Resort' => [
            'how' => [
                'Basud is the first coastal town north of Daet on the national road, roughly half an hour by jeep or van from the Daet terminal.',
                'Ask to be dropped at Basud town proper; local tricycles run the last stretch to the shoreline resorts.',
            ],
            'eat' => [
                'Basud town proper has the nearest carinderias and a public market — buy anything you want on the beach before you leave town.',
            ],
        ],

        /* ○ STRUCTURAL. Same. */
        'La Maestra Campsite and Resort' => [
            'how' => [
                'In Basud, about half an hour north of Daet by jeep or van.',
                'From Basud town proper, take a tricycle to the campsite.',
            ],
            'eat' => [
                'Stock up in Basud town proper. Campsites this size rarely have a kitchen running outside peak weekends.',
            ],
            'book' => [
                'org'   => 'Basud Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Ring ahead if you intend to pitch a tent overnight — capacity and whether the site is open at all vary by season.',
            ],
        ],


        /* ================= CAPALONGA ================= */

        /* ⚠ THIN. The shrine is well known and appears on every
           provincial list; the practical detail is not published.
           The feast is the thing to get right — confirm the date
           with the parish before writing it in. */
        'Shrine of the Black Nazarene' => [
            'how' => [
                'Capalonga is at the far western end of the province. From Daet the trip is long — allow most of a morning by bus or van.',
                'The shrine is in the town proper, walking distance from where the buses stop.',
            ],
            'eat' => [
                'Eateries around the town plaza and the public market. Capalonga is a small town; eat when you arrive rather than assuming something will be open later.',
            ],
            'book' => [
                'org'   => 'Diocesan Shrine of Jesus the Black Nazarene, Capalonga',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'The shrine is open to visitors outside mass times. Pilgrim numbers rise sharply around the feast — ask the parish office for this year\'s dates and for mass schedules before you plan around them.',
            ],
        ],

        /* ○ STRUCTURAL. A small island off Capalonga with no
           published access detail at all. Everything below is
           inference from the coastline and is written to say so. */
        'Pulong Guijanlo' => [
            'how' => [
                'Reached only by boat from the Capalonga mainland — there is no public schedule, so a boat is hired at the shore.',
                'Get to Capalonga town first; the crossing is arranged there, on the day.',
            ],
            'book' => [
                'org'   => 'Capalonga Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Boat hire here is informal and weather-dependent. Ask at the municipal office first rather than at the shore — they will know who is sailing and whether it is safe to.',
            ],
        ],


        /* ================= DAET ================= */

        /* ✅ WELL SOURCED. Bagasbas is the most written-about spot in
           the province after Calaguas and the accounts agree closely.

           On the prices: board rental has been quoted at PHP 200/hr
           consistently from 2010 through 2024, and a lesson with
           board at PHP 400/hr from 2012 through 2024. Two numbers
           that have not moved in a decade of blog posts are about as
           safe as an unverified price gets — which is why they are
           written as "around" and dated.

           ⚠ CANDIDATE CONTACT, NOT CONFIRMED: several accounts name
           "Bagasbas Surfers Club Inc." and an instructor called
           Mocha, found at the shack across from the beach. That is a
           person, not an organisation line, so it is deliberately NOT
           written into the entry — ring the Daet municipal tourism
           desk and ask them for the association's own number instead.

           The old SAMPLE markers are gone. Verify and this one ships. */
        'Bagasbas Beach' => [
            'how' => [
                'Bagasbas is about 4km from Daet town centre, on the Pacific side.',
                'Tricycles run there from the Daet public market and terminal — a short ride, and the standard route for anyone without a car.',
                'The beach road is paved the whole way and parking is at the boardwalk end.',
            ],
            'eat' => [
                'The food stalls along the boardwalk open in the late afternoon, once the heat drops and the boardwalk fills.',
                'Several small cafes and eateries face the break on the main beach road, open through the day.',
                'For pasalubong, Daet is known for pili — pili tarts, pili rolls and pandecilios are sold in town rather than at the beach.',
            ],
            'book' => [
                'org'      => 'Surf instructors\' association at Bagasbas / Daet Municipal Tourism Office',
                'phone'    => '',
                'email'    => '',
                'fb'       => '',
                'note'     => 'Board rental and lessons are arranged on the beach itself — no advance booking needed. Instructors work from the shacks and the park across from the shore. Bring cash; nothing here takes cards. The swell holds close to year-round, with the strongest wind season running roughly November to March.',
                'packages' => [
                    ['name' => 'Board rental', 'detail' => 'Per hour, board only',                'price' => 'Around PHP 200/hr (2024 rates)'],
                    ['name' => 'Surf lesson',  'detail' => 'Instructor and board, one hour',      'price' => 'Around PHP 400/hr (2024 rates)'],
                    ['name' => 'Entrance',     'detail' => 'Public beach',                        'price' => 'Free'],
                ],
            ],
        ],

        /* ✅ WELL SOURCED for location and history, which is all this
           one needs — it is a monument in a town plaza, not a trip.
           The 1898 date is in destinations-data.php already and is
           solid. */
        'First Rizal Monument' => [
            'how' => [
                'In the centre of Daet, at the plaza on the way into town — walking distance from the public market and the bus terminal.',
                'Any tricycle in town will know it. There is no gate and no fee.',
            ],
            'eat' => [
                'The Daet public market and the eateries around the plaza are a few minutes\' walk.',
                'Pili sweets, tikoy and pineapple tarts are the Daet pasalubong, sold from stalls in the town centre.',
            ],
            'book' => [
                'org'   => 'Daet Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Open ground, no booking and no entrance fee. Ring the municipal office only if you want a guide or are bringing a group.',
            ],
        ],


        /* ================= JOSE PANGANIBAN ================= */

        /* ✅ WELL SOURCED. Multiple recent accounts agree on the
           route, the climb and a small entrance fee. Note the
           disagreement on the fee — PHP 20 in one visitor account,
           PHP 30 in a 2024 listing — so it is written as a range.

           ⚠ The 5pm transport note is from a visitor account and is
           the single most useful line in this entry. Confirm it, but
           do not drop it: it is exactly the sort of thing that turns
           a good afternoon into a bad evening. */
        'Turayog View Deck' => [
            'how' => [
                'From Daet, take a bus or van to Jose Panganiban — around an hour.',
                'From the town, a tricycle or habal-habal goes up to Brgy. Luklukan Norte.',
                'The deck is at the top of a flight of steps from the road — a short, steep climb rather than a hike.',
            ],
            'eat' => [
                'There is a small store at the foot of the climb selling water and snacks. Buy before you go up.',
                'Full meals are back down in Jose Panganiban town proper.',
            ],
            'book' => [
                'org'      => 'Jose Panganiban Municipal Tourism Office',
                'phone'    => '',
                'email'    => '',
                'fb'       => '',
                'note'     => 'Go early morning or late afternoon — the view is the whole point and midday flattens it. ⚠ Public transport back down thins out sharply after about 5pm; if you are commuting, plan your way back before you go up, or arrange a habal-habal to wait.',
                'packages' => [
                    ['name' => 'Entrance', 'detail' => 'Per person', 'price' => 'Around PHP 20–30 (2024 rates)'],
                ],
            ],
        ],

        /* ⚠ THIN. The pink sand and the lighthouse are attested in a
           national travel feature; the boat arrangement is not
           documented anywhere findable. Also called Tailon Island —
           worth knowing when you ring to ask. */
        'Parola Island' => [
            'how' => [
                'Reached by boat from the Jose Panganiban coast — roughly half an hour\'s crossing.',
                'Get to Jose Panganiban town first, then to the shore; boats are hired rather than scheduled.',
            ],
            'eat' => [
                'Nothing on the island. Bring food and water for however long you intend to stay.',
            ],
            'book' => [
                'org'   => 'Jose Panganiban Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Also known locally as Tailon Island. Boat hire is arranged on the mainland and depends entirely on the sea state — ask the municipal office or the coastguard before committing to a day.',
            ],
        ],


        /* ================= LABO ================= */

        /* ⚠ THIN. Malatap appears on provincial "ready for tourists"
           lists — which means it has some access infrastructure — but
           the actual route is not published. Labo is the largest
           municipality in the province, so "in Labo" is not an
           address; the barangay is what you need from the office. */
        'Malatap Falls' => [
            'how' => [
                'From Daet, take a bus or jeep toward Labo — Labo town proper is under an hour inland.',
                'The falls are off the highway and reached on foot for the last stretch. Ask at the municipal office for the barangay and the current trailhead before you set out.',
            ],
            'eat' => [
                'Eateries in Labo town proper. There is nothing at the falls — carry water and food in, and carry your rubbish out.',
            ],
            'book' => [
                'org'   => 'Labo Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Named by the province as one of the falls that is set up to receive visitors, but conditions on the trail change with the rain. Ring ahead in the wet season.',
            ],
        ],

        /* ○ STRUCTURAL. A guided climb with no published route.
           Everything here is standard hill-walking advice, written
           plainly so it does not pretend to be local knowledge. */
        'Tulis Peak, Mt. Bagacay' => [
            'how' => [
                'The jump-off is in Labo, inland from Daet. Guides and the current trailhead are arranged through the municipal office rather than found on the day.',
                'A day climb: up and back down inside a morning for most groups.',
            ],
            'eat' => [
                'Nothing on the mountain. Eat in Labo town proper and carry water for the climb.',
            ],
            'book' => [
                'org'   => 'Labo Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Arrange a guide before the day you intend to climb. Start early — the heat, not the gradient, is what makes this one hard.',
            ],
        ],


        /* ================= MERCEDES ================= */

        /* ✅ WELL SOURCED. Canimog is part of the Siete Pecados /
           Mercedes Group of Islands circuit and there is a decade of
           consistent reporting on it. The lighthouse and the
           crocodile-shape nickname are attested repeatedly.

           ⚠ Boat rates vary widely by source and by year — PHP 2,000
           to PHP 6,000 depending on boat size, group and whether it
           is overnight. Written as a wide range on purpose.

           ⚠ CANDIDATES FOUND, NOT CONFIRMED — these two are the most
           promising numbers in the whole file, because they are for a
           municipal office rather than a person:

               (054) 444-1261            Mercedes tourism office, 2016 source
               discovermercedes@yahoo.com  same source

           Ring, and if they answer, move them into 'book' below. */
        'Canimog Island' => [
            'how' => [
                'From the Daet terminal, take a jeep or tricycle to Mercedes — ten to fifteen minutes.',
                'From Mercedes town, a tricycle runs to the fish port, which is the jump-off for the whole island circuit.',
                'Boats are hired at the port. Canimog is usually the first stop on the route out of Mercedes port.',
            ],
            'eat' => [
                'Mercedes is a fishing town and the port market is the reason to arrive hungry — buy and eat before you sail.',
                'Nothing is sold on the island. Bring your own food, and more water than you think.',
            ],
            'book' => [
                'org'      => 'Mercedes Municipal Tourism Office',
                'phone'    => '',
                'email'    => '',
                'fb'       => '',
                'note'     => 'Boats are chartered, not ticketed — the price is for the boat, so a bigger group is a cheaper day. Arrange through the municipal tourism office rather than at the pier if you can; they hold the accredited operators. Crossings are cancelled on rough water without much notice.',
                'packages' => [
                    ['name' => 'Island hopping, day',  'detail' => 'Boat charter, small group',        'price' => 'Around PHP 2,000–3,500 per boat'],
                    ['name' => 'Island hopping, larger group', 'detail' => 'Boat charter, up to ~15',  'price' => 'Around PHP 6,000 per boat'],
                    ['name' => 'Overnight charter',   'detail' => 'Boat held overnight',              'price' => 'Around PHP 3,500 and up'],
                ],
            ],
        ],

        /* ○ STRUCTURAL. Almost nothing published specifically. What
           is certain is the coast and the town it belongs to. */
        'Pebble Beach' => [
            'how' => [
                'On the Mercedes coast. From the Daet terminal, a jeep or tricycle reaches Mercedes in ten to fifteen minutes; local tricycles cover the last stretch to the shore.',
                'Ask for it by name in Mercedes — it is a local spot rather than a signposted one.',
            ],
            'eat' => [
                'Mercedes town and the port market are the nearest food, and worth the stop for the fish.',
            ],
        ],


        /* ================= PARACALE ================= */

        /* ○ STRUCTURAL. Macolabo is named on provincial lists but has
           no published access detail. Paracale itself is well
           documented as a jump-off port, which is the useful part. */
        'Macolabo Island' => [
            'how' => [
                'From Daet, a van or bus reaches Paracale in about an hour.',
                'From Paracale town, a tricycle runs to the fish port, and the crossing is arranged there.',
            ],
            'eat' => [
                'Eat in Paracale town before you sail. Nothing is sold on the island.',
            ],
            'book' => [
                'org'   => 'Paracale Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Paracale port also serves boats to Calaguas, so the boatmen here are used to visitors — but Macolabo is the quieter crossing and worth confirming a day ahead.',
            ],
        ],

        /* ⚠ THIN, but the gold panning is attested in recent travel
           writing and is the most distinctive thing on this entry.
           ALM Pabirik Resort is named in a 2024 account as a lunch
           stop along the Gumaus stretch — named here because it is a
           business with a public name, not a private number. */
        'Gumaus Beach' => [
            'how' => [
                'From Daet, take a van or bus to Paracale — about an hour.',
                'From Paracale town, a tricycle runs out to the Gumaus stretch.',
            ],
            'eat' => [
                'There are resorts along the Gumaus stretch that serve meals to day visitors — a boodle-style lunch is the usual arrangement for groups.',
                'Paracale town has the market and the ordinary eateries.',
            ],
            'book' => [
                'org'   => 'Paracale Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Paracale has worked gold for centuries and small-scale panners still work the sand at Gumaus. Some will show visitors the process — ask through the municipal office rather than approaching people at work. The gold museum inside the municipal building is a short stop worth pairing with the beach.',
            ],
        ],


        /* ================= SAN LORENZO RUIZ ================= */

        /* ○ STRUCTURAL. Nothing findable beyond the map. */
        'Nacali Falls' => [
            'how' => [
                'San Lorenzo Ruiz is inland and south of Daet. Take a jeep from the Daet terminal, then arrange the last stretch locally.',
                'The falls are a short way off the road, on foot. Ask at the municipal office for the barangay and for a guide.',
            ],
            'eat' => [
                'Nothing at the falls. Eat in the town proper and carry water in.',
            ],
            'book' => [
                'org'   => 'San Lorenzo Ruiz Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Upland falls in this province run high and fast after rain. Ask about conditions before you travel out, not when you arrive.',
            ],
        ],

        /* ○ STRUCTURAL. */
        'Mampili River' => [
            'how' => [
                'In San Lorenzo Ruiz, inland from Daet. A jeep from the Daet terminal, then a tricycle to the riverside.',
                'This is a local swimming spot rather than a resort — there is no gate and no set arrival point.',
            ],
            'eat' => [
                'Bring your own. The town proper has the nearest carinderias.',
            ],
        ],


        /* ================= SAN VICENTE ================= */

        /* ⚠ THIN AND CONTRADICTORY — read this before publishing.

           Published accounts of Mananap Falls do not agree with each
           other, and the disagreement is not minor:

             Account A  a resort with a hanging bridge, tiered pools,
                        an entrance fee of about PHP 15, and cottages
                        for hire. (TripAdvisor, c.2018)

             Account B  a 4km walk from Brgy. Fabrica taking 1.5 to 3
                        hours, permission and a trail guide obtained
                        from the municipal hall, forest wardens at a
                        gate house. (local accounts, c.2016)

             Account C  a jeep to San Vicente then a tricycle to Brgy.
                        San Jose, free entry, guide fee by donation.
                        (2025)

           These may be three access points to the same river system,
           or the site may have been developed between the accounts.
           EITHER WAY, PUBLISHING ONE OF THEM AS FACT SENDS SOMEBODY
           ON A THREE-HOUR WALK THEY DID NOT AGREE TO.

           The entry below therefore describes only what all three
           accounts share, and says plainly that the approach must be
           settled at the municipal hall. Replace it wholesale once
           the office tells you which is current. */
        'Mananap Falls' => [
            'how' => [
                'From the Daet terminal, take a jeep or bus to San Vicente — roughly 45 minutes.',
                '⚠ There is more than one way in, and they are not equivalent: one is a short tricycle ride and a walk, another is a trek of several hours from the trailhead. Settle which one you are doing at the San Vicente municipal hall before you go — they also arrange the trail guide.',
                'Go in the morning. Whichever route you take, you want to be out of the forest well before dark.',
            ],
            'eat' => [
                'Eat in San Vicente town proper. Carry water and food to the falls — there is no reliable food once you are on the trail.',
            ],
            'book' => [
                'org'   => 'San Vicente Municipal Tourism Office / Municipal Hall',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Permission and a trail guide are arranged at the municipal hall. Reported charges range from a small entrance fee to a guide fee shared across the group — ask when you register rather than assuming. Do not attempt the longer trail unguided.',
            ],
        ],

        /* ○ STRUCTURAL. Operator-run, so the operator is the answer.
           No published rates and no named operator that could be
           confirmed — deliberately left unnamed rather than guessed. */
        'Mananap Falls ATV Adventure' => [
            'how' => [
                'Book before you travel. The ride is run by local operators on the trails around Mananap, and machines are not kept idle for walk-ins.',
                'From Daet, take a jeep or bus to San Vicente — the operator will tell you where to meet.',
            ],
            'book' => [
                'org'   => 'San Vicente Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Ask the municipal office for the accredited operators — this is the sort of activity where accreditation matters. Confirm what the rate covers: machine, fuel, guide and helmet are not always the same line item. Trails are worse after rain and rides are sometimes cancelled outright.',
            ],
        ],


        /* ================= SANTA ELENA ================= */

        /* ⚠ THIN but the useful detail is attested in a 2024 account:
           a 15-minute hike from the highway, swimming pools, and huts
           for rent. That is a genuinely visitor-ready site and worth
           saying so. */
        'Busay Falls' => [
            'how' => [
                'Santa Elena is at the far northern edge of the province — the longest drive from Daet of anything on this map. Allow most of a morning.',
                'The falls are a short hike from the highway, reported at around fifteen minutes on foot.',
            ],
            'eat' => [
                'Bring food. Huts at the site can be rented for the day, which is the usual arrangement for groups eating there.',
                'Santa Elena town proper has the nearest shops — buy on the way in, not on the way out.',
            ],
            'book' => [
                'org'      => 'Santa Elena Municipal Tourism Office',
                'phone'    => '',
                'email'    => '',
                'fb'       => '',
                'note'     => 'One of the falls the province lists as ready for visitors — there are built swimming pools alongside the natural ones and huts to rent. Given the drive, ring ahead to confirm it is open before you commit to the day.',
                'packages' => [
                    ['name' => 'Hut rental', 'detail' => 'Day use, per hut', 'price' => 'Ask on site'],
                ],
            ],
        ],

        /* ○ STRUCTURAL. A public park. There is not much to say and
           the entry does not pretend otherwise. */
        'Del Moro Park' => [
            'how' => [
                'In Santa Elena, at the northern edge of the province. It sits on the route north, which is most of the point — it is a place to break the drive.',
                'Open ground in the town, no gate and no fee.',
            ],
            'eat' => [
                'Santa Elena town proper, a short walk. This is a stop, not a destination — eat here and carry on.',
            ],
        ],


        /* ================= TALISAY ================= */

        /* ⚠ THIN. Community-run mangrove boardwalks in this province
           generally charge a small fee and have local guides, and a
           2022 account mentions grilled shells sold at the Talisay
           mangroves — which is the one genuinely specific thing found
           and is worth keeping. */
        'Mangrove Eco Tourism Park' => [
            'how' => [
                'Talisay is a short trip north of Daet on the coast road.',
                'From Talisay town proper, a tricycle runs to the mangrove park. The walk itself is on a boardwalk — flat, and manageable for most people.',
            ],
            'eat' => [
                'Grilled shells gathered from the mangroves are sold at the site — the local thing to eat here, and not sold in many other places.',
                'Talisay town proper has the ordinary eateries.',
            ],
            'book' => [
                'org'   => 'Talisay Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Community-run, so hours follow the people who run it rather than a posted schedule. Early morning is best for birds. Expect a small entrance or guide fee, in cash.',
            ],
        ],

        /* ○ STRUCTURAL. A parish church in a town centre. */
        'St. Francis of Assisi Parish Church' => [
            'how' => [
                'In the centre of Talisay, a short trip north of Daet. Walking distance from where the jeeps stop.',
            ],
            'eat' => [
                'The eateries and market around the town centre are a few minutes\' walk.',
            ],
            'book' => [
                'org'   => 'St. Francis of Assisi Parish, Talisay',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Open outside mass times. Busiest on feast days — ask the parish office for this year\'s dates and mass schedule.',
            ],
        ],


        /* ================= VINZONS ================= */

        /* ✅ WELL SOURCED — the best-documented destination in the
           province, with a decade of consistent travel writing.

           Points the accounts agree on: Vinzons and Paracale are both
           legitimate jump-offs; Vinzons is the more formal one and
           Calaguas falls under its jurisdiction; the crossing is
           roughly one to two hours; the beach is Mahabang Buhangin
           (Halabang Baybay locally) on Tinaga Island; charter is by
           the boat, not by the head.

           ⚠ The public Banocboc boat is reported by several older
           accounts with a departure around 11am and a return around
           6am — a schedule that is fifteen years old in some sources.
           It is described below as existing rather than as running at
           a stated time, on purpose.

           ⚠ The camping line is the important one. Mahabang Buhangin
           has no grid power, no shops and no reliable water. Several
           accounts describe visitors arriving without either. */
        'Calaguas Island' => [
            'how' => [
                'From Daet, take a jeep to Vinzons — around twenty minutes to an hour depending on traffic and what you catch.',
                'From Vinzons town, a tricycle runs to the fish port. Vinzons is the usual jump-off; Paracale, about an hour from Daet, is the alternative and some operators sail from there instead.',
                'From the port, a chartered boat crosses to Mahabang Buhangin on Tinaga Island — roughly one to two hours on the water depending on the boat and the sea.',
                'There is also a public boat to Brgy. Banocboc, from where a smaller boat is hired for the last leg. Cheaper, slower, and on a schedule that changes — confirm it locally before relying on it.',
            ],
            'eat' => [
                'There is nothing to buy on the island. Bring all your food, and bring water — drinking water is the thing visitors most often run short of.',
                'Buy in Daet or Vinzons town. The last real market is on the mainland.',
                'Some operators include meals in a package; confirm exactly which meals, because "food included" and "lunch included" are not the same trip.',
            ],
            'book' => [
                'org'      => 'Vinzons Municipal Tourism Office / accredited Calaguas operators',
                'phone'    => '',
                'email'    => '',
                'fb'       => '',
                'note'     => 'Charter is priced by the boat, so cost per head falls sharply with group size. Agree the fare AND the pickup time before you sail — the return is the part that goes wrong. Sailings are cancelled in rough weather and the coastguard has the final word, so build a spare day into any trip you cannot afford to lose. There is no grid power and no shop on the beach: bring cash, water, a torch and a way to charge nothing at all. Dry season, roughly January to May, is the settled window.',
                'packages' => [
                    ['name' => 'Boat charter, small group', 'detail' => 'Round trip, roughly 5–6 people', 'price' => 'Around PHP 2,500–3,500 per boat'],
                    ['name' => 'Boat charter, larger boat', 'detail' => 'Round trip, bigger group',       'price' => 'Around PHP 4,000 and up per boat'],
                    ['name' => 'Tent pitch',                'detail' => 'Overnight on Mahabang Buhangin', 'price' => 'Ask the operator'],
                ],
            ],
        ],

        /* ○ STRUCTURAL. A guided climb with no published trail
           detail. */
        'Mt. Panit' => [
            'how' => [
                'The jump-off is in Vinzons, around twenty minutes to an hour from Daet by jeep.',
                'Arrange a guide through the municipal office. A day climb — up and back inside a morning for most groups.',
            ],
            'eat' => [
                'Eat in Vinzons town. Nothing on the mountain; carry water.',
            ],
            'book' => [
                'org'   => 'Vinzons Municipal Tourism Office',
                'phone' => '',
                'email' => '',
                'fb'    => '',
                'note'  => 'Arrange the guide ahead of the day. Start early — the views back across the coast toward the Calaguas group are best before the haze builds.',
            ],
        ],

    ],
];