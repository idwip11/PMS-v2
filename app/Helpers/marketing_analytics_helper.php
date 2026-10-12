<?php

if (! function_exists('marketing_valid_reservation_statuses')) {
    /**
     * Status reservasi yang mewakili kamar benar-benar dipesan/digunakan.
     *
     * @return list<string>
     */
    function marketing_valid_reservation_statuses(): array
    {
        return ['Confirmed', 'Checked_In', 'Checked_Out'];
    }
}

if (! function_exists('marketing_is_valid_reservation_status')) {
    function marketing_is_valid_reservation_status(mixed $status): bool
    {
        return in_array((string) $status, marketing_valid_reservation_statuses(), true);
    }
}

if (! function_exists('normalize_guest_origin')) {
    /**
     * Menyatukan kapitalisasi dan spasi asal tamu untuk kebutuhan agregasi.
     */
    function normalize_guest_origin(mixed $origin): string
    {
        $origin = trim((string) $origin);
        $origin = preg_replace('/\s+/u', ' ', $origin) ?? '';

        if ($origin === '') {
            return 'Tidak diketahui';
        }

        return mb_convert_case(mb_strtolower($origin, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}

if (! function_exists('canonical_guest_email')) {
    function canonical_guest_email(mixed $email): ?string
    {
        $email = mb_strtolower(trim((string) $email), 'UTF-8');

        return $email === '' ? null : $email;
    }
}

if (! function_exists('canonical_guest_phone')) {
    function canonical_guest_phone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        return $digits;
    }
}

if (! function_exists('marketing_room_nights_in_period')) {
    /**
     * Menghitung malam dari satu reservasi yang berada dalam interval laporan
     * [periodStart, periodEnd). Tanggal check-out tidak dihitung sebagai malam.
     */
    function marketing_room_nights_in_period(
        string $checkIn,
        string $checkOut,
        string $periodStart,
        string $periodEnd,
    ): int {
        try {
            $stayStart = new DateTimeImmutable($checkIn);
            $stayEnd = new DateTimeImmutable($checkOut);
            $scopeStart = new DateTimeImmutable($periodStart);
            $scopeEnd = new DateTimeImmutable($periodEnd);
        } catch (Exception) {
            return 0;
        }

        $start = max($stayStart, $scopeStart);
        // Transit reservations use the same date for check-in and check-out,
        // but occupy the room for one operational night.
        if ($stayEnd <= $stayStart) {
            $stayEnd = $stayStart->modify('+1 day');
        }
        $end = min($stayEnd, $scopeEnd);

        return $end <= $start ? 0 : (int) $start->diff($end)->days;
    }
}
