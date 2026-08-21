<?php

use App\Libraries\MarketingAnalyticsFilter;
use App\Libraries\MarketingAnalyticsQueryService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class MarketingAnalyticsQueryServiceTest extends CIUnitTestCase
{
    public function testReservationScopeUsesSharedPeriodGuesthousesAndValidStatuses(): void
    {
        $filter = MarketingAnalyticsFilter::fromInput([
            'year' => 2026,
            'month' => 8,
            'guesthouses' => [1, 3],
        ]);
        $sql = (new MarketingAnalyticsQueryService(db_connect('tests')))
            ->reservations($filter)
            ->select('reservations.id')
            ->getCompiledSelect();

        $compactSql = str_replace(' ', '', $sql);
        $this->assertStringContainsString("'Confirmed','Checked_In','Checked_Out'", $compactSql);
        $this->assertStringContainsString("'2026-08-01'", $sql);
        $this->assertStringContainsString("'2026-09-01'", $sql);
        $this->assertStringContainsString('IN(1,3)', $compactSql);
    }

    public function testStayOverlapUsesCheckInAndCheckOutBoundaries(): void
    {
        $filter = MarketingAnalyticsFilter::fromInput(['year' => 2026, 'month' => 8]);
        $sql = (new MarketingAnalyticsQueryService(db_connect('tests')))
            ->reservations($filter, true)
            ->select('reservations.id')
            ->getCompiledSelect();

        $this->assertStringContainsString('check_in_date', $sql);
        $this->assertStringContainsString('check_out_date', $sql);
        $this->assertStringContainsString("'2026-08-01'", $sql);
        $this->assertStringContainsString("'2026-09-01'", $sql);
    }

    public function testPaymentRevenueDoesNotDisappearWhenReservationStatusChanges(): void
    {
        $filter = MarketingAnalyticsFilter::fromInput(['year' => 2026, 'month' => 8]);
        $sql = (new MarketingAnalyticsQueryService(db_connect('tests')))
            ->payments($filter)
            ->select('payments.amount')
            ->getCompiledSelect();

        $this->assertStringContainsString("`db_payments`.`status` = 'Paid'", $sql);
        $this->assertStringNotContainsString('Confirmed', $sql);
        $this->assertStringNotContainsString('Checked_In', $sql);
        $this->assertStringNotContainsString('Checked_Out', $sql);
    }
}
