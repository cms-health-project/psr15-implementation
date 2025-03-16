<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\HealthChecker;

use Closure;
use CmsHealth\Definition\CheckResultStatus;
use CmsHealthProject\SerializableReferenceImplementation\Check;
use CmsHealthProject\SerializableReferenceImplementation\CheckResult;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Throwable;

class CallableHealthChecker implements HealthCheckerInterface
{
    private const UNIT_MILLISECONDS = 'ms';
    private const DEFAULT_COMPONENT_TYPE = 'duration';
    private const DEFAULT_THRESHOLD_IN_MS = 500;

    private Closure $closure;

    /**
     * @param non-empty-string $name
     * @param callable(): mixed $callable
     */
    public function __construct(
        private readonly string $name,
        callable $callable,
        private readonly string|null $componentId = null,
        private readonly string|null $componentType = self::DEFAULT_COMPONENT_TYPE,
        private readonly int $tresholdInMs = self::DEFAULT_THRESHOLD_IN_MS,
        private readonly ?ClockInterface $clock = null,
    ) {
        $this->closure = Closure::fromCallable($callable);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function check(): Check
    {
        $output = null;
        $status = CheckResultStatus::Pass;
        $startedAt = $this->clock?->now() ?? new DateTimeImmutable();

        try {
            ($this->closure)();
        } catch (Throwable $e) {
            $status = CheckResultStatus::Fail;
            $output = $e->getMessage();
        } finally {
            $finishedAt = $this->clock?->now() ?? new DateTimeImmutable();
        }

        $durationInMs = $this->getMsSinceUnixEpoch($finishedAt) - $this->getMsSinceUnixEpoch($startedAt);

        if ($status === CheckResultStatus::Pass && $durationInMs > $this->tresholdInMs) {
            $status = CheckResultStatus::Warn;
            $output = "Response time threshold {$this->tresholdInMs}ms surpassed";
        }

        $checkResult = new CheckResult(
            $status,
            $this->componentId,
            $this->componentType,
            $startedAt,
            (string) $durationInMs,
            self::UNIT_MILLISECONDS,
            $output
        );

        return new Check($this->name, [$checkResult]);
    }

    private function getMsSinceUnixEpoch(DateTimeImmutable $date): int
    {
        return (int) ($date->getTimestamp() * 1000 + (int) $date->format('u') / 1000);
    }
}
