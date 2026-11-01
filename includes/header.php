<?php
/**
 * Smart Fire and Rescue Response System
 * Reusable Header Layout
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Emergency Operations Center';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> | <?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS for OpenStreetMap (Requirement 2: No private API keys) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Core Application CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body class="app-body">
    <div class="app-layout">
        <!-- Sidebar Component -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="app-main">
            <!-- Top Navigation Bar -->
            <!-- Top Navigation Bar (Clean & Minimalist) -->
            <header class="top-nav">
                <div class="nav-left">
                    <button class="mobile-menu-btn" id="sidebarToggle" aria-label="Toggle Navigation">
                        <span></span><span></span><span></span>
                    </button>
                    <span style="font-size: 0.95rem; font-weight: 700; color: var(--color-sidebar); display: flex; align-items: center; gap: 8px;">
                        <?= sanitize($pageTitle) ?>
                    </span>
                </div>
                <div class="nav-right" style="display: flex; align-items: center; gap: 14px;">
                    <div class="system-clock" id="systemClock">
                        <?= date('H:i:s') ?> IST
                    </div>
                    <a href="<?= BASE_URL ?>report-incident.php" class="btn btn-emergency-cta" style="padding: 7px 16px; font-size: 0.85rem;">
                        <span class="btn-icon">🚨</span> Report Incident
                    </a>
                </div>
            </header>

            <!-- Flash Alert Messages -->
            <div class="flash-container">
                <?php $flashMessages = get_flash_messages(); ?>
                <?php foreach ($flashMessages as $flash): ?>
                    <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible" role="alert">
                        <div class="alert-icon">
                            <?php if ($flash['type'] === 'success'): ?>✅
                            <?php elseif ($flash['type'] === 'danger'): ?>❌
                            <?php elseif ($flash['type'] === 'warning'): ?>⚠️
                            <?php else: ?>ℹ️<?php endif; ?>
                        </div>
                        <div class="alert-content">
                            <?= $flash['message'] ?>
                        </div>
                        <button type="button" class="alert-close" aria-label="Close" onclick="this.parentElement.remove();">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Page Inner Content -->
            <main class="page-container">
