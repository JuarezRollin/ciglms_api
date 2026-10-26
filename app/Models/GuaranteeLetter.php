<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'office_id',
    'gl_no',
    'gl_date',
    'client_id',
    'beneficiary_id',
    'relationship_id',
    'service_provider_id',
    'branch_id',
    'assistance_type_id',
    'status_id',
    'gl_amount',
    'gl_validity',
    'signatory_id',
    'swo_id',
    'address',
    'referring_party_id',
    'creation_remarks',
    'created_by',
    'reviewed_by_creation',
    'date_reviewed_by_creation',
    'approved_by',
    'date_approved',
    'released_by',
    'date_released',
    'endorsed_by',
    'date_endorsed',
    'onhold_by',
    'date_onhold',
    'cancelled_by_creation',
    'date_cancelled_by_creation',
    'received_by',
    'date_received_gl',
    'category_id',
    'soa_received_by',
    'date_received_soa',
    'obligation_amount',
    'pulled_out_by',
    'date_pulled_out',
    'fund_source_id',
    'date_fwd_to_reviewer',
    'reviewed_by_rmcd',
    'box_a_reviewed_by',
    'date_fwd_to_dc',
    'date_fwd_to_dir_ors',
    'date_fwd_to_budget',
    'date_fwd_to_actg',
    'date_fwd_to_dir_dv',
    'date_fwd_to_dir_fs',
    'date_fwd_to_cash',
    'cancelled_by_rmcd',
    'date_cancelled_by_rmcd',
    'norsa_no',
    'processing_remarks',
])]
class GuaranteeLetter extends Model
{
    use LogsActivity;

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_gl', 'gl_id', 'batch_id')
            ->using(BatchGl::class);
    }

    public function transmittals(): BelongsToMany
    {
        return $this->belongsToMany(GlTransmittal::class, 'gl_transmittal', 'gl_id', 'gl_transmittal_id')->withTimestamps();
    }

    public function compliances(): HasMany
    {
        return $this->hasMany(Compliance::class);
    }

    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(GuaranteeLetterSupportingDocument::class)->orderBy('id');
    }

    public function latestResolvedCompliance(): HasOne
    {
        return $this->hasOne(Compliance::class)->ofMany(
            ['resolved_at' => 'max', 'id' => 'max'],
            fn (Builder $query): Builder => $query->whereNotNull('resolved_at'),
        );
    }

    public function hasOpenCompliance(): bool
    {
        return $this->compliances()->open()->exists();
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options): bool {
            $batches = $this->exists && $this->isDirty('gl_amount')
                ? $this->batches()->orderBy('batches.id')->lockForUpdate()->get()
                : collect();
            $saved = parent::save($options);
            if ($saved) {
                foreach ($batches as $batch) {
                    $batch->recalculateTotal();
                }
            }

            return $saved;
        });
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            $batches = $this->exists
                ? $this->batches()->orderBy('batches.id')->lockForUpdate()->get()
                : collect();
            $deleted = parent::delete();
            if ($deleted) {
                foreach ($batches as $batch) {
                    $batch->recalculateTotal();
                }
            }

            return $deleted;
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class);
    }

    public function signatoryRoutes(): HasMany
    {
        return $this->hasMany(SignatoryRoute::class)->orderBy('sequence');
    }

    public function swo(): BelongsTo
    {
        return $this->belongsTo(Swo::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class);
    }

    public function assistanceType(): BelongsTo
    {
        return $this->belongsTo(AssistanceType::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gl_date' => 'date',
            'gl_amount' => 'decimal:2',
            'obligation_amount' => 'decimal:2',
            'gl_validity' => 'date',
            'date_reviewed_by_creation' => 'datetime',
            'date_approved' => 'datetime',
            'date_released' => 'datetime',
            'date_endorsed' => 'datetime',
            'date_onhold' => 'datetime',
            'date_cancelled_by_creation' => 'datetime',
            'date_received_gl' => 'datetime',
            'date_received_soa' => 'datetime',
            'date_pulled_out' => 'datetime',
            'signed_at' => 'datetime',
            'date_fwd_to_reviewer' => 'datetime',
            'date_fwd_to_dc' => 'datetime',
            'date_fwd_to_dir_ors' => 'datetime',
            'date_fwd_to_budget' => 'datetime',
            'date_fwd_to_actg' => 'datetime',
            'date_fwd_to_dir_dv' => 'datetime',
            'date_fwd_to_dir_fs' => 'datetime',
            'date_fwd_to_cash' => 'datetime',
            'date_cancelled_by_rmcd' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
