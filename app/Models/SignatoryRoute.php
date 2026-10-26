<?php

namespace App\Models;

use Database\Factories\SignatoryRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['guarantee_letter_id', 'signatory_id', 'sequence', 'date_sent', 'date_returned', 'remarks'])]
class SignatoryRoute extends Model
{
    /** @use HasFactory<SignatoryRouteFactory> */
    use HasFactory;

    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(Signatory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'date_sent' => 'datetime',
            'date_returned' => 'datetime',
        ];
    }
}
