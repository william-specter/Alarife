@php
    $crossReferences = $site['cross_references'] ?? [];
@endphp

@if (count($crossReferences))
    <section class="cross-references" aria-labelledby="cross-references-title">
        <div class="container">
            <div class="section-heading">
                <h2 id="cross-references-title">Related works</h2>
                <span class="small muted">From other encyclopedias</span>
            </div>
            <div class="cross-reference-grid">
                @foreach ($crossReferences as $reference)
                    <a
                        class="cross-reference-card"
                        href="{{ $reference['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span class="cross-reference-card__kicker">
                            {{ $reference['target_type'] === 'article' ? 'Article' : 'Topic' }}
                            · {{ $reference['encyclopedia_title'] }}
                        </span>
                        <h3>{{ $reference['title'] }}</h3>
                        @if (! empty($reference['excerpt']))
                            <p>{{ \Illuminate\Support\Str::limit($reference['excerpt'], 150) }}</p>
                        @endif
                        @if (! empty($reference['note']))
                            <p class="cross-reference-card__note">“{{ $reference['note'] }}”</p>
                        @endif
                        <span class="cross-reference-card__link">Read in {{ $reference['encyclopedia_title'] }} ↗</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
