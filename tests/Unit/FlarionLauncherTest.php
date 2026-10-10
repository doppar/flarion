<?php

namespace Doppar\Flarion\Tests\Unit;

use Doppar\Flarion\ApiAuthenticate;
use Doppar\Flarion\FlarionLauncher;
use Phaseolies\Application;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FakeConfigStore
{
    public array $data = [];

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}

class FlarionLauncherTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // A bare, unbooted Application — enough to exercise the plain
        // Container methods (singleton/has/ArrayAccess) that ServiceLauncher
        // relies on, without needing a full app boot (env, config files, etc).
        $this->app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    }

    public function test_register_binds_api_authenticate_as_a_singleton()
    {
        $launcher = new FlarionLauncher($this->app);

        $launcher->register();

        $first = $this->app->get(ApiAuthenticate::class);
        $second = $this->app->get(ApiAuthenticate::class);

        $this->assertInstanceOf(ApiAuthenticate::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_register_merges_the_flarion_config()
    {
        $configStore = new FakeConfigStore();
        $this->app->instance('config', $configStore);

        $launcher = new FlarionLauncher($this->app);
        $launcher->register();

        $merged = $configStore->get('flarion');

        $this->assertIsArray($merged);
        $this->assertArrayHasKey('expiration', $merged);
        $this->assertArrayHasKey('token_prefix', $merged);
    }

    public function test_ghosts_returns_api_authenticate()
    {
        $launcher = new FlarionLauncher($this->app);

        $this->assertSame([ApiAuthenticate::class], $launcher->ghosts());
    }
}
