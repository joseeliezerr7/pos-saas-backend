<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class TenantScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $tenantId = auth()->user()->tenant_id;

            // Store tenant_id in application container
            App::instance('tenant_id', $tenantId);

            // Add tenant_id to request
            $request->merge(['tenant_id' => $tenantId]);

            // Cache tenant company data
            $company = auth()->user()->company;
            App::instance('tenant_company', $company);
        }

        return $next($request);
    }
}
