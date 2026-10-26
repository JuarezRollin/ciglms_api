<?php

namespace App\Models;

use App\Support\NameInitials;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'position', 'initials', 'email', 'password', 'office_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasActivity, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->initials = mb_strtoupper(trim($user->initials ?: NameInitials::fromName($user->name)));
        });
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class, 'created_by');
    }

    public function office(): HasOne
    {
        return $this->hasOne(Office::class);
    }

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
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
