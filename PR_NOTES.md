# Multi-actor API tokens and stricter token sources

Flarion can now issue tokens for more than one model (for example customers and admins), and it only reads the token from the `Authorization: Bearer` header unless you opt in to other sources.

## Why

- Token owners came from a single hard-wired `api` actor in `config/auth.php`, so only one model could own tokens.
- The `personal_access_token` table stored a bare `user_id`. A second model with the same id could not be told apart from the first.
- Tokens were also accepted from the `api_token` query/body value and cookie with no way to turn that off. Query-string tokens leak into logs and referrers, and cookies are sent automatically by browsers, which exposes stateless routes to CSRF.

## What changed

### Token actors

Actors now live in `config/flarion.php`:

```php
'actors' => [
    'api'   => ['model' => App\Models\User::class],
    'admin' => ['model' => App\Models\Admin::class],
],

'default' => 'api',
```

- New `Doppar\Flarion\Actors` class resolves actor names to models, and models back to actors. When two actors share a model, the `default` actor wins.
- `personal_access_token` stores `actor` and `tokenable_id` (both indexed) instead of `user_id`. There is no foreign key, because one column cannot point at several tables.
- `Tokenable::createToken()` records the owner's actor and rejects a model that does not belong to it. Creating a token for a model with no actor throws `InvalidArgumentException`.
- `Tokenable::tokens()` is scoped to the model's actor, so `admin` 5 never sees tokens that belong to `user` 5.
- New `Tokenable::tokenActor()` can be overridden when actors share a model.
- `rotateToken()` also checks the actor.
- `PersonalAccessToken::tokenable()` resolves the owner through the token's actor. `user()` remains as an alias.
- `ApiAuthenticate` authenticates a token only as the actor it was issued for, and adds `actor()`.
  - A token whose actor was removed from config, or whose owner was deleted, now returns 401. A deleted owner used to cause a fatal error.
- `AuthenticateApi` accepts `actor=` parameters, optionally combined with an ability:

  ```php
  Route::get('orders', [OrderController::class, 'index'])
      ->middleware('auth-api:actor=admin,orders:read');
  ```

  A valid token from another actor receives 403. Plain `auth-api:orders:read` behaves as before.
  - The `#[Middleware(...)]` attribute takes no parameters, so actor restrictions need the `auth-api` alias.

### Token sources

New `token_sources` option, default `['header']`:

```php
'token_sources' => ['header', 'input', 'cookie'],
```

| Source | Reads |
| ------ | ----- |
| `header` | `Authorization: Bearer <token>` |
| `input` | `api_token` in the request body or query string |
| `cookie` | `api_token` cookie |

Sources are checked in the listed order and the first token found is used. Unknown names are ignored.

## Breaking changes

1. **Schema.** `user_id` is replaced by `actor` and `tokenable_id`. There is no backfill, so existing tokens stop working and must be reissued.
2. **Config.** The owner model is no longer read from `auth.actors.api.model`. Move it to `flarion.actors`.
3. **Token sources.** Query/body and cookie tokens are no longer accepted by default. Apps that rely on them must set `token_sources`.
4. **Middleware signature.** `AuthenticateApi::__invoke()` takes `string ...$parameters` instead of `?string $ability = null`. Route usage is unchanged.
5. **Model column.** `PersonalAccessToken` no longer has a `user_id` attribute. Use `tokenable_id`.

## Upgrade steps

1. Add `actors` and `default` to `config/flarion.php`, using the model you previously set on the `api` actor in `config/auth.php`.
2. Add the `Tokenable` trait to every model listed in `actors`.
3. Replace the `personal_access_token` migration with the new one, then drop and re-run it. Reissue tokens.
4. If clients send the token as `api_token` or in a cookie, set `token_sources` accordingly.
5. Search the app for `->user_id` on tokens and for custom subclasses of `ApiAuthenticate` or `AuthenticateApi`.

## Tests

- 65 unit tests pass (`vendor/bin/phpunit`), including the new `MultiActorTest`.
  - Actors resolve from config, with default-actor precedence.
  - Tokens, relationships and rotation are scoped to the actor, including the case where two actors share the same id.
  - Tokens are rejected when their actor is removed or their owner is deleted.
  - Middleware actor restrictions work on their own and combined with an ability.
- Token source tests cover the default (header only), cookie and input when enabled, and configured order.
- PHPStan (`composer analyse`) reports no errors.
- Not covered: running the migration against MySQL/PostgreSQL. The tests use SQLite, and I only confirmed that the migration file has no syntax errors.

## Docs

The Flarion README has new "Token Sources" and "Multiple Token Actors" sections. The docs-site page `doppar-flarion.md` has matching changes, plus an upgrade note.
