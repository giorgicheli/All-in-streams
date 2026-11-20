@php
    $contact = $site['contact'];
    $oldContact = static fn (string $field): string => is_scalar(old($field)) ? (string) old($field) : '';
@endphp
<x-layout :title="$contact['page_title']" :description="$contact['description']">
    <div class="public-auth-layout">
        @include('site.navigation')
        <main class="contact-page" aria-labelledby="contact-title">
            <div class="container contact-grid">
                <div class="contact-intro">
                    <span class="contact-visual" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" focusable="false"><rect x="22" y="16" width="34" height="36" rx="11" fill="#dfe8ff"/><path d="m38 9 16 33-17-5-12 9L9 13l29-4Z" fill="#191c1f"/><path d="m9 13 28 24 1-28M37 37l17 5" stroke="#fff" stroke-width="2" stroke-linejoin="round"/><path d="m45 27 10-8-3 16" stroke="#0a5cff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <p class="contact-eyebrow">{{ $contact['eyebrow'] }}</p>
                    <h1 id="contact-title">{{ $contact['title'] }}</h1>
                    <p class="contact-description">{{ $contact['description'] }}</p>
                    @if($contact['email'])
                        <div class="contact-method">
                            <div><p>{{ $contact['email_label'] }}</p><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div>
                        </div>
                    @endif
                </div>
                <section class="contact-form-panel" aria-labelledby="contact-form-title">
                    <h2 class="sr-only" id="contact-form-title">{{ $contact['form_title'] }}</h2>
                    @if(session('contact_status'))<div class="contact-notice" role="status"><x-ui-icon name="check" size="20"/><p>{{ session('contact_status') }}</p></div>@endif
                    @if($errors->any())<div class="contact-errors" role="alert"><p>Please check the highlighted fields.</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <form method="POST" action="{{ route('contact.store') }}" aria-describedby="contact-privacy-note">
                        @csrf
                        <input type="hidden" name="contact_challenge" value="{{ $contactChallenge }}">
                        <div class="contact-trap" aria-hidden="true"><label for="contact-website">Leave this field blank</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off" value=""></div>
                        <div class="contact-form-grid">
                            <div class="contact-field">
                                <label class="sr-only" for="contact-name">{{ $contact['name_label'] }}</label>
                                <input id="contact-name" name="name" placeholder="{{ $contact['name_label'] }}" value="{{ $oldContact('name') }}" autocomplete="name" required minlength="2" maxlength="120" @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror>
                                @error('name')<p class="contact-field-error" id="contact-name-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="contact-field">
                                <label class="sr-only" for="contact-email">{{ $contact['email_field_label'] }}</label>
                                <input id="contact-email" name="email" type="email" placeholder="{{ $contact['email_field_label'] }}" value="{{ $oldContact('email') }}" autocomplete="email" required maxlength="254" @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror>
                                @error('email')<p class="contact-field-error" id="contact-email-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="contact-field contact-field-wide">
                                <label class="sr-only" for="contact-message">{{ $contact['message_label'] }}</label>
                                <textarea id="contact-message" name="message" placeholder="{{ $contact['message_label'] }}" rows="5" required minlength="10" maxlength="5000" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror>{{ $oldContact('message') }}</textarea>
                                @error('message')<p class="contact-field-error" id="contact-message-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="contact-submit-row"><p class="sr-only" id="contact-privacy-note">{{ $contact['privacy_note'] }}</p><button type="submit" class="btn btn-primary">{{ $contact['submit_label'] }}<x-ui-icon name="right" size="17"/></button></div>
                    </form>
                </section>
            </div>
        </main>
        @include('site.footer')
    </div>
</x-layout>
