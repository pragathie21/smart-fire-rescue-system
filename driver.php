<?php
/**
 * Smart Fire and Rescue Response System
 * Driver Response Terminal (MDT - Mobile Data Terminal)
 * In-Cab Vehicle Navigation, Real-Time Audio Siren Alert & Self-Resolution
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/ResponseUnit.php';
require_once __DIR__ . '/classes/DispatchService.php';

$pageTitle = 'Driver Terminal (MDT)';

$unitModel = new ResponseUnit();
$dispatchService = new DispatchService();

$units = $unitModel->getAll();

// Selected unit ID (default FE01)
$selectedUnitId = trim($_GET['unit'] ?? 'FE01');
$selectedUnit = $unitModel->getById($selectedUnitId);
if (!$selectedUnit && !empty($units)) {
    $selectedUnit = $units[0];
    $selectedUnitId = $selectedUnit['unit_id'];
}

// Fetch active dispatch for this unit if any
$db = getDB();
$stmt = $db->prepare("
    SELECT d.*, 
           i.incident_id, i.incident_type, i.location_id, i.location_address,
           i.latitude, i.longitude, i.location_description, i.description AS caller_notes,
           i.priority_score, i.severity_tier,
           i.people_trapped, i.injuries_reported, i.hazmat_involved,
           l.location_name
    FROM dispatch_records d
    JOIN incidents i ON d.incident_id = i.incident_id
    LEFT JOIN locations l ON i.location_id = l.location_id
    WHERE d.unit_id = :unit AND d.status = 'Active'
    ORDER BY d.dispatch_id DESC
    LIMIT 1
");
$stmt->execute(['unit' => $selectedUnitId]);
$activeMission = $stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 960px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Top Terminal Title & Vehicle Selector -->
    <div style="margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size: 1.55rem; font-weight:800; color:var(--color-sidebar); display:flex; align-items:center; gap:8px;">
                <span>📱</span> Driver Response Terminal (MDT)
            </h1>
            <p style="color:var(--color-text-muted); font-size:0.85rem;">
                In-Cab Live Dispatch Screen &bull; Automated Audio Siren &bull; Dijkstra Turn-by-Turn Navigation
            </p>
        </div>

        <!-- Vehicle Switcher Pills -->
        <div style="display:flex; gap:6px; flex-wrap:wrap; background:#f1f5f9; padding:4px; border-radius:10px;">
            <?php foreach ($units as $u): 
                $isActiveUnit = ($u['unit_id'] === $selectedUnitId);
                $isDispatched = ($u['status'] === 'Dispatched');
            ?>
                <a href="<?= BASE_URL ?>driver.php?unit=<?= urlencode($u['unit_id']) ?>" 
                   style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:8px; font-size:0.82rem; font-weight:700; text-decoration:none; transition:all 0.15s; background: <?= $isActiveUnit ? '#0f172a' : '#ffffff' ?>; color: <?= $isActiveUnit ? '#ffffff' : '#334155' ?>; border: 1px solid <?= $isActiveUnit ? '#0f172a' : '#cbd5e1' ?>;">
                    <span><?= strpos($u['unit_type'], 'Rescue') !== false ? '🛟' : '🚒' ?></span>
                    <span><?= htmlspecialchars($u['unit_id']) ?></span>
                    <?php if ($isDispatched): ?>
                        <span style="width:7px; height:7px; border-radius:50%; background:#ef4444; display:inline-block; animation:pulse 1.5s infinite;"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Mission Container (Dynamically rendered & updated via real-time poller) -->
    <div id="mdtContentContainer">
        <?php if ($activeMission): ?>
            <!-- ================= ACTIVE MISSION STATE ================= -->
            <div class="card" style="border: 2px solid #ea580c; box-shadow: 0 10px 25px -5px rgba(234,88,12,0.15); overflow:hidden;">
                <!-- High-Alert Header -->
                <div style="background: linear-gradient(135deg, #ea580c 0%, #dc2626 100%); color:#ffffff; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <span style="font-size:0.75rem; font-weight:800; letter-spacing:0.08em; text-transform:uppercase; background:rgba(0,0,0,0.25); padding:3px 10px; border-radius:4px;">
                            🚨 IMMEDIATE CAD EMERGENCY DIRECTIVE
                        </span>
                        <h2 style="font-size:1.45rem; font-weight:800; margin:6px 0 2px; color:#ffffff;">
                            <?= htmlspecialchars($activeMission['incident_type']) ?> — Mission #DISP-<?= (int)$activeMission['dispatch_id'] ?>
                        </h2>
                        <div style="font-size:0.85rem; opacity:0.95;">
                            Unit Assigned: <strong><?= htmlspecialchars($selectedUnit['unit_id']) ?> (<?= htmlspecialchars($selectedUnit['unit_type']) ?>)</strong> &bull; Station Base: <?= htmlspecialchars($selectedUnit['station_id']) ?>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" onclick="playCabSiren()" class="btn btn-sm" style="background:#ffffff; color:#c2410c; font-weight:800; border:none; box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                            🔊 Cab Siren
                        </button>
                        <button type="button" onclick="speakRouteDirections()" class="btn btn-sm" style="background:rgba(255,255,255,0.2); color:#ffffff; border:1px solid rgba(255,255,255,0.4); font-weight:700;">
                            🗣️ Speak Route
                        </button>
                    </div>
                </div>

                <div class="card-body" style="padding:24px;">
                    <!-- DIJKSTRA SHORTEST ROUTE HERO BOX -->
                    <div style="background:#f8fafc; border:2px solid #bfdbfe; border-radius:12px; padding:20px; margin-bottom:24px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                            <span style="font-size:0.8rem; font-weight:800; color:#1e40af; text-transform:uppercase; letter-spacing:0.05em; display:flex; align-items:center; gap:6px;">
                                <span>🛣️</span> DIJKSTRA OPTIMAL TURN-BY-TURN ROUTE
                            </span>
                            <span style="background:#dbeafe; color:#1e40af; font-weight:800; font-size:0.85rem; padding:4px 12px; border-radius:20px; font-family:monospace;">
                                ⏱️ Target Travel Time: <?= number_format((float)($activeMission['estimated_travel_time'] ?? 0), 1) ?> mins ETA
                            </span>
                        </div>

                        <!-- Route Step Badges -->
                        <?php 
                        $routeRaw = $activeMission['route'] ?? $activeMission['route_taken'] ?? '';
                        $hops = array_map('trim', explode('->', $routeRaw));
                        ?>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
                            <?php foreach ($hops as $idx => $hop): ?>
                                <div style="background:#ffffff; border:2px solid <?= $idx === 0 ? '#3b82f6' : ($idx === count($hops)-1 ? '#ef4444' : '#64748b') ?>; color:#0f172a; padding:8px 16px; border-radius:8px; font-weight:800; font-size:1rem; font-family:monospace; box-shadow:0 2px 6px rgba(0,0,0,0.04);">
                                    <?= htmlspecialchars($hop) ?>
                                </div>
                                <?php if ($idx < count($hops) - 1): ?>
                                    <span style="font-size:1.3rem; color:#f97316; font-weight:900;">➔</span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div style="font-size:0.78rem; color:#64748b;">
                            ⚡ Lowest-weight path selected by CAD. All closed roads or traffic jams have been automatically bypassed.
                        </div>
                    </div>

                    <!-- Destination & Incident Intel Grid -->
                    <div style="display:grid; grid-template-columns: 1.2fr 0.8fr; gap:20px; margin-bottom:24px;">
                        <!-- Destination Details -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                            <span style="font-size:0.75rem; font-weight:800; color:var(--color-text-muted); text-transform:uppercase;">TARGET LOCATION</span>
                            <h3 style="font-size:1.2rem; font-weight:800; color:var(--color-sidebar); margin:4px 0 6px;">
                                📍 <?= htmlspecialchars($activeMission['location_name'] ?? $activeMission['location_id']) ?>
                            </h3>
                            <div style="font-size:0.9rem; color:#334155; margin-bottom:8px;">
                                <?= htmlspecialchars($activeMission['location_address'] ?: 'Sudar Municipal Sector') ?>
                            </div>
                            <?php if (!empty($activeMission['location_description'])): ?>
                                <div style="font-size:0.82rem; color:#64748b; background:#f8fafc; padding:8px 10px; border-radius:6px; margin-bottom:12px;">
                                    <strong>Directions:</strong> <?= htmlspecialchars($activeMission['location_description']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Google Maps GPS Navigation Link -->
                            <?php if (!empty($activeMission['latitude']) && !empty($activeMission['longitude'])): ?>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $activeMission['latitude'] ?>,<?= $activeMission['longitude'] ?>" target="_blank" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:8px; font-weight:700; font-size:0.88rem;">
                                    <span>🗺️</span> Open Google Maps GPS Navigation &rarr;
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Reported Scene Hazards -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:18px;">
                            <span style="font-size:0.75rem; font-weight:800; color:var(--color-text-muted); text-transform:uppercase;">REPORTED ON-SCENE RISKS</span>
                            <div style="display:flex; flex-direction:column; gap:8px; margin-top:10px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
                                    <span>Civilians Trapped:</span>
                                    <strong style="color:<?= $activeMission['people_trapped'] ? '#dc2626' : '#16a34a' ?>;">
                                        <?= $activeMission['people_trapped'] ? '🚨 YES (Trapped inside)' : 'No civilians trapped' ?>
                                    </strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
                                    <span>Injuries / Casualties:</span>
                                    <strong style="color:<?= $activeMission['injuries_reported'] ? '#ea580c' : '#64748b' ?>;">
                                        <?= $activeMission['injuries_reported'] ? '🩹 Active Casualties' : 'None reported' ?>
                                    </strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
                                    <span>Hazardous Materials:</span>
                                    <strong style="color:<?= $activeMission['hazmat_involved'] === 'Yes' ? '#dc2626' : '#64748b' ?>;">
                                        <?= htmlspecialchars($activeMission['hazmat_involved'] ?? 'No') ?>
                                    </strong>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
                                    <span>Priority Score:</span>
                                    <strong style="color:#b91c1c; font-family:monospace;">
                                        <?= number_format($activeMission['priority_score'], 1) ?> / 100
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DRIVER DIRECT ACTION (NO CALLS NEEDED!) -->
                    <div style="background:#f0fdf4; border:1.5px solid #86efac; border-radius:12px; padding:18px 22px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                        <div>
                            <strong style="color:#166534; font-size:1rem; display:block;">Emergency Mission Resolution</strong>
                            <span style="font-size:0.82rem; color:#15803d;">
                                When emergency is extinguished and secured, click below. This automatically notifies CAD and releases Unit <?= htmlspecialchars($selectedUnit['unit_id']) ?> back to Available.
                            </span>
                        </div>

                        <form method="POST" action="<?= BASE_URL ?>actions/complete-mission.php" onsubmit="return confirm('Conclude mission #<?= (int)$activeMission['dispatch_id'] ?>? Unit <?= htmlspecialchars($selectedUnit['unit_id']) ?> will return to Station standby.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="dispatch_id" value="<?= (int)$activeMission['dispatch_id'] ?>">
                            <input type="hidden" name="incident_id" value="<?= (int)$activeMission['incident_id'] ?>">
                            <input type="hidden" name="completion_notes" value="Resolved on-scene via Driver MDT Terminal">
                            <input type="hidden" name="redirect" value="driver.php?unit=<?= urlencode($selectedUnitId) ?>">
                            <button type="submit" class="btn btn-success" style="padding:10px 22px; font-size:0.95rem; font-weight:800; box-shadow:0 4px 12px rgba(22,163,74,0.3);">
                                ✅ Mission Accomplished / Return to Base
                            </button>
                        </form>
                    </div>

                </div>
            </div>

        <?php else: ?>
            <!-- ================= STANDBY STATE ================= -->
            <div class="card" style="text-align:center; padding:48px 24px; box-shadow:0 4px 14px rgba(0,0,0,0.05);">
                <div style="width:76px; height:76px; background:#f0fdf4; border:3px solid #86efac; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:2.4rem; margin-bottom:16px;">
                    🛡️
                </div>
                <h2 style="font-size:1.45rem; font-weight:800; color:var(--color-sidebar); margin-bottom:6px;">
                    Unit <?= htmlspecialchars($selectedUnit['unit_id']) ?> — Standby at <?= htmlspecialchars($selectedUnit['station_id']) ?>
                </h2>
                <div style="display:inline-flex; align-items:center; gap:8px; background:#dcfce7; color:#15803d; font-weight:700; padding:6px 16px; border-radius:20px; font-size:0.85rem; margin-bottom:14px;">
                    <span style="width:8px; height:8px; border-radius:50%; background:#16a34a; display:inline-block; animation:pulse 2s infinite;"></span>
                    STATUS: AVAILABLE FOR DISPATCH
                </div>

                <p style="color:var(--color-text-muted); font-size:0.92rem; max-width:520px; margin:0 auto 20px; line-height:1.5;">
                    Driver terminal is connected live to Sudar CAD. The instant an emergency incident is assigned to <strong><?= htmlspecialchars($selectedUnit['unit_id']) ?></strong>, your cab siren alarm will sound and turn-by-turn Dijkstra route will appear automatically.
                </p>

                <!-- Audio Test & Equipment Info -->
                <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-bottom:24px;">
                    <button type="button" onclick="playCabSiren()" class="btn btn-outline btn-sm" style="border-color:#cbd5e1; font-weight:700;">
                        🔊 Test Cab Siren Alarm
                    </button>
                    <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary btn-sm" style="font-weight:600;">
                        &larr; Open CAD Dashboard
                    </a>
                </div>

                <!-- Equipment on board -->
                <div style="max-width:440px; margin:0 auto; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; text-align:left;">
                    <span style="font-size:0.72rem; font-weight:800; color:var(--color-text-muted); text-transform:uppercase;">VEHICLE ON-BOARD HARDWARE</span>
                    <div style="font-size:0.82rem; color:#334155; margin-top:4px;">
                        <?= implode(' &bull; ', $selectedUnit['equipment_array']) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Web Audio Siren & Real-Time CAD Dispatch Poller -->
<script>
const CURRENT_UNIT_ID = <?= json_encode($selectedUnitId) ?>;
let hasActiveMission = <?= $activeMission ? 'true' : 'false' ?>;
let missionDetails = <?= json_encode($activeMission ?: null) ?>;

// Web Audio API Emergency Siren (Realistic 2-tone alert chime)
function playCabSiren() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(800, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1200, audioCtx.currentTime + 0.3);
        osc.frequency.exponentialRampToValueAtTime(800, audioCtx.currentTime + 0.6);
        osc.frequency.exponentialRampToValueAtTime(1200, audioCtx.currentTime + 0.9);
        osc.frequency.exponentialRampToValueAtTime(800, audioCtx.currentTime + 1.2);

        gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gainNode.gain.linearRampToValueAtTime(0, audioCtx.currentTime + 1.4);

        osc.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        osc.start();
        osc.stop(audioCtx.currentTime + 1.4);
    } catch (e) {
        console.warn('Audio siren playback:', e);
    }
}

// Browser Text-To-Speech Speech Synthesis
function speakRouteDirections() {
    if (!('speechSynthesis' in window) || !missionDetails) return;
    window.speechSynthesis.cancel();

    const routeText = missionDetails.route || missionDetails.route_taken || 'direct path';
    const text = `Attention ${CURRENT_UNIT_ID} driver. Emergency dispatch to ${missionDetails.location_name || missionDetails.location_id}. Take shortest route ${routeText}. Estimated arrival ${missionDetails.estimated_travel_time || 'seven'} minutes.`;

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 1.0;
    utterance.pitch = 1.0;
    window.speechSynthesis.speak(utterance);
}

// Auto-trigger sound and voice on first load if mission is active
document.addEventListener('DOMContentLoaded', function() {
    if (hasActiveMission) {
        // Play alert tone after brief user engagement or timeout
        setTimeout(playCabSiren, 400);
        setTimeout(speakRouteDirections, 1200);
    }

    // Start Real-Time Background Poller every 3 seconds
    setInterval(pollCADTerminal, 3000);
});

// Real-time poller: Checks if this unit has been dispatched by CAD
function pollCADTerminal() {
    fetch(`<?= BASE_URL ?>api.php?action=unit_status&unit_id=${encodeURIComponent(CURRENT_UNIT_ID)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const isNowDispatched = Boolean(data.mission);

            // If a new emergency mission was assigned while in standby, reload to alert driver!
            if (!hasActiveMission && isNowDispatched) {
                playCabSiren();
                window.location.reload();
            } else if (hasActiveMission && !isNowDispatched) {
                // If mission was completed by officer in control room, reload back to standby
                window.location.reload();
            }
        })
        .catch(err => console.debug('CAD poll:', err));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
