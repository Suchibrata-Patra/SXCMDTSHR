<?php
// ─────────────────────────────────────────────────────────────
//  ScholarLens — index.php
//  Nature-black academic UI · Live progress · Gemini IF
// ─────────────────────────────────────────────────────────────
require_once 'scholar.php';

$result = null;
$error  = null;
$apiKey = GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE' && !empty(GEMINI_API_KEY);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_id'])) {
    $userId = trim($_POST['user_id']);
    if (preg_match('/[?&]user=([^&\s]+)/', $userId, $m)) $userId = $m[1];
    $userId = preg_replace('/[^A-Za-z0-9_\-]/', '', $userId);
    if (empty($userId)) {
        $error = 'Invalid ID — paste the full Scholar URL or just the user ID.';
    } else {
        try {
            $result = fetchScholarProfile($userId);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

function qColor(string $q): array {
    return match($q) {
        'Q1' => ['#d4edda','#28a745','#155724'],
        'Q2' => ['#fff3cd','#ffc107','#856404'],
        'Q3' => ['#ffe5d0','#fd7e14','#7d3514'],
        'Q4' => ['#f8d7da','#dc3545','#721c24'],
        default => ['#2a2a2a','#555','#aaa'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ScholarLens — Research Profile Extractor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,700;1,400&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ════════════════════════════════════════════════════════════
   NATURE-BLACK ACADEMIC DESIGN SYSTEM
   Inspired by: Nature.com dark overlays, Springer dark panels,
   Cell Press bold headers — sharp monochrome editorial aesthetic
   ════════════════════════════════════════════════════════════ */

:root {
  /* Core palette */
  --ink:        #0a0a0a;
  --ink-90:     #111111;
  --ink-80:     #1a1a1a;
  --ink-70:     #222222;
  --ink-60:     #2e2e2e;
  --rule:       #2a2a2a;
  --rule-mid:   #383838;
  --rule-lt:    #444444;
  --smoke:      #888888;
  --mist:       #aaaaaa;
  --fog:        #cccccc;
  --white:      #f5f4f0;   /* warm off-white like quality paper */
  --pure:       #ffffff;

  /* Nature red accent */
  --red:        #c8102e;
  --red-dim:    #8a0b1f;
  --red-glow:   rgba(200,16,46,.15);

  /* Gemini purple */
  --gem:        #8b5cf6;
  --gem-dim:    rgba(139,92,246,.12);

  /* Impact factor green */
  --if-green:   #22c55e;
  --if-dim:     rgba(34,197,94,.12);

  /* Typography */
  --font-serif: 'EB Garamond', 'Georgia', serif;
  --font-sans:  'Inter', 'Helvetica Neue', sans-serif;
  --font-mono:  'JetBrains Mono', 'Courier New', monospace;
}

*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; scroll-behavior: smooth; }
body {
  background: var(--ink);
  color: var(--fog);
  font-family: var(--font-sans);
  font-weight: 400;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}

/* ── Scrollbar ──────────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; background: var(--ink-80); }
::-webkit-scrollbar-thumb { background: var(--rule-lt); border-radius: 3px; }

/* ── Top red stripe ─────────────────────────────────────────── */
.stripe { height: 3px; background: var(--red); }

/* ── Site header ────────────────────────────────────────────── */
.site-header {
  background: var(--ink-90);
  border-bottom: 1px solid var(--rule);
  position: sticky; top: 0; z-index: 100;
  backdrop-filter: blur(12px);
}
.hdr-inner {
  max-width: 1100px; margin: 0 auto;
  padding: 0 2rem;
  height: 58px;
  display: flex; align-items: center; justify-content: space-between;
}
.logo {
  display: flex; align-items: center; gap: .6rem;
  text-decoration: none;
}
.logo-mark {
  width: 32px; height: 32px;
  background: var(--red);
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif);
  font-weight: 700; font-size: 1rem;
  color: var(--pure);
  letter-spacing: -.02em;
  flex-shrink: 0;
}
.logo-name {
  font-family: var(--font-serif);
  font-size: 1.2rem; font-weight: 700;
  color: var(--white);
  letter-spacing: -.01em;
}
.logo-name em { color: var(--red); font-style: normal; }
.hdr-badge {
  display: flex; align-items: center; gap: .35rem;
  font-size: .7rem; color: var(--smoke);
  font-family: var(--font-mono);
  letter-spacing: .04em;
}
.hdr-badge .dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--gem); flex-shrink: 0;
  box-shadow: 0 0 6px var(--gem);
}

/* ── Breadcrumb ─────────────────────────────────────────────── */
.breadcrumb {
  background: var(--ink-80);
  border-bottom: 1px solid var(--rule);
  padding: .4rem 0; font-size: .72rem;
}
.breadcrumb .inner {
  max-width: 1100px; margin: 0 auto; padding: 0 2rem;
  display: flex; align-items: center; gap: .4rem;
  color: var(--smoke);
}
.breadcrumb a { color: var(--mist); }
.breadcrumb a:hover { color: var(--white); }
.breadcrumb .sep { color: var(--rule-lt); }

/* ── Page wrap ──────────────────────────────────────────────── */
.page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 2rem 6rem;
}

/* ── Search section ─────────────────────────────────────────── */
.search-section {
  padding: 3rem 0 2.5rem;
  border-bottom: 1px solid var(--rule);
  margin-bottom: 2.5rem;
}
.search-eyebrow {
  font-family: var(--font-mono);
  font-size: .65rem; letter-spacing: .14em;
  text-transform: uppercase; color: var(--red);
  margin-bottom: .5rem;
}
.search-title {
  font-family: var(--font-serif);
  font-size: 2.1rem; font-weight: 700;
  color: var(--white);
  letter-spacing: -.02em; line-height: 1.2;
  margin-bottom: 1.5rem;
}
.search-title span { color: var(--red); }

.search-form {
  display: flex; gap: .6rem; flex-wrap: wrap;
  margin-bottom: .8rem;
}
.search-input {
  flex: 1; min-width: 300px; height: 48px;
  background: var(--ink-70);
  border: 1px solid var(--rule-lt);
  border-radius: 4px;
  padding: 0 1.1rem;
  font-family: var(--font-mono); font-size: .82rem;
  color: var(--white);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.search-input:focus {
  border-color: var(--red);
  box-shadow: 0 0 0 3px var(--red-glow);
}
.search-input::placeholder { color: var(--smoke); }

.search-btn {
  height: 48px; padding: 0 2rem;
  background: var(--red); color: var(--pure);
  border: none; border-radius: 4px;
  font-family: var(--font-sans); font-weight: 600;
  font-size: .875rem; letter-spacing: .03em;
  cursor: pointer;
  transition: background .15s, transform .1s;
  white-space: nowrap;
}
.search-btn:hover { background: #a00d24; }
.search-btn:active { transform: scale(.98); }

.search-note {
  font-size: .72rem; color: var(--smoke); line-height: 1.7;
}
.search-note code {
  font-family: var(--font-mono); font-size: .68rem;
  background: var(--ink-70); color: var(--mist);
  padding: .1rem .4rem; border-radius: 3px;
  border: 1px solid var(--rule-lt);
}

/* ── API key notice ─────────────────────────────────────────── */
.notice {
  margin: 1.2rem 0; padding: .85rem 1.1rem;
  border-radius: 4px; font-size: .8rem; line-height: 1.65;
  border-left: 3px solid;
}
.notice.warn { background: rgba(255,193,7,.07); border-color: #ffc107; color: #ffd54f; }
.notice.active { background: var(--gem-dim); border-color: var(--gem); color: #c4b5fd; }
.notice.error { background: var(--red-glow); border-color: var(--red); color: #fca5a5; }
.notice a { color: inherit; text-decoration: underline dotted; }
.notice code { font-family: var(--font-mono); font-size: .7rem;
  background: rgba(255,255,255,.08); padding: .1rem .35rem; border-radius: 2px; }

/* ════════════════════════════════════════════════════════════
   LIVE PROGRESS PANEL
   ════════════════════════════════════════════════════════════ */
#progress-panel {
  display: none;
  background: var(--ink-80);
  border: 1px solid var(--rule-mid);
  border-radius: 6px;
  padding: 1.5rem;
  margin: 2rem 0;
}
.progress-header {
  display: flex; align-items: center; gap: .75rem;
  margin-bottom: 1.25rem;
}
.progress-spinner {
  width: 20px; height: 20px;
  border: 2px solid var(--rule-lt);
  border-top-color: var(--red);
  border-radius: 50%;
  animation: spin .7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
.progress-title {
  font-family: var(--font-serif); font-size: 1.05rem;
  color: var(--white); font-weight: 500;
}
.progress-subtitle {
  font-size: .72rem; color: var(--smoke);
  font-family: var(--font-mono);
}

/* Step list */
.step-list { display: flex; flex-direction: column; gap: .5rem; }
.step {
  display: flex; align-items: center; gap: .7rem;
  padding: .55rem .75rem;
  border-radius: 4px;
  background: var(--ink-70);
  font-size: .8rem; color: var(--mist);
  transition: background .3s, color .3s;
}
.step.active { background: rgba(200,16,46,.1); color: var(--white); }
.step.done   { background: rgba(34,197,94,.08); color: #86efac; }
.step.error  { background: rgba(200,16,46,.1);  color: #fca5a5; }
.step-icon { font-size: .85rem; width: 18px; text-align: center; flex-shrink: 0; }
.step-text { flex: 1; }
.step-badge {
  font-family: var(--font-mono); font-size: .65rem;
  background: rgba(255,255,255,.07); padding: .1rem .4rem;
  border-radius: 3px; color: var(--smoke);
}
.step.active .step-badge { color: var(--red); }
.step.done   .step-badge { color: #4ade80; }

/* Venue progress */
.venue-progress {
  margin-top: 1rem;
  border-top: 1px solid var(--rule);
  padding-top: .9rem;
}
.vp-header {
  display: flex; justify-content: space-between;
  align-items: center; margin-bottom: .65rem;
}
.vp-label { font-size: .7rem; font-family: var(--font-mono); color: var(--smoke); letter-spacing: .06em; }
.vp-count { font-size: .7rem; font-family: var(--font-mono); color: var(--red); }

.prog-track {
  width: 100%; height: 4px;
  background: var(--rule-mid); border-radius: 2px;
  overflow: hidden; margin-bottom: .75rem;
}
.prog-fill {
  height: 100%; background: var(--red);
  border-radius: 2px; width: 0;
  transition: width .5s ease;
}

.venue-chips {
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.venue-chip {
  font-family: var(--font-mono); font-size: .62rem;
  padding: .2rem .6rem; border-radius: 3px;
  border: 1px solid var(--rule-lt); color: var(--smoke);
  background: var(--ink-70); max-width: 200px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  transition: all .3s;
}
.venue-chip.loading {
  border-color: var(--red);
  color: var(--red);
  animation: pulse 1s ease-in-out infinite;
}
.venue-chip.done    { border-color: #166534; color: #4ade80; background: rgba(34,197,94,.07); }
.venue-chip.no-data { border-color: var(--rule-lt); color: var(--rule-lt); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

/* ════════════════════════════════════════════════════════════
   RESULTS LAYOUT
   ════════════════════════════════════════════════════════════ */
#results { display: none; }

.results-layout {
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 2.5rem;
  align-items: start;
}

/* ── Profile card ───────────────────────────────────────────── */
.profile-card {
  background: var(--ink-80);
  border: 1px solid var(--rule-mid);
  border-radius: 6px;
  padding: 1.75rem;
  margin-bottom: 2rem;
}
.profile-type-tag {
  display: inline-block;
  font-family: var(--font-mono);
  font-size: .62rem; letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--red); border: 1px solid var(--red-dim);
  padding: .15rem .55rem; border-radius: 3px;
  margin-bottom: 1rem;
}
.profile-row {
  display: flex; align-items: flex-start; gap: 1.1rem;
  margin-bottom: .9rem;
}
.profile-avatar {
  width: 72px; height: 72px; border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--rule-mid); flex-shrink: 0;
}
.profile-avatar-ph {
  width: 72px; height: 72px; border-radius: 50%;
  background: var(--ink-70);
  border: 2px solid var(--rule-mid);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-serif); font-size: 1.6rem; font-weight: 700;
  color: var(--smoke); flex-shrink: 0;
}
.profile-name {
  font-family: var(--font-serif);
  font-size: 1.6rem; font-weight: 700;
  color: var(--white); line-height: 1.2;
  letter-spacing: -.02em; margin-bottom: .25rem;
}
.profile-affil {
  font-size: .83rem; color: var(--smoke);
  font-style: italic; line-height: 1.4;
}

.kw-list { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .75rem; }
.kw {
  font-size: .68rem; color: var(--mist);
  background: var(--ink-70);
  border: 1px solid var(--rule-lt);
  padding: .2rem .6rem; border-radius: 3px;
  cursor: default; transition: all .15s;
}
.kw:hover { border-color: var(--red-dim); color: var(--red); }

/* ── Section heading ────────────────────────────────────────── */
.sec-h {
  font-family: var(--font-serif);
  font-size: 1rem; font-weight: 700;
  color: var(--white);
  margin-bottom: 1.1rem;
  padding-bottom: .4rem;
  border-bottom: 1px solid var(--rule-mid);
  letter-spacing: -.01em;
  display: flex; align-items: center; gap: .6rem;
}
.sec-h .sec-count {
  font-family: var(--font-mono); font-size: .68rem;
  color: var(--smoke); font-weight: 400;
  background: var(--ink-70); padding: .1rem .45rem;
  border-radius: 3px; border: 1px solid var(--rule-lt);
}

/* ════════════════════════════════════════════════════════════
   CHART — compact, pro, right-aligned
   ════════════════════════════════════════════════════════════ */
.chart-section { margin-bottom: 2.5rem; }
.chart-outer {
  background: var(--ink-80);
  border: 1px solid var(--rule-mid);
  border-radius: 6px;
  padding: 1.25rem 1.4rem 1rem;
}
.chart-inner {
  /* fixed height, NOT full-width — compact like Nature charts */
  height: 140px;
  display: flex;
  align-items: flex-end;
  gap: 6px;
  border-bottom: 1px solid var(--rule-mid);
  border-left: 1px solid var(--rule-mid);
  padding: 0 0 0 4px;
  max-width: 600px;   /* ← cap the width so it's not horizon-spanning */
}
.ch-col {
  display: flex; flex-direction: column;
  align-items: center; justify-content: flex-end;
  gap: 3px; flex: 1; max-width: 36px;
  height: 100%; position: relative;
}
.ch-bar {
  width: 100%;
  background: var(--red);
  border-radius: 2px 2px 0 0;
  min-height: 2px;
  transition: opacity .15s;
  position: relative;
}
.ch-bar:hover { opacity: .75; }
.ch-bar::after {
  content: attr(data-n);
  position: absolute; bottom: 100%; left: 50%;
  transform: translateX(-50%);
  font-family: var(--font-mono); font-size: .52rem;
  color: var(--smoke); margin-bottom: 2px;
  pointer-events: none;
}
.ch-yr {
  font-family: var(--font-mono); font-size: .5rem;
  color: var(--smoke);
  writing-mode: vertical-rl;
  text-orientation: mixed;
  transform: rotate(180deg);
  margin-top: 3px;
  white-space: nowrap;
}
.chart-meta {
  margin-top: .6rem;
  font-family: var(--font-mono); font-size: .62rem;
  color: var(--smoke); display: flex; gap: 1.5rem;
}
.chart-meta span { display: flex; align-items: center; gap: .3rem; }
.chart-meta .dot-r { width:8px;height:8px;border-radius:2px;background:var(--red);flex-shrink:0; }

/* ════════════════════════════════════════════════════════════
   PAPERS LIST
   ════════════════════════════════════════════════════════════ */
.papers-section { margin-bottom: 2rem; }

.q-legend {
  display: flex; flex-wrap: wrap; gap: .5rem 1.2rem;
  margin-bottom: 1rem; font-size: .68rem; color: var(--smoke);
}
.ql-item { display: flex; align-items: center; gap: .3rem; }
.ql-dot { width:10px;height:10px;border-radius:2px;flex-shrink:0; }

.paper-item {
  display: grid;
  grid-template-columns: 26px 1fr;
  gap: 0 .65rem;
  padding: 1rem 0;
  border-bottom: 1px solid var(--rule);
}
.p-num {
  font-family: var(--font-mono); font-size: .65rem;
  color: var(--rule-lt); padding-top: .1rem;
  text-align: right; line-height: 1.6;
}
.p-body {}
.p-title {
  font-family: var(--font-serif);
  font-size: .95rem; font-weight: 400;
  color: var(--white); line-height: 1.4; margin-bottom: .3rem;
}
.p-title a { color: #6ab0d4; text-decoration: none; }
.p-title a:hover { color: var(--white); text-decoration: underline; }

.p-meta {
  display: flex; flex-wrap: wrap; align-items: center; gap: .4rem;
  font-size: .75rem; color: var(--smoke); margin-bottom: .5rem;
}
.p-venue { font-style: italic; color: var(--mist); }
.p-year-chip {
  font-family: var(--font-mono); font-size: .65rem;
  background: var(--ink-70); color: var(--smoke);
  border: 1px solid var(--rule-lt); padding: .08rem .4rem; border-radius: 3px;
}
.p-cite-chip {
  font-family: var(--font-mono); font-size: .65rem; color: #6ab0d4;
}

/* Metric chips */
.m-row { display: flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
.chip {
  display: inline-flex; align-items: center; gap: .2rem;
  padding: .16rem .5rem; border-radius: 3px;
  font-size: .65rem; font-family: var(--font-mono);
  border: 1px solid; line-height: 1.4; white-space: nowrap;
}
.chip-if   { background:var(--if-dim); border-color:#166534; color:var(--if-green); font-weight:600; }
.chip-q1   { background:rgba(40,167,69,.12);  border-color:#166534; color:#4ade80; }
.chip-q2   { background:rgba(255,193,7,.1);   border-color:#92400e; color:#fbbf24; }
.chip-q3   { background:rgba(253,126,20,.1);  border-color:#92400e; color:#fb923c; }
.chip-q4   { background:rgba(220,53,69,.1);   border-color:#7f1d1d; color:#f87171; }
.chip-sjr  { background:rgba(107,176,212,.1); border-color:#164e63; color:#7dd3fc; }
.chip-cs   { background:var(--gem-dim);       border-color:#4c1d95; color:#c4b5fd; }
.chip-h    { background:rgba(251,191,36,.08); border-color:#78350f; color:#fbbf24; }
.chip-src  { background:transparent; border-color:var(--rule-lt); color:var(--smoke); font-size:.6rem; }
.chip-gem  { background:var(--gem-dim); border-color:#4c1d95; color:#a78bfa; font-size:.6rem; }
.chip-load { background:var(--ink-70); border-color:var(--rule-lt); color:var(--smoke);
             animation: pulse 1.2s ease-in-out infinite; }

.p-sources-btn {
  background:none; border:none; cursor:pointer;
  font-size:.62rem; color:var(--smoke); font-family:var(--font-mono);
  text-decoration:underline dotted; margin-top:.25rem;
  display:block;
}
.p-sources-btn:hover { color:var(--mist); }
.p-sources {
  margin-top:.35rem; padding:.5rem .7rem;
  background:var(--ink-70); border:1px solid var(--rule-lt);
  border-radius:4px; font-size:.66rem; line-height:1.7;
  display:none;
}
.p-sources.open { display:block; }
.p-sources a { color:#7dd3fc; font-size:.66rem; }

/* ════════════════════════════════════════════════════════════
   SIDEBAR
   ════════════════════════════════════════════════════════════ */
.sidebar {}
.sb-card {
  background: var(--ink-80);
  border: 1px solid var(--rule-mid);
  border-radius: 6px;
  margin-bottom: 1.25rem;
  overflow: hidden;
}
.sb-hd {
  background: var(--ink-70);
  border-bottom: 1px solid var(--rule-mid);
  padding: .55rem .9rem;
  font-family: var(--font-mono);
  font-size: .62rem; letter-spacing: .1em;
  text-transform: uppercase; color: var(--smoke);
}
.sb-body { padding: .85rem .9rem; }
.sb-row {
  display: flex; justify-content: space-between; align-items: baseline;
  padding: .35rem 0;
  border-bottom: 1px solid var(--rule);
  font-size: .8rem;
}
.sb-row:last-child { border-bottom: none; }
.sb-label { color: var(--smoke); }
.sb-val {
  font-family: var(--font-serif);
  font-weight: 700; font-size: 1rem;
  color: var(--white);
}
.sb-btn {
  display: block; width: 100%;
  padding: .6rem; text-align: center;
  background: var(--rule-mid);
  color: var(--mist); border: 1px solid var(--rule-lt);
  border-radius: 4px; font-size: .75rem; font-weight: 600;
  text-decoration: none; transition: all .15s;
  font-family: var(--font-sans);
  margin-bottom: .5rem;
}
.sb-btn:hover { background: var(--rule-lt); color: var(--white); text-decoration: none; }
.sb-btn.primary { background: var(--red); border-color: var(--red); color: var(--pure); }
.sb-btn.primary:hover { background: #a00d24; }
.sb-note { font-size: .68rem; color: var(--smoke); line-height: 1.55; }

.gem-badge {
  display: flex; align-items: center; gap: .4rem;
  background: var(--gem-dim);
  border: 1px solid rgba(139,92,246,.25);
  border-radius: 4px; padding: .4rem .65rem;
  font-size: .7rem; color: #c4b5fd;
  font-family: var(--font-mono); margin-bottom: .75rem;
}
.gem-badge::before { content: '✦'; color: var(--gem); }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 820px) {
  .results-layout { grid-template-columns: 1fr; }
  .sidebar { order: -1; }
  .chart-inner { max-width: 100%; }
}
@media (max-width: 540px) {
  .search-input { min-width: 100%; }
  .search-title { font-size: 1.6rem; }
}

/* ── Footer ─────────────────────────────────────────────────── */
.site-footer {
  border-top: 1px solid var(--rule);
  padding: 1.5rem 0;
  text-align: center;
  font-size: .72rem; color: var(--smoke);
}
.site-footer a { color: var(--mist); }
</style>
</head>
<body>

<div class="stripe"></div>

<header class="site-header">
  <div class="hdr-inner">
    <a class="logo" href="/">
      <div class="logo-mark">SL</div>
      <div class="logo-name">Scholar<em>Lens</em></div>
    </a>
    <div class="hdr-badge">
      <div class="dot"></div>
      Gemini 2.0 Flash · Google Search Grounding
    </div>
  </div>
</header>

<div class="breadcrumb">
  <div class="inner">
    <a href="/">Home</a>
    <span class="sep">›</span>
    <span>Profile Extractor</span>
    <?php if ($result): ?>
    <span class="sep">›</span>
    <span><?= htmlspecialchars($result['name']) ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="page">

  <!-- ── SEARCH SECTION ───────────────────────────────────────── -->
  <section class="search-section">
    <div class="search-eyebrow">// scholar profile extractor</div>
    <h1 class="search-title">Research <span>Intelligence</span> Dashboard</h1>

    <?php if (!$apiKey): ?>
    <div class="notice warn">
      <strong>⚠ Gemini API key not set.</strong>
      Journal metrics (Impact Factor, SJR, Quartile) need a key.<br>
      1. Free key at <a href="https://aistudio.google.com/apikey" target="_blank">aistudio.google.com/apikey</a><br>
      2. In <code>scholar.php</code> set <code>define('GEMINI_API_KEY', 'AIza...')</code><br>
      Scholar profile + charts still work without a key.
    </div>
    <?php else: ?>
    <div class="notice active">
      ✦ Gemini 2.0 Flash active — Impact Factor fetched live via Google Search grounding.
      Free tier: 1,500 requests/day · 15 req/min.
    </div>
    <?php endif; ?>

    <form class="search-form" id="searchForm" method="POST">
      <input class="search-input" type="text" name="user_id" id="userIdInput"
        placeholder="Scholar URL or user ID — e.g. JicYPdAAAAAJ"
        value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
      <button class="search-btn" type="submit" id="searchBtn">
        Analyze Profile →
      </button>
    </form>
    <div class="search-note">
      Fetches server-side — no CORS. &nbsp;·&nbsp;
      Scholar profile + stats parsed first. &nbsp;·&nbsp;
      Then Gemini looks up <code>Impact Factor</code>, <code>SJR</code>, <code>Quartile</code>, <code>CiteScore</code> per journal. &nbsp;·&nbsp;
      Progress shown live below.
    </div>
  </section>

  <?php if ($error): ?>
  <div class="notice error"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ════════════════════════════════════════════════════════
       LIVE PROGRESS PANEL (shown during AJAX metric fetch)
       ════════════════════════════════════════════════════════ -->
  <div id="progress-panel">
    <div class="progress-header">
      <div class="progress-spinner" id="mainSpinner"></div>
      <div>
        <div class="progress-title" id="progressTitle">Fetching Scholar profile…</div>
        <div class="progress-subtitle" id="progressSub">Connecting to Google Scholar</div>
      </div>
    </div>

    <div class="step-list" id="stepList">
      <div class="step" id="step-scholar">
        <span class="step-icon">📡</span>
        <span class="step-text">Fetching Google Scholar profile</span>
        <span class="step-badge" id="step-scholar-badge">waiting</span>
      </div>
      <div class="step" id="step-parse">
        <span class="step-icon">🔍</span>
        <span class="step-text">Parsing papers, citations & metadata</span>
        <span class="step-badge" id="step-parse-badge">waiting</span>
      </div>
      <div class="step" id="step-gemini">
        <span class="step-icon">✦</span>
        <span class="step-text">Gemini · looking up journal metrics via Google Search</span>
        <span class="step-badge" id="step-gemini-badge">waiting</span>
      </div>
      <div class="step" id="step-done">
        <span class="step-icon">✅</span>
        <span class="step-text">All done — rendering results</span>
        <span class="step-badge" id="step-done-badge">waiting</span>
      </div>
    </div>

    <!-- Venue-level progress (shown when Gemini step active) -->
    <div class="venue-progress" id="venueProgress" style="display:none">
      <div class="vp-header">
        <span class="vp-label">// JOURNAL LOOKUP PROGRESS</span>
        <span class="vp-count" id="venueCount">0 / 0</span>
      </div>
      <div class="prog-track"><div class="prog-fill" id="progFill"></div></div>
      <div class="venue-chips" id="venueChips"></div>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════
       RESULTS  (populated by PHP + enriched by JS)
       ════════════════════════════════════════════════════════ -->
  <?php if ($result): ?>
  <div id="results" style="display:block">
    <div class="results-layout">

      <!-- ── MAIN CONTENT ──────────────────────────────────── -->
      <main>

        <!-- Profile card -->
        <div class="profile-card">
          <div class="profile-type-tag">// researcher · profile</div>
          <div class="profile-row">
            <?php if (!empty($result['avatar'])): ?>
              <img class="profile-avatar" src="<?= htmlspecialchars($result['avatar']) ?>" alt="">
            <?php else: ?>
              <div class="profile-avatar-ph"><?= mb_strtoupper(mb_substr($result['name'],0,1)) ?></div>
            <?php endif; ?>
            <div>
              <div class="profile-name"><?= htmlspecialchars($result['name']) ?></div>
              <?php if ($result['affiliation']): ?>
              <div class="profile-affil"><?= htmlspecialchars($result['affiliation']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($result['interests']): ?>
          <div class="kw-list">
            <?php foreach ($result['interests'] as $kw): ?>
              <span class="kw"><?= htmlspecialchars($kw) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Chart -->
        <?php
          $ym = []; $totalPapers = 0;
          foreach ($result['papers'] as $p) {
            if (!empty($p['year'])) { $ym[$p['year']] = ($ym[$p['year']] ?? 0) + 1; $totalPapers++; }
          }
          ksort($ym);
          $maxY = max(array_values($ym) ?: [1]);
          $peakYear = array_search($maxY, $ym);
        ?>
        <div class="chart-section">
          <h2 class="sec-h">
            Publication Timeline
            <span class="sec-count"><?= $totalPapers ?> papers · peak <?= $peakYear ?></span>
          </h2>
          <div class="chart-outer">
            <div class="chart-inner">
              <?php foreach ($ym as $yr => $n): ?>
                <?php $h = max(3, (int)round(($n/$maxY)*118)); ?>
                <div class="ch-col" title="<?= $n ?> papers · <?= $yr ?>">
                  <div class="ch-bar" style="height:<?= $h ?>px" data-n="<?= $n ?>"></div>
                  <div class="ch-yr"><?= $yr ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="chart-meta">
              <span><span class="dot-r"></span><?= count($ym) ?> years active</span>
              <span>Peak: <?= $maxY ?> papers in <?= $peakYear ?></span>
              <span>Total: <?= $totalPapers ?> indexed</span>
            </div>
          </div>
        </div>

        <!-- Papers -->
        <div class="papers-section">
          <h2 class="sec-h">
            Publications
            <span class="sec-count"><?= count($result['papers']) ?> papers</span>
          </h2>

          <?php if ($apiKey): ?>
          <div class="q-legend">
            <div class="ql-item"><div class="ql-dot" style="background:#4ade80"></div>Q1 — top 25%</div>
            <div class="ql-item"><div class="ql-dot" style="background:#fbbf24"></div>Q2 — top 50%</div>
            <div class="ql-item"><div class="ql-dot" style="background:#fb923c"></div>Q3 — top 75%</div>
            <div class="ql-item"><div class="ql-dot" style="background:#f87171"></div>Q4 — bottom 25%</div>
            <div class="ql-item"><div class="ql-dot" style="background:#22c55e"></div>IF — Impact Factor (live)</div>
          </div>
          <?php endif; ?>

          <?php foreach ($result['papers'] as $i => $p): ?>
          <?php $vc = htmlspecialchars($p['venue_clean'] ?? ''); ?>
          <div class="paper-item" id="paper-<?= $i ?>">
            <div class="p-num"><?= $i+1 ?></div>
            <div class="p-body">
              <div class="p-title">
                <?php if (!empty($p['link'])): ?>
                  <a href="<?= htmlspecialchars($p['link']) ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($p['title']) ?>
                  </a>
                <?php else: ?>
                  <?= htmlspecialchars($p['title']) ?>
                <?php endif; ?>
              </div>
              <div class="p-meta">
                <?php if ($vc): ?>
                  <span class="p-venue"><?= $vc ?></span>
                <?php endif; ?>
                <?php if (!empty($p['year'])): ?>
                  <span class="p-year-chip"><?= htmlspecialchars($p['year']) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['cited']) && $p['cited'] !== '0'): ?>
                  <span class="p-cite-chip"><?= htmlspecialchars($p['cited']) ?> cited</span>
                <?php endif; ?>
              </div>

              <!-- Metrics injected by JS — placeholder while loading -->
              <?php if ($apiKey && !empty($p['venue_clean'])): ?>
              <div class="m-row" id="metrics-<?= $i ?>"
                data-venue="<?= htmlspecialchars($p['venue_clean']) ?>">
                <span class="chip chip-load">✦ fetching metrics…</span>
              </div>
              <?php endif; ?>

            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </main>

      <!-- ── SIDEBAR ───────────────────────────────────────── -->
      <aside class="sidebar">

        <div class="sb-card">
          <div class="sb-hd">// citation stats</div>
          <div class="sb-body">
            <div class="sb-row">
              <span class="sb-label">Total Citations</span>
              <span class="sb-val"><?= htmlspecialchars($result['citations']) ?></span>
            </div>
            <div class="sb-row">
              <span class="sb-label">h-index</span>
              <span class="sb-val"><?= htmlspecialchars($result['h_index']) ?></span>
            </div>
            <div class="sb-row">
              <span class="sb-label">i10-index</span>
              <span class="sb-val"><?= htmlspecialchars($result['i10_index']) ?></span>
            </div>
            <div class="sb-row">
              <span class="sb-label">Papers indexed</span>
              <span class="sb-val"><?= count($result['papers']) ?></span>
            </div>
            <div class="sb-row">
              <span class="sb-label">Unique journals</span>
              <span class="sb-val"><?= count($result['unique_venues']) ?></span>
            </div>
          </div>
        </div>

        <div class="sb-card">
          <div class="sb-hd">// export</div>
          <div class="sb-body">
            <a class="sb-btn primary" href="export.php?user_id=<?= urlencode($_POST['user_id']) ?>">
              ↓ Download CSV
            </a>
            <div class="sb-note">
              Includes title, venue, year, citations, Impact Factor, SJR, Quartile, CiteScore per paper.
            </div>
          </div>
        </div>

        <div class="sb-card" id="geminiStatusCard" style="<?= $apiKey?'':'display:none' ?>">
          <div class="sb-hd">// gemini metrics status</div>
          <div class="sb-body">
            <div class="gem-badge">Powered by Gemini 2.0 Flash</div>
            <div class="sb-note" id="geminiStatus">
              Looking up journal metrics…<br>
              <span id="geminiProgress">0 / <?= count($result['unique_venues']) ?> venues</span>
            </div>
          </div>
        </div>

        <div class="sb-card">
          <div class="sb-hd">// metric legend</div>
          <div class="sb-body">
            <div class="sb-note" style="line-height:1.9">
              <strong style="color:var(--white)">IF</strong> — Clarivate Impact Factor (live via Gemini+Google)<br>
              <strong style="color:var(--white)">SJR</strong> — SCImago Journal Rank (prestige-weighted)<br>
              <strong style="color:var(--white)">CS</strong> — Scopus CiteScore (4yr avg)<br>
              <strong style="color:var(--white)">Q1–Q4</strong> — Quartile in Scopus subject category<br>
              <strong style="color:var(--white)">h-idx</strong> — Journal h-index<br>
              <br>
              <a href="https://aistudio.google.com/apikey" target="_blank" style="color:#7dd3fc">
                Get free Gemini API key →
              </a>
            </div>
          </div>
        </div>

      </aside>

    </div>
  </div>
  <?php endif; ?>

</div><!-- /.page -->

<footer class="site-footer">
  ScholarLens &nbsp;·&nbsp; Scholar data scraped server-side &nbsp;·&nbsp;
  Metrics via Gemini 2.0 Flash + Google Search Grounding &nbsp;·&nbsp;
  Not affiliated with Google, Clarivate, or Nature Publishing Group
</footer>

<!-- ════════════════════════════════════════════════════════════
     JAVASCRIPT — Live progress + AJAX metric fetching
     ════════════════════════════════════════════════════════════ -->
<script>
// ── Helper ────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const stepState = (id, state, badge) => {
  const el = $(id); if (!el) return;
  el.className = 'step ' + state;
  const b = el.querySelector('.step-badge');
  if (b && badge) b.textContent = badge;
};

// ── Show progress while form is submitting (Scholar fetch) ────
$('searchForm')?.addEventListener('submit', function() {
  const panel = $('progress-panel');
  if (panel) { panel.style.display = 'block'; }
  stepState('step-scholar', 'active', 'connecting…');
  $('searchBtn').textContent = 'Searching…';
  $('searchBtn').disabled = true;
  $('progressTitle').textContent = 'Fetching Scholar profile…';
  $('progressSub').textContent = 'Server-side cURL → scholar.google.com';
});

<?php if ($result && $apiKey): ?>
// ── AJAX: Fetch metrics for each unique venue ─────────────────
(function() {
  // Mark Scholar + parse steps done (server already finished)
  stepState('step-scholar', 'done', 'done');
  stepState('step-parse',   'done', '<?= count($result["papers"]) ?> papers');
  stepState('step-gemini',  'active', 'starting…');
  $('progress-panel').style.display = 'block';
  $('mainSpinner').style.display = 'block';
  $('progressTitle').textContent = 'Fetching journal metrics via Gemini…';
  $('progressSub').textContent = 'Google Search grounding · Impact Factor · SJR · Quartile';

  // All unique venues from PHP
  const venues = <?= json_encode($result['unique_venues']) ?>;
  const total  = venues.length;

  // Map venue → paper indices that use it
  const venuePapers = {};
  document.querySelectorAll('[data-venue]').forEach(el => {
    const v = el.getAttribute('data-venue');
    if (!venuePapers[v]) venuePapers[v] = [];
    venuePapers[v].push(el.id.replace('metrics-',''));
  });

  // Build venue chip UI
  const vp = $('venueProgress');
  vp.style.display = 'block';
  const chips = $('venueChips');
  const chipMap = {};
  venues.forEach(v => {
    const c = document.createElement('span');
    c.className = 'venue-chip';
    c.title = v;
    c.textContent = v.length > 30 ? v.slice(0,28)+'…' : v;
    chips.appendChild(c);
    chipMap[v] = c;
  });

  let done = 0;

  function updateProgress() {
    done++;
    $('venueCount').textContent = done + ' / ' + total;
    $('progFill').style.width = Math.round((done/total)*100) + '%';
    stepState('step-gemini', 'active', done+'/'+total+' venues');
    const gp = $('geminiProgress');
    if (gp) gp.textContent = done + ' / ' + total + ' venues';
    if (done === total) {
      stepState('step-gemini', 'done', total+' venues');
      stepState('step-done',   'done', 'complete ✓');
      $('mainSpinner').style.display = 'none';
      $('progressTitle').textContent = 'All metrics loaded!';
      $('progressSub').textContent = 'Results are ready below ↓';
      const gs = $('geminiStatus');
      if (gs) gs.textContent = total + ' journals enriched via Gemini.';
      // Auto-hide progress after 3s
      setTimeout(() => { $('progress-panel').style.display = 'none'; }, 3000);
    }
  }

  function renderMetrics(paperIdx, m) {
    const el = $('metrics-'+paperIdx);
    if (!el) return;

    if (!m || m._not_found) {
      el.innerHTML = '<span class="chip chip-src">no metrics found</span>';
      return;
    }

    let html = '';

    // Quartile chip
    if (m.quartile) {
      const qcls = {'Q1':'chip-q1','Q2':'chip-q2','Q3':'chip-q3','Q4':'chip-q4'}[m.quartile] || 'chip-src';
      html += `<span class="chip ${qcls}">${m.quartile}</span>`;
    }
    // Impact Factor ← the critical one
    if (m.impact_factor) {
      const yr = m.impact_factor_year ? ` <span style="opacity:.65">(${m.impact_factor_year})</span>` : '';
      html += `<span class="chip chip-if" title="Journal Impact Factor">IF&nbsp;${m.impact_factor}${yr}</span>`;
    }
    if (m.sjr) {
      html += `<span class="chip chip-sjr" title="SCImago Journal Rank">SJR&nbsp;${m.sjr}</span>`;
    }
    if (m.cite_score) {
      html += `<span class="chip chip-cs" title="CiteScore (Scopus)">CS&nbsp;${m.cite_score}</span>`;
    }
    if (m.h_index) {
      html += `<span class="chip chip-h" title="Journal h-index">h&nbsp;${m.h_index}</span>`;
    }
    if (m.source) {
      html += `<span class="chip chip-src">via ${m.source}</span>`;
    }
    html += `<span class="chip chip-gem">✦&nbsp;Gemini</span>`;

    // Grounding sources
    const sources = m.grounding_sources || [];
    if (sources.length) {
      const uid = 'src-'+paperIdx;
      html += `<br><button class="p-sources-btn" onclick="toggleSrc('${uid}')">
        ↳ ${sources.length} source${sources.length>1?'s':''} searched
      </button>
      <div class="p-sources" id="${uid}">
        ${sources.map(s => `<a href="${s.uri}" target="_blank" rel="noopener">${s.title}</a>`).join('<br>')}
        ${m.web_search_queries?.length ? '<br><em style="color:var(--smoke)">Queries: '+m.web_search_queries.join(', ')+'</em>' : ''}
      </div>`;
    }

    el.innerHTML = html;
  }

  // Fetch one venue at a time (sequential to respect 15 req/min)
  async function fetchVenue(venue, idx) {
    const chip = chipMap[venue];
    if (chip) chip.className = 'venue-chip loading';
    try {
      const r = await fetch('scholar.php?ajax_venue=' + encodeURIComponent(venue));
      const m = await r.json();

      const hasData = m && !m._not_found &&
        (m.impact_factor || m.sjr || m.quartile || m.cite_score);

      if (chip) chip.className = 'venue-chip ' + (hasData ? 'done' : 'no-data');

      // Apply to all papers sharing this venue
      const paperIds = venuePapers[venue] || [];
      paperIds.forEach(pid => renderMetrics(pid, m));
    } catch(e) {
      if (chip) chip.className = 'venue-chip no-data';
      (venuePapers[venue]||[]).forEach(pid => {
        const el = $('metrics-'+pid);
        if (el) el.innerHTML = '<span class="chip chip-src">lookup failed</span>';
      });
    }
    updateProgress();
  }

  // Run sequentially (not parallel) to avoid Gemini rate limits
  (async function run() {
    for (let i = 0; i < venues.length; i++) {
      await fetchVenue(venues[i], i);
      if (i < venues.length-1) await new Promise(r=>setTimeout(r, 400)); // 400ms gap
    }
  })();
})();
<?php elseif($result && !$apiKey): ?>
// API key not set — hide progress, show static
$('progress-panel').style.display = 'none';
<?php endif; ?>

function toggleSrc(id) {
  const el = document.getElementById(id);
  if (el) el.classList.toggle('open');
}
</script>

</body>
</html>