<?php

namespace Doppar\Flarion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery;
use Doppar\Flarion\NewAccessToken;

class NewAccessTokenTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }

    public function test_it_holds_access_token_and_plain_text()
    {
        $mockToken = new \stdClass();
        $mockToken->name = 'test-token';

        $token = new NewAccessToken($mockToken, 'plain-text-token');

        $this->assertSame($mockToken, $token->accessToken);
        $this->assertEquals('plain-text-token', $token->plainTextToken);
        $this->assertEquals('plain-text-token', (string) $token);
    }
}
