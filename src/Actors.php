<?php

namespace Doppar\Flarion;

use Phaseolies\Auth\Authable;
use InvalidArgumentException;

class Actors
{
    /**
     * Get every configured token actor, keyed by actor name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $actors = config('flarion.actors', []);

        return is_array($actors) ? $actors : [];
    }

    /**
     * Get the names of all configured token actors.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(static::all());
    }

    /**
     * Determine if the given actor is configured.
     *
     * @param string $actor
     * @return bool
     */
    public static function has(string $actor): bool
    {
        return isset(static::all()[$actor]['model']);
    }

    /**
     * Get the default actor name.
     *
     * @return string
     */
    public static function default(): string
    {
        return (string) config('flarion.default', 'api');
    }

    /**
     * Resolve the Authable model class configured for an actor.
     *
     * @param string|null $actor
     * @return class-string<Authable>
     * @throws InvalidArgumentException
     */
    public static function model(?string $actor = null): string
    {
        $actor ??= static::default();

        if (!static::has($actor)) {
            throw new InvalidArgumentException(
                "Flarion actor [{$actor}] is not defined. Check the actors in config/flarion.php."
            );
        }

        $model = static::all()[$actor]['model'];

        if (!is_string($model) || !is_a($model, Authable::class, true)) {
            throw new InvalidArgumentException(
                "Flarion actor [{$actor}] model must extend " . Authable::class . '.'
            );
        }

        return $model;
    }

    /**
     * Find the actor that owns the given model class.
     *
     * @param string $model
     * @return string|null
     */
    public static function forModel(string $model): ?string
    {
        $default = static::default();

        if (static::has($default) && is_a($model, static::all()[$default]['model'], true)) {
            return $default;
        }

        foreach (static::all() as $actor => $definition) {
            if (is_string($definition['model'] ?? null) && is_a($model, $definition['model'], true)) {
                return $actor;
            }
        }

        return null;
    }
}
