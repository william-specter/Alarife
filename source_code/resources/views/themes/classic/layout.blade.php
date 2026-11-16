@php
    $theme = $site['theme'] ?? [];
    $encyclopedia = $site['encyclopedia'] ?? [];
    $page = $page ?? null;
    $pageType = $page['type'] ?? 'home';
    $pageTitle = match ($pageType) {
        'article' => ($page['article']['title'] ?? 'Article').' · '.$encyclopedia['title'],
        'topic' => ($page['topic']['title'] ?? 'Topic').' · '.$encyclopedia['title'],
        'search' => 'Search · '.$encyclopedia['title'],
        default => $encyclopedia['title'].($encyclopedia['subtitle'] ? ' — '.$encyclopedia['subtitle'] : ''),
    };
    $description = match ($pageType) {
        'article' => $page['article']['excerpt'] ?? $encyclopedia['description'],
        'topic' => $page['topic']['description'] ?? $encyclopedia['description'],
        default => $encyclopedia['description'],
    };
    $settingsHash = substr(md5(json_encode($theme['settings'] ?? [])), 0, 8);
@endphp
<!DOCTYPE html>
<html lang="{{ $encyclopedia['language'] ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description ?? ''), 180) }}">
    @if (! $site['static'])
        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    @if (($encyclopedia['visibility'] ?? 'public') !== 'public')
        <meta name="robots" content="noindex, nofollow">
    @endif
    @if ($site['static'])
        <link rel="stylesheet" href="{{ $site['asset_base'] }}assets/css/style.css">
    @else
        <link rel="stylesheet" href="{{ url('/themes/'.$theme['key'].'.css') }}?e={{ $encyclopedia['slug'] }}&v={{ $settingsHash }}">
    @endif
</head>
<body class="theme-classic page-{{ $pageType }}">
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="container site-header__inner">
            <a class="site-brand" href="{{ $site['home_url'] }}">
                <span class="site-brand__name">{{ $encyclopedia['title'] }}</span>
                @if ($encyclopedia['subtitle'])
                    <span class="site-brand__tag">{{ $encyclopedia['subtitle'] }}</span>
                @endif
            </a>

            <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-navigation">Menu</button>

            <nav class="site-nav" id="site-navigation" aria-label="Main navigation">
                <a href="{{ $site['home_url'] }}">Home</a>
                @foreach (array_slice($site['navigation'], 0, 4) as $item)
                    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                @endforeach
                <a href="{{ $site['search_url'] }}">Search</a>
                @if ($site['download_url'])
                    <a href="{{ $site['download_url'] }}">Download</a>
                @endif
            </nav>

            <div class="site-actions">
                <form class="search-form" action="{{ $site['search_url'] }}" method="get" role="search">
                    <label class="sr-only" for="header-search">Search this encyclopedia</label>
                    <input id="header-search" type="search" name="q" placeholder="Search…" value="{{ $pageType === 'search' ? ($page['query'] ?? '') : '' }}">
                    <button type="submit">Search</button>
                </form>
            </div>
        </div>
    </header>

    <main id="main">
        @yield('content')
        @include('themes.shared.partials.cross-references')
    </main>

    <footer class="site-footer">
        <div class="container site-footer__inner">
            <div>
                <strong>{{ $encyclopedia['title'] }}</strong>
                <p>{{ \Illuminate\Support\Str::limit($encyclopedia['description'] ?? '', 150) }}</p>
                @if (! empty($site['release']['version']))
                    <p class="small">Release v{{ $site['release']['version'] }} · {{ $site['counts']['articles'] }} articles</p>
                @endif
            </div>
            <nav aria-label="Footer navigation">
                <a href="{{ $site['home_url'] }}">Home</a>
                <a href="{{ $site['search_url'] }}">Search</a>
                @if ($site['download_url'])
                    <a href="{{ $site['download_url'] }}">Download offline edition</a>
                @endif
            </nav>
        </div>
        <div class="container site-footer__bottom">
            <span>© {{ $site['year'] }} {{ $encyclopedia['title'] }}</span>
            <span>Published with Alarife</span>
        </div>
    </footer>

    @if ($site['static'])
        @if ($site['include_search'] ?? true)
            <script src="{{ $site['asset_base'] }}assets/search/search-index.js"></script>
        @endif
        @if ($site['include_javascript'] ?? true)
            <script src="{{ $site['asset_base'] }}assets/js/app.js"></script>
            <script src="{{ $site['asset_base'] }}assets/js/carousel.js"></script>
            @if ($site['include_search'] ?? true)
                <script src="{{ $site['asset_base'] }}assets/js/search.js"></script>
            @endif
        @endif
    @else
        <script src="{{ asset('assets/js/app.js') }}" defer></script>
        <script src="{{ asset('assets/js/carousel.js') }}" defer></script>
    @endif
</body>
</html>
