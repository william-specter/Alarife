@extends('themes.'.$site['theme']['key'].'.layout')

@section('content')
    @php
        $topic = $page['topic'];
        $articles = $page['articles'];
        $children = $page['children'];
    @endphp
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ $site['home_url'] }}">{{ $site['encyclopedia']['title'] }}</a>
            <span aria-hidden="true">/</span>
            @if ($page['parent'])
                <a href="{{ $site['link']('/'.$site['encyclopedia']['slug'].'/topics/'.$page['parent']['slug']) }}">{{ $page['parent']['title'] }}</a>
                <span aria-hidden="true">/</span>
            @endif
            <span aria-current="page">{{ $topic['title'] }}</span>
        </nav>

        <header class="page-header topic-header">
            <span class="eyebrow">Topic</span>
            <h1>{{ $topic['title'] }}</h1>
            @if ($topic['description'])
                <p class="page-lede">{{ $topic['description'] }}</p>
            @endif
            <div class="page-header__meta">
                <span>{{ count($articles) }} {{ \Illuminate\Support\Str::plural('article', count($articles)) }}</span>
                @if (count($children))
                    <span>{{ count($children) }} subtopics</span>
                @endif
            </div>
        </header>

        @if (count($children))
            <section class="home-section">
                <div class="section-heading"><h2>Subtopics</h2></div>
                <div class="topic-grid">
                    @foreach ($children as $child)
                        <a class="topic-card" href="{{ $site['link']('/'.$site['encyclopedia']['slug'].'/topics/'.$child['slug']) }}">
                            <span class="topic-card__index">—</span>
                            <h3>{{ $child['title'] }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit($child['description'] ?? '', 110) }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="home-section">
            <div class="section-heading"><h2>Articles</h2></div>
            @if (count($articles))
                <div class="card-grid">
                    @foreach ($articles as $article)
                        @include('themes.shared.partials.article-card', ['article' => $article])
                    @endforeach
                </div>
            @else
                <p class="search-empty">No published articles in this topic yet.</p>
            @endif
        </section>
    </div>
@endsection
