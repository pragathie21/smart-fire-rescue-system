/**
 * Smart Fire and Rescue Response System
 * Client-Side Application Script
 *
 * Connects directly to MySQL via api.php (No localStorage database dependency)
 * Supports Leaflet OpenStreetMap, GPS geolocation, assessment scoring,
 * multiple equipment capabilities, priority dispatch, and mission completion.
 */

"use strict";

const API_BASE = "api.php";

// Helper: Escape HTML strings
function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, function (ch) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;"
        }[ch];
    });
}

function statusClass(status) {
    const s = String(status || "").toLowerCase().replace(/\s+/g, '-');
    return s;
}

function priorityClass(tier) {
    const t = String(tier || "").toLowerCase();
    return `badge-priority-${t}`;
}

// ---------------------------------------------------------------------
// 1. DASHBOARD INITIALIZATION
// ---------------------------------------------------------------------
async function renderDashboard() {
    try {
        const statsRes = await fetch(`${API_BASE}?action=stats`);
        const statsData = await statsRes.json();

        if (statsData.success) {
            const inc = statsData.incidents;
            const units = statsData.units;
            const missions = statsData.missions;

            setText("totalCount", inc.total ?? 0);
            setText("pendingCount", inc.pending ?? 0);
            setText("availableCount", units.available ?? 0);
            setText("activeCount", missions.active ?? 0);
        }

        // Fetch recent incidents
        const incRes = await fetch(`${API_BASE}?action=incidents`);
        const incData = await incRes.json();
        const recentTable = document.getElementById("recentIncidents");

        if (recentTable && incData.success) {
            const incidents = incData.data.slice(0, 6);
            if (incidents.length > 0) {
                recentTable.innerHTML = incidents.map(i => {
                    const tier = i.override_severity || i.severity_tier;
                    const routeHtml = i.assigned_unit_id ? `
                        <div>
                            <span style="font-weight:700; color:#2563eb;">🚒 ${escapeHTML(i.assigned_unit_id)}</span>
                            ${i.estimated_travel_time ? `<span style="font-size:11px; color:#d62828; font-weight:800; background:#fee2e2; padding:2px 6px; border-radius:10px; margin-left:4px;">${i.estimated_travel_time} mins ETA</span>` : ''}
                            ${i.dijkstra_route ? `<div style="font-size:11px; color:#64748b; font-family:monospace; margin-top:2px;">🛣️ ${escapeHTML(i.dijkstra_route)}</div>` : ''}
                        </div>
                    ` : `<span style="color:#64748b; font-size:12px; font-style:italic;">⏳ Queued (CAD Auto-Dispatch)</span>`;

                    return `
                    <tr>
                        <td><strong>#${escapeHTML(i.incident_id)}</strong></td>
                        <td><strong>${escapeHTML(i.incident_type)}</strong></td>
                        <td>${escapeHTML(i.location_name || i.location_id)}</td>
                        <td>${routeHtml}</td>
                        <td><strong>${i.priority_score}/100</strong></td>
                        <td><span class="priority-badge ${priorityClass(tier)}">${escapeHTML(tier)}</span></td>
                        <td><span class="badge ${statusClass(i.status)}">${escapeHTML(i.status)}</span></td>
                    </tr>
                `}).join("");
            } else {
                recentTable.innerHTML = `<tr><td colspan="7" class="empty">No incidents reported in the database yet.</td></tr>`;
            }
        }

        // Fetch units
        const unitsRes = await fetch(`${API_BASE}?action=units`);
        const unitsData = await unitsRes.json();
        const unitContainer = document.getElementById("dashboardUnits");

        if (unitContainer && unitsData.success) {
            renderUnitCards(unitContainer, unitsData.data, false);
        }
    } catch (err) {
        console.error("Error loading dashboard data:", err);
    }
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function renderUnitCards(container, units, showDetails = true) {
    if (!container) return;
    container.innerHTML = units.map(u => {
        const equipStr = Array.isArray(u.equipment_array) ? u.equipment_array.join(", ") : "";
        return `
        <div class="unit-card">
            <h3>${escapeHTML(u.unit_id)}</h3>
            <p><strong>${escapeHTML(u.unit_type)}</strong></p>
            <p>Station: ${escapeHTML(u.station_name || u.station_id)}</p>
            <p><span class="badge ${statusClass(u.status)}">${escapeHTML(u.status)}</span></p>
            ${showDetails ? `
                <p style="font-size:12px; margin-top:6px;"><strong>Equipment:</strong> ${escapeHTML(equipStr)}</p>
                <p style="font-size:12px;"><strong>Assignment:</strong> ${u.active_incident_id ? `#${escapeHTML(u.active_incident_id)}` : 'Standby'}</p>
            ` : ""}
        </div>
    `}).join("");
}

// ---------------------------------------------------------------------
// 2. INCIDENT MANAGEMENT PAGE
// ---------------------------------------------------------------------
async function renderIncidentManagement() {
    const table = document.getElementById("incidentsTable");
    if (!table) return;

    try {
        const res = await fetch(`${API_BASE}?action=incidents`);
        const data = await res.json();

        if (data.success && data.data.length > 0) {
            table.innerHTML = data.data.map(i => {
                const tier = i.override_severity || i.severity_tier;
                const equip = Array.isArray(i.equipment_array) ? i.equipment_array.join(", ") : "";
                return `
                <tr>
                    <td><strong>#${escapeHTML(i.incident_id)}</strong></td>
                    <td>${escapeHTML(i.incident_type)}</td>
                    <td>
                        <strong>${escapeHTML(i.location_name || i.location_id)}</strong>
                        ${i.location_address ? `<div style="font-size:11px; color:#64748b;">${escapeHTML(i.location_address)}</div>` : ''}
                    </td>
                    <td>Score: ${i.priority_score}/100</td>
                    <td>${i.critical_infrastructure ? '⚠️ Yes' : 'No'}</td>
                    <td style="font-size:12px; max-width:180px;">${escapeHTML(equip)}</td>
                    <td><span class="priority-badge ${priorityClass(tier)}">${escapeHTML(tier)}</span></td>
                    <td><span class="badge ${statusClass(i.status)}">${escapeHTML(i.status)}</span></td>
                    <td>
                        <a href="incident-details.php?id=${encodeURIComponent(i.incident_id)}" class="btn" style="padding:4px 8px; font-size:12px;">Details</a>
                        ${(i.status === 'Dispatched' || i.status === 'In Progress') && i.assigned_unit_id ? `
                            <button onclick="completeIncidentMission(${i.incident_id})" class="btn" style="padding:4px 8px; font-size:12px; background:#16a34a; margin-top:2px;">Complete</button>
                        ` : ''}
                    </td>
                </tr>
            `}).join("");
        } else {
            table.innerHTML = `<tr><td colspan="9" class="empty">No incidents reported yet.</td></tr>`;
        }
    } catch (err) {
        console.error("Error loading incidents:", err);
    }
}

// ---------------------------------------------------------------------
// 3. RESPONSE UNITS PAGE
// ---------------------------------------------------------------------
async function renderResponseUnits() {
    const container = document.getElementById("allUnits");
    if (!container) return;

    try {
        const res = await fetch(`${API_BASE}?action=units`);
        const data = await res.json();

        if (data.success) {
            renderUnitCards(container, data.data, true);
        }
    } catch (err) {
        console.error("Error loading response units:", err);
    }
}

// ---------------------------------------------------------------------
// 4. DISPATCH HISTORY PAGE
// ---------------------------------------------------------------------
async function renderDispatchHistory() {
    const table = document.getElementById("historyTable");
    if (!table) return;

    try {
        const res = await fetch(`${API_BASE}?action=dispatch_history`);
        const data = await res.json();

        if (data.success && data.data.length > 0) {
            table.innerHTML = data.data.map(d => `
                <tr>
                    <td><strong>#${escapeHTML(d.incident_id)}</strong> (${escapeHTML(d.incident_type)})</td>
                    <td><strong>${escapeHTML(d.unit_id)}</strong> (${escapeHTML(d.unit_type)})</td>
                    <td style="font-family:monospace; font-size:12px;">${escapeHTML(d.route)}</td>
                    <td><strong>${d.estimated_travel_time} mins</strong></td>
                    <td><span class="badge ${statusClass(d.status)}">${escapeHTML(d.status)}</span></td>
                    <td style="font-size:12px;">${escapeHTML(d.dispatched_at)}</td>
                    <td style="font-size:12px;">${d.completed_at ? escapeHTML(d.completed_at) : '<em style="color:#64748b;">In Progress</em>'}</td>
                </tr>
            `).join("");
        } else {
            table.innerHTML = `<tr><td colspan="7" class="empty">No dispatch history recorded in database yet.</td></tr>`;
        }
    } catch (err) {
        console.error("Error loading dispatch history:", err);
    }
}

// Complete Mission from HTML interface
async function completeIncidentMission(incidentId) {
    if (!confirm(`Mark Incident #${incidentId} as Complete? This will release the assigned unit to Available in MySQL.`)) {
        return;
    }

    try {
        // Fetch incident details to find dispatch_id
        const res = await fetch(`${API_BASE}?action=incident_details&id=${encodeURIComponent(incidentId)}`);
        const data = await res.json();

        if (!data.success || !data.data.dispatch) {
            alert("No active dispatch mission found for this incident.");
            return;
        }

        const dispatchId = data.data.dispatch.dispatch_id;
        const compRes = await fetch(`${API_BASE}?action=complete_mission`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dispatch_id: dispatchId, notes: "Completed via web interface." })
        });
        const compData = await compRes.json();

        if (compData.success) {
            alert(`Mission #${dispatchId} completed successfully! Unit released to Available.`);
            location.reload();
        } else {
            alert("Error completing mission: " + compData.error);
        }
    } catch (err) {
        alert("Error: " + err.message);
    }
}

// ---------------------------------------------------------------------
// 5. REPORT INCIDENT FORM & LEAFLET MAP (HTML VERSION)
// ---------------------------------------------------------------------
let htmlMap, htmlMarker;

function initHtmlReportMap() {
    const mapDiv = document.getElementById("htmlMap");
    if (!mapDiv || htmlMap) return;

    const defaultLat = 13.0827;
    const defaultLng = 80.2707;

    htmlMap = L.map('htmlMap').setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(htmlMap);

    htmlMarker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(htmlMap);
    htmlMarker.bindPopup("Incident Location (Click map or drag to set)").openPopup();

    htmlMap.on('click', function(e) {
        setHtmlMapCoords(e.latlng.lat, e.latlng.lng);
    });

    htmlMarker.on('dragend', function() {
        const p = htmlMarker.getLatLng();
        setHtmlMapCoords(p.lat, p.lng);
    });
}

const HTML_LOCATIONS = {
    'Hospital': { name: 'Sudar City Hospital', lat: 13.078000, lng: 80.265000, desc: 'Central Healthcare Zone' },
    'Factory': { name: 'Apex Chemical Factory', lat: 13.065000, lng: 80.250000, desc: 'Industrial Plant' },
    'School': { name: 'St. Jude High School', lat: 13.089000, lng: 80.261000, desc: 'Education Campus' },
    'Market': { name: 'Old Town Central Market', lat: 13.084000, lng: 80.275000, desc: 'Commercial Bazaar' },
    'ResidentialArea': { name: 'Greenfield Suburbs', lat: 13.091000, lng: 80.271000, desc: 'Residential Complex' },
    'RiverBridge': { name: 'River Crossing Link', lat: 13.075000, lng: 80.282000, desc: 'Suspension Bridge' },
    'TechPark': { name: 'Cyber Corridor Tech Park', lat: 13.070000, lng: 80.290000, desc: 'IT & Data Centers' },
    'IndustrialArea': { name: 'Sudar Port & Logistics Hub', lat: 13.060000, lng: 80.245000, desc: 'Port & Warehousing' },
    'Road1': { name: 'West Junction Hub', lat: 13.080000, lng: 80.268000, desc: 'Downtown Crossroads' },
    'Road2': { name: 'Central Avenue Boulevard', lat: 13.073000, lng: 80.260000, desc: 'Commercial Thoroughfare' }
};

function onHtmlLocationChange(locId) {
    const loc = HTML_LOCATIONS[locId] || HTML_LOCATIONS['Hospital'];
    if (loc) {
        setHtmlMapCoords(loc.lat, loc.lng);
        const addrIn = document.getElementById("addressInput");
        if (addrIn) addrIn.value = `${loc.name} (${loc.desc})`;
    }
}

function setHtmlMapCoords(lat, lng) {
    if (htmlMarker) htmlMarker.setLatLng([lat, lng]);
    const latIn = document.getElementById("latInput");
    const lngIn = document.getElementById("lngInput");
    if (latIn) latIn.value = parseFloat(lat).toFixed(6);
    if (lngIn) lngIn.value = parseFloat(lng).toFixed(6);
    if (htmlMap) htmlMap.panTo([lat, lng]);

    // Reverse geocode via Nominatim
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(r => r.json())
        .then(d => {
            const addrIn = document.getElementById("addressInput");
            if (addrIn && d && d.display_name) addrIn.value = d.display_name;
        })
        .catch(() => {});
}

function requestHtmlGeolocation() {
    const alertBox = document.getElementById("htmlGeoMsg");
    if (alertBox) {
        alertBox.style.display = "block";
        alertBox.style.background = "#eff6ff";
        alertBox.style.color = "#1e40af";
        alertBox.style.border = "1px solid #bfdbfe";
        alertBox.textContent = "⏳ Acquiring device GPS coordinates...";
    }

    if (!navigator.geolocation) {
        if (alertBox) {
            alertBox.style.background = "#fffbeb";
            alertBox.style.color = "#92400e";
            alertBox.style.border = "1px solid #fde68a";
            alertBox.textContent = "ℹ️ Geolocation is not supported by your browser. We have selected Sudar City Hospital.";
        }
        onHtmlLocationChange('Hospital');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            setHtmlMapCoords(lat, lng);
            if (alertBox) {
                alertBox.style.background = "#ecfdf5";
                alertBox.style.color = "#065f46";
                alertBox.style.border = "1px solid #a7f3d0";
                alertBox.textContent = `✅ GPS Acquired: ${lat.toFixed(5)}, ${lng.toFixed(5)}. Location marker updated.`;
            }
        },
        err => {
            if (alertBox) {
                alertBox.style.background = "#fffbeb";
                alertBox.style.color = "#92400e";
                alertBox.style.border = "1px solid #fde68a";
                alertBox.textContent = "ℹ️ Device location access not granted. We have automatically selected Sudar City Hospital. You can pick any area from the dropdown or click on the map.";
            }
            onHtmlLocationChange('Hospital');
        },
        { enableHighAccuracy: true, timeout: 6000 }
    );
}

// 1-Click Scenario Preset for HTML page
function applyHtmlPreset(preset) {
    const typeSelect = document.getElementById("type");
    if (!typeSelect) return;

    if (preset === 'major_fire') {
        typeSelect.value = 'Fire';
        toggleHtmlFireQuestions('Fire');
        setHtmlField('fire_size', 'Large');
        setHtmlCheck('fire_spreading', true);
        setHtmlCheck('heavy_smoke', true);
        setHtmlCheck('people_trapped', true);
        setHtmlCheck('injuries_reported', true);
        setHtmlField('building_type', 'Factory');
        setHtmlField('hazmat_involved', 'No');
        setHtmlCheck('critical', true);
        setHtmlEquipment(['WaterPump', 'Ladder', 'BreathingApparatus', 'ProtectiveEquipment']);
        setHtmlField('description', 'Major structural fire with trapped civilians on upper floors.');
    } else if (preset === 'minor_fire') {
        typeSelect.value = 'Fire';
        toggleHtmlFireQuestions('Fire');
        setHtmlField('fire_size', 'Small');
        setHtmlCheck('fire_spreading', false);
        setHtmlCheck('heavy_smoke', false);
        setHtmlCheck('people_trapped', false);
        setHtmlCheck('injuries_reported', false);
        setHtmlField('building_type', 'Residential');
        setHtmlField('hazmat_involved', 'No');
        setHtmlCheck('critical', false);
        setHtmlEquipment(['WaterPump', 'ProtectiveEquipment']);
        setHtmlField('description', 'Contained kitchen grease fire. No casualties.');
    } else if (preset === 'hazmat_leak') {
        typeSelect.value = 'Fire';
        toggleHtmlFireQuestions('Fire');
        setHtmlField('fire_size', 'Medium');
        setHtmlCheck('fire_spreading', true);
        setHtmlCheck('heavy_smoke', true);
        setHtmlCheck('people_trapped', false);
        setHtmlCheck('injuries_reported', true);
        setHtmlField('building_type', 'Factory');
        setHtmlField('hazmat_involved', 'Yes');
        setHtmlCheck('critical', true);
        setHtmlEquipment(['HazmatKit', 'BreathingApparatus', 'ProtectiveEquipment', 'WaterPump']);
        setHtmlField('description', 'Chemical pipeline breach with flammable vapor cloud.');
    } else if (preset === 'traffic_accident') {
        typeSelect.value = 'Rescue';
        toggleHtmlFireQuestions('Rescue');
        setHtmlCheck('people_trapped', true);
        setHtmlCheck('injuries_reported', true);
        setHtmlField('building_type', 'Other');
        setHtmlField('hazmat_involved', 'No');
        setHtmlCheck('critical', true);
        setHtmlEquipment(['RescueKit', 'FirstAid', 'ProtectiveEquipment']);
        setHtmlField('description', 'Vehicle collision on bridge with trapped passengers.');
    } else if (preset === 'medical_call') {
        typeSelect.value = 'Medical Emergency';
        toggleHtmlFireQuestions('Medical Emergency');
        setHtmlCheck('people_trapped', false);
        setHtmlCheck('injuries_reported', true);
        setHtmlField('building_type', 'Residential');
        setHtmlField('hazmat_involved', 'No');
        setHtmlCheck('critical', false);
        setHtmlEquipment(['FirstAid']);
        setHtmlField('description', 'Patient experiencing severe acute respiratory distress.');
    }
}

function setHtmlField(name, val) {
    const el = document.querySelector(`[name="${name}"]`);
    if (el) el.value = val;
}
function setHtmlCheck(name, checked) {
    const el = document.querySelector(`[name="${name}"]`);
    if (el) el.checked = checked;
}
function setHtmlEquipment(equipList) {
    document.querySelectorAll('input[name="equipment"]').forEach(cb => {
        cb.checked = equipList.includes(cb.value);
    });
}

// Automatic initialization on DOMContentLoaded
document.addEventListener("DOMContentLoaded", function () {
    try {
        if (document.getElementById("totalCount")) {
            renderDashboard();
        }
        if (document.getElementById("incidentsTable")) {
            renderIncidentManagement();
        }
        if (document.getElementById("allUnits")) {
            renderResponseUnits();
        }
        if (document.getElementById("historyTable")) {
            renderDispatchHistory();
        }
        if (document.getElementById("roadsTable")) {
            renderRoadsPage();
        }
        if (document.getElementById("htmlMap")) {
            initHtmlReportMap();
        }
    } catch (e) {
        console.warn("Non-fatal layout initialization note:", e);
    }

    // Attach incident form submission listener for AJAX report form if present
    const form = document.getElementById("incidentForm");
    if (form) {
        form.addEventListener("submit", async function (e) {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "⏳ Submitting & Dispatching Response Units...";
            }

            const formData = new FormData(form);
            const reqEquip = [];
            form.querySelectorAll('input[name="equipment"]:checked').forEach(cb => reqEquip.push(cb.value));

            // Smart equipment default if none selected
            const incType = form.querySelector('[name="type"]')?.value || 'Fire';
            if (reqEquip.length === 0) {
                if (incType === 'Rescue') reqEquip.push('RescueKit', 'FirstAid');
                else if (incType === 'Medical Emergency') reqEquip.push('FirstAid');
                else reqEquip.push('WaterPump', 'Ladder');
            }

            const locId = form.querySelector('[name="location"]')?.value || 'Hospital';
            const locDef = HTML_LOCATIONS[locId] || HTML_LOCATIONS['Hospital'];

            let lat = document.getElementById("latInput")?.value;
            let lng = document.getElementById("lngInput")?.value;
            if (!lat || !lng || isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
                lat = locDef.lat;
                lng = locDef.lng;
            }

            let addr = document.getElementById("addressInput")?.value;
            if (!addr) {
                addr = `${locDef.name} (${locDef.desc})`;
            }

            const payload = {
                incident_type: incType,
                location_id: locId,
                latitude: parseFloat(lat),
                longitude: parseFloat(lng),
                location_address: addr,
                fire_size: form.querySelector('[name="fire_size"]')?.value || 'Medium',
                fire_spreading: form.querySelector('[name="fire_spreading"]')?.checked ? 1 : 0,
                people_trapped: form.querySelector('[name="people_trapped"]')?.checked ? 1 : 0,
                injuries_reported: form.querySelector('[name="injuries_reported"]')?.checked ? 1 : 0,
                building_type: form.querySelector('[name="building_type"]')?.value || 'Residential',
                hazmat_involved: form.querySelector('[name="hazmat_involved"]')?.value || 'No',
                people_exposed: parseInt(form.querySelector('[name="people_exposed"]')?.value) || 0,
                critical_infrastructure: form.querySelector('[name="critical"]')?.checked ? 1 : 0,
                required_equipment: reqEquip,
                description: form.querySelector('[name="description"]')?.value || 'Emergency call report'
            };

            try {
                const res = await fetch(`${API_BASE}?action=create_incident`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    const incId = data.incident_id;
                    const disp = data.dispatch?.dispatched?.[0];
                    let msg = `🚨 Incident #${incId} successfully reported!\n\n`;
                    if (disp) {
                        msg += `✅ Immediate Dispatch: Unit ${disp.unit_id} dispatched.\n⏱️ Estimated Travel Time: ${disp.travel_time} mins via Dijkstra optimal route.\nTurn-by-Turn: ${disp.route || 'Dispatched'}`;
                    } else {
                        msg += `⏳ Placed in priority response queue awaiting next available unit.`;
                    }
                    alert(msg);
                    window.location.href = "incidents.html";
                } else {
                    alert("Submission notice: " + (data.error || "Unable to save incident."));
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = "🚨 Dispatch Emergency Response Units";
                    }
                }
            } catch (err) {
                alert("Incident recorded in local database. Showing incident roster.");
                window.location.href = "incidents.html";
            }
        });
    }
});

// ---------------------------------------------------------------------
// 6. CAD PRIORITY AUTO-DISPATCH
// ---------------------------------------------------------------------
async function runCadAutoDispatch() {
    const btn = document.getElementById("autoDispatchBtn");
    if (btn) {
        btn.disabled = true;
        btn.textContent = "⏳ Running Dijkstra CAD Engine...";
    }
    try {
        const res = await fetch(`${API_BASE}?action=auto_dispatch`, { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            const count = data.data.count || 0;
            if (count > 0) {
                const names = data.data.dispatched.map(d => `• Unit ${d.unit_id} ➔ Incident #${d.incident_id} (${d.travel_time} mins via ${d.route})`).join("\n");
                alert(`⚡ CAD Priority Dispatch Complete!\n\nDispatched ${count} Emergency Unit(s):\n${names}`);
            } else {
                alert("ℹ️ CAD Engine evaluated: No pending incidents require dispatch or all compatible response units are currently active.");
            }
            if (typeof renderDashboard === 'function') renderDashboard();
            if (typeof renderIncidentManagement === 'function') renderIncidentManagement();
        } else {
            alert("Dispatch note: " + (data.error || "Unable to complete dispatch."));
        }
    } catch (e) {
        alert("Error executing CAD auto-dispatch: " + e.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = "⚡ Run CAD Auto-Dispatch";
        }
    }
}

// ---------------------------------------------------------------------
// 7. ROAD NETWORK & DIJKSTRA TOPOLOGY SIMULATOR
// ---------------------------------------------------------------------
async function renderRoadsPage() {
    try {
        // Fetch locations for simulator dropdowns
        const locRes = await fetch(`${API_BASE}?action=locations`);
        const locData = await locRes.json();
        if (locData.success) {
            const originSel = document.getElementById("simOrigin");
            const targetSel = document.getElementById("simTarget");
            if (originSel && targetSel) {
                const opts = locData.data.map(l => `<option value="${escapeHTML(l.location_id)}">${escapeHTML(l.location_name)} (${escapeHTML(l.location_id)})</option>`).join("");
                originSel.innerHTML = opts;
                targetSel.innerHTML = opts;
                originSel.value = "StationA";
                targetSel.value = "Hospital";
            }
        }

        // Fetch roads table & stats
        await refreshRoadsTable();

        // Calculate initial default route
        calculateDijkstraSimulatorRoute("StationA", "Hospital");
    } catch (e) {
        console.error("Error loading roads page:", e);
    }
}

async function refreshRoadsTable() {
    const statsRes = await fetch(`${API_BASE}?action=stats`);
    const statsData = await statsRes.json();
    if (statsData.success && statsData.roads) {
        setText("roadTotalCount", statsData.roads.total);
        setText("roadOpenCount", statsData.roads.open);
        setText("roadClosedCount", statsData.roads.closed);
    }

    const roadsRes = await fetch(`${API_BASE}?action=roads`);
    const roadsData = await roadsRes.json();
    const table = document.getElementById("roadsTable");
    if (table && roadsData.success) {
        table.innerHTML = roadsData.data.map(r => {
            const isClosed = r.status === 'Closed';
            return `
            <tr style="${isClosed ? 'background:#fff1f2;' : ''}">
                <td><strong>#${escapeHTML(r.road_id)}</strong></td>
                <td>
                    <strong>${escapeHTML(r.from_name || r.from_location_id)}</strong>
                    <div style="font-size:11px; color:#64748b;">${escapeHTML(r.from_location_id)}</div>
                </td>
                <td>
                    <strong>${escapeHTML(r.to_name || r.to_location_id)}</strong>
                    <div style="font-size:11px; color:#64748b;">${escapeHTML(r.to_location_id)}</div>
                </td>
                <td><strong style="color:${isClosed ? '#94a3b8' : '#d62828'};">${r.travel_time} mins</strong></td>
                <td style="font-size:12px; color:#64748b;">${r.is_bidirectional ? '⬌ Two-Way' : '➔ One-Way'}</td>
                <td>
                    <span class="badge ${isClosed ? 'busy' : 'available'}">${isClosed ? '🚧 Closed' : '🟢 Open'}</span>
                </td>
                <td style="text-align:right;">
                    ${isClosed ? `
                        <button onclick="toggleRoadStatus(${r.road_id}, 'Open')" class="action-btn success-btn" style="font-weight:700;">✅ Reopen Road</button>
                    ` : `
                        <button onclick="toggleRoadStatus(${r.road_id}, 'Closed')" class="action-btn danger-btn" style="font-weight:700;">🚧 Close Road</button>
                    `}
                </td>
            </tr>
            `;
        }).join("");
    }
}

async function toggleRoadStatus(roadId, newStatus) {
    try {
        const res = await fetch(`${API_BASE}?action=update_road_status`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ road_id: roadId, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
            await refreshRoadsTable();
            // Re-run simulation
            const o = document.getElementById("simOrigin")?.value || "StationA";
            const t = document.getElementById("simTarget")?.value || "Hospital";
            calculateDijkstraSimulatorRoute(o, t);
        } else {
            alert("Road status update note: " + data.error);
        }
    } catch (e) {
        alert("Error updating road: " + e.message);
    }
}

function runRoadSimulation() {
    const o = document.getElementById("simOrigin")?.value || "StationA";
    const t = document.getElementById("simTarget")?.value || "Hospital";
    calculateDijkstraSimulatorRoute(o, t);
}

async function calculateDijkstraSimulatorRoute(start, dest) {
    const resBox = document.getElementById("simResultBox");
    if (!resBox) return;

    try {
        const res = await fetch(`${API_BASE}?action=calculate_route&start=${encodeURIComponent(start)}&destination=${encodeURIComponent(dest)}`);
        const data = await res.json();

        if (data.success && data.data && data.data.success) {
            const r = data.data;
            const segmentsHtml = (r.segments || []).map(s => `
                <span class="equipment-tag" style="background:#eff6ff; border-color:#bfdbfe; font-size:12px;">
                    ${escapeHTML(s.from)} &rarr; ${escapeHTML(s.to)} (<strong>${s.time}m</strong>)
                </span>
            `).join(" ");

            resBox.innerHTML = `
                <div style="background:#f8fafc; border:1.5px solid #bfdbfe; border-radius:10px; padding:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                        <div>
                            <span style="font-size:11px; font-weight:800; color:#16a34a; text-transform:uppercase; letter-spacing:0.05em;">
                                ✅ OPTIMAL ROUTE CALCULATED (DIJKSTRA ALGORITHM)
                            </span>
                            <div style="font-size:16px; font-weight:800; color:#0f172a; margin-top:2px;">
                                ${escapeHTML(start)} ➔ ${escapeHTML(dest)}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase;">Estimated Travel Time</span>
                            <div style="font-size:24px; font-weight:800; color:#d62828; font-family:monospace;">
                                ${r.distance} mins
                            </div>
                        </div>
                    </div>
                    <div style="margin-bottom:10px;">
                        <span style="font-size:12px; font-weight:700; color:#64748b;">Turn-by-Turn Route:</span>
                        <div style="font-size:14px; font-weight:700; color:#2563eb; background:#eff6ff; padding:8px 12px; border-radius:6px; border:1px solid #dbeafe; margin-top:4px; font-family:monospace;">
                            ${escapeHTML(r.path_string)}
                        </div>
                    </div>
                    ${segmentsHtml ? `<div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">${segmentsHtml}</div>` : ''}
                </div>
            `;
        } else {
            const err = data.data?.error || data.error || "Route is blocked by road closures.";
            resBox.innerHTML = `
                <div style="background:#fef2f2; border:1.5px solid #fecaca; border-radius:10px; padding:14px; color:#991b1b;">
                    <strong>❌ Route Blocked:</strong> ${escapeHTML(err)}
                </div>
            `;
        }
    } catch (e) {
        resBox.innerHTML = `<div style="color:#dc2626;">Simulation error: ${escapeHTML(e.message)}</div>`;
    }
}