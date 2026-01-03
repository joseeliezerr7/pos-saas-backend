<?php

namespace App\Http\Controllers\API\Credit;

use App\Http\Controllers\Controller;
use App\Services\CreditReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CreditReportController extends Controller
{
    public function __construct(protected CreditReportService $reportService) {}

    /**
     * Get customer account statement
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function customerStatement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $statement = $this->reportService->getCustomerStatement(
                $request->customer_id,
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'data' => $statement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STATEMENT_GENERATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get aging report
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function agingReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Filter by user's branch (if user has assigned branch)
            $userBranchId = auth()->user()->branch_id;
            $filterBranchId = $userBranchId ?? $request->branch_id ?? null;

            $report = $this->reportService->getAgingReport(
                auth()->user()->tenant_id,
                $filterBranchId
            );

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AGING_REPORT_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get accounts receivable dashboard
     *
     * @return JsonResponse
     */
    public function dashboard(): JsonResponse
    {
        try {
            $stats = $this->reportService->getDashboardStats(auth()->user()->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DASHBOARD_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
