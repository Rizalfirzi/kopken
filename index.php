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

// Include Header Template
require_once __DIR__ . '/includes/header.php';
?>

    <!-- ============================================================= -->
    <!-- 1. LANDING BIO / HUB VIEW (Revisi Poin 5)                       -->
    <!-- ============================================================= -->
    <div class="bio-hub-view" id="bioHubView">
        <div class="bio-card">
            <div class="bio-avatar-wrap">
                <div class="bio-avatar-circle">୨୧</div>
            </div>
            
            <h2 class="bio-title"><?= htmlspecialchars(config('store.bio_title', '୨୧ Kedai Kyuti ୨୧')) ?></h2>
            <div class="bio-badge"><?= htmlspecialchars(config('store.bio_badge', '🌸 JASDOR KOPI KENANGAN 🌸')) ?></div>
            
            <div class="bio-quote-box">
                <p class="bio-quote"><?= htmlspecialchars(config('store.bio_quote', '♡ your little corner for daily food & beverage deals.')) ?></p>
                <p class="bio-desc"><?= htmlspecialchars(config('store.bio_desc', 'fast response, friendly prices, and service made with love ♡')) ?></p>
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
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['wa'] ?? 'https://wa.me/' . $adminPhone) ?>" target="_blank" rel="noopener">
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
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['tele_testi'] ?? 'https://t.me/testikedaikyuti') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#38BDF8">📸</div>
                    <div class="bl-info">
                        <span class="bl-title">Testi Customer (Telegram)</span>
                        <span class="bl-sub">Lihat ribuan bukti order sahabat kyuti</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <!-- 4. X (Twitter) -->
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['x_twitter'] ?? 'https://x.com/kedaikyuti') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#111">🐦</div>
                    <div class="bl-info">
                        <span class="bl-title">X (Twitter) @kedaikyuti</span>
                        <span class="bl-sub">Update promo harian & giveaway</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <!-- 5. Saluran WhatsApp -->
                <a class="bio-link-btn" href="<?= htmlspecialchars($socialLinks['wa_channel'] ?? 'https://whatsapp.com/channel/kedaikyuti') ?>" target="_blank" rel="noopener">
                    <div class="bl-icon" style="color:#10B981">📢</div>
                    <div class="bl-info">
                        <span class="bl-title">Saluran WhatsApp</span>
                        <span class="bl-sub">Katalog menu & info promo terbatas</span>
                    </div>
                    <div class="bl-arrow">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>
            </div>

            <div class="bio-footer">
                <span>© <?= date('Y') ?> <?= strtoupper(htmlspecialchars($storeTitle)) ?>. ALL RIGHTS RESERVED.</span>
                <small><?= htmlspecialchars($domainName) ?></small>
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
            <div class="store-back-tag">Diskon 50% + Fee</div>
        </div>

        <!-- Hero Header -->
        <div class="hero-banner">
            <div class="hero-tag">🌸 Diskon 50% + Fee Murah</div>
            <h2 class="hero-title">Pricelist Kopi Kenangan</h2>
            <p class="hero-desc">Pilih outlet terdekat, cek ketersediaan menu secara live, dan order langsung via WhatsApp!</p>
            <div class="hero-stats">
                <div class="stat-item">
                    <b id="statTotalItems"><?= (int)($initialMenu['stats']['total'] ?? 0) ?></b>
                    <small>Total Menu</small>
                </div>
                <div class="stat-item">
                    <b id="statReadyItems" style="color:#22C55E"><?= (int)($initialMenu['stats']['available'] ?? 0) ?></b>
                    <small>Stok Ready</small>
                </div>
                <div class="stat-item">
                    <b id="statSoldItems" style="color:#F43F5E"><?= (int)($initialMenu['stats']['soldOut'] ?? 0) ?></b>
                    <small>Habis</small>
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

        <!-- Live Stock Indicator Strip -->
        <div class="stock-live-strip">
            <div>
                <span>Status Data: </span>
                <b id="liveSourceText"><?= (!empty($initialMenu['live']) ? 'Stok Live dari Outlet Terpilih' : 'Mode Offline / Fallback') ?></b>
            </div>
            <div id="lastSyncTime" style="font-size:10px">Baru saja diperbarui</div>
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