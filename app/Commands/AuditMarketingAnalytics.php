<?php

namespace App\Commands;

use App\Libraries\MarketingAnalyticsAuditService;
use App\Libraries\MarketingAnalyticsFilter;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AuditMarketingAnalytics extends BaseCommand
{
    protected $group = 'Marketing';
    protected $name = 'marketing:analytics-audit';
    protected $description = 'Audit fondasi data analytics marketing berdasarkan periode dan guesthouse.';
    protected $usage = 'marketing:analytics-audit [options]';
    protected $options = [
        '--year' => 'Tahun laporan (default: tahun berjalan).',
        '--month' => 'Bulan 1-12; kosong atau "all" berarti seluruh tahun.',
        '--guesthouses' => 'Daftar GH dipisahkan koma, misalnya 1,2,5; kosong berarti semua.',
        '--json' => 'Keluarkan hasil lengkap dalam format JSON.',
    ];

    public function run(array $params)
    {
        $filter = MarketingAnalyticsFilter::fromInput([
            'year' => CLI::getOption('year'),
            'month' => CLI::getOption('month'),
            'guesthouses' => CLI::getOption('guesthouses'),
        ]);
        $audit = (new MarketingAnalyticsAuditService(db_connect()))->audit($filter);

        if (CLI::getOption('json')) {
            CLI::write((string) json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

            return;
        }

        $guesthouses = $filter->includesAllGuesthouses()
            ? 'Semua GH'
            : 'GH ' . implode(', GH ', $filter->guesthouses());
        CLI::write('Audit Analytics Marketing — ' . $filter->periodLabel() . ' — ' . $guesthouses, 'yellow');
        CLI::newLine();
        CLI::write('Reservasi', 'green');
        CLI::write('  Baris dalam periode : ' . $audit['reservations']['rows_in_period']);
        CLI::write('  Baris valid          : ' . $audit['reservations']['valid_rows']);
        CLI::write('  Booking unik         : ' . $audit['reservations']['unique_booking_groups']);
        CLI::write('  Tamu utama unik      : ' . $audit['reservations']['unique_main_guests']);
        CLI::write('  Rentang tidak valid  : ' . $audit['reservations']['invalid_stay_date_ranges']);
        CLI::write('  Status               : ' . json_encode($audit['reservations']['status_counts'], JSON_UNESCAPED_UNICODE));
        CLI::newLine();
        CLI::write('Kualitas tamu', 'green');
        CLI::write('  Origin kosong/scope  : ' . $audit['origins']['unknown'] . ' (' . number_format($audit['origins']['unknown_percentage'], 2) . '%)');
        CLI::write('  Origin kosong/global : ' . $audit['origins']['all_guest_records_unknown'] . ' (' . number_format($audit['origins']['all_guest_records_unknown_percentage'], 2) . '%)');
        CLI::write('  Duplikat email       : ' . $audit['duplicate_guests']['email_group_count'] . ' grup');
        CLI::write('  Duplikat telepon     : ' . $audit['duplicate_guests']['phone_group_count'] . ' grup');
        CLI::newLine();
        CLI::write('Pembayaran diterima', 'green');
        CLI::write('  Baris paid           : ' . $audit['payments']['paid_rows_in_period']);
        CLI::write('  Booking unik         : ' . $audit['payments']['booking_group_count']);
        CLI::write('  Total diterima       : Rp' . number_format($audit['payments']['paid_amount_in_period'], 0, ',', '.'));
        CLI::newLine();
        CLI::write('Expense', 'green');
        CLI::write('  Baris dalam periode  : ' . $audit['expenses']['rows_in_period']);
        CLI::write('  Tanpa GH             : ' . $audit['expenses']['without_guesthouse']);
        CLI::write('  Satu GH              : ' . $audit['expenses']['single_guesthouse']);
        CLI::write('  Multi-GH             : ' . $audit['expenses']['multiple_guesthouses']);
        CLI::write('  Mismatch legacy      : ' . $audit['expenses']['legacy_assignment_mismatches']);
        CLI::newLine();
        CLI::write('Rekonsiliasi dashboard Tahap 1', 'green');
        CLI::write('  Tamu unik cocok      : ' . ($audit['stage_one_dashboard']['reconciles_with_audit']['unique_guests'] ? 'YA' : 'TIDAK'));
        CLI::write('  Booking unik cocok   : ' . ($audit['stage_one_dashboard']['reconciles_with_audit']['unique_bookings'] ? 'YA' : 'TIDAK'));
        $topGuest = $audit['stage_one_dashboard']['loyal_guests'][0] ?? null;
        CLI::write('  Top loyal            : ' . ($topGuest === null ? '-' : $topGuest['full_name'] . ' (' . $topGuest['booking_count'] . ' booking)'));
        CLI::newLine();
        CLI::write('Rekonsiliasi dashboard Tahap 2', 'green');
        CLI::write('  Profit = revenue-cost: ' . ($audit['stage_two_dashboard']['reconciles_with_audit']['profit_equals_revenue_less_cost'] ? 'YA' : 'TIDAK'));
        $costReconciles = $audit['stage_two_dashboard']['reconciles_with_audit']['all_cost_allocated_once'];
        CLI::write('  Cost dialokasikan sekali: ' . ($costReconciles === null ? 'N/A (filter GH)' : ($costReconciles ? 'YA' : 'TIDAK')));
        CLI::newLine();
        CLI::write('Rekonsiliasi dashboard Tahap 3', 'green');
        $occupancy = $audit['stage_three_dashboard']['occupancy']['summary'];
        CLI::write('  Kamar-malam terjual  : ' . number_format($occupancy['room_nights_sold'], 0, ',', '.'));
        CLI::write('  Kamar-malam tersedia : ' . number_format($occupancy['room_nights_available'], 0, ',', '.'));
        CLI::write('  Okupansi weighted     : ' . number_format($occupancy['occupancy'], 2, ',', '.') . '%');
        CLI::write('  Rumus titik cocok     : ' . ($audit['stage_three_dashboard']['reconciles_with_audit']['point_formula'] ? 'YA' : 'TIDAK'));
        CLI::write('  Rumus weighted cocok  : ' . ($audit['stage_three_dashboard']['reconciles_with_audit']['weighted_formula'] ? 'YA' : 'TIDAK'));
        CLI::newLine();
        CLI::write('Rekonsiliasi dashboard Tahap 4', 'green');
        $annual = $audit['stage_four_dashboard']['annual_summary']['selected'];
        CLI::write('  Tahun ringkasan       : ' . $annual['year']);
        CLI::write('  Okupansi tahunan      : ' . number_format($annual['occupancy'], 2, ',', '.') . '%');
        CLI::write('  YoY kamar-malam       : ' . ($annual['yoy_room_nights'] === null ? 'N/A' : number_format($annual['yoy_room_nights'], 2, ',', '.') . '%'));
        CLI::write('  Rumus weighted cocok  : ' . ($audit['stage_four_dashboard']['reconciles_with_audit']['weighted_formula'] ? 'YA' : 'TIDAK'));
        CLI::write('  Rumus YoY cocok       : ' . ($audit['stage_four_dashboard']['reconciles_with_audit']['yoy_formula'] ? 'YA' : 'TIDAK'));
        CLI::newLine();
        CLI::write('Rekonsiliasi dashboard Tahap 5', 'green');
        $forecast = $audit['stage_five_dashboard']['forecast'];
        CLI::write('  Histori lengkap       : ' . $forecast['complete_months'] . '/' . $forecast['required_months'] . ' bulan');
        CLI::write('  Forecast aktif        : ' . ($forecast['enabled'] ? 'YA (' . $forecast['quality'] . ')' : 'TIDAK'));
        CLI::write('  Minimum dipatuhi      : ' . ($audit['stage_five_dashboard']['reconciles_with_audit']['minimum_history_enforced'] ? 'YA' : 'TIDAK'));
        CLI::write('  Horizon 6 bulan       : ' . ($audit['stage_five_dashboard']['reconciles_with_audit']['horizon_is_six_months'] ? 'YA' : 'TIDAK'));
        CLI::write('  Confidence band valid : ' . ($audit['stage_five_dashboard']['reconciles_with_audit']['bounds_are_ordered'] ? 'YA' : 'TIDAK'));
        CLI::newLine();
        CLI::write('Gunakan --json untuk hasil lengkap dan rekonsiliasi mesin.', 'light_gray');
    }
}
