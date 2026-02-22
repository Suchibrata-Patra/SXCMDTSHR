<?php
// ─────────────────────────────────────────────────────────────
//  scholar.php — ScholarLens core library
//
//  THE ACTUAL ROOT CAUSE & FIX:
//  Gemini with google_search grounding returns PROSE, not JSON.
//  When you ask it to return JSON while grounding is active it
//  either returns malformed output or empty text because the
//  grounding layer overrides the output format.
//
//  SOLUTION: Two-step approach
//  Step 1: Call Gemini WITH google_search grounding.
//           Ask for plain prose facts — let it ground naturally.
//  Step 2: Feed that prose into a second Gemini call WITHOUT
//           grounding. Ask it to extract structured JSON from
//           the prose text. This ALWAYS returns clean JSON.
// ─────────────────────────────────────────────────────────────

define('GEMINI_API_KEY',  getenv('GEMINI_API_KEY') ?: 'AIzaSyBLUDO86HM_8ye6ks_d7uYy_BMU63UkPS8');
define('GEMINI_URL',
    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key='
    . GEMINI_API_KEY
);

// ── cURL GET (Scholar) ────────────────────────────────────────
function curlGet(string $url, int $timeout = 25): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer: https://scholar.google.com/',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)          throw new Exception("cURL error: $err");
    if ($code === 429) throw new Exception("Scholar rate-limited (429). Wait ~60s and retry.");
    if ($code === 403) throw new Exception("Scholar blocked request (403). Try again shortly.");
    if ($code !== 200) throw new Exception("HTTP $code from Scholar.");
    if (empty($body))  throw new Exception("Empty Scholar response.");
    return $body;
}

// ── Low-level Gemini POST ─────────────────────────────────────
function geminiPost(array $body, int $timeout = 40): ?array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => GEMINI_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || empty($raw)) return null;
    $resp = json_decode($raw, true);
    if (!is_array($resp) || isset($resp['error'])) return null;
    return $resp;
}

// ── Extract all text from a Gemini response ───────────────────
function geminiText(?array $resp): string {
    if (!$resp) return '';
    $text = '';
    foreach ($resp['candidates'] ?? [] as $c) {
        foreach ($c['content']['parts'] ?? [] as $p) {
            if (!empty($p['text'])) $text .= $p['text'];
        }
    }
    return trim($text);
}

// ── Parse Scholar HTML ────────────────────────────────────────
function parseScholarPage(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp  = new DOMXPath($doc);

    $name  = trim($xp->evaluate('string(//*[@id="gsc_prf_in"])'));
    $affil = '';
    $an    = $xp->query('//*[contains(@class,"gsc_prf_il")]');
    if ($an->length) $affil = trim($an->item(0)->textContent);

    $avatar = '';
    $img    = $xp->query('//*[@id="gsc_prf_pup-img"]')->item(0);
    if ($img) {
        $s      = $img->getAttribute('src');
        $avatar = str_starts_with($s, 'http') ? $s : 'https://scholar.google.com'.$s;
    }

    $interests = [];
    foreach ($xp->query('//*[@id="gsc_prf_int"]//a') as $n)
        $interests[] = trim($n->textContent);

    $sc = $xp->query('//*[@id="gsc_rsb_st"]//td[contains(@class,"gsc_rsb_std")]');
    $st = [];
    foreach ($sc as $c) $st[] = trim($c->textContent);

    $papers = [];
    foreach ($xp->query('//*[contains(@class,"gsc_a_tr")]') as $row) {
        $tn    = $xp->query('.//*[contains(@class,"gsc_a_at")]', $row)->item(0);
        $title = $tn ? trim($tn->textContent) : '';
        $link  = $tn ? 'https://scholar.google.com'.$tn->getAttribute('href') : '';

        $gn    = $xp->query('.//*[contains(@class,"gs_gray")]', $row);
        $venue = '';
        if ($gn->length >= 2)     $venue = trim($gn->item(1)->textContent);
        elseif ($gn->length >= 1) $venue = trim($gn->item(0)->textContent);

        $yn    = $xp->query('.//*[contains(@class,"gsc_a_y")]//span', $row)->item(0);
        $year  = $yn ? trim($yn->textContent) : '';

        $cn    = $xp->query('.//*[contains(@class,"gsc_a_c")]//a', $row)->item(0);
        $cited = $cn ? trim($cn->textContent) : '0';

        if (empty($title)) continue;
        $papers[] = compact('title', 'link', 'venue', 'year', 'cited');
    }

    return [
        'name'      => $name,
        'affil'     => $affil,
        'avatar'    => $avatar,
        'interests' => $interests,
        'citations' => $st[0] ?? '—',
        'hIndex'    => $st[2] ?? '—',
        'i10Index'  => $st[4] ?? '—',
        'papers'    => $papers,
    ];
}

// ── Clean venue string ────────────────────────────────────────
function cleanVenue(string $v): string {
    if (str_contains($v, ' - '))
        $v = trim(explode(' - ', $v, 2)[1]);
    $v = preg_replace('/,\s*(\d{4}|\d+[-–]\d+)(\s*,\s*\d+[-–]\d+)?\s*$/', '', $v);
    return trim($v);
}

// ── STEP 1: Grounded search — returns prose facts ─────────────
function groundedSearch(string $journal): ?array {
    $resp = geminiPost([
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' =>
                "Search for the current journal metrics for \"$journal\". " .
                "Find and report: the Impact Factor (JIF), SJR score, Quartile (Q1/Q2/Q3/Q4), " .
                "CiteScore, h-index, publisher, and ISSN. " .
                "Report all numbers you find. Be specific with values."
            ]],
        ]],
        'tools' => [['google_search' => (object)[]]],  // grounding: returns prose
        'generationConfig' => ['temperature' => 1.0, 'maxOutputTokens' => 1024],
    ]);

    $text = geminiText($resp);
    if (empty($text)) return null;

    // Also grab grounding sources
    $chunks  = $resp['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];
    $queries = $resp['candidates'][0]['groundingMetadata']['webSearchQueries'] ?? [];

    return [
        'prose'   => $text,
        'chunks'  => $chunks,
        'queries' => $queries,
    ];
}

// ── STEP 2: Extract JSON from prose — no grounding ────────────
function extractMetricsFromProse(string $journal, string $prose): ?array {
    $resp = geminiPost([
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' =>
                "From the following research about the journal \"$journal\", " .
                "extract the metrics and return ONLY a valid JSON object. " .
                "No markdown, no explanation, just the JSON.\n\n" .
                "Text to extract from:\n$prose\n\n" .
                "Return this exact JSON structure (use null for missing fields):\n" .
                '{"journal_name":null,"impact_factor":null,"impact_factor_year":null,' .
                '"sjr":null,"quartile":null,"cite_score":null,"h_index":null,' .
                '"publisher":null,"issn":null,"source":null}'
            ]],
        ]],
        // NO google_search tool here — just JSON extraction
        'generationConfig' => [
            'temperature'      => 0.0,
            'maxOutputTokens'  => 400,
            'responseMimeType' => 'application/json',  // safe to use without grounding
        ],
    ]);

    $text = geminiText($resp);
    if (empty($text)) return null;

    // Clean and parse
    $text = preg_replace('/```(?:json)?\s*/i', '', $text);
    $text = trim(preg_replace('/\s*```/', '', $text));

    // Find JSON object
    $s = strpos($text, '{');
    $e = strrpos($text, '}');
    if ($s === false || $e === false || $e <= $s) return null;

    $data = json_decode(substr($text, $s, $e - $s + 1), true);
    return is_array($data) ? $data : null;
}

// ── Main: fetch metrics for one journal ───────────────────────
function fetchMetricsViaGemini(string $journal): ?array {
    if (empty(trim($journal))) return null;
    if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') return null;

    // Step 1: grounded search → prose
    $grounded = groundedSearch($journal);
    if (!$grounded || empty($grounded['prose'])) return null;

    // Step 2: extract JSON from prose
    $metrics = extractMetricsFromProse($journal, $grounded['prose']);
    if (!$metrics) return null;

    // Attach grounding sources from step 1
    $metrics['grounding_sources'] = array_values(array_filter(
        array_map(fn($c) => isset($c['web']['title'])
            ? ['title' => $c['web']['title'], 'uri' => $c['web']['uri'] ?? '#']
            : null,
        $grounded['chunks']
    ));
    $metrics['web_search_queries'] = $grounded['queries'];
    $metrics['prose_debug']        = $grounded['prose']; // debug — remove in production

    return $metrics;
}

// ── AJAX endpoint ─────────────────────────────────────────────
if (isset($_GET['ajax_venue'])) {
    header('Content-Type: application/json; charset=utf-8');
    $venue = trim($_GET['ajax_venue'] ?? '');
    if (strlen($venue) < 2) { echo json_encode(['_empty' => true]); exit; }
    $m = fetchMetricsViaGemini($venue);
    echo json_encode($m ?? ['_not_found' => true]);
    exit;
}

// ── Debug: see raw prose + extracted JSON ─────────────────────
if (isset($_GET['debug_venue'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $venue = trim($_GET['debug_venue']);
    echo "=== JOURNAL: $venue ===\n\n";
    echo "STEP 1 — Grounded prose:\n";
    $g = groundedSearch($venue);
    echo ($g['prose'] ?? 'NULL') . "\n\n";
    echo "Grounding sources: " . count($g['chunks'] ?? []) . "\n";
    echo "Queries: " . implode(', ', $g['queries'] ?? []) . "\n\n";
    echo "STEP 2 — Extracted JSON:\n";
    if ($g) {
        $m = extractMetricsFromProse($venue, $g['prose'] ?? '');
        print_r($m);
    }
    exit;
}

// ── Fetch Scholar profile ─────────────────────────────────────
function fetchScholarProfile(string $userId): array {
    $url  = "https://scholar.google.com/citations?user={$userId}&sortby=pubdate&pagesize=100&hl=en";
    $html = curlGet($url);
    $data = parseScholarPage($html);

    if (empty($data['name']))
        throw new Exception("Could not parse Scholar profile. May be private or Scholar temporarily blocked it.");

    $venues = [];
    foreach ($data['papers'] as &$paper) {
        $vc = cleanVenue($paper['venue'] ?? '');
        $paper['venue_clean'] = $vc;
        if (!empty($vc)) $venues[$vc] = true;
    }
    unset($paper);

    return [
        'name'          => $data['name'],
        'affiliation'   => $data['affil'],
        'avatar'        => $data['avatar'],
        'interests'     => $data['interests'],
        'citations'     => $data['citations'],
        'h_index'       => $data['hIndex'],
        'i10_index'     => $data['i10Index'],
        'papers'        => $data['papers'],
        'unique_venues' => array_keys($venues),
    ];
}