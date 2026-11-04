<?php

namespace App\Services;

use App\Models\SitePage;
use App\Models\SitePageRevision;
use App\Models\User;
use App\Rules\SiteLink;
use App\Rules\LegalRichText;
use App\Support\SiteContentDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteContent
{
    public function getPublished(string $slug = 'home'): array
    {
        $defaults = SiteContentDefaults::for($slug);
        if (! Schema::hasTable('site_pages')) return $defaults;
        $content = SitePage::where('slug', $slug)->value('published_content') ?? $defaults;
        return $slug === 'settings' ? $this->normalizeSettings($content) : $content;
    }

    public function getDraft(string $slug = 'home'): array
    {
        $defaults = SiteContentDefaults::for($slug);
        if (! Schema::hasTable('site_pages')) return $defaults;
        $content = SitePage::where('slug', $slug)->value('draft_content') ?? $defaults;
        return $slug === 'settings' ? $this->normalizeSettings($content) : $content;
    }

    public function saveDraft(User $actor, string $slug, array $content): SitePage
    {
        $this->authorize($actor);
        $content = $this->validate($slug, $content);
        return DB::transaction(function () use ($actor, $slug, $content) {
            $page = $this->editablePage($slug);
            $page->update(['draft_content' => $content, 'updated_by' => $actor->id]);
            return $page->refresh();
        });
    }

    public function publish(User $actor, string $slug, ?array $content = null): SitePage
    {
        $this->authorize($actor);
        SiteContentDefaults::for($slug);
        return DB::transaction(function () use ($actor, $slug, $content) {
            $page = $this->editablePage($slug);
            $content = $this->validate($slug, $content ?? $page->draft_content);
            $previous = $page->published_content ?? SiteContentDefaults::for($slug);
            $lastRevision = $page->revisions()->latest('id')->first();
            if (! $lastRevision || $lastRevision->content !== $previous) {
                $page->revisions()->create(['content' => $previous, 'published_by' => $page->updated_by]);
            }
            $page->update(['draft_content' => $content, 'published_content' => $content, 'published_at' => now(), 'updated_by' => $actor->id]);
            if ($previous !== $content) {
                $page->revisions()->create(['content' => $content, 'published_by' => $actor->id]);
            }
            return $page->refresh();
        });
    }

    public function restoreRevision(User $actor, int $revisionId): SitePage
    {
        $this->authorize($actor);
        $revision = SitePageRevision::with('page')->findOrFail($revisionId);
        return $this->saveDraft($actor, $revision->page->slug, $revision->content);
    }

    /** Validate a typed content tree; arbitrary markup, files and block types are not accepted. */
    public function validate(string $slug, array $content): array
    {
        $defaults = SiteContentDefaults::for($slug);
        $rules = [];
        if ($slug === 'settings') $content = $this->normalizeSettings($content);
        if ($slug === 'settings') {
            $this->objectRules($defaults, '', $rules);
        } elseif (in_array($slug, ['privacy', 'terms'], true)) {
            $this->objectRules(['seo' => $defaults['seo'], 'title' => $defaults['title']], '', $rules);
            $rules['seo.title'][] = 'max:200';
            $rules['seo.description'][] = 'max:500';
            $rules['title'][] = 'max:200';
            $rules['body'] = ['required', 'array', new LegalRichText];
        } else {
            $rules = [
                'seo' => ['required', 'array:title,description'],
                'seo.title' => ['required', 'string', 'max:200'],
                'seo.description' => ['required', 'string', 'max:500'],
                'blocks' => ['required', 'array', 'min:1', 'max:8'],
                'blocks.*' => ['required', 'array:type,data'],
                'blocks.*.type' => ['required', Rule::in(SiteContentDefaults::BLOCK_TYPES), 'distinct'],
                'blocks.*.data' => ['required', 'array'],
            ];
            $templates = collect($defaults['blocks'])->keyBy('type');
            foreach (is_array($content['blocks'] ?? null) ? $content['blocks'] : [] as $index => $block) {
                if (is_array($block) && is_string($block['type'] ?? null) && isset($templates[$block['type']])) {
                    $this->objectRules($templates[$block['type']]['data'], 'blocks.'.$index.'.data', $rules);
                }
            }
        }
        $rootKeys = array_keys($defaults);
        $unknown = array_diff(array_keys($content), $rootKeys);
        if ($unknown) throw ValidationException::withMessages(['content' => 'Unsupported website content fields.']);
        Validator::make($content, $rules)->validate();
        // Validation checks every key above; retain the authored ordering of the
        // typed tree instead of Laravel moving wildcard repeaters to its end.
        $validated = $content;
        array_walk_recursive($validated, function (&$value, $key): void {
            if (in_array($key, ['image_path', 'logo_path'], true)) {
                $value = $value === '' ? null : preg_replace('~^/storage/~', '', $value ?? '');
                if ($value === '') $value = null;
            } elseif ($key === 'image_alt' && $value === null) {
                $value = '';
            } elseif ($key === 'enabled') {
                $value = (bool) $value;
            } elseif ($key === 'suffix' && $value === null) {
                $value = '';
            }
        });
        return $validated;
    }

    private function objectRules(array $template, string $prefix, array &$rules): void
    {
        if ($prefix !== '') $rules[$prefix] = ['required', 'array:'.implode(',', array_keys($template))];
        foreach ($template as $key => $value) {
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $limit = match ($key) { 'countries', 'preview_countries', 'items' => 30, default => 12 };
                    $rules[$path] = ['required', 'array', 'min:1', 'max:'.$limit];
                    if ($value) $this->objectRules($value[0], $path.'.*', $rules);
                } else $this->objectRules($value, $path, $rules);
            } elseif (is_int($value)) {
                $rules[$path] = ['required', 'integer', 'min:0', 'max:10000000'];
            } elseif (is_bool($value)) {
                $rules[$path] = ['required', 'boolean'];
            } elseif ($value === null) {
                $rules[$path] = ['present', 'nullable', 'string', 'max:255', function (string $attribute, mixed $media, \Closure $fail): void {
                    if ($media !== null && $media !== '' && (! is_string($media) || ! preg_match('~^(?:/storage/)?site-content/[A-Za-z0-9][A-Za-z0-9/_-]*\.(?:png|jpg|jpeg|webp|gif)$~D', $media))) {
                        $fail('Choose an uploaded website image.');
                    }
                }];
            } else {
                $rules[$path] = array_merge($value === '' || $key === 'suffix' ? ['present', 'nullable'] : ['required'], ['string', 'max:5000', function (string $attribute, mixed $text, \Closure $fail): void {
                    if (is_string($text) && (strip_tags($text) !== $text || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $text))) $fail('Use plain text without HTML or executable markup.');
                }]);
                if ($key === 'icon') $rules[$path][] = Rule::in(SiteContentDefaults::ICONS);
                if ($key === 'code') $rules[$path][] = 'regex:/^[A-Z]{2}$/D';
                if ($key === 'url' || str_ends_with($key, '_url')) {
                    $rules[$path][] = 'max:2048';
                    $rules[$path][] = new SiteLink();
                }
            }
        }
    }

    private function editablePage(string $slug): SitePage
    {
        $defaults = SiteContentDefaults::for($slug);
        $title = match ($slug) { 'home' => 'Home page', 'privacy' => 'Privacy policy', 'terms' => 'Terms & conditions', default => 'Site settings' };
        SitePage::firstOrCreate(['slug' => $slug], ['title' => $title, 'draft_content' => $defaults]);
        return SitePage::where('slug', $slug)->lockForUpdate()->firstOrFail();
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === 'admin', 403);
    }

    /** Keep authored lists intact while adding new settings to older saved versions. */
    private function normalizeSettings(array $content): array
    {
        if (isset($content['footer']) && is_array($content['footer'])) unset($content['footer']['statement']);
        $merge = function (array $defaults, array $authored) use (&$merge): array {
            foreach ($defaults as $key => $value) {
                if (! array_key_exists($key, $authored)) $authored[$key] = $value;
                elseif (is_array($value) && ! array_is_list($value) && is_array($authored[$key])) $authored[$key] = $merge($value, $authored[$key]);
            }
            return $authored;
        };
        return $merge(SiteContentDefaults::settings(), $content);
    }
}
