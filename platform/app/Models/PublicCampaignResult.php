<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Explicit public aggregates; deliberately has no relationship to a private brief or creator. */
class PublicCampaignResult extends Model
{
    public const REGIONS = ['Europe', 'North America', 'LATAM', 'Asia Pacific', 'Middle East', 'Africa'];
    public const PLATFORMS = ['Twitch', 'Kick', 'Instagram', 'Facebook', 'YouTube', 'TikTok'];
    public const PUBLIC_COLUMNS = ['id', 'country', 'region', 'platform', 'completed_on', 'streams', 'posts', 'views', 'clicks', 'conversions', 'is_demo'];
    protected $fillable = ['internal_title', 'demo_key', 'country', 'region', 'platform', 'completed_on', 'streams', 'posts', 'views', 'clicks', 'conversions', 'is_demo', 'is_verified', 'is_published', 'reviewed_by'];
    protected $attributes = ['streams'=>0,'posts'=>0,'views'=>0,'clicks'=>0,'conversions'=>0,'is_demo'=>false,'is_verified'=>false,'is_published'=>false];

    protected function casts(): array
    {
        return ['completed_on'=>'date','streams'=>'integer','posts'=>'integer','views'=>'integer','clicks'=>'integer','conversions'=>'integer','is_demo'=>'boolean','is_verified'=>'boolean','is_published'=>'boolean','verified_at'=>'datetime','published_at'=>'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $result): void {
            $data = $result->attributesToArray();
            $rules = ['internal_title'=>['required','string','max:180'], 'country'=>['required',Rule::in(CreatorProfile::AUDIENCE_COUNTRIES)], 'region'=>['required',Rule::in(self::REGIONS)], 'platform'=>['required',Rule::in(self::PLATFORMS)], 'completed_on'=>['required','date','before_or_equal:today']];
            foreach (['streams','posts','views','clicks','conversions'] as $field) $rules[$field]=['required','integer','min:0','max:2147483647'];
            $rules['clicks'][]='lte:views'; $rules['conversions'][]='lte:clicks';
            foreach (['is_demo','is_verified','is_published'] as $field) $rules[$field]=['required','boolean'];
            validator($data,$rules)->validate();
            if ($result->streams + $result->posts < 1) throw ValidationException::withMessages(['streams'=>'Record at least one completed stream or post.']);
            if ($result->is_published && !$result->is_verified) throw ValidationException::withMessages(['is_published'=>'Review the figures before publishing them.']);
            if ($result->is_verified) {
                $reviewer = auth()->user()?->role === 'admin' ? auth()->id() : $result->reviewed_by;
                if (!$reviewer || !User::whereKey($reviewer)->where('role','admin')->exists()) throw ValidationException::withMessages(['is_verified'=>'An administrator must review the figures.']);
                $result->reviewed_by = $reviewer;
                if (!$result->verified_at || $result->isDirty(['streams','posts','views','clicks','conversions','country','region','platform','completed_on'])) $result->verified_at=now();
            } else { $result->verified_at=null; $result->reviewed_by=null; }
            if ($result->is_published && (!$result->published_at || $result->isDirty('is_published'))) $result->published_at=now();
            if (!$result->is_published) $result->published_at=null;
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published',true)->where('is_verified',true)->whereNotNull('verified_at')->whereNotNull('reviewed_by')->whereDate('completed_on','<=',today());
    }
}
