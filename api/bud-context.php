<?php
/* ====================================================================
   api/bud-context.php

   Turns the destinations tables into a block of text that gets added
   to Bud's instructions, so it answers from your data instead of
   whatever the model happens to know about Camarines Norte.

   TWO THINGS SHAPE THIS FILE.

   1. Tokens cost money on every message. The whole context is resent
      with each question, so it is written compactly: no field labels
      that repeat 24 times, no "none recorded" lines. A fact that has
      no row simply is not printed, and one line at the top explains
      what absence means.

   2. Absence must read as "unknown", never as "free" or "nearby". The
      model is told this explicitly, and every unverified description
      is marked inline. This is the difference between Bud saying "I
      don't have a confirmed fare, please check with the Tourism
      Office" and Bud quoting a number nobody set.
   ==================================================================== */

declare(strict_types=1);

const BUD_CONTEXT_TTL = 600;   // seconds to keep the cached copy

/**
 * Build the grounding text. Returns '' when there is nothing to say,
 * which the caller treats as "run without destination context".
 */
/**
 * The dishes from includes/food-data.php.
 *
 * Same eight the Food page shows, so "what should I eat" is answered
 * from the site's own content rather than from whatever the model
 * happens to know about Bicol cooking — and a dish that gets edited on
 * that page changes here too.
 *
 * The 'where' lines name TOWNS, not businesses, which is deliberate on
 * that page and worth preserving here: an official site naming one
 * carinderia over its neighbours is an endorsement nobody authorised.
 */
function bud_food_context(string $foodFile): string
{
    if (!is_file($foodFile)) {
        return '';
    }

    $rows = require $foodFile;
    if (!is_array($rows) || $rows === []) {
        return '';
    }

    $out   = [];
    $out[] = 'FOOD AND DELICACIES - the complete list (' . count($rows) . ').';
    $out[] = 'These are the only dishes this site covers. You may describe them freely; the descriptions are the site\'s own.';
    $out[] = 'The "where" line names a town or a kind of place, never a named business. Keep it that way - do not recommend a specific restaurant, carinderia or shop, because none has been checked and naming one on an official site is an endorsement.';
    $out[] = '';

    foreach ($rows as $f) {
        if (empty($f['name'])) {
            continue;
        }

        $line = $f['name'];
        if (!empty($f['kind'])) {
            $line .= ' (' . $f['kind'] . ')';
        }
        if (!empty($f['chips']) && is_array($f['chips'])) {
            $line .= ' | ' . implode('; ', $f['chips']);
        }
        $out[] = $line;

        if (!empty($f['desc'])) {
            $out[] = '  ' . $f['desc'];
        }
        if (!empty($f['where'])) {
            $out[] = '  where: ' . $f['where'];
        }
    }

    return implode("\n", $out);
}

/**
 * The 24 destinations straight from includes/destinations-data.php.
 *
 * For use BEFORE the database exists. That file is already the single
 * source of truth for both pages, so Bud can read the same list and be
 * grounded on real destinations today, with nothing to import.
 *
 * It gives names, towns, types and descriptions — no fares, routes or
 * contacts, because the file has none. Bud is told those are unknown,
 * which is the truth. Move to the database when you have that data;
 * bud.php prefers the database automatically once it is there.
 */
/**
 * The collected data: includes/bud-data.php, generated from the
 * worksheet. Preferred over the descriptions-only file because it
 * carries fares, routes and contacts.
 *
 * Everything is emitted as text, ranges intact. A visitor asking what
 * the boat costs is better served by "P2,500-3,000 per boat (4-6 pax)"
 * than by a single number that is right for nobody.
 */
function bud_context_from_data(string $dataFile): string {

    if (!is_file($dataFile)) {
        return '';
    }

    $data = require $dataFile;
    $dests = $data['destinations'] ?? [];
    if (!$dests) {
        return '';
    }

    $out = [];
    $out[] = 'DESTINATIONS - THE COMPLETE LIST (' . count($dests) . ').';
    $out[] = 'These are the only places this site covers. Do not discuss any other location as a destination.';
    $out[] = '';
    $out[] = 'How to read it:';
    $out[] = '- NOTHING BELOW IS CONFIRMED. Every figure came from published guides or informal collection, not from the operator. Give the number, then say it is worth confirming with the Provincial Tourism Office. Never present one as official.';
    $out[] = '- Ranges are real. Quote "P2,500 to P3,000", never a single figure from inside a range.';
    $out[] = '- Anything absent is UNKNOWN, not free and not zero. Say you cannot confirm it.';
    $out[] = '- Check the unit. P600 per person and P2,500 per boat are different answers to "how much is the boat".';
    $out[] = '';

    foreach ($dests as $d) {
        $line = $d['name'];
        if (!empty($d['town'])) {
            $line .= ' | ' . $d['town'];
        }
        $out[] = $line;

        foreach (['best_months' => 'best months', 'hours' => 'hours',
                  'guide' => 'guide required', 'permit' => 'permit required',
                  'booking' => 'advance booking'] as $k => $label) {
            if (!empty($d[$k])) {
                $out[] = '  ' . $label . ': ' . $d[$k];
            }
        }

        if (!empty($d['budget_low']) || !empty($d['budget_high'])) {
            $out[] = '  day budget: ' . trim(($d['budget_low'] ?? '') . ' to ' . ($d['budget_high'] ?? ''), ' to ');
        }

        /* Routes grouped by origin, so "from Manila" reads as one
           journey rather than three unrelated legs. */
        $byOrigin = [];
        foreach ($d['routes'] ?? [] as $r) {
            $byOrigin[$r['origin'] ?? '?'][] = $r;
        }
        foreach ($byOrigin as $origin => $legs) {
            $out[] = '  FROM ' . $origin . ':';
            foreach ($legs as $r) {
                $bits = array_filter([
                    'leg ' . ($r['leg'] ?? '?'),
                    $r['mode'] ?? null,
                    $r['time'] ?? null,
                    $r['fare'] ?? null,
                ]);
                $out[] = '    ' . implode(', ', $bits)
                       . (!empty($r['notes']) ? ' - ' . $r['notes'] : '');
            }
        }

        foreach ($d['fees'] ?? [] as $f) {
            $bits = array_filter([$f['label'] ?? null, $f['amount'] ?? null, $f['unit'] ?? null]);
            $out[] = '  fee: ' . implode(' ', $bits)
                   /* That column holds a date on some rows and a
                      source on others ("supplied by site owner"), so
                      "as of" only fits when it looks like a date. */
                   . (!empty($f['asof'])
                        ? (preg_match('/\d{4}|\d{1,2}\/\d{1,2}/', $f['asof'])
                            ? ' (as of ' . $f['asof'] . ')'
                            : ' (source: ' . $f['asof'] . ')')
                        : '')
                   . (!empty($f['notes']) ? ' - ' . $f['notes'] : '');
        }

        foreach ($d['contacts'] ?? [] as $c) {
            $bits = array_filter([$c['phone'] ?? null, $c['email'] ?? null, $c['facebook'] ?? null]);
            $out[] = '  contact: ' . ($c['org'] ?? 'unnamed')
                   . ($bits ? ' - ' . implode(', ', $bits) : ' (no number on file)');
        }

        if (!empty($d['notes'])) {
            $out[] = '  note: ' . $d['notes'];
        }
    }

    /* --- festivals --- */
    $fests = $data['festivals'] ?? [];
    if ($fests) {
        $out[] = '';
        $out[] = 'FESTIVALS (' . count($fests) . '). Dates below are as collected; confirm before anyone books travel around one.';
        foreach ($fests as $f) {
            $out[] = $f['name'] . ' | ' . ($f['town'] ?? '') . ' | ' . ($f['kind'] ?? '');
            if (!empty($f['about']))  { $out[] = '  ' . $f['about']; }
            if (!empty($f['timing'])) { $out[] = '  timing: ' . $f['timing']; }
            foreach ($f['dates'] ?? [] as $dt) {
                $span = $dt['start'] ?? '';
                if (!empty($dt['end'])) { $span .= ' to ' . $dt['end']; }
                $out[] = '  dates: ' . $span . ' (' . ($dt['status'] ?? 'status unknown') . ')';
            }
            foreach ($f['contacts'] ?? [] as $c) {
                $bits = array_filter([$c['phone'] ?? null, $c['email'] ?? null]);
                $out[] = '  contact: ' . ($c['org'] ?? 'unnamed') . ($bits ? ' - ' . implode(', ', $bits) : '');
            }
        }
    }

    $out[] = '';
    $out[] = 'Send unanswered questions to the Camarines Norte Provincial Tourism Office.';

    return implode("\n", $out);
}

function bud_context_from_file(string $dataFile): string {

    if (!is_file($dataFile)) {
        return '';
    }

    $rows = require $dataFile;
    if (!is_array($rows) || $rows === []) {
        return '';
    }

    $out = [];
    $out[] = 'DESTINATIONS — THE COMPLETE LIST (' . count($rows) . ').';
    $out[] = 'These are the only places this site covers. Do not discuss any other location as a destination.';
    $out[] = '';
    $out[] = 'How to read it:';
    $out[] = '- No fares, travel times or contacts are recorded for ANY of these yet. Every price or duration question gets: you cannot confirm it, contact the Provincial Tourism Office. Do not estimate.';
    $out[] = '- The descriptions are draft website copy, not verified fact. Describe with them; do not present them as official.';
    $out[] = '- The short facts after each name are the only facilities or features you may mention. Do not add others.';
    $out[] = '';

    foreach ($rows as $d) {
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $line = $name . ' | ' . ($d['town'] ?? '') . ' | ' . ($d['tag'] ?? '');

        $chips = $d['chips'] ?? [];
        if (is_array($chips) && $chips) {
            $line .= ' | ' . implode('; ', array_map('strval', $chips));
        }
        $out[] = $line;

        if (!empty($d['desc'])) {
            $out[] = '  ' . $d['desc'] . ' [unverified]';
        }
    }

    $out[] = '';
    $out[] = 'Send unanswered questions to the Camarines Norte Provincial Tourism Office.';

    return implode("\n", $out);
}

function bud_build_context(PDO $pdo): string {

    $dests = $pdo->query(
        'SELECT id, name, town, tag, description, budget_min_php, budget_max_php,
                notes, data_status
           FROM destinations
          WHERE is_published = 1
       ORDER BY town, name'
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$dests) {
        return '';
    }

    /* One query per table rather than per destination — 24 places with
       four related tables would otherwise be about a hundred queries
       for a single chat message. */
    $chips    = bud_group($pdo, 'SELECT destination_id, label FROM destination_chips ORDER BY position');
    $fees     = bud_group($pdo, 'SELECT destination_id, label, amount_php, amount_max_php, unit, notes, effective_date FROM destination_fees');
    $routes   = bud_group($pdo, 'SELECT destination_id, origin, leg_order, mode, duration_minutes, fare_php, notes FROM destination_routes ORDER BY origin, leg_order');
    $contacts = bud_group($pdo, 'SELECT destination_id, org_name, org_type, phone, email, facebook, is_primary FROM destination_contacts ORDER BY is_primary DESC');

    $out = [];

    $out[] = 'DESTINATION DATA — this is the site\'s own database. Prefer it over anything you recall about the province.';
    $out[] = '';
    $out[] = 'How to read it:';
    $out[] = '- No fare, fee, duration or contact listed for a place means IT IS NOT KNOWN. It does not mean free, walkable, or unavailable. Say you cannot confirm it and point the visitor at the Provincial Tourism Office.';
    $out[] = '- Text marked [unverified] is draft website copy, not confirmed fact. You may describe the place with it, but do not present it as official.';
    $out[] = '- Never state a peso amount or a travel time that does not appear below. Do not estimate one, even if asked directly.';
    $out[] = '';

    foreach ($dests as $d) {
        $id = (int) $d['id'];

        $line = $d['name'] . ' | ' . $d['town'] . ' | ' . $d['tag'];

        if (!empty($chips[$id])) {
            $line .= ' | ' . implode('; ', array_column($chips[$id], 'label'));
        }
        $out[] = $line;

        if (!empty($d['description'])) {
            $mark = $d['data_status'] === 'verified' ? '' : ' [unverified]';
            $out[] = '  ' . $d['description'] . $mark;
        }

        /* --- fees --- */
        foreach ($fees[$id] ?? [] as $f) {
            $amount = 'P' . bud_num($f['amount_php']);
            if (!empty($f['amount_max_php'])) {
                $amount .= '-P' . bud_num($f['amount_max_php']);
            }

            $bit = '  fee: ' . $f['label'] . ' ' . $amount . ' ' . $f['unit'];
            if (!empty($f['effective_date'])) {
                $bit .= ' (as of ' . $f['effective_date'] . ')';
            }
            if (!empty($f['notes'])) {
                $bit .= ' - ' . $f['notes'];
            }
            $out[] = $bit;
        }

        /* --- routes --- */
        foreach ($routes[$id] ?? [] as $r) {
            $bit = '  from ' . $r['origin'] . ': leg ' . $r['leg_order'] . ' by ' . $r['mode'];

            if (!empty($r['duration_minutes'])) {
                $bit .= ', ' . bud_duration((int) $r['duration_minutes']);
            }
            if ($r['fare_php'] !== null && $r['fare_php'] !== '') {
                $bit .= ', P' . bud_num($r['fare_php']);
            }
            if (!empty($r['notes'])) {
                $bit .= ' - ' . $r['notes'];
            }
            $out[] = $bit;
        }

        /* --- budget --- */
        if (!empty($d['budget_min_php']) || !empty($d['budget_max_php'])) {
            $out[] = '  day budget: P' . bud_num($d['budget_min_php'])
                   . '-P' . bud_num($d['budget_max_php']) . ' per person';
        }

        /* --- contacts --- */
        foreach ($contacts[$id] ?? [] as $c) {
            $bits = array_filter([$c['phone'], $c['email'], $c['facebook']]);
            $out[] = '  contact: ' . $c['org_name']
                   . ($bits ? ' - ' . implode(', ', $bits) : ' (no number on file)');
        }

        if (!empty($d['notes'])) {
            $out[] = '  note: ' . $d['notes'];
        }
    }

    /* --- festivals --- */
    /* Appended rather than merged into the destination list: a visitor
       asking "what's on in June" is asking a different question from
       "where should I swim", and keeping the two blocks separate stops
       the model blending a fiesta into a beach listing. */
    $fest = bud_festivals($pdo);
    if ($fest !== '') {
        $out[] = '';
        $out[] = $fest;
    }

    /* --- who to send people to --- */
    $site = $pdo->query(
        'SELECT org_name, phone, email, facebook FROM site_contacts WHERE is_active = 1 LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    if ($site) {
        $bits = array_filter([$site['phone'], $site['email'], $site['facebook']]);
        $out[] = '';
        $out[] = 'Send unanswered questions to: ' . $site['org_name']
               . ($bits ? ' (' . implode(', ', $bits) . ')' : '');
    }

    return implode("\n", $out);
}

/**
 * Festivals, with the timing already worked out.
 *
 * The dates are resolved HERE, in PHP, and handed to the model as
 * plain statements — "already finished this year", "starts in 12
 * days". The model is never asked to compare dates itself. Language
 * models are unreliable at arithmetic on dates and have no dependable
 * idea what today is, and the failure mode is a confident wrong answer
 * that someone books a trip around.
 *
 * Returns '' when there are no festivals, or when the tables have not
 * been created yet.
 */
function bud_festivals(PDO $pdo): string {

    try {
        $rows = $pdo->query(
            'SELECT id, name, town, kind, description, typical_timing,
                    typical_month, is_movable, notes, data_status
               FROM festivals
              WHERE is_published = 1
           ORDER BY typical_month IS NULL, typical_month, name'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        /* Tables not created yet — destinations still work on their
           own, so this is not worth failing the whole request over. */
        return '';
    }

    if (!$rows) {
        return '';
    }

    $dates = bud_group_by($pdo,
        'SELECT festival_id, year, start_date, end_date, is_confirmed, notes
           FROM festival_dates ORDER BY start_date',
        'festival_id');

    $contacts = bud_group_by($pdo,
        'SELECT festival_id, org_name, phone, email, facebook
           FROM festival_contacts ORDER BY is_primary DESC',
        'festival_id');

    $today = new DateTimeImmutable('today');

    $out   = [];
    $out[] = 'FESTIVALS';
    $out[] = 'Dates below are already worked out against today\'s date. Repeat what a line says; do not recalculate it or work out day counts yourself.';
    $out[] = 'A festival with no confirmed dates has not announced them. Say the schedule is not out yet and give the usual timing — never guess a date someone might book travel around.';
    $out[] = '';

    foreach ($rows as $f) {
        $id = (int) $f['id'];

        $line = $f['name'] . ' | ' . $f['town'] . ' | ' . $f['kind'];
        $out[] = $line;

        if (!empty($f['description'])) {
            $mark = $f['data_status'] === 'verified' ? '' : ' [unverified]';
            $out[] = '  ' . $f['description'] . $mark;
        }

        if (!empty($f['typical_timing'])) {
            $out[] = '  timing: ' . $f['typical_timing']
                   . ($f['is_movable'] ? ' (movable — the date shifts each year)' : '');
        }

        /* Split this festival's dates around today. */
        $upcoming = null;
        $lastPast = null;

        foreach ($dates[$id] ?? [] as $d) {
            $end = new DateTimeImmutable($d['end_date'] ?: $d['start_date']);
            if ($end >= $today) {
                if ($upcoming === null) {
                    $upcoming = $d;   // ordered by start_date, so first wins
                }
            } else {
                $lastPast = $d;
            }
        }

        if ($upcoming) {
            $start = new DateTimeImmutable($upcoming['start_date']);
            $span  = bud_date_span($upcoming['start_date'], $upcoming['end_date']);
            $days  = (int) $today->diff($start)->format('%r%a');

            $when = $days <= 0 ? 'happening now' : 'starts in ' . $days . ' day' . ($days === 1 ? '' : 's');

            $out[] = '  next: ' . $span . ' — ' . $when
                   . ($upcoming['is_confirmed'] ? ' (confirmed)' : ' (expected, not yet announced)')
                   . (!empty($upcoming['notes']) ? ' - ' . $upcoming['notes'] : '');

        } elseif ($lastPast) {
            $out[] = '  last held ' . bud_date_span($lastPast['start_date'], $lastPast['end_date'])
                   . '; no future dates announced yet';
        } else {
            $out[] = '  no dates on file — schedule not announced';
        }

        foreach ($contacts[$id] ?? [] as $c) {
            $bits = array_filter([$c['phone'], $c['email'], $c['facebook']]);
            $out[] = '  contact: ' . $c['org_name']
                   . ($bits ? ' - ' . implode(', ', $bits) : ' (no number on file)');
        }

        if (!empty($f['notes'])) {
            $out[] = '  note: ' . $f['notes'];
        }
    }

    return implode("\n", $out);
}

/** '2026-06-12'..'2026-06-24' -> '12-24 June 2026' */
function bud_date_span(string $start, ?string $end): string {
    $s = new DateTimeImmutable($start);

    if (!$end || $end === $start) {
        return $s->format('j F Y');
    }

    $e = new DateTimeImmutable($end);

    if ($s->format('Y-m') === $e->format('Y-m')) {
        return $s->format('j') . '-' . $e->format('j F Y');
    }
    if ($s->format('Y') === $e->format('Y')) {
        return $s->format('j F') . ' - ' . $e->format('j F Y');
    }
    return $s->format('j F Y') . ' - ' . $e->format('j F Y');
}

/** Run a query and bucket the rows by an arbitrary key column. */
function bud_group_by(PDO $pdo, string $sql, string $key): array {
    $grouped = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[(int) $row[$key]][] = $row;
    }
    return $grouped;
}

/** Run a query and bucket the rows by destination_id. */
function bud_group(PDO $pdo, string $sql): array {
    $grouped = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[(int) $row['destination_id']][] = $row;
    }
    return $grouped;
}

/** 1500.00 -> 1,500 and 1500.50 -> 1,500.50 */
function bud_num($v): string {
    $f = (float) $v;
    return $f == floor($f) ? number_format($f) : number_format($f, 2);
}

/** 480 -> 8h, 90 -> 1h30m, 45 -> 45m */
function bud_duration(int $minutes): string {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    if ($h && $m) return $h . 'h' . $m . 'm';
    if ($h)       return $h . 'h';
    return $m . 'm';
}

/**
 * Cached wrapper. The context only changes when someone edits the
 * database, so rebuilding it on every chat message is wasted queries.
 */
function bud_context(PDO $pdo, string $cacheDir): string {
    /* The date is part of the filename because the festival lines say
       things like "starts in 12 days". A cache written at 23:55 would
       otherwise still be served at 00:05 with yesterday's arithmetic,
       and the count would be wrong by one all morning. A new day means
       a new file; the old ones are harmless. */
    $cacheFile = rtrim($cacheDir, '/') . '/bud-context-' . date('Y-m-d') . '.txt';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < BUD_CONTEXT_TTL) {
        $cached = file_get_contents($cacheFile);
        if ($cached !== false && $cached !== '') {
            return $cached;
        }
    }

    $text = bud_build_context($pdo);

    if ($text !== '') {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        /* Write to a temp name and rename, so a chat request that lands
           mid-write reads the old file rather than half the new one. */
        $tmp = $cacheFile . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $text) !== false) {
            @rename($tmp, $cacheFile);
        }
    }

    return $text;
}