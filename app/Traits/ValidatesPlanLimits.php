<?php

namespace App\Traits;

use App\Models\Tenant\Subscription;
use Illuminate\Http\JsonResponse;

/**
 * Trait ValidatesPlanLimits
 *
 * Proporciona métodos para validar límites del plan de suscripción
 * antes de permitir la creación de recursos.
 */
trait ValidatesPlanLimits
{
    /**
     * Valida si el tenant puede crear más productos según su plan
     *
     * @return JsonResponse|null Retorna error si alcanzó el límite, null si está OK
     */
    protected function validateProductLimit(): ?JsonResponse
    {
        $user = auth()->user();
        $subscription = $user->company->subscription;

        if (!$subscription) {
            return response()->json([
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'No tienes una suscripción activa'
                ]
            ], 403);
        }

        $plan = $subscription->plan;
        $currentProductCount = \App\Models\Product::count();

        // -1 significa ilimitado
        if ($plan->max_products !== -1 && $currentProductCount >= $plan->max_products) {
            return response()->json([
                'error' => [
                    'code' => 'PRODUCT_LIMIT_REACHED',
                    'message' => "Has alcanzado el límite de productos de tu plan ({$plan->max_products} productos)",
                    'current' => $currentProductCount,
                    'limit' => $plan->max_products,
                    'plan_name' => $plan->name
                ]
            ], 403);
        }

        return null;
    }

    /**
     * Valida si el tenant puede crear más usuarios según su plan
     *
     * @return JsonResponse|null
     */
    protected function validateUserLimit(): ?JsonResponse
    {
        $user = auth()->user();
        $subscription = $user->company->subscription;

        if (!$subscription) {
            return response()->json([
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'No tienes una suscripción activa'
                ]
            ], 403);
        }

        $plan = $subscription->plan;
        $currentUserCount = \App\Models\User::count();

        if ($plan->max_users !== -1 && $currentUserCount >= $plan->max_users) {
            return response()->json([
                'error' => [
                    'code' => 'USER_LIMIT_REACHED',
                    'message' => "Has alcanzado el límite de usuarios de tu plan ({$plan->max_users} usuarios)",
                    'current' => $currentUserCount,
                    'limit' => $plan->max_users,
                    'plan_name' => $plan->name
                ]
            ], 403);
        }

        return null;
    }

    /**
     * Valida si el tenant puede crear más sucursales según su plan
     *
     * @return JsonResponse|null
     */
    protected function validateBranchLimit(): ?JsonResponse
    {
        $user = auth()->user();
        $subscription = $user->company->subscription;

        if (!$subscription) {
            return response()->json([
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'No tienes una suscripción activa'
                ]
            ], 403);
        }

        $plan = $subscription->plan;
        $currentBranchCount = \App\Models\Tenant\Branch::count();

        if ($plan->max_branches !== -1 && $currentBranchCount >= $plan->max_branches) {
            return response()->json([
                'error' => [
                    'code' => 'BRANCH_LIMIT_REACHED',
                    'message' => "Has alcanzado el límite de sucursales de tu plan ({$plan->max_branches} sucursales)",
                    'current' => $currentBranchCount,
                    'limit' => $plan->max_branches,
                    'plan_name' => $plan->name,
                    'upgrade_message' => 'Actualiza tu plan para agregar más sucursales'
                ]
            ], 403);
        }

        return null;
    }

    /**
     * Valida si el tenant puede crear más transacciones este mes según su plan
     *
     * @return JsonResponse|null
     */
    protected function validateMonthlyTransactionLimit(): ?JsonResponse
    {
        $user = auth()->user();
        $subscription = $user->company->subscription;

        if (!$subscription) {
            return response()->json([
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'No tienes una suscripción activa'
                ]
            ], 403);
        }

        $plan = $subscription->plan;

        // Si el plan tiene transacciones ilimitadas, no validar
        if ($plan->max_monthly_transactions === -1) {
            return null;
        }

        // Contar transacciones del mes actual (ventas + compras)
        $currentMonth = now()->startOfMonth();
        $salesCount = \App\Models\Sale::where('created_at', '>=', $currentMonth)->count();
        $purchasesCount = \App\Models\Purchase::where('created_at', '>=', $currentMonth)->count();
        $currentTransactionCount = $salesCount + $purchasesCount;

        if ($currentTransactionCount >= $plan->max_monthly_transactions) {
            return response()->json([
                'error' => [
                    'code' => 'MONTHLY_TRANSACTION_LIMIT_REACHED',
                    'message' => "Has alcanzado el límite de transacciones mensuales de tu plan ({$plan->max_monthly_transactions} transacciones)",
                    'current' => $currentTransactionCount,
                    'limit' => $plan->max_monthly_transactions,
                    'plan_name' => $plan->name,
                    'period' => 'Este mes',
                    'upgrade_message' => 'Actualiza tu plan para aumentar el límite de transacciones'
                ]
            ], 403);
        }

        return null;
    }

    /**
     * Obtiene información de uso actual vs límites del plan
     *
     * @return array
     */
    protected function getPlanUsageStats(): array
    {
        $user = auth()->user();
        $subscription = $user->company->subscription;

        if (!$subscription) {
            return [
                'has_subscription' => false,
                'message' => 'No hay suscripción activa'
            ];
        }

        $plan = $subscription->plan;

        // Contar recursos actuales
        $currentProducts = \App\Models\Product::count();
        $currentUsers = \App\Models\User::count();
        $currentBranches = \App\Models\Tenant\Branch::count();

        // Transacciones del mes
        $currentMonth = now()->startOfMonth();
        $salesCount = \App\Models\Sale::where('created_at', '>=', $currentMonth)->count();
        $purchasesCount = \App\Models\Purchase::where('created_at', '>=', $currentMonth)->count();
        $currentTransactions = $salesCount + $purchasesCount;

        return [
            'has_subscription' => true,
            'plan' => [
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'subscription' => [
                'status' => $subscription->status,
                'expires_at' => $subscription->expires_at,
                'is_active' => $subscription->isActive(),
                'is_on_trial' => $subscription->isOnTrial(),
            ],
            'usage' => [
                'products' => [
                    'current' => $currentProducts,
                    'limit' => $plan->max_products,
                    'unlimited' => $plan->max_products === -1,
                    'percentage' => $plan->max_products > 0 ? round(($currentProducts / $plan->max_products) * 100, 2) : 0,
                ],
                'users' => [
                    'current' => $currentUsers,
                    'limit' => $plan->max_users,
                    'unlimited' => $plan->max_users === -1,
                    'percentage' => $plan->max_users > 0 ? round(($currentUsers / $plan->max_users) * 100, 2) : 0,
                ],
                'branches' => [
                    'current' => $currentBranches,
                    'limit' => $plan->max_branches,
                    'unlimited' => $plan->max_branches === -1,
                    'percentage' => $plan->max_branches > 0 ? round(($currentBranches / $plan->max_branches) * 100, 2) : 0,
                ],
                'monthly_transactions' => [
                    'current' => $currentTransactions,
                    'limit' => $plan->max_monthly_transactions,
                    'unlimited' => $plan->max_monthly_transactions === -1,
                    'percentage' => $plan->max_monthly_transactions > 0 ? round(($currentTransactions / $plan->max_monthly_transactions) * 100, 2) : 0,
                    'period' => now()->format('F Y'),
                ],
            ],
            'warnings' => $this->getUsageWarnings($plan, $currentProducts, $currentUsers, $currentBranches, $currentTransactions),
        ];
    }

    /**
     * Obtiene advertencias si el uso está cerca del límite
     *
     * @param $plan
     * @param int $currentProducts
     * @param int $currentUsers
     * @param int $currentBranches
     * @param int $currentTransactions
     * @return array
     */
    private function getUsageWarnings($plan, int $currentProducts, int $currentUsers, int $currentBranches, int $currentTransactions): array
    {
        $warnings = [];
        $threshold = 0.8; // 80% de uso

        if ($plan->max_products > 0 && ($currentProducts / $plan->max_products) >= $threshold) {
            $warnings[] = [
                'resource' => 'products',
                'message' => 'Estás cerca del límite de productos de tu plan',
                'percentage' => round(($currentProducts / $plan->max_products) * 100, 2),
            ];
        }

        if ($plan->max_users > 0 && ($currentUsers / $plan->max_users) >= $threshold) {
            $warnings[] = [
                'resource' => 'users',
                'message' => 'Estás cerca del límite de usuarios de tu plan',
                'percentage' => round(($currentUsers / $plan->max_users) * 100, 2),
            ];
        }

        if ($plan->max_branches > 0 && ($currentBranches / $plan->max_branches) >= $threshold) {
            $warnings[] = [
                'resource' => 'branches',
                'message' => 'Estás cerca del límite de sucursales de tu plan',
                'percentage' => round(($currentBranches / $plan->max_branches) * 100, 2),
            ];
        }

        if ($plan->max_monthly_transactions > 0 && ($currentTransactions / $plan->max_monthly_transactions) >= $threshold) {
            $warnings[] = [
                'resource' => 'monthly_transactions',
                'message' => 'Estás cerca del límite de transacciones mensuales de tu plan',
                'percentage' => round(($currentTransactions / $plan->max_monthly_transactions) * 100, 2),
            ];
        }

        return $warnings;
    }
}
