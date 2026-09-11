<?php

namespace Doppar\Flarion\Tests\Unit;

use Doppar\Flarion\NewAccessToken;
use Doppar\Flarion\PersonalAccessToken;
use Doppar\Flarion\Tests\Support\DatabaseTestCase;

class TokenableTest extends DatabaseTestCase
{
    public function test_current_access_token_is_null_by_default()
    {
        $user = $this->createUser();

        $this->assertNull($user->currentAccessToken());
    }

    public function test_with_access_token_sets_and_returns_the_user_fluently()
    {
        $user = $this->createUser();
        $token = new PersonalAccessToken();

        $result = $user->withAccessToken($token);

        $this->assertSame($user, $result);
        $this->assertSame($token, $user->currentAccessToken());
    }

    public function test_token_can_delegates_to_the_current_access_tokens_can_method()
    {
        $user = $this->createUser();
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['posts:read']);
        $user->withAccessToken($token);

        $this->assertTrue($user->tokenCan('posts:read'));
        $this->assertFalse($user->tokenCan('posts:delete'));
    }

    public function test_create_token_persists_a_real_token_for_the_user()
    {
        $user = $this->createUser();

        $result = $user->createToken('api-key', null, ['posts:read']);

        $this->assertInstanceOf(NewAccessToken::class, $result);
        $this->assertSame($user->id, $result->accessToken->user_id);
        $this->assertSame('api-key', $result->accessToken->name);

        $found = PersonalAccessToken::findToken($result->plainTextToken);
        $this->assertNotNull($found);
        $this->assertSame($result->accessToken->id, $found->id);
    }

    public function test_create_token_defaults_to_wildcard_ability()
    {
        $user = $this->createUser();

        $result = $user->createToken('api-key');

        $this->assertSame(['*'], $result->accessToken->getAbilitiesAttribute());
    }

    public function test_tokens_relationship_returns_the_users_tokens()
    {
        $user = $this->createUser();
        $user->createToken('token-a');
        $user->createToken('token-b');

        $otherUser = $this->createUser(['email' => 'other@example.com']);
        $otherUser->createToken('token-c');

        $tokens = $user->tokens()->get();

        $this->assertCount(2, $tokens);
    }
}
