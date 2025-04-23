<?php

namespace Doppar\Flarion\Http\Middleware;

use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Closure;

class AuthenticateApi implements Middleware
{
    /**
     * The authentication guard.
     *
     * @var \Phaseolies\Flarion\ApiAuthenticate
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param \Phaseolies\Flarion\ApiAuthenticate $auth
     * @return void
     */
    public function __construct()
    {
        $this->auth = app('api-auth');
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure(\Phaseolies\Http\Request) $next
     * @param string|null $ability
     * @return Response
     */
    public function __invoke(Request $request, Closure $next, ?string $ability = null): Response
    {
        if (!$this->auth->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($this->auth->token()->hasExpired()) {
            return response()->json(['message' => 'Token has been expired.'], 401);
        }

        if ($ability && $this->auth->token()->cant($ability)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}
