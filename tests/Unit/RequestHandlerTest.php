<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\Tests\Unit;

use CmsHealthProject\Psr15Implementation\HealthChecker\CallableHealthChecker;
use CmsHealthProject\Psr15Implementation\HealthChecker\DoctrineConnectionHealthChecker;
use CmsHealthProject\Psr15Implementation\HealthChecker\HealthCheckerInterface;
use CmsHealthProject\Psr15Implementation\HealthChecker\HttpHealthChecker;
use CmsHealthProject\Psr15Implementation\RequestHandler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Clock\MockClock;

class RequestHandlerTest extends TestCase
{
    use MatchesSnapshots;

    /** @param list<HealthCheckerInterface> $checks */
    #[DataProvider('handleProvider')]
    public function testHandle(array $checks, int $expectedStatusCode): void
    {
        $clock = new MockClock('2024-01-01 00:01:00');

        $handler = new RequestHandler(
            new ResponseFactory(),
            new StreamFactory(),
            'test-service',
            'test-description',
            $checks,
            $clock,
        );

        $response = $handler->handle(new ServerRequest());

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertSame(['Content-Type' => ['application/health+json']], $response->getHeaders());
        $this->assertMatchesSnapshot($response->getBody()->__toString());
    }

    /** @return array<string, array{0: list<HealthCheckerInterface>, 1: int}> */
    public static function handleProvider(): array
    {
        $clock = new MockClock('2024-01-01 00:01:00');

        return [
            'single check, success' => [
                [
                    $successCheck = new CallableHealthChecker(
                        'example:test',
                        fn () => true,
                        'baz',
                        'component',
                        500,
                        $clock,
                    ),
                ],
                200,
            ],
            'single check, fail' => [
                [
                    $doctrineFailCheck = new DoctrineConnectionHealthChecker(
                        new Connection([], new Driver()),
                        $clock,
                    ),
                ],
                503,
            ],
            'all checks fail' => [
                [
                    $doctrineFailCheck,
                    new HttpHealthChecker(
                        'http:request',
                        new Client(),
                        new Request('GET', 'not-existing'),
                        clock: $clock,
                    ),
                ],
                503,
            ],
            'multiple checks, one fail' => [
                [$successCheck, $doctrineFailCheck],
                503,
            ],
            'multiple checks, fail on exception' => [
                [
                    $successCheck,
                    new CallableHealthChecker(
                        'test:exception',
                        callable: fn () => throw new Exception('foobar'),
                        clock: $clock,
                    ),
                ],
                503,
            ],
            'multiple checks, success' => [
                [
                    $successCheck,
                    new HttpHealthChecker(
                        'http:request',
                        new Client(['handler' => new MockHandler([new Response()])]),
                        new Request('GET', 'not-existing'),
                        clock: $clock,
                    ),
                ],
                200,
            ],
        ];
    }
}
