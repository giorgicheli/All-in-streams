<?php

namespace App\Filament\Pages;

use App\Models\MailSetting;
use App\Services\SmtpConfiguration;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use UnitEnum;

class MailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Email settings';
    protected static ?string $title = 'Email settings';
    protected string $view = 'filament.pages.mail-settings';

    public array $data = [];
    public string $testRecipient = '';

    public static function canAccess(): bool { return auth()->user()?->fresh()?->role === 'admin'; }

    public function mount(): void
    {
        $this->guardStaff();
        $this->loadSettings();
        $this->testRecipient = auth()->user()->email;
    }

    public function hydrate(): void { $this->guardStaff(); }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('SMTP connection')->description('Password recovery uses these saved settings. Email stays unavailable until SMTP is enabled and complete.')->schema([
                Toggle::make('enabled')->label('Enable SMTP email')->columnSpanFull(),
                TextInput::make('host')->label('SMTP host')->placeholder('smtp.example.com')->maxLength(253),
                TextInput::make('port')->label('SMTP port')->numeric()->integer()->minValue(1)->maxValue(65535)->required(),
                Select::make('encryption')->label('Encryption')->options(['tls' => 'STARTTLS', 'ssl' => 'SSL / TLS'])->required()
                    ->helperText('Encrypted connections and valid server certificates are required.'),
                TextInput::make('username')->label('SMTP username')->maxLength(255)->autocomplete('off'),
                TextInput::make('password')->label('SMTP password')->password()->autocomplete('new-password')->maxLength(4096)
                    ->helperText('Leave blank to keep the saved password. Saved passwords are never displayed.'),
                Toggle::make('clear_password')->label('Remove the saved password')->helperText('Disable SMTP first, or enter a replacement password.'),
            ])->columns(2),
            Section::make('Sender')->schema([
                TextInput::make('from_address')->label('From email address')->email()->maxLength(254),
                TextInput::make('from_name')->label('From name')->required()->maxLength(160),
            ])->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        $this->guardStaff();
        try {
            app(SmtpConfiguration::class)->save(auth()->user(), $this->form->getState());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => [str_starts_with($field, 'data.') ? $field : 'data.'.$field => $messages])->all());
        } finally {
            $this->data['password'] = '';
            $this->data['clear_password'] = false;
        }
        $this->loadSettings();
        Notification::make()->title('Email settings saved')->success()->send();
    }

    public function sendTest(): void
    {
        $this->guardStaff();
        Validator::make(['testRecipient' => $this->testRecipient], ['testRecipient' => ['required', 'email:rfc', 'max:254']])->validate();
        try {
            app(SmtpConfiguration::class)->sendTest(auth()->user(), $this->testRecipient);
            Notification::make()->title('Test email sent')->success()->send();
        } catch (RuntimeException) {
            Notification::make()->title('Test email could not be sent')->body('Check the saved SMTP settings and server connection, then try again.')->danger()->send();
        }
    }

    private function loadSettings(): void
    {
        $this->form->fill([
            ...MailSetting::current()->only(['enabled', 'host', 'port', 'encryption', 'username', 'from_address', 'from_name']),
            'password' => '', 'clear_password' => false,
        ]);
    }

    private function guardStaff(): void { abort_unless(static::canAccess(), 403); }
}
