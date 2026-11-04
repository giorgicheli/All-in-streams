<?php

namespace App\Services;

use App\Models\Brief;
use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\CampaignMetric;
use App\Models\CreatorProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class WorkspaceStatistics
{
    public const STAGES = [
        'submitted' => 'Submitted',
        'review' => 'Admin review',
        'changes_requested' => 'Changes requested',
        'matching' => 'Matching',
        'proposal' => 'Proposal',
        'contracts' => 'Contracts',
        'live' => 'Live',
        'completed' => 'Completed',
        'declined' => 'Declined',
    ];

    private const METRICS = ['impressions', 'unique_viewers', 'clicks', 'conversions', 'streams', 'watch_minutes'];

    public function admin(User $actor, ?CarbonInterface $asOf = null): array
    {
        abort_unless($actor->role === 'admin', 403);

        $summary = $this->summarize(Brief::query(), $asOf);
        $accounts = [
                'companies' => User::where('role', 'business')->count(),
                'agencies' => User::where('role', 'agency')->count(),
                'agencies_approved' => AgencyProfile::where('status', 'approved')->count(),
                'agency_talent' => AgencyTalent::count(),
                'agency_talent_approved' => AgencyTalent::where('status', 'approved')->count(),
                'creator_accounts' => User::where('role', 'creator')->count(),
                'creators_total' => CreatorProfile::count(),
                'streamers_total' => CreatorProfile::where('creator_type', 'streamer')->count(),
                'influencers_total' => CreatorProfile::where('creator_type', 'influencer')->count(),
                'creators_approved' => CreatorProfile::where('status', 'approved')->count(),
                'creators_pending' => CreatorProfile::where('status', 'pending')->count(),
                'creators_declined' => CreatorProfile::where('status', 'declined')->count(),
                'demo_users' => User::where('is_demo', true)->count(),
        ];

        return array_replace($summary, [
            'accounts' => $accounts,
            'contains_demo' => $summary['contains_demo'] || $accounts['demo_users'] > 0,
        ]);
    }

    public function business(User $actor, ?CarbonInterface $asOf = null): array
    {
        abort_unless(in_array($actor->role, ['business', 'agency'], true), 403);

        $summary = $this->summarize(Brief::where('user_id', $actor->id), $asOf);
        $summary['contains_demo'] = $summary['contains_demo'] || (bool) $actor->is_demo;
        return $summary;
    }

    public function campaign(Brief $brief, User $actor, ?CarbonInterface $asOf = null): array
    {
        abort_unless($actor->role === 'admin' || (in_array($actor->role, ['business', 'agency'], true) && $brief->user_id === $actor->id), 403);

        return $this->summarize(Brief::whereKey($brief->id), $asOf);
    }

    public function creator(User $actor): array
    {
        abort_unless($actor->role === 'creator', 403);
        $profile = $actor->creatorProfile;
        $invitations = $profile?->invitations();

        return [
            'followers' => $profile?->followers,
            'average_viewers' => $profile?->average_viewers,
            'pending' => $invitations ? (clone $invitations)->where('status', 'pending')->where('selection_status', '!=', 'not_selected')->whereHas('brief', fn (Builder $query) => $query->whereIn('status', ['matching', 'proposal']))->count() : 0,
            'interested' => $invitations ? (clone $invitations)->where('status', 'interested')->count() : 0,
            'total' => $invitations ? (clone $invitations)->count() : 0,
            'contains_demo' => (bool) $actor->is_demo || ($invitations && (clone $invitations)->whereHas('brief', fn (Builder $query) => $query->where('is_demo', true))->exists()),
        ];
    }

    private function summarize(Builder $briefs, ?CarbonInterface $asOf): array
    {
        $stageCounts = (clone $briefs)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $pipeline = [];
        foreach (self::STAGES as $status => $label) {
            $pipeline[$status] = ['label' => $label, 'count' => (int) ($stageCounts[$status] ?? 0)];
        }
        $budgets = (clone $briefs)->where('status', '!=', 'declined')->selectRaw('currency, SUM(budget) AS aggregate')->groupBy('currency')->orderBy('currency')->pluck('aggregate', 'currency')->map(fn ($amount) => round((float) $amount, 2))->all();
        $demoBriefs = (clone $briefs)->where(fn (Builder $query) => $query->where('is_demo', true)->orWhereHas('user', fn (Builder $owner) => $owner->where('is_demo', true)))->count();

        return [
            'total_briefs' => array_sum(array_column($pipeline, 'count')),
            'active' => $pipeline['matching']['count'] + $pipeline['proposal']['count'] + $pipeline['contracts']['count'] + $pipeline['live']['count'],
            'review' => $pipeline['submitted']['count'] + $pipeline['review']['count'] + $pipeline['changes_requested']['count'],
            'completed' => $pipeline['completed']['count'],
            'pipeline' => $pipeline,
            'budgets' => $budgets,
            'budget_eur' => $budgets['EUR'] ?? 0.0,
            'demo_briefs' => $demoBriefs,
            'contains_demo' => $demoBriefs > 0,
            'performance' => $this->performance($briefs, $asOf),
        ];
    }

    private function performance(Builder $briefs, ?CarbonInterface $asOf): array
    {
        $end = CarbonImmutable::instance($asOf ?? now())->startOfDay();
        $start = $end->subDays(29);
        $query = CampaignMetric::whereIn('brief_id', (clone $briefs)->select('id'))
            ->whereDate('recorded_on', '>=', $start->toDateString())
            ->whereDate('recorded_on', '<=', $end->toDateString());
        $select = implode(', ', array_map(fn ($field) => 'COALESCE(SUM('.$field.'), 0) AS '.$field, self::METRICS));
        $row = (clone $query)->selectRaw($select.', COUNT(*) AS metric_rows, COUNT(DISTINCT brief_id) AS campaign_count, COUNT(DISTINCT recorded_on) AS reported_days, MAX(recorded_on) AS latest_date')->first();
        $totals = [];
        foreach (self::METRICS as $field) {
            $totals[$field] = (int) $row->{$field};
        }
        $daily = (clone $query)->selectRaw('recorded_on, '.$select)->groupBy('recorded_on')->orderByDesc('recorded_on')->limit(7)->get()->map(function ($day) {
            $values = ['date' => $day->recorded_on->toDateString()];
            foreach (self::METRICS as $field) {
                $values[$field] = (int) $day->{$field};
            }
            $values['ctr'] = $values['impressions'] > 0 ? round($values['clicks'] / $values['impressions'] * 100, 2) : null;
            return $values;
        })->all();

        return $totals + [
            'ctr' => $totals['impressions'] > 0 ? round($totals['clicks'] / $totals['impressions'] * 100, 2) : null,
            'metric_rows' => (int) $row->metric_rows,
            'campaigns' => (int) $row->campaign_count,
            'reported_days' => (int) $row->reported_days,
            'latest_date' => $row->latest_date ? CarbonImmutable::parse($row->latest_date)->toDateString() : null,
            'has_data' => (int) $row->metric_rows > 0,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'daily' => $daily,
        ];
    }
}
