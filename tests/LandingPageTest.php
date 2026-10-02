<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests;

use AhmedAliraqi\LaravelDeepLink\Facades\DeepLink;
use AhmedAliraqi\LaravelDeepLink\Tests\Fixtures\Product;

class LandingPageTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('products/{id}', function (int $id) {
            return DeepLink::landing("products/{$id}")
                ->title('Ceramic Mug')
                ->description('<p>A handmade <strong>ceramic</strong> mug.</p>')
                ->image('https://cdn.example.com/mug.jpg');
        })->whereNumber('id')->name('share.products.show');

        $router->get('stores/{id}', function (int $id) {
            return DeepLink::landing(new Product($id))->title('From a model');
        });

        $router->get('bare', fn () => DeepLink::landing('products/1'));

        $router->get('custom', function () {
            return DeepLink::landing('products/1')
                ->view('custom-landing')
                ->with(['badge' => 'Limited offer']);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureStores();

        view()->addLocation(__DIR__.'/Fixtures/views');
    }

    // ── Meta tags ─────────────────────────────────────────────────────────────

    public function test_the_landing_page_has_open_graph_and_twitter_tags(): void
    {
        $this->get('/products/5')
            ->assertOk()
            ->assertSee('<title>Ceramic Mug | Acme</title>', false)
            ->assertSee('<meta property="og:title" content="Ceramic Mug">', false)
            ->assertSee('<meta property="og:site_name" content="Acme">', false)
            ->assertSee('<meta property="og:url" content="'.url('products/5').'">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.com/mug.jpg">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<link rel="canonical" href="'.url('products/5').'">', false);
    }

    public function test_the_description_is_stripped_of_html_and_truncated(): void
    {
        $this->get('/products/5')
            ->assertSee('<meta property="og:description" content="A handmade ceramic mug.">', false)
            ->assertDontSee('<strong>', false);
    }

    public function test_the_smart_app_banner_is_present_when_the_store_id_is_known(): void
    {
        $this->get('/products/5')
            ->assertSee('<meta name="apple-itunes-app" content="app-id=1234567890, app-argument='.url('products/5').'">', false);
    }

    public function test_the_page_falls_back_to_the_app_name_without_a_title(): void
    {
        $this->get('/bare')
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Acme">', false);
    }

    // ── Platform behaviour ────────────────────────────────────────────────────

    public function test_an_iphone_tries_the_scheme_then_falls_back_to_the_app_store(): void
    {
        $this->withHeader('User-Agent', self::IPHONE_UA)
            ->get('/products/5')
            ->assertOk()
            ->assertSee(json_encode('acme://products/5'), false)
            ->assertSee(json_encode('https://apps.apple.com/app/acme/id1234567890'), false);
    }

    public function test_android_uses_an_intent_with_a_play_store_fallback(): void
    {
        $this->configureAndroidApp();

        $response = $this->withHeader('User-Agent', self::ANDROID_UA)
            ->get('/products/5')
            ->assertOk();

        $this->assertStringContainsString(
            'intent://products/5#Intent;scheme=acme;package=com.example.acme;S.browser_fallback_url='
                .rawurlencode('https://play.google.com/store/apps/details?id=com.example.acme'),
            html_entity_decode($response->getContent())
        );
    }

    public function test_android_falls_back_to_the_scheme_without_a_package(): void
    {
        $this->withHeader('User-Agent', self::ANDROID_UA)
            ->get('/products/5')
            ->assertOk()
            ->assertSee(json_encode('acme://products/5'), false);
    }

    public function test_desktop_shows_both_store_links_without_redirecting(): void
    {
        $this->withHeader('User-Agent', self::DESKTOP_UA)
            ->get('/products/5')
            ->assertOk()
            ->assertSee('https://apps.apple.com/app/acme/id1234567890', false)
            ->assertSee('https://play.google.com/store/apps/details?id=com.example.acme', false)
            ->assertDontSee('<script>', false);
    }

    public function test_the_redirect_delay_is_configurable(): void
    {
        config()->set('deep-link.landing.redirect_delay', 2500);

        $this->withHeader('User-Agent', self::IPHONE_UA)
            ->get('/products/5')
            ->assertSee('}, 2500);', false);
    }

    // ── Customization ─────────────────────────────────────────────────────────

    public function test_a_deep_linkable_model_feeds_the_landing_path(): void
    {
        $this->withHeader('User-Agent', self::IPHONE_UA)
            ->get('/stores/7')
            ->assertOk()
            ->assertSee(json_encode('acme://products/7'), false)
            ->assertSee('<link rel="canonical" href="'.url('products/7').'">', false);
    }

    public function test_a_custom_view_receives_the_extra_data(): void
    {
        $this->get('/custom')
            ->assertOk()
            ->assertSee('Custom view for products/1')
            ->assertSee('Limited offer');
    }

    public function test_the_locale_is_negotiated_when_locales_are_configured(): void
    {
        config()->set('deep-link.landing.locales', ['en', 'ar']);

        $this->withHeaders(['User-Agent' => self::IPHONE_UA, 'Accept-Language' => 'ar,en;q=0.8'])
            ->get('/products/5')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('افتح في التطبيق');
    }
}
