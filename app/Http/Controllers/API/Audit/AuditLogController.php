<?php

namespace App\Http\Controllers\API\Audit;

use App\Http\Controllers\Controller;
use App\Models\Audit\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by event type
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // Filter by auditable type (model)
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', "%{$request->auditable_type}%");
        }

        // Filter by date range
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        // Search by URL or IP
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Display the specified audit log
     */
    public function show(string $id): JsonResponse
    {
        $log = AuditLog::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * Get available event types
     */
    public function eventTypes(): JsonResponse
    {
        $events = AuditLog::where('tenant_id', auth()->user()->tenant_id)
            ->distinct()
            ->pluck('event')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get available auditable types
     */
    public function auditableTypes(): JsonResponse
    {
        $types = AuditLog::where('tenant_id', auth()->user()->tenant_id)
            ->distinct()
            ->pluck('auditable_type')
            ->map(function ($type) {
                // Extract class name from namespace
                return class_basename($type);
            })
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }
}
