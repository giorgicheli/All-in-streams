<?php
namespace App\Console\Commands;

use App\Models\PublicCampaignResult;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

class SeedPublicResults extends Command
{
    protected $signature='showcase:public-results {--force : Publish clearly marked simulated results on production}';
    protected $description='Add four explicitly simulated completed campaign cards without changing existing results';
    public function handle(): int
    {
        if(app()->environment('production')&&!$this->option('force')) { $this->error('Production publication requires --force.'); return self::FAILURE; }
        $admin=User::where('role','admin')->orderBy('is_demo')->first();
        if(!$admin) { $this->error('Create an administrator before adding sample results.'); return self::FAILURE; }
        DB::transaction(function()use($admin){
            $rows=[
                ['canada-twitch','Canada','North America','Twitch',4,0,128400,2480,64,6],
                ['germany-kick','Germany','Europe','Kick',6,0,212600,4160,128,12],
                ['brazil-instagram','Brazil','LATAM','Instagram',0,8,356800,5920,172,18],
                ['france-youtube','France','Europe','YouTube',0,6,180400,3520,84,24],
            ];
            foreach($rows as [$key,$country,$region,$platform,$streams,$posts,$views,$clicks,$conversions,$days]) {
                $key='public-demo-v1-'.$key;
                $existing=PublicCampaignResult::where('demo_key',$key)->first();
                if($existing) { if(!$existing->is_demo) throw new LogicException('A sample identifier is already used by an actual result.'); continue; }
                PublicCampaignResult::create(['demo_key'=>$key,'internal_title'=>'DEMO completed '.$country.' '.$platform.' result','country'=>$country,'region'=>$region,'platform'=>$platform,'completed_on'=>today()->subDays($days),'streams'=>$streams,'posts'=>$posts,'views'=>$views,'clicks'=>$clicks,'conversions'=>$conversions,'is_demo'=>true,'is_verified'=>true,'is_published'=>true,'reviewed_by'=>$admin->id]);
            }
        });
        $this->info('Four demo result cards are available. Existing edits and publication choices were preserved.');
        return self::SUCCESS;
    }
}
