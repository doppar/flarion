<?php

namespace Doppar\Flarion;

class NewAccessToken
{
    /**
     * The access token instance.
     *
     * @var \Doppar\Flarion\PersonalAccessToken
     */
    public $accessToken;

    /**
     * The plain text version of the token.
     *
     * @var string
     */
    public $plainTextToken;

    /**
     * Create a new access token result.
     *
     * @param \Doppar\Flarion\PersonalAccessToken $accessToken
     * @param string $plainTextToken
     * @return void
     */
    public function __construct($accessToken, $plainTextToken)
    {
        $this->accessToken = $accessToken;
        $this->plainTextToken = $plainTextToken;
    }

    /**
     * Get the plain text token.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->plainTextToken;
    }
}
