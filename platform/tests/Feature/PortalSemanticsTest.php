<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalSemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
    }

    public function test_private_pages_have_one_page_heading_and_ordered_section_headings(): void
    {
        $business = User::where('role', 'business')->firstOrFail();
        $agency = User::where('role', 'agency')->firstOrFail();
        $creator = User::where('role', 'creator')->firstOrFail();
        $agencyBrief = $business->briefs()->firstOrFail()->replicate();
        $agencyBrief->forceFill(['user_id' => $agency->id, 'title' => 'Agency audit campaign', 'status' => 'changes_requested'])->save();
        $businessEdit = $business->briefs()->firstOrFail()->replicate();
        $businessEdit->forceFill(['title' => 'Business audit campaign', 'status' => 'changes_requested'])->save();

        $businessPages = ['/dashboard', '/campaigns', '/proposals', '/documents', '/briefs/create', '/account/settings'];
        $creatorPages = ['/creator/opportunities', '/creator/apply', '/documents', '/provider/deliveries', '/provider/payments', '/account/settings'];
        $agencyPages = ['/agency/dashboard', '/agency/profile', '/agency/roster', '/agency/opportunities', '/agency/campaigns', '/agency/proposals', '/documents', '/provider/deliveries', '/provider/payments', '/account/settings'];
        $scenarios = [
            [$business, [...$businessPages, '/campaigns/'.$businessEdit->id, '/briefs/'.$businessEdit->id.'/edit']],
            [$creator, $creatorPages],
            [$agency, [...$agencyPages, '/agency/briefs/create', '/agency/campaigns/'.$agencyBrief->id, '/agency/briefs/'.$agencyBrief->id.'/edit']],
            [User::factory()->create(['role' => 'business']), $businessPages],
            [User::factory()->create(['role' => 'creator']), $creatorPages],
            [User::factory()->create(['role' => 'agency', 'account_type' => 'agency']), $agencyPages],
        ];
        foreach (['influencer', 'both'] as $type) {
            $user = User::factory()->create(['role' => 'creator']);
            $profile = $creator->creatorProfile->replicate();
            $profile->forceFill(['user_id' => $user->id, 'creator_type' => $type, 'instagram_url' => 'https://instagram.com/semantic_fixture'])->save();
            $scenarios[] = [$user, $creatorPages];
        }

        $violations = [];
        foreach ($scenarios as [$user, $pages]) {
            $this->actingAs($user);
            foreach ($pages as $path) {
                $response = $this->get($path)->assertOk();
                $document = new DOMDocument;
                $previous = libxml_use_internal_errors(true);
                $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                $xpath = new DOMXPath($document);
                $context = $user->role.':'.$path;
                $this->assertSame(1, $xpath->query('//h1')->length, $context.' must have exactly one H1');
                $this->assertSame(1, $xpath->query('//header')->length, $context.' must have one portal topbar');
                $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
                $level = 0;
                foreach ($headings as $heading) {
                    $next = (int) substr($heading->nodeName, 1);
                    if ($next > $level + 1) $violations[] = $context.' skips H'.$level.' to H'.$next;
                    if (trim($heading->textContent) === '') $violations[] = $context.' contains an empty heading';
                    $level = $next;
                }
            }
        }
        $this->assertSame([], $violations);
    }
}
