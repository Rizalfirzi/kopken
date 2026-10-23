<?php
/**
 * API Endpoint: Track Order by Code
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
    $code = $_GET['code'] ?? '';

    // Support URL rewrites like /api/kopken/track/{code}
    if (empty($code) && !empty($_SERVER['PATH_INFO'])) {
        $code = trim($_SERVER['PATH_INFO'], '/');
    }

    if (empty($code)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'found' => false,
            'message' => 'Kode pesanan wajib diisi.',
        ]);
        exit;
    }

    $api = new KopiKenanganApi();
    $result = $api->findOrder($code);

    if (empty($result['found'])) {
        http_response_code(404);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Terjadi kesalahan saat melacak pesanan.',
        'error' => $e->getMessage(),
    ]);
}
