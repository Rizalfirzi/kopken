<?php
/**
 * API Endpoint: Submit Order & WhatsApp Link Generation
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/KopiKenanganApi.php';

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Format payload pesanan tidak valid.',
        ]);
        exit;
    }

    if (empty($data['customerName'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Nama pemesan wajib diisi.',
        ]);
        exit;
    }

    if (empty($data['items']) || !is_array($data['items'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Keranjang pesanan tidak boleh kosong.',
        ]);
        exit;
    }

    $api = new KopiKenanganApi();
    $result = $api->saveOrder($data);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memproses pesanan.',
        'error' => $e->getMessage(),
    ]);
}
