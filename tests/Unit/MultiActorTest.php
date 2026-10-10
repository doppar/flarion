<?php

namespace Doppar\Flarion\Tests\Unit;

use Doppar\Flarion\Actors;
use Doppar\Flarion\ApiAuthenticate;
use Doppar\Flarion\Http\Middleware\AuthenticateApi;
use Doppar\Flarion\PersonalAccessToken;
use Doppar\Flarion\Tests\Support\Admin;
use Doppar\Flarion\Tests\Support\DatabaseTestCase;
use Doppar\Flarion\Tests\Support\User;
use Phaseolies\Config\Config;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

class TokenAuth extends ApiAuthenticate
{
    public function __construct(private ?string $plain = null)
    {
    }

    protected function getTokenFromRequest()
    {
        return $this->plain;
    }
}

class PlainAdmin extends \Phaseolies\Auth\Authable
{
    protected $table = 'admins';

    protected $connection = 'default';
}

class MultiActorTest extends DatabaseTestCase
{
    public function test_actors_are_resolved_from_the_flarion_config()
    {
        $this->assertSame(['api', 'admin'], Actors::names());
        $this->assertSame(User::class, Actors::model('api'));
        $this->assertSame(Admin::class, Actors::model('admin'));
        $this->assertSame('admin', Actors::forModel(Admin::class));
        $this->assertNull(Actors::forModel(\stdClass::class));
    }

    public function test_unknown_actor_throws()
    {
        $this->expectException(\InvalidArgumentException::class);
        Actors::model('ghost');
    }

    public function test_default_actor_wins_when_models_are_shared()
    {
        Config::set('flarion.actors', [
            'web' => ['model' => User::class],
            'api' => ['model' => User::class],
        ]);

        $this->assertSame('api', Actors::forModel(User::class));
    }

    public function test_tokens_record_the_actor_of_their_owner()
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();

        $this->assertSame('api', $user->createToken('u')->accessToken->actor);
        $this->assertSame('admin', $admin->createToken('a')->accessToken->actor);
    }

    public function test_a_token_resolves_to_the_owner_in_its_own_actor_table()
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $this->assertSame($user->id, $admin->id);

        $adminToken = $admin->createToken('a');

        $auth = new TokenAuth($adminToken->plainTextToken);

        $this->assertInstanceOf(Admin::class, $auth->user());
        $this->assertSame('admin', $auth->actor());
        $this->assertSame('admin@example.com', $auth->user()->email);
    }

    public function test_tokens_relationship_is_scoped_to_the_actor()
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $user->createToken('u');
        $admin->createToken('a1');
        $admin->createToken('a2');

        $this->assertCount(1, $user->tokens()->get());
        $this->assertCount(2, $admin->tokens()->get());
    }

    public function test_rotate_token_rejects_a_token_with_the_same_id_but_another_actor()
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $adminToken = $admin->createToken('a')->accessToken;

        $this->expectException(\InvalidArgumentException::class);
        $user->rotateToken($adminToken);
    }

    public function test_token_for_a_removed_actor_is_rejected()
    {
        $admin = $this->createAdmin();
        $plain = $admin->createToken('a')->plainTextToken;

        Config::set('flarion.actors.admin', null);

        $this->assertFalse((new TokenAuth($plain))->check());
    }

    public function test_token_whose_owner_was_deleted_is_rejected()
    {
        $admin = $this->createAdmin();
        $plain = $admin->createToken('a')->plainTextToken;
        $this->pdo->exec('DELETE FROM admins');

        $this->assertFalse((new TokenAuth($plain))->check());
    }

    public function test_token_whose_owner_model_lacks_the_tokenable_trait_is_rejected()
    {
        $admin = $this->createAdmin();
        $plain = $admin->createToken('a')->plainTextToken;

        Config::set('flarion.actors.admin', ['model' => PlainAdmin::class]);

        $this->assertFalse((new TokenAuth($plain))->check());
    }

    public function test_creating_a_token_for_a_model_without_an_actor_fails()
    {
        Config::set('flarion.actors.admin', null);

        $this->expectException(\InvalidArgumentException::class);
        $this->createAdmin()->createToken('a');
    }

    public function test_explicit_actor_must_match_the_model()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PersonalAccessToken())->createToken($this->createUser(), 'x', ['*'], null, 'admin');
    }

    public function test_middleware_restricts_by_actor()
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $userAuth = new TokenAuth($user->createToken('u')->plainTextToken);
        $adminAuth = new TokenAuth($admin->createToken('a')->plainTextToken);
        $ok = new Response('ok');
        $next = fn () => $ok;

        $this->assertSame(403, (new AuthenticateApi($userAuth))(new Request(), $next, 'actor=admin')->getStatusCode());
        $this->assertSame($ok, (new AuthenticateApi($adminAuth))(new Request(), $next, 'actor=admin'));
        $this->assertSame($ok, (new AuthenticateApi($userAuth))(new Request(), $next, 'actor=admin', 'actor=api'));
    }

    public function test_middleware_combines_actor_and_ability()
    {
        $admin = $this->createAdmin();
        $auth = new TokenAuth($admin->createToken('a', null, ['orders:read'])->plainTextToken);
        $ok = new Response('ok');
        $next = fn () => $ok;

        $this->assertSame($ok, (new AuthenticateApi($auth))(new Request(), $next, 'actor=admin', 'orders:read'));
        $this->assertSame(403, (new AuthenticateApi($auth))(new Request(), $next, 'actor=admin', 'orders:write')->getStatusCode());
    }
}
