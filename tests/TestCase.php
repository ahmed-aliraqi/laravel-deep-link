<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests;

use AhmedAliraqi\LaravelDeepLink\DeepLinkServiceProvider;
use AhmedAliraqi\LaravelDeepLink\Facades\DeepLink;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const FINGERPRINT = 'AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89';

    protected const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    protected const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36';

    protected const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

    protected function getPackageProviders($app): array
    {
        return [DeepLinkServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['DeepLink' => DeepLink::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Acme');
        $app['config']->set('deep-link.scheme', 'acme');
        $app['config']->set('deep-link.paths', ['/products/*', '/stores/*']);
    }

    protected function configureStores(): void
    {
        config()->set('deep-link.ios.store_url', 'https://apps.apple.com/app/acme/id1234567890');
        config()->set('deep-link.android.store_url', 'https://play.google.com/store/apps/details?id=com.example.acme');
    }

    protected function configureIosApp(): void
    {
        config()->set('deep-link.ios.team_id', 'ABCDE12345');
        config()->set('deep-link.ios.bundle_id', 'com.example.acme');
    }

    protected function configureAndroidApp(): void
    {
        config()->set('deep-link.android.package', 'com.example.acme');
        config()->set('deep-link.android.sha256_fingerprints', strtolower(self::FINGERPRINT));
    }
}
