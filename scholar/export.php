<?php
// export.php — CSV export of scholar profile papers
require_once 'scholar.php';

$userId = trim($_GET['user_id'] ?? '');
if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) $userId = $m[1];
$userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);

if (empty($userId)) {
    http_response_code(400);
    die('Missing user_id');
}

try {
    $result = fetchScholarProfile($userId);
} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}

$filename = preg_replace('/[^A-Za-z0-9_]/', '_', $result['name'] ?? $userId) . '_papers.csv';

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8
fputs($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, ['#', 'Title', 'Venue', 'Year', 'Citations', 'CiteScore', 'Journal h-index', 'Scholar Link']);

foreach ($result['papers'] as $i => $p) {
    fputcsv($out, [
        $i + 1,
        $p['title']  ?? '',
        $p['venue']  ?? '',
        $p['year']   ?? '',
        $p['cited']  ?? '0',
        $p['metrics']['citescore']   ?? '',
        $p['metrics']['h_index']     ?? '',
        $p['link']   ?? '',
    ]);
}

fclose($out);