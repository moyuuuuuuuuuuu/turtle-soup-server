<?php

declare(strict_types=1);

return [
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXY_CIDRS', ''))))),
    'anonymous_minute' => (int) env('GAME_GUEST_CALLS_PER_MINUTE', 10),
    'anonymous_day' => (int) env('GAME_GUEST_CALLS_PER_DAY', 120),
    'user_minute' => (int) env('GAME_USER_CALLS_PER_MINUTE', 20),
    'user_day' => (int) env('GAME_USER_CALLS_PER_DAY', 300),
    'ip_minute' => (int) env('GAME_IP_CALLS_PER_MINUTE', 60),
    'ip_day' => (int) env('GAME_IP_CALLS_PER_DAY', 1000),
    'global_day' => (int) env('GAME_GLOBAL_CALLS_PER_DAY', 10000),
    'ip_concurrency' => (int) env('GAME_IP_CONCURRENCY', 4),
    'global_concurrency' => (int) env('GAME_GLOBAL_CONCURRENCY', 20),
];
