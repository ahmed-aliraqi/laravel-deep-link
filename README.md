# Laravel Deep Link

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ahmed-aliraqi/laravel-deep-link.svg?style=flat-square)](https://packagist.org/packages/ahmed-aliraqi/laravel-deep-link)
[![Tests](https://img.shields.io/github/actions/workflow/status/ahmed-aliraqi/laravel-deep-link/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ahmed-aliraqi/laravel-deep-link/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/ahmed-aliraqi/laravel-deep-link.svg?style=flat-square)](https://packagist.org/packages/ahmed-aliraqi/laravel-deep-link)
[![License](https://img.shields.io/packagist/l/ahmed-aliraqi/laravel-deep-link.svg?style=flat-square)](LICENSE.md)

One share URL per entity — `https://shop.example.com/products/42` — that:

- **opens the mobile app directly** when it is installed (iOS Universal Links / Android App Links),
- **falls back to a smart landing page** when it is not: the page tries the app once via its custom scheme (`acme://products/42`) or an Android `intent://` URL, then redirects to the App Store / Google Play,
- **renders rich link previews** (Open Graph + Twitter cards) in WhatsApp, X, Telegram, iMessage and friends,
- **serves the domain verification files** (`apple-app-site-association` and `assetlinks.json`) dynamically from your configuration — no static files to deploy.

Everything is driven by a single config file.

## Table of contents

- [How a link resolves](#how-a-link-resolves)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Configuration reference](#configuration-reference)
- [Usage](#usage)
  - [Share URLs](#share-urls)
  - [The `DeepLinkable` contract](#the-deeplinkable-contract)
  - [Landing pages](#landing-pages)
  - [The `Landing` builder API](#the-landing-builder-api)
  - [Verification endpoints](#verification-endpoints)
  - [The `DeepLink` facade API](#the-deeplink-facade-api)
- [Customizing the landing page](#customizing-the-landing-page)
- [Mobile app integration guides](#mobile-app-integration-guides)
- [Where the values come from](#where-the-values-come-from)
- [Web server requirements](#web-server-requirements)
- [Verifying your setup](#verifying-your-setup)
- [Troubleshooting](#troubleshooting)
- [Testing](#testing)

## How a link resolves

| Situation | What happens |
|---|---|
| App installed | The OS intercepts the URL and opens the app directly. The web page never loads. |
| App not installed (mobile) | The browser loads the landing page. It tries the app once (custom scheme on iOS, `intent://` on Android), then redirects to the store for that device. |
| Desktop | The landing page shows the title, image, description and both store buttons. No redirect. |
| Shared in a chat app | The crawler reads the page's Open Graph tags and renders a preview card. |
| Opened in Safari's address bar | iOS never triggers Universal Links from the address bar — the landing page covers this case too. |

The store fallback is a JavaScript redirect, never an HTTP redirect. That keeps the share URL a `200` response, which both Apple and Google require, and keeps link previews working.

## Requirements

- PHP 8.2+
- Laravel 10, 11 or 12
- HTTPS on the production domain (both platforms refuse plain HTTP)

## Installation

```bash
composer require ahmed-aliraqi/laravel-deep-link
```

Publish the config file:

```bash
php artisan vendor:publish --tag=deep-link-config
```

Optionally publish the landing view and translations:

```bash
php artisan vendor:publish --tag=deep-link-views
php artisan vendor:publish --tag=deep-link-lang
```

## Quick start

A shop that shares products and stores, with the app scheme `acme://`:

**1. Declare the paths the app claims** in `config/deep-link.php`:

```php
'scheme' => 'acme',

'paths' => [
    '/products/*',
    '/stores/*',
],
```

**2. Add the app identifiers** to `.env`:

```dotenv
DEEP_LINK_IOS_TEAM_ID=ABCDE12345
DEEP_LINK_IOS_BUNDLE_ID=com.example.acme
DEEP_LINK_APP_STORE_URL="https://apps.apple.com/app/acme/id1234567890"

DEEP_LINK_ANDROID_PACKAGE=com.example.acme
DEEP_LINK_ANDROID_SHA256_FINGERPRINTS="AB:CD:EF:...:89"
DEEP_LINK_PLAY_STORE_URL="https://play.google.com/store/apps/details?id=com.example.acme"
```

The verification files are now served automatically:

- `GET /.well-known/apple-app-site-association`
- `GET /.well-known/assetlinks.json`

**3. Register the share routes** and return a landing page from your controller:

```php
// routes/web.php
use App\Http\Controllers\ShareController;

Route::get('products/{product}', [ShareController::class, 'product'])
    ->whereNumber('product')->name('share.products.show');

Route::get('stores/{store}', [ShareController::class, 'store'])
    ->whereNumber('store')->name('share.stores.show');
```

```php
// app/Http/Controllers/ShareController.php
namespace App\Http\Controllers;

use AhmedAliraqi\LaravelDeepLink\Facades\DeepLink;
use App\Models\Product;
use App\Models\Store;

class ShareController extends Controller
{
    public function product(Product $product)
    {
        abort_unless($product->isPublished(), 404);

        return DeepLink::landing($product)
            ->title($product->name)
            ->description($product->summary)
            ->image($product->image_url);
    }

    public function store(Store $store)
    {
        return DeepLink::landing($store)
            ->title($store->name)
            ->description($store->about)
            ->image($store->logo_url);
    }
}
```

Done. `url('products/42')` is now a share link that opens the app, falls back to the stores, and previews nicely in chat apps.

## Configuration reference

| Key | Env | Default | Purpose |
|---|---|---|---|
| `scheme` | `DEEP_LINK_SCHEME` | slug of `app.name` | Custom URL scheme, lowercase, without `://`. |
| `paths` | — | `[]` | Path patterns the app claims (`/products/*`). Feeds `apple-app-site-association`. |
| `ios.team_id` | `DEEP_LINK_IOS_TEAM_ID` | `null` | Apple Developer Team ID (10 characters). |
| `ios.bundle_id` | `DEEP_LINK_IOS_BUNDLE_ID` | `null` | iOS bundle identifier. |
| `ios.store_url` | `DEEP_LINK_APP_STORE_URL` | `null` | App Store link. The Smart App Banner id is parsed from its `id123…` segment. |
| `android.package` | `DEEP_LINK_ANDROID_PACKAGE` | `null` | Android `applicationId`. |
| `android.sha256_fingerprints` | `DEEP_LINK_ANDROID_SHA256_FINGERPRINTS` | `null` | Array, or string separated by commas/newlines. Upper-cased automatically. |
| `android.store_url` | `DEEP_LINK_PLAY_STORE_URL` | `null` | Google Play link, also the `intent://` browser fallback. |
| `verification.routes` | — | `true` | Auto-register the verification routes. |
| `verification.legacy_apple_route` | — | `true` | Also serve `/apple-app-site-association` at the root (older iOS). |
| `verification.middleware` | — | `[]` | Middleware for the verification routes. Keep it free of auth and locale redirects. |
| `landing.view` | — | `deep-link::landing` | Default landing view. |
| `landing.app_name` | — | `app.name` | Name shown in the page title and OG tags. |
| `landing.redirect_delay` | — | `1800` | Milliseconds before the store fallback fires. |
| `landing.description_limit` | — | `200` | Description truncation length (after stripping HTML). |
| `landing.locales` | — | `[]` | When set (e.g. `['en', 'ar']`), the landing locale is negotiated from `Accept-Language`. |

Both verification files return `404` until their values are configured — iOS needs `team_id` + `bundle_id`, Android needs `package` + at least one fingerprint. That is intentional: an incomplete file breaks link verification silently.

## Usage

### Share URLs

```php
use AhmedAliraqi\LaravelDeepLink\Facades\DeepLink;

DeepLink::url('products/42');        // https://shop.example.com/products/42
DeepLink::url($product);             // same, from a DeepLinkable model
DeepLink::schemeUrl($product);       // acme://products/42
DeepLink::intentUrl($product);       // intent://products/42#Intent;scheme=acme;package=…;end (null until the package is set)
```

`DeepLink::url()` uses Laravel's `url()` helper, so **`APP_URL` must be the exact production domain** (`https://shop.example.com`). A `www.` or `http://` mismatch makes every shared link skip the app.

### The `DeepLinkable` contract

Implement the contract on anything shareable and use the trait for convenience methods:

```php
use AhmedAliraqi\LaravelDeepLink\Concerns\HasDeepLink;
use AhmedAliraqi\LaravelDeepLink\Contracts\DeepLinkable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements DeepLinkable
{
    use HasDeepLink;

    public function deepLinkPath(): string
    {
        return "products/{$this->id}";
    }
}
```

Expose the link in your API resources so clients never build URLs themselves:

```php
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // ...
            'share_url' => $this->deepLinkUrl(),
        ];
    }
}
```

The path returned by `deepLinkPath()` must match one of the patterns in `deep-link.paths` and one of your share routes — these three always move together.

### Landing pages

`DeepLink::landing()` returns a `Responsable`, so you can return it straight from a controller. It renders the landing view with platform detection, Open Graph tags, the Safari Smart App Banner, and the open-app-or-store script.

```php
return DeepLink::landing($product)          // or DeepLink::landing('products/42')
    ->title($product->name)
    ->description($product->summary)        // HTML stripped, truncated to 200 chars
    ->image($product->image_url);           // absolute URL
```

Guard visibility in the controller before returning the page — unpublished content should `404`:

```php
abort_unless($product->isPublished(), 404);
```

### The `Landing` builder API

| Method | Purpose |
|---|---|
| `title(?string)` | Page `<h1>`, `<title>` and `og:title`. Falls back to the app name. |
| `description(?string)` | Meta description and `og:description`. HTML is stripped, text truncated. |
| `image(?string)` | Preview image and `og:image`. |
| `url(?string)` | Canonical URL override. Defaults to the subject's share URL. |
| `view(string)` | Render a different Blade view for this landing only. |
| `with(array)` | Extra data passed to the view (useful with custom views). |
| `render(?Request)` | Returns the `View` directly if you need it outside a controller response. |

### Verification endpoints

| Path | Route name | Returns |
|---|---|---|
| `GET /.well-known/apple-app-site-association` | `deep-link.apple` | iOS Universal Links JSON. `404` until `team_id` and `bundle_id` are set. |
| `GET /apple-app-site-association` | `deep-link.apple.legacy` | The same file at the root path, checked by older iOS versions. |
| `GET /.well-known/assetlinks.json` | `deep-link.android` | Android App Links JSON. `404` until the package and a fingerprint are set. |

Generated with the example values:

```json
{
  "applinks": {
    "apps": [],
    "details": [{
      "appID": "ABCDE12345.com.example.acme",
      "appIDs": ["ABCDE12345.com.example.acme"],
      "paths": ["/products/*", "/stores/*"],
      "components": [{ "/": "/products/*" }, { "/": "/stores/*" }]
    }]
  },
  "webcredentials": { "apps": ["ABCDE12345.com.example.acme"] }
}
```

`appID` + `paths` is the legacy format for iOS 12 and older; `appIDs` + `components` is the current one. `webcredentials` lets iOS offer the domain's saved passwords inside the app.

```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.example.acme",
    "sha256_cert_fingerprints": ["AB:CD:…:89"]
  }
}]
```

### The `DeepLink` facade API

| Method | Returns |
|---|---|
| `url($subject)` | Absolute share URL. |
| `schemeUrl($subject)` | `acme://products/42`. |
| `intentUrl($subject)` | Android `intent://` URL, `null` until the package is configured. |
| `storeUrl(Platform\|string $platform)` | Store link for `ios` / `android`, `null` otherwise. |
| `appStoreId()` | Numeric App Store id parsed from the iOS store URL. |
| `detectPlatform(?string $userAgent)` | `Platform::Ios` / `Platform::Android` / `Platform::Desktop`. |
| `scheme()` / `paths()` / `androidPackage()` / `androidFingerprints()` | Normalized config values. |
| `appleAppSiteAssociation()` / `assetLinks()` | Verification payloads, `null` until configured. |
| `landing($subject)` | The `Landing` builder. |

`$subject` is always a path string or a `DeepLinkable`.

## Customizing the landing page

Publish the view and edit freely — it is a single self-contained Blade file with no asset pipeline:

```bash
php artisan vendor:publish --tag=deep-link-views
# → resources/views/vendor/deep-link/landing.blade.php
```

The view receives: `meta` (`title`, `description`, `image`, `url`), `appName`, `platform` (a `Platform` enum: `$platform->isIos()`, `isAndroid()`, `isDesktop()`, `isMobile()`), `schemeUrl`, `intentUrl`, `target` (the URL the page should try first), `storeUrl` (for the visitor's platform), `appStoreUrl`, `playStoreUrl`, `appStoreId` and `redirectDelay`.

Or use a different view for one landing only:

```php
return DeepLink::landing($product)
    ->view('share.product')
    ->with(['product' => $product]);
```

Translations ship in English and Arabic. Publish to override or add languages:

```bash
php artisan vendor:publish --tag=deep-link-lang
# → lang/vendor/deep-link/{en,ar}/landing.php
```

To localize the landing page from the visitor's `Accept-Language` header, list the supported locales:

```php
'landing' => [
    'locales' => ['en', 'ar'],
],
```

## Mobile app integration guides

The backend half is only half of the story — the app must claim the domain and handle incoming URLs:

- [Flutter](docs/flutter.md) — `app_links` + `share_plus`, manifest and entitlements, a sealed-class URI parser, router wiring.
- [Android (native)](docs/android.md) — verified intent filters, handling `onCreate`/`onNewIntent`, sharing, `adb` verification commands.
- [iOS (native)](docs/ios.md) — Associated Domains, SwiftUI `onOpenURL` and UIKit scene delegate handling, Smart App Banner notes.
- [React Native](docs/react-native.md) — `Linking` API and React Navigation `linking` config.

All four guides use the same example app (`Acme`, scheme `acme://`, paths `/products/*` and `/stores/*`), so they can be handed to a mobile team as-is and adapted.

## Where the values come from

| Value | Where to find it |
|---|---|
| Apple Team ID | developer.apple.com → Membership details. |
| iOS Bundle ID | Xcode → target → Signing & Capabilities. |
| Android package name | `applicationId` in `android/app/build.gradle`. |
| SHA-256 fingerprints | Play Console → App integrity → **App signing key certificate** — plus the upload and debug keys. |
| Store URLs | The public App Store / Google Play listing links. |

> **Warning — Play App Signing.** Builds installed from Google Play are signed with Google's key, not your upload key. If only the upload key fingerprint is registered, links work in local builds but fail for real users. Register both.

Print the debug key fingerprint:

```bash
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey \
  -storepass android -keypass android | grep SHA256
```

## Web server requirements

- HTTPS with a valid certificate on the exact domain in `APP_URL`.
- **No redirects on the verification paths.** Apple and Google do not follow them: no HTTP→HTTPS hop, no `www` hop, no locale or trailing-slash redirect.
- The paths must reach Laravel. Many nginx configs deny dotfiles with `location ~ /\. { deny all; }`, which also blocks `/.well-known/`.
- No bot challenge (Cloudflare "Under Attack" mode, WAF JS challenge) in front of these paths.
- No static files with the same names under `public/` — the web server would serve them instead of the dynamic response.

```nginx
# Must come before any rule that denies dotfiles
location ^~ /.well-known/ {
    try_files $uri /index.php?$query_string;
}

location = /apple-app-site-association {
    try_files $uri /index.php?$query_string;
}

# Hide other dotfiles as before
location ~ /\.(?!well-known) {
    deny all;
}
```

## Verifying your setup

```bash
# Expect 200, application/json, and no Location header
curl -sI https://shop.example.com/.well-known/apple-app-site-association
curl -sI https://shop.example.com/.well-known/assetlinks.json

# What Apple's CDN serves to devices (can lag behind the origin by up to a day)
curl -s https://app-site-association.cdn-apple.com/a/v1/shop.example.com

# Google's view of the Android statement
curl -s "https://digitalassetlinks.googleapis.com/v1/statements:list?source.web.site=https://shop.example.com&relation=delegate_permission/common.handle_all_urls"

# Landing page as an iPhone — should contain acme://products/42
curl -s -A "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)" \
  https://shop.example.com/products/42 | grep acme://
```

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Verification file returns `404` | Config incomplete (iOS needs Team ID + Bundle ID, Android needs package + fingerprint), or nginx denies `/.well-known/`. |
| Android shows the "Open with" chooser | Verification failed. Check the fingerprint matches the key that signed the installed build, then `adb shell pm verify-app-links --re-verify com.example.acme`. |
| Works in debug, not from Play | Only the upload key is registered. Add the Play App Signing fingerprint. |
| iOS always opens Safari | Apple's CDN still has an old or missing file — check the CDN URL above and reinstall the app. Links typed into Safari's address bar never open the app. |
| Share URL has the wrong host | `APP_URL` mismatch, or a cached config. Run `php artisan config:clear`. |
| No preview image in chat apps | The image URL is missing or not publicly reachable. Chat apps also cache previews per URL. |

## Testing

```bash
composer test
composer lint
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE.md](LICENSE.md).
