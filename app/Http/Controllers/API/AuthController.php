<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Register a new user and company
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'company_rtn' => 'required|string|size:14|unique:companies,rtn',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create company (tenant)
            $company = Company::create([
                'name' => $request->company_name,
                'legal_name' => $request->company_name,
                'rtn' => $request->company_rtn,
                'email' => $request->email,
                'phone' => $request->phone ?? null,
                'address' => $request->address ?? '',
                'is_active' => true,
                'settings' => json_encode([
                    'currency' => 'HNL',
                    'language' => 'es',
                    'timezone' => 'America/Tegucigalpa',
                ]),
            ]);

            // Create user with tenant_id
            $user = User::create([
                'tenant_id' => $company->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'active',
            ]);

            // Create a trial subscription (30 days)
            $basicPlan = Plan::where('slug', 'basic')->firstOrFail();
            Subscription::create([
                'tenant_id' => $company->id,
                'plan_id' => $basicPlan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'trial_ends_at' => Carbon::now()->addDays(30),
                'started_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(30),
                'auto_renew' => false,
            ]);

            // Create access token using Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'tenant_id' => $user->tenant_id,
                        'company' => [
                            'id' => $company->id,
                            'name' => $company->name,
                            'rtn' => $company->rtn,
                        ],
                    ],
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        // Log para debugging
        \Log::info('Login attempt', [
            'email' => $request->email,
            'all_data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            \Log::error('Login validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Ignore tenant scope during login to allow all users to authenticate
        $user = User::withoutGlobalScope('tenant')->where('email', $request->email)->first();

        \Log::info('User lookup', [
            'email' => $request->email,
            'user_found' => $user ? 'yes' : 'no',
            'user_id' => $user?->id
        ]);

        if (!$user || !Hash::check($request->password, $user->password)) {
            \Log::warning('Login failed', [
                'email' => $request->email,
                'user_exists' => $user ? 'yes' : 'no',
                'password_check' => $user ? (Hash::check($request->password, $user->password) ? 'valid' : 'invalid') : 'n/a'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        \Log::info('Login successful', ['user_id' => $user->id]);

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacte al administrador.'
            ], 403);
        }

        // Load roles and permissions
        $user->load(['roles.permissions', 'company', 'branch']);

        // Get all unique permissions from all user roles
        $permissions = $user->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id')->pluck('slug')->values();

        // Generate token using Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'company_id' => $user->tenant_id,
                    'branch_id' => $user->branch_id,
                    'avatar' => $user->avatar,
                    'status' => $user->status,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'name' => $user->company->name,
                        'slug' => $user->company->slug,
                        'rtn' => $user->company->rtn,
                        'logo' => $user->company->logo,
                        'is_active' => $user->company->is_active,
                    ] : null,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'code' => $user->branch->code,
                        'name' => $user->branch->name,
                        'address' => $user->branch->address,
                        'phone' => $user->branch->phone,
                        'is_main' => $user->branch->is_main,
                    ] : null,
                    'roles' => $user->roles->pluck('slug'),
                    'permissions' => $permissions,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles.permissions', 'company', 'branch']);

        // DEBUG: Log current user info
        \Log::info('AuthController@me - User verified', [
            'user_id' => $user->id,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
            'tenant_id' => $user->tenant_id,
            'headers' => [
                'X-Branch-ID' => $request->header('X-Branch-ID'),
                'X-Tenant-ID' => $request->header('X-Tenant-ID'),
                'X-Company-ID' => $request->header('X-Company-ID'),
            ]
        ]);

        // Get all unique permissions from all user roles
        $permissions = $user->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id')->pluck('slug')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->tenant_id,
                'branch_id' => $user->branch_id,
                'avatar' => $user->avatar,
                'status' => $user->status,
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'slug' => $user->company->slug,
                    'rtn' => $user->company->rtn,
                    'logo' => $user->company->logo,
                    'is_active' => $user->company->is_active,
                ] : null,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'code' => $user->branch->code,
                    'name' => $user->branch->name,
                    'address' => $user->branch->address,
                    'phone' => $user->branch->phone,
                    'is_main' => $user->branch->is_main,
                ] : null,
                'roles' => $user->roles->pluck('slug'),
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current user token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso'
        ]);
    }

    /**
     * Refresh access token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete the current token
        $request->user()->currentAccessToken()->delete();

        // Create a new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
