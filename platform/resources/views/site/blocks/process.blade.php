<section class="section line-top" id="how-it-works"><div class="container how-grid">
    <div class="how-intro"><span class="eyebrow">{{ $data['eyebrow'] }}</span><h2>{{ $data['title_line_1'] }}<br>{{ $data['title_line_2'] }}</h2><p>{{ $data['description'] }}</p><a href="{{ $data['url'] }}" class="text-link">{{ $data['label'] }} <x-ui-icon name="arrow" size="16"/></a></div>
    <div class="process-list">@foreach($data['steps'] as $step)<div class="process-step"><span class="step-icon"><x-ui-icon :name="['file', 'users', 'spark', 'chart'][$loop->index] ?? 'check'" size="18"/></span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['description'] }}</p></div><span class="step-tag">{{ $step['tag'] }}</span></div>@endforeach</div>
</div></section>
