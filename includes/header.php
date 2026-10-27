<?php
$storeTitle = config('store.name', 'Kedai Kyuti');
$domainName = config('store.domain', 'kedaikyuti.my.id');
$tagline = config('store.tagline', 'JASDOR KOPI KENANGAN');
$logoText = config('store.logo_text', '୨୧');
$logoImg = config('store.logo_image');

// Dynamic theme colors from config.php (Pink Pastel Theme)
$primaryColor = config('theme.primary_color', '#F472B6');
$primaryDark = config('theme.primary_dark', '#DB2777');
$primaryLight = config('theme.primary_light', '#FDF2F4');
$accentColor = config('theme.accent_color', '#FB7185');
$goldColor = config('theme.gold_color', '#F59E0B');
$bgPage = config('theme.bg_page', '#FFF5F7');
$bgCard = config('theme.bg_card', '#FFFFFF');
$bgCardSubtle = config('theme.bg_card_subtle', '#FCE7F3');
$borderColor = config('theme.border_color', '#FBCFE8');
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($storeTitle) ?> (<?= htmlspecialchars($domainName) ?>) — <?= htmlspecialchars($tagline) ?></title>
    <meta name="description" content="Kedai Kyuti (kedaikyuti.my.id) - Jasa Titip Order (Jasdor) Kopi Kenangan Murah. Diskon 50% + fee hemat, stok real-time, pesan via WhatsApp.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Favicon & Brand Icons -->
    <link rel="icon" type="image/jpeg" href="<?= htmlspecialchars($logoImg ?: 'assets/img/logo/kyuti.jpeg') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($logoImg ?: 'assets/img/logo/kyuti.jpeg') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($logoImg ?: 'assets/img/logo/kyuti.jpeg') ?>">

    <!-- Native Stylesheet with Cache Buster -->
    <?php $cssVer = file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time(); ?>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $cssVer ?>">

    <!-- Dynamic Theme Customization (from config.php) -->
    <style>
        :root {
            --brand-primary: <?= htmlspecialchars($primaryColor) ?> !important;
            --brand-primary-dark: <?= htmlspecialchars($primaryDark) ?> !important;
            --brand-primary-light: <?= htmlspecialchars($primaryLight) ?> !important;
            --brand-accent: <?= htmlspecialchars($accentColor) ?> !important;
            --brand-gold: <?= htmlspecialchars($goldColor) ?> !important;
            --bg-page: <?= htmlspecialchars($bgPage) ?> !important;
            --bg-card: <?= htmlspecialchars($bgCard) ?> !important;
            --bg-card-subtle: <?= htmlspecialchars($bgCardSubtle) ?> !important;
            --border-color: <?= htmlspecialchars($borderColor) ?> !important;
        }
        .custom-brand-logo {
            max-height: 38px;
            max-width: 120px;
            object-fit: contain;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- Top Navigation Bar -->
    <header class="topnav">
        <div class="topnav-left">
            <button class="nav-hub-btn" id="navHubToggleBtn" type="button" title="Menu Utama / Hub">
                <?php if (!empty($logoImg)): ?>
                    <img src="<?= htmlspecialchars($logoImg) ?>" alt="<?= htmlspecialchars($storeTitle) ?>" class="brand-badge-img">
                <?php else: ?>
                    <span class="brand-badge"><?= htmlspecialchars($logoText) ?></span>
                <?php endif; ?>
                <div class="brand-text">
                    <h1><?= htmlspecialchars($storeTitle) ?></h1>
                    <span>🌸 <?= htmlspecialchars($tagline) ?></span>
                </div>
            </button>
        </div>
        <div class="topnav-actions">
            <button class="icon-btn" id="openWelcomeBtn" title="Baca Informasi / Aturan Order" aria-label="Info Order">
                <span style="font-size:16px;">🌷</span>
            </button>
            <button class="icon-btn" id="trackOrderBtn" title="Lacak Pesanan" aria-label="Lacak Pesanan">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.9-.9L3 20.5l1.6-4.6a8.3 8.3 0 0 1-1.1-4.4A8.5 8.5 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>
            </button>
            <button class="icon-btn" id="themeToggleBtn" title="Ganti Tema" aria-label="Ganti Tema">
                <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v2.2M12 20.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M1.5 12h2.2M20.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/></svg>
            </button>
        </div>
    </header>