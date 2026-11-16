@extends('themes.'.$site['theme']['key'].'.layout')

@section('content')
    @php
        $results = $page['results'] ?? ['articles' => [], 'topics' => [], 'count' => 0];
        $isStatic = $site['static'];
    @endphp
    <div class="container search-page" @if ($isStatic) data-search-page @endif>
        <header class="page-header">
            <span class="eyebrow">Search</span>
            <h1>Find an article</h1>
            <p class="page-lede">Search across every published article, topic and reference in {{ $site['encyclopedia']['title'] }}.</p>
        </header>

        <div class="search-page__form">
            <form class="search-form" action="{{ $site['search_url'] }}" method="get" role="search" data-search-form>
                <label class="sr-only" for="search-query">Search this encyclopedia</label>
                <input id="search-query" type="search" name="q" value="{{ $page['query'] }}" placeholder="Search titles, topics and full text…" data-search-input autofocus>
                <button type="submit">Search</button>
            </form>
            <p class="search-count" data-search-count>
                @if ($page['query'] !== '')
                    {{ $results['count'] }} {{ \Illuminate\Support\Str::plural('result', $results['count']) }} for “{{ $page['query'] }}”
                @endif
            </p>
        </div>

        <div class="search-results" data-search-results>
            @foreach (($results['topics'] ?? []) as $topic)
                <article class="search-result">
                    <div class="search-result__meta">Topic</div>
                    <h2><a href="{{ $topic['url'] }}">{{ $topic['title'] }}</a></h2>
                    <p>{{ $topic['description'] }}</p>
                </article>
            @endforeach
            @foreach (($results['articles'] ?? []) as $article)
                <article class="search-result">
                    <div class="search-result__meta">{{ $article['topic_title'] ?? 'Article' }}</div>
                    <h2><a href="{{ $article['url'] }}">{{ $article['title'] }}</a></h2>
                    <p>{{ \Illuminate\Support\Str::limit($article['excerpt'] ?? '', 180) }}</p>
                </article>
            @endforeach
            @if ($page['query'] !== '' && $results['count'] === 0)
                <p class="search-empty">No results matched “{{ $page['query'] }}”. Try a shorter phrase or a topic name.</p>
            @elseif ($page['query'] === '' && ! $isStatic)
                <p class="search-empty">Enter a word or phrase above to search the encyclopedia.</p>
            @endif
        </div>

        <noscript>
            <p class="search-empty">Offline search requires JavaScript. You can still browse the topic index from the homepage.</p>
        </noscript>
    </div>
@endsection
