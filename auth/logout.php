<?php
/* ===================================================================
   auth/logout.php

   Ends the session and sends the user to the homepage. Because
   $_SESSION is empty afterwards, header.php falls to its else branch
   and the nav shows the sign-in icon again with no "Hi, ..." — the
   greeting disappears on its own, there is nothing to switch off.

   THREE STEPS, NOT ONE. session_destroy() alone leaves the session
   cookie sitting in the browser pointing at a dead session, and on
   some setups the next request revives it. Emptying the array,
   expiring the cookie, then destroying the session closes all three
   doors.
   =================================================================== */

session_start();

/* 1. empty the data */
$_SESSION = [];

/* 2. expire the cookie that identifies the session, using the same
      parameters it was set with */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

/* 3. destroy the session itself */
session_destroy();

header('Location: ../homepage.php');
exit;