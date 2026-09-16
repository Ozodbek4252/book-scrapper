<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * An install of the mobile app.
 *
 * Sanctum tokens are polymorphic, so a device can hold one without pretending
 * to be a user account. It is Authenticatable only so the framework can treat
 * it as the current caller; it has no password and cannot sign in anywhere.
 */
#[Fillable(['uuid', 'platform', 'app_version', 'last_seen_at', 'blocked_at'])]
class Device extends Model implements Authenticatable
{
    /** @use HasFactory<DeviceFactory> */
    use AuthenticatableTrait, HasApiTokens, HasFactory;

    /**
     * @return HasMany<BookSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(BookSubmission::class);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }
}
