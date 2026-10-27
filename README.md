# Agro App

Agro App is a smart agriculture ecosystem that connects farmers, franchise operators, staff, and finance teams through a mobile app and a web admin dashboard. The platform helps manage products, orders, payments, notifications, inventory, customer relations, and support in a unified workflow.

## Project Overview

This repository contains two main applications:

- Mobile app: Flutter app for field operations and user-facing workflows
- Web app: Laravel application for admin, franchise, and management dashboards

## Highlights

- Product catalog and inventory management
- Order creation and tracking workflows
- Payment and finance dashboards
- Notification and real-time communication
- Franchise and staff role-based access
- Support tickets and chat interface
- Modern UI designed for agricultural operations

## Architecture

- Mobile frontend: Flutter
- Web backend/admin: Laravel
- Database: MySQL
- Real-time features: Laravel broadcasting / Reverb / notifications
- API layer: REST-based service integration

## Screenshots

### App preview

<div align="center">
  <img src="./agro_mobile/assets/images/banner_1.png" width="420" alt="Agro App mobile banner 1" />
  <img src="./agro_mobile/assets/images/banner_2.png" width="420" alt="Agro App mobile banner 2" />
</div>

### Core system screens

<div align="center">
  <img src="./screenshots/dashboard.png" width="900" alt="Dashboard overview" /><br /><br />
  <img src="./screenshots/product_catalog.png" width="900" alt="Product catalog" /><br /><br />
  <img src="./screenshots/order_workflow.png" width="900" alt="Order workflow" /><br /><br />
  <img src="./screenshots/payments_finance.png" width="900" alt="Payments and finance dashboard" /><br /><br />
  <img src="./screenshots/admin_panel.png" width="900" alt="Admin control panel" />
</div>

## Repository Structure

```text
Agro_App/
├── agro_mobile/      # Flutter mobile application
├── agro_web/         # Laravel web backend and admin app
├── farmmantra.sql    # Project database dump
├── .gitignore
├── README.md
└── project scripts / setup helpers
```

## Getting Started

### Mobile app

```bash
cd agro_mobile
flutter pub get
flutter run
```

### Web app

```bash
cd agro_web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Upgrading an existing installation (October 2026 client update)

```bash
git pull
cd agro_web
composer install
php artisan migrate            # additive: regions, all Uganda districts, ledger, signatures, Warehouse role…
php artisan ledger:sync-opening-balances --dry-run   # check statements vs balances
php artisan ledger:sync-opening-balances             # only if differences are reported
npm install && npm run build   # admin styles (Tailwind via Vite)

cd ../agro_mobile
flutter pub get                # adds url_launcher (printing)
flutter analyze && flutter test
flutter build apk --release
```

Back up the production database before `php artisan migrate`. The migration only adds tables and columns; it backfills each franchise statement from confirmed receipts and accepted payments, with any remaining difference posted as an opening balance.

**New configuration (`agro_web/.env`)**

| Key | Purpose |
|---|---|
| `SMS_DRIVER` | `log` (default — SMS are recorded, not sent) or `africastalking` |
| `SMS_AT_USERNAME`, `SMS_AT_API_KEY`, `SMS_SENDER_ID` | Africa's Talking account for bulk SMS |

**New roles and first steps**

1. Create **Warehouse** users (Admin → Users). The warehouse accepts, declines, adjusts and dispatches orders; Farmmantra (sales) staff now monitor and raise support tickets.
2. Admin → Regions & Districts: create business regions (e.g. *Gulu Region*), tick their districts and the franchise serving them.
3. Admin → Products: set the **Franchise**, **Retail** and **Farmer** price of each SKU.
4. Admin → Terms & Operations: review the franchise terms (partners accept them at first login) and the dispatch target (default 15 days).

Demo logins after `php artisan db:seed` (password `password123`): admin@, staff@, warehouse@, finance@, franchise@farmmantra.co.ug.

## Testing

```bash
cd agro_web && php artisan test        # 38 tests: workflows, client requirements, every page × every role
cd agro_mobile && flutter test         # includes test/client_update_test.dart
```

See `REQUIREMENTS_MATRIX.md` for the requirement-by-requirement status.

## Notes

This project is designed to showcase a professional agri-business workflow with clear roles, data visibility, and operational dashboards. The design is intended to feel modern and production-friendly for agricultural commerce operations.

## License

This project is for demonstration and portfolio use. Please review and adapt licensing terms according to your production requirements.
