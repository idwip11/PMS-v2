<?php

use App\Libraries\HoltWintersForecaster;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class HoltWintersForecasterTest extends CIUnitTestCase
{
    public function testForecastRequiresTwelveMonthlyPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new HoltWintersForecaster())->forecast(array_fill(0, 11, 10));
    }

    public function testForecastReturnsSixNonNegativePointsWithOrderedBounds(): void
    {
        $history = [];
        for ($index = 0; $index < 36; $index++) {
            $history[] = 40 + ($index * 1.5) + (($index % 12) * 2);
        }

        $forecast = (new HoltWintersForecaster())->forecast($history, 6);

        $this->assertCount(6, $forecast);
        foreach ($forecast as $point) {
            $this->assertGreaterThanOrEqual(0, $point['lower']);
            $this->assertGreaterThanOrEqual($point['lower'], $point['value']);
            $this->assertGreaterThanOrEqual($point['value'], $point['upper']);
        }
    }
}
