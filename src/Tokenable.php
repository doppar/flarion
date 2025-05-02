<?php

namespace Doppar\Flarion;

trait Tokenable
{
    /**
     * The access token the user is using for the current request.
     *
     * @var \Doppar\Flarion\PersonalAccessToken|null
     */
    protected $accessToken;

    /**
     * Get the access tokens that belong to the user.
     */
    public function tokens()
    {
        return $this->oneToMany(PersonalAccessToken::class, 'user_id', 'id');
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param string $name
     * @param ?DateTimeInterface $expiresAt = null
     * @param array $abilities
     * @return \Doppar\Flarion\NewAccessToken
     */
    public function createToken(string $name, $expireAt = null, array $abilities = ['*'])
    {
        return app(PersonalAccessToken::class)->createToken($this, $name, $abilities, $expireAt);
    }

    /**
     * Get the access token currently associated with the user.
     * Note: This only works on the user instance returned by the authentication system.
     * Creating a new user instance will not have the token association.
     *
     * @return \Doppar\Flarion\PersonalAccessToken|null
     */
    public function currentAccessToken()
    {
        $this->accessToken = app(ApiAuthenticate::class)->token();

        return $this->accessToken;
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param string $ability
     * @return bool
     */
    public function tokenCan(string $ability)
    {
        $this->accessToken = app(ApiAuthenticate::class)->token();

        return $this->accessToken->can($ability);
    }

    /**
     * Set the current access token for the user.
     *
     * @param PersonalAccessToken $accessToken
     * @return $this
     */
    public function withAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}
