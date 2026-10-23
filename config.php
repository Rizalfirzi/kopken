<?php
/**
 * Kopi Kenangan - Central Application Configuration (PHP Native)
 * 
 * Pengaturan Lengkap:
 * 1. Jam Operasional Outlet (Otomatis hitung waktu WIB & Manual Override)
 * 2. Ketersediaan Stok Real-Time (Live Sync API & Manual Stock Override)
 * 3. Desain Tampilan (Warna, Logo Gambar/Teks, Judul/Banner Retext)
 * 4. Harga Menu (Markup Global & Ubah Harga per Item)
 * 5. Penambahan Menu Baru Custom
 */

// Set Timezone ke Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

define('ROOT_PATH', __DIR__);

// Cache directory (menggunakan system temp directory untuk kompatibilitas Vercel Serverless)
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kopken_cache';
define('CACHE_PATH', $cacheDir);
if (!is_dir(CACHE_PATH)) {
    @mkdir(CACHE_PATH, 0777, true);
}

$config = [
    'app_name' => 'Kopi Kenangan Live Store',
    'app_env' => 'production',

    // =========================================================================
    // 1. BRANDING, LOGO & RETEXT (Point 3: Ubah Teks, Judul & Logo)
    // =========================================================================
    'store' => [
        'name' => 'Kopi Kenangan Live Store',
        'tagline' => 'Cek Outlet Terdekat & Stok Menu Real-Time',
        
        // LOGO:
        // - 'logo_text': Teks inisial jika tidak pakai gambar (contoh: 'KK')
        // - 'logo_image': URL gambar logo Anda (contoh: 'https://domain.com/logo.png'). Isi null jika pakai logo_text
        'logo_text' => 'KK',
        'logo_image' => null, 

        // RETEXT HERO BANNER
        'hero_tag' => '✨ Live Inventory Sync',
        'hero_title' => 'Kenangan Hari Ini, Langsung dari Outlet',
        'hero_desc' => 'Pilih outlet Kopi Kenangan terdekat untuk mengecek stok ketersediaan menu secara live dan order langsung via WhatsApp.',

        // KONTAK ORDER WHATSAPP (Format 62xxx tanpa tanda plus)
        'admin_phone' => '6288298374458',
        'order_prefix' => 'KK',
        'default_outlet' => 'LA.CODEFIN',
    ],

    // =========================================================================
    // 2. DESIGN & WARNA TEMA (Point 3: Kustomisasi Warna Tampilan)
    // =========================================================================
    'theme' => [
        // Warna Utama (Brand Color - Default: Merah Burgundy khas Kopi Kenangan)
        'primary_color' => '#741213',
        'primary_dark' => '#530B0C',
        'primary_light' => '#9B1D1F',
        
        // Warna Aksen & Badge
        'accent_color' => '#E11D48',
        'gold_color' => '#D97706',
        'gold_light' => '#FDE68A',
        
        // Warna Background Halaman (Light Mode)
        'bg_page' => '#FBF8F3',
        'bg_card' => '#FFFFFF',
        'bg_card_subtle' => '#F6F2EC',
    ],

    // =========================================================================
    // 3. PENGATURAN JAM OPERASIONAL OUTLET (Point 1)
    // =========================================================================
    'outlets' => [
        // Auto Time Check:
        // Jika true, aplikasi secara cerdas membaca jam operasional outlet (contoh 06:00 - 22:00)
        // dan mencocokkan dengan jam saat ini (WIB). Jika di luar jam operasional, status
        // otomatis menjadi "Tutup" dan label berganti menjadi merah.
        'auto_time_check' => true,

        // Manual Hours Override:
        // Anda bisa mengubah / menyesuaikan jam buka-tutup outlet tertentu di sini jika jam di API berbeda.
        // Format: 'KODE_OUTLET' => 'JAM_BUKA - JAM_TUTUP'
        'hours_override' => [
            // Contoh:
            // 'LA.CODEFIN' => '07:00 - 22:00',
            // 'KK.SBY.RKABNGAN' => '06:00 - 23:00',
        ],

        // Manual Force Status:
        // Paksa buka/tutup outlet tertentu secara manual jika sedang libur atau renovasi
        // Format: 'KODE_OUTLET' => true (selalu buka) atau false (tutup sementara)
        'force_status' => [
            // 'LA.CODEFIN' => true,
        ],
    ],

    // =========================================================================
    // 4. PENGATURAN STOK MENU (Point 2: Stok Real-Time & Manual Override)
    // =========================================================================
    'stock' => [
        // Cache refresh rate (dalam detik). 60 detik memastikan stok dari aplikasi Kopi Kenangan selalu up-to-date
        'ttl_seconds' => 60,

        // Manual Stock Override:
        // Anda bisa memaksa status ketersediaan menu tertentu:
        // Format: 'key_menu' => true (Habis) / false (Tersedia)
        'overrides' => [
            // 'kopken_mantan' => false,      // false = Selalu Tersedia
            // 'toast_choco_cheese' => true,  // true = Paksa Habis
        ],
    ],

    // =========================================================================
    // 5. PENGATURAN HARGA & PENAMBAHAN MENU BARU (Point 3)
    // =========================================================================
    'menu' => [
        // Kenaikan Harga Global (Contoh: isi 2000 untuk menaikkan harga semua menu Rp 2.000, atau 0 untuk harga asli)
        'global_price_markup' => 0,

        // Ubah Harga Satuan per Menu (Price Override)
        // Format: 'key_menu' => harga_baru (angka tanpa tanda titik)
        'price_overrides' => [
            // 'kopken_mantan' => 15000,      // Ubah harga Kopi Mantan jadi Rp 15.000
            // 'creamy_aren_latte' => 17000,  // Ubah harga Aren Latte jadi Rp 17.000
        ],

        // TAMBAH MENU BARU CUSTOM:
        // Anda bisa menambahkan menu baru di sini. Menu baru ini akan otomatis muncul
        // di kategori yang sesuai, bisa dicari di search bar, bisa dikustomisasi (es/gula),
        // bisa masuk keranjang, dan tercetak di nota WhatsApp!
        'custom_items' => [
            [
                'key' => 'custom_matcha_espresso',
                'name' => 'Matcha Espresso Fusion',
                'category' => 'Coffee', // Pilihan: Coffee, Non Coffee, Frappe, Food
                'price' => 22000,
                'normalPrice' => 26000,
                'desc' => 'Kombinasi unik matcha Jepang murni dengan espresso arabika pilihan dan susu segar creamy legit.',
                'img' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?w=400',
                'isSoldOut' => false, // Ubah ke true jika stok menu ini sedang habis
                'tag' => 'MENU BARU',
                'options' => [
                    'sizes' => ['Regular', 'Large'],
                    'temps' => ['Ice', 'Hot'],
                    'ices' => ['Normal Ice', 'Less Ice'],
                    'sugars' => ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'],
                ],
            ],
            [
                'key' => 'custom_croissant_butter',
                'name' => 'Artisan Butter Croissant',
                'category' => 'Food',
                'price' => 14000,
                'normalPrice' => 18000,
                'desc' => 'Croissant panggang gurih berlapis mentega Prancis harum. Teman sempurna untuk secangkir kopi.',
                'img' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=400',
                'isSoldOut' => false,
                'tag' => 'FAVORIT',
                'options' => [],
            ],
        ],
    ],

    // External Live API Endpoint
    'api_base_url' => 'https://tixandcoffee.developerngantuk.my.id/api',
    'api_timeout' => 8,
    'cache_ttl' => [
        'outlets' => 1800,
        'menu' => 60,
    ],
];

/**
 * Helper function to retrieve configuration values using dot notation
 *
 * @param string $key e.g. 'store.admin_phone'
 * @param mixed $default
 * @return mixed
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