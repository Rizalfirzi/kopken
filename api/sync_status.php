<?php
/**
 * Realtime Sync & Database Status Endpoint for Kedai Kyuti
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SimpleMongo.php';

$mongo = new SimpleMongo();
$status = [
    'success' => true,
    'provider' => $mongo->getProviderName(),
    'connected' => $mongo->isConnected(),
    'timestamp' => date('c'),
];

// Check master files
$outletsFile = __DIR__ . '/../data/outlets_master.json';
$menuFile = __DIR__ . '/../data/menu_master.json';

$status['master_files'] = [
    'outlets_exists' => file_exists($outletsFile),
    'outlets_last_modified' => file_exists($outletsFile) ? date('c', filemtime($outletsFile)) : null,
    'menu_exists' => file_exists($menuFile),
    'menu_last_modified' => file_exists($menuFile) ? date('c', filemtime($menuFile)) : null,
];

if (file_exists($outletsFile)) {
    $outlets = json_decode((string)file_get_contents($outletsFile), true);
    $status['outlets_count'] = is_array($outlets) ? count($outlets) : 0;
}

if (file_exists($menuFile)) {
    $menu = json_decode((string)file_get_contents($menuFile), true);
    $status['menu_items_count'] = isset($menu['items']) && is_array($menu['items']) ? count($menu['items']) : 0;
}

$status['sources'] = [
    'https://tixandcoffee.developerngantuk.my.id',
    'https://ridstore.developerngantuk.my.id',
    'https://tyaastore.my.id',
    'https://twinsjasdor.my.id'
];

if (isset($_GET['test'])) {
    require_once __DIR__ . '/../includes/KopiKenanganApi.php';
    $api = new KopiKenanganApi();
    $reflection = new ReflectionClass($api);
    $method = $reflection->getMethod('getUpstreamSessionCookie');
    $method->setAccessible(true);
    
    $testResults = [];
    foreach ($status['sources'] as $s) {
        $host = parse_url($s, PHP_URL_HOST);
        $cookie = $method->invoke($api, true, $host);
        
        $ch = curl_init($s . '/api/kopken/menu?outlet=LA.CODEFIN');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_REFERER, $s . '/');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'Cookie: ' . $cookie
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $testResults[$host] = [
            'cookie' => $cookie,
            'http_code' => $code,
            'curl_error' => $err,
            'body_sample' => substr((string)$body, 0, 100),
        ];
    }
    $status['upstream_tests'] = $testResults;
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

