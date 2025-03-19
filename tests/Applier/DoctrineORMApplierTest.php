<?php

declare(strict_types=1);

namespace UnZeroUn\Sorter\Tests\Applier;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UnZeroUn\Sorter\Applier\DoctrineORMApplier;
use UnZeroUn\Sorter\Exception\IncompatibleApplierException;
use UnZeroUn\Sorter\Sort;

final class DoctrineORMApplierTest extends TestCase
{
    public function testSupportsQueryBuilder(): void
    {
        $this->assertTrue(
            (new DoctrineORMApplier())->supports(new QueryBuilder($this->createMock(EntityManagerInterface::class))),
        );
    }

    public function testNotSupportsThings(): void
    {
        $this->assertFalse((new DoctrineORMApplier())->supports(new \stdClass()));
    }

    public function testItThrowsInIncompatibleData(): void
    {
        $this->expectException(IncompatibleApplierException::class);

        (new DoctrineORMApplier())->apply($this->createMock(Sort::class), []);
    }

    public function testItDoesNotCrashWithEmptySort(): void
    {
        /** @var Sort&MockObject $sort */
        $toBeSorted = $this->createMock(QueryBuilder::class);
        $toBeSorted->expects($this->never())->method('orderBy');
        $toBeSorted->expects($this->never())->method('addOrderBy');

        $sort = $this->createMock(Sort::class);
        $sort->method('getFields')->willReturn([]);

        (new DoctrineORMApplier())->apply($sort, $toBeSorted);
    }

    public function testItSortsWithoutOverride(): void
    {
        /** @var Sort&MockObject $sort */
        $toBeSorted = $this->createMock(QueryBuilder::class);
        $toBeSorted->expects($this->once())->method('orderBy')->with('a', 'DESC');
        $toBeSorted->expects($this->never())->method('addOrderBy');

        $sort = $this->createMock(Sort::class);
        $sort->method('getFields')->willReturn(['a']);
        $sort->method('getDirection')->with('a')->willReturn('DESC');

        (new DoctrineORMApplier())->apply($sort, $toBeSorted);
    }

    public function testItSortsWithOverride(): void
    {
        /** @var Sort&MockObject $sort */
        $toBeSorted = $this->createMock(QueryBuilder::class);
        $toBeSorted->expects($this->never())->method('orderBy');
        $toBeSorted->expects($this->once())->method('addOrderBy')->with('a', 'DESC');

        $sort = $this->createMock(Sort::class);
        $sort->method('getFields')->willReturn(['a']);
        $sort->method('getDirection')->with('a')->willReturn('DESC');

        (new DoctrineORMApplier())->apply($sort, $toBeSorted, ['override' => false]);
    }

    public function testItSortsMultipleFields(): void
    {
        /** @var Sort&MockObject $sort */
        $toBeSorted = $this->createMock(QueryBuilder::class);
        $toBeSorted->expects($this->once())->method('orderBy')->with('a', 'DESC');
        $toBeSorted->expects($this->once())->method('addOrderBy')->with('b', 'ASC');

        $sort = $this->createMock(Sort::class);
        $sort->method('getFields')->willReturn(['a', 'b']);
        $sort->method('getDirection')->willReturnCallback(fn (string $field) => 'a' === $field ? 'DESC' : 'ASC');

        (new DoctrineORMApplier())->apply($sort, $toBeSorted);
    }
}
