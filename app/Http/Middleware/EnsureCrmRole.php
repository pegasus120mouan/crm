<?php

namespace App\Http\Middleware;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = Session::get('utilisateur');
        $role = is_array($utilisateur) ? ($utilisateur['role'] ?? null) : null;

        if (! in_array($role, Utilisateur::ROLES_CRM, true)) {
            Session::forget('utilisateur');

            return redirect()->route('login');
        }

        if (! in_array($role, $roles, true)) {
            return redirect()->route(Utilisateur::routeAccueil($role));
        }

        return $next($request);
    }
}
