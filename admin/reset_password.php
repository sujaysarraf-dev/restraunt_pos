<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Restaurant Management</title>
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
            box-sizing: border-box;
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
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #151A2D;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Reset Password</h1>
            <p>Enter your new password</p>
        </div>
        
        <form id="resetPasswordForm">
            <input type="hidden" id="resetToken" value="<?php echo isset($_GET['token']) ? htmlspecialchars($_GET['token']) : ''; ?>">
            
            <div class="form-group">
                <label for="newPassword">New Password:</label>
                <input type="password" id="newPassword" name="newPassword" required placeholder="Enter new password (min 6 characters)" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirmPassword">Confirm Password:</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Confirm new password" minlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary" id="resetPasswordBtn">Reset Password</button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        const resetToken = document.getElementById('resetToken').value;
        
        if (!resetToken) {
            showMessage('Invalid reset link. Please request a new password reset.', 'error');
            document.getElementById('resetPasswordForm').style.display = 'none';
        }
        
        // Password match validation
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form submission
        document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const resetPasswordBtn = document.getElementById('resetPasswordBtn');
            
            if (!newPassword || !confirmPassword) {
                showMessage('Please fill in all fields.', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                showMessage('Password must be at least 6 characters long.', 'error');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                return;
            }
            
            resetPasswordBtn.disabled = true;
            resetPasswordBtn.textContent = 'Resetting...';
            
            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=resetPassword&token=${encodeURIComponent(resetToken)}&newPassword=${encodeURIComponent(newPassword)}&confirmPassword=${encodeURIComponent(confirmPassword)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message || 'Password has been reset successfully! Redirecting to login...', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showMessage(result.message || 'Failed to reset password. Please try again.', 'error');
                    resetPasswordBtn.disabled = false;
                    resetPasswordBtn.textContent = 'Reset Password';
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Network error. Please try again.', 'error');
                resetPasswordBtn.disabled = false;
                resetPasswordBtn.textContent = 'Reset Password';
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
            
            const form = document.getElementById('resetPasswordForm');
            form.insertBefore(messageDiv, form.firstChild);
        }
    </script>
</body>
</html>

