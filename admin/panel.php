<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$admin = require_admin_auth();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Panel — <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?= time() ?>">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
</head>
<body>

<div class="app-layout">

  <!-- ══ SIDEBAR ════════════════════════════════════════════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="../assets/img/logo.png" alt="Logo">
      <span class="logo-text">A D M I N</span>
    </div>

    <nav class="sidebar-nav">
      <button class="nav-btn active" data-page="users">
        <!-- Users icon -->
        <svg class="nav-icon" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        Users
      </button>

      <button class="nav-btn" data-page="analytics">
        <!-- Analytics icon -->
        <svg class="nav-icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H7v-2h5v2zm5-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        Analytics
      </button>

      <button class="nav-btn" data-page="feedback">
        <!-- Feedback icon -->
        <svg class="nav-icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
        Feedback
      </button>
    </nav>

    <div class="sidebar-bottom">
      <a href="logout.php" class="btn-logout">
        <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
        Log out
      </a>
    </div>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ══ MAIN AREA ══════════════════════════════════════════ -->
  <div class="main-area">

    <!-- Dynamic header (title changes per page) -->
    <header class="page-header" id="pageHeader" style="justify-content: space-between;">
      <div style="display:flex; align-items:center;">
        <button class="btn-mobile-menu" id="menuBtn">
          <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
        </button>
        <span id="pageTitle">Users</span>
      </div>
      <div style="display:flex; align-items:center; gap:8px;">
        <button class="btn-refresh" id="exportPagePdfBtn" title="Extract Page to PDF" style="display:none;">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>
        </button>
        <button class="btn-refresh" id="refreshBtn" title="Refresh Panel">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
        </button>
      </div>
    </header>

    <!-- ══ USERS PAGE ══════════════════════════════════════ -->
    <section class="page-content active" id="pageUsers">
      <div class="card">
        <div class="card-header">
          <span class="card-title">List</span>
          <!-- Sort button (top-right, matches PDF) -->
          <div style="display:flex; gap:10px; align-items:center;">
            <div style="position:relative">
              <button class="btn-sort" id="sortBtn">
              Sort
              <svg viewBox="0 0 24 24"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
            </button>
            <div class="sort-dropdown" id="sortDropdown">
              <div class="sort-option active" data-sort="date_desc">Date added</div>
              <div class="sort-option" data-sort="alpha_asc">Alphabetical</div>
              <div class="sort-option" data-sort="online_first">Online/Offline</div>
            </div>
          </div>
        </div>
        </div>

        <!-- Search + Export toolbar -->
        <div class="table-toolbar">
          <div class="search-wrapper">
            <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <input type="search" class="search-input" id="userSearch" placeholder="Search users…">
          </div>
          <button class="btn-export" id="exportPdf">
            <svg viewBox="0 0 24 24"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>
            PDF
          </button>
          <button class="btn-export" id="exportXls">
            <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zm-1 8l-2-3-2 3H6l3-4.5L6 11h2l2 3 2-3h2l-3 4.5L14 20h-2z"/></svg>
            Excel
          </button>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width:54px">No.</th>
                <th>Username</th>
                <th>Email</th>
                <th>Date Joined</th>
                <th>Gender</th>
                <th>Status</th>
                <th style="width:100px">Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">
              <tr><td colspan="7"><div class="table-empty"><span class="spinner-sm spinner-blue"></span> Loading users…</div></td></tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="usersPagination" style="display:none">
          <span id="paginInfo">Showing 0 users</span>
          <div class="page-btns" id="paginBtns"></div>
        </div>
      </div>
    </section>

    <!-- ══ ANALYTICS PAGE ════════════════════════════════ -->
    <section class="page-content" id="pageAnalytics">

      <!-- ── Row 1 from PDF: Gender | Age | KPI stack ──── -->
      <div class="analytics-grid-top">
        <!-- Gender donut (PDF: left card) -->
        <div class="chart-card">
          <div class="chart-card-title">Gender</div>
          <div style="position:relative;height:210px;display:flex;align-items:center;justify-content:center">
            <canvas id="chartGender"></canvas>
          </div>
          <div class="chart-legend" id="genderLegend"></div>
        </div>

        <!-- Age donut (PDF: middle card) -->
        <div class="chart-card">
          <div class="chart-card-title">Age</div>
          <div style="position:relative;height:210px;display:flex;align-items:center;justify-content:center">
            <canvas id="chartAge"></canvas>
          </div>
          <div class="chart-legend" id="ageLegend"></div>
        </div>

        <!-- KPI stack (PDF: right column) -->
        <div class="analytics-kpi-stack">
          <div class="kpi-card">
            <div class="kpi-label">Total number of user</div>
            <div class="kpi-value" id="kpiTotalUsers">—</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Ratings</div>
            <div class="kpi-value" id="kpiAvgRating">—</div>
          </div>
        </div>
      </div>

      <!-- ── OLTP: Real-time dashboard ─────────────────── -->
      <div>
        <div class="section-title">
          <svg viewBox="0 0 24 24"><path d="M13 2.05v2.02c3.95.49 7 3.85 7 7.93 0 3.21-1.81 6-4.72 7.28L13 17v5l5-3-1.22-1.22C19.91 15.8 22 12.32 22 12c0-5.18-3.95-9.45-9-9.95zM11 2.05C5.95 2.55 2 6.82 2 12c0 3.32 2.09 6.8 5.22 8.78L6 22l5 3v-5l-2.28 2.28C6.81 21 5 18.21 5 15c0-4.08 3.05-7.44 7-7.93V2.05z"/></svg>
          OLTP — Live Monitoring
        </div>
        <div class="stats-bar" id="oltpStats">
          <div class="kpi-card"><div class="kpi-label">Online Now</div><div class="kpi-value text-green" id="oOnline">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Active Today</div><div class="kpi-value text-blue" id="oActive">—</div></div>
          <div class="kpi-card"><div class="kpi-label">New Today</div><div class="kpi-value" id="oNew">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Total Lessons</div><div class="kpi-value" id="oLessons">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Total Quizzes</div><div class="kpi-value" id="oQuizzes">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Avg Quiz Score</div><div class="kpi-value text-blue" id="oAvgScore">—</div></div>
          <div class="kpi-card"><div class="kpi-label">AI Requests</div><div class="kpi-value" id="oAiReq">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Cached Topics</div><div class="kpi-value" id="oCached">—</div></div>
        </div>
      </div>

      <!-- ── OLAP: Growth charts ────────────────────────── -->
      <div>
        <div class="section-title">
          <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
          OLAP — User Growth Analysis
        </div>
        <div class="grid-2">
          <div class="chart-card">
            <div class="chart-card-title">Daily Growth (Last 7 Days)</div>
            <canvas id="chartGrowth7" height="160"></canvas>
          </div>
          <div class="chart-card">
            <div class="chart-card-title">Monthly Growth (Last 6 Months)</div>
            <canvas id="chartGrowthMonth" height="160"></canvas>
          </div>
        </div>
      </div>

      <!-- ── OLAP: Behavior + Engagement ───────────────── -->
      <div>
        <div class="section-title">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
          OLAP — Behavior &amp; Engagement
        </div>
        <div class="grid-2">
          <div class="chart-card">
            <div class="chart-card-title">Most Active Hours (Last 24h)</div>
            <canvas id="chartHours" height="160"></canvas>
          </div>
          <div class="chart-card">
            <div class="chart-card-title">Quiz Score Distribution</div>
            <div style="height:160px;display:flex;align-items:center;justify-content:center">
              <canvas id="chartQuizScores"></canvas>
            </div>
            <div class="chart-legend" id="quizScoreLegend"></div>
          </div>
        </div>
      </div>

      <!-- ── OLAP: Comparative ──────────────────────────── -->
      <div>
        <div class="section-title">
          <svg viewBox="0 0 24 24"><path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2 2H5V5h14v14zm0-16H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
          OLAP — Comparative Analytics
        </div>
        <div class="grid-3">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#e8f0ff"><svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#2058dc"><path d="M7 14l5-5 5 5z"/></svg></div>
            <div class="kpi-label">This Week vs Last Week</div>
            <div class="kpi-value" id="compWeek">—</div>
            <div class="kpi-sub" id="compWeekSub">Loading…</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#e6f4ee"><svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#007a3f"><path d="M7 14l5-5 5 5z"/></svg></div>
            <div class="kpi-label">This Month vs Last Month</div>
            <div class="kpi-value" id="compMonth">—</div>
            <div class="kpi-sub" id="compMonthSub">Loading…</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fff7e6"><svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#c07400"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
            <div class="kpi-label">Avg Rating Trend</div>
            <div class="kpi-value" id="compRating">—</div>
            <div class="kpi-sub" id="compRatingSub">Loading…</div>
          </div>
        </div>
      </div>

      <!-- ── Predictive Insights ────────────────────────── -->
      <div>
        <div class="section-title">
          <svg viewBox="0 0 24 24"><path d="M19.35 10.04A7.49 7.49 0 0012 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 000 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
          Predictive Insights
        </div>
        <div class="grid-3">
          <div class="kpi-card">
            <div class="kpi-label">Est. Users Next 7 Days</div>
            <div class="kpi-value text-blue" id="predUsers">—</div>
            <div class="kpi-sub">Based on recent growth rate</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Expected AI Requests Today</div>
            <div class="kpi-value" id="predRequests">—</div>
            <div class="kpi-sub">Based on hourly activity</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Engagement Forecast</div>
            <div class="kpi-value text-green" id="predEngagement">—</div>
            <div class="kpi-sub">Lessons + quizzes expected</div>
          </div>
        </div>
      </div>

      <!-- ── ETL Monitoring ─────────────────────────────── -->
      <div>
        <div class="section-title">
          <svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg>
          ETL Pipeline Monitoring
        </div>

        <!-- ETL summary cards -->
        <div class="grid-4" id="etlSummaryCards" style="margin-bottom:16px">
          <div class="kpi-card"><div class="kpi-label">Total Runs</div><div class="kpi-value" id="etlTotalRuns">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Success Rate</div><div class="kpi-value text-green" id="etlSuccessRate">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Records Loaded</div><div class="kpi-value text-blue" id="etlLoaded">—</div></div>
          <div class="kpi-card"><div class="kpi-label">Avg Duration</div><div class="kpi-value" id="etlAvgDuration">—</div></div>
        </div>

        <!-- ETL logs table -->
        <div class="card">
          <div class="card-header"><span class="card-title">Pipeline Logs</span></div>
          <div style="overflow-x:auto;padding:0 0 8px">
            <table class="etl-log-table">
              <thead>
                <tr>
                  <th>Pipeline</th>
                  <th>Status</th>
                  <th>Extracted</th>
                  <th>Loaded</th>
                  <th>Failed</th>
                  <th>Duration</th>
                  <th>Volume</th>
                  <th>Last Run</th>
                </tr>
              </thead>
              <tbody id="etlTableBody">
                <tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray)"><span class="spinner-sm spinner-blue"></span></td></tr>
              </tbody>
            </table>
          </div>

          <!-- Data Sources -->
          <div style="padding:16px 20px;border-top:1px solid #f0f0f0">
            <div style="font-size:0.78rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Data Sources</div>
            <div class="grid-3">
              <div>
                <div style="font-size:0.8rem;font-weight:600;color:var(--navy);margin-bottom:4px">User Database</div>
                <div class="health-bar"><div class="health-bar-fill" id="hbUsers" style="background:var(--green);width:0%"></div></div>
                <div style="font-size:0.72rem;color:var(--gray);margin-top:2px" id="hbUsersLbl">Loading…</div>
              </div>
              <div>
                <div style="font-size:0.8rem;font-weight:600;color:var(--navy);margin-bottom:4px">Feedback Database</div>
                <div class="health-bar"><div class="health-bar-fill" id="hbFeedback" style="background:var(--blue);width:0%"></div></div>
                <div style="font-size:0.72rem;color:var(--gray);margin-top:2px" id="hbFeedbackLbl">Loading…</div>
              </div>
              <div>
                <div style="font-size:0.8rem;font-weight:600;color:var(--navy);margin-bottom:4px">Analytics Database</div>
                <div class="health-bar"><div class="health-bar-fill" id="hbAnalytics" style="background:#c07400;width:0%"></div></div>
                <div style="font-size:0.72rem;color:var(--gray);margin-top:2px" id="hbAnalyticsLbl">Loading…</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FEEDBACK PAGE ════════════════════════════════ -->
    <section class="page-content" id="pageFeedback">

      <!-- Stats bar -->
      <div class="feedback-stats-bar card" id="fbStatsBar">
        <div class="fb-stat"><div class="fb-stat-val" id="fbTotal">—</div><div class="fb-stat-lbl">Total</div></div>
        <div class="fb-stat"><div class="fb-stat-val text-green" id="fbPositive">—</div><div class="fb-stat-lbl">Positive</div></div>
        <div class="fb-stat"><div class="fb-stat-val text-red" id="fbNegative">—</div><div class="fb-stat-lbl">Negative</div></div>
        <div class="fb-stat"><div class="fb-stat-val" id="fbAvgRating">—</div><div class="fb-stat-lbl">Avg Rating</div></div>
      </div>

      <div class="card">
        <!-- Filters -->
        <div class="feedback-filters">
          <div class="search-wrapper" style="max-width:260px;">
            <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <input type="search" class="search-input" id="fbSearch" placeholder="Search feedback…">
          </div>
          <select class="filter-select" id="fbRatingFilter">
            <option value="all">All ratings</option>
            <option value="5">⭐⭐⭐⭐⭐ 5 stars</option>
            <option value="4">⭐⭐⭐⭐ 4 stars</option>
            <option value="3">⭐⭐⭐ 3 stars</option>
            <option value="2">⭐⭐ 2 stars</option>
            <option value="1">⭐ 1 star</option>
            <option value="none">No rating</option>
          </select>
          <button class="btn-export" id="exportFbPdf" style="margin-left:auto;">
            <svg viewBox="0 0 24 24"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>
            PDF
          </button>
        </div>

        <!-- Feedback list -->
        <div id="feedbackList">
          <div class="table-empty"><span class="spinner-sm spinner-blue"></span> Loading feedback…</div>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="fbPagination" style="display:none">
          <span id="fbPaginInfo">0 items</span>
          <div class="page-btns" id="fbPaginBtns"></div>
        </div>
      </div>
    </section>

  </div><!-- .main-area -->
</div><!-- .app-layout -->

<!-- ══ DELETE MODAL ═══════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-card">
    <div class="modal-warning-title">Warning</div>
    <div class="modal-body">
      Do you want to delete user's account permanently?
      <div class="modal-user-info" id="modalUserInfo"></div>
    </div>
    <div class="modal-actions">
      <button class="btn-modal-cancel" id="cancelDeleteBtn">Cancel</button>
      <button class="btn-modal-delete" id="confirmDeleteBtn" disabled>(5s) DELETE</button>
    </div>
  </div>
</div>

<script>
/* ════════════════════════════════════════════════════════════
   ADMIN PANEL — Main JavaScript
   ════════════════════════════════════════════════════════════ */
const CSRF = <?= json_encode(admin_csrf_token()) ?>;

// ── API helper ──────────────────────────────────────────────
async function api(action, params = {}, method = 'GET') {
  let url = `api.php?action=${action}`;
  const opts = { method, headers: { 'X-Admin-CSRF': CSRF } };

  if (method === 'GET') {
    Object.entries(params).forEach(([k,v]) => url += `&${k}=${encodeURIComponent(v)}`);
  } else {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.entries(params).forEach(([k,v]) => fd.append(k, v));
    opts.body = fd;
  }

  const r = await fetch(url, opts);
  if (r.status === 401 || r.status === 419) {
    window.location.href = 'index.php';
    throw new Error('Session expired');
  }
  return r.json();
}

function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d);
  return dt.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
}

// ── Navigation ──────────────────────────────────────────────
const pages = {
  users:     { section: document.getElementById('pageUsers'),    title: 'Users' },
  analytics: { section: document.getElementById('pageAnalytics'), title: 'Analytics' },
  feedback:  { section: document.getElementById('pageFeedback'), title: 'Feedback' },
};

let currentPage = 'users';

function showPage(name) {
  currentPage = name;
  document.getElementById('pageTitle').textContent = pages[name].title;

  Object.entries(pages).forEach(([k, p]) => {
    p.section.classList.toggle('active', k === name);
  });
  document.querySelectorAll('.nav-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.page === name);
  });

  document.getElementById('exportPagePdfBtn').style.display = (name === 'analytics') ? 'block' : 'none';

  if (name === 'users')     loadUsers();
  if (name === 'analytics') loadAnalytics();
  if (name === 'feedback')  { loadFeedback(); startFeedbackRefresh(); }
}

document.querySelectorAll('.nav-btn').forEach(btn => {
  btn.addEventListener('click', () => showPage(btn.dataset.page));
});

// Refresh button functionality
document.getElementById('refreshBtn').addEventListener('click', async () => {
  const btn = document.getElementById('refreshBtn');
  btn.classList.add('spinning');
  
  try {
    if (currentPage === 'users') await loadUsers();
    else if (currentPage === 'analytics') await refreshAnalytics();
    else if (currentPage === 'feedback') await loadFeedback(true);
  } catch (e) {
    console.error('Refresh failed', e);
  } finally {
    setTimeout(() => btn.classList.remove('spinning'), 400);
  }
});

document.querySelectorAll('.panel-refresh-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    btn.classList.add('spinning');
    try {
      if (currentPage === 'users') await loadUsers();
      else if (currentPage === 'analytics') await refreshAnalytics();
      else if (currentPage === 'feedback') await loadFeedback(true);
    } catch (e) {
      console.error('Refresh failed', e);
    } finally {
      setTimeout(() => btn.classList.remove('spinning'), 400);
    }
  });
});

// Mobile menu
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebarOverlay');
const menuBtn  = document.getElementById('menuBtn');
menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('open'); });
overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); });

// Export Page to PDF
document.getElementById('exportPagePdfBtn').addEventListener('click', async () => {
  const btn = document.getElementById('exportPagePdfBtn');
  btn.classList.add('spinning'); // Re-use the refresh spinning animation temporarily
  
  const activePage = document.querySelector('.page-content.active');
  if (!activePage) {
    btn.classList.remove('spinning');
    return;
  }

  try {
    const canvas = await html2canvas(activePage, {
      scale: 2,
      useCORS: true,
      backgroundColor: '#f3f4f8',
      windowHeight: activePage.scrollHeight
    });
    
    const imgData = canvas.toDataURL('image/png');
    const { jsPDF } = window.jspdf;
    
    // A4 dimensions
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pdfWidth = pdf.internal.pageSize.getWidth();
    let pdfHeight = (canvas.height * pdfWidth) / canvas.width;
    
    pdf.setFontSize(16);
    pdf.text(`Admin Panel - ${currentPage.charAt(0).toUpperCase() + currentPage.slice(1)}`, 14, 15);
    
    pdf.addImage(imgData, 'PNG', 0, 20, pdfWidth, pdfHeight);
    
    // Multi-page logic if content is taller than A4 page (approx 297mm)
    let heightLeft = pdfHeight - (297 - 20); // 297mm A4 height minus 20mm margin
    let position = 20 - 297; 

    while (heightLeft > 0) {
      pdf.addPage();
      pdf.addImage(imgData, 'PNG', 0, position, pdfWidth, pdfHeight);
      heightLeft -= 297;
      position -= 297;
    }
    
    pdf.save(`${currentPage}_dashboard_${new Date().toISOString().slice(0,10)}.pdf`);
  } catch (err) {
    alert("Failed to export PDF: " + err.message);
    console.error(err);
  } finally {
    btn.classList.remove('spinning');
  }
});

// ══════════════════════════════════════════════════════════
// USERS
// ══════════════════════════════════════════════════════════
let usersState = { sort: 'date_desc', search: '', page: 1 };
let deleteUserId = null;
let deleteCountdown = null;

async function loadUsers() {
  const tbody = document.getElementById('usersTableBody');
  tbody.innerHTML = `<tr><td colspan="7"><div class="table-empty"><span class="spinner-sm spinner-blue"></span> Loading…</div></td></tr>`;

  const data = await api('users', {
    sort: usersState.sort,
    search: usersState.search,
    page: usersState.page,
  });

  if (!data.ok) {
    tbody.innerHTML = `<tr><td colspan="7" class="table-empty" style="color:var(--red)">Failed to load users.</td></tr>`;
    return;
  }

  if (!data.users.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="table-empty">No users found.</td></tr>`;
    document.getElementById('usersPagination').style.display = 'none';
    return;
  }

  const offset = (data.page - 1) * 20;
  tbody.innerHTML = data.users.map((u, i) => {
    const status   = +u.is_suspended ? 'suspended' : (+u.is_online ? 'online' : 'offline');
    const statusLbl = +u.is_suspended ? 'Suspended' : (+u.is_online ? 'Online' : 'Offline');
    return `<tr>
      <td>${offset + i + 1}</td>
      <td><strong>${esc(u.fullname)}</strong></td>
      <td style="color:var(--gray)">${esc(u.email)}</td>
      <td>${fmtDate(u.created_at)}</td>
      <td>${esc(u.gender)}</td>
      <td><span class="status-badge ${status}">${statusLbl}</span></td>
      <td>
        <div class="table-actions">
          <button class="btn-table-action btn-susp" title="${+u.is_suspended ? 'Activate' : 'Suspend'}" onclick="toggleSuspend(${u.id}, '${esc(u.fullname)}', ${+u.is_suspended})">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="${+u.is_suspended ? 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z' : 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm3.5-9H13V7h-2v4H8.5L12 14.5 15.5 11z'}"/></svg>
          </button>
          <button class="btn-table-action btn-del" title="Delete user" onclick="askDelete(${u.id}, '${esc(u.fullname)}', '${esc(u.email)}')">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
          </button>
        </div>
      </td>
    </tr>`;
  }).join('');

  // Pagination
  const pag = document.getElementById('usersPagination');
  const info = document.getElementById('paginInfo');
  const btns = document.getElementById('paginBtns');
  pag.style.display = 'flex';
  info.textContent = `Showing ${data.users.length} of ${data.total} users`;

  btns.innerHTML = '';
  const prevBtn = makePaginBtn('‹', data.page <= 1, () => { usersState.page--; loadUsers(); });
  btns.appendChild(prevBtn);
  for (let p = 1; p <= Math.min(data.pages, 7); p++) {
    const b = makePaginBtn(p, false, () => { usersState.page = p; loadUsers(); });
    if (p === data.page) b.classList.add('active');
    btns.appendChild(b);
  }
  const nextBtn = makePaginBtn('›', data.page >= data.pages, () => { usersState.page++; loadUsers(); });
  btns.appendChild(nextBtn);
}

function makePaginBtn(label, disabled, cb) {
  const b = document.createElement('button');
  b.className = 'page-btn';
  b.textContent = label;
  b.disabled = !!disabled;
  b.addEventListener('click', cb);
  return b;
}

// Search
let searchTimer;
document.getElementById('userSearch').addEventListener('input', e => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    usersState.search = e.target.value.trim();
    usersState.page   = 1;
    loadUsers();
  }, 350);
});

// Sort dropdown
const sortBtn      = document.getElementById('sortBtn');
const sortDropdown = document.getElementById('sortDropdown');
sortBtn.addEventListener('click', e => { e.stopPropagation(); sortDropdown.classList.toggle('open'); });
document.addEventListener('click', () => sortDropdown.classList.remove('open'));

sortDropdown.querySelectorAll('.sort-option').forEach(opt => {
  opt.addEventListener('click', () => {
    usersState.sort = opt.dataset.sort;
    usersState.page = 1;
    sortDropdown.querySelectorAll('.sort-option').forEach(o => o.classList.remove('active'));
    opt.classList.add('active');
    sortBtn.firstChild.textContent = 'Sort';
    sortDropdown.classList.remove('open');
    loadUsers();
  });
});

// Suspend/Activate
async function toggleSuspend(id, name, currentlySuspended) {
  const action = currentlySuspended ? 'Activate' : 'Suspend';
  if (!confirm(`${action} ${name}?`)) return;
  const data = await api('toggle_suspend', { id }, 'POST');
  if (data.ok) loadUsers();
}

// Delete modal (with countdown)
function askDelete(id, name, email) {
  deleteUserId = id;
  document.getElementById('modalUserInfo').innerHTML =
    `<strong>${esc(name)}</strong><br><span style="color:var(--gray)">${esc(email)}</span>`;

  const confirmBtn = document.getElementById('confirmDeleteBtn');
  confirmBtn.disabled = true;
  let secs = 5;
  confirmBtn.textContent = `(${secs}s) DELETE`;
  document.getElementById('deleteModal').classList.add('open');

  clearInterval(deleteCountdown);
  deleteCountdown = setInterval(() => {
    secs--;
    if (secs <= 0) {
      clearInterval(deleteCountdown);
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'DELETE';
    } else {
      confirmBtn.textContent = `(${secs}s) DELETE`;
    }
  }, 1000);
}

document.getElementById('cancelDeleteBtn').addEventListener('click', () => {
  clearInterval(deleteCountdown);
  document.getElementById('deleteModal').classList.remove('open');
  deleteUserId = null;
});

document.getElementById('confirmDeleteBtn').addEventListener('click', async () => {
  if (!deleteUserId) return;
  const data = await api('delete_user', { id: deleteUserId }, 'POST');
  document.getElementById('deleteModal').classList.remove('open');
  deleteUserId = null;
  if (data.ok) loadUsers();
  else alert(data.message || 'Delete failed.');
});

// Export PDF
document.getElementById('exportPdf').addEventListener('click', () => exportData('pdf'));
document.getElementById('exportXls').addEventListener('click', () => exportData('xls'));

function exportData(type) {
  // Fetch all users and build export
  api('users', { sort: usersState.sort, search: usersState.search, page: 1, limit: 9999 }).then(data => {
    if (!data.ok) return;
    const rows = [];
    data.users.forEach((u, i) => {
      const status = +u.is_suspended ? 'Suspended' : (+u.is_online ? 'Online' : 'Offline');
      rows.push([i+1, u.fullname, u.email, fmtDate(u.created_at), u.gender, status]);
    });

    if (type === 'pdf') {
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      doc.text("Users Export", 14, 15);
      doc.autoTable({
        head: [['No.', 'Username', 'Email', 'Date Joined', 'Gender', 'Status']],
        body: rows,
        startY: 20,
      });
      doc.save(`users_export_${new Date().toISOString().slice(0,10)}.pdf`);
    } else {
      const csvRows = [['No.','Username','Email','Date Joined','Gender','Status'], ...rows];
      const csv = csvRows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const a    = document.createElement('a');
      a.href     = URL.createObjectURL(blob);
      a.download = `users_export_${new Date().toISOString().slice(0,10)}.csv`;
      a.click();
    }
  });
}

// ══════════════════════════════════════════════════════════
// ANALYTICS
// ══════════════════════════════════════════════════════════
let analyticsLoaded = false;
let chartInstances  = {};
let analyticsTimer  = null;

function destroyChart(id) {
  if (chartInstances[id]) { chartInstances[id].destroy(); delete chartInstances[id]; }
}

async function loadAnalytics() {
  if (analyticsLoaded) return;
  analyticsLoaded = true;

  await refreshAnalytics();

  if (!analyticsTimer) {
    analyticsTimer = setInterval(() => {
      if (currentPage === 'analytics') refreshAnalytics();
    }, 10000);
  }
}

async function refreshAnalytics() {
  const [oltp, charts, etl] = await Promise.all([
    api('analytics_oltp'),
    api('analytics_charts'),
    api('etl_stats'),
  ]);

  if (oltp.ok) renderOLTP(oltp);
  if (charts.ok) renderCharts(charts);
  if (etl.ok) renderETL(etl);
}

function renderOLTP(d) {
  document.getElementById('kpiTotalUsers').textContent  = d.total_users;
  document.getElementById('kpiAvgRating').textContent   = d.avg_rating.toFixed(1);
  document.getElementById('oOnline').textContent   = d.online_users;
  document.getElementById('oActive').textContent   = d.active_today;
  document.getElementById('oNew').textContent      = d.new_today;
  document.getElementById('oLessons').textContent  = d.total_lessons;
  document.getElementById('oQuizzes').textContent  = d.total_quizzes;
  document.getElementById('oAvgScore').textContent = d.avg_quiz_score + '%';
  document.getElementById('oAiReq').textContent    = d.ai_requests || 0;
  document.getElementById('oCached').textContent   = d.cached_topics;

  // Predictive
  const dailyRate = d.new_today || 1;
  document.getElementById('predUsers').textContent      = '+' + (dailyRate * 7);
  document.getElementById('predRequests').textContent   = d.ai_requests ? Math.ceil(d.ai_requests * 1.1) : '—';
  document.getElementById('predEngagement').textContent = Math.ceil((d.total_lessons + d.total_quizzes) * 0.1);
}

function renderCharts(d) {
  // ── Gender donut ────────────────────────────────────────
  destroyChart('gender');
  const gData   = [d.gender.Male, d.gender.Female, d.gender.Undefined];
  const gColors = ['#2058dc', '#e91e8c', '#fd9a3f'];
  const gLabels = ['Male', 'Female', 'Undefined'];
  const gTotal  = d.gender_total || 1;

  chartInstances.gender = new Chart(document.getElementById('chartGender'), {
    type: 'doughnut',
    data: { labels: gLabels, datasets: [{ data: gData, backgroundColor: gColors, borderWidth: 0, hoverOffset: 6 }] },
    options: {
      cutout: '62%', plugins: { legend: { display: false }, tooltip: {
        callbacks: { label: ctx => ` ${ctx.label}: ${((ctx.raw/gTotal)*100).toFixed(1)}%` }
      }},
      animation: { duration: 600 },
    }
  });

  document.getElementById('genderLegend').innerHTML = gLabels.map((l,i) =>
    `<div class="legend-item"><div class="legend-dot" style="background:${gColors[i]}"></div>${l} <strong>${((gData[i]/gTotal)*100).toFixed(1)}%</strong></div>`
  ).join('');

  // ── Age donut ───────────────────────────────────────────
  destroyChart('age');
  const aData   = [d.age['18_below'], d.age['19_above']];
  const aColors = ['#007a3f', '#ff3131'];
  const aLabels = ['Age 18 and below', 'Age 19 and above'];
  const aTotal  = aData.reduce((a,b) => a+b, 0) || 1;

  chartInstances.age = new Chart(document.getElementById('chartAge'), {
    type: 'doughnut',
    data: { labels: aLabels, datasets: [{ data: aData, backgroundColor: aColors, borderWidth: 0, hoverOffset: 6 }] },
    options: {
      cutout: '62%', plugins: { legend: { display: false }, tooltip: {
        callbacks: { label: ctx => ` ${ctx.label}: ${((ctx.raw/aTotal)*100).toFixed(1)}%` }
      }},
      animation: { duration: 600 },
    }
  });

  document.getElementById('ageLegend').innerHTML = aLabels.map((l,i) =>
    `<div class="legend-item"><div class="legend-dot" style="background:${aColors[i]}"></div>${l} <strong>${((aData[i]/aTotal)*100).toFixed(1)}%</strong></div>`
  ).join('');

  // ── Growth 7-day line ───────────────────────────────────
  destroyChart('growth7');
  chartInstances.growth7 = new Chart(document.getElementById('chartGrowth7'), {
    type: 'line',
    data: {
      labels: d.growth_7.labels,
      datasets: [{ label: 'New Users', data: d.growth_7.data, borderColor: '#2058dc', backgroundColor: 'rgba(32,88,220,.1)', fill: true, tension: .4, pointRadius: 4, pointHoverRadius: 6 }]
    },
    options: { plugins:{ legend:{display:false} }, scales: { y:{ beginAtZero:true, ticks:{stepSize:1} }, x:{ grid:{display:false} } } }
  });

  // ── Monthly growth bar ──────────────────────────────────
  destroyChart('growthMonth');
  chartInstances.growthMonth = new Chart(document.getElementById('chartGrowthMonth'), {
    type: 'bar',
    data: {
      labels: d.growth_month.labels,
      datasets: [{ label: 'New Users', data: d.growth_month.data, backgroundColor: '#2058dc', borderRadius: 6, borderSkipped: false }]
    },
    options: { plugins:{ legend:{display:false} }, scales: { y:{ beginAtZero:true, ticks:{stepSize:1} }, x:{ grid:{display:false} } } }
  });

  // ── Active hours bar ────────────────────────────────────
  destroyChart('hours');
  chartInstances.hours = new Chart(document.getElementById('chartHours'), {
    type: 'bar',
    data: {
      labels: d.active_hours.labels.filter((_,i) => i % 2 === 0),
      datasets: [{ data: d.active_hours.data.filter((_,i) => i % 2 === 0), backgroundColor: 'rgba(32,88,220,.7)', borderRadius: 4 }]
    },
    options: { plugins:{ legend:{display:false} }, scales: { y:{ beginAtZero:true }, x:{ grid:{display:false} } } }
  });

  // ── Quiz score doughnut ─────────────────────────────────
  destroyChart('quizScores');
  const qs = d.quiz_scores;
  const qsData   = [qs.excellent||0, qs.good||0, qs.fair||0, qs.poor||0];
  const qsColors = ['#007a3f','#2058dc','#f59e0b','#ff3131'];
  const qsLabels = ['Excellent (90%+)', 'Good (70-89%)', 'Fair (31-69%)', 'Poor (<31%)'];
  const qsTotal  = qsData.reduce((a,b)=>a+b,0)||1;

  chartInstances.quizScores = new Chart(document.getElementById('chartQuizScores'), {
    type: 'doughnut',
    data: { labels: qsLabels, datasets: [{ data: qsData, backgroundColor: qsColors, borderWidth: 0, hoverOffset: 4 }] },
    options: { cutout:'60%', plugins:{ legend:{display:false} }, animation:{ duration:600 } }
  });
  document.getElementById('quizScoreLegend').innerHTML = qsLabels.map((l,i) =>
    `<div class="legend-item"><div class="legend-dot" style="background:${qsColors[i]}"></div>${l}</div>`
  ).join('');

  // ── Comparative analytics ───────────────────────────────
  const g7 = d.growth_7.data;
  const len = g7.length;
  const thisWeek  = g7.slice(-3).reduce((a,b)=>a+b,0);
  const lastWeek  = g7.slice(0,3).reduce((a,b)=>a+b,0);
  const diff      = thisWeek - lastWeek;
  const pctChange = lastWeek ? Math.round((diff/lastWeek)*100) : 0;
  document.getElementById('compWeek').textContent    = (diff >= 0 ? '+' : '') + diff + ' users';
  document.getElementById('compWeekSub').textContent = (pctChange >= 0 ? '▲' : '▼') + ' ' + Math.abs(pctChange) + '% vs last week';
  document.getElementById('compWeek').className      = 'kpi-value ' + (diff >= 0 ? 'text-green' : 'text-red');

  const gm = d.growth_month.data;
  const thisMonth = gm[gm.length-1] || 0;
  const lastMonth = gm[gm.length-2] || 0;
  const mDiff     = thisMonth - lastMonth;
  const mPct      = lastMonth ? Math.round((mDiff/lastMonth)*100) : 0;
  document.getElementById('compMonth').textContent    = (mDiff >= 0 ? '+' : '') + mDiff + ' users';
  document.getElementById('compMonthSub').textContent = (mPct >= 0 ? '▲' : '▼') + ' ' + Math.abs(mPct) + '% vs last month';
  document.getElementById('compMonth').className      = 'kpi-value ' + (mDiff >= 0 ? 'text-green' : 'text-red');

  document.getElementById('compRating').textContent    = document.getElementById('kpiAvgRating').textContent;
  document.getElementById('compRatingSub').textContent = 'Based on all feedback ratings';
}

function renderETL(d) {
  const s = d.summary;
  const successRate = s.total_runs ? Math.round((s.completed/s.total_runs)*100) : 0;
  document.getElementById('etlTotalRuns').textContent   = s.total_runs || 0;
  document.getElementById('etlSuccessRate').textContent = successRate + '%';
  document.getElementById('etlLoaded').textContent      = Number(s.total_loaded||0).toLocaleString();
  document.getElementById('etlAvgDuration').textContent = Math.round(s.avg_duration || 0) + 's';

  const tbody = document.getElementById('etlTableBody');
  tbody.innerHTML = (d.logs || []).map(l => `
    <tr>
      <td><strong>${esc(l.pipeline_name)}</strong></td>
      <td><span class="etl-badge ${l.status}">${l.status}</span></td>
      <td>${Number(l.records_extracted).toLocaleString()}</td>
      <td>${Number(l.records_loaded).toLocaleString()}</td>
      <td style="color:${l.records_failed>0?'var(--red)':'var(--gray)'}">${l.records_failed}</td>
      <td>${l.duration_seconds}s</td>
      <td>${parseFloat(l.data_volume_kb||0).toFixed(1)} KB</td>
      <td style="color:var(--gray);font-size:0.78rem">${fmtDate(l.started_at)}</td>
    </tr>
  `).join('') || `<tr><td colspan="8" style="text-align:center;padding:20px;color:var(--gray)">No pipeline logs.</td></tr>`;

  // Health bars
  const completedPct = successRate;
  const failedPct    = s.total_runs ? Math.round((s.failed/s.total_runs)*100) : 0;
  const warnPct      = s.total_runs ? Math.round((s.warnings/s.total_runs)*100) : 0;

  setHealthBar('hbUsers',    completedPct, '#007a3f', completedPct + '% healthy');
  setHealthBar('hbFeedback', Math.max(0, 100-failedPct), '#2058dc', (100-failedPct) + '% available');
  setHealthBar('hbAnalytics', Math.max(0, 100-warnPct), warnPct>20?'#c07400':'#007a3f', warnPct > 20 ? 'Warning' : 'Healthy');
}

function setHealthBar(id, pct, color, label) {
  const el = document.getElementById(id);
  const ll = document.getElementById(id + 'Lbl');
  if (el) { el.style.width = pct + '%'; el.style.background = color; }
  if (ll) ll.textContent = label;
}

// ══════════════════════════════════════════════════════════
// FEEDBACK
// ══════════════════════════════════════════════════════════
let fbState = { search: '', rating: 'all', page: 1 };
let feedbackTimer = null;

function startFeedbackRefresh() {
  if (feedbackTimer) return;
  feedbackTimer = setInterval(() => {
    if (currentPage === 'feedback') loadFeedback(true);
  }, 3000);
}

async function loadFeedback(silent = false) {
  document.getElementById('feedbackList').innerHTML =
    `<div class="table-empty"><span class="spinner-sm spinner-blue"></span> Loading…</div>`;

  const data = await api('feedback', {
    search: fbState.search,
    rating: fbState.rating,
    page:   fbState.page,
  });

  if (!data.ok) return;

  // Stats bar
  const s = data.stats;
  document.getElementById('fbTotal').textContent    = s.total || 0;
  document.getElementById('fbPositive').textContent = s.positive || 0;
  document.getElementById('fbNegative').textContent = s.negative || 0;
  document.getElementById('fbAvgRating').textContent = parseFloat(s.avg_rating||0).toFixed(1) + ' ★';

  // List
  const list = document.getElementById('feedbackList');
  if (!data.items.length) {
    list.innerHTML = `<div class="table-empty">No feedback found.</div>`;
    document.getElementById('fbPagination').style.display = 'none';
    return;
  }

  list.innerHTML = data.items.map(f => {
    const initials = f.fullname ? f.fullname.charAt(0).toUpperCase() : '?';
    const stars    = f.rating ? '★'.repeat(f.rating) + '☆'.repeat(5-f.rating) : null;
    const ratingHtml = f.rating
      ? `<span class="feedback-rating"><span class="stars">${stars}</span> ${f.rating}/5</span>`
      : `<span class="feedback-rating no-rating">(No rating)</span>`;

    return `<div class="feedback-item" data-id="${f.id}">
      <div class="feedback-avatar">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
      </div>
      <div class="feedback-body">
        <div class="feedback-text">${esc(f.message)}</div>
        <div class="feedback-meta">
          ${ratingHtml}
          <span>·</span>
          <span>${fmtDate(f.created_at)}</span>
          ${f.fullname ? `<span>·</span><span style="color:var(--navy);font-weight:500">${esc(f.fullname)}</span>` : ''}
          ${f.is_reviewed ? '<span style="color:var(--green);font-weight:600">✓ Reviewed</span>' : ''}
        </div>
      </div>
      <div class="feedback-actions">
        ${!f.is_reviewed ? `<button class="btn-fb-action" onclick="reviewFeedback(${f.id}, this)">Mark reviewed</button>` : ''}
        <button class="btn-fb-action" style="color:var(--red);border-color:var(--red)" onclick="archiveFeedback(${f.id}, this)">Archive</button>
      </div>
    </div>`;
  }).join('');

  // Pagination
  const pag  = document.getElementById('fbPagination');
  const info = document.getElementById('fbPaginInfo');
  const btns = document.getElementById('fbPaginBtns');
  pag.style.display = 'flex';
  info.textContent = `${data.items.length} of ${data.total} feedback`;

  btns.innerHTML = '';
  const prev = makePaginBtn('‹', data.page <= 1, () => { fbState.page--; loadFeedback(); });
  btns.appendChild(prev);
  for (let p = 1; p <= Math.min(data.pages, 7); p++) {
    const b = makePaginBtn(p, false, () => { fbState.page = p; loadFeedback(); });
    if (p === data.page) b.classList.add('active');
    btns.appendChild(b);
  }
  btns.appendChild(makePaginBtn('›', data.page >= data.pages, () => { fbState.page++; loadFeedback(); }));
}

async function reviewFeedback(id, btn) {
  const data = await api('feedback_review', { id }, 'POST');
  if (data.ok) { fbState.page = 1; loadFeedback(); }
}

async function archiveFeedback(id, btn) {
  const data = await api('feedback_archive', { id }, 'POST');
  if (data.ok) { fbState.page = 1; loadFeedback(); }
}

// Feedback filters
let fbSearchTimer;
document.getElementById('fbSearch').addEventListener('input', e => {
  clearTimeout(fbSearchTimer);
  fbSearchTimer = setTimeout(() => {
    fbState.search = e.target.value.trim();
    fbState.page   = 1;
    loadFeedback();
  }, 350);
});
document.getElementById('fbRatingFilter').addEventListener('change', e => {
  fbState.rating = e.target.value;
  fbState.page   = 1;
  loadFeedback();
});

document.getElementById('exportFbPdf').addEventListener('click', async () => {
  const btn = document.getElementById('exportFbPdf');
  btn.classList.add('loading');
  try {
    const data = await api('feedback', { search: fbState.search, rating: fbState.rating, page: 1, limit: 9999 });
    if (!data.ok || !data.items || data.items.length === 0) {
      alert("No feedback to export.");
      return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    doc.setFontSize(16);
    doc.text('Feedback Export', 14, 15);
    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 14, 21);
    
    const body = data.items.map(i => [
      i.fullname || 'Anonymous',
      i.rating ? `${i.rating} / 5` : 'No rating',
      i.sentiment || '—',
      fmtDate(i.created_at),
      i.message || ''
    ]);
    
    doc.autoTable({
      startY: 25,
      head: [['User', 'Rating', 'Sentiment', 'Date', 'Message']],
      body: body,
      headStyles: { fillColor: [40, 53, 147] },
      styles: { fontSize: 9 },
      columnStyles: {
        4: { cellWidth: 80 } // Give more space to the message column
      }
    });
    
    doc.save(`feedback_export_${new Date().toISOString().slice(0,10)}.pdf`);
  } catch (err) {
    alert("Export failed: " + err.message);
  } finally {
    btn.classList.remove('loading');
  }
});

// ── Kick off ────────────────────────────────────────────────
loadUsers();
</script>
</body>
</html>
