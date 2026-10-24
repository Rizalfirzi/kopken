<!-- ================= MODALS & POPUPS KEDAI KYUTI ================= -->

<!-- 1. WELCOME POPUP (Revisi Poin 4: Pas buka web muncul seperti ini) -->
<div class="sheet-backdrop welcome-modal-backdrop" id="welcomeModal">
    <div class="welcome-card">
        <div class="wc-header">
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

            <div class="wc-thankyou">
                🩰 <i>𝒯𝒽𝒶𝓃𝓀 𝓎𝑜𝓊 𝒻𝑜𝓇 𝒸𝒽𝑜𝑜𝓈𝒾𝓃𝑔 𝓀𝑒𝒹𝒶𝒾 𝓀𝓎𝓊𝓉𝒾!</i> 🩰
            </div>
            <p class="wc-sub-prompt">Yukk, lanjut order kyuties! 💕</p>
        </div>

        <div class="wc-action">
            <button class="btn btn-primary btn-block wc-btn" id="closeWelcomeModalBtn" type="button">
                <span>Okii, pilih outlet dulu ♡</span>
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
            Harga di <b id="premiumOutletNameText">Outlet</b> lebih mahal dari outlet biasa (mis: mall, stasiun, bandara), jadi harga menu menyesuaikan. Satuan &amp; Bundling tidak berlaku di outlet ini.
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

            <!-- Size Selector -->
            <div class="option-group" id="optGroupSize">
                <div class="og-label">Ukuran Cup</div>
                <div class="og-pills" id="optPillsSize"></div>
            </div>

            <!-- Temperature Selector -->
            <div class="option-group" id="optGroupTemp">
                <div class="og-label">Suhu Minuman</div>
                <div class="og-pills" id="optPillsTemp"></div>
            </div>

            <!-- Ice Level -->
            <div class="option-group" id="optGroupIce">
                <div class="og-label">Jumlah Es (Ice Level)</div>
                <div class="og-pills" id="optPillsIce"></div>
            </div>

            <!-- Sugar Level -->
            <div class="option-group" id="optGroupSugar">
                <div class="og-label">Tingkat Manis (Sugar Level)</div>
                <div class="og-pills" id="optPillsSugar"></div>
            </div>

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

            <!-- Bundling Slots Container (Rendered dynamically by JS) -->
            <div id="bundleSlotsContainer"></div>

            <!-- Sugar & Ice Global Preferences for Bundle -->
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

            <!-- Pilihan Kantung Belanja Plastik (Revisi Poin 10: +Rp 1.000) -->
            <div class="checkbox-addon-box">
                <label class="checkbox-label" for="plasticBagCheckbox">
                    <input type="checkbox" id="plasticBagCheckbox">
                    <span class="custom-chk"></span>
                    <span class="chk-text">Pakai kantung belanja <b>+Rp 1.000</b></span>
                </label>
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

                <!-- Fee 1 pcs Row (Poin 8) -->
                <div class="pbc-row" id="singleFeeRow" style="display:none;color:var(--brand-accent)">
                    <span>Fee Pesanan 1 pcs</span>
                    <b id="summarySingleFee">+Rp 2.000</b>
                </div>
                <div class="pbc-row" id="freeFeeRow" style="color:var(--success);font-size:12px">
                    <span>Fee Pesanan Satuan</span>
                    <b>Bebas Fee (Order 2+ pcs) ♡</b>
                </div>

                <!-- Plastic Bag Row (Poin 10) -->
                <div class="pbc-row" id="plasticFeeRow" style="display:none">
                    <span>Kantung Belanja Plastik</span>
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
