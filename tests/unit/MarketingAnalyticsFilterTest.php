<?php

use App\Libraries\MarketingAnalyticsFilter;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class MarketingAnalyticsFilterTest extends CIUnitTestCase
{
    public function testMonthlyFilterProducesExclusiveDateRangeAndUniqueGuesthouses(): void
    {
        $filter = MarketingAnalyticsFilter::fromInput([
            'year' => '2026',
            'month' => '8',
            'guesthouses' => ['5', '2', '2', 'invalid', '9'],
        ]);

        $this->assertSame(2026, $filter->year());
        $this->assertSame(8, $filter->month());
        $this->assertSame([2, 5], $filter->guesthouses());
        $this->assertSame('2026-08-01', $filter->startDate()->format('Y-m-d'));
        $this->assertSame('2026-09-01', $filter->endDate()->format('Y-m-d'));
        $this->assertSame('year=2026&month=8&guesthouses%5B0%5D=2&guesthouses%5B1%5D=5', http_build_query($filter->toQueryParameters()));
    }

    public function testYearlyFilterUsesAllGuesthousesAndFullYear(): void
    {
        $filter = MarketingAnalyticsFilter::fromInput([
            'year' => 2025,
            'month' => 'all',
            'guesthouses' => '',
        ]);

        $this->assertNull($filter->month());
        $this->assertTrue($filter->includesAllGuesthouses());
        $this->assertSame('2025-01-01', $filter->startDate()->format('Y-m-d'));
        $this->assertSame('2026-01-01', $filter->endDate()->format('Y-m-d'));
        $this->assertSame(['year' => 2025], $filter->toQueryParameters());
    }

    public function testInvalidYearFallsBackToProvidedCurrentDate(): void
    {
        $filter = MarketingAnalyticsFilter::fromInput(
            ['year' => 'not-a-year'],
            new DateTimeImmutable('2031-04-05'),
        );

        $this->assertSame(2031, $filter->year());
    }
}
