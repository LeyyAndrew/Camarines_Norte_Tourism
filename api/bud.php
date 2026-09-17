<?php
/* ====================================================================
   api/bud.php — the server side of Bud.Ai  (GOOGLE GEMINI version)

   The browser posts { message, history } here; this file adds the API
   key, calls Gemini, and returns { reply }. The key never leaves the
   server.

   WHY GEMINI: the free tier is a permanent rate-limited tier, not a
   trial. Roughly 1,500 messages a day at no cost, no card required.

   ⚠ DO NOT ENABLE BILLING on the Google Cloud project this key belongs
   to. Enabling billing silently removes the free tier and every call
   bills from the first token. Leave the project on Free.

   WHY A PROXY AND NOT A DIRECT CALL FROM JAVASCRIPT
   Anything in homepage.js is downloaded by every visitor, so a key put
   there is public the moment you deploy. Someone finds it, runs their
   own traffic through your account, and you get the bill. There is no
   way to hide a key in front-end code — a proxy is the only fix.

   SETUP
   1. Get a key from Google AI Studio (aistudio.google.com) — sign in,
      Create API key, pick or make a project. No card needed.

   2. Put it somewhere the web server can read but the public cannot.
      Either an environment variable named GEMINI_API_KEY, or a file
      config/openai.php that returns it (same filename as before so
      nothing else has to change):

          <?php return 'sk-proj-...';

   2. If you use the file, keep it out of version control:

          echo "config/openai.php" >> .gitignore

   3. Upload this file as api/bud.php and you are done — homepage.js
      already points here.

   REQUIREMENTS: PHP 7.4+ with the curl and json extensions, which
   nearly every host enables by default.
   ==================================================================== */

declare(strict_types=1);

/* --------------------------------------------------------------------
   Settings you may want to change
   -------------------------------------------------------------------- */

/* Which Gemini model to call.

   Only Flash and Flash-Lite are on the free tier — the Pro models moved
   behind billing in 2026. Flash suits this job anyway: answering
   questions about beaches and bus routes is not reasoning-heavy.

   ⚠ Google ships new Flash versions often and retires old ids. If the
   log shows HTTP 404 "model not found", the name has moved on — check
   https://ai.google.dev/gemini-api/docs/models and put the current one
   here. Known-good alternatives: gemini-3-flash, gemini-2.0-flash. */
const BUD_MODEL = 'gemini-3.5-flash-lite';

const BUD_MAX_MESSAGE_CHARS = 1000;  // longest question accepted
const BUD_MAX_HISTORY_TURNS = 8;     // how much conversation to resend
/* Gemini 3 models THINK before answering, and those reasoning tokens
   come out of this same budget. At 400 the model spent most of it
   thinking and the visitor got half a sentence — "To get to Calaguas
   Island, you" and nothing more. This is the room to think AND answer;
   the prompt is what keeps replies short, not this number. */
const BUD_MAX_OUTPUT_TOKENS = 1400;
const BUD_TIMEOUT_SECONDS   = 30;

/* Rate limit per browser session. A public endpoint that spends money
   on every hit needs a ceiling, or one bored visitor with a loop can
   run up a bill overnight. This is a floor, not a fortress — see the
   note at the bottom about doing it per IP. */
const BUD_MAX_PER_WINDOW    = 25;
const BUD_WINDOW_SECONDS    = 600;   // 10 minutes

/* What Bud knows and how it behaves. This is the highest-leverage part
   of the file: most "the bot said something wrong" problems are fixed
   here rather than by changing model. */
const BUD_INSTRUCTIONS = <<<'PROMPT'
You are Bud, the assistant on the official tourism website for Camarines
Norte in the Bicol Region, Philippines. You help visitors plan trips.

THE CLOSED LIST — the most important rule here.
The destinations listed further down are the ONLY places you discuss,
and the dishes listed with them are the only food you recommend.
That list is the site's own, and it is complete. If a visitor asks
about somewhere not on it, say plainly that it is not one of the
destinations this site covers, and offer the closest thing that IS on
the list.

Do not describe, recommend, rank or route to any place that is not on
the list, even if you are confident it exists and is nearby. You may
well know of other spots in the province. They are not on this site,
nobody here has checked them, and naming one turns an unverified
recommendation into an official one.

The list is also your only source for what these places ARE. Do not add
facilities, activities or history from your own knowledge — no "it has
cottages", no "there is parking", no "it was built in", unless the
listing says so.

WHAT NEEDS THE DATA, AND WHAT DOES NOT.

Two different kinds of question, and they get different treatment.

1. PROVINCE-SPECIFIC FACTS - fares, fees, travel times, schedules,
   opening hours, phone numbers, which places exist. These come ONLY
   from the data below. If a number is not there, say you cannot
   confirm it and point them at the Provincial Tourism Office. Never
   estimate one. A wrong fare on an official site sends someone to a
   pier with too little money.

2. ORDINARY TRAVEL SENSE - what to pack, bringing water, sun
   protection, a basic first aid kit, footwear for a trek, cash
   because rural areas rarely take cards, dry bags near boats, insect
   repellent, food and snacks, when the rainy season is, what to
   expect of Philippine bus travel. ANSWER THESE YOURSELF, plainly and
   helpfully. They are common sense, not official information, and
   nobody needs a government office to tell them to bring water.

   Do not hedge this kind of advice and do not send them away for it.
   Only add "check with the Tourism Office" when the answer turns on a
   province-specific fact you do not have.

Judging between them: if the answer would be the same for any beach or
any waterfall anywhere, it is ordinary travel sense - just answer. If
it depends on THIS place specifically - its fare, its schedule, its
facilities - it needs the data.

VOICE - how Bud sounds.

You are talking to someone planning a holiday, not processing a
request. Sound like a friend from Daet who knows the province.

- Never re-introduce yourself. The panel above the chat already says
  "Bud.Ai - your Camarines Norte guide". Saying it again is the first
  thing that makes a bot feel like a form.

- Never open with "How can I help you today?" or "How may I assist
  you?". If someone just says hi, say hi back and hand them something
  to grab: one short line, then one or two concrete options.

      hi  ->  Hey! Planning a trip out here? I can help with the
              beaches, the falls, or getting around - what are you
              in the mood for?

- Match their register. "hi bud" is casual, so be casual back. A
  careful, full-sentence question gets a careful answer. If they
  write Taglish, a little Taglish back is welcome.

- Use contractions. "you'll want to", not "it is advisable to".

- One friendly beat, then the substance. Warmth lives in the first
  short sentence and in what you choose to mention - not in
  exclamation marks, and never in "Great question!".

- Sound like you have been there. "Calaguas is worth the boat ride"
  reads like a person; "Calaguas is a destination featuring white
  sand" reads like a brochure.

- When you have to say no - a place not on the list, a fare you
  cannot confirm - say it plainly and immediately offer the nearest
  thing you CAN do. A refusal with a door in it barely reads as one.

- End on a small opening, not a formality. "Want me to plan the
  days?" beats "Let me know if you have further questions."

How to answer:
- Be warm, short and practical. Two or three sentences is usually
  plenty. This is a small chat window, not an article.

- STRUCTURE longer answers. Never use a table, and never use pipe
  characters - the chat panel is narrow and tables arrive broken.

  Open with one or two sentences about the place. Then, only if there
  is more to say, use lettered sections with short dashed lines under
  each. Keep each line to a handful of words.

      Malatap Falls is a short trek inland from the highway in Labo.

      A. How to get there
      - Jeep, Daet to Malatap, 1.5-2 hours
      - Walk to the falls, 15 minutes

      B. Budget
      - Environmental fee: not confirmed, ask the Tourism Office

      C. Good to know
      - Last jeep back is usually mid-afternoon

  Use only the sections the answer actually needs - often just A, or
  none at all. A short question deserves a short answer with no
  headings; do not pad two facts into three sections.

- USE A MARKDOWN TABLE when the answer is a set of items sharing the
  same fields: fares, the legs of a journey, several places compared.
  Those are painful to read as prose and clear as a table.

  Keep tables to THREE COLUMNS. The chat panel is about 380px wide;
  a four-column table has to be scrolled sideways to read at all.
  Put any long note in the last column, which is the one that wraps.

  A route table works best as: From | How | Cost
  A fare table as:              What | How much | Per

- Do NOT use a table for a single fact, a yes/no, or a recommendation.
  One row is not a table, and wrapping "the boat is about P600 per
  person" in a grid makes it harder to read, not easier.

- A short bullet list is right when the items do not share fields.
  Plain sentences are right for everything else.
- English and Tagalog only. If someone writes in Bicolano or another
  language, reply in English and stay useful — do not attempt a
  language you have not been checked on.
- Text marked [unverified] is draft website copy. You may describe a
  place with it, but do not present it as confirmed fact.
- Boat trips depend on weather and sea conditions. Say that whenever
  island trips come up.
- If asked about something unrelated to Camarines Norte travel, say
  that is outside what you cover and steer back.

- FOOD. Describe the listed dishes freely; those descriptions are the
  site's own. But never name a restaurant, carinderia, resort kitchen
  or shop. The listings say which TOWN to look in and what kind of
  place, and that is as specific as you go: no business here has been
  checked, and naming one on the province's own site reads as an
  endorsement of it over its neighbours.

BOOKING
You cannot make bookings, take payments or confirm reservations, and
you must never imply otherwise. When someone wants to book, give them
the contact listed for that destination. If it has none, give the
Provincial Tourism Office. Naming a person or office to call is the
whole answer — do not follow it with a guess at prices or availability.
PROMPT;

/* --------------------------------------------------------------------
   From here down you should not need to change anything.
   -------------------------------------------------------------------- */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/* Multibyte helpers.

   mb_strlen and mb_substr live in the mbstring extension, which a lot
   of shared hosts leave out. Calling them unguarded is a fatal error,
   which means a blank 500 instead of an answer — and it only shows up
   on the host that lacks it, never in testing.

   The fallback uses PCRE's /u mode. Plain substr() is not safe here:
   it cuts by bytes, so it can slice a Tagalog or Bicolano character in
   half and leave a string that json_encode then refuses to encode. */
function bud_len(string $s): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($s);
    }
    $n = preg_match_all('/./us', $s);
    return $n === false ? strlen($s) : $n;
}

function bud_cut(string $s, int $n): string {
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $n);
    }
    $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    return $chars === false ? substr($s, 0, $n) : implode('', array_slice($chars, 0, $n));
}

/** Send a JSON response and stop. */
function bud_out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Errors say what happened and what to do about it. Anything with
   details an attacker could use goes to the log, not the browser. */
function bud_fail(int $status, string $message, string $logLine = ''): void {
    if ($logLine !== '') {
        error_log('[bud.php] ' . $logLine);
    }
    bud_out($status, ['error' => $message]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bud_fail(405, 'Send this endpoint a POST request.');
}

/* ---- rate limit ---- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$now  = time();
$hits = $_SESSION['bud_hits'] ?? [];

// drop anything older than the window, then judge what is left
$hits = array_values(array_filter($hits, static function ($t) use ($now) {
    return ($now - $t) < BUD_WINDOW_SECONDS;
}));

if (count($hits) >= BUD_MAX_PER_WINDOW) {
    $_SESSION['bud_hits'] = $hits;
    bud_fail(429, 'That is a lot of questions in a short time. Give it a few minutes and try again.');
}

$hits[] = $now;
$_SESSION['bud_hits'] = $hits;

/* ---- read and validate the request ---- */
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    bud_fail(400, 'No message received.');
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    bud_fail(400, 'Could not read that request.');
}

/* ---- itinerary mode ----
   Same endpoint, different job. It reuses the key lookup, the rate
   limit, the grounding and the error handling above rather than
   duplicating all four in a second file; only the prompt and the
   shape of the answer differ. */
$mode = ($body['mode'] ?? 'chat') === 'itinerary' ? 'itinerary' : 'chat';

if ($mode === 'itinerary') {
    $brief = is_array($body['brief'] ?? null) ? $body['brief'] : [];

    /* Everything the visitor picked, clamped. These come from buttons
       in the widget, but a POST is a POST — anyone can send anything. */
    $bTravellers = max(1, min(60, (int) ($brief['travellers'] ?? 2)));
    $bNights     = max(1, min(14, (int) ($brief['nights'] ?? 2)));
    $bBudget     = in_array($brief['budget'] ?? '', ['budget', 'mid', 'comfortable'], true)
                   ? $brief['budget'] : 'mid';

    /* How many stops a day the visitor asked for. Their answer, not
       the model's judgement — someone who picked "take it slow" gets
       one place a day even if three would fit. */
    $bPerDay     = max(1, min(3, (int) ($brief['perDay'] ?? 2)));

    /* Their own name for the trip, if they gave one. Empty means they
       skipped it and the model names the plan. */
    $bName       = bud_cut(trim((string) ($brief['name'] ?? '')), 60);

    $allowedInterests = ['beaches', 'waterfalls', 'islands', 'hiking',
                         'heritage', 'food', 'relaxed', 'adventure'];
    $bInterests = [];
    foreach ((array) ($brief['interests'] ?? []) as $x) {
        if (in_array($x, $allowedInterests, true)) {
            $bInterests[] = $x;
        }
    }
    if (!$bInterests) {
        $bInterests = ['beaches'];
    }

    $message = 'Build the itinerary.';
}

if ($mode === 'chat') {
    $message = trim((string) ($body['message'] ?? ''));
}

$message = (string) $message;

if ($message === '') {
    bud_fail(400, 'Type a question and Bud will answer it.');
}

/* Length cap before the key is even loaded. Tokens cost money and a
   megabyte of pasted text is never a real question. mb_ functions so a
   Tagalog or Bicolano sentence is not miscounted by byte length. */
if (bud_len($message) > BUD_MAX_MESSAGE_CHARS) {
    bud_fail(413, 'That message is too long. Try asking in a sentence or two.');
}

/* ---- conversation history ---- */
/* The browser sends back what was said earlier so Bud can follow "how
   about from there?". Only role and content are copied across, only the
   two roles that are valid, and only the most recent few turns — the
   whole history is resent on every call, so an uncapped log would make
   each message cost more than the last. */
$input   = [];
$history = $body['history'] ?? [];

if (is_array($history)) {
    $history = array_slice($history, -(BUD_MAX_HISTORY_TURNS * 2));

    foreach ($history as $turn) {
        if (!is_array($turn)) {
            continue;
        }

        $role = $turn['role'] ?? '';
        $text = trim((string) ($turn['content'] ?? ''));

        if (($role !== 'user' && $role !== 'assistant') || $text === '') {
            continue;
        }

        /* Gemini calls the assistant "model", and wraps text in a
           parts array. A history entry with role "assistant" is
           rejected outright, so it is translated here rather than
           anywhere later. */
        $input[] = [
            'role'  => $role === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => bud_cut($text, BUD_MAX_MESSAGE_CHARS)]],
        ];
    }
}

$input[] = ['role' => 'user', 'parts' => [['text' => $message]]];

/* ---- destination data ---- */
/* Two sources, in order of preference:

     1. the database, once the destinations tables exist
     2. includes/destinations-data.php, the file the site already uses

   Source 2 is what makes this work TODAY, before any database exists —
   the 24 destinations are already in that file, so Bud is grounded on
   the real list from the first message rather than on whatever it
   happens to recall about the province.

   The whole thing is wrapped in try/catch: if grounding fails, Bud
   should still answer, and the closed-list rule in the prompt still
   applies. */
/* The itinerary prompt replaces the chat one entirely. Asking a model
   to "be conversational, but also return only JSON" is asking for
   prose wrapped around a code fence. */
if ($mode === 'itinerary') {

    $days = $bNights + 1;   // 2 nights is a 3-day trip

    $instructions =
"You plan trips for the official Camarines Norte tourism website.

Return ONLY a JSON object. No prose before it, no prose after it, no
markdown fence. The response is parsed by a program, not read by a
person.

Shape:

{
  \"name\": \"a short trip name, under 60 characters\",
  \"summary\": \"one or two sentences about the plan as a whole\",
  \"days\": [
    { \"items\": [
        { \"time\": \"09:00\", \"destId\": \"<slug from the list below>\",
          \"note\": \"one short line on what to do there\" }
    ] }
  ]
}

RULES

- Exactly {$days} days.
- destId MUST be one of the slugs listed under VALID DESTINATIONS
  below. Never invent one, never use a name instead of a slug. A stop
  whose slug is not on that list is discarded by the program, so an
  invented one simply loses the visitor a stop.
- {$bPerDay} stop(s) per day. This is what the visitor asked for, so
  hold to it: do not add a fourth because a day looks empty, and do
  not drop to one because the driving is long. The only exception is
  the Calaguas rule below.
- KEEP EACH DAY TOGETHER. Every destination below lists what is
  within 20km of it, in square brackets. Two stops on the same day
  must appear in each other's [near:] list. This is measured from real
  coordinates, so treat it as fact rather than as a suggestion — the
  province is about 100km across and two stops at opposite ends is a
  day spent in a van.

  A destination whose list says it has nothing else within 20km gets a
  day to itself.
- Times in 24-hour HH:MM, in order through the day.
- Notes are one short line. No fares, no opening hours, no prices —
  those live in the destination data and are not yours to guess here.
- Calaguas Island needs most of a day: an early start and no other
  stop that day.

THE TRIP
- {$bTravellers} travellers
- {$bNights} nights, so {$days} days
- budget: {$bBudget}
- wants about {$bPerDay} place(s) per day
- interested in: " . implode(', ', $bInterests) . "

Lean the choices toward those interests, but a good plan can include
one thing outside them.

NO PRICES ANYWHERE. Not in the notes, not in the name, not in the
summary. Fares and fees are attached to the plan afterwards from the
site's own collected data, so a number you write here would be an
invented one sitting next to real ones.";

} else {

$instructions = BUD_INSTRUCTIONS
    . "\n\nToday is " . date('l, j F Y') . '.';
}

require_once __DIR__ . '/bud-context.php';

$context  = '';
$dbConfig = __DIR__ . '/../config/database.php';

if (is_file($dbConfig)) {
    try {
        require_once $dbConfig;
        if (isset($pdo) && $pdo instanceof PDO) {
            $context = bud_context($pdo, __DIR__ . '/../cache');
        }
    } catch (Throwable $e) {
        error_log('[bud.php] database context unavailable: ' . $e->getMessage());
    }
}

/* Preferred file source: the generated data file, which carries fares,
   routes and contacts. Falls back to the plain destinations list, which
   has names and descriptions only — better than nothing, but Bud cannot
   answer a price question from it. */
if ($context === '') {
    try {
        $context = bud_context_from_data(__DIR__ . '/../includes/bud-data.php');
    } catch (Throwable $e) {
        error_log('[bud.php] bud-data.php unavailable: ' . $e->getMessage());
    }
}

if ($context === '') {
    try {
        $context = bud_context_from_file(__DIR__ . '/../includes/destinations-data.php');
    } catch (Throwable $e) {
        error_log('[bud.php] file context unavailable: ' . $e->getMessage());
    }
}

/* ---- the slugs the model may choose from ----
   Built from includes/destinations-data.php — the same database-backed
   list the rest of the site uses — and the slug is derived the same
   way plan-trip.php derives it, so what comes back can be saved
   without translation.

   This list is also the whitelist the reply is checked against below.
   Naming the valid options in the prompt AND rejecting anything else
   afterwards: the prompt makes the right answer easy, the check makes
   the wrong one impossible. */
$budSlugs = [];

if ($mode === 'itinerary') {
    $destFile = __DIR__ . '/../includes/destinations-data.php';
    if (is_file($destFile)) {
        try {
            foreach ((array) require $destFile as $d) {
                if (empty($d['name'])) {
                    continue;
                }
                $slug = strtolower(($d['town'] ?? '') . '-' . $d['name']);
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
                if ($slug !== '') {
                    $budSlugs[$slug] = [
                        'name' => $d['name'],
                        'town' => $d['town'] ?? '',
                        /* Kept for the neighbour calculation below. */
                        'lat'  => $d['lat'] ?? null,
                        'lng'  => $d['lng'] ?? null,
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('[bud.php] destination list unavailable: ' . $e->getMessage());
        }
    }

    if (!$budSlugs) {
        /* Without the list the model has nothing legitimate to choose
           from, and every stop it invents would be discarded. Better
           to say so than to return an empty plan. */
        bud_fail(503, 'I cannot build an itinerary right now. Please try again shortly.',
            'Itinerary requested but the destination list could not be loaded.');
    }

    /* ---- WHAT IS NEAR WHAT ----

       The model sees town names and nothing else, so nothing stops it
       putting Santa Elena and Mercedes in the same day — opposite ends
       of a province that is about 100km across, most of a day in a van.

       It cannot be trusted to work that out from coordinates either:
       asking a language model to do trigonometry on decimal degrees is
       asking for a confident wrong answer.

       So PHP does the arithmetic and hands over the conclusion. Each
       destination gets the list of others within 20km, and the prompt
       says to keep a day inside that list. Deterministic, checkable,
       and computed from the coordinates in your own database. */
    $near = [];

    foreach ($budSlugs as $aSlug => $a) {
        if ($a['lat'] === null || $a['lng'] === null) {
            continue;
        }
        foreach ($budSlugs as $bSlug => $b) {
            if ($aSlug === $bSlug || $b['lat'] === null || $b['lng'] === null) {
                continue;
            }

            /* Equirectangular rather than haversine. Over 100km at
               14 degrees north the error is a few hundred metres,
               which cannot change whether two places are "within
               20km" in any way that matters here. */
            $dLat = ($b['lat'] - $a['lat']) * 111.0;
            $dLng = ($b['lng'] - $a['lng']) * 111.0 * cos(deg2rad((float) $a['lat']));
            $km   = sqrt($dLat * $dLat + $dLng * $dLng);

            if ($km <= 20.0) {
                $near[$aSlug][] = $bSlug;
            }
        }
    }

    $lines = [];
    foreach ($budSlugs as $slug => $d) {
        $line = $slug . '  =  ' . $d['name'] . ' (' . $d['town'] . ')';
        if (!empty($near[$slug])) {
            $line .= '  [near: ' . implode(', ', array_slice($near[$slug], 0, 6)) . ']';
        } else {
            $line .= '  [near: nothing else within 20km - give it its own day]';
        }
        $lines[] = $line;
    }
    $instructions .= "\n\nVALID DESTINATIONS - slug on the left, use it exactly:\n"
                   . implode("\n", $lines);
}

/* The fees-and-routes block is for CHAT. Itinerary mode is expressly
   forbidden from writing prices — they are attached afterwards from
   the database — so sending several thousand tokens of them is paying
   to tell the model something it must not use. The slug list above is
   all it needs. */
if ($mode === 'itinerary') {
    $context = '';
}

/* The dishes, for chat only. An itinerary is a sequence of places;
   food belongs in the conversation around it, and putting it in the
   generation prompt would only invite "eat Bicol Express" as a
   numbered stop with a time on it. */
if ($mode === 'chat') {
    try {
        $food = bud_food_context(__DIR__ . '/../includes/food-data.php');
        if ($food !== '') {
            $context = $context === '' ? $food : $context . "\n\n" . $food;
        }
    } catch (Throwable $e) {
        error_log('[bud.php] food context unavailable: ' . $e->getMessage());
    }
}

if ($context !== '') {
    $instructions .= "\n\n" . $context;
} else {
    /* No list at all. Rather than let Bud fall back on training
       knowledge and start recommending places nobody has checked,
       tell it plainly that it has nothing and must say so. */
    $instructions .= "\n\nYou currently have NO destination list loaded. "
        . "Do not name or describe any specific destination. Say the "
        . "listings are unavailable right now and refer the visitor to "
        . "the Provincial Tourism Office.";
}

/* ---- the key ---- */
$apiKey = getenv('GEMINI_API_KEY') ?: '';

if ($apiKey === '') {
    /* config/gemini.php first, config/openai.php second. The second is
       only there so an existing install keeps working after switching
       providers — a new setup should use gemini.php, because a file
       called openai.php holding a Google key is a trap for whoever
       reads this next. */
    foreach (['gemini.php', 'openai.php'] as $name) {
        $keyFile = __DIR__ . '/../config/' . $name;
        if (is_file($keyFile)) {
            $apiKey = trim((string) require $keyFile);
            if ($apiKey !== '') {
                break;
            }
        }
    }
}

if ($apiKey === '') {
    bud_fail(
        500,
        'Bud is not configured yet.',
        'No API key. Set GEMINI_API_KEY or create config/openai.php returning your Google AI Studio key.'
    );
}

/* ---- call Gemini ---- */
/* generateContent: system_instruction carries the system prompt,
   contents carries the conversation. Reference:
   https://ai.google.dev/gemini-api/docs/generate-content/text-generation */

/* A six-day plan is a lot more JSON than a chat reply, and a reply
   that stops at the ceiling is not short — it is INVALID, because the
   object never closes. That is what "I could not put a plan together"
   means. Room to finish costs nothing when the answer is shorter. */
$genConfig = [
    'maxOutputTokens' => $mode === 'itinerary' ? 4000 : BUD_MAX_OUTPUT_TOKENS,
    'temperature'     => 0.4,   // steady and factual, not chatty
];

/* thinkingLevel is a GEMINI 3 field. The 2.x models use a different
   parameter entirely and reject this one, failing the whole request
   with a 400 — so it only goes to models that understand it. There is
   a retry further down as a second line of defence, but not sending a
   field the model cannot read beats recovering from it. */
if (strpos(BUD_MODEL, 'gemini-3') === 0) {
    $genConfig['thinkingConfig'] = ['thinkingLevel' => 'low'];
}

$payload = [
    'system_instruction' => [
        'parts' => [['text' => $instructions]],
    ],
    'contents'         => $input,
    'generationConfig' => $genConfig,
];

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/'
    . BUD_MODEL . ':generateContent');

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => BUD_TIMEOUT_SECONDS,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);

$response = curl_exec($ch);
$status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

/* thinkingConfig is a Gemini 3 feature. A model that does not know it
   rejects the WHOLE request with a 400 rather than ignoring the field,
   which would take the widget down over an optimisation. Drop it and
   send once more. */
if ($status === 400 && isset($payload['generationConfig']['thinkingConfig'])
    && stripos((string) $response, 'thinking') !== false) {

    error_log('[bud.php] Model rejected thinkingConfig; retrying without it.');
    unset($payload['generationConfig']['thinkingConfig']);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/'
        . BUD_MODEL . ':generateContent');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => BUD_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
}

if ($response === false) {
    bud_fail(502, 'Bud could not be reached. Please try again in a moment.', 'curl: ' . $curlErr);
}

$data = json_decode((string) $response, true);

if ($status !== 200 || !is_array($data)) {
    /* The upstream error goes to the log, never to the browser — it can
       contain org and project identifiers. 401 means the key is wrong,
       429 means quota, 404 usually means the model name has changed. */
    $detail = is_array($data) && isset($data['error']['message'])
        ? (string) $data['error']['message']
        : substr((string) $response, 0, 400);

    /* 400 usually means a malformed request, 403 a bad or restricted
       key, 404 a model name that has moved on, 429 the free-tier daily
       or per-minute cap. All four look identical to the visitor; the
       log is where you find out which. */
    bud_fail(502, 'Bud is having trouble answering right now. Please try again shortly.',
        'Gemini HTTP ' . $status . ' (model ' . BUD_MODEL . '): ' . $detail);
}

/* The reply lives in candidates[0].content.parts, which is a LIST —
   long answers arrive split across several parts, so they are joined
   rather than taking the first. */
$reply = '';

foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
    if (isset($part['text'])) {
        $reply .= $part['text'];
    }
}

$reply = trim($reply);

/* Gemini can refuse a whole prompt before generating anything, and it
   says so in promptFeedback rather than in an error status — the HTTP
   code is still 200. Without this check that arrives as an unexplained
   empty reply. */
if ($reply === '' && isset($data['promptFeedback']['blockReason'])) {
    bud_fail(502, 'I cannot answer that one. Try asking it a different way?',
        'Prompt blocked: ' . $data['promptFeedback']['blockReason']);
}

/* Ran out of room mid-sentence. Better to say so than to hand the
   visitor half an answer that stops in the middle of a fare. */
$finish = $data['candidates'][0]['finishReason'] ?? '';

if ($reply === '' && $finish === 'MAX_TOKENS') {
    bud_fail(502, 'That answer got too long for me. Could you ask about one thing at a time?',
        'Hit maxOutputTokens with no text returned.');
}

if ($reply === '' && $finish === 'SAFETY') {
    bud_fail(502, 'I cannot answer that one. Try asking it a different way?',
        'Response blocked by safety filter.');
}

if ($reply === '') {
    bud_fail(502, 'Bud did not manage an answer to that. Try rephrasing it?',
        'Empty reply. finishReason=' . ($finish ?: '?'));
}

/* Text came back, but it stopped at the ceiling rather than at the end
   of a thought. The visitor gets the partial answer — half an answer
   beats an error — but it is logged, because a run of these means
   BUD_MAX_OUTPUT_TOKENS is still too low. */
if ($finish === 'MAX_TOKENS') {
    error_log('[bud.php] Reply truncated at maxOutputTokens ('
        . BUD_MAX_OUTPUT_TOKENS . '). Raise it if this recurs.');
}

/* ===================================================================
   ITINERARY: parse, then police.

   The prompt asked for JSON and named the valid slugs. Neither is a
   guarantee — models wrap JSON in markdown fences, add a sentence
   first, and invent plausible-looking slugs. Everything below treats
   the reply as wrong until it proves otherwise.

   Placed before the photograph matching, which is for chat replies
   and has nothing to match in a JSON payload.
   =================================================================== */
if ($mode === 'itinerary') {

    /* Strip a ```json fence if there is one, then take from the first
       brace to the last. Cheaper than failing over something this
       predictable. */
    $json = trim($reply);
    if (strpos($json, '```') !== false) {
        $json = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $json);
    }
    $open  = strpos($json, '{');
    $close = strrpos($json, '}');
    if ($open !== false && $close !== false && $close > $open) {
        $json = substr($json, $open, $close - $open + 1);
    }

    $plan = json_decode($json, true);

    if (!is_array($plan) || !isset($plan['days']) || !is_array($plan['days'])) {
        /* Truncation and refusal look the same from here — both give
           unparseable text — but they need different advice, and
           finishReason tells them apart. */
        if ($finish === 'MAX_TOKENS') {
            bud_fail(502,
                'That trip was too long for me to plan in one go. Try fewer nights, '
                . 'or one place a day.',
                'Itinerary hit MAX_TOKENS at ' . strlen($reply) . ' chars; JSON incomplete.');
        }

        bud_fail(502, 'I could not put a plan together just then. Try again?',
            'Itinerary JSON unparseable (finish=' . $finish . '). First 400 chars: '
            . substr($reply, 0, 400));
    }

    $planDays = [];
    $kept     = 0;
    $dropped  = 0;

    foreach (array_slice($plan['days'], 0, 14) as $day) {
        $items = [];

        foreach (array_slice((array) ($day['items'] ?? []), 0, 6) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = trim((string) ($item['destId'] ?? ''));

            /* THE WHITELIST. A slug that is not one of the 24 is
               discarded however convincing it looks. Naming the valid
               options in the prompt makes the right answer easy; this
               makes the wrong one impossible. */
            if ($slug === '' || !isset($budSlugs[$slug])) {
                if ($slug !== '') {
                    $dropped++;
                }
                continue;
            }

            $time = trim((string) ($item['time'] ?? ''));

            $items[] = [
                'time'   => preg_match('/^\d{1,2}:\d{2}$/', $time)
                            ? substr('0' . $time, -5) : '',
                'destId' => $slug,
                'note'   => bud_cut(trim((string) ($item['note'] ?? '')), 200),
                /* For display only. save-itinerary.php resolves the
                   slug itself and ignores these. */
                'name'   => $budSlugs[$slug]['name'],
                'town'   => $budSlugs[$slug]['town'],
            ];
            $kept++;
        }

        $planDays[] = ['date' => '', 'items' => $items];
    }

    /* One stop is not a plan. Better to say so than to save something
       useless to somebody's account. */
    if ($kept < 2) {
        bud_fail(502, 'I could not put a workable plan together just then. Try again?',
            'Itinerary had ' . $kept . ' valid stops after filtering, ' . $dropped . ' dropped.');
    }

    bud_out(200, [
        'plan' => [
            /* The visitor's name wins. They typed it; the model only
               guessed. */
            'name'      => $bName !== ''
                           ? $bName
                           : bud_cut(trim((string) ($plan['name'] ?? 'Camarines Norte trip')), 160),
            'summary'   => bud_cut(trim((string) ($plan['summary'] ?? '')), 400),
            'travelers' => $bTravellers,
            'source'    => 'bud',
            'brief'     => [
                'travellers' => $bTravellers,
                'nights'     => $bNights,
                'perDay'     => $bPerDay,
                'budget'     => $bBudget,
                'interests'  => $bInterests,
            ],
            'days'      => $planDays,
        ],
        'stops'   => $kept,
        'dropped' => $dropped,
    ]);
}

/* ---- photographs ----

   Which of the 24 does this reply actually talk about? Matched HERE,
   on the server, against the destination list — not in the browser,
   and not by asking the model to tell us. The model would happily
   name a place it did not mention, or invent an image path; a
   straightforward name match against our own data cannot.

   Image paths and coordinates come from includes/destinations-data.php
   — the site's own source of truth, which reads the database. Bud does
   not keep its own copy, so renaming a photo in the admin does not
   leave a broken image here. */
$places = [];

try {
    $destFile = __DIR__ . '/../includes/destinations-data.php';

    if (is_file($destFile)) {
        $all = require $destFile;

        if (is_array($all)) {
            foreach ($all as $d) {
                $name = trim((string) ($d['name'] ?? ''));
                if ($name === '' || empty($d['image'])) {
                    continue;
                }

                /* Whole-name match, case-insensitive. A plain stripos
                   would match "Mananap Falls" inside "Mananap Falls ATV
                   Adventure" and show the wrong photo for both, so the
                   longer names are checked first and a name already
                   claimed is not matched again. */
                if (stripos($reply, $name) === false) {
                    continue;
                }

                $places[] = [
                    'name'  => $name,
                    'town'  => (string) ($d['town'] ?? ''),
                    'tag'   => (string) ($d['tag'] ?? ''),
                    'image' => (string) $d['image'],
                    'lat'   => isset($d['lat']) ? $d['lat'] : null,
                    'lng'   => isset($d['lng']) ? $d['lng'] : null,
                ];
            }

            /* Longest names first, then drop any whose name is contained
               in one already kept — "Mananap Falls" goes when "Mananap
               Falls ATV Adventure" is present. */
            usort($places, static function ($a, $b) {
                return strlen($b['name']) <=> strlen($a['name']);
            });

            $kept = [];
            foreach ($places as $p) {
                $inside = false;
                foreach ($kept as $k) {
                    if (stripos($k['name'], $p['name']) !== false) {
                        $inside = true;
                        break;
                    }
                }
                if (!$inside) {
                    $kept[] = $p;
                }
            }

            /* Three is plenty under a chat reply. More turns the panel
               into a gallery and pushes the answer off screen. */
            $places = array_slice($kept, 0, 3);
        }
    }
} catch (Throwable $e) {
    error_log('[bud.php] destination photos unavailable: ' . $e->getMessage());
    $places = [];
}

bud_out(200, ['reply' => $reply, 'places' => $places]);