<?php
/**
 * Rate Limiting for API Endpoints
 * Prevents abuse and DDoS attacks
 */

/**
 * Simple file-based rate limiter
 * For production, consider using Redis or Memcached
 */
function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 60) {
    $rateLimitDir = __DIR__ . '/../tmp/rate_limits';
    
    // Create directory if it doesn't exist
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    
    // Use IP address or user ID as identifier
    if (empty($identifier)) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    // Sanitize identifier for filename
    $safeIdentifier = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
    $rateLimitFile = $rateLimitDir . '/' . $safeIdentifier . '.json';
    
    $now = time();
    $requests = [];
    
    // Load existing requests
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if ($data && isset($data['requests'])) {
            $requests = $data['requests'];
        }
    }
    
    // Remove requests outside the time window
    $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    // Check if limit exceeded
    if (count($requests) >= $maxRequests) {
        $oldestRequest = min($requests);
        $retryAfter = $timeWindow - ($now - $oldestRequest);
        
        return [
            'allowed' => false,
            'retry_after' => $retryAfter,
            'message' => "Rate limit exceeded. Please try again in {$retryAfter} seconds."
        ];
    }
    
    // Add current request
    $requests[] = $now;
    
    // Save updated requests
    file_put_contents($rateLimitFile, json_encode([
        'requests' => array_values($requests),
        'last_updated' => $now
    ]));
    
    // Clean up old files (older than 1 hour)
    if (rand(1, 100) === 1) { // 1% chance to clean up (avoid overhead)
        cleanupOldRateLimitFiles($rateLimitDir, 3600);
    }
    
    return [
        'allowed' => true,
        'remaining' => $maxRequests - count($requests)
    ];
}

/**
 * Clean up old rate limit files
 */
function cleanupOldRateLimitFiles($directory, $maxAge = 3600) {
    $files = glob($directory . '/*.json');
    $now = time();
    
    foreach ($files as $file) {
        if (filemtime($file) < ($now - $maxAge)) {
            @unlink($file);
        }
    }
}

/**
 * Get rate limit identifier (IP address or user ID)
 */
function getRateLimitIdentifier() {
    // Try to use user ID if logged in
    if (isset($_SESSION['user_id'])) {
        return 'user_' . $_SESSION['user_id'];
    }
    
    // Fall back to IP address
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Handle proxy headers (use X-Forwarded-For if available)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($forwarded[0]);
    }
    
    return 'ip_' . $ip;
}

/**
 * Apply rate limiting to API endpoint
 */
function applyRateLimit($maxRequests = 60, $timeWindow = 60) {
    $identifier = getRateLimitIdentifier();
    $result = checkRateLimit($identifier, $maxRequests, $timeWindow);
    
    if (!$result['allowed']) {
        http_response_code(429); // Too Many Requests
        header('Retry-After: ' . $result['retry_after']);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'retry_after' => $result['retry_after']
        ]);
        
        exit();
    }
    
    // Add rate limit headers to response
    header('X-RateLimit-Limit: ' . $maxRequests);
    header('X-RateLimit-Remaining: ' . ($result['remaining'] ?? 0));
    
    return true;
}

