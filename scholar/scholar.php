<?php
// ─────────────────────────────────────────────────────────────
//  scholar.php — Core scraping + OpenAlex enrichment
// ─────────────────────────────────────────────────────────────

// ── cURL helper ──────────────────────────────────────────────
function curlGet(string $url, array $headers = []): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_HTTPHEADER     => array_merge([
            // Mimic a real browser to avoid bot blocks
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer: https://scholar.google.com/',
        ], $headers),
    ]);
    $body   = curl_exec($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($error) throw new Exception("cURL error: $error");
    if ($code === 429) throw new Exception("Google Scholar rate-limited this server (429). Wait a minute and retry.");
    if ($code === 403) throw new Exception("Google Scholar blocked the request (403). Try again later.");
    if ($code !== 200) throw new Exception("HTTP $code from Scholar.");
    if (empty($body))  throw new Exception("Empty response from Scholar.");

    return $body;
}

// ── Parse Scholar HTML ────────────────────────────────────────
function parseScholarPage(string $html): array {
    // Suppress XML errors for messy HTML
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp  = new DOMXPath($doc);

    // Name
    $name = trim($xp->evaluate('string(//*[@id="gsc_prf_in"])'));

    // Affiliation (first .gsc_prf_il)
    $affilNodes = $xp->query('//*[contains(@class,"gsc_prf_il")]');
    $affil = $affilNodes->length ? trim($affilNodes->item(0)->textContent) : '';

    // Avatar
    $imgNode = $xp->query('//*[@id="gsc_prf_pup-img"]')->item(0);
    $avatar  = '';
    if ($imgNode) {
        $src = $imgNode->getAttribute('src');
        $avatar = str_starts_with($src, 'http') ? $src : 'https://scholar.google.com' . $src;
    }

    // Interests
    $interestNodes = $xp->query('//*[@id="gsc_prf_int"]//a');
    $interests = [];
    foreach ($interestNodes as $n) $interests[] = trim($n->textContent);

    // Stats: citations, h-index, i10-index (pairs: all-time / since 2019)
    $statCells = $xp->query('//*[@id="gsc_rsb_st"]//td[contains(@class,"gsc_rsb_std")]');
    $stats = [];
    foreach ($statCells as $c) $stats[] = trim($c->textContent);
    // Layout: [cit-all, cit-recent, h-all, h-recent, i10-all, i10-recent]
    $citations  = $stats[0] ?? '—';
    $hIndex     = $stats[2] ?? '—';
    $i10Index   = $stats[4] ?? '—';

    // Papers
    $rows   = $xp->query('//*[contains(@class,"gsc_a_tr")]');
    $papers = [];
    foreach ($rows as $row) {
        $titleNode = $xp->query('.//*[contains(@class,"gsc_a_at")]', $row)->item(0);
        $title     = $titleNode ? trim($titleNode->textContent) : '';
        $link      = $titleNode ? 'https://scholar.google.com' . $titleNode->getAttribute('href') : '';

        // Venue: second .gs_gray sibling after title
        $grayNodes = $xp->query('.//*[contains(@class,"gs_gray")]', $row);
        $venue     = $grayNodes->length >= 2 ? trim($grayNodes->item(1)->textContent) : ($grayNodes->length ? trim($grayNodes->item(0)->textContent) : '');

        // Year
        $yearNode  = $xp->query('.//*[contains(@class,"gsc_a_y")]//span', $row)->item(0);
        $year      = $yearNode ? trim($yearNode->textContent) : '';

        // Citations
        $citeNode  = $xp->query('.//*[contains(@class,"gsc_a_c")]//a', $row)->item(0);
        $cited     = $citeNode ? trim($citeNode->textContent) : '0';

        if (empty($title)) continue;
        $papers[] = compact('title', 'link', 'venue', 'year', 'cited');
    }

    return compact('name', 'affil', 'avatar', 'interests', 'citations', 'hIndex', 'i10Index', 'papers');
}

// ── OpenAlex journal metrics ──────────────────────────────────
function fetchJournalMetrics(string $venue): ?array {
    if (empty($venue)) return null;

    $query = urlencode(substr($venue, 0, 100));
    $url   = "https://api.openalex.org/sources?search={$query}&per-page=1&mailto=scholarlens@example.com";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['User-Agent: ScholarLens/1.0 (scholarlens@example.com)'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    if (!$body) return null;
    $data = json_decode($body, true);
    $src  = $data['results'][0] ?? null;
    if (!$src) return null;

    $cs      = $src['summary_stats']['2yr_mean_citedness'] ?? null;
    $hIdx    = $src['summary_stats']['h_index'] ?? null;
    $srcName = $src['display_name'] ?? '';

    if (!$cs && !$hIdx) return null;

    return [
        'citescore'   => $cs   ? number_format((float)$cs, 2)  : null,
        'h_index'     => $hIdx ? (string)$hIdx : null,
        'source_name' => $srcName,
    ];
}

// ── Main: fetch + enrich ──────────────────────────────────────
function fetchScholarProfile(string $userId): array {
    $url  = "https://scholar.google.com/citations?user={$userId}&sortby=pubdate&pagesize=100&hl=en";
    $html = curlGet($url);

    $data = parseScholarPage($html);

    if (empty($data['name'])) {
        throw new Exception("Could not parse Scholar profile. The profile may be private or Google blocked the request.");
    }

    // Enrich each paper with journal metrics
    // Cache by venue name to avoid duplicate API calls
    $venueCache = [];
    foreach ($data['papers'] as &$paper) {
        $venue = $paper['venue'];
        if (empty($venue)) {
            $paper['metrics'] = null;
            continue;
        }
        if (!isset($venueCache[$venue])) {
            $venueCache[$venue] = fetchJournalMetrics($venue);
            usleep(120000); // 120ms delay — respect OpenAlex rate limits
        }
        $paper['metrics'] = $venueCache[$venue];
    }
    unset($paper);

    return [
        'name'        => $data['name'],
        'affiliation' => $data['affil'],
        'avatar'      => $data['avatar'],
        'interests'   => $data['interests'],
        'citations'   => $data['citations'],
        'h_index'     => $data['hIndex'],
        'i10_index'   => $data['i10Index'],
        'papers'      => $data['papers'],
    ];
}