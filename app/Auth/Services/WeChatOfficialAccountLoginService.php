<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Auth\Enums\IdentityProvider;
use App\Common\Enums\ErrorCode;
use JsonException;

/**
 * WeChat Official Account (公众号) OAuth login.
 * Distinct provider from mini-program openid; unionid is stored but not auto-merged.
 */
final class WeChatOfficialAccountLoginService
{
    /** @var null|callable(string,string,array<string,string>):array<string,mixed> */
    private $requester;

    /** @param null|callable(string,string,array<string,string>):array<string,mixed> $requester */
    public function __construct(?callable $requester = null)
    {
        $this->requester = $requester;
    }

    /** @return array{authorize_url:string,state:string,scope:string} */
    public function authorizeUrl(string $redirectUri = '', string $state = '', string $scope = ''): array
    {
        $credentials = $this->credentials();
        $redirect = trim($redirectUri) !== '' ? trim($redirectUri) : $credentials['redirect_uri'];
        if ($redirect === '') {
            ErrorCode::AUTH_THIRD_PARTY_NOT_CONFIGURED->throw('缺少公众号回调地址');
        }
        $scope = trim($scope) !== '' ? trim($scope) : $credentials['oauth_scope'];
        if (!in_array($scope, ['snsapi_base', 'snsapi_userinfo'], true)) {
            ErrorCode::PARAM_ERROR->throw('公众号授权 scope 仅支持 snsapi_base 或 snsapi_userinfo');
        }
        $state = trim($state) !== '' ? trim($state) : bin2hex(random_bytes(8));
        $query = http_build_query([
            'appid' => $credentials['app_id'],
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        ]);

        return [
            'authorize_url' => 'https://open.weixin.qq.com/connect/oauth2/authorize?'.$query.'#wechat_redirect',
            'state' => $state,
            'scope' => $scope,
        ];
    }

    /** @return array{provider:IdentityProvider,subject:string,union_subject:?string,metadata:array<string,mixed>} */
    public function exchange(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            ErrorCode::PARAM_MISSING->throw('缺少公众号授权 code');
        }
        $credentials = $this->credentials();
        $query = http_build_query([
            'appid' => $credentials['app_id'],
            'secret' => $credentials['app_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
        $payload = $this->request('GET', 'https://api.weixin.qq.com/sns/oauth2/access_token?'.$query, []);
        if ((int) ($payload['errcode'] ?? 0) !== 0 || trim((string) ($payload['openid'] ?? '')) === '') {
            ErrorCode::AUTH_THIRD_PARTY_LOGIN_FAILED->throw();
        }

        return [
            'provider' => IdentityProvider::WECHAT_OFFICIAL_ACCOUNT,
            'subject' => (string) $payload['openid'],
            'union_subject' => $this->nullableString($payload['unionid'] ?? null),
            'metadata' => [
                'scope' => trim((string) ($payload['scope'] ?? '')),
                'openid' => (string) $payload['openid'],
            ],
        ];
    }

    /** @return array{app_id:string,app_secret:string,redirect_uri:string,oauth_scope:string} */
    private function credentials(): array
    {
        $appId = trim((string) config('third_party.wechat_official.app_id', ''));
        $secret = trim((string) config('third_party.wechat_official.app_secret', ''));
        if ($appId === '' || $secret === '') {
            ErrorCode::AUTH_THIRD_PARTY_NOT_CONFIGURED->throw('微信公众号');
        }
        return [
            'app_id' => $appId,
            'app_secret' => $secret,
            'redirect_uri' => trim((string) config('third_party.wechat_official.redirect_uri', '')),
            'oauth_scope' => trim((string) config('third_party.wechat_official.oauth_scope', 'snsapi_base')) ?: 'snsapi_base',
        ];
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
            'timeout' => max(1, (int) config('third_party.timeout_seconds', 8)),
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'content' => (string) ($options['body'] ?? ''),
        ]]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            ErrorCode::AUTH_THIRD_PARTY_LOGIN_FAILED->throw();
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            ErrorCode::AUTH_THIRD_PARTY_LOGIN_FAILED->throw();
        }
        if (!is_array($decoded)) {
            ErrorCode::AUTH_THIRD_PARTY_LOGIN_FAILED->throw();
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
