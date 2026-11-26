<?php
/**
 * Comprehensive Multi-Region Store Harvester
 * Harvests all stores across Indonesia from kopkenapi.swamptechnology.id
 */

$masterFile = __DIR__ . '/data/outlets_master.json';
$existingOutlets = [];
if (file_exists($masterFile)) {
    $oldData = json_decode(file_get_contents($masterFile), true);
    if (is_array($oldData)) {
        foreach ($oldData as $o) {
            if (!empty($o['code'])) {
                $existingOutlets[$o['code']] = $o;
            }
        }
    }
}

$regions = [
    'Jakarta' => [-6.2088, 106.8456],
    'Tangerang' => [-6.1783, 106.6319],
    'Bekasi' => [-6.2383, 106.9756],
    'Bogor' => [-6.5971, 106.8060],
    'Depok' => [-6.4025, 106.7942],
    'Karawang' => [-6.3045, 107.2774],
    'Bandung' => [-6.9175, 107.6191],
    'Cirebon' => [-6.7320, 108.5523],
    'Tasikmalaya' => [-7.3274, 108.2207],
    'Sukabumi' => [-6.9277, 106.9300],
    'Serang' => [-6.1104, 106.1640],
    'Cilegon' => [-6.0174, 106.0538],
    'Semarang' => [-6.9932, 110.4203],
    'Solo' => [-7.5755, 110.8243],
    'Yogyakarta' => [-7.7956, 110.3695],
    'Tegal' => [-6.8694, 109.1402],
    'Pekalongan' => [-6.8886, 109.6753],
    'Kudus' => [-6.8048, 110.8405],
    'Purwokerto' => [-7.4243, 109.2302],
    'Surabaya' => [-7.2575, 112.7521],
    'Malang' => [-7.9666, 112.6326],
    'Sidoarjo' => [-7.4478, 112.7183],
    'Gresik' => [-7.1566, 112.6555],
    'Kediri' => [-7.8480, 112.0178],
    'Madiun' => [-7.6298, 111.5239],
    'Jember' => [-8.1724, 113.6995],
    'Banyuwangi' => [-8.2192, 114.3691],
    'Denpasar_Bali' => [-8.6705, 115.2126],
    'Kuta_Bali' => [-8.7230, 115.1764],
    'Ubud_Bali' => [-8.5069, 115.2625],
    'Lombok_Mataram' => [-8.5833, 116.1167],
    'Kupang' => [-10.1772, 123.6070],
    'Medan' => [3.5952, 98.6722],
    'Pematangsiantar' => [2.9599, 99.0687],
    'Banda_Aceh' => [5.5483, 95.3238],
    'Pekanbaru' => [0.5071, 101.4478],
    'Batam' => [1.1301, 104.0529],
    'Tanjungpinang' => [0.9167, 104.4500],
    'Padang' => [-0.9471, 100.4172],
    'Jambi' => [-1.6101, 103.6131],
    'Palembang' => [-2.9761, 104.7754],
    'Bengkulu' => [-3.7928, 102.2608],
    'Bandar_Lampung' => [-5.4292, 105.2611],
    'Pangkalpinang' => [-2.1316, 106.1169],
    'Pontianak' => [-0.0263, 109.3425],
    'Banjarmasin' => [-3.3194, 114.5908],
    'Balikpapan' => [-1.2379, 116.8289],
    'Samarinda' => [-0.5022, 117.1536],
    'Palangkaraya' => [-2.2161, 113.9139],
    'Tarakan' => [3.3271, 117.5786],
    'Makassar' => [-5.1477, 119.4327],
    'Manado' => [1.4748, 124.8421],
    'Kendari' => [-3.9985, 122.5126],
    'Palu' => [-0.9003, 119.8780],
    'Gorontalo' => [0.5435, 123.0568],
    'Ambon' => [-3.6547, 128.1906],
    'Jayapura' => [-2.5916, 140.6690],
];

$allStores = [];

foreach ($regions as $regionName => [$lat, $lng]) {
    $maxPages = ($regionName === 'Jakarta') ? 16 : 4;
    for ($page = 1; $page <= $maxPages; $page++) {
        $url = "https://kopkenapi.swamptechnology.id/api/store/nearest?lat=$lat&lng=$lng&limit=50&page=$page";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'KedaiKyuti/2.0');
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($raw)) {
            break;
        }

        $json = json_decode($raw, true);
        $stores = $json['data']['store'] ?? [];
        if (empty($stores)) {
            break;
        }

        foreach ($stores as $s) {
            $code = trim($s['code'] ?? $s['store_code'] ?? $s['alias'] ?? '');
            if ($code === '') continue;

            $name = trim($s['name'] ?? $s['store_name'] ?? $code);
            if ($code === 'KK.SBY.RKABNGAN' || stripos($name, 'Ambengan') !== false || stripos($code, 'RKABNGAN') !== false) {
                continue;
            }

            if (isset($allStores[$code])) {
                continue;
            }

            $address = trim($s['address'] ?? '');
            $latStore = (float)($s['latitude'] ?? 0);
            $lngStore = (float)($s['longitude'] ?? 0);
            $isOpen = !empty($s['is_open']);
            $openTime = $s['open'] ?? '07:00:00';
            $closeTime = $s['close'] ?? '22:00:00';
            $hours = substr($openTime, 0, 5) . ' - ' . substr($closeTime, 0, 5);
            $img = $s['image_url'] ?? ($s['img'] ?? '');
            $cat = $s['category'] ?? '';
            $phone = $s['phone'] ?? '';

            $city = '';
            if (isset($existingOutlets[$code]['city']) && $existingOutlets[$code]['city'] !== '') {
                $city = $existingOutlets[$code]['city'];
            } else {
                if (preg_match('/(Jakarta\s+[A-Za-z]+|Kepulauan Seribu|Tangerang\s+Selatan|Tangerang|Bekasi|Bogor|Depok|Bandung|Surabaya|Semarang|Yogyakarta|Solo|Surakarta|Malang|Medan|Palembang|Makassar|Denpasar|Badung|Batam|Pekanbaru|Bandar Lampung|Padang|Pontianak|Banjarmasin|Balikpapan|Samarinda|Manado|Cirebon|Sukabumi|Tasikmalaya|Serang|Cilegon|Karawang|Purwakarta|Subang|Garut|Cianjur|Sumedang|Majalengka|Kuningan|Indramayu|Brebes|Tegal|Pemalang|Pekalongan|Batang|Kendal|Demak|Kudus|Pati|Rembang|Blora|Banyumas|Purwokerto|Cilacap|Purbalingga|Banjarnegara|Kebumen|Purworejo|Wonosobo|Magelang|Boyolali|Klaten|Sukoharjo|Wonogiri|Karanganyar|Sragen|Grobogan|Bojonegoro|Tuban|Lamongan|Gresik|Sidoarjo|Mojokerto|Jombang|Nganjuk|Madiun|Magetan|Ngawi|Ponorogo|Pacitan|Kediri|Blitar|Tulungagung|Trenggalek|Pasuruan|Probolinggo|Lumajang|Bondowoso|Situbondo|Jember|Banyuwangi)/i', $address, $m)) {
                    $city = trim($m[1]);
                }
            }

            $allStores[$code] = [
                'code' => $code,
                'id' => $s['id'] ?? ($existingOutlets[$code]['id'] ?? null),
                'name' => $name,
                'address' => $address,
                'city' => $city,
                'lat' => $latStore,
                'lng' => $lngStore,
                'isOpen' => $isOpen,
                'canOrder' => $isOpen,
                'statusText' => $isOpen ? 'Buka' : 'Tutup',
                'hours' => $hours,
                'img' => $img,
                'category' => $cat,
                'phone' => $phone,
                'distanceKm' => null
            ];
        }
        usleep(30000);
    }
    echo "$regionName done. Total unique so far: " . count($allStores) . "\n";
}

// Merge any legacy records not in scrape
foreach ($existingOutlets as $code => $oldStore) {
    if ($code === 'KK.SBY.RKABNGAN' || stripos($oldStore['name'] ?? '', 'Ambengan') !== false) {
        continue;
    }
    if (!isset($allStores[$code])) {
        $allStores[$code] = $oldStore;
    }
}

$finalList = array_values($allStores);
usort($finalList, fn($a, $b) => strcasecmp($a['name'], $b['name']));

file_put_contents($masterFile, json_encode($finalList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "DONE! Written " . count($finalList) . " outlets to $masterFile\n";
