<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <div style="margin-top:24px"><x-filament::button type="submit" wire:loading.attr="disabled">Save email settings</x-filament::button></div>
    </form>
    <x-filament::section heading="Test saved settings" description="Save your connection first. This sends one test email to the address below.">
        <form wire:submit="sendTest" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            <div style="min-width:240px;flex:1">
                <label for="smtp-test-recipient" style="display:block;margin-bottom:8px">Test recipient</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="smtp-test-recipient" type="email" wire:model="testRecipient" required autocomplete="email" />
                </x-filament::input.wrapper>
                @error('testRecipient')<p style="margin-top:8px;color:#ef4444">{{ $message }}</p>@enderror
            </div>
            <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">Send test email</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
