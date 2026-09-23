<?php
/* ===================================================================
   includes/legal-config.php

   FILL THESE IN ONCE. All three legal pages read from here, so the
   owner name, email and date only ever need changing in one place.
   =================================================================== */
$legalSite    = 'Explore Camarines Norte';          // the site's public name
$legalOwner   = 'Lei Andrew Camara';
$legalEmail   = 'leiiiandrewwwcamara@gmail.com';
$legalAddress = '';                                 // optional — leave '' to hide the address line
$legalUpdated = 'September 23, 2026';

/* Who the site is for, and what kind of project it is. */
$legalOffice  = 'Provincial Tourism Office of Camarines Norte';
$legalProject = 'System Integration pre-capstone project';

/* The rest of the team. Lei stays the contact person; these are named
   as the people who helped build the site. */
$legalTeam    = ['Ruvey Chavez', 'Jaspher John Thadeus Azul'];
$legalTeamText = implode(' and ', $legalTeam);

/* Path from the legal pages back to the site root. The pages live in
   /legal, so images and links to the rest of the site need '../'.
   Set to '' if you ever move them back next to homepage.php. */
$legalRoot = '../';