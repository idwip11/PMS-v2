<?php

namespace App\Controllers;

use App\Libraries\MarketingAnalyticsFilter;
use App\Libraries\MarketingAnalyticsService;

class MarketingController extends BaseController
{
    public function index(): string
    {
        $filter = MarketingAnalyticsFilter::fromInput((array) $this->request->getGet());

        return view('pages/marketing', [
            'pageTitle' => 'Analytics',
            'pageDescription' => 'Pantau profil tamu dan performa setiap guesthouse dari data aktual.',
            'filter' => $filter,
            'analytics' => (new MarketingAnalyticsService(db_connect()))->dashboard($filter),
            'availableGuesthouses' => $this->availableGuesthouses(),
            'availableYears' => $this->availableYears($filter->year()),
        ]);
    }

    /** @return list<int> */
    private function availableGuesthouses(): array
    {
        $rows = db_connect()->table('rooms')
            ->select('floor')
            ->where('floor >=', 1)
            ->where('floor <=', 5)
            ->groupBy('floor')
            ->orderBy('floor', 'ASC')
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'floor'));
    }

    /** @return list<int> */
    private function availableYears(int $selectedYear): array
    {
        $rows = db_connect()->table('reservations')
            ->select('YEAR(check_in_date) AS year', false)
            ->groupBy('YEAR(check_in_date)', false)
            ->orderBy('year', 'DESC')
            ->get()
            ->getResultArray();
        $years = array_map('intval', array_column($rows, 'year'));
        $years[] = $selectedYear;
        $years[] = (int) date('Y');
        $years = array_values(array_unique(array_filter($years)));
        rsort($years);

        return $years;
    }
}
