<?php
/**
 * API Endpoint: Admin Pricing & Fee Manager
 * Allows store owner/admin to customize fees (default, category-level, item-level, etc.) directly from the website.
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

$validPasscode = trim((string)config('admin.passcode', 'WHOSUTII26'));
$dataPath = __DIR__ . '/../data/pricing_settings.json';

// Function to get current merged pricing configuration
function getCurrentPricing($dataPath) {
    $default = [
        'default_markup' => (float) config('pricing.default_markup', 5000),
        'category_markups' => config('pricing.category_markups', [
            'Coffee' => 5000,
            'Non Coffee' => 5000,
            'Frappe' => 5000,
            'Food' => 4500,
            'Minuman' => 5000,
            'Makanan' => 4500,
        ]),
        'item_markup_overrides' => config('pricing.item_markup_overrides', [
            'Kopi Kenangan Mantan' => 6000,
        ]),
        'single_item_fee' => (float) config('fees.single_item_fee', 2000),
        'plastic_bag_fee' => (float) config('fees.plastic_bag_fee', 1000),
        'admin_passcode' => (string) config('admin.passcode', 'WHOSUTII26'),
    ];

    if (file_exists($dataPath)) {
        $saved = json_decode(@file_get_contents($dataPath), true);
        if (is_array($saved)) {
            return array_merge($default, $saved);
        }
    }

    return $default;
}

try {
    // 1. GET: Fetch current pricing (requires valid code or session)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $code = trim($_GET['code'] ?? '');
        if ($code !== $validPasscode) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Kode redeem / akses admin salah atau tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = getCurrentPricing($dataPath);
        unset($data['admin_passcode']); // Do not expose passcode in payload

        echo json_encode([
            'success' => true,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 2. POST: Save new pricing
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $code = trim((string)($input['code'] ?? ''));
        if ($code !== $validPasscode) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Kode redeem / akses admin salah! Tidak dapat menyimpan perubahan.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $current = getCurrentPricing($dataPath);

        // Sanitize & Update Default Markup
        if (isset($input['default_markup'])) {
            $current['default_markup'] = max(0, (float)$input['default_markup']);
        }

        // Sanitize & Update Category Markups
        if (isset($input['category_markups']) && is_array($input['category_markups'])) {
            $cleanCats = [];
            foreach ($input['category_markups'] as $cat => $val) {
                $cName = trim((string)$cat);
                if ($cName !== '') {
                    $cleanCats[$cName] = max(0, (float)$val);
                }
            }
            $current['category_markups'] = $cleanCats;
        }

        // Sanitize & Update Item Markup Overrides
        if (isset($input['item_markup_overrides']) && is_array($input['item_markup_overrides'])) {
            $cleanItems = [];
            foreach ($input['item_markup_overrides'] as $name => $val) {
                $iName = trim((string)$name);
                if ($iName !== '') {
                    $cleanItems[$iName] = max(0, (float)$val);
                }
            }
            $current['item_markup_overrides'] = $cleanItems;
        }

        // Sanitize & Update Single Item Fee & Plastic Bag Fee
        if (isset($input['single_item_fee'])) {
            $current['single_item_fee'] = max(0, (float)$input['single_item_fee']);
        }
        if (isset($input['plastic_bag_fee'])) {
            $current['plastic_bag_fee'] = max(0, (float)$input['plastic_bag_fee']);
        }

        $current['updated_at'] = date('c');

        // Save to file
        $dir = dirname($dataPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $saved = @file_put_contents($dataPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Invalidate menu caches so new pricing takes effect instantly
        $cacheDir = __DIR__ . '/../cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/menu_*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
        }

        unset($current['admin_passcode']);

        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan fee berhasil disimpan! Harga menu langsung diperbarui.',
            'savedToFile' => (bool)$saved,
            'data' => $current,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server saat memproses pengaturan admin.',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
