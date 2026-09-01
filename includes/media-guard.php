<?php
/* ===================================================================
   includes/media-guard.php — safe image upload and deletion.

   WHAT THIS FILE DOES NOT DO, because admin/_bootstrap.php already
   does it, and better:

     the admin guard   _bootstrap checks the session AND re-reads the
                       role from the database on every request, so a
                       demoted admin loses access on their next click.
                       A second guard here would be a second thing to
                       keep in step, and the day the two disagree is
                       the day one of them is wrong.
     CSRF              csrfField() and csrfCheck() are already there.
     e()               already there.

   What is left is the part specific to files: proving an uploaded
   file really is an image, storing it under a name we chose, and
   deleting it again without ever letting a path out of the database
   steer where unlink() points.

   THE ATTACK THIS PREVENTS
   ------------------------
   A file upload form is the most dangerous thing on a PHP site. If a
   stranger can put a file called shell.php somewhere your server will
   execute it, they can run any command they like as the web server
   user. Three locks below; the fourth is uploads/.htaccess, which
   assumes all three failed and makes the folder refuse to run PHP at
   all.

   The functions are short on purpose. During your defense you should
   be able to say what each one stops.
   =================================================================== */

/* -------------------------------------------------------------------
   WHERE THE FILES GO
   ------------------------------------------------------------------- */

/** Absolute path to uploads/Gallery-Photo/ on disk. */
function gallery_dir(): string
{
    return dirname(__DIR__) . '/uploads/Gallery-Photo';
}

/** How a browser asks for that folder, from the project root. */
const GALLERY_URL = 'uploads/Gallery-Photo/';

/** Biggest file accepted. Phone photos run 3-6 MB, so 8 is generous. */
const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;

/**
 * Extensions we are willing to write, keyed by the image type PHP
 * detects from the file's own bytes.
 *
 * The extension comes from THIS LIST, never from the name the browser
 * sent. That is the whole trick. A file called "beach.jpg.php" that
 * really is a JPEG gets saved by us as something.jpg; a file called
 * "beach.jpg" that is really PHP is refused before it is saved at
 * all, because whatever it is, it is not in this list.
 */
const ALLOWED_IMAGE_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
];

/** The four crops gallery.css can render, in the order they appear. */
const GALLERY_RATIOS = [
    'ratio-4x3' => 'Wide · 4:3',
    'ratio-3x4' => 'Tall · 3:4',
    'ratio-1x1' => 'Square · 1:1',
    'ratio-4x5' => 'Portrait · 4:5',
];


/* -------------------------------------------------------------------
   IS THIS FILE REALLY AN IMAGE?
   ------------------------------------------------------------------- */

/**
 * Validate an uploaded file and, if it is sound, save it under a name
 * we invented. Returns the new bare filename.
 *
 * Throws RuntimeException with a message written for whoever is using
 * the panel rather than for a developer — the caller hands it
 * straight to flash().
 *
 * NOTE what is deliberately NOT trusted:
 *   $_FILES[..]['name']  chosen by whoever uploaded. Used for nothing.
 *   $_FILES[..]['type']  sent by the browser, trivially faked. Never
 *                        read.
 *   $_FILES[..]['size']  the real size on disk is used instead.
 *
 * Two things are trusted: the tmp_name PHP itself created, and what
 * we read out of the file's own bytes.
 */
function save_uploaded_image(array $file, ?string $dir = null): string
{
    /* Defaults to the gallery folder so existing calls keep working;
       admin/destinations.php passes its own. The folder is always
       chosen by the CALLER in code — never from a form field, or the
       whole basename/realpath discipline below would be pointless. */
    $dir = $dir ?? gallery_dir();

    /* --- did it arrive in one piece? --- */
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($err !== UPLOAD_ERR_OK) {
        $says = [
            UPLOAD_ERR_INI_SIZE  => 'That file is larger than the server accepts. Resize it and try again.',
            UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server accepts. Resize it and try again.',
            UPLOAD_ERR_PARTIAL   => 'The upload was cut off. Try again.',
            UPLOAD_ERR_NO_FILE   => 'Choose an image file.',
        ];

        throw new RuntimeException($says[$err] ?? 'The upload failed. Try again.');
    }

    /* --- did PHP itself put it there? ---
       is_uploaded_file is the one check that cannot be talked around.
       It asks PHP whether this exact path came from THIS request's
       upload. A path smuggled in through a form field fails here. */
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('That upload could not be verified.');
    }

    if (filesize($file['tmp_name']) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Images must be under 8 MB. Resize it and try again.');
    }

    /* --- is it an image? ---
       getimagesize reads the file's own header. A PHP script has no
       image header, so it returns false and we stop here. */
    $info = @getimagesize($file['tmp_name']);

    if ($info === false || !isset(ALLOWED_IMAGE_TYPES[$info[2]])) {
        throw new RuntimeException('That is not a JPG, PNG or WebP image.');
    }

    $ext = ALLOWED_IMAGE_TYPES[$info[2]];

    /* --- second opinion ---
       getimagesize can be fooled by a file that is a valid image AND
       valid PHP at the same time. finfo reads the same bytes a
       different way; arranging for both to agree is much harder. */
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('That is not a JPG, PNG or WebP image.');
    }

    /* --- a name of our own choosing ---
       Random, so nothing of the original survives: no spaces, no ../,
       no second extension, no unicode lookalike, and no overwriting
       an existing photo by uploading one with the same name. */
    $name = bin2hex(random_bytes(10)) . '.' . $ext;

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('The photo folder is missing and could not be created.');
    }

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('The image could not be saved. Check the folder permissions.');
    }

    return $name;
}


/* -------------------------------------------------------------------
   DELETING — the half people forget
   ------------------------------------------------------------------- */

/**
 * Delete one file from the gallery folder.
 *
 * The filename comes out of our own database, and is treated as
 * hostile anyway, because one bad INSERT somewhere else in the
 * project is all it would take. basename() throws away any directory
 * part, so "../../config/database.php" becomes "database.php".
 * realpath() then confirms the result really does sit inside the
 * gallery folder before anything is unlinked.
 *
 * Never pass this a filename straight from $_POST. Look the row up by
 * id, then pass what the row says.
 */
function delete_gallery_file(string $filename, ?string $dir = null): void
{
    $dir = $dir ?? gallery_dir();

    $name = basename($filename);

    if ($name === '' || $name === '.' || $name === '..') {
        return;
    }

    $path = realpath($dir . '/' . $name);
    $root = realpath($dir);

    if ($path === false || $root === false) {
        return;                        // already gone; nothing to do
    }

    $prefix = $root . DIRECTORY_SEPARATOR;

    if (strncmp($path, $prefix, strlen($prefix)) !== 0) {
        return;                        // escaped the folder — refuse
    }

    @unlink($path);
}


/* -------------------------------------------------------------------
   SMALL THINGS
   ------------------------------------------------------------------- */

/* _bootstrap.php defines e() for the admin pages, but the public
   gallery.php includes this file without going anywhere near
   _bootstrap. The guard means whichever loads first wins, and neither
   ever redeclares the other. */
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * The public URL for a gallery photo.
 *
 * basename() again, because this value ends up in an attribute and a
 * stored path with ../ in it would happily point at anything.
 * rawurlencode keeps filenames with spaces working; e() makes the
 * result safe to drop into an attribute.
 *
 * ⚠ The return value is ALREADY ESCAPED — the same convention as the
 * $uploadPath helper at the top of homepage.php. Put it in an
 * attribute and nowhere else, and do not escape it twice.
 *
 * $prefix is for the admin pages, which sit one folder down and need
 * '../' in front. Same idea as adminAsset() in _bootstrap.php.
 */
function gallery_url(string $filename, string $prefix = ''): string
{
    return e($prefix . GALLERY_URL . rawurlencode(basename($filename)));
}

/* ===================================================================
   THE LAST LOCK is not in this file — it is uploads/.htaccess, which
   tells Apache never to execute anything in that folder. Copy it in
   or none of the above matters as much as it should.
   =================================================================== */