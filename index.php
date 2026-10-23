<?php
/**
 * Kopi Kenangan - Real-time Outlet & Stock Application (PHP Native)
 * 
 * Runs without Laravel, Composer, or Node.js dependencies.
 * Compatible with PHP 7.4+, 8.0, 8.1, 8.2, 8.3, Apache, Nginx, XAMPP, and Laragon.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/KopiKenanganApi.php';

$api = new KopiKenanganApi();

$storeName = config('store.name', 'Kopi Kenangan Live Store');
$adminPhone = config('store.admin_phone', '6288298374458');
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

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-tag">✨ Live Inventory Sync</div>
        <h2 class="hero-title">Kenangan Hari Ini, Langsung dari Outlet</h2>
        <p class="hero-desc">Pilih outlet Kopi Kenangan terdekat untuk mengecek stok ketersediaan menu secara live dan order langsung.</p>
        <div class="hero-stats">
            <div class="stat-item">
                <b id="statTotalItems"><?= (int)($initialMenu['stats']['total'] ?? 0) ?></b>
                <small>Total Menu</small>
            </div>
            <div class="stat-item">
                <b id="statReadyItems" style="color:#86EFAC"><?= (int)($initialMenu['stats']['available'] ?? 0) ?></b>
                <small>Stok Ready</small>
            </div>
            <div class="stat-item">
                <b id="statSoldItems" style="color:#FCA5A5"><?= (int)($initialMenu['stats']['soldOut'] ?? 0) ?></b>
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
                        <?php if (!empty($initialMenu['premium'])): ?>
                            <span class="pill pill-premium">Outlet Premium</span>
                        <?php endif; ?>
                        <span id="currentOutletHours" style="color:var(--text-muted)">• <?= htmlspecialchars($initialOutlet['hours'] ?? 'Buka Setiap Hari') ?></span>
                    </div>
                </div>
            </div>
            <div class="oc-right">
                <span>Ganti</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
        </button>
    </div>

    <!-- Sticky Toolbar -->
    <div class="toolbar">
        <div class="search-row">
            <div class="search-box">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
                <input type="search" id="menuSearchInput" placeholder="Cari Kopi Mantan, Matcha, Toast..." autocomplete="off">
            </div>
            <button class="btn-refresh" id="refreshStockBtn" type="button" title="Refresh Stok Outlet Sekarang">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                <span>Live Refresh</span>
            </button>
        </div>

        <div class="cat-bar" id="categoryBar">
            <button class="cat-pill active" data-cat="all">Semua Menu</button>
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

    <!-- Menu List -->
    <main class="menu-section" id="menuContainer">
        <!-- Rendered dynamically by JavaScript with initial server-side fallback -->
        <noscript>
            <div class="menu-grid">
                <?php foreach (($initialMenu['items'] ?? []) as $item): ?>
                    <div class="product-card">
                        <div class="product-img-wrap">
                            <img src="<?= htmlspecialchars($item['img'] ?? '') ?>" alt="<?= htmlspecialchars($item['name'] ?? '') ?>">
                        </div>
                        <h4 class="product-name"><?= htmlspecialchars($item['name'] ?? '') ?></h4>
                        <div class="product-cat"><?= htmlspecialchars($item['category'] ?? '') ?></div>
                        <div class="product-price-row">
                            <span class="product-price">Rp <?= number_format($item['price'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </noscript>
    </main>

<?php
// Include Modals & Sheets
require_once __DIR__ . '/includes/modals.php';

// Include Footer & Scripts
require_once __DIR__ . '/includes/footer.php';
