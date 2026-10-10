<?php

namespace Doppar\Flarion\Tests\Support;

use Doppar\Flarion\Tokenable;
use Phaseolies\Auth\Authable;

class Admin extends Authable
{
    use Tokenable;

    protected $table = 'admins';

    protected $connection = 'default';

    protected $creatable = [
        'name',
        'email',
    ];
}
