<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\HealthChecker;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

class DoctrineConnectionHealthChecker extends CallableHealthChecker
{
    private const NAME = 'doctrine:connection';
    private const COMPONENT_ID = 'database';
    private const COMPONENT_TYPE = 'dummy_select';
    private const TRESHOLD_IN_MS = 500;

    public function __construct(
        Connection $connection,
        ?ClockInterface $clock = null,
    ) {
        parent::__construct(
            self::NAME,
            static fn () => $connection->executeStatement(
                $connection->getDriver()->getDatabasePlatform($connection)->getDummySelectSQL(),
            ),
            self::COMPONENT_ID,
            self::COMPONENT_TYPE,
            self::TRESHOLD_IN_MS,
            $clock,
        );
    }
}
