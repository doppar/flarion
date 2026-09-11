<?php

namespace Doppar\Flarion\Tests\Support;

use PDO;
use Phaseolies\Config\Config;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Phaseolies\Support\StringService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('App\Models\User')) {
            class_alias(User::class, 'App\Models\User');
        }

        $container = new MockContainer();
        Container::setInstance($container);
        $container->singleton('str', StringService::class);

        Config::set('database.default', 'default');
        Config::set('app.key', 'base64:7n/+NIB4i3LQ6+ZbrclxuwyEqG5Uprufs90NJGL2pls=');
        Config::set('flarion.expiration', null);
        Config::set('flarion.token_prefix', '');

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT,
            updated_at TEXT
        )');

        $this->pdo->exec('CREATE TABLE personal_access_token (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            abilities TEXT,
            lookup_hash TEXT,
            last_used_at TEXT,
            expires_at TEXT,
            created_at TEXT,
            updated_at TEXT
        )');

        $databaseReflection = new ReflectionClass(Database::class);
        $databaseReflection->getProperty('connections')->setValue(null, ['default' => $this->pdo]);
        $databaseReflection->getProperty('transactions')->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $databaseReflection = new ReflectionClass(Database::class);
        $databaseReflection->getProperty('connections')->setValue(null, []);
        $databaseReflection->getProperty('transactions')->setValue(null, []);

        Container::forgetInstance();

        parent::tearDown();
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }
}
