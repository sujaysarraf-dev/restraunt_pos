<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Restaurant Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0">
    <style>
        body {
            background: linear-gradient(135deg, #151A2D, #2a3a5c);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: "Poppins", sans-serif;
        }
        
        .auth-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header h1 {
            color: #151A2D;
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .auth-header p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            background: #f5f5f5;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #666;
        }
        
        .auth-tab.active {
            background: #151A2D;
            color: white;
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #151A2D;
            box-shadow: 0 0 0 3px rgba(21, 26, 45, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #151A2D, #2a3a5c);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2a3a5c, #151A2D);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(21, 26, 45, 0.3);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .demo-credentials {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        
        .demo-credentials h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        
        .demo-credentials p {
            margin: 5px 0;
            color: #1976d2;
        }
        
        .forgot-password-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .forgot-password-modal.active {
            display: flex;
        }
        
        .forgot-password-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .forgot-password-content h2 {
            margin: 0 0 10px 0;
            color: #151A2D;
            font-size: 1.5rem;
        }
        
        .forgot-password-content p {
            color: #666;
            margin: 0 0 20px 0;
            font-size: 0.9rem;
        }
        
        .close-modal {
            float: right;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-modal:hover {
            color: #151A2D;
        }
        
        .btn-outline {
            background: #f5f5f5;
            color: #333;
            border: 2px solid #e0e0e0;
        }
        
        .btn-outline:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Restaurant Login</h1>
            <p>Admin, Manager, Waiter & Chef Portal</p>
        </div>
        
        <div class="auth-tabs">
            <div class="auth-tab active" onclick="switchTab('login')">Sign In</div>
            <div class="auth-tab" onclick="switchTab('signup')">Sign Up</div>
        </div>
        
        <!-- Login Form -->
        <form id="loginForm" class="auth-form active">
            <div class="form-group">
                <label for="loginUsername">Username / Email / Phone:</label>
                <input type="text" id="loginUsername" name="username" required placeholder="Enter username, email or phone">
            </div>
            <div class="form-group">
                <label for="loginPassword">Password:</label>
                <input type="password" id="loginPassword" name="password" required placeholder="Enter your password">
            </div>
            <div style="text-align: right; margin-bottom: 15px;">
                <a href="#" id="forgotPasswordLink" style="color: #151A2D; text-decoration: none; font-size: 0.9rem; font-weight: 500;">Forgot Password?</a>
            </div>
            <button type="submit" class="btn btn-primary" id="loginBtn">Sign In</button>
        </form>
        
        <!-- Signup Form -->
        <form id="signupForm" class="auth-form">
            <div class="form-group">
                <label for="signupUsername">Username:</label>
                <input type="text" id="signupUsername" name="username" required placeholder="Choose a username">
            </div>
            <div class="form-group">
                <label for="signupPassword">Password:</label>
                <input type="password" id="signupPassword" name="password" required placeholder="Choose a password">
            </div>
            <div class="form-group">
                <label for="restaurantName">Restaurant Name:</label>
                <input type="text" id="restaurantName" name="restaurant_name" required placeholder="Enter your restaurant name">
            </div>
            <button type="submit" class="btn btn-primary" id="signupBtn">Sign Up</button>
        </form>
        
        <div class="demo-credentials">
            <h4>Login Credentials:</h4>
            <p><strong>Admin:</strong> Use your username</p>
            <p><strong>Staff:</strong> Use your email or phone number</p>
            <p style="margin-top: 10px; font-size: 0.8rem; color: #1976d2;">Staff members can login with email/phone from staff table</p>
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
            // Update tab appearance
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelector(`.auth-tab[onclick="switchTab('${tab}')"]`).classList.add('active');
            
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
            loginBtn.textContent = 'Signing In...';
            
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
                
                if (!response.ok) {
                    // Try to get error message from response
                    let errorText = '';
                    try {
                        errorText = await response.text();
                        const errorJson = JSON.parse(errorText);
                        throw new Error(errorJson.message || `Server error: ${response.status} ${response.statusText}`);
                    } catch (parseError) {
                        throw new Error(`Server error: ${response.status} ${response.statusText}. ${errorText.substring(0, 200)}`);
                    }
                }
                
                // Get response text first to check if it's valid JSON
                const responseText = await response.text();
                
                if (!responseText || responseText.trim() === '') {
                    throw new Error('Server returned empty response. Please check server logs.');
                }
                
                // Check if response is HTML (likely a PHP error page)
                if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html')) {
                    console.error('Server returned HTML instead of JSON:', responseText.substring(0, 500));
                    throw new Error('Server error: PHP error page returned. Please check server configuration.');
                }
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText.substring(0, 500));
                    throw new Error('Server returned invalid JSON. Check console for details.');
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
                    showMessage(result.message || 'Login failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Login Error:', error);
                let errorMessage = 'Network error. Please try again.';
                
                // Provide more specific error messages
                if (error.name === 'AbortError') {
                    errorMessage = 'Request timeout. The server is taking too long to respond. Please try again.';
                } else if (error.message) {
                    if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                        errorMessage = 'Network error. Please check your internet connection and try again.';
                    } else if (error.message.includes('JSON') || error.message.includes('parse')) {
                        errorMessage = 'Server returned invalid response. Please try again.';
                    } else if (error.message.includes('Server error')) {
                        errorMessage = error.message;
                    }
                }
                
                showMessage(errorMessage, 'error');
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign In';
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
                    
                    // If in development mode or email failed, show the reset link
                    if (result.reset_link) {
                        message += '<br><br><strong>Reset Link:</strong><br>';
                        message += '<a href="' + result.reset_link + '" target="_blank" style="color: #151A2D; text-decoration: underline; word-break: break-all; display: inline-block; margin-top: 8px; padding: 10px; background: #f5f5f5; border-radius: 5px; border: 1px solid #ddd;">' + result.reset_link + '</a>';
                        message += '<br><small style="color: #666; margin-top: 5px; display: block;">(Click the link above to reset your password)</small>';
                    }
                    
                    showForgotPasswordMessage(message, 'success');
                    
                    // Only auto-close if email was actually sent
                    if (result.email_sent && !result.development_mode) {
                        setTimeout(() => {
                            closeForgotPasswordModal();
                        }, 5000);
                    }
                } else {
                    showForgotPasswordMessage(result.message || 'Email not found. Please check your email address.', 'error');
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
