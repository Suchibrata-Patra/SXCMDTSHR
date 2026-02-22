<?php
// ─────────────────────────────────────────────────────────────
//  scholar.php — Core scraping + journal metrics enrichment
//
//  Journal metrics strategy (all FREE, no API key needed):
//    1. SCImago (scimagojr.com) → SJR, Quartile (Q1–Q4),
//       Cites/Doc 2yr (mathematically ≡ JIF on Scopus data),
//       h-index
//    2. OpenAlex API → fallback if SCImago finds nothing
// ─────────────────────────────────────────────────────────────

// ── Generic cURL GET ─────────────────────────────────────────
function curlGet(string $url, array $headers = [], int $timeout = 20): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_HTTPHEADER     => array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ], $headers),
    ]);
    $body  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error)        throw new Exception("cURL error: $error");
    if ($code === 429) throw new Exception("Google Scholar rate-limited this server (429). Wait a minute and retry.");
    if ($code === 403) throw new Exception("Google Scholar blocked the request (403). Try again in a few minutes.");
    if ($code !== 200) throw new Exception("HTTP {$code} received from target.");
    if (empty($body))  throw new Exception("Empty response body.");

    return $body;
}

// ── Parse Google Scholar profile HTML ────────────────────────
function parseScholarPage(string $html): array {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp = new DOMXPath($doc);

    // Name
    $name = trim($xp->evaluate('string(//*[@id="gsc_prf_in"])'));

    // Affiliation
    $affilNodes = $xp->query('//*[contains(@class,"gsc_prf_il")]');
    $affil = $affilNodes->length ? trim($affilNodes->item(0)->textContent) : '';

    // Avatar image
    $imgNode = $xp->query('//*[@id="gsc_prf_pup-img"]')->item(0);
    $avatar  = '';
    if ($imgNode) {
        $src    = $imgNode->getAttribute('src');
        $avatar = str_starts_with($src, 'http') ? $src : 'https://scholar.google.com' . $src;
    }

    // Research interests
    $interests = [];
    foreach ($xp->query('//*[@id="gsc_prf_int"]//a') as $n) {
        $interests[] = trim($n->textContent);
    }

    // Citation stats table: [cit-all, cit-recent, h-all, h-recent, i10-all, i10-recent]
    $statCells = $xp->query('//*[@id="gsc_rsb_st"]//td[contains(@class,"gsc_rsb_std")]');
    $stats = [];
    foreach ($statCells as $c) $stats[] = trim($c->textContent);
    $citations = $stats[0] ?? '—';
    $hIndex    = $stats[2] ?? '—';
    $i10Index  = $stats[4] ?? '—';

    // Paper rows
    $papers = [];
    foreach ($xp->query('//*[contains(@class,"gsc_a_tr")]') as $row) {
        $titleNode = $xp->query('.//*[contains(@class,"gsc_a_at")]', $row)->item(0);
        $title     = $titleNode ? trim($titleNode->textContent) : '';
        $link      = $titleNode
            ? 'https://scholar.google.com' . $titleNode->getAttribute('href')
            : '';

        // Venue is the second .gs_gray child of the row
        $grayNodes = $xp->query('.//*[contains(@class,"gs_gray")]', $row);
        $venue = '';
        if ($grayNodes->length >= 2)     $venue = trim($grayNodes->item(1)->textContent);
        elseif ($grayNodes->length === 1) $venue = trim($grayNodes->item(0)->textContent);

        $yearNode = $xp->query('.//*[contains(@class,"gsc_a_y")]//span', $row)->item(0);
        $year     = $yearNode ? trim($yearNode->textContent) : '';

        $citeNode = $xp->query('.//*[contains(@class,"gsc_a_c")]//a', $row)->item(0);
        $cited    = $citeNode ? trim($citeNode->textContent) : '0';

        if (empty($title)) continue;
        $papers[] = compact('title', 'link', 'venue', 'year', 'cited');
    }

    return compact('name', 'affil', 'avatar', 'interests', 'citations', 'hIndex', 'i10Index', 'papers');
}

// ── Clean venue string from Scholar ──────────────────────────
// Scholar venue cells often contain "Author A, B - Journal Name, Year"
function cleanVenue(string $venue): string {
    // Strip "AuthorA, AuthorB - " prefix
    if (str_contains($venue, ' - ')) {
        $venue = trim(explode(' - ', $venue, 2)[1]);
    }
    // Strip trailing year or page range: ", 2020" or ", 12-45" or ", 2020, 12-45"
    $venue = preg_replace('/,\s*(\d{4}|\d+-\d+)(\s*,\s*\d+-\d+)?\s*$/', '', $venue);
    return trim($venue);
}

// ── SCImago journal lookup ────────────────────────────────────
// Scrapes scimagojr.com — fully free, no API key.
// Returns: journal_name, sjr, quartile, cites_per_doc_2yr, h_index, source
function fetchScimagoMetrics(string $venue): ?array {
    if (empty($venue)) return null;

    // Step 1: search page
    $searchUrl = 'https://www.scimagojr.com/journalsearch.php?q='
        . urlencode($venue) . '&tip=jou&clean=0';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $searchUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (compatible; ScholarLens/1.0)',
            'Referer: https://www.scimagojr.com/',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    $searchHtml = curl_exec($ch);
    $searchCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($searchCode !== 200 || empty($searchHtml)) return null;

    // Extract detail page link from search results
    // SCImago result links look like: journalsearch.php?q=12345&tip=sid
    if (!preg_match('/href="(journalsearch\.php\?q=\d+&tip=sid[^"]*)"/', $searchHtml, $linkMatch)) {
        return null;
    }
    $detailUrl = 'https://www.scimagojr.com/' . $linkMatch[1];

    // Step 2: detail page
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL            => $detailUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (compatible; ScholarLens/1.0)',
            'Referer: ' . $searchUrl,
        ],
    ]);
    $detailHtml = curl_exec($ch2);
    $detailCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($detailCode !== 200 || empty($detailHtml)) return null;

    $metrics = ['source' => 'SCImago'];

    // Journal display name
    if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/i', $detailHtml, $nm)) {
        $metrics['journal_name'] = trim(html_entity_decode($nm[1]));
    }

    // ── Parse embedded JS data array ──────────────────────────
    // SCImago injects: var data_... = [[year, docs, refs, cites, self_cites,
    //                                   cites_per_doc, refs_per_doc, sjr], ...]
    // "cites_per_doc" at index 5 over 2 years window ≈ JIF formula
    if (preg_match('/var\s+data_[a-z_]+\s*=\s*(\[.*?\]);/s', $detailHtml, $dataMatch)) {
        $arr = json_decode($dataMatch[1], true);
        if (is_array($arr) && !empty($arr)) {
            $latest = end($arr); // most recent year
            if (is_array($latest)) {
                if (isset($latest[7]) && is_numeric($latest[7]) && $latest[7] > 0) {
                    $metrics['sjr'] = number_format((float)$latest[7], 3);
                }
                if (isset($latest[5]) && is_numeric($latest[5]) && $latest[5] > 0) {
                    $metrics['cites_per_doc_2yr'] = number_format((float)$latest[5], 2);
                }
            }
        }
    }

    // Fallback: regex for SJR value shown on page
    if (empty($metrics['sjr'])) {
        if (preg_match('/SJR\s*[\s\S]{0,60}?(\d+[\.,]\d+)/i', $detailHtml, $sm)) {
            $metrics['sjr'] = str_replace(',', '.', $sm[1]);
        }
    }

    // Quartile: SCImago puts quartile in spans/divs near "Best Quartile"
    if (preg_match('/Best Quartile[\s\S]{0,200}?(Q[1-4])/i', $detailHtml, $qm)
     || preg_match('/class="[^"]*quartile[^"]*"[^>]*>\s*(Q[1-4])/i', $detailHtml, $qm)
     || preg_match('/<span[^>]*title="(Q[1-4])"/i', $detailHtml, $qm)
     || preg_match('/>(Q[1-4])<\/span>/i', $detailHtml, $qm)) {
        $metrics['quartile'] = $qm[1];
    }

    // H-index: look for labelled value
    if (preg_match('/H\s*[Ii]ndex[\s\S]{0,100}?(\d+)/i', $detailHtml, $hm)) {
        $metrics['h_index'] = $hm[1];
    }

    // Only return if we got at least one useful metric
    if (empty($metrics['sjr']) && empty($metrics['h_index']) && empty($metrics['quartile'])
        && empty($metrics['cites_per_doc_2yr'])) {
        return null;
    }

    return $metrics;
}

// ── OpenAlex fallback ─────────────────────────────────────────
function fetchOpenAlexMetrics(string $venue): ?array {
    if (empty($venue)) return null;

    $url = 'https://api.openalex.org/sources?search='
        . urlencode(substr($venue, 0, 100))
        . '&per-page=1&mailto=scholarlens@example.com';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['User-Agent: ScholarLens/1.0'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => 'gzip, deflate',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    if (!$body) return null;
    $data = json_decode($body, true);
    $src  = $data['results'][0] ?? null;
    if (!$src) return null;

    $cs   = $src['summary_stats']['2yr_mean_citedness'] ?? null;
    $hIdx = $src['summary_stats']['h_index'] ?? null;
    if (!$cs && !$hIdx) return null;

    return [
        'source'            => 'OpenAlex',
        'journal_name'      => $src['display_name'] ?? '',
        'cites_per_doc_2yr' => $cs   ? number_format((float)$cs, 2) : null,
        'h_index'           => $hIdx ? (string)$hIdx : null,
    ];
}

// ── Combined lookup: SCImago first, OpenAlex fallback ─────────
function fetchJournalMetrics(string $venue): ?array {
    $m = fetchScimagoMetrics($venue);
    if ($m) return $m;

    usleep(100000); // 100ms pause before fallback
    return fetchOpenAlexMetrics($venue);
}

// ── Main entry point ──────────────────────────────────────────
function fetchScholarProfile(string $userId): array {
    $url  = "https://scholar.google.com/citations?user={$userId}&sortby=pubdate&pagesize=100&hl=en";
    $html = curlGet($url);
    $data = parseScholarPage($html);

    if (empty($data['name'])) {
        throw new Exception(
            "Could not parse Scholar profile. "
            . "The profile may be private, or Google temporarily blocked the request. "
            . "Try again in a minute."
        );
    }

    // Enrich papers; cache metrics per unique cleaned venue name
    $venueCache = [];
    foreach ($data['papers'] as &$paper) {
        $venue = cleanVenue($paper['venue'] ?? '');
        $paper['venue_clean'] = $venue;

        if (empty($venue)) {
            $paper['metrics'] = null;
            continue;
        }

        if (!array_key_exists($venue, $venueCache)) {
            $venueCache[$venue] = fetchJournalMetrics($venue);
            usleep(200000); // 200ms between lookups — polite crawling
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