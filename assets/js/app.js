/**
 * Kedai Kyuti - Native JavaScript Application Engine
 * Domain: kedaikyuti.my.id
 * Handles State, API Integration, Modals, Bundling, Cart, Pickup Time & WhatsApp Checkout.
 */

// Initialize Application State
const AppState = {
    view: 'hub', // 'hub' or 'store'
    outlet: window.INITIAL_DATA?.outlet || null,
    outlets: window.INITIAL_DATA?.outlets || [],
    menu: window.INITIAL_DATA?.menu || { items: [], stats: {} },
    bundlingItems: window.INITIAL_DATA?.bundlingItems || [],
    cart: JSON.parse(localStorage.getItem('kyuti_cart') || '[]'),
    activeCategory: 'all',
    searchQuery: '',
    selectedProduct: null,
    selectedSize: 'Regular',
    selectedTemp: 'Ice',
    selectedIce: 'Normal Ice',
    selectedSugar: 'Normal Sugar',
    selectedQty: 1,

    // Fee & Addon Settings
    singleItemFeeAmount: window.INITIAL_DATA?.singleItemFee || 2000,
    plasticBagFeeAmount: window.INITIAL_DATA?.plasticBagFee || 1000,
    hasPlasticBag: false,
    pickupType: 'Sekarang', // 'Sekarang' or 'Atur Jam'
    pickupTimezone: 'WIB',
    pickupTime: '',

    // Active Bundling Customization
    activeBundle: null,
    bundleSlotChoices: {},
    bundleIce: 'Normal Ice',
    bundleSugar: 'Normal Sugar',

    // Pending Single Item for 1pcs Fee Modal
    pendingCartItem: null,
};

// Global Outlet Store Map for Fast Lookups
const outletStore = new Map();
if (AppState.outlet && AppState.outlet.code) {
    outletStore.set(AppState.outlet.code, AppState.outlet);
}
(AppState.outlets || []).forEach(o => {
    if (o && o.code) outletStore.set(o.code, o);
});

// Currency Formatting Helper
const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');

// Toast Notification
function showToast(msg) {
    const wrap = document.getElementById('toastWrap');
    if (!wrap) return;
    wrap.innerHTML = `<div class="toast"><span>🌸</span><span>${msg}</span></div>`;
    setTimeout(() => { wrap.innerHTML = ''; }, 3200);
}

// Modal Controllers
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

// Theme Controller (Light / Dark)
function initTheme() {
    const saved = localStorage.getItem('kyuti_theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
}

const themeToggleBtn = document.getElementById('themeToggleBtn');
if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', current);
        localStorage.setItem('kyuti_theme', current);
    });
}
initTheme();

// =========================================================================
// 1. WELCOME POPUP & BIO HUB NAVIGATION (Revisi Poin 4 & 5)
// =========================================================================

// Check whether to show Welcome Popup on first load
function checkWelcomePopup() {
    const hasSeen = sessionStorage.getItem('kyuti_seen_welcome');
    if (!hasSeen) {
        openSheet('welcomeModal');
    }
}

const closeWelcomeModalBtn = document.getElementById('closeWelcomeModalBtn');
if (closeWelcomeModalBtn) {
    closeWelcomeModalBtn.addEventListener('click', () => {
        closeSheet('welcomeModal');
        sessionStorage.setItem('kyuti_seen_welcome', '1');
        // If on Hub view, user can immediately choose to open outlet or view store
        openStoreView();
        openSheet('outletSheet');
    });
}

const openWelcomeBtn = document.getElementById('openWelcomeBtn');
if (openWelcomeBtn) {
    openWelcomeBtn.addEventListener('click', () => {
        openSheet('welcomeModal');
    });
}

// Switch between Bio Hub View and Store View
function openStoreView() {
    AppState.view = 'store';
    const hub = document.getElementById('bioHubView');
    const store = document.getElementById('storeView');
    if (hub) hub.style.display = 'none';
    if (store) store.style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function openHubView() {
    AppState.view = 'hub';
    const hub = document.getElementById('bioHubView');
    const store = document.getElementById('storeView');
    if (hub) hub.style.display = 'block';
    if (store) store.style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const openPricelistBtn = document.getElementById('openPricelistBtn');
if (openPricelistBtn) {
    openPricelistBtn.addEventListener('click', openStoreView);
}

const backToHubBtn = document.getElementById('backToHubBtn');
if (backToHubBtn) {
    backToHubBtn.addEventListener('click', openHubView);
}

const navHubToggleBtn = document.getElementById('navHubToggleBtn');
if (navHubToggleBtn) {
    navHubToggleBtn.addEventListener('click', () => {
        if (AppState.view === 'store') {
            openHubView();
        } else {
            openStoreView();
        }
    });
}

// =========================================================================
// 2. OUTLET MANAGEMENT & PREMIUM OUTLET MODAL (Revisi Poin 9)
// =========================================================================

function isPremiumOutlet(outlet) {
    if (!outlet) return false;
    const cat = (outlet.category || '').toLowerCase();
    const name = (outlet.name || '').toLowerCase();
    const addr = (outlet.address || '').toLowerCase();
    const code = (outlet.code || '').toUpperCase();
    const combined = `${cat} ${name} ${addr}`;

    // 1. Gas station / SPBU (Shell, Pertamina, BP, SPBU) are regular outlets, NEVER premium
    const isGasStation = cat.includes('gas station')
        || combined.includes('spbu')
        || combined.includes('pom bensin')
        || name.startsWith('shell')
        || name.startsWith('pertamina')
        || name.startsWith('bp ');

    if (isGasStation && !combined.includes('bandara') && !combined.includes('airport')) {
        return false;
    }

    // 2. Check verified client premium outlet codes
    const premiumCodes = window.INITIAL_DATA?.premiumCodes || [
        'KK.KWG.GLHMAS', 'KK.TNG.RKBARNSRPG', 'KK.TNG.BNTROSKTR9', 'KK.JKT.GNDRKRMTPL',
        'KK.JKT.RKMTRMNJT', 'SOPODEL', 'KK.YYK.BNTRBDYA', 'KK.SBY.JMSRI',
        'SBY.AHMDYNMJO', 'KK.JKT.CKJNG', 'SAHID', 'KK.TRG.RKAMDMKSIN',
        'DOCOKK.TRG.DNUSMYNG', 'KK.TNR.RKMNGABRUKT', 'KK.JKT.RKPCNGAN', 'KK.JKT.RWBLNGJKT',
        'KK.BGR.SMBGR', 'KK.SKT.KPRABN', 'TGR.SDCSERPONG', 'BDG.CHMPLSWK',
        'JKT.CFPIM2', 'JKT.FCPIM3', 'TGR.TERASKOTA'
    ];
    if (premiumCodes.includes(code)) {
        return true;
    }

    // 3. Check verified client premium outlet names
    const premiumList = window.INITIAL_DATA?.premiumList || [
        'buaran serpong', 'bintaro sektor 9', 'galuh mas karawang', 'gandaria kramat pela',
        'matraman', 'sopo del', 'kota baru jogja', 'jemursari', 'ahmad yani surabaya',
        'cikajang', 'sahid sudirman', 'ahmad muksin tenggarong', 'danau semayang',
        'ruko mangga berau', 'pecenongan', 'rawa belong', 'summarecon bogor',
        'keprabon', 'summarecon digital center', 'cihampelas walk', 'pim 2', 'pim 3', 'teras kota'
    ];
    for (const pName of premiumList) {
        const pLower = pName.toLowerCase();
        if (name.includes(pLower) || combined.includes(pLower)) {
            if (pLower.includes('galuh mas') && isGasStation) continue;
            return true;
        }
    }

    // 4. API explicit indicator
    if (outlet.isPremium === true || outlet.premium === true) {
        return true;
    }

    // 5. Mall / Airport / Railway keywords
    const keywords = [
        'mall', 'plaza', 'bandara', 'airport', 'stasiun', 'train station',
        'railway station', 'terminal bandara', 'grand indonesia', 'tunjungan plaza',
        'ciputra world', 'senayan city', 'pacific place', 'central park', 'pakuwon'
    ];

    return keywords.some(k => combined.includes(k));
}

function checkAndShowPremiumOutletAlert(outlet) {
    if (isPremiumOutlet(outlet)) {
        const descEl = document.getElementById('premiumOutletDesc');
        if (descEl) {
            descEl.innerHTML = `Harga di <b id="premiumOutletNameText"></b> lebih mahal dari outlet biasa. Harga dan menu menyesuaikan. Untuk paket bundling tidak tersedia di outlet premium.`;
            const nameEl = descEl.querySelector('#premiumOutletNameText');
            if (nameEl) nameEl.textContent = outlet.name || 'Outlet';
        } else {
            const nameEl = document.getElementById('premiumOutletNameText');
            if (nameEl) nameEl.textContent = outlet.name || 'Outlet';
        }
        openSheet('premiumOutletModal');
    }
}

const premiumOutletOkBtn = document.getElementById('premiumOutletOkBtn');
if (premiumOutletOkBtn) {
    premiumOutletOkBtn.addEventListener('click', () => {
        closeSheet('premiumOutletModal');
    });
}

// Update Outlet Header UI
function updateOutletHeader() {
    if (!AppState.outlet) return;
    const nameEl = document.getElementById('currentOutletName');
    const statusEl = document.getElementById('currentOutletStatus');
    const hoursEl = document.getElementById('currentOutletHours');
    const barOutletEl = document.getElementById('cartBarOutletName');
    const sheetOutletEl = document.getElementById('cartSheetOutletName');
    const premiumBadge = document.getElementById('premiumOutletBadge');

    if (nameEl) nameEl.textContent = AppState.outlet.name;
    if (statusEl) {
        statusEl.textContent = AppState.outlet.statusText || 'Buka';
        statusEl.className = 'pill ' + (AppState.outlet.canOrder ? 'pill-open' : 'pill-closed');
    }
    if (hoursEl) hoursEl.textContent = '• ' + (AppState.outlet.hours || 'Buka Setiap Hari');
    if (barOutletEl) barOutletEl.textContent = AppState.outlet.name;
    if (sheetOutletEl) sheetOutletEl.textContent = AppState.outlet.name;

    if (premiumBadge) {
        premiumBadge.style.display = isPremiumOutlet(AppState.outlet) ? 'inline-flex' : 'none';
    }
}

// Render Outlet Items in Picker Sheet
function renderOutletList(items) {
    const container = document.getElementById('outletListContainer');
    if (!container) return;

    if (!items || !items.length) {
        container.innerHTML = '<div class="empty-box"><p>Outlet tidak ditemukan.</p></div>';
        return;
    }

    items.forEach(o => {
        if (o && o.code) outletStore.set(o.code, o);
    });

    container.innerHTML = items.map(o => {
        const isActive = AppState.outlet && AppState.outlet.code === o.code;
        const safeCode = (o.code || '').replace(/"/g, '&quot;');
        const safeName = (o.name || '').replace(/"/g, '&quot;');
        const safeStatus = (o.statusText || 'Buka').replace(/"/g, '&quot;');
        const safeHours = (o.hours || '').replace(/"/g, '&quot;');
        const safeImg = (o.img || 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae').replace(/"/g, '&quot;');
        const isPrem = isPremiumOutlet(o);

        return `
            <div class="outlet-item ${isActive ? 'active' : ''}" 
                 data-code="${safeCode}"
                 data-name="${safeName}"
                 data-status="${safeStatus}"
                 data-hours="${safeHours}"
                 data-canorder="${o.canOrder ? 'true' : 'false'}"
                 data-img="${safeImg}">
                <img class="outlet-thumb" src="${safeImg}" alt="${safeName}" onerror="this.src='https://placehold.co/100x100?text=Kopi'">
                <div class="outlet-item-body">
                    <div class="oib-title">
                        <span>${o.name}</span>
                        ${o.distanceKm ? `<span class="oib-distance">${o.distanceKm} km</span>` : ''}
                    </div>
                    <div class="oib-address">${o.address || ''}</div>
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <span class="pill ${o.canOrder ? 'pill-open' : 'pill-closed'}">${o.statusText || 'Buka'}</span>
                        ${isPrem ? '<span class="pill pill-premium">Outlet Premium</span>' : ''}
                        <span style="font-size:11px;color:var(--text-muted)">${o.hours || ''}</span>
                    </div>
                </div>
                ${isActive ? '<div style="color:var(--brand-primary-dark);font-weight:900;font-size:18px;display:flex;align-items:center;padding-left:4px;">✓</div>' : ''}
            </div>
        `;
    }).join('');
}

// Event Delegation for Outlet Selection
const outletListContainer = document.getElementById('outletListContainer');
if (outletListContainer) {
    outletListContainer.addEventListener('click', (e) => {
        const item = e.target.closest('.outlet-item');
        if (!item) return;
        const code = item.dataset.code;
        if (code) selectOutlet(code);
    });
}

window.selectOutlet = function(code) {
    if (!code) return;

    let found = outletStore.get(code) || (AppState.outlets || []).find(o => o.code === code);
    if (!found) {
        const el = document.querySelector(`.outlet-item[data-code="${code}"]`);
        if (el) {
            found = {
                code: code,
                name: el.dataset.name || code,
                statusText: el.dataset.status || 'Buka',
                canOrder: el.dataset.canorder === 'true',
                hours: el.dataset.hours || 'Buka Setiap Hari',
                img: el.dataset.img || ''
            };
            outletStore.set(code, found);
        }
    }

    if (!found) return;

    AppState.outlet = found;
    updateOutletHeader();

    document.querySelectorAll('.outlet-item').forEach(el => {
        el.classList.toggle('active', el.dataset.code === code);
    });

    closeSheet('outletSheet');
    showToast(`✓ Outlet aktif: ${found.name}`);

    // Check if selected outlet is Premium and show alert (Revisi Poin 9)
    checkAndShowPremiumOutletAlert(found);

    if (window.history && window.history.pushState) {
        const url = new URL(window.location.href);
        url.searchParams.set('outlet', found.code);
        window.history.pushState({ outlet: found.code }, '', url.toString());
    }

    fetchMenu(found.code, false);
};

const outletCardBtn = document.getElementById('outletCardBtn');
if (outletCardBtn) {
    outletCardBtn.addEventListener('click', () => {
        renderOutletList(AppState.outlets);
        openSheet('outletSheet');
    });
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
                    const list = data.outlets || [];
                    list.forEach(o => outletStore.set(o.code, o));
                    renderOutletList(list);
                })
                .catch(() => {
                    showToast('Gagal mencari outlet.');
                });
        }, 250);
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
                        geoBtn.innerHTML = '<span>✓ Lokasi Ditemukan (Urut Terdekat)</span>';
                        const list = data.outlets || [];
                        list.forEach(o => outletStore.set(o.code, o));
                        AppState.outlets = list;
                        renderOutletList(list);
                    })
                    .catch(() => {
                        geoBtn.innerHTML = '<span>✕ Gagal memuat outlet terdekat</span>';
                    });
            },
            () => {
                geoBtn.innerHTML = '<span>✕ Izin lokasi ditolak</span>';
                setTimeout(() => {
                    geoBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg><span>Pakai Lokasi Saya (Cari Outlet Terdekat)</span>';
                }, 2000);
            }
        );
    });
}

// =========================================================================
// 3. MENU RENDERING & FETCHING (Revisi Poin 6, 7 & 12)
// =========================================================================

function fetchMenu(outletCode, forceRefresh = false) {
    const refreshBtn = document.getElementById('refreshStockBtn');
    if (refreshBtn) refreshBtn.classList.add('spinning');

    fetch(`api/menu.php?outlet=${encodeURIComponent(outletCode)}&refresh=${forceRefresh ? 1 : 0}`)
        .then(res => res.json())
        .then(data => {
            AppState.menu = data;

            // Sync outlet premium status from API response
            if (typeof data.premium !== 'undefined' && AppState.outlet) {
                AppState.outlet.isPremium = Boolean(data.premium);
                AppState.outlet.premium = Boolean(data.premium);
                updateOutletHeader();
            }

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

const refreshStockBtn = document.getElementById('refreshStockBtn');
if (refreshStockBtn) {
    refreshStockBtn.addEventListener('click', () => {
        if (AppState.outlet?.code) {
            fetchMenu(AppState.outlet.code, true);
        }
    });
}

// Category Filter
document.querySelectorAll('.cat-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        AppState.activeCategory = btn.dataset.cat;
        renderMenu();
    });
});

// Search Filter
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
            <span>Menampilkan ${filtered.length} menu (Diskon 50% + Fee Hemat)</span>
        </div>
        <div class="menu-grid">
            ${filtered.map(item => {
                const isSold = item.isSoldOut || item.notCarried;
                const safeKey = (item.key || item.id || '').replace(/"/g, '&quot;');
                const isBundle = item.category === 'Bundling' || item.isBundling;

                return `
                    <div class="product-card ${isSold ? 'sold-out' : ''}" data-key="${safeKey}">
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
                            ${isSold ? 'Stok Habis' : (isBundle ? 'Pilih Paket' : '+ Tambah')}
                        </button>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// Card Click Delegation
const menuContainer = document.getElementById('menuContainer');
if (menuContainer) {
    menuContainer.addEventListener('click', (e) => {
        const card = e.target.closest('.product-card');
        if (!card) return;
        const key = card.dataset.key;
        const item = (AppState.menu?.items || []).find(i => (i.key || i.id) === key);
        if (!item) return;

        if (item.isSoldOut || item.notCarried) {
            showToast('Maaf, menu ini sedang habis di outlet ini.');
            return;
        }

        if (item.category === 'Bundling' || item.isBundling) {
            openBundlingModal(item);
        } else {
            openProductModal(item);
        }
    });
}

// =========================================================================
// 4. BUNDLING MODAL CONTROLLER (Revisi Poin 12)
// =========================================================================

function openBundlingModal(bundle) {
    AppState.activeBundle = bundle;
    AppState.bundleSlotChoices = {};

    const titleEl = document.getElementById('bundleModalTitle');
    const descEl = document.getElementById('bundleModalDesc');
    const priceEl = document.getElementById('bundleModalPrice');
    const normalPriceEl = document.getElementById('bundleModalNormalPrice');
    const imgEl = document.getElementById('bundleModalImg');
    const slotsContainer = document.getElementById('bundleSlotsContainer');

    if (titleEl) titleEl.textContent = bundle.name;
    if (descEl) descEl.textContent = bundle.desc || 'Pilih kombinasi varian menu dalam paket bundling ini.';
    if (priceEl) priceEl.textContent = formatRp(bundle.price);
    if (normalPriceEl) normalPriceEl.textContent = bundle.normalPrice ? formatRp(bundle.normalPrice) : '';
    if (imgEl) imgEl.src = bundle.img || 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=400';

    // Render Slots Choices
    if (slotsContainer) {
        slotsContainer.innerHTML = (bundle.slots || []).map((slot, sIdx) => {
            const firstChoice = slot.options && slot.options.length ? slot.options[0] : '';
            AppState.bundleSlotChoices[sIdx] = firstChoice;

            return `
                <div class="bundle-slot-box">
                    <div class="bundle-slot-label">
                        <span>🎀</span>
                        <span>${slot.label || `Pilihan ${sIdx + 1}`}</span>
                    </div>
                    <div class="bundle-choices-grid" data-slot="${sIdx}">
                        ${(slot.options || []).map((opt, oIdx) => `
                            <div class="bundle-choice-card ${oIdx === 0 ? 'selected' : ''}" data-slot="${sIdx}" data-val="${opt}">
                                ${opt}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }

    openSheet('bundlingSheet');
}

// Handle Slot Choice Click
const bundleSlotsContainer = document.getElementById('bundleSlotsContainer');
if (bundleSlotsContainer) {
    bundleSlotsContainer.addEventListener('click', (e) => {
        const choiceCard = e.target.closest('.bundle-choice-card');
        if (!choiceCard) return;
        const slotIdx = choiceCard.dataset.slot;
        const val = choiceCard.dataset.val;

        AppState.bundleSlotChoices[slotIdx] = val;

        const parentGrid = choiceCard.closest('.bundle-choices-grid');
        if (parentGrid) {
            parentGrid.querySelectorAll('.bundle-choice-card').forEach(c => c.classList.remove('selected'));
        }
        choiceCard.classList.add('selected');
    });
}

// Bundle Ice & Sugar Pills
document.querySelectorAll('#bundleIcePills .og-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#bundleIcePills .og-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        AppState.bundleIce = btn.dataset.val;
    });
});

document.querySelectorAll('#bundleSugarPills .og-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#bundleSugarPills .og-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        AppState.bundleSugar = btn.dataset.val;
    });
});

const addBundleToCartBtn = document.getElementById('addBundleToCartBtn');
if (addBundleToCartBtn) {
    addBundleToCartBtn.addEventListener('click', () => {
        if (!AppState.activeBundle) return;

        const choicesList = Object.values(AppState.bundleSlotChoices).join(' + ');
        const notes = document.getElementById('bundleNotes')?.value.trim() || '';

        const itemToAdd = {
            id: 'bundle_' + Date.now(),
            key: AppState.activeBundle.key,
            name: AppState.activeBundle.name,
            price: AppState.activeBundle.price,
            qty: 1,
            size: '',
            temp: 'Ice',
            ice: AppState.bundleIce,
            sugar: AppState.bundleSugar,
            bundleChoices: choicesList,
            note: notes,
            img: AppState.activeBundle.img,
            subtotal: AppState.activeBundle.price,
            isBundling: true,
        };

        closeSheet('bundlingSheet');
        triggerAddToCartWithFeeCheck(itemToAdd);
    });
}

// =========================================================================
// 5. PRODUCT MODAL & 1 PCS FEE CHECK (Revisi Poin 8)
// =========================================================================

function openProductModal(item) {
    AppState.selectedProduct = item;
    AppState.selectedSize = 'Regular';
    AppState.selectedTemp = 'Ice';
    AppState.selectedIce = 'Normal Ice';
    AppState.selectedSugar = 'Normal Sugar';
    AppState.selectedQty = 1;

    const nameEl = document.getElementById('modalProductName');
    const catEl = document.getElementById('modalProductCat');
    const descEl = document.getElementById('modalProductDesc');
    const priceEl = document.getElementById('modalProductPrice');
    const normalPriceEl = document.getElementById('modalProductNormalPrice');
    const imgEl = document.getElementById('modalProductImg');
    const noteEl = document.getElementById('modalItemNote');
    const qtyValEl = document.getElementById('qtyVal');

    if (nameEl) nameEl.textContent = item.name;
    if (catEl) catEl.textContent = item.category || 'Coffee';
    if (descEl) descEl.textContent = item.desc || '';
    if (imgEl) imgEl.src = item.img || 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae';
    if (noteEl) noteEl.value = '';
    if (qtyValEl) qtyValEl.textContent = '1';

    // Helper to dynamically update regular vs large price & crossed-out normal price (Revisi PDF)
    const updateModalPrice = (sizeVal) => {
        const isLarge = sizeVal === 'Large';
        let currentPrice, currentNormal;

        if (isLarge) {
            currentPrice = item.largePrice ? item.largePrice : (item.price + 3500);
            currentNormal = item.largeNormal ? item.largeNormal : (item.normalPrice ? item.normalPrice + 7000 : 0);
        } else {
            currentPrice = item.price;
            currentNormal = item.normalPrice || 0;
        }

        if (priceEl) priceEl.textContent = formatRp(currentPrice);
        if (normalPriceEl) {
            normalPriceEl.textContent = currentNormal && currentNormal > currentPrice ? formatRp(currentNormal) : '';
        }
    };

    // Initialize price display with selected size
    updateModalPrice(AppState.selectedSize);

    // Build Option Pills
    renderOptionPills('optGroupSize', 'optPillsSize', item.options?.sizes || ['Regular', 'Large'], AppState.selectedSize, (val) => {
        AppState.selectedSize = val;
        updateModalPrice(val);
    });

    renderOptionPills('optGroupTemp', 'optPillsTemp', item.options?.temps || ['Ice', 'Hot'], AppState.selectedTemp, (val) => {
        AppState.selectedTemp = val;
    });

    renderOptionPills('optGroupIce', 'optPillsIce', item.options?.ices || ['Normal Ice', 'Less Ice'], AppState.selectedIce, (val) => {
        AppState.selectedIce = val;
    });

    renderOptionPills('optGroupSugar', 'optPillsSugar', item.options?.sugars || ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'], AppState.selectedSugar, (val) => {
        AppState.selectedSugar = val;
    });

    openSheet('productSheet');
}

function renderOptionPills(groupId, pillsId, options, currentVal, onSelect) {
    const groupEl = document.getElementById(groupId);
    const pillsEl = document.getElementById(pillsId);
    if (!groupEl || !pillsEl) return;

    if (!options || !options.length) {
        groupEl.style.display = 'none';
        return;
    }
    groupEl.style.display = 'block';

    pillsEl.innerHTML = options.map(opt => `
        <button class="og-pill ${opt === currentVal ? 'active' : ''}" type="button" data-val="${opt}">${opt}</button>
    `).join('');

    pillsEl.querySelectorAll('.og-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            pillsEl.querySelectorAll('.og-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            onSelect(btn.dataset.val);
        });
    });
}

// Stepper
const qtyMinus = document.getElementById('qtyMinus');
const qtyPlus = document.getElementById('qtyPlus');
const qtyVal = document.getElementById('qtyVal');

if (qtyMinus && qtyPlus && qtyVal) {
    qtyMinus.addEventListener('click', () => {
        if (AppState.selectedQty > 1) {
            AppState.selectedQty--;
            qtyVal.textContent = AppState.selectedQty;
        }
    });

    qtyPlus.addEventListener('click', () => {
        AppState.selectedQty++;
        qtyVal.textContent = AppState.selectedQty;
    });
}

// Add Single Item to Cart
const addToCartBtn = document.getElementById('addToCartBtn');
if (addToCartBtn) {
    addToCartBtn.addEventListener('click', () => {
        if (!AppState.selectedProduct) return;

        const item = AppState.selectedProduct;
        const isLarge = AppState.selectedSize === 'Large';
        const price = isLarge ? (item.largePrice ? item.largePrice : (item.price + 3500)) : item.price;
        const note = document.getElementById('modalItemNote')?.value.trim() || '';

        const itemToAdd = {
            id: 'item_' + Date.now(),
            key: item.key || item.id,
            name: item.name,
            price: price,
            qty: AppState.selectedQty,
            size: AppState.selectedSize,
            temp: AppState.selectedTemp,
            ice: AppState.selectedIce,
            sugar: AppState.selectedSugar,
            note: note,
            img: item.img,
            subtotal: price * AppState.selectedQty,
        };

        closeSheet('productSheet');
        triggerAddToCartWithFeeCheck(itemToAdd);
    });
}

/**
 * 1 PCS FEE CHECK CONTROLLER (Revisi Poin 8)
 * Jika jumlah item di keranjang sebelum tambah adalah 0 dan item yang ditambah qty = 1,
 * tampilkan popup "Ada Fee Tambahan +2.000" sebelum masuk ke keranjang!
 */
function triggerAddToCartWithFeeCheck(item) {
    const currentTotalQty = AppState.cart.reduce((sum, i) => sum + (i.qty || 1), 0);

    // If current cart is empty and this single item has qty 1, show Fee Warning Modal
    if (currentTotalQty === 0 && item.qty === 1) {
        AppState.pendingCartItem = item;
        openSheet('singleItemFeeModal');
        return;
    }

    // Otherwise, directly push to cart
    commitItemToCart(item);
}

// Modal Fee 1pcs Buttons
const feeContinueBtn = document.getElementById('feeContinueBtn');
if (feeContinueBtn) {
    feeContinueBtn.addEventListener('click', () => {
        closeSheet('singleItemFeeModal');
        if (AppState.pendingCartItem) {
            commitItemToCart(AppState.pendingCartItem);
            AppState.pendingCartItem = null;
            showToast('✓ Pesanan satuan dimasukkan ke keranjang (+fee 2.000)');
        }
    });
}

const feeAddMoreBtn = document.getElementById('feeAddMoreBtn');
if (feeAddMoreBtn) {
    feeAddMoreBtn.addEventListener('click', () => {
        closeSheet('singleItemFeeModal');
        if (AppState.pendingCartItem) {
            commitItemToCart(AppState.pendingCartItem);
            AppState.pendingCartItem = null;
            showToast('Yuk pilih 1 item lagi agar bebas fee 2.000! ♡');
        }
    });
}

function commitItemToCart(item) {
    AppState.cart.push(item);
    localStorage.setItem('kyuti_cart', JSON.stringify(AppState.cart));
    updateCartUI();
    showToast(`✓ Ditambahkan: ${item.name}`);
}

// =========================================================================
// 6. CART MANAGEMENT, PLASTIC BAG (+1.000) & PICKUP TIME (Revisi Poin 10 & 11)
// =========================================================================

function updateCartUI() {
    const totalQty = AppState.cart.reduce((sum, i) => sum + (i.qty || 1), 0);
    const subtotal = AppState.cart.reduce((sum, i) => sum + (i.subtotal || 0), 0);

    // Calculate Single Item Fee (+2.000 if total qty == 1)
    const hasSingleFee = totalQty === 1;
    const singleFee = hasSingleFee ? AppState.singleItemFeeAmount : 0;

    // Calculate Plastic Bag Fee (+1.000 if checked)
    const plasticFee = AppState.hasPlasticBag ? AppState.plasticBagFeeAmount : 0;

    const grandTotal = subtotal + singleFee + plasticFee;

    // Update Floating Cart Bar
    const floatingCart = document.getElementById('floatingCart');
    const cartTotalQty = document.getElementById('cartTotalQty');
    const cartBarTitle = document.getElementById('cartBarTitle');
    const cartBarTotalPrice = document.getElementById('cartBarTotalPrice');

    if (floatingCart) {
        if (totalQty > 0) {
            floatingCart.classList.add('visible');
            if (cartTotalQty) cartTotalQty.textContent = totalQty;
            if (cartBarTitle) cartBarTitle.textContent = `${totalQty} Item di Keranjang`;
            if (cartBarTotalPrice) cartBarTotalPrice.textContent = formatRp(grandTotal);
        } else {
            floatingCart.classList.remove('visible');
        }
    }

    // Update Cart Sheet Summary
    const subtotalEl = document.getElementById('summarySubtotal');
    const totalEl = document.getElementById('summaryTotal');
    const singleFeeRow = document.getElementById('singleFeeRow');
    const freeFeeRow = document.getElementById('freeFeeRow');
    const plasticFeeRow = document.getElementById('plasticFeeRow');

    if (subtotalEl) subtotalEl.textContent = formatRp(subtotal);
    if (totalEl) totalEl.textContent = formatRp(grandTotal);

    if (singleFeeRow) singleFeeRow.style.display = hasSingleFee ? 'flex' : 'none';
    if (freeFeeRow) freeFeeRow.style.display = totalQty > 1 ? 'flex' : 'none';
    if (plasticFeeRow) plasticFeeRow.style.display = AppState.hasPlasticBag ? 'flex' : 'none';

    renderCartItemsList();
}

function renderCartItemsList() {
    const list = document.getElementById('cartItemsList');
    if (!list) return;

    if (!AppState.cart.length) {
        list.innerHTML = `
            <div class="empty-box" style="padding:20px 0">
                <div class="empty-icon">🛒</div>
                <p>Keranjang kamu masih kosong.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = AppState.cart.map((item, idx) => `
        <div style="display:flex;align-items:center;gap:12px;background:var(--bg-page);border-radius:var(--radius-md);padding:10px 12px;border:1px solid var(--border-color)">
            <img src="${item.img || 'https://placehold.co/80x80'}" style="width:48px;height:48px;border-radius:8px;object-fit:cover;">
            <div style="flex:1;overflow:hidden">
                <div style="font-size:13px;font-weight:800;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${item.name}</div>
                <div style="font-size:11px;color:var(--text-muted)">${item.size ? item.size + ' • ' : ''}${item.temp || 'Ice'}</div>
                ${item.bundleChoices ? `<div style="font-size:10px;color:var(--brand-primary-dark);font-weight:700">${item.bundleChoices}</div>` : ''}
                <div style="font-size:12px;font-weight:800;color:var(--brand-primary-dark);margin-top:2px">${formatRp(item.price)} x ${item.qty}</div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <button class="icon-btn" onclick="modifyCartQty(${idx}, -1)" style="width:28px;height:28px">-</button>
                <b style="font-size:13px;min-width:16px;text-align:center">${item.qty}</b>
                <button class="icon-btn" onclick="modifyCartQty(${idx}, 1)" style="width:28px;height:28px">+</button>
            </div>
        </div>
    `).join('');
}

window.modifyCartQty = function(idx, change) {
    if (!AppState.cart[idx]) return;
    AppState.cart[idx].qty += change;
    if (AppState.cart[idx].qty <= 0) {
        AppState.cart.splice(idx, 1);
    } else {
        AppState.cart[idx].subtotal = AppState.cart[idx].price * AppState.cart[idx].qty;
    }
    localStorage.setItem('kyuti_cart', JSON.stringify(AppState.cart));
    updateCartUI();
};

const floatingCart = document.getElementById('floatingCart');
if (floatingCart) {
    floatingCart.addEventListener('click', () => {
        updateCartUI();
        openSheet('cartSheet');
    });
}

const clearCartBtn = document.getElementById('clearCartBtn');
if (clearCartBtn) {
    clearCartBtn.addEventListener('click', () => {
        if (confirm('Kosongkan keranjang belanja?')) {
            AppState.cart = [];
            localStorage.removeItem('kyuti_cart');
            updateCartUI();
            showToast('Keranjang telah dikosongkan.');
        }
    });
}

// Plastic Bag Checkbox Addon (Revisi Poin 10)
const plasticBagCheckbox = document.getElementById('plasticBagCheckbox');
if (plasticBagCheckbox) {
    plasticBagCheckbox.addEventListener('change', (e) => {
        AppState.hasPlasticBag = e.target.checked;
        updateCartUI();
    });
}

// Pickup Time & Timezone Handling (Revisi Poin 11: 15-Minute Intervals)
const pickupNowBtn = document.getElementById('pickupNowBtn');
const pickupCustomBtn = document.getElementById('pickupCustomBtn');
const customPickupContainer = document.getElementById('customPickupContainer');
const pickupTimeSelect = document.getElementById('pickupTimeSelect');

function populate15MinPickupTimes() {
    if (!pickupTimeSelect) return;
    pickupTimeSelect.innerHTML = '';

    const tz = AppState.pickupTimezone;
    // Generate hours from 07:00 to 22:45
    for (let h = 7; h <= 22; h++) {
        for (let m = 0; m < 60; m += 15) {
            const hh = String(h).padStart(2, '0');
            const mm = String(m).padStart(2, '0');
            const timeStr = `${hh}.${mm} ${tz}`;
            const opt = document.createElement('option');
            opt.value = timeStr;
            opt.textContent = timeStr;
            pickupTimeSelect.appendChild(opt);
        }
    }
}

if (pickupNowBtn && pickupCustomBtn && customPickupContainer) {
    pickupNowBtn.addEventListener('click', () => {
        pickupNowBtn.classList.add('active');
        pickupCustomBtn.classList.remove('active');
        customPickupContainer.style.display = 'none';
        AppState.pickupType = 'Sekarang';
    });

    pickupCustomBtn.addEventListener('click', () => {
        pickupCustomBtn.classList.add('active');
        pickupNowBtn.classList.remove('active');
        customPickupContainer.style.display = 'block';
        AppState.pickupType = 'Atur Jam';
        populate15MinPickupTimes();
    });
}

// Timezone Pills
document.querySelectorAll('.tz-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tz-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        AppState.pickupTimezone = btn.dataset.tz;
        populate15MinPickupTimes();
    });
});

// =========================================================================
// 7. CHECKOUT VIA WHATSAPP (Revisi Poin 8, 10, 11)
// =========================================================================

const checkoutWaBtn = document.getElementById('checkoutWaBtn');
if (checkoutWaBtn) {
    checkoutWaBtn.addEventListener('click', () => {
        if (!AppState.cart.length) {
            showToast('Keranjang kamu masih kosong.');
            return;
        }

        const custName = document.getElementById('custName')?.value.trim();
        if (!custName) {
            alert('Silakan masukkan nama pemesan terlebih dahulu.');
            document.getElementById('custName')?.focus();
            return;
        }

        const custPhone = document.getElementById('custPhone')?.value.trim() || '';
        const notes = document.getElementById('orderNotes')?.value.trim() || '';

        let pickupTime = 'Sekarang';
        if (AppState.pickupType === 'Atur Jam' && pickupTimeSelect) {
            pickupTime = pickupTimeSelect.value || 'Sekarang';
        }

        const totalQty = AppState.cart.reduce((sum, i) => sum + (i.qty || 1), 0);
        const subtotal = AppState.cart.reduce((sum, i) => sum + (i.subtotal || 0), 0);
        const hasSingleFee = totalQty === 1;

        checkoutWaBtn.disabled = true;
        checkoutWaBtn.innerHTML = '<span>Menyiapkan Pesanan...</span>';

        const payload = {
            customerName: custName,
            customerPhone: custPhone,
            outletCode: AppState.outlet?.code || 'LA.CODEFIN',
            outletName: AppState.outlet?.name || 'Kopi Kenangan',
            pickupTime: pickupTime,
            pickupTimezone: AppState.pickupTimezone,
            notes: notes,
            plasticBag: AppState.hasPlasticBag,
            hasSingleItemFee: hasSingleFee,
            items: AppState.cart,
            subtotal: subtotal
        };

        fetch('api/order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.waUrl) {
                // Save Order into local history
                const history = JSON.parse(localStorage.getItem('kyuti_orders') || '[]');
                history.unshift(res.order);
                localStorage.setItem('kyuti_orders', JSON.stringify(history.slice(0, 10)));

                // Clear cart
                AppState.cart = [];
                localStorage.removeItem('kyuti_cart');
                updateCartUI();
                closeSheet('cartSheet');

                showToast(`✓ Pesanan ${res.orderCode} berhasil dibuat! Membuka WhatsApp...`);

                // Open WhatsApp
                setTimeout(() => {
                    window.open(res.waUrl, '_blank');
                }, 400);
            } else {
                alert(res.message || 'Gagal memproses pesanan.');
            }
        })
        .catch(() => {
            alert('Terjadi kesalahan jaringan saat membuat pesanan.');
        })
        .finally(() => {
            checkoutWaBtn.disabled = false;
            checkoutWaBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8.2 8.2 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2.1c-.2-.3 0-.5.1-.6l.5-.6.3-.5a.6.6 0 0 0 0-.5l-.9-2.3c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .8 2.5.7 3 .6a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.2-.3-.3-.6-.4zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.2A11.8 11.8 0 0 0 1.7 17.8L0 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.6z"/></svg>
                <span>Pesan via WhatsApp</span>
            `;
        });
    });
}

// =========================================================================
// 8. ORDER TRACKING CONTROLLER
// =========================================================================

const trackOrderBtn = document.getElementById('trackOrderBtn');
if (trackOrderBtn) {
    trackOrderBtn.addEventListener('click', () => {
        openSheet('trackSheet');
    });
}

const trackSubmitBtn = document.getElementById('trackSubmitBtn');
const trackCodeInput = document.getElementById('trackCodeInput');
const trackResultBox = document.getElementById('trackResultBox');

if (trackSubmitBtn && trackCodeInput && trackResultBox) {
    trackSubmitBtn.addEventListener('click', () => {
        const code = trackCodeInput.value.trim().toUpperCase();
        if (!code) {
            showToast('Silakan ketik kode pesanan.');
            return;
        }

        trackSubmitBtn.disabled = true;
        trackSubmitBtn.textContent = 'Mencari...';

        fetch(`api/track.php?code=${encodeURIComponent(code)}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.order) {
                    const ord = res.order;
                    trackResultBox.innerHTML = `
                        <div style="background:var(--bg-page);border-radius:var(--radius-lg);padding:16px;border:1px solid var(--border-color);animation:fadeIn 0.3s ease">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                                <b style="font-size:14px;color:var(--brand-primary-dark)">${ord.code}</b>
                                <span class="pill pill-open">Sedang Disiapkan</span>
                            </div>
                            <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px">${ord.statusDesc || 'Barista sedang meracik pesananmu.'}</div>
                            <div style="border-top:1px dashed var(--border-color);padding-top:10px;font-size:12px">
                                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                                    <span>Outlet:</span>
                                    <b>${ord.outletName || 'Kopi Kenangan'}</b>
                                </div>
                                <div style="display:flex;justify-content:space-between">
                                    <span>Waktu Pickup:</span>
                                    <b>${ord.pickup || 'Sekarang'}</b>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    trackResultBox.innerHTML = `
                        <div class="empty-box">
                            <div class="empty-icon">✕</div>
                            <p>${res.message || 'Pesanan tidak ditemukan.'}</p>
                        </div>
                    `;
                }
            })
            .catch(() => {
                trackResultBox.innerHTML = `
                    <div class="empty-box">
                        <div class="empty-icon">⚠️</div>
                        <p>Gagal menghubungi server pelacakan.</p>
                    </div>
                `;
            })
            .finally(() => {
                trackSubmitBtn.disabled = false;
                trackSubmitBtn.textContent = 'Lacak';
            });
    });
}

// Initial Boot
document.addEventListener('DOMContentLoaded', () => {
    updateOutletHeader();
    renderMenu();
    updateCartUI();
    checkWelcomePopup();
});
