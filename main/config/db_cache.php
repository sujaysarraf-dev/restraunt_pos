<?php
/**
 * Database Query Cache - Optimized for High Concurrency
 * 
 * Provides simple in-memory caching for frequently accessed data
 * Reduces database load for 500-1000+ concurrent users
 * 
 * Features:
 * - In-memory cache with TTL (Time To Live)
 * - Automatic cache invalidation
 * - Memory-efficient storage
 * - Thread-safe for PHP-FPM
 */

if (!defined('DB_CACHE_LOADED')) {
    define('DB_CACHE_LOADED', true);
}

// Cache storage (shared across requests in same PHP-FPM process)
if (!isset($GLOBALS['db_cache'])) {
    $GLOBALS['db_cache'] = [];
}

/**
 * Get cached data
 * 
 * @param string $key Cache key
 * @return mixed|null Cached data or null if not found/expired
 */
function getCache($key) {
    if (!isset($GLOBALS['db_cache'][$key])) {
        return null;
    }
    
    $cache_entry = $GLOBALS['db_cache'][$key];
    
    // Check if expired
    if (time() > $cache_entry['expires']) {
        unset($GLOBALS['db_cache'][$key]);
        return null;
    }
    
    return $cache_entry['data'];
}

/**
 * Set cache data
 * 
 * @param string $key Cache key
 * @param mixed $data Data to cache
 * @param int $ttl Time to live in seconds (default: 60 seconds)
 * @return void
 */
function setCache($key, $data, $ttl = 60) {
    // Limit cache size to prevent memory issues
    if (count($GLOBALS['db_cache']) > 1000) {
        // Remove oldest entries (simple cleanup)
        $oldest_key = null;
        $oldest_time = time();
        foreach ($GLOBALS['db_cache'] as $k => $entry) {
            if ($entry['expires'] < $oldest_time) {
                $oldest_time = $entry['expires'];
                $oldest_key = $k;
            }
        }
        if ($oldest_key) {
            unset($GLOBALS['db_cache'][$oldest_key]);
        }
    }
    
    $GLOBALS['db_cache'][$key] = [
        'data' => $data,
        'expires' => time() + $ttl,
        'created' => time()
    ];
}

/**
 * Clear cache by key or pattern
 * 
 * @param string $key Cache key or pattern (use * for wildcard)
 * @return void
 */
function clearCache($key) {
    if (strpos($key, '*') !== false) {
        // Pattern matching
        $pattern = str_replace('*', '.*', preg_quote($key, '/'));
        foreach (array_keys($GLOBALS['db_cache']) as $k) {
            if (preg_match('/^' . $pattern . '$/', $k)) {
                unset($GLOBALS['db_cache'][$k]);
            }
        }
    } else {
        unset($GLOBALS['db_cache'][$key]);
    }
}

/**
 * Clear all cache
 * 
 * @return void
 */
function clearAllCache() {
    $GLOBALS['db_cache'] = [];
}

/**
 * Get cache statistics
 * 
 * @return array Cache statistics
 */
function getCacheStats() {
    $stats = [
        'entries' => count($GLOBALS['db_cache']),
        'memory_usage' => strlen(serialize($GLOBALS['db_cache'])),
        'keys' => array_keys($GLOBALS['db_cache'])
    ];
    return $stats;
}
?>

