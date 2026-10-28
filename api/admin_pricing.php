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
require_once __DIR__ . '/../includes/SimpleRedis.php';

$validPasscode = trim((string)config('admin.passcode', 'WHOSUTII26'));
$dataPath = __DIR__ . '/../data/pricing_settings.json';
$tempPath = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'pricing_settings.json';

// Function to get current merged pricing configuration
function getCurrentPricing() {
    global $dataPath, $tempPath;

    $default = [
        'default_markup' => (float) config('pricing.default_markup', 5000),
        'category_markups' => config('pricing.category_markups', [
            'Coffee' => 5000,
            'Non Coffee' => 5000,
            'Frappe' => 5000,
            'Food' => 4500,
            'Minuman' => 5000,
            'Makanan' => 4500,
            'Bundling' => 0,
        ]),
        'item_markup_overrides' => config('pricing.item_markup_overrides', []),
        'single_item_fee' => (float) config('fees.single_item_fee', 0),
        'plastic_bag_fee' => (float) config('fees.plastic_bag_fee', 1000),
        'bundling_status' => config('pricing.bundling_status', [
            'bundle_2_cup_large' => true,
            'bundle_2_frappe' => true,
            'promo_minuman_special' => true,
            'bundle_hemat_3_item' => true,
        ]),
        'disabled_bundling_options' => config('pricing.disabled_bundling_options', []),
        'admin_passcode' => (string) config('admin.passcode', 'WHOSUTII26'),
    ];

    $loaded = null;

    // Check Cloud Database via SimpleRedis first
    $redis = new SimpleRedis();
    if ($redis->isConnected()) {
        $tempKvCache = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'kv_pricing_cache.json';
        if (file_exists($tempKvCache)) {
            $loaded = json_decode(@file_get_contents($tempKvCache), true);
        }
        if (!is_array($loaded)) {
            $cachedVal = $redis->get('pricing_settings');
            if (!empty($cachedVal)) {
                $rawResult = is_string($cachedVal) ? json_decode($cachedVal, true) : $cachedVal;
                if (is_array($rawResult)) {
                    $loaded = $rawResult;
                }
            }
        }
    }

    if (!is_array($loaded) && file_exists($tempPath)) {
        $loaded = json_decode(@file_get_contents($tempPath), true);
    }
    if (!is_array($loaded) && file_exists($dataPath)) {
        $loaded = json_decode(@file_get_contents($dataPath), true);
    }
    if (!is_array($loaded) && !empty($_COOKIE['kyuti_custom_pricing'])) {
        $raw = $_COOKIE['kyuti_custom_pricing'];
        $decoded = json_decode(urldecode($raw), true);
        if (!is_array($decoded)) {
            $decoded = json_decode($raw, true);
        }
        if (is_array($decoded)) {
            $loaded = $decoded;
        }
    }

    if (is_array($loaded)) {
        if (isset($loaded['default_markup'])) {
            $default['default_markup'] = max(0, (float)$loaded['default_markup']);
        }
        if (isset($loaded['category_markups']) && is_array($loaded['category_markups'])) {
            $default['category_markups'] = $loaded['category_markups'];
        }
        // Direct assignment, do NOT merge deleted items!
        if (isset($loaded['item_markup_overrides']) && is_array($loaded['item_markup_overrides'])) {
            $default['item_markup_overrides'] = $loaded['item_markup_overrides'];
        }
        if (isset($loaded['single_item_fee'])) {
            $default['single_item_fee'] = max(0, (float)$loaded['single_item_fee']);
        }
        if (isset($loaded['plastic_bag_fee'])) {
            $default['plastic_bag_fee'] = max(0, (float)$loaded['plastic_bag_fee']);
        }
        if (isset($loaded['bundling_status']) && is_array($loaded['bundling_status'])) {
            $default['bundling_status'] = $loaded['bundling_status'];
        }
        if (isset($loaded['disabled_bundling_options']) && is_array($loaded['disabled_bundling_options'])) {
            $default['disabled_bundling_options'] = $loaded['disabled_bundling_options'];
        }
        if (!empty($loaded['admin_passcode'])) {
            $default['admin_passcode'] = trim((string)$loaded['admin_passcode']);
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

        $redis = new SimpleRedis();
        $hasCloudDb = $redis->isConnected();

        $data = getCurrentPricing();
        unset($data['admin_passcode']); // Do not expose passcode in payload

        echo json_encode([
            'success' => true,
            'data' => $data,
            'database' => [
                'cloud_connected' => $hasCloudDb,
                'provider' => $redis->getProviderName(),
            ]
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

        $current = getCurrentPricing();

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

        // Sanitize & Update Item Markup Overrides (Direct assignment to allow deleting)
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

        // Sanitize & Update Bundling ON/OFF status
        if (isset($input['bundling_status']) && is_array($input['bundling_status'])) {
            $current['bundling_status'] = $input['bundling_status'];
        }

        // Sanitize & Update Disabled Bundling Options
        if (isset($input['disabled_bundling_options']) && is_array($input['disabled_bundling_options'])) {
            $cleanOpts = [];
            foreach ($input['disabled_bundling_options'] as $opt) {
                $o = trim((string)$opt);
                if ($o !== '') $cleanOpts[] = $o;
            }
            $current['disabled_bundling_options'] = array_values(array_unique($cleanOpts));
        }

        $current['updated_at'] = date('c');

        // 1. Try to save to persistent data directory (for localhost / persistent servers)
        $dir = dirname($dataPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $savedData = @file_put_contents($dataPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2. ALWAYS save to serverless writable cache directory (works on Vercel Lambda /tmp)
        $tempDir = dirname($tempPath);
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $savedTemp = @file_put_contents($tempPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 3. Sync to Cloud Database (Redis TCP Socket & REST API via SimpleRedis)
        $redis = new SimpleRedis();
        $savedToCloud = false;
        if ($redis->isConnected()) {
            $savedToCloud = $redis->set('pricing_settings', json_encode($current));
        }

        // Overwrite local KV cache immediately so current lambda does not serve stale data
        $tempKvCache = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'kv_pricing_cache.json';
        $tempKvDir = dirname($tempKvCache);
        if (!is_dir($tempKvDir)) @mkdir($tempKvDir, 0777, true);
        @file_put_contents($tempKvCache, json_encode($current));

        // 4. CRITICAL: Clear ALL menu and item cache files in CACHE_PATH so new pricing takes effect instantly
        $cacheDir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . DIRECTORY_SEPARATOR . '*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f) && basename($f) !== 'kv_pricing_cache.json') {
                        @unlink($f);
                    }
                }
            }
        }

        // 5. Set Cookie for immediate cross-request synchronization on serverless
        $cookiePayload = $current;
        unset($cookiePayload['admin_passcode']);
        @setcookie('kyuti_custom_pricing', urlencode(json_encode($cookiePayload, JSON_UNESCAPED_UNICODE)), [
            'expires' => time() + 31536000,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
        ]);

        unset($current['admin_passcode']);

        echo json_encode([
            'success' => true,
            'message' => 'Pengaturan fee berhasil disimpan! Harga menu langsung diperbarui.',
            'savedToFile' => (bool)($savedData || $savedTemp),
            'savedToCloud' => $savedToCloud,
            'database' => [
                'cloud_connected' => $redis->isConnected(),
                'saved_to_cloud' => $savedToCloud,
                'provider' => $redis->getProviderName(),
            ],
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
