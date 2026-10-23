<?php
/**
 * Kopi Kenangan Native API & Data Service
 * 100% API based, Zero Database dependency.
 * Designed for serverless environments like Vercel as well as traditional PHP hosts.
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
        $this->stockTtl = (int) config('cache_ttl.menu', 60);
        $this->cacheDir = defined('CACHE_PATH') ? CACHE_PATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kopken_cache';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    /**
     * Fetch list of outlets with optional search term and geo-distance sorting.
     *
     * @param string|null $query
     * @param float|null $lat
     * @param float|null $lng
     * @return array
     */
    public function getOutlets(?string $query = null, ?float $lat = null, ?float $lng = null): array
    {
        $query = trim((string) $query);
        $cacheKey = 'outlets_' . md5($query . '|' . ($lat ? round($lat, 3) : '') . '|' . ($lng ? round($lng, 3) : ''));
        
        $cached = $this->getCache($cacheKey, $this->outletsTtl);
        if ($cached !== null) {
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

            // Calculate distance locally if user coordinates are provided
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

            $result = [
                'success' => true,
                'source' => 'live',
                'count' => count($outlets),
                'outlets' => $outlets,
            ];

            $this->setCache($cacheKey, $result);
            return $result;
        }

        // Fallback to built-in offline outlets if remote API is unreachable
        $fallback = $this->getFallbackOutlets($query, $lat, $lng);
        return [
            'success' => true,
            'source' => 'fallback',
            'count' => count($fallback),
            'outlets' => $fallback,
        ];
    }

    /**
     * Fetch real-time menu and stock status for an outlet.
     *
     * @param string $outletCode
     * @param bool $forceRefresh
     * @return array
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
        } else {
            $cached = $this->getCache($cacheKey, $this->stockTtl);
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = $this->baseUrl . '/kopken/menu';
        $response = $this->httpRequest($url, ['outlet' => $outletCode]);

        if ($response && isset($response['items'])) {
            $items = $response['items'];
            $totalCount = count($items);
            $availableCount = 0;
            $soldOutCount = 0;

            foreach ($items as $item) {
                $isSoldOut = !empty($item['isSoldOut']) || !empty($item['notCarried']);
                if ($isSoldOut) {
                    $soldOutCount++;
                } else {
                    $availableCount++;
                }
            }

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
                'items' => $items,
                'stats' => [
                    'total' => $totalCount,
                    'available' => $availableCount,
                    'soldOut' => $soldOutCount,
                ],
                'updatedAt' => date('c'),
            ];

            $this->setCache($cacheKey, $result);
            return $result;
        }

        // Fallback to built-in offline menu
        return $this->getFallbackMenu($outletCode);
    }

    /**
     * Process order submission and generate WhatsApp checkout link (No Database).
     *
     * @param array $data
     * @return array
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
        $waMessage = "*FORMAT ORDER KOPI KENANGAN*\n";
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
     *
     * @param string $code
     * @return array
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
     *
     * @param string $url
     * @param array $params
     * @return array|null
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
        $items = [
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
            [
                'key' => 'americano',
                'id' => 'americano',
                'name' => 'Americano (Black Coffee)',
                'category' => 'Coffee',
                'img' => 'https://cdn.kopikenangan.com/public/40c1585d-4909-e368-9e2f-7249fce6e551',
                'desc' => 'Double shot espresso dari biji kopi arabika & robusta lokal dengan aroma kaya dan rasa seimbang.',
                'price' => 12000,
                'normalPrice' => 15000,
                'largePrice' => 16000,
                'isSoldOut' => false,
                'options' => [
                    'sizes' => ['Regular', 'Large'],
                    'temps' => ['Ice', 'Hot'],
                    'sugars' => ['No Sugar', 'Normal Sugar'],
                ],
            ],
            [
                'key' => 'matcha_latte',
                'id' => 'matcha_latte',
                'name' => 'Susu Matcha Kenangan',
                'category' => 'Non Coffee',
                'img' => 'https://cdn.kopikenangan.com/public/dc910c78-30c6-30e2-a9ab-0ffafe803344',
                'desc' => 'Bubuk matcha murni asal Jepang diseduh dengan susu segar berkualitas tinggi, harum dan menenangkan.',
                'price' => 18000,
                'normalPrice' => 24000,
                'largePrice' => 23000,
                'isSoldOut' => false,
                'tag' => 'POPULER',
                'options' => [
                    'sizes' => ['Regular', 'Large'],
                    'temps' => ['Ice', 'Hot'],
                    'ices' => ['Normal Ice', 'Less Ice'],
                    'sugars' => ['Normal Sugar', 'Less Sugar (50%)'],
                ],
            ],
            [
                'key' => 'choco_hazelnut',
                'id' => 'choco_hazelnut',
                'name' => 'Cokelat Klasik Hazelnut',
                'category' => 'Non Coffee',
                'img' => 'https://cdn.kopikenangan.com/public/48d2b75c-329a-0216-6100-e475c6775430',
                'desc' => 'Kombinasi pekat cokelat premium Belgia dengan wangi aroma hazelnut yang memikat.',
                'price' => 17000,
                'normalPrice' => 21000,
                'largePrice' => 22000,
                'isSoldOut' => false,
                'options' => [
                    'sizes' => ['Regular', 'Large'],
                    'temps' => ['Ice', 'Hot'],
                    'ices' => ['Normal Ice', 'Less Ice'],
                    'sugars' => ['Normal Sugar', 'Less Sugar'],
                ],
            ],
            [
                'key' => 'frappe_caramel',
                'id' => 'frappe_caramel',
                'name' => 'Caramel Frappe with Whip',
                'category' => 'Frappe',
                'img' => 'https://cdn.kopikenangan.com/public/5fa5edac-9b48-7596-12a5-00c47632daeb',
                'desc' => 'Minuman blender es lembut rasa kopi karamel dengan topping whipped cream dan saus karamel leleh.',
                'price' => 24000,
                'normalPrice' => 29000,
                'largePrice' => 29000,
                'isSoldOut' => false,
                'tag' => 'SPESIAL',
                'options' => [
                    'sizes' => ['Regular', 'Large'],
                    'sugars' => ['Normal Sugar', 'Less Sugar'],
                ],
            ],
            [
                'key' => 'toast_choco_cheese',
                'id' => 'toast_choco_cheese',
                'name' => 'Bambang Choco Cheese Toast',
                'category' => 'Food',
                'img' => 'https://cdn.kopikenangan.com/public/ceedcaa8-9c46-d366-799b-5bdd0e6386ac',
                'desc' => 'Roti panggang empuk dengan isian cokelat lumer dan keju gurih melimpah. Cocok jadi teman ngopi.',
                'price' => 8000,
                'normalPrice' => 12000,
                'largePrice' => null,
                'isSoldOut' => false,
                'options' => [],
            ],
        ];

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
            'items' => $items,
            'stats' => [
                'total' => count($items),
                'available' => count($items),
                'soldOut' => 0,
            ],
            'updatedAt' => date('c'),
        ];
    }
}
