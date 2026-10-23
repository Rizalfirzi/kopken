<?php
/**
 * Kopi Kenangan Native PHP Application Configuration
 * Ready for Vercel Serverless, XAMPP, Laragon, and standard PHP hosting.
 */

define('ROOT_PATH', __DIR__);

// Cache directory (using system temp directory for Vercel read-only filesystem compatibility)
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kopken_cache';
define('CACHE_PATH', $cacheDir);
if (!is_dir(CACHE_PATH)) {
    @mkdir(CACHE_PATH, 0777, true);
}

// Global configuration parameters (100% API based, Zero Database)
$config = [
    'app_name' => 'Kopi Kenangan Live Store',
    'app_tagline' => 'Cek Outlet Terdekat & Stok Menu Real-Time',
    'app_env' => 'production',

    // External Live API Endpoint
    'api_base_url' => 'https://tixandcoffee.developerngantuk.my.id/api',
    'api_timeout' => 8, // seconds

    // Cache TTL Settings
    'cache_ttl' => [
        'outlets' => 1800, // 30 minutes
        'menu' => 60,      // 1 minute (real-time stock)
    ],

    // Store & Checkout WhatsApp Settings
    'store' => [
        'name' => 'Kopi Kenangan Live Store',
        'admin_phone' => '6288298374458', // WhatsApp admin phone
        'order_prefix' => 'KK',
        'default_outlet' => 'LA.CODEFIN',
    ],
];

/**
 * Helper function to retrieve configuration values using dot notation
 *
 * @param string $key e.g. 'store.admin_phone'
 * @param mixed $default
 * @return mixed
 */
function config(string $key, $default = null)
{
    global $config;

    $segments = explode('.', $key);
    $current = $config;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }
        $current = $current[$segment];
    }

    return $current;
}
