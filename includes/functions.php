<?php
/**
 * Smart Fire and Rescue Response System
 * Core Helper Functions and Priority Sorting Algorithm
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/SeverityScorer.php';

/**
 * Sanitize string output for safe HTML display
 */
function sanitize(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Set a session flash message
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'][] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear session flash messages
 */
function get_flash_messages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Format datetime string into human readable display
 */
function format_datetime(?string $datetime): string {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

/**
 * Format time ago (relative time)
 */
function time_ago(?string $datetime): string {
    if (empty($datetime)) return 'N/A';
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Render visual badge for incident/unit/road status
 */
function render_status_badge(string $status): string {
    $classMap = [
        'Available'      => 'badge-available',
        'Open'           => 'badge-available',
        'Completed'      => 'badge-completed',
        'Assigned'       => 'badge-assigned',
        'Dispatched'     => 'badge-assigned',
        'In Progress'    => 'badge-in-progress',
        'Active'         => 'badge-in-progress',
        'Pending'        => 'badge-pending',
        'Busy'           => 'badge-busy',
        'Closed'         => 'badge-closed',
        'Out of Service' => 'badge-out-of-service',
        'Cancelled'      => 'badge-cancelled'
    ];

    $badgeClass = $classMap[$status] ?? 'badge-secondary';
    return '<span class="status-badge ' . $badgeClass . '">' . sanitize($status) . '</span>';
}

/**
 * Render visual badge for incident priority / severity tier
 */
function render_priority_badge(?string $tier = 'Medium'): string {
    $tier = $tier ?: 'Medium';
    $tierUpper = strtoupper(trim($tier));
    $classMap = [
        'CRITICAL' => 'badge-priority-critical',
        'HIGH'     => 'badge-priority-high',
        'MEDIUM'   => 'badge-priority-medium',
        'LOW'      => 'badge-priority-low'
    ];
    $iconMap = [
        'CRITICAL' => '🔥',
        'HIGH'     => '⚡',
        'MEDIUM'   => '⚠️',
        'LOW'      => 'ℹ️'
    ];
    $badgeClass = $classMap[$tierUpper] ?? 'badge-secondary';
    $icon = $iconMap[$tierUpper] ?? '📌';
    return '<span class="priority-badge ' . $badgeClass . '">' . $icon . ' ' . sanitize($tierUpper) . '</span>';
}

/**
 * Render visual badge for incident severity tier (with override detection)
 */
function render_severity_badge(?string $tier, ?string $override = null): string {
    $activeTier = !empty($override) ? $override : ($tier ?: 'Medium');
    $badge = render_priority_badge($activeTier);
    if (!empty($override)) {
        $badge .= ' <span style="font-size:0.68rem; color:#dc2626; font-weight:700;">(Overridden)</span>';
    }
    return $badge;
}

/**
 * Render equipment item badge with icon (supports all 7 types)
 */
function render_equipment_badge(string $equip): string {
    $meta = defined('EQUIPMENT_METADATA') && isset(EQUIPMENT_METADATA[$equip])
        ? EQUIPMENT_METADATA[$equip]
        : null;

    if ($meta) {
        $icon = $meta['icon'];
        $name = $meta['name'];
    } else {
        $icons = [
            'WaterPump'           => '💧',
            'Ladder'              => '🪜',
            'FirstAid'            => '🩹',
            'RescueKit'           => '🧰',
            'BreathingApparatus'  => '🤿',
            'ProtectiveEquipment' => '🛡️',
            'HazmatKit'           => '☣️'
        ];
        $icon = $icons[$equip] ?? '⚙️';
        $name = $equip;
    }

    return '<span class="equipment-tag">' . $icon . ' ' . sanitize($name) . '</span>';
}

/**
 * Render email delivery status badge
 */
function render_email_badge(string $status, ?string $error = null): string {
    if ($status === 'Sent') {
        return '<span class="status-badge badge-available" title="Email alert transmitted successfully">✉️ Sent</span>';
    } elseif ($status === 'Failed') {
        $tip = !empty($error) ? ' title="' . sanitize($error) . '"' : '';
        return '<span class="status-badge badge-closed"' . $tip . '>❌ Delivery Failed</span>';
    } elseif ($status === 'Disabled') {
        return '<span class="status-badge badge-secondary">✉️ Disabled</span>';
    } else {
        return '<span class="status-badge badge-pending">⏳ Pending</span>';
    }
}

/**
 * Incident Priority Evaluation Algorithm (delegates to SeverityScorer)
 */
function calculate_incident_priority(array $incident): array {
    return SeverityScorer::evaluate($incident);
}

/**
 * Priority Sorting Algorithm for Incidents:
 * 1. Critical infrastructure affected (1 beats 0, strictly highest precedence)
 * 2. Higher priority score (100 down to 1)
 * 3. Earlier reported time
 */
function sort_incidents_by_priority(array &$incidents): void {
    usort($incidents, function ($a, $b) {
        // Criterion 1: Critical Infrastructure (1 beats 0 unconditionally)
        $critA = (int)($a['critical_infrastructure'] ?? 0);
        $critB = (int)($b['critical_infrastructure'] ?? 0);
        if ($critA !== $critB) {
            return $critB <=> $critA;
        }

        // Criterion 2: Priority Score (100 to 1)
        $scoreA = (int)($a['priority_score'] ?? 0);
        $scoreB = (int)($b['priority_score'] ?? 0);
        if ($scoreA !== $scoreB) {
            return $scoreB <=> $scoreA;
        }

        // Criterion 3: Earlier reported time
        $timeA = strtotime($a['reported_at'] ?? 'now');
        $timeB = strtotime($b['reported_at'] ?? 'now');
        return $timeA <=> $timeB;
    });
}
