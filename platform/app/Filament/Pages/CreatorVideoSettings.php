<?php

namespace App\Filament\Pages;

use App\Models\CreatorMediaSetting;
use App\Services\CreatorProofVideo;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CreatorVideoSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Video uploads';
    protected static ?string $title = 'Creator video uploads';
    protected string $view = 'filament.pages.creator-video-settings';
    public array $data = [];

    public static function canAccess(): bool { return auth()->user()?->role === 'admin'; }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(CreatorMediaSetting::current()->only(['max_upload_mb', 'max_duration_seconds', 'compression_enabled', 'compression_crf', 'compression_max_width']));
    }

    public function hydrate(): void { abort_unless(static::canAccess(), 403); }

    public function form(Schema $schema): Schema
    {
        $requestLimit = app(CreatorProofVideo::class)->requestMaxMb();
        return $schema->components([
            Section::make('Verification video limits')->description('Applies to new uploads from streamers and influencers. Videos stay private and can be viewed or deleted only by administrators.')->schema([
                TextInput::make('max_upload_mb')->label('Maximum upload size (MB)')->numeric()->integer()->minValue(1)->maxValue($requestLimit)->required()->helperText('Allowed range: 1–'.$requestLimit.' MB. Existing videos are not changed when this limit is lowered.'),
                TextInput::make('max_duration_seconds')->label('Maximum video duration (seconds)')->numeric()->integer()->minValue(5)->maxValue(120)->required(),
            ])->columns(2),
            Section::make('Compression')->description('A compressed MP4 replaces the upload only when it is smaller. The upload limit is checked before compression.')->schema([
                Toggle::make('compression_enabled')->label('Compress new videos')->live(),
                Select::make('compression_max_width')->label('Maximum video width')->options([640 => '640 px', 960 => '960 px', 1280 => '1280 px', 1920 => '1920 px'])->required(),
                TextInput::make('compression_crf')->label('Compression quality (CRF)')->numeric()->integer()->minValue(18)->maxValue(35)->required()->helperText('18 keeps more detail; 35 makes smaller files. Recommended: 28.'),
            ])->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        if ($data['compression_enabled'] && ! app(CreatorProofVideo::class)->available('ffmpeg')) {
            throw ValidationException::withMessages(['data.compression_enabled' => 'FFmpeg is not available to PHP. Ask the server administrator to enable the configured video processor before turning compression on.']);
        }
        CreatorMediaSetting::current()->update($data);
        Notification::make()->title('Video upload settings saved')->success()->send();
    }

    public function capabilities(): array
    {
        return ['verification' => app(CreatorProofVideo::class)->available('ffprobe'), 'compression' => app(CreatorProofVideo::class)->available('ffmpeg'), 'upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'), 'request' => app(CreatorProofVideo::class)->requestMaxMb(), 'effective' => app(CreatorProofVideo::class)->maxUploadMb()];
    }
}
