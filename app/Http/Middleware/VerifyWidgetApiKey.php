<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWidgetApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil API key dari header Authorization: Bearer {token} atau header x-api-key, atau parameter ?api_key=
        $apiKey = $request->bearerToken() ?? $request->header('x-api-key') ?? $request->query('api_key');
        
        $validKey = env('WIDGET_API_KEY', 'mobile-secret-key-123'); // API key dibaca otomatis dari .env

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Invalid API Key.'
            ], 401);
        }

        return $next($request);
    }
}
