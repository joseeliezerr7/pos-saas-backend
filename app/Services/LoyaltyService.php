<?php

namespace App\Services;

use App\Models\Loyalty\LoyaltyProgram;
use App\Models\Loyalty\LoyaltyTier;
use App\Models\Loyalty\CustomerLoyalty;
use App\Models\Loyalty\LoyaltyTransaction;
use App\Models\Customer;
use App\Models\Sale\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Inscribir un cliente en el programa de lealtad
     */
    public function enrollCustomer(Customer $customer, LoyaltyProgram $program): CustomerLoyalty
    {
        // Verificar si ya está inscrito
        $existing = CustomerLoyalty::where('customer_id', $customer->id)
            ->where('loyalty_program_id', $program->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Obtener el tier inicial (el de menor orden)
        $initialTier = $program->tiers()->orderBy('order')->first();

        // Crear registro de lealtad
        $customerLoyalty = CustomerLoyalty::create([
            'customer_id' => $customer->id,
            'loyalty_program_id' => $program->id,
            'current_tier_id' => $initialTier?->id,
            'points' => 0,
            'lifetime_points' => 0,
            'points_redeemed' => 0,
            'total_spent' => 0,
            'purchases_count' => 0,
            'enrolled_at' => now(),
        ]);

        return $customerLoyalty;
    }

    /**
     * Calcular puntos a ganar por una compra
     */
    public function calculatePointsForPurchase(
        LoyaltyProgram $program,
        float $amount,
        ?Customer $customer = null,
        ?Carbon $date = null
    ): int {
        $date = $date ?? now();

        // Verificar monto mínimo
        if ($amount < $program->min_purchase_amount) {
            return 0;
        }

        // Calcular puntos base
        $basePoints = floor($amount * $program->points_per_currency);

        // Aplicar multiplicador del tier del cliente
        $multiplier = 1.0;
        if ($customer) {
            $customerLoyalty = CustomerLoyalty::where('customer_id', $customer->id)
                ->where('loyalty_program_id', $program->id)
                ->first();

            if ($customerLoyalty && $customerLoyalty->currentTier) {
                $multiplier *= $customerLoyalty->currentTier->points_multiplier;
            }
        }

        // Aplicar multiplicador de cumpleaños
        if ($customer && $customer->birthdate) {
            $birthdate = Carbon::parse($customer->birthdate);
            if ($date->isSameDay($birthdate)) {
                $multiplier *= $program->birthday_multiplier;
            }
        }

        // Aplicar multiplicador de fechas especiales
        if ($program->special_dates) {
            $dateStr = $date->format('Y-m-d');
            foreach ($program->special_dates as $specialDate) {
                if (isset($specialDate['date']) && isset($specialDate['multiplier'])) {
                    if ($specialDate['date'] === $dateStr) {
                        $multiplier *= $specialDate['multiplier'];
                        break;
                    }
                }
            }
        }

        return (int) floor($basePoints * $multiplier);
    }

    /**
     * Otorgar puntos a un cliente por una compra
     */
    public function awardPointsForSale(Sale $sale): ?LoyaltyTransaction
    {
        if (!$sale->customer_id) {
            return null;
        }

        // Obtener el programa de lealtad de la empresa
        $program = LoyaltyProgram::where('tenant_id', $sale->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$program) {
            return null;
        }

        // Inscribir al cliente si no está inscrito
        $customerLoyalty = $this->enrollCustomer($sale->customer, $program);

        // Calcular puntos
        $points = $this->calculatePointsForPurchase(
            $program,
            $sale->total,
            $sale->customer,
            $sale->created_at
        );

        if ($points <= 0) {
            return null;
        }

        // Registrar transacción
        $expiresAt = null;
        if ($program->points_expire && $program->expiration_days) {
            $expiresAt = now()->addDays($program->expiration_days);
        }

        $transaction = $this->recordTransaction(
            $customerLoyalty,
            'earn',
            $points,
            $sale->id,
            "Puntos ganados por compra #{$sale->id}",
            $expiresAt
        );

        // Verificar si el cliente califica para un tier superior
        $this->upgradeTierIfNeeded($customerLoyalty);

        return $transaction;
    }

    /**
     * Registrar una transacción de puntos
     */
    public function recordTransaction(
        CustomerLoyalty $customerLoyalty,
        string $type,
        int $points,
        ?int $saleId = null,
        ?string $description = null,
        ?Carbon $expiresAt = null
    ): LoyaltyTransaction {
        return DB::transaction(function () use ($customerLoyalty, $type, $points, $saleId, $description, $expiresAt) {
            // Actualizar balance
            if ($type === 'earn' || $type === 'adjust') {
                $customerLoyalty->points += $points;
                if ($type === 'earn') {
                    $customerLoyalty->lifetime_points += $points;
                }
            } elseif ($type === 'redeem' || $type === 'expire') {
                $customerLoyalty->points -= abs($points);
                if ($type === 'redeem') {
                    $customerLoyalty->points_redeemed += abs($points);
                }
            }

            // Actualizar estadísticas de compra si está relacionado con una venta
            if ($saleId && $type === 'earn') {
                $sale = Sale::find($saleId);
                if ($sale) {
                    $customerLoyalty->total_spent += $sale->total;
                    $customerLoyalty->purchases_count += 1;
                    $customerLoyalty->last_purchase_at = now();
                }
            }

            $customerLoyalty->save();

            // Verificar actualización de tier
            $this->upgradeTierIfNeeded($customerLoyalty);

            // Crear transacción
            $transaction = LoyaltyTransaction::create([
                'customer_loyalty_id' => $customerLoyalty->id,
                'type' => $type,
                'points' => $type === 'redeem' || $type === 'expire' ? -abs($points) : $points,
                'balance_after' => $customerLoyalty->points,
                'sale_id' => $saleId,
                'description' => $description,
                'expires_at' => $expiresAt,
            ]);

            return $transaction;
        });
    }

    /**
     * Verificar si el cliente puede canjear puntos
     */
    public function canRedeemPoints(CustomerLoyalty $customerLoyalty, int $points): bool
    {
        return $customerLoyalty->points >= $points && $points > 0;
    }

    /**
     * Canjear puntos del cliente
     */
    public function redeemPoints(
        CustomerLoyalty $customerLoyalty,
        int $points,
        ?int $saleId = null,
        ?string $description = null
    ): LoyaltyTransaction {
        if (!$this->canRedeemPoints($customerLoyalty, $points)) {
            throw new \Exception('Puntos insuficientes para canjear');
        }

        return $this->recordTransaction(
            $customerLoyalty,
            'redeem',
            $points,
            $saleId,
            $description ?? "Canje de {$points} puntos"
        );
    }

    /**
     * Calcular el valor monetario de los puntos
     */
    public function calculatePointsValue(LoyaltyProgram $program, int $points): float
    {
        return $points * $program->point_value;
    }

    /**
     * Determinar el tier apropiado basado en puntos
     */
    public function determineTier(LoyaltyProgram $program, int $lifetimePoints): ?LoyaltyTier
    {
        return $program->tiers()
            ->where('min_points', '<=', $lifetimePoints)
            ->reorder('min_points', 'desc')
            ->first();
    }

    /**
     * Actualizar el tier del cliente si cumple requisitos
     */
    public function upgradeTierIfNeeded(CustomerLoyalty $customerLoyalty): bool
    {
        $currentTier = $customerLoyalty->currentTier;
        $appropriateTier = $this->determineTier(
            $customerLoyalty->loyaltyProgram,
            $customerLoyalty->lifetime_points
        );

        if (!$appropriateTier) {
            return false;
        }

        // Si es un tier diferente, actualizar
        if (!$currentTier || $appropriateTier->id !== $currentTier->id) {
            $customerLoyalty->current_tier_id = $appropriateTier->id;
            $customerLoyalty->save();
            return true;
        }

        return false;
    }

    /**
     * Expirar puntos vencidos de un cliente
     */
    public function expirePoints(CustomerLoyalty $customerLoyalty): int
    {
        // Buscar transacciones de puntos ganados que ya expiraron
        $expiredTransactions = LoyaltyTransaction::where('customer_loyalty_id', $customerLoyalty->id)
            ->where('type', 'earn')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereDoesntHave('customerLoyalty.transactions', function ($query) {
                $query->where('type', 'expire')
                    ->whereColumn('loyalty_transactions.id', 'parent_transaction_id');
            })
            ->get();

        $totalExpired = 0;

        foreach ($expiredTransactions as $transaction) {
            // Verificar si estos puntos aún están disponibles
            if ($transaction->points > 0) {
                $this->recordTransaction(
                    $customerLoyalty,
                    'expire',
                    $transaction->points,
                    null,
                    "Expiración de puntos ganados el {$transaction->created_at->format('Y-m-d')}"
                );
                $totalExpired += $transaction->points;
            }
        }

        return $totalExpired;
    }

    /**
     * Obtener el descuento del tier del cliente
     */
    public function getTierDiscount(CustomerLoyalty $customerLoyalty): float
    {
        if (!$customerLoyalty->currentTier) {
            return 0;
        }

        return $customerLoyalty->currentTier->discount_percentage;
    }

    /**
     * Aplicar descuento del tier a un monto
     */
    public function applyTierDiscount(CustomerLoyalty $customerLoyalty, float $amount): float
    {
        $discount = $this->getTierDiscount($customerLoyalty);

        if ($discount <= 0) {
            return $amount;
        }

        return $amount * (1 - ($discount / 100));
    }

    /**
     * Obtener resumen de lealtad de un cliente
     */
    public function getCustomerLoyaltySummary(Customer $customer, int $tenantId): ?array
    {
        $program = LoyaltyProgram::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$program) {
            return null;
        }

        $customerLoyalty = CustomerLoyalty::where('customer_id', $customer->id)
            ->where('loyalty_program_id', $program->id)
            ->with(['currentTier', 'loyaltyProgram'])
            ->first();

        if (!$customerLoyalty) {
            return [
                'enrolled' => false,
                'program' => $program,
            ];
        }

        // Calcular próximo tier
        $nextTier = $program->tiers()
            ->where('min_points', '>', $customerLoyalty->lifetime_points)
            ->orderBy('min_points', 'asc')
            ->first();

        $pointsToNextTier = $nextTier
            ? $nextTier->min_points - $customerLoyalty->lifetime_points
            : 0;

        return [
            'enrolled' => true,
            'program' => $program,
            'points' => $customerLoyalty->points,
            'lifetime_points' => $customerLoyalty->lifetime_points,
            'points_redeemed' => $customerLoyalty->points_redeemed,
            'current_tier' => $customerLoyalty->currentTier,
            'next_tier' => $nextTier,
            'points_to_next_tier' => $pointsToNextTier,
            'tier_discount' => $this->getTierDiscount($customerLoyalty),
            'points_value' => $this->calculatePointsValue($program, $customerLoyalty->points),
            'total_spent' => $customerLoyalty->total_spent,
            'purchases_count' => $customerLoyalty->purchases_count,
        ];
    }
}
