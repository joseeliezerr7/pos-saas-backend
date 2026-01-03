<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FinancialReportController extends Controller
{
    protected $financialReportService;

    public function __construct(FinancialReportService $financialReportService)
    {
        $this->financialReportService = $financialReportService;
    }

    /**
     * Generar Estado de Resultados (P&L)
     */
    public function profitAndLoss(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getProfitAndLoss(
            auth()->user()->tenant_id,
            $request->start_date,
            $request->end_date,
            $request->branch_id
        );

        return response()->json($data);
    }

    /**
     * Generar Balance General
     */
    public function balanceSheet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'as_of_date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getBalanceSheet(
            auth()->user()->tenant_id,
            $request->as_of_date,
            $request->branch_id
        );

        return response()->json($data);
    }

    /**
     * Generar Flujo de Caja
     */
    public function cashFlow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getCashFlow(
            auth()->user()->tenant_id,
            $request->start_date,
            $request->end_date,
            $request->branch_id
        );

        return response()->json($data);
    }

    /**
     * Análisis de rentabilidad por producto
     */
    public function productProfitability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getProductProfitability(
            auth()->user()->tenant_id,
            $request->start_date,
            $request->end_date,
            $request->limit ?? 20
        );

        return response()->json($data);
    }

    /**
     * Análisis de rentabilidad por categoría
     */
    public function categoryProfitability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getCategoryProfitability(
            auth()->user()->tenant_id,
            $request->start_date,
            $request->end_date
        );

        return response()->json($data);
    }

    /**
     * Análisis de rentabilidad por sucursal
     */
    public function branchProfitability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getBranchProfitability(
            auth()->user()->tenant_id,
            $request->start_date,
            $request->end_date
        );

        return response()->json($data);
    }

    /**
     * Comparativo mensual
     */
    public function monthlyComparison(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->financialReportService->getMonthlyComparison(
            auth()->user()->tenant_id,
            $request->year,
            $request->branch_id
        );

        return response()->json($data);
    }

    /**
     * Reporte completo con todos los análisis
     */
    public function comprehensiveReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $companyId = auth()->user()->tenant_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $branchId = $request->branch_id;

        $data = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'profit_and_loss' => $this->financialReportService->getProfitAndLoss(
                $companyId,
                $startDate,
                $endDate,
                $branchId
            ),
            'cash_flow' => $this->financialReportService->getCashFlow(
                $companyId,
                $startDate,
                $endDate,
                $branchId
            ),
            'product_profitability' => $this->financialReportService->getProductProfitability(
                $companyId,
                $startDate,
                $endDate,
                10
            ),
            'category_profitability' => $this->financialReportService->getCategoryProfitability(
                $companyId,
                $startDate,
                $endDate
            ),
        ];

        return response()->json($data);
    }
}
