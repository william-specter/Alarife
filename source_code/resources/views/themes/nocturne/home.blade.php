@extends('themes.nocturne.layout')

@section('content')
    <div class="container">
        @include('themes.shared.partials.covers')

        <section class="home-intro">
            <div>
                <span class="eyebrow" style="color: var(--site-accent)">Living reference</span>
                @if (($site['encyclopedia']['cover_style'] !== 'hero') || count($site['covers']) === 0)
                    <h1>{{ $site['encyclopedia']['title'] }}</h1>
                @endif
                <p class="home-intro__lede">{{ $site['encyclopedia']['description'] }}</p>
            </div>
            <div class="home-intro__meta">
                <div><strong>{{ $site['counts']['articles'] }}</strong><br>Articles</div>
                <div><strong>{{ $site['counts']['topics'] }}</strong><br>Topics</div>
                @if (! empty($site['release']['version']))
                    <div><strong>v{{ $site['release']['version'] }}</strong><br>Release</div>
                @endif
            </div>
        </section>

        @if (count($site['featured_topics']))
            <section class="home-section">
                <div class="section-heading">
                    <h2>Explore</h2>
                    <a href="#index">Index</a>
                </div>
                <div class="topic-grid">
                    @foreach ($site['featured_topics'] as $index => $topic)
                        <a class="topic-card" href="{{ $topic['url'] }}">
                            <span class="topic-card__index">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3>{{ $topic['title'] }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($topic['description'] ?? '', 120) }}</p>
                            <span class="topic-card__meta">{{ $topic['article_count'] }} articles</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if (count($site['recent_articles']))
            <section class="home-section">
                <div class="section-heading">
                    <h2>Latest</h2>
                    <a href="{{ $site['search_url'] }}">Search</a>
                </div>
                <div class="card-grid">
                    @foreach ($site['recent_articles'] as $article)
                        @include('themes.shared.partials.article-card', ['article' => $article])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="home-section" id="index">
            <div class="section-heading"><h2>Complete index</h2></div>
            <ul class="topic-index-list">
                @foreach ($site['navigation'] as $topic)
                    <li><a href="{{ $topic['url'] }}"><span>{{ $topic['title'] }}</span><span>{{ $topic['article_count'] }}</span></a></li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
