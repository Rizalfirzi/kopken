<?php
/**
 * API Endpoint: Outlet Wi-Fi Details
 * URL: /api/wifi.php?outlet={outletCode}
 * Or: /api/kopken/wifi?outlet={outletCode}
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/KopiKenanganApi.php';

try {
    $outlet = trim($_GET['outlet'] ?? $_GET['outletCode'] ?? config('store.default_outlet', 'LA.CODEFIN'));

    $api = new KopiKenanganApi();
    $result = $api->getWifi($outlet);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memuat info Wi-Fi outlet.',
        'error' => $e->getMessage(),
    ]);
}
