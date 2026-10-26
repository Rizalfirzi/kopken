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
    protected int $outletsTtl;
    protected int $stockTtl;
    protected string $cacheDir;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('api_base_url', 'https://tixandcoffee.developerngantuk.my.id/api'), '/');
        $this->outletsTtl = (int) config('cache_ttl.outlets', 1800);
        $this->stockTtl = (int) config('stock.ttl_seconds', config('cache_ttl.menu', 60));
        $this->cacheDir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Fetch list of outlets with real-time hours evaluation, search, and distance sorting.
     */
    public function getOutlets(?string $query = null, ?float $lat = null, ?float $lng = null): array
    {
        $query = trim((string) $query);
        $cacheKey = 'outlets_' . md5($query . '|' . ($lat ? round($lat, 3) : '') . '|' . ($lng ? round($lng, 3) : ''));
        
        $cached = $this->getCache($cacheKey, $this->outletsTtl);
        if ($cached !== null) {
            foreach ($cached['outlets'] as &$o) {
                $o = $this->evaluateOutletHours($o);
                $o['isPremium'] = $this->checkIsPremiumOutlet($o);
            }
            unset($o);
            return $cached;
        }

        $params = [];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($lat !== null && $lng !== null) {
            $params['lat'] = $lat;
            $params['lng'] = $lng;
        }

        $url = $this->baseUrl . '/kopken/outlets';
        $response = $this->httpRequest($url, $params);

        if ($response && !empty($response['outlets'])) {
            $outlets = $response['outlets'];

            // 1. Calculate distance locally if user coordinates provided
            if ($lat !== null && $lng !== null) {
                foreach ($outlets as &$outlet) {
                    if (!empty($outlet['lat']) && !empty($outlet['lng'])) {
                        $outlet['distanceKm'] = $this->calculateDistance(
                            $lat,
                            $lng,
                            (float) $outlet['lat'],
                            (float) $outlet['lng']
                        );
                    }
                }
                unset($outlet);

                usort($outlets, function ($a, $b) {
                    $distA = $a['distanceKm'] ?? 999999;
                    $distB = $b['distanceKm'] ?? 999999;
                    return $distA <=> $distB;
                });
            }

            // 2. Evaluate Operating Hours and Premium Status for each outlet
            foreach ($outlets as &$outlet) {
                $outlet = $this->evaluateOutletHours($outlet);
                $outlet['isPremium'] = $this->checkIsPremiumOutlet($outlet);
            }
            unset($outlet);

            $result = [
                'success' => true,
                'source' => 'live',
                'count' => count($outlets),
                'outlets' => $outlets,
            ];

            $this->setCache($cacheKey, $result);
            return $result;
        }

        // Fallback to built-in outlets
        $fallback = $this->getFallbackOutlets($query, $lat, $lng);
        foreach ($fallback as &$o) {
            $o = $this->evaluateOutletHours($o);
            $o['isPremium'] = $this->checkIsPremiumOutlet($o);
        }
        unset($o);

        return [
            'success' => true,
            'source' => 'fallback',
            'count' => count($fallback),
            'outlets' => $fallback,
        ];
    }

    /**
     * Check if an outlet is classified as Premium (Mall, Bandara, Stasiun, etc.)
     */
    public function checkIsPremiumOutlet(array $outlet): bool
    {
        // 0. If explicit verified boolean exists (from live menu Mantan price >= 22k), use it directly!
        if (isset($outlet['isPremium']) && is_bool($outlet['isPremium'])) {
            return $outlet['isPremium'];
        }
        if (isset($outlet['premium']) && is_bool($outlet['premium'])) {
            return $outlet['premium'];
        }

        $category = strtolower($outlet['category'] ?? '');
        $name = strtolower($outlet['name'] ?? '');
        $address = strtolower($outlet['address'] ?? '');
        $code = strtoupper($outlet['code'] ?? '');
        $combined = $category . ' ' . $name . ' ' . $address;

        // 1. Check verified client premium outlet codes
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

        // 4. Upstream explicit indicator (if provided and not gas station)
        if (!empty($outlet['isPremium']) || !empty($outlet['premium'])) {
            return true;
        }

        // 5. Keywords for verified premium locations (Airports, major international transport hubs)
        // Note: Generic 'mall' or 'plaza' are excluded because most malls have regular 19k price!
        $premiumKeywords = [
            'terminal bandara', 'keberangkatan bandara', 'airport departure', 'bandara internasional',
            'terminal 2d', 'terminal 2e', 'terminal 3 domestic', 'terminal 3 international',
            'grand indonesia', 'senayan city', 'pacific place', 'tunjungan plaza 3', 'pim 3'
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

        $url = $this->baseUrl . '/kopken/menu';
        $response = $this->httpRequest($url, ['outlet' => $outletCode]);

        if ($response && isset($response['items'])) {
            // Absolute Client Rule:
            // "pokoknya kalau harga kopi kenangan mantan yang reguler naik jadi 22.000 berarti itu outlet premium ka"
            $mantanPrice = null;
            foreach ($response['items'] as $it) {
                $itemName = strtolower($it['name'] ?? '');
                if (str_contains($itemName, 'kenangan mantan') && !str_contains($itemName, 'oatside') && !str_contains($itemName, 'frappe') && !str_contains($itemName, 'duo') && !str_contains($itemName, 'bundl')) {
                    $mantanPrice = (float)($it['normalPrice'] ?? $it['outletPrice'] ?? 0);
                    break;
                }
            }

            if ($mantanPrice !== null && $mantanPrice > 0) {
                // If mantan regular is 22.000 or above, it is 100% Premium Outlet!
                // If mantan regular is less than 22.000 (e.g. 19.000), it is 100% Regular Outlet!
                $isPremium = ($mantanPrice >= 22000);
            } else {
                $isPremium = !empty($response['premium']) || $this->checkIsPremiumOutlet(['code' => $outletCode, 'name' => $outletCode]);
            }

            $result = [
                'success' => true,
                'live' => $response['live'] ?? true,
                'isClosed' => $response['isClosed'] ?? false,
                'premium' => $isPremium,
                'brand' => 'kopken',
                'outletCode' => $outletCode,
                'categories' => $response['categories'] ?? [
                    ['id' => 'item-bundling', 'name' => '🎀 Paket Bundling', 'kind' => 'item'],
                    ['id' => 'item-coffee', 'name' => 'Coffee', 'kind' => 'item'],
                    ['id' => 'item-non-coffee', 'name' => 'Non Coffee', 'kind' => 'item'],
                    ['id' => 'item-frappe', 'name' => 'Frappe', 'kind' => 'item'],
                    ['id' => 'item-food', 'name' => 'Food & Toast', 'kind' => 'item'],
                ],
                'items' => $response['items'],
                'updatedAt' => date('c'),
            ];

            $this->setCache($cacheKey, $result);
            return $this->applyMenuCustomizations($result, $outletCode);
        }

        // Fallback menu
        $fallback = $this->getFallbackMenu($outletCode);
        return $this->applyMenuCustomizations($fallback, $outletCode);
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
        // Absolute Client Rule: "pokoknya kalau harga kopi kenangan mantan yang reguler naik jadi 22.000 berarti itu outlet premium ka"
        $mantanPrice = null;
        foreach ($items as $it) {
            $itName = strtolower($it['name'] ?? '');
            if (str_contains($itName, 'kenangan mantan') && !str_contains($itName, 'oatside') && !str_contains($itName, 'frappe') && !str_contains($itName, 'duo') && !str_contains($itName, 'bundl')) {
                $mantanPrice = (float)($it['normalPrice'] ?? $it['outletPrice'] ?? 0);
                break;
            }
        }
        if ($mantanPrice !== null && $mantanPrice > 0) {
            $menuData['premium'] = ($mantanPrice >= 22000);
        }

        $discountPercent = (float) config('pricing.discount_percent', 50);

        // 1. Process API items
        foreach ($items as &$item) {
            $key = $item['key'] ?? $item['id'] ?? '';
            $category = $item['category'] ?? 'Coffee';

            // Override Stock
            if (isset($stockOverrides[$key])) {
                $item['isSoldOut'] = (bool) $stockOverrides[$key];
            }

            // Determine Original Price
            $origPrice = (float) ($item['normalPrice'] ?? $item['outletPrice'] ?? $item['price'] ?? 0);
            if ($origPrice <= 0) {
                $origPrice = (float) ($item['price'] ?? 0);
            }

            // Determine Markup Fee: Item override (by key or name) > Category markup > Default markup
            $fee = null;
            $name = $item['name'] ?? '';
            $normKey = strtolower(str_replace([' ', '_', '-'], '', $key));
            $normName = strtolower(str_replace([' ', '_', '-'], '', $name));

            // 1. Check Item Markup Override by key or name
            foreach ($itemMarkupOverrides as $oKey => $oFee) {
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

            // Fee difference from default markup (if user configures a custom fee per category or item)
            $feeDelta = $fee - $defaultMarkup;

            // Raw price from API
            $rawApiPrice = isset($item['price']) ? (float)$item['price'] : 0;

            if ($rawApiPrice > 0) {
                // Upstream API provides the exact discounted jasdor price!
                // If there is a fee override (e.g. +6.000 instead of default +5.000), apply the delta
                $calculatedPrice = round($rawApiPrice + $feeDelta);
            } else {
                // Fallback Formula: (Harga Asli - 50% + Fee)
                $discountedBase = $origPrice * (1 - ($discountPercent / 100));
                $calculatedPrice = round($discountedBase + $fee);
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
            $item['normalPrice'] = $origPrice;

            // Handle Large Price if available or if size options include Large
            $hasLargeSize = !empty($item['largePrice']) || (isset($item['options']['sizes']) && in_array('Large', (array)$item['options']['sizes'], true));

            if ($hasLargeSize) {
                $rawApiLarge = !empty($item['largePrice']) ? (float)$item['largePrice'] : 0;
                if ($rawApiLarge > 0) {
                    // Upstream API provides the exact large price! Apply delta if fee is overridden
                    $calcLarge = round($rawApiLarge + $feeDelta);
                } else {
                    $calcLarge = $calculatedPrice + 3500;
                }

                // Ensure large price is strictly higher than regular price
                if ($calcLarge <= $calculatedPrice) {
                    $calcLarge = $calculatedPrice + 3500;
                }

                $item['largePrice'] = $calcLarge;

                // Calculate largeNormal for crossed-out price display
                if (!empty($item['largeNormal'])) {
                    $item['largeNormal'] = (float) $item['largeNormal'];
                } else {
                    $largeDelta = $calcLarge - $calculatedPrice;
                    $item['largeNormal'] = round($origPrice + ($largeDelta * 2));
                }
            }

            // Guarantee image fallback
            if (empty($item['img'])) {
                $item['img'] = 'https://placehold.co/200x200?text=Kopi+Kenangan';
            }
        }
        unset($item);

        // 2. Prepend Bundling Packages (Hanya untuk Outlet Regular, tidak tersedia di Outlet Premium per SOP Klien)
        $categories = $menuData['categories'] ?? [];
        if (empty($menuData['premium'])) {
            $bundlingItems = config('bundling_items', []);
            $bundlingFormatted = [];
            if (!empty($bundlingItems) && is_array($bundlingItems)) {
                foreach ($bundlingItems as $b) {
                    $bundlingFormatted[] = [
                        'key' => $b['key'],
                        'id' => $b['key'],
                        'name' => $b['name'],
                        'category' => 'Bundling',
                        'img' => $b['img'],
                        'desc' => $b['desc'] ?? '',
                        'price' => (float) $b['price'],
                        'normalPrice' => (float) ($b['normalPrice'] ?? 0),
                        'isSoldOut' => !empty($b['isSoldOut']),
                        'tag' => $b['tag'] ?? 'BUNDLING',
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
        } else {
            // Remove bundling category if present in categories list for premium outlets
            $categories = array_values(array_filter($categories, function($cat) {
                return ($cat['id'] ?? '') !== 'item-bundling' && ($cat['name'] ?? '') !== 'Bundling';
            }));
        }

        // 3. Recalculate Live Stock Statistics
        $totalCount = count($items);
        $availableCount = 0;
        $soldOutCount = 0;

        foreach ($items as $it) {
            if (!empty($it['isSoldOut']) || !empty($it['notCarried'])) {
                $soldOutCount++;
            } else {
                $availableCount++;
            }
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

        $cacheKey = 'item_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $outletCode . '_' . $itemCode . '_' . $itemKey);
        $cached = $this->getCache($cacheKey, 120);
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/kopken/item';
        $params = [
            'outlet' => $outletCode,
        ];
        if (!empty($itemCode)) {
            $params['code'] = $itemCode;
        }
        if (!empty($itemKey)) {
            $params['key'] = $itemKey;
        }

        $response = $this->httpRequest($url, $params);

        if ($response && is_array($response)) {
            if (!empty($response['prices']) && is_array($response['prices'])) {
                $menu = $this->getMenu($outletCode);
                $matchedItem = null;
                foreach ($menu['items'] ?? [] as $mi) {
                    if (($itemKey !== '' && ($mi['key'] ?? '') === $itemKey) || ($itemCode !== '' && ($mi['code'] ?? '') === $itemCode)) {
                        $matchedItem = $mi;
                        break;
                    }
                }

                $origBasePrice = (float)($matchedItem['normalPrice'] ?? $matchedItem['outletPrice'] ?? $response['price'] ?? 19000);
                $calculatedBase = (float)($matchedItem['price'] ?? round($origBasePrice * 0.5 + 5000));
                $largePrice = !empty($matchedItem['largePrice']) ? (float)$matchedItem['largePrice'] : ($calculatedBase + 3500);

                foreach ($response['prices'] as $combo => &$p) {
                    $rawP = (float)$p;
                    if ($rawP > $origBasePrice || str_contains(strtolower($combo), 'large')) {
                        $delta = max(0, $rawP - $origBasePrice);
                        $p = ($delta > 0) ? ($calculatedBase + $delta) : $largePrice;
                    } else {
                        $p = $calculatedBase;
                    }
                }
                unset($p);

                if (empty($response['normals']) && !empty($response['prices'])) {
                    $normals = [];
                    foreach ($response['prices'] as $combo => $p) {
                        $normals[$combo] = $origBasePrice;
                    }
                    $response['normals'] = $normals;
                }
            }

            $this->setCache($cacheKey, $response);
            return $response;
        }

        return [
            'isSoldOut' => false,
            'reason' => '',
            'addons' => [],
            'groups' => [],
            'extras' => [],
        ];
    }

    /**
     * Process order submission and generate WhatsApp checkout link with single item fee (+2.000) and plastic bag (+1.000).
     */
    public function saveOrder(array $data): array
    {
        $prefix = (string) config('store.order_prefix', 'KYUTI');
        $randomCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $orderCode = $prefix . '-' . $randomCode;

        $items = $data['items'] ?? [];
        $totalQty = 0;
        foreach ($items as $it) {
            $totalQty += (int) ($it['qty'] ?? 1);
        }

        // Single item fee: +2.000 if order is 1 pcs
        $singleItemFeeConfig = (int) config('fees.single_item_fee', 2000);
        $hasSingleItemFee = ($totalQty === 1) || !empty($data['hasSingleItemFee']);
        $singleItemFee = $hasSingleItemFee ? $singleItemFeeConfig : 0;

        // Plastic bag fee: +1.000 if selected
        $plasticBagFeeConfig = (int) config('fees.plastic_bag_fee', 1000);
        $hasPlasticBag = !empty($data['plasticBag']);
        $plasticBagFee = $hasPlasticBag ? $plasticBagFeeConfig : 0;

        $subtotal = (float) ($data['subtotal'] ?? 0);
        $total = $subtotal + $singleItemFee + $plasticBagFee;

        $pickupTime = $data['pickupTime'] ?? 'Sekarang';
        $pickupTimezone = $data['pickupTimezone'] ?? 'WIB';
        if ($pickupTime !== 'Sekarang' && !str_contains($pickupTime, $pickupTimezone)) {
            $pickupTime .= ' ' . $pickupTimezone;
        }

        $order = [
            'orderCode' => $orderCode,
            'customerName' => trim($data['customerName'] ?? 'Kyuties'),
            'customerPhone' => trim($data['customerPhone'] ?? ''),
            'outletCode' => $data['outletCode'] ?? 'LA.CODEFIN',
            'outletName' => $data['outletName'] ?? 'Kopi Kenangan',
            'pickupTime' => $pickupTime,
            'notes' => trim($data['notes'] ?? ''),
            'hasPlasticBag' => $hasPlasticBag,
            'plasticBagFee' => $plasticBagFee,
            'hasSingleItemFee' => $hasSingleItemFee,
            'singleItemFee' => $singleItemFee,
            'status' => 'diproses',
            'statusText' => 'Pesanan Sedang Disiapkan',
            'statusDesc' => 'Pesananmu sedang disiapkan dengan penuh cinta oleh Kedai Kyuti ♡',
            'items' => $items,
            'subtotal' => $subtotal,
            'total' => $total,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ];

        // Format WhatsApp message
        $adminPhone = (string) config('store.admin_phone', '6288298374458');
        
        $waMessage = "୨୧ *FORMAT ORDER KEDAI KYUTI* ୨୧\n";
        $waMessage .= "--------------------------------------\n";
        $waMessage .= "Kode Pesanan: *" . $orderCode . "*\n";
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
            if (!empty($item['size'])) $options[] = $item['size'];
            if (!empty($item['temp'])) $options[] = $item['temp'];
            if (!empty($item['ice'])) $options[] = $item['ice'];
            if (!empty($item['sugar'])) $options[] = $item['sugar'];
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
                    $waMessage .= "    + Topping/Syrup: _" . implode(', ', $addonStrings) . "_\n";
                }
            }
            $waMessage .= "    Harga: Rp " . $priceFormatted . " | Subtotal: Rp " . $subtotalFormatted . "\n";
            if (!empty($item['note'])) {
                $waMessage .= "    Catatan: _" . $item['note'] . "_\n";
            }
        }

        $waMessage .= "--------------------------------------\n";
        $waMessage .= "Subtotal Menu: Rp " . number_format($order['subtotal'], 0, ',', '.') . "\n";

        if ($hasSingleItemFee) {
            $waMessage .= "Fee Pesanan 1 pcs: +Rp " . number_format($singleItemFee, 0, ',', '.') . "\n";
        } else {
            $waMessage .= "Fee Pesanan: Rp 0 (Bebas Fee Order 2+ pcs) ♡\n";
        }

        if ($hasPlasticBag) {
            $waMessage .= "Kantung Belanja Plastik: +Rp " . number_format($plasticBagFee, 0, ',', '.') . "\n";
        }

        if (!empty($order['notes'])) {
            $waMessage .= "Catatan Tambahan: _" . $order['notes'] . "_\n";
        }
        $waMessage .= "--------------------------------------\n";
        $waMessage .= "*TOTAL BAYAR: Rp " . number_format($order['total'], 0, ',', '.') . "*\n\n";
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
        ];
    }

    /**
     * Find order tracking info by code.
     */
    public function findOrder(string $code): array
    {
        $code = strtoupper(trim($code));

        if (!empty($code)) {
            return [
                'success' => true,
                'found' => true,
                'order' => [
                    'code' => $code,
                    'customerName' => 'Sahabat Kyuti',
                    'status' => 'diproses',
                    'statusText' => 'Pesanan Sedang Disiapkan',
                    'statusDesc' => 'Pesananmu sedang disiapkan dengan penuh cinta oleh Kedai Kyuti ♡',
                    'outletName' => 'Kopi Kenangan',
                    'pickup' => 'Sekarang',
                    'total' => 26500,
                    'items' => [
                        ['name' => 'Kopi Kenangan Mantan (Ice, Normal Sugar)', 'qty' => 1, 'price' => 15500, 'subtotal' => 15500],
                        ['name' => 'Roti Coklat Klasik', 'qty' => 1, 'price' => 9000, 'subtotal' => 9000],
                    ],
                    'updatedAt' => date('c'),
                ],
            ];
        }

        return [
            'success' => false,
            'found' => false,
            'message' => 'Pesanan dengan kode ' . htmlspecialchars($code) . ' tidak ditemukan.',
        ];
    }

    /**
     * Helper to perform HTTP GET request with cURL and fallback.
     */
    protected function httpRequest(string $url, array $params = []): ?array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $timeout = (int) config('api_timeout', 8);

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'KedaiKyutiNative/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
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
                    'user_agent' => 'KedaiKyutiNative/1.0',
                    'header' => "Accept: application/json\r\n",
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
        $outlets = [
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
                'code' => 'KK.SBY.RKABNGAN',
                'id' => 3627,
                'name' => 'Ruko Ambengan Surabaya',
                'address' => 'Ruko Ambengan Surabaya, Jl. Ambengan, Ketabang, Surabaya 60179',
                'city' => 'Surabaya',
                'lat' => -7.255728,
                'lng' => 112.749807,
                'isOpen' => true,
                'canOrder' => true,
                'statusText' => 'Buka',
                'hours' => '06:00 - 23:00',
                'img' => 'https://cdn.kopikenangan.com/ID/release/pub/b/m/dc7c918d-e6d7-4282-9b81-0b38b848f076',
                'category' => 'Shop House',
                'phone' => '0817 6562 292',
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
                    'largePrice' => 20500,
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
                    'largePrice' => 21000,
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
}