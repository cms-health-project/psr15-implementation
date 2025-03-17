<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation;

use CmsHealth\Definition\HealthCheckStatus;
use CmsHealthProject\Psr15Implementation\EventDispatcher\CollectHealthCheckResultsEvent;
use CmsHealthProject\Psr15Implementation\HealthChecker\HealthCheckerInterface;
use CmsHealthProject\SerializableReferenceImplementation\Check;
use CmsHealthProject\SerializableReferenceImplementation\CheckCollection;
use CmsHealthProject\SerializableReferenceImplementation\HealthCheck;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    private const CMS_HEALTH_VERSION = '1';
    private const CONTENT_TYPE = 'application/health+json';

    /** @param HealthCheckerInterface[] $healthCheckers */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $serviceId,
        private readonly string $description,
        private readonly array $healthCheckers,
        private readonly ?ClockInterface $clock = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{names?: non-empty-list<string>} $queryParams */
        $queryParams = $request->getQueryParams();
        $checkNames = $queryParams['names'] ?? null;

        $healthCheck = $this->getHealthCheck($checkNames);
        $statusCode = $this->getStatusCode($healthCheck);
        $body = $this->getBody($healthCheck);

        return $this->responseFactory->createResponse($statusCode)
            ->withHeader('Content-Type', self::CONTENT_TYPE)
            ->withBody($body);
    }

    /**
     * @param non-empty-list<string>|null $filterByCheckNames
     */
    protected function getHealthCheck(?array $filterByCheckNames = null): HealthCheck
    {
        $time = $this->clock?->now() ?? new DateTimeImmutable();

        return new HealthCheck(
            self::CMS_HEALTH_VERSION,
            $this->serviceId,
            $this->description,
            $time,
            $this->getCheckCollection($filterByCheckNames),
        );
    }

    /**
     * @param non-empty-list<string>|null $filterByCheckNames
     */
    protected function getCheckCollection(?array $filterByCheckNames = null): CheckCollection
    {
        $event = new CollectHealthCheckResultsEvent($filterByCheckNames);
        $this->eventDispatcher?->dispatch($event);

        /** @var Check[] $checks */
        $checks = [
            ...$this->getCheckResults($filterByCheckNames),
            ...$event->getChecks(),
        ];

        return new CheckCollection($checks);
    }

    /**
     * @param non-empty-list<string>|null $filterByCheckNames
     *
     * @return Check[]
     */
    protected function getCheckResults(?array $filterByCheckNames = null): array
    {
        $healthCheckers = $this->healthCheckers;
        if (is_array($filterByCheckNames)) {
            $healthCheckers = array_filter(
                $this->healthCheckers,
                static fn (HealthCheckerInterface $healthChecker) => in_array($healthChecker->getName(), $filterByCheckNames, true),
            );
        }

        return array_map(
            static fn (HealthCheckerInterface $check): Check => $check->check(),
            $healthCheckers,
        );
    }

    protected function getStatusCode(HealthCheck $healthCheck): int
    {
        return match ($healthCheck->getStatus()) {
            HealthCheckStatus::Fail => 503,
            default => 200,
        };
    }

    protected function getBody(HealthCheck $healthCheck): StreamInterface
    {
        return $this->streamFactory->createStream(
            json_encode($healthCheck, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        );
    }
}
