<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom URL Scheme
    |--------------------------------------------------------------------------
    |
    | The scheme the landing page uses when Universal/App Links were not
    | triggered, e.g. `myapp://products/5`. Lowercase, without `://`.
    | When empty, a slug of the application name is used.
    |
    */

    'scheme' => env('DEEP_LINK_SCHEME'),

    /*
    |--------------------------------------------------------------------------
    | Claimed Paths
    |--------------------------------------------------------------------------
    |
    | Path patterns the mobile app claims on this domain. They feed the
    | `apple-app-site-association` file and must stay in sync with your
    | share routes and the Android intent filters.
    |
    */

    'paths' => [
        // '/products/*',
        // '/stores/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | iOS Application
    |--------------------------------------------------------------------------
    |
    | Both `team_id` and `bundle_id` are required before the
    | `apple-app-site-association` file is served; it returns 404 until then.
    | The App Store id for the Safari Smart App Banner is parsed from the
    | `id123…` segment of the store URL.
    |
    */

    'ios' => [
        'team_id' => env('DEEP_LINK_IOS_TEAM_ID'),
        'bundle_id' => env('DEEP_LINK_IOS_BUNDLE_ID'),
        'store_url' => env('DEEP_LINK_APP_STORE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Android Application
    |--------------------------------------------------------------------------
    |
    | `package` and at least one SHA-256 fingerprint are required before the
    | `assetlinks.json` file is served; it returns 404 until then.
    | Fingerprints may be an array, or a string separated by commas or
    | newlines. Register the Play App Signing key, not only the upload key.
    |
    */

    'android' => [
        'package' => env('DEEP_LINK_ANDROID_PACKAGE'),
        'sha256_fingerprints' => env('DEEP_LINK_ANDROID_SHA256_FINGERPRINTS'),
        'store_url' => env('DEEP_LINK_PLAY_STORE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Routes
    |--------------------------------------------------------------------------
    |
    | The package serves `/.well-known/apple-app-site-association` and
    | `/.well-known/assetlinks.json` automatically. The legacy root
    | `/apple-app-site-association` path is checked by older iOS versions.
    | Keep the middleware list minimal: no auth, no locale redirects.
    |
    */

    'verification' => [
        'routes' => true,
        'legacy_apple_route' => true,
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Landing Page
    |--------------------------------------------------------------------------
    |
    | Rendered when the OS did not hand the link to the app. `locales` limits
    | the languages negotiated from the Accept-Language header; leave it
    | empty to keep the application locale untouched. `redirect_delay` is
    | the time in milliseconds before falling back to the store.
    |
    */

    'landing' => [
        'view' => 'deep-link::landing',
        'app_name' => null,
        'redirect_delay' => 1800,
        'description_limit' => 200,
        'locales' => [],
    ],

];
