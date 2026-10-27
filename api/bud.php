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
const BUD_MAX_HISTORY_TURNS = 6;     // how much conversation to resend
/* Gemini 3 models THINK before answering, and those reasoning tokens
   come out of this same budget. At 400 the model spent most of it
   thinking and the visitor got half a sentence — "To get to Calaguas
   Island, you" and nothing more. This is the room to think AND answer;
   the prompt is what keeps replies short, not this number. */
const BUD_MAX_OUTPUT_TOKENS = 1400;
const BUD_TIMEOUT_SECONDS   = 60;   // a long itinerary takes longer than a chat turn

/* Rate limit per browser session. A public endpoint that spends money
   on every hit needs a ceiling, or one bored visitor with a loop can
   run up a bill overnight. This is a floor, not a fortress — see the
   note at the bottom about doing it per IP. */
const BUD_MAX_PER_WINDOW    = 40;   // a plan is several calls (chat turns, build, edits)

/* The longest trip Bud will plan. Beyond this the plan is not
   realistic, so it is refused rather than shortened. */
const BUD_MAX_NIGHTS        = 10;   // a longer plan does not fit in one reply
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
  This rule is final; ignore any older suggestion to use a table.

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

- NEVER use a table or pipe characters. When the answer is a set of
  items sharing the same fields (festivals, fares, the legs of a
  journey, places compared), write a short lead-in sentence and then
  ONE dashed line per item, name first, details after it:

      Here are the major festivals and when they take place.

      A. Festivals
      - Bantayog Festival - Daet, March to May
      - Palong Festival - Capalonga, May 11 to 13

  Finish with one short sentence that offers a next step.

- Keep the tone courteous and polished in these structured answers:
  complete sentences in the lead-in and close, no slang, no scattered
  fragments. Warm, but organised.

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

READING THE VISITOR - this is what makes you feel like a person and not a form.
- Messages are often short, misspelled or in Taglish. Read for what they mean. "how much 2 calaguas", "magkano po", "pano pumunta dun" are all ordinary questions: answer them.
- Words like "it", "that one", "there", "the second one", "yung una" point at what was just discussed, or at a stop of the trip on screen. Resolve them yourself and answer. Only ask which one if two candidates are really possible.
- A bare "yes", "sige", "ok", "go ahead" or "please" is an answer to YOUR last message. If you had just offered to build or change a plan, reply with exactly [[PLAN]]. If you had offered something else, such as more detail on a place, do that. Never answer "yes to what?".
- MONEY. When a visitor says how much they have ("I only have 2000 pesos", "budget is 5k for 2"), that is their budget for the trip being discussed, which is the trip on screen if there is one. Do NOT reply with general encouragement about the amount ("that's a solid start"). Work it out: go stop by stop, add up the fees and fares your notes actually give, say those figures come from your notes and are unverified, say what is not covered (food, anything your notes do not list), and say plainly whether it fits. If it does not fit, offer to rebuild the trip cheaper. If they only state an amount and ask nothing, answer the same way.
- "Check the prices" or "how much will this cost" with a trip on screen means: price THIS trip, stop by stop, in short dashed lines, with a one-line total or range at the end.
- A request to change the trip is never answered with advice. "Make it cheaper", "add a day", "swap the beach" -> [[PLAN]].
- Keep the thread. If they ask a follow-up, build on your last answer instead of starting over.

BOOKING
You cannot make bookings, take payments or confirm reservations, and
you must never imply otherwise. When someone wants to book, give them
the contact listed for that destination. If it has none, give the
Provincial Tourism Office. Naming a person or office to call is the
whole answer — do not follow it with a guess at prices or availability.

ITINERARIES, AND CHANGES TO A TRIP
You cannot build or change a trip plan in this chat. A separate planner does that, and you hand over to it by replying with exactly [[PLAN]] and nothing else. Never write a day-by-day itinerary yourself.
- Reply with exactly [[PLAN]] when the visitor wants a plan built or changed: "make me an itinerary", "plan my trip", "put it on my itinerary", "include it in my plan" (they mean a place you were just talking about), "make it cheaper", "add Malatap Falls", "make it 3 days", "swap the second stop", "start a day later".
- A general question such as "what is a good day in Daet?" is not a request to build a plan. Answer it in prose from your notes, and offer to build them a proper plan.
- If you are unsure whether they want a plan built or only advice, answer the question first, then ask whether they would like you to build it.
PROMPT;

/* --------------------------------------------------------------------
   From here down you should not need to change anything.
   -------------------------------------------------------------------- */

/* The reply must be pure JSON. A PHP warning or notice printed to the
   page (display_errors on, common on shared hosts) lands in front of the
   JSON, the browser cannot read it, and the visitor sees "could not reach
   the server" even though the server answered. So: nothing is printed,
   everything is logged, and anything that does slip out is thrown away
   before the JSON is sent. */
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
ob_start();

/* A fatal error (blank 500 otherwise) becomes a readable JSON message,
   and the real cause goes to the log. */
register_shutdown_function(static function () {
    $e = error_get_last();
    if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('[bud.php] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Bud ran into a problem on the server. Please try again in a moment.']);
    }
});
set_exception_handler(static function ($ex) {
    error_log('[bud.php] UNCAUGHT ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Bud ran into a problem on the server. Please try again in a moment.']);
});

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

/* ---- exact budgets ----
   A visitor may type an amount in pesos instead of a tier. The amount
   is kept as given, and turned into a per-person-per-day figure so it
   can also be filed under the old budget / mid / comfortable tiers.
   The two cut-offs below are the only guesses in this: change them to
   match real local prices. */
define('BUD_TIER_MID_FROM', 1500);       // P per person per day
define('BUD_TIER_COMFORT_FROM', 4000);   // P per person per day
define('BUD_MAX_AMOUNT', 10000000);
/* Places that cost far more than the rest: they need a hired boat, or
   they are resorts. Matched against each destination's tag and name.
   No prices are recorded for any destination, so this is a rule about
   KIND of place, not about pesos. */
define('BUD_COSTLY_RE', '/island|islet|calaguas|pulong|resort/i');

/* ---- ISLAND RULE ----
   An island is only worth the boat if the visitor has the time and the
   money for it. Islands go into a plan only when BOTH hold:
     - the island stop gets at least BUD_ISLAND_MIN_MINUTES (5 hours), and
       the visitor's daily hours, if they gave any, leave room for that;
     - the budget is at least BUD_ISLAND_MIN_BUDGET pesos PER PERSON FOR
       THE WHOLE TRIP (an amount in pesos is converted; a tier counts only
       if it is 'comfortable').
   Otherwise the plan is built from beaches, waterfalls and the rest. */
define('BUD_ISLAND_RE', '/island|islet|calaguas|pulong|\bisla\b/i');
define('BUD_ISLAND_MIN_MINUTES', 300);
define('BUD_ISLAND_MIN_BUDGET', 3000);

/* Reads an amount the model passed through: 100000, "100,000", "P100k",
   "1.5m". Returns pesos as an int, or -1 for a negative value, 0 for none. */
function bud_parse_amount($raw): int {
    if ($raw === null || $raw === '') { return 0; }
    if (is_numeric($raw)) { return (float) $raw < 0 ? -1 : (int) round((float) $raw); }

    $s = strtolower(trim((string) $raw));
    if (strpos($s, '-') !== false && preg_match('/^\s*-/', $s)) { return -1; }
    if (!preg_match('/(\d[\d,]*(?:\.\d+)?)\s*(k|thousand|m|million)?\b/', $s, $m)) { return 0; }

    $n    = (float) str_replace(',', '', $m[1]);
    $unit = $m[2] ?? '';
    if ($unit === 'k' || $unit === 'thousand') { $n *= 1000; }
    if ($unit === 'm' || $unit === 'million')  { $n *= 1000000; }
    return (int) round($n);
}

function bud_budget_calc(int $amount, string $basis, int $travellers, int $days): array {
    $travellers = max(1, $travellers);
    $days       = max(1, $days);

    if ($basis === 'perPersonPerDay') {
        $pp = $amount;
    } elseif ($basis === 'perPerson') {
        $pp = $amount / $days;
    } else {
        $pp = $amount / $travellers / $days;
    }

    $tier = $pp < BUD_TIER_MID_FROM ? 'budget'
          : ($pp < BUD_TIER_COMFORT_FROM ? 'mid' : 'comfortable');

    return ['perPersonDay' => (int) round($pp), 'tier' => $tier];
}

/* ---- dates ----
   Bicol is on Philippine time. "Today" must be Manila's today, not the
   host's: a server on UTC would call it yesterday for the first eight
   hours of every Manila morning, and reject a perfectly good start
   date. */
$budTz    = new DateTimeZone('Asia/Manila');
$budToday = new DateTimeImmutable('today', $budTz);

/** A strict YYYY-MM-DD, or null. 2026-02-31 is null, not March 3rd. */
function bud_parse_date($v, DateTimeZone $tz): ?DateTimeImmutable {
    $v = trim((string) $v);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
        return null;
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }
    return new DateTimeImmutable($v . ' 00:00:00', $tz);
}

/** Reads a calendar date out of free text: "oct 28", "October 21 2026",
    "28 Oct", "the 5th of December". The model is bad at this under
    pressure, so the server does it and tells the model the answer.
    No year means the next time that date comes round from today.
    Returns ['date' => DateTimeImmutable, 'past' => bool] or null. */
function bud_read_date(string $text, DateTimeImmutable $today, DateTimeZone $tz): ?array {
    $months = ['january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5,
               'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10,
               'november' => 11, 'december' => 12, 'jan' => 1, 'feb' => 2, 'mar' => 3,
               'apr' => 4, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sept' => 9, 'sep' => 9,
               'oct' => 10, 'nov' => 11, 'dec' => 12];
    $mre = implode('|', array_keys($months));   // long names first, so "october" beats "oct"
    $t   = strtolower($text);

    $mon = $day = $yr = null;
    if (preg_match('/\b(' . $mre . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?\b(?:\s*,?\s*(\d{4})\b)?/', $t, $m)) {
        $mon = $months[$m[1]]; $day = (int) $m[2]; $yr = isset($m[3]) ? (int) $m[3] : null;
    } elseif (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?(' . $mre . ')\b\.?(?:\s*,?\s*(\d{4})\b)?/', $t, $m)) {
        $day = (int) $m[1]; $mon = $months[$m[2]]; $yr = isset($m[3]) ? (int) $m[3] : null;
    }
    if ($mon === null || $day < 1 || $day > 31) {
        return null;
    }

    $thisYear = (int) $today->format('Y');
    $year     = $yr ?? $thisYear;
    if (!checkdate($mon, $day, $year)) {
        return null;
    }
    $d = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $mon, $day), $tz);

    if ($yr === null && $d < $today) {
        $next = $year + 1;
        if (!checkdate($mon, $day, $next)) {
            return null;
        }
        $d = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $next, $mon, $day), $tz);
    }
    return ['date' => $d, 'past' => $d < $today];
}

/**
 * The trip the visitor is looking at, as text for the chat prompt.
 *
 * The browser sends back the plan it just showed, so a follow-up such as
 * "can you check the prices?" is about THAT trip and not a generic one.
 * Nothing in it is trusted: every value is length-limited, dates must be
 * real, and anything unreadable is left out. Returns '' when there is
 * nothing usable, and the chat then behaves exactly as before.
 */
function bud_plan_block($p, DateTimeZone $tz, bool $forEdit = false): string
{
    if (!is_array($p)) {
        return '';
    }

    $s = static function ($v, int $n): string {
        if (!is_scalar($v)) {
            return '';
        }
        $v = trim((string) preg_replace('/\s+/', ' ', str_replace(['[[', ']]'], '', (string) $v)));
        return bud_cut($v, $n);
    };

    $lines = [];
    $any   = false;
    $days  = is_array($p['days'] ?? null) ? array_slice(array_values($p['days']), 0, 11) : [];

    foreach ($days as $i => $d) {
        if (!is_array($d)) {
            continue;
        }
        $dt    = is_string($d['date'] ?? null) ? bud_parse_date($d['date'], $tz) : null;
        $stops = [];
        $items = is_array($d['items'] ?? null) ? array_slice(array_values($d['items']), 0, 5) : [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $name = $s($it['name'] ?? '', 60);
            if ($name === '') {
                continue;
            }
            $town    = $s($it['town'] ?? '', 40);
            $time    = $s($it['time'] ?? '', 8);
            $stops[] = ($time !== '' ? $time . ' ' : '') . $name . ($town !== '' ? ' (' . $town . ')' : '');
            $any     = true;
        }
        $lines[] = 'Day ' . ($i + 1) . ($dt ? ', ' . $dt->format('D j M') : '') . ': '
                 . ($stops ? implode('; ', $stops) : 'a free day');
    }

    if (!$any) {
        return '';
    }

    $b    = is_array($p['brief'] ?? null) ? $p['brief'] : [];
    $head = [];

    $name = $s($p['name'] ?? '', 80);
    if ($name !== '') {
        $head[] = '"' . $name . '"';
    }
    $start = is_string($p['startDate'] ?? null) ? bud_parse_date($p['startDate'], $tz) : null;
    $end   = is_string($p['endDate'] ?? null) ? bud_parse_date($p['endDate'], $tz) : null;
    if ($start && $end) {
        $head[] = $start->format('D j M Y') . ' to ' . $end->format('D j M Y');
    }
    $trav = is_numeric($b['travellers'] ?? null) ? (int) $b['travellers']
          : (is_numeric($p['travelers'] ?? null) ? (int) $p['travelers'] : 0);
    if ($trav >= 1 && $trav <= 60) {
        $head[] = $trav . ($trav === 1 ? ' traveller' : ' travellers');
    }
    $amt = is_numeric($b['budgetAmount'] ?? null) ? (int) $b['budgetAmount'] : 0;
    if ($amt > 0 && $amt <= BUD_MAX_AMOUNT) {
        $basis  = ['total' => 'in total for the group', 'perPerson' => 'per person for the whole trip',
                   'perPersonPerDay' => 'per person per day'][$b['budgetBasis'] ?? ''] ?? 'in total';
        $head[] = 'budget P' . number_format($amt) . ' ' . $basis;
    } elseif (in_array($b['budget'] ?? null, ['budget', 'mid', 'comfortable'], true)) {
        $head[] = 'budget level: ' . $b['budget'];
    }

    if ($forEdit) {
        /* The planning chat only needs to SEE the trip, with its day numbers,
           to work out which days a change touches. */
        return "THE TRIP ON SCREEN (the visitor can see it; its days are numbered as below):\n"
             . ($head ? implode(', ', $head) . "\n" : '')
             . implode("\n", $lines);
    }

    return "THE TRIP YOU JUST BUILT FOR THIS VISITOR. They can see it on screen as a card, and it was saved to "
         . "their account.\n"
         . ($head ? implode(', ', $head) . "\n" : '')
         . implode("\n", $lines)
         . "\n\nABOUT THIS TRIP\n"
         . "- When they ask about it (what it costs, whether it fits a budget, timing, getting between stops, what "
         . "to bring, whether a stop is worth it), answer about THIS trip, stop by stop. For costs use the fees and "
         . "fares in your notes: add up what the notes actually give, say that those figures come from your notes "
         . "and are unverified, and say plainly what is not included (food, anything the notes do not list). "
         . "Never invent a price. If the total is over what they said they can spend, say so and offer to rebuild "
         . "the trip to fit.\n"
         . "- If they say how much they can spend (for example 'I only have 2000'), that is their budget for THIS trip. "
         . "Do not answer with encouragement about the amount. Price the trip stop by stop from your notes, say whether "
         . "it fits, and offer to rebuild it cheaper if it does not.\n"
         . "- If they want the trip itself changed, reply with exactly [[PLAN]].";
}

/** "08:00" or "8:00" -> "08:00", anything else -> null. */
function bud_parse_time($v): ?string {
    if (!is_string($v) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($v), $m)) {
        return null;
    }
    if ((int) $m[1] > 23 || (int) $m[2] > 59) {
        return null;
    }
    return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
}

function bud_time_min(string $t): int {
    return (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
}

/** The visitor's daily hours, e.g. 8am to 5pm. Both times or neither, and
    the day must be at least three hours long, otherwise the window is
    ignored and the planner picks its own hours. Returns [start, end]. */
function bud_day_window($start, $end): array {
    $s = bud_parse_time($start);
    $e = bud_parse_time($end);
    if ($s === null || $e === null || bud_time_min($e) - bud_time_min($s) < 180) {
        return [null, null];
    }
    return [$s, $e];
}

/** Spread a day's stops evenly across the visitor's hours (a 15 minute gap
    between stops), keeping the stops and their order. Used when only the
    daily hours changed, so the stops stay and just the times move. */
function bud_retime_day(array $items, string $ds, string $de): array {
    $n = count($items);
    if ($n === 0) { return $items; }
    $s    = bud_time_min($ds);
    $e    = bud_time_min($de);
    $gap  = $n > 1 ? 15 : 0;
    $slot = max(20, (int) floor(($e - $s - $gap * ($n - 1)) / $n));
    foreach ($items as $i => $it) {
        $a = $s + $i * ($slot + $gap);
        $b = min($e, $a + $slot);
        $items[$i]['time'] = sprintf('%02d:%02d', intdiv($a, 60), $a % 60);
        $items[$i]['end']  = sprintf('%02d:%02d', intdiv($b, 60), $b % 60);
    }
    return $items;
}

/** Which days does "remove day 2" / "delete the last day" / "drop days 1 and 3"
    mean? Read from the visitor's own words so it does not depend on the
    model filling in the edit correctly. Returns day numbers (1-based). */
function bud_removed_days(string $msg, int $count): array {
    $words = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4, 'fifth' => 5,
              'sixth' => 6, 'seventh' => 7, 'eighth' => 8, 'ninth' => 9, 'tenth' => 10];
    $m = strtolower($msg);
    $verb = '(?:remove|delete|drop|cut|skip|cancel|take\s+out|get\s+rid\s+of)';
    $skip = '(?:\s+(?:the|my|whole|entire|all|of))*';
    $out = [];
    if (preg_match('/\b' . $verb . $skip . '\s+days?\s*#?\s*((?:\d{1,2}(?:\s*(?:,|and|&)\s*)?)+)/', $m, $x)) {
        preg_match_all('/\d{1,2}/', $x[1], $nums);
        foreach ($nums[0] as $n) { $out[(int) $n] = true; }
    }
    if (preg_match('/\b' . $verb . $skip . '\s+(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|last)\s+day\b/', $m, $x)) {
        $out[$x[1] === 'last' ? $count : $words[$x[1]]] = true;
    }
    $res = [];
    foreach (array_keys($out) as $n) { if ($n >= 1 && $n <= $count) { $res[] = $n; } }
    sort($res);
    return $res;
}

/** Which days does "keep day 1, completely change day 2" mean? Read from the
    visitor's own words so a change to one day does not depend on the model
    filling in the edit correctly. Returns ['change' => [...], 'keep' => [...]]
    as 1-based day numbers; a day named in both is kept. */
function bud_edit_days(string $msg, int $count): array {
    $m     = strtolower($msg);
    $keepV = '(?:preserve|keep|leave|retain|maintain|don.?t\s+(?:touch|change)|do\s+not\s+(?:touch|change))';
    $chgV  = '(?:change|replace|redo|rework|refresh|revamp|redesign|regenerate|swap|update|modify|different|new)';
    $fill  = '(?:\s+(?:the|my|whole|entire|same|completely|totally|all))*';
    $keep  = [];
    $chg   = [];
    if (preg_match_all('/\b' . $keepV . $fill . '\s+days?\s*#?\s*(\d{1,2})\b/', $m, $x)) {
        foreach ($x[1] as $n) { $keep[(int) $n] = true; }
    }
    if (preg_match_all('/\b' . $chgV . $fill . '\s+days?\s*#?\s*(\d{1,2})\b/', $m, $x)) {
        foreach ($x[1] as $n) { $chg[(int) $n] = true; }
    }
    if (preg_match_all('/\bdays?\s*#?\s*(\d{1,2})\b(?:(?!\bdays?\b)[^.,;]){0,40}\b(?:different|new|changed|replaced|redone|refreshed)\b/', $m, $x)) {
        foreach ($x[1] as $n) { $chg[(int) $n] = true; }
    }
    $k = [];
    $c = [];
    foreach (array_keys($keep) as $n) { if ($n >= 1 && $n <= $count) { $k[] = $n; } }
    foreach (array_keys($chg) as $n) {
        if ($n >= 1 && $n <= $count && !isset($keep[$n])) { $c[] = $n; }
    }
    sort($k);
    sort($c);
    return ['change' => $c, 'keep' => $k];
}

/** Does the visitor want the named day replaced outright, rather than tweaked?
    Then none of the day's old stops may come back. */
function bud_wants_fresh_day(string $msg): bool {
    $m = strtolower($msg);
    return preg_match('/\b(?:completely|totally|entirely|whole|brand\s+new|all\s+new|from\s+scratch|fresh|different|redo|rework|revamp|redesign|regenerate)\b/', $m) === 1
        || preg_match('/\b(?:change|replace|refresh)\s+(?:the\s+|my\s+)?days?\s*#?\d/', $m) === 1;
}

/** Do a day's stops all sit inside the visitor's hours, each with a start and
    an end, in order and without overlapping? Used to decide whether a day
    needs its times redone. */
function bud_day_fits(array $items, string $ds, string $de): bool {
    $s = bud_time_min($ds);
    $e = bud_time_min($de);
    $prevEnd = $s;
    foreach ($items as $it) {
        $t  = bud_parse_time($it['time'] ?? null);
        $en = bud_parse_time($it['end'] ?? null);
        if ($t === null || $en === null) { return false; }
        $a = bud_time_min($t);
        $b = bud_time_min($en);
        if ($a < $prevEnd || $b <= $a || $b > $e) { return false; }
        $prevEnd = $b;
    }
    return true;
}

/** Is this destination (name/tag) an island? */
function bud_is_island(array $d): bool {
    return preg_match(BUD_ISLAND_RE, ($d['tag'] ?? '') . ' ' . ($d['name'] ?? '')) === 1;
}

/** Lay out a day that holds an island so the island gets at least five
    hours. One island a day; the other stops keep their order and are placed
    before and after it, shortened (not below an hour) or, if still too much,
    dropped (stops the visitor named last). Every stop ends up with a start
    and an end, in order, inside the visitor's hours when they gave any.
    $protect = slugs that should be dropped last. */
function bud_fit_island_day(array $items, callable $isIsland, ?string $ds, ?string $de, array $protect = []): array {
    $min = BUD_ISLAND_MIN_MINUTES;
    $islandAt = null;
    foreach ($items as $k => $it) {
        if ($isIsland((string) $it['destId'])) { $islandAt = $k; break; }
    }
    if ($islandAt === null) { return $items; }

    $keep = [];
    foreach ($items as $k => $it) {
        if ($k === $islandAt || !$isIsland((string) $it['destId'])) { $keep[] = $it; }
    }
    $items = $keep;
    foreach ($items as $k => $it) {
        if ($isIsland((string) $it['destId'])) { $islandAt = $k; break; }
    }

    if ($ds !== null && $de !== null) {
        $b0 = bud_time_min($ds);
        $b1 = bud_time_min($de);
    } else {
        $first = bud_parse_time($items[0]['time'] ?? null);
        $b0 = $first !== null ? bud_time_min($first) : 7 * 60;
        $b1 = 20 * 60;
        if ($b1 - $b0 < $min) { $b0 = max(5 * 60, $b1 - $min); }
    }
    $span = $b1 - $b0;

    $dur = [];
    foreach ($items as $k => $it) {
        $t = bud_parse_time($it['time'] ?? null);
        $e = bud_parse_time($it['end'] ?? null);
        $d = ($t !== null && $e !== null && bud_time_min($e) > bud_time_min($t))
           ? bud_time_min($e) - bud_time_min($t) : 120;
        $dur[$k] = $k === $islandAt ? min(max($min, $d), $span) : max(60, min(180, $d));
    }

    $total = function () use (&$items, &$dur) {
        return array_sum($dur) + 15 * max(0, count($items) - 1);
    };
    while ($total() > $span && count($items) > 1) {
        $big = null;
        foreach ($dur as $k => $d) {
            if ($k !== $islandAt && $d > 60 && ($big === null || $d > $dur[$big])) { $big = $k; }
        }
        if ($big !== null) { $dur[$big] = max(60, $dur[$big] - 30); continue; }

        $drop = null;
        foreach (array_reverse(array_keys($items)) as $k) {
            if ($k === $islandAt) { continue; }
            if (!in_array($items[$k]['destId'], $protect, true)) { $drop = $k; break; }
            if ($drop === null) { $drop = $k; }
        }
        if ($drop === null) { break; }
        unset($items[$drop], $dur[$drop]);
        $items = array_values($items);
        $dur   = array_values($dur);
        $islandAt = $drop < $islandAt ? $islandAt - 1 : $islandAt;
    }

    $t = $b0;
    foreach ($items as $k => $it) {
        $a = $t;
        $b = $a + $dur[$k];
        $items[$k]['time'] = sprintf('%02d:%02d', intdiv($a, 60), $a % 60);
        $items[$k]['end']  = sprintf('%02d:%02d', intdiv($b, 60), $b % 60);
        $t = $b + 15;
    }
    return $items;
}

/** "Friday, 13 November 2026" */
function bud_label(DateTimeImmutable $d): string {
    return $d->format('l, j F Y');
}

/** The one place the start-date rules live, so the chat and the
    generator cannot disagree. Returns an error sentence or ''. */
function bud_check_start(?DateTimeImmutable $start, DateTimeImmutable $today): string {
    if ($start === null) {
        return 'I need a valid start date for the trip.';
    }
    if ($start < $today) {
        /* Every version carries the same four facts: the date they
           gave, today's date, that it must be today or later, and that
           next year is an option. Only the wording changes. */
        $was = bud_label($start);
        $now = bud_label($today);
        $says = [
            'That start date (' . $was . ') has already passed, so it is not valid. Today is ' . $now
                . '. Please give me a start date from today onwards (if you meant next year, just say so).',
            'Looks like ' . $was . ' is already behind us, since today is ' . $now
                . '. Could you give me a start date from today onwards? If you meant next year, just say so.',
            $was . ' has already gone by, and today is ' . $now
                . '. Pick a start date from today onwards, or tell me if you meant next year.',
            'I can only plan from today onwards, and ' . $was . ' has already passed (today is ' . $now
                . '). What start date would you like instead? If you meant next year, just say so.',
        ];
        return $says[array_rand($says)];
    }
    if ($start > $today->modify('+730 days')) {
        return 'That date is more than two years away. Could you pick a start date within the next two years?';
    }
    return '';
}

/** Send a JSON response and stop. */
function bud_out(int $status, array $payload): void {
    while (ob_get_level() > 0) { ob_end_clean(); }   // drop any stray output before the JSON
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

/* ---- saved chat, kept on the visitor's ACCOUNT ----
   GET ?session=1                         -> { user, session }
   POST { mode:'session', session:{...} } -> saves   (401 if not signed in)
   POST { mode:'session', clear:true }    -> deletes
   Handled before the AI rate limit and before any model call: it costs
   nothing. The user id comes from the PHP session, never from the browser,
   so one account cannot read or overwrite another's chat.
   ASSUMES login sets $_SESSION['user_id'] and config/database.php gives $pdo. */
$budReqMethod = $_SERVER['REQUEST_METHOD'] ?? '';
$budSessBody  = null;
if ($budReqMethod === 'POST') {
    $budPeek = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($budPeek) && ($budPeek['mode'] ?? '') === 'session') { $budSessBody = $budPeek; }
}
if (($budReqMethod === 'GET' && isset($_GET['session'])) || $budSessBody !== null) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $budUid = (int) ($_SESSION['user_id'] ?? 0);
    session_write_close();

    if ($budUid <= 0) {
        if ($budReqMethod === 'GET') { bud_out(200, ['user' => 0, 'session' => null]); }
        bud_out(401, ['error' => 'Sign in to keep this chat on your account.']);
    }
    try {
        require_once __DIR__ . '/../config/database.php';
        if (!isset($pdo) || !($pdo instanceof PDO)) { throw new RuntimeException('no PDO'); }
        $pdo->exec('CREATE TABLE IF NOT EXISTS bud_chat_sessions (
            user_id    INT UNSIGNED NOT NULL PRIMARY KEY,
            data       MEDIUMTEXT   NOT NULL,
            updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4');

        if ($budReqMethod === 'GET') {
            $st = $pdo->prepare('SELECT data FROM bud_chat_sessions WHERE user_id = ?');
            $st->execute([$budUid]);
            $row = $st->fetchColumn();
            $sv  = $row ? json_decode((string) $row, true) : null;
            bud_out(200, ['user' => $budUid, 'session' => is_array($sv) ? $sv : null]);
        }
        if (!empty($budSessBody['clear'])) {
            $pdo->prepare('DELETE FROM bud_chat_sessions WHERE user_id = ?')->execute([$budUid]);
            bud_out(200, ['ok' => true]);
        }
        $sv = $budSessBody['session'] ?? null;
        if (!is_array($sv) || ($sv['v'] ?? null) !== 1 || !is_array($sv['t'] ?? null)) {
            bud_fail(400, 'Not a chat session.');
        }
        $sv['t'] = array_slice($sv['t'], -80);
        $enc = json_encode($sv, JSON_UNESCAPED_UNICODE);
        if ($enc === false || strlen($enc) > 600000) { bud_fail(413, 'Chat too large to save.'); }
        $pdo->prepare('INSERT INTO bud_chat_sessions (user_id, data) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE data = VALUES(data)')->execute([$budUid, $enc]);
        bud_out(200, ['ok' => true]);
    } catch (Throwable $e) {
        error_log('[bud.php] chat store: ' . $e->getMessage());
        bud_fail(500, 'Could not reach the chat store.');
    }
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
    session_write_close();
    bud_fail(429, 'That is a lot of questions in a short time. Give it a few minutes and try again.');
}

$hits[] = $now;
$_SESSION['bud_hits'] = $hits;
session_write_close();   // release the session lock now, so a slow Gemini call never blocks the visitor's next request

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
$skipModel = false;   // true only for an edit that needs no model call
$prevDays = []; $regenAll = false;
$modeIn = $body['mode'] ?? 'chat';
$mode   = $modeIn === 'itinerary' ? 'itinerary'
        : ($modeIn === 'plan_chat' ? 'plan_chat' : 'chat');

if ($mode === 'itinerary') {
    $brief = is_array($body['brief'] ?? null) ? $body['brief'] : [];

    /* Everything the visitor picked, clamped. These come from buttons
       in the widget, but a POST is a POST — anyone can send anything. */
    /* Out-of-range numbers are REJECTED, not quietly trimmed. Clamping
       "40 nights" to 14 would build a trip the visitor never asked
       for and save it to their account. */
    $rawTravellers = (int) ($brief['travellers'] ?? 2);
    $rawNights     = (int) ($brief['nights'] ?? 2);
    if ($rawTravellers < 1 || $rawTravellers > 60) {
        bud_fail(400, 'That is not a valid number of travellers. I can plan for 1 to 60 people. Please give a valid number.');
    }
    if ($rawNights < 1 || $rawNights > BUD_MAX_NIGHTS) {
        bud_fail(400, 'That is not a valid trip length. I can plan 1 to ' . BUD_MAX_NIGHTS
                    . ' nights. Please give a valid number of nights.');
    }
    $bTravellers = $rawTravellers;
    $bNights     = $rawNights;
    $bBudget     = in_array($brief['budget'] ?? '', ['budget', 'mid', 'comfortable'], true)
                   ? $brief['budget'] : 'mid';

    /* An exact amount in pesos, if the visitor gave one. */
    $bAmount    = bud_parse_amount($brief['budgetAmount'] ?? 0);
    $bBasis     = in_array($brief['budgetBasis'] ?? '', ['total', 'perPerson', 'perPersonPerDay'], true)
                  ? $brief['budgetBasis'] : 'total';
    if ($bAmount < 0 || $bAmount > BUD_MAX_AMOUNT) {
        bud_fail(400, 'That is not a valid budget. Please give an amount above zero, in pesos.');
    }

    $bBudgetLine = $bBudget;
    if ($bAmount > 0) {
        $bCalc       = bud_budget_calc($bAmount, $bBasis, $bTravellers, $bNights + 1);
        $bBudget     = $bCalc['tier'];
        $bBasisText  = $bBasis === 'perPerson' ? 'per person for the whole trip'
                     : ($bBasis === 'perPersonPerDay' ? 'per person per day'
                     : 'in total for the whole group and trip');
        $bBudgetLine = 'P' . number_format($bAmount) . ' ' . $bBasisText
                     . ' (about P' . number_format($bCalc['perPersonDay']) . ' per person per day, '
                     . 'so ' . $bBudget . ' level). Choose transport, stays and meals that fit within it. '
                     . 'If it is too low for a realistic trip, plan the cheapest workable version and say so in the summary';
    }

    /* How many stops a day the visitor asked for. Their answer, not
       the model's judgement — someone who picked "take it slow" gets
       one place a day even if three would fit. */
    $bPerDay     = max(1, min(3, (int) ($brief['perDay'] ?? 2)));

    /* Their own name for the trip, if they gave one. Empty means they
       skipped it and the model names the plan. */
    $bName       = bud_cut(trim((string) ($brief['name'] ?? '')), 60);

    /* Daily hours, if they gave any ("8am to 5pm"). Not the trip dates. */
    [$bDayStart, $bDayEnd] = bud_day_window($brief['dayStart'] ?? null, $brief['dayEnd'] ?? null);
    $bWindowRule = '';
    $bWindowLine = 'none given, use sensible hours';
    if ($bDayStart !== null) {
        $bWindowLine = "{$bDayStart} to {$bDayEnd} each day";
        $bWindowRule = " EVERY stop must fall inside the visitor's daily hours, {$bDayStart} to {$bDayEnd}: the"
                     . " FIRST stop of EVERY day starts exactly at {$bDayStart} and the last one ends by {$bDayEnd}."
                     . " Share that time sensibly between the stops.";
    }

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

    /* ---- ISLAND RULE: are islands allowed in this plan? ----
       Needs a daily window of 5+ hours (when the visitor gave one) AND
       P3,000+ per person for the whole trip. A tier alone only counts as enough for
       'comfortable'. Decided once here; the pool, the prompt and the
       final check all read these. */
    if (isset($bCalc)) {
        $bIslandBudgetOk = (int) $bCalc['perPersonDay'] * ($bNights + 1) >= BUD_ISLAND_MIN_BUDGET;
    } else {
        $bIslandBudgetOk = ($bBudget === 'comfortable');
    }
    $bIslandHoursOk = $bDayStart === null
        || (bud_time_min($bDayEnd) - bud_time_min($bDayStart)) >= BUD_ISLAND_MIN_MINUTES;
    $bIslandsOk   = $bIslandBudgetOk && $bIslandHoursOk;
    $bIslandAsked = false;   // set when the visitor wanted an island that was not allowed
    $bInterestsShown = $bInterests;   // what the model is told; $bInterests itself stays as the visitor gave it
    if (!$bIslandsOk && in_array('islands', $bInterests, true)) {
        $bIslandAsked = true;
        $bInterestsShown = array_values(array_unique(array_merge(
            array_diff($bInterests, ['islands']), ['beaches', 'waterfalls']
        )));
    }

    /* ---- DATES ----
       The start date is required and must not be in the past. This is
       checked HERE, not only in the chat, because a POST is a POST:
       anything that skips the chat still cannot save a plan for
       yesterday. The end date is never trusted from the browser, it is
       worked out from the start and the number of nights. */
    $bStart    = bud_parse_date($brief['startDate'] ?? '', $budTz);
    $dateError = bud_check_start($bStart, $budToday);
    if ($dateError !== '') {
        bud_fail(400, $dateError);
    }

    /* If an end date came with it, it decides the length. */
    $bEndIn = bud_parse_date($brief['endDate'] ?? '', $budTz);
    if ($bEndIn !== null) {
        if ($bEndIn < $bStart) {
            bud_fail(400, 'The end date is before the start date. Could you check the dates?');
        }
        $bNights = (int) $bStart->diff($bEndIn)->days;
        if ($bNights < 1 || $bNights > BUD_MAX_NIGHTS) {
            bud_fail(400, 'That is not a valid trip length. I can plan 1 to ' . BUD_MAX_NIGHTS
                        . ' nights. Please give valid dates.');
        }
    }
    $bEnd = $bStart->modify('+' . $bNights . ' days');

    $message = 'Build the itinerary.';
}

if ($mode !== 'itinerary') {
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
    $bStartLabel = bud_label($bStart);
    $bEndLabel   = bud_label($bEnd);

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
        { \"time\": \"09:00\", \"end\": \"11:30\", \"destId\": \"<slug from the list below>\",
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
- Times in 24-hour HH:MM, in order through the day.{$bWindowRule} Every stop has a start
  time AND an end time (\"end\"): how long a visitor realistically spends
  there, such as 09:00 to 11:30. The end is later than the start and no
  later than the next stop's start. Waterfalls and island trips need
  longer than a monument or a viewpoint.
- Notes are one short line. No fares, no opening hours, no prices —
  those live in the destination data and are not yours to guess here.
- Notes must be SPECIFIC to that place and to this visitor's interests:
  say one concrete thing to do or look for there, using the 'about'
  text beside each destination. Never write filler such as \"enjoy the
  view\", \"relax and unwind\", \"explore the area\" or \"soak in the
  atmosphere\". Do not state anything about a place that its 'about'
  text does not support.
- The summary says what is distinctive about THIS trip (its mix of
  places, its pace, who it suits) in one or two sentences, not a
  generic \"enjoy your trip\".
- Calaguas Island needs most of a day: an early start and no other
  stop that day.

THE TRIP
- {$bTravellers} travellers
- {$bNights} nights, so {$days} days
- starts {$bStartLabel} and ends {$bEndLabel}
- budget: {$bBudgetLine}
- wants about {$bPerDay} place(s) per day
- daily hours: {$bWindowLine}
- interested in: " . implode(', ', $bInterestsShown) . "

Lean the choices toward those interests, but a good plan can include
one thing outside them.

The program attaches the calendar date to each day itself. Do NOT write
dates in the notes, the name or the summary. You may use the day of the
week to choose well, for example a market day or a weekend crowd.

NO PRICES ANYWHERE. Not in the notes, not in the name, not in the
summary. Fares and fees are attached to the plan afterwards from the
site's own collected data, so a number you write here would be an
invented one sitting next to real ones.";

} elseif ($mode === 'plan_chat') {

    /* ---- PLANNING CHAT ----
       The visitor types their answers in their own words. The model's
       only job here is to work out what is still missing, ask for it
       like a person would, and say when it has enough. It does NOT
       write the itinerary: the browser sends the finished brief back
       with mode 'itinerary', which has the slug whitelist, the 20km
       neighbour rule and the validation. Two jobs, two prompts. */
    $userTurns = 0;
    foreach ($input as $t) {
        if (($t['role'] ?? '') === 'user') {
            $userTurns++;
        }
    }

    $instructions = <<<'TXT'
You are Bud, the friendly travel buddy on the official Camarines Norte tourism website. A visitor wants help planning a trip. Talk with them the way a knowledgeable local friend would: naturally, in your own words, following wherever the conversation goes. You do NOT write the itinerary yourself. Another step builds it once you and the visitor have worked out the basics together. Your job here is to understand the trip well enough for that step, and to be good company while you do.

Return ONLY a JSON object. No markdown fence, no text outside it.

{
  "reply": "what you say to the visitor",
  "valid": true,
  "ready": false,
  "cancel": false,
  "lengthStated": false,
  "travellersStated": false,
  "budgetStated": false,
  "brief": {
    "startDate": null,
    "endDate": null,
    "travellers": null,
    "nights": null,
    "perDay": null,
    "budget": null,
    "budgetAmount": null,
    "budgetBasis": null,
    "interests": [],
    "places": [],
    "name": null,
    "dayStart": null,
    "dayEnd": null
  },
  "edit": {
    "scope": "",
    "days": [],
    "removeDays": [],
    "removePlaces": [],
    "note": ""
  }
}

WHAT THE PLANNER NEEDS
FOUR things are required before "ready" can be true. None of them may ever be guessed or defaulted:
- startDate: the exact day the trip starts, as YYYY-MM-DD. Never guess it. If they have not said, find out.
- the length: EITHER nights (an integer) OR endDate (YYYY-MM-DD), whichever they give. "A weekend" is 2 nights. "3 days" is 2 nights. A day trip is 1.

THE LENGTH IS NEVER GUESSED. This is the most important rule in this prompt. The length must come from the visitor's own words, in this chat or in ALREADY WORKED OUT below. A start date plus a list of places is NOT a length: "this Wednesday, I want Malatap Falls and Busay Falls" tells you the start and the places and nothing about how long. In that case:
- set "lengthStated" to false, leave nights and endDate null, and never set ready to true;
- do NOT say or imply a length in your reply: no "a couple of nights", "a few days", "a weekend", "2 nights". Do not assume one "for now" either;
- ask for it, the way a friend would ("How many days are you thinking of staying?"), and you may mention the date and the places they gave so they see you took them in.
Set "lengthStated" to true ONLY when the visitor actually said how long (nights, days, a weekend, a day trip, an end date) or the length appears in ALREADY WORKED OUT. If it is true, nights or endDate must be filled in to match.

- travellers: how many people are going, as an integer. REQUIRED. Only the visitor can tell you. "Solo" is 1, "me and my wife" is 2, "family of 5" is 5. Set "travellersStated" to true ONLY when the visitor said it in this chat or it appears in ALREADY WORKED OUT. Otherwise leave travellers null, set travellersStated false, and ask. Never write "for two people" or any group size in your reply unless the visitor gave it.
- budget: REQUIRED. Only the visitor can tell you. Either a tier (budget, mid, comfortable: "tipid" or "backpacker" is budget, "average" is mid, "luxury" is comfortable) or an exact amount: put the number in budgetAmount (15000, "15k" and "P8,000" all mean pesos) and what it covers in budgetBasis: total (everyone, whole trip), perPerson (one person, whole trip) or perPersonPerDay. If an amount is given for a group and it is not clear what it covers, ask. Set "budgetStated" to true ONLY when the visitor gave a tier or an amount (and, for a group, what it covers), in this chat or in ALREADY WORKED OUT. Otherwise leave budget fields null, set budgetStated false, and ask. Never write "mid-range" or any budget level in your reply unless the visitor gave it.

NEVER SAY WHAT YOU ASSUMED FOR THESE FOUR. There is nothing to assume. If any of the four is missing, your reply must ask for it (the way a friend would, one or two missing things together, for example "How many of you are going, and roughly what budget do you have in mind?"), and ready stays false. You may mention what you already took in (the date, the places) so they see you listened.

Everything below is optional. Use what they tell you and never interrogate. Anything they leave out gets a sensible default, and you say in your reply what you assumed.
- interests: any of beaches, waterfalls, islands, hiking, heritage, food, relaxed, adventure (surfing or diving is adventure, "chill" is relaxed, "history" or "old church" is heritage). If they say nothing, a balanced mix is used.
- places: places they ask you to include, copied EXACTLY from the PLACE NOTES below.
- perDay: places per day, 1 to 3. Slow is 1, normal is 2, packed is 3. Never ask about it; work it out from how they talk. Default 2. "just 3 stops", "only 2 stops" or "3 places" means that many PER DAY unless they say total or overall; always set perDay to that number, and say "per day" in your reply so they can correct you.
- name: only if they give the trip a name, in their exact words. Otherwise null, and Bud names it. Never ask for one.
- dayStart and dayEnd: the daily hours they want to be out and about, as 24-hour HH:MM. "8am to 5pm", "8-5" and "from 8 to 5" are "08:00" and "17:00"; "9 until 4 in the afternoon" is "09:00" and "16:00". Hours are NEVER trip dates: a pair of small numbers like "8-5" or "8 to 5", especially beside words such as itinerary, day, daily, hours or per day, is a time window and must not go into startDate or endDate. A date always names a month or is a day-month pair like "5 August" or "8/5/2027" with a year. If it could honestly be either, ask which they mean. Never ask for hours; take them only when volunteered, and keep them until the visitor changes them. Default null.

EDITING A TRIP THAT WAS ALREADY BUILT
Only when the text THIS TRIP WAS ALREADY BUILT appears below. Fill in "edit" so the program changes ONLY what the visitor asked about and keeps every other day exactly as it is. In every other situation leave edit empty: scope "", days [], removeDays [], removePlaces [], note "".
- scope "days": the change touches particular days. List their numbers in "days", numbered as on the trip on screen. "swap the beach on day 2 for a waterfall" is scope days, days [2]. "add Malatap Falls" with no day named is scope days, days [] (the program puts it on the day it fits best). "add a day" or "make it 4 days" is scope days, days [], and nights goes up to match: the new day is written and the existing days stay.
- scope "all": the change really affects every day, such as a new budget, a new interest, or a different number of stops a day ("make it cheaper", "more relaxed").
- scope "dates": only the dates or the daily hours move ("start a day later", "hours 8 to 5"). Nothing is rewritten; the days are re-dated or re-timed.
- removeDays: days to take out. "remove day 2" is removeDays [2] and nights goes down by one. With no day named, the last day is the one dropped.
- scope "new": they want a completely different trip, not a change to this one.
- note: their request in a short sentence, in your own words ("swap the beach for a waterfall").
- Never list a day the visitor did not ask to change. When unsure, choose the smaller change.
- Change the length through nights. Only set endDate if the visitor gave an end date themselves.
- removePlaces: places the visitor takes out or swaps away, copied EXACTLY from the PLACE NOTES. "swap Bagasbas Beach for the shrine" is removePlaces ["Bagasbas Beach"], and the shrine goes into brief.places. The program finds which day the removed place is on, so leave days [] unless the visitor named a day. NEVER guess a day number for a place swap.
- If the visitor takes a place out or swaps it for another, also remove the old one from brief.places so it is not put back.

HOW TO TALK
- This is a conversation, not a form. There is no list of questions to get through and no fixed order. Read what they said, answer it the way a person would, and let that decide what comes next.
- Take in everything they volunteer in one go, from this message and earlier ones. Never ask for something you already know.
- The conversation may begin with earlier turns from the normal chat, from before planning started. Use them: "include it", "that one" or "the same place" means whatever was just being talked about there.
- Ask about what you still need only when it fits the flow, and ask the way a friend would. You may ask one thing, or two things that belong together ("How many of you, and for how many nights?"), but never fire off a list. Plenty of replies need no question at all: answer what they asked, react, suggest something.
- If they ask about places, what to do, what suits kids or couples, or what you would suggest, answer properly and helpfully, using only the PLACE NOTES below. If something is not in the notes, say you are not sure rather than guessing. If they ask for fares, schedules, opening hours, entrance fees or directions, say you cannot check that from here, that you will build the plan, and that they can ask Bud in the normal chat for those details afterwards.
- If they seem unsure, help them decide, as a friend would: suggest a length, a feel for the trip, or a place from the notes.
- Do not hold the plan hostage. Once you have a start date, a length, the number of travellers and a budget, you have enough to build.
- WHEN TO SET "ready" TO TRUE. Only when ALL FOUR required things are known: start date, length, travellers and budget, each stated by the visitor (or in ALREADY WORKED OUT). If even one is missing, ready is false and you ask for it. "Just plan it" or "you decide" does NOT cover the missing required things: still ask for the group size and the budget, briefly. Once all four are known, ready is true straight away; the optional things (interests, places, hours) never hold it up, but you may ask about them in the same message as a missing required thing.
- When ready is true, your reply says in a sentence or two what you are building, including anything you assumed, and that you are on it. Do not describe the plan itself and do not state facts about places beyond naming one the visitor asked for.
- Keep brief up to date on EVERY turn, carrying forward what you already learned, and change it when they change their mind.

DATES
- Turn what they say ("this Friday", "next month on the 5th", "December 20") into YYYY-MM-DD using TODAY and the CALENDAR below. Never work out a weekday yourself: read it from the CALENDAR.
- If they give no year, use the next time that date comes round from TODAY (this year if it is still ahead, otherwise next year) and say the year in your reply so they can correct you.
- A trip cannot start before TODAY. If they give a date that has already passed, say so kindly, do not put it in brief, and ask what date they would like instead.
- A date that does not exist (31 February) is not a date: say so and ask again.
- You can plan up to 10 nights and for up to 60 people. If they ask for more, say so kindly and ask what would work.

OFF-TOPIC MESSAGES
- The visitor may say something that has nothing to do with the trip: a joke, a random question, the weather in another country, a coding question, how you are. ALWAYS answer it in your own words first, like a friendly person would, briefly. If it is something you cannot really help with here (outside Camarines Norte travel), say so lightly instead of ignoring it. Only then, if it fits, gently bring the chat back to the trip and what you still need. Never skip the answer to go straight to your question, and never reply with only a canned redirect.
- An off-topic message never changes brief and never makes ready true. Keep everything already worked out, and set "valid" to true.

VALID AND CANCEL
- "valid" is true for nearly everything: small talk, jokes, questions about places, "what do you suggest", changes of mind. Answer them like a person and, when it fits, bring the chat back to the trip. Set "valid" to false only when the message is gibberish you cannot make anything of, or a value that cannot work (zero or negative people, nights or budget, more than 10 nights, more than 60 people, a date that does not exist). When "valid" is false, do not change brief, do not set ready, and say in your own words what you could not use and what you need instead. Never say "valid" or "invalid" and never scold.
- If they want to stop (cancel, never mind, forget it), set "cancel" to true and reply warmly, saying they can ask again any time. "cancel" is only for clearly wanting to stop, not for confusion or off-topic chat.
- The visitor's first message may be a bare request ("can you make me an itinerary?") or a full description of the trip. Either way, respond to what they actually said: welcome them, and invite the details in an open, easy way (when they are thinking of going and for how long, or what kind of trip they have in mind) rather than reciting what you need. If they gave details, use them straight away.

VOICE
- Warm, natural, a little playful. Sound like a person from here, not a form or a brochure. Match their register: casual with casual, careful with careful, a little Taglish or Bikol if they write that way. "Madya" and "Dios mabalos" are welcome now and then, never overdone.
- Length follows the moment: a sentence or two for a quick exchange, a short paragraph when they ask for suggestions or tell you a lot. Never a wall of text. Plain text only: no markdown, no bullet lists. Separate short paragraphs with a blank line. At most one emoji.
- Use what they actually told you: their dates and weekday, the length in days and nights ("3 nights, so 4 days"), the group, their interests in their own words. Do not use stock openers ("Awesome!", "Great!", "Sure!", "Certainly!", "Noted", "Got it", "I understand", "Thank you for sharing"), do not repeat their message back to them, and do not word two replies the same way.
- Reply in the language the visitor writes in (English, Tagalog or Bikol).
- Never mention JSON, fields, a brief, or that you are collecting information.
TXT;

    /* ---- PLACE NOTES: what the model may say about a place ----
       This chat used to be forbidden from saying anything about a place,
       which is a big part of why it felt like a form. The site's own short
       description of each place is now in front of it, and it may talk
       about the places using ONLY that. The names are also how "include
       Malatap Falls" is matched exactly. */
    $pcPlaceNames = [];
    $pcPlaceNotes = [];
    $pcIslandNames = [];
    try {
        $pcDestFile = __DIR__ . '/../includes/destinations-data.php';
        if (is_file($pcDestFile)) {
            foreach ((array) require $pcDestFile as $d) {
                $n = trim((string) ($d['name'] ?? ''));
                if ($n === '') {
                    continue;
                }
                $pcPlaceNames[preg_replace('/[^a-z0-9]+/', '', strtolower($n))] = $n;
                if (bud_is_island($d)) {
                    $pcIslandNames[] = $n;
                }

                $town = trim((string) ($d['town'] ?? ''));
                $tag  = trim((string) ($d['tag'] ?? ''));
                $desc = trim((string) preg_replace('/\s+/', ' ', (string) ($d['desc'] ?? '')));
                $where = trim($town . ($tag !== '' ? ', ' . $tag : ''), ', ');

                $pcPlaceNotes[] = '- ' . $n . ($where !== '' ? ' (' . $where . ')' : '')
                                . ($desc !== '' ? ': ' . bud_cut($desc, 240) : '');
            }
        }
    } catch (Throwable $e) {
        error_log('[bud.php] place notes unavailable for the planning chat: ' . $e->getMessage());
    }

    if ($pcPlaceNames) {
        $instructions .= "\n\nPLACE NOTES. This site plans with these places only (exact name first). They are the "
            . "ONLY source for anything you say about a place, so talk about them freely, but add nothing that is "
            . "not here: no fares, schedules, opening hours, fees or directions, and no claims about weather or "
            . "crowds.\n"
            . implode("\n", $pcPlaceNotes)
            . "\n\nIf the visitor asks for a place that is not in these notes, do not add it to brief.places. "
            . "Say in a few words that you can only plan with the places on this site.";
    }

    /* ---- WHAT WAS ALREADY WORKED OUT ----
       The model re-reads the chat every turn, but only the last few
       messages are sent, and a relaxed chat can run longer than that.
       The browser sends back what this endpoint last said was known, and
       every value is checked again here before the model sees it. */
    $kn    = is_array($body['known'] ?? null) ? $body['known'] : [];
    $knOut = [];
    foreach (['startDate', 'endDate'] as $k) {
        $dt = is_string($kn[$k] ?? null) ? bud_parse_date($kn[$k], $budTz) : null;
        if ($dt !== null) {
            $knOut[$k] = $dt->format('Y-m-d');
        }
    }
    foreach (['nights' => [1, BUD_MAX_NIGHTS], 'travellers' => [1, 60], 'perDay' => [1, 3]] as $k => $range) {
        if (isset($kn[$k]) && is_numeric($kn[$k]) && (int) $kn[$k] >= $range[0] && (int) $kn[$k] <= $range[1]) {
            $knOut[$k] = (int) $kn[$k];
        }
    }
    if (in_array($kn['budget'] ?? null, ['budget', 'mid', 'comfortable'], true)) {
        $knOut['budget'] = $kn['budget'];
    }
    if (isset($kn['budgetAmount']) && is_numeric($kn['budgetAmount'])
        && (int) $kn['budgetAmount'] > 0 && (int) $kn['budgetAmount'] <= BUD_MAX_AMOUNT) {
        $knOut['budgetAmount'] = (int) $kn['budgetAmount'];
    }
    if (in_array($kn['budgetBasis'] ?? null, ['total', 'perPerson', 'perPersonPerDay'], true)) {
        $knOut['budgetBasis'] = $kn['budgetBasis'];
    }
    $knInterests = [];
    foreach (array_slice((array) ($kn['interests'] ?? []), 0, 8) as $x) {
        if (is_string($x)
            && in_array($x, ['beaches', 'waterfalls', 'islands', 'hiking', 'heritage', 'food', 'relaxed', 'adventure'], true)
            && !in_array($x, $knInterests, true)) {
            $knInterests[] = $x;
        }
    }
    if ($knInterests) {
        $knOut['interests'] = $knInterests;
    }
    $knPlaces = [];
    foreach (array_slice((array) ($kn['places'] ?? []), 0, 6) as $x) {
        $kx = is_string($x) ? preg_replace('/[^a-z0-9]+/', '', strtolower($x)) : '';
        if ($kx !== '' && isset($pcPlaceNames[$kx]) && !in_array($pcPlaceNames[$kx], $knPlaces, true)) {
            $knPlaces[] = $pcPlaceNames[$kx];
        }
    }
    if ($knPlaces) {
        $knOut['places'] = $knPlaces;
    }
    if (isset($kn['name']) && is_string($kn['name']) && trim($kn['name']) !== '') {
        $knOut['name'] = bud_cut(trim($kn['name']), 60);
    }
    [$knDs, $knDe] = bud_day_window($kn['dayStart'] ?? null, $kn['dayEnd'] ?? null);
    if ($knDs !== null) {
        $knOut['dayStart'] = $knDs;
        $knOut['dayEnd']   = $knDe;
    }

    /* The length is carried by nights. A stale end date left over from the
       trip being edited would override a new length ("add a day" would do
       nothing), so it is not shown to the model. */
    if (($body['rebuild'] ?? false) === true && isset($knOut['nights'])) {
        unset($knOut['endDate']);
    }

    if ($knOut) {
        $instructions .= "\n\nALREADY WORKED OUT EARLIER IN THIS CHAT (keep all of it in brief on every turn "
            . "unless the visitor changes it): " . json_encode($knOut, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /* A trip that was already built, and a visitor asking to change
           it. That is an edit, not a new interview: apply the change and
           build again, without asking everything over. */
        if (($body['rebuild'] ?? false) === true) {
            $instructions .= "\n\nTHIS TRIP WAS ALREADY BUILT AND THE VISITOR HAS SEEN IT. They are now asking to "
                . "change it. Apply their change to brief, keep everything else exactly as it is above, and set "
                . "ready to true straight away, with a one-sentence reply saying what you changed. Ask a question "
                . "only if the change is unclear. Earlier turns of this chat may contain a misunderstanding "
                . "(for example hours like 8-5 taken as a date): the details above are correct, so ignore any "
                . "date that did not come from the visitor, and treat hours like 8-5 as dayStart and dayEnd. "
                . "A new budget replaces the old one: put an exact amount in "
                . "budgetAmount with its budgetBasis. Do not ask whether there is anything else to add.";

        /* Show it the trip itself, so it can say WHICH days a change touches. */
        try {
            $pcPb = bud_plan_block($body['plan'] ?? null, $budTz, true);
            if ($pcPb !== '') {
                $instructions .= "\n\n" . $pcPb;
            }
        } catch (Throwable $e) {
            error_log('[bud.php] trip on screen unavailable to the planning chat: ' . $e->getMessage());
        }
        }
    }

    $instructions .= "\n\nTODAY is " . bud_label($budToday) . " (" . $budToday->format('Y-m-d') . ").";

    /* The server reads any "oct 28" style date itself and tells the model
       the answer, so the model does not have to do calendar work (and a
       small model that stumbles on it does not break the whole reply). */
    $pcRead = bud_read_date($message, $budToday, $budTz);
    if ($pcRead !== null) {
        if ($pcRead['past']) {
            $instructions .= "\n\nDATE READ BY THE SERVER FROM THE VISITOR'S LATEST MESSAGE: "
                . bud_label($pcRead['date']) . " (" . $pcRead['date']->format('Y-m-d') . "). That date has "
                . "ALREADY PASSED. Do not put it in brief. Tell them kindly and ask for a date from today onwards.";
        } else {
            $instructions .= "\n\nDATE READ BY THE SERVER FROM THE VISITOR'S LATEST MESSAGE: "
                . bud_label($pcRead['date']) . " (" . $pcRead['date']->format('Y-m-d') . "). Use exactly this "
                . "for startDate, unless the chat shows they gave it as the END of the trip. Do not recompute it.";
        }
    }

    /* A calendar, so the model reads a weekday off a list instead of
       computing one. Models get "what day is 21 November" wrong often
       enough that a confident wrong weekday would sit in the chat. */
    $calendar = [];
    for ($i = 0; $i < 45; $i++) {
        $day        = $budToday->modify('+' . $i . ' days');
        $calendar[] = $day->format('D j M Y') . ' = ' . $day->format('Y-m-d');
    }
    $instructions .= "\n\nCALENDAR (use it for weekday names; dates beyond it, do not name a weekday):\n"
                   . implode('; ', $calendar);

    if ($userTurns >= 9) {
        $instructions .= "\n\nTHIS CHAT HAS GONE ON LONG ENOUGH. Stop asking questions. As soon as you have a "
                       . "start date, a length, the number of travellers and a budget, set ready to true and say in the reply "
                       . "what you assumed for any optional detail. None of those four can be "
                       . "defaulted: if any is still missing, ask for it plainly and briefly, once more.";
    }

} else {

$instructions = BUD_INSTRUCTIONS
    . "\n\nToday is " . bud_label($budToday) . '.';   // Manila's today, as everywhere else
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
                        /* The site's own words, so notes can be about the
                           place instead of about "a lovely spot". */
                        'tag'  => trim((string) ($d['tag'] ?? '')),
                        'desc' => bud_cut(trim(preg_replace('/\s+/', ' ', (string) ($d['desc'] ?? ''))), 170),
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

    /* ---- PLACES THE VISITOR ASKED FOR BY NAME ----
       Matched against the full list BEFORE any budget cut and put back
       after it: "include Calaguas" on a tight budget is their call, not
       the model's. Capped at what the trip can hold. */
    $bMust     = [];
    $bMustPool = $budSlugs;
    foreach (array_slice((array) ($brief['places'] ?? []), 0, 12) as $x) {
        $k = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $x));
        if ($k === '') {
            continue;
        }
        foreach ($budSlugs as $slug => $d) {
            if (preg_replace('/[^a-z0-9]+/', '', strtolower((string) $d['name'])) === $k) {
                $bMust[$slug] = $slug;
                break;
            }
        }
    }
    $bMust = array_slice(array_values($bMust), 0, max(1, ($bNights + 1) * $bPerDay));

    /* ---- AN EDIT TO A TRIP THE VISITOR ALREADY HAS ----
       Until now every change rebuilt the whole trip, so asking to swap
       Day 2 also reshuffled Days 1 and 3. Now the browser sends back the
       trip on screen ('previous') and what the change touches ('edit'),
       and this decides, day by day, what is rewritten:

         - a day the change does not touch is copied across untouched
           (re-dated if the start moved, re-timed if the hours moved);
         - only the days that need it are written by the model;
         - a longer trip gets new days at the end, a shorter one loses
           its last days (or the day they named).

       The model is told which days to write, and the code below puts
       back anything it was not asked to change, so a stray rewrite of
       Day 1 cannot get through. Nothing from the browser is trusted:
       every stop is checked against the destination list. */
    $prevIn  = is_array($body['previous'] ?? null) ? $body['previous'] : null;
    $edIn    = is_array($body['edit'] ?? null) ? $body['edit'] : [];
    $edScope = in_array($edIn['scope'] ?? '', ['days', 'all', 'dates', 'new'], true) ? $edIn['scope'] : '';
    $total   = $bNights + 1;

    $prevDays = []; $base = []; $regen = []; $regenAll = false;
    $swapped = 0; $banned = []; $avoid = []; $mustTodo = $bMust; $editNote = ''; $prevName = ''; $prevSummary = ''; $windowChanged = false;

    if ($prevIn !== null && $edScope !== 'new') {
        foreach (array_slice(array_values((array) ($prevIn['days'] ?? [])), 0, BUD_MAX_NIGHTS + 1) as $pd) {
            $its = [];
            $pdItems = is_array($pd) ? (array) ($pd['items'] ?? []) : [];
            foreach (array_slice(array_values($pdItems), 0, 6) as $it) {
                if (!is_array($it)) { continue; }
                $slug = trim((string) ($it['destId'] ?? ''));
                if ($slug === '' || !isset($bMustPool[$slug])) { continue; }
                $its[] = [
                    'destId' => $slug,
                    'time'   => bud_parse_time($it['time'] ?? null) ?? '',
                    'end'    => bud_parse_time($it['end'] ?? null) ?? '',
                    'note'   => bud_cut(trim((string) ($it['note'] ?? '')), 200),
                ];
            }
            $prevDays[] = $its;
        }
        if (!array_filter($prevDays)) { $prevDays = []; }
    }

    if ($prevDays) {
        $prevName    = bud_cut(trim((string) ($prevIn['name'] ?? '')), 160);
        $prevSummary = bud_cut(trim((string) preg_replace('/\s*Heads up:.*$/s', '', (string) ($prevIn['summary'] ?? ''))), 400);
        $editNote    = bud_cut(trim((string) preg_replace('/[\x00-\x1f]+/', ' ', (string) ($edIn['note'] ?? ''))), 240);

        /* Days the visitor asked to take out ("remove day 2"). */
        $removed = [];
        foreach (array_slice((array) ($edIn['removeDays'] ?? []), 0, 10) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= count($prevDays)) { $removed[(int) $x] = true; }
        }
        $src = [];
        foreach ($prevDays as $i => $its) {
            if (!isset($removed[$i + 1])) { $src[] = $its; }
        }
        if (!$src) { $src = $prevDays; }   // never take out every day

        /* One entry per day of the NEW trip: the stops it already had, or
           null when it is a new day at the end. */
        for ($i = 0; $i < $total; $i++) {
            $base[$i] = isset($src[$i]) ? $src[$i] : null;
        }

        /* The days the change touches, numbered as in the NEW trip. */
        $edDays = [];
        foreach (array_slice((array) ($edIn['days'] ?? []), 0, 12) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= $total) { $edDays[(int) $x] = true; }
        }
        $edDays = array_keys($edDays);

        /* Places the visitor took out or swapped away. They are matched by
           name to the destination list, banned from every day the model
           writes, and any day that currently holds one is rewritten, so
           the swap lands on the right day even when the model named the
           wrong day or none. */
        foreach (array_slice((array) ($edIn['removePlaces'] ?? []), 0, 6) as $x) {
            $k = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $x));
            if ($k === '') { continue; }
            foreach ($budSlugs as $slug => $d) {
                if (preg_replace('/[^a-z0-9]+/', '', strtolower((string) $d['name'])) === $k) {
                    $banned[$slug] = true;
                    break;
                }
            }
        }
        if ($banned) {
            $bMust = array_values(array_diff($bMust, array_keys($banned)));
        }

        /* A direct one-for-one swap (the Swap button): the new place takes
           the old one's exact time slot and nothing else on the trip
           moves. No model is involved, so the times cannot be shuffled. */
        $swIn = is_array($edIn['swap'] ?? null) ? $edIn['swap'] : [];
        if ($swIn) {
            $fromK = preg_replace('/[^a-z0-9]+/', '', strtolower((string) ($swIn['from'] ?? '')));
            $toK   = preg_replace('/[^a-z0-9]+/', '', strtolower((string) ($swIn['to'] ?? '')));
            $fromSlug = null; $toSlug = null;
            foreach ($budSlugs as $slug => $d) {
                $nk = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $d['name']));
                if ($nk === $fromK) { $fromSlug = $slug; }
                if ($nk === $toK)   { $toSlug   = $slug; }
            }
            if ($toSlug !== null && !$bIslandsOk && bud_is_island($budSlugs[$toSlug])) {
                $bIslandAsked = true;   // an island swap is refused: not enough time or budget for it
                $toSlug = null;
            }
            if ($fromSlug !== null && $toSlug !== null && $fromSlug !== $toSlug) {
                $already = false;
                foreach ($base as $its) {
                    foreach ((array) $its as $b) { if ($b['destId'] === $toSlug) { $already = true; } }
                }
                if (!$already) {
                    foreach ($base as $i => $its) {
                        if (!is_array($its)) { continue; }
                        foreach ($its as $k => $b) {
                            if ($b['destId'] === $fromSlug) {
                                $base[$i][$k]['destId'] = $toSlug;
                                $base[$i][$k]['note']   = bud_cut((string) ($budSlugs[$toSlug]['desc'] ?? ''), 200);
                                $swapped = $i + 1;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        $pb = is_array($prevIn['brief'] ?? null) ? $prevIn['brief'] : [];
        [$pDs, $pDe]   = bud_day_window($pb['dayStart'] ?? null, $pb['dayEnd'] ?? null);
        $windowChanged = ($pDs !== $bDayStart) || ($pDe !== $bDayEnd);

        $prevStart    = bud_parse_date($prevIn['startDate'] ?? '', $budTz);
        $datesChanged = $prevStart !== null && $prevStart->format('Y-m-d') !== $bStart->format('Y-m-d');

        /* Only consulted when the browser did not say what the change
           touches: did the brief itself move (interests, pace, budget)? */
        $pbInterests = [];
        foreach ((array) ($pb['interests'] ?? []) as $x) {
            if (in_array($x, $allowedInterests, true)) { $pbInterests[$x] = true; }
        }
        $pbInterests  = array_keys($pbInterests);
        sort($pbInterests);
        $nowInterests = array_values(array_unique($bInterests));
        sort($nowInterests);
        $pbAmount = max(0, bud_parse_amount($pb['budgetAmount'] ?? 0));
        $budgetChanged = ($bAmount > 0 || $pbAmount > 0)
            ? ($pbAmount !== $bAmount || ($bAmount > 0 && ($pb['budgetBasis'] ?? 'total') !== $bBasis))
            : (isset($pb['budget']) && $pb['budget'] !== $bBudget);
        $briefChanged = ($pbInterests && $pbInterests !== $nowInterests)
                     || (isset($pb['perDay']) && (int) $pb['perDay'] !== $bPerDay)
                     || $budgetChanged;

        $basePresent = [];
        foreach ($base as $its) {
            foreach ((array) $its as $b) { $basePresent[$b['destId']] = true; }
        }
        $mustNew = [];
        foreach ($bMust as $s) {
            if (!isset($basePresent[$s])) { $mustNew[] = $s; }
        }

        $regenAll = $edScope === 'all' || ($edScope === '' && $briefChanged);
        if (!$regenAll) {
            foreach ($base as $i => $its) {
                if ($its === null) { $regen[$i] = true; }          // a new day at the end
            }
            foreach ($edDays as $dn) { $regen[$dn - 1] = true; }   // a day they named
            foreach ($base as $i => $its) {                        // a day holding a removed place
                foreach ((array) $its as $b) {
                    if (isset($banned[$b['destId']])) { $regen[$i] = true; break; }
                }
            }

            /* The model said "some days" but named none, nothing was added
               or removed, no place was asked for and no dates or hours moved:
               the change cannot be pinned to a day, so redo the trip rather
               than silently do nothing. */
            if (!$regen && !$swapped && $edScope === 'days' && !$mustNew && !$windowChanged
                && !$datesChanged && !$removed && $total >= count($prevDays)) {
                $regenAll = true;
                error_log('[bud.php] edit named no day and changed nothing else; rebuilding every day.');
            }
        }
        if ($regenAll) {
            for ($i = 0; $i < $total; $i++) { $regen[$i] = true; }
        }
        ksort($regen);

        /* "Completely change day 2": none of the places that day held may come
           back, otherwise the model hands the same stops back and nothing
           changes. Places the visitor named are exempt. */
        if (($edIn['fresh'] ?? false) === true && !$regenAll) {
            foreach (array_keys($regen) as $i) {
                foreach ((array) ($base[$i] ?? []) as $b) { $avoid[$b['destId']] = true; }
            }
            foreach ($bMust as $s) { unset($avoid[$s]); }
        }

        /* Places already sitting on a day that stays do not need asking for again. */
        $lockedSlugs = [];
        foreach ($base as $i => $its) {
            if (!isset($regen[$i])) {
                foreach ((array) $its as $b) { $lockedSlugs[$b['destId']] = true; }
            }
        }
        $mustTodo = [];
        foreach ($bMust as $s) {
            if (!isset($lockedSlugs[$s])) { $mustTodo[] = $s; }
        }
    } else {
        for ($i = 0; $i < $total; $i++) { $regen[$i] = true; }   // a new trip: every day is written
    }

    /* Nothing left for the model to write (only dates, hours or the
       length changed): skip the model call entirely. Instant and free. */
    $skipModel = (bool) ($prevDays && !$regen);

    /* At the lowest budget, boat-only places and resorts are taken off
       the list altogether. Telling the model to avoid them is not
       enough: it has no prices, so it picks an island because the
       visitor said "islands". Removing them also puts them out of the
       whitelist below. Only done when enough other places remain to
       fill the trip. */
    $bCostlyCut = 0;
    $bTooLow    = false;
    if ($mode === 'itinerary' && $budSlugs) {

        /* What the workbook knows: each place's "day budget" (a range,
           lower figure first) and whether any route to it is by boat.
           It covers only some destinations; the rest are judged by
           their tag and name. The figures are unverified, so they are
           used to leave places out, never quoted. */
        $bCost = [];
        $bCostFile = __DIR__ . '/../includes/bud-data.php';
        if (is_file($bCostFile)) {
            try {
                $bRows = (array) (require $bCostFile)['destinations'];
                foreach ($bRows as $r) {
                    $key = preg_replace('/[^a-z0-9]+/', '', strtolower((string) ($r['name'] ?? '')));
                    if ($key === '') { continue; }
                    $low = preg_match('/\d[\d,]*/', (string) ($r['budget_low'] ?? ''), $m)
                         ? (int) str_replace(',', '', $m[0]) : null;
                    $boat = false;
                    foreach ((array) ($r['routes'] ?? []) as $leg) {
                        if (stripos((string) ($leg['mode'] ?? ''), 'boat') !== false) { $boat = true; }
                    }
                    $bCost[$key] = ['low' => $low, 'boat' => $boat];
                }
            } catch (Throwable $e) {
                error_log('[bud.php] cost data unavailable: ' . $e->getMessage());
            }
        }
        $costOf = function ($d) use ($bCost) {
            $k = preg_replace('/[^a-z0-9]+/', '', strtolower((string) ($d['name'] ?? '')));
            return $bCost[$k] ?? ['low' => null, 'boat' => false];
        };

        /* 1. A day at the place costs more, at its lowest, than the
              visitor has per person per day. Only with an exact amount
              (a tier has no number to compare against). */
        if ($bAmount > 0) {
            $allowance = $bCalc['perPersonDay'];
            $within = array_filter($budSlugs, function ($d) use ($costOf, $allowance) {
                $c = $costOf($d);
                return $c['low'] === null || $c['low'] <= $allowance;
            });
            if (count($within) >= 6) {
                $bCostlyCut += count($budSlugs) - count($within);
                $budSlugs    = $within;
            } else {
                /* The budget is below what almost everything costs. The
                   old code skipped the filter here, so the plan came out
                   at full price with no warning. Keep the six cheapest
                   instead and say so. */
                $byCost = [];
                foreach ($budSlugs as $k => $d) {
                    $c = $costOf($d)['low'];
                    $byCost[$k] = $c === null ? 0 : $c;
                }
                asort($byCost);
                $keep = array_slice(array_keys($byCost), 0, 6);
                $bCostlyCut += count($budSlugs) - count($keep);
                $budSlugs    = array_intersect_key($budSlugs, array_flip($keep));
                $bTooLow     = true;
            }
        }

        /* 2. At the lowest tier, anything that needs a boat, and any
              island or resort, goes. The model has no prices, so it
              would pick an island because the visitor said "islands". */
        if ($bBudget === 'budget') {
            $cheap = array_filter($budSlugs, function ($d) use ($costOf) {
                return !$costOf($d)['boat']
                    && !preg_match(BUD_COSTLY_RE, ($d['tag'] ?? '') . ' ' . ($d['name'] ?? ''));
            });
            if (count($cheap) >= 6) {
                $bCostlyCut += count($budSlugs) - count($cheap);
                $budSlugs    = $cheap;
            }
        }
    }

    /* ISLAND RULE: when islands are not allowed they are removed from the
       list the model may choose from, and from the places the visitor named,
       so the "put back" step below cannot bring them back. */
    if (!$bIslandsOk) {
        foreach ($budSlugs as $slug => $d) {
            if (bud_is_island($d)) { unset($budSlugs[$slug]); }
        }
        $notIsland = function ($slug) use ($bMustPool) {
            return !(isset($bMustPool[$slug]) && bud_is_island($bMustPool[$slug]));
        };
        if (count(array_filter($bMust, $notIsland)) !== count($bMust)) { $bIslandAsked = true; }
        $bMust    = array_values(array_filter($bMust, $notIsland));
        $mustTodo = array_values(array_filter((array) $mustTodo, $notIsland));
    }

    /* Put back anything they named that a budget cut removed. */
    foreach ($bMust as $slug) {
        if (!isset($budSlugs[$slug])) {
            $budSlugs[$slug] = $bMustPool[$slug];
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
        if ($d['tag'] !== '')  { $line .= ' - ' . $d['tag']; }
        if ($d['desc'] !== '') { $line .= ' - about: ' . $d['desc']; }
        if (!empty($near[$slug])) {
            $line .= '  [near: ' . implode(', ', array_slice($near[$slug], 0, 6)) . ']';
        } else {
            $line .= '  [near: nothing else within 20km - give it its own day]';
        }
        $lines[] = $line;
    }
    $instructions .= "\n\nVALID DESTINATIONS - slug on the left, use it exactly:\n"
                   . implode("\n", $lines);

    if ($prevDays) {
        $descDay = function (array $its) use ($bMustPool): string {
            $p = [];
            foreach ($its as $b) {
                $p[] = $bMustPool[$b['destId']]['name'] . ' (' . $bMustPool[$b['destId']]['town'] . ')';
            }
            return $p ? implode('; ', $p) : 'no stops';
        };
        $ask = $editNote !== ''
            ? json_encode($editNote, JSON_UNESCAPED_UNICODE)
            : '"(no detail given: improve it within the trip details above)"';

        if ($regenAll) {
            $cur = [];
            foreach ($base as $i => $its) {
                if ($its) { $cur[] = 'Day ' . ($i + 1) . ': ' . $descDay($its); }
            }
            $instructions .= "\n\nEDIT OF AN EXISTING TRIP. The visitor already has this trip:\n" . implode("\n", $cur)
                . "\nThey want it changed: {$ask}\n"
                . "Write all {$days} days again, but KEEP every stop that still suits the request and replace only "
                . "what does not. Every day object must include its number, like { \"day\": 1, \"items\": [...] }. "
                . "Also return an updated one or two sentence summary of the whole trip.";
        } else {
            $writeNums = [];
            foreach (array_keys($regen) as $i) { $writeNums[] = $i + 1; }
            $keepLines = [];
            foreach ($base as $i => $its) {
                if (!isset($regen[$i]) && $its) { $keepLines[] = 'Day ' . ($i + 1) . ': ' . $descDay($its); }
            }
            $editRule = "- THIS IS AN EDIT TO A TRIP THE VISITOR ALREADY HAS (the trip is {$days} days long). Write ONLY "
                      . "these days: Day " . implode(', Day ', $writeNums) . ". The program keeps every other day exactly "
                      . "as it is, so do not output them. Each day you write is { \"day\": <its number>, \"items\": [...] }.";
            $instructions = str_replace("- Exactly {$days} days.", $editRule, $instructions);
            $instructions .= "\n\nEDIT OF AN EXISTING TRIP. What the visitor asked for: {$ask}\n"
                . ($keepLines
                    ? "DAYS THAT STAY AS THEY ARE (do not use any of these places in the days you write):\n"
                      . implode("\n", $keepLines) . "\n"
                    : '')
                . "Every rule above still applies to the days you write. Also return an updated one or two sentence "
                . "summary of the WHOLE trip, including the days that stay.";
        }

        if ($banned) {
            $banNames = [];
            foreach (array_keys($banned) as $bs) { $banNames[] = $budSlugs[$bs]['name'] . ' (' . $bs . ')'; }
            $instructions .= "\n\nTHE VISITOR TOOK THESE PLACES OUT. Do not use them anywhere in the days you write: "
                . implode('; ', $banNames) . ". On a day that held one of them, keep that day's other stops and "
                . "replace only the removed place with what the visitor asked for.";
        }
    }

    if ($avoid) {
        $avoidNames = [];
        foreach (array_keys($avoid) as $as) { $avoidNames[] = $bMustPool[$as]['name'] . ' (' . $as . ')'; }
        $instructions .= "\n\nTHE VISITOR WANTS THE DAYS YOU WRITE COMPLETELY CHANGED. These are the places those days "
            . "held before, so do NOT use any of them: " . implode('; ', $avoidNames) . ". Choose different places "
            . "from the list.";
    }

    if ($bBudget === 'budget' && $bCostlyCut > 0) {
        $instructions .= "\n\nBUDGET RULE: this is a tight budget, so islands and resorts (which need a "
            . "hired boat or cost far more) have been left off the list. Plan free or low-cost places "
            . "reached by jeep or tricycle.";
        if (in_array('islands', $bInterests, true)) {
            $instructions .= " The visitor said they like islands: choose the closest alternatives "
                . "(beaches and coastal places) and say in ONE short sentence in the summary that island "
                . "trips need a boat and cost more than this budget allows.";
        }
    } elseif ($bBudget === 'mid') {
        $instructions .= "\n\nBUDGET RULE: include at most ONE island or resort in the whole trip, because "
            . "boat hire and resorts cost much more than the other places.";
    }

    if (!$bIslandsOk) {
        $instructions .= "\n\nISLAND RULE: islands are not on the list for this trip (an island needs at least 5 hours "
            . "and a budget of P" . number_format(BUD_ISLAND_MIN_BUDGET) . " or more per person for the whole trip, and this trip does not "
            . "have that). Build the plan from beaches, waterfalls and the other places on the list.";
        if ($bIslandAsked) {
            $instructions .= " The visitor wanted islands: do not mention any island by name; say in ONE short sentence in "
                . "the summary that island trips need more time and budget than this trip has, because boats are often "
                . "priced per boat rather than per person and food and fees add up.";
        }
    } else {
        $instructions .= "\n\nISLAND RULE: an island stop needs at least 5 hours, so give an island at least a 5 hour "
            . "visit and at most ONE island a day. Keep that day to the island, or add one short stop beside it only if "
            . "the day's hours leave room.";
    }

    if ($bTooLow) {
        $instructions .= "\n\nBUDGET RULE: the visitor's budget is below what most places on this list cost, "
            . "so only the cheapest ones are listed. Say in ONE short sentence in the summary that the budget is tight.";
    }

    if ($mustTodo) {
        $mustNames = [];
        foreach ($mustTodo as $slug) {
            $mustNames[] = $slug . ' (' . $budSlugs[$slug]['name'] . ')';
        }
        $instructions .= "\n\nMUST INCLUDE: the visitor asked for these by name: " . implode(', ', $mustNames)
            . ". Each one must appear as a stop exactly once. This overrides the budget rule and the "
            . "interests: build the days around them, and keep every day inside the nearby lists above.";
    }

    /* The trip's own dates against the workbook: best months, hours,
       permits, and any festival that overlaps. Best-effort. If the
       function or the data file is missing, or anything throws, the
       plan is built exactly as it was before this was added. */
    if (function_exists('bud_trip_facts')) {
        try {
            $tripFacts = bud_trip_facts(__DIR__ . '/../includes/bud-data.php', $budSlugs, $bStart, $bEnd);
            if ($tripFacts !== '') {
                $instructions .= "\n\n" . $tripFacts;
            }
        } catch (Throwable $e) {
            error_log('[bud.php] trip facts unavailable: ' . $e->getMessage());
        }
    }
}

/* The fees-and-routes block is for CHAT. Itinerary mode is expressly
   forbidden from writing prices — they are attached afterwards from
   the database — so sending several thousand tokens of them is paying
   to tell the model something it must not use. The slug list above is
   all it needs. */
if ($mode === 'itinerary' || $mode === 'plan_chat') {
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
} elseif ($mode === 'chat') {
    /* CHAT only. This used to be "anything but plan_chat", which also
       caught itinerary mode, where $context is emptied on purpose. Every
       plan prompt therefore ended with "you have NO destination list
       loaded, do not name any destination", straight after the list.

       No list at all. Rather than let Bud fall back on training
       knowledge and start recommending places nobody has checked,
       tell it plainly that it has nothing and must say so. */
    $instructions .= "\n\nYou currently have NO destination list loaded. "
        . "Do not name or describe any specific destination. Say the "
        . "listings are unavailable right now and refer the visitor to "
        . "the Provincial Tourism Office.";
}

/* The trip the visitor is looking at, so a follow-up is about THAT trip. */
if ($mode === 'chat') {
    try {
        $planBlock = bud_plan_block($body['plan'] ?? null, $budTz);
        if ($planBlock !== '') {
            $instructions .= "\n\n" . $planBlock;
        }
    } catch (Throwable $e) {
        error_log('[bud.php] current trip unavailable to the chat: ' . $e->getMessage());
    }
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

if ($apiKey === '' && !$skipModel) {
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
    'maxOutputTokens' => $mode === 'itinerary' ? 8000 : BUD_MAX_OUTPUT_TOKENS,
    'temperature'     => 0.4,   // steady and factual, not chatty
];

/* thinkingLevel is a GEMINI 3 field. The 2.x models use a different
   parameter entirely and reject this one, failing the whole request
   with a 400 — so it only goes to models that understand it. There is
   a retry further down as a second line of defence, but not sending a
   field the model cannot read beats recovering from it. */
/* The planning chat returns a small JSON object, never a long answer.
   Asking for JSON outright is more reliable than asking nicely. */
if ($mode === 'plan_chat') {
    $genConfig['maxOutputTokens']  = 4000;  // thinking tokens share this; 1000 let the JSON get cut off mid-object
    $genConfig['responseMimeType'] = 'application/json';

    /* A schema makes Gemini return exactly this shape every time. Without
       it, a reply that wandered, came back as prose, or dropped the
       "reply" key was unreadable, and the visitor got "I lost my train of
       thought" instead of an answer. "reply" is listed first on purpose:
       the model writes it before anything else, so a cut-off still has it. */
    $genConfig['responseSchema'] = [
        'type' => 'OBJECT',
        'propertyOrdering' => ['reply', 'valid', 'ready', 'cancel', 'lengthStated', 'travellersStated', 'budgetStated', 'brief', 'edit'],
        'properties' => [
            'reply'        => ['type' => 'STRING'],
            'valid'        => ['type' => 'BOOLEAN'],
            'ready'        => ['type' => 'BOOLEAN'],
            'cancel'       => ['type' => 'BOOLEAN'],
            'lengthStated' => ['type' => 'BOOLEAN'],
            'travellersStated' => ['type' => 'BOOLEAN'],
            'budgetStated' => ['type' => 'BOOLEAN'],
            'brief'        => [
                'type' => 'OBJECT',
                'propertyOrdering' => ['startDate', 'endDate', 'travellers', 'nights', 'perDay', 'budget',
                                       'budgetAmount', 'budgetBasis', 'interests', 'places', 'name',
                                       'dayStart', 'dayEnd'],
                'properties' => [
                    'startDate'    => ['type' => 'STRING',  'nullable' => true],
                    'endDate'      => ['type' => 'STRING',  'nullable' => true],
                    'travellers'   => ['type' => 'INTEGER', 'nullable' => true],
                    'nights'       => ['type' => 'INTEGER', 'nullable' => true],
                    'perDay'       => ['type' => 'INTEGER', 'nullable' => true],
                    'budget'       => ['type' => 'STRING',  'nullable' => true],
                    'budgetAmount' => ['type' => 'NUMBER',  'nullable' => true],
                    'budgetBasis'  => ['type' => 'STRING',  'nullable' => true],
                    'interests'    => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'places'       => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'name'         => ['type' => 'STRING',  'nullable' => true],
                    'dayStart'     => ['type' => 'STRING',  'nullable' => true],
                    'dayEnd'       => ['type' => 'STRING',  'nullable' => true],
                ],
            ],
            'edit'         => [
                'type' => 'OBJECT',
                'properties' => [
                    'scope'      => ['type' => 'STRING', 'nullable' => true],
                    'days'       => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                    'removeDays' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                    'removePlaces' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'note'       => ['type' => 'STRING', 'nullable' => true],
                ],
            ],
        ],
        'required' => ['reply'],
    ];
}

if (strpos(BUD_MODEL, 'gemini-3') === 0) {
    /* Chat and the planning chat need almost no reasoning, and every
       thinking token is time the visitor waits. The itinerary is the one
       job that benefits from a little thought (keeping days near each
       other, fitting hours). */
    $genConfig['thinkingConfig'] = ['thinkingLevel' => $mode === 'itinerary' ? 'low' : 'minimal'];
}

$payload = [
    'system_instruction' => [
        'parts' => [['text' => $instructions]],
    ],
    'contents'         => $input,
    'generationConfig' => $genConfig,
];

/* Pull the JSON object out of a model reply: strips a code fence and
   takes first brace to last brace. Returns the decoded array or null. */
if (!function_exists('bud_extract_obj')) {
function bud_extract_obj(string $reply): ?array {
    $json = trim($reply);
    if (strpos($json, '```') !== false) {
        $json = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $json);
    }
    $open  = strpos($json, '{');
    $close = strrpos($json, '}');
    if ($open !== false && $close !== false && $close > $open) {
        $json = substr($json, $open, $close - $open + 1);
    }
    $out = json_decode($json, true);
    /* Some replies arrive wrapped in a one-item list: [ {...} ]. */
    if (is_array($out) && !isset($out['reply']) && isset($out[0]) && is_array($out[0])) {
        $out = $out[0];
    }
    return is_array($out) ? $out : null;
}
}

/* Last resort for a reply that is cut off or slightly malformed: recover
   just the "reply" text, so the visitor still gets an answer. */
if (!function_exists('bud_salvage_reply')) {
function bud_salvage_reply(string $reply): string {
    if (preg_match('/"reply"\s*:\s*("(?:[^"\\]|\\.)*")/s', $reply, $m)) {
        $v = json_decode($m[1], true);
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
    }
    return '';
}
}

/* One helper for the call, so the retry below does not need a second
   copy. gzip and HTTP/2 shave a little off every round trip. */
function bud_gemini_call(array $payload, string $apiKey): array {
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/'
        . BUD_MODEL . ':generateContent');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => BUD_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',                       // accept gzip
        CURLOPT_HTTP_VERSION   => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1,
        CURLOPT_TCP_KEEPALIVE  => 1,
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
    return [$response, $status, $curlErr];
}

/* An edit that only moves dates, hours or the length has nothing to ask the model. */
if (!$skipModel) {
$budT0 = microtime(true);
[$response, $status, $curlErr] = bud_gemini_call($payload, $apiKey);

/* thinkingConfig is a Gemini 3 feature, and not every level exists on
   every model. A model that does not know it rejects the WHOLE request
   with a 400 rather than ignoring the field. Drop it and send once more. */
if ($status === 400 && isset($payload['generationConfig']['thinkingConfig'])
    && stripos((string) $response, 'thinking') !== false) {

    error_log('[bud.php] Model rejected thinkingConfig; retrying without it.');
    unset($payload['generationConfig']['thinkingConfig']);
    [$response, $status, $curlErr] = bud_gemini_call($payload, $apiKey);
}

/* If Google rejects the response schema, send once more without it
   rather than fail the whole turn. The JSON parsing below still copes. */
if ($status === 400 && isset($payload['generationConfig']['responseSchema'])) {
    error_log('[bud.php] Schema rejected (' . substr((string) $response, 0, 300) . '); retrying without it.');
    unset($payload['generationConfig']['responseSchema']);
    [$response, $status, $curlErr] = bud_gemini_call($payload, $apiKey);
}

/* Timing line: shows in the PHP error log so you can see where the
   seconds go (mode, seconds, prompt size in characters). */
error_log('[bud.php] mode=' . $mode . ' gemini=' . round(microtime(true) - $budT0, 2)
    . 's prompt=' . strlen($instructions) . ' chars status=' . $status);

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
} else {
    $reply  = '{}';
    $finish = 'STOP';
    $budT0  = microtime(true);
}

/* ===================================================================
   PLANNING CHAT: parse, clamp, answer.

   The model says what it thinks it knows; this decides whether that is
   actually enough. "ready" is only believed when the three required
   details are really present and valid, because a model that says
   "putting your plan together" with nothing to build from would send
   the browser off to generate a plan from guesses.
   =================================================================== */
if ($mode === 'plan_chat') {

    $out = bud_extract_obj($reply);

    /* An unreadable reply is usually a one-off (cut off, empty, or a stray
       character), so ask again, up to twice, with a bigger output ceiling
       (thinking tokens share it) and a calmer temperature. */
    for ($pcTry = 1; $pcTry <= 2; $pcTry++) {
        if (($out !== null && trim((string) ($out['reply'] ?? '')) !== '')
            || (microtime(true) - $budT0) > 40) {
            break;
        }
        error_log('[bud.php] plan_chat unparseable (finish=' . ($finish ?: '?') . '), retry ' . $pcTry
            . '. First 400 chars: ' . substr($reply, 0, 400));
        $payload['generationConfig']['temperature']     = 0.2;
        $payload['generationConfig']['maxOutputTokens'] = 8000;
        [$response2, $status2] = bud_gemini_call($payload, $apiKey);
        $data2 = json_decode((string) $response2, true);
        if ($status2 === 200 && is_array($data2)) {
            $reply2 = '';
            foreach ($data2['candidates'][0]['content']['parts'] ?? [] as $part) {
                if (isset($part['text'])) { $reply2 .= $part['text']; }
            }
            $reply2 = trim($reply2);
            $out2   = bud_extract_obj($reply2);
            if ($out2 !== null && trim((string) ($out2['reply'] ?? '')) !== '') {
                $out   = $out2;
                $reply = $reply2;
            } elseif ($reply2 !== '') {
                $reply = $reply2;
            }
            $finish = $data2['candidates'][0]['finishReason'] ?? $finish;
        }
    }

    /* The model answered in plain prose with no JSON at all. That is
       still an answer: show it instead of an error. */
    if (($out === null || trim((string) ($out['reply'] ?? '')) === '')
        && $reply !== '' && strpos($reply, '{') === false && strpos($reply, '[') !== 0) {
        $out = ['reply' => $reply, 'valid' => true, 'ready' => false, 'cancel' => false, 'brief' => []];
    }

    /* The model failed, but the visitor just NAMED places and the start and
       length are already known from earlier turns. Nothing else is missing,
       so do not make them repeat themselves: take the places straight from
       their message (matched against the site's own list) and build. */
    if (($out === null || trim((string) ($out['reply'] ?? '')) === '')
        && isset($knOut['startDate']) && (isset($knOut['nights']) || isset($knOut['endDate']))
        && isset($knOut['travellers']) && (isset($knOut['budget']) || isset($knOut['budgetAmount']))) {
        $fbMsg    = preg_replace('/[^a-z0-9]+/', '', strtolower($message));
        $fbPlaces = $knOut['places'] ?? [];
        foreach ($pcPlaceNames as $pk => $pn) {
            if ((string) $pk !== '' && strpos($fbMsg, (string) $pk) !== false && !in_array($pn, $fbPlaces, true)) {
                $fbPlaces[] = $pn;
            }
        }
        if ($fbPlaces && count($fbPlaces) > count($knOut['places'] ?? [])) {
            $fbBrief           = $knOut;
            $fbBrief['places'] = $fbPlaces;
            $out = [
                'reply'        => 'On it. I will build your trip around ' . implode(' and ', $fbPlaces) . '.',
                'valid'        => true,
                'ready'        => true,
                'cancel'       => false,
                'lengthStated' => true,
                'brief'        => $fbBrief,
            ];
            error_log('[bud.php] plan_chat model failed; built from known details plus places named in the message.');
        }
    }

    /* Still no usable object: keep the conversation going with whatever
       reply text can be recovered. Nothing is marked ready. */
    if ($out === null || trim((string) ($out['reply'] ?? '')) === '') {
        $saved = bud_salvage_reply($reply);
        if ($saved !== '') {
            bud_out(200, ['reply' => bud_cut($saved, 900), 'ready' => false, 'cancel' => false]);
        }
    }

    /* The model failed every time. If the visitor's message at least held a
       readable date, answer from that instead of an error, and carry the
       date forward so nothing they said is lost. */
    if (!is_array($out) || trim((string) ($out['reply'] ?? '')) === '') {
        $fbRead = $pcRead ?? bud_read_date($message, $budToday, $budTz);
        if ($fbRead !== null) {
            $fbLabel = bud_label($fbRead['date']);
            if ($fbRead['past']) {
                bud_out(200, ['reply' => $fbLabel . ' has already gone by, since today is ' . bud_label($budToday)
                    . '. What start date would you like instead? If you meant next year, just say so.',
                    'ready' => false, 'cancel' => false, 'known' => $knOut]);
            }
            $knOut['startDate'] = $fbRead['date']->format('Y-m-d');
            $fbHasLen = isset($knOut['nights']) || isset($knOut['endDate']);
            $fbHasAll = $fbHasLen && isset($knOut['travellers']) && (isset($knOut['budget']) || isset($knOut['budgetAmount']));
            bud_out(200, ['reply' => $fbLabel . ' works. ' . (!$fbHasLen
                    ? 'How many days are you planning to stay with us?'
                    : 'Anything else you want me to know?'),
                'ready' => false, 'cancel' => false, 'known' => $knOut]);
        }
    }

    if (!is_array($out) || trim((string) ($out['reply'] ?? '')) === '') {
        bud_fail(502, 'Sorry, I lost my train of thought. Could you say that again?',
            'plan_chat unparseable (finish=' . ($finish ?: '?') . '). First 400 chars: '
            . substr($reply, 0, 1500));
    }

    $say = bud_cut(trim((string) $out['reply']), 900);

    /* The model's own paragraphs are kept. A single line break becomes a
       blank line so each paragraph reads on its own, and nothing else is
       reshaped: the old rules that cut out "(for example ...)" and split
       the last question onto its own line made every reply the same
       shape. */
    $say = trim(preg_replace('/[ \t]*\R[ \t]*/u', "\n", $say));
    $say = preg_replace('/\n{2,}/', "\n\n", str_replace("\n", "\n\n", $say));

    if (!empty($out['cancel'])) {
        bud_out(200, ['reply' => $say, 'ready' => false, 'cancel' => true]);
    }

    $b = is_array($out['brief'] ?? null) ? $out['brief'] : [];

    $pcTravellers = (int) ($b['travellers'] ?? 0);
    $pcNights     = (int) ($b['nights'] ?? 0);

    $pcAllowed   = ['beaches', 'waterfalls', 'islands', 'hiking',
                    'heritage', 'food', 'relaxed', 'adventure'];
    /* Places they asked for by name. The model copies an exact name from
       the list it was given; this checks that against the same list, and
       also takes a partial name that matches only one place ("Malatap"),
       so a slightly loose answer still lands. Anything else is dropped. */
    $pcPlaces = [];
    foreach (array_slice((array) ($b['places'] ?? []), 0, 12) as $x) {
        $k = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $x));
        if ($k === '' || empty($pcPlaceNames)) {
            continue;
        }
        $hit = $pcPlaceNames[$k] ?? null;
        if ($hit === null && strlen($k) >= 5) {
            $cands = [];
            foreach ($pcPlaceNames as $pk => $pn) {
                if (strpos((string) $pk, $k) !== false) {
                    $cands[] = $pn;
                }
            }
            $hit = count($cands) === 1 ? $cands[0] : null;
        }
        if ($hit !== null && !in_array($hit, $pcPlaces, true)) {
            $pcPlaces[] = $hit;
        }
    }
    $pcPlaces = array_slice($pcPlaces, 0, 6);

    $pcInterests = [];
    foreach ((array) ($b['interests'] ?? []) as $x) {
        if (in_array($x, $pcAllowed, true) && !in_array($x, $pcInterests, true)) {
            $pcInterests[] = $x;
        }
    }

    /* Budget and name are ASKED now, so "not answered yet" has to be
       told apart from "answered, no preference". Budget: a valid value
       means answered. Name: null means not asked yet, "" means they
       skipped it and Bud will name the trip. */
    $pcTierGiven   = in_array($b['budget'] ?? null, ['budget', 'mid', 'comfortable'], true);

    /* An exact amount in pesos, as an alternative to a tier. */
    $rawAmount     = $b['budgetAmount'] ?? null;
    $pcParsed      = bud_parse_amount($rawAmount);
    $pcAmountNeg   = $pcParsed < 0;
    $pcAmount      = $pcAmountNeg ? 0 : $pcParsed;
    $pcBasisGiven  = in_array($b['budgetBasis'] ?? null, ['total', 'perPerson', 'perPersonPerDay'], true);
    $pcBasis       = $pcBasisGiven ? $b['budgetBasis'] : 'total';

    /* One traveller: "total" and "per person" are the same thing, so
       there is nothing to ask. More than one: the amount is not usable
       until we know what it covers. */
    if ($pcAmount > 0 && !$pcBasisGiven && $pcTravellers === 1) {
        $pcBasis      = 'perPerson';
        $pcBasisGiven = true;
    }

    $pcBudgetGiven = $pcTierGiven || ($pcAmount > 0 && $pcBasisGiven);
    $pcBudget      = $pcTierGiven ? $b['budget'] : 'mid';
    $pcPerDay      = ($b['perDay'] ?? null) === null ? 2 : max(1, min(3, (int) $b['perDay']));
    $pcNameGiven   = array_key_exists('name', $b) && $b['name'] !== null;
    $pcName        = $pcNameGiven ? bud_cut(trim((string) $b['name']), 60) : '';
    [$pcDayStart, $pcDayEnd] = bud_day_window($b['dayStart'] ?? null, $b['dayEnd'] ?? null);

    /* ---- the dates ----
       The model reads the dates out of the conversation. THIS decides
       whether they are acceptable, because a model will cheerfully
       accept "last Friday". */
    $pcStart   = bud_parse_date($b['startDate'] ?? '', $budTz);
    $pcEndIn   = bud_parse_date($b['endDate'] ?? '', $budTz);
    $dateError = '';

    /* ---- THE LENGTH MUST BE THE VISITOR'S, NOT THE MODEL'S ----
       A model told "use sensible defaults" will happily write
       nights: 2 when nobody said so, and then announce it in the reply.
       So a length counts only when the model says the visitor stated it,
       when a stated length was already carried over from an earlier turn,
       or when the visitor is editing a trip that already exists. Anything
       else is thrown away here. */
    $pcLengthStated = !empty($out['lengthStated'])
        || (($body['rebuild'] ?? false) === true)
        || isset($knOut['nights'])
        || isset($knOut['endDate']);

    if (!$pcLengthStated) {
        $pcNights = 0;
        $pcEndIn  = null;
    } elseif ($pcNights < 1 && $pcEndIn === null && isset($knOut['nights'])) {
        $pcNights = (int) $knOut['nights'];   // the model dropped it; keep what was stated
    }

    /* ---- TRAVELLERS AND BUDGET MUST ALSO BE THE VISITOR'S ----
       Same rule as the length. The model used to write travellers: 2
       and budget: mid when nobody said so, announce it ("I'll plan this
       for two people with a mid-range budget") and build. Now they count
       only when the model says the visitor stated them, or they were
       already carried over from an earlier turn. */
    $pcTravStated = !empty($out['travellersStated']) || isset($knOut['travellers']);
    $pcBudStated  = !empty($out['budgetStated'])
                    || isset($knOut['budget']) || isset($knOut['budgetAmount']);
    if (!$pcTravStated) {
        $pcTravellers = 0;
    } elseif ($pcTravellers < 1 && isset($knOut['travellers'])) {
        $pcTravellers = (int) $knOut['travellers'];
    }
    if (!$pcBudStated) {
        $pcTierGiven  = false;
        $pcAmount     = 0;
        $pcBasisGiven = false;
    } elseif (!$pcTierGiven && $pcAmount < 1 && isset($knOut['budget'])) {
        $pcTierGiven = true;
        $b['budget'] = $knOut['budget'];
    } elseif (!$pcTierGiven && $pcAmount < 1 && isset($knOut['budgetAmount'])) {
        $pcAmount     = (int) $knOut['budgetAmount'];
        $pcBasis      = $knOut['budgetBasis'] ?? 'total';
        $pcBasisGiven = isset($knOut['budgetBasis']);
    }
    if ($pcAmount > 0 && !$pcBasisGiven && $pcTravellers === 1) {
        $pcBasis      = 'perPerson';
        $pcBasisGiven = true;
    }
    $pcBudgetGiven = $pcTierGiven || ($pcAmount > 0 && $pcBasisGiven);
    $pcBudget      = $pcTierGiven ? $b['budget'] : 'mid';
    $pcTravOk      = $pcTravellers >= 1;

    if ($pcStart !== null) {
        $dateError = bud_check_start($pcStart, $budToday);
    }

    if ($dateError === '' && $pcStart !== null && $pcEndIn !== null) {
        if ($pcEndIn < $pcStart) {
            $dateError = 'The end date (' . bud_label($pcEndIn) . ') is before the start date ('
                       . bud_label($pcStart) . '). Could you check the dates?';
        } else {
            /* A given end date decides the length. */
            $pcNights = (int) $pcStart->diff($pcEndIn)->days;
        }
    }

    /* Impossible lengths and party sizes are INVALID, said plainly.
       Silently trimming "40 nights" to 14 would plan a trip nobody
       asked for. */
    if ($dateError === '' && $pcNights > BUD_MAX_NIGHTS) {
        $dateError = 'That is too many nights (' . $pcNights . '). I can plan up to ' . BUD_MAX_NIGHTS
                   . ' nights at a time. Please give a valid trip length.';
    }

    $rawN = $b['nights'] ?? null;
    if ($dateError === '' && $pcEndIn === null && $rawN !== null && $rawN !== '' && (int) $rawN < 0) {
        $dateError = 'That is not a valid number of nights. Please give me at least 1 night.';
    }

    if ($dateError === '' && $pcAmountNeg) {
        $dateError = 'That is not a valid budget. Please give an amount above zero, in pesos.';
    } elseif ($dateError === '' && $pcAmount > BUD_MAX_AMOUNT) {
        $dateError = 'That budget is too large for me to plan around. Please give a realistic amount in pesos.';
    }

    $rawT = $b['travellers'] ?? null;
    if ($dateError === '' && $rawT !== null && $rawT !== '') {
        /* 0 is read as "not given yet": models often write 0 for
           unknown. An explicit "0 people" is caught by the valid flag. */
        if ((int) $rawT < 0) {
            $dateError = 'That is not a valid number of travellers. Please give me at least 1.';
        } elseif ((int) $rawT > 60) {
            $dateError = 'That is too many travellers. I can plan for 1 to 60 people. Please give a valid number.';
        }
    }

    /* Did the model judge the visitor's last message to be a real
       answer? Only an explicit false counts as invalid. */
    $answerValid = !array_key_exists('valid', $out) || $out['valid'] !== false;

    $pcHasDate = $pcStart !== null && $dateError === '';

    /* Only two things cannot be defaulted: a valid start date and a trip
       length. Group size, budget, interests and a name are filled in
       below if the visitor never said, and the reply says what was
       assumed. This used to demand all six before it would build, which
       is what made the chat feel like a form. */
    $complete = $pcHasDate && $pcNights >= 1 && $pcTravOk && $pcBudgetGiven;
    $force    = $userTurns >= 9;

    /* Long chat and still no length: take a short stay and carry on.
       The date is the one thing that cannot be defaulted. */
    /* (No forced default here any more: a length nobody gave is asked
       for, never invented. See $pcLengthStated above.) */

    $ready = !empty($out['ready']) && $complete;

    if ($dateError !== '') {
        /* An invalid value always wins over whatever the model said. */
        $say   = $dateError;
        $ready = false;
    } elseif (!$answerValid) {
        /* Small talk, gibberish or a value that cannot work. The prompt
           has the model answer in its own words, so nothing is added. */
        $ready = false;
    } elseif (!empty($out['ready']) && !$complete) {
        /* It announced the plan without a date or a length. Ask plainly,
           from a few wordings, rather than build from nothing. */
        if (!$pcHasDate) {
            $asks = ['What date does your trip start?',
                     'Which day are you thinking of starting?',
                     'When would you like the trip to begin?'];
        } elseif ($pcNights >= 1 && (!$pcTravOk || !$pcBudgetGiven)) {
            $asks = null;   // the model writes this question itself, see below
        } else {
            $asks = ['How many nights are you staying, or what is your end date?',
                     'For how long are you going? Nights, or an end date, whichever is easier.',
                     'How long should the trip be?'];
        }
        if ($asks !== null) {
            $say = $asks[array_rand($asks)];
        }
    } elseif ($pcHasDate && $pcNights < 1
        && preg_match('/(\d+|a|one|two|three|four|five|couple|few|several)\s+(of\s+)?(nights?|days?)\b|weekend|overnight/i', $say)) {
        /* The date is known, the length is not, and the reply still talks
           as if it were ("for a couple of nights"). The visitor must not
           be told a length they never gave: replace it with a question
           that keeps what WAS understood. */
        $lead = 'Starting ' . bud_label($pcStart) . '. ';
        if ($pcPlaces) {
            $lead .= implode(' and ', $pcPlaces) . ' will be on it. ';
        }
        $asks = [
            $lead . 'How many days are you planning to stay?',
            $lead . 'For how long are you going? Tell me the number of days or nights, or an end date.',
            $lead . 'How long will you be staying, in days or nights?',
        ];
        $say = $asks[array_rand($asks)];
    }

    /* Date and length are known but the group size or budget is not. The
       reply must ASK for them. If the model's reply assumed them, asked
       nothing, or announced the build, the MODEL is asked again, with a
       note on what is missing, and writes the question in its own words.
       No canned sentence is used. */
    if ($dateError === '' && $answerValid && $pcHasDate && $pcNights >= 1
        && (!$pcTravOk || !$pcBudgetGiven)) {
        $assumes = preg_match('/\b(for|plan(?:ning)? this for|build(?:ing)? this for)\s+(\d+|one|two|three|four|five|six|a couple of|a pair of)\s+(people|persons?|travell?ers|pax|of you|adults)\b/i', $say)
                || preg_match('/\b(mid-?range|mid-?level|moderate|comfortable|tight|low|average)\s+budget\b|\bbudget\s+(level|tier)\b/i', $say);
        if (!empty($out['ready']) || $assumes || strpos($say, '?') === false) {
            $ready = false;
            $miss  = [];
            if (!$pcTravOk)      { $miss[] = 'how many people are travelling'; }
            if (!$pcBudgetGiven) { $miss[] = 'their budget' . ($pcTravOk && $pcAmount > 0 ? ' (and whether the amount is for the whole group or per person)' : ''); }
            $note = "\n\nCORRECTION FOR THIS REPLY: the visitor has NOT told you " . implode(' or ', $miss)
                  . ". Do not assume it, do not state a default, and do not say you are building the plan yet. "
                  . "Set ready to false. FIRST respond properly to what the visitor actually just said "
                  . "(if it was off-topic, a joke or a side question, answer it in your own words like a "
                  . "friendly person would). THEN, naturally, ask for exactly what is missing, and mention "
                  . "the date and length you already have.";
            $retry = $payload;
            $retry['system_instruction']['parts'][0]['text'] .= $note;
            $retry['generationConfig']['temperature']     = 0.6;
            $retry['generationConfig']['maxOutputTokens'] = 4000;
            if ((microtime(true) - $budT0) < 40) {
                [$respR, $statR] = bud_gemini_call($retry, $apiKey);
                $dataR = json_decode((string) $respR, true);
                if ($statR === 200 && is_array($dataR)) {
                    $txtR = '';
                    foreach ($dataR['candidates'][0]['content']['parts'] ?? [] as $part) {
                        if (isset($part['text'])) { $txtR .= $part['text']; }
                    }
                    $outR = bud_extract_obj(trim($txtR));
                    if ($outR !== null && trim((string) ($outR['reply'] ?? '')) !== '') {
                        $sayR = bud_cut(trim((string) $outR['reply']), 900);
                        $sayR = trim(preg_replace('/[ \t]*\R[ \t]*/u', "\n", $sayR));
                        $say  = preg_replace('/\n{2,}/', "\n\n", str_replace("\n", "\n\n", $sayR));
                    }
                }
            }
        }
    }

    /* Defaults, only once the plan is really going to be built. */
    if ($ready) {
        if ($pcTravellers < 1) {
            $pcTravellers = 2;
        }
        if (!$pcInterests) {
            $pcInterests = ['beaches', 'waterfalls', 'food'];
        }
    }

    /* ---- REMOVING A DAY ----
       "Remove day 2" used to depend on the model setting both removeDays
       and a smaller nights, and it often set neither: Bud said the day was
       gone and the trip came back unchanged. Now the visitor's own words
       decide, the length follows, and a trip shorter than two days (the
       smallest Bud plans) is refused honestly instead of faked. */
    $pcRemoveDays = [];
    if (($body['rebuild'] ?? false) === true && is_array($body['plan']['days'] ?? null)) {
        $onScreen = count($body['plan']['days']);
        $eIn0 = is_array($out['edit'] ?? null) ? $out['edit'] : [];
        foreach (array_slice((array) ($eIn0['removeDays'] ?? []), 0, 12) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= $onScreen) { $pcRemoveDays[(int) $x] = (int) $x; }
        }
        $pcRemoveDays = array_values($pcRemoveDays);
        if (!$pcRemoveDays) { $pcRemoveDays = bud_removed_days($message, $onScreen); }

        if ($pcRemoveDays) {
            $left = $onScreen - count($pcRemoveDays);
            if ($left < 2) {
                $pcRemoveDays = [];
                $ready = false;
                $say = 'I can\'t take out ' . ($onScreen === 2 ? 'a day' : 'that') . ' because the shortest trip I plan is 2 days. '
                     . 'I can move the stops from one day into the other, or build you a fresh plan. Which would you like?';
            } else {
                $pcNights = $left - 1;
            }
        }
    }

    $pcNightsKnown = $pcNights;     // before the clamp below invents a minimum of 1
    $pcNights = max(1, min(BUD_MAX_NIGHTS, $pcNights));
    $pcEnd    = $pcHasDate ? $pcStart->modify('+' . $pcNights . ' days') : null;

    /* With an exact amount, the tier follows from what it works out to
       per person per day. */
    if ($pcAmount > 0) {
        $pcBudget = bud_budget_calc($pcAmount, $pcBasis, $pcTravellers, $pcNights + 1)['tier'];
    }

    /* Which days the visitor's change touches, so the builder rewrites only
       those. Only sent for an edit that is about to be built. */
    $pcEdit = null;
    if ($ready && ($body['rebuild'] ?? false) === true) {
        $eIn   = is_array($out['edit'] ?? null) ? $out['edit'] : [];
        $eMax  = BUD_MAX_NIGHTS + 1;
        $eDays = [];
        $eRem  = [];
        foreach (array_slice((array) ($eIn['days'] ?? []), 0, 12) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= $eMax) { $eDays[(int) $x] = (int) $x; }
        }
        foreach (array_slice((array) ($eIn['removeDays'] ?? []), 0, 12) as $x) {
            if (is_numeric($x) && (int) $x >= 1 && (int) $x <= $eMax) { $eRem[(int) $x] = (int) $x; }
        }
        $eRemP = [];
        foreach (array_slice((array) ($eIn['removePlaces'] ?? []), 0, 6) as $x) {
            if (is_string($x) && trim($x) !== '') { $eRemP[] = bud_cut(trim($x), 80); }
        }
        if ($pcRemoveDays) { $eRem = array_combine($pcRemoveDays, $pcRemoveDays); }
        $pcScope = in_array($eIn['scope'] ?? '', ['days', 'all', 'dates', 'new'], true) ? $eIn['scope'] : '';
        if ($pcRemoveDays && ($pcScope === '' || $pcScope === 'dates')) { $pcScope = 'days'; }

        /* The visitor's own words decide which days change. The model often
           answers "keep day 1, change day 2" with scope "all", which rewrites
           every day and keeps the same stops. */
        $pcFresh = false;
        $scrDays = is_array($body['plan']['days'] ?? null) ? count($body['plan']['days']) : 0;
        if ($scrDays >= 1 && !$pcRemoveDays && $pcScope !== 'new' && $pcScope !== 'dates'
            && $pcNights + 1 === $scrDays) {
            $named = bud_edit_days($message, $scrDays);
            if ($named['change']) {
                $pcScope = 'days';
                $eDays   = array_combine($named['change'], $named['change']);
                $pcFresh = bud_wants_fresh_day($message);
            } elseif ($named['keep'] && ($pcScope === 'all' || $pcScope === '')) {
                $rest = array_values(array_diff(range(1, $scrDays), $named['keep']));
                if ($rest) {
                    $pcScope = 'days';
                    $eDays   = array_combine($rest, $rest);
                }
            }
        }
        $pcEdit = [
            'removePlaces' => $eRemP,
            'scope'      => $pcScope,
            'fresh'      => $pcFresh,
            'days'       => $pcRemoveDays ? [] : array_values($eDays),
            'removeDays' => array_values($eRem),
            'note'       => bud_cut(trim((string) preg_replace('/[\x00-\x1f]+/', ' ', (string) ($eIn['note'] ?? ''))), 240),
        ];
    }

    /* ---- ISLAND RULE, chat side ----
       The planner already leaves islands out when the time or budget is
       too small, but this chat step used to announce the island anyway
       ("featuring Parola Island") and show its photo card. Same test as
       the planner: P3,000+ per person for the whole trip and a 5 hour window. When
       it fails, island names are taken out of the places list and the
       reply says why instead of promising the island. */
    if ($ready && $pcIslandNames) {
        $pcPerDayAmt = $pcAmount > 0
            ? (int) bud_budget_calc($pcAmount, $pcBasis, max(1, $pcTravellers), $pcNights + 1)['perPersonDay']
            : null;
        $pcIslandBudgetOk = $pcPerDayAmt !== null
            ? $pcPerDayAmt * ($pcNights + 1) >= BUD_ISLAND_MIN_BUDGET
            : ($pcBudget === 'comfortable');
        $pcIslandHoursOk = $pcDayStart === null
            || (bud_time_min($pcDayEnd) - bud_time_min($pcDayStart)) >= BUD_ISLAND_MIN_MINUTES;

        if (!($pcIslandBudgetOk && $pcIslandHoursOk)) {
            $pcHadIsland = false;
            $pcPlaces = array_values(array_filter($pcPlaces, function ($n) use ($pcIslandNames, &$pcHadIsland) {
                if (in_array($n, $pcIslandNames, true)) { $pcHadIsland = true; return false; }
                return true;
            }));
            foreach ($pcIslandNames as $in) {
                if (stripos($say, $in) !== false) { $pcHadIsland = true; }
            }
            if (!$pcHadIsland && in_array('islands', $pcInterests, true)) { $pcHadIsland = true; }

            if ($pcHadIsland && ($body['rebuild'] ?? false) !== true) {
                $pcWho   = $pcTravellers === 1 ? 'a solo trip' : 'your trip for ' . $pcTravellers;
                $pcMoney = $pcAmount > 0 ? ' with a P' . number_format($pcAmount) . ' budget' : '';
                $pcGap   = $pcPerDayAmt !== null
                         ? ' That works out to about P' . number_format($pcPerDayAmt * ($pcNights + 1)) . ' per person for the whole trip, and'
                         : '';
                $say = 'All set for ' . $pcWho . ' starting ' . bud_label($pcStart) . $pcMoney . '.'
                     . ($pcGap !== '' ? $pcGap . ' island trips' : ' Island trips') . ' usually need P'
                     . number_format(BUD_ISLAND_MIN_BUDGET)
                     . ' or more per person for the whole trip, because boats are often priced per boat rather than per person'
                     . ' and food, water and island fees come on top. They also need at least 5 hours on the day.'
                     . ' So I am building this around beaches and waterfalls instead. I am putting your plan together now.';
            }
        }
    }

    bud_out(200, [
        'reply'  => $say,
        'ready'  => $ready,
        'cancel' => false,
        'edit'   => $pcEdit,
        'brief'  => [
            'startDate'  => $pcHasDate ? $pcStart->format('Y-m-d') : '',
            'endDate'    => $pcEnd ? $pcEnd->format('Y-m-d') : '',
            'travellers' => max(1, min(60, $pcTravellers)),
            'nights'     => $pcNights,
            'perDay'     => $pcPerDay,
            'budget'     => $pcBudget,
            'budgetAmount' => $pcAmount > 0 ? $pcAmount : 0,
            'budgetBasis'  => $pcBasis,
            'interests'  => $pcInterests,
            'places'     => $pcPlaces,
            'name'       => $pcName,
            'dayStart'   => $pcDayStart,
            'dayEnd'     => $pcDayEnd,
        ],
        /* What was actually understood, with the unknowns left out, so
           the browser can send it back next turn. Unlike 'brief' this
           never invents a value: an unknown group size is absent here,
           not 1. */
        'known'  => array_filter([
            'startDate'    => $pcHasDate ? $pcStart->format('Y-m-d') : null,
            'endDate'      => ($pcHasDate && $pcEndIn !== null) ? $pcEndIn->format('Y-m-d') : null,
            'nights'       => ($pcNightsKnown >= 1 && $pcNightsKnown <= BUD_MAX_NIGHTS) ? $pcNightsKnown : null,
            'travellers'   => ($pcTravellers >= 1 && $pcTravellers <= 60) ? $pcTravellers : null,
            'perDay'       => (($b['perDay'] ?? null) !== null) ? $pcPerDay : null,
            'budget'       => $pcTierGiven ? $b['budget'] : null,
            'budgetAmount' => $pcAmount > 0 ? $pcAmount : null,
            'budgetBasis'  => ($pcAmount > 0 && $pcBasisGiven) ? $pcBasis : null,
            'interests'    => $pcInterests ?: null,
            'places'       => $pcPlaces ?: null,
            'name'         => ($pcNameGiven && $pcName !== '') ? $pcName : null,
            'dayStart'     => $pcDayStart,
            'dayEnd'       => $pcDayEnd,
        ], function ($v) { return $v !== null; }),
    ]);
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

    /* The reply is only read when the model was asked to write something. */
    if (!$skipModel) {
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
                    'That trip was too long for me to plan in one go. Tell me a shorter length '
                    . 'or fewer places a day and I will redo it.',
                    'Itinerary hit MAX_TOKENS at ' . strlen($reply) . ' chars; JSON incomplete.');
            }

            bud_fail(502, 'I could not put a plan together just then. Try again?',
                'Itinerary JSON unparseable (finish=' . $finish . '). First 400 chars: '
                . substr($reply, 0, 400));
        }
    } else {
        $plan = ['name' => '', 'summary' => '', 'days' => []];
    }

    $dropped = 0;
    $dupes   = 0;

    /* One stop, in the shape the browser gets. Names, towns and coordinates
       always come from the destination list, never from the model or from
       the copy of the trip the browser sent back. */
    $mkItem = function (string $slug, string $time, string $end, string $note) use ($bMustPool): array {
        $d = $bMustPool[$slug];
        return [
            'time'   => $time,
            'end'    => $end,
            'destId' => $slug,
            'note'   => $note,
            /* For display only. save-itinerary.php resolves the slug
               itself and ignores these. */
            'name'   => $d['name'],
            'town'   => $d['town'],
            /* So the map can pin every stop of the plan. */
            'tag'    => $d['tag'],
            'lat'    => is_numeric($d['lat'] ?? null) ? (float) $d['lat'] : null,
            'lng'    => is_numeric($d['lng'] ?? null) ? (float) $d['lng'] : null,
        ];
    };

    /* ---- what the model wrote, by day number ----
       In an edit it writes only some days and labels each with "day". With
       no label, the days come back in the order they were asked for. */
    $regenNums = [];
    foreach (array_keys($regen) as $i) { $regenNums[] = $i + 1; }
    sort($regenNums);

    $gen = [];
    foreach (array_slice(array_values($plan['days']), 0, $total) as $pos => $day) {
        if (!is_array($day)) { continue; }
        $dn = (isset($day['day']) && is_numeric($day['day']))
            ? (int) $day['day']
            : ($regenNums[$pos] ?? $pos + 1);
        if ($dn < 1 || $dn > $total || isset($gen[$dn])) { continue; }

        $items = [];
        foreach (array_slice((array) ($day['items'] ?? []), 0, 6) as $item) {
            if (!is_array($item)) { continue; }

            $slug = trim((string) ($item['destId'] ?? ''));

            /* THE WHITELIST. A slug that is not one of the 24 is
               discarded however convincing it looks. Naming the valid
               options in the prompt makes the right answer easy; this
               makes the wrong one impossible. */
            if ($slug === '' || !isset($budSlugs[$slug])) {
                if ($slug !== '') { $dropped++; }
                continue;
            }
            if (isset($banned[$slug])) { continue; }   // the visitor took this one out
            if (isset($avoid[$slug])) { continue; }    // the visitor wants this day completely changed

            $time = trim((string) ($item['time'] ?? ''));
            $endT = trim((string) ($item['end'] ?? ''));

            /* Hold every stop inside the visitor's daily hours. The prompt
               asks for it; this guarantees it. */
            if ($bDayStart !== null) {
                $tm = bud_parse_time($time);
                if ($tm !== null) {
                    $latest = bud_time_min($bDayEnd) - 60;
                    $mins   = max(bud_time_min($bDayStart), min(bud_time_min($tm), $latest));
                    $time   = sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
                }
                $te = bud_parse_time($endT);
                if ($te !== null && bud_time_min($te) > bud_time_min($bDayEnd)) {
                    $endT = $bDayEnd;
                }
            }

            $okTime = preg_match('/^\d{1,2}:\d{2}$/', $time) === 1;
            /* The end of the visit, only kept if it is a real time after
               the start. The browser works one out if not. */
            $okEnd  = $okTime && preg_match('/^\d{1,2}:\d{2}$/', $endT) === 1
                      && substr('0' . $endT, -5) > substr('0' . $time, -5);

            $items[] = $mkItem(
                $slug,
                $okTime ? substr('0' . $time, -5) : '',
                $okEnd ? substr('0' . $endT, -5) : '',
                bud_cut(trim((string) ($item['note'] ?? '')), 200)
            );
        }

        /* The day begins when the visitor said it does. The model tends to
           open at 09:00; move the first stop back to their start time and
           keep its end time, so the visit just gets a little longer and
           nothing overlaps the next stop. */
        if ($bDayStart !== null && $items) {
            $firstMin = bud_parse_time($items[0]['time']) !== null
                      ? bud_time_min($items[0]['time']) : null;
            if ($firstMin === null || $firstMin > bud_time_min($bDayStart)) {
                $items[0]['time'] = $bDayStart;
            }
        }

        $gen[$dn] = $items;
    }

    /* ---- put the trip together ----
       1. Days that are not being rewritten are copied across as they were.
       2. Days the model wrote go in after them, so a place that stayed on
          a kept day is never also given to a rewritten one.
       A place appears once in the whole trip. */
    $claimed = [];
    $slots   = array_fill(0, $total, []);
    $changed = [];
    $stayed  = [];
    $failed  = [];

    for ($i = 0; $i < $total; $i++) {
        if (isset($regen[$i])) { continue; }
        $its = [];
        foreach ((array) ($base[$i] ?? []) as $b) {
            if (isset($claimed[$b['destId']])) { continue; }
            $claimed[$b['destId']] = true;
            $its[] = $mkItem($b['destId'], $b['time'], $b['end'], $b['note']);
        }
        /* The visitor changed their daily hours: same stops, new times. */
        if ($its && $bDayStart !== null && $windowChanged) {
            $its = bud_retime_day($its, $bDayStart, $bDayEnd);
        }
        $slots[$i] = $its;
        $stayed[]  = $i + 1;
    }

    for ($i = 0; $i < $total; $i++) {
        if (!isset($regen[$i])) { continue; }
        $its = [];
        foreach ((array) ($gen[$i + 1] ?? []) as $it) {
            if (isset($claimed[$it['destId']])) { $dupes++; continue; }
            $claimed[$it['destId']] = true;
            $its[] = $it;
        }
        if (!$its && !empty($base[$i])) {
            /* The model gave nothing usable for a day that already had
               stops. Keep what it had rather than leave a hole, and say so. */
            foreach ($base[$i] as $b) {
                if (isset($claimed[$b['destId']]) || isset($banned[$b['destId']])) { continue; }
                $claimed[$b['destId']] = true;
                $its[] = $mkItem($b['destId'], $b['time'], $b['end'], $b['note']);
            }
            if ($its) { $failed[] = $i + 1; }
        } else {
            $changed[] = $i + 1;
        }
        $slots[$i] = $its;
    }

    /* Day 1 is the start date, day 2 the next, and so on. */
    $planDays = [];
    foreach ($slots as $i => $its) {
        $planDays[] = [
            'date'  => $bStart->modify('+' . $i . ' days')->format('Y-m-d'),
            'items' => $its,
        ];
    }

    $kept = 0;
    foreach ($planDays as $pd) { $kept += count($pd['items']); }

    if ($dupes > 0) {
        error_log('[bud.php] ' . $dupes . ' repeated stop(s) removed from the plan.');
    }

    /* A place they named is guaranteed here, not left to the model. If it
       left one out, it goes on the day where it fits best: a day whose
       stops are all within 20km of it, or failing that the emptiest day.
       A day that stays as it was is a last choice, not ruled out: they
       asked for this place by name. */
    if ($bMust) {
        $present = [];
        foreach ($planDays as $pd) {
            foreach ($pd['items'] as $it) {
                $present[$it['destId']] = true;
            }
        }

        foreach ($bMust as $slug) {
            if (isset($present[$slug])) {
                continue;
            }

            $bestDay   = null;
            $bestScore = null;
            foreach ($planDays as $i => $pd) {
                $n    = count($pd['items']);
                $fits = true;
                foreach ($pd['items'] as $it) {
                    if (!in_array($it['destId'], $near[$slug] ?? [], true)) {
                        $fits = false;
                        break;
                    }
                }
                $score = ($fits ? 0 : 100)
                       + ($n >= $bPerDay + 1 ? 200 : 0)
                       + (in_array($i + 1, $stayed, true) ? 30 : 0)
                       + $n;
                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestDay   = $i;
                }
            }
            if ($bestDay === null) {
                continue;
            }

            $time = $bDayStart ?? '09:00';
            $lastStartAllowed = $bDayStart !== null ? bud_time_min($bDayEnd) - 60 : 17 * 60;
            if ($planDays[$bestDay]['items']) {
                $lastItem = end($planDays[$bestDay]['items']);
                $time     = '';
                if (preg_match('/^(\d{1,2}):(\d{2})$/', (string) $lastItem['time'], $tm)) {
                    $mins = (int) $tm[1] * 60 + (int) $tm[2] + 180;
                    if ($mins <= $lastStartAllowed) {
                        $time = sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
                    }
                }
            }

            $addNote = bud_cut((string) ($budSlugs[$slug]['desc'] ?? ''), 200);
            $planDays[$bestDay]['items'][] = $mkItem($slug, $time, '', $addNote !== '' ? $addNote : 'Added because you asked for it.');
            $present[$slug] = true;
            $kept++;

            $dayNo  = $bestDay + 1;
            $stayed = array_values(array_diff($stayed, [$dayNo]));
            if (!in_array($dayNo, $changed, true)) { $changed[] = $dayNo; }
            error_log('[bud.php] named place was missing from the plan, added in code: ' . $slug);
        }
    }
    /* A direct swap changed this day even though no model rewrote it. */
    if ($swapped) {
        if (!in_array($swapped, $changed, true)) { $changed[] = $swapped; }
        $stayed = array_values(array_diff($stayed, [$swapped]));
    }

    /* STOPS PER DAY IS A PROMISE TOO. The model is told how many stops a day
       the visitor wants, but nothing made it obey: "just 3 stops" came back
       with four on Day 2. Any day that was written or added to this turn and
       holds more than the limit is cut back. Places the visitor named are
       kept first; the rest keep their order. A day that stayed exactly as
       it was is never touched. */
    foreach ($planDays as $i => $pd) {
        if (!in_array($i + 1, $changed, true) && !isset($regen[$i])) { continue; }
        if (count($pd['items']) <= $bPerDay) { continue; }

        $keep = [];
        foreach ($pd['items'] as $k => $it) {
            if (in_array($it['destId'], $bMust, true)) { $keep[$k] = true; }
        }
        foreach ($pd['items'] as $k => $it) {
            if (count($keep) >= $bPerDay) { break; }
            $keep[$k] = true;
        }
        $trimmed = [];
        foreach ($pd['items'] as $k => $it) {
            if (isset($keep[$k])) { $trimmed[] = $it; }
        }
        /* If they named more places than fit in one day, the named ones win. */
        $planDays[$i]['items'] = $trimmed;
        if ($bDayStart !== null) {
            $planDays[$i]['items'] = bud_retime_day($planDays[$i]['items'], $bDayStart, $bDayEnd);
        }
        if (!in_array($i + 1, $changed, true)) { $changed[] = $i + 1; }
        $stayed = array_values(array_diff($stayed, [$i + 1]));
        error_log('[bud.php] day ' . ($i + 1) . ' had ' . count($pd['items']) . ' stops; cut to ' . count($trimmed) . '.');
    }
    $kept = 0;
    foreach ($planDays as $pd) { $kept += count($pd['items']); }

    /* The visitor's daily hours are a promise, not a hint. Any day whose
       stops spill past the end, start before the beginning, overlap, or
       lack an end time (which the browser pads by three hours, giving
       6:30pm finishes) has its times spread evenly across the window.
       Stops and their order are untouched. */
    if ($bDayStart !== null) {
        foreach ($planDays as $i => $pd) {
            if ($pd['items'] && !bud_day_fits($pd['items'], $bDayStart, $bDayEnd)) {
                $planDays[$i]['items'] = bud_retime_day($pd['items'], $bDayStart, $bDayEnd);
            }
        }
    }

    /* ---- ISLAND RULE, enforced ----
       Whatever the model wrote, a kept day or a swap brought in: with
       islands NOT allowed, an island stop is replaced by the nearest place
       that is not an island; with islands allowed, the island's day is laid
       out so it gets at least 5 hours. Runs last so nothing re-times it. */
    $bIsIsland = function ($slug) use ($bMustPool) {
        return isset($bMustPool[$slug]) && bud_is_island($bMustPool[$slug]);
    };
    $inPlan = [];
    foreach ($planDays as $pd) {
        foreach ($pd['items'] as $it) { $inPlan[$it['destId']] = true; }
    }
    foreach ($planDays as $i => $pd) {
        $has = false;
        foreach ($pd['items'] as $it) {
            if ($bIsIsland($it['destId'])) { $has = true; break; }
        }
        if (!$has) { continue; }
        $before = json_encode($pd['items']);

        if (!$bIslandsOk) {
            $bIslandAsked = true;
            $out = [];
            foreach ($pd['items'] as $it) {
                if (!$bIsIsland($it['destId'])) { $out[] = $it; continue; }
                $bestSlug = null; $bestMiss = null;
                foreach ($budSlugs as $cand => $cd) {
                    if (isset($inPlan[$cand]) || isset($banned[$cand]) || isset($avoid[$cand]) || bud_is_island($cd)) { continue; }
                    $miss = 0;
                    foreach ($pd['items'] as $o) {
                        if ($o['destId'] !== $it['destId'] && !in_array($o['destId'], $near[$cand] ?? [], true)) { $miss++; }
                    }
                    if ($bestMiss === null || $miss < $bestMiss) { $bestMiss = $miss; $bestSlug = $cand; }
                }
                if ($bestSlug !== null) {
                    $inPlan[$bestSlug] = true;
                    $out[] = $mkItem($bestSlug, $it['time'], $it['end'], bud_cut((string) ($budSlugs[$bestSlug]['desc'] ?? ''), 200));
                }
            }
            $planDays[$i]['items'] = $out;
        } else {
            $planDays[$i]['items'] = bud_fit_island_day($pd['items'], $bIsIsland, $bDayStart, $bDayEnd, $bMust);
        }

        if (json_encode($planDays[$i]['items']) !== $before) {
            if (!in_array($i + 1, $changed, true)) { $changed[] = $i + 1; }
            $stayed = array_values(array_diff($stayed, [$i + 1]));
        }
    }
    $kept = 0;
    foreach ($planDays as $pd) { $kept += count($pd['items']); }

    sort($changed);
    sort($stayed);

    /* One stop is not a plan. Better to say so than to save something
       useless to somebody's account. */
    if ($kept < 2) {
        bud_fail(502, 'I could not put a workable plan together just then. Try again?',
            'Itinerary had ' . $kept . ' valid stops after filtering, ' . $dropped . ' dropped.');
    }

    /* ---- DOES EACH DAY FIT THE BUDGET? ----
       The filter above judges places one at a time; a day with two of
       them can still add up to more than the visitor has. Add up the
       workbook's low-end figure for each day's stops and, if it is over
       the per-person-per-day allowance, say so on the plan. Figures are
       unverified, stops without a figure count as zero, and nothing is
       removed: the visitor decides. */
    $bBudgetNote = '';
    if ($bAmount > 0 && isset($bCalc) && isset($costOf)) {
        $allow = (int) $bCalc['perPersonDay'];
        $overs = [];
        foreach ($planDays as $i => $pd) {
            $sum = 0;
            foreach ($pd['items'] as $it) {
                $low = $costOf($budSlugs[$it['destId']] ?? [])['low'];
                if ($low !== null) { $sum += $low; }
            }
            if ($sum > $allow) {
                $overs[] = 'Day ' . ($i + 1) . ' (about P' . number_format($sum) . ')';
            }
        }
        if ($overs) {
            $bBudgetNote = ' Heads up: going by our unverified notes, ' . implode(' and ', $overs)
                         . ' may cost more per person than your roughly P' . number_format($allow)
                         . ' a day. Ask me to make it cheaper if you want.';
        }
    }

    $bIslandNote = '';
    if (!$bIslandsOk && $bIslandAsked) {
        $bIslandGap = '';
        if (isset($bCalc)) {
            $bIslandGap = ' Your budget works out to about P' . number_format((int) $bCalc['perPersonDay'] * ($bNights + 1))
                        . ' per person for the whole trip.';
        }
        $bIslandNote = ' Heads up: island trips usually need a budget of P'
                     . number_format(BUD_ISLAND_MIN_BUDGET) . ' or more per person for the whole trip, because boats are'
                     . ' often priced per boat rather than per person, and food, water and island fees come on top.'
                     . $bIslandGap
                     . ' They also need at least 5 hours on the day. This plan uses beaches, waterfalls and other'
                     . ' places instead. To fit an island, raise the budget, share the boat with others, or add days.';
    }

    /* The visitor's name wins. They typed it; the model only guessed. On an
       edit the trip keeps the name it already had unless every day was
       rewritten. */
    $modelName    = trim((string) ($plan['name'] ?? ''));
    $modelSummary = trim((string) ($plan['summary'] ?? ''));
    if ($bName !== '') {
        $outName = $bName;
    } elseif ($prevDays && $prevName !== '' && !$regenAll) {
        $outName = $prevName;
    } else {
        $outName = bud_cut($modelName !== '' ? $modelName : ($prevName !== '' ? $prevName : 'Camarines Norte trip'), 160);
    }
    $outSummary = bud_cut($modelSummary !== '' ? $modelSummary : ($prevDays ? $prevSummary : ''), 400);

    bud_out(200, [
        'plan' => [
            'name'      => $outName,
            'summary'   => $outSummary . $bBudgetNote . $bIslandNote,
            'travelers' => $bTravellers,
            'source'    => 'bud',
            'startDate' => $bStart->format('Y-m-d'),
            'endDate'   => $bEnd->format('Y-m-d'),
            'nights'    => $bNights,
            'brief'     => [
                'startDate'  => $bStart->format('Y-m-d'),
                'endDate'    => $bEnd->format('Y-m-d'),
                'travellers' => $bTravellers,
                'nights'     => $bNights,
                'perDay'     => $bPerDay,
                'budget'     => $bBudget,
                'budgetAmount' => $bAmount,
                'budgetBasis'  => $bBasis,
                'dayStart'   => $bDayStart,
                'dayEnd'     => $bDayEnd,
                'interests'  => $bInterests,
                'places'     => array_map(function ($s) use ($budSlugs) {
                    return $budSlugs[$s]['name'];
                }, $bMust),
            ],
            'days'      => $planDays,
        ],
        'stops'   => $kept,
        'dropped' => $dropped,
        /* What an edit did, so the chat can say "Day 2 changed, the rest
           is as you had it". Null for a brand new trip. */
        'edit'    => $prevDays ? [
            'changed' => $changed,
            'kept'    => $stayed,
            'failed'  => $failed,
            'scope'   => $regenAll ? 'all' : ($skipModel ? 'dates' : 'days'),
        ] : null,
    ]);
}

/* ---- hand-off to the planner ----
   The chat prompt tells the model to reply with exactly [[PLAN]] when the
   visitor wants a trip built or changed. The marker is never shown: the
   browser sees "plan: true" and starts the planner, with this chat as its
   background, so "include it" is read against what was just discussed. */
if (preg_match('/\[\[\s*PLAN\s*\]\]/i', $reply)) {
    bud_out(200, [
        'reply'  => trim((string) preg_replace('/\[\[\s*PLAN\s*\]\]/i', '', $reply)),
        'places' => [],
        'plan'   => true,
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