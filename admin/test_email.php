<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - Restaurant Management</title>
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
        
        .test-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            margin: 20px;
        }
        
        .test-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .test-header h1 {
            color: #151A2D;
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .test-header p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .test-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .test-info strong {
            color: #1976d2;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #151A2D;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>Test Email System</h1>
            <p>Send a test email to verify mail configuration</p>
        </div>
        
        <div class="test-info">
            <strong>Test Email:</strong> sujaysarraf55@gmail.com<br>
            <strong>Message:</strong> hi sujay<br><br>
            <strong>Status:</strong> SMTP configured and ready to send emails
        </div>
        
        <div id="messageContainer"></div>
        
        <button type="button" class="btn btn-primary" id="testEmailBtn" onclick="sendTestEmail()">
            <span class="material-symbols-rounded">send</span>
            Send Test Email
        </button>
        
        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>

    <script>
        async function sendTestEmail() {
            const btn = document.getElementById('testEmailBtn');
            const messageContainer = document.getElementById('messageContainer');
            
            // Clear previous messages
            messageContainer.innerHTML = '';
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-rounded">hourglass_empty</span> Sending...';
            
            try {
                const response = await fetch('test_email_send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=send_test_email'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message || 'Test email sent successfully!', 'success');
                } else {
                    showMessage(result.message || 'Failed to send test email.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Network error. Please try again.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-rounded">send</span> Send Test Email';
            }
        }
        
        function showMessage(message, type) {
            const messageContainer = document.getElementById('messageContainer');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = message;
            messageContainer.appendChild(messageDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>

