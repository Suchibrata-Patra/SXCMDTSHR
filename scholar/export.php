<?php
require_once 'scholar.php';
$userId = trim($_GET['user_id'] ?? '');
if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) $userId = $m[1];
$userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);
if (empty($userId)) { http_response_code(400); die('Missing user_id'); }
try { $r = fetchScholarProfile($userId); }
catch (Exception $e) { http_response_code(500); die($e->getMessage()); }
$fn = preg_replace('/[^A-Za-z0-9_]/', '_', $r['name'] ?? $userId).'_papers.csv';
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$fn\"");
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['#','Title','Journal','Year','Citations','IF','IF Year','SJR','Quartile','CiteScore','h-index','Publisher','ISSN','Source','URL']);
// Note: export fetches Gemini metrics synchronously
foreach ($r['papers'] as $i => $p) {
    $m = !empty($p['venue_clean']) ? (fetchMetricsViaGemini($p['venue_clean']) ?? []) : [];
    fputcsv($out, [$i+1,$p['title']??'',$p['venue_clean']??$p['venue']??'',$p['year']??'',$p['cited']??'0',
        $m['impact_factor']??'',$m['impact_factor_year']??'',$m['sjr']??'',$m['quartile']??'',
        $m['cite_score']??'',$m['h_index']??'',$m['publisher']??'',$m['issn']??'',
        $m['source']??'Gemini+GoogleSearch',$p['link']??'']);
    usleep(300000);
}
fclose($out);