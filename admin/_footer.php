</main>
</div>

<?php
/* The shared interface layer: the toast that confirms a save, the
   drawer controller, and the confirmation dialog. Required here at
   the end of every admin page for the same reason _bootstrap.php is
   required at the top of every one — so no individual page has to
   remember it, and a page that forgets breaks loudly rather than
   quietly losing a feature. */
require __DIR__ . '/_ui.php';
?>

</body>
</html>