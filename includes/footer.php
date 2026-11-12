
    <!-- Floating Cart Bar -->
    <div class="floating-cart" id="floatingCart">
        <div class="fc-left">
            <div class="fc-badge" id="cartTotalQty">0</div>
            <div class="fc-details">
                <b id="cartBarTitle">0 Item di Keranjang</b>
                <small id="cartBarOutletName"><?= htmlspecialchars($initialOutlet['name'] ?? 'Kedai Kyuti') ?></small>
            </div>
        </div>
        <div class="fc-right">
            <span id="cartBarTotalPrice">Rp 0</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><polyline points="9 18 15 12 9 6"/></svg>
        </div>
    </div>

    <!-- Floating BGM Player Widget (Fitur Lagu Café) -->
    <div class="floating-bgm-player" id="floatingBgmPlayer">
        <!-- Floating Disc Button -->
        <button type="button" class="bgm-disc-btn" id="bgmDiscBtn" title="Putar Musik Café Kyuti" aria-label="Putar Musik Café Kyuti">
            <div class="bgm-disc" id="bgmDisc">
                <div class="bgm-disc-core">☕</div>
            </div>
            <div class="bgm-wave" id="bgmWave">
                <span></span><span></span><span></span>
            </div>
        </button>

        <!-- Mini Pop-up Controls Drawer -->
        <div class="bgm-popup" id="bgmPopup" style="display:none">
            <div class="bgm-popup-head">
                <div class="bgm-track-info">
                    <span class="bgm-badge">🌸 BGM CAFÉ</span>
                    <strong class="bgm-title" id="bgmCurrentTitle">Kedai Kyuti Lo-Fi Vibes</strong>
                    <small class="bgm-artist" id="bgmCurrentArtist">Chill Lofi Beats ♫</small>
                </div>
                <button type="button" class="bgm-close-mini-btn" id="bgmClosePopupBtn" title="Tutup Menu Musik">✕</button>
            </div>
            <div class="bgm-popup-ctrls">
                <button type="button" class="bgm-ctrl-btn" id="bgmPrevBtn" title="Lagu Sebelumnya">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2.6"/></svg>
                </button>
                <button type="button" class="bgm-ctrl-btn bgm-play-btn" id="bgmPlayToggleBtn" title="Putar / Jeda">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" id="bgmPlayIcon"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </button>
                <button type="button" class="bgm-ctrl-btn" id="bgmNextBtn" title="Lagu Selanjutnya">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2.6"/></svg>
                </button>
                <button type="button" class="bgm-ctrl-btn" id="bgmMuteBtn" title="Mute / Unmute">
                    <span id="bgmMuteIcon" style="font-size:14px">🔊</span>
                </button>
            </div>
            <div style="margin-top:8px;text-align:center">
                <span style="font-size:10px;font-weight:700;color:var(--brand-primary-dark);background:var(--brand-primary-light);padding:3px 10px;border-radius:20px;display:inline-block">
                    ♫ Pemutaran Otomatis Versi Full / Lirik
                </span>
            </div>
        </div>
    </div>

    <!-- Hidden Native Audio Tag -->
    <audio id="bgmAudio" preload="none"></audio>

    <!-- Hidden YouTube Background Audio Player Container (Full Playback Engine) -->
    <div id="bgmYtContainer" style="position:fixed;bottom:0;right:0;width:1px;height:1px;opacity:0.01;pointer-events:none;z-index:-1;overflow:hidden">
        <div id="bgmYtPlayer"></div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- Pass Server-side Initial Data to Native JavaScript Engine -->
<script>
window.INITIAL_DATA = {
    outlet: <?= json_encode($initialOutlet ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    outlets: <?= json_encode($initialOutlets ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    menu: <?= json_encode($initialMenu ?? ['items' => [], 'stats' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    storeName: <?= json_encode($storeName ?? 'Kedai Kyuti') ?>,
    adminPhone: <?= json_encode(config('store.admin_phone', '628972276154')) ?>,
    orderPrefix: <?= json_encode(config('store.order_prefix', 'KYUTI')) ?>,
    waBaseLink: <?= json_encode(config('store.social_links.wa', 'https://wa.me/628972276154')) ?>,
    singleItemFee: <?= (int) config('fees.single_item_fee', 0) ?>,
    plasticBagFee: <?= (int) config('fees.plastic_bag_fee', 1000) ?>,
    bundlingItems: <?= json_encode(config('bundling_items', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    premiumList: <?= json_encode(config('outlets.premium_list', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    premiumCodes: <?= json_encode(config('outlets.premium_codes', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    pricing: {
        defaultMarkup: <?= (float) config('pricing.default_markup', 5000) ?>,
        categoryMarkups: <?= json_encode(config('pricing.category_markups', []), JSON_UNESCAPED_UNICODE) ?>,
        itemMarkupOverrides: <?= json_encode(config('pricing.item_markup_overrides', []), JSON_UNESCAPED_UNICODE) ?>,
        bundlingStatus: <?= json_encode(config('pricing.bundling_status', [
            'bundle_2_cup_large' => true,
            'bundle_2_frappe' => true,
            'promo_minuman_special' => true,
            'bundle_hemat_3_item' => true,
        ]), JSON_UNESCAPED_UNICODE) ?>,
        disabledBundlingOptions: <?= json_encode(config('pricing.disabled_bundling_options', []), JSON_UNESCAPED_UNICODE) ?>,
    },
    addons: <?= json_encode(config('addons', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    wifi: <?= json_encode($initialWifi ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    webHours: <?= json_encode(getWebHoursState(), JSON_UNESCAPED_UNICODE) ?>,
    bgm: <?= json_encode(config('bgm', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    reviewsUrl: 'api/reviews.php'
};
</script>

<!-- YouTube Iframe API for Full Background Music Playback -->
<script src="https://www.youtube.com/iframe_api"></script>

<!-- Native App Script with Cache Buster -->
<?php $jsVer = file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : time(); ?>
<script src="assets/js/app.js?v=<?= $jsVer ?>"></script>

</body>
</html>
