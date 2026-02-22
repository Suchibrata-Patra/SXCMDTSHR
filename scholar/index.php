<?php
// ─────────────────────────────────────────────────────────────
//  ScholarLens — index.php
// ─────────────────────────────────────────────────────────────
$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
    require_once 'scholar.php';
    $userId = trim($_POST['user_id']);
    if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) $userId = $m[1];
    $userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);

    if (empty($userId)) {
        $error = 'Invalid user ID. Paste the full Google Scholar URL or just the user ID.';
    } else {
        try {
            $result = fetchScholarProfile($userId);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Quartile colour mapping
function quartileColor(string $q): string {
    return match($q) {
        'Q1' => '#7fff6e',   // green
        'Q2' => '#ffd166',   // yellow
        'Q3' => '#ff9f43',   // orange
        'Q4' => '#ff6b6b',   // red
        default => '#6b6b80',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScholarLens — Research Profile Extractor</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=IBM+Plex+Mono:wght@300;400;500&family=IBM+Plex+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#09090f; --surface:#111118; --border:#1e1e2e;
  --accent:#7fff6e; --accent2:#00d4ff; --accent3:#ff6b6b;
  --text:#e8e8f0; --muted:#6b6b80; --card:#13131c;
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'IBM Plex Sans',sans-serif;min-height:100vh;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(127,255,110,.03)1px,transparent 1px),linear-gradient(90deg,rgba(127,255,110,.03)1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}
.wrap{max-width:1080px;margin:0 auto;padding:2rem;position:relative;z-index:1}

header{padding:3.5rem 0 2.5rem;border-bottom:1px solid var(--border);margin-bottom:2.5rem}
.logo{font-family:'Syne',sans-serif;font-weight:800;font-size:2.6rem;letter-spacing:-.02em;line-height:1;margin-bottom:.4rem}
.logo span{color:var(--accent)}
.tagline{font-family:'IBM Plex Mono',monospace;font-size:.7rem;color:var(--muted);letter-spacing:.14em;text-transform:uppercase}

.search-label{font-family:'IBM Plex Mono',monospace;font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);margin-bottom:.7rem;display:block}
.search-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:.6rem}
input[type=text]{flex:1;min-width:280px;background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:.85rem 1.1rem;color:var(--text);font-family:'IBM Plex Mono',monospace;font-size:.82rem;outline:none;transition:border-color .2s}
input[type=text]:focus{border-color:var(--accent)}
input::placeholder{color:var(--muted)}
.btn{background:var(--accent);color:#000;border:none;border-radius:4px;padding:.85rem 1.8rem;font-family:'Syne',sans-serif;font-weight:700;font-size:.85rem;letter-spacing:.05em;cursor:pointer;transition:opacity .2s,transform .1s;white-space:nowrap}
.btn:hover{opacity:.85}.btn:active{transform:scale(.98)}
.hint{font-size:.72rem;color:var(--muted);font-family:'IBM Plex Mono',monospace;line-height:1.7}
.hint code{color:var(--accent2)}

.alert{padding:.9rem 1.1rem;border-radius:4px;font-family:'IBM Plex Mono',monospace;font-size:.78rem;margin-bottom:2rem}
.alert-err{background:rgba(255,107,107,.08);border:1px solid rgba(255,107,107,.25);color:var(--accent3)}

/* Profile */
.profile-header{display:grid;grid-template-columns:auto 1fr;gap:1.8rem;align-items:start;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1.8rem;margin-bottom:1.5rem}
.avatar{width:85px;height:85px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);background:var(--border)}
.avatar-ph{width:85px;height:85px;border-radius:50%;background:var(--border);border:2px solid var(--accent);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:2rem;color:var(--accent)}
.pname{font-family:'Syne',sans-serif;font-weight:800;font-size:1.7rem;margin-bottom:.2rem}
.paffil{color:var(--muted);font-size:.83rem;margin-bottom:.8rem}
.tags{display:flex;flex-wrap:wrap;gap:.35rem}
.tag{background:rgba(127,255,110,.08);border:1px solid rgba(127,255,110,.2);color:var(--accent);padding:.18rem .55rem;border-radius:20px;font-size:.68rem;font-family:'IBM Plex Mono',monospace}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(148px,1fr));gap:1rem;margin-bottom:1.5rem}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1.4rem;text-align:center}
.stat-val{font-family:'Syne',sans-serif;font-weight:800;font-size:2rem;color:var(--accent);line-height:1;margin-bottom:.35rem}
.stat-lbl{font-family:'IBM Plex Mono',monospace;font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}

.sec-title{font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;letter-spacing:.08em;text-transform:uppercase;color:var(--accent2);margin-bottom:.9rem;padding-bottom:.45rem;border-bottom:1px solid var(--border)}

/* Chart */
.chart-wrap{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1.4rem;margin-bottom:1.5rem}
.bar-chart{display:flex;align-items:flex-end;gap:5px;height:130px;margin-bottom:.4rem}
.bar-col{display:flex;flex-direction:column;align-items:center;flex:1;height:100%;justify-content:flex-end;gap:4px}
.bar{width:100%;background:var(--accent);border-radius:3px 3px 0 0;min-height:3px;cursor:default}
.bar:hover{opacity:.65}
.bar-lbl{font-family:'IBM Plex Mono',monospace;font-size:.5rem;color:var(--muted);transform:rotate(-45deg);white-space:nowrap}
.bar-cnt{font-family:'IBM Plex Mono',monospace;font-size:.58rem;color:var(--accent)}

/* Table */
.tbl-wrap{overflow-x:auto;margin-bottom:1.5rem}
table{width:100%;border-collapse:collapse;font-size:.8rem}
th{font-family:'IBM Plex Mono',monospace;font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:.55rem .75rem;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:.75rem;border-bottom:1px solid rgba(30,30,46,.6);vertical-align:top}
tr:hover td{background:rgba(255,255,255,.015)}
.ptitle{font-weight:500;line-height:1.35;margin-bottom:.2rem}
.ptitle a{color:var(--text);text-decoration:none}
.ptitle a:hover{color:var(--accent)}
.pvenue{font-family:'IBM Plex Mono',monospace;font-size:.67rem;color:var(--muted);margin-top:.15rem}
.pyear{font-family:'IBM Plex Mono',monospace;font-size:.78rem;color:var(--accent2);white-space:nowrap}
.pcite{font-family:'IBM Plex Mono',monospace;font-size:.78rem;color:var(--accent)}

/* Metric badges */
.metric-row{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center}
.badge{display:inline-flex;align-items:center;gap:.25rem;padding:.15rem .45rem;border-radius:3px;font-family:'IBM Plex Mono',monospace;font-size:.66rem;white-space:nowrap;border:1px solid transparent}
.badge-sjr{background:rgba(0,212,255,.1);border-color:rgba(0,212,255,.25);color:var(--accent2)}
.badge-q{font-weight:700;padding:.15rem .5rem;border-radius:3px;font-family:'Syne',sans-serif;font-size:.72rem}
.badge-cites{background:rgba(127,255,110,.1);border-color:rgba(127,255,110,.25);color:var(--accent)}
.badge-h{background:rgba(255,209,102,.1);border-color:rgba(255,209,102,.25);color:#ffd166}
.badge-src{background:rgba(107,107,128,.1);border-color:rgba(107,107,128,.2);color:var(--muted);font-size:.58rem}
.badge-na{color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.7rem}

/* Legend */
.legend{display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;font-family:'IBM Plex Mono',monospace;font-size:.68rem;color:var(--muted)}
.legend-item{display:flex;align-items:center;gap:.35rem}
.legend-dot{width:10px;height:10px;border-radius:2px}

/* Export */
.export-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem}
.btn-ghost{background:transparent;border:1px solid var(--accent2);color:var(--accent2);border-radius:4px;padding:.55rem 1.1rem;font-family:'IBM Plex Mono',monospace;font-size:.72rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:rgba(0,212,255,.07)}

/* Notice */
.notice{background:rgba(0,212,255,.05);border:1px solid rgba(0,212,255,.15);border-radius:6px;padding:.9rem 1.1rem;margin-bottom:1.5rem;font-size:.76rem;color:var(--muted);line-height:1.65}
.notice strong{color:var(--accent2)}

@media(max-width:620px){
  .profile-header{grid-template-columns:1fr}
  .logo{font-size:2rem}
  th:nth-child(4),td:nth-child(4){display:none}
}
</style>
</head>
<body>
<div class="wrap">
<header>
  <div class="logo">Scholar<span>Lens</span></div>
  <div class="tagline">// Research Profile Extractor — PHP + SCImago + OpenAlex</div>
</header>

<form method="POST">
  <label class="search-label">// Google Scholar Profile URL or User ID</label>
  <div class="search-row">
    <input type="text" name="user_id"
      placeholder="https://scholar.google.com/citations?user=JicYPdAAAAAJ  or just  JicYPdAAAAAJ"
      value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
    <button class="btn" type="submit">Analyze →</button>
  </div>
  <div class="hint">
    Runs entirely server-side — no CORS issues, no browser blocks.<br>
    Journal metrics: <code>SCImago</code> (SJR + Quartile + Cites/Doc ≈ JIF) with <code>OpenAlex</code> as fallback.<br>
    <strong style="color:var(--accent)">No API keys required.</strong>
    True Clarivate JIF is proprietary — this uses the closest freely available equivalents.
  </div>
</form>

<?php if ($error): ?>
<br>
<div class="alert alert-err">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
<br>

<!-- Notice -->
<div class="notice">
  <strong>About Impact Factor:</strong>
  The Clarivate <em>Journal Impact Factor (JIF)</em> is proprietary (requires paid Web of Science access).
  This tool uses the closest free alternatives:<br>
  &nbsp;• <strong>Cites/Doc (2yr)</strong> from SCImago — computed with the same formula as JIF, but using Scopus citation data.<br>
  &nbsp;• <strong>SJR</strong> (SCImago Journal Rank) — prestige-weighted score (analogous to Google PageRank for journals).<br>
  &nbsp;• <strong>Quartile</strong> (Q1–Q4) — where the journal ranks in its Scopus subject category.<br>
  Both are published annually by <a href="https://www.scimagojr.com" target="_blank" style="color:var(--accent2)">SCImago</a>, freely and without login.
</div>

<!-- Profile card -->
<div class="profile-header">
  <?php if (!empty($result['avatar'])): ?>
    <img class="avatar" src="<?= htmlspecialchars($result['avatar']) ?>" alt="Avatar">
  <?php else: ?>
    <div class="avatar-ph"><?= mb_strtoupper(mb_substr($result['name'], 0, 1)) ?></div>
  <?php endif; ?>
  <div>
    <div class="pname"><?= htmlspecialchars($result['name']) ?></div>
    <div class="paffil"><?= htmlspecialchars($result['affiliation']) ?></div>
    <div class="tags">
      <?php foreach ($result['interests'] as $i): ?>
        <span class="tag"><?= htmlspecialchars($i) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-val"><?= htmlspecialchars($result['citations']) ?></div><div class="stat-lbl">Total Citations</div></div>
  <div class="stat-card"><div class="stat-val"><?= htmlspecialchars($result['h_index']) ?></div><div class="stat-lbl">h-index</div></div>
  <div class="stat-card"><div class="stat-val"><?= htmlspecialchars($result['i10_index']) ?></div><div class="stat-lbl">i10-index</div></div>
  <div class="stat-card"><div class="stat-val"><?= count($result['papers']) ?></div><div class="stat-lbl">Papers</div></div>
</div>

<!-- Year Chart -->
<?php
  $yearMap = [];
  foreach ($result['papers'] as $p) {
    if (!empty($p['year'])) $yearMap[$p['year']] = ($yearMap[$p['year']] ?? 0) + 1;
  }
  ksort($yearMap);
  $maxY = max(array_values($yearMap) ?: [1]);
?>
<div class="sec-title">Publications by Year</div>
<div class="chart-wrap">
  <div class="bar-chart">
    <?php foreach ($yearMap as $yr => $cnt): ?>
      <?php $h = max(3, (int)round(($cnt / $maxY) * 110)); ?>
      <div class="bar-col">
        <div class="bar-cnt"><?= $cnt ?></div>
        <div class="bar" style="height:<?= $h ?>px" title="<?= $cnt ?> papers in <?= $yr ?>"></div>
        <div class="bar-lbl"><?= $yr ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Legend -->
<div class="legend">
  <div class="legend-item"><div class="legend-dot" style="background:#7fff6e"></div>Q1 — top 25%</div>
  <div class="legend-item"><div class="legend-dot" style="background:#ffd166"></div>Q2 — top 50%</div>
  <div class="legend-item"><div class="legend-dot" style="background:#ff9f43"></div>Q3 — top 75%</div>
  <div class="legend-item"><div class="legend-dot" style="background:#ff6b6b"></div>Q4 — bottom 25%</div>
  <div class="legend-item"><div class="legend-dot" style="background:var(--accent2)"></div>SJR — prestige score</div>
  <div class="legend-item"><div class="legend-dot" style="background:var(--accent)"></div>Cites/Doc 2yr ≈ JIF</div>
</div>

<!-- Papers Table -->
<div class="sec-title">Publications</div>
<div class="tbl-wrap">
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Title / Journal</th>
      <th>Year</th>
      <th>Cited by</th>
      <th>Journal Metrics</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($result['papers'] as $i => $p): ?>
  <tr>
    <td style="color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.72rem"><?= $i + 1 ?></td>
    <td>
      <div class="ptitle">
        <?php if (!empty($p['link'])): ?>
          <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars($p['title']) ?>
          </a>
        <?php else: ?>
          <?= htmlspecialchars($p['title']) ?>
        <?php endif; ?>
      </div>
      <?php $venue = $p['venue_clean'] ?? $p['venue'] ?? ''; ?>
      <?php if ($venue): ?>
        <div class="pvenue"><?= htmlspecialchars($venue) ?></div>
      <?php endif; ?>
    </td>
    <td class="pyear"><?= htmlspecialchars($p['year'] ?? '—') ?></td>
    <td class="pcite"><?= htmlspecialchars($p['cited'] ?? '0') ?></td>
    <td>
      <?php $m = $p['metrics'] ?? null; ?>
      <?php if ($m): ?>
        <div class="metric-row">
          <?php if (!empty($m['quartile'])): ?>
            <?php $qc = quartileColor($m['quartile']); ?>
            <span class="badge badge-q" style="background:<?= $qc ?>22;border-color:<?= $qc ?>55;color:<?= $qc ?>">
              <?= htmlspecialchars($m['quartile']) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($m['sjr'])): ?>
            <span class="badge badge-sjr" title="SCImago Journal Rank">SJR <?= htmlspecialchars($m['sjr']) ?></span>
          <?php endif; ?>
          <?php if (!empty($m['cites_per_doc_2yr'])): ?>
            <span class="badge badge-cites" title="Cites per Document (2-year) ≈ Impact Factor formula">
              IF≈ <?= htmlspecialchars($m['cites_per_doc_2yr']) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($m['h_index'])): ?>
            <span class="badge badge-h" title="Journal h-index">h <?= htmlspecialchars($m['h_index']) ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($m['source'])): ?>
          <div style="margin-top:.3rem">
            <span class="badge badge-src"><?= htmlspecialchars($m['source']) ?></span>
            <?php if (!empty($m['journal_name']) && $m['journal_name'] !== ($venue ?? '')): ?>
              <span class="badge badge-src" style="color:var(--muted);font-style:italic">
                matched: <?= htmlspecialchars(mb_substr($m['journal_name'], 0, 40)) ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (empty($m['sjr']) && empty($m['h_index']) && empty($m['quartile']) && empty($m['cites_per_doc_2yr'])): ?>
          <span class="badge-na">N/A</span>
        <?php endif; ?>
      <?php else: ?>
        <span class="badge-na">—</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="export-row">
  <a class="btn-ghost" href="export.php?user_id=<?= urlencode($_POST['user_id']) ?>">⬇ Export CSV</a>
</div>

<?php endif; ?>
</div>
</body>
</html>