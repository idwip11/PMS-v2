<?php

namespace App\Libraries;

use InvalidArgumentException;

final class HoltWintersForecaster
{
    public const SEASON_LENGTH = 12;
    public const MINIMUM_POINTS = 12;

    /**
     * Holt-Winters additive untuk data kamar-malam bulanan.
     *
     * @param list<int|float> $values
     * @return list<array{value:float,lower:float,upper:float}>
     */
    public function forecast(array $values, int $horizon = 6): array
    {
        if (count($values) < self::MINIMUM_POINTS) {
            throw new InvalidArgumentException('Holt-Winters membutuhkan minimal 12 titik bulanan.');
        }
        if ($horizon < 1) {
            throw new InvalidArgumentException('Horizon forecast minimal satu bulan.');
        }

        $values = array_map('floatval', array_values($values));
        $period = self::SEASON_LENGTH;
        $alpha = 0.35;
        $beta = 0.15;
        $gamma = 0.25;
        $level = array_sum(array_slice($values, 0, $period)) / $period;
        $trend = 0.0;
        if (count($values) >= $period * 2) {
            for ($index = 0; $index < $period; $index++) {
                $trend += ($values[$index + $period] - $values[$index]) / $period;
            }
            $trend /= $period;
        }
        $seasonals = [];
        for ($index = 0; $index < $period; $index++) {
            $seasonals[$index] = $values[$index] - $level;
        }

        $squaredErrors = [];
        foreach ($values as $index => $value) {
            $seasonIndex = $index % $period;
            $prediction = $level + $trend + $seasonals[$seasonIndex];
            if ($index >= $period) {
                $squaredErrors[] = ($value - $prediction) ** 2;
            }
            $previousLevel = $level;
            $level = $alpha * ($value - $seasonals[$seasonIndex]) + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $previousLevel) + (1 - $beta) * $trend;
            $seasonals[$seasonIndex] = $gamma * ($value - $level) + (1 - $gamma) * $seasonals[$seasonIndex];
        }

        $rmse = $squaredErrors === [] ? 0.0 : sqrt(array_sum($squaredErrors) / count($squaredErrors));
        $result = [];
        $count = count($values);
        for ($step = 1; $step <= $horizon; $step++) {
            $seasonIndex = ($count + $step - 1) % $period;
            $value = max(0.0, $level + $step * $trend + $seasonals[$seasonIndex]);
            $uncertainty = 1.96 * $rmse * sqrt(1 + ($step / $period));
            $result[] = [
                'value' => round($value, 2),
                'lower' => round(max(0.0, $value - $uncertainty), 2),
                'upper' => round($value + $uncertainty, 2),
            ];
        }

        return $result;
    }
}
