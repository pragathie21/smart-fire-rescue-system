<?php
/**
 * Smart Fire and Rescue Response System
 * Application Configuration & System Tokens
 */

// Error reporting for local development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------
// 1. Database Credentials
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_fire_rescue');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// 2. System Metadata & Geography
// ---------------------------------------------------------------------
define('APP_NAME', 'Smart Fire & Rescue Response System');
define('APP_SHORT_NAME', 'Sudar Rescue Command');
define('TOWN_NAME', 'Sudar');
define('APP_VERSION', '2.0.0');

// Fictional Town Geographical Center (for map default viewport)
define('SUDAR_DEFAULT_LAT', 13.0827);
define('SUDAR_DEFAULT_LNG', 80.2707);
define('SUDAR_DEFAULT_ZOOM', 13);

// Dynamic Base URL Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', $scriptDir);

if (strpos($scriptDir, '/smart-fire-rescue') !== false) {
    define('BASE_URL', $protocol . $host . '/smart-fire-rescue/');
} elseif (strpos($scriptDir, '/mini project') !== false || strpos($scriptDir, '/mini%20project') !== false) {
    define('BASE_URL', $protocol . $host . '/mini project/');
} else {
    define('BASE_URL', rtrim($protocol . $host . $scriptDir, '/') . '/');
}

// ---------------------------------------------------------------------
// 3. Allowed Emergency Categories & Equipment
// ---------------------------------------------------------------------
define('ALLOWED_INCIDENT_TYPES', ['Fire', 'Rescue', 'Medical Emergency']);

define('ALLOWED_EQUIPMENT', [
    'WaterPump',
    'Ladder',
    'FirstAid',
    'RescueKit',
    'BreathingApparatus',
    'ProtectiveEquipment',
    'HazmatKit'
]);

// Equipment Metadata: labels, icons, category
define('EQUIPMENT_METADATA', [
    'WaterPump' => [
        'name'     => 'Water Pump',
        'icon'     => '💧',
        'category' => 'Fire Suppression',
        'desc'     => 'High-pressure water pump for fire knockdown'
    ],
    'Ladder' => [
        'name'     => 'Ladder',
        'icon'     => '🪜',
        'category' => 'Access & Extraction',
        'desc'     => 'Heavy aerial ladder for elevated/multi-story rescues'
    ],
    'FirstAid' => [
        'name'     => 'First Aid Kit',
        'icon'     => '🩹',
        'category' => 'Medical Care',
        'desc'     => 'Trauma dressings, burn treatment, and life support supplies'
    ],
    'RescueKit' => [
        'name'     => 'Rescue Kit',
        'icon'     => '🧰',
        'category' => 'Heavy Extraction',
        'desc'     => 'Hydraulic spreaders, shears, and extrication gear'
    ],
    'BreathingApparatus' => [
        'name'     => 'Breathing Apparatus',
        'icon'     => '🤿',
        'category' => 'Respiratory Protection',
        'desc'     => 'SCBA air tanks and masks for toxic smoke and enclosed areas'
    ],
    'ProtectiveEquipment' => [
        'name'     => 'Protective Equipment',
        'icon'     => '🛡️',
        'category' => 'Personal Safety',
        'desc'     => 'Bunker coats, heat-resistant helmets, and flash hoods'
    ],
    'HazmatKit' => [
        'name'     => 'Hazardous Material Kit',
        'icon'     => '☣️',
        'category' => 'Hazmat Containment',
        'desc'     => 'Chemical neutralization, detector tubes, and containment suits'
    ]
]);

// ---------------------------------------------------------------------
// 4. Severity Scoring Weights (Requirement 3: Rules-Based Decision Support)
// ---------------------------------------------------------------------
define('SEVERITY_WEIGHTS', [
    // Base points by incident type
    'type_base' => [
        'Fire'              => 20,
        'Rescue'            => 25,
        'Medical Emergency' => 20
    ],
    // High-impact conditions
    'people_trapped'    => 30, // Guaranteed minimum HIGH severity
    'injuries_reported' => 20,
    'hazmat_yes'        => 25,
    'hazmat_unknown'    => 10,
    'critical_infra'    => 35, // Highest priority asset
    
    // Fire-specific factors
    'fire_size' => [
        'Large'  => 25,
        'Medium' => 15,
        'Small'  => 5
    ],
    'fire_spreading'    => 20,
    'heavy_smoke'       => 10,

    // Building/Location risk multiplier
    'building_type' => [
        'Hospital'    => 25,
        'School'      => 20,
        'Factory'     => 20,
        'Market'      => 15,
        'Residential' => 10,
        'Other'       => 5
    ],

    // Population exposure points
    'population_exposure' => [
        'high'     => 25, // > 50 people
        'medium'   => 15, // 6-50 people
        'low'      => 5,  // 1-5 people
        'none'     => 0
    ],

    // Severity Thresholds (0 to 100)
    'thresholds' => [
        'critical' => 80,
        'high'     => 55,
        'medium'   => 30
    ]
]);

// ---------------------------------------------------------------------
// 5. SMTP Email Configuration (Requirement 6)
// ---------------------------------------------------------------------
// Load optional custom credentials from smtp_config.php if present
if (file_exists(__DIR__ . '/smtp_config.php')) {
    require_once __DIR__ . '/smtp_config.php';
}

if (!defined('SMTP_HOST'))           define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT'))           define('SMTP_PORT', 1025); // 1025 (MailHog) or 587/465 (Gmail/Outlook)
if (!defined('SMTP_AUTH'))           define('SMTP_AUTH', false);
if (!defined('SMTP_USER'))           define('SMTP_USER', '');
if (!defined('SMTP_PASS'))           define('SMTP_PASS', '');
if (!defined('SMTP_SECURE'))         define('SMTP_SECURE', ''); // '', 'tls', or 'ssl'
if (!defined('SMTP_FROM_EMAIL'))     define('SMTP_FROM_EMAIL', 'cad@sudar.gov.in');
if (!defined('SMTP_FROM_NAME'))      define('SMTP_FROM_NAME', 'Sudar Emergency Dispatch');
if (!defined('OFFICER_NOTIFY_EMAIL'))define('OFFICER_NOTIFY_EMAIL', 'duty-officer@sudar.gov.in');
// When true, simulates delivery to local file storage when no live SMTP server is configured
if (!defined('SMTP_LOG_FALLBACK'))   define('SMTP_LOG_FALLBACK', true);

// ---------------------------------------------------------------------
// 6. Timezone
// ---------------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');
