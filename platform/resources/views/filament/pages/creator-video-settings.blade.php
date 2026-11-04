<x-filament-panels::page>
    @php($capabilities = $this->capabilities())
    <x-filament::section heading="Server capability">
        <p>Video verification: {{ $capabilities['verification'] ? 'Available' : 'Unavailable — registration video uploads cannot be verified until FFprobe is configured.' }}</p>
        <p>Compression: {{ $capabilities['compression'] ? 'Available' : 'Unavailable — keep compression off until FFmpeg is configured.' }}</p>
        <p>PHP upload limit: {{ $capabilities['upload'] }}. PHP request limit: {{ $capabilities['post'] }}. The server request limit must also allow the chosen upload size.</p>
        <p>Deployment request ceiling: {{ $capabilities['request'] }} MB (reserved below the web server limit for request overhead).</p>
        <p>Effective maximum for new uploads: {{ $capabilities['effective'] }} MB (the lower of your setting and the server limits).</p>
    </x-filament::section>
    <form wire:submit="save">
        {{ $this->form }}
        <div style="margin-top:24px"><x-filament::button type="submit" wire:loading.attr="disabled">Save video settings</x-filament::button></div>
    </form>
</x-filament-panels::page>
