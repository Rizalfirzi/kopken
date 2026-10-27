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
    bundleSlotPrefs: {},
    bundleIce: 'Normal Ice',
    bundleSugar: 'Normal Sugar',

    // Product Customization Sheet State (Addons & Options)
    sheet: {
        item: null,
        groups: [],
        prices: null,
        normals: null,
        sel: {},
        qty: 1,
        loading: false,
        addons: [],
        comboAddons: null,
        picked: new Set(),
    },

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

// Currency & String Formatting Helpers
const $ = (sel) => document.querySelector(sel);
const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');
const esc = (str) => {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, s => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[s]));
};

// Outlet Status Helper: Check if visited outlet is closed
function isOutletClosed() {
    if (!AppState.outlet) return false;

    // Explicit boolean / string checks from outlet state
    if (AppState.outlet.canOrder === false || AppState.outlet.isOpen === false) return true;
    if (AppState.outlet.canOrder === 'false' || AppState.outlet.isOpen === 'false') return true;

    // Menu level isClosed flag from API
    if (AppState.menu?.isClosed === true || AppState.menu?.isClosed === 'true') return true;
    if (AppState.menu?.canOrder === false || AppState.menu?.canOrder === 'false') return true;

    // Text status fallback check
    const status = (AppState.outlet.statusText || '').toLowerCase().trim();
    if (status.includes('tutup') || status.includes('closed') || status === 'off') {
        return true;
    }

    return false;
}

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
    if (id === 'adminPricingSheet') {
        if (typeof adminPricingState !== 'undefined') {
            adminPricingState.activePasscode = '';
        }
        sessionStorage.removeItem('kyuti_admin_auth');
        sessionStorage.removeItem('kyuti_admin_code');
        const passInput = document.getElementById('adminPasscodeInput');
        if (passInput) passInput.value = '';
    }
}

document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', () => closeSheet(btn.dataset.close));
});

document.querySelectorAll('.sheet-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeSheet(backdrop.id);
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

    // 0. If explicit verified boolean exists (from live menu Mantan price >= 22k), use it directly!
    if (typeof outlet.isPremium === 'boolean') {
        return outlet.isPremium;
    }
    if (typeof outlet.premium === 'boolean') {
        return outlet.premium;
    }

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

    // 5. Major Airport Terminals & verified transport hubs keywords
    // Note: Generic 'mall' or 'plaza' are excluded because most malls have regular 19k price!
    const keywords = [
        'terminal bandara', 'keberangkatan bandara', 'airport departure', 'bandara internasional',
        'terminal 2d', 'terminal 2e', 'terminal 3 domestic', 'terminal 3 international',
        'grand indonesia', 'senayan city', 'pacific place', 'tunjungan plaza 3', 'pim 3'
    ];

    return keywords.some(k => combined.includes(k));
}

function checkAndShowPremiumOutletAlert(outlet) {
    if (outlet?.isPremium) {
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
        const closed = isOutletClosed();
        statusEl.textContent = closed ? (AppState.outlet.statusText || 'Tutup') : (AppState.outlet.statusText || 'Buka');
        statusEl.className = 'pill ' + (closed ? 'pill-closed' : 'pill-open');
    }
    if (hoursEl) hoursEl.textContent = '• ' + (AppState.outlet.hours || 'Buka Setiap Hari');
    if (barOutletEl) barOutletEl.textContent = AppState.outlet.name;
    if (sheetOutletEl) sheetOutletEl.textContent = AppState.outlet.name;

    if (premiumBadge) {
        premiumBadge.style.display = AppState.outlet?.isPremium ? 'inline-flex' : 'none';
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
        if (o && o.code) {
            const existing = outletStore.get(o.code);
            if (existing && typeof existing.isPremium === 'boolean') {
                o.isPremium = existing.isPremium;
                o.premium = existing.isPremium;
            } else {
                outletStore.set(o.code, o);
            }
        }
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
    if (isOutletClosed()) {
        showToast(`⚠️ Perhatian: ${found.name} saat ini sedang tutup`);
    } else {
        showToast(`✓ Outlet aktif: ${found.name}`);
    }

    // If already verified as Premium from previous menu fetch, show alert immediately
    if (found.isPremium === true) {
        checkAndShowPremiumOutletAlert(found);
    }

    if (window.history && window.history.pushState) {
        const url = new URL(window.location.href);
        url.searchParams.set('outlet', found.code);
        window.history.pushState({ outlet: found.code }, '', url.toString());
    }

    fetchMenu(found.code, false);
    fetchWifi(found.code);
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

    fetch(`api/menu.php?outlet=${encodeURIComponent(outletCode)}&refresh=${forceRefresh ? 1 : 0}${forceRefresh ? `&_t=${Date.now()}` : ''}`)
        .then(res => res.json())
        .then(data => {
            AppState.menu = data;

            // Sync closed status if data indicates isClosed
            if (data && typeof data.isClosed === 'boolean') {
                if (data.isClosed && AppState.outlet) {
                    AppState.outlet.canOrder = false;
                    AppState.outlet.isOpen = false;
                    if (!AppState.outlet.statusText || AppState.outlet.statusText === 'Buka') {
                        AppState.outlet.statusText = 'Tutup';
                    }
                    outletStore.set(AppState.outlet.code, AppState.outlet);
                    updateOutletHeader();
                }
            }

            // Absolute Client Rule: Single source of truth is Kopi Kenangan Mantan normalPrice >= 22k
            let isPrem = false;
            if (Array.isArray(data.items)) {
                const mantan = data.items.find(i => {
                    const n = (i.name || '').toLowerCase();
                    return n.includes('kenangan mantan') && !n.includes('oatside') && !n.includes('frappe') && !n.includes('bundl') && !n.includes('duo');
                });
                if (mantan) {
                    const normalP = Number(mantan.normalPrice || mantan.outletPrice || 0);
                    isPrem = normalP >= 22000;
                } else {
                    isPrem = Boolean(data.premium);
                }
            } else {
                isPrem = Boolean(data.premium);
            }

            if (AppState.outlet) {
                AppState.outlet.isPremium = isPrem;
                AppState.outlet.premium = isPrem;
                outletStore.set(AppState.outlet.code, AppState.outlet);
                updateOutletHeader();
                if (isPrem) {
                    checkAndShowPremiumOutletAlert(AppState.outlet);
                }
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
            updateCartUI();
            populateMenuDropdown();
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
    const outletClosed = isOutletClosed();
    
    const bStatus = adminPricingState?.bundlingStatus || window.INITIAL_DATA?.pricing?.bundlingStatus;
    const filtered = items.filter(item => {
        if ((item.category === 'Bundling' || item.isBundling) && bStatus && item.key && bStatus[item.key] === false) {
            return false;
        }
        const matchesCat = AppState.activeCategory === 'all' || item.category === AppState.activeCategory;
        const matchesSearch = !AppState.searchQuery || 
            item.name.toLowerCase().includes(AppState.searchQuery) ||
            (item.desc && item.desc.toLowerCase().includes(AppState.searchQuery));
        return matchesCat && matchesSearch;
    });

    const closedBannerHtml = outletClosed ? `
        <div class="outlet-closed-banner" id="outletClosedBanner">
            <div class="ocb-icon-wrap">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <div class="ocb-content">
                <div class="ocb-title">Outlet Sedang Tutup</div>
                <div class="ocb-desc">
                    <b>${esc(AppState.outlet?.name || 'Outlet')}</b> saat ini sedang tutup${AppState.outlet?.hours ? ` (${esc(AppState.outlet.hours)})` : ''}. Semua menu dinonaktifkan sementara. Silakan ganti outlet yang sedang buka untuk memesan.
                </div>
            </div>
            <button type="button" class="ocb-btn" onclick="openSheet('outletSheet')">
                <span>Ganti Outlet</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>
    ` : '';

    if (!filtered.length) {
        container.innerHTML = `
            ${closedBannerHtml}
            <div class="empty-box">
                <div class="empty-icon">☕</div>
                <h4>Menu Tidak Ditemukan</h4>
                <p>Coba kata kunci pencarian lain atau pilih kategori berbeda.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        ${closedBannerHtml}
        <div class="sec-title">
            <span>Menampilkan ${filtered.length} menu ${outletClosed ? '• (Outlet Tutup)' : ''}</span>
        </div>
        <div class="menu-grid">
            ${filtered.map(item => {
                const isSold = item.isSoldOut || item.notCarried;
                const safeKey = (item.key || item.id || '').replace(/"/g, '&quot;');
                const isBundle = item.category === 'Bundling' || item.isBundling;

                let cardClasses = 'product-card';
                if (outletClosed) {
                    cardClasses += ' outlet-closed-card';
                } else if (isSold) {
                    cardClasses += ' sold-out';
                }

                let badgeHtml = '';
                if (outletClosed) {
                    badgeHtml = `<span class="stock-badge closed">✕ Outlet Tutup</span>`;
                } else if (isSold) {
                    badgeHtml = `<span class="stock-badge empty">✕ Habis di Outlet</span>`;
                } else {
                    badgeHtml = `<span class="stock-badge ready">● Stok Ready</span>`;
                }

                let btnText = '+ Tambah';
                let btnDisabled = false;
                if (outletClosed) {
                    btnText = 'Outlet Tutup';
                    btnDisabled = true;
                } else if (isSold) {
                    btnText = 'Stok Habis';
                    btnDisabled = true;
                } else if (isBundle) {
                    btnText = 'Pilih Paket';
                }

                return `
                    <div class="${cardClasses}" data-key="${safeKey}">
                        <div class="product-img-wrap">
                            <img src="${item.img || 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae'}" alt="${item.name}" loading="lazy" onerror="this.src='https://placehold.co/200x200?text=Kopi+Kenangan'">
                            ${badgeHtml}
                            ${item.tag ? `<span class="promo-tag">${item.tag}</span>` : ''}
                        </div>
                        <h4 class="product-name">${item.name}</h4>
                        <div class="product-cat">${item.category || 'Minuman'}</div>
                        <div class="product-price-row">
                            <span class="product-price">${item.priceLabel ? item.priceLabel : formatRp(item.price)}</span>
                            ${item.normalPrice && item.normalPrice > item.price ? `<span class="product-normal-price">${formatRp(item.normalPrice)}</span>` : ''}
                        </div>
                        <button class="product-add-btn" type="button" ${btnDisabled ? 'disabled aria-disabled="true"' : ''}>
                            ${btnText}
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

        if (isOutletClosed()) {
            showToast(`⚠️ Maaf, Outlet ${AppState.outlet?.name || ''} sedang tutup. Silakan pilih outlet lain.`);
            openSheet('outletSheet');
            return;
        }

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
// 4. BUNDLING MODAL CONTROLLER (Per-Item Customizer, Live Stock & Admin Control)
// =========================================================================

function normBundleStr(str) {
    return String(str || '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

const BUNDLE_ITEM_ALIASES = {
    'chocochipcookie': ['chocochipcookie', 'chocochipcookies'],
    'bananachocosoftbake': ['bananachocosoftbakedcookie', 'bananachocosoftbake', 'bananachoco'],
    'sweethoneysoftbake': ['sweethoneysoftbakedcookie', 'sweethoneysoftbake'],
    'oatmealraisinsoftbake': ['oatmealraisinsoftbakedcookie', 'oatmealraisinsoftbake'],
    'dutchchocokenangan': ['dutchchocokenanganfrappe', 'dutchchocolate', 'dutchchocokenangan'],
    'butterscotchkenangan': ['butterscotchkenanganfrappe', 'butterscotchkenangan'],
};

const BUNDLING_FOOD_ITEMS = new Set([
    'classic peanut butter panini', 'butter croissant', 'salt bread sausage',
    'salt bread choco butter', 'friend chip cookie', 'choco chip cookie',
    'chocolate croissant', 'bananachoco soft bake', 'sweet honey soft bake',
    'oatmeal raisin soft bake', 'chocolate donut', 'donut almond',
    'double choco bagel', 'vanilla choux puff', 'strawberry choux puff',
    'chocolate choux puff', 'bambang choco cheese toast', 'adam ayam toast',
    'triple cheese bagel', 'wahyu sapi toast', 'roti srikaya'
]);

function isBundlingFoodItem(name) {
    if (!name) return false;
    const n = String(name).toLowerCase().trim();
    if (BUNDLING_FOOD_ITEMS.has(n)) return true;
    return /croissant|panini|toast|bread|cookie|donut|bagel|puff|bake|srikaya|roti/i.test(n);
}

function isSingleItemSoldOut(name) {
    const menuItems = AppState.menu?.items || [];
    if (!menuItems.length) return false;

    const n = normBundleStr(name);
    const targets = BUNDLE_ITEM_ALIASES[n] || [n];

    // 1. Direct exact normalized match
    for (const it of menuItems) {
        const itN = normBundleStr(it.name);
        for (const t of targets) {
            if (itN === t) {
                return Boolean(it.isSoldOut || it.notCarried);
            }
        }
    }

    // 2. Substring startsWith match for longer names (>= 6 chars)
    for (const it of menuItems) {
        const itN = normBundleStr(it.name);
        for (const t of targets) {
            if (t.length >= 6 && (itN.startsWith(t) || t.startsWith(itN))) {
                return Boolean(it.isSoldOut || it.notCarried);
            }
        }
    }

    // 3. Not found in current outlet's menu -> outlet doesn't carry it -> Habis!
    return true;
}

function isBundleOptionDisabledByAdmin(opt) {
    let optName = typeof opt === 'string' ? opt : (opt?.name || '');
    const disabledList = adminPricingState?.disabledBundlingOptions || window.INITIAL_DATA?.pricing?.disabledBundlingOptions || [];
    if (!disabledList.length) return false;
    if (disabledList.includes(optName)) return true;
    if (optName.includes('+')) {
        const parts = optName.split('+').map(p => p.trim());
        for (const p of parts) {
            if (disabledList.includes(p)) return true;
        }
    }
    return false;
}

function isBundleOptionSoldOut(opt) {
    let optName = typeof opt === 'string' ? opt : (opt?.name || '');
    // 1. Check if disabled via admin toggle
    if (isBundleOptionDisabledByAdmin(optName)) {
        return true;
    }
    // 2. Check live outlet stock
    if (optName.includes('+')) {
        const parts = optName.split('+').map(p => p.trim());
        for (const p of parts) {
            if (isSingleItemSoldOut(p)) {
                return true;
            }
        }
        return false;
    }
    return isSingleItemSoldOut(optName);
}

function renderBundleSlotCustomizer(sIdx) {
    const box = document.getElementById(`bundleCustomBox_${sIdx}`);
    if (!box) return;

    const choice = AppState.bundleSlotChoices[sIdx];
    if (!choice || !choice.name) {
        box.style.display = 'none';
        box.innerHTML = '';
        return;
    }

    const optName = choice.name;

    // Food item -> show food badge note without ice/sugar
    if (isBundlingFoodItem(optName)) {
        box.style.display = 'block';
        box.innerHTML = `
            <div class="bundle-food-badge-note">
                <span>🥐</span>
                <span><b>${esc(optName)}</b> siap saji (tanpa pilihan es / gula)</span>
            </div>
        `;
        return;
    }

    if (!AppState.bundleSlotPrefs[sIdx]) {
        AppState.bundleSlotPrefs[sIdx] = {};
    }

    // Frappe Combo (2 cups in one slot, separated by '+')
    if (optName.includes('+')) {
        const cups = optName.split('+').map(c => c.trim());
        const cup1Name = cups[0] || 'Cup 1';
        const cup2Name = cups[1] || 'Cup 2';

        if (!AppState.bundleSlotPrefs[sIdx].cup1) {
            AppState.bundleSlotPrefs[sIdx].cup1 = { ice: 'Normal Ice', sugar: 'Normal Sugar' };
        }
        if (!AppState.bundleSlotPrefs[sIdx].cup2) {
            AppState.bundleSlotPrefs[sIdx].cup2 = { ice: 'Normal Ice', sugar: 'Normal Sugar' };
        }

        const p1 = AppState.bundleSlotPrefs[sIdx].cup1;
        const p2 = AppState.bundleSlotPrefs[sIdx].cup2;

        box.style.display = 'block';
        box.innerHTML = `
            <div class="bundle-cup-custom-group">
                <div class="bundle-cup-pref-header">🥤 Cup 1: <b>${esc(cup1Name)}</b></div>
                <div class="bundle-pref-row">
                    <span class="bundle-pref-title">Es:</span>
                    <div class="bundle-pref-pills" data-slot="${sIdx}" data-cup="cup1" data-type="ice">
                        <button type="button" class="og-pill ${p1.ice === 'Normal Ice' ? 'active' : ''}" data-val="Normal Ice">Normal Ice</button>
                        <button type="button" class="og-pill ${p1.ice === 'Less Ice' ? 'active' : ''}" data-val="Less Ice">Less Ice</button>
                    </div>
                </div>
                <div class="bundle-pref-row">
                    <span class="bundle-pref-title">Gula:</span>
                    <div class="bundle-pref-pills" data-slot="${sIdx}" data-cup="cup1" data-type="sugar">
                        <button type="button" class="og-pill ${p1.sugar === 'Normal Sugar' ? 'active' : ''}" data-val="Normal Sugar">Normal Sugar</button>
                        <button type="button" class="og-pill ${p1.sugar === 'Less Sugar (50%)' ? 'active' : ''}" data-val="Less Sugar (50%)">Less Sugar</button>
                        <button type="button" class="og-pill ${p1.sugar === 'No Sugar' ? 'active' : ''}" data-val="No Sugar">No Sugar</button>
                    </div>
                </div>
            </div>

            <div class="bundle-cup-custom-group">
                <div class="bundle-cup-pref-header">🥤 Cup 2: <b>${esc(cup2Name)}</b></div>
                <div class="bundle-pref-row">
                    <span class="bundle-pref-title">Es:</span>
                    <div class="bundle-pref-pills" data-slot="${sIdx}" data-cup="cup2" data-type="ice">
                        <button type="button" class="og-pill ${p2.ice === 'Normal Ice' ? 'active' : ''}" data-val="Normal Ice">Normal Ice</button>
                        <button type="button" class="og-pill ${p2.ice === 'Less Ice' ? 'active' : ''}" data-val="Less Ice">Less Ice</button>
                    </div>
                </div>
                <div class="bundle-pref-row">
                    <span class="bundle-pref-title">Gula:</span>
                    <div class="bundle-pref-pills" data-slot="${sIdx}" data-cup="cup2" data-type="sugar">
                        <button type="button" class="og-pill ${p2.sugar === 'Normal Sugar' ? 'active' : ''}" data-val="Normal Sugar">Normal Sugar</button>
                        <button type="button" class="og-pill ${p2.sugar === 'Less Sugar (50%)' ? 'active' : ''}" data-val="Less Sugar (50%)">Less Sugar</button>
                        <button type="button" class="og-pill ${p2.sugar === 'No Sugar' ? 'active' : ''}" data-val="No Sugar">No Sugar</button>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // Single drink slot
    if (!AppState.bundleSlotPrefs[sIdx].single) {
        AppState.bundleSlotPrefs[sIdx].single = { ice: 'Normal Ice', sugar: 'Normal Sugar' };
    }
    const p = AppState.bundleSlotPrefs[sIdx].single;

    box.style.display = 'block';
    box.innerHTML = `
        <div class="bundle-cup-custom-group">
            <div class="bundle-cup-pref-header">🥤 Pilihan Minuman: <b>${esc(optName)}</b></div>
            <div class="bundle-pref-row">
                <span class="bundle-pref-title">Es:</span>
                <div class="bundle-pref-pills" data-slot="${sIdx}" data-cup="single" data-type="ice">
                    <button type="button" class="og-pill ${p.ice === 'Normal Ice' ? 'active' : ''}" data-val="Normal Ice">Normal Ice</button>
                    <button type="button" class="og-pill ${p.ice === 'Less Ice' ? 'active' : ''}" data-val="Less Ice">Less Ice</button>
                </div>
            </div>
            <div class="bundle-pref-row">
                <span class="bundle-pref-title">Gula:</span>
                <div class="bundle-pref-pills" data-slot="${sIdx}" data-cup="single" data-type="sugar">
                    <button type="button" class="og-pill ${p.sugar === 'Normal Sugar' ? 'active' : ''}" data-val="Normal Sugar">Normal Sugar</button>
                    <button type="button" class="og-pill ${p.sugar === 'Less Sugar (50%)' ? 'active' : ''}" data-val="Less Sugar (50%)">Less Sugar</button>
                    <button type="button" class="og-pill ${p.sugar === 'No Sugar' ? 'active' : ''}" data-val="No Sugar">No Sugar</button>
                </div>
            </div>
        </div>
    `;
}

function updateBundleModalPrice() {
    if (!AppState.activeBundle) return;
    const priceEl = document.getElementById('bundleModalPrice');
    const addBtn = document.getElementById('addBundleToCartBtn');

    let finalPrice = AppState.activeBundle.price;

    // If options have dynamic price (e.g. Frappe bundle or Promo), take from selected choice
    const selectedChoices = Object.values(AppState.bundleSlotChoices || {});
    const customPrices = selectedChoices.map(c => c?.price).filter(p => typeof p === 'number' && p > 0);

    if (customPrices.length > 0) {
        finalPrice = customPrices[0];
    }

    AppState.activeBundle.currentPrice = finalPrice;
    if (priceEl) priceEl.textContent = formatRp(finalPrice);
    if (addBtn && !addBtn.disabled) {
        addBtn.textContent = `Tambahkan ke Keranjang • ${formatRp(finalPrice)}`;
    }
}

function openBundlingModal(bundle) {
    if (isOutletClosed()) {
        showToast(`⚠️ Outlet ${AppState.outlet?.name || ''} sedang tutup.`);
        openSheet('outletSheet');
        return;
    }
    AppState.activeBundle = bundle;
    AppState.bundleSlotChoices = {};
    AppState.bundleSlotPrefs = {};
    AppState.bundleIce = 'Normal Ice';
    AppState.bundleSugar = 'Normal Sugar';

    const titleEl = document.getElementById('bundleModalTitle');
    const descEl = document.getElementById('bundleModalDesc');
    const priceEl = document.getElementById('bundleModalPrice');
    const normalPriceEl = document.getElementById('bundleModalNormalPrice');
    const imgEl = document.getElementById('bundleModalImg');
    const slotsContainer = document.getElementById('bundleSlotsContainer');
    const noticeEl = document.getElementById('bundleStockNotice');
    const addBtn = document.getElementById('addBundleToCartBtn');

    if (titleEl) titleEl.textContent = bundle.name;
    if (descEl) descEl.textContent = bundle.desc || 'Pilih kombinasi varian menu dalam paket bundling ini.';
    if (priceEl) priceEl.textContent = bundle.priceLabel ? bundle.priceLabel : formatRp(bundle.price);
    if (normalPriceEl) normalPriceEl.textContent = bundle.normalPrice ? formatRp(bundle.normalPrice) : '';
    if (imgEl) imgEl.src = bundle.img || 'assets/img/bundling/PAKET BUNDLING 2 CUP LARGE.jpeg';

    if (noticeEl) {
        noticeEl.style.display = 'none';
        noticeEl.innerHTML = '';
    }

    let hasUnavailableRequiredSlot = false;
    const slots = bundle.slots || [];

    if (slotsContainer) {
        slotsContainer.innerHTML = slots.map((slot, sIdx) => {
            let optionsHtml = '';
            let selectedOptForSlot = null;

            // Helper to render an option card
            const renderCard = (opt) => {
                const optName = typeof opt === 'string' ? opt : (opt?.name || '');
                const optPrice = (typeof opt === 'object' && opt?.price) ? opt.price : null;
                const isSoldOut = isBundleOptionSoldOut(opt);
                const isDisabledByAdmin = isBundleOptionDisabledByAdmin(opt);

                let isSelected = false;
                if (!isSoldOut && !selectedOptForSlot) {
                    selectedOptForSlot = { name: optName, price: optPrice };
                    isSelected = true;
                }

                const priceBadge = optPrice ? `<span class="bundle-opt-price">${formatRp(optPrice)}</span>` : '';
                const soldBadge = isSoldOut ? `<span class="bundle-opt-badge-sold">${isDisabledByAdmin ? '✕ Nonaktif' : '✕ Habis'}</span>` : '';
                
                // Khusus PAKET HEMAT 3 ITEM, jangan gunakan grid-column span agar layout rapi 2 kolom simetris tanpa celah kosong
                const isHemat3Item = (bundle.key === 'bundle_hemat_3_item');
                const singleColClass = (!isHemat3Item && optName.length > 28) ? 'style="grid-column: 1 / -1;"' : '';

                return `
                    <div class="bundle-choice-card ${isSelected ? 'selected' : ''} ${isSoldOut ? 'sold-out' : ''}" 
                         data-slot="${sIdx}" 
                         data-val="${esc(optName)}" 
                         ${optPrice ? `data-price="${optPrice}"` : ''}
                         ${isSoldOut ? 'aria-disabled="true"' : ''}
                         ${singleColClass}>
                        <span class="bundle-opt-name">${esc(optName)}</span>
                        ${soldBadge}
                        ${priceBadge}
                    </div>
                `;
            };

            // Categorized groups (e.g. Minuman vs Makanan in Paket Hemat 3 Item)
            if (slot.groups && typeof slot.groups === 'object') {
                for (const [groupTitle, groupItems] of Object.entries(slot.groups)) {
                    optionsHtml += `
                        <div class="bundle-subgroup-title">${esc(groupTitle)}</div>
                        <div class="bundle-choices-grid" data-slot="${sIdx}">
                            ${groupItems.map(renderCard).join('')}
                        </div>
                    `;
                }
            } else {
                // Regular or variant list
                const isSingleCol = (slot.options || []).some(o => (typeof o === 'string' ? o : o.name || '').length > 28);
                optionsHtml = `
                    <div class="bundle-choices-grid ${isSingleCol ? 'single-col' : ''}" data-slot="${sIdx}">
                        ${(slot.options || []).map(renderCard).join('')}
                    </div>
                `;
            }

            if (selectedOptForSlot) {
                AppState.bundleSlotChoices[sIdx] = selectedOptForSlot;
            } else {
                hasUnavailableRequiredSlot = true;
            }

            return `
                <div class="bundle-slot-box" data-slot-box="${sIdx}">
                    <div class="bundle-slot-label">
                        <span>🎀</span>
                        <span>${slot.label || `Pilihan ${sIdx + 1}`}</span>
                    </div>
                    ${optionsHtml}
                    <div class="bundle-slot-custom-box" id="bundleCustomBox_${sIdx}" style="display:none"></div>
                </div>
            `;
        }).join('');

        // Render initial customizers for pre-selected slot choices
        slots.forEach((_, sIdx) => {
            renderBundleSlotCustomizer(sIdx);
        });
    }

    if (hasUnavailableRequiredSlot) {
        if (noticeEl) {
            noticeEl.style.display = 'block';
            noticeEl.innerHTML = `⚠️ <b>Menu Paket Habis</b>: Beberapa pilihan menu dalam paket ini sedang kosong atau dinonaktifkan di <b>${esc(AppState.outlet?.name || 'outlet ini')}</b>. Silakan coba paket lain atau ganti outlet.`;
        }
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.textContent = 'Menu Paket Habis di Outlet Ini';
        }
    } else {
        if (addBtn) {
            addBtn.disabled = false;
        }
        updateBundleModalPrice();
    }

    openSheet('bundlingSheet');
}

// Handle Slot Choice & Customizer Pill Clicks
const bundleSlotsContainer = document.getElementById('bundleSlotsContainer');
if (bundleSlotsContainer) {
    bundleSlotsContainer.addEventListener('click', (e) => {
        // 1. Pill click inside slot customizer (Ice / Sugar)
        const pill = e.target.closest('.bundle-pref-pills .og-pill');
        if (pill) {
            const container = pill.closest('.bundle-pref-pills');
            const slot = container.dataset.slot;
            const cup = container.dataset.cup; // 'cup1', 'cup2', or 'single'
            const type = container.dataset.type; // 'ice' or 'sugar'
            const val = pill.dataset.val;

            if (!AppState.bundleSlotPrefs[slot]) AppState.bundleSlotPrefs[slot] = {};
            if (!AppState.bundleSlotPrefs[slot][cup]) {
                AppState.bundleSlotPrefs[slot][cup] = { ice: 'Normal Ice', sugar: 'Normal Sugar' };
            }
            AppState.bundleSlotPrefs[slot][cup][type] = val;

            container.querySelectorAll('.og-pill').forEach(b => b.classList.remove('active'));
            pill.classList.add('active');
            return;
        }

        // 2. Choice card click
        const choiceCard = e.target.closest('.bundle-choice-card');
        if (!choiceCard) return;

        if (choiceCard.classList.contains('sold-out')) {
            showToast('⚠️ Maaf, menu ini sedang habis atau dinonaktifkan di outlet ini.');
            return;
        }

        const slotIdx = choiceCard.dataset.slot;
        const val = choiceCard.dataset.val;
        const price = choiceCard.dataset.price ? Number(choiceCard.dataset.price) : null;

        AppState.bundleSlotChoices[slotIdx] = {
            name: val,
            price: price
        };

        const parentSlotBox = choiceCard.closest('.bundle-slot-box');
        if (parentSlotBox) {
            parentSlotBox.querySelectorAll('.bundle-choice-card').forEach(c => c.classList.remove('selected'));
        }
        choiceCard.classList.add('selected');

        renderBundleSlotCustomizer(slotIdx);
        updateBundleModalPrice();
    });
}

const addBundleToCartBtn = document.getElementById('addBundleToCartBtn');
if (addBundleToCartBtn) {
    addBundleToCartBtn.addEventListener('click', () => {
        if (!AppState.activeBundle) return;

        const slots = AppState.activeBundle.slots || [];
        const selectedCount = Object.keys(AppState.bundleSlotChoices).length;
        if (selectedCount < slots.length) {
            showToast('Silakan pilih semua varian menu paket terlebih dahulu.');
            return;
        }

        const choicesParts = [];
        slots.forEach((slot, sIdx) => {
            const choice = AppState.bundleSlotChoices[sIdx];
            if (!choice || !choice.name) return;
            const optName = choice.name;

            if (isBundlingFoodItem(optName)) {
                choicesParts.push(optName);
            } else if (optName.includes('+')) {
                const cups = optName.split('+').map(c => c.trim());
                const p1 = AppState.bundleSlotPrefs[sIdx]?.cup1 || { ice: 'Normal Ice', sugar: 'Normal Sugar' };
                const p2 = AppState.bundleSlotPrefs[sIdx]?.cup2 || { ice: 'Normal Ice', sugar: 'Normal Sugar' };
                choicesParts.push(`${cups[0]} (${p1.ice}, ${p1.sugar}) + ${cups[1]} (${p2.ice}, ${p2.sugar})`);
            } else {
                const p = AppState.bundleSlotPrefs[sIdx]?.single || { ice: 'Normal Ice', sugar: 'Normal Sugar' };
                choicesParts.push(`${optName} (${p.ice}, ${p.sugar})`);
            }
        });

        const choicesList = choicesParts.join(' + ');
        const notes = document.getElementById('bundleNotes')?.value.trim() || '';
        const finalPrice = AppState.activeBundle.currentPrice || AppState.activeBundle.price;

        const itemToAdd = {
            id: 'bundle_' + Date.now(),
            key: AppState.activeBundle.key,
            name: AppState.activeBundle.name,
            price: finalPrice,
            qty: 1,
            size: AppState.activeBundle.size || '',
            variantText: AppState.activeBundle.size || 'Paket Bundling',
            bundleChoices: choicesList,
            note: notes,
            img: AppState.activeBundle.img,
            subtotal: finalPrice,
            isBundling: true,
            category: 'Bundling',
        };

        closeSheet('bundlingSheet');
        triggerAddToCartWithFeeCheck(itemToAdd);
    });
}

// =========================================================================
// 5. PRODUCT MODAL, ITEM OPTIONS & ADDONS (Topping & Syrup) (Revisi Poin 8)
// =========================================================================

function defaultAddons(item) {
    const target = item || AppState.sheet?.item || AppState.selectedProduct;
    if (target && (target.isFood || (target.category && target.category.toLowerCase().includes('food')))) {
        return [];
    }
    if (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.addons) && window.INITIAL_DATA.addons.length) {
        return window.INITIAL_DATA.addons;
    }
    return [
        {
            id: '2905',
            name: 'Topping',
            hint: 'opsional, boleh lebih dari satu',
            values: [
                { id: '4099', text: 'Espresso Shot Kenangan Blend', price: 6000 },
                { id: '4100', text: 'Boba', price: 5000 },
                { id: '4101', text: 'Grass Jelly', price: 5000 },
                { id: '4102', text: 'Coconut Jelly', price: 5000 },
                { id: '4103', text: 'Coffee Jelly', price: 5000 },
            ]
        },
        {
            id: '2904',
            name: 'Syrup',
            hint: 'opsional, boleh lebih dari satu',
            values: [
                { id: '4079', text: 'Vanilla Syrup', price: 6000 },
                { id: '4080', text: 'Hazelnut Syrup', price: 6000 },
                { id: '4081', text: 'Caramel Syrup', price: 6000 },
                { id: '4082', text: 'Salted Caramel Sauce', price: 6000 },
                { id: '4083', text: 'Choco Sauce', price: 6000 },
                { id: '4084', text: 'Butterscotch Sauce', price: 6000 },
            ]
        }
    ];
}

function staticGroups(item) {
    const o = item.options || {};
    const isFood = item.isFood || (item.category && item.category.toLowerCase().includes('food'));
    const groups = [];
    const sizes = (o.sizes?.length ? o.sizes : (!isFood ? ['Regular', 'Large'] : [])).filter(s => s && s.toLowerCase() !== 'jumbo');
    if (sizes.length) {
        groups.push({ id: 'size', key: 'size', name: 'Ukuran', values: sizes.map((v) => ({ id: v, text: v })), default: sizes[0], impact: true });
    }
    const temps = o.temps?.length ? o.temps : (!isFood ? ['Ice', 'Hot'] : []);
    if (temps.length) {
        groups.push({ id: 'temp', key: 'temp', name: 'Suhu', values: temps.map((v) => ({ id: v, text: v })), default: temps[0] });
    }
    const ices = o.ices?.length ? o.ices : (!isFood ? ['Normal Ice', 'Less Ice', 'No Ice'] : []);
    if (ices.length) {
        groups.push({ id: 'ice', key: 'ice', name: 'Level es', values: ices.map((v) => ({ id: v, text: v })), default: ices[0], hideWhen: { temp: 'Hot' } });
    }
    const sugars = o.sugars?.length ? o.sugars : (!isFood ? ['Normal Sugar', 'Less Sugar', 'No Sugar'] : []);
    if (sugars.length) {
        groups.push({ id: 'sugar', key: 'sugar', name: 'Level gula', values: sugars.map((v) => ({ id: v, text: v })), default: sugars[0] });
    }
    return groups;
}

function restoreSelection(choices = []) {
    for (const c of choices || []) {
        const g = AppState.sheet?.groups?.find((x) => x.name === c.group || x.key === c.group);
        const v = g?.values.find((x) => x.text === c.value || x.id === c.value);
        if (g && v) AppState.sheet.sel[g.id] = v.id;
        const a = AppState.sheet?.addons?.find((x) => x.name === c.group || x.id === c.group);
        const av = a?.values.find((x) => `+${x.text}` === c.value || x.text === c.value || x.id === c.value);
        if (a && av) AppState.sheet.picked.add(`${a.id}:${av.id}`);
    }
}

/** Add-on values the current variant allows (official add-ons depend on the combination). */
function availableAddons() {
    const sh = AppState.sheet;
    if (!sh) return [];
    const allowed = sh.comboAddons && sh.prices ? new Set(sh.comboAddons[comboKey(sh.sel)] || []) : null;
    const list = (sh.addons && sh.addons.length) ? sh.addons : defaultAddons(sh.item);
    return list
        .map((a) => ({ ...a, values: a.id === 'extras' || !allowed ? a.values : a.values.filter((v) => allowed.has(v.id)) }))
        .filter((a) => a.values && a.values.length);
}

function addonTotal() {
    let t = 0;
    for (const a of availableAddons()) {
        for (const v of a.values) {
            if (AppState.sheet?.picked?.has(`${a.id}:${v.id}`)) t += Number(v.price || 0);
        }
    }
    return t;
}

const impactGroups = () => (AppState.sheet?.groups || []).filter((g) => g.impact && AppState.sheet?.prices);
const comboKey = (sel) => impactGroups().map((g) => sel[g.id]).join('|');

/** Price of the chosen variant, without add-ons. */
function unitPrice(sel = AppState.sheet?.sel) {
    const sh = AppState.sheet;
    if (!sh || !sh.item) return 0;
    const { item, prices } = sh;
    if (prices) {
        const key = comboKey(sel);
        if (prices[key] != null) return prices[key];
    }
    const isLarge = sel?.size === 'Large' || sel?.['size'] === 'Large' || Object.values(sel || {}).includes('Large');
    if (isLarge && item.largePrice) return item.largePrice;
    return item.price;
}

/** Pre-discount price of the chosen variant (for the strike-through), or null. */
function normalPrice(sel = AppState.sheet?.sel) {
    const sh = AppState.sheet;
    if (!sh || !sh.item) return null;
    const { item, normals, prices } = sh;
    if (prices && normals) {
        const key = comboKey(sel);
        if (normals[key] != null) return normals[key];
    }
    const isLarge = sel?.size === 'Large' || sel?.['size'] === 'Large' || Object.values(sel || {}).includes('Large');
    if (isLarge && item.largePrice) return item.largeNormal || (item.normalPrice ? item.normalPrice + 6000 : null);
    return item.normalPrice || null;
}

function ensureValidCombo(changedGroup) {
    const sh = AppState.sheet;
    if (!sh) return;
    const { prices, sel } = sh;
    if (!prices || prices[comboKey(sel)] != null) return;
    // Pick the available combination closest to the current selection
    const ids = impactGroups().map((g) => g.id);
    let best = null;
    let bestScore = -1;
    for (const k of Object.keys(prices)) {
        const vals = k.split('|');
        if (changedGroup && vals[ids.indexOf(changedGroup)] !== sel[changedGroup]) continue;
        const score = vals.reduce((a, v, i) => a + (sel[ids[i]] === v ? 1 : 0), 0);
        if (score > bestScore) [best, bestScore] = [vals, score];
    }
    if (best) ids.forEach((id, i) => (sel[id] = best[i]));
}

function selectOption(gid, vid) {
    if (!AppState.sheet) return;
    AppState.sheet.sel[gid] = vid;
    ensureValidCombo(gid);
    renderOptions();
}

function isHidden(g, sel) {
    return Object.entries(g.hideWhen || {}).some(([gid, vid]) => sel[gid] === vid);
}

const GROUP_LABELS = {
    Temperature: 'Suhu',
    Size: 'Ukuran',
    Beans: 'Biji kopi',
    'Milk Option': 'Susu',
    'Sugar Level': 'Level gula',
    'Ice Level': 'Level es'
};

function renderOptions() {
    const sh = AppState.sheet;
    if (!sh || !sh.item) return;

    const base = unitPrice();
    const html = (sh.groups || [])
        .filter((g) => !isHidden(g, sh.sel))
        .map((g) => {
            const ref = g.impact ? unitPrice({ ...sh.sel, [g.id]: g.default }) : null;
            const pills = g.values.map((v) => {
                let extra = '';
                let disabled = false;
                if (g.impact) {
                    const p = unitPrice({ ...sh.sel, [g.id]: v.id });
                    if (sh.prices && p == null) {
                        disabled = !Object.keys(sh.prices).some((k) => k.split('|')[impactGroups().findIndex((x) => x.id === g.id)] === v.id);
                    } else if (ref != null && p != null && p > ref) {
                        extra = ` <small>+${formatRp(p - ref)}</small>`;
                    }
                }
                return `<button type="button" class="opt-pill ${sh.sel[g.id] === v.id ? 'active' : ''}" data-g="${esc(g.id)}" data-v="${esc(v.id)}" aria-pressed="${sh.sel[g.id] === v.id}" ${disabled ? 'disabled' : ''}>${esc(v.text)}${extra}</button>`;
            });
            return `<div class="opt-group" role="group" aria-label="${esc(GROUP_LABELS[g.name] || g.name)}"><div class="opt-title">${esc(GROUP_LABELS[g.name] || g.name)}</div><div class="opt-choices">${pills.join('')}</div></div>`;
        })
        .join('');

    const addonHtml = availableAddons()
        .map(
            (a) => `<div class="opt-group" role="group" aria-label="${esc(a.name)}"><div class="opt-title">${esc(a.name)} <span class="opt-hint">opsional, boleh lebih dari satu</span></div><div class="opt-choices">${a.values
                .map((v) => {
                    const on = sh.picked.has(`${a.id}:${v.id}`);
                    return `<button type="button" class="opt-pill addon ${on ? 'active' : ''}" data-addon="${esc(`${a.id}:${v.id}`)}" aria-pressed="${on}">${esc(v.text)}<small>+${formatRp(v.price)}</small></button>`;
                })
                .join('')}</div></div>`,
        )
        .join('');

    const itemOptionsEl = document.getElementById('itemOptions');
    if (itemOptionsEl) {
        itemOptionsEl.innerHTML = html + addonHtml + (sh.loading ? `<div class="opt-loading"><span class="spinner"></span> Cek varian & stok di outlet…</div>` : '');
    }

    const extra = addonTotal();
    const price = (base ?? sh.item.price) + extra;
    const normal = normalPrice();

    const priceEl = document.getElementById('modalProductPrice');
    const normalPriceEl = document.getElementById('modalProductNormalPrice');
    const addToCartBtn = document.getElementById('addToCartBtn');

    if (priceEl) priceEl.textContent = formatRp(price);
    if (normalPriceEl) {
        normalPriceEl.textContent = normal && normal > (base ?? 0) ? formatRp(normal + extra) : '';
    }

    if (addToCartBtn) {
        addToCartBtn.innerHTML = `<span>Tambahkan ke Keranjang • ${formatRp(price * sh.qty)}</span>`;
        addToCartBtn.disabled = base == null;
    }
}

async function loadItemDetail(item, line) {
    const sheet = AppState.sheet;
    if (!sheet) return;
    sheet.loading = true;
    renderOptions();
    try {
        const outletCode = AppState.outlet?.code || 'LA.CODEFIN';
        const itemCode = item.code || '';
        const itemKey = item.key || item.id || '';
        const res = await fetch(`api/kopken/item?outlet=${encodeURIComponent(outletCode)}&code=${encodeURIComponent(itemCode)}&key=${encodeURIComponent(itemKey)}&_t=${Date.now()}`, { cache: 'no-store' });
        const d = await res.json();
        if (AppState.sheet !== sheet) return;
        if (d.isSoldOut) {
            closeSheet('productSheet');
            item.isSoldOut = true;
            showToast(`Maaf, ${item.name} sedang habis di outlet ini.`);
            return;
        }
        if (d.groups?.length && d.prices && Object.keys(d.prices).length) {
            // Filter out 'Jumbo' from groups
            sheet.groups = d.groups.map(g => {
                const gKey = (g.key || '').toLowerCase();
                const gName = (g.name || '').toLowerCase();
                if (gKey === 'size' || gName === 'size' || gName === 'ukuran') {
                    return {
                        ...g,
                        values: (g.values || []).filter(v => {
                            const t = (v.text || '').toLowerCase();
                            const vId = (v.id || '').toLowerCase();
                            return t !== 'jumbo' && vId !== 'jumbo' && !t.includes('jumbo');
                        })
                    };
                }
                return g;
            });
            sheet.prices = d.prices;
            sheet.normals = d.normals || null;
            sheet.sel = {};
            for (const g of sheet.groups) sheet.sel[g.id] = g.default;
            sheet.addons = [...(d.addons || [])];
            sheet.comboAddons = d.comboAddons && Object.keys(d.comboAddons).length ? d.comboAddons : null;
            sheet.picked = new Set();
            if (line) restoreSelection(line.choices);
            ensureValidCombo();
        } else if (d.addons && Array.isArray(d.addons) && d.addons.length) {
            sheet.addons = d.addons;
        }
        if (d.desc && !item.desc) {
            const descEl = document.getElementById('modalProductDesc');
            if (descEl) descEl.textContent = d.desc;
        }
    } catch (err) {
        console.warn('loadItemDetail error:', err);
    } finally {
        if (AppState.sheet === sheet) {
            sheet.loading = false;
            renderOptions();
        }
    }
}

function currentChoices(groups, sel) {
    return (groups || []).filter((g) => !isHidden(g, sel)).map((g) => ({
        group: g.name,
        value: g.values.find((v) => v.id === sel[g.id])?.text || ''
    }));
}

function pickedChoices() {
    const out = [];
    for (const a of availableAddons()) {
        for (const v of a.values) {
            if (AppState.sheet?.picked?.has(`${a.id}:${v.id}`)) {
                out.push({
                    id: `${a.id}:${v.id}`,
                    group: a.name,
                    name: v.text,
                    value: `+${v.text}`,
                    price: Number(v.price || 0)
                });
            }
        }
    }
    return out;
}

function optionText(opts) {
    if (!opts) return '';
    if (Array.isArray(opts)) {
        return opts
            .map((c) => (typeof c === 'string' ? c : c && (c.value || c.text || c.name)))
            .filter(Boolean)
            .join(' • ');
    }
    const parts = [];
    if (opts.temp) parts.push(opts.temp);
    if (opts.size) parts.push(opts.size);
    if (opts.ice && opts.temp !== 'Hot') parts.push(opts.ice);
    if (opts.sugar) parts.push(opts.sugar);
    return parts.join(' • ');
}

function openProductModal(item, editIndex = null) {
    if (isOutletClosed()) {
        showToast(`⚠️ Outlet ${AppState.outlet?.name || ''} sedang tutup.`);
        openSheet('outletSheet');
        return;
    }
    AppState.selectedProduct = item;
    AppState.selectedQty = 1;

    const staticG = staticGroups(item);
    AppState.sheet = {
        item,
        groups: staticG,
        prices: null,
        normals: null,
        sel: {},
        qty: 1,
        editIndex,
        loading: false,
        addons: defaultAddons(item),
        comboAddons: null,
        picked: new Set(),
    };
    for (const g of AppState.sheet.groups) {
        AppState.sheet.sel[g.id] = g.default;
    }

    const nameEl = document.getElementById('modalProductName');
    const catEl = document.getElementById('modalProductCat');
    const descEl = document.getElementById('modalProductDesc');
    const imgEl = document.getElementById('modalProductImg');
    const noteEl = document.getElementById('modalItemNote');
    const qtyValEl = document.getElementById('qtyVal');

    if (nameEl) nameEl.textContent = item.name;
    if (catEl) catEl.textContent = item.category || 'Coffee';
    if (descEl) descEl.textContent = item.desc || '';
    if (imgEl) imgEl.src = item.img || 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae';
    if (noteEl) noteEl.value = '';
    if (qtyValEl) qtyValEl.textContent = '1';

    renderOptions();
    openSheet('productSheet');

    // Live variants, prices & addons straight from outlet
    if (item.code && AppState.outlet?.code) {
        loadItemDetail(item);
    }
}

// Event delegation on #itemOptions matching client spec
$('#itemOptions')?.addEventListener('click', (e) => {
    const b = e.target.closest('.opt-pill');
    if (!b || b.disabled) return;
    if (b.dataset.addon) {
        const k = b.dataset.addon;
        const S = AppState;
        if (S.sheet.picked.has(k)) S.sheet.picked.delete(k);
        else S.sheet.picked.add(k);
        renderOptions();
    } else if (b.dataset.g && b.dataset.v) {
        selectOption(b.dataset.g, b.dataset.v);
    }
});

// Stepper
const qtyMinus = document.getElementById('qtyMinus');
const qtyPlus = document.getElementById('qtyPlus');
const qtyVal = document.getElementById('qtyVal');

if (qtyMinus && qtyPlus && qtyVal) {
    qtyMinus.addEventListener('click', () => {
        if (AppState.sheet && AppState.sheet.qty > 1) {
            AppState.sheet.qty--;
            AppState.selectedQty = AppState.sheet.qty;
            qtyVal.textContent = AppState.sheet.qty;
            renderOptions();
        }
    });

    qtyPlus.addEventListener('click', () => {
        if (AppState.sheet) {
            AppState.sheet.qty = Math.min(20, (AppState.sheet.qty || 1) + 1);
            AppState.selectedQty = AppState.sheet.qty;
            qtyVal.textContent = AppState.sheet.qty;
            renderOptions();
        }
    });
}

// Add Single Item to Cart
const addToCartBtn = document.getElementById('addToCartBtn');
if (addToCartBtn) {
    addToCartBtn.addEventListener('click', () => {
        const sh = AppState.sheet;
        if (!sh || !sh.item) return;

        const variant = unitPrice();
        if (variant == null) return;
        const addonsTotal = addonTotal();
        const finalPrice = variant + addonsTotal;
        const note = document.getElementById('modalItemNote')?.value.trim() || '';
        const choices = currentChoices(sh.groups, sh.sel);
        const pickedAddons = pickedChoices();
        const allChoices = [...choices, ...pickedAddons];

        const sizeVal = choices.find(c => c.group.toLowerCase().includes('size') || c.group.toLowerCase().includes('ukuran'))?.value || 'Regular';
        const tempVal = choices.find(c => c.group.toLowerCase().includes('temp') || c.group.toLowerCase().includes('suhu'))?.value || 'Ice';
        const iceVal = choices.find(c => c.group.toLowerCase().includes('ice') || c.group.toLowerCase().includes('es'))?.value || '';
        const sugarVal = choices.find(c => c.group.toLowerCase().includes('sugar') || c.group.toLowerCase().includes('gula'))?.value || '';

        const itemToAdd = {
            id: 'item_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4),
            key: sh.item.key || sh.item.id,
            name: sh.item.name,
            price: finalPrice,
            basePrice: variant,
            qty: sh.qty,
            size: sizeVal,
            temp: tempVal,
            ice: iceVal,
            sugar: sugarVal,
            choices: allChoices,
            variantText: optionText(choices),
            optionsText: optionText(allChoices),
            addons: pickedAddons,
            addonsTotal: addonsTotal,
            note: note,
            img: sh.item.img,
            subtotal: finalPrice * sh.qty,
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
    if (isOutletClosed()) {
        showToast(`⚠️ Maaf, tidak dapat menambah menu karena outlet sedang tutup.`);
        openSheet('outletSheet');
        return;
    }

    // Bundling packages are exempt from single-item fee (+2.000) and warning modal
    if (item.isBundling || item.category === 'Bundling') {
        commitItemToCart(item);
        return;
    }

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

    // Calculate Single Item Fee (+2.000 if total qty == 1, but exempt if cart contains bundling)
    const hasBundling = AppState.cart.some(i => i.isBundling || i.category === 'Bundling');
    const hasSingleFee = (totalQty === 1) && !hasBundling;
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
    const summarySingleFee = document.getElementById('summarySingleFee');
    const freeFeeRow = document.getElementById('freeFeeRow');
    const plasticFeeRow = document.getElementById('plasticFeeRow');

    if (subtotalEl) subtotalEl.textContent = formatRp(subtotal);
    if (totalEl) totalEl.textContent = formatRp(grandTotal);

    // Fee Pesanan Satuan: Tulis "+Rp 2.000" jika 1 pcs, tulis "0" jika bebas fee / order 2+ pcs (Revisi Client)
    if (singleFeeRow) {
        singleFeeRow.style.display = totalQty > 0 ? 'flex' : 'none';
        if (summarySingleFee) {
            if (hasSingleFee) {
                summarySingleFee.textContent = `+${formatRp(AppState.singleItemFeeAmount)}`;
                summarySingleFee.style.color = 'var(--brand-accent)';
            } else {
                summarySingleFee.textContent = '0';
                summarySingleFee.style.color = 'var(--text-muted)';
            }
        }
    }
    if (freeFeeRow) freeFeeRow.style.display = 'none';
    if (plasticFeeRow) plasticFeeRow.style.display = AppState.hasPlasticBag ? 'flex' : 'none';

    // Update Checkout Button if Outlet Closed
    const checkoutWaBtn = document.getElementById('checkoutWaBtn');
    if (checkoutWaBtn) {
        if (isOutletClosed()) {
            checkoutWaBtn.disabled = true;
            checkoutWaBtn.style.opacity = '0.7';
            checkoutWaBtn.innerHTML = `<span>⚠️ Outlet Sedang Tutup (Ganti Outlet)</span>`;
        } else {
            checkoutWaBtn.disabled = false;
            checkoutWaBtn.style.opacity = '1';
            checkoutWaBtn.innerHTML = `
                <span>Kirim Pesanan ke WhatsApp</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            `;
        }
    }

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
                <div style="font-size:11px;color:var(--text-muted)">${item.variantText || `${item.size ? item.size + ' • ' : ''}${item.temp || 'Ice'}${item.sugar ? ' • ' + item.sugar : ''}`}</div>
                ${item.bundleChoices ? `<div style="font-size:10px;color:var(--brand-primary-dark);font-weight:700">${item.bundleChoices}</div>` : ''}
                ${item.addons && item.addons.length ? `<div style="font-size:10px;color:var(--brand-primary-dark);font-weight:700;margin-top:2px">+ ${item.addons.map(a => `${a.name || a.text || a.value} (+${formatRp(a.price)})`).join(', ')}</div>` : ''}
                ${item.note ? `<div style="font-size:10px;color:var(--text-muted);font-style:italic">"${item.note}"</div>` : ''}
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

function getCurrentTimeInTz(tz) {
    const tzMap = {
        'WIB': 'Asia/Jakarta',
        'WITA': 'Asia/Makassar',
        'WIT': 'Asia/Jayapura'
    };
    const timeZone = tzMap[tz] || 'Asia/Jakarta';
    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone,
            hour: 'numeric',
            minute: 'numeric',
            hour12: false
        }).formatToParts(new Date());

        let h = 0, m = 0;
        for (const p of parts) {
            if (p.type === 'hour') h = parseInt(p.value, 10);
            if (p.type === 'minute') m = parseInt(p.value, 10);
        }
        return { hour: h, minute: m };
    } catch {
        const now = new Date();
        return { hour: now.getHours(), minute: now.getMinutes() };
    }
}

function populate15MinPickupTimes() {
    if (!pickupTimeSelect) return;
    pickupTimeSelect.innerHTML = '';

    const tz = AppState.pickupTimezone || 'WIB';
    const { hour: curH, minute: curM } = getCurrentTimeInTz(tz);

    // Buffer ~15 min from current time, then round UP to the next 15-minute mark
    // E.g. at 19:28 -> 19:28 + 15 = 19:43 -> rounded up to 19:45!
    let totalTargetMin = curH * 60 + curM + 15;
    let roundedMin = Math.ceil(totalTargetMin / 15) * 15;
    let startH = Math.floor(roundedMin / 60);
    let startM = roundedMin % 60;

    // Minimum operating start hour is 07:00
    if (startH < 7) {
        startH = 7;
        startM = 0;
    }

    let addedCount = 0;
    // Generate times up to 23:45
    for (let h = startH; h <= 23; h++) {
        const firstM = (h === startH) ? startM : 0;
        for (let m = firstM; m < 60; m += 15) {
            const hh = String(h).padStart(2, '0');
            const mm = String(m).padStart(2, '0');
            const timeStr = `${hh}.${mm} ${tz}`;
            const opt = document.createElement('option');
            opt.value = timeStr;
            opt.textContent = timeStr;
            pickupTimeSelect.appendChild(opt);
            addedCount++;
        }
    }

    // If outlet is about to close (after 23:45 or late night), provide next day option
    if (addedCount === 0) {
        const opt = document.createElement('option');
        opt.value = `Besok 07.00 ${tz}`;
        opt.textContent = `Besok 07.00 ${tz} (Outlet Mendekati Jam Tutup)`;
        pickupTimeSelect.appendChild(opt);
    }

    AppState.pickupTime = pickupTimeSelect.value;
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
        if (isOutletClosed()) {
            alert(`Maaf, Outlet ${AppState.outlet?.name || ''} saat ini sedang tutup. Silakan pilih outlet lain yang sedang buka untuk memesan.`);
            openSheet('outletSheet');
            return;
        }

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
        const hasBundling = AppState.cart.some(i => i.isBundling || i.category === 'Bundling');
        const hasSingleFee = (totalQty === 1) && !hasBundling;

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

// =========================================================================
// 8. ADMIN PRICING & FEE MANAGEMENT CONTROLLER
// =========================================================================

let adminPricingState = {
    activePasscode: '',
    defaultMarkup: window.INITIAL_DATA?.pricing?.defaultMarkup || 5000,
    categoryMarkups: window.INITIAL_DATA?.pricing?.categoryMarkups || {
        Coffee: 5000,
        'Non Coffee': 5000,
        Frappe: 5000,
        Food: 4500,
        Minuman: 5000,
        Makanan: 4500,
    },
    itemMarkupOverrides: (window.INITIAL_DATA?.pricing?.itemMarkupOverrides && typeof window.INITIAL_DATA.pricing.itemMarkupOverrides === 'object' && !Array.isArray(window.INITIAL_DATA.pricing.itemMarkupOverrides))
        ? Object.assign({}, window.INITIAL_DATA.pricing.itemMarkupOverrides)
        : {},
    singleItemFee: window.INITIAL_DATA?.singleItemFee || 2000,
    plasticBagFee: window.INITIAL_DATA?.plasticBagFee || 1000,
    bundlingStatus: (window.INITIAL_DATA?.pricing?.bundlingStatus && typeof window.INITIAL_DATA.pricing.bundlingStatus === 'object')
        ? Object.assign({}, window.INITIAL_DATA.pricing.bundlingStatus)
        : {
            bundle_2_cup_large: true,
            bundle_2_frappe: true,
            promo_minuman_special: true,
            bundle_hemat_3_item: true,
        },
    disabledBundlingOptions: Array.isArray(window.INITIAL_DATA?.pricing?.disabledBundlingOptions)
        ? [...window.INITIAL_DATA.pricing.disabledBundlingOptions]
        : [],
};

function initAdminPricingFeature() {
    const adminTriggers = [
        document.getElementById('openAdminPricingBtn'),
        ...document.querySelectorAll('.open-admin-trigger-link')
    ].filter(Boolean);

    adminTriggers.forEach(btn => {
        btn.addEventListener('click', () => {
            adminPricingState.activePasscode = '';
            sessionStorage.removeItem('kyuti_admin_auth');
            sessionStorage.removeItem('kyuti_admin_code');
            const passInput = document.getElementById('adminPasscodeInput');
            const errBox = document.getElementById('adminPasscodeErr');
            if (passInput) passInput.value = '';
            if (errBox) errBox.style.display = 'none';
            openSheet('adminPasscodeModal');
        });
    });

    // Passcode Verification
    const passSubmitBtn = document.getElementById('adminPasscodeSubmitBtn');
    const passInput = document.getElementById('adminPasscodeInput');
    const passErr = document.getElementById('adminPasscodeErr');

    const handlePasscodeSubmit = () => {
        const code = (passInput?.value || '').trim();
        if (!code) {
            if (passErr) {
                passErr.textContent = 'Silakan masukkan passcode admin.';
                passErr.style.display = 'block';
            }
            return;
        }

        if (passSubmitBtn) {
            passSubmitBtn.disabled = true;
            passSubmitBtn.textContent = 'Memverifikasi...';
        }

        fetch(`api/admin_pricing.php?code=${encodeURIComponent(code)}&_t=${Date.now()}`)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    adminPricingState.activePasscode = code;
                    sessionStorage.setItem('kyuti_admin_auth', '1');
                    sessionStorage.setItem('kyuti_admin_code', code);
                    closeSheet('adminPasscodeModal');
                    populateAdminPricingForm(res.data, res.database);
                    openSheet('adminPricingSheet');
                    showToast('✓ Akses admin berhasil terverifikasi!');
                } else {
                    if (passErr) {
                        passErr.textContent = res.message || '✕ Kode redeem / passcode salah!';
                        passErr.style.display = 'block';
                    }
                }
            })
            .catch(() => {
                if (passErr) {
                    passErr.textContent = '✕ Gagal menghubungi server verifikasi.';
                    passErr.style.display = 'block';
                }
            })
            .finally(() => {
                if (passSubmitBtn) {
                    passSubmitBtn.disabled = false;
                    passSubmitBtn.textContent = 'Verifikasi & Masuk';
                }
            });
    };

    if (passSubmitBtn) passSubmitBtn.addEventListener('click', handlePasscodeSubmit);
    if (passInput) {
        passInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') handlePasscodeSubmit();
        });
    }

    // Menu Dropdown Selection Handler
    const menuSelect = document.getElementById('cfgMenuSelect');
    const customWrap = document.getElementById('cfgCustomNameWrap');
    const nameInput = document.getElementById('cfgNewItemName');
    const feeInput = document.getElementById('cfgNewItemFee');

    if (menuSelect) {
        menuSelect.addEventListener('change', () => {
            const val = menuSelect.value;
            if (val === '__CUSTOM__') {
                if (customWrap) customWrap.style.display = 'block';
                if (nameInput) {
                    nameInput.value = '';
                    nameInput.focus();
                }
            } else {
                if (customWrap) customWrap.style.display = 'none';
                if (nameInput) nameInput.value = val;

                // Pre-fill fee if already overridden, or suggest category/default fee
                if (val && adminPricingState.itemMarkupOverrides?.[val] !== undefined) {
                    if (feeInput) feeInput.value = adminPricingState.itemMarkupOverrides[val];
                } else if (val) {
                    const items = AppState.menu?.items || window.INITIAL_DATA?.menu?.items || [];
                    const found = items.find(i => i.name === val);
                    const cat = found?.category || 'Minuman';
                    const catMarkup = adminPricingState.categoryMarkups?.[cat];
                    const defaultFee = catMarkup !== undefined ? catMarkup : (adminPricingState.defaultMarkup || 5000);
                    if (feeInput) feeInput.value = defaultFee;
                }
            }
        });
    }

    // Add New Item Override Button
    const addItemBtn = document.getElementById('cfgAddNewItemBtn');
    if (addItemBtn) {
        addItemBtn.addEventListener('click', () => {
            let name = (menuSelect?.value || '').trim();
            if (name === '__CUSTOM__' || !name) {
                name = (nameInput?.value || '').trim();
            }
            const fee = parseInt(feeInput?.value || '0', 10);

            if (!name) {
                showToast('Silakan pilih menu dari daftar API terlebih dahulu');
                return;
            }
            if (isNaN(fee) || fee < 0) {
                showToast('Masukkan nominal fee tambahan yang valid');
                return;
            }

            adminPricingState.itemMarkupOverrides[name] = fee;
            renderItemOverridesList();
            populateMenuDropdown();

            if (menuSelect) menuSelect.value = '';
            if (nameInput) nameInput.value = '';
            if (feeInput) feeInput.value = '';
            if (customWrap) customWrap.style.display = 'none';

            showToast(`✓ Fee khusus untuk "${name}" diset +${formatRp(fee).replace('Rp ', '')}`);
        });
    }

    // Add Disabled Bundling Option Button
    const addDisabledBundlingBtn = document.getElementById('cfgAddDisabledOptionBtn');
    const disableBundlingSelect = document.getElementById('cfgDisableBundlingSelect');
    if (addDisabledBundlingBtn && disableBundlingSelect) {
        addDisabledBundlingBtn.addEventListener('click', () => {
            const val = disableBundlingSelect.value.trim();
            if (!val) {
                showToast('Silakan pilih menu bundling yang ingin dinonaktifkan.');
                return;
            }
            if (!adminPricingState.disabledBundlingOptions.includes(val)) {
                adminPricingState.disabledBundlingOptions.push(val);
                renderDisabledBundlingOptionsList();
                showToast(`✓ "${val}" dinonaktifkan dari menu bundling.`);
            } else {
                showToast(`Menu "${val}" sudah berstatus nonaktif.`);
            }
            disableBundlingSelect.value = '';
        });
    }

    // Save Admin Pricing Changes
    const saveBtn = document.getElementById('saveAdminPricingBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            const code = adminPricingState.activePasscode || sessionStorage.getItem('kyuti_admin_code') || '';
            if (!code) {
                showToast('✕ Sesi admin telah berakhir. Silakan masukkan kode kembali.');
                closeSheet('adminPricingSheet');
                return;
            }
            const defMarkup = parseFloat(document.getElementById('cfgDefaultMarkup')?.value || '5000');
            const drinkMarkup = parseFloat(document.getElementById('cfgCategoryDrinks')?.value || '5000');
            const foodMarkup = parseFloat(document.getElementById('cfgCategoryFood')?.value || '4500');
            const singleFee = parseFloat(document.getElementById('cfgSingleItemFee')?.value || '2000');
            const plasticFee = parseFloat(document.getElementById('cfgPlasticBagFee')?.value || '1000');

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span>Menyimpan pengaturan...</span>';

            const payload = {
                code: code,
                default_markup: defMarkup,
                category_markups: {
                    Coffee: drinkMarkup,
                    'Non Coffee': drinkMarkup,
                    Frappe: drinkMarkup,
                    Food: foodMarkup,
                    Minuman: drinkMarkup,
                    Makanan: foodMarkup,
                    Bundling: 0,
                },
                item_markup_overrides: adminPricingState.itemMarkupOverrides,
                single_item_fee: singleFee,
                plastic_bag_fee: plasticFee,
                bundling_status: adminPricingState.bundlingStatus,
                disabled_bundling_options: adminPricingState.disabledBundlingOptions,
            };

            fetch('api/admin_pricing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        AppState.singleItemFeeAmount = singleFee;
                        AppState.plasticBagFeeAmount = plasticFee;

                        // Synchronize in-memory admin pricing state & initial data
                        if (res.data) {
                            adminPricingState.itemMarkupOverrides = Object.assign({}, res.data.item_markup_overrides || {});
                            if (res.data.bundling_status) {
                                adminPricingState.bundlingStatus = Object.assign({}, res.data.bundling_status);
                            }
                            if (Array.isArray(res.data.disabled_bundling_options)) {
                                adminPricingState.disabledBundlingOptions = [...res.data.disabled_bundling_options];
                            }
                            if (window.INITIAL_DATA?.pricing) {
                                window.INITIAL_DATA.pricing.itemMarkupOverrides = Object.assign({}, res.data.item_markup_overrides || {});
                                window.INITIAL_DATA.pricing.defaultMarkup = res.data.default_markup ?? defMarkup;
                                window.INITIAL_DATA.pricing.categoryMarkups = Object.assign({}, res.data.category_markups || {});
                                window.INITIAL_DATA.pricing.bundlingStatus = Object.assign({}, res.data.bundling_status || adminPricingState.bundlingStatus);
                                window.INITIAL_DATA.pricing.disabledBundlingOptions = Array.isArray(res.data.disabled_bundling_options) ? [...res.data.disabled_bundling_options] : adminPricingState.disabledBundlingOptions;
                            }
                        } else {
                            adminPricingState.itemMarkupOverrides = Object.assign({}, payload.item_markup_overrides || {});
                        }

                        // Save to localStorage for instant client-side persistence across reloads
                        try {
                            localStorage.setItem('kyuti_custom_pricing', JSON.stringify(res.data || payload));
                        } catch (e) {}

                        // Also set a 1-year Cookie so EVERY request (including menu & checkout) sends this pricing to Vercel PHP backend
                        try {
                            const cookieData = encodeURIComponent(JSON.stringify(res.data || payload));
                            document.cookie = `kyuti_custom_pricing=${cookieData}; path=/; max-age=31536000; SameSite=Lax`;
                        } catch (e) {}

                        updateCartUI();
                        renderMenu();

                        closeSheet('adminPricingSheet');
                        adminPricingState.activePasscode = '';
                        sessionStorage.removeItem('kyuti_admin_auth');
                        sessionStorage.removeItem('kyuti_admin_code');
                        showToast('✓ Pengaturan fee & bundling berhasil disimpan!');

                        // Refresh menu to immediately apply new fee formula
                        if (AppState.outlet?.code) {
                            fetchMenu(AppState.outlet.code, true);
                        }
                    } else {
                        showToast(`✕ ${res.message || 'Gagal menyimpan fee'}`);
                    }
                })
                .catch(() => {
                    showToast('✕ Gagal menghubungi server.');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<span>💾 Simpan Perubahan Fee</span>';
                });
        });
    }
}

function loadAndOpenAdminPanel(code) {
    fetch(`api/admin_pricing.php?code=${encodeURIComponent(code)}&_t=${Date.now()}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                populateAdminPricingForm(res.data, res.database);
                openSheet('adminPricingSheet');
            } else {
                sessionStorage.removeItem('kyuti_admin_auth');
                sessionStorage.removeItem('kyuti_admin_code');
                openSheet('adminPasscodeModal');
            }
        })
        .catch(() => {
            openSheet('adminPasscodeModal');
        });
}

function populateAdminPricingForm(data, dbInfo) {
    if (!data) return;
    const defInput = document.getElementById('cfgDefaultMarkup');
    const drinksInput = document.getElementById('cfgCategoryDrinks');
    const foodInput = document.getElementById('cfgCategoryFood');
    const singleInput = document.getElementById('cfgSingleItemFee');
    const plasticInput = document.getElementById('cfgPlasticBagFee');

    if (defInput) defInput.value = data.default_markup ?? 5000;
    if (drinksInput) drinksInput.value = data.category_markups?.Coffee ?? data.category_markups?.Minuman ?? 5000;
    if (foodInput) foodInput.value = data.category_markups?.Food ?? data.category_markups?.Makanan ?? 4500;
    if (singleInput) singleInput.value = data.single_item_fee ?? 2000;
    if (plasticInput) plasticInput.value = data.plastic_bag_fee ?? 1000;

    const dbBadge = document.getElementById('adminDbStatusBadge');
    if (dbBadge) {
        if (dbInfo && dbInfo.cloud_connected) {
            dbBadge.innerHTML = '🟢 Terhubung (Vercel KV Cloud)';
            dbBadge.style.background = '#D1FAE5';
            dbBadge.style.color = '#065F46';
        } else {
            dbBadge.innerHTML = '🟡 Belum Terhubung (Lokal / Cache)';
            dbBadge.style.background = '#FEF3C7';
            dbBadge.style.color = '#92400E';
        }
    }

    adminPricingState.itemMarkupOverrides = Object.assign({}, data.item_markup_overrides || {});
    adminPricingState.bundlingStatus = Object.assign({
        bundle_2_cup_large: true,
        bundle_2_frappe: true,
        promo_minuman_special: true,
        bundle_hemat_3_item: true,
    }, data.bundling_status || {});
    adminPricingState.disabledBundlingOptions = Array.isArray(data.disabled_bundling_options)
        ? [...data.disabled_bundling_options]
        : [];

    renderItemOverridesList();
    renderBundlingStatusList();
    populateBundlingOptionsDropdown();
    renderDisabledBundlingOptionsList();
    populateMenuDropdown();
}

function renderBundlingStatusList() {
    const container = document.getElementById('cfgBundlingStatusList');
    if (!container) return;

    const packages = [
        { key: 'bundle_2_cup_large', label: 'PAKET BUNDLING 2 CUP LARGE' },
        { key: 'bundle_2_frappe', label: 'PAKET BUNDLING 2 FRAPPE' },
        { key: 'promo_minuman_special', label: 'PROMO MINUMAN SPECIAL' },
        { key: 'bundle_hemat_3_item', label: 'PAKET HEMAT 3 ITEM' },
    ];

    container.innerHTML = packages.map(pkg => {
        const isOn = adminPricingState.bundlingStatus[pkg.key] !== false;
        return `
            <div class="admin-bundling-toggle-row">
                <div>
                    <div style="font-size:12px;font-weight:800;color:var(--text-main)">${pkg.label}</div>
                    <small style="font-size:10px;color:${isOn ? '#059669' : '#DC2626'};font-weight:700">
                        ${isOn ? '● Status: Aktif (Ditampilkan di Website)' : '✕ Status: Nonaktif (Disembunyikan)'}
                    </small>
                </div>
                <label class="admin-toggle-switch">
                    <input type="checkbox" class="cfg-bundle-pkg-toggle" data-key="${pkg.key}" ${isOn ? 'checked' : ''}>
                    <span class="admin-toggle-slider"></span>
                </label>
            </div>
        `;
    }).join('');

    container.querySelectorAll('.cfg-bundle-pkg-toggle').forEach(input => {
        input.addEventListener('change', (e) => {
            const key = e.target.dataset.key;
            adminPricingState.bundlingStatus[key] = e.target.checked;
            renderBundlingStatusList();
            renderMenu();
        });
    });
}

function renderDisabledBundlingOptionsList() {
    const container = document.getElementById('cfgDisabledOptionsList');
    if (!container) return;

    const list = adminPricingState.disabledBundlingOptions || [];
    if (!list.length) {
        container.innerHTML = '<div style="font-size:11px;color:var(--text-muted);font-style:italic">Semua varian menu dalam paket bundling berstatus aktif.</div>';
        return;
    }

    container.innerHTML = list.map(item => `
        <div class="disabled-bundling-pill">
            <span>${esc(item)}</span>
            <button type="button" class="btn-remove-disabled-bundle" data-val="${esc(item)}" title="Aktifkan Kembali">✕</button>
        </div>
    `).join('');

    container.querySelectorAll('.btn-remove-disabled-bundle').forEach(btn => {
        btn.addEventListener('click', () => {
            const val = btn.dataset.val;
            adminPricingState.disabledBundlingOptions = adminPricingState.disabledBundlingOptions.filter(x => x !== val);
            renderDisabledBundlingOptionsList();
            showToast(`✓ "${val}" diaktifkan kembali di bundling.`);
        });
    });
}

function populateBundlingOptionsDropdown() {
    const select = document.getElementById('cfgDisableBundlingSelect');
    if (!select) return;

    const allOptionsSet = new Set();
    const bundlingItems = window.INITIAL_DATA?.bundlingItems || [];
    bundlingItems.forEach(b => {
        (b.slots || []).forEach(slot => {
            if (slot.groups) {
                Object.values(slot.groups).forEach(grp => {
                    grp.forEach(name => allOptionsSet.add(name));
                });
            } else if (Array.isArray(slot.options)) {
                slot.options.forEach(opt => {
                    const name = typeof opt === 'string' ? opt : opt.name;
                    if (name) {
                        if (name.includes('+')) {
                            name.split('+').forEach(p => allOptionsSet.add(p.trim()));
                            allOptionsSet.add(name);
                        } else {
                            allOptionsSet.add(name);
                        }
                    }
                });
            }
        });
    });

    const sorted = Array.from(allOptionsSet).sort((a, b) => a.localeCompare(b));
    let html = '<option value="">-- 🔍 Pilih Menu untuk Dinonaktifkan --</option>';
    sorted.forEach(opt => {
        html += `<option value="${esc(opt)}">${esc(opt)}</option>`;
    });
    select.innerHTML = html;
}

function renderItemOverridesList() {
    const container = document.getElementById('cfgItemOverridesList');
    if (!container) return;

    const entries = Object.entries(adminPricingState.itemMarkupOverrides || {});
    if (!entries.length) {
        container.innerHTML = '<div style="font-size:12px;color:var(--text-muted);font-style:italic">Belum ada fee khusus per menu. Semua menu mengikuti fee kategori/default.</div>';
        return;
    }

    container.innerHTML = entries.map(([name, fee]) => `
        <div style="display:flex;justify-content:space-between;align-items:center;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:8px 12px;">
            <div style="display:flex;align-items:center;gap:6px">
                <span style="font-size:12px;font-weight:800;color:var(--text-main)">${name}</span>
                <span class="fee-badge-pill" style="font-size:11px;padding:2px 8px">+Rp ${Number(fee).toLocaleString('id-ID')}</span>
            </div>
            <button type="button" class="btn-del-override" data-name="${name}" style="background:#FFE4E6;color:#E11D48;border:none;border-radius:6px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:700">
                Hapus ✕
            </button>
        </div>
    `).join('');

    container.querySelectorAll('.btn-del-override').forEach(btn => {
        btn.addEventListener('click', () => {
            const itemName = btn.dataset.name;
            if (itemName && adminPricingState.itemMarkupOverrides[itemName] !== undefined) {
                delete adminPricingState.itemMarkupOverrides[itemName];
                renderItemOverridesList();
                populateMenuDropdown();
                showToast(`Fee khusus "${itemName}" dihapus.`);
            }
        });
    });
}

function populateMenuDropdown() {
    const select = document.getElementById('cfgMenuSelect');
    if (!select) return;

    // Get live items from AppState.menu or initial data + bundling items
    let items = (AppState.menu?.items || window.INITIAL_DATA?.menu?.items || []).slice();
    if (Array.isArray(AppState.bundlingItems)) {
        AppState.bundlingItems.forEach(b => {
            if (b && b.name && !items.find(i => i.name === b.name)) {
                items.push({ name: b.name, category: 'Bundling' });
            }
        });
    }
    
    if (!items.length) {
        select.innerHTML = '<option value="">-- Menu sedang dimuat dari API... --</option>';
        return;
    }

    // Group items by category (Coffee, Non Coffee, Frappe, Food, Bundling, etc.)
    const groups = {};
    items.forEach(item => {
        if (!item || !item.name) return;
        const cat = item.category || 'Lainnya';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(item.name);
    });

    let html = '<option value="">-- 🔍 Pilih Menu dari API (Tinggal Klik) --</option>';
    
    // Sort categories: Coffee, Non Coffee, Frappe, Food, Bundling, etc.
    const catKeys = Object.keys(groups).sort((a, b) => {
        if (a === 'Coffee') return -1;
        if (b === 'Coffee') return 1;
        if (a === 'Non Coffee') return -1;
        if (b === 'Non Coffee') return 1;
        if (a === 'Frappe') return -1;
        if (b === 'Frappe') return 1;
        if (a === 'Food') return -1;
        if (b === 'Food') return 1;
        return a.localeCompare(b);
    });

    catKeys.forEach(cat => {
        const uniqueNames = Array.from(new Set(groups[cat])).sort((a, b) => a.localeCompare(b));
        const icon = cat === 'Coffee' ? '☕' : (cat === 'Non Coffee' ? '🍵' : (cat === 'Frappe' ? '🍦' : (cat === 'Food' ? '🥪' : (cat === 'Bundling' ? '🎀' : '📂'))));
        html += `<optgroup label="${icon} ${cat}">`;
        uniqueNames.forEach(name => {
            const currentOverride = adminPricingState.itemMarkupOverrides?.[name];
            const badge = currentOverride !== undefined ? ` ★ (+Rp ${Number(currentOverride).toLocaleString('id-ID')})` : '';
            html += `<option value="${name.replace(/"/g, '&quot;')}">${name}${badge}</option>`;
        });
        html += `</optgroup>`;
    });

    html += '<option value="__CUSTOM__">✍️ Ketik Nama Manual...</option>';

    select.innerHTML = html;
}

// =========================================================================
// 12. WI-FI OUTLET INTEGRATION (Revisi Client ala Referensi)
// =========================================================================

async function fetchWifi(outletCode) {
    const code = outletCode || AppState.outlet?.code || 'LA.CODEFIN';
    try {
        const res = await fetch(`api/kopken/wifi?outlet=${encodeURIComponent(code)}`);
        const data = await res.json();
        if (data && data.wifi) {
            updateWifiUI(data);
            return data.wifi;
        }
    } catch (err) {
        console.warn('fetchWifi error:', err);
    }
    const fallback = window.INITIAL_DATA?.wifi || {
        ssid: 'Teman Kenangan',
        password: 'KopiBikinSemangat@' + new Date().getDate()
    };
    updateWifiUI({ outlet: code, wifi: fallback, date: new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) });
    return fallback;
}

function updateWifiUI(data) {
    if (!data || !data.wifi) return;
    const ssid = data.wifi.ssid || 'Teman Kenangan';
    const pass = data.wifi.password || ('KopiBikinSemangat@' + new Date().getDate());
    const dateText = data.date || new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    // 1. Update Banner Card in Store View
    const ssidValEl = document.getElementById('wifiSsidVal');
    const passValEl = document.getElementById('wifiPassVal');
    const dateTextEl = document.getElementById('wifiTodayDate');

    if (ssidValEl) ssidValEl.textContent = ssid;
    if (passValEl) passValEl.textContent = pass;
    if (dateTextEl) dateTextEl.textContent = dateText;

    // 2. Update Modal in Store / Hub View
    const modalTitleEl = document.getElementById('wifiModalTitle');
    const modalDateEl = document.getElementById('wifiModalDate');
    const modalSsidEl = document.getElementById('wifiModalSsid');
    const modalPassEl = document.getElementById('wifiModalPass');

    if (modalTitleEl) modalTitleEl.textContent = `Wi-Fi ${AppState.outlet?.name || 'Outlet'}`;
    if (modalDateEl) modalDateEl.textContent = dateText;
    if (modalSsidEl) modalSsidEl.textContent = ssid;
    if (modalPassEl) modalPassEl.textContent = pass;
}

function copyWifiPassword() {
    const pass = document.getElementById('wifiPassVal')?.textContent || document.getElementById('wifiModalPass')?.textContent || '';
    if (!pass || pass === 'Memuat...') {
        showToast('Password Wi-Fi belum siap.');
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pass).then(() => {
            showToast('✓ Password Wi-Fi disalin ke clipboard!');
        }).catch(() => {
            fallbackCopyText(pass);
        });
    } else {
        fallbackCopyText(pass);
    }
}

function fallbackCopyText(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showToast('✓ Password Wi-Fi disalin!');
    } catch {
        showToast(`Password: ${text}`);
    }
    document.body.removeChild(ta);
}

function initWifiFeature() {
    if (window.INITIAL_DATA?.wifi) {
        updateWifiUI(window.INITIAL_DATA.wifi);
    }
    if (AppState.outlet?.code) {
        fetchWifi(AppState.outlet.code);
    }

    document.getElementById('copyWifiBannerBtn')?.addEventListener('click', copyWifiPassword);
    document.getElementById('wifiModalCopyBtn')?.addEventListener('click', copyWifiPassword);

    document.getElementById('wifiNavBtn')?.addEventListener('click', () => {
        openSheet('wifiModal');
    });
}

// Initial Boot
document.addEventListener('DOMContentLoaded', () => {
    updateOutletHeader();
    renderMenu();
    updateCartUI();
    checkWelcomePopup();
    initAdminPricingFeature();
    initWifiFeature();
});
