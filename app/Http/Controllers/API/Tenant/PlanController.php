<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * Get all active plans
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price_monthly' => (float) $plan->price_monthly,
                    'price_yearly' => (float) $plan->price_yearly,
                    'features' => $plan->features,
                    'limits' => [
                        'max_branches' => $plan->max_branches,
                        'max_users' => $plan->max_users,
                        'max_products' => $plan->max_products,
                        'max_monthly_transactions' => $plan->max_monthly_transactions,
                    ],
                    'savings_yearly' => $plan->price_monthly * 12 - $plan->price_yearly,
                    'savings_percentage' => $plan->price_monthly > 0
                        ? round((($plan->price_monthly * 12 - $plan->price_yearly) / ($plan->price_monthly * 12)) * 100)
                        : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }
}
