<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\HealthChecker;

use CmsHealth\Definition\CheckResultStatus;
use CmsHealthProject\SerializableReferenceImplementation\Check;
use CmsHealthProject\SerializableReferenceImplementation\CheckResult;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class HttpHealthChecker implements HealthCheckerInterface
{
    private const UNIT_STATUS_CODE = 'statusCode';
    private const COMPONENT_ID = 'httpRequest';
    private const COMPONENT_TYPE = 'duration';
    private const DEFAULT_THRESHOLD_IN_MS = 500;

    /**
     * @param non-empty-string $name
     * @param non-empty-list<int> $expectedStatusCodes
     */
    public function __construct(
        public readonly string $name,
        private readonly ClientInterface $httpClient,
        private readonly RequestInterface $request,
        private readonly string|null $componentId = self::COMPONENT_ID,
        private readonly string|null $componentType = self::COMPONENT_TYPE,
        private readonly int $tresholdInMs = self::DEFAULT_THRESHOLD_IN_MS,
        private readonly array $expectedStatusCodes = [200, 204],
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function check(): Check
    {
        $startedAt = $this->clock?->now() ?? new \DateTimeImmutable();

        $response = null;
        $check = (new CallableHealthChecker(
            $this->name,
            function () use (&$response) {
                $response = $this->httpClient->sendRequest($this->request);
            },
            $this->componentId,
            null,
            $this->tresholdInMs,
            $this->clock,
        ))->check();

        $statusCode = $response?->getStatusCode();
        if (!in_array($statusCode, $this->expectedStatusCodes, true)) {
            $expected = implode(', ', $this->expectedStatusCodes);
            $body = (string) $response?->getBody();
            $output = 'Expected HTTP status code(s) '.$expected.' expected, but received '.$statusCode.'. Response: '.$body;

            $checkResult = new CheckResult(
                CheckResultStatus::Fail,
                $this->componentId,
                $this->componentType,
                $startedAt,
                var_export($statusCode, true),
                self::UNIT_STATUS_CODE,
                $output
            );
            $check->addCheckResults($checkResult);
        }

        return $check;
    }
}
