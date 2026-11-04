<?php

namespace App\Services;

use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserFile;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class DemoRoleWorkspace
{
    public const WORKSPACE = 'demo-roles-v2';
    public const ROLES = ['admin', 'business', 'streamer', 'influencer', 'agency'];

    /** Add only this explicitly fictional workspace; never reset passwords, profiles or campaign progress. */
    public function provision(string $fixtures, array $existingCredentials, Closure $saveCredentials): array
    {
        $paths = [];
        foreach (['sample-agreement.pdf', 'internal-notes.docx', 'verification-demo.mp4'] as $name) {
            $path = realpath($fixtures.DIRECTORY_SEPARATOR.$name);
            if (! $path || ! is_file($path) || filesize($path) < 1 || filesize($path) > 10 * 1048576) {
                throw new LogicException('Provide nonempty demo PDF, DOCX and MP4 fixtures, each at most 10 MB.');
            }
            $paths[$name] = $path;
        }
        if (! str_starts_with(file_get_contents($paths['sample-agreement.pdf'], false, null, 0, 8), '%PDF-')) throw new LogicException('The agreement fixture must be a real PDF document.');
        if (! str_starts_with(file_get_contents($paths['internal-notes.docx'], false, null, 0, 4), 'PK')) throw new LogicException('The internal-notes fixture must be a real DOCX document.');

        $stored = [];
        try {
            return DB::transaction(function () use ($paths, $existingCredentials, $saveCredentials, &$stored): array {
                $credentials = collect($existingCredentials)->keyBy('email')->all();
                $accounts = [];
                $newCount = 0;
                foreach (self::ROLES as $role) {
                    $email = $role.'@demo.allinstreams.example';
                    $actualRole = in_array($role, ['streamer', 'influencer'], true) ? 'creator' : $role;
                    $user = User::where('email', $email)->first();
                    if ($user && (! $user->is_demo || $user->role !== $actualRole)) throw new LogicException('A reserved demo email belongs to an existing account. That account was preserved.');
                    if (! $user) {
                        $password = $credentials[$email]['password'] ?? Str::password(24);
                        $user = User::create([
                            'name' => 'DEMO '.ucfirst($role).($actualRole === 'creator' ? ' Canada' : ''),
                            'email' => $email, 'password' => $password, 'role' => $actualRole,
                            'company' => match ($role) { 'business' => 'DEMO Pixel North Games', 'agency' => 'DEMO Maple Talent Agency', default => null },
                            'account_type' => in_array($role, ['business', 'agency'], true) ? $role : null,
                            'telegram' => '@ais_demo_'.$role, 'is_demo' => true,
                        ]);
                        $credentials[$email] = ['role' => $role, 'email' => $email, 'password' => $password, 'login_url' => rtrim(config('app.url'), '/').($role === 'admin' ? '/admin/login' : '/login')];
                        $newCount++;
                    }
                    if (isset($credentials[$email])) $credentials[$email]['password_is_current'] = Hash::check($credentials[$email]['password'], $user->password);
                    $accounts[$role] = $user;
                }

                foreach (['streamer', 'influencer'] as $role) {
                    $user = $accounts[$role];
                    $profile = CreatorProfile::withTrashed()->firstOrNew(['user_id' => $user->id]);
                    if (! $profile->exists) {
                        $video = app(CreatorProofVideo::class)->store(new UploadedFile($paths['verification-demo.mp4'], 'DEMO-verification-video.mp4', null, null, true));
                        $stored[] = $video['verification_video_path'];
                        $influencer = $role === 'influencer';
                        $profile->fill([
                            'creator_type' => $role, 'public_name' => 'DEMO '.ucfirst($role).' Canada', 'country' => 'Canada', 'language' => 'English',
                            'platform' => $influencer ? 'Instagram' : 'Twitch',
                            'channel_url' => $influencer ? 'https://www.instagram.com/ais_demo_influencer' : 'https://www.twitch.tv/ais_demo_streamer',
                            'twitch_url' => $influencer ? null : 'https://www.twitch.tv/ais_demo_streamer',
                            'instagram_url' => $influencer ? 'https://www.instagram.com/ais_demo_influencer' : null,
                            'facebook_url' => $influencer ? 'https://www.facebook.com/ais.demo.influencer' : null,
                            'followers' => $influencer ? 42000 : 58000, 'average_viewers' => $influencer ? 0 : 1250,
                            'audience_geos' => ['Canada', 'United States'], 'vertical' => 'Gaming', 'rate' => $influencer ? 450 : 650,
                            'commercial_model' => 'Fixed', 'availability' => 'Open', 'status' => 'approved',
                            'bio' => 'DEMO ONLY. Fictional identity, social links and simulated audience statistics. The attached generated demo video is not actual platform verification.',
                        ] + $video)->save();
                    }
                }

                $agency = $accounts['agency'];
                AgencyProfile::firstOrCreate(['user_id' => $agency->id], ['services' => ['supply', 'request'], 'country' => 'Canada', 'languages' => ['English', 'French'], 'audience_geos' => ['Canada', 'United States'], 'platforms' => ['Twitch', 'Kick'], 'bio' => 'DEMO ONLY. Fictional talent agency and simulated roster.', 'status' => 'approved']);
                $talent = AgencyTalent::withTrashed()->firstOrCreate(['agency_user_id' => $agency->id, 'public_name' => 'DEMO Maple Roster Streamer'], [
                    'country' => 'Canada', 'language' => 'English', 'platform' => 'Kick', 'kick_url' => 'https://kick.com/ais_demo_roster', 'followers' => 34000, 'average_viewers' => 850,
                    'audience_geos' => ['Canada'], 'rate' => 550, 'availability' => 'Open', 'status' => 'approved',
                ]);

                $workflow = app(CampaignWorkflow::class);
                $admin = $accounts['admin'];
                $briefs = [];
                foreach (['streamer', 'influencer', 'agency', 'negotiation'] as $kind) {
                    $key = self::WORKSPACE.'-'.$kind;
                    $owner = $kind === 'negotiation' ? $agency : $accounts['business'];
                    $brief = Brief::where('demo_key', $key)->first();
                    if ($brief && (! $brief->is_demo || $brief->user_id !== $owner->id)) throw new LogicException('An existing campaign uses a reserved demo identifier. Existing data was preserved.');
                    if (! $brief) {
                        $brief = Brief::create([
                            'user_id' => $owner->id, 'is_demo' => true, 'demo_key' => $key,
                            'title' => 'DEMO Canada '.ucfirst($kind).' Campaign', 'brand' => $owner->company,
                            'vertical' => 'Gaming', 'geos' => ['Canada'], 'language' => 'English', 'platform' => $kind === 'influencer' ? 'Instagram' : 'Twitch',
                            'budget' => $kind === 'agency' ? 6000 : 4000, 'currency' => 'EUR', 'commercial_model' => 'Fixed', 'provider_type' => $kind === 'agency' ? 'agency' : 'creator',
                            'deliverables' => $kind === 'influencer' ? '2 social videos and 3 stories' : '2 live streams and a recap',
                            'start_date' => today()->addDays(10), 'end_date' => today()->addDays(30), 'status' => 'submitted',
                            'payment_ready' => false, 'notes' => 'DEMO ONLY. Fictional campaign. No real contract, payment or performance claim.',
                        ]);
                        $workflow->transition($brief, 'matching', $admin);
                        if ($kind === 'negotiation') {
                            $workflow->invite($brief->refresh(), $accounts['streamer']->creatorProfile, $admin, $this->terms('DEMO Canadian streaming opportunity', 650));
                            $counter = $workflow->invite($brief->refresh(), $accounts['influencer']->creatorProfile, $admin, $this->terms('DEMO Canadian social opportunity', 450));
                            $workflow->respond($counter, $accounts['influencer'], 'changes_requested', 'DEMO counterproposal: please include editing time in the package.', 550, offerVersion: 1);
                        } else {
                            $invitation = $kind === 'agency'
                                ? $workflow->inviteAgency($brief->refresh(), $agency, $admin, $this->terms('DEMO Canadian roster opportunity', 2200))
                                : $workflow->invite($brief->refresh(), $accounts[$kind]->creatorProfile, $admin, $this->terms('DEMO Canadian '.($kind === 'streamer' ? 'streaming' : 'social').' opportunity', $kind === 'streamer' ? 1300 : 900));
                            $workflow->respond($invitation, $accounts[$kind], 'interested', 'DEMO response: sample terms accepted.', offerVersion: 1, talentIds: $kind === 'agency' ? [$talent->id] : []);
                            $workflow->selectProvider($invitation->refresh(), $admin);
                            $workflow->transition($brief->refresh(), 'proposal', $admin);
                            $workflow->transition($brief->refresh(), 'contracts', $admin);
                        }
                    }
                    $briefs[$kind] = $brief->refresh();
                }

                foreach ($accounts as $user) {
                    foreach (['sample-agreement.pdf' => 'demo-role-agreement.pdf', 'internal-notes.docx' => 'demo-role-internal-notes.docx'] as $fixture => $name) {
                        if (! UserFile::where('user_id', $user->id)->where('original_name', $name)->exists()) {
                            $records = app(PrivateUserFiles::class)->upload($admin, $user, [new UploadedFile($paths[$fixture], $name, null, null, true)], 'DEMO — '.($fixture === 'sample-agreement.pdf' ? 'Internal agreement' : 'Internal account notes'), 'DEMO ONLY. Administrator-only example attachment; not an executed agreement.');
                            foreach ($records as $record) $stored[] = $record->file_path;
                        }
                    }
                }
                foreach (['streamer', 'influencer', 'agency'] as $kind) {
                    $brief = $briefs[$kind];
                    $selected = $brief->invitations()->where('selection_status', 'selected')->where('status', 'interested')->whereColumn('accepted_offer_version', 'offer_version')->first();
                    // Preserve user changes: do not recreate provider agreements if the selection was removed or changed.
                    $expected = $kind === 'agency' ? $selected?->agency_user_id === $agency->id : $selected?->creator_profile_id === $accounts[$kind]->creatorProfile?->id;
                    if (! $selected || ! $expected) continue;
                    foreach (['business', $kind === 'agency' ? 'agency' : 'creator'] as $audience) {
                        $path = 'campaign-documents/demo-roles/'.$brief->id.'/'.$audience.'-agreement.pdf';
                        if ($brief->documents()->where('file_path', $path)->exists()) continue;
                        if (! Storage::disk('local')->put($path, file_get_contents($paths['sample-agreement.pdf']))) throw new LogicException('The private demo agreement could not be stored.');
                        $stored[] = $path;
                        CampaignDocument::create([
                            'brief_id' => $brief->id, 'title' => 'DEMO — '.($audience === 'business' ? 'Business' : 'Provider').' agreement (unsigned)', 'audience' => $audience,
                            'creator_profile_id' => $audience === 'creator' ? $selected->creator_profile_id : null,
                            'agency_user_id' => $audience === 'agency' ? $selected->agency_user_id : null,
                            'file_path' => $path, 'signature_status' => 'uploaded',
                        ]);
                    }
                }
                $saveCredentials(array_values($credentials));
                $briefIds = collect($briefs)->pluck('id');
                return ['new_accounts' => $newCount, 'accounts' => count($accounts), 'campaigns' => count($briefs), 'offers' => Invitation::whereIn('brief_id', $briefIds)->count(), 'campaign_documents' => CampaignDocument::whereIn('brief_id', $briefIds)->count(), 'private_user_files' => UserFile::whereIn('user_id', collect($accounts)->pluck('id'))->count()];
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function terms(string $title, int $amount): array
    {
        return ['opportunity_title' => $title, 'compensation' => $amount, 'deliverables' => 'DEMO scope: 2 content sessions and a recap, coordinated only through All In Streams.', 'requirements' => 'DEMO ONLY. Submit sample delivery evidence to All In Streams; do not contact other parties.', 'notes' => 'Fictional example. All parties remain anonymous.'];
    }
}
