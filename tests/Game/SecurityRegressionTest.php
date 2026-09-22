<?php

declare(strict_types=1);

namespace Tests\Game;

use App\Auth\Entities\PlayerContext;
use App\Auth\Services\EmailCodeService;
use App\Auth\Services\PlayerPrincipalService;
use App\Common\Enums\ErrorCode;
use App\Common\Exceptions\BaseException;
use App\Game\Formats\WebSocketErrorFormat;
use App\Game\Repositories\GameRepository;
use App\Game\WebSocket\GameWebSocket;
use App\Room\Business\RoomBusiness;
use App\Room\Models\Room;
use App\Room\Models\RoomMember;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Webman\Config;
use Workerman\Connection\TcpConnection;

/** Exercises real Eloquent query building with mocked I/O; never opens a database. */
final class SecurityRegressionTest extends TestCase
{
    public function testGameQuestionCannotCallJudgeWhenSharedQuotaDeniesAdmission(): void
    {
        $this->connection->method('select')->willReturnOnConsecutiveCalls(
            [['id' => 1, 'status' => 'playing', 'question_count' => 0, 'question_limit' => 12]],
            [],
        );
        $judge = $this->createMock(\App\Game\Contracts\GameJudgeInterface::class);
        $judge->expects(self::never())->method('judgeQuestion');
        $usage = new \App\Game\Services\GameUsageGuard(static fn (): int => 0);
        $business = new \App\Game\Business\GameBusiness(judge: $judge, usage: $usage);
        $this->expectException(BaseException::class);
        $business->ask(new PlayerContext(anonymousSessionId: 1), 'game', 'request', '问题');
    }

    public function testUnauthorizedGameCannotReserveAnotherGamesLockOrGlobalBudget(): void
    {
        $this->connection->method('select')->willReturn([]);
        $usage = new \App\Game\Services\GameUsageGuard(static fn (): int => self::fail('Authorization must precede quota reservation'));
        $business = new \App\Game\Business\GameBusiness(usage: $usage);
        $this->expectException(BaseException::class);
        $business->ask(new PlayerContext(anonymousSessionId: 1), 'other-game', 'request', '问题');
    }

    public function testGameGuessCannotCallJudgeWhenSharedQuotaDeniesAdmission(): void
    {
        $this->connection->method('select')->willReturnOnConsecutiveCalls(
            [['id' => 1, 'status' => 'playing']],
            [],
            [['exists' => 0]],
        );
        $judge = $this->createMock(\App\Game\Contracts\GameJudgeInterface::class);
        $judge->expects(self::never())->method('judgeGuess');
        $usage = new \App\Game\Services\GameUsageGuard(static fn (): int => 0);
        $business = new \App\Game\Business\GameBusiness(judge: $judge, usage: $usage);
        $this->expectException(BaseException::class);
        $business->guess(new PlayerContext(anonymousSessionId: 1), 'game', 'request', '答案');
    }

    private MySqlConnection&\PHPUnit\Framework\MockObject\MockObject $connection;
    private mixed $oldResolver;
    private mixed $oldCapsule;
    /** @var array<string, mixed> */
    private array $oldConfig = [];

    protected function setUp(): void
    {
        // Load the framework initializer before replacing its resolver.
        class_exists(\support\Model::class);
        class_exists(\support\Db::class);
        $this->oldResolver = Model::getConnectionResolver();
        $this->oldCapsule = (new ReflectionProperty(Manager::class, 'instance'))->getValue();
        foreach (['config', 'flatCache'] as $property) {
            $this->oldConfig[$property] = (new ReflectionProperty(Config::class, $property))->getValue();
        }
        (new ReflectionProperty(Config::class, 'config'))->setValue(null, ['player_auth' => ['email_code_secret' => 'test-only-secret']]);
        (new ReflectionProperty(Config::class, 'flatCache'))->setValue(null, []);
        $this->connection = $this->getMockBuilder(MySqlConnection::class)
            ->setConstructorArgs([null, 'test'])
            ->onlyMethods(['select', 'update', 'delete', 'transaction'])
            ->getMock();
        $resolver = new ConnectionResolver(['default' => $this->connection]);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'test-double']);
        $capsule->getDatabaseManager()->extend('test-double', fn () => $this->connection);
        $capsule->setAsGlobal();
        $this->connection->expects(self::never())->method('delete');
    }

    protected function tearDown(): void
    {
        if ($this->oldResolver !== null) {
            Model::setConnectionResolver($this->oldResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        (new ReflectionProperty(Manager::class, 'instance'))->setValue(null, $this->oldCapsule);
        foreach ($this->oldConfig as $property => $value) {
            (new ReflectionProperty(Config::class, $property))->setValue(null, $value);
        }
        foreach (['roomConnections', 'connectionRooms', 'connectionContexts'] as $property) {
            (new ReflectionProperty(GameWebSocket::class, $property))->setValue(null, []);
        }
    }

    public function testGameOwnerFallbackOnlyAppliesToSinglePlayer(): void
    {
        $this->connection->expects(self::once())->method('select')->willReturnCallback(static function (string $sql, array $bindings): array {
            self::assertStringContainsString('(`room_id` is null and `user_id` = ?) or exists', $sql);
            self::assertStringContainsString('`status` = ?', $sql);
            self::assertSame(['game-public-id', 7, 7, 'active'], $bindings);
            return [];
        });
        self::assertNull((new GameRepository())->find('game-public-id', new PlayerContext(userId: 7)));
    }

    public function testAnonymousQueriesExcludeGamesMergedIntoAnAccount(): void
    {
        $this->connection->expects(self::once())->method('select')->willReturnCallback(static function (string $sql): array {
            self::assertStringContainsString('`room_id` is null and `user_id` is null and `anonymous_session_id` = ?', $sql);
            return [];
        });
        self::assertNull((new GameRepository())->find('game', new PlayerContext(anonymousSessionId: 8)));
    }

    public function testAnonymousPrincipalIsRecheckedAfterRevocationAndExpiry(): void
    {
        $valid = ['id' => 8, 'expires_at' => date('Y-m-d H:i:s', time() + 600), 'revoked_at' => null, 'user_id' => null];
        $this->connection->method('select')->willReturnOnConsecutiveCalls(
            [$valid],
            [array_replace($valid, ['revoked_at' => date('Y-m-d H:i:s')])],
            [array_replace($valid, ['expires_at' => '2000-01-01 00:00:00'])],
            [array_replace($valid, ['user_id' => 7])],
            [],
        );
        $service = new PlayerPrincipalService();
        $context = new PlayerContext(anonymousSessionId: 8);
        $service->validate($context);
        for ($i = 0; $i < 4; ++$i) {
            try {
                $service->validate($context);
                self::fail('Invalid anonymous principal was accepted');
            } catch (BaseException $exception) {
                self::assertSame(ErrorCode::AUTH_ANONYMOUS_INVALID, $exception->errorCode);
            }
        }
    }

    public function testBroadcastDropsDepartedAndExpiredRecipients(): void
    {
        $this->connection->method('select')->willReturnCallback(static function (string $sql): array {
            if (str_contains($sql, '`turtle_users`')) {
                return [['id' => 7, 'status' => 'active']];
            }
            if (str_contains($sql, '`turtle_refresh_sessions`')) {
                return [['id' => 9, 'expires_at' => date('Y-m-d H:i:s', time() + 600)]];
            }
            if (str_contains($sql, '`turtle_rooms`')) {
                return [['id' => 3, 'public_id' => 'room', 'status' => 'playing']];
            }
            return []; // HTTP leave removed active membership, but socket is still attached.
        });
        $socket = new GameWebSocket();
        $connections = [];
        foreach ([101, 102] as $id) {
            $connection = $this->createMock(TcpConnection::class);
            $connection->id = $id;
            $connection->expects(self::never())->method('send');
            $socket->onConnect($connection);
            (new \ReflectionMethod($socket, 'attach'))->invoke($socket, $connection, 'room');
            $connections[$id] = new PlayerContext(userId: 7, refreshSessionId: 9, accessExpiresAt: $id === 101 ? time() + 600 : 1);
        }
        (new ReflectionProperty(GameWebSocket::class, 'connectionContexts'))->setValue(null, $connections);
        (new \ReflectionMethod($socket, 'broadcast'))->invoke($socket, 'room', 'v1.game.finished', 'request', ['bottom' => 'protected']);
        self::assertSame([], (new ReflectionProperty(GameWebSocket::class, 'roomConnections'))->getValue());
    }

    public function testEmailCodeConsumptionLocksAndCommitsBeforeReportingFailure(): void
    {
        $state = new class () {
            public bool $committed = false;
            public bool $consumed = false;
        };
        $this->connection->method('transaction')->willReturnCallback(static function (callable $callback) use ($state): mixed {
            $state->committed = false;
            $result = $callback();
            $state->committed = true;
            return $result;
        });
        $this->connection->method('select')->willReturnCallback(static function (string $sql) use ($state): array {
            self::assertStringEndsWith('for update', $sql);
            return $state->consumed ? [] : [[
                'id' => 1, 'attempts' => 0, 'expires_at' => date('Y-m-d H:i:s', time() + 600),
                'code_hash' => hash_hmac('sha256', 'a@example.test|login|123456', 'test-only-secret'),
            ]];
        });
        $this->connection->expects(self::exactly(2))->method('update')->willReturnCallback(static function (string $sql) use ($state): int {
            if (str_contains($sql, '`consumed_at`')) {
                $state->consumed = true;
            } else {
                self::assertStringContainsString('`attempts` = `attempts` + 1', $sql);
            }
            return 1;
        });
        $service = new EmailCodeService();
        try {
            $service->verify('a@example.test', 'login', 'wrong');
            self::fail('Wrong code was accepted');
        } catch (BaseException $exception) {
            self::assertTrue($state->committed);
            self::assertSame(ErrorCode::AUTH_EMAIL_CODE_INVALID, $exception->errorCode);
        }
        $service->verify('a@example.test', 'login', '123456');
        self::assertTrue($state->committed);
        self::assertTrue($state->consumed);
        $this->expectException(BaseException::class);
        $service->verify('a@example.test', 'login', '123456');
    }

    public function testBroadcastStillReachesAnActiveMember(): void
    {
        $this->connection->method('select')->willReturnOnConsecutiveCalls(
            [['id' => 7, 'status' => 'active']],
            [['id' => 9, 'expires_at' => date('Y-m-d H:i:s', time() + 600)]],
            [['id' => 3, 'status' => 'playing']],
            [['id' => 4, 'user_id' => 7, 'status' => 'active']],
        );
        $socket = new GameWebSocket();
        $connection = $this->createMock(TcpConnection::class);
        $connection->id = 103;
        $connection->expects(self::once())->method('send')->willReturnCallback(static function (string $raw): void {
            $message = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('v1.game.answer', $message['event']);
            self::assertSame('request', $message['request_id']);
        });
        $socket->onConnect($connection);
        (new \ReflectionMethod($socket, 'attach'))->invoke($socket, $connection, 'room');
        (new ReflectionProperty(GameWebSocket::class, 'connectionContexts'))->setValue(null, [
            103 => new PlayerContext(userId: 7, refreshSessionId: 9, accessExpiresAt: time() + 600),
        ]);
        (new \ReflectionMethod($socket, 'broadcast'))->invoke($socket, 'room', 'v1.game.answer', 'request', []);
    }

    public function testReauthenticationClearsPreviousRoomSubscriptions(): void
    {
        $this->connection->method('select')->willReturn([['id' => 8, 'expires_at' => date('Y-m-d H:i:s', time() + 600)]]);
        $this->connection->method('update')->willReturn(1);
        $socket = new GameWebSocket(new \App\Common\Services\RequestLimiter(static fn (): int => 1));
        $connection = $this->createMock(TcpConnection::class);
        $connection->id = 104;
        $connection->expects(self::once())->method('send')->willReturnCallback(static function (string $raw): void {
            self::assertSame('v1.authenticated', json_decode($raw, true, 512, JSON_THROW_ON_ERROR)['event']);
        });
        $socket->onConnect($connection);
        (new \ReflectionMethod($socket, 'attach'))->invoke($socket, $connection, 'old-room');
        $socket->onMessage($connection, json_encode(['event' => 'v1.auth', 'request_id' => 'reauth', 'data' => ['token' => 'test-only-token']], JSON_THROW_ON_ERROR));
        self::assertSame([], (new ReflectionProperty(GameWebSocket::class, 'roomConnections'))->getValue());
    }

    public function testWebSocketErrorsKeepStableCodesAndHideInternalDetails(): void
    {
        foreach ([ErrorCode::AUTH_TOKEN_INVALID, ErrorCode::AI_WORKFLOW_TIMEOUT] as $code) {
            try {
                $code->throw();
            } catch (BaseException $exception) {
                self::assertSame(['code' => $code->value, 'retryable' => $code === ErrorCode::AI_WORKFLOW_TIMEOUT], WebSocketErrorFormat::format($exception));
            }
        }
        self::assertSame(['code' => 'system.error', 'retryable' => false], WebSocketErrorFormat::format(new RuntimeException('SQL error with private bindings')));
        self::assertSame('request.param_error', WebSocketErrorFormat::format(new \InvalidArgumentException('request.param_error'))['code']);
    }

    public function testDeparturePreservesParticipationAndCompletedResults(): void
    {
        $updates = [];
        $this->connection->expects(self::exactly(2))->method('update')->willReturnCallback(static function (string $sql, array $bindings) use (&$updates): int {
            $updates[] = [$sql, $bindings];
            return 1;
        });
        $room = new Room(['id' => 3, 'game_id' => 4, 'owner_user_id' => 99]);
        $member = new RoomMember(['id' => 5, 'room_id' => 3, 'user_id' => 7, 'status' => 'active']);
        $member->exists = true;
        (new \ReflectionMethod(RoomBusiness::class, 'deactivateMembership'))->invoke(new RoomBusiness(), $room, $member, 7);
        self::assertStringContainsString('update `turtle_game_players`', $updates[1][0]);
        self::assertStringContainsString('`status` = ?', $updates[1][0]);
        self::assertSame('left', $updates[1][1][0]);
        self::assertSame('playing', $updates[1][1][count($updates[1][1]) - 1]);
        self::assertStringNotContainsString('completed_at', $updates[1][0]);
    }
}
