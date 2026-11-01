<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Activity;
use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Demo data may only be seeded in local or testing environments.');
        }

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => $this->seedDemo());
    }

    private function seedDemo(): void
    {
        $password = 'StreamDemo!2026';
        $admin = User::updateOrCreate(['email' => 'admin@allinstreams.test'], ['name' => 'Alex Morgan', 'role' => 'admin', 'company' => 'All In Streams', 'password' => $password]);
        $business = User::updateOrCreate(['email' => 'brand@allinstreams.test'], ['name' => 'Jordan Blake', 'role' => 'business', 'company' => 'NovaBet Casino', 'password' => $password]);
        $creatorUser = User::updateOrCreate(['email' => 'creator@allinstreams.test'], ['name' => 'Max Weber', 'role' => 'creator', 'password' => $password]);
        $french = User::updateOrCreate(['email' => 'france@allinstreams.test'], ['name' => 'Lucas Martin', 'role' => 'creator', 'password' => $password]);
        $portuguese = User::updateOrCreate(['email' => 'portugal@allinstreams.test'], ['name' => 'Rafael Costa', 'role' => 'creator', 'password' => $password]);
        $agency = User::updateOrCreate(['email' => 'agency@allinstreams.test'], ['name'=>'Morgan Lee','role'=>'agency','account_type'=>'agency','company'=>'Orbit Talent Demo','password'=>$password,'is_demo'=>true]);
        AgencyProfile::updateOrCreate(['user_id'=>$agency->id], ['services'=>['supply','request'],'country'=>'Germany','website'=>'https://example.com/orbit-talent-demo','languages'=>['German','English'],'audience_geos'=>['Germany','Austria'],'platforms'=>['Twitch','Kick'],'bio'=>'Fictional sample talent agency.','status'=>'approved']);
        AgencyTalent::updateOrCreate(['agency_user_id'=>$agency->id,'public_name'=>'Orbit Arena Demo'], ['country'=>'Germany','language'=>'German','platform'=>'Twitch','twitch_url'=>'https://www.twitch.tv/ais_demo_orbit_arena','followers'=>32000,'average_viewers'=>850,'audience_geos'=>['Germany','Austria'],'rate'=>700,'availability'=>'Open','status'=>'approved']);

        $sharedCreator = ['language' => 'German', 'platform' => 'Twitch', 'vertical' => 'iGaming', 'commercial_model' => 'Fixed + CPA', 'availability' => 'Open', 'status' => 'approved'];
        $max = CreatorProfile::updateOrCreate(['id' => 24], $sharedCreator + ['user_id' => $creatorUser->id, 'public_name' => 'StreamNorth', 'country' => 'Germany', 'channel_url' => 'https://example.com/streamnorth', 'followers' => 48200, 'average_viewers' => 1200, 'audience_geos' => ['Germany', 'Austria', 'Switzerland'], 'rate' => 750, 'bio' => 'German-speaking gaming creator with an engaged community. Demo profile.']);
        $lucas = CreatorProfile::updateOrCreate(['id' => 18], array_merge($sharedCreator, ['user_id' => $french->id, 'public_name' => 'LucasLive', 'country' => 'France', 'language' => 'French', 'channel_url' => 'https://example.com/lucaslive', 'followers' => 36700, 'average_viewers' => 900, 'audience_geos' => ['France', 'Belgium'], 'rate' => 600, 'bio' => 'Community-led live gaming. Demo profile.']));
        $rafael = CreatorProfile::updateOrCreate(['id' => 27], array_merge($sharedCreator, ['user_id' => $portuguese->id, 'public_name' => 'RafaPlays', 'country' => 'Portugal', 'language' => 'Portuguese', 'channel_url' => 'https://example.com/rafaplays', 'followers' => 41900, 'average_viewers' => 1100, 'audience_geos' => ['Portugal', 'Brazil'], 'rate' => 700, 'bio' => 'Portuguese-speaking streamer. Demo profile.']));

        $sharedBrief = ['user_id' => $business->id, 'brand' => 'NovaBet Casino', 'vertical' => 'iGaming', 'geos' => ['Germany', 'Austria', 'Switzerland'], 'language' => 'German', 'platform' => 'Twitch', 'currency' => 'EUR', 'commercial_model' => 'Fixed + CPA', 'deliverables' => '4 live streams, 90 minutes each, plus 2 VODs. Include approved campaign tracking links.', 'start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(48)->toDateString(), 'kpis' => 'Reach qualified adult audiences in the agreed markets.', 'notes' => 'Demo campaign. Target an adult audience and coordinate deliverables through All In Streams.', 'payment_ready' => false];
        $review = Brief::updateOrCreate(['id' => 842], $sharedBrief + ['title' => 'Q4 DACH Slots Push', 'budget' => 18000, 'status' => 'submitted']);
        $proposal = Brief::updateOrCreate(['id' => 839], array_merge($sharedBrief, ['title' => 'European Creator Spotlight', 'budget' => 12500, 'status' => 'proposal', 'geos' => ['France', 'Portugal'], 'language' => 'Multiple']));
        $matching = Brief::updateOrCreate(['id' => 831], $sharedBrief + ['title' => 'Winter Gaming Sessions', 'budget' => 9600, 'status' => 'matching']);
        $live = Brief::updateOrCreate(['id' => 825], array_merge($sharedBrief, ['title' => 'Community Play Nights', 'vertical' => 'Gaming', 'budget' => 15000, 'status' => 'live', 'payment_ready' => true, 'start_date' => now()->subDays(5)->toDateString(), 'end_date' => now()->addDays(15)->toDateString()]));

        Invitation::updateOrCreate(['brief_id' => $proposal->id, 'creator_profile_id' => $lucas->id], ['status' => 'interested', 'compensation' => 2400, 'deliverables' => '4 live streams + 2 VODs', 'notes' => 'Fixed fee plus a performance bonus coordinated by All In Streams.']);
        Invitation::updateOrCreate(['brief_id' => $proposal->id, 'creator_profile_id' => $rafael->id], ['status' => 'interested', 'compensation' => 2800, 'deliverables' => '4 live streams + 2 VODs']);
        Invitation::updateOrCreate(['brief_id' => $matching->id, 'creator_profile_id' => $max->id], ['status' => 'pending', 'compensation' => 2400, 'deliverables' => '4 live streams · 90 minutes each', 'notes' => 'German language. Adult audience disclosure. Approved tracking links required. Final terms are coordinated by your account manager.']);
        Invitation::updateOrCreate(['brief_id' => $live->id, 'creator_profile_id' => $max->id], ['status' => 'interested', 'compensation' => 3000, 'deliverables' => '4 community live streams', 'introduced_at' => now()->subDays(8)]);
        Invitation::updateOrCreate(['brief_id'=>$matching->id,'agency_user_id'=>$agency->id], ['status'=>'pending','compensation'=>3200,'deliverables'=>'4 live streams from an approved represented creator','opportunity_title'=>'iGaming agency talent partnership']);

        foreach (Invitation::whereIn('brief_id', [$proposal->id,$matching->id,$live->id])->with('brief')->get() as $invitation) {
            $selected = $invitation->brief_id === $live->id && $invitation->creator_profile_id === $max->id;
            $invitation->update([
                'opportunity_title'=>$invitation->opportunity_title ?: $invitation->brief->vertical.' creator partnership',
                'offer_details'=>Invitation::detailsFromBrief($invitation->brief),'currency'=>'EUR','offer_version'=>1,
                'accepted_offer_version'=>$invitation->status === 'interested' ? 1 : null,
                'selection_status'=>$selected ? 'selected' : 'unselected',
                'selected_at'=>$selected ? now()->subDays(8) : null,'selected_by'=>$selected ? $admin->id : null,
                'requirements'=>'Use agreed tracking links and provide evidence of the delivered streams.',
            ]);
        }

        foreach ([$review, $proposal, $matching, $live] as $brief) {
            Activity::updateOrCreate(['brief_id' => $brief->id, 'title' => 'We received your brief', 'audience' => 'business'], ['user_id' => $admin->id, 'body' => 'Your campaign brief is with the All In Streams team. Your account manager will coordinate matching, proposals, and delivery.']);
        }
        Activity::updateOrCreate(['brief_id' => $proposal->id, 'title' => 'Your creator proposal is ready', 'audience' => 'business'], ['user_id' => $admin->id, 'body' => 'Two creators are interested in your campaign. Review your anonymized shortlist and request an introduction through your account manager.']);
        Activity::updateOrCreate(['brief_id' => $live->id, 'title' => 'Your campaign is live', 'audience' => 'business'], ['user_id' => $admin->id, 'body' => 'Agreements and payment readiness are confirmed. Your account manager is coordinating campaign delivery.']);

        Storage::disk('local')->put('campaign-documents/demo-business-agreement.txt', "ALL IN STREAMS — DEMO BUSINESS AGREEMENT\n\nThis is a fictional sample document for local demonstration. It is not a binding agreement.\nCampaign: Community Play Nights\nBusiness terms are visible only to the business and All In Streams administrators.\n");
        Storage::disk('local')->put('campaign-documents/demo-creator-agreement.txt', "ALL IN STREAMS — DEMO CREATOR AGREEMENT\n\nThis is a fictional sample document for local demonstration. It is not a binding agreement.\nCreator: StreamNorth\nPrivate creator compensation: EUR 3,000\n");
        CampaignDocument::updateOrCreate(['brief_id' => $live->id, 'audience' => 'business', 'title' => 'Business service agreement · Demo'], ['file_path' => 'campaign-documents/demo-business-agreement.txt', 'signature_status' => 'signed']);
        CampaignDocument::updateOrCreate(['brief_id' => $live->id, 'audience' => 'creator', 'title' => 'Creator service agreement · Demo'], ['creator_profile_id' => $max->id, 'file_path' => 'campaign-documents/demo-creator-agreement.txt', 'signature_status' => 'signed']);
    }
}
