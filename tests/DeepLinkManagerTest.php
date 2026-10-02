<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests;

use AhmedAliraqi\LaravelDeepLink\DeepLinkManager;
use AhmedAliraqi\LaravelDeepLink\Facades\DeepLink;
use AhmedAliraqi\LaravelDeepLink\Platform;
use AhmedAliraqi\LaravelDeepLink\Tests\Fixtures\Product;

class DeepLinkManagerTest extends TestCase
{
    // ── URLs ──────────────────────────────────────────────────────────────────

    public function test_it_builds_the_share_url_from_a_path(): void
    {
        $this->assertSame(url('products/5'), DeepLink::url('products/5'));
        $this->assertSame(url('products/5'), DeepLink::url('/products/5'));
    }

    public function test_it_builds_the_share_url_from_a_deep_linkable(): void
    {
        $product = new Product(5);

        $this->assertSame(url('products/5'), DeepLink::url($product));
        $this->assertSame(url('products/5'), $product->deepLinkUrl());
    }

    public function test_it_builds_the_scheme_url(): void
    {
        $this->assertSame('acme://products/5', DeepLink::schemeUrl('products/5'));
        $this->assertSame('acme://products/5', (new Product(5))->deepLinkSchemeUrl());
    }

    public function test_the_scheme_falls_back_to_the_app_name_slug(): void
    {
        config()->set('deep-link.scheme', null);
        config()->set('app.name', 'My Shop');

        $this->assertSame('myshop', DeepLink::scheme());
    }

    public function test_the_intent_url_requires_an_android_package(): void
    {
        $this->assertNull(DeepLink::intentUrl('products/5'));
    }

    public function test_the_intent_url_contains_the_scheme_package_and_store_fallback(): void
    {
        $this->configureAndroidApp();
        $this->configureStores();

        $this->assertSame(
            'intent://products/5#Intent;scheme=acme;package=com.example.acme;'
                .'S.browser_fallback_url='.rawurlencode('https://play.google.com/store/apps/details?id=com.example.acme').';end',
            DeepLink::intentUrl('products/5')
        );
    }

    public function test_the_intent_url_omits_the_fallback_when_no_store_is_configured(): void
    {
        $this->configureAndroidApp();

        $this->assertSame(
            'intent://products/5#Intent;scheme=acme;package=com.example.acme;end',
            DeepLink::intentUrl('products/5')
        );
    }

    // ── Stores ────────────────────────────────────────────────────────────────

    public function test_store_urls_per_platform(): void
    {
        $this->configureStores();

        $this->assertSame('https://apps.apple.com/app/acme/id1234567890', DeepLink::storeUrl(Platform::Ios));
        $this->assertSame('https://play.google.com/store/apps/details?id=com.example.acme', DeepLink::storeUrl('android'));
        $this->assertNull(DeepLink::storeUrl(Platform::Desktop));
    }

    public function test_the_app_store_id_is_parsed_from_the_store_url(): void
    {
        $this->assertNull(DeepLink::appStoreId());

        $this->configureStores();

        $this->assertSame('1234567890', DeepLink::appStoreId());
    }

    // ── Platform detection ────────────────────────────────────────────────────

    public function test_it_detects_the_platform_from_the_user_agent(): void
    {
        $this->assertSame(Platform::Ios, DeepLink::detectPlatform(self::IPHONE_UA));
        $this->assertSame(Platform::Android, DeepLink::detectPlatform(self::ANDROID_UA));
        $this->assertSame(Platform::Desktop, DeepLink::detectPlatform(self::DESKTOP_UA));
        $this->assertSame(Platform::Desktop, DeepLink::detectPlatform(null));
    }

    // ── Config parsing ────────────────────────────────────────────────────────

    public function test_paths_are_normalized_to_a_leading_slash(): void
    {
        config()->set('deep-link.paths', ['products/*', '/stores/*']);

        $this->assertSame(['/products/*', '/stores/*'], DeepLink::paths());
    }

    public function test_fingerprints_accept_strings_and_arrays(): void
    {
        config()->set('deep-link.android.sha256_fingerprints', strtolower(self::FINGERPRINT).",\n ".self::FINGERPRINT."\n");
        $this->assertSame([self::FINGERPRINT, self::FINGERPRINT], DeepLink::androidFingerprints());

        config()->set('deep-link.android.sha256_fingerprints', [strtolower(self::FINGERPRINT)]);
        $this->assertSame([self::FINGERPRINT], DeepLink::androidFingerprints());

        config()->set('deep-link.android.sha256_fingerprints', null);
        $this->assertSame([], DeepLink::androidFingerprints());
    }

    // ── Verification payloads ─────────────────────────────────────────────────

    public function test_the_apple_payload_is_null_until_configured(): void
    {
        $this->assertNull(DeepLink::appleAppSiteAssociation());

        config()->set('deep-link.ios.team_id', 'ABCDE12345');
        $this->assertNull(DeepLink::appleAppSiteAssociation());
    }

    public function test_the_apple_payload_lists_the_app_in_both_formats(): void
    {
        $this->configureIosApp();

        $payload = DeepLink::appleAppSiteAssociation();

        $this->assertSame('ABCDE12345.com.example.acme', $payload['applinks']['details'][0]['appID']);
        $this->assertSame(['ABCDE12345.com.example.acme'], $payload['applinks']['details'][0]['appIDs']);
        $this->assertSame(['/products/*', '/stores/*'], $payload['applinks']['details'][0]['paths']);
        $this->assertSame([['/' => '/products/*'], ['/' => '/stores/*']], $payload['applinks']['details'][0]['components']);
        $this->assertSame(['apps' => ['ABCDE12345.com.example.acme']], $payload['webcredentials']);
    }

    public function test_the_asset_links_payload_is_null_until_configured(): void
    {
        $this->assertNull(DeepLink::assetLinks());

        config()->set('deep-link.android.package', 'com.example.acme');
        $this->assertNull(DeepLink::assetLinks());
    }

    public function test_the_asset_links_payload_lists_the_package_and_fingerprints(): void
    {
        $this->configureAndroidApp();

        $payload = DeepLink::assetLinks();

        $this->assertSame(['delegate_permission/common.handle_all_urls'], $payload[0]['relation']);
        $this->assertSame('com.example.acme', $payload[0]['target']['package_name']);
        $this->assertSame([self::FINGERPRINT], $payload[0]['target']['sha256_cert_fingerprints']);
    }

    public function test_the_manager_is_a_singleton_with_an_alias(): void
    {
        $this->assertSame(app(DeepLinkManager::class), app('deep-link'));
    }
}
