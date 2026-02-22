<?php
// ─────────────────────────────────────────────────────────────
//  ScholarLens — index.php
//  Fetches Google Scholar profile server-side (no CORS issues)
// ─────────────────────────────────────────────────────────────

$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
    require_once 'scholar.php';
    $userId = trim($_POST['user_id']);
    // Extract user ID from URL if pasted
    if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) {
        $userId = $m[1];
    }
    $userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);

    if (empty($userId)) {
        $error = 'Invalid user ID.';
    } else {
        try {
            $result = fetchScholarProfile($userId);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
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
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(127,255,110,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(127,255,110,.03) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;z-index:0}
.wrap{max-width:1060px;margin:0 auto;padding:2rem;position:relative;z-index:1}

/* ── Header ── */
header{padding:3.5rem 0 2.5rem;border-bottom:1px solid var(--border);margin-bottom:2.5rem}
.logo{font-family:'Syne',sans-serif;font-weight:800;font-size:2.6rem;letter-spacing:-.02em;line-height:1;margin-bottom:.4rem}
.logo span{color:var(--accent)}
.tagline{font-family:'IBM Plex Mono',monospace;font-size:.7rem;color:var(--muted);letter-spacing:.14em;text-transform:uppercase}

/* ── Search ── */
.search-label{font-family:'IBM Plex Mono',monospace;font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);margin-bottom:.7rem;display:block}
.search-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:.6rem}
input[type=text]{flex:1;min-width:280px;background:var(--surface);border:1px solid var(--border);border-radius:4px;padding:.85rem 1.1rem;color:var(--text);font-family:'IBM Plex Mono',monospace;font-size:.82rem;outline:none;transition:border-color .2s}
input[type=text]:focus{border-color:var(--accent)}
input::placeholder{color:var(--muted)}
.btn{background:var(--accent);color:#000;border:none;border-radius:4px;padding:.85rem 1.8rem;font-family:'Syne',sans-serif;font-weight:700;font-size:.85rem;letter-spacing:.05em;cursor:pointer;transition:opacity .2s,transform .1s;white-space:nowrap}
.btn:hover{opacity:.85}
.btn:active{transform:scale(.98)}
.hint{font-size:.72rem;color:var(--muted);font-family:'IBM Plex Mono',monospace;line-height:1.6}
.hint code{color:var(--accent2)}

/* ── Alert ── */
.alert{padding:.9rem 1.1rem;border-radius:4px;font-family:'IBM Plex Mono',monospace;font-size:.78rem;margin-bottom:2rem}
.alert-err{background:rgba(255,107,107,.08);border:1px solid rgba(255,107,107,.25);color:var(--accent3)}
.alert-info{background:rgba(0,212,255,.07);border:1px solid rgba(0,212,255,.2);color:var(--accent2)}

/* ── Profile card ── */
.profile-header{display:grid;grid-template-columns:auto 1fr;gap:1.8rem;align-items:start;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1.8rem;margin-bottom:1.5rem}
.avatar{width:85px;height:85px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);background:var(--border)}
.avatar-ph{width:85px;height:85px;border-radius:50%;background:var(--border);border:2px solid var(--accent);display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:2rem;color:var(--accent)}
.pname{font-family:'Syne',sans-serif;font-weight:800;font-size:1.7rem;margin-bottom:.2rem}
.paffil{color:var(--muted);font-size:.83rem;margin-bottom:.8rem}
.tags{display:flex;flex-wrap:wrap;gap:.35rem}
.tag{background:rgba(127,255,110,.08);border:1px solid rgba(127,255,110,.2);color:var(--accent);padding:.18rem .55rem;border-radius:20px;font-size:.68rem;font-family:'IBM Plex Mono',monospace}

/* ── Stats ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1.4rem;text-align:center}
.stat-val{font-family:'Syne',sans-serif;font-weight:800;font-size:2rem;color:var(--accent);line-height:1;margin-bottom:.35rem}
.stat-lbl{font-family:'IBM Plex Mono',monospace;font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}

/* ── Section title ── */
.sec-title{font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;letter-spacing:.08em;text-transform:uppercase;color:var(--accent2);margin-bottom:.9rem;padding-bottom:.45rem;border-bottom:1px solid var(--border)}

/* ── Chart ── */
.chart-wrap{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:1.4rem;margin-bottom:1.5rem}
.bar-chart{display:flex;align-items:flex-end;gap:5px;height:130px;margin-bottom:.4rem}
.bar-col{display:flex;flex-direction:column;align-items:center;flex:1;height:100%;justify-content:flex-end;gap:4px}
.bar{width:100%;background:var(--accent);border-radius:3px 3px 0 0;min-height:3px;cursor:default;transition:opacity .2s}
.bar:hover{opacity:.65}
.bar-lbl{font-family:'IBM Plex Mono',monospace;font-size:.5rem;color:var(--muted);transform:rotate(-45deg);white-space:nowrap}
.bar-cnt{font-family:'IBM Plex Mono',monospace;font-size:.58rem;color:var(--accent)}

/* ── Papers table ── */
.tbl-wrap{overflow-x:auto;margin-bottom:1.5rem}
table{width:100%;border-collapse:collapse;font-size:.8rem}
th{font-family:'IBM Plex Mono',monospace;font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);padding:.55rem .75rem;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:.75rem;border-bottom:1px solid rgba(30,30,46,.6);vertical-align:top}
tr:hover td{background:rgba(255,255,255,.015)}
.ptitle{font-weight:500;line-height:1.35;margin-bottom:.2rem}
.ptitle a{color:var(--text);text-decoration:none}
.ptitle a:hover{color:var(--accent)}
.pvenue{font-family:'IBM Plex Mono',monospace;font-size:.67rem;color:var(--muted)}
.pyear{font-family:'IBM Plex Mono',monospace;font-size:.78rem;color:var(--accent2);white-space:nowrap}
.pcite{font-family:'IBM Plex Mono',monospace;font-size:.78rem;color:var(--accent)}
.badge{display:inline-block;background:rgba(127,255,110,.1);border:1px solid rgba(127,255,110,.25);color:var(--accent);padding:.12rem .42rem;border-radius:3px;font-family:'IBM Plex Mono',monospace;font-size:.66rem;white-space:nowrap;margin:.1rem .1rem 0 0}
.badge-na{color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.7rem}

/* ── Notice ── */
.notice{background:rgba(255,107,107,.05);border:1px solid rgba(255,107,107,.18);border-radius:6px;padding:.9rem 1.1rem;margin-bottom:1.5rem;font-size:.76rem;color:var(--muted);line-height:1.6}
.notice strong{color:var(--accent3)}

/* ── Export ── */
.export-row{display:flex;gap:.75rem;flex-wrap:wrap}
.btn-ghost{background:transparent;border:1px solid var(--accent2);color:var(--accent2);border-radius:4px;padding:.55rem 1.1rem;font-family:'IBM Plex Mono',monospace;font-size:.72rem;cursor:pointer;transition:background .2s;text-decoration:none;display:inline-block}
.btn-ghost:hover{background:rgba(0,212,255,.07)}

@media(max-width:600px){
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
    <div class="tagline">// Research Profile Extractor — PHP Edition</div>
  </header>

  <!-- Search form -->
  <form method="POST">
    <label class="search-label">// Google Scholar Profile URL or User ID</label>
    <div class="search-row">
      <input type="text" name="user_id"
        placeholder="https://scholar.google.com/citations?user=JicYPdAAAAAJ"
        value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
      <button class="btn" type="submit">Analyze →</button>
    </div>
    <div class="hint">
      Paste the full URL or just the user ID (e.g. <code>JicYPdAAAAAJ</code>). &nbsp;|&nbsp;
      Journal metrics via <code>OpenAlex</code> (free, open). &nbsp;|&nbsp;
      True Clarivate IF requires institutional access.
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
    <strong>Impact Factor note:</strong> Clarivate JIF is proprietary. This tool shows
    <strong style="color:var(--accent)">CiteScore</strong> (2-year mean citedness) and
    <strong style="color:var(--accent)">h-index</strong> from OpenAlex — widely accepted
    open alternatives. Values shown per journal/venue where available.
  </div>

  <!-- Profile -->
  <div class="profile-header">
    <?php if (!empty($result['avatar'])): ?>
      <img class="avatar" src="<?= htmlspecialchars($result['avatar']) ?>" alt="avatar">
    <?php else: ?>
      <div class="avatar-ph"><?= mb_strtoupper(mb_substr($result['name'], 0, 1)) ?></div>
    <?php endif; ?>
    <div>
      <div class="pname"><?= htmlspecialchars($result['name']) ?></div>
      <div class="paffil"><?= htmlspecialchars($result['affiliation']) ?></div>
      <div class="tags">
        <?php foreach ($result['interests'] as $int): ?>
          <span class="tag"><?= htmlspecialchars($int) ?></span>
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
  <div class="sec-title">Publications by Year</div>
  <?php
    $yearMap = [];
    foreach ($result['papers'] as $p) {
      if (!empty($p['year'])) $yearMap[$p['year']] = ($yearMap[$p['year']] ?? 0) + 1;
    }
    ksort($yearMap);
    $maxY = max(array_values($yearMap) ?: [1]);
  ?>
  <div class="chart-wrap">
    <div class="bar-chart">
      <?php foreach ($yearMap as $yr => $cnt): ?>
        <?php $h = max(3, round(($cnt / $maxY) * 110)); ?>
        <div class="bar-col">
          <div class="bar-cnt"><?= $cnt ?></div>
          <div class="bar" style="height:<?= $h ?>px" title="<?= $cnt ?> papers in <?= $yr ?>"></div>
          <div class="bar-lbl"><?= $yr ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Papers Table -->
  <div class="sec-title">Publications</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Title / Venue</th>
          <th>Year</th>
          <th>Cited by</th>
          <th>Journal Metrics (CiteScore / h-idx)</th>
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
            <?php if (!empty($p['venue'])): ?>
              <div class="pvenue"><?= htmlspecialchars($p['venue']) ?></div>
            <?php endif; ?>
          </td>
          <td class="pyear"><?= htmlspecialchars($p['year'] ?? '—') ?></td>
          <td class="pcite"><?= htmlspecialchars($p['cited'] ?? '0') ?></td>
          <td>
            <?php if (!empty($p['metrics'])): ?>
              <?php if (!empty($p['metrics']['citescore'])): ?>
                <span class="badge">CS <?= htmlspecialchars($p['metrics']['citescore']) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['metrics']['h_index'])): ?>
                <span class="badge">h-idx <?= htmlspecialchars($p['metrics']['h_index']) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['metrics']['source_name'])): ?>
                <div style="font-size:.62rem;color:var(--muted);margin-top:.25rem;font-family:'IBM Plex Mono',monospace">
                  via <?= htmlspecialchars($p['metrics']['source_name']) ?>
                </div>
              <?php endif; ?>
              <?php if (empty($p['metrics']['citescore']) && empty($p['metrics']['h_index'])): ?>
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

  <!-- Export -->
  <div class="export-row">
    <a class="btn-ghost" href="export.php?user_id=<?= urlencode($_POST['user_id']) ?>">⬇ Export CSV</a>
  </div>

  <?php endif; ?>

</div>
</body>
</html>