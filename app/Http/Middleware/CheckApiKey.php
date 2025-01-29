<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

class CheckApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Retrieve the API key from the headers
        $apiKey = $request->header('x-api-key');

        // Check if the API key is valid
        if (!$apiKey || !ApiKey::where('api_key', $apiKey)->exists()) {
            return response()->json(['error' => 'Wrong Api Key'], 401);
        }

        return $next($request);
    }
}