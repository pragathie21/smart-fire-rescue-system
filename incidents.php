<?php
/**
 * Smart Fire and Rescue Response System
 * Manage Incidents - Prioritized Incident Log & CAD Management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Incident.php';

$pageTitle = 'Manage Emergency Incidents';

$incidentModel = new Incident();

$statusFilter  = $_GET['status'] ?? 'all';
$stationFilter = $_GET['station'] ?? 'all';
$search        = trim($_GET['search'] ?? '');

$incidents = $incidentModel->getAll($statusFilter, $search);
$stats     = $incidentModel->getStatistics();

// Optional filter by originating response station
if ($stationFilter !== 'all') {
    $incidents = array_values(array_filter($incidents, fn($i) => ($i['assigned_station_id'] ?? '') === $stationFilter));
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar);">
            📋 Emergency Incidents Master Log
        </h1>
        <p style="color:var(--color-text-muted); font-size:0.88rem;">
            Deterministic 4-tier triage priority order: Critical Infrastructure &bull; Intensity &bull; Exposure &bull; Timestamp.
        </p>
    </div>
    <div style="display:flex; gap:12px;">
        <a href="<?= BASE_URL ?>report-incident.php" class="btn btn-emergency-cta">
            <span>🚨</span> Log New Emergency
        </a>
    </div>
</div>

<!-- Filter and Search Toolbar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="<?= BASE_URL ?>incidents.php" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <input type="hidden" name="station" value="<?= sanitize($stationFilter) ?>">
            
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <!-- Status Filters -->
                <a href="<?= BASE_URL ?>incidents.php?status=all&station=<?= urlencode($stationFilter) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                    All (<?= $stats['total'] ?>)
                </a>
                <a href="<?= BASE_URL ?>incidents.php?status=Pending&station=<?= urlencode($stationFilter) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $statusFilter === 'Pending' ? 'btn-warning' : 'btn-secondary' ?>">
                    Pending Triage (<?= $stats['pending'] ?>)
                </a>
                <a href="<?= BASE_URL ?>incidents.php?status=Assigned&station=<?= urlencode($stationFilter) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $statusFilter === 'Assigned' ? 'btn-primary' : 'btn-secondary' ?>">
                    Assigned (<?= $stats['assigned'] ?>)
                </a>
                <a href="<?= BASE_URL ?>incidents.php?status=Completed&station=<?= urlencode($stationFilter) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $statusFilter === 'Completed' ? 'btn-success' : 'btn-secondary' ?>">
                    Completed (<?= $stats['completed'] ?>)
                </a>

                <!-- Station Filter Divider & Buttons -->
                <span style="color:#cbd5e1; margin:0 4px;">|</span>
                <span style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">Station:</span>
                <a href="<?= BASE_URL ?>incidents.php?status=<?= urlencode($statusFilter) ?>&station=all<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $stationFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.78rem; padding:4px 8px;">
                    All Bases
                </a>
                <a href="<?= BASE_URL ?>incidents.php?status=<?= urlencode($statusFilter) ?>&station=StationA<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $stationFilter === 'StationA' ? 'btn-danger' : 'btn-secondary' ?>" style="font-size:0.78rem; padding:4px 8px;">
                    🏢 Station A
                </a>
                <a href="<?= BASE_URL ?>incidents.php?status=<?= urlencode($statusFilter) ?>&station=StationB<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                   class="btn btn-sm <?= $stationFilter === 'StationB' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:0.78rem; padding:4px 8px;">
                    🏢 Station B
                </a>
            </div>

            <div style="display:flex; gap:8px;">
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search ID, Location..." class="form-control" style="width:200px; padding:6px 12px; font-size:0.85rem;">
                <button type="submit" class="btn btn-sm btn-secondary">Search</button>
                <?php if ($search || $statusFilter !== 'all' || $stationFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>incidents.php" class="btn btn-sm btn-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Incidents Master Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><span>🛡️</span> Prioritized Incident Roster (<?= count($incidents) ?> found)</h3>
            <p class="card-subtitle">Critical Infrastructure incidents rank strictly above non-critical incidents</p>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($incidents)): ?>
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h4 class="empty-title">No Incident Records Found</h4>
                <p class="empty-desc">
                    <?= $search || $statusFilter !== 'all' ? 'No incident matched your search or status filter criteria.' : 'There are currently 0 incidents logged in the MySQL database.' ?>
                </p>
                <a href="<?= BASE_URL ?>report-incident.php" class="btn btn-emergency-cta">Submit New Incident Report</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Incident ID</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Priority Tier</th>
                            <th>Priority Rationale</th>
                            <th>Required Equipment</th>
                            <th>Status</th>
                            <th>Reported Time</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($incidents as $inc): 
                            $effectiveTier = !empty($inc['override_severity']) ? $inc['override_severity'] : $inc['severity_tier'];
                        ?>
                            <tr>
                                <td style="font-family:var(--font-mono); font-weight:700; color:var(--color-text-muted);">
                                    #<?= $rank++ ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>incident-details.php?id=<?= urlencode((string)$inc['incident_id']) ?>" style="font-family:var(--font-mono); font-weight:700;">
                                        #<?= $inc['incident_id'] ?>
                                    </a>
                                </td>
                                <td>
                                    <span style="font-weight:600;">
                                        <?php if ($inc['incident_type'] === 'Fire'): ?>🔥 
                                        <?php elseif ($inc['incident_type'] === 'Rescue'): ?>🛟 
                                        <?php else: ?>🚑 <?php endif; ?>
                                        <?= sanitize($inc['incident_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= sanitize($inc['location_name'] ?? $inc['location_id']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--color-text-muted);"><?= sanitize($inc['location_type'] ?? '') ?></div>
                                    <?php if (!empty($inc['assigned_unit_id'])): ?>
                                        <div style="margin-top:4px; font-size:0.75rem; display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                                            <span style="font-family:var(--font-mono); font-weight:700; color:var(--color-primary);">🚒 <?= sanitize($inc['assigned_unit_id']) ?></span>
                                            <?php if (!empty($inc['assigned_station_id'])): ?>
                                                <span class="badge" style="font-size:0.68rem; font-weight:700; background:<?= $inc['assigned_station_id'] === 'StationA' ? '#fee2e2' : '#dbeafe' ?>; color:<?= $inc['assigned_station_id'] === 'StationA' ? '#b91c1c' : '#1d4ed8' ?>;">
                                                    🏢 <?= sanitize($inc['assigned_station_id']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= render_priority_badge($effectiveTier) ?>
                                    <div style="font-size:0.72rem; color:var(--color-text-muted); margin-top:2px; font-family:var(--font-mono);">
                                        Score: <?= $inc['priority_score'] ?>/100
                                    </div>
                                </td>
                                <td style="max-width:260px; font-size:0.8rem; color:#475569; line-height:1.3;">
                                    <?= sanitize($inc['assessment_reason'] ?? '') ?>
                                </td>
                                <td>
                                    <div style="display:flex; flex-wrap:wrap; gap:4px; max-width:200px;">
                                        <?php foreach ($inc['equipment_array'] as $eq): ?>
                                            <?= render_equipment_badge($eq) ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <?= render_status_badge($inc['status']) ?>
                                </td>
                                <td style="font-size:0.8rem; white-space:nowrap;">
                                    <div><?= time_ago($inc['reported_at']) ?></div>
                                    <div style="font-size:0.72rem; color:var(--color-text-muted);"><?= date('H:i:s', strtotime($inc['reported_at'])) ?></div>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?= BASE_URL ?>incident-details.php?id=<?= urlencode((string)$inc['incident_id']) ?>" class="btn btn-sm btn-secondary">
                                        View
                                    </a>
                                    <?php if ($inc['status'] === 'Pending'): ?>
                                        <a href="<?= BASE_URL ?>dispatch.php?incident_id=<?= urlencode((string)$inc['incident_id']) ?>" class="btn btn-sm btn-primary">
                                            Dispatch
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
