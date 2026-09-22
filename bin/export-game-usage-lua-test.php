<?php

declare(strict_types=1);

use App\Game\Services\GameUsageGuard;

require dirname(__DIR__) . '/vendor/autoload.php';

// Emit an offline Lua test using the actual production scripts, with no Redis/database connection.
echo 'local acquire = assert(load([====[' . GameUsageGuard::ACQUIRE . ']====]))' . PHP_EOL;
echo 'local release = assert(load([====[' . GameUsageGuard::RELEASE . ']====]))' . PHP_EOL;
echo file_get_contents(dirname(__DIR__) . '/tests/Game/game_usage.lua');
