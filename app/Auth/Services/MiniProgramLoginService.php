<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Auth\Enums\IdentityProvider;
use App\Common\Enums\ErrorCode;
use JsonException;

final class MiniProgramLoginService
{
    /** @var null|callable(string,string,array<string,string>):array<string,mixed> */
    private $requester;

    /** @param null|callable(string,string,array<string,string>):array<string,mixed> $requester */
    public function __construct(?callable $requester = null)
    {
        $this->requester = $requester;
    }

    /** @return array{provider:IdentityProvider,subject:string,union_subject:?string,metadata:array<string,mixed>} */
    public function exchange(string $platform, string $code, string $anonymousCode = ''): array
    {
        $code = trim($code);
        if ($code === '') {
            ErrorCode::PARAM_MISSING->throw('缺少小程序登录凭证');
        }

        return match ($platform) {
            'wechat' => $this->wechat($code),
            'douyin' => $this->douyin($code, trim($anonymousCode)),
            default => ErrorCode::AUTH_MINI_PROGRAM_PLATFORM_INVALID->throw(),
        };
    }

    /** @return array{provider:IdentityProvider,subject:string,union_subject:?string,metadata:array<string,mixed>} */
    private function wechat(string $code): array
    {
        $credentials = $this->credentials('wechat');
        $query = http_build_query([
            'appid' => $credentials['app_id'],
            'secret' => $credentials['app_secret'],
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ]);
        $payload = $this->request('GET', 'https://api.weixin.qq.com/sns/jscode2session?'.$query, []);
        if ((int) ($payload['errcode'] ?? 0) !== 0 || trim((string) ($payload['openid'] ?? '')) === '') {
            ErrorCode::AUTH_MINI_PROGRAM_LOGIN_FAILED->throw();
        }

        return [
            'provider' => IdentityProvider::WECHAT_MINI_PROGRAM,
            'subject' => (string) $payload['openid'],
            'union_subject' => $this->nullableString($payload['unionid'] ?? null),
            'metadata' => [],
        ];
    }

    /** @return array{provider:IdentityProvider,subject:string,union_subject:?string,metadata:array<string,mixed>} */
    private function douyin(string $code, string $anonymousCode): array
    {
        $credentials = $this->credentials('douyin');
        $body = ['appid' => $credentials['app_id'], 'secret' => $credentials['app_secret'], 'code' => $code];
        if ($anonymousCode !== '') {
            $body['anonymous_code'] = $anonymousCode;
        }
        try {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            ErrorCode::AUTH_MINI_PROGRAM_LOGIN_FAILED->throw();
        }
        $payload = $this->request('POST', 'https://developer.toutiao.com/api/apps/v2/jscode2session', ['Content-Type: application/json', 'body' => $json]);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if ((int) ($payload['err_no'] ?? -1) !== 0 || trim((string) ($data['openid'] ?? '')) === '') {
            ErrorCode::AUTH_MINI_PROGRAM_LOGIN_FAILED->throw();
        }

        return [
            'provider' => IdentityProvider::DOUYIN_MINI_PROGRAM,
            'subject' => (string) $data['openid'],
            'union_subject' => $this->nullableString($data['unionid'] ?? null),
            'metadata' => ['anonymous_openid' => $this->nullableString($data['anonymous_openid'] ?? null)],
        ];
    }

    /** @return array{app_id:string,app_secret:string} */
    private function credentials(string $platform): array
    {
        $appId = trim((string) config("mini_program.$platform.app_id", ''));
        $secret = trim((string) config("mini_program.$platform.app_secret", ''));
        if ($appId === '' || $secret === '') {
            ErrorCode::AUTH_MINI_PROGRAM_NOT_CONFIGURED->throw();
        }
        return ['app_id' => $appId, 'app_secret' => $secret];
    }

    /**
     * @param array<int|string,string> $options
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, array $options): array
    {
        if ($this->requester !== null) {
            return ($this->requester)($method, $url, $options);
        }
        $headers = array_values(array_filter($options, 'is_int', ARRAY_FILTER_USE_KEY));
        $context = stream_context_create(['http' => [
            'method' => $method,
            'timeout' => max(1, (int) config('mini_program.timeout_seconds', 8)),
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'content' => (string) ($options['body'] ?? ''),
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            ErrorCode::AUTH_MINI_PROGRAM_LOGIN_FAILED->throw();
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            ErrorCode::AUTH_MINI_PROGRAM_LOGIN_FAILED->throw();
        }
        if (!is_array($decoded)) {
            ErrorCode::AUTH_MINI_PROGRAM_LOGIN_FAILED->throw();
        }
        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
