<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'company',
        'account_type',
        'is_demo',
        'telegram',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(Brief::class);
    }

    public function creatorProfile(): HasOne
    {
        return $this->hasOne(CreatorProfile::class)->withTrashed();
    }

    public function agencyProfile(): HasOne { return $this->hasOne(AgencyProfile::class); }

    public function agencyTalent(): HasMany { return $this->hasMany(AgencyTalent::class, 'agency_user_id'); }

    public function internalFiles(): HasMany { return $this->hasMany(UserFile::class); }

    public function getAccountTypeLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'creator' => $this->creatorProfile?->creator_type === 'influencer' ? 'Influencer' : 'Streamer',
            'agency' => 'Agency',
            default => $this->account_type === 'agency' ? 'Agency' : 'Business',
        };
    }
}
