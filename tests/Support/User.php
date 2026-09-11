<?php

namespace Doppar\Flarion\Tests\Support;

use Doppar\Flarion\Tokenable;
use Phaseolies\Database\Entity\Model;

class User extends Model
{
    use Tokenable;

    protected $table = 'users';

    protected $connection = 'default';

    protected $creatable = [
        'name',
        'email',
    ];
}
