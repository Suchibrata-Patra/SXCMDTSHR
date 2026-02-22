<?php
// ─────────────────────────────────────────────────────────────
//  scholar.php  — Core library
//
//  • Fetches Google Scholar profile via server-side cURL
//  • Enriches each paper's journal with Gemini 2.0 Flash
//    using google_search grounding for REAL-TIME Impact Factor
//
//  ✦ FIX NOTES (why IF was blank before):
//    1. google_search tool key must be "googleSearch" (camelCase)
//       not "google_search" — Gemini v1beta API spec.
//    2. When google_search grounding is active Gemini sometimes
//       returns the grounded answer in a separate "text" part
//       after a tool-use part — we must scan ALL parts.
//    3. Prompt now explicitly forbids null and demands numbers.
// ─────────────────────────────────────────────────────────────

define('GEMINI_API_KEY',  getenv('GEMINI_API_KEY')  ?: 'AIzaSyBLUDO86HM_8ye6ks_d7uYy_BMU63UkPS8');
define('GEMINI_MODEL',    'gemini-2.0-flash');
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/'
    . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY);

// ── cURL GET (Scholar scraping) ───────────────────────────────
function curlGet(string $url, int $timeout = 22): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',          // accept any encoding
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Referer: https://scholar.google.com/',
        ],
    ]);
    $body  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($err)        throw new Exception("cURL error: $err");
    if ($code === 429) throw new Exception("Scholar rate-limited (HTTP 429). Wait ~60 s and retry.");
    if ($code === 403) throw new Exception("Scholar blocked the request (HTTP 403). Try again shortly.");
    if ($code !== 200) throw new Exception("Unexpected HTTP $code from Scholar.");
    if (empty($body))  throw new Exception("Empty response body.");
    return $body;
}

// ── Parse Scholar profile HTML ────────────────────────────────
function parseScholarPage(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp  = new DOMXPath($doc);

    $name  = trim($xp->evaluate('string(//*[@id="gsc_prf_in"])'));
    $affil = '';
    $afn   = $xp->query('//*[contains(@class,"gsc_prf_il")]');
    if ($afn->length) $affil = trim($afn->item(0)->textContent);

    $avatar = '';
    $img    = $xp->query('//*[@id="gsc_prf_pup-img"]')->item(0);
    if ($img) {
        $s = $img->getAttribute('src');
        $avatar = str_starts_with($s, 'http') ? $s : 'https://scholar.google.com'.$s;
    }

    $interests = [];
    foreach ($xp->query('//*[@id="gsc_prf_int"]//a') as $n)
        $interests[] = trim($n->textContent);

    // Stat table: [cit-all, cit-recent, h-all, h-recent, i10-all, i10-recent]
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
        elseif ($gn->length == 1) $venue = trim($gn->item(0)->textContent);

        $yn   = $xp->query('.//*[contains(@class,"gsc_a_y")]//span', $row)->item(0);
        $year = $yn ? trim($yn->textContent) : '';

        $cn    = $xp->query('.//*[contains(@class,"gsc_a_c")]//a', $row)->item(0);
        $cited = $cn ? trim($cn->textContent) : '0';

        if (empty($title)) continue;
        $papers[] = compact('title','link','venue','year','cited');
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

// ── Clean venue string from Scholar ──────────────────────────
function cleanVenue(string $v): string {
    // Strip "Authors - " prefix that Scholar prepends
    if (str_contains($v, ' - '))
        $v = trim(explode(' - ', $v, 2)[1]);
    // Strip trailing ", 2023" or ", 45-67"
    $v = preg_replace('/,\s*(\d{4}|\d+[-–]\d+)(\s*,\s*\d+[-–]\d+)?\s*$/', '', $v);
    return trim($v);
}

// ── Gemini + Google Search → Journal metrics ──────────────────
function fetchMetricsViaGemini(string $journal): ?array {
    if (empty(trim($journal))) return null;
    if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') return null;

    // ── Strict prompt that demands a JSON object ──────────────
    // We explicitly tell Gemini to search for the IF and NOT
    // return null — find the best available figure.
    $prompt = <<<PROMPT
You are a research metrics assistant. Use Google Search to find current journal metrics for:

Journal: "{$journal}"

Search for: "{$journal} impact factor 2024 site:clarivate.com OR site:scimagojr.com OR site:scopus.com OR site:elsevier.com"

Then return ONLY a raw JSON object (no markdown, no code fences, no text before or after):

{
  "journal_name": "official journal name",
  "impact_factor": "number only, e.g. 4.521",
  "impact_factor_year": "e.g. 2024",
  "sjr": "number only, e.g. 1.234",
  "quartile": "Q1 or Q2 or Q3 or Q4",
  "cite_score": "number only, e.g. 6.7",
  "h_index": "integer only, e.g. 145",
  "publisher": "publisher name",
  "issn": "ISSN",
  "source": "domain where found, e.g. scimagojr.com"
}

Rules:
- impact_factor must be a number string like "4.521" — NEVER null if any IF exists online
- Use null ONLY when absolutely no data exists anywhere
- Do NOT wrap in markdown
PROMPT;

    $payload = json_encode([
        'contents' => [[
            'role'  => 'user',
            'parts' => [['text' => $prompt]],
        ]],
        // ✦ FIXED: correct camelCase key for Gemini v1beta
        'tools' => [['googleSearch' => (object)[]]],
        'generationConfig' => [
            'temperature'     => 0.0,
            'maxOutputTokens' => 600,
            'responseMimeType'=> 'application/json',   // force JSON output mode
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => GEMINI_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || empty($body)) return null;

    $resp = json_decode($body, true);
    if (!$resp) return null;

    // ── Extract text — scan ALL parts (grounding fix) ─────────
    $text = '';
    $parts = $resp['candidates'][0]['content']['parts'] ?? [];
    foreach ($parts as $part) {
        if (!empty($part['text'])) {
            $text .= $part['text'];
        }
    }
    if (empty($text)) return null;

    // Strip any accidental markdown fences
    $text = preg_replace('/```(?:json)?\s*/i', '', $text);
    $text = trim(preg_replace('/\s*```/', '', $text));

    // Extract first {...} JSON block
    if (!preg_match('/\{[\s\S]*?\}/s', $text, $m)) return null;
    $data = json_decode($m[0], true);
    if (!is_array($data)) return null;

    // Attach grounding metadata
    $chunks = $resp['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];
    $data['grounding_sources'] = array_values(array_filter(
        array_map(fn($c) => !empty($c['web']['title'])
            ? ['title'=>$c['web']['title'],'uri'=>$c['web']['uri']??'#']
            : null,
        $chunks)
    ));
    $data['web_search_queries'] = $resp['candidates'][0]['groundingMetadata']['webSearchQueries'] ?? [];

    return $data;
}

// ── AJAX endpoint: fetch one venue's metrics ──────────────────
// Called by the frontend JS — returns JSON for a single venue.
// This keeps the HTML page load fast (Scholar only) and does
// the slow Gemini calls one-by-one asynchronously.
if (!empty($_GET['ajax_venue'])) {
    header('Content-Type: application/json');
    $v = trim($_GET['ajax_venue']);
    if (strlen($v) < 2) { echo json_encode(null); exit; }
    $metrics = fetchMetricsViaGemini($v);
    echo json_encode($metrics ?? ['_not_found' => true]);
    exit;
}

// ── Main: fetch Scholar profile (NO Gemini yet — done via AJAX)
function fetchScholarProfile(string $userId): array {
    $url  = "https://scholar.google.com/citations?user={$userId}&sortby=pubdate&pagesize=100&hl=en";
    $html = curlGet($url);
    $data = parseScholarPage($html);

    if (empty($data['name'])) {
        throw new Exception("Could not parse Scholar profile. Profile may be private or Scholar temporarily blocked.");
    }

    // Clean venue names, deduplicate
    $venuesSeen = [];
    foreach ($data['papers'] as &$paper) {
        $vc = cleanVenue($paper['venue'] ?? '');
        $paper['venue_clean'] = $vc;
        if (!empty($vc)) $venuesSeen[$vc] = true;
    }
    unset($paper);

    return [
        'name'         => $data['name'],
        'affiliation'  => $data['affil'],
        'avatar'       => $data['avatar'],
        'interests'    => $data['interests'],
        'citations'    => $data['citations'],
        'h_index'      => $data['hIndex'],
        'i10_index'    => $data['i10Index'],
        'papers'       => $data['papers'],
        'unique_venues'=> array_keys($venuesSeen),
    ];
}