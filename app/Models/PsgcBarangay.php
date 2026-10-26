<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code_correspondence', 'name', 'code', 'geo_level', 'old_name', 'city_class', 'urb_rur', 'city', 'city_correspondence', 'city_id', 'archive', 'remarks'])]
class PsgcBarangay extends Model
{
    protected $table = 'psgc_barangays';

    protected $primaryKey = 'brgy_id';

    public $timestamps = false;

    public function parentCity(): BelongsTo
    {
        return $this->belongsTo(PsgcCity::class, 'city_id', 'city_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archive' => 'boolean'];
    }
}
