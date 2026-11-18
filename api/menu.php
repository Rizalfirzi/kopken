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
    if (!empty($_GET['debug'])) {
        $testSources = [
            'tixandcoffee' => 'https://tixandcoffee.developerngantuk.my.id',
            'ridstore' => 'https://ridstore.developerngantuk.my.id',
            'tyaastore' => 'https://tyaastore.my.id',
            'twinsjasdor' => 'https://twinsjasdor.my.id'
        ];
        
        $diag = [];
        foreach ($testSources as $name => $sUrl) {
            $ch = curl_init($sUrl . '/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            $h = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'X-Forwarded-For: 180.252.164.88',
                'Client-IP: 180.252.164.88'
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            $diag[$name] = [
                'code' => $code,
                'err' => $err,
                'sample' => substr((string)$resp, 0, 200),
            ];
        }
        $result['debug_diag'] = $diag;
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server saat memuat menu.',
        'error' => $e->getMessage(),
    ]);
}
