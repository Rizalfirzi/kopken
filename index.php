<?php
/**
 * Kedai Kyuti - Kopi Kenangan Live Store (PHP Native)
 * Domain: kedaikyuti.my.id
 * 100% PHP Native, Zero Database, Zero Framework.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/KopiKenanganApi.php';

$api = new KopiKenanganApi();

$storeName = config('store.name', 'Kedai Kyuti');
$domainName = config('store.domain', 'kedaikyuti.my.id');
$tagline = config('store.tagline', 'JASDOR KOPI KENANGAN');
$adminPhone = config('store.admin_phone', '6288298374458');
$socialLinks = config('store.social_links', []);

$defaultOutletCode = isset($_GET['outlet']) ? trim((string)$_GET['outlet']) : config('store.default_outlet', 'LA.CODEFIN');

// Fetch initial outlets
$outletsData = $api->getOutlets();
$initialOutlets = $outletsData['outlets'] ?? [];

// Match requested outlet or fallback to first
$initialOutlet = null;
foreach ($initialOutlets as $outlet) {
    if ($outlet['code'] === $defaultOutletCode) {
        $initialOutlet = $outlet;
        break;
    }
}

if (!$initialOutlet && !empty($initialOutlets)) {
    $initialOutlet = $initialOutlets[0];
    $defaultOutletCode = $initialOutlet['code'];
}

// Fetch initial menu & stock for selected outlet
$initialMenu = $api->getMenu($defaultOutletCode);

// Fetch initial Wi-Fi details for selected outlet
$initialWifi = $api->getWifi($defaultOutletCode);

// Sync initialOutlet premium & closed status with initialMenu
if ($initialOutlet) {
    $initialOutlet['isPremium'] = !empty($initialMenu['premium']);
    $initialOutlet['premium'] = !empty($initialMenu['premium']);

    if (!empty($initialMenu['isClosed'])) {
        $initialOutlet['canOrder'] = false;
        $initialOutlet['isOpen'] = false;
        $initialOutlet['statusText'] = 'Tutup';
    } elseif (isset($initialOutlet['canOrder']) && !$initialOutlet['canOrder']) {
        $initialMenu['isClosed'] = true;
    }
}

// Include Header Template
require_once __DIR__ . '/includes/header.php';
?>

    <!-- ============================================================= -->
    <!-- 1. LANDING BIO / HUB VIEW (Revisi Poin 5)                       -->
    <!-- ============================================================= -->
    <div class="bio-hub-view" id="bioHubView">
        <div class="bio-card">
            <div class="bio-avatar-wrap">
                <?php if (!empty($logoImg)): ?>
                    <img src="<?= htmlspecialchars($logoImg) ?>" alt="<?= htmlspecialchars($storeName) ?>" class="bio-avatar-img">
                <?php else: ?>
                    <div class="bio-avatar-circle">୨୧</div>
                <?php endif; ?>
            </div>
            
            <h2 class="bio-title"><?= htmlspecialchars(config('store.bio_title', '୨୧ Kedai Kyuti ୨୧')) ?></h2>
            <div class="bio-badge"><?= htmlspecialchars(config('store.bio_badge', '🌸 JASDOR KOPI KENANGAN 🌸')) ?></div>
            
            <div class="bio-quote-box">
                <p class="bio-quote"><?= htmlspecialchars(config('store.bio_quote', '♡ your little corner for daily food & beverage deals.')) ?></p>
                <p class="bio-desc"><?= htmlspecialchars(config('store.bio_desc', 'fast response, friendly prices, and service made with love ♡')) ?></p>
            </div>

            <!-- Deretan Icon Media Sosial (Revisi Poin 4: Komunitas & Kontak di Halaman Landing Pertama) -->
            <div class="bio-social-bar">
                <a href="<?= htmlspecialchars($socialLinks['wa'] ?? 'https://wa.me/message/7PJFBJ3DLCINJ1') ?>" target="_blank" rel="noopener" class="bio-social-icon-btn bio-soc-wa" title="WhatsApp Admin">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.9-.9L3 20.5l1.6-4.6a8.3 8.3 0 0 1-1.1-4.4A8.5 8.5 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>
                    <span>WA</span>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['tele_testi'] ?? 'https://t.me/kedaikyuti') ?>" target="_blank" rel="noopener" class="bio-social-icon-btn bio-soc-tele" title="Telegram @kedaikyuti">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    <span>Tele</span>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['x_twitter'] ?? 'https://x.com/lolaloliyo?s=11') ?>" target="_blank" rel="noopener" class="bio-social-icon-btn bio-soc-x" title="X (Twitter) @lolaloliyo">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    <span>X</span>
                </a>
                <a href="<?= htmlspecialchars($socialLinks['wa_channel'] ?? 'https://whatsapp.com/channel/0029VbDgR1s6BIEb4d0JWl26') ?>" target="_blank" rel="noopener" class="bio-social-icon-btn bio-soc-channel" title="Saluran WhatsApp JASDORTII">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span>Saluran</span>
                </a>
            </div>

            <div class="bio-links-grid">
                <!-- 1. Pricelist Button (Main Call to Action) -->
                <button class="bio-link-btn bio-btn-primary" id="openPricelistBtn" type="button">
                    <div class="bl-icon">☕</div>
                    <div class="bl-info">
                        <span class="bl-title">Pricelist Kopi Kenangan</span>
                        <span class="bl-sub">Cek stok real-time & order langsung</span>
                    </div>
                    <div class="bl-arrow">
                        <span class="bl-tag">ORDER NOW</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </button>

                <!-- 2. WhatsApp Admin -->
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['wa'] ?? 'https://wa.me/message/7PJFBJ3DLCINJ1') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#22C55E">💬</div>
                    <div class="bl-info">
                        <span class="bl-title">WhatsApp Admin</span>
                        <span class="bl-sub">Tanya ongkir, delivery & custom request</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <!-- 3. Testi Customer (Telegram) -->
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['tele_testi'] ?? 'https://t.me/kedaikyuti') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#38BDF8">📸</div>
                    <div class="bl-info">
                        <span class="bl-title">Telegram @kedaikyuti</span>
                        <span class="bl-sub">Lihat ribuan bukti order sahabat kyuti</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <!-- 4. X (Twitter) -->
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['x_twitter'] ?? 'https://x.com/lolaloliyo?s=11') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#111">🐦</div>
                    <div class="bl-info">
                        <span class="bl-title">X (Twitter) @lolaloliyo</span>
                        <span class="bl-sub">Update promo harian & giveaway</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <!-- 5. Saluran WhatsApp -->
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['wa_channel'] ?? 'https://whatsapp.com/channel/0029VbDgR1s6BIEb4d0JWl26') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#10B981">📢</div>
                    <div class="bl-info">
                        <span class="bl-title">Saluran WhatsApp JASDORTII</span>
                        <span class="bl-sub">Ikuti saluran JASDORTII di WhatsApp</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>
            </div>

            <div class="bio-footer">
                <span>© <?= date('Y') ?> <?= strtoupper(htmlspecialchars($storeTitle)) ?>. ALL RIGHTS RESERVED.</span>
                <small><?= htmlspecialchars($domainName) ?></small>
                <div style="margin-top:8px">
                    <button type="button" class="open-admin-trigger-link" id="openAdminPricingBtn" title="Kelola Fee &amp; Harga" aria-label="Kelola Fee &amp; Harga" style="background:transparent;border:none;color:var(--text-muted);font-size:14px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:4px;opacity:0.6;transition:var(--transition)">
                        <span>⚙️</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- 2. PRICELIST STORE VIEW (Revisi Poin 6)                         -->
    <!-- ============================================================= -->
    <div class="store-view" id="storeView" style="display:none">
        
        <!-- Back to Hub Navigation Bar -->
        <div class="store-back-bar">
            <button class="btn-back-hub" id="backToHubBtn" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                <span>Menu Utama Kedai Kyuti</span>
            </button>
            <button type="button" class="btn-wifi-nav" id="wifiNavBtn" title="Lihat Password Wi-Fi Outlet">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                <span>Wi-Fi</span>
            </button>
        </div>

        <!-- Tampilan Password Wi-Fi Outlet (Pengganti Tanda Hijau sesuai Revisi Client) -->
        <div class="wifi-banner-card" id="wifiBannerCard">
            <div class="wbc-head">
                <div class="wbc-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                    <span>Password Wi-Fi Outlet</span>
                </div>
                <span class="wbc-date" id="wifiTodayDate"><?= htmlspecialchars($initialWifi['date'] ?? date('l, d F Y')) ?></span>
            </div>
            <div class="wbc-body">
                <div class="wbc-field">
                    <span class="wbc-label">Nama Jaringan (SSID)</span>
                    <strong class="wbc-val" id="wifiSsidVal"><?= htmlspecialchars($initialWifi['wifi']['ssid'] ?? 'Teman Kenangan') ?></strong>
                </div>
                <div class="wbc-pass-field">
                    <div class="wbc-pass-left">
                        <span class="wbc-label">Password Hari Ini</span>
                        <strong class="wbc-pass-val" id="wifiPassVal"><?= htmlspecialchars($initialWifi['wifi']['password'] ?? ('KopiBikinSemangat@' . date('j'))) ?></strong>
                    </div>
                    <button type="button" class="btn-copy-wifi" id="copyWifiBannerBtn" title="Salin Password Wi-Fi">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Salin</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sticky Outlet Picker -->
        <div class="outlet-bar">
            <button class="outlet-card" id="outletCardBtn" type="button" aria-haspopup="dialog">
                <div class="oc-left">
                    <div class="oc-icon-box">☕</div>
                    <div class="oc-info">
                        <div class="oc-eyebrow">Outlet Pesanan Aktif</div>
                        <div class="oc-title" id="currentOutletName"><?= htmlspecialchars($initialOutlet['name'] ?? 'Pilih Outlet') ?></div>
                        <div class="oc-meta">
                            <span class="pill <?= (!empty($initialOutlet['canOrder']) ? 'pill-open' : 'pill-closed') ?>" id="currentOutletStatus">
                                <?= htmlspecialchars($initialOutlet['statusText'] ?? 'Buka') ?>
                            </span>
                            <span id="premiumOutletBadge" class="pill pill-premium" style="<?= !empty($initialOutlet['isPremium']) ? '' : 'display:none;' ?>">Outlet Premium</span>
                            <span id="currentOutletHours" style="color:var(--text-muted)">• <?= htmlspecialchars($initialOutlet['hours'] ?? 'Buka Setiap Hari') ?></span>
                        </div>
                    </div>
                </div>
                <div class="oc-right">
                    <span>Ganti</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </button>
        </div>

        <!-- Sticky Toolbar -->
        <div class="toolbar">
            <div class="search-row">
                <div class="search-box">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7.5"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
                    <input type="search" id="menuSearchInput" placeholder="Cari Kopi Mantan, Matcha, Bundling..." autocomplete="off">
                </div>
                <button class="btn-refresh" id="refreshStockBtn" type="button" title="Refresh Stok Outlet Sekarang">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                    <span>Refresh</span>
                </button>
            </div>

            <!-- Categories with Bundling at First Tab -->
            <div class="cat-bar" id="categoryBar">
                <button class="cat-pill active" data-cat="all">Semua Menu</button>
                <button class="cat-pill" data-cat="Bundling">🎀 Bundling</button>
                <button class="cat-pill" data-cat="Coffee">☕ Coffee</button>
                <button class="cat-pill" data-cat="Non Coffee">🍵 Non-Coffee</button>
                <button class="cat-pill" data-cat="Frappe">🥤 Frappe</button>
                <button class="cat-pill" data-cat="Food">🥪 Food & Toast</button>
            </div>
        </div>



        <!-- Menu Grid Container -->
        <main class="menu-section" id="menuContainer">
            <!-- Rendered dynamically by JavaScript -->
        </main>
    </div>

<?php
// Include Modals & Sheets (Welcome popup, Fee 1pcs alert, Premium outlet alert, Cart, Bundling, Customizer)
require_once __DIR__ . '/includes/modals.php';

// Include Footer & Scripts
require_once __DIR__ . '/includes/footer.php';