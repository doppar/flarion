<?php

namespace Doppar\Flarion\Tests\Unit;

use App\Models\User;
use Doppar\Flarion\ApiAuthenticate;
use Doppar\Flarion\PersonalAccessToken;
use Doppar\Flarion\Tests\Support\DatabaseTestCase;
use Phaseolies\Config\Config;
use Phaseolies\DI\Container;
use Phaseolies\Support\CookieJar;
use Phaseolies\Http\Request;

/**
 * A thin test double that lets tests inject a resolved token directly,
 * bypassing the real bearer/input/cookie lookup — the lookup precedence
 * itself is covered separately via getTokenFromRequest().
 */
class InjectableApiAuthenticate extends ApiAuthenticate
{
    private ?string $injectedToken = null;
    private bool $useInjectedToken = false;

    public function injectToken(?string $token): void
    {
        $this->injectedToken = $token;
        $this->useInjectedToken = true;
    }

    protected function getTokenFromRequest()
    {
        if ($this->useInjectedToken) {
            return $this->injectedToken;
        }

        return parent::getTokenFromRequest();
    }

    public function exposedGetTokenFromRequest()
    {
        return $this->getTokenFromRequest();
    }
}

class ApiAuthenticateTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::getInstance()->instance('cookie', new CookieJar());

        unset($_COOKIE['api_token']);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['api_token']);

        parent::tearDown();
    }

    public function test_check_is_false_when_there_is_no_token()
    {
        $auth = new InjectableApiAuthenticate();
        $auth->injectToken(null);

        $this->assertFalse($auth->check());
        $this->assertNull($auth->user());
    }

    public function test_check_is_false_for_an_unknown_token()
    {
        $auth = new InjectableApiAuthenticate();
        $auth->injectToken('this-token-does-not-exist');

        $this->assertFalse($auth->check());
    }

    public function test_user_returns_the_owner_of_a_valid_token()
    {
        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $auth = new InjectableApiAuthenticate();
        $auth->injectToken($result->plainTextToken);

        $resolvedUser = $auth->user();

        $this->assertInstanceOf(User::class, $resolvedUser);
        $this->assertSame($user->id, $resolvedUser->id);
        $this->assertTrue($auth->check());
    }

    public function test_user_attaches_the_current_access_token_to_the_resolved_user()
    {
        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $auth = new InjectableApiAuthenticate();
        $auth->injectToken($result->plainTextToken);

        $resolvedUser = $auth->user();

        $this->assertNotNull($resolvedUser->currentAccessToken());
        $this->assertSame($result->accessToken->id, $resolvedUser->currentAccessToken()->id);
        $this->assertSame($result->accessToken->id, $auth->token()->id);
    }

    public function test_user_result_is_cached_after_the_first_resolution()
    {
        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $auth = new InjectableApiAuthenticate();
        $auth->injectToken($result->plainTextToken);

        $first = $auth->user();
        $second = $auth->user();

        $this->assertSame($first, $second);
    }

    public function test_user_updates_last_used_at_on_the_token()
    {
        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');
        $this->assertNull($result->accessToken->last_used_at);

        $auth = new InjectableApiAuthenticate();
        $auth->injectToken($result->plainTextToken);
        $auth->user();

        $refreshed = PersonalAccessToken::findToken($result->plainTextToken);
        $this->assertNotNull($refreshed->last_used_at);
    }

    /**
     * KNOWN BUG (documented, not fixed here — out of scope for a test-only
     * change): the raw token is never persisted, only its lookup_hash, so a
     * token loaded back via findToken() always has $accessToken->token ===
     * null. The moment flarion.token_prefix is configured, isValidAccessToken()
     * calls Str::startsWith(null, $prefix), which throws — every request
     * with an otherwise-valid token crashes with a TypeError instead of
     * authenticating. This test documents the current (broken) behavior.
     */
    public function test_check_throws_when_a_token_prefix_is_configured()
    {
        Config::set('flarion.token_prefix', 'flarion_');

        $user = $this->createUser();
        $result = (new PersonalAccessToken())->createToken($user, 'api-key');

        $this->assertNull($result->accessToken->token);

        $auth = new InjectableApiAuthenticate();
        $auth->injectToken($result->plainTextToken);

        $this->expectException(\TypeError::class);

        $auth->check();
    }

    public function test_get_token_from_request_prefers_bearer_token()
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer bearer-token');
        Container::getInstance()->instance('request', $request);

        $_COOKIE['api_token'] = 'cookie-token';

        $auth = new InjectableApiAuthenticate();

        $this->assertSame('bearer-token', $auth->exposedGetTokenFromRequest());
    }

    public function test_get_token_from_request_falls_back_to_cookie_when_no_bearer_token()
    {
        $request = new Request();
        Container::getInstance()->instance('request', $request);

        $_COOKIE['api_token'] = 'cookie-token';

        $auth = new InjectableApiAuthenticate();

        $this->assertSame('cookie-token', $auth->exposedGetTokenFromRequest());
    }

    public function test_get_token_from_request_returns_null_when_nothing_is_present()
    {
        $request = new Request();
        Container::getInstance()->instance('request', $request);

        $auth = new InjectableApiAuthenticate();

        $this->assertNull($auth->exposedGetTokenFromRequest());
    }
}
