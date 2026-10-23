/**
 * Kopi Kenangan Native JavaScript Engine
 * Handles State, API Integration, Modals, Cart, Geolocation, and WhatsApp Checkout.
 */

// Initialize Application State
const AppState = {
    outlet: window.INITIAL_DATA?.outlet || null,
    outlets: window.INITIAL_DATA?.outlets || [],
    menu: window.INITIAL_DATA?.menu || { items: [], stats: {} },
    cart: JSON.parse(localStorage.getItem('kk_cart') || '[]'),
    activeCategory: 'all',
    searchQuery: '',
    selectedProduct: null,
    selectedSize: 'Regular',
    selectedTemp: 'Ice',
    selectedIce: 'Normal Ice',
    selectedSugar: 'Normal Sugar',
    selectedQty: 1,
};

// Currency Formatting Helper
const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');

// Toast Notification
function showToast(msg) {
    const wrap = document.getElementById('toastWrap');
    if (!wrap) return;
    wrap.innerHTML = `<div class="toast"><span>☕</span><span>${msg}</span></div>`;
    setTimeout(() => { wrap.innerHTML = ''; }, 3200);
}

// Theme Controller (Light / Dark)
function initTheme() {
    const saved = localStorage.getItem('kk_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
}

const themeToggleBtn = document.getElementById('themeToggleBtn');
if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', current);
        localStorage.setItem('kk_theme', current);
    });
}
initTheme();

// Modal & Bottom Sheet Controllers
function openSheet(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeSheet(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeSheet(btn.dataset.close));
});

document.querySelectorAll('.sheet-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) backdrop.classList.remove('active');
    });
});

// Update Outlet Header Info
function updateOutletHeader() {
    if (!AppState.outlet) return;
    const nameEl = document.getElementById('currentOutletName');
    const statusEl = document.getElementById('currentOutletStatus');
    const hoursEl = document.getElementById('currentOutletHours');
    const barOutletEl = document.getElementById('cartBarOutletName');
    const sheetOutletEl = document.getElementById('cartSheetOutletName');

    if (nameEl) nameEl.textContent = AppState.outlet.name;
    if (statusEl) {
        statusEl.textContent = AppState.outlet.statusText || 'Buka';
        statusEl.className = 'pill ' + (AppState.outlet.canOrder ? 'pill-open' : 'pill-closed');
    }
    if (hoursEl) hoursEl.textContent = '• ' + (AppState.outlet.hours || 'Buka Setiap Hari');
    if (barOutletEl) barOutletEl.textContent = AppState.outlet.name;
    if (sheetOutletEl) sheetOutletEl.textContent = AppState.outlet.name;
}

// Render Outlets in Picker Sheet
function renderOutletList(items) {
    const container = document.getElementById('outletListContainer');
    if (!container) return;

    if (!items || !items.length) {
        container.innerHTML = '<div class="empty-box"><p>Outlet tidak ditemukan.</p></div>';
        return;
    }

    container.innerHTML = items.map(o => `
        <div class="outlet-item ${AppState.outlet?.code === o.code ? 'active' : ''}" onclick="selectOutlet('${o.code}')">
            <img class="outlet-thumb" src="${o.img || 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae'}" alt="${o.name}" onerror="this.src='https://placehold.co/100x100?text=Kopi'">
            <div class="outlet-item-body">
                <div class="oib-title">
                    <span>${o.name}</span>
                    ${o.distanceKm ? `<span class="oib-distance">${o.distanceKm} km</span>` : ''}
                </div>
                <div class="oib-address">${o.address || ''}</div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <span class="pill ${o.canOrder ? 'pill-open' : 'pill-closed'}">${o.statusText || 'Buka'}</span>
                    <span style="font-size:11px;color:var(--text-muted)">${o.hours || ''}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// Live Search Outlets via API
let outletSearchTimer;
const outletSearchInput = document.getElementById('outletSearchInput');
if (outletSearchInput) {
    outletSearchInput.addEventListener('input', (e) => {
        clearTimeout(outletSearchTimer);
        const query = e.target.value.trim();
        outletSearchTimer = setTimeout(() => {
            fetch(`api/outlets.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    renderOutletList(data.outlets || []);
                })
                .catch(() => {
                    showToast('Gagal mencari outlet.');
                });
        }, 280);
    });
}

// Geo Location Trigger
const geoBtn = document.getElementById('geoBtn');
if (geoBtn) {
    geoBtn.addEventListener('click', () => {
        if (!navigator.geolocation) {
            showToast('Browser kamu belum mendukung akses lokasi');
            return;
        }
        geoBtn.innerHTML = '<span>Mencari lokasi kamu...</span>';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                fetch(`api/outlets.php?lat=${lat}&lng=${lng}`)
                    .then(r => r.json())
                    .then(data => {
                        geoBtn.innerHTML = '<span>✓ Lokasi Ditemukan (Urut Berdasarkan Terdekat)</span>';
                        AppState.outlets = data.outlets;
                        renderOutletList(data.outlets);
                    })
                    .catch(() => {
                        geoBtn.innerHTML = '<span>✕ Gagal memuat outlet terdekat</span>';
                    });
            },
            () => {
                geoBtn.innerHTML = '<span>✕ Izin lokasi ditolak</span>';
                setTimeout(() => {
                    geoBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg><span>Pakai Lokasi Saya (Cari Outlet Terdekat)</span>';
                }, 2000);
            }
        );
    });
}

// Select Outlet & Trigger Live Menu Fetch
window.selectOutlet = function(code) {
    const found = AppState.outlets.find(o => o.code === code);
    if (!found) return;

    AppState.outlet = found;
    updateOutletHeader();
    closeSheet('outletSheet');
    showToast(`Memuat stok live untuk: ${found.name}...`);

    fetchMenu(found.code, false);
};

// Fetch Menu API
function fetchMenu(outletCode, forceRefresh = false) {
    const refreshBtn = document.getElementById('refreshStockBtn');
    if (refreshBtn) refreshBtn.classList.add('spinning');

    fetch(`api/menu.php?outlet=${encodeURIComponent(outletCode)}&refresh=${forceRefresh ? 1 : 0}`)
        .then(res => res.json())
        .then(data => {
            AppState.menu = data;
            
            // Update Stats
            const totalEl = document.getElementById('statTotalItems');
            const readyEl = document.getElementById('statReadyItems');
            const soldEl = document.getElementById('statSoldItems');
            const sourceEl = document.getElementById('liveSourceText');
            const syncEl = document.getElementById('lastSyncTime');

            if (totalEl) totalEl.textContent = data.stats?.total || 0;
            if (readyEl) readyEl.textContent = data.stats?.available || 0;
            if (soldEl) soldEl.textContent = data.stats?.soldOut || 0;
            if (sourceEl) sourceEl.textContent = data.live ? `Stok Live dari Outlet: ${AppState.outlet?.name}` : 'Mode Offline / Fallback';
            if (syncEl) syncEl.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' WIB';

            renderMenu();
            showToast(forceRefresh ? '✓ Stok outlet berhasil diperbarui!' : `Stok siap untuk ${AppState.outlet?.name}`);
        })
        .catch(() => {
            showToast('Gagal memuat stok outlet.');
        })
        .finally(() => {
            if (refreshBtn) refreshBtn.classList.remove('spinning');
        });
}

// Live Refresh Button
const refreshStockBtn = document.getElementById('refreshStockBtn');
if (refreshStockBtn) {
    refreshStockBtn.addEventListener('click', () => {
        if (AppState.outlet?.code) {
            fetchMenu(AppState.outlet.code, true);
        }
    });
}

// Category Filtering
document.querySelectorAll('.cat-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        AppState.activeCategory = btn.dataset.cat;
        renderMenu();
    });
});

// Search Filtering
const menuSearchInput = document.getElementById('menuSearchInput');
if (menuSearchInput) {
    menuSearchInput.addEventListener('input', (e) => {
        AppState.searchQuery = e.target.value.toLowerCase().trim();
        renderMenu();
    });
}

// Render Menu Cards
function renderMenu() {
    const container = document.getElementById('menuContainer');
    if (!container) return;

    const items = AppState.menu?.items || [];
    
    // Filter by category & search query
    const filtered = items.filter(item => {
        const matchesCat = AppState.activeCategory === 'all' || item.category === AppState.activeCategory;
        const matchesSearch = !AppState.searchQuery || 
            item.name.toLowerCase().includes(AppState.searchQuery) ||
            (item.desc && item.desc.toLowerCase().includes(AppState.searchQuery));
        return matchesCat && matchesSearch;
    });

    if (!filtered.length) {
        container.innerHTML = `
            <div class="empty-box">
                <div class="empty-icon">☕</div>
                <h4>Menu Tidak Ditemukan</h4>
                <p>Coba kata kunci pencarian lain atau pilih kategori berbeda.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="sec-title">
            <span>Menampilkan ${filtered.length} menu</span>
        </div>
        <div class="menu-grid">
            ${filtered.map(item => {
                const isSold = item.isSoldOut || item.notCarried;
                return `
                    <div class="product-card ${isSold ? 'sold-out' : ''}" onclick="openProductDetail('${item.key || item.id}')">
                        <div class="product-img-wrap">
                            <img src="${item.img || 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae'}" alt="${item.name}" loading="lazy" onerror="this.src='https://placehold.co/200x200?text=Kopi+Kenangan'">
                            <span class="stock-badge ${isSold ? 'empty' : 'ready'}">
                                ${isSold ? '✕ Habis di Outlet' : '● Stok Ready'}
                            </span>
                            ${item.tag ? `<span class="promo-tag">${item.tag}</span>` : ''}
                        </div>
                        <h4 class="product-name">${item.name}</h4>
                        <div class="product-cat">${item.category || 'Minuman'}</div>
                        <div class="product-price-row">
                            <span class="product-price">${formatRp(item.price)}</span>
                            ${item.normalPrice && item.normalPrice > item.price ? `<span class="product-normal-price">${formatRp(item.normalPrice)}</span>` : ''}
                        </div>
                        <button class="product-add-btn" type="button">
                            ${isSold ? 'Stok Habis' : '+ Tambah'}
                        </button>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// Product Customization Modal
window.openProductDetail = function(key) {
    const item = (AppState.menu?.items || []).find(i => (i.key === key || i.id === key));
    if (!item) return;

    AppState.selectedProduct = item;
    AppState.selectedSize = 'Regular';
    AppState.selectedTemp = item.options?.temps?.[0] || 'Ice';
    AppState.selectedIce = item.options?.ices?.[0] || 'Normal Ice';
    AppState.selectedSugar = item.options?.sugars?.[0] || 'Normal Sugar';
    AppState.selectedQty = 1;

    document.getElementById('modalProductName').textContent = item.name;
    document.getElementById('modalProductCat').textContent = item.category || 'Menu';
    document.getElementById('modalProductImg').src = item.img || 'https://placehold.co/200x200?text=Kopi';
    document.getElementById('modalProductDesc').textContent = item.desc || 'Dibuat dengan bahan pilihan dan racikan khas Kopi Kenangan.';
    document.getElementById('modalProductPrice').textContent = formatRp(item.price);
    document.getElementById('modalProductNormalPrice').textContent = item.normalPrice && item.normalPrice > item.price ? formatRp(item.normalPrice) : '';
    document.getElementById('modalItemNote').value = '';
    document.getElementById('qtyVal').textContent = '1';

    const isSold = item.isSoldOut || item.notCarried;
    const addBtn = document.getElementById('addToCartBtn');
    if (isSold) {
        document.getElementById('modalStockStatus').innerHTML = '<span class="pill pill-closed">✕ Stok Habis di Outlet Ini</span>';
        addBtn.disabled = true;
        addBtn.textContent = 'Menu Ini Sedang Habis di Outlet';
        addBtn.style.opacity = '0.6';
    } else {
        document.getElementById('modalStockStatus').innerHTML = '<span class="pill pill-open">● Stok Ready / Tersedia</span>';
        addBtn.disabled = false;
        addBtn.textContent = 'Tambahkan ke Keranjang';
        addBtn.style.opacity = '1';
    }

    // Render Options
    renderOptionGroup('optGroupSize', 'optPillsSize', item.options?.sizes || ['Regular', 'Large'], AppState.selectedSize, (val) => {
        AppState.selectedSize = val;
        const currentPrice = val === 'Large' && item.largePrice ? item.largePrice : item.price;
        document.getElementById('modalProductPrice').textContent = formatRp(currentPrice);
    });

    renderOptionGroup('optGroupTemp', 'optPillsTemp', item.options?.temps || [], AppState.selectedTemp, (val) => {
        AppState.selectedTemp = val;
    });

    renderOptionGroup('optGroupIce', 'optPillsIce', item.options?.ices || [], AppState.selectedIce, (val) => {
        AppState.selectedIce = val;
    });

    renderOptionGroup('optGroupSugar', 'optPillsSugar', item.options?.sugars || [], AppState.selectedSugar, (val) => {
        AppState.selectedSugar = val;
    });

    openSheet('productSheet');
};

function renderOptionGroup(groupId, pillsId, options, currentValue, onSelect) {
    const groupEl = document.getElementById(groupId);
    const pillsEl = document.getElementById(pillsId);
    if (!options || !options.length) {
        groupEl.style.display = 'none';
        return;
    }
    groupEl.style.display = 'block';
    pillsEl.innerHTML = options.map(opt => `
        <button class="opt-btn ${opt === currentValue ? 'active' : ''}" type="button" onclick="selectOption('${groupId}', '${pillsId}', '${opt}')">${opt}</button>
    `).join('');

    window[`callback_${pillsId}`] = onSelect;
}

window.selectOption = function(groupId, pillsId, value) {
    document.querySelectorAll(`#${pillsId} .opt-btn`).forEach(b => {
        b.classList.toggle('active', b.textContent.trim() === value);
    });
    if (window[`callback_${pillsId}`]) {
        window[`callback_${pillsId}`](value);
    }
};

// Quantity Steppers
const qtyMinus = document.getElementById('qtyMinus');
const qtyPlus = document.getElementById('qtyPlus');
if (qtyMinus) {
    qtyMinus.addEventListener('click', () => {
        if (AppState.selectedQty > 1) {
            AppState.selectedQty--;
            document.getElementById('qtyVal').textContent = AppState.selectedQty;
        }
    });
}
if (qtyPlus) {
    qtyPlus.addEventListener('click', () => {
        AppState.selectedQty++;
        document.getElementById('qtyVal').textContent = AppState.selectedQty;
    });
}

// Add to Cart Action
const addToCartBtn = document.getElementById('addToCartBtn');
if (addToCartBtn) {
    addToCartBtn.addEventListener('click', () => {
        const item = AppState.selectedProduct;
        if (!item) return;

        const unitPrice = AppState.selectedSize === 'Large' && item.largePrice ? item.largePrice : item.price;
        const note = document.getElementById('modalItemNote').value.trim();

        const cartItem = {
            id: (item.key || item.id) + '_' + Date.now(),
            key: item.key || item.id,
            name: item.name,
            price: unitPrice,
            qty: AppState.selectedQty,
            size: item.options?.sizes?.length ? AppState.selectedSize : null,
            temp: item.options?.temps?.length ? AppState.selectedTemp : null,
            ice: item.options?.ices?.length ? AppState.selectedIce : null,
            sugar: item.options?.sugars?.length ? AppState.selectedSugar : null,
            note: note,
            subtotal: unitPrice * AppState.selectedQty,
        };

        AppState.cart.push(cartItem);
        saveCart();
        closeSheet('productSheet');
        showToast(`✓ Ditambahkan: ${item.name} (${AppState.selectedQty}x)`);
    });
}

// Cart Storage & Calculations
function saveCart() {
    localStorage.setItem('kk_cart', JSON.stringify(AppState.cart));
    updateCartUI();
}

function updateCartUI() {
    const totalQty = AppState.cart.reduce((sum, i) => sum + i.qty, 0);
    const subtotal = AppState.cart.reduce((sum, i) => sum + i.subtotal, 0);

    const floating = document.getElementById('floatingCart');
    if (totalQty > 0) {
        if (floating) floating.classList.add('visible');
        const qtyEl = document.getElementById('cartTotalQty');
        const titleEl = document.getElementById('cartBarTitle');
        const priceEl = document.getElementById('cartBarTotalPrice');
        if (qtyEl) qtyEl.textContent = totalQty;
        if (titleEl) titleEl.textContent = `${totalQty} Item Siap Dipesan`;
        if (priceEl) priceEl.textContent = formatRp(subtotal);
    } else {
        if (floating) floating.classList.remove('visible');
    }

    renderCartItems();
}

function renderCartItems() {
    const container = document.getElementById('cartItemsList');
    if (!container) return;

    if (!AppState.cart.length) {
        container.innerHTML = '<div class="empty-box"><p>Keranjang kamu masih kosong.</p></div>';
        const subtotalEl = document.getElementById('summarySubtotal');
        const totalEl = document.getElementById('summaryTotal');
        if (subtotalEl) subtotalEl.textContent = 'Rp 0';
        if (totalEl) totalEl.textContent = 'Rp 0';
        return;
    }

    container.innerHTML = AppState.cart.map((i, idx) => `
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px 14px;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <b style="font-size:13.5px;color:var(--text-main)">${i.name}</b>
                <div style="font-size:11.5px;color:var(--text-muted)">
                    ${[i.size, i.temp, i.ice, i.sugar].filter(Boolean).join(' • ')}
                </div>
                ${i.note ? `<div style="font-size:11px;color:var(--brand-primary);font-style:italic">"${i.note}"</div>` : ''}
                <div style="font-size:12px;font-weight:700;color:var(--brand-primary);margin-top:2px">
                    ${formatRp(i.price)} × ${i.qty} = ${formatRp(i.subtotal)}
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <button class="icon-btn" onclick="adjustCartQty(${idx}, -1)" style="width:28px;height:28px">-</button>
                <span style="font-weight:700;font-size:13px;min-width:16px;text-align:center">${i.qty}</span>
                <button class="icon-btn" onclick="adjustCartQty(${idx}, 1)" style="width:28px;height:28px">+</button>
            </div>
        </div>
    `).join('');

    const subtotal = AppState.cart.reduce((sum, i) => sum + i.subtotal, 0);
    const subtotalEl = document.getElementById('summarySubtotal');
    const totalEl = document.getElementById('summaryTotal');
    if (subtotalEl) subtotalEl.textContent = formatRp(subtotal);
    if (totalEl) totalEl.textContent = formatRp(subtotal);
}

window.adjustCartQty = function(index, delta) {
    if (AppState.cart[index]) {
        AppState.cart[index].qty += delta;
        if (AppState.cart[index].qty <= 0) {
            AppState.cart.splice(index, 1);
        } else {
            AppState.cart[index].subtotal = AppState.cart[index].price * AppState.cart[index].qty;
        }
        saveCart();
    }
};

const clearCartBtn = document.getElementById('clearCartBtn');
if (clearCartBtn) {
    clearCartBtn.addEventListener('click', () => {
        AppState.cart = [];
        saveCart();
        showToast('Keranjang dikosongkan.');
    });
}

// Open Cart Sheet
const floatingCart = document.getElementById('floatingCart');
if (floatingCart) {
    floatingCart.addEventListener('click', () => {
        openSheet('cartSheet');
    });
}

// Checkout via WhatsApp & Backend Submit
const checkoutWaBtn = document.getElementById('checkoutWaBtn');
if (checkoutWaBtn) {
    checkoutWaBtn.addEventListener('click', () => {
        const nameInput = document.getElementById('custName');
        const name = nameInput ? nameInput.value.trim() : '';
        if (!name) {
            showToast('Mohon isi nama pemesan terlebih dahulu');
            if (nameInput) nameInput.focus();
            return;
        }

        if (!AppState.cart.length) {
            showToast('Keranjang kamu masih kosong');
            return;
        }

        const phoneInput = document.getElementById('custPhone');
        const pickupInput = document.getElementById('pickupSelect');
        const notesInput = document.getElementById('orderNotes');

        const payload = {
            customerName: name,
            customerPhone: phoneInput ? phoneInput.value.trim() : '',
            outletCode: AppState.outlet?.code || 'LA.CODEFIN',
            outletName: AppState.outlet?.name || 'Kopi Kenangan',
            pickupTime: pickupInput ? pickupInput.value : 'Sekarang',
            notes: notesInput ? notesInput.value.trim() : '',
            items: AppState.cart,
            subtotal: AppState.cart.reduce((s, i) => s + i.subtotal, 0),
            total: AppState.cart.reduce((s, i) => s + i.subtotal, 0),
        };

        checkoutWaBtn.textContent = 'Memproses...';
        checkoutWaBtn.disabled = true;

        fetch('api/order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Save order history in localStorage (Zero Database Needed)
                localStorage.setItem('kk_last_order', data.orderCode);
                const orderHistory = JSON.parse(localStorage.getItem('kk_orders') || '[]');
                orderHistory.unshift(data.order);
                localStorage.setItem('kk_orders', JSON.stringify(orderHistory.slice(0, 30)));

                AppState.cart = [];
                saveCart();
                closeSheet('cartSheet');

                showToast(`✓ Pesanan dibuat: ${data.orderCode}`);
                // Open WhatsApp directly in new window
                window.open(data.waUrl, '_blank');
            } else {
                showToast(data.message || 'Gagal memproses pesanan.');
            }
        })
        .catch(() => {
            showToast('Terjadi kesalahan jaringan.');
        })
        .finally(() => {
            checkoutWaBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8.2 8.2 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2.1c-.2-.3 0-.5.1-.6l.5-.6.3-.5a.6.6 0 0 0 0-.5l-.9-2.3c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .8 2.5.7 3 .6a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.2-.3-.3-.6-.4zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.2A11.8 11.8 0 0 0 1.7 17.8L0 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6z"/></svg>
                <span>Pesan via WhatsApp</span>
            `;
            checkoutWaBtn.disabled = false;
        });
    });
}

// Order Tracking System (LocalStorage + API)
const trackOrderBtn = document.getElementById('trackOrderBtn');
if (trackOrderBtn) {
    trackOrderBtn.addEventListener('click', () => {
        const last = localStorage.getItem('kk_last_order');
        if (last) {
            const input = document.getElementById('trackCodeInput');
            if (input) input.value = last;
            performTrack(last);
        }
        openSheet('trackSheet');
    });
}

const trackSubmitBtn = document.getElementById('trackSubmitBtn');
if (trackSubmitBtn) {
    trackSubmitBtn.addEventListener('click', () => {
        const input = document.getElementById('trackCodeInput');
        const code = input ? input.value.trim() : '';
        if (!code) {
            showToast('Masukkan kode pesanan');
            return;
        }
        performTrack(code);
    });
}

function performTrack(code) {
    const resultBox = document.getElementById('trackResultBox');
    if (!resultBox) return;

    resultBox.innerHTML = '<div class="empty-box"><p>Mencari status pesanan...</p></div>';

    const renderCard = (o) => {
        resultBox.innerHTML = `
            <div style="background:var(--bg-card-subtle);border-radius:var(--radius-md);padding:16px;border:1px solid var(--border-color)">
                <div class="track-stepper">
                    <div class="step-line"></div>
                    <div class="track-step active">
                        <div class="step-circle">✓</div>
                        <span class="step-label">Diterima</span>
                    </div>
                    <div class="track-step active">
                        <div class="step-circle">☕</div>
                        <span class="step-label">Diproses</span>
                    </div>
                    <div class="track-step">
                        <div class="step-circle">🛍️</div>
                        <span class="step-label">Siap Ambil</span>
                    </div>
                </div>

                <div style="text-align:center;margin:16px 0 20px">
                    <h4 style="font-size:16px;color:var(--brand-primary);font-weight:800">${o.statusText || 'Pesanan Sedang Disiapkan'}</h4>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:2px">${o.statusDesc || 'Barista sedang meracik pesanan kopimu dengan cinta.'}</p>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;font-size:12.5px;border-top:1px dashed var(--border-color);padding-top:12px">
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Kode Pesanan</span>
                        <b>${o.code || o.orderCode}</b>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Outlet</span>
                        <span>${o.outletName || 'Kopi Kenangan'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Nama Pemesan</span>
                        <span>${o.customerName || 'Sahabat Kenangan'}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:var(--text-muted)">Total Pembayaran</span>
                        <b style="color:var(--brand-primary)">${formatRp(o.total)}</b>
                    </div>
                </div>
            </div>
        `;
    };

    // 1. Check local order history first
    const localOrders = JSON.parse(localStorage.getItem('kk_orders') || '[]');
    const localMatch = localOrders.find(o => (o.orderCode || o.code || '').toUpperCase() === code.toUpperCase());
    if (localMatch) {
        renderCard(localMatch);
        return;
    }

    // 2. Fetch from tracking API
    fetch(`api/track.php?code=${encodeURIComponent(code)}`)
        .then(r => r.json())
        .then(data => {
            if (data.found && data.order) {
                renderCard(data.order);
            } else {
                resultBox.innerHTML = `
                    <div class="empty-box">
                        <div class="empty-icon">✕</div>
                        <h4>Pesanan Tidak Ditemukan</h4>
                        <p>Pastikan kode pesanan <b>${code}</b> sudah benar.</p>
                    </div>
                `;
            }
        })
        .catch(() => {
            resultBox.innerHTML = '<div class="empty-box"><p>Gagal menghubungi server pelacakan.</p></div>';
        });
}

// Initial Bootstrapping
const outletCardBtn = document.getElementById('outletCardBtn');
if (outletCardBtn) {
    outletCardBtn.addEventListener('click', () => {
        renderOutletList(AppState.outlets);
        openSheet('outletSheet');
    });
}

updateOutletHeader();
renderMenu();
updateCartUI();
