<?php

namespace AhmedAliraqi\LaravelDeepLink;

enum Platform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Desktop = 'desktop';

    public static function fromUserAgent(?string $userAgent): self
    {
        $userAgent = (string) $userAgent;

        return match (true) {
            (bool) preg_match('/android/i', $userAgent) => self::Android,
            (bool) preg_match('/iphone|ipad|ipod/i', $userAgent) => self::Ios,
            default => self::Desktop,
        };
    }

    public function isIos(): bool
    {
        return $this === self::Ios;
    }

    public function isAndroid(): bool
    {
        return $this === self::Android;
    }

    public function isDesktop(): bool
    {
        return $this === self::Desktop;
    }

    public function isMobile(): bool
    {
        return ! $this->isDesktop();
    }
}
