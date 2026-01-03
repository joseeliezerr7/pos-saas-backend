<?php

namespace App\Services;

use App\Models\GiftCard\GiftCard;
use App\Models\GiftCard\GiftCardTransaction;
use App\Models\Customer;
use App\Models\Sale\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GiftCardService
{
    /**
     * Generate a unique gift card code
     */
    public function generateUniqueCode(string $prefix = 'GC'): string
    {
        do {
            $code = $prefix . '-' . strtoupper(Str::random(12));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }

    /**
     * Create a new gift card
     */
    public function createGiftCard(
        int $tenantId,
        float $amount,
        ?int $customerId = null,
        ?int $saleId = null,
        ?Carbon $expiresAt = null,
        ?string $notes = null
    ): GiftCard {
        return DB::transaction(function () use ($tenantId, $amount, $customerId, $saleId, $expiresAt, $notes) {
            $code = $this->generateUniqueCode();

            $giftCard = GiftCard::create([
                'tenant_id' => $tenantId,
                'code' => $code,
                'initial_balance' => $amount,
                'current_balance' => $amount,
                'status' => 'active',
                'issued_by' => auth()->id(),
                'customer_id' => $customerId,
                'sold_in_sale_id' => $saleId,
                'issued_at' => now(),
                'expires_at' => $expiresAt,
                'notes' => $notes,
            ]);

            // Record initial transaction
            $this->recordTransaction(
                $giftCard,
                'issue',
                $amount,
                null,
                "Gift card emitida - Código: {$code}"
            );

            return $giftCard->fresh();
        });
    }

    /**
     * Check gift card balance
     */
    public function checkBalance(string $code, int $tenantId): ?GiftCard
    {
        return GiftCard::forTenant($tenantId)
            ->byCode($code)
            ->with(['transactions' => function ($query) {
                $query->latest()->limit(5);
            }])
            ->first();
    }

    /**
     * Redeem amount from gift card
     */
    public function redeem(
        GiftCard $giftCard,
        float $amount,
        ?int $saleId = null,
        ?string $description = null
    ): GiftCardTransaction {
        if (!$giftCard->canRedeem($amount)) {
            if (!$giftCard->is_active) {
                throw new \Exception('La gift card no está activa');
            }
            if ($giftCard->is_expired) {
                throw new \Exception('La gift card está vencida');
            }
            if ($amount > $giftCard->current_balance) {
                throw new \Exception("Balance insuficiente. Balance actual: L. {$giftCard->current_balance}");
            }
            throw new \Exception('No se puede canjear esta gift card');
        }

        return DB::transaction(function () use ($giftCard, $amount, $saleId, $description) {
            $balanceBefore = $giftCard->current_balance;
            $giftCard->current_balance -= $amount;
            $giftCard->save();

            // Mark as fully redeemed if balance is zero
            if ($giftCard->current_balance <= 0) {
                $giftCard->markAsRedeemed();
            }

            $description = $description ?? "Canje en venta #{$saleId}";

            return $this->recordTransaction(
                $giftCard,
                'redeem',
                $amount,
                $saleId,
                $description
            );
        });
    }

    /**
     * Add balance to an existing gift card
     */
    public function addBalance(
        GiftCard $giftCard,
        float $amount,
        ?int $saleId = null,
        ?string $description = null
    ): GiftCardTransaction {
        return DB::transaction(function () use ($giftCard, $amount, $saleId, $description) {
            $giftCard->current_balance += $amount;
            $giftCard->save();

            // Reactivate if was fully redeemed
            if ($giftCard->status === 'redeemed') {
                $giftCard->update(['status' => 'active']);
            }

            $description = $description ?? "Recarga de gift card - L. {$amount}";

            return $this->recordTransaction(
                $giftCard,
                'add',
                $amount,
                $saleId,
                $description
            );
        });
    }

    /**
     * Void a gift card
     */
    public function voidGiftCard(GiftCard $giftCard, string $reason): bool
    {
        if ($giftCard->status === 'voided') {
            throw new \Exception('La gift card ya está anulada');
        }

        return DB::transaction(function () use ($giftCard, $reason) {
            $balanceBefore = $giftCard->current_balance;

            $giftCard->markAsVoided();
            $giftCard->current_balance = 0;
            $giftCard->notes = ($giftCard->notes ?? '') . "\nANULADA: {$reason}";
            $giftCard->save();

            if ($balanceBefore > 0) {
                $this->recordTransaction(
                    $giftCard,
                    'void',
                    $balanceBefore,
                    null,
                    "Gift card anulada: {$reason}"
                );
            }

            return true;
        });
    }

    /**
     * Record a gift card transaction
     */
    protected function recordTransaction(
        GiftCard $giftCard,
        string $type,
        float $amount,
        ?int $saleId = null,
        ?string $description = null
    ): GiftCardTransaction {
        $balanceBefore = $type === 'issue' ? 0 : $giftCard->getOriginal('current_balance');
        $balanceAfter = $giftCard->current_balance;

        return GiftCardTransaction::create([
            'gift_card_id' => $giftCard->id,
            'type' => $type,
            'amount' => ($type === 'redeem' || $type === 'void') ? -abs($amount) : abs($amount),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'sale_id' => $saleId,
            'user_id' => auth()->id(),
            'description' => $description,
        ]);
    }

    /**
     * Expire old gift cards
     */
    public function expireGiftCards(int $tenantId): int
    {
        $expiredCards = GiftCard::forTenant($tenantId)
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expiredCards as $card) {
            $card->markAsExpired();
            $count++;
        }

        return $count;
    }

    /**
     * Get gift card statistics
     */
    public function getStatistics(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = GiftCard::forTenant($tenantId);

        if ($startDate && $endDate) {
            $query->whereBetween('issued_at', [$startDate, $endDate]);
        }

        $cards = $query->get();

        $totalIssued = $cards->sum('initial_balance');
        $totalRedeemed = $cards->sum(fn($card) => $card->initial_balance - $card->current_balance);
        $totalOutstanding = $cards->where('status', 'active')->sum('current_balance');

        return [
            'total_cards' => $cards->count(),
            'active_cards' => $cards->where('status', 'active')->count(),
            'redeemed_cards' => $cards->where('status', 'redeemed')->count(),
            'expired_cards' => $cards->where('status', 'expired')->count(),
            'voided_cards' => $cards->where('status', 'voided')->count(),
            'total_issued' => $totalIssued,
            'total_redeemed' => $totalRedeemed,
            'total_outstanding' => $totalOutstanding,
            'redemption_rate' => $totalIssued > 0 ? ($totalRedeemed / $totalIssued) * 100 : 0,
        ];
    }
}
