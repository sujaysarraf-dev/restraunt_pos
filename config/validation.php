<?php
/**
 * Input Validation Helper Functions
 * Comprehensive validation for all input types
 */

/**
 * Validate email address
 */
function validateEmail($email) {
    if (empty($email)) {
        return ['valid' => false, 'message' => 'Email is required'];
    }
    
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email format'];
    }
    
    // Check length (max 255 characters for database)
    if (strlen($email) > 255) {
        return ['valid' => false, 'message' => 'Email is too long (max 255 characters)'];
    }
    
    return ['valid' => true, 'value' => $email];
}

/**
 * Validate phone number (international format)
 */
function validatePhone($phone, $countryCode = '') {
    if (empty($phone)) {
        return ['valid' => false, 'message' => 'Phone number is required'];
    }
    
    // Remove all non-digit characters except +
    $cleaned = preg_replace('/[^\d+]/', '', $phone);
    
    // If country code is provided, prepend it
    if (!empty($countryCode) && strpos($cleaned, '+') !== 0) {
        $cleaned = $countryCode . $cleaned;
    }
    
    // Validate phone number (7-15 digits, may start with +)
    if (!preg_match('/^\+?[1-9]\d{6,14}$/', $cleaned)) {
        return ['valid' => false, 'message' => 'Invalid phone number format. Please use international format (e.g., +1234567890)'];
    }
    
    return ['valid' => true, 'value' => $cleaned];
}

/**
 * Validate date
 */
function validateDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return ['valid' => false, 'message' => 'Date is required'];
    }
    
    $d = DateTime::createFromFormat($format, $date);
    
    if (!$d || $d->format($format) !== $date) {
        return ['valid' => false, 'message' => 'Invalid date format. Expected: ' . $format];
    }
    
    return ['valid' => true, 'value' => $date, 'datetime' => $d];
}

/**
 * Validate date range
 */
function validateDateRange($startDate, $endDate, $format = 'Y-m-d') {
    $start = validateDate($startDate, $format);
    $end = validateDate($endDate, $format);
    
    if (!$start['valid']) {
        return $start;
    }
    
    if (!$end['valid']) {
        return $end;
    }
    
    if ($start['datetime'] > $end['datetime']) {
        return ['valid' => false, 'message' => 'Start date must be before end date'];
    }
    
    return ['valid' => true, 'start' => $start['value'], 'end' => $end['value']];
}

/**
 * Validate integer
 */
function validateInteger($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return ['valid' => false, 'message' => 'Must be a number'];
    }
    
    $int = (int)$value;
    
    if ($min !== null && $int < $min) {
        return ['valid' => false, 'message' => "Value must be at least {$min}"];
    }
    
    if ($max !== null && $int > $max) {
        return ['valid' => false, 'message' => "Value must be at most {$max}"];
    }
    
    return ['valid' => true, 'value' => $int];
}

/**
 * Validate float/decimal
 */
function validateFloat($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return ['valid' => false, 'message' => 'Must be a number'];
    }
    
    $float = (float)$value;
    
    if ($min !== null && $float < $min) {
        return ['valid' => false, 'message' => "Value must be at least {$min}"];
    }
    
    if ($max !== null && $float > $max) {
        return ['valid' => false, 'message' => "Value must be at most {$max}"];
    }
    
    return ['valid' => true, 'value' => $float];
}

/**
 * Validate string
 */
function validateString($value, $minLength = null, $maxLength = null, $required = true) {
    if ($required && empty(trim($value))) {
        return ['valid' => false, 'message' => 'This field is required'];
    }
    
    $trimmed = trim($value);
    
    if ($minLength !== null && strlen($trimmed) < $minLength) {
        return ['valid' => false, 'message' => "Must be at least {$minLength} characters"];
    }
    
    if ($maxLength !== null && strlen($trimmed) > $maxLength) {
        return ['valid' => false, 'message' => "Must be at most {$maxLength} characters"];
    }
    
    return ['valid' => true, 'value' => $trimmed];
}

/**
 * Sanitize string (prevent XSS)
 */
function sanitizeString($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate password strength
 */
function validatePassword($password, $minLength = 6) {
    if (empty($password)) {
        return ['valid' => false, 'message' => 'Password is required'];
    }
    
    if (strlen($password) < $minLength) {
        return ['valid' => false, 'message' => "Password must be at least {$minLength} characters"];
    }
    
    // Optional: Add more strength requirements
    // if (!preg_match('/[A-Z]/', $password)) {
    //     return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter'];
    // }
    
    return ['valid' => true, 'value' => $password];
}

/**
 * Validate time format (HH:MM or HH:MM:SS)
 */
function validateTime($time) {
    if (empty($time)) {
        return ['valid' => false, 'message' => 'Time is required'];
    }
    
    $time = trim($time);
    
    // Check if it's in 24-hour format (HH:MM or HH:MM:SS)
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:([0-5][0-9]))?$/', $time)) {
        // Extract just HH:MM if seconds are included
        $parts = explode(':', $time);
        $hour = isset($parts[0]) ? $parts[0] : '00';
        $minute = isset($parts[1]) ? $parts[1] : '00';
        return ['valid' => true, 'value' => sprintf('%02d:%02d', (int)$hour, (int)$minute)];
    }
    
    // Check if it's in 12-hour format (H:MM AM/PM or HH:MM AM/PM)
    if (preg_match('/^([0]?[1-9]|1[0-2]):([0-5][0-9])\s*(AM|PM)$/i', $time, $matches)) {
        // Convert 12-hour to 24-hour format
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        $ampm = strtoupper(trim($matches[3]));
        
        if ($ampm === 'PM' && $hour !== 12) {
            $hour += 12;
        } elseif ($ampm === 'AM' && $hour === 12) {
            $hour = 0;
        }
        
        $time24 = sprintf('%02d:%02d', $hour, $minute);
        return ['valid' => true, 'value' => $time24];
    }
    
    return ['valid' => false, 'message' => 'Invalid time format. Expected: HH:MM (24-hour) or H:MM AM/PM (12-hour)'];
}

/**
 * Validate URL
 */
function validateUrl($url) {
    if (empty($url)) {
        return ['valid' => false, 'message' => 'URL is required'];
    }
    
    $url = filter_var(trim($url), FILTER_SANITIZE_URL);
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['valid' => false, 'message' => 'Invalid URL format'];
    }
    
    return ['valid' => true, 'value' => $url];
}

/**
 * Validate array
 */
function validateArray($value, $minItems = null, $maxItems = null) {
    if (!is_array($value)) {
        return ['valid' => false, 'message' => 'Must be an array'];
    }
    
    $count = count($value);
    
    if ($minItems !== null && $count < $minItems) {
        return ['valid' => false, 'message' => "Must have at least {$minItems} items"];
    }
    
    if ($maxItems !== null && $count > $maxItems) {
        return ['valid' => false, 'message' => "Must have at most {$maxItems} items"];
    }
    
    return ['valid' => true, 'value' => $value];
}

/**
 * Check request size limit
 */
function checkRequestSize($maxSizeMB = 10) {
    $maxSizeBytes = $maxSizeMB * 1024 * 1024;
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
    
    if ($contentLength > $maxSizeBytes) {
        return [
            'valid' => false,
            'message' => "Request size exceeds maximum allowed size of {$maxSizeMB}MB"
        ];
    }
    
    return ['valid' => true];
}

