<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Display a listing of all permissions
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Get permissions grouped by category
     */
    public function grouped(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $grouped = $permissions->groupBy('group');

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }
}
