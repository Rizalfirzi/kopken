<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($storeName ?? 'Kopi Kenangan Live Store') ?> — Cek Outlet & Stok Real-Time</title>
    <meta name="description" content="Aplikasi pemesanan dan monitoring stok real-time outlet Kopi Kenangan Indonesia. Cek ketersediaan menu, promo harga, dan pesan langsung via WhatsApp.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">

    <!-- Native Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-container">
    <!-- Top Navigation -->
    <header class="topnav">
        <div class="topnav-left">
            <a href="index.php" class="brand-logo-wrap">
                <div class="brand-badge">KK</div>
                <div class="brand-text">
                    <h1>Kopi Kenangan</h1>
                    <span><span class="live-dot"></span> Real-Time Outlet & Stok</span>
                </div>
            </a>
        </div>
        <div class="topnav-actions">
            <button class="icon-btn" id="trackOrderBtn" title="Lacak Pesanan" aria-label="Lacak Pesanan">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.9-.9L3 20.5l1.6-4.6a8.3 8.3 0 0 1-1.1-4.4A8.5 8.5 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>
            </button>
            <button class="icon-btn" id="themeToggleBtn" title="Ganti Tema" aria-label="Ganti Tema">
                <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v2.2M12 20.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M1.5 12h2.2M20.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/></svg>
            </button>
        </div>
    </header>
