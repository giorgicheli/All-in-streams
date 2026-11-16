<section class="network-section section {{ ($networkPanel ?? false) ? 'network-panel' : '' }}{{ ($compact ?? false) ? ' network-compact' : '' }}" id="network" aria-labelledby="network-title">
    <div class="container">
        @if($compact ?? false)
            <h2 id="network-title" class="sr-only">{{ $data['title'] }}</h2>
        @else
        <div class="section-heading"><div><span class="eyebrow">{{ $data['eyebrow'] }}</span><h2 id="network-title">{{ $data['title'] }}</h2></div><p>{{ $data['description'] }}</p></div>
        @endif
        <div class="network-stat-grid" data-motion-group>
            @foreach($data['cards'] as $card)
                <article class="network-stat-card">
                    <div class="network-stat-top">
                    <p class="network-stat-value"><span class="sr-only">{{ number_format($card['count']) }}{{ $card['suffix'] }}</span><span aria-hidden="true"><span data-network-count="{{ $card['count'] }}">{{ number_format($card['count']) }}</span><span class="network-stat-suffix">{{ $card['suffix'] }}</span></span></p>
                    <span class="network-stat-icon"><x-ui-icon :name="$card['icon']" size="22"/></span>
                    </div>
                    <h3>{{ $card['label'] }}</h3><p class="network-stat-description" @if($compact ?? false) hidden @endif>{{ $card['description'] }}</p>
                    <span class="network-stat-line" aria-hidden="true"></span>
                </article>
            @endforeach
        </div>
    </div>
</section>
