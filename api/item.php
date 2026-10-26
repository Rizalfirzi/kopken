<?php
/**
 * API Endpoint: Live Item Detail & Addons (Topping & Syrup) per Outlet
 * Example: /api/item.php?outlet=BDG.ELCENTER&code=7245645&key=tarocloudlatte
 * Or: /api/kopken/item?outlet=BDG.ELCENTER&code=7245645&key=tarocloudlatte
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
    $code = trim($_GET['code'] ?? '');
    $key = trim($_GET['key'] ?? '');

    $api = new KopiKenanganApi();
    $result = $api->getItemDetails($outlet, $code, $key);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memuat detail item dan addon.',
        'error' => $e->getMessage(),
    ]);
}
