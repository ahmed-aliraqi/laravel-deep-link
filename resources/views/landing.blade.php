<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['title'] ? $meta['title'].' | ' : '' }}{{ $appName }}</title>
    @if($meta['description'])
        <meta name="description" content="{{ $meta['description'] }}">
    @endif
    <link rel="canonical" href="{{ $meta['url'] }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:title" content="{{ $meta['title'] ?? $appName }}">
    @if($meta['description'])
        <meta property="og:description" content="{{ $meta['description'] }}">
    @endif
    <meta property="og:url" content="{{ $meta['url'] }}">
    @if($meta['image'])
        <meta property="og:image" content="{{ $meta['image'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['title'] ?? $appName }}">
    @if($meta['description'])
        <meta name="twitter:description" content="{{ $meta['description'] }}">
    @endif
    @if($meta['image'])
        <meta name="twitter:image" content="{{ $meta['image'] }}">
    @endif

    @if($appStoreId)
        {{-- Safari shows "Open" when the app is installed, "Get" otherwise. --}}
        <meta name="apple-itunes-app" content="app-id={{ $appStoreId }}, app-argument={{ $meta['url'] }}">
    @endif

    <style>
        :root { --bg: #f6f5f2; --card: #fff; --text: #1d1d1f; --muted: #6b6b70; --accent: #3b6ef2; --border: #e6e4df; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #111113; --card: #1c1c1f; --text: #f2f2f4; --muted: #a0a0a8; --accent: #6b93ff; --border: #2c2c31; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
            background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; }
        .card { width: 100%; max-width: 420px; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; text-align: center; }
        .cover { max-width: 160px; max-height: 220px; object-fit: cover; border-radius: 10px; margin: 0 auto 16px; display: block; background: var(--border); }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { color: var(--muted); line-height: 1.7; margin: 0 0 16px; font-size: .95rem; }
        .status { font-size: .9rem; margin-bottom: 16px; }
        .btn { display: block; padding: 12px 16px; border-radius: 10px; text-decoration: none; font-weight: 600; margin-top: 10px; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-outline { border: 1px solid var(--border); color: var(--text); }
    </style>
</head>
<body>
<main class="card">
    @if($meta['image'])
        <img class="cover" src="{{ $meta['image'] }}" alt="{{ $meta['title'] ?? $appName }}">
    @endif
    <h1>{{ $meta['title'] ?? $appName }}</h1>
    @if($meta['description'])
        <p>{{ $meta['description'] }}</p>
    @endif

    @if($platform->isDesktop())
        <p class="status">{{ __('deep-link::landing.desktop_hint') }}</p>
        @if($appStoreUrl)
            <a class="btn btn-primary" href="{{ $appStoreUrl }}">{{ __('deep-link::landing.app_store') }}</a>
        @endif
        @if($playStoreUrl)
            <a class="btn btn-outline" href="{{ $playStoreUrl }}">{{ __('deep-link::landing.google_play') }}</a>
        @endif
    @else
        <p class="status">{{ __('deep-link::landing.opening') }}<br>{{ __('deep-link::landing.hint') }}</p>
        <a class="btn btn-primary" href="{{ $target }}">{{ __('deep-link::landing.open_in_app') }}</a>
        @if($storeUrl)
            <a class="btn btn-outline" href="{{ $storeUrl }}">{{ __('deep-link::landing.download') }}</a>
        @endif
    @endif
</main>

@if($platform->isMobile())
    <script>
        (function () {
            var target = @json($target);
            var store = @json($storeUrl);

            // If the app opens, the page gets hidden and the store redirect is cancelled.
            var timer = setTimeout(function () {
                if (!document.hidden && store) {
                    window.location.replace(store);
                }
            }, {{ $redirectDelay }});

            var cancel = function () { clearTimeout(timer); };
            document.addEventListener('visibilitychange', function () { if (document.hidden) cancel(); });
            window.addEventListener('pagehide', cancel);

            window.location.href = target;
        })();
    </script>
@endif
</body>
</html>
