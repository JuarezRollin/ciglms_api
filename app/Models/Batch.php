<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['voucher_id', 'ors_id', 'dv_id', 'cheque_id', 'remarks', 'created_by', 'reviewer_id'])]
class Batch extends Model
{
    use HasFactory;

    protected $attributes = ['total_amount' => '0.00'];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function ors(): BelongsTo
    {
        return $this->belongsTo(Ors::class, 'ors_id');
    }

    public function dv(): BelongsTo
    {
        return $this->belongsTo(Dv::class, 'dv_id');
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function gls(): BelongsToMany
    {
        return $this->belongsToMany(GuaranteeLetter::class, 'batch_gl', 'batch_id', 'gl_id')
            ->using(BatchGl::class)
            ->withPivot('position')
            ->orderByPivot('position')
            ->orderBy('guarantee_letters.id');
    }

    public function compliances(): HasMany
    {
        return $this->hasMany(Compliance::class);
    }

    public function latestResolvedCompliance(): HasOne
    {
        return $this->hasOne(Compliance::class)->ofMany(
            ['resolved_at' => 'max', 'id' => 'max'],
            fn (Builder $query): Builder => $query->whereNotNull('resolved_at'),
        );
    }

    #[Scope]
    protected function atStatus(Builder $query, string $statusName): Builder
    {
        $statusIds = Status::query()->where('name', $statusName)->pluck('id');

        return $query
            ->whereNotNull('voucher_id')
            ->whereHas('gls', fn (Builder $letterQuery): Builder => $letterQuery->whereIn('status_id', $statusIds))
            ->whereDoesntHave('gls', fn (Builder $letterQuery): Builder => $letterQuery->whereNotIn('status_id', $statusIds));
    }

    public function glsWithOpenCompliance(): BelongsToMany
    {
        return $this->gls()->whereHas('compliances', fn (Builder $query): Builder => $query->open());
    }

    public function hasOpenCompliance(): bool
    {
        return $this->compliances()->open()->exists()
            || $this->glsWithOpenCompliance()->exists();
    }

    /** @param list<int> $glIds */
    public function syncGls(array $glIds): void
    {
        $this->getConnection()->transaction(function () use ($glIds): void {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $positionedLetters = [];
            foreach ($glIds as $index => $glId) {
                $positionedLetters[$glId] = ['position' => $index + 1];
            }
            $this->gls()->sync($positionedLetters);
            $this->recalculateTotal();
        });
        $this->unsetRelation('gls');
    }

    public function recalculateTotal(): void
    {
        $this->getConnection()->transaction(function (): void {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $total = BigDecimal::zero()->toScale(2);
            foreach ($this->gls()->select('guarantee_letters.gl_amount')->cursor() as $gl) {
                $total = $total->plus($gl->gl_amount ?? '0.00');
            }
            $this->forceFill(['total_amount' => (string) $total])->saveQuietly();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2'];
    }
}
