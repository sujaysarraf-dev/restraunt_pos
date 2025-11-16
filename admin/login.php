<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Restaurant Management</title>
    <link rel="stylesheet" href="../style.css">
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
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect || '../views/dashboard.php';
                    }, 1000);
                } else {
                    showMessage(result.message || 'Login failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Network error. Please try again.', 'error');
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
            activeForm.insertBefore(messageDiv, activeForm.firstChild);
        }
    </script>
</body>
</html>
