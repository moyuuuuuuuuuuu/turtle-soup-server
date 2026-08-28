<?php

declare(strict_types=1);

return [
    'wechat' => [
        'app_id' => (string) env('WECHAT_MINI_PROGRAM_APP_ID', ''),
        'app_secret' => (string) env('WECHAT_MINI_PROGRAM_APP_SECRET', ''),
    ],
    'douyin' => [
        'app_id' => (string) env('DOUYIN_MINI_PROGRAM_APP_ID', ''),
        'app_secret' => (string) env('DOUYIN_MINI_PROGRAM_APP_SECRET', ''),
    ],
    'timeout_seconds' => (int) env('MINI_PROGRAM_LOGIN_TIMEOUT_SECONDS', 8),
];
