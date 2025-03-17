<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\Tests\Unit\EventDispatcher;

use CmsHealthProject\Psr15Implementation\EventDispatcher\CollectHealthCheckResultsEvent;
use CmsHealthProject\SerializableReferenceImplementation\Check;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CollectHealthCheckResultsEventTest extends TestCase
{
    public function testEmptyChecksAfterCreation(): void
    {
        $event = new CollectHealthCheckResultsEvent();

        self::assertEmpty($event->getChecks());
    }

    public function testGetChecksReturnsAddedChecks(): void
    {
        $event = new CollectHealthCheckResultsEvent();

        $check1 = $this->createMock(Check::class);
        $check2 = $this->createMock(Check::class);

        $event->addCheck($check1);
        $event->addCheck($check2);

        self::assertSame([$check1, $check2], $event->getChecks());
    }

    /**
     * @param non-empty-list<string>|null $checkNames
     */
    #[TestWith([null, 'foobar', true])]
    #[TestWith([['foobar'], 'foobar', true])]
    #[TestWith([['foobar'], 'nope', false])]
    #[TestWith([['foo', 'bar'], 'foo', true])]
    #[TestWith([['foo', 'bar'], 'bar', true])]
    #[TestWith([['foo', 'bar'], 'baz', false])]
    public function testShouldCheck(?array $checkNames, string $name, bool $expected): void
    {
        $event = new CollectHealthCheckResultsEvent($checkNames);

        self::assertSame($expected, $event->shouldCheck($name));
    }

    public function testAddCheckThrowsExceptionIfCheckNameNotOnList(): void
    {
        $event = new CollectHealthCheckResultsEvent(['foo', 'bar']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only requested checks allowed, got nope, expected one of: foo, bar');

        $check = $this->createMock(Check::class);
        $check->method('getName')->willReturn('nope');

        $event->addCheck($check);
    }
}
