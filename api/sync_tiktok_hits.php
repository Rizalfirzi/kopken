<?php
/**
 * API Endpoint: Auto-Sync Lagu Viral TikTok via Spotify API
 * Fetches real-time TikTok Viral / Top Hits tracks and updates web BGM playlist
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Admin-Code');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SimpleRedis.php';

$passcode = config('admin.passcode', 'WHOSUTII26');
$input = json_decode((string)file_get_contents('php://input'), true) ?? $_POST;

$providedCode = trim((string)(
    $_SERVER['HTTP_X_ADMIN_CODE']
    ?? $input['passcode']
    ?? $_GET['code']
    ?? $_SESSION['kyuti_admin_auth']
    ?? ''
));

// Verify admin authorization
if ($providedCode !== $passcode && empty($_SESSION['kyuti_admin_auth'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Passcode admin salah atau sesi tidak valid. Silakan login admin terlebih dahulu.',
    ]);
    exit;
}

// Playlist ID defaults to TikTok Viral Hits (37i9dQZF1DX2L0iB23Enbq) or Today's Top Hits (37i9dQZF1DXcBWIGoYBM5M)
$rawPlaylist = trim((string)($input['playlist_id'] ?? $input['playlist_url'] ?? '37i9dQZF1DX2L0iB23Enbq'));

$playlistId = '37i9dQZF1DX2L0iB23Enbq';
if (preg_match('/playlist\/([a-zA-Z0-9]+)/', $rawPlaylist, $m)) {
    $playlistId = $m[1];
} elseif (preg_match('/^[a-zA-Z0-9]+$/', $rawPlaylist)) {
    $playlistId = $rawPlaylist;
}

$limit = isset($input['limit']) ? max(5, min(30, (int)$input['limit'])) : 15;

// Fetch Spotify Embed Playlist
$url = "https://open.spotify.com/embed/playlist/{$playlistId}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || empty($html)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghubungi server Spotify (HTTP ' . $httpCode . '). Coba beberapa saat lagi.',
        'error' => $curlErr,
    ]);
    exit;
}

if (!preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Format playlist Spotify tidak dikenali atau playlist bersifat privat.',
    ]);
    exit;
}

$data = json_decode($matches[1], true);
$entity = $data['props']['pageProps']['state']['data']['entity'] ?? null;

if (!$entity || empty($entity['trackList'])) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Daftar lagu di playlist Spotify tidak ditemukan.',
    ]);
    exit;
}

$playlistName = $entity['name'] ?? 'TikTok Viral Hits';
$rawTracks = $entity['trackList'];

// Denny Caknan - Negoro Angin (Request khusus client sebagai track pembuka)
$dennyTrack = [
    'title' => 'Negoro Angin (Trending TikTok ♫)',
    'artist' => 'Denny Caknan',
    'src' => 'https://raw.githubusercontent.com/riccardobertolini/lofi-music/main/public/audio/empty-mind-118973.mp3',
    'spotify_url' => 'https://open.spotify.com/intl-id/track/3kfVlzPUsy7x1MiPHRuFr0?si=798585022f7e4349',
    'spotify_id' => '3kfVlzPUsy7x1MiPHRuFr0',
];

$syncedTracks = [$dennyTrack];

foreach ($rawTracks as $t) {
    if (count($syncedTracks) >= ($limit + 1)) {
        break;
    }

    $uri = (string)($t['uri'] ?? '');
    $trackId = str_replace('spotify:track:', '', $uri);
    $title = trim((string)($t['title'] ?? 'Lagu TikTok Viral'));
    $artist = trim((string)($t['subtitle'] ?? 'Trending TikTok'));
    $audioPreview = (string)($t['audioPreview']['url'] ?? '');

    // Avoid duplicate of Denny Caknan
    if ($trackId === '3kfVlzPUsy7x1MiPHRuFr0') {
        continue;
    }

    // Audio source: official preview MP3, or lofi fallback if not previewable
    $src = !empty($audioPreview)
        ? $audioPreview
        : 'https://raw.githubusercontent.com/riccardobertolini/lofi-music/main/public/audio/piano.mp3';

    $spotifyUrl = "https://open.spotify.com/track/{$trackId}";

    $syncedTracks[] = [
        'title' => $title,
        'artist' => $artist,
        'src' => $src,
        'spotify_url' => $spotifyUrl,
        'spotify_id' => $trackId,
    ];
}

// Save updated playlist into pricing_settings.json & Redis/Cache
$dataPath = __DIR__ . '/../data/pricing_settings.json';
$tempPath = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'pricing_settings.json';

$current = [];
if (file_exists($dataPath)) {
    $current = json_decode((string)file_get_contents($dataPath), true) ?: [];
}

if (!isset($current['bgm']) || !is_array($current['bgm'])) {
    $current['bgm'] = [
        'enabled' => true,
        'default_volume' => 0.4,
    ];
}

$current['bgm']['playlist'] = $syncedTracks;
$current['updated_at'] = date('c');

// 1. Save to local persistent storage
$dir = dirname($dataPath);
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}
@file_put_contents($dataPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 2. Save to serverless /tmp
$tempDir = dirname($tempPath);
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0777, true);
}
@file_put_contents($tempPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 3. Save to Cloud Redis if connected
$redis = new SimpleRedis();
if ($redis->isConnected()) {
    $redis->set('pricing_settings', json_encode($current));
    $tempKvCache = $tempDir . DIRECTORY_SEPARATOR . 'kv_pricing_cache.json';
    @file_put_contents($tempKvCache, json_encode($current));
}

// 4. Set fallback cookie for instant sync across serverless lambdas
$cookieVal = urlencode(json_encode($current));
setcookie('kyuti_custom_pricing', $cookieVal, [
    'expires' => time() + (86400 * 365),
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => false,
    'samesite' => 'Lax'
]);

echo json_encode([
    'success' => true,
    'message' => 'Berhasil sinkronisasi ' . count($syncedTracks) . ' lagu viral TikTok via Spotify API!',
    'playlist_name' => $playlistName,
    'count' => count($syncedTracks),
    'playlist' => $syncedTracks,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
