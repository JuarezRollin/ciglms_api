<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_correspondence', 'name', 'code', 'classification', 'old_name', 'city_class', 'income_classification', 'province', 'province_correspondence', 'prov_id', 'archive', 'remarks'])]
class PsgcCity extends Model
{
    protected $table = 'psgc_city_4q25';

    protected $primaryKey = 'city_id';

    public $timestamps = false;

    public function parentProvince(): BelongsTo
    {
        return $this->belongsTo(PsgcProvince::class, 'prov_id', 'prov_id');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(PsgcBarangay::class, 'city_id', 'city_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archive' => 'boolean'];
    }
}
