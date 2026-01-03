<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GiftCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = \App\Models\Tenant::all();

        foreach ($tenants as $tenant) {
            $users = \App\Models\User::where('tenant_id', $tenant->id)->get();
            $customers = \App\Models\Customer::where('tenant_id', $tenant->id)->take(5)->get();

            if ($users->isEmpty()) {
                continue;
            }

            $firstUser = $users->first();

            // Create some active gift cards
            $giftCards = [
                [
                    'code' => 'GC-WELCOME100',
                    'initial_balance' => 100.00,
                    'current_balance' => 100.00,
                    'status' => 'active',
                    'notes' => 'Gift card de bienvenida',
                ],
                [
                    'code' => 'GC-PROMO500',
                    'initial_balance' => 500.00,
                    'current_balance' => 350.00,
                    'status' => 'active',
                    'notes' => 'Gift card promocional',
                ],
                [
                    'code' => 'GC-BIRTHDAY200',
                    'initial_balance' => 200.00,
                    'current_balance' => 200.00,
                    'status' => 'active',
                    'notes' => 'Regalo de cumpleaños',
                ],
                [
                    'code' => 'GC-USED50',
                    'initial_balance' => 50.00,
                    'current_balance' => 0.00,
                    'status' => 'redeemed',
                    'notes' => 'Gift card totalmente canjeada',
                ],
            ];

            foreach ($giftCards as $index => $cardData) {
                $customer = $customers->get($index % $customers->count());

                $giftCard = \App\Models\GiftCard\GiftCard::create([
                    'tenant_id' => $tenant->id,
                    'code' => $cardData['code'],
                    'initial_balance' => $cardData['initial_balance'],
                    'current_balance' => $cardData['current_balance'],
                    'status' => $cardData['status'],
                    'issued_by' => $firstUser->id,
                    'customer_id' => $customer?->id,
                    'issued_at' => now()->subDays(rand(1, 30)),
                    'expires_at' => now()->addYear(),
                    'notes' => $cardData['notes'],
                ]);

                // Create initial transaction
                \App\Models\GiftCard\GiftCardTransaction::create([
                    'gift_card_id' => $giftCard->id,
                    'type' => 'issue',
                    'amount' => $cardData['initial_balance'],
                    'balance_before' => 0,
                    'balance_after' => $cardData['initial_balance'],
                    'user_id' => $firstUser->id,
                    'description' => "Gift card emitida - Código: {$cardData['code']}",
                ]);

                // If some balance was used, create redeem transaction
                if ($cardData['current_balance'] < $cardData['initial_balance']) {
                    $redeemed = $cardData['initial_balance'] - $cardData['current_balance'];
                    \App\Models\GiftCard\GiftCardTransaction::create([
                        'gift_card_id' => $giftCard->id,
                        'type' => 'redeem',
                        'amount' => -$redeemed,
                        'balance_before' => $cardData['initial_balance'],
                        'balance_after' => $cardData['current_balance'],
                        'user_id' => $firstUser->id,
                        'description' => "Canje de gift card - L. {$redeemed}",
                    ]);
                }
            }
        }
    }
}
