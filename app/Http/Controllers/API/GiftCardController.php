<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard\GiftCard;
use App\Services\GiftCardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GiftCardController extends Controller
{
    public function __construct(
        protected GiftCardService $giftCardService
    ) {}

    /**
     * Get all gift cards for tenant
     */
    public function index(Request $request)
    {
        $query = GiftCard::forTenant(auth()->user()->tenant_id)
            ->with(['issuedBy', 'customer', 'soldInSale']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by code (search)
        if ($request->has('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('issued_at', [
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
            ]);
        }

        $giftCards = $query->latest('issued_at')->paginate(20);

        return response()->json($giftCards);
    }

    /**
     * Create a new gift card
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'customer_id' => 'nullable|exists:customers,id',
            'expires_at' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $expiresAt = $request->expires_at ? Carbon::parse($request->expires_at) : null;

            $giftCard = $this->giftCardService->createGiftCard(
                auth()->user()->tenant_id,
                $request->amount,
                $request->customer_id,
                null,
                $expiresAt,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Gift card creada exitosamente',
                'data' => $giftCard->load(['issuedBy', 'customer', 'transactions']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear gift card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gift card details
     */
    public function show($id)
    {
        $giftCard = GiftCard::forTenant(auth()->user()->tenant_id)
            ->with(['issuedBy', 'customer', 'soldInSale', 'transactions.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $giftCard,
        ]);
    }

    /**
     * Check balance by code
     */
    public function checkBalance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $giftCard = $this->giftCardService->checkBalance(
            $request->code,
            auth()->user()->tenant_id
        );

        if (!$giftCard) {
            return response()->json([
                'success' => false,
                'message' => 'Gift card no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $giftCard->code,
                'current_balance' => $giftCard->current_balance,
                'status' => $giftCard->status,
                'is_active' => $giftCard->is_active,
                'expires_at' => $giftCard->expires_at,
                'recent_transactions' => $giftCard->transactions,
            ],
        ]);
    }

    /**
     * Redeem gift card
     */
    public function redeem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'sale_id' => 'nullable|exists:sales,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $giftCard = GiftCard::forTenant(auth()->user()->tenant_id)
                ->byCode($request->code)
                ->firstOrFail();

            $transaction = $this->giftCardService->redeem(
                $giftCard,
                $request->amount,
                $request->sale_id,
                $request->description
            );

            return response()->json([
                'success' => true,
                'message' => 'Gift card canjeada exitosamente',
                'data' => [
                    'transaction' => $transaction,
                    'new_balance' => $giftCard->fresh()->current_balance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Add balance to gift card
     */
    public function addBalance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $giftCard = GiftCard::forTenant(auth()->user()->tenant_id)
                ->findOrFail($id);

            $transaction = $this->giftCardService->addBalance(
                $giftCard,
                $request->amount,
                null,
                $request->description
            );

            return response()->json([
                'success' => true,
                'message' => 'Balance agregado exitosamente',
                'data' => [
                    'transaction' => $transaction,
                    'new_balance' => $giftCard->fresh()->current_balance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Void a gift card
     */
    public function void(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $giftCard = GiftCard::forTenant(auth()->user()->tenant_id)
                ->findOrFail($id);

            $this->giftCardService->voidGiftCard($giftCard, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Gift card anulada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get gift card statistics
     */
    public function statistics(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : null;

        $stats = $this->giftCardService->getStatistics(
            auth()->user()->tenant_id,
            $startDate,
            $endDate
        );

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Expire old gift cards (run as scheduled task)
     */
    public function expireCards()
    {
        try {
            $count = $this->giftCardService->expireGiftCards(auth()->user()->tenant_id);

            return response()->json([
                'success' => true,
                'message' => "{$count} gift cards expiradas",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
