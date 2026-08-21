<?php

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class MarketingAnalyticsHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('marketing_analytics');
    }

    public function testReservationStatusesAreCentralized(): void
    {
        $this->assertSame(['Confirmed', 'Checked_In', 'Checked_Out'], marketing_valid_reservation_statuses());
        $this->assertTrue(marketing_is_valid_reservation_status('Checked_Out'));
        $this->assertFalse(marketing_is_valid_reservation_status('Cancelled'));
        $this->assertFalse(marketing_is_valid_reservation_status('confirmed'));
    }

    public function testOriginNormalizationCollapsesWhitespaceAndCapitalization(): void
    {
        $this->assertSame('Jakarta Selatan', normalize_guest_origin("  JAKARTA\t  selatan "));
        $this->assertSame('Mataram', normalize_guest_origin('mAtArAm'));
        $this->assertSame('Tidak diketahui', normalize_guest_origin('  '));
        $this->assertSame('Tidak diketahui', normalize_guest_origin(null));
    }

    public function testGuestIdentityCanonicalization(): void
    {
        $this->assertSame('guest@example.com', canonical_guest_email(' Guest@Example.COM '));
        $this->assertNull(canonical_guest_email(''));
        $this->assertSame('628123456789', canonical_guest_phone('+62 812-3456-789'));
        $this->assertSame('628123456789', canonical_guest_phone('0812 3456 789'));
        $this->assertNull(canonical_guest_phone('---'));
    }

    public function testRoomNightsAreSplitAtReportingBoundaries(): void
    {
        $this->assertSame(2, marketing_room_nights_in_period('2026-07-30', '2026-08-03', '2026-08-01', '2026-09-01'));
        $this->assertSame(1, marketing_room_nights_in_period('2026-08-31', '2026-09-02', '2026-08-01', '2026-09-01'));
        $this->assertSame(2, marketing_room_nights_in_period('2025-12-30', '2026-01-03', '2026-01-01', '2027-01-01'));
        $this->assertSame(0, marketing_room_nights_in_period('2026-07-30', '2026-08-01', '2026-08-01', '2026-09-01'));
        $this->assertSame(0, marketing_room_nights_in_period('2026-08-05', '2026-08-05', '2026-08-01', '2026-09-01'));
    }
}
