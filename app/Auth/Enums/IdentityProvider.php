<?php

declare(strict_types=1);

namespace App\Auth\Enums;

enum IdentityProvider: string
{
    case WECHAT_MINI_PROGRAM = 'wechat_mini_program';
    case DOUYIN_MINI_PROGRAM = 'douyin_mini_program';
    case WECHAT_OFFICIAL_ACCOUNT = 'wechat_official_account';
    case WECHAT_OPEN_PLATFORM = 'wechat_open_platform';
    case DOUYIN_OPEN_PLATFORM = 'douyin_open_platform';

    public function label(): string
    {
        return match ($this) {
            self::WECHAT_MINI_PROGRAM => '微信小程序',
            self::DOUYIN_MINI_PROGRAM => '抖音小程序',
            self::WECHAT_OFFICIAL_ACCOUNT => '微信公众号',
            self::WECHAT_OPEN_PLATFORM => '微信开放平台',
            self::DOUYIN_OPEN_PLATFORM => '抖音开放平台',
        };
    }

    public function isMiniProgram(): bool
    {
        return match ($this) {
            self::WECHAT_MINI_PROGRAM,
            self::DOUYIN_MINI_PROGRAM => true,
            default => false,
        };
    }

    public function isOpenPlatformOAuth(): bool
    {
        return match ($this) {
            self::WECHAT_OPEN_PLATFORM,
            self::DOUYIN_OPEN_PLATFORM,
            self::WECHAT_OFFICIAL_ACCOUNT => true,
            default => false,
        };
    }

    public function usernamePrefix(): string
    {
        return match ($this) {
            self::WECHAT_MINI_PROGRAM,
            self::WECHAT_OFFICIAL_ACCOUNT,
            self::WECHAT_OPEN_PLATFORM => 'wx_player',
            self::DOUYIN_MINI_PROGRAM,
            self::DOUYIN_OPEN_PLATFORM => 'dy_player',
        };
    }

    /**
     * Map public login platform tokens to a concrete provider.
     * Mini-program login uses short platform names; OAuth will use distinct tokens later.
     */
    public static function fromMiniProgramPlatform(string $platform): self
    {
        return match (mb_strtolower(trim($platform))) {
            'wechat', 'weixin', 'wx' => self::WECHAT_MINI_PROGRAM,
            'douyin', 'dy', 'toutiao' => self::DOUYIN_MINI_PROGRAM,
            default => self::WECHAT_MINI_PROGRAM,
        };
    }
}
