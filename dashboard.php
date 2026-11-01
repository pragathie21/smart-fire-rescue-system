<?php
/**
 * Smart Fire and Rescue Response System
 * Unified Command & Operations Dashboard with Multi-Station Filtering
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Incident.php';
require_once __DIR__ . '/classes/ResponseUnit.php';
require_once __DIR__ . '/classes/DispatchService.php';
require_once __DIR__ . '/classes/RoadNetwork.php';

$pageTitle = 'Incident Operations Dashboard';

$incidentModel   = new Incident();
$unitModel       = new ResponseUnit();
$dispatchService = new DispatchService();
$roadNetwork     = new RoadNetwork();

// Live statistics
$incStats            = $incidentModel->getStatistics();
$unitStats           = $unitModel->getStatistics();
$activeMissionsCount = $dispatchService->getActiveMissionsCount();
$roadStats           = $roadNetwork->getStatistics();

// All incidents & fleet
$allIncidents    = $incidentModel->getAll();
$recentIncidents = array_slice($allIncidents, 0, 10);
$units           = $unitModel->getAll();

// Station Definitions & Metadata
$stationMeta = [
    'StationA' => [
        'id'        => 'StationA',
        'name'      => 'Station A (Central HQ)',
        'fullName'  => 'Central Fire Station Base',
        'sector'    => 'Downtown Central Sector',
        'baseNode'  => 'StationA',
        'phone'     => '911-SUDAR-CENTRAL',
        'email'     => 'station-central@sudar.gov.in',
        'color'     => '#dc2626',
        'badgeBg'   => '#fee2e2',
        'badgeText' => '#b91c1c',
        'icon'      => '🏢',
        'units'     => ['FE01', 'RV01'],
    ],
    'StationB' => [
        'id'        => 'StationB',
        'name'      => 'Station B (North)',
        'fullName'  => 'North Fire Station Base',
        'sector'    => 'North Ridge Sector',
        'baseNode'  => 'StationB',
        'phone'     => '911-SUDAR-NORTH',
        'email'     => 'station-north@sudar.gov.in',
        'color'     => '#2563eb',
        'badgeBg'   => '#dbeafe',
        'badgeText' => '#1d4ed8',
        'icon'      => '🏢',
        'units'     => ['FE02', 'RV02'],
    ]
];

// Pre-calculate per-station metrics
$stationStats = [
    'StationA' => ['total' => 0, 'available' => 0, 'busy' => 0, 'active_missions' => 0, 'total_incidents' => 0],
    'StationB' => ['total' => 0, 'available' => 0, 'busy' => 0, 'active_missions' => 0, 'total_incidents' => 0],
];

foreach ($units as $u) {
    $st = $u['station_id'] ?? '';
    if (isset($stationStats[$st])) {
        $stationStats[$st]['total']++;
        if ($u['status'] === 'Available') {
            $stationStats[$st]['available']++;
        } else {
            $stationStats[$st]['busy']++;
        }
    }
}

foreach ($allIncidents as $inc) {
    $st = $inc['assigned_station_id'] ?? '';
    if (isset($stationStats[$st])) {
        $stationStats[$st]['total_incidents']++;
        if (in_array($inc['status'], ['Dispatched', 'In Progress'], true)) {
            $stationStats[$st]['active_missions']++;
        }
    }
}

// Active station filter from GET parameter (default: 'all')
$selectedStation = trim($_GET['station'] ?? 'all');
if (!in_array($selectedStation, ['all', 'StationA', 'StationB'], true)) {
    $selectedStation = 'all';
}

// Newly reported incident highlight if redirected from report form
$newIncidentId = isset($_GET['new_incident']) ? (int)$_GET['new_incident'] : null;
$newIncidentData = $newIncidentId ? $incidentModel->getById($newIncidentId) : null;

include __DIR__ . '/includes/header.php';
?>

<!-- Custom Scoped Styling for Station Switcher & Micro-interactions -->
<style>
.station-switcher-container {
    background: #ffffff;
    border: 1px solid var(--color-card-border, #e2e8f0);
    border-radius: 14px;
    padding: 12px 18px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
}

.station-pill-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.station-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.station-pill-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #0f172a;
    transform: translateY(-1px);
}

.station-pill-btn.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
}

.station-pill-btn.active .pill-subtag {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}

.pill-subtag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 6px;
    background: #e2e8f0;
    color: #475569;
    transition: background 0.2s;
}

.station-info-banner {
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    transition: all 0.3s ease;
}

.unit-card-transition {
    transition: all 0.25s ease-in-out;
}
</style>

<!-- Top Action Header -->
<div style="margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar); display:flex; align-items:center; gap:8px;">
            <span>🚨</span> Incident Command &amp; Operations Dashboard
        </h1>
        <p style="color:var(--color-text-muted); font-size:0.88rem;">
            Town of <?= TOWN_NAME ?> &bull; Automated Priority Triage, Dijkstra Routing &amp; Multi-Station Fleet Dispatch
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <form method="POST" action="<?= BASE_URL ?>actions/auto-dispatch.php" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" style="background:#0f172a; border-color:#334155;">
                <span>⚡</span> Run CAD Auto-Dispatch
            </button>
        </form>
        <a href="<?= BASE_URL ?>report-incident.php" class="btn btn-emergency-cta">
            <span>🚨</span> Log Emergency
        </a>
    </div>
</div>

<!-- ===================================================================== -->
<!-- STATION COMMAND SWITCHER (All Sudar / Station A / Station B)           -->
<!-- ===================================================================== -->
<div class="station-switcher-container">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="font-size:0.78rem; font-weight:800; color:#64748b; text-transform:uppercase; letter-spacing:0.06em; display:flex; align-items:center; gap:6px;">
            <span>🏢</span> Station Command View:
        </span>
        <div class="station-pill-group" id="stationPillGroup">
            <!-- All Town View -->
            <button type="button" 
                    class="station-pill-btn <?= $selectedStation === 'all' ? 'active' : '' ?>" 
                    data-station="all" 
                    onclick="switchStationFilter('all')">
                <span>🌐</span>
                <span>All Sudar Town</span>
                <span class="pill-subtag"><?= count($units) ?> Units &bull; 2 Stations</span>
            </button>

            <!-- Station A View -->
            <button type="button" 
                    class="station-pill-btn <?= $selectedStation === 'StationA' ? 'active' : '' ?>" 
                    data-station="StationA" 
                    onclick="switchStationFilter('StationA')">
                <span style="color:#ef4444;">🏢</span>
                <span>Station A (Central HQ)</span>
                <span class="pill-subtag" style="<?= $selectedStation === 'StationA' ? '' : 'background:#fee2e2; color:#b91c1c;' ?>">
                    <?= $stationStats['StationA']['available'] ?>/<?= $stationStats['StationA']['total'] ?> Ready
                </span>
            </button>

            <!-- Station B View -->
            <button type="button" 
                    class="station-pill-btn <?= $selectedStation === 'StationB' ? 'active' : '' ?>" 
                    data-station="StationB" 
                    onclick="switchStationFilter('StationB')">
                <span style="color:#3b82f6;">🏢</span>
                <span>Station B (North)</span>
                <span class="pill-subtag" style="<?= $selectedStation === 'StationB' ? '' : 'background:#dbeafe; color:#1d4ed8;' ?>">
                    <?= $stationStats['StationB']['available'] ?>/<?= $stationStats['StationB']['total'] ?> Ready
                </span>
            </button>
        </div>
    </div>

    <!-- Active View Mode Indicator -->
    <div id="stationActiveModeBadge" style="font-size:0.8rem; font-weight:700; color:#475569; display:flex; align-items:center; gap:6px;">
        <span style="width:8px; height:8px; border-radius:50%; background:#16a34a; display:inline-block;"></span>
        <span id="activeModeText">
            <?= $selectedStation === 'all' ? 'Mode: Unified Town CAD (All Stations)' : ($selectedStation === 'StationA' ? 'Mode: Station A Central Base View' : 'Mode: Station B North Base View') ?>
        </span>
    </div>
</div>

<!-- ===================================================================== -->
<!-- STATION FOCUS BANNER (Shown when Station A or Station B is selected)   -->
<!-- ===================================================================== -->
<div id="stationFocusBanner" 
     class="station-info-banner" 
     style="display: <?= $selectedStation === 'all' ? 'none' : 'flex' ?>; 
            background: <?= $selectedStation === 'StationA' ? 'linear-gradient(135deg, #fff1f2 0%, #fef2f2 100%)' : 'linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%)' ?>; 
            border: 1.5px solid <?= $selectedStation === 'StationA' ? '#fca5a5' : '#bfdbfe' ?>;">
    <div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:0.75rem; font-weight:800; letter-spacing:0.06em; text-transform:uppercase; padding:3px 8px; border-radius:4px; 
                         background:<?= $selectedStation === 'StationA' ? '#fee2e2' : '#dbeafe' ?>; 
                         color:<?= $selectedStation === 'StationA' ? '#b91c1c' : '#1d4ed8' ?>;">
                <?= $selectedStation === 'StationA' ? '🏢 STATION A &bull; CENTRAL DOWNTOWN HQ' : '🏢 STATION B &bull; NORTH RIDGE SECTOR' ?>
            </span>
            <span style="font-size:0.8rem; color:#64748b;">
                Hotline: <strong id="bannerPhone"><?= $selectedStation === 'StationA' ? '911-SUDAR-CENTRAL' : '911-SUDAR-NORTH' ?></strong>
            </span>
        </div>
        <h3 id="bannerTitle" style="font-size:1.15rem; font-weight:800; color:#0f172a; margin:4px 0 2px;">
            <?= $selectedStation === 'StationA' ? 'Central Fire Station Command' : 'North Fire Station Command' ?>
        </h3>
        <p id="bannerDesc" style="font-size:0.82rem; color:#475569; margin:0;">
            Showing response units and emergency missions dispatched from this specific base.
        </p>
    </div>

    <!-- Quick Driver MDT Links for this Station -->
    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <span style="font-size:0.75rem; font-weight:700; color:#64748b;">Driver Terminal:</span>
        <div id="bannerMdtLinks" style="display:flex; gap:6px;">
            <?php if ($selectedStation === 'StationA'): ?>
                <a href="<?= BASE_URL ?>driver.php?unit=FE01" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">
                    🚒 FE01 MDT
                </a>
                <a href="<?= BASE_URL ?>driver.php?unit=RV01" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">
                    🛟 RV01 MDT
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>driver.php?unit=FE02" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">
                    🚒 FE02 MDT
                </a>
                <a href="<?= BASE_URL ?>driver.php?unit=RV02" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">
                    🛟 RV02 MDT
                </a>
            <?php endif; ?>
        </div>
        <button type="button" onclick="switchStationFilter('all')" class="btn btn-sm" style="background:#ffffff; border:1px solid #cbd5e1; color:#334155; font-size:0.78rem; font-weight:700;">
            ↺ Reset to All
        </button>
    </div>
</div>

<!-- Highlight Banner if incident was just reported -->
<?php if ($newIncidentData): ?>
    <div style="background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border: 2px solid #3b82f6; border-radius:var(--radius-md); padding:18px 22px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(37,99,235,0.12);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:10px;">
            <div>
                <span style="font-size:0.75rem; font-weight:800; color:#1d4ed8; text-transform:uppercase; letter-spacing:0.06em;">
                    ✅ INCIDENT REPORTED &amp; TRIAGED SUCCESSFULLY
                </span>
                <h3 style="font-size:1.25rem; font-weight:800; color:var(--color-sidebar); margin-top:2px;">
                    Incident #<?= $newIncidentData['incident_id'] ?>: <?= sanitize($newIncidentData['incident_type']) ?> &bull; <?= sanitize($newIncidentData['location_name'] ?? $newIncidentData['location_id']) ?>
                </h3>
            </div>
            <div>
                <span class="badge badge-<?= strtolower($newIncidentData['status']) ?>" style="font-size:0.85rem; padding:6px 14px;">
                    Status: <?= sanitize($newIncidentData['status']) ?>
                </span>
            </div>
        </div>

        <?php if (!empty($newIncidentData['dispatch'])): ?>
            <?php $d = $newIncidentData['dispatch']; ?>
            <div style="background:#ffffff; border:1px solid #bfdbfe; border-radius:var(--radius-sm); padding:12px 16px; margin-top:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <div>
                        <strong style="color:#1e40af;">🚒 Assigned Unit:</strong>
                        <span style="font-weight:700; color:var(--color-sidebar);"><?= sanitize($d['unit_id']) ?> (<?= sanitize($d['unit_type']) ?>)</span>
                        <span style="color:var(--color-text-muted); font-size:0.8rem; margin-left:6px;">from <?= sanitize($d['station_name']) ?></span>
                    </div>
                    <div>
                        <strong style="color:var(--color-emergency); font-size:1.1rem; font-family:var(--font-mono);">
                            ⏱️ <?= $d['estimated_travel_time'] ?> mins ETA
                        </strong>
                    </div>
                </div>
                <div style="margin-top:8px; font-size:0.85rem;">
                    <span style="color:#64748b; font-weight:700;">Dijkstra Optimal Route:</span>
                    <span style="font-family:var(--font-mono); font-weight:700; color:var(--color-primary); background:#eff6ff; padding:3px 8px; border-radius:4px; margin-left:6px;">
                        🛣️ <?= sanitize($d['route']) ?>
                    </span>
                </div>
            </div>
        <?php else: ?>
            <p style="font-size:0.85rem; color:#b45309; margin:4px 0 0;">
                ⏳ Placed in priority response queue. Will be automatically dispatched as soon as a compatible unit is released.
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- 1. Live Statistics Metrics Grid -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <!-- Stat 1: Incidents (Dynamic per station or town total) -->
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title" id="statTitle1">
                <?= $selectedStation === 'all' ? 'Total Incidents' : ($selectedStation === 'StationA' ? 'Station A Incidents' : 'Station B Incidents') ?>
            </span>
            <span class="stat-value" id="statVal1">
                <?= $selectedStation === 'all' ? $incStats['total'] : $stationStats[$selectedStation]['total_incidents'] ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-red">🚨</div>
    </div>

    <!-- Stat 2: Pending Triage (Queue) -->
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Pending CAD Queue</span>
            <span class="stat-value" id="statVal2" style="color:<?= $incStats['pending'] > 0 ? 'var(--color-emergency)' : 'var(--color-sidebar)' ?>;">
                <?= $incStats['pending'] ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-amber">⏳</div>
    </div>

    <!-- Stat 3: Available Fleet (Dynamic per station or town total) -->
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title" id="statTitle3">
                <?= $selectedStation === 'all' ? 'Available Fleet' : ($selectedStation === 'StationA' ? 'Station A Ready' : 'Station B Ready') ?>
            </span>
            <span class="stat-value" id="statVal3" style="color:var(--color-success);">
                <?= $selectedStation === 'all' ? $unitStats['available'] : $stationStats[$selectedStation]['available'] . '/' . $stationStats[$selectedStation]['total'] ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-green">🚒</div>
    </div>

    <!-- Stat 4: Active Missions (Dynamic per station or town total) -->
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title" id="statTitle4">
                <?= $selectedStation === 'all' ? 'Active CAD Missions' : ($selectedStation === 'StationA' ? 'Station A Missions' : 'Station B Missions') ?>
            </span>
            <span class="stat-value" id="statVal4" style="color:var(--color-primary);">
                <?= $selectedStation === 'all' ? $activeMissionsCount : $stationStats[$selectedStation]['active_missions'] ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-blue">⚡</div>
    </div>

    <!-- Stat 5: Road Network Topology -->
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Road Network</span>
            <span class="stat-value" style="color:<?= $roadStats['closed'] > 0 ? 'var(--color-emergency)' : 'var(--color-success)' ?>;">
                <?= $roadStats['open'] ?>/<?= $roadStats['total'] ?> Open
            </span>
        </div>
        <div class="stat-icon stat-icon-blue">🛣️</div>
    </div>
</div>

<!-- 2. Road Network & Dijkstra Topology Quick Banner -->
<div class="card" style="margin-bottom: 24px; border: 1.5px solid #bfdbfe; background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);">
    <div class="card-body" style="padding: 16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="font-size:1.8rem;">🛣️</span>
            <div>
                <strong style="color:var(--color-sidebar); font-size:1rem;">Dijkstra Road Network &amp; Hazard Closures</strong>
                <p style="color:var(--color-text-muted); font-size:0.82rem; margin:0;">
                    20 town road segments monitored. Closed roads are immediately bypassed by the Dijkstra pathfinding engine.
                </p>
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="<?= BASE_URL ?>roads.php" class="btn btn-sm btn-primary">
                <span>🧪</span> Open Dijkstra Route Simulator &amp; Closures &rarr;
            </a>
        </div>
    </div>
</div>

<!-- 3. Recent Emergency Incidents & Live Missions Table -->
<div class="card" style="margin-bottom: 28px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 class="card-title">
                <span>📋</span> Recent Emergency Incidents &amp; Missions
            </h3>
            <p class="card-subtitle" id="incidentTableSubtitle">
                <?= $selectedStation === 'all' 
                    ? 'Showing emergencies across all town sectors &bull; Automatic Dijkstra route assignment' 
                    : 'Showing emergencies assigned to ' . ($selectedStation === 'StationA' ? 'Station A (Central HQ)' : 'Station B (North)') . ' &amp; active triage queue' ?>
            </p>
        </div>
        <a href="<?= BASE_URL ?>incidents.php" class="btn btn-secondary btn-sm">
            View All Incidents (<?= $incStats['total'] ?>) &rarr;
        </a>
    </div>

    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type &amp; Severity</th>
                        <th>Location &amp; Address</th>
                        <th>Assigned Station &amp; Unit</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="incidentTableBody">
                    <?php if (!empty($recentIncidents)): ?>
                        <?php foreach ($recentIncidents as $inc): ?>
                            <?php 
                                $isHighlighted = ($newIncidentId && (int)$inc['incident_id'] === $newIncidentId);
                                $assignedStation = $inc['assigned_station_id'] ?? '';
                                $isRowVisible = ($selectedStation === 'all' || empty($assignedStation) || $assignedStation === $selectedStation);
                            ?>
                            <tr class="incident-row" 
                                data-station="<?= sanitize($assignedStation ?: 'unassigned') ?>"
                                data-status="<?= sanitize($inc['status']) ?>"
                                style="<?= $isHighlighted ? 'background:#eff6ff; border-left:4px solid #3b82f6;' : '' ?><?= !$isRowVisible ? 'display:none;' : '' ?>">
                                <td>
                                    <strong style="font-family:var(--font-mono); font-size:0.95rem;">
                                        #<?= $inc['incident_id'] ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong><?= sanitize($inc['incident_type']) ?></strong>
                                    <div><?= render_severity_badge($inc['severity_tier'], $inc['override_severity']) ?></div>
                                </td>
                                <td>
                                    <strong><?= sanitize($inc['location_name'] ?? $inc['location_id']) ?></strong>
                                    <?php if (!empty($inc['location_address'])): ?>
                                        <div style="font-size:0.75rem; color:var(--color-text-muted); max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= sanitize($inc['location_address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($inc['assigned_unit_id'])): ?>
                                        <div>
                                            <strong style="color:var(--color-primary); font-family:var(--font-mono);">
                                                🚒 <?= sanitize($inc['assigned_unit_id']) ?>
                                            </strong>
                                            
                                            <!-- Station Badge -->
                                            <?php if (!empty($assignedStation) && isset($stationMeta[$assignedStation])): ?>
                                                <span class="badge" style="background:<?= $stationMeta[$assignedStation]['badgeBg'] ?>; color:<?= $stationMeta[$assignedStation]['badgeText'] ?>; font-size:0.7rem; font-weight:700; margin-left:4px;">
                                                    🏢 <?= sanitize($assignedStation) ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if (!empty($inc['estimated_travel_time'])): ?>
                                                <span style="font-size:0.75rem; color:var(--color-emergency); font-weight:800; background:#fee2e2; padding:2px 6px; border-radius:10px; margin-left:4px;">
                                                    <?= $inc['estimated_travel_time'] ?>m ETA
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($inc['dijkstra_route'])): ?>
                                            <div style="font-size:0.72rem; color:var(--color-text-muted); font-family:var(--font-mono); margin-top:2px;">
                                                🛣️ <?= sanitize($inc['dijkstra_route']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#b45309; font-size:0.8rem; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                            ⏳ Queued in CAD Triage
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-family:var(--font-mono); font-weight:700;">
                                        <?= $inc['priority_score'] ?>/100
                                    </span>
                                </td>
                                <td>
                                    <?= render_status_badge($inc['status']) ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?= BASE_URL ?>incident-details.php?id=<?= $inc['incident_id'] ?>" class="btn btn-sm btn-secondary">
                                        Details
                                    </a>
                                    <?php if (in_array($inc['status'], ['Dispatched', 'In Progress'], true) && !empty($inc['assigned_unit_id'])): ?>
                                        <!-- Quick Complete Button -->
                                        <form method="POST" action="<?= BASE_URL ?>actions/complete-mission.php" style="display:inline;"
                                              data-confirm="Mark Incident #<?= $inc['incident_id'] ?> as Completed and release Unit <?= sanitize($inc['assigned_unit_id']) ?>?"
                                              data-confirm-title="Complete Emergency Mission"
                                              data-confirm-btn="Complete &amp; Release Unit"
                                              data-confirm-class="btn-success">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="dispatch_id" value="<?= (int)($inc['dispatch_id'] ?? 0) ?>">
                                            <input type="hidden" name="incident_id" value="<?= (int)$inc['incident_id'] ?>">
                                            <input type="hidden" name="redirect" value="dashboard.php">
                                            <button type="submit" class="btn btn-sm btn-success" style="padding:4px 10px; font-size:0.78rem; font-weight:700;">
                                                ✓ Complete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Empty Station Row (shown dynamically when filter has no match) -->
                        <tr id="emptyStationRow" style="display:none;">
                            <td colspan="7" style="text-align:center; padding:32px; color:var(--color-text-muted);">
                                ℹ️ No active emergency missions currently dispatched from this selected fire station.
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:32px; color:var(--color-text-muted);">
                                No emergency incidents logged yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 4. Response Units Fleet Live Readiness -->
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 class="card-title"><span>🚒</span> Response Unit Fleet Status</h3>
            <p class="card-subtitle" id="fleetSectionSubtitle">
                <?= $selectedStation === 'all' 
                    ? 'Live readiness and carried equipment across Town of Sudar Fire Stations' 
                    : 'Showing stationed vehicles at ' . ($selectedStation === 'StationA' ? 'Station A (Central HQ)' : 'Station B (North)') ?>
            </p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="<?= BASE_URL ?>driver.php" class="btn btn-sm btn-secondary">
                <span>📱</span> Driver MDT Terminal
            </a>
            <a href="<?= BASE_URL ?>units.php" class="btn btn-secondary btn-sm">
                Fleet Directory &rarr;
            </a>
        </div>
    </div>

    <div class="card-body">
        <div id="fleetCardsContainer" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
            <?php foreach ($units as $u): ?>
                <?php 
                    $uStation = $u['station_id'] ?? '';
                    $isCardVisible = ($selectedStation === 'all' || $uStation === $selectedStation);
                ?>
                <div class="card unit-fleet-card unit-card-transition" 
                     data-station="<?= sanitize($uStation) ?>"
                     style="border: 1px solid var(--color-card-border); background:#ffffff; padding:16px; border-radius:12px; <?= !$isCardVisible ? 'display:none;' : '' ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <h4 style="font-size:1.15rem; font-weight:800; color:var(--color-sidebar); margin:0; display:flex; align-items:center; gap:6px;">
                            <span><?= strpos($u['unit_type'], 'Rescue') !== false ? '🛟' : '🚒' ?></span>
                            <span><?= sanitize($u['unit_id']) ?></span>
                        </h4>
                        <?= render_status_badge($u['status']) ?>
                    </div>
                    <div style="font-weight:700; color:#334155; font-size:0.9rem; margin-bottom:4px;">
                        <?= sanitize($u['unit_type']) ?>
                    </div>
                    <div style="font-size:0.8rem; color:var(--color-text-muted); margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                        <span>🏢</span>
                        <span><?= sanitize($u['station_name'] ?? $uStation) ?></span>
                        <span class="badge" style="font-size:0.68rem; font-weight:700; background:<?= ($uStation === 'StationA' ? '#fee2e2' : '#dbeafe') ?>; color:<?= ($uStation === 'StationA' ? '#b91c1c' : '#1d4ed8') ?>;">
                            <?= sanitize($uStation) ?>
                        </span>
                    </div>

                    <!-- Direct Link to Driver Terminal -->
                    <div style="margin-bottom:10px;">
                        <a href="<?= BASE_URL ?>driver.php?unit=<?= urlencode($u['unit_id']) ?>" 
                           class="btn btn-sm btn-secondary" 
                           style="width:100%; display:inline-flex; justify-content:center; align-items:center; gap:6px; font-size:0.75rem; font-weight:700; padding:5px 8px;">
                            <span>📱</span> Open Driver Navigation
                        </a>
                    </div>

                    <div style="font-size:0.75rem; color:var(--color-text-muted); border-top:1px solid #f1f5f9; padding-top:8px;">
                        <strong>Carried Equipment:</strong>
                        <div style="margin-top:4px; display:flex; flex-wrap:wrap; gap:4px;">
                            <?php foreach (($u['equipment_array'] ?? []) as $eq): ?>
                                <span class="badge" style="background:#f1f5f9; color:#475569; font-size:0.68rem; padding:2px 6px;">
                                    <?= sanitize($eq) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- DYNAMIC JAVASCRIPT CONTROLLER FOR CLIENT-SIDE STATION FILTERING        -->
<!-- ===================================================================== -->
<script>
// Static metrics snapshot from PHP
const townStats = {
    all: {
        title1: 'Total Incidents',
        val1: '<?= $incStats['total'] ?>',
        val2: '<?= $incStats['pending'] ?>',
        title3: 'Available Fleet',
        val3: '<?= $unitStats['available'] ?>/<?= $unitStats['total'] ?>',
        title4: 'Active CAD Missions',
        val4: '<?= $activeMissionsCount ?>',
        mode: 'Mode: Unified Town CAD (All Stations)',
        incSub: 'Showing emergencies across all town sectors • Automatic Dijkstra route assignment',
        fleetSub: 'Live readiness and carried equipment across Town of Sudar Fire Stations'
    },
    StationA: {
        title1: 'Station A Incidents',
        val1: '<?= $stationStats['StationA']['total_incidents'] ?>',
        val2: '<?= $incStats['pending'] ?>',
        title3: 'Station A Ready',
        val3: '<?= $stationStats['StationA']['available'] ?>/<?= $stationStats['StationA']['total'] ?>',
        title4: 'Station A Missions',
        val4: '<?= $stationStats['StationA']['active_missions'] ?>',
        mode: 'Mode: Station A Central Base View',
        phone: '911-SUDAR-CENTRAL',
        title: 'Central Fire Station Command (HQ)',
        desc: 'Showing response units (FE01, RV01) and incidents assigned to Station A base.',
        mdtHtml: '<a href="<?= BASE_URL ?>driver.php?unit=FE01" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">🚒 FE01 MDT</a> <a href="<?= BASE_URL ?>driver.php?unit=RV01" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">🛟 RV01 MDT</a>',
        bg: 'linear-gradient(135deg, #fff1f2 0%, #fef2f2 100%)',
        border: '#fca5a5',
        incSub: 'Showing emergencies assigned to Station A (Central HQ) & active triage queue',
        fleetSub: 'Showing vehicles stationed at Station A (Central HQ)'
    },
    StationB: {
        title1: 'Station B Incidents',
        val1: '<?= $stationStats['StationB']['total_incidents'] ?>',
        val2: '<?= $incStats['pending'] ?>',
        title3: 'Station B Ready',
        val3: '<?= $stationStats['StationB']['available'] ?>/<?= $stationStats['StationB']['total'] ?>',
        title4: 'Station B Missions',
        val4: '<?= $stationStats['StationB']['active_missions'] ?>',
        mode: 'Mode: Station B North Base View',
        phone: '911-SUDAR-NORTH',
        title: 'North Fire Station Command',
        desc: 'Showing response units (FE02, RV02) and incidents assigned to Station B base.',
        mdtHtml: '<a href="<?= BASE_URL ?>driver.php?unit=FE02" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">🚒 FE02 MDT</a> <a href="<?= BASE_URL ?>driver.php?unit=RV02" class="btn btn-sm btn-secondary" style="font-size:0.78rem; font-weight:700;">🛟 RV02 MDT</a>',
        bg: 'linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%)',
        border: '#bfdbfe',
        incSub: 'Showing emergencies assigned to Station B (North) & active triage queue',
        fleetSub: 'Showing vehicles stationed at Station B (North)'
    }
};

function switchStationFilter(stationId) {
    if (!townStats[stationId]) return;

    // 1. Update Buttons Active State
    document.querySelectorAll('.station-pill-btn').forEach(btn => {
        if (btn.dataset.station === stationId) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // 2. Update Browser URL seamlessly
    const currentUrl = new URL(window.location);
    if (stationId === 'all') {
        currentUrl.searchParams.delete('station');
    } else {
        currentUrl.searchParams.set('station', stationId);
    }
    window.history.replaceState({}, '', currentUrl);

    // 3. Update Stat Cards
    const data = townStats[stationId];
    document.getElementById('statTitle1').textContent = data.title1;
    document.getElementById('statVal1').textContent = data.val1;
    document.getElementById('statTitle3').textContent = data.title3;
    document.getElementById('statVal3').textContent = data.val3;
    document.getElementById('statTitle4').textContent = data.title4;
    document.getElementById('statVal4').textContent = data.val4;
    document.getElementById('activeModeText').textContent = data.mode;
    document.getElementById('incidentTableSubtitle').textContent = data.incSub;
    document.getElementById('fleetSectionSubtitle').textContent = data.fleetSub;

    // 4. Update Station Focus Banner
    const banner = document.getElementById('stationFocusBanner');
    if (stationId === 'all') {
        banner.style.display = 'none';
    } else {
        banner.style.display = 'flex';
        banner.style.background = data.bg;
        banner.style.borderColor = data.border;
        document.getElementById('bannerTitle').textContent = data.title;
        document.getElementById('bannerDesc').textContent = data.desc;
        document.getElementById('bannerPhone').textContent = data.phone;
        document.getElementById('bannerMdtLinks').innerHTML = data.mdtHtml;
    }

    // 5. Filter Fleet Cards
    const fleetCards = document.querySelectorAll('.unit-fleet-card');
    fleetCards.forEach(card => {
        const cardStation = card.dataset.station;
        if (stationId === 'all' || cardStation === stationId) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });

    // 6. Filter Incidents Table
    const incidentRows = document.querySelectorAll('.incident-row');
    let visibleCount = 0;
    incidentRows.forEach(row => {
        const rowStation = row.dataset.station;
        // In station view: show incidents assigned to this station, OR pending triage incidents waiting for dispatch
        if (stationId === 'all' || rowStation === stationId || rowStation === 'unassigned') {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Toggle Empty Row if no incidents match
    const emptyRow = document.getElementById('emptyStationRow');
    if (emptyRow) {
        if (visibleCount === 0 && incidentRows.length > 0) {
            emptyRow.style.display = '';
        } else {
            emptyRow.style.display = 'none';
        }
    }
}

// Support browser Back/Forward buttons
window.addEventListener('popstate', () => {
    const params = new URLSearchParams(window.location.search);
    const st = params.get('station') || 'all';
    switchStationFilter(st);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
