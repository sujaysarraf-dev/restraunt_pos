<?php
// Start session for CSRF token generation
if (session_status() === PHP_SESSION_NONE) {
    if (file_exists(__DIR__ . '/../config/session_config.php')) {
        require_once __DIR__ . '/../config/session_config.php';
        configureSecureSession();
    } else {
        session_start();
    }
}

// Generate CSRF token for login form
if (file_exists(__DIR__ . '/../config/csrf.php')) {
    require_once __DIR__ . '/../config/csrf.php';
    $csrfToken = getCSRFToken();
} else {
    // Fallback if CSRF config doesn't exist
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['csrf_token'];
}

require_once __DIR__ . '/../db_connection.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (function_exists('validateCSRFPost')) {
        try {
            validateCSRFPost();
        } catch (Exception $e) {
            $error = 'Invalid security token. Please refresh the page and try again.';
        }
    }
    
    if (empty($error)) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        try {
            $stmt = $pdo->prepare('SELECT * FROM super_admins WHERE username = :u AND is_active = 1 LIMIT 1');
            $stmt->execute([':u' => $username]);
            $sa = $stmt->fetch();
            if ($sa && password_verify($password, $sa['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                if (function_exists('regenerateSessionId')) {
                    regenerateSessionId();
                } else {
                    session_regenerate_id(true);
                }
                
                // Regenerate CSRF token after successful login
                if (function_exists('regenerateCSRFToken')) {
                    regenerateCSRFToken();
                }
                
                $_SESSION['superadmin_id'] = $sa['id'];
                $_SESSION['superadmin_username'] = $sa['username'];
                $_SESSION['last_activity'] = time();
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Superadmin Login</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">
  <style>
    body { margin:0; font-family: Inter, system-ui, Arial; background:#f4f6fb; min-height:100vh; display:grid; place-items:center; }
    .card { background:#fff; width:100%; max-width:400px; padding:24px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
    h1 { margin:0 0 16px; font-size:1.4rem; }
    .form-group { margin-bottom:12px; }
    label { font-weight:600; display:block; margin-bottom:6px; }
    input { width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:8px; font-size:1rem; }
    input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.15); }
    .btn { width:100%; padding:12px; border:none; background:#151A2D; color:#fff; border-radius:8px; font-weight:700; cursor:pointer; }
    .error { color:#dc2626; margin:8px 0; font-weight:600; }
  </style>
  </head>
  <body>
    <div class="card">
      <h1>Superadmin Login</h1>
      <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button class="btn" type="submit">Sign In</button>
      </form>
    </div>
  </body>
  </html>


