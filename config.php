<?php
/**
 * Kedai Kyuti - Central Application Configuration (PHP Native)
 * Domain: kedaikyuti.my.id
 * Brand: Kedai Kyuti (JASDOR KOPI KENANGAN)
 * 
 * Pengaturan Lengkap:
 * 1. Branding, Nama Web, Logo & Bio Links
 * 2. Warna Tema Pink Pastel / Rose Chic
 * 3. Jam Operasional Outlet (Otomatis WIB & Manual Override)
 * 4. Ketersediaan Stok Real-Time
 * 5. Formula Harga: (Harga Asli - 50% + Fee)
 *    - Fee default (+5.000)
 *    - Fee per kategori (misal: Food +4.500, Minuman +5.000)
 *    - Fee khusus per menu (misal: Kopi Kenangan Mantan +6.000)
 * 6. Fee Pesanan Satuan 1 pcs (+2.000) & Kantung Belanja Plastik (+1.000)
 * 7. Paket Menu Bundling & Menu Custom
 */

// Set Timezone ke Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

define('ROOT_PATH', __DIR__);

// Cache directory
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache';
define('CACHE_PATH', $cacheDir);
if (!is_dir(CACHE_PATH)) {
    @mkdir(CACHE_PATH, 0777, true);
}

$config = [
    'app_name' => 'Kedai Kyuti',
    'app_domain' => 'kedaikyuti.my.id',
    'app_env' => 'production',

    // =========================================================================
    // 1. BRANDING, NAMA WEB & BIO PROFILE (Revisi Poin 1, 2, 5)
    // =========================================================================
    'store' => [
        'name' => 'Kedai Kyuti',
        'domain' => 'kedaikyuti.my.id',
        'tagline' => 'JASDOR KOPI KENANGAN',
        
        // Teks Bio Header (Landing Hub View)
        'bio_title' => '୨୧ Kedai Kyuti ୨୧',
        'bio_badge' => '🌸 JASDOR KOPI KENANGAN 🌸',
        'bio_quote' => '♡ your little corner for daily food & beverage deals.',
        'bio_desc' => 'fast response, friendly prices, and service made with love ♡',

        // LOGO:
        'logo_text' => '୨୧',
        'logo_image' => null, 

        // HERO BANNER DI HALAMAN MENU
        'hero_tag' => '✨ Live Inventory Sync',
        'hero_title' => 'Pricelist Kopi Kenangan',
        'hero_desc' => 'Pilih outlet terdekat untuk cek stok menu real-time dan pesan langsung via WhatsApp dengan harga hemat!',

        // KONTAK ORDER WHATSAPP & SOSIAL MEDIA (Poin 5)
        'admin_phone' => '08972276154',
        'order_prefix' => 'KYUTI',
        'default_outlet' => 'LA.CODEFIN',

        'social_links' => [
            'wa' => 'https://wa.me/628972276154',
            'tele_testi' => 'https://t.me/kedaikyuti',
            'x_twitter' => 'https://x.com/lolaloliyo?s=11',
            'wa_channel' => 'https://whatsapp.com/channel/0029VbDgR1s6BIEb4d0JWl26',
        ],
    ],

    // =========================================================================
    // 2. DESIGN & WARNA TEMA PINK PASTEL (Revisi Poin 3)
    // =========================================================================
    'theme' => [
        // Warna Utama: Pink Pastel Aesthetic & Rose Feminine
        'primary_color' => '#F472B6',      // Pink Pastel Elegan
        'primary_dark' => '#DB2777',       // Rose Pink Deep untuk teks/tombol
        'primary_light' => '#FDF2F4',      // Soft Pink Tint
        
        // Warna Aksen & Badge
        'accent_color' => '#FB7185',       // Rose Accent
        'gold_color' => '#F59E0B',
        'gold_light' => '#FEF3C7',
        
        // Warna Background Halaman (Light Mode)
        'bg_page' => '#FFF5F7',            // Pastel Pink Soft Creamy
        'bg_card' => '#FFFFFF',            // Kartu Putih Bersih
        'bg_card_subtle' => '#FCE7F3',     // Kartu Aksen Pink Halus
        'border_color' => '#FBCFE8',       // Garis Border Pastel
    ],

    // =========================================================================
    // 3. PENGATURAN BIAYA TAMBAHAN (Revisi Poin 8 & 10)
    // =========================================================================
    'fees' => [
        // Fee jika pesanan hanya 1 pcs / satuan (Poin 8: +2.000)
        'single_item_fee' => 2000,

        // Pilihan kantung belanja plastik (Poin 10: +1.000)
        'plastic_bag_fee' => 1000,
    ],

    // =========================================================================
    // 4. PENGATURAN HARGA & FEE FORMULA (Revisi Poin 7)
    // Formula: (Harga Asli Kopken - 50% + Fee)
    // =========================================================================
    'pricing' => [
        // Diskon persen dari harga asli outlet (default: 50%)
        'discount_percent' => 50,

        // Fee default per item jika tidak diatur di kategori/menu (contoh: +5.000)
        'default_markup' => 5000,

        // Fee per Kategori (Contoh: Makanan/Food +4.500, Minuman +5.000)
        'category_markups' => [
            'Coffee' => 5000,
            'Non Coffee' => 5000,
            'Frappe' => 5000,
            'Food' => 4500,
            'Minuman' => 5000,
            'Makanan' => 4500,
            'Bundling' => 0,
        ],

        // Fee khusus per Menu (Contoh: Kopi Kenangan Mantan +6.000)
        // Format: 'Nama Menu' atau 'key_menu' => fee_tambahan
        'item_markup_overrides' => [
            'Kopi Kenangan Mantan' => 6000,  // Kopi Kenangan Mantan +6.000
            'kopikenanganmantan' => 6000,
        ],

        // Override Harga Tetap Manual (jika ada menu yang ingin dipatok harga fix)
        'price_overrides' => [
            // 'Kopi Kenangan Mantan' => 15000,
        ],
    ],

    // =========================================================================
    // 5. MENU BUNDLING HEMAT (Revisi Poin 12)
    // =========================================================================
    'bundling_items' => [
        [
            'key' => 'bundle_duo_bestie',
            'name' => 'Bundling Bestie Duo (2 Minuman)',
            'category' => 'Bundling',
            'price' => 26000,
            'normalPrice' => 38000,
            'desc' => 'Paket hemat 2 cup minuman reguler favorit untuk berdua! Bebas pilih varian minuman.',
            'img' => 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=400',
            'tag' => 'BEST SELLER BUNDLE',
            'isSoldOut' => false,
            'slots' => [
                [
                    'label' => 'Minuman 1 (Reguler)',
                    'options' => [
                        'Kopi Kenangan Mantan',
                        'Creamy Aren Latte',
                        'Spanish Latte',
                        'Americano',
                        'Taro Cloud Milk Tea',
                        'Kenangan Milk Tea'
                    ]
                ],
                [
                    'label' => 'Minuman 2 (Reguler)',
                    'options' => [
                        'Kopi Kenangan Mantan',
                        'Creamy Aren Latte',
                        'Spanish Latte',
                        'Americano',
                        'Taro Cloud Milk Tea',
                        'Kenangan Milk Tea'
                    ]
                ],
            ],
            'options' => [
                'ices' => ['Normal Ice', 'Less Ice'],
                'sugars' => ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'],
            ]
        ],
        [
            'key' => 'bundle_trio_kenangan',
            'name' => 'Bundling Trio Kenangan (3 Minuman)',
            'category' => 'Bundling',
            'price' => 39000,
            'normalPrice' => 57000,
            'desc' => 'Paket 3 cup minuman reguler terlaris. Cocok untuk teman nongkrong atau traktiran bestie!',
            'img' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=400',
            'tag' => 'HEMAT BER-3',
            'isSoldOut' => false,
            'slots' => [
                [
                    'label' => 'Minuman 1 (Reguler)',
                    'options' => ['Kopi Kenangan Mantan', 'Creamy Aren Latte', 'Spanish Latte', 'Taro Cloud Milk Tea']
                ],
                [
                    'label' => 'Minuman 2 (Reguler)',
                    'options' => ['Kopi Kenangan Mantan', 'Creamy Aren Latte', 'Spanish Latte', 'Taro Cloud Milk Tea']
                ],
                [
                    'label' => 'Minuman 3 (Reguler)',
                    'options' => ['Kopi Kenangan Mantan', 'Creamy Aren Latte', 'Spanish Latte', 'Taro Cloud Milk Tea']
                ],
            ],
            'options' => [
                'ices' => ['Normal Ice', 'Less Ice'],
                'sugars' => ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'],
            ]
        ],
        [
            'key' => 'bundle_coffee_toast',
            'name' => 'Bundling Coffee & Toast Combo',
            'category' => 'Bundling',
            'price' => 22000,
            'normalPrice' => 32000,
            'desc' => 'Kombinasi 1 Kopi Kenangan Mantan (Ice/Hot) + 1 Roti Coklat Klasik atau Toast hangat gurih.',
            'img' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400',
            'tag' => 'COMBO SARAPAN',
            'isSoldOut' => false,
            'slots' => [
                [
                    'label' => 'Pilihan Minuman',
                    'options' => ['Kopi Kenangan Mantan', 'Creamy Aren Latte', 'Americano']
                ],
                [
                    'label' => 'Pilihan Roti / Snack',
                    'options' => ['Roti Coklat Klasik', 'Roti Keju Manis', 'Choco Chip Cookies', 'Donut Almond']
                ],
            ],
            'options' => [
                'ices' => ['Normal Ice', 'Less Ice'],
                'sugars' => ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'],
            ]
        ],
    ],

    // =========================================================================
    // 5B. ADDONS OPSIONAL (TOPPING & SYRUP - Boleh Lebih Dari Satu)
    // =========================================================================
    'addons' => [
        [
            'id' => 'topping',
            'name' => 'Topping',
            'hint' => 'opsional, boleh lebih dari satu',
            'values' => [
                ['id' => 'boba', 'text' => 'Boba', 'price' => 4000],
                ['id' => 'grass_jelly', 'text' => 'Grass Jelly', 'price' => 4000],
                ['id' => 'coffee_jelly', 'text' => 'Coffee Jelly', 'price' => 4000],
                ['id' => 'coconut_jelly', 'text' => 'Coconut Jelly', 'price' => 4000],
                ['id' => 'extra_shot', 'text' => 'Extra Espresso Shot', 'price' => 5000],
                ['id' => 'oat_milk', 'text' => 'Oat Milk (Substitusi)', 'price' => 6000],
            ],
        ],
        [
            'id' => 'syrup',
            'name' => 'Syrup',
            'hint' => 'opsional, boleh lebih dari satu',
            'values' => [
                ['id' => 'caramel', 'text' => 'Caramel Syrup', 'price' => 5000],
                ['id' => 'vanilla', 'text' => 'Vanilla Syrup', 'price' => 5000],
                ['id' => 'hazelnut', 'text' => 'Hazelnut Syrup', 'price' => 5000],
                ['id' => 'aren', 'text' => 'Gula Aren Extra', 'price' => 3000],
            ],
        ],
    ],

    // =========================================================================
    // 6. JAM OPERASIONAL & DAFTAR OUTLET PREMIUM
    // =========================================================================
    'outlets' => [
        'auto_time_check' => true,
        'hours_override' => [],
        'force_status' => [],

        // Daftar Outlet Premium yang dikonfirmasi Klien
        'premium_list' => [
            'buaran serpong',
            'bintaro sektor 9',
            'galuh mas karawang',
            'gandaria kramat pela',
            'matraman',
            'sopo del',
            'kota baru jogja',
            'jemursari',
            'ahmad yani surabaya',
            'cikajang',
            'sahid sudirman',
            'sahid sudirmam',
            'ahmad muksin tenggarong',
            'danau semayang',
            'ruko mangga berau',
            'pecenongan',
            'rawa belong',
            'summarecon bogor',
            'keprabon',
            'summarecon digital center',
            'cihampelas walk',
            'pim 2',
            'pim 3',
            'teras kota',
        ],

        // Kode Outlet Premium terdaftar
        'premium_codes' => [
            'KK.KWG.GLHMAS',       // Galuh Mas Karawang
            'KK.TNG.RKBARNSRPG',   // Buaran Serpong
            'KK.TNG.BNTROSKTR9',   // Bintaro Sektor 9
            'KK.JKT.GNDRKRMTPL',   // Gandaria Kramat Pela
            'KK.JKT.RKMTRMNJT',    // Ruko Matraman Jakarta Timur
            'SOPODEL',             // Sopo Del Tower
            'KK.YYK.BNTRBDYA',     // Kota Baru Jogja
            'KK.SBY.JMSRI',        // Jemursari Surabaya
            'SBY.AHMDYNMJO',       // Ahmad Yani Surabaya
            'KK.JKT.CKJNG',        // Cikajang
            'SAHID',               // Sahid Sudirman
            'KK.TRG.RKAMDMKSIN',   // Ruko Ahmad Muksin Tenggarong
            'DOCOKK.TRG.DNUSMYNG', // Danau Semayang Tenggarong
            'KK.TNR.RKMNGABRUKT',  // Ruko Mangga Berau
            'KK.JKT.RKPCNGAN',     // Ruko Pecenongan Jakarta Pusat
            'KK.JKT.RWBLNGJKT',    // Rawa Belong Jakarta
            'KK.BGR.SMBGR',        // Summarecon Bogor
            'KK.SKT.KPRABN',       // Keprabon Surakarta
            'TGR.SDCSERPONG',      // Summarecon Digital Center (SDC Serpong)
            'BDG.CHMPLSWK',        // Cihampelas Walk
            'JKT.CFPIM2',          // PIM 2
            'JKT.FCPIM3',          // Food Court PIM 3
            'TGR.TERASKOTA',       // Teras Kota
        ],
    ],

    // =========================================================================
    // 7. PENGATURAN STOK MENU
    // =========================================================================
    'stock' => [
        'ttl_seconds' => 60,
        'overrides' => [],
    ],

    // External Live API Endpoint
    'api_base_url' => 'https://tixandcoffee.developerngantuk.my.id/api',
    'api_timeout' => 8,
    'cache_ttl' => [
        'outlets' => 1800,
        'menu' => 60,
    ],

    // =========================================================================
    // 8. AKSES ADMIN / REDEEM CODE
    // =========================================================================
    'admin' => [
        'passcode' => 'WHOSUTII26', // Redeem Code / Passcode untuk akses Admin Fee di website
    ],

    // =========================================================================
    // 9. DATABASE CLOUD UNTUK VERCEL (100% FREE TIER)
    // =========================================================================
    // Mendukung Vercel KV / Upstash Redis REST API.
    // Otomatis membaca Environment Variables di Vercel:
    // - KV_REST_API_URL / UPSTASH_REDIS_REST_URL
    // - KV_REST_API_TOKEN / UPSTASH_REDIS_REST_TOKEN
    'database' => [
        'kv_url' => getenv('KV_REST_API_URL') ?: getenv('UPSTASH_REDIS_REST_URL') ?: '',
        'kv_token' => getenv('KV_REST_API_TOKEN') ?: getenv('UPSTASH_REDIS_REST_TOKEN') ?: '',
    ],
];

// =========================================================================
// MERGE DYNAMIC PRICING DARI ADMIN PANEL (Vercel KV Cloud, data/pricing_settings.json, /tmp cache, & Cookie fallback)
// =========================================================================
// 1. Check Cloud Database (Vercel KV / Upstash Redis via SimpleRedis: Socket & REST)
require_once __DIR__ . '/includes/SimpleRedis.php';
$simpleRedis = new SimpleRedis();

$dynamicData = null;
if ($simpleRedis->isConnected()) {
    $tempKvCache = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'kv_pricing_cache.json';
    if (file_exists($tempKvCache) && (time() - filemtime($tempKvCache)) < 5) {
        $dynamicData = json_decode(@file_get_contents($tempKvCache), true);
    } else {
        $cachedVal = $simpleRedis->get('pricing_settings');
        if (!empty($cachedVal)) {
            $parsedRedis = is_string($cachedVal) ? json_decode($cachedVal, true) : $cachedVal;
            if (is_array($parsedRedis)) {
                $dynamicData = $parsedRedis;
                $tempDir = dirname($tempKvCache);
                if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
                @file_put_contents($tempKvCache, json_encode($dynamicData));
            }
        }
    }
}

// 2. Fallback to Local Files (/tmp cache on Vercel or data/pricing_settings.json)
if ($dynamicData === null) {
    $dynamicPricingCandidates = [
        (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'pricing_settings.json',
        __DIR__ . '/data/pricing_settings.json',
    ];

    foreach ($dynamicPricingCandidates as $dynFile) {
        if (file_exists($dynFile)) {
            $parsed = json_decode(@file_get_contents($dynFile), true);
            if (is_array($parsed)) {
                $dynamicData = $parsed;
                break;
            }
        }
    }
}

// Fallback to Cookie if present (ensures seamless instant sync on serverless platforms like Vercel)
if ($dynamicData === null && !empty($_COOKIE['kyuti_custom_pricing'])) {
    $rawCookie = $_COOKIE['kyuti_custom_pricing'];
    $parsedCookie = json_decode(urldecode($rawCookie), true);
    if (!is_array($parsedCookie)) {
        $parsedCookie = json_decode($rawCookie, true);
    }
    if (is_array($parsedCookie)) {
        $dynamicData = $parsedCookie;
    }
}

if (is_array($dynamicData)) {
    if (isset($dynamicData['default_markup'])) {
        $config['pricing']['default_markup'] = max(0, (float)$dynamicData['default_markup']);
    }
    if (isset($dynamicData['category_markups']) && is_array($dynamicData['category_markups'])) {
        $config['pricing']['category_markups'] = $dynamicData['category_markups'];
    }
    // CRITICAL BUGFIX: Direct assignment, NOT array_merge!
    // When an admin deletes an item override, $dynamicData['item_markup_overrides'] is [] (empty).
    // array_merge with the default array would wrongly restore the deleted item override.
    if (isset($dynamicData['item_markup_overrides']) && is_array($dynamicData['item_markup_overrides'])) {
        $config['pricing']['item_markup_overrides'] = $dynamicData['item_markup_overrides'];
    }
    if (isset($dynamicData['single_item_fee'])) {
        $config['fees']['single_item_fee'] = max(0, (float)$dynamicData['single_item_fee']);
    }
    if (isset($dynamicData['plastic_bag_fee'])) {
        $config['fees']['plastic_bag_fee'] = max(0, (float)$dynamicData['plastic_bag_fee']);
    }
    if (!empty($dynamicData['admin_passcode'])) {
        $config['admin']['passcode'] = trim((string)$dynamicData['admin_passcode']);
    }
}

/**
 * Helper function to retrieve configuration values using dot notation
 */
function config(string $key, $default = null)
{
    global $config;

    $segments = explode('.', $key);
    $current = $config;

    foreach ($segments as $segment) {
        if (!is_array($current) || !array_key_exists($segment, $current)) {
            return $default;
        }
        $current = $current[$segment];
    }

    return $current;
}