<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerEnquiryRequest;
use App\Models\PartnerEnquiry;
use App\Rules\SocialProfileUrl;
use App\Rules\StreamingChannelUrl;
use App\Rules\TelegramHandle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;

class PartnerEnquiryController extends Controller
{
    public function create(Request $request): Response
    {
        $kind = in_array($request->query('type'), array_keys(PartnerEnquiry::KINDS), true) ? $request->query('type') : 'brand';
        $creatorType = in_array($request->query('creator_type'), array_keys(PartnerEnquiry::CREATOR_TYPES), true) ? $request->query('creator_type') : 'streamer';
        return response()->view('site.talk-to-us', [
            'kind' => $kind,
            'creatorType' => $creatorType,
            'countries' => PartnerEnquiry::countries(),
            'currencies' => PartnerEnquiry::CURRENCIES,
            'submissionKey' => (string) Str::uuid(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(StorePartnerEnquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['age_confirmed']);
        $data['telegram'] = TelegramHandle::canonicalize($data['telegram']);
        if (isset($data['twitch_url'])) $data['twitch_url'] = StreamingChannelUrl::canonicalize($data['twitch_url'], 'Twitch');
        if (isset($data['kick_url'])) $data['kick_url'] = StreamingChannelUrl::canonicalize($data['kick_url'], 'Kick');
        if (isset($data['instagram_url'])) $data['instagram_url'] = SocialProfileUrl::canonicalize($data['instagram_url'], 'Instagram');
        $data['age_confirmed_at'] = now();
        PartnerEnquiry::firstOrCreate(['submission_key' => $data['submission_key']], $data);
        return redirect('/talk-to-us?type='.$data['kind'].'#success')->with('enquiry_submitted', true);
    }
}
