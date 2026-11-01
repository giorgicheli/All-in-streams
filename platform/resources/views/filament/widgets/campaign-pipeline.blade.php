<x-filament-widgets::widget>
    <x-filament::section heading="Campaign pipeline" description="Current stage of every brief in the private workspace. Each brief is counted once.">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:20px">
            @foreach($statistics['pipeline'] as $status => $stage)
                <div>
                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;margin-bottom:8px">
                        <span>{{ $stage['label'] }}</span><strong>{{ number_format($stage['count']) }}</strong>
                    </div>
                    <progress max="{{ max($statistics['total_briefs'],1) }}" value="{{ $stage['count'] }}" aria-label="{{ $stage['label'] }}: {{ $stage['count'] }} of {{ $statistics['total_briefs'] }} briefs" style="width:100%;height:7px;accent-color:#c5f65c"></progress>
                </div>
            @endforeach
        </div>
        <p style="margin-top:20px;color:#94a3b8;font-size:12px">{{ number_format($statistics['total_briefs']) }} briefs total. {{ $statistics['demo_briefs'] ? $statistics['demo_briefs'].' sample briefs included.' : 'No sample briefs included.' }}</p>
    </x-filament::section>
</x-filament-widgets::widget>
