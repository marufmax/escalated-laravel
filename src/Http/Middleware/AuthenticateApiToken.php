<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Models\ApiToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $apiToken = ApiToken::findByPlainText($token);

        if (! $apiToken) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['message' => 'Token has expired.'], 401);
        }

        if ($ability && ! $apiToken->hasAbility($ability)) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $user = $apiToken->tokenable;

        if (! $user) {
            return response()->json(['message' => 'Token owner not found.'], 401);
        }

        $apiToken->update([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ]);

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
