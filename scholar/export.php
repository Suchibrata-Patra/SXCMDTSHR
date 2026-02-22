<?php
// export.php — CSV download with full journal metrics
require_once 'scholar.php';

$userId = trim($_GET['user_id'] ?? '');
if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) $userId = $m[1];
$userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);

if (empty($userId)) { http_response_code(400); die('Missing user_id'); }

try {
    $result = fetchScholarProfile($userId);
} catch (Exception $e) {
    http_response_code(500); die('Error: ' . $e->getMessage());
}

$filename = preg_replace('/[^A-Za-z0-9_]/', '_', $result['name'] ?? $userId) . '_papers.csv';
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

fputcsv($out, ['#','Title','Journal/Venue','Year','Citations','Quartile','SJR','Cites/Doc 2yr (≈JIF)','Journal h-index','Metrics Source','Scholar Link']);

foreach ($result['papers'] as $i => $p) {
    $m = $p['metrics'] ?? [];
    fputcsv($out, [
        $i + 1,
        $p['title']                     ?? '',
        $p['venue_clean'] ?? $p['venue'] ?? '',
        $p['year']                      ?? '',
        $p['cited']                     ?? '0',
        $m['quartile']                  ?? '',
        $m['sjr']                       ?? '',
        $m['cites_per_doc_2yr']         ?? '',
        $m['h_index']                   ?? '',
        $m['source']                    ?? '',
        $p['link']                      ?? '',
    ]);
}
fclose($out);