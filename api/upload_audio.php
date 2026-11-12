<?php
/**
 * Kedai Kyuti - Audio Upload Endpoint (MP3)
 * Allows admin to upload MP3 songs for Cafe Background Music (BGM).
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SimpleRedis.php';

$validPasscode = trim((string)config('admin.passcode', 'WHOSUTII26'));

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode HTTP tidak diizinkan. Gunakan POST untuk upload audio.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Verify Admin Passcode
    $code = trim($_POST['code'] ?? $_SERVER['HTTP_X_ADMIN_CODE'] ?? '');
    if ($code !== $validPasscode) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Akses admin tidak sah atau kode redeem salah.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Check File in $_FILES
    if (empty($_FILES['audio_file']) || !is_uploaded_file($_FILES['audio_file']['tmp_name'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tidak ada file MP3 yang dipilih untuk di-upload.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $file = $_FILES['audio_file'];
    $fileError = $file['error'];
    if ($fileError !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form.',
            UPLOAD_ERR_PARTIAL    => 'File hanya ter-upload sebagian.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang di-upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk server.',
        ];
        throw new Exception($errorMessages[$fileError] ?? 'Gagal upload file (Error code: ' . $fileError . ')');
    }

    // 3. Check File Extension & MIME
    $origName = $file['name'];
    $fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($fileExt !== 'mp3') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Format file harus .mp3 (file kamu: .' . $fileExt . ').',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fileSize = (int)$file['size'];
    $maxBytes = 8 * 1024 * 1024; // 8MB max
    if ($fileSize > $maxBytes) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ukuran file terlalu besar (maksimal 8 MB).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $targetDir = __DIR__ . '/../assets/audio';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }

    $safeBaseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
    $safeBaseName = substr($safeBaseName, 0, 30);
    $newFileName = 'kyuti_bgm_' . time() . '_' . $safeBaseName . '.mp3';
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newFileName;

    $audioUrl = '';

    // Check if assets/audio is writable (Persistent server / VPS / Localhost)
    if (is_writable($targetDir) && @move_uploaded_file($file['tmp_name'], $targetPath)) {
        $audioUrl = 'assets/audio/' . $newFileName;
    } else {
        // Fallback for Read-Only / Serverless Environment (Vercel)
        // Store in /tmp or Base64 Data URI if file size <= 4MB
        $fileBytes = @file_get_contents($file['tmp_name']);
        if ($fileBytes === false || strlen($fileBytes) === 0) {
            throw new Exception('Gagal membaca isi file audio.');
        }

        // Base64 Data URI allows instant audio playback without server-side storage dependency
        $base64 = base64_encode($fileBytes);
        $audioUrl = 'data:audio/mp3;base64,' . $base64;

        // Also save to temp cache directory if available
        $tempDir = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        @file_put_contents($tempDir . DIRECTORY_SEPARATOR . $newFileName, $fileBytes);
    }

    echo json_encode([
        'success' => true,
        'message' => 'File MP3 berhasil di-upload!',
        'url' => $audioUrl,
        'filename' => $origName,
        'size' => $fileSize,
        'size_formatted' => round($fileSize / 1024 / 1024, 2) . ' MB',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memproses file audio: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

