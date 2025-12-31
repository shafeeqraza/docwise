<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompany
{
    /**
     * Handle an incoming request and resolve company context.
     *
     * For Super Admins: Uses X-Company-Id header for impersonation
     * For Regular Users: Uses their assigned company_id
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Super Admin can impersonate companies via header
        if (!$user->isSuperAdmin()) {
            $company = Company::findOrFail($user->company_id);

            $request->attributes->add(['current_company_id' => $user->company_id]);
            $request->attributes->add(['impersonated_company' => $company]);
            return $next($request);
        }

        // Check for impersonation header
        $companyId = $request->header('X-Company-Id');

        if (!$companyId) {
            abort(400, __('errors.company_context_required'));
        }

        // Validate company exists and is accessible
        $company = Company::findOrFail($companyId);


        // Set company context for this request
        $request->attributes->add(['current_company_id' => $company->id]);
        $request->attributes->add(['impersonated_company' => $company]);

        return $next($request);
    }
}
