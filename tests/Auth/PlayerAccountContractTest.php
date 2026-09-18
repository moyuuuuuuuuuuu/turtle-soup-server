<?php

declare(strict_types=1);

namespace Tests\Auth;

use App\Auth\Entities\PlayerContext;
use App\Auth\Enums\IdentityProvider;
use App\Auth\Services\EmailCodeService;
use App\Common\Enums\ErrorCode;
use PHPUnit\Framework\TestCase;

final class PlayerAccountContractTest extends TestCase
{
    public function testPlayerContextDistinguishesRegisteredAndAnonymousPlayers(): void
    {
        self::assertTrue((new PlayerContext(userId: 10, refreshSessionId: 20))->isUser());
        self::assertFalse((new PlayerContext(anonymousSessionId: 30))->isUser());
    }

    public function testEmailNormalizationAndWechatProvidersAreStable(): void
    {
        self::assertSame('player@example.com', EmailCodeService::normalizeEmail(' Player@Example.COM '));
        self::assertSame('wechat_mini_program', IdentityProvider::WECHAT_MINI_PROGRAM->value);
        self::assertSame('douyin_mini_program', IdentityProvider::DOUYIN_MINI_PROGRAM->value);
        self::assertSame('wechat_official_account', IdentityProvider::WECHAT_OFFICIAL_ACCOUNT->value);
        self::assertSame('wechat_open_platform', IdentityProvider::WECHAT_OPEN_PLATFORM->value);
        self::assertSame('douyin_open_platform', IdentityProvider::DOUYIN_OPEN_PLATFORM->value);
        self::assertTrue(IdentityProvider::WECHAT_OPEN_PLATFORM->isOpenPlatformOAuth());
        self::assertTrue(IdentityProvider::WECHAT_MINI_PROGRAM->isMiniProgram());
        self::assertSame('wx_player', IdentityProvider::WECHAT_OPEN_PLATFORM->usernamePrefix());
        self::assertSame('dy_player', IdentityProvider::DOUYIN_OPEN_PLATFORM->usernamePrefix());
    }

    public function testMiniProgramLoginKeepsProviderSecretsOnTheServer(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/config/route.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/MiniProgramLoginService.php');
        $config = file_get_contents(dirname(__DIR__, 2).'/config/mini_program.php');
        $thirdParty = file_get_contents(dirname(__DIR__, 2).'/config/third_party.php');
        $envExample = file_get_contents(dirname(__DIR__, 2).'/.env.example');
        self::assertIsString($routes);
        self::assertIsString($service);
        self::assertIsString($config);
        self::assertIsString($thirdParty);
        self::assertIsString($envExample);
        self::assertStringContainsString("'/api/v1/auth/login/mini-program'", $routes);
        self::assertStringContainsString("'/api/v1/auth/login/open-platform'", $routes);
        self::assertStringContainsString('https://api.weixin.qq.com/sns/jscode2session', $service);
        self::assertStringContainsString('https://developer.toutiao.com/api/apps/v2/jscode2session', $service);
        self::assertStringContainsString("env('WECHAT_MINI_PROGRAM_APP_SECRET'", $config);
        self::assertStringContainsString("env('DOUYIN_MINI_PROGRAM_APP_SECRET'", $config);
        self::assertStringContainsString("env('WECHAT_OPEN_PLATFORM_APP_SECRET'", $thirdParty);
        self::assertStringContainsString("env('DOUYIN_OPEN_PLATFORM_APP_SECRET'", $thirdParty);
        self::assertStringContainsString('WECHAT_OPEN_PLATFORM_APP_ID=', $envExample);
        self::assertStringContainsString('DOUYIN_OPEN_PLATFORM_APP_ID=', $envExample);
    }

    public function testThirdPartyLoginReusesIdentityAndSupportsExplicitBind(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        $repository = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Repositories/PlayerRepository.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/config/route.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Controllers/PlayerAuthController.php');
        $format = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Formats/PlayerFormat.php');
        $openPlatform = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/OpenPlatformLoginService.php');
        self::assertIsString($business);
        self::assertIsString($repository);
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($format);
        self::assertIsString($openPlatform);

        self::assertStringContainsString('resolveOrCreateUserByIdentity', $business);
        self::assertStringContainsString('byIdentity($provider, $subject)', $business);
        self::assertStringContainsString('AUTH_IDENTITY_BOUND', $business);
        self::assertStringContainsString('AUTH_IDENTITY_LAST_LOGIN_METHOD', $business);
        self::assertStringContainsString('bindMiniProgramIdentity', $business);
        self::assertStringContainsString('unbindIdentity', $business);
        self::assertStringContainsString('OpenPlatformLoginService', $business);
        self::assertStringContainsString('public function identity(', $repository);
        self::assertStringContainsString('public function identities(', $repository);
        self::assertStringContainsString("'/api/v1/me/identities'", $routes);
        self::assertStringContainsString("'/api/v1/me/identities/mini-program'", $routes);
        self::assertStringContainsString("'/api/v1/me/identities/open-platform'", $routes);
        self::assertStringContainsString('bindMiniProgramIdentity', $controller);
        self::assertStringContainsString('unbindIdentity', $controller);
        self::assertStringContainsString('provider_subject', $format);
        self::assertStringContainsString('Never expose provider_subject', $format);
        self::assertStringContainsString('AUTH_THIRD_PARTY_NOT_READY', $openPlatform);
        self::assertStringContainsString('IdentityProvider::WECHAT_OPEN_PLATFORM', $openPlatform);
        self::assertStringContainsString('IdentityProvider::DOUYIN_OPEN_PLATFORM', $openPlatform);
    }

    public function testWeChatOfficialAccountOAuthIsWired(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/WeChatOfficialAccountLoginService.php');
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/config/route.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Controllers/PlayerAuthController.php');
        $thirdParty = file_get_contents(dirname(__DIR__, 2).'/config/third_party.php');
        $envExample = file_get_contents(dirname(__DIR__, 2).'/.env.example');
        self::assertIsString($service);
        self::assertIsString($business);
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($thirdParty);
        self::assertIsString($envExample);

        self::assertStringContainsString('https://open.weixin.qq.com/connect/oauth2/authorize', $service);
        self::assertStringContainsString('https://api.weixin.qq.com/sns/oauth2/access_token', $service);
        self::assertStringContainsString('IdentityProvider::WECHAT_OFFICIAL_ACCOUNT', $service);
        self::assertStringContainsString('wechat_official', $thirdParty);
        self::assertStringContainsString("env('WECHAT_OFFICIAL_ACCOUNT_APP_SECRET'", $thirdParty);
        self::assertStringContainsString('WECHAT_OFFICIAL_ACCOUNT_APP_ID=', $envExample);
        self::assertStringContainsString("'/api/v1/auth/login/wechat-official'", $routes);
        self::assertStringContainsString("'/api/v1/auth/wechat-official/authorize-url'", $routes);
        self::assertStringContainsString("'/api/v1/me/identities/wechat-official'", $routes);
        self::assertStringContainsString('wechatOfficialLogin', $controller);
        self::assertStringContainsString('wechatOfficialAuthorizeUrl', $controller);
        self::assertStringContainsString('bindWechatOfficialIdentity', $controller);
        self::assertStringContainsString('wechatOfficialLogin', $business);
        self::assertStringContainsString('bindWechatOfficialIdentity', $business);
        self::assertStringContainsString('resolveOrCreateUserByIdentity', $business);
        self::assertSame('wechat_official_account', IdentityProvider::WECHAT_OFFICIAL_ACCOUNT->value);
        self::assertFalse(IdentityProvider::WECHAT_OFFICIAL_ACCOUNT->isMiniProgram());
        self::assertTrue(IdentityProvider::WECHAT_OFFICIAL_ACCOUNT->isOpenPlatformOAuth());
    }

    public function testIdentityErrorCodesRemainStable(): void
    {
        self::assertSame('auth.identity_bound', ErrorCode::AUTH_IDENTITY_BOUND->value);
        self::assertSame('auth.identity_not_found', ErrorCode::AUTH_IDENTITY_NOT_FOUND->value);
        self::assertSame('auth.identity_last_login_method', ErrorCode::AUTH_IDENTITY_LAST_LOGIN_METHOD->value);
        self::assertSame('auth.third_party_not_ready', ErrorCode::AUTH_THIRD_PARTY_NOT_READY->value);
        self::assertSame(409, ErrorCode::AUTH_IDENTITY_BOUND->httpStatus());
        self::assertSame(404, ErrorCode::AUTH_IDENTITY_NOT_FOUND->httpStatus());
        self::assertSame(409, ErrorCode::AUTH_IDENTITY_LAST_LOGIN_METHOD->httpStatus());
        self::assertSame(503, ErrorCode::AUTH_THIRD_PARTY_NOT_READY->httpStatus());
        self::assertFalse(ErrorCode::AUTH_IDENTITY_BOUND->isReportable());
    }

    public function testPlayerAuthenticationErrorsRemainStable(): void
    {
        self::assertSame('auth.device_limit_reached', ErrorCode::AUTH_DEVICE_LIMIT_REACHED->value);
        self::assertSame(401, ErrorCode::AUTH_TOKEN_INVALID->httpStatus());
        self::assertFalse(ErrorCode::AUTH_CREDENTIALS_INVALID->isReportable());
    }

    public function testNewDeviceReplacesOldestActiveSessionAtTheLimit(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/PlayerTokenService.php');
        self::assertIsString($service);
        self::assertStringContainsString("['create_time', 'asc']", $service);
        self::assertStringContainsString("['id', 'asc']", $service);
        self::assertStringContainsString("'revoke_reason' => 'device_limit_replaced'", $service);
        self::assertStringNotContainsString('AUTH_DEVICE_LIMIT_REACHED->throw()', $service);
    }

    public function testMigrationContainsNoSeederOrRawSql(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/20260826010004_create_player_accounts.php');
        self::assertIsString($migration);
        self::assertStringContainsString("'turtle_users'", $migration);
        self::assertStringContainsString("'avatar_url'", $migration);
        self::assertStringContainsString("'avatar_object_key'", $migration);
        self::assertStringContainsString("'wechat_mini_program'", file_get_contents(dirname(__DIR__, 2).'/app/Auth/Enums/IdentityProvider.php'));
        self::assertStringNotContainsString('DemoSeeder', $migration);
        self::assertStringNotContainsString('execute("', $migration);
    }

    public function testPasswordLoginUsesEmailOnlyAndAvatarUsesBos(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        $repository = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Repositories/PlayerRepository.php');
        self::assertIsString($business);
        self::assertIsString($repository);
        self::assertStringContainsString("\$data['email']", $business);
        self::assertStringNotContainsString('byAccount', $repository);
        self::assertStringContainsString('BosAvatarService', $business);
    }

    public function testBosAvatarObjectKeyDoesNotExposePublicPlayerId(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/BosAvatarService.php');
        self::assertIsString($service);
        self::assertStringContainsString("hash('sha256', substr(\$publicId, 0, 2).'/'.\$publicId)", $service);
        self::assertStringNotContainsString("'/'.\$publicId.'.svg'", $service);
    }

    public function testPlayerCanUploadAValidatedCustomAvatar(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/config/route.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Controllers/PlayerAuthController.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/BosAvatarService.php');
        self::assertIsString($routes);
        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertStringContainsString("'/api/v1/me/avatar'", $routes);
        self::assertStringContainsString("file('avatar')", $controller);
        self::assertStringContainsString('5 * 1024 * 1024', $service);
        self::assertStringContainsString("'image/webp' => 'webp'", $service);
        self::assertStringContainsString("'avatars/custom/'", $service);
    }

    public function testFailedLoginAuditAndEmailOnlyContractArePresent(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        self::assertIsString($business);
        self::assertStringContainsString("recordLogin(\$user, 'password', 'failed'", $business);
        self::assertStringContainsString("recordLogin(\$user, 'email_code', 'failed'", $business);
        self::assertStringContainsString("\$data['email']", $business);
        self::assertStringNotContainsString("\$data['account']", $business);
    }

    public function testEmailCodeLoginCreatesPasswordlessAccountWhenEmailIsNew(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Business/PlayerAuthBusiness.php');
        self::assertIsString($business);
        self::assertStringContainsString("'password_hash' => ''", $business);
        self::assertStringContainsString("\$this->avatars->createDefault(\$email, \$publicId)", $business);
        self::assertStringContainsString("(string) \$user->password_hash !== ''", $business);
    }

    public function testSmtpSenderConfigurationUsesCanonicalEnvironmentName(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/mail.php');
        $mailer = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/SmtpMailer.php');
        self::assertIsString($config);
        self::assertIsString($mailer);
        self::assertStringContainsString("env('SMTP_FROM_ADDRESS'", $config);
        self::assertStringContainsString('SMTP configuration is incomplete', $mailer);
        self::assertStringContainsString("'mail_from'", $mailer);
    }

    public function testEmailCodeRateLimitsAreIsolatedByPurpose(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Auth/Services/EmailCodeService.php');
        self::assertIsString($service);
        $sendMethod = strstr($service, 'public function send');
        self::assertIsString($sendMethod);
        $sendMethod = strstr($sendMethod, 'public function verify', true);
        self::assertIsString($sendMethod);
        self::assertSame(4, substr_count($sendMethod, "where('purpose', \$purpose)"));
    }
}
