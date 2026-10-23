# Kopi Kenangan Live Store (PHP Native) ☕

Aplikasi Web **PHP Native Murni (Zero Database & Zero Laravel)** untuk pemesanan dan monitoring stok real-time outlet **Kopi Kenangan Indonesia** dengan koneksi API langsung dan siap di-deploy ke **Vercel**, XAMPP, Laragon, maupun server hosting lainnya.

---

## 🌟 Fitur & Keunggulan

1. **100% PHP Native & Tanpa Database (Zero Database Dependency)**
   - Semua data menu, harga, kategori, dan stok live outlet diambil langsung secara real-time dari API.
   - Tidak memerlukan PostgreSQL, MySQL, SQLite, ataupun file database lokal.
   - Riwayat pesanan dan status pelacakan untuk pemesan disimpan di sisi browser (`localStorage`).
   - Format pemesanan langsung terintegrasi dengan **WhatsApp** resmi Barista.

2. **Siap Deploy ke Vercel (Vercel Serverless Ready)**
   - Sudah dilengkapi dengan konfigurasi [`vercel.json`](file:///d:/kopken/vercel.json) menggunakan runtime serverless `vercel-php`.
   - Caching file otomatis diarahkan ke temporary storage (`sys_get_temp_dir()`) yang ramah lingkungan read-only serverless seperti Vercel.

3. **Koneksi Live API Kopi Kenangan**
   - **Outlets**: Mengambil daftar outlet resmi secara real-time dengan filter pencarian dan kalkulasi jarak GPS (Haversine Formula).
   - **Menu & Real-Time Stock**: Mengambil menu lengkap beserta status ketersediaan (*Stok Ready* vs *Habis di Outlet*).
   - **Live Stock Refresh**: Tombol pembaruan stok real-time instan dengan animasi spinner.
   - **Offline Fallback**: Menyediakan data fallback cerdas jika koneksi internet atau server remote sedang offline.

4. **Front-End & UI/UX Interaktif (HTML & Vanilla JavaScript)**
   - **Kustomisasi Minuman**: Pilihan ukuran cup (*Regular / Large*), suhu (*Ice / Hot*), level es (*Normal / Less*), level gula (*Normal / 50% / No Sugar*), catatan pesanan, dan kuantitas.
   - **Keranjang Belanja (Cart)**: Disimpan otomatis di `localStorage`, perhitungan subtotal otomatis, dan sticky bottom bar.
   - **Pelacakan Pesanan (Order Tracking)**: Lacak status pesanan secara visual dengan stepper interaktif (*Diterima ➜ Diproses ➜ Siap Ambil*).
   - **Tema Gelap & Terang (Dark/Light Mode)**: Tersedia toggle switcher yang disimpan otomatis di browser.

---

## 📁 Struktur Direktori Bersih

```text
d:/kopken/
├── api/                     # REST API Endpoints (Native PHP)
│   ├── menu.php             # GET: Data menu & status stok per outlet
│   ├── order.php            # POST: Format pesanan & generate link WhatsApp
│   ├── outlets.php          # GET: Daftar outlet & pencarian jarak GPS
│   └── track.php            # GET: Pelacakan pesanan berdasarkan kode
├── assets/
│   ├── css/
│   │   └── style.css        # Stylesheet lengkap (Responsive, Theme, UI)
│   └── js/
│       └── app.js           # Client logic (State, Fetch API, Cart, Modals)
├── includes/
│   ├── footer.php           # Template footer, floating cart, & scripts
│   ├── header.php           # Template header & navigation bar
│   ├── KopiKenanganApi.php  # Service class PHP untuk request API & cache
│   └── modals.php           # Modal bottom sheet (Outlet, Custom, Cart, Track)
├── .htaccess                # URL rewrite untuk Apache / XAMPP / cPanel
├── config.php               # Konfigurasi aplikasi & nomor WhatsApp admin
├── index.php                # Entry point utama aplikasi
├── vercel.json              # Konfigurasi deployment Vercel Serverless
└── README.md                # Dokumentasi proyek
```

---

## 🚀 Panduan Deployment ke Vercel

Aplikasi ini sudah 100% siap untuk di-deploy ke Vercel:

### Langkah Deploy:
1. Push repository ini ke **GitHub / GitLab / Bitbucket**.
2. Buka dashboard [Vercel](https://vercel.com/) lalu klik **Add New Project**.
3. Import repository project `kopken`.
4. Vercel akan otomatis mendeteksi file [`vercel.json`](file:///d:/kopken/vercel.json).
5. Klik tombol **Deploy**!
6. Web langsung live dan berfungsi penuh!

---

## 💻 Cara Menjalankan Secara Lokal

### Menggunakan PHP Built-in Server (Paling Cepat)
Cukup buka terminal / PowerShell di folder ini, lalu jalankan:
```bash
php -S localhost:8000
```
Buka browser dan akses: [http://localhost:8000](http://localhost:8000)

### Menggunakan XAMPP / Laragon
1. Letakkan folder ini di `htdocs` (XAMPP) atau `www` (Laragon).
2. Akses via browser: [http://localhost/kopken](http://localhost/kopken).

---

## ⚙️ Konfigurasi (`config.php`)

Ubah nomor WhatsApp admin penerima pesanan atau URL API di [config.php](file:///d:/kopken/config.php):
```php
'store' => [
    'name' => 'Kopi Kenangan Live Store',
    'admin_phone' => '6288298374458', // Nomor WA admin
    'order_prefix' => 'KK',
    'default_outlet' => 'LA.CODEFIN',
],
```
