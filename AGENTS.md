# Kopi Kenangan Web Guidelines (PHP Native)

## Project Overview
This is a lightweight PHP Native web application without framework dependencies.
It integrates with the live Kopi Kenangan REST API (`https://tixandcoffee.developerngantuk.my.id/api`) and generates WhatsApp ordering receipts.

## Architecture
- `index.php`: Main entry point (HTML, PHP, JS).
- `config.php`: Global configuration (API endpoints, store details).
- `api/`: REST API endpoints (`outlets.php`, `menu.php`, `order.php`, `track.php`).
- `includes/`: Modular templates & `KopiKenanganApi.php` service.
- `assets/`: Vanilla CSS & Vanilla JavaScript.
- No database needed: All live data comes from external API, and cart/tracking uses localStorage & WhatsApp checkout.
- Vercel ready via `vercel.json`.
