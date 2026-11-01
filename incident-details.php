<?php
/**
 * Smart Fire and Rescue Response System
 * Incident Details - Complete Triage Audit, GPS Map & Active CAD Mission Coordination
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/Incident.php';
require_once __DIR__ . '/classes/DispatchService.php';

$incidentId = (int)($_GET['id'] ?? 0);

if ($incidentId <= 0) {
    set_flash('danger', 'Valid Incident ID is required.');
    header("Location: " . BASE_URL . "incidents.php");
    exit;
}

$incidentModel = new Incident();
$incident = $incidentModel->getById($incidentId);

if (!$incident) {
    set_flash('danger', "Incident #{$incidentId} was not found in the database.");
    header("Location: " . BASE_URL . "incidents.php");
    exit;
}

$pageTitle = "Incident #{$incident['incident_id']} Details";
$dispatch = $incident['dispatch'] ?? null;
$currentUser = current_user();

include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto;">
    <!-- Top Header & Breadcrumbs -->
    <div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div style="display:flex; align-items:center; gap:14px;">
            <a href="<?= BASE_URL ?>incidents.php" class="btn btn-sm btn-secondary">&larr; All Incidents</a>
            <div>
                <h1 style="font-size: 1.6rem; font-weight:800; color:var(--color-sidebar); display:flex; align-items:center; gap:10px;">
                    Incident <span style="font-family:var(--font-mono); color:var(--color-primary);">#<?= $incident['incident_id'] ?></span>
                    <?= render_status_badge($incident['status']) ?>
                    <?php if (!empty($incident['override_severity'])): ?>
                        <span style="font-size:0.75rem; background:#fef3c7; border:1px solid #fde68a; color:#92400e; padding:3px 8px; border-radius:4px; font-weight:700;">
                            ✏️ Triage Corrected
                        </span>
                    <?php endif; ?>
                </h1>
                <p style="color:var(--color-text-muted); font-size:0.85rem;">
                    Reported on <?= format_datetime($incident['reported_at']) ?> (<?= time_ago($incident['reported_at']) ?>) &bull; Town of <?= TOWN_NAME ?>
                </p>
            </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <?php if ($incident['status'] === 'Pending'): ?>
                <a href="<?= BASE_URL ?>dispatch.php?incident_id=<?= urlencode((string)$incident['incident_id']) ?>" class="btn btn-emergency-cta">
                    <span>📡</span> Open CAD Dispatch
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Mission Banner (When Unit Assigned) -->
    <?php if ($dispatch && $dispatch['status'] === 'Active'): ?>
        <div class="card" style="border: 2px solid var(--color-primary); background:#eff6ff; margin-bottom: 24px; box-shadow:0 8px 20px -4px rgba(37,99,235,0.15);">
            <div class="card-body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div style="display:flex; align-items:flex-start; gap:16px;">
                    <div style="font-size:2.6rem;">🚒</div>
                    <div>
                        <div style="font-size:0.75rem; font-weight:800; color:var(--color-primary); letter-spacing:0.06em; text-transform:uppercase;">
                            ACTIVE CAD MISSION &bull; DISPATCH #<?= $dispatch['dispatch_id'] ?>
                        </div>
                        <h3 style="font-size:1.25rem; font-weight:800; color:#1e3a8a; margin: 2px 0;">
                            Unit <?= sanitize($dispatch['unit_id']) ?> (<?= sanitize($dispatch['unit_type']) ?>) Dispatched
                        </h3>
                        <p style="font-size:0.88rem; color:#1e40af; margin-top:2px;">
                            Originating Station: <strong><?= sanitize($dispatch['station_name']) ?></strong> &bull; Estimated Arrival: <strong><?= $dispatch['estimated_travel_time'] ?> mins</strong>
                        </p>

                        <!-- Route Path -->
                        <div style="font-family:var(--font-mono); font-size:0.82rem; background:#ffffff; border:1px solid #bfdbfe; padding:6px 10px; border-radius:var(--radius-sm); margin-top:8px; color:#1e3a8a; display:inline-block;">
                            Route: <strong><?= sanitize($dispatch['route']) ?></strong>
                        </div>

                        <!-- Email Notification Delivery Status (Requirement 6) -->
                        <div style="margin-top:10px; font-size:0.82rem; display:flex; align-items:center; gap:8px;">
                            <span>Notification Delivery:</span>
                            <?= render_email_badge($dispatch['email_status'], $dispatch['email_error']) ?>
                            <?php if ($dispatch['email_status'] === 'Failed' || $dispatch['email_status'] === 'Pending'): ?>
                                <form method="POST" action="<?= BASE_URL ?>actions/retry-email.php" style="display:inline-block;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="dispatch_id" value="<?= $dispatch['dispatch_id'] ?>">
                                    <input type="hidden" name="incident_id" value="<?= $incident['incident_id'] ?>">
                                    <input type="hidden" name="redirect" value="incident-details.php?id=<?= $incident['incident_id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding:2px 8px; font-size:0.75rem;">
                                        🔄 Retry Email
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Lifecycle Actions (Requirement 8) -->
                <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                    <?php if ($incident['status'] === 'Dispatched'): ?>
                        <form method="POST" action="<?= BASE_URL ?>actions/update-incident-status.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="incident_id" value="<?= $incident['incident_id'] ?>">
                            <input type="hidden" name="status" value="In Progress">
                            <button type="submit" class="btn btn-primary">
                                <span>⚡</span> Mark On-Scene (In Progress)
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>actions/complete-mission.php"
                          data-confirm="Conclude Mission #<?= $dispatch['dispatch_id'] ?>? This will mark Incident #<?= $incident['incident_id'] ?> as Completed and release Unit <?= sanitize($dispatch['unit_id']) ?> back to Available."
                          data-confirm-title="Confirm Mission Resolution"
                          data-confirm-btn="Complete & Release Unit"
                          data-confirm-class="btn-success">
                        <?= csrf_field() ?>
                        <input type="hidden" name="dispatch_id" value="<?= $dispatch['dispatch_id'] ?>">
                        <input type="hidden" name="redirect" value="incident-details.php?id=<?= $incident['incident_id'] ?>">
                        <button type="submit" class="btn btn-success btn-lg" style="box-shadow: 0 4px 12px rgba(22,163,74,0.3);">
                            <span>✅</span> Conclude & Complete Mission
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Two-Column Layout -->
    <div style="display:grid; grid-template-columns: 3fr 2fr; gap:24px; align-items:start;">
        <!-- Left Column: Location, Leaflet Map & Parameters -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <!-- Interactive Map Card (Requirement 2) -->
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 class="card-title"><span>📍</span> Incident Location & Coordinates</h3>
                    <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                        <a href="https://www.google.com/maps?q=<?= $incident['latitude'] ?>,<?= $incident['longitude'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size:0.75rem;">
                            External Map &rarr;
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <!-- Leaflet Map Container -->
                    <div id="detailsMap" style="height:260px; width:100%; border-radius:var(--radius-sm); border:1px solid var(--color-card-border); margin-bottom:14px; background:#e2e8f0;"></div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; font-size:0.85rem;">
                        <div>
                            <span style="font-size:0.72rem; color:var(--color-text-muted); text-transform:uppercase; font-weight:700;">Simulated Sudar Road Node</span>
                            <div style="font-weight:700; color:var(--color-sidebar); margin-top:2px;">
                                <?= sanitize($incident['location_name'] ?? $incident['location_id']) ?>
                            </div>
                            <span style="font-size:0.72rem; color:var(--color-text-muted);">
                                <?= sanitize($incident['location_type'] ?? 'Municipal Node') ?> (Node: <?= sanitize($incident['location_id']) ?>)
                            </span>
                        </div>

                        <div>
                            <span style="font-size:0.72rem; color:var(--color-text-muted); text-transform:uppercase; font-weight:700;">GPS Coordinates</span>
                            <div style="font-family:var(--font-mono); font-weight:700; margin-top:2px;">
                                <?php if (!empty($incident['latitude']) && !empty($incident['longitude'])): ?>
                                    <?= number_format((float)$incident['latitude'], 6) ?>, <?= number_format((float)$incident['longitude'], 6) ?>
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted);">Default municipal coordinates applied</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($incident['location_address'])): ?>
                            <div style="grid-column: 1 / -1;">
                                <span style="font-size:0.72rem; color:var(--color-text-muted); text-transform:uppercase; font-weight:700;">Street Address / Resolved Location</span>
                                <div style="color:#1e293b; margin-top:2px;">
                                    <?= sanitize($incident['location_address']) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($incident['location_description'])): ?>
                            <div style="grid-column: 1 / -1; background:#f8fafc; padding:8px 12px; border-radius:var(--radius-sm); border:1px solid #e2e8f0;">
                                <span style="font-size:0.72rem; color:var(--color-text-muted); text-transform:uppercase; font-weight:700;">Navigation Directions / Landmarks:</span>
                                <div style="color:#334155; margin-top:2px;">
                                    <?= sanitize($incident['location_description']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Core Triage Assessment Parameters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><span>📋</span> Incident Parameters & Reported Hazards</h3>
                </div>
                <div class="card-body">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-bottom:18px;">
                        <div>
                            <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Emergency Type</span>
                            <div style="font-size:1.05rem; font-weight:800; color:var(--color-sidebar); margin-top:2px;">
                                <?= sanitize($incident['incident_type']) ?>
                            </div>
                        </div>

                        <div>
                            <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Building / Structure</span>
                            <div style="font-size:1.05rem; font-weight:800; color:var(--color-sidebar); margin-top:2px;">
                                <?= sanitize($incident['building_type'] ?? 'Residential') ?>
                            </div>
                        </div>

                        <div>
                            <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">People Trapped?</span>
                            <div style="font-size:0.95rem; font-weight:800; margin-top:2px;">
                                <?= $incident['people_trapped'] ? '<span style="color:#b91c1c;">🚨 YES - Trapped Civilians</span>' : '<span style="color:#166534;">🛡️ No Trapped Civilians</span>' ?>
                            </div>
                        </div>

                        <div>
                            <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Injuries Reported?</span>
                            <div style="font-size:0.95rem; font-weight:800; margin-top:2px;">
                                <?= $incident['injuries_reported'] ? '<span style="color:#c2410c;">🩹 YES - Casualties Present</span>' : '<span style="color:#475569;">None Reported</span>' ?>
                            </div>
                        </div>

                        <div>
                            <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Hazardous Materials</span>
                            <div style="font-size:0.95rem; font-weight:800; margin-top:2px;">
                                <?= $incident['hazmat_involved'] === 'Yes' ? '<span style="color:#b91c1c;">☣️ YES - Hazmat Involved</span>' : sanitize($incident['hazmat_involved']) ?>
                            </div>
                        </div>

                        <div>
                            <span style="font-size:0.72rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Critical Infrastructure</span>
                            <div style="font-size:0.95rem; font-weight:800; margin-top:2px;">
                                <?= $incident['critical_infrastructure'] ? '<span style="color:#991b1b;">⚠️ IMPACTED (Highest Priority)</span>' : '<span style="color:#64748b;">Standard Asset</span>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Required Equipment Capabilities (Requirement 4) -->
                    <div style="border-top:1px solid var(--color-card-border); padding-top:16px;">
                        <span style="font-size:0.75rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; margin-bottom:8px; display:block;">
                            Required Emergency Equipment Capabilities
                        </span>
                        <div style="display:flex; flex-wrap:wrap; gap:8px;">
                            <?php foreach ($incident['equipment_array'] as $eq): ?>
                                <span style="font-size:0.85rem; padding:5px 12px; border-radius:var(--radius-sm); background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; font-weight:600;">
                                    <?= render_equipment_badge($eq) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($incident['description'])): ?>
                        <div style="border-top:1px solid var(--color-card-border); padding-top:16px; margin-top:16px;">
                            <span style="font-size:0.75rem; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Caller Situation Notes</span>
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-sm); padding:12px; font-size:0.88rem; color:#334155; margin-top:6px; line-height:1.5;">
                                <?= nl2br(sanitize($incident['description'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Priority Algorithm, Officer Override & Mission History -->
        <div style="display:flex; flex-direction:column; gap:24px;">
            <!-- Priority & Severity Score Breakdown Card (Requirement 3) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><span>⚖️</span> Triage Scoring Breakdown</h3>
                </div>
                <div class="card-body">
                    <div style="text-align:center; padding:12px 0 16px; border-bottom:1px solid var(--color-card-border); margin-bottom:16px;">
                        <div style="margin-bottom:8px;">
                            <?php 
                            $effectiveTier = !empty($incident['override_severity']) ? $incident['override_severity'] : $incident['severity_tier'];
                            echo render_priority_badge($effectiveTier); 
                            ?>
                        </div>
                        <div style="font-family:var(--font-mono); font-size:2.2rem; font-weight:800; color:var(--color-sidebar);">
                            <?= $incident['priority_score'] ?><span style="font-size:1rem; color:var(--color-text-muted);">/100</span>
                        </div>
                        <div style="font-size:0.75rem; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.05em; font-weight:700;">
                            Composite CAD Priority Rank
                        </div>
                    </div>

                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-sm); padding:12px; font-size:0.8rem; color:#475569; line-height:1.5; margin-bottom:16px;">
                        <strong style="color:var(--color-sidebar);">Rules-Based Evaluation Reason:</strong><br>
                        <?= sanitize($incident['assessment_reason'] ?? 'Standard scoring weights applied.') ?>
                    </div>

                    <!-- Authorized Override Display if corrected -->
                    <?php if (!empty($incident['override_severity'])): ?>
                        <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:var(--radius-sm); padding:12px; font-size:0.8rem; color:#92400e; margin-bottom:16px;">
                            <strong>✏️ Officer Triage Correction:</strong><br>
                            Severity adjusted to <strong><?= sanitize($incident['override_severity']) ?></strong> by <?= sanitize($incident['override_by']) ?> on <?= format_datetime($incident['override_at']) ?>.<br>
                            <em>Reason: <?= sanitize($incident['override_reason']) ?></em>
                        </div>
                    <?php endif; ?>

                    <!-- Override Severity Form for Authorized Personnel (Requirement 3) -->
                    <details style="border-top:1px solid var(--color-card-border); padding-top:12px;">
                        <summary style="font-size:0.82rem; font-weight:700; color:var(--color-primary); cursor:pointer;">
                            ⚙️ Correct / Override Triage (Authorized Officer)
                        </summary>
                        <form method="POST" action="<?= BASE_URL ?>actions/override-severity.php" style="margin-top:12px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="incident_id" value="<?= $incident['incident_id'] ?>">

                            <div class="form-group" style="margin-bottom:10px;">
                                <label class="form-label" for="override_severity">Corrected Severity Tier</label>
                                <select name="override_severity" id="override_severity" class="form-control" required style="font-size:0.85rem;">
                                    <option value="Critical" <?= $effectiveTier === 'Critical' ? 'selected' : '' ?>>Critical</option>
                                    <option value="High" <?= $effectiveTier === 'High' ? 'selected' : '' ?>>High</option>
                                    <option value="Medium" <?= $effectiveTier === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="Low" <?= $effectiveTier === 'Low' ? 'selected' : '' ?>>Low</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom:12px;">
                                <label class="form-label" for="override_reason">Operational Justification (Required)</label>
                                <textarea name="override_reason" id="override_reason" class="form-control" rows="2" placeholder="Explain the operational rationale for adjusting the rules-based score..." required style="font-size:0.82rem;"></textarea>
                            </div>

                            <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;">
                                Record Severity Correction
                            </button>
                        </form>
                    </details>
                </div>
            </div>

            <!-- Historical Dispatch Record (if completed) -->
            <?php if ($dispatch && $dispatch['status'] === 'Completed'): ?>
                <div class="card" style="border-left: 4px solid var(--color-success);">
                    <div class="card-header" style="background:#ecfdf5;">
                        <h3 class="card-title" style="color:#065f46;"><span>✅</span> Mission Completed Audit</h3>
                    </div>
                    <div class="card-body" style="font-size:0.85rem; line-height:1.6;">
                        <p><strong>Dispatch ID:</strong> #<?= $dispatch['dispatch_id'] ?></p>
                        <p><strong>Assigned Unit:</strong> <?= sanitize($dispatch['unit_id']) ?> (<?= sanitize($dispatch['unit_type']) ?>)</p>
                        <p><strong>Operating Station:</strong> <?= sanitize($dispatch['station_name']) ?></p>
                        <p><strong>Travel Time:</strong> <?= $dispatch['estimated_travel_time'] ?> mins</p>
                        <p><strong>Dispatched At:</strong> <?= format_datetime($dispatch['dispatched_at']) ?></p>
                        <p><strong>Completed At:</strong> <?= format_datetime($dispatch['completed_at']) ?></p>
                        <p><strong>Responsible Officer:</strong> <?= sanitize($incident['completed_by'] ?? 'Duty Officer') ?></p>
                        <?php if (!empty($incident['completion_notes'])): ?>
                            <p><strong>Resolution Notes:</strong> <?= sanitize($incident['completion_notes']) ?></p>
                        <?php endif; ?>
                        <div style="margin-top:10px; font-family:var(--font-mono); font-size:0.78rem; background:#f1f5f9; padding:6px 10px; border-radius:var(--radius-sm); color:#1e293b;">
                            Route: <?= sanitize($dispatch['route']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const lat = <?= $incident['effective_lat'] ?>;
    const lng = <?= $incident['effective_lng'] ?>;

    const detailsMap = L.map('detailsMap').setView([lat, lng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(detailsMap);

    const m = L.marker([lat, lng]).addTo(detailsMap);
    m.bindPopup("<strong>Incident #<?= $incident['incident_id'] ?>: <?= sanitize($incident['incident_type']) ?></strong><br><?= sanitize($incident['location_name'] ?? $incident['location_id']) ?>").openPopup();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
