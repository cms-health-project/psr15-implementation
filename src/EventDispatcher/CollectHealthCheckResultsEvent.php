<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\EventDispatcher;

use CmsHealthProject\SerializableReferenceImplementation\Check;

final class CollectHealthCheckResultsEvent
{
    /** @var Check[] */
    private array $checks = [];

    /**
     * @param non-empty-list<string>|null $checkNames Names of the checks that should be done, null for allowing all checks.
     */
    public function __construct(
        private readonly ?array $checkNames = null
    ) {
    }

    public function shouldCheck(string $name): bool
    {
        return null === $this->checkNames || in_array($name, $this->checkNames, true);
    }

    public function addCheck(Check $check): void
    {
        if (!$this->shouldCheck($check->getName())) {
            throw new \RuntimeException('only requested checks allowed, got '.$check->getName().', expected one of: '.implode(', ', $this->checkNames));
        }

        $this->checks[] = $check;
    }

    /**
     * @return Check[]
     */
    public function getChecks(): array
    {
        return $this->checks;
    }
}
