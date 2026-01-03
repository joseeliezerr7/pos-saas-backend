<?php

namespace App\Http\Controllers\API\CashRegister;

use App\Http\Controllers\Controller;
use App\Models\CashRegister\CashOpening;
use App\Models\CashRegister\CashRegister;
use App\Models\CashRegister\CashTransaction;
use App\Traits\FiltersByBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CashRegisterController extends Controller
{
    use FiltersByBranch;
    /**
     * Get all cash registers for the current branch
     */
    public function index(Request $request): JsonResponse
    {
        $query = CashRegister::with(['branch'])
            ->where('tenant_id', auth()->user()->tenant_id);

        // Apply branch filter
        $query = $this->applyBranchFilter($query, $request->branch_id);

        $cashRegisters = $query->get();

        return response()->json([
            'success' => true,
            'data' => $cashRegisters,
        ]);
    }

    /**
     * Create a new cash register
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'code' => 'required|string|max:10|unique:cash_registers,code',
            'name' => 'required|string|max:255',
            'printer_config' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,maintenance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $cashRegister = CashRegister::create([
            'tenant_id' => auth()->user()->tenant_id,
            'branch_id' => $request->branch_id,
            'code' => $request->code,
            'name' => $request->name,
            'printer_config' => $request->printer_config,
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'data' => $cashRegister,
            'message' => 'Caja registradora creada exitosamente',
        ], 201);
    }

    /**
     * Get a single cash register
     */
    public function show(int $id): JsonResponse
    {
        $cashRegister = CashRegister::with(['branch', 'openings' => function($q) {
            $q->latest()->take(10);
        }])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cashRegister,
        ]);
    }

    /**
     * Update cash register
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'printer_config' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,maintenance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $cashRegister->update($request->only(['name', 'printer_config', 'status']));

        return response()->json([
            'success' => true,
            'data' => $cashRegister,
            'message' => 'Caja registradora actualizada exitosamente',
        ]);
    }

    /**
     * Delete cash register
     */
    public function destroy(int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        if ($cashRegister->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una caja que está abierta',
            ], 400);
        }

        $cashRegister->delete();

        return response()->json([
            'success' => true,
            'message' => 'Caja registradora eliminada exitosamente',
        ]);
    }

    /**
     * Open cash register
     */
    public function open(Request $request, int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        if ($cashRegister->isOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta caja ya está abierta',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'opening_amount' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $opening = CashOpening::create([
            'tenant_id' => auth()->user()->tenant_id,
            'cash_register_id' => $cashRegister->id,
            'user_id' => auth()->id(),
            'opening_amount' => $request->opening_amount,
            'opening_notes' => $request->opening_notes,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $opening->load(['cashRegister', 'user']),
            'message' => 'Caja abierta exitosamente',
        ], 201);
    }

    /**
     * Close cash register
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $opening = $cashRegister->currentOpening();

        if (!$opening) {
            return response()->json([
                'success' => false,
                'message' => 'Esta caja no está abierta',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'actual_amount' => 'required|numeric|min:0',
            'denomination_breakdown' => 'nullable|array',
            'closing_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $closing = $opening->close(
                $request->actual_amount,
                $request->closing_notes
            );

            if ($request->has('denomination_breakdown')) {
                $closing->update([
                    'denomination_breakdown' => $request->denomination_breakdown,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $closing->load(['cashOpening.cashRegister', 'user']),
                'message' => 'Caja cerrada exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar caja: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current opening for a cash register
     */
    public function currentOpening(int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $opening = $cashRegister->currentOpening();

        if (!$opening) {
            return response()->json([
                'success' => false,
                'message' => 'No hay apertura activa para esta caja',
            ], 404);
        }

        $opening->load(['cashRegister', 'user', 'sales', 'transactions']);

        // Add stats to the opening object
        $opening->stats = [
            'total_sales' => $opening->getTotalSales(),
            'total_cash_sales' => $opening->getTotalCashSales(),
            'total_card_sales' => $opening->getTotalCardSales(),
            'total_withdrawals' => $opening->getTotalWithdrawals(),
            'total_deposits' => $opening->getTotalDeposits(),
            'expected_amount' => $opening->getExpectedAmount(),
        ];

        return response()->json([
            'success' => true,
            'data' => $opening,
        ]);
    }

    /**
     * Add transaction to cash register
     */
    public function addTransaction(Request $request, int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $opening = $cashRegister->currentOpening();

        if (!$opening) {
            return response()->json([
                'success' => false,
                'message' => 'No hay apertura activa para esta caja',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:withdrawal,deposit,expense,correction',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,transfer,qr,check,other',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = CashTransaction::create([
            'tenant_id' => auth()->user()->tenant_id,
            'cash_opening_id' => $opening->id,
            'user_id' => auth()->id(),
            'type' => $request->type,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference' => $request->reference,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $transaction->load(['user']),
            'message' => 'Transacción registrada exitosamente',
        ], 201);
    }

    /**
     * Get cash register summary
     */
    public function summary(int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $opening = $cashRegister->currentOpening();

        if (!$opening) {
            return response()->json([
                'success' => false,
                'message' => 'No hay apertura activa para esta caja',
            ], 404);
        }

        $sales = $opening->sales()
            ->with(['user', 'customer', 'details'])
            ->where('status', 'completed')
            ->get();

        $transactions = $opening->transactions()
            ->with(['user'])
            ->get();

        $summary = [
            'opening' => $opening,
            'cash_register' => $cashRegister,
            'sales_count' => $sales->count(),
            'total_sales' => $opening->getTotalSales(),
            'total_cash_sales' => $opening->getTotalCashSales(),
            'total_card_sales' => $opening->getTotalCardSales(),
            'total_withdrawals' => $opening->getTotalWithdrawals(),
            'total_deposits' => $opening->getTotalDeposits(),
            'expected_amount' => $opening->getExpectedAmount(),
            'sales_by_payment_method' => $sales->groupBy('payment_method')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('total'),
                ];
            }),
            'recent_sales' => $sales->take(10),
            'recent_transactions' => $transactions->take(10),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get cash register history (openings and closings)
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $cashRegister = CashRegister::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        $perPage = $request->get('per_page', 15);

        $openings = CashOpening::where('cash_register_id', $cashRegister->id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->with([
                'user:id,name,email',
                'closing.user:id,name,email'
            ])
            ->orderBy('opened_at', 'desc')
            ->paginate($perPage);

        // Add computed stats to each opening
        $openings->getCollection()->transform(function ($opening) {
            $opening->stats = [
                'total_sales' => $opening->getTotalSales(),
                'total_cash_sales' => $opening->getTotalCashSales(),
                'total_card_sales' => $opening->getTotalCardSales(),
                'total_withdrawals' => $opening->getTotalWithdrawals(),
                'total_deposits' => $opening->getTotalDeposits(),
                'expected_amount' => $opening->getExpectedAmount(),
            ];
            return $opening;
        });

        return response()->json([
            'success' => true,
            'data' => $openings->items(),
            'meta' => [
                'current_page' => $openings->currentPage(),
                'last_page' => $openings->lastPage(),
                'per_page' => $openings->perPage(),
                'total' => $openings->total(),
                'from' => $openings->firstItem(),
                'to' => $openings->lastItem(),
            ],
        ]);
    }

    /**
     * Generate cash register reports
     */
    public function reports(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cash_register_id' => 'nullable|exists:cash_registers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $cashRegisterId = $request->cash_register_id;

        // Base query for openings in date range
        $openingsQuery = CashOpening::where('tenant_id', $tenantId)
            ->whereBetween('opened_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($cashRegisterId) {
            $openingsQuery->where('cash_register_id', $cashRegisterId);
        }

        $openings = $openingsQuery->with(['cashRegister', 'user', 'closing'])->get();

        // Calculate totals
        $totalOpenings = $openings->count();
        $totalSales = 0;
        $totalCashSales = 0;
        $totalCardSales = 0;
        $closingsWithDifference = 0;

        foreach ($openings as $opening) {
            $totalSales += $opening->getTotalSales();
            $totalCashSales += $opening->getTotalCashSales();
            $totalCardSales += $opening->getTotalCardSales();

            if ($opening->closing && abs($opening->closing->difference) > 0.01) {
                $closingsWithDifference++;
            }
        }

        // Group by cash register
        $detailsByRegister = [];
        foreach ($openings->groupBy('cash_register_id') as $registerId => $registerOpenings) {
            $cashRegister = $registerOpenings->first()->cashRegister;
            $registerSales = 0;
            $registerCashSales = 0;
            $registerCardSales = 0;
            $registerDifferences = 0;

            foreach ($registerOpenings as $opening) {
                $registerSales += $opening->getTotalSales();
                $registerCashSales += $opening->getTotalCashSales();
                $registerCardSales += $opening->getTotalCardSales();

                if ($opening->closing && abs($opening->closing->difference) > 0.01) {
                    $registerDifferences++;
                }
            }

            $detailsByRegister[] = [
                'cash_register_id' => $registerId,
                'cash_register_name' => $cashRegister->name,
                'total_openings' => $registerOpenings->count(),
                'total_sales' => $registerSales,
                'total_cash_sales' => $registerCashSales,
                'total_card_sales' => $registerCardSales,
                'closings_with_difference' => $registerDifferences,
            ];
        }

        // Group by user
        $userStats = [];
        foreach ($openings->groupBy('user_id') as $userId => $userOpenings) {
            $user = $userOpenings->first()->user;
            $userSales = 0;

            foreach ($userOpenings as $opening) {
                $userSales += $opening->getTotalSales();
            }

            $userStats[] = [
                'user_id' => $userId,
                'user_name' => $user->name,
                'total_openings' => $userOpenings->count(),
                'total_sales' => $userSales,
            ];
        }

        // Sort users by total openings
        usort($userStats, function ($a, $b) {
            return $b['total_openings'] - $a['total_openings'];
        });

        $topUsers = array_slice($userStats, 0, 10);

        return response()->json([
            'success' => true,
            'data' => [
                'total_openings' => $totalOpenings,
                'total_sales' => $totalSales,
                'total_cash_sales' => $totalCashSales,
                'total_card_sales' => $totalCardSales,
                'closings_with_difference' => $closingsWithDifference,
                'details_by_register' => $detailsByRegister,
                'top_users' => $topUsers,
            ],
        ]);
    }
}
