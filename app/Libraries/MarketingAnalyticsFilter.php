<?php

namespace App\Libraries;

use DateTimeImmutable;

final class MarketingAnalyticsFilter
{
    private const FIRST_GUESTHOUSE = 1;
    private const LAST_GUESTHOUSE = 5;

    /**
     * @param list<int> $guesthouses
     */
    private function __construct(
        private readonly int $year,
        private readonly ?int $month,
        private readonly array $guesthouses,
    ) {
    }

    public static function fromInput(array $input, ?DateTimeImmutable $today = null): self
    {
        $today ??= new DateTimeImmutable('today');
        $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 2000, 'max_range' => 2100],
        ]);
        $monthInput = $input['month'] ?? null;
        $month = filter_var($monthInput, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 12],
        ]);

        return new self(
            $year === false ? (int) $today->format('Y') : $year,
            $month === false ? null : $month,
            self::parseGuesthouses($input['guesthouses'] ?? $input['guesthouse'] ?? []),
        );
    }

    public function year(): int
    {
        return $this->year;
    }

    public function month(): ?int
    {
        return $this->month;
    }

    /** @return list<int> */
    public function guesthouses(): array
    {
        return $this->guesthouses;
    }

    public function includesAllGuesthouses(): bool
    {
        return $this->guesthouses === [];
    }

    public function startDate(): DateTimeImmutable
    {
        $month = $this->month ?? 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $month));
    }

    public function endDate(): DateTimeImmutable
    {
        if ($this->month === null) {
            return $this->startDate()->modify('+1 year');
        }

        return $this->startDate()->modify('+1 month');
    }

    /** @return array{year:int,month:?int,guesthouses:list<int>} */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'guesthouses' => $this->guesthouses,
        ];
    }

    /** @return array{year:int,month?:int,guesthouses?:list<int>} */
    public function toQueryParameters(): array
    {
        $query = ['year' => $this->year];
        if ($this->month !== null) {
            $query['month'] = $this->month;
        }
        if ($this->guesthouses !== []) {
            $query['guesthouses'] = $this->guesthouses;
        }

        return $query;
    }

    public function periodLabel(): string
    {
        if ($this->month === null) {
            return (string) $this->year;
        }

        return $this->startDate()->format('Y-m');
    }

    /** @return list<int> */
    private static function parseGuesthouses(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        } elseif (! is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        $guesthouses = [];
        foreach ($value as $candidate) {
            $candidate = filter_var($candidate, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => self::FIRST_GUESTHOUSE,
                    'max_range' => self::LAST_GUESTHOUSE,
                ],
            ]);
            if ($candidate !== false) {
                $guesthouses[$candidate] = $candidate;
            }
        }

        ksort($guesthouses);

        return array_values($guesthouses);
    }
}
