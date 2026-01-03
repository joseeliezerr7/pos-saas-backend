<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Traits\ValidatesPlanLimits;
use Illuminate\Http\JsonResponse;

/**
 * Controlador para obtener información de uso del plan
 */
class PlanUsageController extends Controller
{
    use ValidatesPlanLimits;

    /**
     * Obtiene estadísticas de uso del plan actual
     *
     * GET /api/plan-usage
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $usage = $this->getPlanUsageStats();

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }
}
