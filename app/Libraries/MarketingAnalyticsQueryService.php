<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;

final class MarketingAnalyticsQueryService
{
    public function __construct(private readonly BaseConnection $db)
    {
        helper('marketing_analytics');
    }

    public function reservations(MarketingAnalyticsFilter $filter, bool $stayOverlap = false): BaseBuilder
    {
        $builder = $this->db->table('reservations')
            ->join('rooms', 'rooms.id = reservations.room_id');

        $this->applyValidReservations($builder);
        $this->applyGuesthouses($builder, $filter);

        if ($stayOverlap) {
            return $this->applyStayOverlap($builder, $filter);
        }

        return $this->applyPeriod($builder, $filter, 'reservations.check_in_date');
    }

    public function payments(MarketingAnalyticsFilter $filter): BaseBuilder
    {
        $builder = $this->db->table('payments')
            ->join('reservations', 'reservations.id = payments.reservation_id')
            ->join('rooms', 'rooms.id = reservations.room_id')
            ->where('payments.status', 'Paid');

        $this->applyGuesthouses($builder, $filter);

        return $this->applyPeriod($builder, $filter, 'payments.payment_date');
    }

    public function expenses(MarketingAnalyticsFilter $filter): BaseBuilder
    {
        $builder = $this->db->table('expenses')
            ->where('expenses.status !=', 'Rejected');

        $this->applyExpenseGuesthouses($builder, $filter);

        return $this->applyPeriod($builder, $filter, 'expenses.date_incurred');
    }

    public function applyPeriod(
        BaseBuilder $builder,
        MarketingAnalyticsFilter $filter,
        string $dateColumn,
    ): BaseBuilder {
        return $builder
            ->where($dateColumn . ' >=', $filter->startDate()->format('Y-m-d'))
            ->where($dateColumn . ' <', $filter->endDate()->format('Y-m-d'));
    }

    public function applyStayOverlap(
        BaseBuilder $builder,
        MarketingAnalyticsFilter $filter,
        string $checkInColumn = 'reservations.check_in_date',
        string $checkOutColumn = 'reservations.check_out_date',
    ): BaseBuilder {
        return $builder->groupStart()
            ->groupStart()
                ->where($checkInColumn . ' <', $filter->endDate()->format('Y-m-d'))
                ->where($checkOutColumn . ' >', $filter->startDate()->format('Y-m-d'))
            ->groupEnd()
            ->orGroupStart()
                ->where($checkInColumn . ' = ' . $checkOutColumn, null, false)
                ->where($checkInColumn . ' >=', $filter->startDate()->format('Y-m-d'))
                ->where($checkInColumn . ' <', $filter->endDate()->format('Y-m-d'))
            ->groupEnd()
        ->groupEnd();
    }

    public function applyGuesthouses(
        BaseBuilder $builder,
        MarketingAnalyticsFilter $filter,
        string $guesthouseColumn = 'rooms.floor',
    ): BaseBuilder {
        $builder->where('reservations.property_id', $filter->propertyId())
            ->where('rooms.property_id', $filter->propertyId());
        if (! $filter->includesAllGuesthouses()) {
            $builder->whereIn($guesthouseColumn, $filter->guesthouses());
        }

        return $builder;
    }

    public function applyValidReservations(
        BaseBuilder $builder,
        string $statusColumn = 'reservations.status',
    ): BaseBuilder {
        return $builder->whereIn($statusColumn, marketing_valid_reservation_statuses());
    }

    public function applyExpenseGuesthouses(
        BaseBuilder $builder,
        MarketingAnalyticsFilter $filter,
    ): BaseBuilder {
        $builder->where('expenses.property_id', $filter->propertyId());
        if ($filter->includesAllGuesthouses()) {
            return $builder;
        }

        $guesthouses = implode(',', $filter->guesthouses());

        return $builder->groupStart()
            ->whereIn('expenses.guesthouse', $filter->guesthouses())
            ->orWhere(
                'EXISTS (SELECT 1 FROM expense_guesthouses eg '
                . 'WHERE eg.expense_id = expenses.id AND eg.guesthouse IN (' . $guesthouses . '))',
                null,
                false,
            )
            ->groupEnd();
    }
}
