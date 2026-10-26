<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_correspondence', 'name', 'altName', 'code', 'geo_level', 'remarks', 'archive'])]
class PsgcRegion extends Model
{
    protected $table = 'psgc_regions';

    protected $primaryKey = 'reg_id';

    public $timestamps = false;

    public function provinces(): HasMany
    {
        return $this->hasMany(PsgcProvince::class, 'reg_id', 'reg_id');
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class, 'region_id', 'reg_id');
    }

    public function serviceProviders(): HasMany
    {
        return $this->hasMany(ServiceProvider::class, 'region_id', 'reg_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archive' => 'boolean'];
    }
}
