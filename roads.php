<?php
/**
 * Smart Fire and Rescue Response System
 * Road Network & Closures - Dijkstra Topology Management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/RoadNetwork.php';
require_once __DIR__ . '/classes/RoutePlanner.php';

$pageTitle = 'Road Network & Closure Management';

$roadNetwork  = new RoadNetwork();
$routePlanner = new RoutePlanner($roadNetwork);

$roads     = $roadNetwork->getAllRoads();
$locations = $roadNetwork->getLocations();
$stats     = $roadNetwork->getStatistics();

// Interactive Dijkstra Route Simulator calculation if requested via GET
$simOrigin = trim($_GET['sim_origin'] ?? 'StationA');
$simTarget = trim($_GET['sim_target'] ?? 'Hospital');
$simResult = null;

if (!empty($simOrigin) && !empty($simTarget)) {
    $simResult = $routePlanner->findShortestRoute($simOrigin, $simTarget);
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar); display:flex; align-items:center; gap:8px;">
            <span>🛣️</span> Road Network &amp; Hazard Closure Management
        </h1>
        <p style="color:var(--color-text-muted); font-size:0.88rem;">
            Dynamic road graph in town of <?= TOWN_NAME ?>. Closed roads are automatically ignored by Dijkstra pathfinding algorithms.
        </p>
    </div>
    <div style="display:flex; gap:12px;">
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary btn-sm">
            &larr; Back to Dashboard
        </a>
    </div>
</div>

<!-- Road Network Summary Stats -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Total Road Segments</span>
            <span class="stat-value"><?= $stats['total'] ?></span>
        </div>
        <div class="stat-icon stat-icon-blue">🛣️</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Open &amp; Passable</span>
            <span class="stat-value" style="color:var(--color-success);"><?= $stats['open'] ?></span>
        </div>
        <div class="stat-icon stat-icon-green">🟢</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Closed / Blocked</span>
            <span class="stat-value" style="color:var(--color-emergency);"><?= $stats['closed'] ?></span>
        </div>
        <div class="stat-icon stat-icon-red">🚧</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Total Network Nodes</span>
            <span class="stat-value"><?= count($locations) ?></span>
        </div>
        <div class="stat-icon stat-icon-amber">📍</div>
    </div>
</div>

<!-- Interactive Dijkstra Route Testing & Simulation Tool -->
<div class="card" style="margin-bottom: 28px; border:2px solid #93c5fd; background:#f8fafc;">
    <div class="card-header" style="background:#eff6ff;">
        <div>
            <h3 class="card-title" style="color:#1e40af; display:flex; align-items:center; gap:8px;">
                <span>🧪</span> Interactive Dijkstra Route Simulator &amp; Test Tool
            </h3>
            <p class="card-subtitle" style="color:#3b82f6;">
                Test Dijkstra shortest-path calculations dynamically between any two locations under current road open/closed status.
            </p>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>roads.php" style="display:flex; align-items:flex-end; flex-wrap:wrap; gap:16px; margin-bottom:18px;">
            <div style="flex:1; min-width:200px;">
                <label class="form-label" for="sim_origin">Starting Location (Origin Node):</label>
                <select name="sim_origin" id="sim_origin" class="form-control" required>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= sanitize($loc['location_id']) ?>" <?= $loc['location_id'] === $simOrigin ? 'selected' : '' ?>>
                            <?= sanitize($loc['location_name']) ?> (<?= sanitize($loc['location_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="font-size:1.5rem; color:var(--color-text-muted); padding-bottom:6px;">➔</div>

            <div style="flex:1; min-width:200px;">
                <label class="form-label" for="sim_target">Destination Location (Target Node):</label>
                <select name="sim_target" id="sim_target" class="form-control" required>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= sanitize($loc['location_id']) ?>" <?= $loc['location_id'] === $simTarget ? 'selected' : '' ?>>
                            <?= sanitize($loc['location_name']) ?> (<?= sanitize($loc['location_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">
                    <span>⚡</span> Compute Shortest Route
                </button>
            </div>
        </form>

        <?php if ($simResult): ?>
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:var(--radius-md); padding:16px;">
                <?php if ($simResult['success']): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                        <div>
                            <span style="font-size:0.75rem; font-weight:800; color:var(--color-success); letter-spacing:0.05em; text-transform:uppercase;">
                                ✅ OPTIMAL ROUTE CALCULATED (DIJKSTRA'S ALGORITHM)
                            </span>
                            <div style="font-size:1.05rem; font-weight:700; color:var(--color-sidebar); margin-top:2px;">
                                <?= sanitize($simOrigin) ?> ➔ <?= sanitize($simTarget) ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:0.7rem; color:var(--color-text-muted); font-weight:700; text-transform:uppercase;">TOTAL TRAVEL TIME</span>
                            <div style="font-family:var(--font-mono); font-size:1.5rem; font-weight:800; color:var(--color-emergency);">
                                <?= $simResult['distance'] ?> mins
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <span style="font-size:0.75rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Turn-by-Turn Reconstructed Path:</span>
                        <div style="font-family:var(--font-mono); font-size:0.95rem; font-weight:700; color:var(--color-primary); background:#eff6ff; padding:10px 14px; border-radius:var(--radius-sm); border:1px solid #bfdbfe; margin-top:4px;">
                            <?= sanitize($simResult['path_string']) ?>
                        </div>
                    </div>

                    <?php if (!empty($simResult['segments'])): ?>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                            <?php foreach ($simResult['segments'] as $seg): ?>
                                <span style="font-size:0.78rem; font-family:var(--font-mono); background:#f1f5f9; padding:4px 8px; border-radius:var(--radius-sm); border:1px solid #e2e8f0;">
                                    <?= sanitize($seg['from']) ?> &rarr; <?= sanitize($seg['to']) ?> (<strong><?= $seg['time'] ?>m</strong>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:var(--radius-sm); padding:14px; color:#991b1b;">
                        <strong>❌ Route Blocked:</strong> <?= sanitize($simResult['error']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Road Segments Roster & Closure Toggles -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><span>🚧</span> Road Segments Directory (<?= count($roads) ?> Segments)</h3>
            <p class="card-subtitle">Close roads undergoing maintenance or structural hazards to automatically reroute traffic</p>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Road ID</th>
                        <th>From Location</th>
                        <th>To Location</th>
                        <th>Travel Time</th>
                        <th>Directionality</th>
                        <th>Current Status</th>
                        <th style="text-align:right;">Road Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roads as $r): ?>
                        <tr style="<?= $r['status'] === 'Closed' ? 'background:#fff1f2;' : '' ?>">
                            <td style="font-family:var(--font-mono); font-weight:700;">
                                #<?= $r['road_id'] ?>
                            </td>
                            <td>
                                <strong><?= sanitize($r['from_name']) ?></strong>
                                <div style="font-size:0.75rem; color:var(--color-text-muted);">
                                    <?= sanitize($r['from_type']) ?> (<code><?= sanitize($r['from_location_id']) ?></code>)
                                </div>
                            </td>
                            <td>
                                <strong><?= sanitize($r['to_name']) ?></strong>
                                <div style="font-size:0.75rem; color:var(--color-text-muted);">
                                    <?= sanitize($r['to_type']) ?> (<code><?= sanitize($r['to_location_id']) ?></code>)
                                </div>
                            </td>
                            <td>
                                <span style="font-family:var(--font-mono); font-weight:700; color:<?= $r['status'] === 'Closed' ? 'var(--color-text-muted)' : 'var(--color-emergency)' ?>;">
                                    <?= $r['travel_time'] ?> mins
                                </span>
                            </td>
                            <td style="font-size:0.8rem; color:var(--color-text-muted);">
                                <?= $r['is_bidirectional'] ? '⬌ Bidirectional' : '➔ One-Way' ?>
                            </td>
                            <td>
                                <?= render_status_badge($r['status']) ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <?php if ($r['status'] === 'Open'): ?>
                                    <form method="POST" action="<?= BASE_URL ?>actions/close-road.php" style="display:inline;"
                                          data-confirm="Close Road Segment #<?= $r['road_id'] ?> (<?= sanitize($r['from_name']) ?> ⬌ <?= sanitize($r['to_name']) ?>)?<br><br>The Dijkstra pathfinding engine will immediately bypass this road."
                                          data-confirm-title="Confirm Road Closure"
                                          data-confirm-btn="Close Road Segment"
                                          data-confirm-class="btn-danger">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="road_id" value="<?= $r['road_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            🚧 Close Road
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL ?>actions/reopen-road.php" style="display:inline;"
                                          data-confirm="Reopen Road Segment #<?= $r['road_id'] ?> (<?= sanitize($r['from_name']) ?> ⬌ <?= sanitize($r['to_name']) ?>) for emergency traffic?"
                                          data-confirm-title="Confirm Road Reopening"
                                          data-confirm-btn="Reopen Road"
                                          data-confirm-class="btn-success">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="road_id" value="<?= $r['road_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            ✅ Reopen Road
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
