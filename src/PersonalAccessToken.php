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
        'token',
        'abilities',
        'last_used_at',
        'expires_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $unexposable = [
        'token'
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
        return $this->oneToOne(User::class, 'id', 'user_id');
    }

    /**
     * Create a new personal access token.
     *
     * @param \App\Models\User $user
     * @param string $name
     * @param array $abilities
     * @return \Doppar\Flarion\NewAccessToken
     */
    public function createToken(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null
    ) {
        $token = $this->generateTokenString();
        $expiration = (int) config('flarion.expiration');

        $personalAccessToken = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $token,
            'abilities' => json_encode($abilities),
            'expires_at' => $expiration ? now()->addMinutes($expiration) : $expiresAt,
        ]);

        return new NewAccessToken($personalAccessToken, $token);
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generateTokenString()
    {
        return sprintf(
            '%s%s%s',
            config('flarion.token_prefix', ''),
            $token = Str::random(40),
            hash('crc32b', $token)
        );
    }

    /**
     * Get the abilities attribute.
     *
     * @return array
     */
    public function getAbilitiesAttribute()
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
    public function setAbilitiesAttribute($value)
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
    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            return static::query()->where('token', '=', $token)->first();
        }

        [$id, $token] = explode('|', $token, 2);

        return static::query()->where('id', '=', $id)
            ->where('token', '=', $token)
            ->first();
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
