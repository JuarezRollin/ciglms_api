<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ors_no'])]
class Ors extends Model
{
    use HasFactory;

    protected $table = 'ors';

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'ors_id');
    }

    public function gls(): Builder
    {
        return GuaranteeLetter::query()
            ->select('guarantee_letters.*')
            ->join('batch_gl', 'batch_gl.gl_id', '=', 'guarantee_letters.id')
            ->join('batches', 'batches.id', '=', 'batch_gl.batch_id')
            ->where('batches.ors_id', $this->getKey())
            ->distinct();
    }
}
