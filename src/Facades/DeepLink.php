<?php

namespace AhmedAliraqi\LaravelDeepLink\Facades;

use AhmedAliraqi\LaravelDeepLink\Contracts\DeepLinkable;
use AhmedAliraqi\LaravelDeepLink\DeepLinkManager;
use AhmedAliraqi\LaravelDeepLink\Landing;
use AhmedAliraqi\LaravelDeepLink\Platform;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed config(string $key, mixed $default = null)
 * @method static string path(DeepLinkable|string $subject)
 * @method static string url(DeepLinkable|string $subject)
 * @method static string schemeUrl(DeepLinkable|string $subject)
 * @method static string|null intentUrl(DeepLinkable|string $subject)
 * @method static string|null storeUrl(Platform|string $platform)
 * @method static string|null appStoreId()
 * @method static Platform detectPlatform(?string $userAgent)
 * @method static string scheme()
 * @method static list<string> paths()
 * @method static string|null androidPackage()
 * @method static list<string> androidFingerprints()
 * @method static array|null appleAppSiteAssociation()
 * @method static array|null assetLinks()
 * @method static Landing landing(DeepLinkable|string $subject)
 *
 * @see DeepLinkManager
 */
class DeepLink extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DeepLinkManager::class;
    }
}
