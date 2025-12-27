<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Get current subscription details
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $subscription = Subscription::with('plan')
            ->where('tenant_id', $user->tenant_id)
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'No se encontró suscripción activa',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'description' => $subscription->plan->description,
                ],
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'trial_ends_at' => $subscription->trial_ends_at?->format('Y-m-d H:i:s'),
                'started_at' => $subscription->started_at?->format('Y-m-d H:i:s'),
                'expires_at' => $subscription->expires_at?->format('Y-m-d H:i:s'),
                'canceled_at' => $subscription->canceled_at?->format('Y-m-d H:i:s'),
                'auto_renew' => $subscription->auto_renew,
                'is_active' => $subscription->isActive(),
                'is_on_trial' => $subscription->isOnTrial(),
                'days_until_expiry' => $subscription->expires_at ? now()->diffInDays($subscription->expires_at, false) : null,
            ],
        ]);
    }

    /**
     * Upgrade or change subscription plan
     */
    public function upgrade(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Los datos proporcionados no son válidos',
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $user = auth()->user();
        $newPlan = Plan::findOrFail($request->plan_id);

        if (!$newPlan->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAN_NOT_AVAILABLE',
                    'message' => 'El plan seleccionado no está disponible',
                ],
            ], 400);
        }

        try {
            DB::transaction(function () use ($user, $newPlan, $request) {
                // Cancel current subscription if exists
                $currentSubscription = Subscription::where('tenant_id', $user->tenant_id)
                    ->whereIn('status', ['active', 'trial'])
                    ->latest()
                    ->first();

                if ($currentSubscription) {
                    $currentSubscription->update([
                        'status' => 'canceled',
                        'canceled_at' => now(),
                    ]);
                }

                // Create new subscription
                $expiresAt = $request->billing_cycle === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();

                Subscription::create([
                    'tenant_id' => $user->tenant_id,
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'billing_cycle' => $request->billing_cycle,
                    'started_at' => now(),
                    'expires_at' => $expiresAt,
                    'auto_renew' => true,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Suscripción actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPGRADE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Cancel current subscription
     */
    public function cancel(): JsonResponse
    {
        $user = auth()->user();

        try {
            $subscription = Subscription::where('tenant_id', $user->tenant_id)
                ->whereIn('status', ['active', 'trial'])
                ->latest()
                ->firstOrFail();

            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'auto_renew' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Suscripción cancelada exitosamente. El acceso permanecerá activo hasta la fecha de expiración.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No se encontró una suscripción activa para cancelar',
                ],
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANCEL_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get subscription invoices/payment history
     */
    public function invoices(): JsonResponse
    {
        $user = auth()->user();

        // In a real implementation, this would fetch from a payment gateway
        // For now, returning a placeholder response
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Esta funcionalidad estará disponible próximamente',
        ]);
    }
}
