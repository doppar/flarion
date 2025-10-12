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
        return $this->linkMany(PersonalAccessToken::class, 'user_id', 'id');
    }

    /**
     * Create a new personal access token for the user.
     *
     * @param string $name
     * @param ?DateTimeInterface $expiresAt = null
     * @param array $abilities
     * @return \Doppar\Flarion\NewAccessToken
     */
    public function createToken(string $name, $expireAt = null, array $abilities = ['*']): NewAccessToken
    {
        return app(PersonalAccessToken::class)->createToken($this, $name, $abilities, $expireAt);
    }

    /**
     * Get the access token currently associated with the user.
     *
     * @return \Doppar\Flarion\PersonalAccessToken|null
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->accessToken;
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param string $ability
     * @return bool
     */
    public function tokenCan(string $ability): bool
    {
        return $this->accessToken->can($ability);
    }

    /**
     * Set the current access token for the user.
     *
     * @param PersonalAccessToken $accessToken
     * @return $this
     */
    public function withAccessToken(PersonalAccessToken $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}
