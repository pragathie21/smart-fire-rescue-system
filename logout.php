<?php
/**
 * Smart Fire and Rescue Response System
 * Logout Action
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout_user();
set_flash('info', 'You have been safely logged out of the Command CAD system.');
header("Location: " . BASE_URL . "login.php");
exit;
