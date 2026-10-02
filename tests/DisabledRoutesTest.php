<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests;

use Illuminate\Support\Facades\Route;

class DisabledRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('deep-link.verification.routes', false);
    }

    public function test_verification_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('deep-link.apple'));
        $this->assertFalse(Route::has('deep-link.android'));
        $this->assertFalse(Route::has('deep-link.apple.legacy'));
    }
}
