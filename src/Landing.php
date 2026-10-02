<?php

namespace AhmedAliraqi\LaravelDeepLink;

use AhmedAliraqi\LaravelDeepLink\Contracts\DeepLinkable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Renders the page shown when the OS did not hand the link to the app:
 * it tries the app once, then falls back to the store for the visitor's device.
 */
class Landing implements Responsable
{
    protected ?string $title = null;

    protected ?string $description = null;

    protected ?string $image = null;

    protected ?string $canonicalUrl = null;

    protected ?string $view = null;

    protected array $with = [];

    public function __construct(
        protected DeepLinkManager $manager,
        protected DeepLinkable|string $subject,
    ) {}

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** HTML is stripped and the text truncated to `landing.description_limit`. */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Absolute image URL used for the preview and the Open Graph tags. */
    public function image(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /** Canonical URL override; defaults to the subject's share URL. */
    public function url(?string $url): static
    {
        $this->canonicalUrl = $url;

        return $this;
    }

    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /** Extra data passed to the view, e.g. for a published/custom template. */
    public function with(array $data): static
    {
        $this->with = [...$this->with, ...$data];

        return $this;
    }

    public function toResponse($request)
    {
        return response($this->render($request));
    }

    public function render(?Request $request = null): View
    {
        $request ??= request();

        $this->applyLocale($request);

        $platform = $this->manager->detectPlatform($request->userAgent());
        $schemeUrl = $this->manager->schemeUrl($this->subject);
        $intentUrl = $this->manager->intentUrl($this->subject);
        $limit = (int) $this->manager->config('landing.description_limit', 200);

        return view($this->view ?? $this->manager->config('landing.view', 'deep-link::landing'), [
            'meta' => [
                'title' => $this->title,
                'description' => $this->description === null
                    ? null
                    : Str::limit(trim(strip_tags($this->description)), $limit),
                'image' => $this->image,
                'url' => $this->canonicalUrl ?? $this->manager->url($this->subject),
            ],
            'appName' => $this->manager->config('landing.app_name') ?: config('app.name'),
            'platform' => $platform,
            'schemeUrl' => $schemeUrl,
            'intentUrl' => $intentUrl,
            'target' => $platform->isAndroid() && $intentUrl ? $intentUrl : $schemeUrl,
            'storeUrl' => $this->manager->storeUrl($platform),
            'appStoreUrl' => $this->manager->storeUrl(Platform::Ios),
            'playStoreUrl' => $this->manager->storeUrl(Platform::Android),
            'appStoreId' => $this->manager->appStoreId(),
            'redirectDelay' => (int) $this->manager->config('landing.redirect_delay', 1800),
            ...$this->with,
        ]);
    }

    protected function applyLocale(Request $request): void
    {
        $locales = (array) $this->manager->config('landing.locales', []);

        if ($locales !== []) {
            app()->setLocale($request->getPreferredLanguage($locales) ?? config('app.locale'));
        }
    }
}
