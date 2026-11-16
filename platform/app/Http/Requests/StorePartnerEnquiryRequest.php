<?php

namespace App\Http\Requests;

use App\Models\PartnerEnquiry;
use App\Rules\SocialProfileUrl;
use App\Rules\StreamingChannelUrl;
use App\Rules\TelegramHandle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerEnquiryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $changes = [];
        foreach (['name', 'email', 'telegram', 'website', 'requirements', 'twitch_url', 'kick_url', 'instagram_url'] as $field) {
            if (is_string($this->input($field))) $changes[$field] = trim($this->input($field));
        }
        if (isset($changes['email'])) $changes['email'] = mb_strtolower($changes['email']);
        if (is_string($this->input('currency'))) $changes['currency'] = strtoupper(trim($this->input('currency')));
        if (is_string($this->input('audience_geos'))) {
            $countries = array_combine(array_map('mb_strtolower', PartnerEnquiry::countries()), PartnerEnquiry::countries());
            $changes['audience_geos'] = array_map(
                fn (string $country) => $countries[mb_strtolower(trim($country))] ?? trim($country),
                explode(',', $this->input('audience_geos'))
            );
        }
        $this->merge($changes);
    }

    public function rules(): array
    {
        $creator = $this->input('kind') === 'creator';
        $agency = $this->input('kind') === 'agency';
        $streamer = $creator && in_array($this->input('creator_type'), ['streamer', 'both'], true);
        $influencer = $creator && in_array($this->input('creator_type'), ['influencer', 'both'], true);
        $money = ['numeric', 'min:0.01', 'max:1000000000', 'decimal:0,2'];
        return [
            'submission_key' => ['required', 'uuid'],
            'kind' => ['required', 'string', Rule::in(array_keys(PartnerEnquiry::KINDS))],
            'creator_type' => $creator ? ['required', 'string', Rule::in(array_keys(PartnerEnquiry::CREATOR_TYPES))] : ['exclude'],
            'name' => ['required', 'string', 'max:120'],
            'email' => $creator ? ['nullable', 'email', 'max:255'] : ['required', 'email', 'max:255'],
            'telegram' => ['required', 'string', 'max:100', new TelegramHandle],
            'website' => $creator ? ['exclude'] : ['required', 'string', 'max:2048', 'url:http,https', function (string $attribute, mixed $value, \Closure $fail): void {
                $url = is_string($value) ? parse_url($value) : false;
                if (!is_array($url) || isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20]/', $value)) $fail('Enter a company or agency website URL without login credentials.');
            }],
            'audience_geos' => ['required', 'array', 'min:'.($creator ? 2 : 1), 'max:'.($creator ? 2 : 30)],
            'audience_geos.*' => ['required', 'string', 'distinct:strict', Rule::in(PartnerEnquiry::countries())],
            'streams' => $agency ? ['required', 'integer', 'min:1', 'max:10000'] : ['exclude'],
            'budget' => $creator ? ['exclude'] : array_merge($agency ? ['required'] : ['nullable'], $money),
            'currency' => array_merge($creator || $agency ? ['required'] : ['nullable', 'required_with:budget'], ['string', Rule::in(PartnerEnquiry::CURRENCIES)]),
            'budget_scope' => $agency ? ['required', 'string', Rule::in(['total', 'per_stream'])] : ['exclude'],
            'requirements' => ['nullable', 'string', 'max:5000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && (strip_tags($value) !== $value || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value))) $fail('Use plain text without HTML or executable markup.');
            }],
            'twitch_url' => $streamer ? ['nullable', 'required_without:kick_url', 'string', 'max:255', new StreamingChannelUrl('Twitch')] : ['exclude'],
            'kick_url' => $streamer ? ['nullable', 'required_without:twitch_url', 'string', 'max:255', new StreamingChannelUrl('Kick')] : ['exclude'],
            'instagram_url' => $influencer ? ['required', 'string', 'max:255', new SocialProfileUrl('Instagram')] : ['exclude'],
            'stream_rate' => $streamer ? ['required', ...$money] : ['exclude'],
            'story_rate' => $influencer ? ['required', ...$money] : ['exclude'],
            'reel_rate' => $influencer ? ['required', ...$money] : ['exclude'],
            'age_confirmed' => ['required', 'accepted'],
        ];
    }

    public function attributes(): array
    {
        return ['audience_geos' => 'audience countries', 'audience_geos.*' => 'audience country', 'age_confirmed' => '18+ confirmation', 'budget_scope' => 'budget basis', 'stream_rate' => 'price per stream', 'story_rate' => 'price per Instagram story', 'reel_rate' => 'price per Instagram Reel'];
    }
}
