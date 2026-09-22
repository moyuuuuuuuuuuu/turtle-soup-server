<?php

declare(strict_types=1);

namespace App\Common\Bootstrap;

use App\Common\Support\AdminJwtConfiguration;
use Webman\Bootstrap;

final class AdminJwtConfigurationBootstrap implements Bootstrap
{
    public static function start($worker): void
    {
        AdminJwtConfiguration::validate(
            (string) config('plugin.tinywan.jwt.app.jwt.access_secret_key', ''),
            (string) config('plugin.tinywan.jwt.app.jwt.refresh_secret_key', ''),
        );
    }
}
