<?php

namespace Doppar\Flarion\Tests\Support;

use Doppar\Flarion\Tokenable;
use Phaseolies\Auth\Authable;

class User extends Authable
{
    use Tokenable;

    protected $table = 'users';

    protected $connection = 'default';

    protected $creatable = [
        'name',
        'email',
    ];
}
