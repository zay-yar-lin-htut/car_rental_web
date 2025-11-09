<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WildcardCors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Illuminate\Http\Response $response */
        $response = $next($request);

        // ---- 1. Allow *any* origin ------------------------------------------------
        $response->headers->set('Access-Control-Allow-Origin', '*');

        // ---- 2. Methods we accept -------------------------------------------------
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');

        // ---- 3. Headers the browser may send (multipart needs Content-Type) ------
        $response->headers->set(
            'Access-Control-Allow-Headers',
            'Origin, Content-Type, Accept, Authorization, X-Requested-With'
        );

        // ---- 4. OPTIONAL: expose headers to JS ------------------------------------
        // $response->headers->set('Access-Control-Expose-Headers', 'Content-Length, X-My-Header');

        // ---- 5. Preflight (OPTIONS) → immediate 200 -------------------------------
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With');
        }

        return $response;
    }
}