<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CurrentCompanyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $companyId = $request->header('company_id');

        // Optionally, you can validate the account ID or perform additional checks

        // Set the current account ID in the authentication context
        if ($companyId) {
            Auth::user()->current_company_id = $companyId; // Assuming you have this property
        } else {
            Auth::user()->current_company_id = Client::where('user_id', auth()->user()?->id)->first()?->id;
        }
    }
}
