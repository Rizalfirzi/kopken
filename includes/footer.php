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
    adminPhone: <?= json_encode($adminPhone ?? '6288298374458') ?>,
    singleItemFee: <?= (int) config('fees.single_item_fee', 2000) ?>,
    plasticBagFee: <?= (int) config('fees.plastic_bag_fee', 1000) ?>,
    bundlingItems: <?= json_encode(config('bundling_items', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>

<!-- Native App Script -->
<script src="assets/js/app.js"></script>

</body>
</html>
