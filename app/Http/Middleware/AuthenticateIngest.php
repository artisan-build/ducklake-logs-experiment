<?php

namespace App\Http\Middleware;

use App\Models\LogApp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIngest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $app = LogApp::query()->where('token_hash', hash('sha256', $token))->first();

        if ($app === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('log_app', $app);

        return $next($request);
    }
}
