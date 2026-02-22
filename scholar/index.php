<?php
// ─────────────────────────────────────────────────────────────
//  ScholarLens — index.php  (Nature journal theme)
//  Journal metrics powered by Gemini API + Google Search
// ─────────────────────────────────────────────────────────────
$result     = null;
$error      = null;
$apiKeySet  = (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '') !== 'YOUR_GEMINI_API_KEY_HERE'
               && !empty(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');

// We need scholar.php before checking the key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
    require_once 'scholar.php';
    $apiKeySet = GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE' && !empty(GEMINI_API_KEY);
    $userId = trim($_POST['user_id']);
    if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) $userId = $m[1];
    $userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);
    if (empty($userId)) {
        $error = 'Invalid user ID. Please paste a full Google Scholar URL or just the user ID.';
    } else {
        try {
            $result = fetchScholarProfile($userId);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} else {
    // Load to check the key
    if (file_exists('scholar.php')) {
        require_once 'scholar.php';
        $apiKeySet = defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE' && !empty(GEMINI_API_KEY);
    }
}

function quartileColor(string $q): array {
    return match($q) {
        'Q1' => ['bg'=>'#e8f5e9','border'=>'#2e7d32','text'=>'#1b5e20'],
        'Q2' => ['bg'=>'#fff8e1','border'=>'#f9a825','text'=>'#e65100'],
        'Q3' => ['bg'=>'#fff3e0','border'=>'#ef6c00','text'=>'#bf360c'],
        'Q4' => ['bg'=>'#fce4ec','border'=>'#c62828','text'=>'#b71c1c'],
        default => ['bg'=>'#f5f5f5','border'=>'#bdbdbd','text'=>'#616161'],
    };
}

function safe(mixed $v, string $default = '—'): string {
    return !empty($v) ? htmlspecialchars((string)$v) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScholarLens — Research Profile Extractor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400&family=Source+Sans+3:wght@300;400;500;600;700&family=Source+Code+Pro:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset ────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --white:#ffffff;
  --off-white:#f8f8f8;
  --pale:#f3f3f3;
  --rule:#d8d8d8;
  --rule-lt:#ececec;
  --tx-primary:#222;
  --tx-body:#333;
  --tx-meta:#666;
  --tx-muted:#999;
  --red:#c8102e;
  --link:#006699;
  --link-h:#004e75;
  --gemini-purple:#7c4dff;
  --gemini-bg:#f3f0ff;
  --gemini-border:#d1c4e9;
}
html{font-size:16px;scroll-behavior:smooth}
body{background:var(--white);color:var(--tx-body);font-family:'Source Sans 3','Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;line-height:1.6}
a{color:var(--link);text-decoration:none}
a:hover{color:var(--link-h);text-decoration:underline}

/* ── Top stripe ───────────────────────────────────────────── */
.brand-stripe{height:6px;background:var(--red)}

/* ── Site header ──────────────────────────────────────────── */
.site-header{border-bottom:1px solid var(--rule);background:var(--white)}
.site-header-inner{max-width:1200px;margin:0 auto;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:64px}
.logo-wrap{display:flex;align-items:center;gap:.55rem;text-decoration:none}
.logo-mark{width:36px;height:36px;background:var(--red);border-radius:2px;display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Merriweather',serif;font-weight:900;font-size:1.1rem;flex-shrink:0}
.logo-name{font-family:'Merriweather',serif;font-weight:700;font-size:1.15rem;color:var(--tx-primary);letter-spacing:-.01em}
.logo-name span{color:var(--red)}
.powered-badge{display:flex;align-items:center;gap:.3rem;font-size:.7rem;color:var(--tx-muted);font-style:italic}
.gemini-dot{width:8px;height:8px;border-radius:50%;background:var(--gemini-purple);flex-shrink:0}

/* ── Breadcrumb ───────────────────────────────────────────── */
.breadcrumb{background:var(--off-white);border-bottom:1px solid var(--rule-lt);padding:.45rem 0;font-size:.75rem}
.breadcrumb .inner{max-width:1200px;margin:0 auto;padding:0 2rem;display:flex;align-items:center;gap:.4rem;color:var(--tx-muted)}
.breadcrumb a{color:var(--link);font-size:.75rem}

/* ── Page wrap ────────────────────────────────────────────── */
.page{max-width:1200px;margin:0 auto;padding:0 2rem 4rem}

/* ── Search section ───────────────────────────────────────── */
.search-section{padding:2.5rem 0 2rem;border-bottom:1px solid var(--rule);margin-bottom:2rem}
.eyebrow{font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--red);margin-bottom:.45rem}
.search-heading{font-family:'Merriweather',serif;font-size:1.5rem;font-weight:700;color:var(--tx-primary);margin-bottom:1.2rem;line-height:1.3}
.search-row{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.7rem}
.search-input{flex:1;min-width:320px;height:44px;border:1.5px solid var(--rule);border-radius:3px;padding:0 1rem;font-family:'Source Sans 3',sans-serif;font-size:.9rem;color:var(--tx-body);background:var(--white);outline:none;transition:border-color .15s,box-shadow .15s}
.search-input:focus{border-color:var(--link);box-shadow:0 0 0 3px rgba(0,102,153,.08)}
.search-input::placeholder{color:var(--tx-muted)}
.search-btn{height:44px;padding:0 1.6rem;background:var(--red);color:#fff;border:none;border-radius:3px;font-family:'Source Sans 3',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;letter-spacing:.02em;transition:background .15s;white-space:nowrap}
.search-btn:hover{background:#a00d24}
.search-hint{font-size:.78rem;color:var(--tx-muted);line-height:1.65}
.search-hint code{font-family:'Source Code Pro',monospace;font-size:.73rem;background:var(--pale);padding:.1rem .35rem;border-radius:2px;color:var(--link)}

/* ── API key banner ───────────────────────────────────────── */
.api-banner{padding:.85rem 1.1rem;border-radius:3px;font-size:.83rem;margin:1.2rem 0;border-left:4px solid;line-height:1.65}
.api-banner.warn{background:#fff8e1;border-color:#f9a825;color:#5d4037}
.api-banner.info{background:var(--gemini-bg);border-color:var(--gemini-purple);color:#37007a}
.api-banner strong{font-weight:700}
.api-banner code{font-family:'Source Code Pro',monospace;font-size:.75rem;background:rgba(0,0,0,.06);padding:.1rem .35rem;border-radius:2px}

/* ── Alert ────────────────────────────────────────────────── */
.alert{padding:.9rem 1.1rem;border-radius:3px;font-size:.85rem;margin:1.2rem 0;border-left:4px solid}
.alert.error{background:#fff5f5;border-color:var(--red);color:#7a0000}

/* ── Two-column layout ────────────────────────────────────── */
.layout{display:grid;grid-template-columns:1fr 280px;gap:3rem;align-items:start}

/* ── Article header ───────────────────────────────────────── */
.art-header{padding-bottom:1.5rem;border-bottom:1px solid var(--rule);margin-bottom:1.75rem}
.art-type{display:inline-block;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--red);border:1px solid var(--red);padding:.18rem .55rem;border-radius:2px;margin-bottom:.9rem}
.researcher-row{display:flex;align-items:flex-start;gap:1rem;margin-bottom:.9rem}
.r-avatar{width:68px;height:68px;border-radius:50%;object-fit:cover;border:2px solid var(--rule);flex-shrink:0}
.r-avatar-ph{width:68px;height:68px;border-radius:50%;background:var(--pale);border:2px solid var(--rule);display:flex;align-items:center;justify-content:center;font-family:'Merriweather',serif;font-size:1.5rem;font-weight:700;color:var(--tx-meta);flex-shrink:0}
.r-name{font-family:'Merriweather',serif;font-size:1.7rem;font-weight:700;color:var(--tx-primary);line-height:1.25;margin-bottom:.2rem;letter-spacing:-.01em}
.r-affil{font-size:.9rem;color:var(--tx-meta);font-style:italic}
.keywords{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.85rem}
.kw{font-size:.72rem;background:var(--pale);color:var(--tx-meta);padding:.2rem .6rem;border-radius:2px;border:1px solid var(--rule-lt);font-weight:500;cursor:default}
.kw:hover{background:#e8f4f8;color:var(--link);border-color:#b3d4e3}

/* ── Section heading ──────────────────────────────────────── */
.sec-h{font-family:'Merriweather',serif;font-size:1.05rem;font-weight:700;color:var(--tx-primary);margin-bottom:1rem;padding-bottom:.4rem;border-bottom:2px solid var(--tx-primary);letter-spacing:-.01em}

/* ── Year chart ───────────────────────────────────────────── */
.chart-wrap{margin-bottom:2rem}
.chart-area{display:flex;align-items:flex-end;gap:4px;height:110px;padding-bottom:24px;border-bottom:1px solid var(--rule);border-left:1px solid var(--rule);position:relative}
.bc{display:flex;flex-direction:column;align-items:center;flex:1;height:100%;justify-content:flex-end;gap:3px;position:relative}
.bar{width:100%;background:var(--link);border-radius:1px 1px 0 0;min-height:2px}
.bar:hover{opacity:.7}
.bar-yr{position:absolute;bottom:-20px;font-size:.49rem;color:var(--tx-muted);transform:rotate(-45deg);transform-origin:top left;white-space:nowrap}
.bar-n{font-size:.54rem;color:var(--tx-muted);position:absolute;bottom:102%;white-space:nowrap}

/* ── Papers list ──────────────────────────────────────────── */
.papers{margin-bottom:2rem}
.paper-item{padding:1.1rem 0;border-bottom:1px solid var(--rule-lt);display:grid;grid-template-columns:28px 1fr;gap:0 .75rem}
.paper-n{font-size:.72rem;color:var(--tx-muted);font-family:'Source Code Pro',monospace;padding-top:.15rem;text-align:right}
.paper-title{font-family:'Merriweather',serif;font-size:.9rem;font-weight:400;color:var(--tx-primary);line-height:1.45;margin-bottom:.3rem}
.paper-title a{color:var(--link)}
.paper-title a:hover{text-decoration:underline}
.paper-meta{font-size:.77rem;color:var(--tx-meta);margin-bottom:.5rem;display:flex;flex-wrap:wrap;align-items:center;gap:.45rem}
.meta-venue{font-style:italic}
.meta-chip{font-size:.68rem;background:var(--pale);color:var(--tx-meta);padding:.1rem .4rem;border-radius:2px;border:1px solid var(--rule-lt);font-style:normal}
.meta-cites{font-size:.7rem;color:var(--link);font-style:normal}

/* ── Metric chips ─────────────────────────────────────────── */
.chip-row{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;margin-bottom:.3rem}
.chip{display:inline-flex;align-items:center;gap:.2rem;padding:.18rem .5rem;border-radius:2px;font-size:.68rem;font-family:'Source Sans 3',sans-serif;font-weight:600;border:1px solid;white-space:nowrap;line-height:1.4}
.chip-if   {background:#e8f5e9;border-color:#a5d6a7;color:#1b5e20}
.chip-if.estimated{background:#f1f8e9;border-color:#c5e1a5;color:#33691e}
.chip-sjr  {background:#e8f4f8;border-color:#b3d4e3;color:#004e75}
.chip-cs   {background:#ede7f6;border-color:#ce93d8;color:#4a148c}
.chip-h    {background:#fff8e1;border-color:#ffe082;color:#5d4037}
.chip-src  {background:var(--pale);border-color:var(--rule);color:var(--tx-muted);font-weight:400;font-size:.62rem}
.chip-gemini{background:var(--gemini-bg);border-color:var(--gemini-border);color:var(--gemini-purple);font-weight:400;font-size:.62rem}

/* ── Grounding sources (tooltip-like collapsible) ─────────── */
.sources-toggle{font-size:.65rem;color:var(--link);cursor:pointer;border:none;background:none;padding:0;font-family:'Source Sans 3',sans-serif;text-decoration:underline dotted;margin-top:.25rem}
.sources-list{margin-top:.3rem;padding:.5rem .7rem;background:var(--gemini-bg);border:1px solid var(--gemini-border);border-radius:3px;display:none;font-size:.68rem;line-height:1.65}
.sources-list.open{display:block}
.sources-list a{color:var(--gemini-purple);font-size:.68rem}

/* ── Sidebar ──────────────────────────────────────────────── */
.sidebar{}
.sb-card{border:1px solid var(--rule);border-radius:3px;margin-bottom:1.25rem;overflow:hidden}
.sb-card-hd{background:var(--pale);padding:.65rem 1rem;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--tx-muted);border-bottom:1px solid var(--rule)}
.sb-card-bd{padding:.9rem 1rem;background:var(--white)}
.sb-row{display:flex;justify-content:space-between;align-items:baseline;padding:.35rem 0;border-bottom:1px solid var(--rule-lt);font-size:.82rem}
.sb-row:last-child{border-bottom:none}
.sb-label{color:var(--tx-meta)}
.sb-val{font-weight:700;color:var(--tx-primary);font-family:'Merriweather',serif}
.sb-action{display:block;width:100%;padding:.6rem 1rem;background:var(--link);color:#fff;text-align:center;font-size:.8rem;font-weight:600;border:none;border-radius:2px;cursor:pointer;text-decoration:none;transition:background .15s;margin-bottom:.5rem}
.sb-action:hover{background:var(--link-h);color:#fff;text-decoration:none}
.sb-note{font-size:.72rem;color:var(--tx-muted);line-height:1.5}

/* ── Gemini powered badge in sidebar ─────────────────────── */
.gemini-badge{display:flex;align-items:center;gap:.4rem;font-size:.72rem;color:var(--gemini-purple);font-weight:600;margin-bottom:.7rem;padding:.4rem .6rem;background:var(--gemini-bg);border:1px solid var(--gemini-border);border-radius:3px}
.gemini-icon{font-size:.9rem}

/* ── Legend ───────────────────────────────────────────────── */
.q-legend{display:flex;gap:.7rem;flex-wrap:wrap;margin-bottom:.75rem;font-size:.7rem;color:var(--tx-muted)}
.q-leg-item{display:flex;align-items:center;gap:.3rem}
.q-swatch{width:12px;height:12px;border-radius:2px;border:1px solid;flex-shrink:0}

/* ── Footer ───────────────────────────────────────────────── */
footer{margin-top:4rem;padding:1.5rem 0;border-top:1px solid var(--rule);font-size:.75rem;color:var(--tx-muted);text-align:center}
footer a{color:var(--link)}

/* ── Responsive ───────────────────────────────────────────── */
@media(max-width:820px){
  .layout{grid-template-columns:1fr}
  .sidebar{order:-1}
}
@media(max-width:560px){
  .search-input{min-width:100%}
  .r-name{font-size:1.4rem}
}
</style>
</head>
<body>

<div class="brand-stripe"></div>

<header class="site-header">
  <div class="site-header-inner">
    <a class="logo-wrap" href="/">
      <div class="logo-mark">SL</div>
      <div class="logo-name">Scholar<span>Lens</span></div>
    </a>
    <div class="powered-badge">
      <div class="gemini-dot"></div>
      Powered by Gemini + Google Search
    </div>
  </div>
</header>

<div class="breadcrumb">
  <div class="inner">
    <a href="/">Home</a>
    <span>›</span>
    <span>Researcher Profile</span>
    <?php if ($result): ?>
    <span>›</span>
    <span><?= htmlspecialchars($result['name']) ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="page">

  <!-- ── Search ─────────────────────────────────────────────── -->
  <section class="search-section">
    <div class="eyebrow">Profile Search</div>
    <div class="search-heading">Lookup a Google Scholar Researcher</div>

    <?php if (!$apiKeySet): ?>
    <div class="api-banner warn">
      <strong>⚠ Gemini API key not configured.</strong>
      Journal metrics (Impact Factor, SJR, Quartile) are powered by Gemini AI + Google Search.
      To enable them:<br>
      1. Get a <strong>free</strong> key at <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com/apikey</a><br>
      2. Open <code>scholar.php</code> and set <code>GEMINI_API_KEY</code> to your key, or set the environment variable <code>GEMINI_API_KEY</code>.<br>
      Scholar profile lookup still works without a key — journal metrics will be blank.
    </div>
    <?php else: ?>
    <div class="api-banner info">
      ✦ <strong>Gemini API active.</strong>
      Impact Factor and journal metrics are fetched in real-time using Gemini 2.0 Flash + Google Search grounding.
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="search-row">
        <input class="search-input" type="text" name="user_id"
          placeholder="Paste Google Scholar URL or user ID — e.g. JicYPdAAAAAJ"
          value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
        <button class="search-btn" type="submit">Search →</button>
      </div>
      <div class="search-hint">
        Scholar data fetched server-side (no CORS). &nbsp;·&nbsp;
        Impact Factor via <code>Gemini 2.0 Flash</code> + <code>Google Search</code>. &nbsp;·&nbsp;
        Gemini free tier: 1,500 requests/day.
      </div>
    </form>
  </section>

  <?php if ($error): ?>
  <div class="alert error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($result): ?>

  <div class="layout">

    <!-- ── Main ──────────────────────────────────────────────── -->
    <main>

      <!-- Profile header -->
      <header class="art-header">
        <div class="art-type">Researcher Profile</div>
        <div class="researcher-row">
          <?php if (!empty($result['avatar'])): ?>
            <img class="r-avatar" src="<?= htmlspecialchars($result['avatar']) ?>" alt="">
          <?php else: ?>
            <div class="r-avatar-ph"><?= mb_strtoupper(mb_substr($result['name'],0,1)) ?></div>
          <?php endif; ?>
          <div>
            <h1 class="r-name"><?= htmlspecialchars($result['name']) ?></h1>
            <?php if ($result['affiliation']): ?>
              <div class="r-affil"><?= htmlspecialchars($result['affiliation']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($result['interests']): ?>
        <div class="keywords">
          <?php foreach ($result['interests'] as $kw): ?>
            <span class="kw"><?= htmlspecialchars($kw) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </header>

      <!-- Publication timeline -->
      <?php
        $yearMap = [];
        foreach ($result['papers'] as $p) {
          if (!empty($p['year'])) $yearMap[$p['year']] = ($yearMap[$p['year']] ?? 0) + 1;
        }
        ksort($yearMap);
        $maxY = max(array_values($yearMap) ?: [1]);
      ?>
      <div class="chart-wrap">
        <h2 class="sec-h">Publication Timeline</h2>
        <div class="chart-area">
          <?php foreach ($yearMap as $yr => $cnt): ?>
            <?php $h = max(2, (int)round(($cnt/$maxY)*90)); ?>
            <div class="bc" title="<?= $cnt ?> papers in <?= $yr ?>">
              <span class="bar-n"><?= $cnt ?></span>
              <div class="bar" style="height:<?= $h ?>px"></div>
              <span class="bar-yr"><?= $yr ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Papers -->
      <div class="papers">
        <h2 class="sec-h">Publications (<?= count($result['papers']) ?>)</h2>

        <!-- Quartile legend -->
        <div class="q-legend">
          <?php foreach (['Q1'=>'Top 25%','Q2'=>'Top 50%','Q3'=>'Top 75%','Q4'=>'Bottom 25%'] as $q=>$lbl): ?>
            <?php $qc = quartileColor($q); ?>
            <div class="q-leg-item">
              <div class="q-swatch" style="background:<?= $qc['bg'] ?>;border-color:<?= $qc['border'] ?>"></div>
              <span><?= $q ?> — <?= $lbl ?></span>
            </div>
          <?php endforeach; ?>
          <div class="q-leg-item">
            <div class="q-swatch" style="background:#e8f5e9;border-color:#a5d6a7"></div>
            <span>IF — Impact Factor (Gemini/Google Search)</span>
          </div>
        </div>

        <?php foreach ($result['papers'] as $i => $p): ?>
        <?php $m = $p['metrics'] ?? null; ?>
        <div class="paper-item">
          <div class="paper-n"><?= $i+1 ?></div>
          <div>
            <div class="paper-title">
              <?php if (!empty($p['link'])): ?>
                <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars($p['title']) ?>
                </a>
              <?php else: ?>
                <?= htmlspecialchars($p['title']) ?>
              <?php endif; ?>
            </div>
            <div class="paper-meta">
              <?php $venue = $p['venue_clean'] ?? $p['venue'] ?? ''; ?>
              <?php if ($venue): ?>
                <span class="meta-venue"><?= htmlspecialchars($venue) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['year'])): ?>
                <span class="meta-chip"><?= htmlspecialchars($p['year']) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['cited']) && $p['cited'] !== '0'): ?>
                <span class="meta-cites"><?= htmlspecialchars($p['cited']) ?> citation<?= $p['cited']!=1?'s':'' ?></span>
              <?php endif; ?>
            </div>

            <?php if ($m): ?>
              <div class="chip-row">
                <?php if (!empty($m['quartile'])): ?>
                  <?php $qc = quartileColor($m['quartile']); ?>
                  <span class="chip chip-quartile"
                    style="background:<?= $qc['bg'] ?>;border-color:<?= $qc['border'] ?>;color:<?= $qc['text'] ?>">
                    <?= safe($m['quartile']) ?>
                  </span>
                <?php endif; ?>

                <?php if (!empty($m['impact_factor'])): ?>
                  <span class="chip chip-if"
                    title="Impact Factor <?= !empty($m['impact_factor_year']) ? '('.$m['impact_factor_year'].')' : '' ?>">
                    IF <?= safe($m['impact_factor']) ?>
                    <?php if (!empty($m['impact_factor_year'])): ?>
                      <span style="font-weight:400;opacity:.75">(<?= safe($m['impact_factor_year']) ?>)</span>
                    <?php endif; ?>
                  </span>
                <?php endif; ?>

                <?php if (!empty($m['sjr'])): ?>
                  <span class="chip chip-sjr" title="SCImago Journal Rank">SJR <?= safe($m['sjr']) ?></span>
                <?php endif; ?>

                <?php if (!empty($m['cite_score'])): ?>
                  <span class="chip chip-cs" title="Scopus CiteScore">CS <?= safe($m['cite_score']) ?></span>
                <?php endif; ?>

                <?php if (!empty($m['h_index'])): ?>
                  <span class="chip chip-h" title="Journal h-index">h-idx <?= safe($m['h_index']) ?></span>
                <?php endif; ?>

                <?php if (!empty($m['source'])): ?>
                  <span class="chip chip-src">via <?= safe($m['source']) ?></span>
                <?php endif; ?>

                <span class="chip chip-gemini">✦ Gemini</span>
              </div>

              <?php
                // Grounding sources
                $sources = $m['grounding_sources'] ?? [];
                if (!empty($sources)):
                  $uid = 'src-'.$i;
              ?>
              <button class="sources-toggle" onclick="toggleSrc('<?= $uid ?>')">
                View <?= count($sources) ?> search source<?= count($sources)>1?'s':'' ?> →
              </button>
              <div class="sources-list" id="<?= $uid ?>">
                <strong>Search grounding sources:</strong><br>
                <?php foreach ($sources as $src): ?>
                  <a href="<?= htmlspecialchars($src['uri']) ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($src['title']) ?>
                  </a><br>
                <?php endforeach; ?>
                <?php if (!empty($m['raw_queries'])): ?>
                  <br><em>Queries used: <?= htmlspecialchars(implode(', ', $m['raw_queries'])) ?></em>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php elseif ($apiKeySet): ?>
              <span style="font-size:.7rem;color:var(--tx-muted);font-style:italic">No metrics found for this venue.</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </main>

    <!-- ── Sidebar ─────────────────────────────────────────── -->
    <aside class="sidebar">

      <div class="sb-card">
        <div class="sb-card-hd">Citation Metrics</div>
        <div class="sb-card-bd">
          <div class="sb-row"><span class="sb-label">Total Citations</span><span class="sb-val"><?= safe($result['citations']) ?></span></div>
          <div class="sb-row"><span class="sb-label">h-index</span><span class="sb-val"><?= safe($result['h_index']) ?></span></div>
          <div class="sb-row"><span class="sb-label">i10-index</span><span class="sb-val"><?= safe($result['i10_index']) ?></span></div>
          <div class="sb-row"><span class="sb-label">Papers</span><span class="sb-val"><?= count($result['papers']) ?></span></div>
        </div>
      </div>

      <div class="sb-card">
        <div class="sb-card-hd">Download Data</div>
        <div class="sb-card-bd">
          <a class="sb-action" href="export.php?user_id=<?= urlencode($_POST['user_id']) ?>">↓ Download CSV</a>
          <div class="sb-note">Includes title, venue, year, citations, Quartile, SJR, Impact Factor, CiteScore per paper.</div>
        </div>
      </div>

      <div class="sb-card">
        <div class="sb-card-hd">About Journal Metrics</div>
        <div class="sb-card-bd">
          <div class="gemini-badge"><span class="gemini-icon">✦</span> Powered by Gemini 2.0 Flash + Google Search</div>
          <div class="sb-note" style="line-height:1.7">
            <p style="margin-bottom:.6rem"><strong style="color:var(--tx-body)">Impact Factor (IF)</strong><br>Clarivate JIF — fetched live from the web via Gemini + Google Search grounding.</p>
            <p style="margin-bottom:.6rem"><strong style="color:var(--tx-body)">SJR</strong><br>SCImago Journal Rank — prestige-weighted citation score.</p>
            <p style="margin-bottom:.6rem"><strong style="color:var(--tx-body)">CiteScore</strong><br>Scopus 4-year citation average.</p>
            <p style="margin-bottom:.6rem"><strong style="color:var(--tx-body)">Quartile</strong><br>Journal rank within its Scopus subject category.</p>
            <p><a href="https://aistudio.google.com/apikey" target="_blank">Get free Gemini API key →</a></p>
          </div>
        </div>
      </div>

    </aside>

  </div>

  <?php endif; ?>

</div><!-- /.page -->

<footer>
  ScholarLens &nbsp;·&nbsp; Scholar data via Google Scholar &nbsp;·&nbsp;
  Journal metrics via <strong>Gemini 2.0 Flash + Google Search</strong> &nbsp;·&nbsp;
  Not affiliated with Google, Clarivate, or Nature Publishing Group.
</footer>

<script>
function toggleSrc(id) {
  const el = document.getElementById(id);
  el.classList.toggle('open');
}
</script>

</body>
</html>