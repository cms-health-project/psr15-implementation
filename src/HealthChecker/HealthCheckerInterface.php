<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\HealthChecker;

use CmsHealthProject\SerializableReferenceImplementation\Check;

interface HealthCheckerInterface
{
    public function getName(): string;
    public function check(): Check;
}
