<section class="hero-statistics" aria-labelledby="hero-statistics-title">
    <div class="container hero-statistics-inner">
        <h3 class="sr-only" id="hero-statistics-title">{{ $data['title'] }}</h3>
        <div class="hero-stat-grid">
            @foreach($data['cards'] as $card)
                <article class="hero-stat-card">
                    <div class="hero-stat-top">
                        <p class="network-stat-value"><span class="sr-only">{{ number_format($card['count']) }}{{ $card['suffix'] }}</span><span aria-hidden="true"><span data-network-count="{{ $card['count'] }}">{{ number_format($card['count']) }}</span><span class="network-stat-suffix">{{ $card['suffix'] }}</span></span></p>
                        <span class="hero-stat-icon"><x-ui-icon :name="$card['icon']" size="22"/></span>
                    </div>
                    <h4>{{ $card['label'] }}</h4>
                </article>
            @endforeach
        </div>
    </div>
</section>
