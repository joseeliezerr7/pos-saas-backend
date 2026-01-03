<?php

namespace App\Http\Controllers\API\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Branch;
use App\Models\Product;
use App\Models\User;
use App\Models\User\Role;
use App\Models\Sale\Sale;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador para Super Admin - Gestión de Tenants
 *
 * Permite al dueño del SaaS administrar todos los clientes/tenants
 */
class SuperAdminTenantController extends Controller
{
    /**
     * Listar todos los tenants con estadísticas
     *
     * GET /api/super-admin/tenants
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::with([
            'subscription.plan',
            'branches' => function ($query) {
                $query->withoutGlobalScope('tenant');
            },
            'mainBranch' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }
        ])
        ->withCount([
            'users' => function ($query) {
                $query->withoutGlobalScope('tenant');
            },
            'branches' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }
        ]);

        // Filtros - Use filled() instead of has() to check for non-null values
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('subscription_status')) {
            $query->whereHas('subscription', function ($q) use ($request) {
                $q->where('status', $request->subscription_status);
            });
        }

        if ($request->filled('plan_id')) {
            $query->whereHas('subscription', function ($q) use ($request) {
                $q->where('plan_id', $request->plan_id);
            });
        }

        // Búsqueda - Use filled() instead of has()
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('legal_name', 'like', "%{$search}%")
                  ->orWhere('rtn', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $tenants = $query->paginate($perPage);

        // Agregar estadísticas a cada tenant
        $tenants->getCollection()->transform(function ($tenant) {
            return $this->enrichTenantWithStats($tenant);
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * Crear un nuevo tenant
     *
     * POST /api/super-admin/tenants
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'company_rtn' => 'required|string|size:14|unique:companies,rtn',
            'legal_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'website' => 'nullable|url',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'plan_id' => 'required|exists:plans,id',
            'subscription_status' => 'required|in:trial,active,suspended',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'subscription_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Crear Company
            $company = Company::create([
                'name' => $request->company_name,
                'legal_name' => $request->legal_name ?? $request->company_name,
                'rtn' => $request->company_rtn,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'website' => $request->website,
                'is_active' => true,
                'settings' => [
                    'currency' => 'HNL',
                    'language' => 'es',
                    'timezone' => 'America/Tegucigalpa',
                ],
            ]);

            // Crear Usuario Admin
            $user = User::withoutGlobalScope('tenant')->create([
                'tenant_id' => $company->id,
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'status' => 'active',
            ]);

            // Buscar o crear rol de admin
            $adminRole = Role::withoutGlobalScope('tenant')
                ->where(function ($query) use ($company) {
                    $query->where('tenant_id', $company->id)
                          ->orWhere('is_system', true);
                })
                ->where('slug', 'admin')
                ->first();

            if (!$adminRole) {
                // Crear rol admin si no existe
                $adminRole = Role::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $company->id,
                    'name' => 'Administrador',
                    'slug' => 'admin',
                    'is_system' => false,
                ]);
            }

            $user->roles()->attach($adminRole->id);

            // Crear Sucursal Principal
            Branch::withoutGlobalScope('tenant')->create([
                'tenant_id' => $company->id,
                'name' => 'Sucursal Principal',
                'code' => 'MAIN',
                'is_main' => true,
                'is_active' => true,
            ]);

            // Crear Suscripción
            $trialDays = $request->trial_days ?? 30;
            $subscriptionDays = $request->subscription_days ?? 30;

            Subscription::create([
                'tenant_id' => $company->id,
                'plan_id' => $request->plan_id,
                'status' => $request->subscription_status,
                'billing_cycle' => 'monthly',
                'trial_ends_at' => $request->subscription_status === 'trial'
                    ? now()->addDays($trialDays)
                    : null,
                'started_at' => now(),
                'expires_at' => now()->addDays($subscriptionDays),
                'auto_renew' => false,
            ]);

            DB::commit();

            // Cargar relaciones para la respuesta
            $company->load([
                'subscription.plan',
                'users' => function ($query) {
                    $query->withoutGlobalScope('tenant')->with(['roles' => function ($q) {
                        $q->withoutGlobalScope('tenant');
                    }]);
                },
                'branches' => function ($query) {
                    $query->withoutGlobalScope('tenant');
                }
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente creado exitosamente',
                'data' => $company,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('[SuperAdmin] Error creating tenant: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalles de un tenant específico
     *
     * GET /api/super-admin/tenants/{id}
     */
    public function show(int $id): JsonResponse
    {
        $tenant = Company::with([
            'subscription.plan',
            'branches' => function ($query) {
                $query->withoutGlobalScope('tenant');
            },
            'users' => function ($query) {
                $query->withoutGlobalScope('tenant')->with(['roles' => function ($q) {
                    $q->withoutGlobalScope('tenant');
                }]);
            },
            'mainBranch' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }
        ])
        ->withCount([
            'users' => function ($query) {
                $query->withoutGlobalScope('tenant');
            },
            'branches' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }
        ])
        ->findOrFail($id);

        $stats = $this->getTenantDetailedStats($tenant);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $this->enrichTenantWithStats($tenant),
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Activar o desactivar un tenant
     *
     * POST /api/super-admin/tenants/{id}/toggle-status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $tenant = Company::findOrFail($id);
        $tenant->is_active = !$tenant->is_active;
        $tenant->save();

        $status = $tenant->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => "Tenant {$status} exitosamente",
        ]);
    }

    /**
     * Actualizar suscripción de un tenant
     *
     * PUT /api/super-admin/tenants/{id}/subscription
     */
    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active,expired,canceled,suspended',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant = Company::findOrFail($id);
        $subscription = $tenant->subscription;

        if (!$subscription) {
            // Crear nueva suscripción
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $request->plan_id,
                'status' => $request->status,
                'started_at' => now(),
                'expires_at' => $request->expires_at ?? now()->addMonth(),
            ]);
        } else {
            // Actualizar suscripción existente
            $subscription->update([
                'plan_id' => $request->plan_id,
                'status' => $request->status,
                'expires_at' => $request->expires_at,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription->load('plan'),
            'message' => 'Suscripción actualizada exitosamente',
        ]);
    }

    /**
     * Dashboard de métricas globales
     *
     * GET /api/super-admin/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'tenants' => [
                'total' => Company::count(),
                'active' => Company::where('is_active', true)->count(),
                'inactive' => Company::where('is_active', false)->count(),
                'new_this_month' => Company::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],
            'subscriptions' => [
                'active' => Subscription::where('status', 'active')->count(),
                'trial' => Subscription::where('status', 'trial')->count(),
                'expired' => Subscription::where('status', 'expired')->count(),
                'canceled' => Subscription::where('status', 'canceled')->count(),
                'suspended' => Subscription::where('status', 'suspended')->count(),
            ],
            'plans' => Plan::withCount(['subscriptions' => function ($query) {
                    $query->where('status', 'active');
                }])
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'active_subscriptions' => $plan->subscriptions_count,
                    ];
                }),
            'global' => [
                'total_users' => User::withoutGlobalScope('tenant')->count(),
                'total_branches' => \App\Models\Tenant\Branch::withoutGlobalScope('tenant')->count(),
                'total_products' => Product::withoutGlobalScope('tenant')->count(),
                'total_sales' => Sale::withoutGlobalScope('tenant')->count(),
                'monthly_revenue' => $this->calculateMonthlyRevenue(),
            ],
            'recent_signups' => Company::with('subscription.plan')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'created_at' => $tenant->created_at,
                        'plan' => $tenant->subscription?->plan->name ?? 'Sin plan',
                    ];
                }),
            'growth' => [
                'tenants_growth' => $this->getTenantGrowth(),
                'revenue_growth' => $this->getRevenueGrowth(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Exportar datos de tenants
     *
     * GET /api/super-admin/tenants/export
     */
    public function export(Request $request): JsonResponse
    {
        $tenants = Company::with([
            'subscription.plan',
            'branches' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }
        ])
        ->withCount([
            'users' => function ($query) {
                $query->withoutGlobalScope('tenant');
            },
            'branches' => function ($query) {
                $query->withoutGlobalScope('tenant');
            }
        ])
        ->get()
        ->map(function ($tenant) {
            $stats = $this->getTenantDetailedStats($tenant);
                return [
                    'ID' => $tenant->id,
                    'Nombre' => $tenant->name,
                    'Razón Social' => $tenant->legal_name,
                    'RTN' => $tenant->rtn,
                    'Email' => $tenant->email,
                    'Teléfono' => $tenant->phone,
                    'Estado' => $tenant->is_active ? 'Activo' : 'Inactivo',
                    'Plan' => $tenant->subscription?->plan->name ?? 'Sin plan',
                    'Estado Suscripción' => $tenant->subscription?->status ?? 'N/A',
                    'Usuarios' => $tenant->users_count,
                    'Sucursales' => $tenant->branches_count,
                    'Productos' => $stats['products_count'],
                    'Ventas Totales' => $stats['total_sales'],
                    'Fecha Registro' => $tenant->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tenants,
            'message' => 'Datos exportados exitosamente',
        ]);
    }

    /**
     * Eliminar un tenant (soft delete)
     *
     * DELETE /api/super-admin/tenants/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $tenant = Company::findOrFail($id);

        // Validar que no sea el último tenant activo
        if (Company::where('is_active', true)->count() === 1 && $tenant->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_LAST_TENANT',
                    'message' => 'No puedes eliminar el último tenant activo',
                ]
            ], 422);
        }

        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant eliminado exitosamente',
        ]);
    }

    // ========== Métodos Helper Privados ==========

    /**
     * Enriquecer tenant con estadísticas básicas
     */
    private function enrichTenantWithStats(Company $tenant): Company
    {
        // Remove global tenant scope to allow Super Admin to view all tenant data
        $tenant->stats = [
            'products_count' => Product::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'sales_count' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'revenue_last_month' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->sum('total'),
            'subscription_status' => $tenant->subscription?->status ?? 'none',
            'subscription_expires_at' => $tenant->subscription?->expires_at,
        ];

        return $tenant;
    }

    /**
     * Obtener estadísticas detalladas de un tenant
     */
    private function getTenantDetailedStats(Company $tenant): array
    {
        // Remove global tenant scope to allow Super Admin to view all tenant data
        return [
            'products_count' => Product::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'categories_count' => \App\Models\Category::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'customers_count' => \App\Models\Customer::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'suppliers_count' => \App\Models\Supplier::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'total_sales' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'total_purchases' => Purchase::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count(),
            'revenue_total' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->sum('total'),
            'revenue_this_month' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total'),
            'revenue_last_month' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->sum('total'),
            'last_sale_date' => Sale::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)
                ->orderBy('created_at', 'desc')
                ->value('created_at'),
        ];
    }

    /**
     * Calcular ingresos mensuales totales
     */
    private function calculateMonthlyRevenue(): float
    {
        // Calcular basado en suscripciones activas
        return Subscription::where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(function ($subscription) {
                return $subscription->plan->monthly_price ?? 0;
            });
    }

    /**
     * Obtener crecimiento de tenants (últimos 6 meses)
     */
    private function getTenantGrowth(): array
    {
        $growth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = Company::whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();

            $growth[] = [
                'month' => $month->format('M Y'),
                'count' => $count,
            ];
        }

        return $growth;
    }

    /**
     * Obtener crecimiento de ingresos (últimos 6 meses)
     */
    private function getRevenueGrowth(): array
    {
        $growth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);

            // Contar suscripciones activas en ese mes
            $revenue = Subscription::where('status', 'active')
                ->whereDate('started_at', '<=', $month->endOfMonth())
                ->whereDate('expires_at', '>=', $month->startOfMonth())
                ->with('plan')
                ->get()
                ->sum(function ($subscription) {
                    return $subscription->plan->monthly_price ?? 0;
                });

            $growth[] = [
                'month' => $month->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        return $growth;
    }
}
