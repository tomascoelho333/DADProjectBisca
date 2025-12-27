<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->type !== 'A') {

            // If not admin, reply with 403
            return response()->json([
                'message' => 'Unauthorized. Administrator access required.'
            ], 403);
        }

        // If they are, returns the request
        return $next($request);
    }
}
