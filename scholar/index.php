<?php
// ─────────────────────────────────────────────────────────────
//  ScholarLens — index.php  (Nature journal aesthetic)
// ─────────────────────────────────────────────────────────────
$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
    require_once 'scholar.php';
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
}

function quartileColor(string $q): array {
    return match($q) {
        'Q1' => ['bg' => '#e8f5e9', 'border' => '#2e7d32', 'text' => '#1b5e20'],
        'Q2' => ['bg' => '#fff8e1', 'border' => '#f9a825', 'text' => '#e65100'],
        'Q3' => ['bg' => '#fff3e0', 'border' => '#ef6c00', 'text' => '#bf360c'],
        'Q4' => ['bg' => '#fce4ec', 'border' => '#c62828', 'text' => '#b71c1c'],
        default => ['bg' => '#f5f5f5', 'border' => '#bdbdbd', 'text' => '#616161'],
    };
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
/* ── Reset & Base ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --white:       #ffffff;
  --off-white:   #f8f8f8;
  --pale-grey:   #f3f3f3;
  --rule:        #d8d8d8;
  --rule-light:  #ececec;
  --text-primary:#222222;
  --text-body:   #333333;
  --text-meta:   #666666;
  --text-muted:  #999999;
  --nature-red:  #c8102e;      /* Nature's signature red */
  --link:        #006699;      /* Nature link blue */
  --link-hover:  #004e75;
  --q1:          #1b5e20;
  --accent-pale: #e8f4f8;
  --sidebar-w:   280px;
  --content-max: 780px;
}

html { font-size: 16px; scroll-behavior: smooth; }

body {
  background: var(--white);
  color: var(--text-body);
  font-family: 'Source Sans 3', 'Helvetica Neue', Arial, sans-serif;
  font-weight: 400;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}

a { color: var(--link); text-decoration: none; }
a:hover { color: var(--link-hover); text-decoration: underline; }

/* ── Top brand bar ────────────────────────────────────────── */
.brand-bar {
  background: var(--nature-red);
  padding: 0;
  height: 6px;
}

/* ── Site header ──────────────────────────────────────────── */
.site-header {
  border-bottom: 1px solid var(--rule);
  background: var(--white);
}
.site-header-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
}
.site-logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}
.logo-mark {
  width: 36px;
  height: 36px;
  background: var(--nature-red);
  border-radius: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-family: 'Merriweather', serif;
  font-weight: 900;
  font-size: 1.1rem;
  letter-spacing: -0.02em;
  flex-shrink: 0;
}
.logo-text {
  font-family: 'Merriweather', serif;
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--text-primary);
  letter-spacing: -0.01em;
}
.logo-text span {
  color: var(--nature-red);
}
.site-tagline {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-style: italic;
  font-family: 'Merriweather', serif;
}

/* ── Breadcrumb nav ───────────────────────────────────────── */
.breadcrumb-bar {
  background: var(--off-white);
  border-bottom: 1px solid var(--rule-light);
  padding: 0.45rem 0;
}
.breadcrumb-bar .inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 2rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.75rem;
  color: var(--text-muted);
}
.breadcrumb-bar a {
  color: var(--link);
  font-size: 0.75rem;
}
.breadcrumb-bar .sep { color: var(--rule); }

/* ── Main layout ──────────────────────────────────────────── */
.page-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 2rem 4rem;
}

/* ── Search section ───────────────────────────────────────── */
.search-section {
  padding: 2.5rem 0 2rem;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 2rem;
}
.search-eyebrow {
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--nature-red);
  margin-bottom: 0.5rem;
}
.search-heading {
  font-family: 'Merriweather', serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 1.25rem;
  line-height: 1.3;
}
.search-form-row {
  display: flex;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.search-input {
  flex: 1;
  min-width: 320px;
  height: 44px;
  border: 1.5px solid var(--rule);
  border-radius: 3px;
  padding: 0 1rem;
  font-family: 'Source Sans 3', sans-serif;
  font-size: 0.9rem;
  color: var(--text-body);
  background: var(--white);
  outline: none;
  transition: border-color 0.15s;
}
.search-input:focus {
  border-color: var(--link);
  box-shadow: 0 0 0 3px rgba(0,102,153,0.08);
}
.search-input::placeholder { color: var(--text-muted); }
.search-btn {
  height: 44px;
  padding: 0 1.6rem;
  background: var(--nature-red);
  color: white;
  border: none;
  border-radius: 3px;
  font-family: 'Source Sans 3', sans-serif;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  letter-spacing: 0.02em;
  transition: background 0.15s;
  white-space: nowrap;
}
.search-btn:hover { background: #a00d24; }
.search-hint {
  font-size: 0.78rem;
  color: var(--text-muted);
  line-height: 1.6;
}
.search-hint code {
  font-family: 'Source Code Pro', monospace;
  font-size: 0.75rem;
  background: var(--pale-grey);
  padding: 0.1rem 0.35rem;
  border-radius: 2px;
  color: var(--link);
}

/* ── Alert ────────────────────────────────────────────────── */
.alert {
  padding: 0.9rem 1.1rem;
  border-radius: 3px;
  font-size: 0.85rem;
  margin: 1.5rem 0;
  border-left: 4px solid;
}
.alert-error {
  background: #fff5f5;
  border-color: var(--nature-red);
  color: #7a0000;
}
.alert-info {
  background: var(--accent-pale);
  border-color: var(--link);
  color: #003d5c;
  font-size: 0.82rem;
  line-height: 1.65;
}
.alert-info strong { color: var(--link); }

/* ── Article layout (two-col) ─────────────────────────────── */
.article-layout {
  display: grid;
  grid-template-columns: 1fr var(--sidebar-w);
  gap: 3rem;
  align-items: start;
}

/* ── Profile / article header ─────────────────────────────── */
.article-header {
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 1.75rem;
}
.article-type-tag {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--nature-red);
  border: 1px solid var(--nature-red);
  padding: 0.18rem 0.55rem;
  border-radius: 2px;
  margin-bottom: 0.9rem;
}
.researcher-name {
  font-family: 'Merriweather', serif;
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.25;
  margin-bottom: 0.5rem;
  letter-spacing: -0.01em;
}
.researcher-affil {
  font-size: 0.9rem;
  color: var(--text-meta);
  margin-bottom: 1rem;
  font-style: italic;
}
.researcher-meta-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.researcher-avatar {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--rule);
  flex-shrink: 0;
}
.researcher-avatar-ph {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  background: var(--pale-grey);
  border: 2px solid var(--rule);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Merriweather', serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-meta);
  flex-shrink: 0;
}
.researcher-info { flex: 1; }

/* ── Interest keywords ────────────────────────────────────── */
.keyword-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.9rem;
}
.keyword {
  font-size: 0.72rem;
  background: var(--pale-grey);
  color: var(--text-meta);
  padding: 0.2rem 0.6rem;
  border-radius: 2px;
  border: 1px solid var(--rule-light);
  font-weight: 500;
}
.keyword:hover {
  background: var(--accent-pale);
  color: var(--link);
  border-color: #b3d4e3;
  cursor: default;
}

/* ── Abstract/stats box (like Nature's article metrics box) ── */
.metrics-panel {
  background: var(--pale-grey);
  border: 1px solid var(--rule);
  border-radius: 3px;
  padding: 1.25rem;
  margin-bottom: 1.75rem;
}
.metrics-panel-title {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--rule);
}
.metrics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem 1rem;
}
.metric-item {}
.metric-value {
  font-family: 'Merriweather', serif;
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1;
  margin-bottom: 0.2rem;
}
.metric-label {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* ── Section heading (like Nature's section titles) ───────── */
.section-heading {
  font-family: 'Merriweather', serif;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 1rem;
  padding-bottom: 0.4rem;
  border-bottom: 2px solid var(--text-primary);
  letter-spacing: -0.01em;
}

/* ── Year chart ───────────────────────────────────────────── */
.chart-container {
  margin-bottom: 2rem;
}
.chart-area {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  height: 100px;
  padding-bottom: 28px;
  position: relative;
  border-bottom: 1px solid var(--rule);
  border-left: 1px solid var(--rule);
}
.bar-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  height: 100%;
  justify-content: flex-end;
  gap: 3px;
  position: relative;
}
.bar {
  width: 100%;
  background: var(--link);
  border-radius: 1px 1px 0 0;
  min-height: 2px;
  transition: opacity 0.15s;
}
.bar:hover { opacity: 0.7; }
.bar-year-label {
  position: absolute;
  bottom: -22px;
  font-size: 0.5rem;
  color: var(--text-muted);
  transform: rotate(-45deg);
  transform-origin: top left;
  white-space: nowrap;
}
.bar-count {
  font-size: 0.55rem;
  color: var(--text-muted);
  position: absolute;
  bottom: 102%;
  white-space: nowrap;
}

/* ── Papers list (Nature article-listing style) ───────────── */
.papers-list {
  margin-bottom: 2rem;
}
.paper-item {
  padding: 1.1rem 0;
  border-bottom: 1px solid var(--rule-light);
  display: grid;
  grid-template-columns: 28px 1fr;
  gap: 0 0.75rem;
}
.paper-number {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-family: 'Source Code Pro', monospace;
  padding-top: 0.15rem;
  text-align: right;
}
.paper-body {}
.paper-title {
  font-family: 'Merriweather', serif;
  font-size: 0.9rem;
  font-weight: 400;
  color: var(--text-primary);
  line-height: 1.45;
  margin-bottom: 0.3rem;
}
.paper-title a {
  color: var(--link);
}
.paper-title a:hover { text-decoration: underline; }
.paper-venue-row {
  font-size: 0.77rem;
  color: var(--text-meta);
  margin-bottom: 0.5rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.paper-venue-name {
  font-style: italic;
}
.paper-year-chip {
  font-size: 0.7rem;
  background: var(--pale-grey);
  color: var(--text-meta);
  padding: 0.1rem 0.4rem;
  border-radius: 2px;
  border: 1px solid var(--rule-light);
  font-style: normal;
}
.paper-cite-chip {
  font-size: 0.7rem;
  color: var(--link);
  font-style: normal;
}
.paper-metrics-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}

/* metric chips */
.chip {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  padding: 0.18rem 0.5rem;
  border-radius: 2px;
  font-size: 0.68rem;
  font-family: 'Source Sans 3', sans-serif;
  font-weight: 600;
  border: 1px solid;
  white-space: nowrap;
}
.chip-quartile {
  /* dynamically styled via inline */
}
.chip-sjr {
  background: #e8f4f8;
  border-color: #b3d4e3;
  color: #004e75;
}
.chip-if {
  background: #e8f5e9;
  border-color: #a5d6a7;
  color: #1b5e20;
}
.chip-h {
  background: #fff8e1;
  border-color: #ffe082;
  color: #5d4037;
}
.chip-src {
  background: var(--pale-grey);
  border-color: var(--rule);
  color: var(--text-muted);
  font-weight: 400;
  font-size: 0.62rem;
}

/* ── Sidebar (Nature-style: access, metrics, download) ────── */
.sidebar {}
.sidebar-card {
  border: 1px solid var(--rule);
  border-radius: 3px;
  margin-bottom: 1.25rem;
  overflow: hidden;
}
.sidebar-card-header {
  background: var(--pale-grey);
  padding: 0.65rem 1rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-muted);
  border-bottom: 1px solid var(--rule);
}
.sidebar-card-body {
  padding: 0.9rem 1rem;
  background: var(--white);
}
.sidebar-stat-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 0.35rem 0;
  border-bottom: 1px solid var(--rule-light);
  font-size: 0.82rem;
}
.sidebar-stat-row:last-child { border-bottom: none; }
.sidebar-stat-label { color: var(--text-meta); }
.sidebar-stat-value {
  font-weight: 700;
  color: var(--text-primary);
  font-family: 'Merriweather', serif;
}
.sidebar-action {
  display: block;
  width: 100%;
  padding: 0.6rem 1rem;
  background: var(--link);
  color: white;
  text-align: center;
  font-size: 0.8rem;
  font-weight: 600;
  border: none;
  border-radius: 2px;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s;
  margin-bottom: 0.5rem;
}
.sidebar-action:hover {
  background: var(--link-hover);
  color: white;
  text-decoration: none;
}

/* ── Legend ───────────────────────────────────────────────── */
.quartile-legend {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
  font-size: 0.72rem;
  color: var(--text-muted);
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.legend-swatch {
  width: 12px;
  height: 12px;
  border-radius: 2px;
  border: 1px solid;
  flex-shrink: 0;
}

/* ── Footer ───────────────────────────────────────────────── */
.site-footer {
  margin-top: 4rem;
  padding: 1.5rem 0;
  border-top: 1px solid var(--rule);
  font-size: 0.75rem;
  color: var(--text-muted);
  text-align: center;
}
.site-footer a { color: var(--link); }

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 820px) {
  .article-layout {
    grid-template-columns: 1fr;
  }
  .sidebar { order: -1; }
  .metrics-grid { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 560px) {
  .search-input { min-width: 100%; }
  .metrics-grid { grid-template-columns: 1fr 1fr; }
  .researcher-name { font-size: 1.4rem; }
}
</style>
</head>
<body>

<!-- Red brand stripe -->
<div class="brand-bar"></div>

<!-- Site header -->
<header class="site-header">
  <div class="site-header-inner">
    <a class="site-logo" href="/">
      <div class="logo-mark">SL</div>
      <div>
        <div class="logo-text">Scholar<span>Lens</span></div>
      </div>
    </a>
    <div class="site-tagline">Research Profile Extractor</div>
  </div>
</header>

<!-- Breadcrumb -->
<div class="breadcrumb-bar">
  <div class="inner">
    <a href="/">Home</a>
    <span class="sep">›</span>
    <span>Researcher Profile</span>
    <?php if ($result): ?>
    <span class="sep">›</span>
    <span><?= htmlspecialchars($result['name']) ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="page-wrap">

  <!-- Search -->
  <section class="search-section">
    <div class="search-eyebrow">Profile Search</div>
    <div class="search-heading">Lookup a Google Scholar Researcher</div>
    <form method="POST">
      <div class="search-form-row">
        <input class="search-input" type="text" name="user_id"
          placeholder="Paste Google Scholar URL or user ID — e.g. JicYPdAAAAAJ"
          value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
        <button class="search-btn" type="submit">Search →</button>
      </div>
      <div class="search-hint">
        Runs server-side — no CORS restrictions. &nbsp;·&nbsp;
        Journal metrics via <code>SCImago</code> (SJR, Quartile, Cites/Doc ≈ JIF) and <code>OpenAlex</code> fallback. &nbsp;·&nbsp;
        No API keys required.
      </div>
    </form>
  </section>

  <?php if ($error): ?>
  <div class="alert alert-error">
    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($result): ?>

  <!-- Impact factor notice -->
  <div class="alert alert-info">
    <strong>About Impact Factor:</strong>
    The Clarivate <em>Journal Impact Factor (JIF)</em> is proprietary (Elsevier/Web of Science paywall).
    This tool shows the closest freely available equivalents from SCImago:
    <strong>Cites/Doc&nbsp;(2yr)</strong> — computed with the identical formula on Scopus data —
    <strong>SJR</strong> (prestige-weighted rank), and <strong>Quartile&nbsp;(Q1–Q4)</strong>.
    Data from <a href="https://www.scimagojr.com" target="_blank">scimagojr.com</a>
    and <a href="https://openalex.org" target="_blank">OpenAlex</a>.
  </div>

  <div class="article-layout">

    <!-- ── Main content column ─────────────────────────────── -->
    <main>

      <!-- Researcher header (like a Nature article header) -->
      <header class="article-header">
        <div class="article-type-tag">Researcher Profile</div>
        <div class="researcher-meta-row" style="margin-bottom:1rem">
          <?php if (!empty($result['avatar'])): ?>
            <img class="researcher-avatar" src="<?= htmlspecialchars($result['avatar']) ?>" alt="">
          <?php else: ?>
            <div class="researcher-avatar-ph"><?= mb_strtoupper(mb_substr($result['name'],0,1)) ?></div>
          <?php endif; ?>
          <div class="researcher-info">
            <h1 class="researcher-name"><?= htmlspecialchars($result['name']) ?></h1>
            <?php if ($result['affiliation']): ?>
            <div class="researcher-affil"><?= htmlspecialchars($result['affiliation']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($result['interests']): ?>
        <div class="keyword-list">
          <?php foreach ($result['interests'] as $kw): ?>
            <span class="keyword"><?= htmlspecialchars($kw) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </header>

      <!-- Publication timeline chart -->
      <div class="chart-container">
        <h2 class="section-heading">Publication Timeline</h2>
        <?php
          $yearMap = [];
          foreach ($result['papers'] as $p) {
            if (!empty($p['year'])) $yearMap[$p['year']] = ($yearMap[$p['year']] ?? 0) + 1;
          }
          ksort($yearMap);
          $maxY = max(array_values($yearMap) ?: [1]);
        ?>
        <div class="chart-area">
          <?php foreach ($yearMap as $yr => $cnt): ?>
            <?php $h = max(2, (int)round(($cnt / $maxY) * 90)); ?>
            <div class="bar-col" title="<?= $cnt ?> papers in <?= $yr ?>">
              <span class="bar-count"><?= $cnt ?></span>
              <div class="bar" style="height:<?= $h ?>px"></div>
              <span class="bar-year-label"><?= $yr ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Papers list -->
      <div class="papers-list">
        <h2 class="section-heading">Publications (<?= count($result['papers']) ?>)</h2>

        <!-- Quartile legend -->
        <div class="quartile-legend">
          <?php foreach (['Q1'=>'Top 25%','Q2'=>'Top 50%','Q3'=>'Top 75%','Q4'=>'Bottom 25%'] as $q => $label): ?>
            <?php $qc = quartileColor($q); ?>
            <div class="legend-item">
              <div class="legend-swatch" style="background:<?= $qc['bg'] ?>;border-color:<?= $qc['border'] ?>"></div>
              <span><?= $q ?> — <?= $label ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <?php foreach ($result['papers'] as $i => $p): ?>
        <?php $m = $p['metrics'] ?? null; ?>
        <div class="paper-item">
          <div class="paper-number"><?= $i + 1 ?></div>
          <div class="paper-body">
            <div class="paper-title">
              <?php if (!empty($p['link'])): ?>
                <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener">
                  <?= htmlspecialchars($p['title']) ?>
                </a>
              <?php else: ?>
                <?= htmlspecialchars($p['title']) ?>
              <?php endif; ?>
            </div>
            <div class="paper-venue-row">
              <?php $venue = $p['venue_clean'] ?? $p['venue'] ?? ''; ?>
              <?php if ($venue): ?>
                <span class="paper-venue-name"><?= htmlspecialchars($venue) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['year'])): ?>
                <span class="paper-year-chip"><?= htmlspecialchars($p['year']) ?></span>
              <?php endif; ?>
              <?php if (!empty($p['cited']) && $p['cited'] !== '0'): ?>
                <span class="paper-cite-chip">
                  <?= htmlspecialchars($p['cited']) ?> citation<?= $p['cited'] != 1 ? 's' : '' ?>
                </span>
              <?php endif; ?>
            </div>

            <?php if ($m): ?>
            <div class="paper-metrics-row">
              <?php if (!empty($m['quartile'])): ?>
                <?php $qc = quartileColor($m['quartile']); ?>
                <span class="chip chip-quartile"
                  style="background:<?= $qc['bg'] ?>;border-color:<?= $qc['border'] ?>;color:<?= $qc['text'] ?>">
                  <?= htmlspecialchars($m['quartile']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($m['sjr'])): ?>
                <span class="chip chip-sjr" title="SCImago Journal Rank">SJR <?= htmlspecialchars($m['sjr']) ?></span>
              <?php endif; ?>
              <?php if (!empty($m['cites_per_doc_2yr'])): ?>
                <span class="chip chip-if" title="Cites per Document (2yr) — equivalent to Impact Factor formula">
                  IF≈ <?= htmlspecialchars($m['cites_per_doc_2yr']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($m['h_index'])): ?>
                <span class="chip chip-h" title="Journal h-index">h-index <?= htmlspecialchars($m['h_index']) ?></span>
              <?php endif; ?>
              <?php if (!empty($m['source'])): ?>
                <span class="chip chip-src"><?= htmlspecialchars($m['source']) ?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endforeach; ?>
      </div>

    </main>

    <!-- ── Sidebar ──────────────────────────────────────────── -->
    <aside class="sidebar">

      <!-- Citation metrics card -->
      <div class="sidebar-card">
        <div class="sidebar-card-header">Citation Metrics</div>
        <div class="sidebar-card-body">
          <div class="sidebar-stat-row">
            <span class="sidebar-stat-label">Total Citations</span>
            <span class="sidebar-stat-value"><?= htmlspecialchars($result['citations']) ?></span>
          </div>
          <div class="sidebar-stat-row">
            <span class="sidebar-stat-label">h-index</span>
            <span class="sidebar-stat-value"><?= htmlspecialchars($result['h_index']) ?></span>
          </div>
          <div class="sidebar-stat-row">
            <span class="sidebar-stat-label">i10-index</span>
            <span class="sidebar-stat-value"><?= htmlspecialchars($result['i10_index']) ?></span>
          </div>
          <div class="sidebar-stat-row">
            <span class="sidebar-stat-label">Papers</span>
            <span class="sidebar-stat-value"><?= count($result['papers']) ?></span>
          </div>
        </div>
      </div>

      <!-- Download card -->
      <div class="sidebar-card">
        <div class="sidebar-card-header">Download Data</div>
        <div class="sidebar-card-body">
          <a class="sidebar-action"
            href="export.php?user_id=<?= urlencode($_POST['user_id']) ?>">
            ↓ Download CSV
          </a>
          <div style="font-size:0.72rem;color:var(--text-muted);line-height:1.5">
            Includes title, venue, year, citations, Quartile, SJR, Cites/Doc (≈ JIF), and h-index per paper.
          </div>
        </div>
      </div>

      <!-- About metrics card -->
      <div class="sidebar-card">
        <div class="sidebar-card-header">About Metrics</div>
        <div class="sidebar-card-body" style="font-size:0.76rem;color:var(--text-meta);line-height:1.65">
          <p style="margin-bottom:0.6rem">
            <strong style="color:var(--text-body)">IF≈ (Cites/Doc 2yr)</strong><br>
            Same formula as Clarivate JIF, computed on Scopus citation data. Highly correlated with JIF.
          </p>
          <p style="margin-bottom:0.6rem">
            <strong style="color:var(--text-body)">SJR</strong><br>
            SCImago Journal Rank. Prestige-weighted score — higher-quality citations count more.
          </p>
          <p style="margin-bottom:0.6rem">
            <strong style="color:var(--text-body)">Quartile</strong><br>
            Where the journal ranks among all journals in its Scopus subject category.
          </p>
          <p>
            Source: <a href="https://www.scimagojr.com" target="_blank">SCImago</a> /
            <a href="https://openalex.org" target="_blank">OpenAlex</a>
          </p>
        </div>
      </div>

    </aside>

  </div><!-- /.article-layout -->

  <?php endif; ?>

</div><!-- /.page-wrap -->

<footer class="site-footer">
  ScholarLens &nbsp;·&nbsp; Journal metrics from
  <a href="https://www.scimagojr.com" target="_blank">SCImago</a> &amp;
  <a href="https://openalex.org" target="_blank">OpenAlex</a> &nbsp;·&nbsp;
  Not affiliated with Google Scholar, Clarivate, or Nature Publishing Group.
</footer>

</body>
</html>