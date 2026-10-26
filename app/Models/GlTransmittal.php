<?php

namespace App\Models;

use Database\Factories\GlTransmittalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['bundle_no', 'category_id', 'created_by', 'received_by', 'cancelled_by', 'cancelled_at'])]
class GlTransmittal extends Model
{
    /** @use HasFactory<GlTransmittalFactory> */
    use HasFactory;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function gls(): BelongsToMany
    {
        return $this->belongsToMany(GuaranteeLetter::class, 'gl_transmittal', 'gl_transmittal_id', 'gl_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }
}
