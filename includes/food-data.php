<?php
/* ===================================================================
   includes/food-data.php

   THE DISHES — moved out of food.php so more than one thing can read
   them.

   food.php still owns how they look; this file owns what they are.
   The array is exactly as it was, keys and order unchanged, so that
   page needs one line altered and nothing else.

   IT IS READ TWICE:
     food.php              draws the cards
     api/bud-context.php   grounds Bud, so the assistant can answer
                           "what should I eat" from the same eight
                           dishes the page shows

   ⚠ SAME CAVEAT AS food.php CARRIED. This is a starting point, not
   research. The dishes are real and the descriptions are honest, but
   the 'where' field names towns rather than businesses on purpose,
   and nobody local has checked these lines yet. Bud is told to treat
   them as description rather than as official recommendation.
   =================================================================== */

return [
    [
        'name'  => 'Bicol Express',
        'kind'  => 'Savoury',
        'quote' => 'The one everyone means by "Bicolano food".',
        'desc'  => 'Pork simmered down in coconut milk with shrimp paste and a serious quantity of long chillies. Rich rather than sharp — the coconut carries the heat instead of fighting it. Ordered with plain rice, always.',
        'chips' => ['Hot', 'Coconut milk', 'Pork'],
        'where' => 'Every town. Daet carinderias do it by the tray.',
        'image' => 'uploads/food/Bicol-Express.webp',
    ],
    [
        'name'  => 'Laing',
        'kind'  => 'Savoury',
        'quote' => 'Dried taro leaves, coconut milk, patience.',
        'desc'  => 'Dried gabi leaves cooked slowly in coconut milk until they collapse into something dark and silky. Left alone while it cooks — stirring early turns it itchy, which is the one thing every household here knows and no recipe abroad mentions.',
        'chips' => ['Mild to hot', 'Taro leaf', 'Vegetarian if asked'],
        'where' => 'Province-wide, and sold frozen to take home.',
        'image' => 'uploads/food/Laing.jpg',
    ],
    [
        'name'  => 'Pinangat',
        'kind'  => 'Savoury',
        'quote' => 'Laing\'s tidier relative, tied in a parcel.',
        'desc'  => 'The same taro leaves, but wrapped around a filling and bound with string before they go into the coconut milk, so each one comes out as a parcel rather than a mass. Fish, pork or shrimp inside depending on who made it.',
        'chips' => ['Medium heat', 'Wrapped', 'Sold by the piece'],
        'where' => 'Market stalls, and roadside stops on the way inland.',
        'image' => 'uploads/food/Pinangat.jpg',
    ],
    [
        'name'  => 'Kinunot',
        'kind'  => 'Seafood',
        'quote' => 'Flaked fish, coconut, malunggay.',
        'desc'  => 'Fish poached, flaked fine, then finished in coconut milk with malunggay leaves and chilli. Lighter than it sounds and the one to order if the pork dishes are starting to add up.',
        'chips' => ['Mild', 'Coconut milk', 'Fish'],
        'where' => 'Coastal towns — Mercedes, Vinzons, Talisay.',
        'image' => 'uploads/food/Kinunot.webp',
    ],
    [
        'name'  => 'Sinantolan',
        'kind'  => 'Savoury',
        'quote' => 'Grated santol, and unlike anything else on this list.',
        'desc'  => 'Santol fruit grated coarse and cooked with coconut milk, shrimp paste and chilli. Sour, salty and rich all at once. A side dish rather than a plate of its own, and the one visitors never see coming.',
        'chips' => ['Sour and hot', 'Santol fruit', 'Side dish'],
        'where' => 'Home kitchens and the better carinderias.',
        'image' => 'uploads/food/Sinantolan.jpg',
    ],
    [
        'name'  => 'Pili Nuts',
        'kind'  => 'Sweet',
        'quote' => 'The pasalubong that actually gets eaten.',
        'desc'  => 'A native nut, buttery and softer than an almond, sold roasted and salted, glazed in sugar, or baked into tarts and mazapan. Bicol grows most of the world\'s supply and it barely leaves the region.',
        'chips' => ['Sweet or salted', 'Native nut', 'Travels well'],
        'where' => 'Pasalubong shops in Daet, and the public market.',
        'image' => 'uploads/food/Pili-Nuts.jpg',
    ],
    [
        'name'  => 'Formosa Pineapple',
        'kind'  => 'Produce',
        'quote' => 'Camarines Norte\'s own, and worth the detour.',
        'desc'  => 'Grown inland around Labo and Basud, sweeter and less acidic than the pineapple sold everywhere else — sweet enough to eat without salt. Sold whole from roadside stands, and turned into jam, juice and dried rings.',
        'chips' => ['Sweet', 'Grown in Labo', 'Roadside stands'],
        'where' => 'The Labo and Basud stretch of the national road.',
        'image' => 'uploads/food/Pineapple.jpg',
    ],
    [
        'name'  => 'Mercedes Seafood',
        'kind'  => 'Seafood',
        'quote' => 'Off the boat, onto the grill, done.',
        'desc'  => 'Mercedes runs one of the largest fish ports on this coast. Squid, tuna, tamban and whatever came in that morning, plus dried and smoked fish by the kilo to take home. Earliest is best — the port works before dawn.',
        'chips' => ['Fresh daily', 'Fish port', 'Go early'],
        'where' => 'Mercedes town proper, at the port.',
        'image' => 'uploads/food/Seafood.png',
    ],
];