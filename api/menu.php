<?php
/**
 * API Endpoint: Menu & Real-time Stock per Outlet
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/KopiKenanganApi.php';

try {
    $api = new KopiKenanganApi();

    // Check query params: outlet, outletCode, or path info
    $outletCode = $_GET['outlet'] ?? $_GET['outletCode'] ?? '';
    
    // Support URL rewrites like /api/kopken/menu/{outletCode}
    if (empty($outletCode) && !empty($_SERVER['PATH_INFO'])) {
        $outletCode = trim($_SERVER['PATH_INFO'], '/');
    }
    
    if (empty($outletCode)) {
        $outletCode = config('store.default_outlet', 'LA.CODEFIN');
    }

    $forceRefresh = isset($_GET['refresh']) && in_array(strtolower((string)$_GET['refresh']), ['1', 'true', 'yes'], true);

    $result = $api->getMenu($outletCode, $forceRefresh);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server saat memuat menu.',
        'error' => $e->getMessage(),
    ]);
}
