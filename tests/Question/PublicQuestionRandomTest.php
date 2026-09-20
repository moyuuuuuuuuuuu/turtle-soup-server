<?php

declare(strict_types=1);

namespace Tests\Question;

use App\Common\Enums\ErrorCode;
use App\Common\Exceptions\BaseException;
use App\Question\Business\PublicQuestionBusiness;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verifies generated SQL using mocked I/O without connecting to a database. */
final class PublicQuestionRandomTest extends TestCase
{
    private ?ConnectionResolverInterface $oldResolver;

    protected function setUp(): void
    {
        class_exists(\support\Model::class);
        $this->oldResolver = Model::getConnectionResolver();
    }

    protected function tearDown(): void
    {
        if ($this->oldResolver !== null) {
            Model::setConnectionResolver($this->oldResolver);
        } else {
            Model::unsetConnectionResolver();
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function filters(): iterable
    {
        yield 'default' => [[]];
        yield 'filtered' => [['difficulty' => 2, 'tag_id' => 7, 'risk_level' => 'safe']];
        yield 'featured and popular' => [['featured' => true, 'sort' => 'popular']];
    }

    /** @param array<string, mixed> $filters */
    #[DataProvider('filters')]
    public function testRandomUsesOnlyRandomOrderingAndPreservesFilters(array $filters): void
    {
        $connection = $this->getMockBuilder(MySqlConnection::class)
            ->setConstructorArgs([null, 'test'])
            ->onlyMethods(['select'])
            ->getMock();
        $resolver = new ConnectionResolver(['default' => $connection]);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);

        $connection->expects(self::once())->method('select')->willReturnCallback(static function (string $sql, array $bindings) use ($filters): array {
            self::assertMatchesRegularExpression('/order by RAND\(\) limit 1$/', $sql);
            self::assertStringContainsString('`status` = ?', $sql);
            self::assertStringContainsString('`risk_level` in (?, ?)', $sql);
            self::assertSame(['published', 'safe', 'caution'], array_slice($bindings, 0, 3));
            if (isset($filters['difficulty'])) {
                self::assertStringContainsString('`difficulty` = ?', $sql);
                self::assertStringContainsString('`turtle_tags`.`id` = ?', $sql);
                self::assertStringContainsString('`risk_level` = ?', $sql);
                self::assertSame([2, 7, 'safe'], array_slice($bindings, 3));
            }
            if (isset($filters['featured'])) {
                self::assertStringContainsString('`is_featured` = ?', $sql);
                self::assertStringContainsString('featured_starts_at <= ?', $sql);
                self::assertStringContainsString('featured_ends_at > ?', $sql);
            }

            return [];
        });

        try {
            (new PublicQuestionBusiness())->random($filters);
            self::fail('An empty candidate pool must report question not found.');
        } catch (BaseException $exception) {
            self::assertSame(ErrorCode::QUESTION_NOT_FOUND, $exception->errorCode);
        }
    }
}
