@extends('themes.'.$site['theme']['key'].'.layout')

@section('content')
    @php
        $article = $page['article'];
        $body = $page['body'];
        $toc = $body->toc;
        $published = ! empty($article['published_at']) ? \Illuminate\Support\Carbon::parse($article['published_at']) : null;
    @endphp
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ $site['home_url'] }}">{{ $site['encyclopedia']['title'] }}</a>
            <span aria-hidden="true">/</span>
            @if ($page['topic'])
                <a href="{{ $site['link']('/'.$site['encyclopedia']['slug'].'/topics/'.$page['topic']['slug']) }}">{{ $page['topic']['title'] }}</a>
                <span aria-hidden="true">/</span>
            @endif
            <span aria-current="page">{{ $article['title'] }}</span>
        </nav>

        <header class="article-header">
            @if ($page['topic'])
                <a class="eyebrow" href="{{ $site['link']('/'.$site['encyclopedia']['slug'].'/topics/'.$page['topic']['slug']) }}">{{ $page['topic']['title'] }}</a>
            @endif
            <h1>{{ $article['title'] }}</h1>
            @if ($article['excerpt'])
                <p class="article-header__excerpt">{{ $article['excerpt'] }}</p>
            @endif
            <div class="page-header__meta">
                @if ($article['author'])
                    <span>By {{ $article['author'] }}</span>
                @endif
                @if ($published)
                    <time datetime="{{ $article['published_at'] }}">{{ $published->format('F j, Y') }}</time>
                @endif
                <span>{{ $page['reading_minutes'] }} min read</span>
                <span>{{ $body->words }} words</span>
                <button class="chip" type="button" data-copy-link>Copy link</button>
                <button class="chip" type="button" data-print>Print</button>
            </div>
        </header>

        @if ($page['featured'])
            <figure class="article-featured">
                <img src="{{ $page['featured']['url'] }}" alt="{{ $page['featured']['alt'] ?: $article['title'] }}">
                @if ($page['featured']['caption'])
                    <figcaption>{{ $page['featured']['caption'] }}</figcaption>
                @endif
            </figure>
        @endif

        <div class="article-layout {{ count($toc) ? '' : 'article-layout--plain' }}">
            <div class="article-main">
                <div class="article-body">
                    {!! $body->html !!}
                </div>

                @if (count($page['related']))
                    <footer class="article-footer">
                        <div class="section-heading"><h2>Related reading</h2></div>
                        <div class="related-grid">
                            @foreach ($page['related'] as $related)
                                <article class="article-card">
                                    <span class="article-card__topic">{{ $related['topic_title'] ?? 'Article' }}</span>
                                    <h3><a href="{{ $related['url'] }}">{{ $related['title'] }}</a></h3>
                                    <p>{{ \Illuminate\Support\Str::limit($related['excerpt'] ?? '', 100) }}</p>
                                </article>
                            @endforeach
                        </div>
                    </footer>
                @endif

                <nav class="prev-next" aria-label="Article navigation">
                    @if ($page['prev'])
                        <a href="{{ $page['prev']['url'] }}">
                            <span>Previous</span>
                            <strong>{{ $page['prev']['title'] }}</strong>
                        </a>
                    @else
                        <span></span>
                    @endif
                    @if ($page['next'])
                        <a href="{{ $page['next']['url'] }}" style="text-align: right">
                            <span>Next</span>
                            <strong>{{ $page['next']['title'] }}</strong>
                        </a>
                    @endif
                </nav>
            </div>

            @if (count($toc))
                <aside class="article-side">
                    <h2>Contents</h2>
                    <nav aria-label="Table of contents">
                        <ul class="toc">
                            @foreach ($toc as $item)
                                <li data-level="{{ $item['level'] }}"><a href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                </aside>
            @endif
        </div>
    </div>
@endsection
