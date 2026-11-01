<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CampaignMetric;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

class ShowcaseSeeder extends Seeder
{
    public const PREFIX = 'showcase-v1-';

    /** This entry point retains the usual protection against accidental production seeding. */
    public function run(): void
    {
        $this->provision();
    }

    /**
     * Add a fictional workspace without overwriting existing people, passwords or workflow changes.
     * The command passes its private credential writer before committing the new accounts.
     */
    public function provision(bool $force = false, ?Closure $beforeCommit = null): array
    {
        if (app()->environment('production') && ! $force) {
            throw new LogicException('Production sample data requires the explicit showcase:seed --force command.');
        }

        return Model::unguarded(fn () => DB::transaction(function () use ($beforeCommit) {
            $credentials = [];
            $businesses = [];
            foreach ($this->businesses() as $index => [$slug, $name, $company, $type]) {
                $businesses[] = $this->account($slug, $name, 'business', $company, $type, 60 - $index * 3, $credentials);
            }

            $creators = [];
            foreach ($this->creators() as $index => $row) {
                [$slug, $name, $handle, $country, $language, $platform, $followers, $viewers, $geos, $vertical, $rate, $status] = $row;
                $user = $this->account($slug, $name, 'creator', null, null, ($index < 8 ? 80 - $index * 3 : 20 - $index * 2), $credentials);
                $profile = CreatorProfile::firstOrNew(['user_id' => $user->id]);
                if (! $profile->exists) {
                    $twitch = 'https://www.twitch.tv/ais_demo_'.$handle;
                    $kick = 'https://kick.com/ais_demo_'.$handle;
                    $profile->forceFill([
                        'public_name' => $name, 'country' => $country, 'language' => $language,
                        'platform' => $platform, 'channel_url' => $platform === 'Kick' ? $kick : $twitch,
                        'twitch_url' => $index % 3 !== 2 ? $twitch : null,
                        'kick_url' => $index % 3 !== 0 ? $kick : null,
                        'followers' => $followers, 'average_viewers' => $viewers, 'audience_geos' => $geos,
                        'vertical' => $vertical, 'rate' => $rate, 'commercial_model' => $index % 2 ? 'Fixed' : 'Fixed + CPA',
                        'availability' => $index === 7 ? 'Opening soon' : 'Open',
                        'status' => $status,
                        'bio' => 'Fictional sample creator. Simulated audience figures and channel links for exploring the All In Streams workflow.',
                        'created_at' => $user->created_at->copy()->addHours(2), 'updated_at' => $user->created_at->copy()->addHours(2),
                    ])->save();
                }
                $creators[] = $profile;
            }

            $briefs = [];
            foreach ($this->campaigns() as $index => $row) {
                [$slug, $owner, $title, $vertical, $status, $budget, $geos, $language, $platform, $start, $end, $age] = $row;
                $key = self::PREFIX.$slug;
                $brief = Brief::where('demo_key', $key)->first();
                if ($brief && (! $brief->is_demo || $brief->user_id !== $businesses[$owner]->id)) {
                    throw new LogicException('A sample campaign identifier is already used by another record. No existing data was changed.');
                }
                if (! $brief) {
                    $brief = new Brief();
                    $brief->forceFill([
                        'demo_key' => $key, 'is_demo' => true, 'user_id' => $businesses[$owner]->id,
                        'title' => $title, 'brand' => $businesses[$owner]->company,
                        'vertical' => $vertical, 'status' => $status, 'budget' => $budget, 'currency' => 'EUR',
                        'geos' => $geos, 'language' => $language, 'platform' => $platform,
                        'commercial_model' => $vertical === 'iGaming' ? 'Fixed + CPA' : 'Fixed',
                        'deliverables' => '4 live streams of 90 minutes, 2 highlights and a campaign recap. All deliverables are coordinated by All In Streams.',
                        'start_date' => now()->startOfDay()->addDays($start), 'end_date' => now()->startOfDay()->addDays($end),
                        'kpis' => 'Reach the agreed audience, track campaign clicks and report completed streams.',
                        'notes' => 'SAMPLE WORKSPACE — fictional company, campaign and performance figures. This record is for demonstration only.',
                        'payment_ready' => in_array($status, ['live', 'completed'], true),
                        'created_at' => now()->startOfDay()->subDays($age)->addHours(10 + $index % 5),
                        'updated_at' => now()->subHours(3 + $index * 2),
                    ])->save();
                }
                $briefs[] = $brief;
                $this->history($brief);
            }

            $invitationPlans = [
                3 => [[0, 'pending'], [1, 'changes_requested'], [2, 'declined']],
                4 => [[3, 'pending'], [4, 'pending']],
                5 => [[1, 'interested'], [3, 'interested'], [5, 'pending']],
                6 => [[0, 'interested'], [2, 'interested']],
                7 => [[4, 'interested'], [6, 'interested']],
                8 => [[0, 'interested'], [1, 'interested']],
                9 => [[3, 'interested'], [5, 'interested']],
                10 => [[2, 'interested'], [6, 'interested']],
            ];
            foreach ($invitationPlans as $campaign => $invitations) {
                $brief = $briefs[$campaign];
                foreach ($invitations as $position => [$creator, $status]) {
                    $invitation = Invitation::firstOrCreate([
                        'brief_id' => $brief->id, 'creator_profile_id' => $creators[$creator]->id,
                    ], [
                        'status' => $status, 'compensation' => $creators[$creator]->rate * 4,
                        'deliverables' => '4 live streams · 90 minutes each + 2 highlights',
                        'notes' => 'Sample invitation. Scope and compensation remain private with All In Streams.',
                        'response_notes' => $status === 'changes_requested' ? 'Could we move the first stream to a weekend? Sample response.' : null,
                        'introduced_at' => in_array($brief->status, ['contracts', 'live', 'completed'], true) && $status === 'interested' ? $brief->created_at->copy()->addDays(3) : null,
                        'created_at' => $brief->created_at->copy()->addDay(), 'updated_at' => $brief->created_at->copy()->addDays(2),
                    ]);
                    if (in_array($brief->status, ['contracts', 'live', 'completed'], true) && $invitation->status === 'interested') {
                        $this->document($brief, 'creator', $creators[$creator], $brief->status !== 'contracts' || $position === 0 ? 'signed' : 'pending');
                    }
                }
                if (in_array($brief->status, ['contracts', 'live', 'completed'], true)) {
                    $this->document($brief, 'business', null, $brief->status === 'contracts' ? 'pending' : 'signed');
                }
            }

            foreach ($briefs as $index => $brief) {
                if (in_array($brief->status, ['live', 'completed'], true)) {
                    $this->metrics($brief, $index);
                }
            }

            $ids = array_map(fn (Brief $brief) => $brief->id, $briefs);
            $result = [
                'new_credentials' => $credentials,
                'counts' => [
                    'companies' => 4, 'agencies' => 2, 'creators' => count($creators), 'campaigns' => count($briefs),
                    'invitations' => Invitation::whereIn('brief_id', $ids)->count(),
                    'metric_days' => CampaignMetric::whereIn('brief_id', $ids)->count(),
                ],
            ];
            if ($beforeCommit) {
                $beforeCommit($result);
            }
            return $result;
        }));
    }

    private function account(string $slug, string $name, string $role, ?string $company, ?string $type, int $age, array &$credentials): User
    {
        $email = $slug.'@workspace.allinstreams.example';
        $user = User::where('email', $email)->first();
        if ($user) {
            if (! $user->is_demo || $user->role !== $role) {
                throw new LogicException('A sample email address is already used by another account. No existing account was changed.');
            }
            return $user;
        }

        $password = 'Demo!9'.bin2hex(random_bytes(20));
        $user = new User();
        $user->forceFill([
            'name' => $name, 'email' => $email, 'password' => $password,
            'role' => $role, 'company' => $company, 'account_type' => $type, 'is_demo' => true,
            'created_at' => now()->startOfDay()->subDays($age)->addHours(9), 'updated_at' => now()->subDays(max(1, $age - 4)),
        ])->save();
        $credentials[] = ['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role, 'account_type' => $type];
        return $user;
    }

    private function history(Brief $brief): void
    {
        $titles = ['submitted' => 'Sample brief received', 'review' => 'Account manager reviewing requirements', 'matching' => 'Creator matching in progress', 'proposal' => 'Creator shortlist ready', 'contracts' => 'Agreements being prepared', 'live' => 'Campaign delivery started', 'completed' => 'Delivery complete — reporting ready'];
        $path = match ($brief->status) {
            'submitted' => ['submitted'], 'review' => ['submitted', 'review'],
            'matching' => ['submitted', 'review', 'matching'],
            'proposal' => ['submitted', 'review', 'matching', 'proposal'],
            'contracts' => ['submitted', 'review', 'matching', 'proposal', 'contracts'],
            'live' => ['submitted', 'review', 'matching', 'proposal', 'contracts', 'live'],
            'completed' => ['submitted', 'review', 'matching', 'proposal', 'contracts', 'live', 'completed'],
            default => ['submitted', 'review'],
        };
        foreach ($path as $index => $stage) {
            $timestamp = match ($stage) {
                'live' => $brief->start_date->copy()->setTime(18, 0),
                'completed' => $brief->end_date->copy()->addDay()->setTime(10, 0),
                default => $brief->created_at->copy()->addDays($index),
            };
            Activity::firstOrCreate(['brief_id' => $brief->id, 'title' => $titles[$stage], 'audience' => 'business'], [
                'body' => 'Sample activity: '.$titles[$stage].'. Your All In Streams account manager coordinates the next step.',
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);
        }
        if (in_array($brief->status, ['changes_requested', 'declined'], true)) {
            Activity::firstOrCreate(['brief_id' => $brief->id, 'title' => $brief->status === 'declined' ? 'Sample brief declined' : 'Campaign timing needs clarification', 'audience' => 'business'], [
                'body' => $brief->status === 'declined' ? 'Sample outcome: the requested creator availability did not match this campaign window.' : 'Sample request: confirm the launch dates and audience language before matching.',
                'created_at' => $brief->created_at->copy()->addDays(2), 'updated_at' => $brief->created_at->copy()->addDays(2),
            ]);
        }
    }

    private function document(Brief $brief, string $audience, ?CreatorProfile $creator, string $signature): void
    {
        $title = ($audience === 'business' ? 'Business service agreement' : 'Creator service agreement').' · Sample';
        $identity = ['brief_id' => $brief->id, 'creator_profile_id' => $creator?->id, 'audience' => $audience, 'title' => $title];
        if (CampaignDocument::where($identity)->exists()) {
            return;
        }
        $path = 'showcase-documents/'.$brief->demo_key.'/'.$audience.($creator ? '-'.$creator->id : '').'.txt';
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, "ALL IN STREAMS — FICTIONAL SAMPLE DOCUMENT\n\nThis is not a legally binding agreement. Signature status is simulated for workflow demonstration.\nCampaign: ".$brief->title."\nAudience: ".$audience."\n".($creator ? 'Creator: '.$creator->public_name."\n" : 'Company: '.$brief->brand."\n"));
        }
        CampaignDocument::create($identity + [
            'file_path' => $path, 'signature_status' => $signature,
            'created_at' => $brief->created_at->copy()->addDays(4), 'updated_at' => $brief->created_at->copy()->addDays(5),
        ]);
    }

    private function metrics(Brief $brief, int $index): void
    {
        $first = $brief->start_date->copy()->max(now()->startOfDay()->subDays(29));
        $last = $brief->end_date->copy()->min(now()->startOfDay());
        for ($day = $first->copy(); $day->lte($last); $day->addDay()) {
            if (CampaignMetric::where('brief_id', $brief->id)->whereDate('recorded_on', $day->toDateString())->exists()) {
                continue;
            }
            $seed = (int) sprintf('%u', crc32($brief->demo_key.$day->toDateString()));
            $streamDay = (int) $brief->start_date->diffInDays($day);
            $streams = in_array($streamDay, [0, 5, 10, 15], true) ? 1 : 0;
            $viewers = 2300 + $seed % 5400;
            $timestamp = $day->copy()->setTime(23, 0)->min(now());
            CampaignMetric::firstOrCreate(['brief_id' => $brief->id, 'recorded_on' => $day->toDateString()], [
                'impressions' => $viewers * (3 + $seed % 4), 'unique_viewers' => $viewers,
                'clicks' => 120 + $seed % 310, 'conversions' => 4 + $seed % 31,
                'streams' => $streams, 'watch_minutes' => $viewers * (8 + $seed % 19),
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ]);
        }
    }

    private function businesses(): array
    {
        return [
            ['heliogear', 'Maya Bennett', 'HelioGear', 'business'],
            ['novaforge', 'Daniel Reed', 'NovaForge Games', 'business'],
            ['orbitmobile', 'Sofia Laurent', 'Orbit Mobile', 'business'],
            ['aether', 'Luka Vardania', 'Aether Peripherals', 'business'],
            ['northlight', 'Elena Brooks', 'Northlight Creative', 'agency'],
            ['pixelbridge', 'Marco Silva', 'PixelBridge Agency', 'agency'],
        ];
    }

    private function creators(): array
    {
        return [
            ['nika', 'Nika Plays', 'nikaplays', 'Georgia', 'Georgian', 'Twitch', 28400, 640, ['Georgia', 'Germany'], 'Gaming', 420, 'approved'],
            ['leonie', 'Leonie Live', 'leonielive', 'Germany', 'German', 'Kick', 84200, 1680, ['Germany', 'Austria', 'Switzerland'], 'Gaming', 980, 'approved'],
            ['theo', 'Theo On Air', 'theoonair', 'France', 'French', 'Kick', 51600, 1140, ['France', 'Belgium'], 'Esports', 720, 'approved'],
            ['ines', 'Ines Quest', 'inesquest', 'Portugal', 'Portuguese', 'Twitch', 67300, 1320, ['Portugal', 'Brazil'], 'Gaming', 850, 'approved'],
            ['mateo', 'Mateo GG', 'mateogg', 'Spain', 'Spanish', 'Kick', 43500, 890, ['Spain', 'Mexico'], 'iGaming', 640, 'approved'],
            ['amelia', 'Amelia Respawn', 'ameliarespawn', 'United Kingdom', 'English', 'Kick', 126000, 2460, ['United Kingdom', 'United States', 'Canada'], 'Gaming', 1450, 'approved'],
            ['jakub', 'Jakub Arena', 'jakubarena', 'Poland', 'Polish', 'Twitch', 38900, 780, ['Poland', 'Germany'], 'Esports', 560, 'approved'],
            ['rafa', 'Rafa Sidequest', 'rafasidequest', 'Brazil', 'Portuguese', 'Kick', 94100, 1860, ['Brazil', 'Portugal'], 'Gaming', 1120, 'approved'],
            ['salome', 'Salome Streams', 'salomestreams', 'Georgia', 'Georgian', 'Kick', 14200, 310, ['Georgia'], 'Gaming', 240, 'pending'],
            ['emil', 'Emil Next Level', 'emilnextlevel', 'Sweden', 'English', 'Twitch', 22600, 460, ['Sweden', 'United Kingdom'], 'Esports', 380, 'draft'],
        ];
    }

    private function campaigns(): array
    {
        return [
            ['gear-launch', 0, 'HelioGear creator launch', 'Gaming', 'submitted', 14000, ['Georgia', 'Germany'], 'English', 'Twitch', 20, 40, 2],
            ['co-op-discovery', 1, 'Co-op discovery weekend', 'Gaming', 'review', 22000, ['United Kingdom', 'Germany'], 'English', 'Twitch', 18, 35, 4],
            ['winter-roster', 4, 'Winter creator roster', 'Esports', 'changes_requested', 18000, ['France', 'Poland'], 'English', 'Twitch', 25, 45, 8],
            ['ranked-nights', 2, 'Ranked nights community series', 'Gaming', 'matching', 17500, ['Georgia', 'Germany'], 'English', 'Twitch', 12, 32, 10],
            ['mobile-first', 5, 'Mobile-first creator discovery', 'Gaming', 'matching', 32000, ['Portugal', 'Spain'], 'Portuguese', 'Kick', 14, 34, 12],
            ['indie-wave', 1, 'Indie launch creator wave', 'Gaming', 'proposal', 24500, ['Germany', 'Portugal'], 'English', 'Twitch', 10, 30, 16],
            ['dach-spotlight', 0, 'DACH streaming spotlight', 'Gaming', 'proposal', 16500, ['Germany', 'Austria', 'Switzerland'], 'German', 'Kick', 9, 29, 18],
            ['tournament-partners', 4, 'Tournament partner activation', 'Esports', 'contracts', 28000, ['Spain', 'Poland'], 'English', 'Twitch', 6, 26, 21],
            ['community-lobbies', 2, 'Community lobbies live', 'Gaming', 'live', 21000, ['Georgia', 'Germany'], 'English', 'Twitch', -12, 9, 30],
            ['cross-market-play', 5, 'Cross-market play sessions', 'Gaming', 'live', 34500, ['Portugal', 'United Kingdom'], 'English', 'Kick', -6, 16, 27],
            ['peripheral-recap', 3, 'Peripheral launch recap', 'Esports', 'completed', 19500, ['France', 'Poland'], 'English', 'Twitch', -40, -12, 48],
            ['late-season-pitch', 3, 'Late-season creator pitch', 'Gaming', 'declined', 8500, ['France'], 'French', 'Kick', 11, 21, 14],
        ];
    }
}
