<?php
/**
 * Kopi Kenangan Native API & Data Service
 * 
 * Fitur:
 * 1. Menyesuaikan jam operasional di seluruh outlet (Otomatis WIB & Manual Override)
 * 2. Menyesuaikan stok ketersediaan real-time dari APK Kopi Kenangan & Manual Override
 * 3. Menyesuaikan harga per menu (Markup & Custom Price) & Menggabungkan menu baru custom
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
        $this->cacheDir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kopken_cache';

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
            // Re-evaluate hours dynamically on cached outlets to reflect current clock minute
            foreach ($cached['outlets'] as &$o) {
                $o = $this->evaluateOutletHours($o);
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

            // 2. Evaluate Operating Hours for each outlet (Point 1)
            foreach ($outlets as &$outlet) {
                $outlet = $this->evaluateOutletHours($outlet);
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
     * Point 1: Evaluate Operating Hours based on current WIB time and config overrides.
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

                // Handle 24 hours (e.g. 00:01 - 23:19 or 00:00 - 23:59)
                $is24Hours = ($openMinutes <= 1 && $closeMinutes >= 23 * 60);

                if ($is24Hours) {
                    $isOpen = true;
                } elseif ($openMinutes <= $closeMinutes) {
                    // Normal schedule within same day
                    $isOpen = ($nowMinutes >= $openMinutes && $nowMinutes <= $closeMinutes);
                } else {
                    // Overnight schedule (e.g. 18:00 - 03:00)
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
     * Point 2 & 3: Fetch Real-time Menu, adjust stock, apply price overrides, and merge custom menus.
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
            $result = [
                'success' => true,
                'live' => $response['live'] ?? true,
                'isClosed' => $response['isClosed'] ?? false,
                'premium' => $response['premium'] ?? false,
                'brand' => $response['brand'] ?? 'kopken',
                'outletCode' => $outletCode,
                'categories' => $response['categories'] ?? [
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
     * Apply User Customizations:
     * - Stock Overrides (Point 2)
     * - Price Adjustments & Markup (Point 3)
     * - Custom New Menus (Point 3)
     */
    protected function applyMenuCustomizations(array $menuData, string $outletCode): array
    {
        $items = $menuData['items'] ?? [];
        $stockOverrides = config('stock.overrides', []);
        $priceOverrides = config('menu.price_overrides', []);
        $markup = (float) config('menu.global_price_markup', 0);

        // 1. Apply overrides & price adjustments on existing items
        foreach ($items as &$item) {
            $key = $item['key'] ?? $item['id'] ?? '';

            // Stock Override (Point 2)
            if (isset($stockOverrides[$key])) {
                $item['isSoldOut'] = (bool) $stockOverrides[$key];
            }

            // Price Override (Point 3)
            if (isset($priceOverrides[$key])) {
                $item['price'] = (float) $priceOverrides[$key];
            } elseif ($markup > 0) {
                $item['price'] += $markup;
                if (!empty($item['largePrice'])) {
                    $item['largePrice'] += $markup;
                }
            }
        }
        unset($item);

        // 2. Add New Custom Menus (Point 3)
        $customItems = config('menu.custom_items', []);
        if (!empty($customItems) && is_array($customItems)) {
            $customFormatted = [];
            foreach ($customItems as $c) {
                $key = $c['key'] ?? ('custom_' . md5($c['name']));
                $isSold = !empty($c['isSoldOut']);
                if (isset($stockOverrides[$key])) {
                    $isSold = (bool) $stockOverrides[$key];
                }

                $price = (float) ($c['price'] ?? 0);
                if (isset($priceOverrides[$key])) {
                    $price = (float) $priceOverrides[$key];
                } elseif ($markup > 0) {
                    $price += $markup;
                }

                $customFormatted[] = [
                    'key' => $key,
                    'id' => $key,
                    'name' => $c['name'] ?? 'Menu Baru',
                    'category' => $c['category'] ?? 'Coffee',
                    'img' => $c['img'] ?? 'https://placehold.co/200x200?text=Menu+Baru',
                    'desc' => $c['desc'] ?? '',
                    'price' => $price,
                    'normalPrice' => !empty($c['normalPrice']) ? (float) $c['normalPrice'] : null,
                    'largePrice' => !empty($c['largePrice']) ? (float) $c['largePrice'] : null,
                    'isSoldOut' => $isSold,
                    'tag' => $c['tag'] ?? 'BARU',
                    'options' => $c['options'] ?? [
                        'sizes' => ['Regular', 'Large'],
                        'temps' => ['Ice', 'Hot'],
                    ],
                ];
            }

            // Prepend custom items so they appear prominently at the top
            $items = array_merge($customFormatted, $items);
        }

        // 3. Recalculate Live Stock Statistics (Point 2)
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

        $menuData['items'] = $items;
        $menuData['stats'] = [
            'total' => $totalCount,
            'available' => $availableCount,
            'soldOut' => $soldOutCount,
        ];

        return $menuData;
    }

    /**
     * Process order submission and generate WhatsApp checkout link (No Database).
     */
    public function saveOrder(array $data): array
    {
        $prefix = (string) config('store.order_prefix', 'KK');
        $randomCode = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $orderCode = $prefix . '-' . $randomCode;

        $order = [
            'orderCode' => $orderCode,
            'customerName' => trim($data['customerName'] ?? 'Sahabat Kenangan'),
            'customerPhone' => trim($data['customerPhone'] ?? ''),
            'outletCode' => $data['outletCode'] ?? 'LA.CODEFIN',
            'outletName' => $data['outletName'] ?? 'Kopi Kenangan',
            'pickupTime' => $data['pickupTime'] ?? 'Sekarang',
            'notes' => trim($data['notes'] ?? ''),
            'status' => 'diproses',
            'statusText' => 'Pesanan Sedang Disiapkan',
            'statusDesc' => 'Barista sedang meracik pesanan kopimu dengan cinta.',
            'items' => $data['items'] ?? [],
            'subtotal' => (float) ($data['subtotal'] ?? 0),
            'discount' => (float) ($data['discount'] ?? 0),
            'total' => (float) ($data['total'] ?? 0),
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ];

        // Format WhatsApp message
        $adminPhone = (string) config('store.admin_phone', '6288298374458');
        $storeTitle = (string) config('store.name', 'Kopi Kenangan');
        
        $waMessage = "*FORMAT ORDER " . strtoupper($storeTitle) . "*\n";
        $waMessage .= "--------------------------------------\n";
        $waMessage .= "Kode Pesanan: *" . $orderCode . "*\n";
        $waMessage .= "Outlet: *" . $order['outletName'] . "*\n";
        $waMessage .= "Nama: *" . $order['customerName'] . "*\n";
        if (!empty($order['customerPhone'])) {
            $waMessage .= "No. HP: " . $order['customerPhone'] . "\n";
        }
        $waMessage .= "Waktu Pickup: " . $order['pickupTime'] . "\n";
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
            $waMessage .= "    Harga: Rp " . $priceFormatted . " | Subtotal: Rp " . $subtotalFormatted . "\n";
            if (!empty($item['note'])) {
                $waMessage .= "    Catatan: _" . $item['note'] . "_\n";
            }
        }

        $waMessage .= "--------------------------------------\n";
        if (!empty($order['notes'])) {
            $waMessage .= "Catatan Tambahan: _" . $order['notes'] . "_\n";
        }
        $waMessage .= "*Total Bayar: Rp " . number_format($order['total'], 0, ',', '.') . "*\n\n";
        $waMessage .= "Mohon segera diproses ya min, terima kasih! ☕✨";

        $waUrl = 'https://wa.me/' . $adminPhone . '?text=' . rawurlencode($waMessage);

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
                    'customerName' => 'Sahabat Kenangan',
                    'status' => 'diproses',
                    'statusText' => 'Pesanan Sedang Disiapkan',
                    'statusDesc' => 'Barista sedang meracik pesanan kopimu dengan cinta.',
                    'outletName' => 'Kopi Kenangan',
                    'pickup' => 'Sekarang',
                    'total' => 38500,
                    'items' => [
                        ['name' => 'Kopi Kenangan Mantan (Ice, Normal Sugar)', 'qty' => 1, 'price' => 14500, 'subtotal' => 14500],
                        ['name' => 'Creamy Aren Latte (Ice, Less Sugar)', 'qty' => 1, 'price' => 16000, 'subtotal' => 16000],
                        ['name' => 'Bambang Choco Cheese Toast', 'qty' => 1, 'price' => 8000, 'subtotal' => 8000],
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

        // Try cURL first if available
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'KopiKenanganNative/1.0');
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

        // Fallback to file_get_contents
        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'user_agent' => 'KopiKenanganNative/1.0',
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

    /**
     * Cache file helpers using system temp directory
     */
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

    /**
     * Built-in fallback outlets
     */
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

    /**
     * Built-in fallback menu items
     */
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
                    'price' => 14500,
                    'normalPrice' => 19000,
                    'largePrice' => 19500,
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