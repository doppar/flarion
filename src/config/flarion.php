<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Flarion can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('FLARION_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Token Sources
    |--------------------------------------------------------------------------
    |
    | Where Flarion looks for the token on an incoming request, in order.
    | Only the "Authorization: Bearer" header is enabled by default.
    |
    |   header - "Authorization: Bearer <token>" (recommended)
    |   input  - "api_token" in the request body or query string. Query
    |            strings can leak into logs and referrers, so prefer the
    |            body, and only for clients that cannot set headers.
    |   cookie - "api_token" cookie. Browsers send cookies automatically, so
    |            add your own CSRF protection to state-changing routes.
    |
    */

    'token_sources' => ['header'],

    /*
    |--------------------------------------------------------------------------
    | Token Actors
    |--------------------------------------------------------------------------
    |
    | Each actor maps a name to an Authable model that is allowed to own API
    | tokens. Add as many actors as you need, such as "api" for customers and
    | "admin" for staff. The model must use the Tokenable trait. A token only
    | authenticates as the actor it was issued for.
    |
    */

    'actors' => [
        'api' => [
            'model' => 'App\\Models\\User',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Actor
    |--------------------------------------------------------------------------
    |
    | The actor used when several actors share one model, or when a token is
    | created without naming an actor.
    |
    */

    'default' => 'api',
];
