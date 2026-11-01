<x-filament-panels::page>
    <style>
        .website-editor-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}
        .website-editor-tabs,.website-editor-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .website-editor-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:24px;align-items:start}
        .website-editor-preview{position:sticky;top:88px;border:1px solid #293536;border-radius:12px;overflow:hidden;background:#080d0e}
        .website-editor-preview-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #293536;font-size:13px}
        .website-editor-preview-tools{display:flex;align-items:center;gap:8px;padding:10px 16px;border-bottom:1px solid #293536;font-size:12px;flex-wrap:wrap}
        .website-editor-viewport-button{border:1px solid #34413d;border-radius:6px;padding:6px 11px;color:#b7c5bc;background:#101718;cursor:pointer}
        .website-editor-viewport-button[aria-pressed="true"]{color:#c5f65c;border-color:#7d9e44;background:#192311}
        .website-editor-preview-select{background:#101718;color:#e7ede9;border:1px solid #34413d;border-radius:6px;padding:6px 10px;margin-left:auto}
        .website-editor-preview iframe{display:block;width:100%;height:75vh;min-height:540px;border:0;background:#090d0c}
        .website-editor-note{font-size:13px;line-height:1.6;color:#a1aaa6;margin-bottom:20px}
        .website-editor-history{margin-top:24px;padding:18px;border:1px solid #293536;border-radius:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
        .website-editor-history select{min-width:200px;background:#101718;color:#e7ede9;border:1px solid #34413d;border-radius:6px;padding:8px 12px}
        @media(max-width:1199px){.website-editor-grid{grid-template-columns:1fr}.website-editor-preview{position:static}.website-editor-preview iframe{height:640px}}
    </style>
    <div>
        <div class="website-editor-toolbar">
            <div class="website-editor-tabs" role="group" aria-label="Website content">
                <x-filament::button wire:click="selectPage('home')" :color="$editingPage === 'home' ? 'primary' : 'gray'">Home page</x-filament::button>
                <x-filament::button wire:click="selectPage('settings')" :color="$editingPage === 'settings' ? 'primary' : 'gray'">Site settings</x-filament::button>
            </div>
            <div class="website-editor-actions">
                <x-filament::button color="gray" icon="heroicon-o-eye" wire:click="saveDraft" wire:loading.attr="disabled">Save draft & preview</x-filament::button>
                <x-filament::button icon="heroicon-o-arrow-up-tray" wire:click="publish" wire:loading.attr="disabled">Publish changes</x-filament::button>
            </div>
        </div>
        <p class="website-editor-note">Draft changes stay private until you publish. Save the draft to refresh the page preview. Uploaded images are public website assets.</p>
        <div class="website-editor-grid">
            <section aria-label="Content editor">
                {{ $this->form }}
                @php $versions = $this->revisionOptions(); @endphp
                @if($versions)
                    <div class="website-editor-history">
                        <label for="website-revision">Published versions</label>
                        <select id="website-revision" wire:model.live="revisionId"><option value="">Choose a version</option>@foreach($versions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                        <x-filament::button color="gray" size="sm" wire:click="restoreRevision" :disabled="! $revisionId" wire:loading.attr="disabled">Restore to draft</x-filament::button>
                    </div>
                @endif
            </section>
            <aside class="website-editor-preview" aria-label="Draft preview" x-data="{mobile:false}">
                <div class="website-editor-preview-head"><strong>Draft preview</strong><a href="{{ $this->previewUrl() }}" target="_blank" rel="noopener noreferrer" style="color:#c5f65c">Open full preview ↗</a></div>
                <div class="website-editor-preview-tools">
                    <button type="button" class="website-editor-viewport-button" :aria-pressed="!mobile" @click="mobile=false">Fit</button>
                    <button type="button" class="website-editor-viewport-button" :aria-pressed="mobile" @click="mobile=true">Mobile</button>
                    @if($editingPage === 'settings')
                        <label for="website-preview-target" class="sr-only">Preview page</label>
                        <select id="website-preview-target" class="website-editor-preview-select" wire:model.live="previewTarget"><option value="home">Home page</option><option value="login">Sign-in page</option><option value="register">Registration page</option></select>
                    @endif
                </div>
                <iframe wire:key="website-preview-{{ $editingPage }}-{{ $previewVersion }}" src="{{ $this->previewUrl() }}" :style="mobile ? 'width:390px;max-width:100%;margin-inline:auto' : ''" title="Private website draft preview" loading="lazy" referrerpolicy="same-origin"></iframe>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
