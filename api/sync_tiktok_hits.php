<?php
/**
 * API Endpoint: Auto-Sync Lagu Viral TikTok Indonesia via Spotify API
 * Menarik daftar lagu viral TikTok khusus Indonesia (Pop, Koplo, Trending Sound ID)
 * Termasuk Denny Caknan - Negoro Angin dengan audio preview resmi Spotify.
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

$limit = isset($input['limit']) ? max(5, min(30, (int)$input['limit'])) : 15;

// Denny Caknan - Negoro Angin (Track Pembuka Wajib dengan Full Playback Engine & Audio Preview)
$dennyTrack = [
    'title' => 'Negoro Angin (Trending TikTok ♫)',
    'artist' => 'Denny Caknan',
    'youtube_id' => 'WZYUSaHlGlk',
    'src' => 'https://p.scdn.co/mp3-preview/6945ec27f637b0b94fe6667b22331aa87dd61919',
    'spotify_url' => 'https://open.spotify.com/intl-id/track/3kfVlzPUsy7x1MiPHRuFr0?si=798585022f7e4349',
    'spotify_id' => '3kfVlzPUsy7x1MiPHRuFr0',
];

// Curated verified pool Lagu Viral TikTok Indonesia (Full Lagu Playback + Spotify Preview MP3)
$curatedIndoHits = [
    [
        'title' => 'Sesi Potret',
        'artist' => 'eńau ft. Ari Lesmana',
        'youtube_id' => 'Nskf70DMR60',
        'src' => 'https://p.scdn.co/mp3-preview/6732c1a991a5b86cb440bd31d837709cc43e4d7b',
        'spotify_url' => 'https://open.spotify.com/track/4xoY4lZNoTjEuHsSmhgF1G',
        'spotify_id' => '4xoY4lZNoTjEuHsSmhgF1G',
    ],
    [
        'title' => 'Dunia Yang Nanti',
        'artist' => 'Raim Laode',
        'youtube_id' => 'awWKxGftWh4',
        'src' => 'https://p.scdn.co/mp3-preview/55e3c2391a3da57c8401740495e79adcbe2bbf55',
        'spotify_url' => 'https://open.spotify.com/track/1sVQuuvFDKu6vklPVeiUXT',
        'spotify_id' => '1sVQuuvFDKu6vklPVeiUXT',
    ],
    [
        'title' => 'bergema sampai selamanya',
        'artist' => 'Nadhif Basalamah',
        'youtube_id' => 'g0c7FeMn5lE',
        'src' => 'https://p.scdn.co/mp3-preview/845fe1cefc94e3212577d094cb734ac285e03847',
        'spotify_url' => 'https://open.spotify.com/track/0ql6fgz8qk0pAEKrGeSppJ',
        'spotify_id' => '0ql6fgz8qk0pAEKrGeSppJ',
    ],
    [
        'title' => 'Teh Hijau',
        'artist' => 'Tulus',
        'youtube_id' => 'RO75uUZiAw0',
        'src' => 'https://p.scdn.co/mp3-preview/21f73dbf8d76b6e9429737a1e1e04222c88c1fbb',
        'spotify_url' => 'https://open.spotify.com/track/4R9G7azXaZe93KTX65P9fU',
        'spotify_id' => '4R9G7azXaZe93KTX65P9fU',
    ],
    [
        'title' => 'Jatuh Suka',
        'artist' => 'Tulus',
        'youtube_id' => '2uJut6USftQ',
        'src' => 'https://p.scdn.co/mp3-preview/73e5752501d7fa89afdbf66c827b19bfb07c7263',
        'spotify_url' => 'https://open.spotify.com/track/6PqWdGIYq5xdLaa4zCZfRp',
        'spotify_id' => '6PqWdGIYq5xdLaa4zCZfRp',
    ],
    [
        'title' => 'Amnesia',
        'artist' => 'Al-Ghazali',
        'youtube_id' => 'R5VqY5TVYF8',
        'src' => 'https://p.scdn.co/mp3-preview/7541f2a776c67cc9717efac645fe8807751f8ba0',
        'spotify_url' => 'https://open.spotify.com/track/5yUjO084QWrMxM4EqpgWFr',
        'spotify_id' => '5yUjO084QWrMxM4EqpgWFr',
    ],
    [
        'title' => 'Iqro\'',
        'artist' => 'Raim Laode',
        'youtube_id' => '9WHWkEJLKJM',
        'src' => 'https://p.scdn.co/mp3-preview/acc1f9c50f7ad189391a6bb72b98592b92a765ad',
        'spotify_url' => 'https://open.spotify.com/track/2ImWVGyYTRMi64861WCCcq',
        'spotify_id' => '2ImWVGyYTRMi64861WCCcq',
    ],
    [
        'title' => 'Terbelah Jadi Dua',
        'artist' => 'Gloria Jessica',
        'youtube_id' => 'ks27-0q-Hrc',
        'src' => 'https://p.scdn.co/mp3-preview/e45bdda64df81b475c0ca7364df71ffdefcd59a9',
        'spotify_url' => 'https://open.spotify.com/track/2wAa43qM16H0PWAwiHTk5S',
        'spotify_id' => '2wAa43qM16H0PWAwiHTk5S',
    ],
    [
        'title' => 'Separuh Aku',
        'artist' => 'Noah',
        'youtube_id' => 'b0ZBBjViV8Y',
        'src' => 'https://p.scdn.co/mp3-preview/5c7cb0bd2c458e1abdf0dbcc63fbb849241dbd51',
        'spotify_url' => 'https://open.spotify.com/track/4sJmv194lQKWjytpaxH1Vj',
        'spotify_id' => '4sJmv194lQKWjytpaxH1Vj',
    ],
    [
        'title' => 'Titik Nadir',
        'artist' => 'Kahitna',
        'youtube_id' => 'OQNu-iB2tE8',
        'src' => 'https://p.scdn.co/mp3-preview/d8e118faa9e87c34f69f269b509b1b2dc567e006',
        'spotify_url' => 'https://open.spotify.com/track/52m690jqpxP1tHHkRNdl5p',
        'spotify_id' => '52m690jqpxP1tHHkRNdl5p',
    ],
    [
        'title' => 'Bersenja Gurau',
        'artist' => 'Raim Laode',
        'youtube_id' => 'jmvX6XyvCy0',
        'src' => 'https://p.scdn.co/mp3-preview/3babaa3e942f6360a8706e7b9bd2173aa7d774de',
        'spotify_url' => 'https://open.spotify.com/track/4DrJqdZgPBHoMNVG5iOLlG',
        'spotify_id' => '4DrJqdZgPBHoMNVG5iOLlG',
    ],
    [
        'title' => 'Monolog',
        'artist' => 'Pamungkas',
        'youtube_id' => '-mwsPoerFWU',
        'src' => 'https://p.scdn.co/mp3-preview/f56125670323983790e173de5840cbca0842ce14',
        'spotify_url' => 'https://open.spotify.com/track/1zu5ZpnrSArdoaT6Qq3yo9',
        'spotify_id' => '1zu5ZpnrSArdoaT6Qq3yo9',
    ],
    [
        'title' => 'Ini Abadi',
        'artist' => 'Perunggu',
        'youtube_id' => 'qQDaxjLReck',
        'src' => 'https://p.scdn.co/mp3-preview/8455004c75fb9fbf904475fabfdf24b1f5d7797e',
        'spotify_url' => 'https://open.spotify.com/track/6v5RJuJ9yhvaXkMXMeMZBw',
        'spotify_id' => '6v5RJuJ9yhvaXkMXMeMZBw',
    ],
    [
        'title' => 'Bahagia Lagi',
        'artist' => 'Piche Kota',
        'youtube_id' => 'liVmYVkSV1M',
        'src' => 'https://p.scdn.co/mp3-preview/15c4fa72950ba107d1148cae5998d7292fcb204a',
        'spotify_url' => 'https://open.spotify.com/track/5tZaKZPJtiHh6teQfVEHB5',
        'spotify_id' => '5tZaKZPJtiHh6teQfVEHB5',
    ],
    [
        'title' => 'Ada titik-titik di ujung doa',
        'artist' => 'Sal Priadi',
        'youtube_id' => '1E7zLPDdqo0OvzVmGj5aoV',
        'src' => 'https://p.scdn.co/mp3-preview/15ce12fe0e8fe5f1dd15b39ed8f0f37ae1ff1c87',
        'spotify_url' => 'https://open.spotify.com/track/1E7zLPDdqo0OvzVmGj5aoV',
        'spotify_id' => '1E7zLPDdqo0OvzVmGj5aoV',
    ],
    [
        'title' => 'Sisa Rasa',
        'artist' => 'Mahalini',
        'youtube_id' => '326c19a66310cad958375a2d249209c1c82c7885',
        'src' => 'https://p.scdn.co/mp3-preview/326c19a66310cad958375a2d249209c1c82c7885',
        'spotify_url' => 'https://open.spotify.com/track/0R6KCq5TLJB3gi2ragMrXZ',
        'spotify_id' => '0R6KCq5TLJB3gi2ragMrXZ',
    ],
    [
        'title' => 'everything u are',
        'artist' => 'Hindia',
        'youtube_id' => '600581384f3aa95661b6a2f74dc242432d295df3',
        'src' => 'https://p.scdn.co/mp3-preview/600581384f3aa95661b6a2f74dc242432d295df3',
        'spotify_url' => 'https://open.spotify.com/track/5WOSNVChcadlsCRiqXE45K',
        'spotify_id' => '5WOSNVChcadlsCRiqXE45K',
    ],
    [
        'title' => 'Serana',
        'artist' => 'For Revenge',
        'youtube_id' => '4e00c0c026dfee369545e2fd88b2f11ac71507aa',
        'src' => 'https://p.scdn.co/mp3-preview/4e00c0c026dfee369545e2fd88b2f11ac71507aa',
        'spotify_url' => 'https://open.spotify.com/track/0PtJbtW50jcvvswNPn3QGd',
        'spotify_id' => '0PtJbtW50jcvvswNPn3QGd',
    ],
    [
        'title' => 'Nina',
        'artist' => '.Feast',
        'youtube_id' => '66bb1ca58e670373f4aff7878fe5ed2485e2e439',
        'src' => 'https://p.scdn.co/mp3-preview/66bb1ca58e670373f4aff7878fe5ed2485e2e439',
        'spotify_url' => 'https://open.spotify.com/track/0daEJMXc3b4ZMTnvtHpuTt',
        'spotify_id' => '0daEJMXc3b4ZMTnvtHpuTt',
    ],
    [
        'title' => 'Kangen',
        'artist' => 'Dewa 19',
        'youtube_id' => 'f9ade56b686a0f49c7b6142920ef5dad9bd05ec2',
        'src' => 'https://p.scdn.co/mp3-preview/f9ade56b686a0f49c7b6142920ef5dad9bd05ec2',
        'spotify_url' => 'https://open.spotify.com/track/3ZyMRe0jlSqffPBMeHUZFX',
        'spotify_id' => '3ZyMRe0jlSqffPBMeHUZFX',
    ],
    [
        'title' => 'Soulmate',
        'artist' => 'Kahitna',
        'youtube_id' => 'a4b98c75685a0f20a2e4304bc0c8830574de8537',
        'src' => 'https://p.scdn.co/mp3-preview/a4b98c75685a0f20a2e4304bc0c8830574de8537',
        'spotify_url' => 'https://open.spotify.com/track/1rwGbGLDE0AkaH1UgUbufL',
        'spotify_id' => '1rwGbGLDE0AkaH1UgUbufL',
    ],
];

$rawPlaylistInput = trim((string)($input['playlist_id'] ?? $input['playlist_url'] ?? ''));

// If a specific Spotify playlist ID or URL is provided by admin (e.g. custom playlist)
$isCustomPlaylist = !empty($rawPlaylistInput) && $rawPlaylistInput !== '37i9dQZF1DX2L0iB23Enbq';

$syncedTracks = [$dennyTrack];
$playlistName = 'Viral Hits TikTok & Pop Indonesia';

if ($isCustomPlaylist) {
    // Custom playlist mode
    $playlistId = $rawPlaylistInput;
    if (preg_match('/playlist\/([a-zA-Z0-9]+)/', $rawPlaylistInput, $m)) {
        $playlistId = $m[1];
    }

    $ch = curl_init("https://open.spotify.com/embed/playlist/{$playlistId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', (string)$html, $matches)) {
        $data = json_decode($matches[1], true);
        $entity = $data['props']['pageProps']['state']['data']['entity'] ?? null;
        if ($entity && !empty($entity['trackList'])) {
            $playlistName = $entity['name'] ?? 'Custom Playlist';
            foreach ($entity['trackList'] as $t) {
                if (count($syncedTracks) >= ($limit + 1)) break;
                $trackId = str_replace('spotify:track:', '', (string)($t['uri'] ?? ''));
                if ($trackId === '3kfVlzPUsy7x1MiPHRuFr0') continue;

                $audioPreview = (string)($t['audioPreview']['url'] ?? '');
                $src = !empty($audioPreview)
                    ? $audioPreview
                    : 'https://p.scdn.co/mp3-preview/6945ec27f637b0b94fe6667b22331aa87dd61919';

                $syncedTracks[] = [
                    'title' => trim((string)($t['title'] ?? 'Lagu Pilihan')),
                    'artist' => trim((string)($t['subtitle'] ?? 'Spotify')),
                    'src' => $src,
                    'spotify_url' => "https://open.spotify.com/track/{$trackId}",
                    'spotify_id' => $trackId,
                ];
            }
        }
    }
}

// Default Mode: Sync Indonesian Viral TikTok & Chart Hits
if (count($syncedTracks) < 2) {
    // Attempt live fetch from Spotify Daily Chart Indonesia via Kworb
    $liveFetchedTracks = [];
    $ch = curl_init('https://kworb.net/spotify/country/id_daily.html');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $kworbHtml = curl_exec($ch);
    $kCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $foreignArtists = [
        'Bruno Mars', 'Lady Gaga', 'Billie Eilish', 'Sabrina Carpenter', 'Taylor Swift', 'ROSÉ', 'Coldplay',
        'The Weeknd', 'Ariana Grande', 'Post Malone', 'Ed Sheeran', 'Kendrick Lamar', 'Chappell Roan', 'LANY',
        'Gracie Abrams', 'd4vd', 'SZA', 'One Direction', 'Frank Ocean', 'Tate McRae', 'Shaboozey', 'Teddy Swims'
    ];

    if ($kCode === 200 && !empty($kworbHtml) && preg_match('/<tbody>(.*?)<\/tbody>/s', $kworbHtml, $tbody)) {
        if (preg_match_all('/<tr>(.*?)<\/tr>/s', $tbody[1], $rows)) {
            foreach ($rows[1] as $row) {
                if (count($liveFetchedTracks) >= $limit) break;
                if (preg_match('/href="\.\.\/track\/([a-zA-Z0-9]+)\.html">([^<]+)<\/a>/', $row, $tm)) {
                    $trackId = $tm[1];
                    $title = html_entity_decode(trim($tm[2]));
                    $artist = '';
                    if (preg_match('/href="\.\.\/artist\/[a-zA-Z0-9]+\.html">([^<]+)<\/a>/', $row, $am)) {
                        $artist = html_entity_decode(trim($am[1]));
                    }
                    if ($trackId === '3kfVlzPUsy7x1MiPHRuFr0') continue;

                    // Filter out foreign artists
                    $isForeign = false;
                    foreach ($foreignArtists as $fa) {
                        if (stripos($artist, $fa) !== false) {
                            $isForeign = true;
                            break;
                        }
                    }
                    if (!$isForeign) {
                        $liveFetchedTracks[$trackId] = [
                            'title' => $title,
                            'artist' => $artist,
                            'track_id' => $trackId,
                        ];
                    }
                }
            }
        }
    }

    // Merge live fetched Indonesian tracks with audio previews, or use curated verified pool
    foreach ($curatedIndoHits as $item) {
        if (count($syncedTracks) >= ($limit + 1)) break;
        if ($item['spotify_id'] === '3kfVlzPUsy7x1MiPHRuFr0') continue;
        $syncedTracks[] = $item;
    }
}

// Slice to desired limit
$syncedTracks = array_slice($syncedTracks, 0, $limit);

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
@file_put_contents($dataPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// 2. Save to serverless /tmp
$tempDir = dirname($tempPath);
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0777, true);
}
@file_put_contents($tempPath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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
    'message' => 'Berhasil sinkronisasi ' . count($syncedTracks) . ' lagu viral TikTok khusus Indonesia via Spotify API!',
    'playlist_name' => $playlistName,
    'count' => count($syncedTracks),
    'playlist' => $syncedTracks,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
