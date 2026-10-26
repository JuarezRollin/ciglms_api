<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): JsonResponse
    {
        $dashboard = $dashboardService->getDashboardData($request->user('api'));

        return (new DashboardResource($dashboard))->response();
    }
}
