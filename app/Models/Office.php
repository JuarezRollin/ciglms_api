<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['region_id', 'name'])]
class Office extends Model
{
    use LogsActivity;

    public function region(): BelongsTo
    {
        return $this->belongsTo(PsgcRegion::class, 'region_id', 'reg_id');
    }

    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
