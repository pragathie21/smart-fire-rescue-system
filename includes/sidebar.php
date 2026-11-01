<?php
/**
 * Smart Fire and Rescue Response System
 * Dark Charcoal Sidebar Navigation
 */
$current = $currentPage ?? basename($_SERVER['PHP_SELF'], '.php');
if ($current === 'index') $current = 'dashboard';
?>
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand / Logo -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" fill="#f97316" stroke="#ea580c"/>
            </svg>
        </div>
        <div class="brand-info">
            <span class="brand-title">SUDAR RESCUE</span>
            <span class="brand-subtitle">Smart Response CAD</span>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">OPERATIONS</div>

        <a href="<?= BASE_URL ?>dashboard.php" class="nav-link <?= $current === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>report-incident.php" class="nav-link <?= $current === 'report-incident' ? 'active' : '' ?>">
            <span class="nav-icon">🚨</span>
            <span class="nav-text">Report Incident</span>
            <span class="nav-tag tag-pulse">NEW</span>
        </a>

        <a href="<?= BASE_URL ?>incidents.php" class="nav-link <?= $current === 'incidents' || $current === 'incident-details' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Manage Incidents</span>
        </a>

        <a href="<?= BASE_URL ?>dispatch.php" class="nav-link <?= $current === 'dispatch' ? 'active' : '' ?>">
            <span class="nav-icon">📡</span>
            <span class="nav-text">Dispatch Center</span>
            <span class="nav-tag tag-dijkstra">Dijkstra</span>
        </a>

        <a href="<?= BASE_URL ?>driver.php" class="nav-link <?= $current === 'driver' ? 'active' : '' ?>">
            <span class="nav-icon">📱</span>
            <span class="nav-text">Driver Terminal</span>
            <span class="nav-tag" style="background:#16a34a; color:#fff; font-weight:700;">MDT</span>
        </a>

        <div class="nav-section-label">INFRASTRUCTURE</div>

        <a href="<?= BASE_URL ?>units.php" class="nav-link <?= $current === 'units' ? 'active' : '' ?>">
            <span class="nav-icon">🚒</span>
            <span class="nav-text">Response Units</span>
        </a>

        <a href="<?= BASE_URL ?>roads.php" class="nav-link <?= $current === 'roads' ? 'active' : '' ?>">
            <span class="nav-icon">🛣️</span>
            <span class="nav-text">Road Network</span>
        </a>

        <div class="nav-section-label">ARCHIVE & AUDIT</div>

        <a href="<?= BASE_URL ?>records.php" class="nav-link <?= $current === 'records' ? 'active' : '' ?>">
            <span class="nav-icon">📜</span>
            <span class="nav-text">Dispatch Records</span>
        </a>
    </nav>

    <!-- Stations Status Widget -->
    <div class="sidebar-stations-widget">
        <div class="widget-header">
            <span>COMMAND STATIONS</span>
            <span class="status-indicator-green"></span>
        </div>
        <a href="<?= BASE_URL ?>dashboard.php?station=StationA" class="station-mini-card" style="text-decoration:none; display:block; transition:all 0.15s ease;">
            <div class="st-name" style="display:flex; justify-content:space-between; align-items:center;">
                <span>StationA (Central)</span>
                <span style="font-size:0.65rem; opacity:0.75;">View &rarr;</span>
            </div>
            <div class="st-units">FE01 &bull; RV01</div>
        </a>
        <a href="<?= BASE_URL ?>dashboard.php?station=StationB" class="station-mini-card" style="text-decoration:none; display:block; transition:all 0.15s ease;">
            <div class="st-name" style="display:flex; justify-content:space-between; align-items:center;">
                <span>StationB (North)</span>
                <span style="font-size:0.65rem; opacity:0.75;">View &rarr;</span>
            </div>
            <div class="st-units">FE02 &bull; RV02</div>
        </a>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="footer-meta">
            <span>Sudar Municipal CAD</span>
            <span class="meta-version">v<?= APP_VERSION ?></span>
        </div>
    </div>
</aside>
