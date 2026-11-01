<section class="panel mt-6">
    <div class="panel-header">
        <div><h2><x-ui-icon name="chart"/> {{ $performanceTitle ?? 'Recorded campaign performance' }}</h2><p>{{ \Carbon\Carbon::parse($performance['from'])->format('d M Y') }} – {{ \Carbon\Carbon::parse($performance['to'])->format('d M Y') }} inclusive</p></div>
        <span class="tag">Last 30 calendar days</span>
    </div>
    @if($performance['has_data'])
        <div style="padding:22px">
            <div class="stats-grid !mb-4">
                @foreach([['Impressions',$performance['impressions'],'eye'],['Clicks',$performance['clicks'],'target'],['Conversions',$performance['conversions'],'check'],['Streams',$performance['streams'],'game']] as [$metricLabel,$metricValue,$metricIcon])
                    <div class="stat-card"><div class="stat-label">{{ $metricLabel }}<x-ui-icon :name="$metricIcon"/></div><div class="stat-value">{{ number_format($metricValue) }}</div><div class="stat-footnote">Staff-recorded campaign totals</div></div>
                @endforeach
            </div>
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <p class="prose-note"><strong class="lime">CTR {{ $performance['ctr'] === null ? '—' : number_format($performance['ctr'],2).'%' }}</strong> · Total clicks ÷ total impressions</p>
                <p class="prose-note">{{ $performance['campaigns'] }} {{ $performance['campaigns'] === 1 ? 'campaign' : 'campaigns' }} · {{ $performance['reported_days'] }} reported {{ $performance['reported_days'] === 1 ? 'date' : 'dates' }}</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <caption class="sr-only">Most recent reported dates in this 30-day period</caption>
                <thead><tr><th scope="col">Reported date</th><th scope="col">Impressions</th><th scope="col">Clicks</th><th scope="col">CTR</th><th scope="col">Conversions</th><th scope="col">Streams</th></tr></thead>
                <tbody>@foreach($performance['daily'] as $day)<tr><td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}</td><td>{{ number_format($day['impressions']) }}</td><td>{{ number_format($day['clicks']) }}</td><td>{{ $day['ctr'] === null ? '—' : number_format($day['ctr'],2).'%' }}</td><td>{{ number_format($day['conversions']) }}</td><td>{{ number_format($day['streams']) }}</td></tr>@endforeach</tbody>
            </table>
        </div>
        <p class="prose-note" style="padding:16px 22px">Figures are recorded by the All In Streams team. Recent reported dates are shown; dates without a report are not estimates.</p>
    @else
        <div class="empty-state"><x-ui-icon name="chart"/><h3>No campaign figures recorded yet.</h3><p>Your team’s performance reports for this period will appear here when they are added.</p></div>
    @endif
</section>
