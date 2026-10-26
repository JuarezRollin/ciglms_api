<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['compliance_group_id', 'guarantee_letter_id', 'batch_id', 'compliance_type_id', 'compliance_classification', 'returned_at', 'resolved_at', 'duration', 'remarks', 'resolution_remarks'])]
class Compliance extends Model
{
    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function complianceType(): BelongsTo
    {
        return $this->belongsTo(ComplianceType::class);
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'duration' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $compliance): void {
            $hasGuaranteeLetter = $compliance->guarantee_letter_id !== null;
            $hasBatch = $compliance->batch_id !== null;

            if (! $hasGuaranteeLetter && ! $hasBatch) {
                throw new InvalidArgumentException('A compliance must belong to a guarantee letter or batch.');
            }

            $type = ComplianceType::query()->find($compliance->compliance_type_id);

            if ($type === null) {
                throw new InvalidArgumentException('The compliance type is invalid.');
            }

            if (! $type->is_active && (! $compliance->exists || $compliance->isDirty('compliance_type_id'))) {
                throw new InvalidArgumentException('The compliance type is inactive.');
            }

            if ($compliance->returned_at === null) {
                throw new InvalidArgumentException('A compliance requires a return date.');
            }

            if ($compliance->resolved_at !== null && $compliance->resolved_at->lessThan($compliance->returned_at)) {
                throw new InvalidArgumentException('The resolved date cannot be before the return date.');
            }
        });
    }
}
