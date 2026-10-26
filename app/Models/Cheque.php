<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cheque_no', 'cheque_date'])]
class Cheque extends Model
{
    use HasFactory;

    protected $table = 'cheques';

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'cheque_id');
    }

    public function gls(): Builder
    {
        return GuaranteeLetter::query()
            ->select('guarantee_letters.*')
            ->join('batch_gl', 'batch_gl.gl_id', '=', 'guarantee_letters.id')
            ->join('batches', 'batches.id', '=', 'batch_gl.batch_id')
            ->where('batches.cheque_id', $this->getKey())
            ->distinct();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cheque_date' => 'date'];
    }
}
