<?php

declare(strict_types=1);

return [
    // WeChat Official Account (公众号) OAuth — in WeChat browser only.
    'wechat_official' => [
        'app_id' => (string) env('WECHAT_OFFICIAL_ACCOUNT_APP_ID', ''),
        'app_secret' => (string) env('WECHAT_OFFICIAL_ACCOUNT_APP_SECRET', ''),
        'redirect_uri' => (string) env('WECHAT_OFFICIAL_ACCOUNT_REDIRECT_URI', ''),
        'oauth_scope' => (string) env('WECHAT_OFFICIAL_ACCOUNT_OAUTH_SCOPE', 'snsapi_base'),
    ],
    // Future open-platform OAuth (not the product path today; WeChat = mini program + official account).
    'open_platform' => [
        'wechat' => [
            'app_id' => (string) env('WECHAT_OPEN_PLATFORM_APP_ID', ''),
            'app_secret' => (string) env('WECHAT_OPEN_PLATFORM_APP_SECRET', ''),
            'redirect_uri' => (string) env('WECHAT_OPEN_PLATFORM_REDIRECT_URI', ''),
        ],
        'douyin' => [
            'app_id' => (string) env('DOUYIN_OPEN_PLATFORM_APP_ID', ''),
            'app_secret' => (string) env('DOUYIN_OPEN_PLATFORM_APP_SECRET', ''),
            'redirect_uri' => (string) env('DOUYIN_OPEN_PLATFORM_REDIRECT_URI', ''),
        ],
    ],
    'timeout_seconds' => (int) env('THIRD_PARTY_AUTH_TIMEOUT_SECONDS', 8),
];
