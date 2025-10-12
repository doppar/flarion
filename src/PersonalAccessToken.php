<?php

namespace Doppar\Flarion;

use Phaseolies\Support\Facades\Str;
use Phaseolies\Database\Eloquent\Model;
use DateTimeInterface;
use App\Models\User;

class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_token';

    protected $creatable = [
        'user_id',
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
     * Get the access tokens that belong to the user.
     */
    public function user()
    {
        return $this->bindTo(User::class, 'id', 'user_id');
    }

    /**
     * Create a new personal access token.
     *
     * @param \App\Models\User $user
     * @param string $name
     * @param array $abilities
     * @return \Doppar\Flarion\NewAccessToken
     */
    public function createToken(User $user, string $name,  array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $token = $this->generateTokenString();
        $expiration = (int) config('flarion.expiration');

        $lookupHash = hash_hmac('sha256', $token, config('app.key'));

        $personalAccessToken = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'abilities' => json_encode($abilities),
            'lookup_hash' => $lookupHash,
            'expires_at' => $expiration ? now()->addMinutes($expiration) : $expiresAt,
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
