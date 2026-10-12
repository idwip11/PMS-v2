<?php

namespace App\Libraries;

use DateTimeImmutable;

final class PropertyPricing
{
    /** @return array{pricing_mode:string,base_rate:float} */
    public static function forProperty(int $propertyId): array
    {
        $row = db_connect()->table('properties')->select('pricing_mode, base_rate')
            ->where('id', $propertyId)->get()->getRowArray();
        return [
            'pricing_mode' => (string) ($row['pricing_mode'] ?? 'room_nightly'),
            'base_rate' => (float) ($row['base_rate'] ?? 0),
        ];
    }

    /** @return array{pricing_mode:string,base_rate:float} */
    public static function active(): array
    {
        return self::forProperty(PropertyContext::activeId());
    }

    /** A checkout is an anchored month anniversary, clamped to month end. */
    public static function wholeMonths(string $checkIn, string $checkOut): ?int
    {
        $start = new DateTimeImmutable($checkIn);
        $end = new DateTimeImmutable($checkOut);
        if ($end <= $start) {
            return null;
        }
        $day = (int) $start->format('d');
        for ($months = 1; $months <= 240; $months++) {
            $target = $start->modify('first day of this month')->modify('+' . $months . ' months');
            $targetDay = min($day, (int) $target->format('t'));
            $anniversary = $target->setDate((int) $target->format('Y'), (int) $target->format('m'), $targetDay);
            if ($anniversary == $end) {
                return $months;
            }
            if ($anniversary > $end) {
                return null;
            }
        }
        return null;
    }
}
