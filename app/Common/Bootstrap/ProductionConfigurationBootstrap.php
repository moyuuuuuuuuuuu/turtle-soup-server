<?php

declare(strict_types=1);

namespace App\Common\Bootstrap;

use App\Common\Support\ProductionConfiguration;
use RuntimeException;
use Webman\Bootstrap;

final class ProductionConfigurationBootstrap implements Bootstrap
{
    public static function start($worker): void
    {
        $names = [
            'APP_ENV', 'APP_DEBUG', 'APP_URL', 'CORS_ALLOWED_ORIGINS',
            'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'REDIS_HOST', 'REDIS_QUEUE_ENABLED',
            'ANONYMOUS_TOKEN_SECRET', 'PLAYER_JWT_SECRET', 'PLAYER_TOKEN_HASH_SECRET', 'PLAYER_EMAIL_CODE_SECRET',
            'COZE_GAME_DRIVER', 'COZE_API_TOKEN', 'COZE_QUESTION_JUDGE_WORKFLOW_ID', 'COZE_GUESS_JUDGE_WORKFLOW_ID',
            'API_RATE_LIMIT_ENABLED', 'GAME_GUEST_CALLS_PER_MINUTE', 'GAME_GUEST_CALLS_PER_DAY',
            'GAME_USER_CALLS_PER_MINUTE', 'GAME_USER_CALLS_PER_DAY', 'GAME_IP_CALLS_PER_MINUTE',
            'GAME_IP_CALLS_PER_DAY', 'GAME_GLOBAL_CALLS_PER_DAY', 'GAME_IP_CONCURRENCY', 'GAME_GLOBAL_CONCURRENCY',
        ];
        $values = [];
        foreach ($names as $name) {
            $values[$name] = (string) env($name, '');
        }

        $violations = ProductionConfiguration::violations($values);
        if ($violations !== []) {
            throw new RuntimeException("Production configuration is invalid:\n- " . implode("\n- ", $violations));
        }
    }
}
