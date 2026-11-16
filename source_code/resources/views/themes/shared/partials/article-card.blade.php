@php
    $article = $article ?? [];
@endphp
<article class="article-card">
    @if (! empty($article['image_url']))
        <a class="article-card__media" href="{{ $article['url'] }}">
            <img src="{{ $article['image_url'] }}" alt="{{ $article['title'] }}" loading="lazy" decoding="async">
        </a>
    @endif
    @if (! empty($article['topic_title']))
        <a class="article-card__topic" href="{{ $article['topic_url'] }}">{{ $article['topic_title'] }}</a>
    @endif
    <h3><a href="{{ $article['url'] }}">{{ $article['title'] }}</a></h3>
    @if (! empty($article['excerpt']))
        <p>{{ \Illuminate\Support\Str::limit($article['excerpt'], 150) }}</p>
    @endif
    <div class="article-card__meta">
        <span>{{ $article['reading_minutes'] ?? 1 }} min read</span>
        @if (! empty($article['published_at']))
            <time datetime="{{ $article['published_at'] }}">{{ \Illuminate\Support\Carbon::parse($article['published_at'])->format('M j, Y') }}</time>
        @endif
    </div>
</article>
