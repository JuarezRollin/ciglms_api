<?php

namespace App\Models;

use App\Support\NameInitials;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'initials', 'designation', 'office', 'minimum_amount', 'maximum_amount', 'is_active'])]
#[Hidden(['p12_path', 'signature_image_path', 'pnpki_password'])]
class Signatory extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        static::saving(function (self $signatory): void {
            $signatory->initials = mb_strtoupper(trim($signatory->initials ?: NameInitials::fromName($signatory->name)));
        });
    }

    #[Scope]
    protected function coveringAmount(Builder $query, string|int|float $amount): Builder
    {
        return $query
            ->where('minimum_amount', '<=', $amount)
            ->where('maximum_amount', '>=', $amount);
    }

    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class);
    }

    public function signatoryRoutes(): HasMany
    {
        return $this->hasMany(SignatoryRoute::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'pnpki_password' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
