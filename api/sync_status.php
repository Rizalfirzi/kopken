<?php
/**
 * Realtime Multi-Brand API Status Endpoint for Kedai Kyuti
 * Microservices Powered by swamptechnology.id
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30, s-maxage=60');

require_once __DIR__ . '/../config.php';

$status = [
    'success' => true,
    'architecture' => 'Direct Multi-Brand Serverless (swamptechnology.id)',
    'timestamp' => date('c'),
];

// Check master files
$outletsFile = __DIR__ . '/../data/outlets_master.json';
$menuFile = __DIR__ . '/../data/menu_master.json';
$wifiFile = __DIR__ . '/../data/wifi_master.json';

$status['master_files'] = [
    'outlets_exists' => file_exists($outletsFile),
    'outlets_last_modified' => file_exists($outletsFile) ? date('c', filemtime($outletsFile)) : null,
    'menu_exists' => file_exists($menuFile),
    'menu_last_modified' => file_exists($menuFile) ? date('c', filemtime($menuFile)) : null,
    'wifi_exists' => file_exists($wifiFile),
    'wifi_last_modified' => file_exists($wifiFile) ? date('c', filemtime($wifiFile)) : null,
];

if (file_exists($outletsFile)) {
    $outlets = json_decode((string)file_get_contents($outletsFile), true);
    $status['outlets_count'] = is_array($outlets) ? count($outlets) : 0;
}

if (file_exists($menuFile)) {
    $menu = json_decode((string)file_get_contents($menuFile), true);
    $status['menu_items_count'] = isset($menu['items']) && is_array($menu['items']) ? count($menu['items']) : 0;
}

$brandApis = config('brand_apis', []);
$status['brand_apis'] = [];

foreach ($brandApis as $key => $cfg) {
    $status['brand_apis'][$key] = [
        'name' => $cfg['name'] ?? $key,
        'domain' => $cfg['domain'] ?? '',
        'base_url' => $cfg['base_url'] ?? '',
    ];
}

// Optional live connectivity tests
if (isset($_GET['test'])) {
    $testResults = [];
    foreach ($brandApis as $key => $cfg) {
        $testUrl = rtrim($cfg['base_url'], '/') . '/status';
        $ch = curl_init($testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $t0 = microtime(true);
        $body = curl_exec($ch);
        $elapsed = round((microtime(true) - $t0) * 1000, 1);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $testResults[$key] = [
            'domain' => $cfg['domain'],
            'http_code' => $code,
            'response_time_ms' => $elapsed,
            'status' => ($code >= 200 && $code < 300) ? 'ONLINE' : 'ERROR',
            'error' => $err ?: null,
            'sample' => substr((string)$body, 0, 80),
        ];
    }
    $status['live_tests'] = $testResults;
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
