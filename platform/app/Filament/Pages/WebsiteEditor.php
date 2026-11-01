<?php

namespace App\Filament\Pages;

use App\Models\SitePageRevision;
use App\Rules\SiteLink;
use App\Services\SiteContent;
use App\Support\Countries;
use App\Support\SiteContentDefaults;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use UnitEnum;

class WebsiteEditor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static string|UnitEnum|null $navigationGroup = 'Website';
    protected static ?string $navigationLabel = 'Website editor';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Website editor';
    protected string $view = 'filament.pages.website-editor';

    #[Locked]
    public string $editingPage = 'home';

    #[Locked]
    public int $previewVersion = 0;

    public array $data = [];
    public ?string $revisionId = null;
    public string $previewTarget = 'home';

    private const BLOCK_LABELS = [
        'hero' => ['Hero & partnership preview', 'heroicon-o-sparkles'],
        'geo_strip' => ['Audience countries', 'heroicon-o-globe-alt'],
        'audiences' => ['Companies, agencies & creators', 'heroicon-o-users'],
        'industries' => ['Industries', 'heroicon-o-puzzle-piece'],
        'process' => ['How it works', 'heroicon-o-list-bullet'],
        'results' => ['Campaign results gallery', 'heroicon-o-chart-bar'],
        'faq' => ['Questions & answers', 'heroicon-o-question-mark-circle'],
        'cta' => ['Call to action', 'heroicon-o-megaphone'],
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->guardStaff();
        $this->fillDraft();
    }

    public function hydrate(): void
    {
        $this->guardStaff();
    }

    public function getSubheading(): ?string
    {
        return 'Edit your content, arrange sections and preview the draft before publishing.';
    }

    public function form(Schema $schema): Schema
    {
        if ($this->editingPage === 'settings') {
            return $schema->components(static::fields(SiteContentDefaults::settings()))->statePath('data');
        }

        $blocks = [];
        foreach (SiteContentDefaults::home()['blocks'] as $block) {
            [$label, $icon] = self::BLOCK_LABELS[$block['type']];
            $blocks[] = Block::make($block['type'])->label($label)->icon($icon)->maxItems(1)
                ->schema(static::fields($block['data']))->columns(2);
        }

        return $schema->components([
            Section::make('Search & sharing')->schema(static::fields(SiteContentDefaults::home()['seo'], 'seo.'))->collapsible()->collapsed(),
            Builder::make('blocks')->label('Page sections')->blocks($blocks)
                ->blockIcons()->blockNumbers(false)->collapsible()->collapsed()
                ->reorderableWithButtons()->addActionLabel('Add section')->maxItems(8)
                ->helperText('Drag sections to reorder them. Turn off a section to hide it without deleting its content.'),
        ])->statePath('data');
    }

    public function selectPage(string $page): void
    {
        $this->guardStaff();
        abort_unless(in_array($page, ['home', 'settings'], true), 404);
        $this->editingPage = $page;
        if ($page === 'home') $this->previewTarget = 'home';
        $this->revisionId = null;
        unset($this->cachedSchemas['form']);
        $this->fillDraft();
        $this->previewVersion++;
    }

    public function saveDraft(): void
    {
        $this->guardStaff();
        $content = $this->form->getState();
        $this->persist(fn () => app(SiteContent::class)->saveDraft(auth()->user(), $this->editingPage, $content));
        $this->previewVersion++;
        Notification::make()->title('Draft saved')->body('The preview is updated. Your published site is unchanged.')->success()->send();
    }

    public function publish(): void
    {
        $this->guardStaff();
        $content = $this->form->getState();
        $this->persist(fn () => app(SiteContent::class)->publish(auth()->user(), $this->editingPage, $content));
        $this->previewVersion++;
        Notification::make()->title('Website content published')->body('The published site now uses this version.')->success()->send();
    }

    public function restoreRevision(): void
    {
        $this->guardStaff();
        $revision = SitePageRevision::query()->whereKey($this->revisionId)
            ->whereHas('page', fn ($query) => $query->where('slug', $this->editingPage))->firstOrFail();
        app(SiteContent::class)->restoreRevision(auth()->user(), $revision->id);
        $this->fillDraft();
        $this->previewVersion++;
        Notification::make()->title('Version restored to draft')->body('Review the preview, then publish when ready.')->success()->send();
    }

    public function revisionOptions(): array
    {
        $this->guardStaff();
        return SitePageRevision::query()->whereHas('page', fn ($query) => $query->where('slug', $this->editingPage))
            ->latest()->limit(20)->get()->mapWithKeys(fn (SitePageRevision $revision) => [
                $revision->id => $revision->created_at->format('d M Y, H:i').' · Version '.$revision->id,
            ])->all();
    }

    public function previewUrl(): string
    {
        $target = in_array($this->previewTarget, ['home', 'login', 'register'], true) ? $this->previewTarget : 'home';
        return url('/admin/website-preview/'.$this->editingPage).'?'.http_build_query(['v' => $this->previewVersion, 'view' => $target]);
    }

    public function updatedPreviewTarget(string $target): void
    {
        $this->guardStaff();
        abort_unless(in_array($target, ['home', 'login', 'register'], true), 404);
        $this->previewVersion++;
    }

    private function guardStaff(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    private function fillDraft(): void
    {
        $this->form->fill(app(SiteContent::class)->getDraft($this->editingPage));
    }

    private function persist(\Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $messages = [];
            $keys = array_keys($this->data['blocks'] ?? []);
            foreach ($exception->errors() as $path => $errors) {
                $path = preg_replace_callback('/^blocks\.(\d+)\./', fn (array $match) => 'blocks.'.($keys[(int) $match[1]] ?? $match[1]).'.', $path);
                $messages['data.'.$path] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }
    }

    /** Build human-labelled form fields from the same typed defaults used publicly. */
    private static function fields(array $defaults, string $prefix = ''): array
    {
        $fields = [];
        foreach ($defaults as $key => $value) {
            $name = $prefix.$key;
            $label = static::fieldLabel($key);
            if ($key === 'name' && isset($defaults['code'])) $label = 'Country name';
            if ($key === 'label' && count($defaults) === 1) $label = 'Region name';
            if (is_bool($value)) {
                $fields[] = Toggle::make($name)->label($key === 'enabled' ? 'Show this section' : $label)->default($value)->columnSpanFull();
                continue;
            }
            if ($key === 'image_path' || $key === 'logo_path') {
                $fields[] = FileUpload::make($name)->label($key === 'logo_path' ? 'Brand logo' : 'Section image')
                    ->disk('public')->directory('site-content')->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                    ->image()->maxSize(4096)->imagePreviewHeight('150')->columnSpanFull()
                    ->helperText('JPG, PNG, WebP or GIF, up to 4 MB. Leave empty to use the original design.');
                continue;
            }
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $sample = $value[0] ?? ['text' => ''];
                    $repeater = Repeater::make($name)->label($label)->default($value)->maxItems(static::listLimit($key))
                        ->collapsible()->collapsed()->reorderableWithButtons()->columnSpanFull();
                    if (is_array($sample)) {
                        $repeater->schema(static::fields($sample))->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['question'] ?? $state['label'] ?? $state['name'] ?? $state['text'] ?? null);
                    } else {
                        $repeater->simple(TextInput::make('text')->label($label)->required()->maxLength(5000));
                    }
                    $fields[] = $repeater;
                } else {
                    $fields[] = Section::make($label)->schema(static::fields($value, $name.'.'))->columns(2)->collapsible()->collapsed();
                }
                continue;
            }
            if ($key === 'icon') {
                $fields[] = Select::make($name)->label('Icon')->options(array_combine(SiteContentDefaults::ICONS, array_map(fn ($icon) => str($icon)->replace('_', ' ')->title()->toString(), SiteContentDefaults::ICONS)))
                    ->required()->searchable()->default($value);
                continue;
            }
            if ($key === 'code') {
                $fields[] = Select::make($name)->label('Country flag')->options(fn () => Countries::options())->required()->searchable()->default($value);
                continue;
            }
            $long = in_array($key, ['body', 'description', 'answer', 'privacy_note', 'channel_help', 'password_help', 'site_description'], true);
            $field = $long ? Textarea::make($name)->rows(3)->columnSpanFull() : TextInput::make($name);
            $field->label($label)->default($value)->maxLength(5000);
            if ($value !== '' && $value !== null) $field->required();
            if ($key === 'url' || str_ends_with($key, '_url')) $field->maxLength(2048)->rules([new SiteLink()]);
            if ($prefix === 'seo.') $field->maxLength($key === 'title' ? 200 : 500);
            $fields[] = $field;
        }
        return $fields;
    }

    private static function listLimit(string $key): int
    {
        return match ($key) {
            'items', 'countries', 'preview_countries' => 30,
            default => 12,
        };
    }

    private static function fieldLabel(string $key): string
    {
        return [
            'seo' => 'Search & sharing', 'brand_name' => 'Brand name', 'brand_tagline' => 'Brand tagline',
            'navigation' => 'Main navigation', 'header' => 'Header buttons', 'footer' => 'Footer',
            'auth_story' => 'Sign-in & registration story', 'auth' => 'Account page copy', 'labels' => 'Form labels',
            'login' => 'Sign-in page', 'register' => 'Registration page', 'eyebrow' => 'Small heading',
            'title_line_1' => 'Headline · first line', 'title_line_2' => 'Headline · second line',
            'primary_label' => 'Primary button text', 'primary_url' => 'Primary button link',
            'secondary_label' => 'Secondary button text', 'secondary_url' => 'Secondary button link',
            'url' => 'Link', 'label' => 'Link text', 'code' => 'Country code', 'name' => 'Name',
            'image_alt' => 'Image description', 'preview_countries' => 'Preview card flags',
            'countries' => 'Countries in the strip', 'perks' => 'Partnership benefits',
        ][$key] ?? str($key)->replace('_', ' ')->headline()->toString();
    }
}
