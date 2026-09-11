<?php

namespace Doppar\Flarion\Tests\Unit;

use Doppar\Flarion\NewAccessToken;
use Doppar\Flarion\PersonalAccessToken;
use Doppar\Flarion\Tests\Support\DatabaseTestCase;
use Phaseolies\Config\Config;

class PersonalAccessTokenTest extends DatabaseTestCase
{
    public function test_abilities_round_trip_through_json()
    {
        $token = new PersonalAccessToken();

        $token->setAbilitiesAttribute(['read', 'write']);

        $this->assertSame(['read', 'write'], $token->getAbilitiesAttribute());
        $this->assertSame(json_encode(['read', 'write']), $token->abilities);
    }

    public function test_abilities_default_to_empty_array_when_unset()
    {
        $token = new PersonalAccessToken();

        $this->assertSame([], $token->getAbilitiesAttribute());
    }

    public function test_abilities_default_to_empty_array_when_malformed_json()
    {
        $token = new PersonalAccessToken(['abilities' => 'not-json']);

        $this->assertSame([], $token->getAbilitiesAttribute());
    }

    public function test_setting_a_non_array_ability_value_falls_back_to_empty_array()
    {
        $token = new PersonalAccessToken();

        $token->setAbilitiesAttribute('not-an-array');

        $this->assertSame([], $token->getAbilitiesAttribute());
    }

    public function test_can_returns_true_for_wildcard_ability()
    {
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['*']);

        $this->assertTrue($token->can('anything'));
        $this->assertTrue($token->can('posts:delete'));
    }

    public function test_can_returns_true_when_single_ability_matches()
    {
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['posts:read']);

        $this->assertTrue($token->can('posts:read'));
    }

    public function test_can_returns_false_when_ability_is_missing()
    {
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['posts:read']);

        $this->assertFalse($token->can('posts:delete'));
    }

    public function test_can_requires_all_ampersand_joined_abilities()
    {
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['posts:read', 'posts:write']);

        $this->assertTrue($token->can('posts:read & posts:write'));
        $this->assertFalse($token->can('posts:read & posts:delete'));
    }

    public function test_can_trims_whitespace_around_ampersand_joined_abilities()
    {
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['posts:read', 'posts:write']);

        $this->assertTrue($token->can('  posts:read   &   posts:write  '));
    }

    public function test_cant_is_the_inverse_of_can()
    {
        $token = new PersonalAccessToken();
        $token->setAbilitiesAttribute(['posts:read']);

        $this->assertFalse($token->cant('posts:read'));
        $this->assertTrue($token->cant('posts:delete'));
    }

    public function test_has_expired_is_false_when_expires_at_is_null()
    {
        $token = new PersonalAccessToken();

        $this->assertFalse($token->hasExpired());
    }

    public function test_has_expired_is_true_for_a_past_expiration()
    {
        $token = new PersonalAccessToken();
        $token->expires_at = now()->subMinute();

        $this->assertTrue($token->hasExpired());
    }

    public function test_has_expired_is_false_for_a_future_expiration()
    {
        $token = new PersonalAccessToken();
        $token->expires_at = now()->addMinute();

        $this->assertFalse($token->hasExpired());
    }

    public function test_generate_token_string_has_no_prefix_by_default()
    {
        Config::set('flarion.token_prefix', '');

        $token = new PersonalAccessToken();
        $plain = $token->generateTokenString();

        $this->assertIsString($plain);
        $this->assertNotSame('', $plain);
    }

    public function test_generate_token_string_includes_configured_prefix()
    {
        Config::set('flarion.token_prefix', 'flarion_');

        $token = new PersonalAccessToken();
        $plain = $token->generateTokenString();

        $this->assertStringStartsWith('flarion_', $plain);
    }

    public function test_generate_token_string_is_unique_across_calls()
    {
        $token = new PersonalAccessToken();

        $first = $token->generateTokenString();
        $second = $token->generateTokenString();

        $this->assertNotSame($first, $second);
    }

    public function test_create_token_persists_a_row_and_returns_new_access_token()
    {
        $user = $this->createUser();

        $result = (new PersonalAccessToken())->createToken($user, 'api-key', ['posts:read']);

        $this->assertInstanceOf(NewAccessToken::class, $result);
        $this->assertInstanceOf(PersonalAccessToken::class, $result->accessToken);
        $this->assertIsString($result->plainTextToken);
        $this->assertSame($user->id, $result->accessToken->user_id);
        $this->assertSame('api-key', $result->accessToken->name);
        $this->assertSame(json_encode(['posts:read']), $result->accessToken->abilities);
        $this->assertSame(['posts:read'], $result->accessToken->getAbilitiesAttribute());
    }

    public function test_create_token_stores_a_lookup_hash_of_the_plain_token()
    {
        Config::set('app.key', 'base64:test-key');

        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $expectedHash = hash_hmac('sha256', $result->plainTextToken, 'base64:test-key');

        $this->assertSame($expectedHash, $result->accessToken->lookup_hash);
    }

    public function test_create_token_sets_expiration_from_config_when_present()
    {
        Config::set('flarion.expiration', 60);

        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $this->assertNotNull($result->accessToken->expires_at);
    }

    public function test_create_token_uses_explicit_expiration_when_config_is_unset()
    {
        Config::set('flarion.expiration', null);
        $explicitExpiry = now()->addDays(5);

        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key', ['*'], $explicitExpiry);

        $this->assertNotNull($result->accessToken->expires_at);
    }

    public function test_find_token_returns_the_matching_token()
    {
        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $found = PersonalAccessToken::findToken($result->plainTextToken);

        $this->assertNotNull($found);
        $this->assertSame($result->accessToken->id, $found->id);
    }

    public function test_find_token_returns_null_for_an_unknown_token()
    {
        $this->createUser();

        $found = PersonalAccessToken::findToken('this-token-does-not-exist');

        $this->assertNull($found);
    }
}
