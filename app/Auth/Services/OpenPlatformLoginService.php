<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Auth\Enums\IdentityProvider;
use App\Common\Enums\ErrorCode;

/**
 * Placeholder adapter for future WeChat / Douyin open-platform OAuth login.
 * Mini-program jscode2session stays in MiniProgramLoginService.
 */
final class OpenPlatformLoginService
{
    /** @var null|callable(string,string,array<string,string>):array<string,mixed> */
    private $requester;

    /** @param null|callable(string,string,array<string,string>):array<string,mixed> $requester */
    public function __construct(?callable $requester = null)
    {
        $this->requester = $requester;
    }

    /**
     * @param array{code?:string,redirect_uri?:string,access_token?:string} $payload
     * @return array{provider:IdentityProvider,subject:string,union_subject:?string,metadata:array<string,mixed>}
     */
    public function exchange(string $platform, array $payload = []): array
    {
        $provider = $this->provider($platform);
        $credentials = $this->credentials($platform);
        if ($credentials['app_id'] === '' || $credentials['app_secret'] === '') {
            ErrorCode::AUTH_THIRD_PARTY_NOT_CONFIGURED->throw();
        }

        // OAuth token/user exchange will be implemented when the open platforms go live.
        ErrorCode::AUTH_THIRD_PARTY_NOT_READY->throw($provider->label());
    }

    public function provider(string $platform): IdentityProvider
    {
        return match (mb_strtolower(trim($platform))) {
            'wechat', 'weixin', 'wx', 'wechat_oauth', 'wechat_open_platform' => IdentityProvider::WECHAT_OPEN_PLATFORM,
            'douyin', 'dy', 'toutiao', 'douyin_oauth', 'douyin_open_platform' => IdentityProvider::DOUYIN_OPEN_PLATFORM,
            default => ErrorCode::AUTH_THIRD_PARTY_PLATFORM_INVALID->throw(),
        };
    }

    /** @return array{app_id:string,app_secret:string,redirect_uri:string} */
    private function credentials(string $platform): array
    {
        $key = $this->provider($platform) === IdentityProvider::WECHAT_OPEN_PLATFORM ? 'wechat' : 'douyin';
        return [
            'app_id' => trim((string) config("third_party.open_platform.$key.app_id", '')),
            'app_secret' => trim((string) config("third_party.open_platform.$key.app_secret", '')),
            'redirect_uri' => trim((string) config("third_party.open_platform.$key.redirect_uri", '')),
        ];
    }
}
