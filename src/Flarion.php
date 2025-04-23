<?php

namespace Doppar\Flarion;

class Flarion
{
    public static function applicationUrlWithPort()
    {
        $appUrl = config('app.url');

        return $appUrl ? ',' . parse_url($appUrl, PHP_URL_HOST) . (parse_url($appUrl, PHP_URL_PORT) ? ':' . parse_url($appUrl, PHP_URL_PORT) : '') : '';
    }
}
