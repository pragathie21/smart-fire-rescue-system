<?php
/**
 * Smart Fire and Rescue Response System
 * Response Units - Fleet Status & Station Equipment Manifest
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/ResponseUnit.php';

$pageTitle = 'Response Units Fleet Management';

$unitModel = new ResponseUnit();
$units = $unitModel->getAll();
$stats = $unitModel->getStatistics();

// Group units by station
$stationMap = [
    'StationA' => [
        'name'     => 'Central Fire Station (HQ)',
        'location' => 'Downtown Sudar (StationA base)',
        'phone'    => '911-SUDAR-CENTRAL',
        'units'    => []
    ],
    'StationB' => [
        'name'     => 'North Fire Station',
        'location' => 'North Ridge Sector (StationB base)',
        'phone'    => '911-SUDAR-NORTH',
        'units'    => []
    ]
];

foreach ($units as $u) {
    if (isset($stationMap[$u['station_id']])) {
        $stationMap[$u['station_id']]['units'][] = $u;
    }
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar);">
            🚒 Emergency Response Fleet & Units
        </h1>
        <p style="color:var(--color-text-muted); font-size:0.88rem;">
            Station readiness, onboard equipment manifests, and CAD active mission tracking.
        </p>
    </div>
    <div style="display:flex; gap:12px;">
        <a href="<?= BASE_URL ?>dispatch.php" class="btn btn-primary">
            <span>📡</span> Open CAD Dispatch
        </a>
    </div>
</div>

<!-- Fleet Status Summary Cards -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Total Units</span>
            <span class="stat-value"><?= $stats['total'] ?></span>
        </div>
        <div class="stat-icon stat-icon-blue">🚒</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Available for Call</span>
            <span class="stat-value" style="color:var(--color-success);"><?= $stats['available'] ?></span>
        </div>
        <div class="stat-icon stat-icon-green">🟢</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Currently Busy</span>
            <span class="stat-value" style="color:var(--color-emergency);"><?= $stats['busy'] ?></span>
        </div>
        <div class="stat-icon stat-icon-orange">🔴</div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-title">Out of Service</span>
            <span class="stat-value" style="color:var(--color-text-muted);"><?= $stats['out_of_service'] ?></span>
        </div>
        <div class="stat-icon stat-icon-amber">🔧</div>
    </div>
</div>

<!-- Stations and Unit Cards -->
<?php foreach ($stationMap as $stId => $station): ?>
    <div class="card" style="margin-bottom: 28px;">
        <div class="card-header" style="background:#f8fafc;">
            <div>
                <h3 class="card-title">
                    <span>🏢</span> <?= sanitize($station['name']) ?>
                </h3>
                <p class="card-subtitle">
                    Base: <?= sanitize($station['location']) ?> &bull; Hotline: <?= sanitize($station['phone']) ?>
                </p>
            </div>
            <span class="badge-tech">Station Node: <?= sanitize($stId) ?></span>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
                <?php foreach ($station['units'] as $u): ?>
                    <div style="border:1px solid var(--color-card-border); border-radius:var(--radius-lg); padding:20px; background:#ffffff; box-shadow:var(--shadow-sm); display:flex; flex-direction:column; justify-content:space-between; gap:16px;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:48px; height:48px; border-radius:var(--radius-md); background:<?= $u['unit_type'] === 'Fire Engine' ? '#fee2e2' : '#e0e7ff' ?>; display:flex; align-items:center; justify-content:center; font-size:1.6rem;">
                                        <?= $u['unit_type'] === 'Fire Engine' ? '🚒' : '🚐' ?>
                                    </div>
                                    <div>
                                        <h4 style="font-size:1.15rem; font-weight:800; font-family:var(--font-mono); color:var(--color-sidebar); margin-bottom:2px;">
                                            <?= sanitize($u['unit_id']) ?>
                                        </h4>
                                        <p style="font-size:0.8rem; color:var(--color-text-muted); font-weight:600;">
                                            <?= sanitize($u['unit_type']) ?>
                                        </p>
                                    </div>
                                </div>
                                <?= render_status_badge($u['status']) ?>
                            </div>

                            <div style="margin-bottom:14px;">
                                <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:6px;">
                                    Certified Equipment Capabilities:
                                </span>
                                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                    <?php foreach ($u['equipment_array'] as $eq): ?>
                                        <?= render_equipment_badge($eq) ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php if ($u['status'] === 'Available'): ?>
                                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:var(--radius-sm); padding:10px 14px; font-size:0.8rem; color:#166534; display:flex; align-items:center; gap:8px;">
                                    <span>🟢</span>
                                    <span>Ready for emergency assignment at base.</span>
                                </div>
                            <?php elseif ($u['status'] === 'Busy'): ?>
                                <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:var(--radius-sm); padding:10px 14px; font-size:0.8rem; color:#991b1b;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span><strong>Active Mission:</strong> <?= sanitize($u['active_incident_id']) ?></span>
                                        <a href="<?= BASE_URL ?>incident-details.php?id=<?= urlencode($u['active_incident_id']) ?>" style="color:#b91c1c; font-weight:700; text-decoration:underline;">
                                            View CAD &rarr;
                                        </a>
                                    </div>
                                    <div style="font-size:0.74rem; color:#b91c1c; margin-top:4px;">
                                        Unit cannot take secondary calls until mission completes.
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:var(--radius-sm); padding:10px 14px; font-size:0.8rem; color:#475569;">
                                    <span>🔧</span> Unit offline for maintenance.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
