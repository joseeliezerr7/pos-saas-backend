<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'No autenticado',
                ],
            ], 401);
        }

        $company = $user->company;

        if (!$company || !$company->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMPANY_INACTIVE',
                    'message' => 'La empresa está inactiva',
                ],
            ], 403);
        }

        $subscription = $company->subscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'No hay suscripción activa',
                ],
            ], 403);
        }

        if (!$subscription->isActive()) {
            $status = $subscription->status;
            $message = match ($status) {
                'expired' => 'Su suscripción ha expirado',
                'canceled' => 'Su suscripción ha sido cancelada',
                'suspended' => 'Su suscripción está suspendida',
                default => 'Su suscripción no está activa',
            };

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_INACTIVE',
                    'message' => $message,
                    'status' => $status,
                    'expires_at' => $subscription->expires_at,
                ],
            ], 403);
        }

        // Check plan limits
        $plan = $subscription->plan;
        $request->merge(['plan_limits' => [
            'max_branches' => $plan->max_branches,
            'max_users' => $plan->max_users,
            'max_products' => $plan->max_products,
            'max_monthly_transactions' => $plan->max_monthly_transactions,
        ]]);

        return $next($request);
    }
}
