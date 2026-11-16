@php
    $covers = $site['covers'] ?? [];
    $count = count($covers);
    $style = $site['encyclopedia']['cover_style'] ?? 'hero';
    $transition = $site['theme']['settings']['carousel']['transition'] ?? 'fade';
    $autoplay = ! empty($site['theme']['settings']['carousel']['autoplay']);
    $showCaptions = $site['theme']['settings']['carousel']['showCaptions'] ?? true;
    $interactive = ! $site['static'] || ($site['include_javascript'] ?? true);
    $autoplay = $autoplay && $interactive;
@endphp

@if ($count > 0)
    @if ($style === 'hero')
        <section class="cover-section">
            <div class="cover-hero">
                <div class="cover-carousel" data-carousel data-count="{{ $count }}" data-transition="{{ $transition }}" data-autoplay="{{ $autoplay ? 'true' : 'false' }}">
                    <div class="carousel-track">
                        @foreach ($covers as $index => $cover)
                            <figure class="carousel-slide {{ $index === 0 ? 'is-active' : '' }}">
                                <img src="{{ $cover['url'] }}" alt="{{ $cover['alt'] ?: $site['encyclopedia']['title'] }}" @if ($index > 0) loading="lazy" @endif>
                                @if ($showCaptions && ! empty($cover['caption']))
                                    <figcaption>{{ $cover['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                    @if ($autoplay && $interactive)
                        <button class="carousel-pause" type="button" data-carousel-pause aria-label="Pause slideshow">Pause</button>
                    @endif
                    @if ($count > 1 && $interactive)
                        <div class="carousel-dots" role="tablist" aria-label="Cover slides">
                            @foreach ($covers as $index => $cover)
                                <button type="button" data-carousel-dot="{{ $index }}" class="{{ $index === 0 ? 'is-active' : '' }}" aria-label="Show cover {{ $index + 1 }}" @if ($index === 0) aria-current="true" @endif></button>
                            @endforeach
                        </div>
                        <div class="carousel-controls">
                            <button type="button" data-carousel-prev aria-label="Previous cover">←</button>
                            <button type="button" data-carousel-next aria-label="Next cover">→</button>
                        </div>
                    @endif
                </div>
                <div class="cover-hero__copy">
                    <span class="eyebrow">{{ $site['counts']['articles'] }} articles · {{ $site['counts']['topics'] }} topics</span>
                    <h1>{{ $site['encyclopedia']['title'] }}</h1>
                    @if ($site['encyclopedia']['subtitle'])
                        <p>{{ $site['encyclopedia']['subtitle'] }}</p>
                    @else
                        <p>{{ \Illuminate\Support\Str::limit($site['encyclopedia']['description'] ?? '', 200) }}</p>
                    @endif
                </div>
            </div>
        </section>
    @elseif ($style === 'editorial')
        <section class="cover-section">
            <div class="cover-editorial">
                <div class="cover-carousel" data-carousel data-count="{{ $count }}" data-transition="{{ $transition }}" data-autoplay="{{ $autoplay ? 'true' : 'false' }}">
                    <div class="carousel-track">
                        @foreach ($covers as $index => $cover)
                            <figure class="carousel-slide {{ $index === 0 ? 'is-active' : '' }}">
                                <img src="{{ $cover['url'] }}" alt="{{ $cover['alt'] ?: $site['encyclopedia']['title'] }}" @if ($index > 0) loading="lazy" @endif>
                                @if ($showCaptions && ! empty($cover['caption']))
                                    <figcaption>{{ $cover['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                    @if ($autoplay && $interactive)
                        <button class="carousel-pause" type="button" data-carousel-pause aria-label="Pause slideshow">Pause</button>
                    @endif
                    @if ($count > 1 && $interactive)
                        <div class="carousel-dots" role="tablist" aria-label="Cover slides">
                            @foreach ($covers as $index => $cover)
                                <button type="button" data-carousel-dot="{{ $index }}" class="{{ $index === 0 ? 'is-active' : '' }}" aria-label="Show cover {{ $index + 1 }}" @if ($index === 0) aria-current="true" @endif></button>
                            @endforeach
                        </div>
                        <div class="carousel-controls">
                            <button type="button" data-carousel-prev aria-label="Previous cover">←</button>
                            <button type="button" data-carousel-next aria-label="Next cover">→</button>
                        </div>
                    @endif
                </div>
                <div class="cover-editorial__note">
                    <div>
                        <span class="eyebrow">Cover story</span>
                        <p class="carousel-caption">{{ $covers[0]['caption'] ?: $site['encyclopedia']['title'] }}</p>
                    </div>
                    <p>{{ \Illuminate\Support\Str::limit($site['encyclopedia']['description'] ?? '', 180) }}</p>
                    <a class="btn btn--ghost" href="{{ $site['home_url'] }}#topics">Explore the edition</a>
                </div>
            </div>
        </section>
    @else
        <section class="cover-section">
            <div class="cover-cards">
                @foreach ($covers as $index => $cover)
                    @php($topic = $site['featured_topics'][$index] ?? null)
                    <a class="cover-card" href="{{ $topic['url'] ?? $site['home_url'] }}">
                        <img src="{{ $cover['url'] }}" alt="{{ $cover['alt'] ?: $site['encyclopedia']['title'] }}" @if ($index > 0) loading="lazy" @endif>
                        <span class="cover-card__copy">
                            <span>{{ $topic ? 'Topic '.str_pad($index + 1, 2, '0', STR_PAD_LEFT) : 'Cover' }}</span>
                            <strong>{{ $topic['title'] ?? ($cover['caption'] ?: $site['encyclopedia']['title']) }}</strong>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endif
