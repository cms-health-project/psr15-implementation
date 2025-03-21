<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\Tests\Unit;

use CmsHealth\Definition\CheckResultStatus;
use CmsHealthProject\Psr15Implementation\EventDispatcher\CollectHealthCheckResultsEvent;
use CmsHealthProject\Psr15Implementation\HealthChecker\CallableHealthChecker;
use CmsHealthProject\Psr15Implementation\HealthChecker\DoctrineConnectionHealthChecker;
use CmsHealthProject\Psr15Implementation\HealthChecker\HealthCheckerInterface;
use CmsHealthProject\Psr15Implementation\HealthChecker\HttpHealthChecker;
use CmsHealthProject\Psr15Implementation\RequestHandler;
use CmsHealthProject\SerializableReferenceImplementation\Check;
use CmsHealthProject\SerializableReferenceImplementation\CheckResult;
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
use Symfony\Component\EventDispatcher\EventDispatcher;

class RequestHandlerTest extends TestCase
{
    use MatchesSnapshots;

    /**
     * @param list<HealthCheckerInterface> $checks
     * @param list<Check> $eventDispatcherChecks
     */
    #[DataProvider('handleProvider')]
    public function testHandle(array $checks, array $eventDispatcherChecks, int $expectedStatusCode): void
    {
        $clock = new MockClock('2024-01-01 00:01:00');

        $eventDispatcher = new EventDispatcher();
        foreach ($eventDispatcherChecks as $eventDispatcherCheck) {
            $eventDispatcher->addListener(
                CollectHealthCheckResultsEvent::class,
                fn (CollectHealthCheckResultsEvent $event) => $event->addCheck($eventDispatcherCheck),
            );
        }

        $handler = new RequestHandler(
            new ResponseFactory(),
            new StreamFactory(),
            'test-service',
            'test-description',
            $checks,
            $clock,
            $eventDispatcher,
        );

        $response = $handler->handle(new ServerRequest());

        $this->assertSame($expectedStatusCode, $response->getStatusCode());
        $this->assertSame(['Content-Type' => ['application/health+json']], $response->getHeaders());
        $this->assertMatchesSnapshot($response->getBody()->__toString());
    }

    /** @return array<string, array{0: list<HealthCheckerInterface>, 1: list<Check>, 2: int}> */
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
                [],
                200,
            ],
            'single check, fail' => [
                [
                    $doctrineFailCheck = new DoctrineConnectionHealthChecker(
                        new Connection([], new Driver()),
                        $clock,
                    ),
                ],
                [],
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
                [],
                503,
            ],
            'multiple checks, one fail' => [
                [$successCheck, $doctrineFailCheck],
                [],
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
                [],
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
                [],
                200,
            ],
            'one event-dispatcher check, pass' => [
                [],
                [
                    new Check('event-dispatcher:pass', [new CheckResult(
                        CheckResultStatus::Pass,
                    )]),
                ],
                200,
            ],
            'multiple event-dispatcher checks, pass' => [
                [],
                [
                    new Check('event-dispatcher:one', [new CheckResult(
                        CheckResultStatus::Pass,
                    )]),
                    new Check('event-dispatcher:two', [new CheckResult(
                        CheckResultStatus::Info,
                    )]),
                ],
                200,
            ],
            'multiple event-dispatcher checks, fail' => [
                [],
                [
                    new Check('event-dispatcher:one', [new CheckResult(
                        CheckResultStatus::Info,
                    )]),
                    new Check('event-dispatcher:two', [new CheckResult(
                        CheckResultStatus::Fail,
                    )]),
                ],
                503,
            ],
            'direct and event-dispatcher checks, pass' => [
                [$successCheck],
                [
                    new Check('event-dispatcher:test', [new CheckResult(
                        CheckResultStatus::Pass,
                    )]),
                ],
                200,
            ],
        ];
    }
}
