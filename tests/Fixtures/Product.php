<?php

namespace AhmedAliraqi\LaravelDeepLink\Tests\Fixtures;

use AhmedAliraqi\LaravelDeepLink\Concerns\HasDeepLink;
use AhmedAliraqi\LaravelDeepLink\Contracts\DeepLinkable;

class Product implements DeepLinkable
{
    use HasDeepLink;

    public function __construct(public int $id) {}

    public function deepLinkPath(): string
    {
        return "products/{$this->id}";
    }
}
