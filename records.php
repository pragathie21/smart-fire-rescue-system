<?php
/**
 * Smart Fire and Rescue Response System
 * Dispatch Records & Historical Mission Audit
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/DispatchService.php';

$pageTitle = 'Dispatch Records & Mission History';

$dispatchService = new DispatchService();
$filterStatus = $_GET['status'] ?? 'all';

$records = $dispatchService->getAllDispatchRecords($filterStatus);
$activeCount = $dispatchService->getActiveMissionsCount();

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar);">
            📜 Dispatch Operations & Mission History
        </h1>
        <p style="color:var(--color-text-muted); font-size:0.88rem;">
            Auditable log of CAD unit dispatches, Dijkstra routing selections, and emergency response completion times.
        </p>
    </div>
    <div style="display:flex; gap:12px;">
        <a href="<?= BASE_URL ?>dispatch.php" class="btn btn-primary btn-sm">
            <span>📡</span> CAD Dispatch Center
        </a>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding:16px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; gap:8px;">
                <a href="<?= BASE_URL ?>records.php?status=all" class="btn btn-sm <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                    All Missions (<?= count($dispatchService->getAllDispatchRecords()) ?>)
                </a>
                <a href="<?= BASE_URL ?>records.php?status=Active" class="btn btn-sm <?= $filterStatus === 'Active' ? 'btn-emergency-cta' : 'btn-secondary' ?>">
                    Active Missions (<?= $activeCount ?>)
                </a>
                <a href="<?= BASE_URL ?>records.php?status=Completed" class="btn btn-sm <?= $filterStatus === 'Completed' ? 'btn-success' : 'btn-secondary' ?>">
                    Completed History
                </a>
            </div>

            <div style="font-size:0.85rem; color:var(--color-text-muted);">
                Showing <strong><?= count($records) ?></strong> records
            </div>
        </div>
    </div>
</div>

<!-- Dispatch Records Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><span>📡</span> Dispatch Archive</h3>
            <p class="card-subtitle">Permanent mission logs with Dijkstra paths and timestamps</p>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($records)): ?>
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h4 class="empty-title">No Dispatch Records Found</h4>
                <p class="empty-desc">
                    <?= $filterStatus !== 'all' ? 'No dispatch records matching this status.' : 'There are currently 0 dispatch operations in the database.' ?>
                </p>
                <a href="<?= BASE_URL ?>dispatch.php" class="btn btn-primary">Open Dispatch CAD</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Dispatch #</th>
                            <th>Incident</th>
                            <th>Emergency Type</th>
                            <th>Assigned Unit</th>
                            <th>Station Base</th>
                            <th>Dijkstra Travel Path</th>
                            <th>Est. Travel Time</th>
                            <th>Dispatched At</th>
                            <th>Completed At</th>
                            <th>Email Alert</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td style="font-family:var(--font-mono); font-weight:700;">
                                    #<?= $r['dispatch_id'] ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>incident-details.php?id=<?= urlencode((string)$r['incident_id']) ?>" style="font-family:var(--font-mono); font-weight:700;">
                                        #<?= $r['incident_id'] ?>
                                    </a>
                                    <div style="font-size:0.75rem; color:var(--color-text-muted);"><?= sanitize($r['location_name'] ?? $r['location_id']) ?></div>
                                </td>
                                <td>
                                    <strong><?= sanitize($r['incident_type']) ?></strong>
                                    <?php if ($r['critical_infrastructure']): ?>
                                        <div style="font-size:0.7rem; color:#dc2626; font-weight:700;">Critical Infra</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-family:var(--font-mono);"><?= sanitize($r['unit_id']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--color-text-muted);"><?= sanitize($r['unit_type']) ?></div>
                                </td>
                                <td>
                                    <?= sanitize($r['station_name']) ?>
                                </td>
                                <td style="max-width:260px; font-family:var(--font-mono); font-size:0.78rem; color:#334155;">
                                    <?= sanitize($r['route']) ?>
                                </td>
                                <td style="font-family:var(--font-mono); font-weight:700; color:var(--color-emergency);">
                                    <?= $r['estimated_travel_time'] ?> mins
                                </td>
                                <td style="font-size:0.8rem; white-space:nowrap;">
                                    <?= format_datetime($r['dispatched_at']) ?>
                                </td>
                                <td style="font-size:0.8rem; white-space:nowrap;">
                                    <?php if ($r['completed_at']): ?>
                                        <span style="color:var(--color-success); font-weight:600;"><?= format_datetime($r['completed_at']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted); font-style:italic;">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= render_email_badge($r['email_status'], $r['email_error']) ?>
                                </td>
                                <td>
                                    <?= render_status_badge($r['status']) ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <?php if ($r['status'] === 'Active'): ?>
                                        <form method="POST" action="<?= BASE_URL ?>actions/complete-mission.php" style="display:inline;"
                                              data-confirm="Conclude Mission #<?= $r['dispatch_id'] ?>? Assigned unit <?= sanitize($r['unit_id']) ?> will become Available."
                                              data-confirm-title="Complete Emergency Mission"
                                              data-confirm-btn="Complete Mission"
                                              data-confirm-class="btn-success">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="dispatch_id" value="<?= $r['dispatch_id'] ?>">
                                            <input type="hidden" name="redirect" value="records.php">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                ✅ Complete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>incident-details.php?id=<?= urlencode((string)$r['incident_id']) ?>" class="btn btn-sm btn-secondary">
                                            Audit
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
