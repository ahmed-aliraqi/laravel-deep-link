<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests;

class VerificationRoutesTest extends TestCase
{
    public function test_verification_files_are_missing_until_configured(): void
    {
        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
        $this->get('/apple-app-site-association')->assertNotFound();
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_apple_app_site_association_lists_the_app_and_paths(): void
    {
        $this->configureIosApp();

        $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('applinks.details.0.appIDs', ['ABCDE12345.com.example.acme'])
            ->assertJsonPath('applinks.details.0.paths', ['/products/*', '/stores/*'])
            ->assertJsonPath('applinks.details.0.components.0', ['/' => '/products/*'])
            ->assertJsonPath('webcredentials.apps', ['ABCDE12345.com.example.acme']);
    }

    public function test_slashes_are_not_escaped_in_the_apple_payload(): void
    {
        $this->configureIosApp();

        $this->assertStringContainsString(
            '"/products/*"',
            $this->get('/.well-known/apple-app-site-association')->getContent()
        );
    }

    public function test_the_legacy_root_path_serves_the_same_apple_payload(): void
    {
        $this->configureIosApp();

        $this->get('/apple-app-site-association')
            ->assertOk()
            ->assertJsonPath('applinks.details.0.appIDs', ['ABCDE12345.com.example.acme']);
    }

    public function test_asset_links_lists_the_package_and_fingerprints(): void
    {
        $this->configureAndroidApp();

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('0.relation', ['delegate_permission/common.handle_all_urls'])
            ->assertJsonPath('0.target.namespace', 'android_app')
            ->assertJsonPath('0.target.package_name', 'com.example.acme')
            ->assertJsonPath('0.target.sha256_cert_fingerprints', [self::FINGERPRINT]);
    }

    public function test_routes_have_names(): void
    {
        $this->configureIosApp();
        $this->configureAndroidApp();

        $this->assertSame(url('.well-known/apple-app-site-association'), route('deep-link.apple'));
        $this->assertSame(url('.well-known/assetlinks.json'), route('deep-link.android'));
        $this->assertSame(url('apple-app-site-association'), route('deep-link.apple.legacy'));
    }
}
