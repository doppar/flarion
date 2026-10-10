<?php

namespace Doppar\Flarion;

use Phaseolies\Support\Facades\Str;
use Doppar\Flarion\PersonalAccessToken;

class ApiAuthenticate
{
    /**
     * The currently authenticated user.
     *
     * @var \Phaseolies\Auth\Authable|null
     */
    protected $user;

    /**
     * The current access token.
     *
     * @var \Doppar\Flarion\PersonalAccessToken|null
     */
    protected $token;

    /**
     * Determine if the request has a valid token.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Phaseolies\Auth\Authable|null
     */
    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$this->isValidAccessToken($accessToken)) {
            return null;
        }

        $owner = $accessToken->tokenable;

        if (!$owner) {
            return null;
        }

        $this->token = $accessToken;
        $this->token->fill(['last_used_at' => now()]);
        $this->token->save();

        return $this->user = $owner->withAccessToken($accessToken);
    }

    /**
     * Get the name of the actor the current token was issued for.
     *
     * @return string|null
     */
    public function actor(): ?string
    {
        $this->user();

        return $this->token?->actor;
    }

    /**
     * Get the token from the request.
     *
     * @return string|null
     */
    protected function getTokenFromRequest()
    {
        foreach ((array) config('flarion.token_sources', ['header']) as $source) {
            $token = match ($source) {
                'header' => $this->getBearerToken(),
                'input' => request()->input('api_token'),
                'cookie' => cookie()->get('api_token'),
                default => null,
            };

            if (!empty($token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    protected function getBearerToken()
    {
        return request()->bearerToken();
    }

    /**
     * Determine if the provided access token is valid.
     *
     * @param \Doppar\Flarion\PersonalAccessToken|null $accessToken
     * @return bool
     */
    protected function isValidAccessToken($accessToken)
    {
        if (!$accessToken) {
            return false;
        }

        if (!Actors::has((string) $accessToken->actor)) {
            return false;
        }

        $tokenPrefix = config('flarion.token_prefix', '');

        if ($tokenPrefix && !Str::startsWith($accessToken->token, $tokenPrefix)) {
            return false;
        }

        return true;
    }

    /**
     * Get the current access token being used by the user.
     *
     * @return \Doppar\Flarion\PersonalAccessToken|null
     */
    public function token()
    {
        return $this->token;
    }
}
