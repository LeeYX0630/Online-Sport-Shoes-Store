<?php
/**
 * Application Configuration File
 * Store all configurable settings here instead of hardcoding them
 */

// Mobile device configuration
define('MOBILE_DEVICE_IP', getenv('MOBILE_IP') ?: '10.127.43.155');
define('MOBILE_DEVICE_PORT', getenv('MOBILE_PORT') ?: '80');

// Image processing configuration
define('IMAGE_RESOLUTION_WIDTH', 1280);
define('IMAGE_RESOLUTION_HEIGHT', 720);
define('IMAGE_COMPRESSION_QUALITY', 0.90);
define('IMAGE_CACHE_BUST', true);

// Polling configuration
define('AR_SCAN_POLL_INTERVAL', 2000); // milliseconds
define('WEAR_SCAN_POLL_INTERVAL', 2000); // milliseconds

// Session configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_LENGTH', 32); // bytes for random token

// Recently viewed products
define('RECENTLY_VIEWED_LIMIT', 8);

// Quantity limits
define('MIN_QUANTITY', 1);
define('MAX_QUANTITY', 99);

?>
