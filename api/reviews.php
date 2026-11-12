<?php
/**
 * API Endpoint: Customer Reviews & Ratings
 * Handles listing reviews and customer review submissions.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Code');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SimpleRedis.php';

$dataPath = __DIR__ . '/../data/reviews.json';
$tempPath = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'reviews.json';

function getAdminPasscode(): string {
    $code = (string)config('admin.passcode', 'WHOSUTII26');
    $pricingPath = __DIR__ . '/../data/pricing_settings.json';
    if (file_exists($pricingPath)) {
        $pData = json_decode(@file_get_contents($pricingPath), true);
        if (!empty($pData['admin_passcode'])) {
            $code = trim((string)$pData['admin_passcode']);
        }
    }
    return $code;
}

function getReviewsList(): array {
    global $dataPath, $tempPath;

    $loaded = null;

    // Check Cloud Database via SimpleRedis first
    $redis = new SimpleRedis();
    if ($redis->isConnected()) {
        $cachedVal = $redis->get('customer_reviews');
        if (!empty($cachedVal)) {
            $raw = is_string($cachedVal) ? json_decode($cachedVal, true) : $cachedVal;
            if (is_array($raw)) {
                $loaded = $raw;
            }
        }
    }

    if (!is_array($loaded) && file_exists($tempPath)) {
        $loaded = json_decode(@file_get_contents($tempPath), true);
    }

    if (!is_array($loaded) && file_exists($dataPath)) {
        $loaded = json_decode(@file_get_contents($dataPath), true);
    }

    if (!is_array($loaded)) {
        $loaded = [];
    }

    return $loaded;
}

function saveReviewsList(array $reviews): bool {
    global $dataPath, $tempPath;

    $json = json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Save to local data
    @file_put_contents($dataPath, $json);

    // Save to temp cache
    @file_put_contents($tempPath, $json);

    // Save to Cloud Redis if connected
    $redis = new SimpleRedis();
    if ($redis->isConnected()) {
        $redis->set('customer_reviews', $json);
    }

    return true;
}

function computeStats(array $reviews): array {
    $total = count($reviews);
    if ($total === 0) {
        return [
            'total' => 0,
            'average' => 5.0,
            'stars' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            'satisfactionPercent' => 100,
        ];
    }

    $sum = 0;
    $stars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach ($reviews as $r) {
        $rating = max(1, min(5, (int)($r['rating'] ?? 5)));
        $sum += $rating;
        $stars[$rating] = ($stars[$rating] ?? 0) + 1;
    }

    $avg = round($sum / $total, 1);
    $satPercent = round((($stars[5] + $stars[4]) / $total) * 100);

    return [
        'total' => $total,
        'average' => $avg,
        'stars' => $stars,
        'satisfactionPercent' => $satPercent,
    ];
}

try {
    $reviews = getReviewsList();

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // 1. DELETE ACTION (Admin only)
    $isDelete = ($method === 'DELETE') || ($method === 'POST' && ($input['action'] ?? '') === 'delete');

    if ($isDelete) {
        $validPasscode = getAdminPasscode();
        $providedCode = trim((string)(
            $_SERVER['HTTP_X_ADMIN_CODE']
            ?? $input['passcode']
            ?? $input['code']
            ?? $_GET['code']
            ?? $_SESSION['kyuti_admin_auth']
            ?? ''
        ));

        if ($providedCode !== $validPasscode && empty($_SESSION['kyuti_admin_auth'])) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Passcode admin salah atau tidak valid! Hanya admin yang dapat menghapus ulasan.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $reviewId = trim((string)($input['id'] ?? $_GET['id'] ?? ''));
        if ($reviewId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID ulasan tidak valid atau tidak ditemukan.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $found = false;
        $deletedName = '';
        $newReviews = [];
        foreach ($reviews as $rev) {
            if (($rev['id'] ?? '') === $reviewId) {
                $found = true;
                $deletedName = $rev['name'] ?? 'Customer';
                // Delete photo file if locally stored
                if (!empty($rev['photo']) && strpos($rev['photo'], 'assets/img/reviews/') === 0) {
                    $localImg = __DIR__ . '/../' . $rev['photo'];
                    if (file_exists($localImg)) {
                        @unlink($localImg);
                    }
                }
            } else {
                $newReviews[] = $rev;
            }
        }

        if (!$found) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Ulasan dengan ID tersebut tidak ditemukan atau sudah dihapus.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        saveReviewsList($newReviews);

        echo json_encode([
            'success' => true,
            'message' => "Ulasan dari {$deletedName} berhasil dihapus.",
            'reviews' => $newReviews,
            'stats' => computeStats($newReviews),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 2. CREATE REVIEW (POST)
    if ($method === 'POST') {
        $name = trim((string)($input['name'] ?? ''));
        $rating = (int)($input['rating'] ?? 5);
        $menu = trim((string)($input['menu'] ?? ''));
        $comment = trim((string)($input['comment'] ?? ''));
        $outlet = trim((string)($input['outlet'] ?? ''));
        $photo = trim((string)($input['photo'] ?? ''));

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nama tidak boleh kosong.']);
            exit;
        }

        if ($comment === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ulasan tidak boleh kosong.']);
            exit;
        }

        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }

        // Handle Photo Upload (Shopee-style review photo)
        $cleanPhoto = '';
        if (!empty($photo)) {
            if (strpos($photo, 'data:image/') === 0) {
                $imgDir = __DIR__ . '/../assets/img/reviews';
                if (!is_dir($imgDir)) {
                    @mkdir($imgDir, 0755, true);
                }

                if (preg_match('/^data:image\/(\w+);base64,/', $photo, $match)) {
                    $ext = strtolower($match[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    $base64Data = substr($photo, strpos($photo, ',') + 1);
                    $decoded = base64_decode($base64Data);
                    if ($decoded !== false) {
                        $imgName = 'rev_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
                        $targetPath = $imgDir . DIRECTORY_SEPARATOR . $imgName;
                        if (is_writable($imgDir) && @file_put_contents($targetPath, $decoded)) {
                            $cleanPhoto = 'assets/img/reviews/' . $imgName;
                        } else {
                            // Serverless fallback (Data URI)
                            $cleanPhoto = $photo;
                        }
                    }
                }
            } elseif (filter_var($photo, FILTER_VALIDATE_URL) || strpos($photo, 'assets/') === 0) {
                $cleanPhoto = htmlspecialchars($photo);
            }
        }

        // Sanitize
        $cleanName = htmlspecialchars(strip_tags($name));
        $cleanComment = htmlspecialchars(strip_tags($comment));
        $cleanMenu = htmlspecialchars(strip_tags($menu ?: 'Menu Kopi Kenangan'));
        $cleanOutlet = htmlspecialchars(strip_tags($outlet ?: 'Kedai Kyuti'));

        // Indonesian date format
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $todayDate = date('j') . ' ' . ($months[(int)date('n')] ?? date('F')) . ' ' . date('Y');

        $newReview = [
            'id' => 'rev_' . time() . '_' . substr(md5(uniqid()), 0, 4),
            'name' => $cleanName,
            'rating' => $rating,
            'menu' => $cleanMenu,
            'comment' => $cleanComment,
            'date' => $todayDate,
            'verified' => true,
            'outlet' => $cleanOutlet,
        ];

        if (!empty($cleanPhoto)) {
            $newReview['photo'] = $cleanPhoto;
        }

        // Prepend new review to top of list
        array_unshift($reviews, $newReview);

        // Keep maximum 100 reviews
        if (count($reviews) > 100) {
            $reviews = array_slice($reviews, 0, 100);
        }

        saveReviewsList($reviews);

        echo json_encode([
            'success' => true,
            'message' => 'Terima kasih atas ulasan manis kamu! ♡ Ulasan kamu sudah ditampilkan.',
            'review' => $newReview,
            'reviews' => $reviews,
            'stats' => computeStats($reviews),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Default GET
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'stats' => computeStats($reviews),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memproses review: ' . $e->getMessage(),
    ]);
}
