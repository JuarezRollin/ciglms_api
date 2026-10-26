<?php

namespace App\Models;

use Database\Factories\GuaranteeLetterSupportingDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class GuaranteeLetterSupportingDocument extends Model
{
    /** @use HasFactory<GuaranteeLetterSupportingDocumentFactory> */
    use HasFactory;

    public function guaranteeLetter(): BelongsTo
    {
        return $this->belongsTo(GuaranteeLetter::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
