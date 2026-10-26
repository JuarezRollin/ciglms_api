<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'region_id', 'source_row', 'name', 'status', 'address', 'salutation', 'addressee',
    'designation', 'category', 'sp_remarks', 'contact_number', 'contact_person',
    'email', 'business_name', 'moa', 'jurisdiction', 'is_active',
])]
class ServiceProvider extends Model
{
    use LogsActivity;

    public function region(): BelongsTo
    {
        return $this->belongsTo(PsgcRegion::class, 'region_id', 'reg_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'moa' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
