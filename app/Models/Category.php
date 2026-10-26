<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'description', 'first_reviewer', 'second_reviewer'])]
class Category extends Model
{
    use LogsActivity;

    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class);
    }

    public function glTransmittals(): HasMany
    {
        return $this->hasMany(GlTransmittal::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
