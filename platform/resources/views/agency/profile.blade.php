<x-portal-layout title="Agency profile">
    <div class="page-heading"><div><span class="eyebrow">YOUR AGENCY, DISTINCT FROM THE CLIENT</span><h1>Agency profile</h1><p>Tell the team how your agency works and the markets your roster serves.</p></div>@if($profile)<x-status :value="$profile->status"/>@endif</div>
    @if($errors->any())<div class="alert error"><span>{{ $errors->first() }}</span></div>@endif
    <form method="POST" action="{{ route('agency.profile.save') }}" class="panel wizard-panel">@csrf
        <div class="form-grid">
            <div class="form-field"><label for="agency-company">Agency name</label><input id="agency-company" name="company" value="{{ old('company',auth()->user()->company) }}" required maxlength="180"></div>
            <div class="form-field"><label for="agency-country">Country</label><input id="agency-country" name="country" value="{{ old('country',$profile?->country) }}" required maxlength="80"></div>
            <div class="form-field span-two"><label for="agency-website">Agency website (optional)</label><input id="agency-website" name="website" type="url" value="{{ old('website',$profile?->website) }}" placeholder="https://your-agency.com" maxlength="255"></div>
            <fieldset class="form-field span-two"><legend class="field-label">How you work with All In Streams</legend><div class="choice-grid">@foreach(['supply'=>'Supply roster talent','request'=>'Request creators for clients'] as $value=>$label)<label class="choice-label"><input type="checkbox" name="services[]" value="{{ $value }}" @checked(in_array($value,old('services',$profile?->services ?? ['supply','request']),true))>{{ $label }}</label>@endforeach</div></fieldset>
            <div class="form-field"><label for="agency-languages">Content languages</label><input id="agency-languages" name="languages" value="{{ is_array(old('languages'))?implode(', ',old('languages')):old('languages',implode(', ',$profile?->languages ?? [])) }}" placeholder="English, German" required><p class="field-help">Separate multiple languages with commas.</p></div>
            <div class="form-field"><label for="agency-geos">Audience markets</label><input id="agency-geos" name="audience_geos" value="{{ is_array(old('audience_geos'))?implode(', ',old('audience_geos')):old('audience_geos',implode(', ',$profile?->audience_geos ?? [])) }}" placeholder="Germany, Austria, Switzerland" required></div>
            <fieldset class="form-field span-two"><legend class="field-label">Platforms</legend><div class="choice-grid">@foreach(['Twitch','Kick','Facebook','Instagram','YouTube','TikTok'] as $platform)<label class="choice-label"><input type="checkbox" name="platforms[]" value="{{ $platform }}" @checked(in_array($platform,old('platforms',$profile?->platforms ?? []),true))>{{ $platform }}</label>@endforeach</div></fieldset>
            <div class="form-field span-two"><label for="agency-bio">Agency overview</label><textarea id="agency-bio" name="bio" maxlength="3000">{{ old('bio',$profile?->bio) }}</textarea></div>
        </div>
        <div class="privacy-note"><x-ui-icon name="shield"/><span>Profile changes require staff review. Business clients never see your private supplier compensation or counteroffers.</span></div>
        <button class="btn btn-primary">Submit agency profile for review <x-ui-icon name="right" size="16"/></button>
    </form>
</x-portal-layout>
