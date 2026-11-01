<?php
/**
 * Smart Fire and Rescue Response System
 * Index Entrypoint - Redirects to Dashboard
 */

require_once __DIR__ . '/config.php';
header("Location: " . BASE_URL . "dashboard.php");
exit;
