<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests;

use Illuminate\Support\Facades\Route;

class LegacyAppleRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('deep-link.verification.legacy_apple_route', false);
    }

    public function test_the_legacy_root_path_can_be_disabled(): void
    {
        $this->assertTrue(Route::has('deep-link.apple'));
        $this->assertFalse(Route::has('deep-link.apple.legacy'));
    }
}
