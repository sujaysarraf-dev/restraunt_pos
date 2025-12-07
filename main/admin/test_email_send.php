<?php
// Test Email Sending Script
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include email configuration
if (file_exists(__DIR__ . '/../config/email_config.php')) {
    require_once __DIR__ . '/../config/email_config.php';
}

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    
    if ($action !== 'send_test_email') {
        throw new Exception('Invalid action');
    }
    
    // Test email configuration
    $toEmail = 'sujaysarraf55@gmail.com';
    $subject = 'Test Email - Restaurant POS System';
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #151A2D; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Test Email</h2>
            </div>
            <div class='content'>
                <p>Hi Sujay,</p>
                <p>This is a test email from the Restaurant POS System.</p>
                <p>If you received this email, the mailing system is working correctly!</p>
                <p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            <div class='footer'>
                <p>This is an automated test message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Try to send email using the email helper function
    $emailResult = ['success' => false, 'error' => 'Email function not available'];
    
    if (function_exists('sendEmail')) {
        $emailResult = sendEmail($toEmail, $subject, $message);
    } else {
        // Fallback to PHP mail() function
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Restaurant POS System <noreply@" . $host . ">" . "\r\n";
        $headers .= "Reply-To: noreply@" . $host . "\r\n";
        $mailSent = @mail($toEmail, $subject, $message, $headers);
        
        if ($mailSent) {
            $emailResult = ['success' => true];
        } else {
            $lastError = error_get_last();
            $errorMsg = $lastError ? ($lastError['message'] ?? 'Mail function returned false') : 'Mail function returned false';
            $emailResult = ['success' => false, 'error' => $errorMsg];
        }
    }
    
    if ($emailResult['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully to ' . $toEmail . '! Please check your inbox and spam folder.'
        ]);
    } else {
        $errorMsg = $emailResult['error'] ?? 'Unknown error';
        error_log("Failed to send test email: " . $errorMsg);
        
        // Get host for development check
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Check if we're in development mode
        $isDevelopment = (
            in_array($host, ['localhost', '127.0.0.1']) || 
            strpos($host, 'localhost') !== false || 
            strpos($host, '127.0.0.1') !== false
        );
        
        $helpMessage = '';
        if ($isDevelopment) {
            if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
                if (empty(SMTP_PASSWORD)) {
                    $helpMessage = '<br><br><strong>SMTP Configuration Required:</strong><br>';
                    $helpMessage .= '1. Open <code>config/email_config.php</code><br>';
                    $helpMessage .= '2. Set your Gmail App Password in <code>SMTP_PASSWORD</code><br>';
                    $helpMessage .= '3. To get Gmail App Password:<br>';
                    $helpMessage .= '   - Go to Google Account → Security<br>';
                    $helpMessage .= '   - Enable 2-Step Verification<br>';
                    $helpMessage .= '   - Go to App Passwords → Generate new password<br>';
                    $helpMessage .= '   - Copy the 16-character password and paste it in email_config.php';
                } else {
                    $helpMessage = '<br><br><strong>SMTP Error Details:</strong><br>';
                    $helpMessage .= 'Error: ' . htmlspecialchars($errorMsg) . '<br><br>';
                    $helpMessage .= 'Please check:<br>';
                    $helpMessage .= '- Gmail App Password is correct<br>';
                    $helpMessage .= '- Firewall allows port 587<br>';
                    $helpMessage .= '- Gmail account has 2-Step Verification enabled<br>';
                    $helpMessage .= '- Check PHP error logs for more details';
                }
            } else {
                $helpMessage = '<br><br>To configure email, edit <code>config/email_config.php</code> and set up SMTP settings.';
            }
        } else {
            $helpMessage = '<br><br>Error: ' . htmlspecialchars($errorMsg);
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Email sending failed. ' . htmlspecialchars($errorMsg) . $helpMessage
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in test_email_send.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

