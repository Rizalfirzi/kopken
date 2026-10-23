<!-- ================= MODALS & SHEETS ================= -->

<!-- 1. Outlet Selection Sheet -->
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
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7.5"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></svg>
                    <input type="search" id="outletSearchInput" placeholder="Cari nama outlet, jalan, atau kota..." autocomplete="off">
                </div>
            </div>

            <button class="geo-btn" id="geoBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                <span>Pakai Lokasi Saya (Cari Outlet Terdekat)</span>
            </button>

            <div class="outlet-list-items" id="outletListContainer">
                <!-- Dynamically rendered by app.js -->
            </div>
        </div>
    </div>
</div>

<!-- 2. Product Customization Sheet -->
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
                        <span id="modalProductPrice" style="font-size:18px;font-weight:900;color:var(--brand-primary)">Rp 0</span>
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

<!-- 3. Cart & Checkout Sheet -->
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

            <div class="form-group">
                <label class="form-label" for="pickupSelect">Waktu Pengambilan (Pickup)</label>
                <select class="form-input" id="pickupSelect">
                    <option value="Sekarang">Sekarang (Langsung dibuatkan)</option>
                    <option value="15 Menit Lagi">15 Menit Lagi</option>
                    <option value="30 Menit Lagi">30 Menit Lagi</option>
                    <option value="1 Jam Lagi">1 Jam Lagi</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="orderNotes">Catatan Pesanan Keseluruhan</label>
                <textarea class="form-textarea" id="orderNotes" rows="2" placeholder="Catatan tambahan untuk barista..."></textarea>
            </div>

            <!-- Price Breakdown -->
            <div style="background:var(--bg-card-subtle);border-radius:var(--radius-md);padding:14px;margin-top:14px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                    <span>Subtotal</span>
                    <b id="summarySubtotal">Rp 0</b>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:var(--success)">
                    <span>Diskon Voucher</span>
                    <b id="summaryDiscount">-Rp 0</b>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:900;border-top:1px dashed var(--border-color);padding-top:8px">
                    <span>Total Pembayaran</span>
                    <b id="summaryTotal" style="color:var(--brand-primary)">Rp 0</b>
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

<!-- 4. Order Tracking Sheet -->
<div class="sheet-backdrop" id="trackSheet">
    <div class="sheet">
        <div class="sheet-handle"></div>
        <div class="sheet-header">
            <div class="sheet-title">
                <h3>Lacak Status Pesanan</h3>
                <small>Cek status pesanan Kopi Kenangan kamu</small>
            </div>
            <button class="sheet-close" data-close="trackSheet" aria-label="Tutup">✕</button>
        </div>
        <div class="sheet-body">
            <div style="display:flex;gap:8px;margin-bottom:16px">
                <input class="form-input" id="trackCodeInput" placeholder="Masukkan Kode Pesanan (misal: KK-DEMO01)" style="text-transform:uppercase">
                <button class="btn btn-primary" id="trackSubmitBtn" style="flex-shrink:0">Lacak</button>
            </div>

            <div id="trackResultBox">
                <!-- Result dynamically injected by JavaScript -->
                <div class="empty-box">
                    <div class="empty-icon">🔍</div>
                    <p>Masukkan kode pesanan untuk melihat status peracikan minumanmu.</p>
                </div>
            </div>
        </div>
    </div>
</div>
