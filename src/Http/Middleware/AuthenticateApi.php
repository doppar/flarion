<?php

namespace Doppar\Flarion\Http\Middleware;

use Phaseolies\Middleware\Contracts\Middleware;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Doppar\Flarion\ApiAuthenticate;
use Closure;

class AuthenticateApi implements Middleware
{
    /**
     * The authentication guard.
     *
     * @var ApiAuthenticate
     */
    protected ApiAuthenticate $auth;

    /**
     * Create a new middleware instance.
     *
     * @param ApiAuthenticate $apiAuthenticate
     * @return void
     */
    public function __construct(ApiAuthenticate $apiAuthenticate)
    {
        $this->auth = $apiAuthenticate;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure(\Phaseolies\Http\Request) $next
     * @param string ...$parameters
     * @return Response
     */
    public function __invoke(Request $request, Closure $next, string ...$parameters): Response
    {
        [$actors, $ability] = $this->parseParameters($parameters);

        if (!$this->auth->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($this->auth->token()->hasExpired()) {
            return response()->json(['message' => 'Token expired.'], 401);
        }

        if ($actors && !in_array($this->auth->actor(), $actors, true)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($ability && $this->auth->token()->cant($ability)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }

    /**
     * Split the middleware parameters into allowed actors and an ability.
     *
     * @param array<int, string> $parameters
     * @return array{0: array<int, string>, 1: string|null}
     */
    protected function parseParameters(array $parameters): array
    {
        $actors = [];
        $ability = null;

        foreach ($parameters as $parameter) {
            $parameter = trim($parameter);

            if (str_starts_with($parameter, 'actor=')) {
                $actors[] = substr($parameter, 6);
            } elseif ($parameter !== '') {
                $ability = $parameter;
            }
        }

        return [$actors, $ability];
    }
}
