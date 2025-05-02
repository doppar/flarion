<?php

namespace Doppar\Flarion;

use Phaseolies\Support\Facades\Str;
use Doppar\Flarion\PersonalAccessToken;

class ApiAuthenticate
{
    /**
     * The currently authenticated user.
     *
     * @var \App\Models\User|null
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
        $this->token();

        return $this->user() !== null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \App\Models\User|null
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

        $this->token = $accessToken;
        $this->token->fill(['last_used_at' => now()]);
        $this->token->save();

        return $this->user = $accessToken->user->withAccessToken($accessToken);
    }

    /**
     * Get the token from the request.
     *
     * @return string|null
     */
    protected function getTokenFromRequest()
    {
        $token = $this->getBearerToken();

        if (empty($token)) {
            $token = request()->input('api_token');
        }

        if (empty($token)) {
            $token = cookie()->get('api_token');
        }

        return $token;
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
