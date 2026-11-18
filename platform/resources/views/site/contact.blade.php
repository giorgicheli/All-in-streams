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
                    <h1 id="contact-title">{{ $contact['title'] }}</h1>
                    <p class="contact-description">{{ $contact['description'] }}</p>
                    @if($contact['email'])
                        <div class="contact-method">
                            <span class="contact-mail-icon"><x-ui-icon name="mail" size="24"/></span>
                            <div><p>{{ $contact['email_label'] }}</p><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div>
                        </div>
                    @endif
                </div>
                <section class="contact-form-panel" aria-labelledby="contact-form-title">
                    <h2 id="contact-form-title">{{ $contact['form_title'] }}</h2>
                    @if(session('contact_status'))<div class="contact-notice" role="status"><x-ui-icon name="check" size="20"/><p>{{ session('contact_status') }}</p></div>@endif
                    @if($errors->any())<div class="contact-errors" role="alert"><p>Please check the highlighted fields.</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <form method="POST" action="{{ route('contact.store') }}">
                        @csrf
                        <input type="hidden" name="contact_challenge" value="{{ $contactChallenge }}">
                        <div class="contact-trap" aria-hidden="true"><label for="contact-website">Leave this field blank</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off" value=""></div>
                        <div class="contact-form-grid">
                            <div class="contact-field">
                                <label for="contact-name">{{ $contact['name_label'] }}</label>
                                <input id="contact-name" name="name" value="{{ $oldContact('name') }}" autocomplete="name" required minlength="2" maxlength="120" @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror>
                                @error('name')<p class="contact-field-error" id="contact-name-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="contact-field">
                                <label for="contact-email">{{ $contact['email_field_label'] }}</label>
                                <input id="contact-email" name="email" type="email" value="{{ $oldContact('email') }}" autocomplete="email" required maxlength="254" @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror>
                                @error('email')<p class="contact-field-error" id="contact-email-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="contact-field contact-field-wide">
                                <label for="contact-subject">{{ $contact['subject_label'] }}</label>
                                <input id="contact-subject" name="subject" value="{{ $oldContact('subject') }}" required minlength="3" maxlength="160" @error('subject') aria-invalid="true" aria-describedby="contact-subject-error" @enderror>
                                @error('subject')<p class="contact-field-error" id="contact-subject-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="contact-field contact-field-wide">
                                <label for="contact-message">{{ $contact['message_label'] }}</label>
                                <textarea id="contact-message" name="message" rows="6" required minlength="10" maxlength="5000" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror>{{ $oldContact('message') }}</textarea>
                                @error('message')<p class="contact-field-error" id="contact-message-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="contact-submit-row"><p>{{ $contact['privacy_note'] }}</p><button type="submit" class="btn btn-primary">{{ $contact['submit_label'] }}<x-ui-icon name="arrow" size="17"/></button></div>
                    </form>
                </section>
            </div>
        </main>
        @include('site.footer')
    </div>
</x-layout>
