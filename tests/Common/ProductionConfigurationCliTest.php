<?php

declare(strict_types=1);

namespace Tests\Common;

use PHPUnit\Framework\TestCase;

final class ProductionConfigurationCliTest extends TestCase
{
    public function testComposeEnvironmentCanBeCheckedWithoutAnEnvFile(): void
    {
        [$exit, $output] = $this->runCheck($this->productionEnvironment());
        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('Production configuration is valid.', $output);
        self::assertStringNotContainsString('test-only-coze-token', $output);
    }

    public function testInvalidEnvironmentFailsWithoutPrintingSecrets(): void
    {
        $environment = $this->productionEnvironment();
        $environment['PLAYER_JWT_SECRET'] = 'short-test-secret';
        [$exit, $output] = $this->runCheck($environment);
        self::assertSame(1, $exit);
        self::assertStringContainsString('PLAYER_JWT_SECRET', $output);
        self::assertStringNotContainsString('short-test-secret', $output);
        self::assertStringNotContainsString('test-only-coze-token', $output);
    }

    public function testSharedConfigurationCanBeCheckedThroughStdin(): void
    {
        $lines = [];
        foreach ($this->productionEnvironment() as $key => $value) {
            $lines[] = $key . '=' . $value;
        }
        [$exit, $output] = $this->runCheck([], implode("\n", $lines));
        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('Production configuration is valid.', $output);
        self::assertStringNotContainsString('test-only-coze-token', $output);
    }

    /** @return array<string, string> */
    private function productionEnvironment(): array
    {
        return [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'APP_URL' => 'https://example.test', 'CORS_ALLOWED_ORIGINS' => 'https://example.test',
            'DB_HOST' => 'mysql', 'DB_NAME' => 'test', 'DB_USER' => 'test',
            'DB_PASSWORD' => 'test-only-password', 'REDIS_HOST' => 'redis',
            'REDIS_QUEUE_ENABLED' => 'true', 'COZE_GAME_DRIVER' => 'coze',
            'COZE_API_TOKEN' => 'test-only-coze-token',
            'COZE_QUESTION_JUDGE_WORKFLOW_ID' => 'test-question',
            'COZE_GUESS_JUDGE_WORKFLOW_ID' => 'test-guess',
            'ANONYMOUS_TOKEN_SECRET' => str_repeat('a', 32),
            'PLAYER_JWT_SECRET' => str_repeat('b', 32),
            'PLAYER_TOKEN_HASH_SECRET' => str_repeat('c', 32),
            'PLAYER_EMAIL_CODE_SECRET' => str_repeat('d', 32),
        ];
    }

    /**
     * @param array<string, string> $environment
     * @return array{int, string}
     */
    private function runCheck(array $environment, ?string $input = null): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/check-production-config.php', $input === null ? '--environment' : '--stdin'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(getenv(), $environment),
        );
        self::assertIsResource($process);
        if ($input !== null) {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
