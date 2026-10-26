<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['full_name', 'sex', 'address'])]
class Client extends Model
{
    use LogsActivity;

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
            'sex' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
