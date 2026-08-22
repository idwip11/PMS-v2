<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column gap-3 mb-4">
    <form class="marketing-filter card border-0 shadow-sm" method="get" action="<?= site_url('marketing') ?>" data-marketing-filter>
        <div class="card-body d-flex flex-column flex-lg-row align-items-lg-end gap-3 p-3 flex-wrap">
            <div>
                <label class="form-label small fw-semibold mb-1" for="marketing-year">Tahun</label>
                <select class="form-select" id="marketing-year" name="year">
                    <?php foreach ($availableYears as $year): ?>
                        <option value="<?= $year ?>" <?= $filter->year() === $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1" for="marketing-month">Bulan</label>
                <select class="form-select" id="marketing-month" name="month">
                    <option value="" <?= $filter->month() === null ? 'selected' : '' ?>>Seluruh bulan</option>
                    <?php foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $monthNumber => $monthName): ?>
                        <option value="<?= $monthNumber + 1 ?>" <?= $filter->month() === $monthNumber + 1 ? 'selected' : '' ?>><?= $monthName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <fieldset class="marketing-gh-filter">
                <legend class="form-label small fw-semibold mb-1">Guesthouse</legend>
                <div class="d-flex flex-wrap gap-2">
                    <label class="marketing-filter-choice"><input class="form-check-input" type="checkbox" data-marketing-all-gh <?= $filter->includesAllGuesthouses() ? 'checked' : '' ?>><span>Semua GH</span></label>
                    <?php foreach ($availableGuesthouses as $guesthouse): ?>
                        <label class="marketing-filter-choice"><input class="form-check-input" type="checkbox" name="guesthouses[]" value="<?= $guesthouse ?>" data-marketing-gh <?= in_array($guesthouse, $filter->guesthouses(), true) ? 'checked' : '' ?>><span>GH <?= $guesthouse ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="d-flex gap-2 ms-lg-auto"><a class="btn btn-outline-secondary" href="<?= site_url('marketing') ?>">Reset</a><button class="btn btn-primary" type="submit">Terapkan</button></div>
        </div>
    </form>
</div>

<?= view('components/marketing_analytics_panel', ['filter' => $filter, 'analytics' => $analytics]) ?>
<?= $this->endSection() ?>
