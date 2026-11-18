<?php
/**
 * API Endpoint: Menu & Real-time Stock per Outlet
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Set smart Edge CDN and browser cache headers (drastically cuts Vercel bandwidth & function invocations)
if (!headers_sent()) {
    $forceRefresh = isset($_GET['refresh']) && in_array(strtolower((string)$_GET['refresh']), ['1', 'true', 'yes'], true);
    if ($forceRefresh) {
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
    } else {
        header('Cache-Control: public, max-age=15, s-maxage=60, stale-while-revalidate=120');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
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
