<?php
// Include secure session configuration
require_once __DIR__ . '/../config/session_config.php';
startSecureSession();

// Check if user is already logged in and session is valid
if (isSessionValid() && (isset($_SESSION['user_id']) || isset($_SESSION['staff_id'])) && isset($_SESSION['username']) && isset($_SESSION['restaurant_id'])) {
    // User is already logged in, redirect to appropriate dashboard
    if (isset($_SESSION['staff_id']) && isset($_SESSION['role'])) {
        $role = $_SESSION['role'];
        switch ($role) {
            case 'Waiter':
                header('Location: ../views/waiter_dashboard.php');
                exit();
            case 'Chef':
                header('Location: ../views/chef_dashboard.php');
                exit();
            case 'Manager':
                header('Location: ../views/manager_dashboard.php');
                exit();
            default:
                header('Location: ../views/dashboard.php');
                exit();
        }
    } else {
        // Admin user
        header('Location: ../views/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Restro Grow</title>
    <link rel="icon" type="image/png" href="../assets/images/logo-transparent.png">
    <link rel="apple-touch-icon" href="../assets/images/logo-transparent.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Background patterns */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.06) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        
        .login-wrapper {
            position: relative;
            z-index: 1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-y: auto;
        }
        
        .login-container {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 32px;
            align-items: center;
        }
        
        /* Left side - Logo and heading */
        .login-left {
            text-align: center;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 32px;
        }
        
        .logo-img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        .logo-text {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .login-heading {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 12px 0;
            line-height: 1.2;
        }
        
        .login-tagline {
            color: rgba(255, 255, 255, 0.85);
            font-size: 1rem;
            margin: 0;
            font-weight: 400;
        }
        
        /* Right side - Form card */
        .login-right {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .auth-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        
        .auth-header {
            margin-bottom: 28px;
        }
        
        .auth-header h1 {
            color: #151A2D;
            margin: 0 0 8px 0;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .auth-header p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .forgot-password-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-password-link:hover {
            color: #764ba2;
        }
        
        .btn {
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.95rem;
        }
        
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
        }
        
        .signup-link a:hover {
            color: #764ba2;
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .demo-credentials {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 12px;
            margin-top: 20px;
            font-size: 0.8rem;
        }
        
        .demo-credentials h4 {
            margin: 0 0 8px 0;
            color: #1e40af;
            font-size: 0.9rem;
        }
        
        .demo-credentials p {
            margin: 4px 0;
            color: #1e40af;
        }
        
        /* Signup form styling */
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        /* Forgot Password Modal */
        .forgot-password-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .forgot-password-modal.active {
            display: flex;
        }
        
        .forgot-password-content {
            background: white;
            border-radius: 24px;
            padding: 32px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .forgot-password-content h2 {
            margin: 0 0 12px 0;
            color: #151A2D;
            font-size: 1.5rem;
        }
        
        .forgot-password-content p {
            color: #666;
            margin: 0 0 24px 0;
            font-size: 0.95rem;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            color: #151A2D;
            background: #f3f4f6;
        }
        
        .btn-outline {
            background: #f5f5f5;
            color: #333;
            border: 2px solid #e0e0e0;
            margin-top: 12px;
        }
        
        .btn-outline:hover {
            background: #e5e7eb;
        }
        
        /* Desktop layout */
        @media (min-width: 768px) {
            .login-wrapper {
                padding: 32px;
            }
            
            .login-container {
                grid-template-columns: 1fr 1fr;
                gap: 48px;
            }
            
            .login-left {
                text-align: left;
            }
            
            .logo-section {
                justify-content: flex-start;
                margin-bottom: 28px;
            }
            
            .login-heading {
                font-size: 2.75rem;
                margin-bottom: 10px;
            }
            
            .login-tagline {
                font-size: 1.05rem;
            }
            
            .auth-container {
                padding: 44px;
                max-width: 460px;
            }
        }
        
        @media (min-width: 1024px) {
            .login-heading {
                font-size: 3rem;
            }
        }
        
        /* Footer */
        .login-footer {
            position: fixed;
            bottom: 16px;
            left: 24px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            z-index: 1;
        }
        
        @media (max-width: 767px) {
            body {
                overflow: hidden;
            }
            
            .login-footer {
                display: none;
            }
            
            .login-wrapper {
                padding: 0;
                align-items: stretch;
                height: 100vh;
                overflow: hidden;
            }
            
            .login-container {
                gap: 0;
                height: 100%;
                display: flex;
                align-items: stretch;
            }
            
            .login-left {
                display: none;
            }
            
            .login-right {
                width: 100%;
                display: flex;
                align-items: stretch;
            }
            
            .auth-container {
                padding: 20px;
                max-width: 100%;
                width: 100%;
                border-radius: 0;
                box-shadow: none;
                display: flex;
                flex-direction: column;
                justify-content: center;
                overflow-y: auto;
            }
            
            .auth-header {
                margin-bottom: 16px;
            }
            
            .auth-header h1 {
                font-size: 1.5rem;
                margin-bottom: 4px;
            }
            
            .auth-header p {
                font-size: 0.85rem;
            }
            
            .form-group {
                margin-bottom: 14px;
            }
            
            .form-group label {
                font-size: 0.9rem;
                margin-bottom: 5px;
            }
            
            .form-group input {
                padding: 12px 14px;
                font-size: 0.95rem;
            }
            
            .form-actions {
                margin-bottom: 14px;
            }
            
            .signup-link {
                margin-top: 14px;
                font-size: 0.85rem;
            }
            
            .demo-credentials {
                padding: 8px 12px;
                margin-top: 12px;
                font-size: 0.7rem;
            }
            
            .demo-credentials h4 {
                font-size: 0.8rem;
                margin-bottom: 4px;
            }
            
            .demo-credentials p {
                margin: 2px 0;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Left Side - Logo and Heading -->
            <div class="login-left">
                <div class="logo-section">
                    <img src="../assets/images/logo-transparent.png" alt="Restro Grow Logo" class="logo-img">
                    <span class="logo-text">Restro Grow</span>
                </div>
                <h1 class="login-heading">Login into your account</h1>
                <p class="login-tagline">Let us make your restaurant grow!</p>
            </div>
            
            <!-- Right Side - Form Card -->
            <div class="login-right">
                <div class="auth-container">
                    <div class="auth-header">
                        <h1>Welcome Back</h1>
                        <p>Enter your credentials to access your account</p>
                    </div>
                    
                    <!-- Login Form -->
                    <form id="loginForm" class="auth-form active">
                        <div class="form-group">
                            <label for="loginUsername">Email</label>
                            <input type="text" id="loginUsername" name="username" required placeholder="name@example.com">
                        </div>
                        <div class="form-group">
                            <label for="loginPassword">Password</label>
                            <input type="password" id="loginPassword" name="password" required placeholder="Your password">
                        </div>
                        <div class="form-actions">
                            <a href="#" id="forgotPasswordLink" class="forgot-password-link">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary" id="loginBtn">Login</button>
                        <div class="signup-link">
                            Don't have an account? <a href="#" onclick="event.preventDefault(); switchTab('signup');">Sign up</a>
                        </div>
                    </form>
                    
                    <!-- Signup Form -->
                    <form id="signupForm" class="auth-form">
                        <div class="form-group">
                            <label for="signupUsername">Username</label>
                            <input type="text" id="signupUsername" name="username" required placeholder="Choose a username">
                        </div>
                        <div class="form-group">
                            <label for="signupPassword">Password</label>
                            <input type="password" id="signupPassword" name="password" required placeholder="Choose a password">
                        </div>
                        <div class="form-group">
                            <label for="restaurantName">Restaurant Name</label>
                            <input type="text" id="restaurantName" name="restaurant_name" required placeholder="Enter your restaurant name">
                        </div>
                        <button type="submit" class="btn btn-primary" id="signupBtn">Sign Up</button>
                        <div class="signup-link">
                            Already have an account? <a href="#" onclick="event.preventDefault(); switchTab('login');">Sign in</a>
                        </div>
                    </form>
                    
                    <div class="demo-credentials">
                        <h4>Login Credentials:</h4>
                        <p><strong>Admin:</strong> Use your username</p>
                        <p><strong>Staff:</strong> Use your email or phone number</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-footer">
            © 2024 Restro Grow. All Rights Reserved.
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="forgot-password-modal">
        <div class="forgot-password-content">
            <button class="close-modal" onclick="closeForgotPasswordModal()">&times;</button>
            <h2>Forgot Password</h2>
            <p>Enter your restaurant email address and we'll send you a password reset link.</p>
            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="forgotEmail">Email Address:</label>
                    <input type="email" id="forgotEmail" name="email" required placeholder="Enter your restaurant email">
                </div>
                <button type="submit" class="btn btn-primary" id="forgotPasswordBtn">Send Reset Link</button>
                <button type="button" class="btn btn-outline" onclick="closeForgotPasswordModal()" style="background: #f5f5f5; color: #333; border: 2px solid #e0e0e0;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Update form visibility
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            document.getElementById(tab + 'Form').classList.add('active');
            
            // Clear messages
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => msg.remove());
        }
        
        // Login form submission
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('loginUsername').value.trim();
            const password = document.getElementById('loginPassword').value;
            const loginBtn = document.getElementById('loginBtn');
            
            if (!username || !password) {
                showMessage('Please fill in all fields.', 'error');
                return;
            }
            
            loginBtn.disabled = true;
            loginBtn.textContent = 'Logging in...';
            
            try {
                // Create AbortController for timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
                
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                // Get response text first to check if it's valid JSON
                const responseText = await response.text();
                
                // Log full response to console for debugging
                console.log('Login response status:', response.status);
                if (!response.ok) {
                    console.error('Server error response:', responseText);
                }
                
                if (!responseText || responseText.trim() === '') {
                    console.error('Server returned empty response');
                    showMessage('Incorrect username or password', 'error');
                    return;
                }
                
                // Check if response is HTML (likely a PHP error page)
                if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html')) {
                    console.error('Server returned HTML instead of JSON:', responseText.substring(0, 500));
                    showMessage('Incorrect username or password', 'error');
                    return;
                }
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText.substring(0, 500));
                    showMessage('Incorrect username or password', 'error');
                    return;
                }
                
                // Check if response is ok
                if (!response.ok) {
                    // Log error details to console
                    console.error('Server error:', result);
                    // Show user-friendly message
                    showMessage(result.message || 'Incorrect username or password', 'error');
                    return;
                }
                
                if (result.success) {
                    showMessage('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        try {
                            sessionStorage.setItem('forceDashboard', '1');
                            localStorage.removeItem('admin_active_page');
                        } catch (storageErr) {
                            console.warn('Unable to set dashboard preference', storageErr);
                        }
                        window.location.href = result.redirect || '../views/dashboard.php';
                    }, 1000);
                } else {
                    // Show user-friendly message (backend should already return friendly message)
                    showMessage(result.message || 'Incorrect username or password', 'error');
                }
            } catch (error) {
                // Log full error to console for debugging
                console.error('Login Error:', error);
                console.error('Error details:', {
                    name: error.name,
                    message: error.message,
                    stack: error.stack
                });
                
                // Show only user-friendly message
                let errorMessage = 'Incorrect username or password';
                
                // Only show network errors if it's clearly a network issue
                if (error.name === 'AbortError') {
                    errorMessage = 'Request timeout. Please try again.';
                } else if (error.message && (error.message.includes('Failed to fetch') || error.message.includes('NetworkError'))) {
                    errorMessage = 'Network error. Please check your internet connection and try again.';
                }
                
                showMessage(errorMessage, 'error');
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Login';
            }
        });
        
        // Signup form submission
        document.getElementById('signupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('signupUsername').value.trim();
            const password = document.getElementById('signupPassword').value;
            const restaurantName = document.getElementById('restaurantName').value.trim();
            const signupBtn = document.getElementById('signupBtn');
            
            if (!username || !password || !restaurantName) {
                showMessage('Please fill in all fields.', 'error');
                return;
            }
            
            if (password.length < 6) {
                showMessage('Password must be at least 6 characters long.', 'error');
                return;
            }
            
            signupBtn.disabled = true;
            signupBtn.textContent = 'Creating Account...';
            
            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=signup&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&restaurant_name=${encodeURIComponent(restaurantName)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('Account created successfully! Please sign in.', 'success');
                    setTimeout(() => {
                        switchTab('login');
                        document.getElementById('loginUsername').value = username;
                    }, 1500);
                } else {
                    showMessage(result.message || 'Signup failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Network error. Please try again.', 'error');
            } finally {
                signupBtn.disabled = false;
                signupBtn.textContent = 'Sign Up';
            }
        });
        
        function showMessage(message, type) {
            const existingMessage = document.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = message;
            
            const activeForm = document.querySelector('.auth-form.active');
            if (activeForm) {
                activeForm.insertBefore(messageDiv, activeForm.firstChild);
            }
        }
        
        // Forgot Password functionality
        document.getElementById('forgotPasswordLink').addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('forgotPasswordModal').classList.add('active');
        });
        
        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').classList.remove('active');
            document.getElementById('forgotPasswordForm').reset();
            const messages = document.querySelectorAll('#forgotPasswordForm .message');
            messages.forEach(msg => msg.remove());
        }
        
        // Close modal when clicking outside
        document.getElementById('forgotPasswordModal').addEventListener('click', (e) => {
            if (e.target.id === 'forgotPasswordModal') {
                closeForgotPasswordModal();
            }
        });
        
        // Forgot Password Form Submission
        document.getElementById('forgotPasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('forgotEmail').value.trim();
            const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
            
            if (!email) {
                showForgotPasswordMessage('Please enter your email address.', 'error');
                return;
            }
            
            if (!email.includes('@')) {
                showForgotPasswordMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            forgotPasswordBtn.disabled = true;
            forgotPasswordBtn.textContent = 'Sending...';
            
            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=forgotPassword&email=${encodeURIComponent(email)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    let message = result.message || 'Password reset link has been sent to your email. Please check your inbox.';
                    
                    // Don't show reset link in UI for security reasons
                    // Link is only sent via email
                    
                    showForgotPasswordMessage(message, 'success');
                    
                    // Don't auto-close - let user close manually
                } else {
                    // Check if there's a cooldown
                    if (result.cooldown_seconds) {
                        const minutes = Math.floor(result.cooldown_seconds / 60);
                        const seconds = result.cooldown_seconds % 60;
                        const timeStr = minutes > 0 ? (minutes + ' minute(s) and ' + seconds + ' second(s)') : (seconds + ' second(s)');
                        showForgotPasswordMessage(result.message || 'Please wait ' + timeStr + ' before requesting another password reset.', 'error');
                    } else {
                        showForgotPasswordMessage(result.message || 'Email not found. Please check your email address.', 'error');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showForgotPasswordMessage('Network error. Please try again.', 'error');
            } finally {
                forgotPasswordBtn.disabled = false;
                forgotPasswordBtn.textContent = 'Send Reset Link';
            }
        });
        
        function showForgotPasswordMessage(message, type) {
            const form = document.getElementById('forgotPasswordForm');
            const existingMessage = form.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = message; // Use innerHTML to support HTML content
            form.insertBefore(messageDiv, form.firstChild);
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show temporary feedback
                const feedback = document.createElement('div');
                feedback.textContent = 'Copied!';
                feedback.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 5px; z-index: 10000;';
                document.body.appendChild(feedback);
                setTimeout(() => feedback.remove(), 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy. Please select and copy manually.');
            });
        }
    </script>
</body>
</html>
