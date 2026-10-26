<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_correspondence', 'name', 'code', 'geo_level', 'old_name', 'income_classification', 'region', 'region_correspondence', 'reg_id', 'archive', 'remarks'])]
class PsgcProvince extends Model
{
    protected $table = 'psgc_provinces';

    protected $primaryKey = 'prov_id';

    public $timestamps = false;

    public function parentRegion(): BelongsTo
    {
        return $this->belongsTo(PsgcRegion::class, 'reg_id', 'reg_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(PsgcCity::class, 'prov_id', 'prov_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archive' => 'boolean'];
    }
}
