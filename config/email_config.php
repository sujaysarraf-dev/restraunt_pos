<?php
/**
 * Email Configuration
 * Configure SMTP settings here
 */

// SMTP Configuration
define('SMTP_ENABLED', true); // Set to false to use PHP mail() function
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_USERNAME', 'restrogrow@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'eelnueoixaluiffq'); // Your Gmail App Password (not regular password)
define('SMTP_FROM_EMAIL', 'restrogrow@gmail.com');
define('SMTP_FROM_NAME', 'Restaurant POS System');

/**
 * Send email using SMTP
 */
function sendSMTPEmail($to, $subject, $message, $headers = '') {
    if (!SMTP_ENABLED) {
        // Fallback to PHP mail() function
        return @mail($to, $subject, $message, $headers);
    }
    
    $smtp = null;
    $errorMsg = '';
    
    try {
        // Create socket connection with SSL/TLS support
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Connect to SMTP server
        $smtp = stream_socket_client(
            SMTP_HOST . ':' . SMTP_PORT,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$smtp) {
            $errorMsg = "SMTP Connection failed: $errstr ($errno)";
            error_log($errorMsg);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Read server greeting
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            $errorMsg = "SMTP Error: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Send EHLO
        fputs($smtp, "EHLO " . SMTP_HOST . "\r\n");
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // Start TLS if required
        if (SMTP_SECURE == 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) != '220') {
                $errorMsg = "STARTTLS failed: $response";
                error_log($errorMsg);
                fclose($smtp);
                return ['success' => false, 'error' => $errorMsg];
            }
            
            // Enable crypto
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $errorMsg = "TLS encryption failed";
                error_log($errorMsg);
                fclose($smtp);
                return ['success' => false, 'error' => $errorMsg];
            }
            
            // Send EHLO again after TLS
            fputs($smtp, "EHLO " . SMTP_HOST . "\r\n");
            $response = '';
            while ($line = fgets($smtp, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') break;
            }
        }
        
        // Authenticate
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '334') {
            $errorMsg = "AUTH LOGIN failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        fputs($smtp, base64_encode(SMTP_USERNAME) . "\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '334') {
            $errorMsg = "Username authentication failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        fputs($smtp, base64_encode(SMTP_PASSWORD) . "\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '235') {
            $errorMsg = "SMTP Authentication failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Send email
        fputs($smtp, "MAIL FROM: <" . SMTP_FROM_EMAIL . ">\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '250') {
            $errorMsg = "MAIL FROM failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        fputs($smtp, "RCPT TO: <" . $to . ">\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '250') {
            $errorMsg = "RCPT TO failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '354') {
            $errorMsg = "DATA command failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Prepare email headers
        $emailHeaders = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $emailHeaders .= "To: <" . $to . ">\r\n";
        $emailHeaders .= "Subject: " . $subject . "\r\n";
        $emailHeaders .= "MIME-Version: 1.0\r\n";
        $emailHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
        if ($headers) {
            $emailHeaders .= $headers . "\r\n";
        }
        
        // Send email data
        fputs($smtp, $emailHeaders . "\r\n" . $message . "\r\n.\r\n");
        $response = fgets($smtp, 515);
        
        if (substr($response, 0, 3) != '250') {
            $errorMsg = "SMTP Send failed: $response";
            error_log($errorMsg);
            fclose($smtp);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $errorMsg = "SMTP Exception: " . $e->getMessage();
        error_log($errorMsg);
        if ($smtp) {
            fclose($smtp);
        }
        return ['success' => false, 'error' => $errorMsg];
    }
}

/**
 * Send email (wrapper function that tries SMTP first, then falls back to mail())
 * Returns array with 'success' and optional 'error' keys
 */
function sendEmail($to, $subject, $message, $headers = '') {
    // Try SMTP first if enabled
    if (SMTP_ENABLED && !empty(SMTP_PASSWORD)) {
        $result = sendSMTPEmail($to, $subject, $message, $headers);
        if (is_array($result)) {
            return $result; // Return the result array
        }
        // Legacy: if function returns boolean, convert to array
        if ($result === true) {
            return ['success' => true];
        }
        // If SMTP fails, fall back to mail()
        error_log("SMTP failed, falling back to mail() function");
    }
    
    // Fallback to PHP mail() function
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $defaultHeaders = "MIME-Version: 1.0\r\n";
    $defaultHeaders .= "Content-type: text/html; charset=UTF-8\r\n";
    $defaultHeaders .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    if ($headers) {
        $defaultHeaders .= $headers;
    }
    
    $mailResult = @mail($to, $subject, $message, $defaultHeaders);
    
    if ($mailResult) {
        return ['success' => true];
    } else {
        $lastError = error_get_last();
        $errorMsg = $lastError ? ($lastError['message'] ?? 'Mail function returned false') : 'Mail function returned false';
        return ['success' => false, 'error' => $errorMsg];
    }
}
?>

