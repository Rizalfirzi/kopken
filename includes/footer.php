
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
    wifi: <?= json_encode($initialWifi ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>

<!-- Native App Script with Cache Buster -->
<?php $jsVer = file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : time(); ?>
<script src="assets/js/app.js?v=<?= $jsVer ?>"></script>

</body>
</html>
