<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyClientApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = trim((string) config('client_api.header_name', 'X-Client-Token'));
        $configuredToken = trim((string) config('client_api.shared_token', ''));

        if ($configuredToken === '') {
            return $this->deny('client_api_not_configured', 503);
        }

        $providedToken = trim((string) $request->header($headerName, ''));

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return $this->deny('client_api_unauthorized', 401);
        }

        return $next($request);
    }

    private function deny(string $reason, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'reason' => $reason,
        ], $status);
    }
}
