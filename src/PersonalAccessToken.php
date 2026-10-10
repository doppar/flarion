<?php

namespace Doppar\Flarion;

use Phaseolies\Support\Facades\Str;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Auth\Authable;
use DateTimeInterface;

/**
 * @property string $actor
 * @property int $tokenable_id
 * @property-read \Phaseolies\Auth\Authable|null $tokenable
 */
class PersonalAccessToken extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personal_access_token';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $creatable = [
        'actor',
        'tokenable_id',
        'name',
        'abilities',
        'lookup_hash',
        'last_used_at',
        'expires_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $unexposable = [
        'token',
        'lookup_hash',
    ];

    /**
     * The decoded abilities array cache.
     *
     * @var array|null
     */
    protected $abilitiesArray = null;

    /**
     * Get the model that owns the token, resolved through its actor.
     *
     * @return \Phaseolies\Database\Entity\Builder
     */
    public function tokenable()
    {
        return $this->bindTo(Actors::model($this->actor), 'id', 'tokenable_id');
    }

    /**
     * Alias of tokenable() kept so `$token->user` keeps working.
     *
     * @return \Phaseolies\Database\Entity\Builder
     */
    public function user()
    {
        return $this->tokenable();
    }

    /**
     * Create a new personal access token.
     *
     * @param \Phaseolies\Auth\Authable $user
     * @param string $name
     * @param array $abilities
     * @param DateTimeInterface|string|null $expiresAt
     * @param string|null $actor
     * @return \Doppar\Flarion\NewAccessToken
     */
    public function createToken(Authable $user, string $name, array $abilities = ['*'], DateTimeInterface|string|null $expiresAt = null, ?string $actor = null): NewAccessToken
    {
        $actor ??= Actors::forModel($user::class);

        if ($actor === null) {
            throw new \InvalidArgumentException(
                'No Flarion actor is configured for [' . $user::class . ']. Add one to config/flarion.php.'
            );
        }

        if (!is_a($user, Actors::model($actor))) {
            throw new \InvalidArgumentException(
                'The model [' . $user::class . "] cannot own tokens for the [{$actor}] actor."
            );
        }

        $token = $this->generateTokenString();
        $expiration = (int) config('flarion.expiration');

        $lookupHash = hash_hmac('sha256', $token, config('app.key'));

        $expiresAt = $expiration ? now()->addMinutes($expiration) : $expiresAt;

        if ($expiresAt instanceof DateTimeInterface) {
            $expiresAt = $expiresAt->format('Y-m-d H:i:s');
        }

        $personalAccessToken = static::create([
            'actor' => $actor,
            'tokenable_id' => $user->getAuthIdentifier(),
            'name' => $name,
            'abilities' => json_encode($abilities),
            'lookup_hash' => $lookupHash,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($personalAccessToken, $token);
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generateTokenString(): string
    {
        return sprintf(
            '%s%s%s',
            config('flarion.token_prefix', ''),
            $token = config('flarion.token_prefix', '') . bin2hex(random_bytes(40)),
            hash('crc32b', (string) $token)
        );
    }

    /**
     * Get the abilities attribute.
     *
     * @return array
     */
    public function getAbilitiesAttribute(): array
    {
        if ($this->abilitiesArray === null) {
            $this->abilitiesArray = json_decode($this->attributes['abilities'] ?? '[]', true) ?? [];
        }

        return $this->abilitiesArray;
    }

    /**
     * Set the abilities attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setAbilitiesAttribute($value): void
    {
        $this->abilitiesArray = is_array($value) ? $value : [];
        $this->attributes['abilities'] = json_encode($this->abilitiesArray);
    }

    /**
     * Find the token instance matching the given token.
     *
     * @param string $token
     * @return static|null
     */
    public static function findToken($token): ?PersonalAccessToken
    {
        return static::findMatchingToken($token);
    }

    /**
     * Find token by checking hash.
     *
     * @param string $token
     * @return PersonalAccessToken|null
     */
    protected static function findMatchingToken($token): ?PersonalAccessToken
    {
        $lookupHash = hash_hmac('sha256', $token, config('app.key'));

        $tokenInstance = static::where('lookup_hash', $lookupHash)->first();

        return $tokenInstance ?? null;
    }

    /**
     * Determine if the token has a given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function can($ability)
    {
        $isMultipleAbilityPassed = Str::contains($ability, '&');

        if ($isMultipleAbilityPassed) {
            $abilities = explode('&', $ability);
            $abilities = array_map('trim', $abilities);
        } else {
            $abilities = [$ability];
        }

        $userAbilities = $this->getAbilitiesAttribute();

        if (in_array('*', $userAbilities)) {
            return true;
        }

        foreach ($abilities as $ability) {
            if (!in_array($ability, $userAbilities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the token is missing a given ability.
     *
     * @param  string  $ability
     * @return bool
     */
    public function cant($ability)
    {
        return !$this->can($ability);
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return \Carbon\Carbon
     */
    public function freshTimestamp()
    {
        return now();
    }

    /**
     * Check if the token has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        if (is_null($this->expires_at)) {
            return false;
        }

        return $this->freshTimestamp()->gt($this->expires_at);
    }
}
