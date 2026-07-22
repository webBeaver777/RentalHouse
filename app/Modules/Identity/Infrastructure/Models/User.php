<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Participation\Infrastructure\Models\Participant;
use App\Modules\Property\Infrastructure\Models\Property;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'preferred_locale',
        'is_admin',
    ];

    protected $attributes = [
        'preferred_locale' => 'pl',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Determine if the user can access Filament panel.
     *
     * G6 SECURITY: Only admins can access /admin panel.
     * Without this check, any authenticated user could access admin area.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Get user's preferred locale or default.
     */
    public function getLocale(): string
    {
        return $this->preferred_locale ?? config('app.locale', 'pl');
    }

    /**
     * Get user's properties.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /**
     * Get user's protocol participations.
     */
    public function participations(): HasMany
    {
        return $this->hasMany(Participant::class);
    }
}
