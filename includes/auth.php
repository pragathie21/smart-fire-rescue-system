<?php
/**
 * Smart Fire and Rescue Response System
 * Authentication & CSRF Protection Helpers
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/**
 * Generate or retrieve the current session's CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input element containing the CSRF token
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify that the submitted CSRF token matches the session token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Guard state-changing POST requests against CSRF attacks
 */
function require_csrf_token(): void {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash'][] = [
            'type'    => 'danger',
            'message' => 'Security validation failed (Invalid or expired CSRF token). Please try submitting again.'
        ];
        $referer = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_URL') ? BASE_URL : 'dashboard.php');
        header("Location: " . $referer);
        exit;
    }
}

/**
 * Check if a command user is currently authenticated in session
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']['user_id']);
}

/**
 * Retrieve the currently logged in user profile
 */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Store user session upon successful login
 */
function login_user(array $user): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'user_id'   => (int)$user['user_id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'email'     => $user['email']
    ];
}

/**
 * Clear session and log out
 */
function logout_user(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    unset($_SESSION['user']);
}

/**
 * Require authorized login for protected officer/admin operations
 */
function require_auth(string $redirectTo = 'login.php'): void {
    if (!is_logged_in()) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash'][] = [
            'type'    => 'warning',
            'message' => 'Please log in with an authorized officer or administrator account to perform this action.'
        ];
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? (BASE_URL . 'dashboard.php');
        header("Location: " . BASE_URL . $redirectTo);
        exit;
    }
}

/**
 * Require specific role (e.g. 'admin' or 'officer')
 */
function require_role($roles, string $redirectTo = 'dashboard.php'): void {
    require_auth();
    $roles = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['user']['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash'][] = [
            'type'    => 'danger',
            'message' => 'Access Denied: You do not possess the required permissions (' . implode(', ', $roles) . ').'
        ];
        header("Location: " . BASE_URL . $redirectTo);
        exit;
    }
}
