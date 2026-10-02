<?php

namespace AhmedAliraqi\LaravelDeepLink\Contracts;

interface DeepLinkable
{
    /**
     * The path of this entity on the claimed domain, e.g. `products/5`.
     * It must match one of the patterns in `deep-link.paths`.
     */
    public function deepLinkPath(): string;
}
