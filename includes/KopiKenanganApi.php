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
        $this->baseUrl = rtrim((string) config('api_base_url', 'https://twinsjasdor.my.id/api'), '/');
        $this->origin = rtrim((string) config('api_origin', 'https://twinsjasdor.my.id'), '/');
        $this->outletsTtl = (int) config('cache_ttl.outlets', 1800);
        $this->stockTtl = (int) config('stock.ttl_seconds', config('cache_ttl.menu', 60));
        $this->cacheDir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kedaikyuti_cache';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Cookie file path for session persistence with twinsjasdor.my.id
     */
    protected function getCookieFile(): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . 'twins_cookie.txt';
    }

    /**
     * Handshake with twinsjasdor.my.id to obtain a valid Cloudflare session cookie (jd_v).
     */
    protected function refreshSessionCookie(): bool
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $cookieFile = $this->getCookieFile();
        $targetOrigin = $this->origin ?: 'https://twinsjasdor.my.id';

        $ch = curl_init($targetOrigin . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 400 && file_exists($cookieFile));
    }

    /**
     * Fetch list of outlets with real-time hours evaluation, search, and distance sorting.
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
     * Get a specific outlet by code, searching upstream if not present in default outlet list.
     */
    public function getOutletByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
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

        if ($response && isset($response['items']) && is_array($response['items'])) {
            $rawItems = $response['items'];
            $rawAvail = 0;
            foreach ($rawItems as $it) {
                if (empty($it['isSoldOut']) && empty($it['notCarried'])) {
                    $rawAvail++;
                }
            }

            // An outlet that is open (!isClosed) should never have 0 available items across 10+ items.
            // If availCount == 0 on an open outlet, this is an upstream stock sync glitch / unauthenticated session!
            $isReportedClosed = !empty($response['isClosed']);
            if ($rawAvail === 0 && !$isReportedClosed && count($rawItems) > 5) {
                // Attempt to retrieve a previous valid cache (up to 4 hours old) to protect users from seeing all menus empty
                $staleCached = $this->getCache($cacheKey, 14400);
                if ($staleCached !== null && !empty($staleCached['items'])) {
                    $staleAvail = 0;
                    foreach ($staleCached['items'] as $sit) {
                        if (empty($sit['isSoldOut']) && empty($sit['notCarried'])) {
                            $staleAvail++;
                        }
                    }
                    if ($staleAvail > 0) {
                        return $this->applyMenuCustomizations($staleCached, $outletCode);
                    }
                }

                // If no stale cache exists, recover core items:
                // Only mark genuinely discontinued seasonal promos as notCarried, restore regular staple items
                foreach ($rawItems as &$it) {
                    $normName = strtolower($it['name'] ?? '');
                    $isSeasonalDiscontinued = str_contains($normName, 'banana') || 
                                              str_contains($normName, 'earl grey') || 
                                              str_contains($normName, 'fresh lemonade');
                    if (!$isSeasonalDiscontinued) {
                        $it['isSoldOut'] = false;
                        $it['notCarried'] = false;
                    }
                }
                unset($it);
                $response['items'] = $rawItems;
            }

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

            // Check if outlet is closed via operational hours or force_status
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
            $isClosed = !empty($response['isClosed']);
            if ($outletInfo && isset($outletInfo['canOrder']) && !$outletInfo['canOrder']) {
                $isClosed = true;
            }

            $result = [
                'success' => true,
                'live' => $response['live'] ?? true,
                'isClosed' => $isClosed,
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

            // Only cache if valid items exist or if outlet is closed
            if ($rawAvail > 0 || $isClosed) {
                $this->setCache($cacheKey, $result);
            }
            return $this->applyMenuCustomizations($result, $outletCode);
        }

        // Fallback menu when upstream is unreachable or returns 502
        $fallback = $this->getFallbackMenu($outletCode);
        $outletInfo = $this->getOutletByCode($outletCode);
        if (!$outletInfo) {
            $outletInfo = $this->evaluateOutletHours(['code' => $outletCode]);
        }
        if ($outletInfo && isset($outletInfo['canOrder']) && !$outletInfo['canOrder']) {
            $fallback['isClosed'] = true;
        }
        if ($outletInfo && !empty($outletInfo['name'])) {
            $fallback['outletName'] = $outletInfo['name'];
        }
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
        $processedItems = [];
        foreach ($items as &$item) {
            $key = $item['key'] ?? $item['id'] ?? '';
            $category = $item['category'] ?? 'Coffee';

            // Filter out items that are NOT CARRIED by this outlet (discontinued seasonal promos like Korean Banana / not sold here)
            // unless explicitly overridden in admin stock settings
            if (!empty($item['notCarried']) && !isset($stockOverrides[$key])) {
                continue;
            }

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

            // Fee difference from default markup (if user configures a custom fee per category or item)
            $feeDelta = $fee - $defaultMarkup;

            // Raw price from API
            $rawApiPrice = isset($item['price']) ? (float)$item['price'] : 0;

            // Calculate Selling Price using Kedai Kyuti Formula: (Harga Asli - 50%) + Fee
            // Catatan: Upstream API menambahkan fee +6.500 (surcharge +1.500) pada outlet premium.
            // Oleh karena itu, kita hitung murni dari $origPrice (normalPrice / outletPrice)
            // agar fee tetap konsisten sesuai aturan Kedai Kyuti (+5.000 / setting admin).
            if ($origPrice > 0) {
                $discountedBase = $origPrice * (1 - ($discountPercent / 100));
                $calculatedPrice = round($discountedBase + $fee);
            } elseif ($rawApiPrice > 0) {
                // Fallback jika item tidak memiliki normalPrice di upstream API
                $baseRaw = (!empty($menuData['premium']) && $rawApiPrice > 1500) ? ($rawApiPrice - 1500) : $rawApiPrice;
                $calculatedPrice = round($baseRaw + $feeDelta);
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
            $item['normalPrice'] = $origPrice;

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
                // Kopi Kenangan Mantan: Regular = 19.000, Large = 25.000 (Selisih +6.000)
                // Other drinks: Regular = $origPrice, Large = $origPrice + 6.000
                $isKKM = str_contains($normName, 'kenanganmantan') || str_contains($normKey, 'kenanganmantan') || str_contains($normName, 'kkm') || str_contains($normKey, 'kkm');
                if ($isKKM) {
                    $origLarge = ($origPrice >= 22000) ? 28000.0 : 25000.0;
                } elseif (!empty($item['largeNormal']) && (float)$item['largeNormal'] > $origPrice) {
                    $origLarge = (float)$item['largeNormal'];
                } else {
                    $origLarge = $origPrice + 6000.0;
                }
                $item['largeNormal'] = $origLarge;

                // Calculate Large Selling Price using Kedai Kyuti Formula: (Harga Asli - 50%) + Fee
                if ($origLarge > 0) {
                    $discountedLarge = $origLarge * (1 - ($discountPercent / 100));
                    $calcLarge = round($discountedLarge + $effectiveLargeFee);
                } else {
                    $calcLarge = $calculatedPrice + 3000;
                }

                // Check manual price override for large
                if (isset($priceOverrides[$largeKey])) {
                    $calcLarge = (float) $priceOverrides[$largeKey];
                }

                // Ensure large price is strictly higher than regular price (by at least 3.000)
                if ($calcLarge <= $calculatedPrice) {
                    $calcLarge = $calculatedPrice + 3000;
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

        // 2. Prepend Bundling Packages (Hanya untuk Outlet Regular, tidak tersedia di Outlet Premium per SOP Klien)
        $categories = $menuData['categories'] ?? [];
        if (empty($menuData['premium'])) {
            $bundlingItems = config('bundling_items', []);
            $bundlingStatus = config('pricing.bundling_status', []);
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

            $rawResponse = $this->httpRequest($url, $params);
            if ($rawResponse && is_array($rawResponse)) {
                $this->setCache($upstreamCacheKey, $rawResponse);
            }
        }

        if ($rawResponse && is_array($rawResponse)) {
            // Deep copy so upstream cache remains untouched and pure
            $response = json_decode(json_encode($rawResponse), true);

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

                $largePriceBase = (float)($matchedItem['largePrice'] ?? ($calculatedBase + 3000));
                $origLargeNormal = (float)($matchedItem['largeNormal'] ?? ($origBaseNormal + 6000));
                if ($origLargeNormal <= 0) $origLargeNormal = 25000;

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

        return [
            'isSoldOut' => false,
            'reason' => '',
            'addons' => [],
            'groups' => [],
            'extras' => [],
        ];
    }

    /**
     * Fetch Live Wi-Fi SSID & Daily Password for an outlet
     * URL: https://tixandcoffee.developerngantuk.my.id/api/kopken/wifi?outlet={outlet}
     */
    public function getWifi(string $outletCode): array
    {
        $outletCode = trim($outletCode);
        if ($outletCode === '') {
            $outletCode = config('store.default_outlet', 'LA.CODEFIN');
        }

        $cacheKey = 'wifi_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $outletCode);
        $cached = $this->getCache($cacheKey, 300); // 5 minutes cache
        if ($cached !== null) {
            return $cached;
        }

        $url = $this->baseUrl . '/kopken/wifi';
        $response = $this->httpRequest($url, ['outlet' => $outletCode]);

        if ($response && !empty($response['wifi'])) {
            $result = [
                'success' => true,
                'outlet' => $outletCode,
                'wifi' => [
                    'ssid' => $response['wifi']['ssid'] ?? 'Teman Kenangan',
                    'password' => $response['wifi']['password'] ?? ('KopiBikinSemangat@' . date('j')),
                ],
                'date' => date('l, d F Y'),
            ];
            $this->setCache($cacheKey, $result);
            return $result;
        }

        // Fallback default Kenangan Wi-Fi format
        $dayNum = date('j');
        $fallback = [
            'success' => true,
            'outlet' => $outletCode,
            'wifi' => [
                'ssid' => 'Teman Kenangan',
                'password' => 'KopiBikinSemangat@' . $dayNum,
            ],
            'date' => date('l, d F Y'),
        ];
        return $fallback;
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
        $waMessage .= "🔍 Link Tracking:\n" . $trackUrl . "\n";
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
            if (!$isBundling) {
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
     * Helper to perform HTTP GET request with cURL, automatic cookie session handling, and fallback.
     */
    protected function httpRequest(string $url, array $params = [], int $retry = 1): ?array
    {
        if (!empty($params)) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        $timeout = (int) config('api_timeout', 12);
        $cookieFile = $this->getCookieFile();
        $isTwins = str_contains($url, 'twinsjasdor.my.id');

        // Automatically fetch cookie if missing or older than 6 hours
        if ($isTwins && (!file_exists($cookieFile) || (time() - (int)@filemtime($cookieFile) > 21600))) {
            $this->refreshSessionCookie();
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

            $headers = ['Accept: application/json'];
            if ($isTwins) {
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
                curl_setopt($ch, CURLOPT_REFERER, 'https://twinsjasdor.my.id/');
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // If session expired or unauthorized on twinsjasdor, refresh session and retry once
            if ($isTwins && $retry > 0 && ($httpCode === 401 || $httpCode === 403 || ($response !== false && (str_contains($response, 'Sesi halaman habis') || str_contains($response, 'Akses ditolak'))))) {
                @unlink($cookieFile);
                $this->refreshSessionCookie();
                return $this->httpRequest($url, [], $retry - 1);
            }

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode($response, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (ini_get('allow_url_fopen')) {
            $headerStr = "Accept: application/json\r\n";
            if ($isTwins) {
                $headerStr .= "Referer: https://twinsjasdor.my.id/\r\n";
                if (file_exists($cookieFile)) {
                    $cookieContent = @file_get_contents($cookieFile);
                    if ($cookieContent && preg_match('/jd_v\s+([^\s]+)/', $cookieContent, $m)) {
                        $headerStr .= "Cookie: jd_v=" . $m[1] . "\r\n";
                    }
                }
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'header' => $headerStr,
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
}