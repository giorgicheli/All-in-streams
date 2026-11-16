@props(['data'])
<section {{ $attributes->class('rope-statistics') }} id="network" data-rope-statistics aria-labelledby="network-title">
    <h2 class="sr-only" id="network-title">{{ $data['title'] }}</h2>
    <svg class="rope-statistics-ropes" aria-hidden="true" focusable="false">
        @foreach(['anchor-0', 'anchor-1', '0-1', '0-2', '1-3', '2-3'] as $link)
            <path data-rope-link="{{ $link }}"/>
        @endforeach
    </svg>
    <div class="rope-statistics-board">
        @foreach($data['cards'] as $card)
            <article class="rope-stat-card" data-rope-node tabindex="0" role="group" aria-label="{{ number_format($card['count']) }}{{ $card['suffix'] }} {{ $card['label'] }}" aria-describedby="rope-statistics-help">
                <span class="rope-stat-value"><span class="sr-only">{{ number_format($card['count']) }}{{ $card['suffix'] }}</span><span aria-hidden="true"><span data-network-count="{{ $card['count'] }}">{{ number_format($card['count']) }}</span><span class="rope-stat-suffix">{{ $card['suffix'] }}</span></span></span>
                <span class="rope-stat-label">{{ $card['label'] }}</span>
                <span class="rope-stat-icon" aria-hidden="true"><x-ui-icon :name="$card['icon']" size="21"/></span>
            </article>
        @endforeach
    </div>
    <span class="sr-only" id="rope-statistics-help">Drag a card with your mouse or finger. With a card focused, use the arrow keys to move it, or Home or Escape to reset the cards.</span>
    <span class="sr-only" data-rope-status role="status" aria-live="polite"></span>
</section>
