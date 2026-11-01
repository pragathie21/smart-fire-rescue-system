<?php
/**
 * Smart Fire and Rescue Response System
 * CAD Dispatch Center - Intelligent Dijkstra Routing & Multi-Station Unit Assignment
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Incident.php';
require_once __DIR__ . '/classes/ResponseUnit.php';
require_once __DIR__ . '/classes/DispatchService.php';
require_once __DIR__ . '/classes/RoadNetwork.php';
require_once __DIR__ . '/classes/RoutePlanner.php';

$pageTitle = 'CAD Dispatch Center';

$incidentModel   = new Incident();
$unitModel       = new ResponseUnit();
$dispatchService = new DispatchService();
$roadNetwork     = new RoadNetwork();
$routePlanner    = new RoutePlanner();

// Fetch pending incidents
$pendingIncidents = $incidentModel->getPendingIncidents();

// Fetch active missions
$activeMissions = $dispatchService->getAllDispatchRecords('Active');

// Fetch fleet & stats
$units       = $unitModel->getAll();
$unitStats   = $unitModel->getStatistics();
$incStats    = $incidentModel->getStatistics();
$activeCount = count($activeMissions);

// Group units by station
$stations = [
    'StationA' => [
        'name'     => 'StationA Central (HQ)',
        'location' => 'Downtown Central Sector',
        'units'    => []
    ],
    'StationB' => [
        'name'     => 'StationB North',
        'location' => 'North Ridge Sector',
        'units'    => []
    ]
];
foreach ($units as $u) {
    if (isset($stations[$u['station_id']])) {
        $stations[$u['station_id']]['units'][] = $u;
    }
}

// Pre-calculate unit assessments for each pending incident
$incidentAssessments = [];
foreach ($pendingIncidents as $inc) {
    try {
        $incidentAssessments[$inc['incident_id']] = $dispatchService->getUnitAssessmentsForIncident((int)$inc['incident_id']);
    } catch (Exception $e) {
        $incidentAssessments[$inc['incident_id']] = null;
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Action Header -->
<div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar); display:flex; align-items:center; gap:8px;">
            <span>📡</span> CAD Emergency Dispatch Center
        </h1>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <form method="POST" action="<?= BASE_URL ?>actions/auto-dispatch.php" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="dispatch.php">
            <button type="submit" class="btn btn-primary" style="background:#0f172a; border-color:#334155;">
                <span>⚡</span> Run CAD Auto-Dispatch
            </button>
        </form>
        <a href="<?= BASE_URL ?>report-incident.php" class="btn btn-emergency-cta">
            <span>🚨</span> Log New Incident
        </a>
    </div>
</div>

<!-- 1. Real-time Status Metric Cards (Using proven Dashboard layout) -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Pending Queue</span>
            <span class="stat-value" style="color:<?= count($pendingIncidents) > 0 ? 'var(--color-emergency)' : 'var(--color-sidebar)' ?>;">
                <?= count($pendingIncidents) ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-red">🚨</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Active Missions</span>
            <span class="stat-value" style="color:var(--color-warning);">
                <?= $activeCount ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-orange">🚒</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Available Fleet</span>
            <span class="stat-value" style="color:var(--color-success);">
                <?= $unitStats['by_status']['Available'] ?? 0 ?> / <?= $unitStats['total'] ?? 4 ?>
            </span>
        </div>
        <div class="stat-icon stat-icon-green">⚡</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Road Engine</span>
            <span class="stat-value" style="font-size:1.35rem; color:var(--color-primary); padding-top:4px;">
                Dijkstra Active
            </span>
        </div>
        <div class="stat-icon stat-icon-blue">🛣️</div>
    </div>
</div>

<!-- 2. Active Missions In-Progress (Top Priority When Active) -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                <span>🚒</span> Active Emergency Missions
                <span class="badge" style="background:#f97316; color:#fff; font-size:0.75rem; padding:3px 8px;">
                    <?= $activeCount ?> Deployed
                </span>
            </h2>
            <p class="card-subtitle">
                Live field deployments, computed turn-by-turn Dijkstra routes, and real-time mission completion.
            </p>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($activeMissions)): ?>
            <div style="padding: 32px 20px; text-align:center;">
                <div style="font-size: 2.2rem; margin-bottom: 6px;">🛡️</div>
                <h3 style="font-size: 1.05rem; font-weight:700; color:var(--color-sidebar); margin-bottom: 4px;">No Active Field Deployments</h3>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mission ID</th>
                            <th>Unit &amp; Station</th>
                            <th>Emergency Target</th>
                            <th>Turn-by-Turn Dijkstra Route</th>
                            <th>Est. Travel Time</th>
                            <th>Dispatched At</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeMissions as $m): ?>
                            <tr>
                                <td>
                                    <strong style="color:var(--color-sidebar); font-family:var(--font-mono);">#DISP-<?= (int)$m['dispatch_id'] ?></strong>
                                    <div style="margin-top:2px;">
                                        <span class="badge" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa; font-size:0.7rem;">
                                            En Route / Active
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--color-sidebar);">
                                         <?= htmlspecialchars($m['unit_id']) ?>
                                    </div>
                                    <div style="font-size:0.76rem; color:var(--color-text-muted);">
                                        <?= htmlspecialchars($m['unit_type'] ?? 'Fire Engine') ?> &bull; <?= htmlspecialchars($m['station_name'] ?? $m['station_id']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600;">
                                        <a href="<?= BASE_URL ?>incident-details.php?id=<?= (int)$m['incident_id'] ?>" style="color:var(--color-primary); text-decoration:none;">
                                            #<?= (int)$m['incident_id'] ?> &bull; <?= htmlspecialchars($m['incident_type']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size:0.76rem; color:var(--color-text-muted); margin-top:2px;">
                                         <?= htmlspecialchars($m['location_name'] ?? $m['location_id']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $routeRaw = $m['route_taken'] ?? '';
                                    $hops = array_map('trim', explode('->', $routeRaw));
                                    ?>
                                    <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                        <?php foreach ($hops as $idx => $hop): ?>
                                            <span class="badge" style="background:<?= $idx === 0 ? '#eff6ff' : ($idx === count($hops)-1 ? '#fef2f2' : '#f8fafc') ?>; color:<?= $idx === 0 ? '#1d4ed8' : ($idx === count($hops)-1 ? '#dc2626' : '#334155') ?>; border:1px solid #cbd5e1; font-size:0.72rem; font-family:var(--font-mono);">
                                                <?= htmlspecialchars($hop) ?>
                                            </span>
                                            <?php if ($idx < count($hops) - 1): ?>
                                                <span style="color:#94a3b8; font-size:0.72rem;">➔</span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color:#2563eb; font-size:0.92rem; font-family:var(--font-mono);">
                                        ⏱️ <?= number_format((float)($m['estimated_travel_time'] ?? 0), 1) ?> mins
                                    </strong>
                                </td>
                                <td style="font-size:0.78rem; color:var(--color-text-muted);">
                                    <?= date('M d, H:i:s', strtotime($m['dispatched_at'] ?? $m['dispatch_time'] ?? 'now')) ?>
                                </td>
                                <td style="text-align:right;">
                                    <form method="POST" action="<?= BASE_URL ?>actions/complete-mission.php" onsubmit="return confirm('Complete Mission #<?= $m['dispatch_id'] ?> and release Unit <?= htmlspecialchars($m['unit_id']) ?> back to station?');" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="dispatch_id" value="<?= (int)$m['dispatch_id'] ?>">
                                        <input type="hidden" name="incident_id" value="<?= (int)$m['incident_id'] ?>">
                                        <input type="hidden" name="completion_notes" value="Resolved via CAD Dispatch Center">
                                        <input type="hidden" name="redirect" value="dispatch.php">
                                        <button type="submit" class="btn btn-sm btn-success" style="padding:4px 12px; font-weight:700; font-size:0.8rem;">
                                            ✓ Complete Mission
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 3. Pending Emergencies Priority Queue -->
<?php if (empty($pendingIncidents)): ?>
    <!-- Compact, Clean Notice When Queue is Empty -->
    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:var(--radius-md); padding:14px 20px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="font-size:1.4rem;">✅</span>
            <div>
                <strong style="color:#15803d; font-size:0.95rem;">Pending Queue Clear: No Incidents Waiting</strong>
                <div style="font-size:0.78rem; color:#166534;">All reported emergencies are assigned to units or resolved. Fleet is standing by.</div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>report-incident.php" class="btn btn-emergency-cta btn-sm" style="font-size:0.82rem; padding:6px 14px;">
            <span>🚨</span> Log New Incident
        </a>
    </div>
<?php else: ?>
    <!-- Structured Table When Queue Has Items -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                    <span>🚨</span> Pending Incidents Priority Queue
                    <span class="badge" style="background:#ef4444; color:#fff; font-size:0.75rem; padding:3px 8px;">
                        <?= count($pendingIncidents) ?> Waiting
                    </span>
                </h2>
            </div>
            <form method="POST" action="<?= BASE_URL ?>actions/auto-dispatch.php">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="dispatch.php">
                <button type="submit" class="btn btn-sm btn-primary">
                    ⚡ Auto-Dispatch All Pending
                </button>
            </form>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Priority &amp; Incident</th>
                            <th>Type &amp; Severity</th>
                            <th>Location &amp; Hazards</th>
                            <th>Required Equipment</th>
                            <th>Recommended Unit (Dijkstra)</th>
                            <th style="text-align:right;">CAD Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingIncidents as $inc): 
                            $assessment = $incidentAssessments[$inc['incident_id']] ?? null;
                            $bestUnit = (!empty($assessment['eligible'])) ? $assessment['eligible'][0] : null;
                        ?>
                            <tr style="border-left: 4px solid #ef4444;">
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:800; font-size:0.85rem; padding:4px 8px;">
                                            Score <?= number_format($inc['priority_score'], 1) ?>
                                        </span>
                                        <strong>#<?= (int)$inc['incident_id'] ?></strong>
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--color-text-muted); margin-top:2px;">
                                        <?= date('M d, H:i', strtotime($inc['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--color-sidebar);">
                                        <?= htmlspecialchars($inc['incident_type']) ?>
                                    </div>
                                    <div style="display:flex; gap:4px; margin-top:4px;">
                                        <?= render_severity_badge($inc['severity_tier'] ?? 'Medium', $inc['override_severity'] ?? null) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight:600;">
                                        <?= htmlspecialchars($inc['location_name'] ?? $inc['location_id']) ?>
                                    </div>
                                    <div style="display:flex; gap:4px; flex-wrap:wrap; margin-top:4px;">
                                        <?php if (!empty($inc['critical_infrastructure'])): ?>
                                            <span class="badge" style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-size:0.68rem;">
                                                ⚠️ Critical Infra
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($inc['exposure_type']) && $inc['exposure_type'] !== 'None'): ?>
                                            <span class="badge" style="background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-size:0.68rem;">
                                                <?= htmlspecialchars($inc['exposure_type']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $eqList = json_decode($inc['equipment_required'] ?? '[]', true) ?: [];
                                    if (empty($eqList)): ?>
                                        <span style="color:var(--color-text-muted); font-size:0.8rem;">Standard Gear</span>
                                    <?php else: ?>
                                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                            <?php foreach ($eqList as $eq): ?>
                                                <span class="badge" style="background:#f1f5f9; color:#334155; font-size:0.68rem;">
                                                    <?= htmlspecialchars($eq) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($bestUnit): ?>
                                        <div style="font-weight:700; color:#1e293b; display:flex; align-items:center; gap:6px;">
                                            <span style="color:#16a34a;">●</span>
                                            <?= htmlspecialchars($bestUnit['unit']['unit_id']) ?>
                                            <span style="font-weight:400; font-size:0.8rem; color:var(--color-text-muted);">
                                                (<?= htmlspecialchars($bestUnit['unit']['station_id']) ?>)
                                            </span>
                                        </div>
                                        <div style="font-size:0.76rem; color:#2563eb; font-weight:600; margin-top:2px;">
                                            ⏱️ ETA: <?= number_format($bestUnit['travel_time'], 1) ?> mins
                                        </div>
                                        <div style="font-size:0.7rem; color:var(--color-text-muted); font-family:var(--font-mono); margin-top:2px;" title="<?= htmlspecialchars($bestUnit['route_string']) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($bestUnit['route_string'], 0, 32, '...')) ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size:0.8rem; color:#dc2626; font-weight:600;">
                                            ⚠️ No Compatible Unit Available
                                        </div>
                                        <div style="font-size:0.72rem; color:var(--color-text-muted);">
                                            Waiting for unit to return or road to reopen
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($bestUnit): ?>
                                        <form method="POST" action="<?= BASE_URL ?>actions/auto-dispatch.php" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="redirect" value="dispatch.php">
                                            <button type="submit" class="btn btn-primary btn-sm" style="font-size:0.8rem; padding:6px 12px;">
                                                ⚡ Dispatch <?= htmlspecialchars($bestUnit['unit']['unit_id']) ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>incident-details.php?id=<?= (int)$inc['incident_id'] ?>" class="btn btn-secondary btn-sm" style="font-size:0.8rem;">
                                            Inspect
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Station Readiness & Fleet Allocation Grid -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom: 24px;">
    <!-- Station A -->
    <div class="card">
        <div class="card-header" style="background:#f8fafc; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="font-size:1.02rem; font-weight:800; color:var(--color-sidebar); margin-bottom:2px;">
                    🏢 Station A — Central HQ
                </h3>
                <span style="font-size:0.76rem; color:var(--color-text-muted);">Downtown Central Base (StationA node)</span>
            </div>
            <span class="badge" style="background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; font-size:0.72rem; font-weight:700;">
                OPERATIONAL
            </span>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($stations['StationA']['units'] as $u): 
                    $isAvail = ($u['status'] === 'Available');
                ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong style="font-size:0.92rem; color:var(--color-sidebar);"><?= htmlspecialchars($u['unit_id']) ?></strong>
                                <span style="font-size:0.78rem; color:var(--color-text-muted);">(<?= htmlspecialchars($u['unit_type']) ?>)</span>
                            </div>
                            <div style="font-size:0.74rem; color:#64748b; margin-top:2px;">
                                Gear: <?= implode(' &bull; ', $u['equipment_array']) ?>
                            </div>
                        </div>
                        <span class="badge" style="background:<?= $isAvail ? '#dcfce7' : ($u['status'] === 'Dispatched' ? '#ffedd5' : '#fee2e2') ?>; color:<?= $isAvail ? '#15803d' : ($u['status'] === 'Dispatched' ? '#c2410c' : '#b91c1c') ?>; font-weight:700;">
                            <?= htmlspecialchars($u['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Station B -->
    <div class="card">
        <div class="card-header" style="background:#f8fafc; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="font-size:1.02rem; font-weight:800; color:var(--color-sidebar); margin-bottom:2px;">
                    🏢 Station B — North Station
                </h3>
                <span style="font-size:0.76rem; color:var(--color-text-muted);">North Ridge Sector Base (StationB node)</span>
            </div>
            <span class="badge" style="background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; font-size:0.72rem; font-weight:700;">
                OPERATIONAL
            </span>
        </div>
        <div class="card-body" style="padding:16px;">
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($stations['StationB']['units'] as $u): 
                    $isAvail = ($u['status'] === 'Available');
                ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <strong style="font-size:0.92rem; color:var(--color-sidebar);"><?= htmlspecialchars($u['unit_id']) ?></strong>
                                <span style="font-size:0.78rem; color:var(--color-text-muted);">(<?= htmlspecialchars($u['unit_type']) ?>)</span>
                            </div>
                            <div style="font-size:0.74rem; color:#64748b; margin-top:2px;">
                                Gear: <?= implode(' &bull; ', $u['equipment_array']) ?>
                            </div>
                        </div>
                        <span class="badge" style="background:<?= $isAvail ? '#dcfce7' : ($u['status'] === 'Dispatched' ? '#ffedd5' : '#fee2e2') ?>; color:<?= $isAvail ? '#15803d' : ($u['status'] === 'Dispatched' ? '#c2410c' : '#b91c1c') ?>; font-weight:700;">
                            <?= htmlspecialchars($u['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 5. Dijkstra Architecture Info Banner -->
<div class="card" style="background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%); border: 1px solid #bfdbfe;">
    <div class="card-body" style="padding:18px 22px;">
        <div style="display:flex; align-items:flex-start; gap:14px;">
            <div style="font-size:1.8rem; background:#dbeafe; padding:8px 12px; border-radius:10px;">🧠</div>
            <div>
                <h3 style="font-size:1rem; font-weight:800; color:#1e3a8a; margin-bottom:4px;">
                    Dijkstra Optimal Pathfinding &bull; Dynamic Traffic Routing
                </h3>
                <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:0.8rem; color:#475569;">
                    <span>✅ Multi-Station Comparison (StationA vs StationB)</span>
                    <span>✅ Real-Time Road Closure Avoidance</span>
                    <span>✅ Unit Hardware &amp; Equipment Verification</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
