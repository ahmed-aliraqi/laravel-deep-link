<?php

namespace AhmedAliraqi\LaravelDeepLink;

use AhmedAliraqi\LaravelDeepLink\Contracts\DeepLinkable;
use Illuminate\Support\Str;

/**
 * Builds shareable links that open the mobile app (Universal Links / App Links),
 * plus the verification files the OS fetches to trust the domain.
 */
class DeepLinkManager
{
    public function config(string $key, mixed $default = null): mixed
    {
        return config("deep-link.{$key}") ?? $default;
    }

    public function path(DeepLinkable|string $subject): string
    {
        $path = $subject instanceof DeepLinkable ? $subject->deepLinkPath() : $subject;

        return ltrim($path, '/');
    }

    /** Absolute share URL — the one users share and the OS intercepts. */
    public function url(DeepLinkable|string $subject): string
    {
        return url($this->path($subject));
    }

    /** Custom scheme link used when Universal/App Links are not triggered, e.g. `myapp://products/5`. */
    public function schemeUrl(DeepLinkable|string $subject): string
    {
        return $this->scheme().'://'.$this->path($subject);
    }

    /**
     * Android Chrome intent: opens the app when installed, otherwise Chrome
     * navigates to the Play Store fallback by itself.
     */
    public function intentUrl(DeepLinkable|string $subject): ?string
    {
        if (! $package = $this->androidPackage()) {
            return null;
        }

        $fragment = "Intent;scheme={$this->scheme()};package={$package};";

        if ($store = $this->storeUrl(Platform::Android)) {
            $fragment .= 'S.browser_fallback_url='.rawurlencode($store).';';
        }

        return 'intent://'.$this->path($subject).'#'.$fragment.'end';
    }

    public function storeUrl(Platform|string $platform): ?string
    {
        $platform = $platform instanceof Platform ? $platform : Platform::from($platform);

        return match ($platform) {
            Platform::Ios => $this->config('ios.store_url') ?: null,
            Platform::Android => $this->config('android.store_url') ?: null,
            Platform::Desktop => null,
        };
    }

    /** Numeric App Store id parsed from the store link, used by the Safari Smart App Banner. */
    public function appStoreId(): ?string
    {
        return preg_match('/id(\d+)/', (string) $this->storeUrl(Platform::Ios), $matches)
            ? $matches[1]
            : null;
    }

    public function detectPlatform(?string $userAgent): Platform
    {
        return Platform::fromUserAgent($userAgent);
    }

    public function scheme(): string
    {
        return $this->config('scheme')
            ?: (Str::slug((string) config('app.name'), '') ?: 'app');
    }

    /**
     * Path patterns the app claims, each normalized to a leading slash.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return array_values(array_map(
            fn ($path) => '/'.ltrim((string) $path, '/'),
            (array) $this->config('paths', [])
        ));
    }

    public function androidPackage(): ?string
    {
        return $this->config('android.package') ?: null;
    }

    /**
     * Uppercased SHA-256 fingerprints, from an array or a comma/newline separated string.
     *
     * @return list<string>
     */
    public function androidFingerprints(): array
    {
        $raw = $this->config('android.sha256_fingerprints');

        $values = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string) $raw);

        return array_values(array_filter(array_map(
            fn ($value) => strtoupper(trim((string) $value)),
            $values
        )));
    }

    /** `apple-app-site-association` payload, or null when the iOS app is not configured. */
    public function appleAppSiteAssociation(): ?array
    {
        $teamId = $this->config('ios.team_id');
        $bundleId = $this->config('ios.bundle_id');

        if (! $teamId || ! $bundleId) {
            return null;
        }

        $appId = "{$teamId}.{$bundleId}";
        $paths = $this->paths();

        return [
            'applinks' => [
                // `appID` + `paths` is kept for iOS 12 and older.
                'apps' => [],
                'details' => [[
                    'appID' => $appId,
                    'appIDs' => [$appId],
                    'paths' => $paths,
                    'components' => array_map(fn ($path) => ['/' => $path], $paths),
                ]],
            ],
            'webcredentials' => ['apps' => [$appId]],
        ];
    }

    /** `assetlinks.json` payload, or null when the Android app is not configured. */
    public function assetLinks(): ?array
    {
        $fingerprints = $this->androidFingerprints();

        if (! ($package = $this->androidPackage()) || $fingerprints === []) {
            return null;
        }

        return [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $package,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]];
    }

    /** Fluent builder for the landing page shown when the OS did not open the app. */
    public function landing(DeepLinkable|string $subject): Landing
    {
        return new Landing($this, $subject);
    }
}
