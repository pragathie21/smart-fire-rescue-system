<?php
/**
 * Smart Fire and Rescue Response System
 * Login - Authorized Officer & Command Authentication
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Command Personnel Login';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    
    // Check if quick demo login was clicked
    $demoRole = $_POST['demo_login'] ?? null;
    
    if ($demoRole) {
        $username = $demoRole; // 'officer', 'admin', or 'dispatcher'
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            login_user($user);
            set_flash('success', "Welcome back, <strong>" . sanitize($user['full_name']) . "</strong> (" . ucfirst($user['role']) . "). Session authenticated.");
            $redirect = $_SESSION['login_redirect'] ?? (BASE_URL . 'dashboard.php');
            unset($_SESSION['login_redirect']);
            header("Location: " . $redirect);
            exit;
        }
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Both username and password are required.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                set_flash('success', "Welcome back, <strong>" . sanitize($user['full_name']) . "</strong>. Session authenticated.");
                $redirect = $_SESSION['login_redirect'] ?? (BASE_URL . 'dashboard.php');
                unset($_SESSION['login_redirect']);
                header("Location: " . $redirect);
                exit;
            } else {
                $error = 'Invalid username or password. Please verify your credentials.';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 40px auto;">
    <div class="card" style="border-top: 4px solid var(--color-primary); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);">
        <div class="card-header" style="text-align: center; display:block; padding: 24px 20px 16px;">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">🛡️</div>
            <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--color-sidebar); margin-bottom: 4px;">
                Command Authentication
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem;">
                Authorized personnel portal for <?= TOWN_NAME ?> Emergency Response
            </p>
        </div>

        <div class="card-body" style="padding: 24px;">
            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 18px; font-size: 0.85rem;">
                    ❌ <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>login.php">
                <?= csrf_field() ?>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" for="username">Username / Badge ID</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="e.g. officer, admin" required autofocus>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter secure password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-weight: 700;">
                    <span>🔒</span> Authenticate & Log In
                </button>
            </form>

            <div style="margin: 24px 0 16px; border-top: 1px solid var(--color-card-border); position: relative; text-align: center;">
                <span style="background: #ffffff; padding: 0 10px; font-size: 0.75rem; color: var(--color-text-muted); position: relative; top: -10px;">
                    QUICK DEMO ACCESS (Academic Prototype)
                </span>
            </div>

            <!-- Quick 1-Click Demo Buttons -->
            <form method="POST" action="<?= BASE_URL ?>login.php" style="display:flex; flex-direction:column; gap:8px;">
                <?= csrf_field() ?>
                <button type="submit" name="demo_login" value="officer" class="btn btn-secondary btn-sm" style="justify-content: flex-start; text-align: left;">
                    <span>🧑‍🚒</span>
                    <div>
                        <strong>Duty Officer</strong>
                        <div style="font-size:0.7rem; color:var(--color-text-muted);">Captain John Ray &bull; Can complete missions & override triage</div>
                    </div>
                </button>

                <button type="submit" name="demo_login" value="admin" class="btn btn-secondary btn-sm" style="justify-content: flex-start; text-align: left;">
                    <span>⭐</span>
                    <div>
                        <strong>Chief Administrator</strong>
                        <div style="font-size:0.7rem; color:var(--color-text-muted);">Chief Controller Smith &bull; Full command permissions</div>
                    </div>
                </button>

                <button type="submit" name="demo_login" value="dispatcher" class="btn btn-secondary btn-sm" style="justify-content: flex-start; text-align: left;">
                    <span>📡</span>
                    <div>
                        <strong>CAD Dispatcher</strong>
                        <div style="font-size:0.7rem; color:var(--color-text-muted);">CAD Operator Adams &bull; Triage and dispatch coordinator</div>
                    </div>
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
