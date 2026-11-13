<?php
/**
 * API Endpoint: Order Status Manager (Admin)
 * Allows Admin to view and update order tracking status in real-time.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/KopiKenanganApi.php';
require_once __DIR__ . '/../includes/SimpleRedis.php';

function getAdminPasscode(): string {
    $code = (string)config('admin.passcode', 'WHOSUTII26');
    $pricingPath = __DIR__ . '/../data/pricing_settings.json';
    if (file_exists($pricingPath)) {
        $json = @file_get_contents($pricingPath);
        $data = json_decode($json, true);
        if (!empty($data['passcode'])) {
            $code = (string)$data['passcode'];
        }
    }
    return trim($code);
}

try {
    $api = new KopiKenanganApi();
    $validPass = getAdminPasscode();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        $passcode = trim((string)($_GET['passcode'] ?? ''));

        if ($passcode !== $validPass) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Passcode admin salah atau tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $orders = $api->loadOrders();
        echo json_encode([
            'success' => true,
            'count' => count($orders),
            'orders' => $orders,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payload JSON tidak valid.']);
            exit;
        }

        $passcode = trim((string)($payload['passcode'] ?? ''));
        if ($passcode !== $validPass) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Passcode admin salah atau tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $orderCode = strtoupper(trim((string)($payload['orderCode'] ?? '')));
        $status = trim((string)($payload['status'] ?? 'diproses'));
        $statusText = trim((string)($payload['statusText'] ?? ''));
        $statusDesc = trim((string)($payload['statusDesc'] ?? ''));

        if (empty($orderCode)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Kode pesanan wajib diisi.']);
            exit;
        }

        $updated = $api->updateOrderStatus($orderCode, $status, $statusText, $statusDesc);

        echo json_encode([
            'success' => true,
            'message' => "Status pesanan {$orderCode} berhasil diubah menjadi: " . ($updated['statusText'] ?? $status),
            'order' => $updated,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak didukung.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada server saat memperbarui status pesanan.',
        'error' => $e->getMessage(),
    ]);
}

