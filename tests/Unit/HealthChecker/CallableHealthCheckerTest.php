<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\Tests\Unit\HealthChecker;

use CmsHealthProject\Psr15Implementation\HealthChecker\CallableHealthChecker;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Spatie\Snapshots\MatchesSnapshots;

use function json_encode;

class CallableHealthCheckerTest extends TestCase
{
    use MatchesSnapshots;

    #[TestWith(['@1704455198.0166', '@1704455198.7166'])]
    #[TestWith(['2024-01-15 12:43:41.328996', '2024-01-15 12:43:48.551069'])]
    #[TestWith(['2024-01-15 12:43:41.328996', '2024-01-15 12:43:41.351069'])]
    public function testFailOnTimeoutReached(string $start, string $finish): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')
            ->willReturnOnConsecutiveCalls(new DateTimeImmutable($start), new DateTimeImmutable($finish));

        $this->assertMatchesJsonSnapshot(
            json_encode(
                (new CallableHealthChecker(
                    'test-id',
                    fn () => true,
                    'component-id',
                    'component-type',
                    500,
                    $clock,
                ))->check(),
                JSON_THROW_ON_ERROR
            ),
        );
    }

    public function testFailOnException(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')
            ->willReturnOnConsecutiveCalls(new DateTimeImmutable('2025-03-16 12:02:13'), new DateTimeImmutable('2025-03-16 12:02:14'));

        $this->assertMatchesJsonSnapshot(
            json_encode(
                (new CallableHealthChecker(
                    'test-id',
                    fn () => throw new \RuntimeException('foobar'),
                    'component-id',
                    'component-type',
                    500,
                    $clock,
                ))->check(),
                JSON_THROW_ON_ERROR
            ),
        );
    }
}
