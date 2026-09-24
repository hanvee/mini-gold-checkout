<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards POST /api/mock-payments. Fails closed: if MOCK_PAYMENT_TOKEN is
 * unset/empty, every request is rejected — an empty header never matches
 * an empty config value. See AI_AGENT.md §2.4.
 */
class VerifyMockPaymentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.mock_payment.token');
        $providedToken = (string) $request->header('X-Payment-Token', '');

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'result' => 'rejected',
                'code' => 'invalid_token',
                'message' => 'Missing or incorrect X-Payment-Token header.',
            ], 401);
        }

        return $next($request);
    }
}
