<!-- ================= MODALS & POPUPS KEDAI KYUTI ================= -->

<!-- 1. WELCOME POPUP (Revisi Poin 4: Pas buka web muncul seperti ini) -->
<div class="sheet-backdrop welcome-modal-backdrop" id="welcomeModal">
    <div class="welcome-card">
        <div class="wc-header">
            <?php $modalLogoImg = config('store.logo_image'); ?>
            <?php if (!empty($modalLogoImg)): ?>
                <div class="wc-logo-wrap">
                    <img src="<?= htmlspecialchars($modalLogoImg) ?>" alt="<?= htmlspecialchars(config('store.name', 'Kedai Kyuti')) ?>" class="wc-logo-img">
                </div>
            <?php endif; ?>
            <div class="wc-badge-top">୨୧ Kedai Kyuti ୨୧</div>
            <h3 class="wc-title">୨୧ 𝐖𝐞𝐥𝐜𝐨𝐦𝐞 𝐭𝐨 𝐊𝐞𝐝𝐚𝐢 𝐊𝐲𝐮𝐭𝐢! ୨୧</h3>
            <p class="wc-greeting">Hi, kyuties! 🌷</p>
            <div class="wc-rule-title">୨ৎ 𝐩𝐥𝐞𝐚𝐬𝐞 𝐫𝐞𝐚𝐝 𝐛𝐞𝐟𝐨𝐫𝐞 𝐨𝐫𝐝𝐞𝐫𝐢𝐧𝐠! ୨ৎ</div>
        </div>

        <div class="wc-body">
            <div class="wc-rule-item">
                <span class="wc-icon">☕</span>
                <span class="wc-text"><b>Kopi Kenangan Only</b></span>
            </div>
            <div class="wc-rule-item">
                <span class="wc-icon">🎀</span>
                <span class="wc-text">Ready satuan &amp; bundling</span>
            </div>
            <div class="wc-rule-item">
                <span class="wc-icon">🍓</span>
                <span class="wc-text"><b>TIDAK ADA FEE!</b> Kec. Order 1pcs/satuan ada fee <b>+2k</b></span>
            </div>
            <div class="wc-rule-item">
                <span class="wc-icon">🛵</span>
                <span class="wc-text">Delivery boleh tanya di WA dulu</span>
            </div>

            <!-- Social Links Strip (Revisi Poin 4: Komunitas & Kontak di Halaman Awal/Welcome) -->
            <div class="wc-social-strip">
                <a href="<?= htmlspecialchars(config('store.social_links.wa', 'https://wa.me/message/7PJFBJ3DLCINJ1')) ?>" target="_blank" rel="noopener" class="wc-soc-pill" title="WhatsApp Admin">
                    <span>💬 WA</span>
                </a>
                <a href="<?= htmlspecialchars(config('store.social_links.tele_testi', 'https://t.me/kedaikyuti')) ?>" target="_blank" rel="noopener" class="wc-soc-pill" title="Telegram @kedaikyuti">
                    <span>✈️ Tele</span>
                </a>
                <a href="<?= htmlspecialchars(config('store.social_links.x_twitter', 'https://x.com/lolaloliyo?s=11')) ?>" target="_blank" rel="noopener" class="wc-soc-pill" title="X (Twitter) @lolaloliyo">
                    <span>🐦 X</span>
                </a>
                <a href="<?= htmlspecialchars(config('store.social_links.wa_channel', 'https://whatsapp.com/channel/0029VbDgR1s6BIEb4d0JWl26')) ?>" target="_blank" rel="noopener" class="wc-soc-pill" title="Saluran WhatsApp JASDORTII">
                    <span>📢 Saluran</span>
                </a>
            </div>

            <div class="wc-thankyou">
                🩰 <i>𝒯𝒽𝒶𝓃𝓀 𝓎𝑜𝓊 𝒻𝑜𝓇 𝒸𝒽𝑜𝑜𝓈𝒾𝓃𝑔 𝓀𝑒𝒹𝒶𝒾 𝓀𝓎𝓊𝓉𝒾!</i> 🩰
            </div>
        </div>

        <div class="wc-action">
            <button class="btn btn-primary btn-block wc-btn" id="closeWelcomeModalBtn" type="button">
                <span>Yuk, lanjut order kyuties! ♡</span>
            </button>
        </div>
    </div>
</div>

<!-- 2. FEE 1 PCS MODAL ALERT (Revisi Poin 8: Sebelum masukin ke keranjang jika order 1pcs) -->
<div class="sheet-backdrop alert-modal-backdrop" id="singleItemFeeModal">
    <div class="alert-card">
        <div class="alert-icon-wrap fee-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </div>
        <h3 class="alert-title">Ada Fee Tambahan</h3>
        <div class="fee-badge-pill">ADA FEE +2.000</div>
        <p class="alert-desc">
            Pesanan ini terhitung <b>1pcs/satuan</b>. Tambahkan 1 item lagi atau lebih untuk bebas fee 2.000. ♡
        </p>
        <div class="alert-btn-grid">
            <button class="btn btn-secondary" id="feeContinueBtn" type="button">Tetap Lanjutkan</button>
            <button class="btn btn-primary" id="feeAddMoreBtn" type="button">Tambah Produk</button>
        </div>
    </div>
</div>

<!-- 3. OUTLET PREMIUM MODAL ALERT (Revisi Poin 9: Otomatis jika outlet mall/stasiun/bandara) -->
<div class="sheet-backdrop alert-modal-backdrop" id="premiumOutletModal">
    <div class="alert-card">
        <div class="alert-icon-wrap premium-icon">
            <span style="font-size:30px">🏬</span>
        </div>
        <h3 class="alert-title">Outlet premium</h3>
        <p class="alert-desc" id="premiumOutletDesc">
            Harga di <b id="premiumOutletNameText"><?= htmlspecialchars($initialOutlet['name'] ?? 'Outlet') ?></b> lebih mahal dari outlet biasa. Harga dan menu menyesuaikan. Untuk paket bundling tidak tersedia di outlet premium.
        </p>
        <div class="alert-action">
            <button class="btn btn-primary btn-block" id="premiumOutletOkBtn" type="button">Oke, lanjut</button>
        </div>
    </div>
</div>

<!-- 4. OUTLET SELECTION SHEET -->
<div class="sheet-backdrop" id="outletSheet">
    <div class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3>Pilih Outlet Kopi Kenangan</h3>
                <small>Stok dan harga akan disesuaikan secara real-time</small>
            </div>
            <button class="sheet-close" data-close="outletSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body">
            <div class="outlet-search-wrap">
                <div class="search-box">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7.5"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
                    <input type="search" id="outletSearchInput" placeholder="Cari nama outlet, mall, atau kota..." autocomplete="off">
                </div>
            </div>

            <button class="geo-btn" id="geoBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                <span>Pakai Lokasi Saya (Cari Outlet Terdekat)</span>
            </button>

            <div class="outlet-list-items" id="outletListContainer">
                <!-- Dynamically rendered by app.js -->
            </div>
        </div>
    </div>
</div>

<!-- 5. PRODUCT CUSTOMIZATION SHEET (Single Item) -->
<div class="sheet-backdrop" id="productSheet">
    <div class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3 id="modalProductName">Detail Produk</h3>
                <small id="modalProductCat">Kategori</small>
            </div>
            <button class="sheet-close" data-close="productSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body">
            <div style="display:flex;gap:14px;margin-bottom:16px;">
                <img id="modalProductImg" src="" alt="Produk" style="width:88px;height:88px;border-radius:12px;object-fit:cover;background:var(--bg-card-subtle)">
                <div>
                    <div id="modalStockStatus" style="margin-bottom:4px"></div>
                    <div id="modalProductDesc" style="font-size:12px;color:var(--text-muted);margin-bottom:6px;line-height:1.4"></div>
                    <div style="display:flex;align-items:baseline;gap:8px">
                        <span id="modalProductPrice" style="font-size:18px;font-weight:900;color:var(--brand-primary-dark)">Rp 0</span>
                        <span id="modalProductNormalPrice" style="font-size:13px;color:var(--text-light);text-decoration:line-through"></span>
                    </div>
                </div>
            </div>

            <!-- Dynamic Item Options & Addons (Ukuran, Suhu, Es, Manis, Topping, Syrup) -->
            <div id="itemOptions"></div>

            <!-- Special Notes -->
            <div class="form-group">
                <label class="form-label" for="modalItemNote">Catatan Khusus (Opsional)</label>
                <input class="form-input" id="modalItemNote" placeholder="Contoh: Es dipisah, jangan terlalu manis...">
            </div>

            <!-- Quantity Stepper -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">
                <span style="font-size:13px;font-weight:700">Jumlah Pesanan</span>
                <div style="display:flex;align-items:center;gap:12px">
                    <button class="icon-btn" id="qtyMinus" style="width:34px;height:34px">-</button>
                    <b id="qtyVal" style="font-size:16px;min-width:20px;text-align:center">1</b>
                    <button class="icon-btn" id="qtyPlus" style="width:34px;height:34px">+</button>
                </div>
            </div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-primary btn-block" id="addToCartBtn">Tambahkan ke Keranjang</button>
        </div>
    </div>
</div>

<!-- 6. BUNDLING CUSTOMIZATION SHEET (Revisi Poin 12: Menu Bundling) -->
<div class="sheet-backdrop" id="bundlingSheet">
    <div class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3 id="bundleModalTitle">Paket Bundling Hemat</h3>
                <small>Kustomisasi pilihan menu dalam paket</small>
            </div>
            <button class="sheet-close" data-close="bundlingSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body">
            <div style="display:flex;gap:14px;margin-bottom:16px;">
                <img id="bundleModalImg" src="" alt="Bundling" style="width:84px;height:84px;border-radius:12px;object-fit:cover;background:var(--bg-card-subtle)">
                <div>
                    <div class="promo-tag" style="display:inline-block;margin-bottom:4px">🎀 PAKET BUNDLING</div>
                    <div id="bundleModalDesc" style="font-size:12px;color:var(--text-muted);margin-bottom:6px;line-height:1.4"></div>
                    <div style="display:flex;align-items:baseline;gap:8px">
                        <span id="bundleModalPrice" style="font-size:18px;font-weight:900;color:var(--brand-primary-dark)">Rp 0</span>
                        <span id="bundleModalNormalPrice" style="font-size:13px;color:var(--text-light);text-decoration:line-through"></span>
                    </div>
                </div>
            </div>

            <div id="bundleStockNotice" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:8px;background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;font-size:12px;font-weight:700"></div>

            <!-- Bundling Slots Container (Rendered dynamically by JS) -->
            <div id="bundleSlotsContainer"></div>

            <!-- Sugar & Ice Global Preferences for Bundle (Hidden - Replaced by Per-Menu Option) -->
            <div id="bundleGlobalPrefs" style="display:none">
                <div class="option-group">
                    <div class="og-label">Pilihan Es untuk Minuman</div>
                    <div class="og-pills" id="bundleIcePills">
                        <button class="og-pill active" data-val="Normal Ice">Normal Ice</button>
                        <button class="og-pill" data-val="Less Ice">Less Ice</button>
                    </div>
                </div>

                <div class="option-group">
                    <div class="og-label">Tingkat Manis (Sugar)</div>
                    <div class="og-pills" id="bundleSugarPills">
                        <button class="og-pill active" data-val="Normal Sugar">Normal Sugar</button>
                        <button class="og-pill" data-val="Less Sugar (50%)">Less Sugar (50%)</button>
                        <button class="og-pill" data-val="No Sugar">No Sugar</button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bundleNotes">Catatan untuk paket ini (Opsional)</label>
                <textarea class="form-textarea" id="bundleNotes" rows="2" placeholder="Catatan khusus isi paket..."></textarea>
            </div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-primary btn-block" id="addBundleToCartBtn">Tambahkan Bundling ke Keranjang</button>
        </div>
    </div>
</div>

<!-- 7. CART & CHECKOUT SHEET (Revisi Poin 10: Kantung Plastik +1.000, Poin 11: Jam Pickup 15 Menit + Zona Waktu) -->
<div class="sheet-backdrop" id="cartSheet">
    <div class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3>Keranjang Pesanan</h3>
                <small id="cartSheetOutletName">Outlet</small>
            </div>
            <button class="sheet-close" data-close="cartSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body">
            <!-- Items in Cart -->
            <div id="cartItemsList" style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px"></div>

            <!-- Customer Details Form -->
            <div class="form-group">
                <label class="form-label" for="custName">Nama Pemesan <span style="color:var(--danger)">*</span></label>
                <input class="form-input" id="custName" placeholder="Ketik nama kamu..." required>
            </div>

            <div class="form-group">
                <label class="form-label" for="custPhone">Nomor WhatsApp (Opsional)</label>
                <input class="form-input" id="custPhone" type="tel" placeholder="08xxxxxxxxxx">
            </div>

            <!-- Pilihan Waktu Pickup (Revisi Poin 11) -->
            <div class="form-group">
                <label class="form-label">Waktu Pickup</label>
                <div class="pickup-toggle-row">
                    <button class="pickup-toggle-btn active" id="pickupNowBtn" type="button">Sekarang</button>
                    <button class="pickup-toggle-btn" id="pickupCustomBtn" type="button">Atur Jam</button>
                </div>

                <!-- Custom Time Container (Kelipatan 15 menit & Zona Waktu) -->
                <div id="customPickupContainer" style="display:none;margin-top:10px">
                    <div class="tz-pills-row">
                        <button class="tz-pill active" data-tz="WIB" type="button">WIB</button>
                        <button class="tz-pill" data-tz="WITA" type="button">WITA</button>
                        <button class="tz-pill" data-tz="WIT" type="button">WIT</button>
                    </div>
                    <select class="form-input" id="pickupTimeSelect" style="margin-top:8px">
                        <!-- Populated with 15-minute intervals by app.js -->
                    </select>
                </div>
            </div>

            <!-- Pilihan Kantung Belanja Plastik (Revisi: Bisa Diatur Jumlahnya) -->
            <div class="checkbox-addon-box" id="plasticBagAddonBox">
                <div class="plastic-bag-row">
                    <label class="checkbox-label" for="plasticBagCheckbox" style="flex:1;margin:0;cursor:pointer">
                        <input type="checkbox" id="plasticBagCheckbox">
                        <span class="custom-chk"></span>
                        <span class="chk-text">
                            <span class="chk-title">Pakai kantung belanja</span>
                            <small class="chk-price-tag">+Rp 1.000 / kantung</small>
                        </span>
                    </label>
                    <div class="plastic-stepper" id="plasticBagStepper" style="display:none">
                        <button type="button" class="plastic-step-btn" id="plasticBagMinusBtn" title="Kurangi Kantung" aria-label="Kurangi Kantung">−</button>
                        <span class="plastic-qty-count" id="plasticBagQtyDisplay">1</span>
                        <button type="button" class="plastic-step-btn" id="plasticBagPlusBtn" title="Tambah Kantung" aria-label="Tambah Kantung">+</button>
                    </div>
                </div>
                <div class="plastic-bag-hint" id="plasticBagHint" style="display:none">
                    <span id="plasticBagHintText">🛍️ 1 Kantung Belanja (+Rp 1.000)</span>
                </div>
            </div>

            <!-- Overall Notes -->
            <div class="form-group" style="margin-top:12px">
                <label class="form-label" for="orderNotes">Catatan Pesanan Keseluruhan</label>
                <textarea class="form-textarea" id="orderNotes" rows="2" placeholder="Catatan tambahan untuk admin / barista..."></textarea>
            </div>

            <!-- Price Breakdown -->
            <div class="price-breakdown-card">
                <div class="pbc-row">
                    <span>Subtotal Menu</span>
                    <b id="summarySubtotal">Rp 0</b>
                </div>

                <!-- Fee Pesanan Satuan Row (Revisi Client: Tulis "0" jika tidak ada fee) -->
                <div class="pbc-row" id="singleFeeRow">
                    <span>Fee Pesanan Satuan</span>
                    <b id="summarySingleFee">0</b>
                </div>
                <div class="pbc-row" id="freeFeeRow" style="display:none"></div>

                <!-- Plastic Bag Row (Poin 10) -->
                <div class="pbc-row" id="plasticFeeRow" style="display:none">
                    <span id="summaryPlasticLabel">Kantung Belanja Plastik</span>
                    <b id="summaryPlasticFee">+Rp 1.000</b>
                </div>

                <div class="pbc-total">
                    <span>Total Pembayaran</span>
                    <b id="summaryTotal" style="color:var(--brand-primary-dark)">Rp 0</b>
                </div>
            </div>
        </div>
        <div class="sheet-actions">
            <button class="btn btn-secondary" id="clearCartBtn" style="flex:1">Kosongkan</button>
            <button class="btn btn-wa" id="checkoutWaBtn" style="flex:2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8.2 8.2 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2.1c-.2-.3 0-.5.1-.6l.5-.6.3-.5a.6.6 0 0 0 0-.5l-.9-2.3c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .8 2.5.7 3 .6a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.2-.3-.3-.6-.4zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.2A11.8 11.8 0 0 0 1.7 17.8L0 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6z"/></svg>
                <span>Pesan via WhatsApp</span>
            </button>
        </div>
    </div>
</div>

<!-- 8. ORDER TRACKING SHEET -->
<div class="sheet-backdrop" id="trackSheet">
    <div class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3>Lacak Status Pesanan</h3>
                <small>Cek status pesanan Kedai Kyuti kamu</small>
            </div>
            <button class="sheet-close" data-close="trackSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body">
            <div style="display:flex;gap:8px;margin-bottom:16px">
                <input class="form-input" id="trackCodeInput" placeholder="Masukkan Kode Pesanan (misal: KYUTI-A1B2)" style="text-transform:uppercase">
                <button class="btn btn-primary" id="trackSubmitBtn" style="flex-shrink:0">Lacak</button>
            </div>

            <div id="trackResultBox">
                <div class="empty-box">
                    <div class="empty-icon">🔍</div>
                    <p>Masukkan kode pesanan untuk melihat status pesananmu.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 9. ADMIN PASSCODE / REDEEM CODE MODAL                                     -->
<!-- ========================================================================= -->
<div class="sheet-backdrop alert-modal-backdrop" id="adminPasscodeModal">
    <div class="alert-card" style="max-width:360px">
        <div class="alert-icon-wrap" style="background:#FCE7F3;color:var(--brand-primary-dark)">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <h3 class="alert-title" style="margin-bottom:4px">Akses Khusus Admin</h3>
        <p class="alert-desc" style="font-size:12px;margin-bottom:14px">
            Masukkan <b>Redeem Code / Passcode Admin</b> untuk mengelola fee dan harga menu langsung di website:
        </p>

        <div style="margin-bottom:16px">
            <input type="password" class="form-input" id="adminPasscodeInput" placeholder="Masukkan passcode..." style="text-align:center;font-size:15px;letter-spacing:2px;font-weight:700">
            <div id="adminPasscodeErr" style="display:none;color:#E11D48;font-size:11px;font-weight:700;margin-top:6px;text-align:center"></div>
        </div>

        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary" data-close="adminPasscodeModal" type="button" style="flex:1">Batal</button>
            <button class="btn btn-primary" id="adminPasscodeSubmitBtn" type="button" style="flex:2">Verifikasi &amp; Masuk</button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 10. ADMIN PRICING & FEE MANAGEMENT SHEET                                  -->
<!-- ========================================================================= -->
<div class="sheet-backdrop" id="adminPricingSheet">
    <div class="sheet" style="max-height:92vh">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3>⚙️ Kelola Fee &amp; Harga (Admin)</h3>
                <small>Ubah fee menu langsung di website tanpa coding</small>
            </div>
            <button class="sheet-close" data-close="adminPricingSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body" style="padding-bottom:90px">
            <!-- Alert Info -->
            <div style="background:var(--bg-card-subtle);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px 14px;margin-bottom:12px;font-size:12px;color:var(--text-main);line-height:1.4">
                💡 <b>Formula Harga:</b> <code>(Harga Asli Kopken - 50%) + Fee</code>.<br>
                Di sini kamu bisa atur fee default, fee per kategori makanan/minuman, hingga fee khusus untuk menu tertentu!
            </div>

            <!-- Database Status Indicator -->
            <div id="adminDbStatusBox" style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:8px 12px;margin-bottom:16px;font-size:12px;display:flex;align-items:center;justify-content:space-between">
                <span style="font-weight:700;color:var(--text-main);display:inline-flex;align-items:center;gap:6px">
                    <span>☁️ Database Cloud:</span>
                </span>
                <span id="adminDbStatusBadge" style="font-weight:700;padding:3px 10px;border-radius:12px;font-size:11px;background:#FEF3C7;color:#92400E">Memeriksa...</span>
            </div>

            <!-- 1. Fee Global / Default -->
            <div class="admin-setting-section" style="margin-bottom:18px">
                <label style="font-size:13px;font-weight:800;color:var(--text-main);display:block;margin-bottom:6px">
                    1. Fee Default Per Menu (Standar)
                </label>
                <div style="position:relative">
                    <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-weight:700;color:var(--text-muted);font-size:13px">Rp +</span>
                    <input type="number" class="form-input" id="cfgDefaultMarkup" placeholder="5000" style="padding-left:52px;font-weight:800;font-size:15px">
                </div>
                <small style="color:var(--text-muted);font-size:11px">Fee dasar jika menu tidak memiliki fee khusus kategori atau menu.</small>
            </div>

            <!-- 2. Fee Kategori Makanan & Minuman -->
            <div class="admin-setting-section" style="margin-bottom:18px">
                <label style="font-size:13px;font-weight:800;color:var(--text-main);display:block;margin-bottom:6px">
                    2. Fee Berdasarkan Kategori
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:6px">
                    <div>
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">☕ Minuman (Coffee, etc)</small>
                        <div style="position:relative">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:700;color:var(--text-muted);font-size:12px">Rp +</span>
                            <input type="number" class="form-input" id="cfgCategoryDrinks" placeholder="5000" style="padding-left:46px;font-weight:800">
                        </div>
                    </div>
                    <div>
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">🥪 Makanan &amp; Toast</small>
                        <div style="position:relative">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:700;color:var(--text-muted);font-size:12px">Rp +</span>
                            <input type="number" class="form-input" id="cfgCategoryFood" placeholder="4500" style="padding-left:46px;font-weight:800">
                        </div>
                    </div>
                </div>
                <small style="color:var(--text-muted);font-size:11px">Contoh kemauan klien: Makanan semua +4.500, Minuman semua +5.000.</small>
            </div>

            <!-- 3. Fee Khusus Menu Tertentu (Item Markup Overrides) -->
            <div class="admin-setting-section" style="margin-bottom:18px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <label style="font-size:13px;font-weight:800;color:var(--text-main)">
                        3. Fee Khusus Menu Tertentu
                    </label>
                    <span class="pill pill-premium" style="font-size:10px">Prioritas Utama</span>
                </div>
                <small style="color:var(--text-muted);font-size:11px;display:block;margin-bottom:8px">
                    Bisa atur fee per menu maupun <b>khusus ukuran Large</b> (misal: <i>Kopi Kenangan Mantan Regular +5.000</i> dan <i>Large +7.000</i>).
                </small>

                <!-- List of current active item overrides -->
                <div id="cfgItemOverridesList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
                    <!-- Rendered dynamically by JS -->
                </div>

                <!-- Add new item override box with Live API Menu Selector -->
                <div style="background:var(--bg-page);border:1.5px dashed var(--brand-primary);border-radius:var(--radius-md);padding:14px;margin-top:6px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                        <b style="font-size:12px;font-weight:800;color:var(--text-main)">+ Tambah Fee Khusus Menu & Ukuran:</b>
                        <span style="font-size:10px;color:var(--brand-primary-dark);font-weight:700;background:var(--bg-card-subtle);padding:2px 8px;border-radius:10px;border:1px solid var(--border-color)">⚡ Live dari API</span>
                    </div>

                    <!-- Row 1: Pilih Menu -->
                    <div style="margin-bottom:8px">
                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">1. Pilih Menu:</label>
                        <select class="form-input" id="cfgMenuSelect" style="font-size:12.5px;font-weight:700;color:var(--text-main);background:var(--bg-card);border:1.5px solid var(--border-color);cursor:pointer;width:100%;text-overflow:ellipsis">
                            <option value="">-- 🔍 Pilih Menu dari API --</option>
                        </select>
                    </div>

                    <!-- Optional Fallback: Manual Name Input (if user specifically selects manual) -->
                    <div id="cfgCustomNameWrap" style="display:none;margin-bottom:8px">
                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Ketik Nama Menu:</label>
                        <input type="text" class="form-input" id="cfgNewItemName" placeholder="Ketik nama menu manual..." style="font-size:12px">
                    </div>

                    <!-- Row 2: Pilih Ukuran + Nominal Fee -->
                    <div style="display:grid;grid-template-columns:1fr 120px;gap:8px;margin-bottom:10px">
                        <div>
                            <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">2. Pilih Ukuran:</label>
                            <select class="form-input" id="cfgItemSizeSelect" style="font-size:12px;font-weight:700;color:var(--text-main);background:var(--bg-card);border:1.5px solid var(--border-color);cursor:pointer;width:100%">
                                <option value="">Regular / Default</option>
                                <option value="Large">Khusus Ukuran Large ⭐</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">3. Fee (Rp):</label>
                            <div style="position:relative">
                                <span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);font-weight:800;color:var(--text-muted);font-size:12px">+</span>
                                <input type="number" class="form-input" id="cfgNewItemFee" placeholder="6000" style="padding-left:22px;font-size:13px;font-weight:800;width:100%">
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Add Button Full Width -->
                    <button type="button" class="btn btn-primary btn-block" id="cfgAddNewItemBtn" style="font-size:13px;padding:10px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:6px">
                        <span>+ Pasang Fee Khusus Menu</span>
                    </button>
                </div>
            </div>

            <!-- 4. Fee Satuan & Kantong Belanja -->
            <div class="admin-setting-section" style="margin-bottom:20px">
                <label style="font-size:13px;font-weight:800;color:var(--text-main);display:block;margin-bottom:6px">
                    4. Biaya Tambahan Lainnya (Addon)
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div>
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Fee Order 1 Pcs (0 = Bebas Fee)</small>
                        <div style="position:relative">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:700;color:var(--text-muted);font-size:12px">Rp +</span>
                            <input type="number" class="form-input" id="cfgSingleItemFee" placeholder="0" style="padding-left:46px;font-weight:800">
                        </div>
                    </div>
                    <div>
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Kantong Plastik</small>
                        <div style="position:relative">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-weight:700;color:var(--text-muted);font-size:12px">Rp +</span>
                            <input type="number" class="form-input" id="cfgPlasticBagFee" placeholder="1000" style="padding-left:46px;font-weight:800">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Kelola Status Menu Bundling (ON / OFF) (Revisi Client) -->
            <div class="admin-setting-section" style="margin-bottom:20px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <label style="font-size:13px;font-weight:800;color:var(--text-main)">
                        5. Status Menu Bundling (ON / OFF)
                    </label>
                    <span class="pill pill-premium" style="font-size:10px">Kontrol Menu</span>
                </div>
                <small style="color:var(--text-muted);font-size:11px;display:block;margin-bottom:12px">
                    Aktifkan atau nonaktifkan paket bundling dan menu di dalamnya langsung dari website.
                </small>

                <!-- List of Bundling Packages with ON/OFF Switches -->
                <div id="cfgBundlingStatusList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
                    <!-- Rendered dynamically by JS -->
                </div>

                <!-- Disable specific items inside bundling -->
                <div style="background:var(--bg-page);border:1.5px dashed var(--border-color);border-radius:var(--radius-md);padding:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <b style="font-size:11.5px;color:var(--text-main)">Nonaktifkan Menu Tertentu di Bundling:</b>
                        <small style="color:var(--text-muted);font-size:10px">Opsional</small>
                    </div>
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <select class="form-input" id="cfgDisableBundlingSelect" style="font-size:12px;font-weight:700;flex:1">
                            <option value="">-- 🔍 Pilih Menu untuk Dinonaktifkan --</option>
                        </select>
                        <button type="button" class="btn btn-secondary" id="cfgAddDisabledOptionBtn" style="padding:6px 12px;font-size:12px;font-weight:800;white-space:nowrap">
                            + Matikan
                        </button>
                    </div>
                    <!-- Disabled Bundling Items List -->
                    <div id="cfgDisabledOptionsList" style="display:flex;flex-wrap:wrap;gap:6px">
                        <!-- Rendered by JS -->
                    </div>
                </div>
            </div>

            <!-- 6. Jam Operasional Web (Fitur Baru Permintaan Client) -->
            <div class="admin-setting-section" style="margin-bottom:18px;background:var(--bg-page);border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <label style="font-size:13px;font-weight:800;color:var(--text-main);display:flex;align-items:center;gap:6px">
                        <span>⏰ 6. Jam Operasional Web Kedai Kyuti</span>
                    </label>
                    <span id="cfgWebHoursLiveBadge" class="pill pill-open" style="font-size:11px">Live: Buka</span>
                </div>
                <small style="color:var(--text-muted);font-size:11px;display:block;margin-bottom:12px">
                    Atur jam operasional pemesanan online Kedai Kyuti (misal <b>09.00 - 12.00 WIB</b>). Di luar jam ini, pelanggan tetap bisa browsing menu namun checkout akan diarahkan sesuai jam buka.
                </small>

                <!-- Toggle Aktifkan Jam Operasional -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;background:var(--bg-card);padding:10px 12px;border-radius:var(--radius-sm);border:1px solid var(--border-color)">
                    <div>
                        <b style="font-size:12px;color:var(--text-main);display:block">Batasi Sesuai Jam Operasional</b>
                        <small style="font-size:11px;color:var(--text-muted)">Nonaktifkan jika ingin web buka 24 jam</small>
                    </div>
                    <label class="switch-toggle" style="cursor:pointer">
                        <input type="checkbox" id="cfgWebHoursEnabled" checked>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Input Jam Buka & Jam Tutup -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
                    <div>
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Jam Buka (WIB)</small>
                        <input type="time" class="form-input" id="cfgWebHoursOpen" value="09:00" style="font-weight:800;text-align:center">
                    </div>
                    <div>
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Jam Tutup (WIB)</small>
                        <input type="time" class="form-input" id="cfgWebHoursClose" value="12:00" style="font-weight:800;text-align:center">
                    </div>
                </div>

                <!-- Mode Override Status -->
                <div style="margin-bottom:12px">
                    <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Status Khusus / Override Manual</small>
                    <select class="form-input" id="cfgWebHoursForce" style="font-size:12px;font-weight:700">
                        <option value="auto">⚡ Otomatis (Sesuai Jam Buka &amp; Tutup di atas)</option>
                        <option value="open">🟢 Paksa Selalu Buka (Abaikan Jam)</option>
                        <option value="closed">🔴 Paksa Tutup Sementara Sekarang</option>
                    </select>
                </div>

                <!-- Pesan Saat Tutup -->
                <div>
                    <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Pesan Saat Web Tutup</small>
                    <textarea class="form-input" id="cfgWebHoursMsg" rows="2" style="font-size:11px;line-height:1.4" placeholder="Pesan ramah untuk customer saat web tutup..."></textarea>
                </div>
            </div>

            <!-- 7. Pengaturan Musik & Lagu Café (BGM) (Fitur Baru) -->
            <div class="admin-setting-section" style="margin-bottom:18px;background:var(--bg-page);border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <label style="font-size:13px;font-weight:800;color:var(--text-main);display:flex;align-items:center;gap:6px">
                        <span>🎵 7. Pengaturan Musik &amp; Lagu Café (BGM)</span>
                    </label>
                    <span id="cfgBgmActiveCountBadge" class="pill pill-premium" style="font-size:11px">3 Lagu Aktif</span>
                </div>
                <small style="color:var(--text-muted);font-size:11px;display:block;margin-bottom:12px">
                    Client dapat mengupload file musik format <b>.mp3</b> atau menginputkan link lagu URL untuk dimainkan di pemutar musik website.
                </small>

                <!-- Toggle BGM Player -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;background:var(--bg-card);padding:10px 12px;border-radius:var(--radius-sm);border:1px solid var(--border-color)">
                    <div>
                        <b style="font-size:12px;color:var(--text-main);display:block">Tampilkan Pemutar Musik di Website</b>
                        <small style="font-size:11px;color:var(--text-muted)">Nonaktifkan jika tidak ingin ada musik café</small>
                    </div>
                    <label class="switch-toggle" style="cursor:pointer">
                        <input type="checkbox" id="cfgBgmEnabled" checked>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Volume Slider -->
                <div style="margin-bottom:14px;background:var(--bg-card);padding:10px 12px;border-radius:var(--radius-sm);border:1px solid var(--border-color)">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <small style="font-weight:700;color:var(--text-main);font-size:11px">Volume Musik Default</small>
                        <span id="cfgBgmVolumeVal" style="font-size:11px;font-weight:800;color:var(--brand-primary-dark)">40%</span>
                    </div>
                    <input type="range" id="cfgBgmVolume" min="5" max="100" value="40" style="width:100%;accent-color:var(--brand-primary);cursor:pointer">
                </div>

                <!-- Auto-Sync TikTok Viral Hits Card (Full Song / Versi Lirik) -->
                <div style="background:linear-gradient(135deg, rgba(236, 72, 153, 0.08) 0%, rgba(244, 114, 182, 0.12) 100%);border:1.5px solid var(--brand-primary);border-radius:var(--radius-md);padding:12px;margin-bottom:14px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                        <div style="display:flex;align-items:center;gap:6px">
                            <span style="font-size:16px">🔥</span>
                            <b style="font-size:12px;color:var(--text-main)">Auto-Sync Lagu Viral TikTok (Versi Lirik / Full)</b>
                        </div>
                        <span class="pill" style="background:var(--brand-primary);color:#fff;font-size:10px;font-weight:800;padding:2px 8px">Full Audio</span>
                    </div>
                    <p style="font-size:11px;color:var(--text-muted);line-height:1.4;margin-bottom:10px">
                        Tarik otomatis 10–20 daftar lagu yang lagi viral & trending di TikTok (termasuk track TikTok Denny Caknan) versi lirik audio langsung tanpa dialog/drama video klip.
                    </p>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="btn btn-primary btn-block" id="btnSyncTikTokHits" style="font-weight:800;font-size:12px;padding:9px 12px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;gap:6px;border:none;cursor:pointer">
                            <span>🔄 Tarik Lagu Viral TikTok (Versi Lirik Full)</span>
                        </button>
                    </div>
                    <div id="syncTikTokMsg" style="display:none;font-size:11px;margin-top:8px;text-align:center;font-weight:700"></div>
                </div>

                <!-- Daftar Playlist Lagu Saat Ini -->
                <div style="margin-bottom:14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <b style="font-size:12px;color:var(--text-main)">Daftar Lagu Aktif:</b>
                        <button type="button" id="cfgBgmResetBtn" style="background:none;border:none;color:var(--brand-primary-dark);font-size:11px;font-weight:700;cursor:pointer;text-decoration:underline">
                            ↺ Reset Lagu Default
                        </button>
                    </div>
                    <div id="cfgBgmPlaylistContainer" style="display:flex;flex-direction:column;gap:8px">
                        <!-- Rendered dynamically by JS -->
                    </div>
                </div>

                <!-- Form Tambah Lagu Baru (Upload / URL) -->
                <div style="background:var(--bg-card);border:1.5px dashed var(--border-color);border-radius:var(--radius-md);padding:12px">
                    <b style="font-size:12px;color:var(--text-main);display:block;margin-bottom:8px">Tambah Lagu Baru:</b>

                    <!-- Pilihan Tab: Upload File vs Input Link -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px">
                        <button type="button" class="btn btn-sm" id="cfgBgmTabUploadBtn" style="font-size:11px;font-weight:800;padding:6px;border:1px solid var(--brand-primary);background:var(--brand-primary-light);color:var(--brand-primary-dark);border-radius:var(--radius-sm)">
                            📤 Upload File MP3
                        </button>
                        <button type="button" class="btn btn-sm" id="cfgBgmTabLinkBtn" style="font-size:11px;font-weight:800;padding:6px;border:1px solid var(--border-color);background:var(--bg-page);color:var(--text-muted);border-radius:var(--radius-sm)">
                            🔗 Input Link URL MP3
                        </button>
                    </div>

                    <!-- Panel 1: Upload File MP3 -->
                    <div id="cfgBgmUploadPanel">
                        <div style="margin-bottom:8px">
                            <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Pilih File Lagu (.mp3)</small>
                            <input type="file" id="cfgBgmFileInput" accept=".mp3,audio/mpeg,audio/mp3" class="form-input" style="font-size:11px;padding:6px">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                            <div>
                                <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Judul Lagu</small>
                                <input type="text" id="cfgBgmUploadTitle" class="form-input" placeholder="Misal: Lofi Kopi Sore" style="font-size:11px">
                            </div>
                            <div>
                                <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Artis / Keterangan</small>
                                <input type="text" id="cfgBgmUploadArtist" class="form-input" placeholder="Misal: Kedai Kyuti Chill" style="font-size:11px">
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-block" id="cfgBgmSubmitUploadBtn" style="font-size:12px;padding:8px;font-weight:800">
                            <span>📤 Upload File &amp; Tambahkan</span>
                        </button>
                        <div id="cfgBgmUploadMsg" style="font-size:11px;margin-top:6px;display:none;text-align:center"></div>
                    </div>

                    <!-- Panel 2: Input Link URL MP3 -->
                    <div id="cfgBgmLinkPanel" style="display:none">
                        <div style="margin-bottom:8px">
                            <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Link / URL File MP3 Langsung</small>
                            <input type="url" id="cfgBgmLinkUrl" class="form-input" placeholder="https://example.com/lagu.mp3 atau Google Drive" style="font-size:11px">
                            <small style="font-size:10px;color:var(--text-light);display:block;margin-top:2px">Bisa link MP3 publik, Google Drive (view link), atau Dropbox</small>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
                            <div>
                                <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Judul Lagu</small>
                                <input type="text" id="cfgBgmLinkTitle" class="form-input" placeholder="Misal: Sweet Afternoon Acoustic" style="font-size:11px">
                            </div>
                            <div>
                                <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Artis / Keterangan</small>
                                <input type="text" id="cfgBgmLinkArtist" class="form-input" placeholder="Misal: Lofi Vibe ☕" style="font-size:11px">
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-block" id="cfgBgmSubmitLinkBtn" style="font-size:12px;padding:8px;font-weight:800">
                            <span>+ Tambahkan Link Lagu</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 8. Kelola & Hapus Ulasan Customer (Fitur Baru Permintaan Client) -->
            <div class="admin-setting-section" style="margin-bottom:18px;background:var(--bg-page);border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <label style="font-size:13px;font-weight:800;color:var(--text-main);display:flex;align-items:center;gap:6px">
                        <span>⭐ 8. Kelola &amp; Hapus Ulasan Customer</span>
                    </label>
                    <span class="badge" id="adminReviewCountBadge" style="background:#FCE7F3;color:var(--brand-primary-dark);font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:12px">
                        0 Ulasan
                    </span>
                </div>
                <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px;line-height:1.4">
                    Admin dapat menghapus ulasan customer yang tidak pantas, spam, atau ingin dibersihkan. Ulasan yang dihapus akan langsung terhapus permanen dari website.
                </p>

                <!-- Container List Ulasan di Panel Admin -->
                <div id="adminReviewsList" style="display:flex;flex-direction:column;gap:10px;max-height:360px;overflow-y:auto;padding-right:4px">
                    <div style="text-align:center;padding:16px;color:var(--text-muted);font-size:11px">
                        <span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid var(--brand-primary);border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;vertical-align:middle;margin-right:4px"></span>
                        Memuat data ulasan...
                    </div>
                </div>
            </div>
        </div>

        <div class="sheet-actions" style="border-top:1px solid var(--border-color);padding:14px 18px;background:var(--bg-card)">
            <button class="btn btn-primary btn-block" id="saveAdminPricingBtn" type="button" style="font-weight:900">
                <span>💾 Simpan Perubahan Fee</span>
            </button>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- 10. MODAL WI-FI OUTLET (Revisi Client ala Referensi)          -->
<!-- ============================================================= -->
<div class="sheet-backdrop dialog-backdrop" id="wifiModal">
    <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="wifiModalTitle" style="max-width:360px">
        <button class="sheet-close" data-close="wifiModal" aria-label="Tutup" id="wifiCloseBtn" style="position:absolute;right:14px;top:14px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:var(--bg-card-subtle);border:1px solid var(--border-color);border-radius:var(--radius-full);color:var(--text-muted);font-size:18px;cursor:pointer">&times;</button>
        <div style="text-align:center;margin-bottom:12px">
            <div style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:var(--radius-full);background:var(--bg-card-subtle);color:var(--brand-primary);margin-bottom:8px">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
            </div>
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:800;color:var(--text-muted)">Wi-Fi Kopi Kenangan</div>
            <h3 id="wifiModalTitle" style="font-size:18px;font-weight:900;color:var(--text-main);margin-top:2px">Wi-Fi Outlet</h3>
            <div id="wifiModalDate" style="font-size:12px;color:var(--text-muted);margin-top:2px">Hari Ini</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px">
            <div style="background:var(--bg-page);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px 14px">
                <small style="display:block;font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700">Nama Jaringan (SSID)</small>
                <strong id="wifiModalSsid" style="font-size:15px;color:var(--text-main)">Teman Kenangan</strong>
            </div>
            <div style="background:var(--bg-page);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                <div>
                    <small style="display:block;font-size:11px;color:var(--text-muted);text-transform:uppercase;font-weight:700">Password Hari Ini</small>
                    <strong id="wifiModalPass" style="font-size:16px;color:var(--brand-primary-dark);font-family:monospace;letter-spacing:0.5px">Memuat...</strong>
                </div>
                <button type="button" class="btn-copy-wifi" id="wifiModalCopyBtn" style="padding:8px 12px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span>Salin</span>
                </button>
            </div>
        </div>
        <div style="margin-top:16px">
            <button type="button" class="btn btn-secondary btn-block" data-close="wifiModal">Tutup</button>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- 11. MODAL ORDER SUKSES & REDIRECT WHATSAPP                    -->
<!-- ============================================================= -->
<div class="sheet-backdrop dialog-backdrop" id="orderSuccessModal">
    <div class="dialog" role="dialog" aria-modal="true" style="max-width:390px;text-align:center">
        <button class="sheet-close" data-close="orderSuccessModal" aria-label="Tutup" style="position:absolute;right:14px;top:14px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:var(--bg-card-subtle);border:1px solid var(--border-color);border-radius:var(--radius-full);color:var(--text-muted);font-size:18px;cursor:pointer">&times;</button>
        
        <div style="width:60px;height:60px;border-radius:var(--radius-full);background:linear-gradient(135deg, #DCFCE7, #BBF7D0);color:#16A34A;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;box-shadow:0 4px 12px rgba(22, 163, 74, 0.2)">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        
        <h3 style="font-size:17px;font-weight:900;color:var(--text-main);margin-bottom:4px">Pesanan Berhasil Disiapkan! 🌸</h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px">Format pesan telah siap. Kamu sedang dialihkan ke WhatsApp...</p>
        
        <div style="background:var(--bg-page);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:12px;text-align:left;font-size:12px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="color:var(--text-muted)">Kode Pesanan:</span>
                <strong id="successModalCode" style="color:var(--brand-primary-dark);font-family:monospace;font-size:13px">KYUTI-XXXX</strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="color:var(--text-muted)">Nama Pemesan:</span>
                <strong id="successModalName">-</strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="color:var(--text-muted)">Kantung Belanja:</span>
                <strong id="successModalBag">-</strong>
            </div>
            <div style="display:flex;justify-content:space-between;border-top:1px dashed var(--border-color);padding-top:6px;margin-top:4px">
                <span style="color:var(--text-main);font-weight:700">Total Pembayaran:</span>
                <strong id="successModalTotal" style="color:var(--brand-primary-dark);font-size:14px">Rp 0</strong>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px">
            <a href="#" id="successOpenWaBtn" class="btn btn-wa btn-block" style="text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;font-size:14px;font-weight:800;border-radius:var(--radius-full)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8.2 8.2 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2.1c-.2-.3 0-.5.1-.6l.5-.6.3-.5a.6.6 0 0 0 0-.5l-.9-2.3c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .8 2.5.7 3 .6a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.2-.3-.3-.6-.4zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.2A11.8 11.8 0 0 0 1.7 17.8L0 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6z"/></svg>
                <span>Buka WhatsApp Sekarang</span>
            </a>
            <button type="button" class="btn btn-secondary btn-block" id="successCopyMsgBtn" style="padding:10px;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span id="successCopyMsgText">Salin Format Pesanan</span>
            </button>
            <button type="button" class="btn btn-secondary btn-block" data-close="orderSuccessModal" style="padding:8px;font-size:12px;opacity:0.8;border:none;background:transparent;cursor:pointer">
                Tutup Jendela Ini
            </button>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- 12. MODAL REVIEW & TESTIMONI CUSTOMER (Fitur Baru Permintaan Client) -->
<!-- ============================================================= -->
<div class="sheet-backdrop" id="reviewSheet">
    <div class="sheet review-sheet" style="max-height:92vh">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3>⭐ Ulasan &amp; Testimoni Customer</h3>
                <small>Cerita &amp; pengalaman manis para sahabat Kedai Kyuti ♡</small>
            </div>
            <button class="sheet-close" data-close="reviewSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body" style="padding-bottom:80px">
            
            <!-- Summary Rating Box -->
            <div class="review-summary-card">
                <div class="rsc-left">
                    <div class="rsc-big-score" id="reviewAvgScore">4.9</div>
                    <div class="rsc-stars">⭐⭐⭐⭐⭐</div>
                    <div class="rsc-count" id="reviewTotalCount">120+ Ulasan Sahabat Kyuti</div>
                </div>
                <div class="rsc-right">
                    <div class="rsc-highlight-item">
                        <span class="rsc-badge">🌸 100% Amanah</span>
                        <small>Jasdor Kopi Kenangan Asli</small>
                    </div>
                    <div class="rsc-highlight-item">
                        <span class="rsc-badge">⚡ Respon Cepat</span>
                        <small>Proses orderan tanpa ribet</small>
                    </div>
                </div>
            </div>

            <!-- Action Button to Open Review Form -->
            <div style="margin-bottom:14px;display:flex;gap:8px">
                <button type="button" class="btn btn-primary btn-block" id="toggleWriteReviewBtn" style="font-weight:800;border-radius:var(--radius-full);padding:10px 16px;font-size:13px;display:flex;align-items:center;justify-content:center;gap:6px">
                    <span>✨ Tulis Ulasan Kamu ♡</span>
                </button>
            </div>

            <!-- Form Tulis Review (Hidden by default, toggles on click) -->
            <div id="writeReviewBox" style="display:none;background:var(--bg-card);border:1.5px solid var(--brand-primary);border-radius:var(--radius-md);padding:14px;margin-bottom:16px;box-shadow:var(--shadow-sm)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <b style="font-size:13px;color:var(--brand-primary-dark)">📝 Kirim Ulasan Pengalamanmu</b>
                    <button type="button" id="closeWriteReviewBtn" style="background:none;border:none;font-size:16px;cursor:pointer;color:var(--text-muted)">&times;</button>
                </div>

                <form id="customerReviewForm" onsubmit="return false;">
                    <!-- Rating Star Selector -->
                    <div style="margin-bottom:12px;text-align:center;background:var(--bg-page);padding:8px;border-radius:var(--radius-sm)">
                        <small style="font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">Beri Bintang Kepuasan:</small>
                        <div class="star-rating-picker" id="starRatingPicker">
                            <button type="button" class="star-pick-btn active" data-val="1">★</button>
                            <button type="button" class="star-pick-btn active" data-val="2">★</button>
                            <button type="button" class="star-pick-btn active" data-val="3">★</button>
                            <button type="button" class="star-pick-btn active" data-val="4">★</button>
                            <button type="button" class="star-pick-btn active" data-val="5">★</button>
                        </div>
                        <span id="starRatingLabel" style="font-size:11px;font-weight:800;color:var(--brand-primary-dark)">⭐⭐⭐⭐⭐ Sangat Puas!</span>
                    </div>

                    <!-- Input Nama -->
                    <div style="margin-bottom:10px">
                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:3px">Nama Panggilan / Inisial</label>
                        <input type="text" class="form-input" id="revInputName" required maxlength="50" style="font-size:13px">
                    </div>

                    <!-- Input Menu -->
                    <div style="margin-bottom:10px">
                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:3px">Menu yang Dipesan (Opsional)</label>
                        <input type="text" class="form-input" id="revInputMenu" maxlength="70" style="font-size:13px">
                    </div>

                    <!-- Input Ulasan -->
                    <div style="margin-bottom:12px">
                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:3px">Ulasan / Testimoni Kamu</label>
                        <textarea class="form-input" id="revInputComment" rows="3" placeholder="Tuliskan pengalaman manismu pesan di Kedai Kyuti..." required maxlength="400" style="font-size:12px;line-height:1.4"></textarea>
                    </div>

                    <!-- Input Foto Pesanan ala Shopee (Fitur Baru) -->
                    <div style="margin-bottom:14px">
                        <label style="font-size:11px;font-weight:700;color:var(--text-muted);display:block;margin-bottom:4px">
                            📷 Foto Pesanan Kamu (Opsional - ala Shopee)
                        </label>
                        <div class="review-photo-upload-wrap">
                            <label for="revInputPhoto" class="rev-photo-pick-box" id="revPhotoPickLabel">
                                <span style="font-size:20px">📷</span>
                                <span style="font-size:10.5px;font-weight:800">+ Foto Pesanan</span>
                                <small style="font-size:9px;color:var(--text-muted)">Kamera / Galeri</small>
                            </label>
                            <input type="file" id="revInputPhoto" accept="image/png,image/jpeg,image/webp,image/jpg" style="display:none">
                            
                            <!-- Thumbnail Preview (Hidden until image selected) -->
                            <div class="rev-photo-preview-wrap" id="revPhotoPreviewWrap" style="display:none">
                                <img src="" id="revPhotoPreviewImg" alt="Preview Foto">
                                <button type="button" class="rev-photo-del-btn" id="revPhotoDelBtn" title="Hapus Foto">&times;</button>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px">
                        <button type="button" class="btn btn-secondary" id="cancelReviewBtn" style="flex:1;font-size:12px">Batal</button>
                        <button type="submit" class="btn btn-primary" id="submitReviewBtn" style="flex:2;font-size:13px;font-weight:800">
                            <span>Kirim Ulasan 🌸</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Banner Bukti Testimoni Telegram -->
            <a href="https://t.me/kedaikyuti" target="_blank" rel="noopener" class="telegram-testi-banner">
                <div class="ttb-icon">📸</div>
                <div class="ttb-info">
                    <strong>Channel Telegram Testi @kedaikyuti</strong>
                    <small>Lihat ratusan foto paket asli &amp; bukti transfer customer ↗</small>
                </div>
            </a>

            <!-- Reviews List Container -->
            <div class="reviews-list-wrap" id="reviewsListWrap">
                <div style="text-align:center;padding:24px 0;color:var(--text-muted)">
                    <span class="spinner"></span> Memuat ulasan customer...
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Lightbox Preview Foto Review ala Shopee -->
<div class="sheet-backdrop dialog-backdrop" id="reviewPhotoLightboxModal" style="display:none">
    <div class="dialog" style="max-width:400px;padding:12px;background:#18181B;border-radius:var(--radius-lg);position:relative">
        <button type="button" class="sheet-close" id="closeReviewLightboxBtn" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.25);color:#fff;border:none;width:32px;height:32px;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center">&times;</button>
        <img src="" id="lightboxImg" style="width:100%;max-height:75vh;object-fit:contain;border-radius:var(--radius-md);display:block" alt="Foto Pesanan Customer">
        <div style="text-align:center;padding:10px 0 2px;color:#E4E4E7;font-size:12px;font-weight:700">
            📸 Foto Pesanan Asli Sahabat Kedai Kyuti
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- 13. MODAL JAM OPERASIONAL WEB TUTUP (Fitur Baru Permintaan Client) -->
<!-- ============================================================= -->
<div class="sheet-backdrop alert-modal-backdrop" id="webClosedModal">
    <div class="alert-card" style="max-width:370px;text-align:center">
        <button class="sheet-close" data-close="webClosedModal" aria-label="Tutup" style="position:absolute;right:14px;top:14px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;background:var(--bg-card-subtle);border:1px solid var(--border-color);border-radius:var(--radius-full);color:var(--text-muted);font-size:16px;cursor:pointer">&times;</button>
        
        <div class="alert-icon-wrap" style="background:#FFE4E6;color:#E11D48;margin:0 auto 12px">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        
        <h3 class="alert-title" style="margin-bottom:6px;font-size:16px">Di Luar Jam Operasional Web ⏰</h3>
        
        <div style="display:inline-flex;align-items:center;gap:6px;background:var(--bg-card-subtle);border:1px solid var(--border-color);border-radius:20px;padding:4px 12px;margin-bottom:12px">
            <span style="font-size:12px;color:var(--brand-primary-dark);font-weight:800">
                Jam Layanan: <span id="webClosedHoursLabel">09.00 - 12.00 WIB</span>
            </span>
        </div>

        <p class="alert-desc" id="webClosedMessageText" style="font-size:12px;line-height:1.5;margin-bottom:16px;color:var(--text-main)">
            ♡ Maaf, jam operasional pemesanan Kedai Kyuti saat ini sedang tutup. Pemesanan dibuka pukul 09.00 - 12.00 WIB. Kamu tetap bisa melihat-lihat menu terlebih dahulu ya! ♡
        </p>

        <div style="display:flex;flex-direction:column;gap:8px">
            <button class="btn btn-secondary btn-block" data-close="webClosedModal" type="button" style="padding:10px;font-size:13px;font-weight:700">
                Lihat-Lihat Menu Dulu ☕
            </button>
            <a href="https://wa.me/628972276154" target="_blank" rel="noopener" class="btn btn-wa btn-block" style="text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;font-size:13px;font-weight:800;border-radius:var(--radius-full)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8.2 8.2 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2.1c-.2-.3 0-.5.1-.6l.5-.6.3-.5a.6.6 0 0 0 0-.5l-.9-2.3c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .8 2.5.7 3 .6a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.2-.3-.3-.6-.4zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.2A11.8 11.8 0 0 0 1.7 17.8L0 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6z"/></svg>
                <span>Tanya Admin di WhatsApp</span>
            </a>
        </div>
    </div>
</div>


