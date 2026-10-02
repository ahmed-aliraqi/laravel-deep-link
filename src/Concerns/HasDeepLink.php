<?php

namespace AhmedAliraqi\LaravelDeepLink\Concerns;

use AhmedAliraqi\LaravelDeepLink\Facades\DeepLink;

/**
 * For models implementing the DeepLinkable contract.
 */
trait HasDeepLink
{
    /** Absolute share URL, the value exposed to API clients. */
    public function deepLinkUrl(): string
    {
        return DeepLink::url($this);
    }

    /** Custom scheme URL, e.g. `myapp://products/5`. */
    public function deepLinkSchemeUrl(): string
    {
        return DeepLink::schemeUrl($this);
    }
}
