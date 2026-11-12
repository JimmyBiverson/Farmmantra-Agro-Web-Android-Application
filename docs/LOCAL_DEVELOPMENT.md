# Local development and testing

## Branding and product-photo update — 12 November 2026

The local source includes Admin site logo, favicon and app artwork settings, plus
product-photo delivery at `/product-asset/products/...`. Product photos uploaded
through Admin no longer require a public storage symlink to display. Gallery-only
products use the first gallery photo as their thumbnail. Flutter uses the API's
delivery URLs instead of reconstructing URLs from upload paths.

For the existing server, extract `tmp/branding-deploy/farmmantra-branding-products-20261112.zip`
into the Laravel project root (beside `artisan`, not inside `public`). Back up
the corresponding files first. This is a patch for the existing installation,
not a complete fresh-install archive. Keep the server `.env`, database and
`storage/app/public` uploads. Local uploaded images are not Git files; if moving
local product records to a new installation, copy their matching uploaded files too.

After uploading, run from the server Laravel root:

```sh
php artisan optimize:clear
php artisan storage:link
php artisan queue:restart
```

Use the real HTTPS domain for `APP_URL` in the server environment. Test a new
Admin product upload and open its image on the catalog. The Android source changes
require a new APK; uploading PHP alone does not update an installed Android app:

```sh
flutter build apk --release --dart-define=API_BASE_URL=https://farmmantra.duckdns.org/api/v1
```

Configure release signing before distributing a production APK. Admin artwork
changes refresh in-app branding; changing the installed launcher icon requires
regenerating launcher assets using `app/tool/branding.ps1` and rebuilding/installing.

## Current workspace

Backend: `C:/laragon/www/Farmmatra App new/Web` (Laravel 13, PHP 8.5).
Mobile: `C:/laragon/www/Farmmatra App new/app` (Flutter 3.41.4, Dart 3.11.1).
Database: the existing `farmmantra_android_webapp` database. Connection was verified using the existing local environment credentials; the database contained no tables before migration. No legacy or production records were imported.

The local database contains sample products, two franchises, test sales, orders and payments. `DEMO_DATA=true` displays a demo marker. Demo data is for interface/workflow testing, not certified opening balances. Do not run the demo seeder against real data. It generates random activity and should not be rerun to append another sample period.

## Web and API

In PowerShell:

```powershell
Set-Location 'C:/laragon/www/Farmmatra App new/Web'
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000`. Assets are already built. After frontend source changes run `npm run build`; for development use `npm run dev` in a separate terminal. Run `php artisan storage:link` for public product/avatar uploads if needed; payment proofs intentionally remain private.

Accounts: `admin@farmmantra.co.ug`, `staff@farmmantra.co.ug`, `finance@farmmantra.co.ug`, `warehouse@farmmantra.co.ug`, `franchise@farmmantra.co.ug`. The generated local-only password is the `DEMO_PASSWORD` value in `Web/.env`. Read it locally in your editor. It is not embedded in this guide, UI or source code. All accounts were seeded with that value. Editing the environment value alone does not reset existing user passwords. Use the administrator user-management password-reset action if changing them.

All mobile APIs use `/api/v1`; compatibility `/api` endpoints call the same controllers. Inspect exact endpoints:

```powershell
php artisan route:list --path=api/v1
```

## Reverb and queues

In separate PowerShell terminals in Web:

```powershell
php artisan reverb:start --host=127.0.0.1 --port=8080
```

```powershell
php artisan queue:work --tries=3
```

Local Reverb keys are generated in the ignored `.env`. The browser subscribes to its own authenticated private user channel, then refreshes persistent notifications when an event arrives. A 15-second polling fallback also refreshes the unread badge and notification popups without reloading your form. Initial history does not generate a burst of old alerts. Read state lives in MySQL.

The Reverb process was started and the queue worker was exercised. Full two-client notification delivery still needs acceptance testing. Firebase and real SMS delivery are deferred at the user's request.

## Android emulator

```powershell
Set-Location 'C:/laragon/www/Farmmatra App new/app'
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Use `flutter devices` to select the intended device with `-d DEVICE_ID`. Android's 10.0.2.2 points to the development PC. Never use device localhost for the PC's server without ADB reverse.

## USB phone

Enable Android developer options and USB debugging, authorize this PC on the phone, then:

```powershell
adb devices
adb reverse tcp:8000 tcp:8000
Set-Location 'C:/laragon/www/Farmmatra App new/app'
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

This localhost address is valid only with the explicit ADB reverse mapping above.

## Same-Wi-Fi phone

The active Wi-Fi address observed during setup was `192.168.1.67`. Confirm with `ipconfig` because DHCP can change it. Do not use virtual adapter IPs (172.29.*, 192.168.56.* or VMware addresses).

Start Laravel using this alternate command, replacing the loopback server:

```powershell
Set-Location 'C:/laragon/www/Farmmatra App new/Web'
php artisan serve --host=0.0.0.0 --port=8000
```

```powershell
Set-Location 'C:/laragon/www/Farmmatra App new/app'
flutter run --dart-define=API_BASE_URL=http://192.168.1.67:8000/api/v1
```

If Windows Firewall prompts, allow the development server only on the private network. No firewall rule was added automatically. Check `http://192.168.1.67:8000/up` in the phone browser first. The debug Android manifest allows local HTTP; the main/release manifest does not weaken cleartext security. Minimum Android SDK is 26 (Android 8).

## Firebase and SMS later

The app tolerates absent Firebase configuration so local API workflows remain usable. Before enabling push, supply a Firebase Android application for the final package ID, its google-services.json and Google Services Gradle configuration; set the server FCM project and service-account path using the FCM config variables. Store service-account keys outside public storage and never in Flutter or Git. Complete multi-device token storage, durable retries and notification deep-link acceptance before claiming the push requirements complete.

SMS defaults to the log driver; it does not deliver texts. Set SMS_DRIVER=africastalking and the SMS_AT_USERNAME / SMS_AT_API_KEY / sender environment values when ready. Confirm recipient lists before any live bulk send.

## Tests

In Web:

```powershell
php artisan test --compact
php vendor/laravel/pint/builds/pint --format agent
```

Tests use isolated in-memory SQLite via phpunit.xml. They do not reset the local MySQL demo database. MySQL migration success does not substitute for MySQL concurrency testing.

In app:

```powershell
flutter analyze
flutter test --reporter expanded
flutter build apk --debug
```

Debug APK output, when a build succeeds: `app/build/app/outputs/flutter-apk/app-debug.apk`. Do not distribute a release signed with debug keys. Production package identity, release signing, HTTPS and store configuration remain to be completed.

## Manual acceptance scenario still required

Use a fresh test order: franchise accepts terms, places order, Sales Head approves, Admin approves, warehouse accepts and dispatches with transport details, franchise reconciles and accepts quantities, franchise sells to an assigned retailer/farmer, franchise submits a partial payment, finance verifies then accepts, and both channels show the same stock and statement balance. Also check insufficient stock, repeat actions, cross-franchise access and deactivated accounts. Physical phone and actual FCM delivery are not yet verified.

## Production readiness

Read IMPLEMENTATION.md before using real transactions. Critical remaining work includes exact-decimal financial calculations, robust idempotency/concurrent reference numbering, full accounting reconciliation, complete notification delivery lifecycle, and deployment/security acceptance. Native XLSX operations exports are now implemented. The current work is a functioning local development milestone with a modern interface, not a completed commercial launch.

### Android build result

The initial all-architecture build stalled on large Flutter engine downloads. Offline diagnostics identified the missing artifacts. An ARM64-only retry downloaded the required official engine and successfully built app-debug.apk. This verifies compilation and packaging, not installation or live operation on a phone.

No Android phone or emulator was connected when `flutter devices` was checked. Physical phone testing and Firebase delivery remain pending.

The temporary preview and Reverb servers were stopped after QA. Restart them with the commands above before opening the local dashboard.

Final phone-testing APK: `app/build/app/outputs/flutter-apk/app-debug.apk`.

Build command verified successfully:

```powershell
flutter build apk --debug --target-platform android-arm64 --dart-define=API_BASE_URL=http://192.168.1.67:8000/api/v1
```

This debug APK targets ARM64 Android phones and the PC Wi-Fi address observed during setup. Start Laravel on `0.0.0.0:8000`, keep both devices on the same Wi-Fi network, and use the seeded franchise account. If the PC address changes, rebuild with its new address. This APK is for local testing; it is not a production release or an x86 emulator build.

## Client operations setup — 11 November 2026

Start local MySQL before running these commands from Web:

```powershell
php artisan migrate --no-interaction
php artisan db:seed --class=ClientRolesSeeder --no-interaction
php artisan schedule:work
```

The role seeder always installs the new roles. Demo accounts are created only with local APP_ENV, enabled DEMO_DATA and a configured DEMO_PASSWORD in the ignored environment file. New account emails are saleshead@farmmantra.co.ug and agronomist@farmmantra.co.ug; their password comes from DEMO_PASSWORD. Existing accounts are unchanged.

Daily operational alerts are scheduled at 08:00 Africa/Kampala. In production, configure the standard once-per-minute Laravel schedule:run cron and the existing queue worker. Firebase/SMS configuration remains deferred. Admin can configure targets, field zones and draft knowledge through /workspace. Publish only client-approved crop guidance and actual product photographs.

Role-specific mobile entry/configuration files are in app/lib/screens/admin, sales_head, agronomist, warehouse, franchise and finance. Shared request, return, knowledge, target and field-report screens live in app/lib/features/operations; edit those for shared behavior.

The earlier IP addresses above describe historical builds. The API endpoint is configured in app/lib/core/constants/api_endpoints.dart and can be overridden with --dart-define=API_BASE_URL when building. The latest local ARM64 APK preserves the workspace's existing default endpoint (192.168.1.10:8000/api/v1); that local endpoint edit is intentionally not committed. Use your PC's current reachable LAN address for phone testing. Output: app/build/app/outputs/flutter-apk/app-debug.apk.

On 11 November MySQL was stopped, so rerun migrations after starting it. Local database migration status and physical Android GPS/download acceptance must be checked before demonstrating live transactions.
