<?php
/**
 * Multi-Brand Real-time API Gateway (swamptechnology.id)
 * Brands: Kopi Kenangan, Janji Jiwa, Point Coffee, Fore Coffee, Tomoro Coffee
 * Edge-cached on Vercel CDN for ultra-fast, zero-overhead delivery.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Edge CDN and browser caching (optimizes Vercel serverless function invocations)
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
    $brand = strtolower(trim($_GET['brand'] ?? 'kopken'));
    $action = strtolower(trim($_GET['action'] ?? 'menu'));

    $params = [];
    if (!empty($_GET['outlet']) || !empty($_GET['store_code'])) {
        $params['store_code'] = trim($_GET['outlet'] ?? $_GET['store_code']);
    }
    if (!empty($_GET['q'])) {
        $params['q'] = trim($_GET['q']);
    }
    if (isset($_GET['lat']) && is_numeric($_GET['lat'])) {
        $params['lat'] = (float)$_GET['lat'];
    }
    if (isset($_GET['lng']) && is_numeric($_GET['lng'])) {
        $params['lng'] = (float)$_GET['lng'];
    }
    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $params['limit'] = (int)$_GET['limit'];
    }

    $api = new KopiKenanganApi();
    $result = $api->getBrandData($brand, $action, $params);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memuat data brand.',
        'error' => $e->getMessage(),
    ]);
}
