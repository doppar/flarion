<?php

namespace Doppar\Flarion\Tests\Unit\Http\Middleware;

use Doppar\Flarion\ApiAuthenticate;
use Doppar\Flarion\Http\Middleware\AuthenticateApi;
use Doppar\Flarion\PersonalAccessToken;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class AuthenticateApiTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }

    public function test_it_returns_401_when_not_authenticated()
    {
        $auth = Mockery::mock(ApiAuthenticate::class);
        $auth->shouldReceive('check')->once()->andReturn(false);

        $middleware = new AuthenticateApi($auth);
        $called = false;

        $response = $middleware(new Request(), function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Unauthenticated.'], $response->getData());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_it_returns_401_when_token_has_expired()
    {
        $token = $this->createMock(PersonalAccessToken::class);
        $token->method('hasExpired')->willReturn(true);

        $auth = Mockery::mock(ApiAuthenticate::class);
        $auth->shouldReceive('check')->once()->andReturn(true);
        $auth->shouldReceive('token')->andReturn($token);

        $middleware = new AuthenticateApi($auth);
        $called = false;

        $response = $middleware(new Request(), function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Token expired.'], $response->getData());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_it_returns_403_when_token_lacks_required_ability()
    {
        $token = $this->createMock(PersonalAccessToken::class);
        $token->method('hasExpired')->willReturn(false);
        $token->expects($this->once())->method('cant')->with('posts:delete')->willReturn(true);

        $auth = Mockery::mock(ApiAuthenticate::class);
        $auth->shouldReceive('check')->once()->andReturn(true);
        $auth->shouldReceive('token')->andReturn($token);

        $middleware = new AuthenticateApi($auth);
        $called = false;

        $response = $middleware(new Request(), function () use (&$called) {
            $called = true;
        }, 'posts:delete');

        $this->assertFalse($called);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['message' => 'Unauthorized.'], $response->getData());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_it_calls_next_when_authenticated_and_no_ability_required()
    {
        $token = $this->createMock(PersonalAccessToken::class);
        $token->method('hasExpired')->willReturn(false);

        $auth = Mockery::mock(ApiAuthenticate::class);
        $auth->shouldReceive('check')->once()->andReturn(true);
        $auth->shouldReceive('token')->andReturn($token);

        $middleware = new AuthenticateApi($auth);
        $request = new Request();
        $receivedRequest = null;

        $nextResponse = new Response('next-was-called');

        $result = $middleware($request, function ($req) use (&$receivedRequest, $nextResponse) {
            $receivedRequest = $req;

            return $nextResponse;
        });

        $this->assertSame($request, $receivedRequest);
        $this->assertSame($nextResponse, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_it_calls_next_when_token_has_the_required_ability()
    {
        $token = $this->createMock(PersonalAccessToken::class);
        $token->method('hasExpired')->willReturn(false);
        $token->expects($this->once())->method('cant')->with('posts:read')->willReturn(false);

        $auth = Mockery::mock(ApiAuthenticate::class);
        $auth->shouldReceive('check')->once()->andReturn(true);
        $auth->shouldReceive('token')->andReturn($token);

        $middleware = new AuthenticateApi($auth);
        $called = false;
        $nextResponse = new Response('ok');

        $result = $middleware(new Request(), function () use (&$called, $nextResponse) {
            $called = true;

            return $nextResponse;
        }, 'posts:read');

        $this->assertTrue($called);
        $this->assertSame($nextResponse, $result);
    }
}
