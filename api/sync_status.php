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

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

