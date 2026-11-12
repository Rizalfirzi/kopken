
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
            
            <!-- Link Spotify & Spotify Player Widget -->
            <div style="margin-top:10px;text-align:center" id="bgmSpotifyArea">
                <a href="#" id="bgmSpotifyLink" target="_blank" rel="noopener" class="bgm-spotify-link-btn" style="display:none">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm4.586 14.424c-.18.295-.563.387-.857.207-2.35-1.434-5.308-1.758-8.793-.963-.335.077-.67-.133-.746-.468-.077-.334.132-.67.467-.746 3.808-.87 7.076-.51 9.722 1.113.294.18.386.563.207.857zm1.224-2.719c-.226.367-.706.482-1.072.257-2.687-1.652-6.785-2.131-9.965-1.166-.413.127-.848-.106-.973-.517-.125-.413.108-.848.52-.973 3.632-1.102 8.147-.568 11.233 1.328.366.226.481.707.257 1.071zm.105-2.835C14.692 8.95 9.375 8.775 6.297 9.71c-.494.15-1.016-.129-1.166-.624-.149-.495.13-1.016.624-1.167 3.532-1.073 9.404-.866 13.115 1.337.445.264.59.838.327 1.282-.264.443-.838.59-1.282.326z"/></svg>
                    <span>Buka Lagu di Spotify</span>
                </a>
                <div id="bgmSpotifyIframeWrap" style="display:none;margin-top:6px;border-radius:12px;overflow:hidden"></div>
            </div>
        </div>
    </div>

    <!-- Hidden Native Audio Tag -->
    <audio id="bgmAudio" preload="none"></audio>
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

<!-- Native App Script with Cache Buster -->
<?php $jsVer = file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : time(); ?>
<script src="assets/js/app.js?v=<?= $jsVer ?>"></script>

</body>
</html>
