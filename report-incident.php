<?php
/**
 * Smart Fire and Rescue Response System
 * Report Incident - Clean, Modern Emergency Intake & CAD Triage
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/classes/RoadNetwork.php';

$pageTitle = 'Report Emergency Incident';

$roadNetwork = new RoadNetwork();
$locations   = $roadNetwork->getLocations();

include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 920px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Top Action Header -->
    <div style="margin-bottom: 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <h1 style="font-size: 1.55rem; font-weight:800; color:var(--color-sidebar); display:flex; align-items:center; gap:8px;">
                <span>🚨</span> Report Emergency Incident
            </h1>
            <p style="color:var(--color-text-muted); font-size:0.88rem;">
                Official CAD intake protocol for <?= TOWN_NAME ?> &bull; Priority scored in real-time via rules engine
            </p>
        </div>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary btn-sm" style="font-weight:600;">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Intake Card -->
    <form action="<?= BASE_URL ?>actions/save-incident.php" method="POST" id="incidentReportForm" onsubmit="return validateAndSubmit(event)">
        <?= csrf_field() ?>
        <input type="hidden" name="incident_type" id="incident_type" value="Fire">
        <input type="hidden" name="latitude" id="latitude" value="13.078000">
        <input type="hidden" name="longitude" id="longitude" value="80.265000">

        <div class="card" style="box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid var(--color-card-border); overflow:hidden;">
            
            <!-- STEP 1: Emergency Classification Toggle Cards -->
            <div style="padding: 22px 24px; border-bottom: 1px solid #f1f5f9;">
                <label style="font-size: 0.85rem; font-weight: 800; color: var(--color-sidebar); text-transform: uppercase; letter-spacing: 0.05em; display:flex; align-items:center; gap:6px; margin-bottom: 12px;">
                    <span style="background:var(--color-primary); color:white; width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;">1</span>
                    Select Emergency Category
                </label>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                    <!-- Fire -->
                    <div class="type-card active" id="card-Fire" onclick="selectType('Fire')" style="border: 2px solid #ea580c; background: #fff7ed; border-radius: 10px; padding: 14px 16px; cursor: pointer; transition: all 0.2s;">
                        <div style="display:flex; align-items:center; gap: 10px;">
                            <span style="font-size: 1.8rem;">🔥</span>
                            <div>
                                <div style="font-weight: 800; color: #c2410c; font-size: 1rem;">Fire Emergency</div>
                                <div style="font-size: 0.76rem; color: #9a3412;">Structure, Chemical, Wildfire</div>
                            </div>
                        </div>
                    </div>

                    <!-- Rescue -->
                    <div class="type-card" id="card-Rescue" onclick="selectType('Rescue')" style="border: 2px solid #e2e8f0; background: #ffffff; border-radius: 10px; padding: 14px 16px; cursor: pointer; transition: all 0.2s;">
                        <div style="display:flex; align-items:center; gap: 10px;">
                            <span style="font-size: 1.8rem;">🛟</span>
                            <div>
                                <div style="font-weight: 800; color: #1e293b; font-size: 1rem;">Rescue Operation</div>
                                <div style="font-size: 0.76rem; color: #64748b;">Vehicle Crash, Trapped Victims</div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical -->
                    <div class="type-card" id="card-Medical" onclick="selectType('Medical Emergency')" style="border: 2px solid #e2e8f0; background: #ffffff; border-radius: 10px; padding: 14px 16px; cursor: pointer; transition: all 0.2s;">
                        <div style="display:flex; align-items:center; gap: 10px;">
                            <span style="font-size: 1.8rem;">🚑</span>
                            <div>
                                <div style="font-weight: 800; color: #1e293b; font-size: 1rem;">Medical Emergency</div>
                                <div style="font-size: 0.76rem; color: #64748b;">Trauma, Casualty, Life Support</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Location & Map Integration -->
            <div style="padding: 22px 24px; border-bottom: 1px solid #f1f5f9; background:#fafafa;">
                <label style="font-size: 0.85rem; font-weight: 800; color: var(--color-sidebar); text-transform: uppercase; letter-spacing: 0.05em; display:flex; align-items:center; gap:6px; margin-bottom: 14px;">
                    <span style="background:var(--color-primary); color:white; width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;">2</span>
                    Incident Location (Sudar Grid)
                </label>

                <div style="display:grid; grid-template-columns: 1.1fr 0.9fr; gap: 18px; align-items:start;">
                    <!-- Left: Location Inputs -->
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label class="form-label" for="location_id" style="font-weight:700; font-size:0.85rem;">
                                Select Area / Landmark <span style="color:#ef4444;">*</span>
                            </label>
                            <select name="location_id" id="location_id" class="form-control" required onchange="onLandmarkSelect(this.value)" style="font-weight:600; padding:10px 12px; background:#ffffff;">
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= sanitize($loc['location_id']) ?>" 
                                            data-lat="<?= $loc['latitude'] ?? '' ?>" 
                                            data-lng="<?= $loc['longitude'] ?? '' ?>"
                                            data-name="<?= sanitize($loc['location_name']) ?>"
                                            <?= $loc['location_id'] === 'Hospital' ? 'selected' : '' ?>>
                                        <?php 
                                            $icon = '📍';
                                            if (stripos($loc['location_name'], 'Hospital') !== false) $icon = '🏥';
                                            elseif (stripos($loc['location_name'], 'Factory') !== false || stripos($loc['location_name'], 'Chemical') !== false) $icon = '🏭';
                                            elseif (stripos($loc['location_name'], 'School') !== false) $icon = '🏫';
                                            elseif (stripos($loc['location_name'], 'Market') !== false) $icon = '🛍️';
                                            elseif (stripos($loc['location_name'], 'Residential') !== false) $icon = '🏘️';
                                            elseif (stripos($loc['location_name'], 'Tech') !== false) $icon = '🏢';
                                        ?>
                                        <?= $icon ?> <?= sanitize($loc['location_name']) ?> (<?= sanitize($loc['location_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="location_address" style="font-weight:700; font-size:0.85rem;">
                                Street Address / Building Details
                            </label>
                            <input type="text" name="location_address" id="location_address" class="form-control" value="Sudar City Hospital (Main Healthcare Wing)" placeholder="e.g. 104 Main Street, Gate 2..." style="background:#ffffff;">
                        </div>

                        <div>
                            <label class="form-label" for="location_description" style="font-weight:700; font-size:0.85rem;">
                                Specific Navigation Clues
                            </label>
                            <input type="text" name="location_description" id="location_description" class="form-control" placeholder="e.g. Near Emergency Trauma entrance, 2nd floor..." style="background:#ffffff;">
                        </div>

                        <!-- GPS & Geolocation Pill -->
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:8px 12px; display:flex; justify-content:space-between; align-items:center;">
                            <div style="font-size:0.8rem; color:#475569;">
                                <span style="color:#16a34a; font-weight:800;">● GPS:</span>
                                <span id="gpsDisplay" style="font-family:monospace; font-weight:700; color:#0f172a; margin-left:4px;">13.078000, 80.265000</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline" onclick="locateMe()" style="font-size:0.75rem; padding:3px 8px; border-color:#cbd5e1;">
                                📍 Device GPS
                            </button>
                        </div>
                    </div>

                    <!-- Right: Compact Map -->
                    <div>
                        <div id="incidentMap" style="height: 240px; width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; background:#e2e8f0;"></div>
                        <div style="font-size:0.72rem; color:var(--color-text-muted); text-align:center; margin-top:4px;">
                            Drag pin on map or select landmark to adjust GPS coordinates
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Real-Time Severity & Risk Assessment -->
            <div style="padding: 22px 24px; border-bottom: 1px solid #f1f5f9;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 14px;">
                    <label style="font-size: 0.85rem; font-weight: 800; color: var(--color-sidebar); text-transform: uppercase; letter-spacing: 0.05em; display:flex; align-items:center; gap:6px; margin:0;">
                        <span style="background:var(--color-primary); color:white; width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;">3</span>
                        Risk &amp; Severity Assessment
                    </label>

                    <!-- Live Triage Preview Pill -->
                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:20px; padding:4px 14px; display:flex; align-items:center; gap:8px;">
                        <span style="font-size:0.75rem; font-weight:800; color:#1e40af;">LIVE TRIAGE:</span>
                        <span id="triageScoreBadge" class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:800; font-size:0.78rem;">
                            CRITICAL &bull; 90/100
                        </span>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
                    <!-- Intensity / Scale -->
                    <div>
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">Scale / Size</label>
                        <select name="fire_size" id="fire_size" class="form-control" onchange="calcTriageScore()" style="font-size:0.88rem;">
                            <option value="Small">Minor / Contained</option>
                            <option value="Medium" selected>Medium / Active Scene</option>
                            <option value="Large">Severe / Full Structural</option>
                        </select>
                    </div>

                    <!-- Civilians Trapped -->
                    <div>
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">Civilians Trapped?</label>
                        <select name="people_trapped" id="people_trapped" class="form-control" onchange="calcTriageScore()" style="font-size:0.88rem;">
                            <option value="0">No Persons Trapped</option>
                            <option value="1">🚨 YES - Trapped Inside</option>
                        </select>
                    </div>

                    <!-- Injuries -->
                    <div>
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">Injuries / Casualties?</label>
                        <select name="injuries_reported" id="injuries_reported" class="form-control" onchange="calcTriageScore()" style="font-size:0.88rem;">
                            <option value="0">None Reported</option>
                            <option value="1">🩹 Active Injuries</option>
                        </select>
                    </div>

                    <!-- Critical Infrastructure -->
                    <div>
                        <label class="form-label" style="font-size:0.8rem; font-weight:700;">Critical Infrastructure?</label>
                        <select name="critical_infrastructure" id="critical_infrastructure" class="form-control" onchange="calcTriageScore()" style="font-size:0.88rem;">
                            <option value="1" selected>⚠️ Yes (Hospital/Energy/Water)</option>
                            <option value="0">Standard Residential/Commercial</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Equipment Chips (One-Click Selection) -->
            <div style="padding: 22px 24px; border-bottom: 1px solid #f1f5f9; background:#fafafa;">
                <label style="font-size: 0.85rem; font-weight: 800; color: var(--color-sidebar); text-transform: uppercase; letter-spacing: 0.05em; display:flex; align-items:center; gap:6px; margin-bottom: 6px;">
                    <span style="background:var(--color-primary); color:white; width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;">4</span>
                    Required Response Equipment
                </label>
                <p style="font-size:0.78rem; color:var(--color-text-muted); margin-bottom:12px;">
                    Equipment is automatically pre-selected based on emergency type. Click any chip to add or remove tools.
                </p>

                <div style="display:flex; flex-wrap:wrap; gap:8px;" id="equipmentChipsContainer">
                    <?php 
                    $availableGear = [
                        'WaterPump'           => ['icon' => '💧', 'name' => 'Water Pump & Tanker'],
                        'Ladder'              => ['icon' => '🪜', 'name' => 'Hydraulic Ladder'],
                        'FirstAid'            => ['icon' => '🩹', 'name' => 'First Aid & Trauma'],
                        'RescueKit'           => ['icon' => '🧰', 'name' => 'Extrication Rescue Kit'],
                        'BreathingApparatus'  => ['icon' => '🤿', 'name' => 'SCBA Breathing Set'],
                        'ProtectiveEquipment' => ['icon' => '🛡️', 'name' => 'Hazmat Bunker Gear'],
                        'HazmatKit'           => ['icon' => '☣️', 'name' => 'Chemical Containment']
                    ];
                    foreach ($availableGear as $gearKey => $gear): 
                    ?>
                        <label class="gear-chip" id="chip-<?= $gearKey ?>" style="display:inline-flex; align-items:center; gap:6px; background:#ffffff; border:1.5px solid #cbd5e1; border-radius:20px; padding:6px 14px; cursor:pointer; font-size:0.83rem; font-weight:600; color:#334155; user-select:none; transition:all 0.15s;">
                            <input type="checkbox" name="required_equipment[]" value="<?= $gearKey ?>" id="chk-<?= $gearKey ?>" onchange="toggleGearChip('<?= $gearKey ?>')" style="display:none;">
                            <span><?= $gear['icon'] ?></span>
                            <span><?= $gear['name'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STEP 5: Caller & Scene Notes -->
            <div style="padding: 22px 24px;">
                <label style="font-size: 0.85rem; font-weight: 800; color: var(--color-sidebar); text-transform: uppercase; letter-spacing: 0.05em; display:flex; align-items:center; gap:6px; margin-bottom: 10px;">
                    <span style="background:var(--color-primary); color:white; width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem;">5</span>
                    Caller &amp; Scene Details (Optional)
                </label>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px;">
                    <div>
                        <input type="text" name="caller_name" class="form-control" placeholder="Reporting Caller Name" style="font-size:0.85rem;">
                    </div>
                    <div>
                        <input type="tel" name="caller_phone" class="form-control" placeholder="Callback Phone (e.g. 9876543210)" style="font-size:0.85rem;">
                    </div>
                </div>

                <div>
                    <textarea name="description" class="form-control" rows="2" placeholder="Additional emergency notes, hazards, visible smoke color, or special access instructions..." style="font-size:0.85rem;"></textarea>
                </div>
            </div>

            <!-- Submit CTA Footer -->
            <div style="background: #f8fafc; border-top: 1px solid var(--color-card-border); padding: 18px 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="font-size:0.8rem; color:var(--color-text-muted);">
                    ⚡ Submitting automatically initiates Dijkstra routing &amp; dispatches nearest station unit.
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary btn-sm" style="font-weight:600;">Cancel</a>
                    <button type="submit" id="btnSubmitIncident" class="btn btn-emergency-cta" style="padding:10px 24px; font-size:0.95rem; font-weight:800;">
                        <span>🚨</span> Log Emergency &amp; Auto-Dispatch
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

<!-- Embedded Map & Real-Time Triage Logic -->
<script>
const SUDAR_LOCATIONS = <?= json_encode($locations) ?>;
let map, marker;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    initLeafletMap();
    selectType('Fire'); // Default Fire
    calcTriageScore();
});

function initLeafletMap() {
    const defaultLat = 13.078000;
    const defaultLng = 80.265000;

    map = L.map('incidentMap', { zoomControl: true }).setView([defaultLat, defaultLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);

    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        setCoordinates(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        setCoordinates(e.latlng.lat, e.latlng.lng);
    });
}

function onLandmarkSelect(locId) {
    const loc = SUDAR_LOCATIONS.find(l => l.location_id === locId);
    if (!loc) return;

    const lat = parseFloat(loc.latitude);
    const lng = parseFloat(loc.longitude);

    setCoordinates(lat, lng);
    document.getElementById('location_address').value = `${loc.location_name} (${loc.location_type} Zone)`;

    // Critical Infrastructure auto-detect
    const critSelect = document.getElementById('critical_infrastructure');
    if (locId === 'Hospital' || loc.location_type === 'Hospital' || locId === 'PowerStation' || locId === 'WaterPlant') {
        critSelect.value = '1';
    } else {
        critSelect.value = '0';
    }

    if (map && marker) {
        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng]);
    }
    calcTriageScore();
}

function setCoordinates(lat, lng) {
    document.getElementById('latitude').value = lat.toFixed(6);
    document.getElementById('longitude').value = lng.toFixed(6);
    document.getElementById('gpsDisplay').innerText = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
}

function locateMe() {
    if (!navigator.geolocation) {
        alert('Geolocation not supported by browser.');
        return;
    }
    navigator.geolocation.getCurrentPosition(function(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        setCoordinates(lat, lng);
        if (map && marker) {
            marker.setLatLng([lat, lng]);
            map.panTo([lat, lng]);
        }
    }, function() {
        alert('Could not retrieve device location. Using Sudar landmark coordinates.');
    });
}

// Emergency Type Switcher & Smart Equipment Pre-select
function selectType(type) {
    document.getElementById('incident_type').value = type;

    // Reset styles
    ['Fire', 'Rescue', 'Medical'].forEach(t => {
        const card = document.getElementById('card-' + t);
        if (card) {
            card.style.borderColor = '#e2e8f0';
            card.style.background = '#ffffff';
            card.querySelector('div > div:first-child').style.color = '#1e293b';
        }
    });

    // Highlight selected card
    let activeKey = type === 'Medical Emergency' ? 'Medical' : type;
    const activeCard = document.getElementById('card-' + activeKey);
    if (activeCard) {
        if (type === 'Fire') {
            activeCard.style.borderColor = '#ea580c';
            activeCard.style.background = '#fff7ed';
            activeCard.querySelector('div > div:first-child').style.color = '#c2410c';
            setGear(['WaterPump', 'Ladder', 'BreathingApparatus']);
        } else if (type === 'Rescue') {
            activeCard.style.borderColor = '#2563eb';
            activeCard.style.background = '#eff6ff';
            activeCard.querySelector('div > div:first-child').style.color = '#1d4ed8';
            setGear(['RescueKit', 'FirstAid', 'ProtectiveEquipment']);
        } else {
            activeCard.style.borderColor = '#16a34a';
            activeCard.style.background = '#f0fdf4';
            activeCard.querySelector('div > div:first-child').style.color = '#15803d';
            setGear(['FirstAid']);
        }
    }
    calcTriageScore();
}

function setGear(gearKeys) {
    // Uncheck all first
    document.querySelectorAll('#equipmentChipsContainer input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
        updateChipStyle(cb.value, false);
    });

    // Check specific
    gearKeys.forEach(k => {
        const cb = document.getElementById('chk-' + k);
        if (cb) {
            cb.checked = true;
            updateChipStyle(k, true);
        }
    });
}

function toggleGearChip(gearKey) {
    const cb = document.getElementById('chk-' + gearKey);
    if (cb) {
        updateChipStyle(gearKey, cb.checked);
    }
}

function updateChipStyle(gearKey, isChecked) {
    const chip = document.getElementById('chip-' + gearKey);
    if (!chip) return;
    if (isChecked) {
        chip.style.borderColor = '#ea580c';
        chip.style.background = '#fff7ed';
        chip.style.color = '#c2410c';
        chip.style.fontWeight = '700';
    } else {
        chip.style.borderColor = '#cbd5e1';
        chip.style.background = '#ffffff';
        chip.style.color = '#334155';
        chip.style.fontWeight = '600';
    }
}

// Calculate live triage priority score
function calcTriageScore() {
    let score = 20; // base score

    const type = document.getElementById('incident_type').value;
    if (type === 'Fire') score += 15;
    else if (type === 'Rescue') score += 10;
    else score += 5;

    const size = document.getElementById('fire_size').value;
    if (size === 'Large') score += 30;
    else if (size === 'Medium') score += 15;

    const trapped = document.getElementById('people_trapped').value === '1';
    if (trapped) score += 25;

    const injuries = document.getElementById('injuries_reported').value === '1';
    if (injuries) score += 15;

    const crit = document.getElementById('critical_infrastructure').value === '1';
    if (crit) score += 20;

    score = Math.min(100, Math.max(10, score));

    // Update live badge
    const badge = document.getElementById('triageScoreBadge');
    if (score >= 80) {
        badge.style.background = '#fee2e2';
        badge.style.color = '#b91c1c';
        badge.innerHTML = `🔥 CRITICAL &bull; ${score}/100`;
    } else if (score >= 55) {
        badge.style.background = '#ffedd5';
        badge.style.color = '#c2410c';
        badge.innerHTML = `⚡ HIGH &bull; ${score}/100`;
    } else if (score >= 35) {
        badge.style.background = '#fef3c7';
        badge.style.color = '#b45309';
        badge.innerHTML = `⚠️ MEDIUM &bull; ${score}/100`;
    } else {
        badge.style.background = '#f1f5f9';
        badge.style.color = '#475569';
        badge.innerHTML = `ℹ️ LOW &bull; ${score}/100`;
    }
}

function validateAndSubmit(e) {
    const btn = document.getElementById('btnSubmitIncident');
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span> Transmitting CAD Dispatch...';
    return true;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
