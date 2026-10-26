<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Models\PsgcRegion as Region;

class RegionController extends Controller
{
    public function dropdown(): JsonResponse
    {
        return response()->json(Region::query()->select('reg_id', 'name')->distinct()->orderBy('name')->get());
    }
}
