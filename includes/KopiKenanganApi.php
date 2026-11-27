<?php
/**
 * Kedai Kyuti - Kopi Kenangan Native API & Data Service
 * 
 * Fitur:
 * 1. Menyesuaikan jam operasional di seluruh outlet (Otomatis WIB & Manual Override)
 * 2. Menyesuaikan stok ketersediaan real-time dari APK Kopi Kenangan & Manual Override
 * 3. Formula Harga: (Harga Asli Outlet - 50% + Fee Tambahan)
 *    - Fee default (+5.000)
 *    - Fee per kategori (misal: Food +4.500, Minuman +5.000)
 *    - Fee khusus per menu (misal: Kopi Kenangan Mantan +6.000)
 * 4. Mendeteksi Outlet Premium (Mall, Bandara, Stasiun) secara otomatis
 * 5. Penggabungan Menu Bundling Khusus Kedai Kyuti
 * 6. Pemrosesan Pesanan WhatsApp dengan opsi Kantung Belanja (+1.000) & Fee Satuan 1 pcs (+2.000)
 */

class KopiKenanganApi
{
    protected string $baseUrl;
    protected string $origin;
    protected int $outletsTtl;
    protected int $stockTtl;
    protected string $cacheDir;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('api_base_url', 'https://kopkenapi.swamptechnology.id/api'), '/');
        $this->origin = rtrim((string) config('api_origin', 'https://kopkenapi.swamptechnology.id'), '/');
        $this->outletsTtl = (int) config('cache_ttl.outlets', 1800);
        $this->stockTtl = (int) config('stock.ttl_seconds', config('cache_ttl.menu', 60));
        $this->cacheDir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Fetch list of outlets with real-time hours evaluation, search, and distance sorting.
     * Directly queries kopkenapi.swamptechnology.id with zero DB overhead for optimal Vercel performance.
     */
    public function getOutlets(?string $query = null, ?float $lat = null, ?float $lng = null): array
    {
        $query = trim((string) $query);
        $isSearch = ($query !== '');
        $ttl = $isSearch ? 180 : $this->outletsTtl;
        $cacheKey = 'outlets_' . md5($query . '|' . ($lat ? round($lat, 3) : '') . '|' . ($lng ? round($lng, 3) : ''));
        
        $cached = $this->getCache($cacheKey, $ttl);
        if ($cached !== null && !empty($cached['outlets'])) {
            foreach ($cached['outlets'] as &$o) {
                $o = $this->evaluateOutletHours($o);
                $o['isPremium'] = $this->checkIsPremiumOutlet($o);
            }
            unset($o);
            return $cached;
        }

        $outlets = [];

        // 1. If GPS coordinates provided and no search query, query nearest stores directly from kopkenapi
        if ($lat !== null && $lng !== null && !$isSearch) {
            $nearestUrl = 'https://kopkenapi.swamptechnology.id/api/store/nearest';
            $resp = $this->httpRequest($nearestUrl, [
                'lat' => $lat,
                'lng' => $lng,
                'limit' => 30
            ]);
            $rawStores = $resp['data']['store'] ?? [];
            if (!empty($rawStores) && is_array($rawStores)) {
                foreach ($rawStores as $s) {
                    $code = $s['code'] ?? $s['store_code'] ?? '';
                    if (empty($code)) continue;
                    $outlets[] = [
                        'code' => $code,
                        'id' => $s['id'] ?? null,
                        'name' => $s['name'] ?? $s['store_name'] ?? $code,
                        'address' => $s['address'] ?? '',
                        'city' => $s['alias'] ?? '',
                        'lat' => (float)($s['latitude'] ?? 0),
                        'lng' => (float)($s['longitude'] ?? 0),
                        'isOpen' => !empty($s['is_open']),
                        'canOrder' => !empty($s['is_open']),
                        'hours' => (!empty($s['open']) && !empty($s['close'])) ? substr($s['open'], 0, 5) . ' - ' . substr($s['close'], 0, 5) : '07:00 - 22:00',
                        'category' => $s['category'] ?? '',
                        'phone' => $s['phone'] ?? '',
                        'distanceKm' => !empty($s['distance_str']) ? $s['distance_str'] : null,
                    ];
                }
            }
        }

        // 2. If no GPS or nearest was empty, query from fast bundled master database
        if (empty($outlets)) {
            $outlets = $this->getFallbackOutlets($query, $lat, $lng);
        }

        foreach ($outlets as &$o) {
            $o = $this->evaluateOutletHours($o);
            $o['isPremium'] = $this->checkIsPremiumOutlet($o);
        }
        unset($o);

        // Filter out excluded outlets per client request (Ruko Ambengan Surabaya)
        $outlets = array_values(array_filter($outlets, function ($o) {
            $code = $o['code'] ?? '';
            $name = $o['name'] ?? '';
            if ($code === 'KK.SBY.RKABNGAN' || stripos($name, 'Ambengan') !== false) {
                return false;
            }
            return true;
        }));

        $result = [
            'success' => true,
            'source' => 'kopkenapi.swamptechnology.id',
            'count' => count($outlets),
            'outlets' => $outlets,
        ];

        $this->setCache($cacheKey, $result);
        return $result;
    }

    /**
     * Get a specific outlet by code, searching upstream if not present in default outlet list.
     */
    public function getOutletByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || $code === 'KK.SBY.RKABNGAN' || stripos($code, 'Ambengan') !== false) {
            return null;
        }

        // 1. Check default outlets list first
        $defaultOutlets = $this->getOutlets();
        foreach ($defaultOutlets['outlets'] ?? [] as $o) {
            if (($o['code'] ?? '') === $code) {
                return $o;
            }
        }

        // 2. Query upstream with outlet code
        $searched = $this->getOutlets($code);
        foreach ($searched['outlets'] ?? [] as $o) {
            if (($o['code'] ?? '') === $code) {
                return $o;
            }
        }

        if (!empty($searched['outlets'])) {
            return $searched['outlets'][0];
        }

        // 3. Fallback evaluated
        return $this->evaluateOutletHours(['code' => $code, 'name' => $code]);
    }

    /**
     * Check if an outlet is classified as Premium (Mall, Bandara, Stasiun, etc.)
     */
    public function checkIsPremiumOutlet(array $outlet): bool
    {
        // 0. If explicit verified boolean true exists, return true immediately!
        if (!empty($outlet['isPremium']) || !empty($outlet['premium'])) {
            return true;
        }

        $category = strtolower($outlet['category'] ?? '');
        $name = strtolower($outlet['name'] ?? '');
        $address = strtolower($outlet['address'] ?? '');
        $code = strtoupper($outlet['code'] ?? '');
        $combined = $category . ' ' . $name . ' ' . $address;

        // 1. Check verified client premium outlet codes (Pajajaran Bandung, Galuh Mas, Stasiun, Bandara, etc.)
        $premiumCodes = config('outlets.premium_codes', []);
        if (in_array($code, $premiumCodes, true)) {
            return true;
        }

        // 2. Check verified client premium outlet names
        $premiumList = config('outlets.premium_list', []);
        foreach ($premiumList as $pName) {
            $pLower = strtolower(trim($pName));
            if ($pLower !== '' && str_contains($name, $pLower)) {
                // If Shell Galuhmas, it's not the shop house Galuh Mas Karawang
                if ($pLower === 'galuh mas karawang' && (str_contains($name, 'shell') || str_contains($category, 'gas station'))) {
                    continue;
                }
                return true;
            }
        }

        // 3. Gas stations / SPBU (Shell, Pertamina, BP, SPBU) are regular outlets, NOT premium
        $isGasStation = str_contains($category, 'gas station')
            || str_contains($combined, 'spbu')
            || str_contains($combined, 'pom bensin')
            || str_starts_with($name, 'shell')
            || str_starts_with($name, 'pertamina')
            || str_starts_with($name, 'bp ');

        if ($isGasStation && !str_contains($combined, 'bandara') && !str_contains($combined, 'airport')) {
            return false;
        }

        // 4. Categories for verified premium transport hubs: Train Station, Airport
        // Note: Generic 'mall' or 'plaza' are excluded because most regional malls (e.g. Karawang Central Plaza, Blok M Plaza) have regular 19k price!
        if ($category === 'train station' || $category === 'airport') {
            return true;
        }

        // 5. Upstream explicit indicator (if provided and not gas station)
        if (!empty($outlet['isPremium']) || !empty($outlet['premium'])) {
            return true;
        }

        // 6. Keywords for verified premium locations (Airports, Major Train Stations, Iconic Luxury Malls)
        // Generic 'mall', 'plaza', 'square', 'supermal' are excluded!
        $premiumKeywords = [
            'pajajaran bandung', 'stasiun kcjb', 'stasiun pasar turi', 'stasiun gambir', 'stasiun bandung',
            'terminal bandara', 'keberangkatan bandara', 'airport departure', 'bandara internasional',
            'terminal 2d', 'terminal 2e', 'terminal 3 domestic', 'terminal 3 international',
            'kualanamu', 'ngurah rai', 'soekarno hatta', 'halim perdana',
            'grand indonesia', 'senayan city', 'pacific place', 'plaza senayan', 'tunjungan plaza',
            'pim 2', 'pim 3', 'pondok indah mall', 'lotte mall', 'botani square', 'cihampelas walk',
            'central park', 'kota kasablanka', 'epicentrum walk', 'fx sudirman', 'margo city depok',
            'sdc serpong', 'teras kota'
        ];

        foreach ($premiumKeywords as $kw) {
            if (str_contains($combined, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate Operating Hours based on current WIB time and config overrides.
     */
    public function evaluateOutletHours(array $outlet): array
    {
        $code = $outlet['code'] ?? '';

        // 1. Check Manual Hours Override from config.php
        $hoursOverrides = config('outlets.hours_override', []);
        if (!empty($hoursOverrides[$code])) {
            $outlet['hours'] = $hoursOverrides[$code];
        }

        // 2. Check Manual Force Status from config.php
        $forceStatus = config('outlets.force_status', []);
        if (isset($forceStatus[$code])) {
            $forced = (bool) $forceStatus[$code];
            $outlet['isOpen'] = $forced;
            $outlet['canOrder'] = $forced;
            $outlet['statusText'] = $forced ? 'Buka' : 'Tutup Sementara';
            return $outlet;
        }

        // 3. Auto Time Check based on Current WIB Time (Asia/Jakarta)
        $autoTimeCheck = (bool) config('outlets.auto_time_check', true);
        if ($autoTimeCheck && !empty($outlet['hours'])) {
            $hours = trim($outlet['hours']);

            // Parse time range: e.g. "06:00 - 22:00" or "07.00 - 21.30"
            if (preg_match('/(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})/', $hours, $m)) {
                $openMinutes = (int) $m[1] * 60 + (int) $m[2];
                $closeMinutes = (int) $m[3] * 60 + (int) $m[4];

                $currentHour = (int) date('H');
                $currentMin = (int) date('i');
                $nowMinutes = $currentHour * 60 + $currentMin;

                $is24Hours = ($openMinutes <= 1 && $closeMinutes >= 23 * 60);

                if ($is24Hours) {
                    $isOpen = true;
                } elseif ($openMinutes <= $closeMinutes) {
                    $isOpen = ($nowMinutes >= $openMinutes && $nowMinutes <= $closeMinutes);
                } else {
                    $isOpen = ($nowMinutes >= $openMinutes || $nowMinutes <= $closeMinutes);
                }

                $outlet['isOpen'] = $isOpen;
                $outlet['canOrder'] = $isOpen;
                $outlet['statusText'] = $isOpen ? 'Buka' : 'Tutup';
            }
        }

        return $outlet;
    }

    /**
     * Fetch Real-time Menu, adjust stock, apply price formula (Harga Asli - 50% + Fee), and merge custom & bundling items.
     */
    public function getMenu(string $outletCode, bool $forceRefresh = false): array
    {
        $outletCode = trim($outletCode);
        if ($outletCode === '') {
            $outletCode = config('store.default_outlet', 'LA.CODEFIN');
        }

        $cacheKey = 'menu_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $outletCode);

        if ($forceRefresh) {
            $this->deleteCache($cacheKey);
            $cached = null;
        } else {
            $cached = $this->getCache($cacheKey, $this->stockTtl);
        }

        if ($cached !== null) {
            return $this->applyMenuCustomizations($cached, $outletCode);
        }

        $masterMenu = $this->getFallbackMenu($outletCode);

        // Direct fetch from kopkenapi.swamptechnology.id (0ms DB overhead, Vercel optimized)
        $url = 'https://kopkenapi.swamptechnology.id/api/product/query_product_menu';
        $response = $this->httpRequest($url, ['store_code' => $outletCode]);

        if (!empty($response['data']['menu_groups']) && is_array($response['data']['menu_groups'])) {
            $menuData = $this->parseKopkenApiMenu($response, $outletCode, $masterMenu);
        } else {
            // Instant graceful local master fallback if API is slow or unreachable
            $menuData = $masterMenu;
            $menuData['source'] = 'master_database';
            $menuData['live'] = false;
        }

        $outletInfo = null;
        $outletsData = $this->getOutlets();
        if (!empty($outletsData['outlets'])) {
            foreach ($outletsData['outlets'] as $o) {
                if (($o['code'] ?? '') === $outletCode) {
                    $outletInfo = $o;
                    break;
                }
            }
        }
        if (!$outletInfo) {
            $outletInfo = $this->evaluateOutletHours(['code' => $outletCode]);
        }
        $isClosed = false;
        if ($outletInfo && isset($outletInfo['canOrder']) && !$outletInfo['canOrder']) {
            $isClosed = true;
        }
        if (!empty($menuData['isClosed'])) {
            $isClosed = true;
        }
        $menuData['isClosed'] = $isClosed;
        $menuData['outletCode'] = $outletCode;

        // Strip heavy redundant upstream bloat before caching
        unset($menuData['bundles'], $menuData['satuan'], $menuData['seliter'], $menuData['raw_dump'], $menuData['bundleAll']);

        $this->setCache($cacheKey, $menuData);
        return $this->applyMenuCustomizations($menuData, $outletCode);
    }

    /**
     * Check if a product is a beverage/drink (100% NEVER a food item)
     */
    public function isDrinkBeverage(string $name): bool
    {
        $name = strtolower(trim($name));
        if ($name === '') return false;
        return (bool) preg_match('/latte|frappe|coffee|kopi|espresso|americano|cappuccino|macchiato|tea|matcha|shake|freeze|lemonade|chocolate|choco|boba|drink|susu|avocado|hibiscus|babyccino|cold\s*brew|bluebericano|strawbericano|cheesecake|cheesycake|chiller|speculoos|cloud|float|blend|shaken/i', $name);
    }

    /**
     * Check if a product is a food item (Bakery, Toast, Bread, Pastry, Meals)
     */
    public function isFoodProduct(string $name, string $category = ''): bool
    {
        $name = strtolower(trim($name));
        if ($name === '') return false;

        // 1. Any drink/beverage is 100% NEVER a food item!
        if ($this->isDrinkBeverage($name)) {
            return false;
        }

        // 2. Definite food products: bakery, toast, bread, pastry, meals
        if (preg_match('/puff|croissant|panini|toast|bread|roti|donut|bagel|bungeoppang|nugget|nasi|chicken|spaghetti|pasta|danish|pie|bolognese|carbonara|aglio|meal/i', $name)) {
            return true;
        }

        // 3. Cookie or Soft Bake only if not a drink
        if (preg_match('/cookie|soft\s*bake/i', $name)) {
            return true;
        }

        $cLower = strtolower(trim($category));
        if (in_array($cLower, ['food', 'toast', 'roti', 'snack', 'boulangerie', 'pastry', 'bakery'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Determine standard category for a product
     */
    public function determineItemCategory(string $name, string $groupCat = ''): string
    {
        $name = strtolower(trim($name));
        $norm = preg_replace('/[^a-z0-9]/', '', $name);

        if ($this->isFoodProduct($name, $groupCat)) {
            return 'Food';
        }

        // Frappe items
        if (str_contains($name, 'frappe') || str_contains($name, 'freeze') || str_contains($name, 'chiller') || str_contains($name, 'shake') || str_contains($norm, 'oreoshake')) {
            return 'Frappe';
        }

        // Coffee items
        if (
            str_contains($name, 'latte') ||
            str_contains($name, 'coffee') ||
            str_contains($name, 'kopi') ||
            str_contains($name, 'espresso') ||
            str_contains($name, 'americano') ||
            str_contains($name, 'cappuccino') ||
            str_contains($name, 'macchiato') ||
            str_contains($name, 'shaken') ||
            str_contains($name, 'bericano') ||
            str_contains($norm, 'duashoticedshaken')
        ) {
            if (str_contains($name, 'matcha') && !str_contains($name, 'espresso') && !str_contains($name, 'coffee')) {
                return 'Non Coffee';
            }
            return 'Coffee';
        }

        // Non Coffee items
        if (
            str_contains($name, 'matcha') ||
            str_contains($name, 'tea') ||
            str_contains($name, 'lemonade') ||
            str_contains($name, 'chocolate') ||
            str_contains($name, 'choco') ||
            str_contains($name, 'susu') ||
            str_contains($name, 'avocado') ||
            str_contains($name, 'hibiscus') ||
            str_contains($name, 'babyccino') ||
            str_contains($name, 'boba')
        ) {
            return 'Non Coffee';
        }

        // Cheesecake / Cheesycake without coffee keywords
        if (str_contains($name, 'cheesecake') || str_contains($name, 'cheesycake')) {
            return 'Frappe';
        }

        $g = trim($groupCat);
        if ($g === 'Non Coffee' || $g === 'Frappe' || $g === 'Food' || $g === 'Coffee') {
            return $g;
        }

        return 'Coffee';
    }

    /**
     * Parse raw menu groups from kopkenapi.swamptechnology.id into Kedai Kyuti standard items structure
     */
    protected function parseKopkenApiMenu(array $apiResponse, string $outletCode, array $masterMenu): array
    {
        $groups = $apiResponse['data']['menu_groups'] ?? [];
        if (empty($groups)) {
            return $masterMenu;
        }

        // Fast metadata lookup map from masterMenu for images, descriptions, options, largeBase
        $masterMap = [];
        foreach ($masterMenu['items'] ?? [] as $mi) {
            $mName = strtolower(trim($mi['name'] ?? ''));
            $mCode = (string)($mi['code'] ?? '');
            $mKey = (string)($mi['key'] ?? '');
            $norm = strtolower(preg_replace('/[^a-z0-9]/', '', $mName));
            if ($mName !== '') $masterMap[$mName] = $mi;
            if ($mCode !== '') $masterMap[$mCode] = $mi;
            if ($mKey !== '') $masterMap[$mKey] = $mi;
            if ($norm !== '') $masterMap[$norm] = $mi;
        }

        $liveItems = [];
        $seen = [];
        $kkmSinglePrice = null;

        foreach ($groups as $g) {
            $catName = trim($g['group_name'] ?? 'Coffee');
            $catLower = strtolower($catName);

            // Exclude upstream promo & combo groups (PEKA, PaHe, 2 Savory Toasts, Paket, etc.)
            // Kedai Kyuti operates as Jasdor with regular menu 50% + custom bundling packages
            if (
                str_contains($catLower, 'promo') ||
                str_contains($catLower, 'combo') ||
                str_contains($catLower, 'peka') ||
                str_contains($catLower, 'pahe') ||
                str_contains($catLower, 'paket')
            ) {
                continue;
            }

            // Exclude merchandise & seliter groups (Tumbler, Drip Bag, Seliter, Kenangan at Home, etc.)
            if (
                str_contains($catLower, 'merchandise') ||
                str_contains($catLower, 'tumbler') ||
                str_contains($catLower, 'seliter') ||
                str_contains($catLower, 'liter') ||
                str_contains($catLower, 'home')
            ) {
                continue;
            }

            if (stripos($catName, 'non coffee') !== false) {
                $stdCat = 'Non Coffee';
            } elseif (stripos($catName, 'frappe') !== false) {
                $stdCat = 'Frappe';
            } elseif (
                stripos($catName, 'food') !== false ||
                stripos($catName, 'toast') !== false ||
                stripos($catName, 'roti') !== false ||
                stripos($catName, 'chicken') !== false ||
                stripos($catName, 'pastry') !== false ||
                stripos($catName, 'bakery') !== false ||
                stripos($catName, 'snack') !== false ||
                stripos($catName, 'meal') !== false ||
                stripos($catName, 'chiller') !== false
            ) {
                $stdCat = 'Food';
            } else {
                $stdCat = 'Coffee';
            }

            foreach ($g['menu_products'] ?? [] as $p) {
                $name = trim($p['name'] ?? '');
                if ($name === '') continue;

                $lowerName = strtolower($name);
                $normProduct = preg_replace('/[^a-z0-9]/', '', $lowerName);

                // Exclude any upstream promo & combo items by name
                if (
                    preg_match('/^(peka\s*\d|pahe\s*\d|paket\s+|combo\s+|2\s+savory\s+toasts)/i', $name) ||
                    str_contains($lowerName, 'peka ') ||
                    str_contains($lowerName, 'pahe ') ||
                    str_contains($lowerName, 'savory toasts')
                ) {
                    continue;
                }

                // Exclude merchandise & seliter items (Tumbler, Drip Bag, Seliter, Sachet, Bottle, etc.)
                if (
                    str_contains($catLower, 'merchandise') ||
                    str_contains($catLower, 'tumbler') ||
                    str_contains($catLower, 'seliter') ||
                    str_contains($lowerName, 'tumbler') ||
                    str_contains($lowerName, 'drip bag') ||
                    str_contains($lowerName, 'seliter') ||
                    str_contains($lowerName, '1 liter') ||
                    str_contains($lowerName, '1l') ||
                    str_contains($lowerName, 'sachet') ||
                    str_contains($lowerName, 'tote bag') ||
                    str_contains($lowerName, 'totebag') ||
                    str_contains($lowerName, 'jug ') ||
                    str_contains($lowerName, 'bottle') ||
                    str_contains($lowerName, 'merchandise')
                ) {
                    continue;
                }

                $pid = (string)($p['id'] ?? $p['product_code'] ?? $name);
                if (isset($seen[$pid]) || isset($seen[$lowerName])) {
                    continue;
                }
                $seen[$pid] = true;
                $seen[$lowerName] = true;

                $ref = $masterMap[$lowerName] ?? $masterMap[$pid] ?? $masterMap[$normProduct] ?? null;

                $origNormal = (float)($p['orig_price'] ?? $p['price'] ?? 0);
                if ($origNormal <= 0) {
                    $origNormal = (float)($ref['normalPrice'] ?? 19000);
                }

                // Check for single-cup KKM price (Client rule: normal price < 22000 is Regular, >= 22000 is Premium)
                $isSingleKkm = ($lowerName === 'kopi kenangan mantan' || $lowerName === 'kopi kenangan mantan (regular)')
                    || (str_contains($lowerName, 'kenangan mantan')
                        && !str_contains($lowerName, 'seliter')
                        && !str_contains($lowerName, 'liter')
                        && !str_contains($lowerName, 'duo')
                        && !str_contains($lowerName, 'bundl')
                        && !str_contains($lowerName, 'combo')
                        && !str_contains($lowerName, 'paket')
                        && !str_contains($lowerName, 'oatside')
                        && !str_contains($lowerName, 'frappe')
                        && !str_contains($lowerName, 'large'));

                if ($isSingleKkm && $kkmSinglePrice === null) {
                    $kkmSinglePrice = $origNormal;
                }

                // Clear food items: bakery, toast, bread, pastry, cookies, meals (NEVER a drink, even with choco/susu flavour)
                $isDrink = $this->isDrinkBeverage($name);
                $isDefiniteFood = !$isDrink && $this->isFoodProduct($name, $catName);
                $isFoodItem = $isDefiniteFood;

                // Category classification (Revisi: Dua Shot Iced Shaken -> Coffee, Oreo Shake -> Non Coffee, Frappe -> Frappe, Cheesecake -> Frappe/Coffee)
                $itemCat = $this->determineItemCategory($name, $catName);

                $origLargeBase = (float)($ref['largeBase'] ?? $ref['largeNormal'] ?? 0);
                $hasNoLarge = $isFoodItem || !empty($ref['noLarge']) || str_contains($normProduct, 'butterscotchseasaltcrumble');

                $liveItems[] = [
                    'id' => $p['id'] ?? null,
                    'code' => $p['product_code'] ?? ($ref['code'] ?? $pid),
                    'key' => $ref['key'] ?? $normProduct,
                    'name' => $name,
                    'category' => $itemCat,
                    'isFood' => $isFoodItem,
                    'desc' => !empty($p['description']) ? $p['description'] : ($ref['desc'] ?? ''),
                    'price' => $origNormal,
                    'normalPrice' => $isFoodItem ? 0 : $origNormal,
                    'largeBase' => (!$hasNoLarge && $origLargeBase > 0) ? $origLargeBase : null,
                    'largeNormal' => (!$hasNoLarge && $origLargeBase > 0) ? $origLargeBase : null,
                    'largePrice' => (!$hasNoLarge && !empty($ref['largePrice'])) ? (float)$ref['largePrice'] : null,
                    'noLarge' => $hasNoLarge,
                    'isSoldOut' => !empty($p['is_sold_out']),
                    'notCarried' => false,
                    'img' => !empty($p['image']) ? $p['image'] : ($ref['img'] ?? 'assets/img/menu/default.jpg'),
                    'options' => $isFoodItem ? [
                        'sizes' => [],
                        'temps' => [],
                        'ices' => [],
                        'sugars' => []
                    ] : ($ref['options'] ?? [
                        'sizes' => $hasNoLarge ? ['Regular'] : ['Regular', 'Large'],
                        'temps' => ['Ice', 'Hot'],
                        'ices' => ['Normal Ice', 'Less Ice', 'No Ice'],
                        'sugars' => ['Normal Sugar', 'Less Sugar', 'No Sugar']
                    ])
                ];
            }
        }

        // Outlet premium classification (KKM normal price > 22000 e.g. Bandara/Stasiun or verified luxury mall)
        $outletInfo = $this->getOutletByCode($outletCode) ?: $this->evaluateOutletHours(['code' => $outletCode]);
        if ($kkmSinglePrice !== null && $kkmSinglePrice > 0) {
            $isPremiumOutlet = ($kkmSinglePrice > 22000) || $this->checkIsPremiumOutlet($outletInfo);
        } else {
            $isPremiumOutlet = $this->checkIsPremiumOutlet($outletInfo);
        }

        return [
            'success' => true,
            'outlet' => $outletCode,
            'outletCode' => $outletCode,
            'count' => count($liveItems),
            'items' => $liveItems,
            'isPremium' => $isPremiumOutlet,
            'premium' => $isPremiumOutlet,
            'source' => 'kopkenapi.swamptechnology.id',
            'live' => true,
            'isClosed' => false
        ];
    }

    /**
     * Apply Formula: (Harga Asli Kopken - 50% + Fee)
     * Plus category fees (Food: +4.500, Minuman: +5.000) & item overrides (Kopi Kenangan Mantan: +6.000)
     */
    protected function applyMenuCustomizations(array $menuData, string $outletCode): array
    {
        $items = $menuData['items'] ?? [];
        $stockOverrides = config('stock.overrides', []);
        $priceOverrides = config('pricing.price_overrides', []);
        $itemMarkupOverrides = config('pricing.item_markup_overrides', []);
        $categoryMarkups = config('pricing.category_markups', []);
        $defaultMarkup = (float) config('pricing.default_markup', 5000);
        $outletInfo = $this->getOutletByCode($outletCode) ?: $this->evaluateOutletHours(['code' => $outletCode]);

        $mantanPrice = null;
        foreach ($items as $it) {
            $itName = strtolower($it['name'] ?? '');
            $isSingleKkm = ($itName === 'kopi kenangan mantan' || $itName === 'kopi kenangan mantan (regular)')
                || (str_contains($itName, 'kenangan mantan')
                    && !str_contains($itName, 'seliter')
                    && !str_contains($itName, 'liter')
                    && !str_contains($itName, 'duo')
                    && !str_contains($itName, 'bundl')
                    && !str_contains($itName, 'combo')
                    && !str_contains($itName, 'paket')
                    && !str_contains($itName, 'oatside')
                    && !str_contains($itName, 'frappe')
                    && !str_contains($itName, 'large'));

            if ($isSingleKkm) {
                $mantanPrice = (float)($it['normalPrice'] ?? $it['outletPrice'] ?? 0);
                break;
            }
        }

        // Outlet premium classification (KKM normal price > 22000 e.g. Bandara/Stasiun or verified luxury mall)
        if ($mantanPrice !== null && $mantanPrice > 0) {
            $isPrem = ($mantanPrice > 22000) || $this->checkIsPremiumOutlet($outletInfo);
        } else {
            $isPrem = !empty($menuData['premium']) || $this->checkIsPremiumOutlet($outletInfo);
        }

        $menuData['premium'] = $isPrem;
        $menuData['isPremium'] = $isPrem;

        $discountPercent = (float) config('pricing.discount_percent', 50);

        // 1. Process API items
        $processedItems = [];
        $masterItemsCache = null;

        foreach ($items as &$item) {
            $key = $item['key'] ?? $item['id'] ?? '';
            $category = $item['category'] ?? 'Coffee';
            $name = $item['name'] ?? '';
            $normKey = strtolower(str_replace([' ', '_', '-'], '', (string)$key));
            $normName = strtolower(str_replace([' ', '_', '-'], '', (string)$name));
            $catLower = strtolower((string)$category);

            // Filter out upstream promo & combo items (e.g. PEKA 1, PEKA 2, 2 Savory Toasts, PaHe 1)
            // Kedai Kyuti uses custom bundling packages, not upstream combos
            if (
                (str_contains($catLower, 'promo') && str_contains($catLower, 'combo')) ||
                preg_match('/^(peka\s*\d|pahe\s*\d|2\s+savory\s+toasts|paket\s+|combo\s+)/i', $name) ||
                (str_contains($normName, 'peka') && preg_match('/\d/', $normName)) ||
                (str_contains($normName, 'pahe') && preg_match('/\d/', $normName))
            ) {
                continue;
            }

            // Filter out merchandise & seliter items (Tumbler, Drip Bag, Seliter, Sachet, Bottle, etc.)
            if (
                str_contains($catLower, 'merchandise') ||
                str_contains($catLower, 'tumbler') ||
                str_contains($catLower, 'seliter') ||
                str_contains($normName, 'tumbler') ||
                str_contains($normName, 'dripbag') ||
                str_contains($normName, 'seliter') ||
                str_contains($normName, '1liter') ||
                str_contains($normName, '1l') ||
                str_contains($normName, 'sachet') ||
                str_contains($normName, 'totebag') ||
                str_contains($normName, 'jug') ||
                str_contains($normName, 'bottle') ||
                str_contains($normName, 'merchandise')
            ) {
                continue;
            }

            // Clear food items: bakery, toast, bread, pastry, cookies, meals (NEVER a drink)
            $isDrink = $this->isDrinkBeverage($name);
            $isFood = !$isDrink && $this->isFoodProduct($name, $category);
            $category = $this->determineItemCategory($name, $category);

            if ($isFood) {
                $category = 'Food';
                $item['category'] = 'Food';
                $item['isFood'] = true;
                $item['noLarge'] = true;
                unset($item['largePrice'], $item['largeNormal'], $item['largeBase']);
                $item['options'] = [
                    'sizes' => [],
                    'temps' => [],
                    'ices' => [],
                    'sugars' => []
                ];
            } else {
                $item['isFood'] = false;
                $item['category'] = $category;
                if (empty($item['options']['sizes'])) {
                    $item['options'] = [
                        'sizes' => ['Regular', 'Large'],
                        'temps' => (stripos($name, 'frappe') !== false) ? ['Ice'] : ['Ice', 'Hot'],
                        'ices' => ['Normal Ice', 'Less Ice', 'No Ice'],
                        'sugars' => ['Normal Sugar', 'Less Sugar', 'No Sugar']
                    ];
                }
            }

            // Synchronize stock: if item is sold out or not carried at this outlet, mark as isSoldOut
            if (!empty($item['notCarried'])) {
                $item['isSoldOut'] = true;
            }

            // Override Stock from Admin settings if set
            if (isset($stockOverrides[$key])) {
                $item['isSoldOut'] = (bool) $stockOverrides[$key];
            }

            // Determine Original Price
            $origPrice = (float) ($item['normalPrice'] ?? $item['outletPrice'] ?? $item['price'] ?? 0);
            if ($origPrice <= 0) {
                $origPrice = (float) ($item['price'] ?? 0);
            }

            // If outlet is Premium and NOT live data (master database fallback mode only), adjust standard base prices:
            if (!empty($menuData['premium']) && empty($menuData['live'])) {
                if (str_contains($normName, 'oatside') && str_contains($normName, 'kenanganmantan')) {
                    $origPrice = 25000.0;
                } elseif (str_contains($normName, 'kenanganmantan') && !str_contains($normName, 'frappe')) {
                    $origPrice = 22000.0;
                } elseif (str_contains($normName, 'blackaren') || str_contains($normName, 'blacksaren')) {
                    $origPrice = 23000.0;
                } elseif (str_contains($normName, 'tarocloudlatte') || str_contains($normName, 'tarocloudmatcha')) {
                    $origPrice = 25000.0;
                } elseif (str_contains($normName, 'americano') && !str_contains($normName, 'banana')) {
                    $origPrice = 20000.0;
                } elseif (str_contains($normName, 'frappe')) {
                    if ($origPrice < 31000.0) $origPrice = 31000.0;
                } elseif (in_array(strtolower($category), ['coffee', 'non coffee', 'frappe', 'tea']) && empty($item['isFood'])) {
                    if ($origPrice > 0 && $origPrice <= 19000) {
                        $origPrice += 3000.0;
                    } elseif ($origPrice > 19000 && $origPrice < 25000) {
                        $origPrice += 2000.0;
                    }
                }
            }

            // Determine Markup Fee: Item override (by key or name) > Category markup > Default markup
            $fee = null;

            // 1. Check Item Markup Override by key or name (excluding Large-specific overrides)
            foreach ($itemMarkupOverrides as $oKey => $oFee) {
                // Large overrides only apply when calculating the Large size price
                if (preg_match('/\(large\)$/i', trim((string)$oKey))) {
                    continue;
                }
                $normOK = strtolower(str_replace([' ', '_', '-'], '', (string)$oKey));
                if ($oKey === $key || $oKey === $name || $normOK === $normKey || $normOK === $normName || str_contains($normName, $normOK) || str_contains($normKey, $normOK)) {
                    $fee = (float) $oFee;
                    break;
                }
            }

            // 2. Check Category Markup
            if ($fee === null) {
                foreach ($categoryMarkups as $cKey => $cFee) {
                    $cLower = strtolower($cKey);
                    $catLower = strtolower($category);
                    if ($cKey === $category || $cLower === $catLower) {
                        $fee = (float) $cFee;
                        break;
                    }
                    if (($cLower === 'food' || $cLower === 'makanan') && (!empty($item['isFood']) || $catLower === 'food')) {
                        $fee = (float) $cFee;
                        break;
                    }
                    if (($cLower === 'minuman' || $cLower === 'drinks' || $cLower === 'beverage') && empty($item['isFood']) && $catLower !== 'food') {
                        $fee = (float) $cFee;
                        break;
                    }
                }
            }

            // 3. Fallback to default markup
            if ($fee === null) {
                $fee = $defaultMarkup;
            }

            // Formula Fee (Murni: harga asli - 50% + fee, tanpa surcharge tambahan agar harga sesuai aplikasi asli)
            $effectiveFee = $fee;

            // Raw price from API
            $rawApiPrice = isset($item['price']) ? (float)$item['price'] : 0;

            // Calculate Selling Price using Kedai Kyuti Formula: (Harga Asli - 50%) + Fee
            if ($origPrice > 0) {
                $discountedBase = $origPrice * (1 - ($discountPercent / 100));
                $calculatedPrice = round($discountedBase + $effectiveFee);
            } elseif ($rawApiPrice > 0) {
                $calculatedPrice = round($rawApiPrice);
            } else {
                $calculatedPrice = (float)($item['price'] ?? 0);
            }

            // Manual fixed price override if configured (by key or name)
            foreach ($priceOverrides as $pKey => $pPrice) {
                $normPK = strtolower(str_replace([' ', '_', '-'], '', (string)$pKey));
                if ($pKey === $key || $pKey === $name || $normPK === $normKey || $normPK === $normName) {
                    $calculatedPrice = (float) $pPrice;
                    break;
                }
            }

            $item['price'] = $calculatedPrice;
            $item['normalPrice'] = $isFood ? 0 : $origPrice;

            // Menu khusus ukuran Regular saja (misal: Butterscotch Sea Salt Crumble tidak ada ukuran Large)
            $regularOnlyList = config('store.regular_only_items', [
                'Butterscotch Sea Salt Crumble',
                'butterscotchseasaltcrumble',
            ]);
            $isRegularOnly = false;
            foreach ($regularOnlyList as $regItem) {
                $normReg = strtolower(str_replace([' ', '_', '-'], '', (string)$regItem));
                if ($normName === $normReg || $normKey === $normReg || str_contains($normName, $normReg) || str_contains($normKey, $normReg)) {
                    $isRegularOnly = true;
                    break;
                }
            }

            if ($isRegularOnly) {
                $hasLargeSize = false;
                $item['noLarge'] = true;
                unset($item['largePrice'], $item['largeNormal']);
                if (isset($item['options']['sizes'])) {
                    $item['options']['sizes'] = array_values(array_filter((array)$item['options']['sizes'], function($s) {
                        return strtolower($s) !== 'large';
                    }));
                    if (empty($item['options']['sizes'])) {
                        $item['options']['sizes'] = ['Regular'];
                    }
                }
            } else {
                // Handle Large Price if available or if size options include Large
                $hasLargeSize = !empty($item['largePrice']) || (isset($item['options']['sizes']) && in_array('Large', (array)$item['options']['sizes'], true));
            }

            if ($hasLargeSize) {
                // Check if there is an override specifically for Large size (e.g. "Kopi Kenangan Mantan (Large)")
                $largeFee = null;
                $largeKey = $name . ' (Large)';
                $normLargeKey = strtolower(str_replace([' ', '_', '-', '(', ')'], '', $largeKey));

                foreach ($itemMarkupOverrides as $oKey => $oFee) {
                    $oKeyStr = trim((string)$oKey);
                    if (preg_match('/\(large\)$/i', $oKeyStr)) {
                        $normOK = strtolower(str_replace([' ', '_', '-', '(', ')'], '', $oKeyStr));
                        $basePart = trim(preg_replace('/\(large\)$/i', '', $oKeyStr));
                        $normBasePart = strtolower(str_replace([' ', '_', '-', '(', ')'], '', $basePart));

                        if (
                            strtolower($oKeyStr) === strtolower($largeKey) ||
                            $normOK === $normLargeKey ||
                            $normBasePart === $normKey ||
                            $normBasePart === $normName ||
                            str_contains($normName, $normBasePart) ||
                            str_contains($normBasePart, $normName)
                        ) {
                            $largeFee = (float)$oFee;
                            break;
                        }
                    }
                }

                $effectiveLargeFee = ($largeFee !== null) ? $largeFee : $fee;

                // Determine Original Large Price (Normal / Outlet Price)
                // Kopi Kenangan official rules:
                // Check raw data: largeBase or largeNormal first (holds real outlet large price)
                $origLarge = 0.0;
                if (!empty($item['largeBase']) && (float)$item['largeBase'] > 0) {
                    $origLarge = (float)$item['largeBase'];
                } elseif (!empty($item['largeNormal']) && (float)$item['largeNormal'] > 0) {
                    $origLarge = (float)$item['largeNormal'];
                }

                // If not directly on item, check master database
                if ($origLarge <= 0) {
                    if ($masterItemsCache === null) {
                        $masterMenu = $this->getFallbackMenu($outletCode);
                        $masterItemsCache = [];
                        foreach ($masterMenu['items'] ?? [] as $mi) {
                            $mn = strtolower(preg_replace('/[^a-z0-9]/', '', $mi['name'] ?? ''));
                            $mk = strtolower(preg_replace('/[^a-z0-9]/', '', $mi['key'] ?? ''));
                            if ($mn !== '') $masterItemsCache[$mn] = $mi;
                            if ($mk !== '') $masterItemsCache[$mk] = $mi;
                        }
                    }
                    $refMaster = $masterItemsCache[$normName] ?? $masterItemsCache[$normKey] ?? null;
                    if ($refMaster && !empty($refMaster['largeBase']) && (float)$refMaster['largeBase'] > 0) {
                        $origLarge = (float)$refMaster['largeBase'];
                    }
                }

                $isKKM = str_contains($normName, 'kenanganmantan') || str_contains($normKey, 'kenanganmantan') || str_contains($normName, 'kkm') || str_contains($normKey, 'kkm');
                if (!empty($menuData['premium'])) {
                    if ($isKKM) {
                        $origLarge = max($origLarge, $origPrice + 6000.0);
                    } elseif ($origLarge <= 0 || $origLarge <= $origPrice) {
                        $diff = ($origPrice >= 26000) ? 8000.0 : 6000.0;
                        $origLarge = $origPrice + $diff;
                    }
                } else {
                    if ($origLarge <= 0) {
                        if ($isKKM) {
                            $origLarge = 25000.0;
                        } elseif ($origPrice > 0) {
                            $diff = ($origPrice >= 26000) ? 8000.0 : 6000.0;
                            $origLarge = $origPrice + $diff;
                        }
                    }
                }

                // Ensure large normal price is always higher than regular normal price
                if ($origLarge <= $origPrice) {
                    $diff = ($origPrice >= 26000) ? 8000.0 : 6000.0;
                    $origLarge = $origPrice + $diff;
                }

                $item['largeNormal'] = $origLarge;
                $item['largeBase'] = $origLarge;

                // Calculate Large Selling Price using Kedai Kyuti Formula: (Harga Asli Large - 50%) + Fee
                $effectiveLargeMarkup = $effectiveLargeFee;
                if ($origLarge > 0) {
                    $discountedLarge = $origLarge * (1 - ($discountPercent / 100));
                    $calcLarge = round($discountedLarge + $effectiveLargeMarkup);
                } else {
                    $calcLarge = round(($origPrice * 1.35 * (1 - ($discountPercent / 100))) + $effectiveLargeMarkup);
                }

                // Check manual price override for large
                if (isset($priceOverrides[$largeKey])) {
                    $calcLarge = (float) $priceOverrides[$largeKey];
                }

                // Ensure large price is strictly higher than regular price
                if ($calcLarge <= $calculatedPrice) {
                    $calcLarge = $calculatedPrice + 2000;
                }

                $item['largePrice'] = $calcLarge;
            }

            // Guarantee image fallback
            if (empty($item['img'])) {
                $item['img'] = 'https://placehold.co/200x200?text=Kopi+Kenangan';
            }

            $processedItems[] = $item;
        }
        unset($item);

        // Sort items so that available items (isSoldOut == false) appear first, and sold-out items appear at the end
        $availList = [];
        $soldList = [];
        foreach ($processedItems as $it) {
            if (!empty($it['isSoldOut'])) {
                $soldList[] = $it;
            } else {
                $availList[] = $it;
            }
        }
        $items = array_merge($availList, $soldList);

        // 2. Prepend Bundling Packages (Selalu tersedia untuk pelanggan)
        $categories = $menuData['categories'] ?? [];
        $bundlingItems = config('bundling_items', []);
        $rawBundlingStatus = config('pricing.bundling_status', []);
        $bundlingStatus = array_merge([
            'bundle_2_cup_large' => true,
            'bundle_2_frappe' => true,
            'promo_minuman_special' => true,
            'bundle_hemat_3_item' => true,
        ], is_array($rawBundlingStatus) ? $rawBundlingStatus : []);
        $bundlingFormatted = [];
        if (!empty($bundlingItems) && is_array($bundlingItems)) {
            foreach ($bundlingItems as $b) {
                // Check if package is disabled by admin
                if (isset($bundlingStatus[$b['key']]) && $bundlingStatus[$b['key']] === false) {
                    continue;
                }
                $bundlingFormatted[] = [
                    'key' => $b['key'],
                    'id' => $b['key'],
                    'name' => $b['name'],
                    'category' => 'Bundling',
                    'img' => $b['img'],
                    'desc' => $b['desc'] ?? '',
                    'price' => (float) $b['price'],
                    'priceLabel' => $b['priceLabel'] ?? '',
                    'normalPrice' => (float) ($b['normalPrice'] ?? 0),
                    'isSoldOut' => !empty($b['isSoldOut']),
                    'tag' => $b['tag'] ?? 'BUNDLING',
                    'size' => $b['size'] ?? '',
                    'isBundling' => true,
                    'slots' => $b['slots'] ?? [],
                    'options' => $b['options'] ?? [
                        'ices' => ['Normal Ice', 'Less Ice'],
                        'sugars' => ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'],
                    ],
                ];
            }
        }

        // Merge Bundling items at the beginning
        $items = array_merge($bundlingFormatted, $items);

        // Ensure Bundling category is in categories list
        $hasBundlingCat = false;
        foreach ($categories as $cat) {
            if (($cat['id'] ?? '') === 'item-bundling' || ($cat['name'] ?? '') === 'Bundling') {
                $hasBundlingCat = true;
                break;
            }
        }
        if (!$hasBundlingCat) {
            array_unshift($categories, [
                'id' => 'item-bundling',
                'name' => '🎀 Paket Bundling',
                'kind' => 'item',
            ]);
        }

        // 3. Recalculate Live Stock Statistics
        $totalCount = count($items);
        $availableCount = 0;
        $soldOutCount = 0;

        foreach ($items as $it) {
            if (!empty($it['isSoldOut'])) {
                $soldOutCount++;
            } else {
                $availableCount++;
            }
        }

        // Double check if outlet status is closed
        $outletCheck = $this->evaluateOutletHours(['code' => $outletCode]);
        if (!empty($menuData['isClosed']) || ($outletCheck && isset($outletCheck['canOrder']) && !$outletCheck['canOrder'])) {
            $menuData['isClosed'] = true;
        }

        $menuData['categories'] = $categories;
        $menuData['items'] = $items;
        $menuData['stats'] = [
            'total' => $totalCount,
            'available' => $availableCount,
            'soldOut' => $soldOutCount,
        ];

        return $menuData;
    }

    /**
     * Fetch Live Item Details (Addons, Groups, Modifiers, Prices) from Upstream API
     * URL: https://tixandcoffee.developerngantuk.my.id/api/kopken/item?outlet={outlet}&code={code}&key={key}
     */
    public function getItemDetails(string $outletCode, string $itemCode = '', string $itemKey = ''): array
    {
        $outletCode = trim($outletCode);
        if ($outletCode === '') {
            $outletCode = config('store.default_outlet', 'LA.CODEFIN');
        }

        $upstreamCacheKey = 'raw_item_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $outletCode . '_' . $itemCode . '_' . $itemKey);
        $rawResponse = $this->getCache($upstreamCacheKey, 120);

        if ($rawResponse === null) {
            $sources = [
                'https://tixandcoffee.developerngantuk.my.id',
                'https://ridstore.developerngantuk.my.id',
                'https://tyaastore.my.id',
                $this->origin ?: 'https://twinsjasdor.my.id'
            ];
            $params = [
                'outlet' => $outletCode,
            ];
            if (!empty($itemCode)) {
                $params['code'] = $itemCode;
            }
            if (!empty($itemKey)) {
                $params['key'] = $itemKey;
            }

            foreach ($sources as $src) {
                $src = rtrim($src, '/');
                $url = $src . '/api/kopken/item';
                $resp = $this->httpRequest($url, $params);
                if ($resp && is_array($resp) && (!empty($resp['groups']) || !empty($resp['prices']) || isset($resp['isSoldOut']))) {
                    $rawResponse = $resp;
                    $this->setCache($upstreamCacheKey, $rawResponse);
                    break;
                }
            }
        }

        if ($rawResponse && is_array($rawResponse)) {
            // Deep copy so upstream cache remains untouched and pure
            $response = json_decode(json_encode($rawResponse), true);

            // Check if item is food (Food items have NO drink groups/options: sizes, temps, ices, sugars)
            $isDefiniteFood = (bool) preg_match('/puff|croissant|panini|toast|bread|roti|donut|bagel|cookie|bungeoppang|nugget|nasi|chicken|spaghetti|pasta|danish|snack|pie|bolognese|carbonara|aglio/i', $itemKey . ' ' . $itemCode);
            $isDrinkKey = !$isDefiniteFood && (bool) preg_match('/latte|frappe|coffee|kopi|espresso|americano|cappuccino|macchiato|tea|matcha|shake|freeze|lemonade|chocolate|choco|boba|drink|susu|avocado|hibiscus|babyccino|cold\s*brew|bluebericano|strawbericano/i', $itemKey . ' ' . $itemCode);
            $isFoodItem = $isDefiniteFood || (!$isDrinkKey && (bool) preg_match('/cheesycake|\bcake\b|chiller|meal/i', $itemKey . ' ' . $itemCode));
            if (!$isFoodItem && !$isDrinkKey) {
                $menu = $this->getMenu($outletCode);
                foreach ($menu['items'] ?? [] as $mi) {
                    if (($itemKey !== '' && ($mi['key'] ?? '') === $itemKey) || ($itemCode !== '' && ($mi['code'] ?? '') === $itemCode)) {
                        if (!empty($mi['isFood']) || ($mi['category'] ?? '') === 'Food') {
                            $isFoodItem = true;
                        }
                        break;
                    }
                }
            }

            if ($isFoodItem) {
                $response['groups'] = [];
                $response['addons'] = [];
                $response['isFood'] = true;
                return $response;
            }

            // 1. Remove 'Jumbo' from groups and detect 'Large' IDs
            $jumboIds = [];
            $largeIds = [];
            if (!empty($response['groups']) && is_array($response['groups'])) {
                foreach ($response['groups'] as &$group) {
                    $gKey = strtolower($group['key'] ?? '');
                    $gName = strtolower($group['name'] ?? '');
                    if ($gKey === 'size' || $gName === 'size' || $gName === 'ukuran') {
                        $group['values'] = array_values(array_filter($group['values'], function($val) use (&$jumboIds, &$largeIds) {
                            $t = strtolower($val['text'] ?? '');
                            $vId = strtolower((string)($val['id'] ?? ''));
                            if ($t === 'jumbo' || $vId === 'jumbo' || str_contains($t, 'jumbo')) {
                                $jumboIds[] = (string)$val['id'];
                                return false;
                            }
                            if ($t === 'large' || $vId === 'large' || str_contains($t, 'large')) {
                                $largeIds[] = (string)$val['id'];
                            }
                            return true;
                        }));
                    }
                }
                unset($group);
            }

            // Remove jumbo combinations from prices and normals
            if (!empty($jumboIds) && !empty($response['prices']) && is_array($response['prices'])) {
                foreach ($jumboIds as $jId) {
                    foreach (array_keys($response['prices']) as $cKey) {
                        $parts = explode('|', (string)$cKey);
                        if (in_array((string)$jId, $parts, true)) {
                            unset($response['prices'][$cKey]);
                            if (isset($response['normals'][$cKey])) {
                                unset($response['normals'][$cKey]);
                            }
                        }
                    }
                }
            }

            // Check if regular only item (e.g. Butterscotch Sea Salt Crumble)
            $isRegularOnly = false;
            $regularOnlyList = config('store.regular_only_items', [
                'Butterscotch Sea Salt Crumble',
                'butterscotchseasaltcrumble',
            ]);
            $normCheckKey = strtolower(str_replace([' ', '_', '-'], '', (string)$itemKey));
            foreach ($regularOnlyList as $regItem) {
                $normReg = strtolower(str_replace([' ', '_', '-'], '', (string)$regItem));
                if ($normCheckKey === $normReg || str_contains($normCheckKey, $normReg)) {
                    $isRegularOnly = true;
                    break;
                }
            }

            // Remove Large if item is regular only
            if ($isRegularOnly) {
                if (!empty($response['groups']) && is_array($response['groups'])) {
                    foreach ($response['groups'] as &$group) {
                        $gKey = strtolower($group['key'] ?? '');
                        $gName = strtolower($group['name'] ?? '');
                        if ($gKey === 'size' || $gName === 'size' || $gName === 'ukuran') {
                            $group['values'] = array_values(array_filter($group['values'], function($val) use (&$largeIds) {
                                $t = strtolower($val['text'] ?? '');
                                $vId = strtolower((string)($val['id'] ?? ''));
                                if ($t === 'large' || $vId === 'large' || str_contains($t, 'large')) {
                                    $largeIds[] = (string)$val['id'];
                                    return false;
                                }
                                return true;
                            }));
                            if (!empty($group['values'])) {
                                $group['default'] = $group['values'][0]['id'];
                            }
                        }
                    }
                    unset($group);
                }

                if (!empty($largeIds) && !empty($response['prices']) && is_array($response['prices'])) {
                    foreach ($largeIds as $lId) {
                        foreach (array_keys($response['prices']) as $cKey) {
                            $parts = explode('|', (string)$cKey);
                            if (in_array((string)$lId, $parts, true)) {
                                unset($response['prices'][$cKey]);
                                if (isset($response['normals'][$cKey])) {
                                    unset($response['normals'][$cKey]);
                                }
                            }
                        }
                    }
                }
            }

            // 2. Adjust prices & calculate normals with accurate fee markup dynamically!
            if (!empty($response['prices']) && is_array($response['prices'])) {
                $menu = $this->getMenu($outletCode);
                $matchedItem = null;
                foreach ($menu['items'] ?? [] as $mi) {
                    if (($itemKey !== '' && ($mi['key'] ?? '') === $itemKey) || ($itemCode !== '' && ($mi['code'] ?? '') === $itemCode)) {
                        $matchedItem = $mi;
                        break;
                    }
                }

                // Base selling & normal prices from $matchedItem
                $calculatedBase = (float)($matchedItem['price'] ?? 15500);
                $origBaseNormal = (float)($matchedItem['normalPrice'] ?? $matchedItem['outletPrice'] ?? 19000);
                if ($origBaseNormal <= 0) $origBaseNormal = 19000;

                $origLargeNormal = (float)($matchedItem['largeNormal'] ?? $matchedItem['largeBase'] ?? 0);
                if ($origLargeNormal <= 0) {
                    $origLargeNormal = $origBaseNormal + 7000;
                }
                $largePriceBase = (float)($matchedItem['largePrice'] ?? round(($origLargeNormal * 0.5) + 5000));

                // Segregate combos into Regular vs Large to find their respective base raw prices
                $rawRegPrices = [];
                $rawLargePrices = [];
                foreach ($response['prices'] as $combo => $rawP) {
                    $parts = explode('|', (string)$combo);
                    $isLarge = false;
                    foreach ($parts as $partId) {
                        if (in_array((string)$partId, $largeIds, true)) {
                            $isLarge = true;
                            break;
                        }
                    }
                    if ($isLarge) {
                        $rawLargePrices[] = (float)$rawP;
                    } else {
                        $rawRegPrices[] = (float)$rawP;
                    }
                }

                $rawRegBase = !empty($rawRegPrices) ? min($rawRegPrices) : (float)($response['price'] ?? 14500);
                $rawLargeBase = !empty($rawLargePrices) ? min($rawLargePrices) : ($rawRegBase + 6000);

                $normals = [];
                foreach ($response['prices'] as $combo => &$p) {
                    $rawP = (float)$p;

                    // Check if this combo is Large
                    $isLargeCombo = false;
                    $parts = explode('|', (string)$combo);
                    foreach ($parts as $partId) {
                        if (in_array((string)$partId, $largeIds, true)) {
                            $isLargeCombo = true;
                            break;
                        }
                    }

                    if ($isLargeCombo) {
                        // Extra modifier difference (e.g. Oatside milk +3.000)
                        $extraDiff = max(0, $rawP - $rawLargeBase);
                        $p = round($largePriceBase + $extraDiff);
                        // Normal price strictly starts from $origLargeNormal (e.g. 25.000) + any modifier
                        $normals[$combo] = round($origLargeNormal + ($extraDiff * 2));
                    } else {
                        // Extra modifier difference (e.g. Oatside milk +3.000)
                        $extraDiff = max(0, $rawP - $rawRegBase);
                        $p = round($calculatedBase + $extraDiff);
                        // Normal price strictly starts from $origBaseNormal (e.g. 19.000) + any modifier
                        $normals[$combo] = round($origBaseNormal + ($extraDiff * 2));
                    }
                }
                unset($p);

                $response['normals'] = $normals;
            }

            return $response;
        }

        // Dynamic fallback for items when upstream modifier endpoint is unavailable
        $menu = $this->getMenu($outletCode);
        $matchedItem = null;
        foreach ($menu['items'] ?? [] as $mi) {
            if (($itemKey !== '' && ($mi['key'] ?? '') === $itemKey) || ($itemCode !== '' && ($mi['code'] ?? '') === $itemCode)) {
                $matchedItem = $mi;
                break;
            }
        }

        $itemName = $matchedItem['name'] ?? $itemKey;
        $isDrink = $this->isDrinkBeverage($itemName);
        $isFoodItem = !$isDrink && $this->isFoodProduct($itemName, $matchedItem['category'] ?? '');

        if ($isFoodItem) {
            return [
                'isSoldOut' => !empty($matchedItem['isSoldOut']),
                'reason' => '',
                'addons' => [],
                'groups' => [],
                'extras' => [],
                'isFood' => true
            ];
        }

        // Standard drink customization groups
        $isFrappe = stripos($itemName, 'frappe') !== false;
        $hasNoLarge = !empty($matchedItem['noLarge']) || stripos($itemName, 'butterscotch sea salt crumble') !== false;

        $sizeValues = [['id' => 'regular', 'text' => 'Regular']];
        if (!$hasNoLarge) {
            $sizeValues[] = ['id' => 'large', 'text' => 'Large'];
        }

        $groups = [
            [
                'id' => 'size',
                'key' => 'size',
                'name' => 'Ukuran',
                'values' => $sizeValues,
                'default' => 'regular',
                'impact' => true
            ],
            [
                'id' => 'temp',
                'key' => 'temp',
                'name' => 'Suhu',
                'values' => $isFrappe ? [['id' => 'ice', 'text' => 'Ice']] : [['id' => 'ice', 'text' => 'Ice'], ['id' => 'hot', 'text' => 'Hot']],
                'default' => 'ice',
                'impact' => false
            ],
            [
                'id' => 'ice',
                'key' => 'ice',
                'name' => 'Tingkat Es',
                'values' => [['id' => 'normal', 'text' => 'Normal Ice'], ['id' => 'less', 'text' => 'Less Ice'], ['id' => 'none', 'text' => 'No Ice']],
                'default' => 'normal',
                'impact' => false
            ],
            [
                'id' => 'sugar',
                'key' => 'sugar',
                'name' => 'Tingkat Gula',
                'values' => [['id' => 'normal', 'text' => 'Normal Sugar'], ['id' => 'less', 'text' => 'Less Sugar'], ['id' => 'none', 'text' => 'No Sugar']],
                'default' => 'normal',
                'impact' => false
            ]
        ];

        $basePrice = (float)($matchedItem['price'] ?? 16000);
        $baseNormal = (float)($matchedItem['normalPrice'] ?? 22000);
        $largePrice = (float)($matchedItem['largePrice'] ?? ($basePrice + 3500));
        $largeNormal = (float)($matchedItem['largeNormal'] ?? ($baseNormal + 7000));

        $prices = ['regular' => $basePrice];
        $normals = ['regular' => $baseNormal];
        if (!$hasNoLarge) {
            $prices['large'] = $largePrice;
            $normals['large'] = $largeNormal;
        }

        return [
            'isSoldOut' => !empty($matchedItem['isSoldOut']),
            'reason' => '',
            'addons' => config('addons', []),
            'groups' => $groups,
            'prices' => $prices,
            'normals' => $normals,
            'isFood' => false
        ];
    }

    /**
     * Fetch Live Wi-Fi SSID & Daily Password for an outlet
     * Prioritizes:
     * 1. Exact 31-day daily rotation password table strictly per client reference
     * 2. Local Master Snapshot (data/wifi_master.json)
     * 3. Multi-source Upstream Failover (tixandcoffee, ridstore, tyaastore, twinsjasdor)
     * 4. Safe fallback: 'SecangkirRindu' . date('j')
     */
    public function getWifi(string $outletCode = ''): array
    {
        $outletCode = trim($outletCode);
        if ($outletCode === '') {
            $outletCode = config('store.default_outlet', 'LA.CODEFIN');
        }

        // Exact 31-day password table strictly per client reference (Gambar 6)
        $wifiDailyPasswords = [
            1 => 'NgopiSantuy#01',
            2 => 'KopiPenyemangat@02',
            3 => 'SemangatBaru03',
            4 => 'HariCeria=04',
            5 => 'KopiTemanSetia!05',
            6 => 'NgopiSeru#06',
            7 => 'KopiTemanPagi@07',
            8 => 'KopiPenutupSore08',
            9 => 'SegarnyaKopi=09',
            10 => 'NgopiBarengYuk!10',
            11 => 'KenanganIndah#11',
            12 => 'KopiJodohSejati@12',
            13 => 'HangatnyaKopi13',
            14 => 'PagiSemangat=14',
            15 => 'NgopiDuluGaes!15',
            16 => 'KopiPenyelamat#16',
            17 => 'RasaKangen@17',
            18 => 'SecangkirRindu18',
            19 => 'KopiTanpaBatas=19',
            20 => 'NgopiSambilKerja!20',
            21 => 'KenanganManis#21',
            22 => 'KopiTemanCurhat@22',
            23 => 'BahagiaItuSederhana23',
            24 => 'NgopiPenuhCinta=24',
            25 => 'KopiRasaCinta!25',
            26 => 'SemangatPejuang#26',
            27 => 'KopiBikinSemangat@27',
            28 => 'PagiPenuhSyukur28',
            29 => 'KenanganTakTelupa=29',
            30 => 'KopiSelaluSetia!30',
            31 => 'NgopiSampaiPuas#31',
        ];

        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTime('now', $tz);
        $todayDate = $now->format('l, d F Y');
        $dayNum = (int) $now->format('j');
        $pwd = $wifiDailyPasswords[$dayNum] ?? ('NgopiSantuy#' . sprintf('%02d', $dayNum));
        $ssid = 'Teman Kenangan';
        $username = 'kopi kenangan';

        $result = [
            'success' => true,
            'outlet' => $outletCode,
            'wifi' => [
                'ssid' => $ssid,
                'username' => $username,
                'password' => $pwd,
            ],
            'date' => $todayDate,
            'day' => $dayNum,
            'source' => 'swamptechnology'
        ];

        return $result;
    }

    /**
     * Process order submission and generate WhatsApp checkout link with single item fee (+2.000) and plastic bag (+1.000).
     */
    public function saveOrder(array $data): array
    {
        $prefix = (string) config('store.order_prefix', 'KYUTI');
        $randomCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $orderCode = !empty($data['orderCode']) ? trim((string)$data['orderCode']) : ($prefix . '-' . $randomCode);

        $items = $data['items'] ?? [];
        $totalQty = 0;
        foreach ($items as $it) {
            $totalQty += (int) ($it['qty'] ?? 1);
        }

        // Single item fee: +2.000 if order is 1 pcs (Exempt for Bundling packages per client requirement)
        $hasBundling = false;
        foreach ($items as $it) {
            if (!empty($it['isBundling']) || ($it['category'] ?? '') === 'Bundling') {
                $hasBundling = true;
                break;
            }
        }
        $singleItemFeeConfig = (int) config('fees.single_item_fee', 0);
        $hasSingleItemFee = (($totalQty === 1) || !empty($data['hasSingleItemFee'])) && !$hasBundling && ($singleItemFeeConfig > 0);
        $singleItemFee = $hasSingleItemFee ? $singleItemFeeConfig : 0;

        // Plastic bag fee: support quantity and boolean
        $plasticBagFeeConfig = (int) config('fees.plastic_bag_fee', 1000);
        $plasticBagQty = 0;
        if (isset($data['plasticBagQty'])) {
            $plasticBagQty = max(0, (int) $data['plasticBagQty']);
        } elseif (!empty($data['plasticBag'])) {
            $plasticBagQty = 1;
        }
        $hasPlasticBag = $plasticBagQty > 0;
        $plasticBagFee = $plasticBagQty * $plasticBagFeeConfig;

        $subtotal = 0;
        if (isset($data['subtotal']) && (float)$data['subtotal'] > 0) {
            $subtotal = (float)$data['subtotal'];
        } else {
            foreach ($items as $it) {
                $subtotal += (float)($it['subtotal'] ?? (($it['price'] ?? 0) * ($it['qty'] ?? 1)));
            }
        }
        $total = $subtotal + $singleItemFee + $plasticBagFee;

        $pickupTime = $data['pickupTime'] ?? 'Sekarang';
        $pickupTimezone = $data['pickupTimezone'] ?? 'WIB';
        if ($pickupTime !== 'Sekarang' && !str_contains($pickupTime, $pickupTimezone)) {
            $pickupTime .= ' ' . $pickupTimezone;
        }

        $domain = (string) config('store.domain', 'kedaikyuti.my.id');
        $trackUrl = "https://{$domain}/?track=" . rawurlencode($orderCode);

        $order = [
            'orderCode' => $orderCode,
            'code' => $orderCode,
            'customerName' => trim($data['customerName'] ?? 'Kyuties'),
            'customerPhone' => trim($data['customerPhone'] ?? ''),
            'outletCode' => $data['outletCode'] ?? 'LA.CODEFIN',
            'outletName' => $data['outletName'] ?? 'Kopi Kenangan',
            'pickupTime' => $pickupTime,
            'notes' => trim($data['notes'] ?? ''),
            'hasPlasticBag' => $hasPlasticBag,
            'plasticBagQty' => $plasticBagQty,
            'plasticBagFee' => $plasticBagFee,
            'hasSingleItemFee' => $hasSingleItemFee,
            'singleItemFee' => $singleItemFee,
            'status' => 'diproses',
            'statusText' => 'Pesanan Sedang Disiapkan',
            'statusDesc' => 'Pesananmu sedang disiapkan dengan penuh cinta oleh Kedai Kyuti ♡',
            'items' => $items,
            'subtotal' => $subtotal,
            'total' => $total,
            'trackUrl' => $trackUrl,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ];

        // Persist order to local store & cache
        $this->storeOrder($order);

        // Format WhatsApp message
        $rawPhone = (string) config('store.admin_phone', '628972276154');
        $adminPhone = preg_replace('/\D/', '', $rawPhone);
        if (str_starts_with($adminPhone, '0')) {
            $adminPhone = '62' . substr($adminPhone, 1);
        }
        if (empty($adminPhone)) {
            $adminPhone = '628972276154';
        }
        
        $waMessage = "୨୧ *FORMAT ORDER KEDAI KYUTI* ୨୧\n";
        $waMessage .= "--------------------------------------\n";
        $waMessage .= "🧾 Kode Order: *" . $orderCode . "*\n";
        $waMessage .= "Outlet: *" . $order['outletName'] . "*\n";
        $waMessage .= "Nama: *" . $order['customerName'] . "*\n";
        if (!empty($order['customerPhone'])) {
            $waMessage .= "No. HP: " . $order['customerPhone'] . "\n";
        }
        $waMessage .= "Waktu Pickup: *" . $order['pickupTime'] . "*\n";
        $waMessage .= "--------------------------------------\n";
        $waMessage .= "*Detail Menu:*\n";

        foreach ($order['items'] as $idx => $item) {
            $options = [];
            $isBundling = !empty($item['isBundling']) || ($item['category'] ?? '') === 'Bundling';
            $isDefiniteFood = (bool) preg_match('/puff|croissant|panini|toast|bread|roti|donut|bagel|cookie|bungeoppang|nugget|nasi|chicken|spaghetti|pasta|danish|snack|pie|bolognese|carbonara|aglio/i', $item['name'] ?? '');
            $isDrink = !$isDefiniteFood && (bool) preg_match('/latte|frappe|coffee|kopi|espresso|americano|cappuccino|macchiato|tea|matcha|shake|freeze|lemonade|chocolate|choco|boba|drink|susu|avocado|hibiscus|babyccino|cold\s*brew|bluebericano|strawbericano/i', $item['name'] ?? '');
            $isFood = $isDefiniteFood || (!$isDrink && (!empty($item['isFood']) || ($item['category'] ?? '') === 'Food' || preg_match('/cheesycake|\bcake\b|chiller|meal/i', $item['name'] ?? '')));
            if (!$isBundling && !$isFood) {
                if (!empty($item['size'])) $options[] = $item['size'];
                if (!empty($item['temp'])) $options[] = $item['temp'];
                if (!empty($item['ice'])) $options[] = $item['ice'];
                if (!empty($item['sugar'])) $options[] = $item['sugar'];
            }
            $optStr = !empty($options) ? ' (' . implode(', ', $options) . ')' : '';

            $priceFormatted = number_format($item['price'] ?? 0, 0, ',', '.');
            $subtotalFormatted = number_format($item['subtotal'] ?? 0, 0, ',', '.');

            $waMessage .= ($idx + 1) . ". *" . ($item['name'] ?? 'Item') . "* x" . ($item['qty'] ?? 1) . $optStr . "\n";
            if (!empty($item['bundleChoices'])) {
                $waMessage .= "    Isi Paket: _" . $item['bundleChoices'] . "_\n";
            }
            if (!empty($item['addons']) && is_array($item['addons'])) {
                $addonStrings = [];
                foreach ($item['addons'] as $ad) {
                    $addonStrings[] = ($ad['name'] ?? 'Addon') . ' (+Rp ' . number_format($ad['price'] ?? 0, 0, ',', '.') . ')';
                }
                if (!empty($addonStrings)) {
                    $waMessage .= "    + Topping: _" . implode(', ', $addonStrings) . "_\n";
                }
            }
            $waMessage .= "    Harga: Rp " . $priceFormatted . " | Subtotal: Rp " . $subtotalFormatted . "\n";
            if (!empty($item['note'])) {
                $waMessage .= "    Catatan: _" . $item['note'] . "_\n";
            }
        }

        $waMessage .= "--------------------------------------\n";
        $waMessage .= "Subtotal Menu: Rp " . number_format($order['subtotal'], 0, ',', '.') . "\n";

        if ($hasSingleItemFee && $singleItemFee > 0) {
            $waMessage .= "Fee Pesanan Satuan: +Rp " . number_format($singleItemFee, 0, ',', '.') . "\n";
        } elseif ($singleItemFeeConfig > 0) {
            $waMessage .= "Fee Pesanan Satuan: 0\n";
        }

        if ($hasPlasticBag) {
            $bagText = $plasticBagQty > 1 ? "Kantung Belanja Plastik ({$plasticBagQty} pcs)" : "Kantung Belanja Plastik";
            $waMessage .= "{$bagText}: +Rp " . number_format($plasticBagFee, 0, ',', '.') . "\n";
        }

        if (!empty($order['notes'])) {
            $waMessage .= "Catatan Tambahan: _" . $order['notes'] . "_\n";
        }
        $waMessage .= "--------------------------------------\n";
        $waMessage .= "*TOTAL BAYAR: Rp " . number_format($order['total'], 0, ',', '.') . "*\n\n";
        $waMessage .= "Setelah format dikirim, segera payment ya kak.\n";
        $waMessage .= "𝒯𝒽𝒶𝓃𝓀 𝓎𝑜𝓊 𝒻𝑜𝓇 𝒸𝒽𝑜𝑜𝓈𝒾𝓃𝑔 𝓀𝑒𝒹𝒶𝒾 𝓀𝓎𝓊𝓉𝒾! ୨୧ ♡\n";
        $waMessage .= "Mohon diproses ya min, terima kasih! 🌷✨";

        $waBaseLink = (string) config('store.social_links.wa', '');
        if (!empty($waBaseLink) && str_contains($waBaseLink, 'wa.me')) {
            $delim = str_contains($waBaseLink, '?') ? '&' : '?';
            $waUrl = $waBaseLink . $delim . 'text=' . rawurlencode($waMessage);
        } else {
            $waUrl = 'https://wa.me/' . $adminPhone . '?text=' . rawurlencode($waMessage);
        }

        return [
            'success' => true,
            'orderCode' => $orderCode,
            'order' => $order,
            'waMessage' => $waMessage,
            'waUrl' => $waUrl,
            'trackUrl' => $trackUrl,
        ];
    }

    /**
     * Load orders list from data storage & fallback cache.
     */
    public function loadOrders(): array
    {
        $dataPath = __DIR__ . '/../data/orders.json';
        $tempPath = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'orders.json';

        // 1. Try local data file
        if (file_exists($dataPath)) {
            $json = @file_get_contents($dataPath);
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }

        // 2. Try temp cache file
        if (file_exists($tempPath)) {
            $json = @file_get_contents($tempPath);
            $data = json_decode($json, true);
            if (is_array($data)) return $data;
        }

        // 3. Try Cloud Redis if available
        if (class_exists('SimpleRedis')) {
            try {
                $redis = new SimpleRedis();
                if ($redis->isConnected()) {
                    $json = $redis->get('kyuti_orders_list');
                    if (!empty($json)) {
                        $data = json_decode($json, true);
                        if (is_array($data)) return $data;
                    }
                }
            } catch (Throwable $e) {}
        }

        return [];
    }

    /**
     * Store order to local file, temp cache, and cloud Redis.
     */
    public function storeOrder(array $order): void
    {
        $orders = $this->loadOrders();
        $code = strtoupper(trim($order['orderCode'] ?? ($order['code'] ?? '')));

        // Filter existing by code
        $orders = array_values(array_filter($orders, function ($o) use ($code) {
            $oCode = strtoupper(trim($o['orderCode'] ?? ($o['code'] ?? '')));
            return $oCode !== $code;
        }));

        array_unshift($orders, $order);
        if (count($orders) > 100) {
            $orders = array_slice($orders, 0, 100);
        }

        $json = json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dataPath = __DIR__ . '/../data/orders.json';
        $tempPath = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'orders.json';

        @file_put_contents($dataPath, $json);
        @file_put_contents($tempPath, $json);

        if (class_exists('SimpleRedis')) {
            try {
                $redis = new SimpleRedis();
                if ($redis->isConnected()) {
                    $redis->set('kyuti_orders_list', $json);
                    $redis->set('kyuti_order_' . $code, json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } catch (Throwable $e) {}
        }
    }

    /**
     * Find order tracking info by code.
     */
    public function findOrder(string $code): array
    {
        $code = strtoupper(trim($code));

        if (empty($code)) {
            return [
                'success' => false,
                'found' => false,
                'message' => 'Kode pesanan wajib diisi.',
            ];
        }

        // 1. Search in persistent orders store
        $orders = $this->loadOrders();
        foreach ($orders as $ord) {
            $oCode = strtoupper(trim($ord['orderCode'] ?? ($ord['code'] ?? '')));
            if ($oCode === $code) {
                return [
                    'success' => true,
                    'found' => true,
                    'order' => $ord,
                ];
            }
        }

        // 2. Search direct Redis key
        if (class_exists('SimpleRedis')) {
            try {
                $redis = new SimpleRedis();
                if ($redis->isConnected()) {
                    $json = $redis->get('kyuti_order_' . $code);
                    if (!empty($json)) {
                        $ord = json_decode($json, true);
                        if (is_array($ord)) {
                            return [
                                'success' => true,
                                'found' => true,
                                'order' => $ord,
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        // 3. Fallback: Return active processing state so customer never gets a blank error
        return [
            'success' => true,
            'found' => true,
            'order' => [
                'orderCode' => $code,
                'code' => $code,
                'customerName' => 'Sahabat Kyuti',
                'status' => 'diproses',
                'statusText' => 'Pesanan Sedang Disiapkan',
                'statusDesc' => 'Pesananmu sedang disiapkan dengan penuh cinta oleh Kedai Kyuti ♡',
                'outletName' => 'Kopi Kenangan',
                'pickupTime' => 'Sekarang',
                'pickup' => 'Sekarang',
                'total' => 0,
                'items' => [],
                'updatedAt' => date('c'),
            ],
        ];
    }

    /**
     * Update order status by code (Admin).
     */
    public function updateOrderStatus(string $orderCode, string $status, string $statusText = '', string $statusDesc = ''): ?array
    {
        $code = strtoupper(trim($orderCode));
        $orders = $this->loadOrders();
        $updatedOrder = null;

        $statusMap = [
            'menunggu_pembayaran' => [
                'text' => 'Menunggu Pembayaran',
                'desc' => 'Pesanan telah diterima. Silakan selesaikan pembayaran agar barista segera meracik pesananmu ♡',
            ],
            'diproses' => [
                'text' => 'Pesanan Sedang Disiapkan',
                'desc' => 'Pesananmu sedang disiapkan dengan penuh cinta oleh barista Kedai Kyuti ♡',
            ],
            'siap_pickup' => [
                'text' => 'Siap Diambil / Diantar',
                'desc' => 'Yey! Pesananmu sudah selesai disiapkan dan siap diambil di outlet / siap diantar ♡',
            ],
            'selesai' => [
                'text' => 'Pesanan Selesai',
                'desc' => 'Terima kasih banyak telah memesan di Kedai Kyuti! Semoga harimu manis dan menyenangkan ୨୧ ♡',
            ],
            'batal' => [
                'text' => 'Pesanan Dibatalkan',
                'desc' => 'Pesanan ini telah dibatalkan. Silakan hubungi admin di WhatsApp jika ada pertanyaan ♡',
            ],
        ];

        if (empty($statusText) && isset($statusMap[$status])) {
            $statusText = $statusMap[$status]['text'];
        }
        if (empty($statusDesc) && isset($statusMap[$status])) {
            $statusDesc = $statusMap[$status]['desc'];
        }

        $found = false;
        foreach ($orders as &$ord) {
            $oCode = strtoupper(trim($ord['orderCode'] ?? ($ord['code'] ?? '')));
            if ($oCode === $code) {
                $ord['status'] = $status;
                $ord['statusText'] = $statusText ?: 'Pesanan Sedang Disiapkan';
                $ord['statusDesc'] = $statusDesc ?: 'Status pesanan diperbarui oleh Admin Kedai Kyuti.';
                $ord['updatedAt'] = date('c');
                $updatedOrder = $ord;
                $found = true;
                break;
            }
        }
        unset($ord);

        if (!$found) {
            $updatedOrder = [
                'orderCode' => $code,
                'code' => $code,
                'customerName' => 'Sahabat Kyuti',
                'status' => $status,
                'statusText' => $statusText ?: 'Pesanan Sedang Disiapkan',
                'statusDesc' => $statusDesc ?: 'Status pesanan diperbarui oleh Admin Kedai Kyuti.',
                'outletName' => 'Kopi Kenangan',
                'pickupTime' => 'Sekarang',
                'pickup' => 'Sekarang',
                'total' => 0,
                'items' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c'),
            ];
            array_unshift($orders, $updatedOrder);
        }

        $json = json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dataPath = __DIR__ . '/../data/orders.json';
        $tempPath = (defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache') . DIRECTORY_SEPARATOR . 'orders.json';

        @file_put_contents($dataPath, $json);
        @file_put_contents($tempPath, $json);

        if (class_exists('SimpleRedis')) {
            try {
                $redis = new SimpleRedis();
                if ($redis->isConnected()) {
                    $redis->set('kyuti_orders_list', $json);
                    $redis->set('kyuti_order_' . $code, json_encode($updatedOrder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } catch (Throwable $e) {}
        }

        return $updatedOrder;
    }

    /**
     * Ultra-fast HTTP client for swamptechnology.id Serverless APIs.
     * Engineered with short 2s connect & 4s read timeouts to prevent Vercel timeouts.
     */
    protected function httpRequest(string $url, array $params = [], int $retry = 1): ?array
    {
        if (!empty($params)) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        $timeout = (int) config('api_timeout', 4);

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'X-Requested-With: XMLHttpRequest',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'header' => "Accept: application/json\r\nUser-Agent: Mozilla/5.0\r\n",
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response !== false) {
                $decoded = json_decode($response, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Calculate Haversine distance between two coordinates in kilometers.
     */
    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    protected function getCache(string $key, int $ttl): ?array
    {
        $filePath = $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.json';
        if (file_exists($filePath)) {
            $mtime = @filemtime($filePath);
            if ($mtime && (time() - $mtime < $ttl)) {
                $raw = @file_get_contents($filePath);
                $data = json_decode((string) $raw, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        return null;
    }

    protected function setCache(string $key, array $data): void
    {
        $filePath = $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.json';
        @file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    protected function deleteCache(string $key): void
    {
        $filePath = $this->cacheDir . DIRECTORY_SEPARATOR . $key . '.json';
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    protected function getFallbackOutlets(string $query = '', ?float $lat = null, ?float $lng = null): array
    {
        $masterFile = dirname(__DIR__) . '/data/outlets_master.json';
        if (file_exists($masterFile)) {
            $json = json_decode((string)file_get_contents($masterFile), true);
            if (is_array($json) && !empty($json)) {
                $outlets = $json;
                if ($query !== '') {
                    $qRaw = strtolower(trim($query));

                    // 1. Exact outlet code match
                    $exactCode = [];
                    foreach ($outlets as $o) {
                        if (strcasecmp($o['code'] ?? '', $qRaw) === 0) {
                            $exactCode[] = $o;
                        }
                    }
                    if (!empty($exactCode)) {
                        return array_values($exactCode);
                    }

                    // 2. Tokenize search words
                    $cleanQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $qRaw);
                    $words = array_values(array_filter(preg_split('/\s+/', trim((string)$cleanQuery))));

                    // Filter out common prefix stop words if user typed more words
                    $stopWords = ['kopi', 'kenangan', 'kopken', 'kedai', 'outlet', 'cabang', 'store', 'toko'];
                    $meaningfulWords = array_values(array_filter($words, fn($w) => !in_array($w, $stopWords, true)));
                    $searchWords = !empty($meaningfulWords) ? $meaningfulWords : $words;

                    $compactQuery = preg_replace('/[^\p{L}\p{N}]/u', '', $qRaw);

                    $scoredOutlets = [];

                    foreach ($outlets as $o) {
                        $name = strtolower($o['name'] ?? '');
                        $addr = strtolower($o['address'] ?? '');
                        $city = strtolower($o['city'] ?? '');
                        $code = strtolower($o['code'] ?? '');
                        $cat = strtolower($o['category'] ?? '');

                        $haystack = "$name $city $code $addr $cat";
                        $compactHaystack = preg_replace('/[^\p{L}\p{N}]/u', '', $haystack);

                        // Match compact query (e.g. "galuhmas" matches "galuh mas")
                        if ($compactQuery !== '' && strlen($compactQuery) >= 3 && str_contains($compactHaystack, $compactQuery)) {
                            $score = 15;
                            if (str_contains($name, $qRaw)) $score += 25;
                            if (str_contains($city, $qRaw)) $score += 15;
                            $scoredOutlets[] = ['outlet' => $o, 'score' => $score];
                            continue;
                        }

                        // Check if all search words match in haystack or compact haystack
                        $allMatched = true;
                        $score = 0;
                        foreach ($searchWords as $w) {
                            $wClean = preg_replace('/[^\p{L}\p{N}]/u', '', $w);
                            $inName = str_contains($name, $w);
                            $inCity = str_contains($city, $w);
                            $inCode = str_contains($code, $w);
                            $inAddr = str_contains($addr, $w);
                            $inCompact = ($wClean !== '' && str_contains($compactHaystack, $wClean));

                            if ($inName || $inCity || $inCode || $inAddr || $inCompact) {
                                if ($inName) $score += 12;
                                if ($inCity) $score += 8;
                                if ($inCode) $score += 10;
                                if ($inAddr) $score += 4;
                            } else {
                                $allMatched = false;
                                break;
                            }
                        }

                        if ($allMatched) {
                            $scoredOutlets[] = ['outlet' => $o, 'score' => $score];
                        }
                    }

                    // Sort by relevance score desc
                    usort($scoredOutlets, fn($a, $b) => $b['score'] <=> $a['score']);
                    $outlets = array_map(fn($item) => $item['outlet'], $scoredOutlets);
                }
                if ($lat !== null && $lng !== null) {
                    foreach ($outlets as &$o) {
                        if (!empty($o['lat']) && !empty($o['lng'])) {
                            $o['distanceKm'] = $this->calculateDistance($lat, $lng, (float)$o['lat'], (float)$o['lng']);
                        }
                    }
                    unset($o);
                    usort($outlets, fn ($a, $b) => ($a['distanceKm'] ?? 999999) <=> ($b['distanceKm'] ?? 999999));
                }
                return array_values($outlets);
            }
        }

        $outlets = [
            [
                'code' => 'JKT.SPBUDODOTB',
                'id' => 660,
                'name' => 'Shell (Tanjung Barat)',
                'address' => 'Shell Tanjung Barat (Container), 34.125.05, Jl. Tj. Barat Raya No. 120, Jagakarsa, Jakarta Selatan 12530',
                'city' => 'Jakarta Selatan',
                'lat' => -6.30376,
                'lng' => 106.840222,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '07:00 - 21:30',
                'img' => 'https://cdn.kopikenangan.com/public/7ca8d809-5389-2fb1-00a8-dcaf87da87d9',
                'category' => 'Gas Station',
                'phone' => '08170739217',
                'distanceKm' => null,
            ],
            [
                'code' => 'JKT.SHELLANTSR',
                'id' => 353,
                'name' => 'Shell Antasari',
                'address' => 'Shell Antasari, Jl. Pangeran Antasari No.68, RT.4/RW.11, Cipete Utara, Kec. Kby. Baru, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12150',
                'city' => 'Jakarta Selatan',
                'lat' => -6.262529,
                'lng' => 106.804107,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '07:00 - 21:00',
                'img' => 'https://cdn.kopikenangan.com/public/b7512ea2-9eb5-8e7c-eb8d-4767220ae868',
                'category' => 'Gas Station',
                'phone' => '08170739097',
                'distanceKm' => null,
            ],
            [
                'code' => 'JKT.SHELLCPNG',
                'id' => 350,
                'name' => 'Shell Cipinang',
                'address' => 'Shell Cipinang, Jl. Bekasi Tim. Raya No.154, RT.8/RW.13, Cipinang Besar Utara, Kecamatan Jatinegara, Kota Jakarta Timur, Daerah Khusus Ibukota Jakarta 13410',
                'city' => 'Jakarta Timur',
                'lat' => -6.223847,
                'lng' => 106.883713,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '09:00 - 20:30',
                'img' => 'https://cdn.kopikenangan.com/public/f2015fa4-96fe-c3c2-d1dc-02ebfc2fa209',
                'category' => 'Gas Station',
                'phone' => '08170739095',
                'distanceKm' => null,
            ],
            [
                'code' => 'LA.CODEFIN',
                'id' => 101,
                'name' => 'Codefin Kemang Jakarta',
                'address' => 'Jl. Kemang Raya No. 10A, Mampang Prapatan, Jakarta Selatan 12730',
                'city' => 'Jakarta Selatan',
                'lat' => -6.2608,
                'lng' => 106.8166,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '07:00 - 22:00',
                'img' => 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae',
                'category' => 'Shop House',
                'phone' => '081234567890',
                'distanceKm' => null,
            ],
            [
                'code' => 'JKT.THAMCIT',
                'id' => 411,
                'name' => 'Thamrin City',
                'address' => 'Thamrin City, Lantai Dasar, Jl. K.H. Mas Mansyur, Kb. Melati, Kecamatan Tanah Abang, Kota Jakarta Pusat, Daerah Khusus Ibukota Jakarta 10230',
                'city' => 'Jakarta Pusat',
                'lat' => -6.1949231,
                'lng' => 106.8170567,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '08:00 - 20:00',
                'img' => 'https://cdn.kopikenangan.com/public/8f19b461-7e86-b8dc-6f0f-d225299d387a',
                'category' => 'Mall',
                'phone' => '08170110244',
                'distanceKm' => null,
            ],
            [
                'code' => 'TP3.SBY',
                'id' => 242,
                'name' => 'Tunjungan Plaza 3',
                'address' => 'Tunjungan Plaza 3 Lt 5, Jl. Basuki Rahmat No.107, Surabaya 60261',
                'city' => 'Surabaya',
                'lat' => -7.2634859,
                'lng' => 112.7396328,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '10:00 - 21:30',
                'img' => 'https://cdn.kopikenangan.com/public/90bd14d0-a474-c6d6-a1f0-25d685b07bef',
                'category' => 'Mall',
                'phone' => '0817 9876 022',
                'distanceKm' => null,
            ],
        ];

        if ($query !== '') {
            $q = mb_strtolower($query);
            $outlets = array_filter($outlets, function ($o) use ($q) {
                return (
                    str_contains(mb_strtolower($o['name']), $q) ||
                    str_contains(mb_strtolower($o['address']), $q) ||
                    str_contains(mb_strtolower($o['city'] ?? ''), $q)
                );
            });
        }

        if ($lat !== null && $lng !== null) {
            foreach ($outlets as &$o) {
                if ($o['lat'] && $o['lng']) {
                    $o['distanceKm'] = $this->calculateDistance($lat, $lng, $o['lat'], $o['lng']);
                }
            }
            unset($o);
            usort($outlets, fn ($a, $b) => ($a['distanceKm'] ?? 999999) <=> ($b['distanceKm'] ?? 999999));
        }

        return array_values($outlets);
    }

    protected function getFallbackMenu(string $outletCode): array
    {
        $masterFile = dirname(__DIR__) . '/data/menu_master.json';
        if (file_exists($masterFile)) {
            $json = json_decode((string)file_get_contents($masterFile), true);
            if (is_array($json) && !empty($json['items'])) {
                unset($json['bundles'], $json['satuan'], $json['seliter'], $json['raw_dump']);
                $json['outletCode'] = $outletCode;
                $json['source'] = 'master_database';
                return $json;
            }
        }

        return [
            'success' => true,
            'live' => false,
            'isClosed' => false,
            'premium' => false,
            'brand' => 'kopken',
            'outletCode' => $outletCode,
            'categories' => [
                ['id' => 'item-bundling', 'name' => '🎀 Paket Bundling', 'kind' => 'item'],
                ['id' => 'item-coffee', 'name' => 'Coffee', 'kind' => 'item'],
                ['id' => 'item-non-coffee', 'name' => 'Non Coffee', 'kind' => 'item'],
                ['id' => 'item-frappe', 'name' => 'Frappe', 'kind' => 'item'],
                ['id' => 'item-food', 'name' => 'Food & Toast', 'kind' => 'item'],
            ],
            'items' => [
                [
                    'key' => 'kopken_mantan',
                    'id' => 'kopken_mantan',
                    'name' => 'Kopi Kenangan Mantan',
                    'category' => 'Coffee',
                    'img' => 'https://cdn.kopikenangan.com/public/da484154-47b1-a756-54a4-8b3490fb0eae',
                    'desc' => 'Espresso berpadu dengan susu segar dan gula aren murni khas Kopi Kenangan yang creamy dan legit.',
                    'price' => 15500,
                    'normalPrice' => 19000,
                    'largePrice' => 18500,
                    'largeNormal' => 25000,
                    'isSoldOut' => false,
                    'tag' => 'BEST SELLER',
                    'options' => [
                        'sizes' => ['Regular', 'Large'],
                        'temps' => ['Ice', 'Hot'],
                        'ices' => ['Normal Ice', 'Less Ice'],
                        'sugars' => ['Normal Sugar', 'Less Sugar (50%)', 'No Sugar'],
                    ],
                ],
                [
                    'key' => 'creamy_aren_latte',
                    'id' => 'creamy_aren_latte',
                    'name' => 'Creamy Aren Latte',
                    'category' => 'Coffee',
                    'img' => 'https://cdn.kopikenangan.com/public/8fed1c14-eb1f-dafe-8608-c438521ec78d',
                    'desc' => 'Sensasi kopi latte dengan tekstur extra creamy dan sentuhan manis gula aren alami.',
                    'price' => 16000,
                    'normalPrice' => 22000,
                    'largePrice' => 19000,
                    'largeNormal' => 28000,
                    'isSoldOut' => false,
                    'tag' => 'FAVORIT',
                    'options' => [
                        'sizes' => ['Regular', 'Large'],
                        'temps' => ['Ice', 'Hot'],
                        'ices' => ['Normal Ice', 'Less Ice'],
                        'sugars' => ['Normal Sugar', 'Less Sugar (50%)'],
                    ],
                ],
            ],
            'stats' => ['total' => 2, 'available' => 2, 'soldOut' => 0],
            'updatedAt' => date('c'),
        ];
    }

    /**
     * Retrieve real-time outlet stock profile from local master snapshot (0ms latency, zero DB overhead for Vercel).
     */
    public function getOutletStockProfile(string $outletCode): ?array
    {
        $outletCode = trim($outletCode);
        if ($outletCode === '') {
            $outletCode = config('store.default_outlet', 'LA.CODEFIN');
        }

        $cacheKey = 'outlet_stock_profile_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $outletCode);
        $cached = $this->getCache($cacheKey, 60);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        $stocksFile = dirname(__DIR__) . '/data/outlet_stocks_master.json';
        if (file_exists($stocksFile)) {
            static $allStocks = null;
            if ($allStocks === null) {
                $raw = @file_get_contents($stocksFile);
                $allStocks = json_decode((string)$raw, true) ?: [];
            }
            if (!empty($allStocks[$outletCode])) {
                $profile = [
                    'code' => $outletCode,
                    'soldOut' => (array)($allStocks[$outletCode]['soldOut'] ?? []),
                    'isClosed' => !empty($allStocks[$outletCode]['isClosed']),
                    'isPremium' => !empty($allStocks[$outletCode]['isPremium']),
                    'prices' => (array)($allStocks[$outletCode]['prices'] ?? []),
                    'source' => 'master_json'
                ];
                $this->setCache($cacheKey, $profile);
                return $profile;
            }
        }

        return null;
    }

    /**
     * Unified Multi-Brand API Proxy (swamptechnology.id)
     * Supported brands: kopken, jiwa, point, fore, tomoro
     */
    public function getBrandData(string $brand, string $action = 'menu', array $params = []): array
    {
        $brand = strtolower(trim($brand));
        $brandConfigs = config('brand_apis', []);

        if (!isset($brandConfigs[$brand])) {
            return [
                'success' => false,
                'message' => "Brand '$brand' tidak didukung. Pilihan: kopken, jiwa, point, fore, tomoro."
            ];
        }

        $bCfg = $brandConfigs[$brand];
        $baseUrl = rtrim($bCfg['base_url'], '/');

        $cacheKey = 'brand_' . $brand . '_' . $action . '_' . md5(json_encode($params));
        $cached = $this->getCache($cacheKey, 60);
        if ($cached !== null) {
            return $cached;
        }

        $endpoint = '';
        if ($action === 'status') {
            $endpoint = '/status';
        } elseif ($action === 'outlets' || $action === 'stores') {
            if ($brand === 'kopken') {
                $endpoint = !empty($params['lat']) ? '/store/nearest' : '/store/query_pageable_store';
            } elseif ($brand === 'jiwa') {
                $endpoint = '/ksj/riders/nearest';
            } elseif ($brand === 'tomoro') {
                $endpoint = !empty($params['lat']) ? '/store/nearest' : '/store/list';
            } else {
                $endpoint = '/config/version';
            }
        } elseif ($action === 'menu') {
            if ($brand === 'kopken') {
                $endpoint = '/product/query_product_menu';
            } elseif ($brand === 'jiwa') {
                $endpoint = '/ksj/menu';
            } elseif ($brand === 'point') {
                $endpoint = '/catalog/products';
            } elseif ($brand === 'fore') {
                $endpoint = '/product/menu';
            } elseif ($brand === 'tomoro') {
                $endpoint = '/product/menu';
            }
        }

        $url = $baseUrl . $endpoint;
        $response = $this->httpRequest($url, $params);

        if ($response !== null) {
            $result = [
                'success' => true,
                'brand' => $brand,
                'brand_name' => $bCfg['name'],
                'domain' => $bCfg['domain'],
                'action' => $action,
                'data' => $response,
            ];
            $this->setCache($cacheKey, $result);
            return $result;
        }

        return [
            'success' => false,
            'brand' => $brand,
            'message' => "Gagal menghubungi $url",
        ];
    }
}