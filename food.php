<?php
/* ===================================================================
   food.php

   The one row in the Destinations menu that is NOT a filtered view of
   destinations.php. A dish is not a place: it has no coordinates, no
   municipality of its own, and nothing to put a pin on — so it has no
   row in includes/destinations-data.php and nothing to filter to.
   Hence a page.

   It still reads as part of the same site: the same includes/header.php
   and includes/footer.php, the same .page-hero banner as About and
   Destinations, the same card shape as the destination grid. What is
   new here lives in assets/css/food.css and nowhere else — header.php
   loads that automatically because the file is named after the page.

   THE CONTENT BELOW IS A STARTING POINT, NOT RESEARCH. The dishes are
   real and the descriptions are honest, but you live there and I do
   not. Read every line before this goes live, and fix anything a local
   would raise an eyebrow at — particularly the "where" field, which
   names towns rather than specific businesses on purpose (see the note
   above $foods).
   =================================================================== */
$pageTitle = 'Food & Delicacies — Camarines Norte';
$pageDesc  = 'Bicol Express, laing, pinangat, pili and the Formosa pineapple: what to eat in Camarines Norte and where to find it.';

require __DIR__ . '/includes/header.php';

/* ===================================================================
   THE DISHES

   An array, not markup, for the same reason destinations-data.php is
   an array: adding a dish should be one row, not a block of HTML
   copied and edited. Move this to includes/food-data.php if it grows
   past a screen — the page below only needs $foods to exist.

     name   what it is called
     kind   Savoury / Sweet / Produce / Seafood. These are the filter
            chips, built from whatever values appear here, so adding a
            fifth kind adds a fifth chip with no other edit.
     quote  one line, the only voice on the card
     desc   what it actually is, for someone who has never had it
     chips  two or three facts — heat, base ingredient, when to buy
     where  WHICH TOWNS, not which restaurants. A named carinderia
            closes and this page is wrong; a town is still right in ten
            years. Swap in specific places only if you are prepared to
            check them.
     image  uploads/food/<name>.jpg. Missing files degrade to the
            graded panel behind them rather than a broken-image icon —
            same .gradient-fill trick the destination cards use — so
            the page is presentable before you have taken a single
            photograph.
   =================================================================== */
$foods = [
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

/* ===================================================================
   THE FILTER

   ?kind= only, and built from the data rather than typed out, so the
   chips can never list a category that has nothing in it — the bug
   that had the Destinations menu pointing at ?cat=islands when no such
   category existed.

   Same shape as destinations.php: a real query parameter, so a
   filtered view is a URL you can send someone, and a plain <a href>
   that works with JavaScript off.
   =================================================================== */
$kind = isset($_GET['kind']) ? trim($_GET['kind']) : '';

$kindCounts = [];
foreach ($foods as $f) {
    $kindCounts[$f['kind']] = ($kindCounts[$f['kind']] ?? 0) + 1;
}

$shown = array_values(array_filter($foods, function ($f) use ($kind) {
    return $kind === '' || strcasecmp($f['kind'], $kind) === 0;
}));

/* #foodList for the same reason the Destinations menu links carry
   #destFilters: picking a chip is a full page load, and without the
   fragment every click would drop you back at the top of the banner. */
function foodUrl($kind) {
    return 'food.php' . ($kind !== '' ? '?kind=' . rawurlencode($kind) : '') . '#foodList';
}
?>

<!-- ===================================================================
     THE BANNER

     The shared .page-hero from section 15 of assets/css/base.css — the
     same component Destinations and About use, so this page is the
     same height and the same shape as the rest of the site rather than
     something maintained on its own.

     Same uploads/bg.mp4 as the other pages, so it is almost certainly
     cached by the time anyone reaches this page. Swap the poster for a
     food photograph when you have one worth using; it is what shows if
     the video is still loading or if the browser refuses autoplay.
     =================================================================== -->
<header class="page-hero">
  <video class="photo-layer"
         src="uploads/bg.mp4"
         poster="uploads/dest-banner.jpg"
         autoplay muted loop playsinline
         preload="metadata"
         disablepictureinpicture
         disableremoteplayback></video>

  <div class="page-hero__scrim"></div>

  <div class="wrap page-hero__inner">
    <span class="page-hero__eyebrow">Food &amp; Delicacies</span>
    <h1 class="font-display page-hero__title">Coconut milk, chilli,<br>and a nut worth flying for</h1>
    <p class="page-hero__lead">Bicol cooks with coconut milk the way other regions cook with stock, and Camarines Norte adds its own pineapple and pili to the argument. Here is what to order, and which part of the province to order it in.</p>
  </div>

  <a class="hero-feature__down" href="#foodList" aria-label="Skip to the dishes">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <polyline points="6 10 12 16 18 10"></polyline>
    </svg>
  </a>
</header>

<div class="wrap section food-layout" id="foodList">

  <!-- ---- the filter bar ----
       Deliberately the same object as the one on destinations.php: a
       label, a row of chips, a count underneath. Someone who has used
       that page already knows how this one works.

       The id is on this bar rather than on the grid so a chip click
       lands on the control that made it happen, with the chosen chip
       lit and the results underneath. -->
  <div class="food-filters" id="foodFilters">
    <span class="food-filters__label">What kind</span>
    <ul class="food-chips">
      <li>
        <a class="<?= $kind === '' ? 'is-on' : '' ?>" href="<?= foodUrl('') ?>">
          <span>Everything</span><em><?= count($foods) ?></em>
        </a>
      </li>
      <?php foreach ($kindCounts as $label => $n): ?>
        <li>
          <a class="<?= strcasecmp($label, $kind) === 0 ? 'is-on' : '' ?>"
             href="<?= foodUrl(strcasecmp($label, $kind) === 0 ? '' : $label) ?>">
            <span><?= htmlspecialchars($label) ?></span><em><?= $n ?></em>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="food-result">
    <p class="food-result__count">
      <strong><?= count($shown) ?></strong>
      <?= count($shown) === 1 ? 'dish' : 'dishes' ?>
      <?= $kind !== '' ? '— ' . htmlspecialchars($kind) : 'to look for' ?>
    </p>
    <?php if ($kind !== ''): ?>
      <a class="food-result__clear" href="<?= foodUrl('') ?>">Clear filter</a>
    <?php endif; ?>
  </div>

  <!-- ---- the grid ----
       Same card anatomy as .dest-card: picture with a tag on it, name,
       pull quote, description, facts, then one line along the bottom.
       Two components that look alike because they ARE alike, not
       because the CSS was copied — food.css sets its own sizes and
       leans on base.css for .media, .gradient-fill and .photo-layer. -->
  <div class="food-grid">
    <?php foreach ($shown as $f): ?>
      <article class="food-card">
        <div class="media food-card__media">
          <div class="gradient-fill"></div>
          <!-- alt empty ON PURPOSE: the name is in the h3 directly
               below, so alt here would have a screen reader say it
               twice — and while the photos are missing, filled alt
               text renders as stray words across every card. -->
          <img class="photo-layer" src="<?= htmlspecialchars($f['image']) ?>" alt="" loading="lazy">
          <span class="food-card__tag"><?= htmlspecialchars($f['kind']) ?></span>
        </div>

        <div class="food-card__body">
          <h3 class="font-display food-card__name"><?= htmlspecialchars($f['name']) ?></h3>

          <?php if (!empty($f['quote'])): ?>
            <p class="food-card__quote"><?= htmlspecialchars($f['quote']) ?></p>
          <?php endif; ?>

          <p class="food-card__desc"><?= htmlspecialchars($f['desc']) ?></p>

          <?php if (!empty($f['chips'])): ?>
            <ul class="food-card__facts">
              <?php foreach ($f['chips'] as $c): ?>
                <li><?= htmlspecialchars($c) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <p class="food-card__where">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M12 2a7.4 7.4 0 0 0-7.4 7.4C4.6 15 12 22.5 12 22.5s7.4-7.5 7.4-13.1A7.4 7.4 0 0 0 12 2zm0 10.1a2.7 2.7 0 1 1 0-5.4 2.7 2.7 0 0 1 0 5.4z"></path>
            </svg>
            <?= htmlspecialchars($f['where']) ?>
          </p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <!-- ---- the closing note ----
       The page ends by handing the visitor back to the rest of the
       site. Eating is something you do between destinations, not
       instead of them, and the link is a real filtered URL rather than
       a vague "explore more". -->
  <aside class="food-note">
    <h2 class="font-display food-note__title">Where to eat it</h2>
    <p class="food-note__body">
      There is no restaurant district here. The best version of any of these
      is usually a carinderia with four tables, or somebody's kitchen — ask
      at your accommodation and you will be pointed somewhere better than
      anything a list could name. What travels home is pili and dried fish;
      buy those at the public market in Daet rather than at the terminal.
    </p>
    <a class="food-note__link" href="destinations.php#destFilters">
      <span>Find somewhere to go with it</span>
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <line x1="4" y1="12" x2="19" y2="12"></line>
        <polyline points="13 6 19 12 13 18"></polyline>
      </svg>
    </a>
  </aside>

</div>

<!-- Same correction as destinations.php, and for the same reason: the
     browser acts on #foodList while the banner video and the lazy card
     photographs are still loading, so everything above the filter bar
     is still growing when it jumps and the landing comes out short.
     Scrolling again on load, when the heights are final, fixes it.

     Only when the URL actually carries a fragment, so a plain visit to
     food.php is never scrolled anywhere. -->
<script>
(function () {
  var hash = window.location.hash;
  if (hash !== '#foodList' && hash !== '#foodFilters') return;

  window.addEventListener('load', function () {
    var target = document.querySelector(hash);
    if (!target) return;

    /* one frame later: 'load' fires before the browser has laid out the
       last of what it just finished loading. */
    requestAnimationFrame(function () {
      target.scrollIntoView({ behavior: 'auto', block: 'start' });
    });
  });
}());
</script>
<?php require __DIR__ . '/includes/bud-widget.php'; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>