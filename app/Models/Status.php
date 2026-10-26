<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'is_active'])]
class Status extends Model
{
    use LogsActivity;

    public const FOR_REVIEW_BY_CRD_ID = 1;

    public const APPROVED_ID = 2;

    public const RELEASED_ID = 3;

    public const ON_HOLD_ID = 4;

    public const CANCELLED_BY_CRD_ID = 5;

    public const FOR_ENDORSEMENT_ID = 6;

    public const AWAITING_SOA_ID = 7;

    public const FOR_PULLOUT_ID = 8;

    public const FOR_CREATION_ID = 9;

    public const FOR_COMPLIANCE_ID = 10;

    public const FOR_REVIEW_BY_RMCD_ID = 11;

    public const ON_PROCESS_ID = 12;

    public const FOR_OBLIGATION_ID = 13;

    public const FOR_PRE_AUDIT_ID = 14;

    public const FOR_CREDITING_ID = 15;

    public const PAID_ID = 16;

    public const CANCELLED_BY_RMCD_ID = 17;

    public function guaranteeLetters(): HasMany
    {
        return $this->hasMany(GuaranteeLetter::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
