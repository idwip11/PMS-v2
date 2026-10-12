<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $currentRole = (string) session('role'); ?>

<div class="alert alert-primary border-0 shadow-sm mb-4">
    <strong>Role Anda: <?= esc($currentRole ?: '-') ?></strong>
    <span class="ms-2">Gunakan halaman ini sebagai panduan cepat saat mengoperasikan HomiQ PMS.</span>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">Panduan Kerja Singkat</h3>
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0 d-flex gap-3">
                        <span class="badge text-bg-primary rounded-pill align-self-start">1</span>
                        <div><strong>New Booking</strong><div class="text-secondary small">Pilih atau ketik data tamu, tentukan tanggal, kamar, sumber, layanan, dan pembayaran awal. Klik hasil nama tamu agar data lama terisi otomatis.</div></div>
                    </div>
                    <div class="list-group-item px-0 d-flex gap-3">
                        <span class="badge text-bg-primary rounded-pill align-self-start">2</span>
                        <div><strong>Room Map</strong><div class="text-secondary small">Gunakan untuk melihat ketersediaan kamar berdasarkan tanggal. Klik booking untuk membuka detail tamu dan tindakan reservasi.</div></div>
                    </div>
                    <div class="list-group-item px-0 d-flex gap-3">
                        <span class="badge text-bg-primary rounded-pill align-self-start">3</span>
                        <div><strong>Reservasi</strong><div class="text-secondary small">Gunakan filter GH, status, nama, dan tanggal. Dari Detail tersedia perubahan harga kamar, layanan, pembayaran, status, serta invoice Detail/Ringkas.</div></div>
                    </div>
                    <div class="list-group-item px-0 d-flex gap-3">
                        <span class="badge text-bg-primary rounded-pill align-self-start">4</span>
                        <div><strong>Finance & Export</strong><div class="text-secondary small">Catat pembayaran dan pengeluaran sesuai bukti. Gunakan Export Excel untuk laporan yang perlu diolah atau disimpan.</div></div>
                    </div>
                    <div class="list-group-item px-0 d-flex gap-3">
                        <span class="badge text-bg-primary rounded-pill align-self-start">5</span>
                        <div><strong>Housekeeping</strong><div class="text-secondary small">Perbarui status kebersihan kamar dan selesaikan cleaning task agar Room Map menunjukkan kondisi terbaru.</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">Status Reservasi</h3>
                <div class="small">
                    <p class="mb-2"><span class="badge text-bg-primary me-2">Confirmed</span>Booking sudah dibuat dan menunggu kedatangan tamu.</p>
                    <p class="mb-2"><span class="badge text-bg-warning me-2">Checked In</span>Tamu sudah masuk dan sedang menginap.</p>
                    <p class="mb-2"><span class="badge text-bg-success me-2">Checked Out</span>Tamu sudah keluar dan tagihan harus sudah lunas.</p>
                    <p class="mb-2"><span class="badge text-bg-secondary me-2">Cancelled</span>Booking atau kamar dibatalkan.</p>
                    <p class="mb-0"><span class="badge text-bg-dark me-2">No-Show</span>Tamu tidak datang; pembayaran yang sudah diterima tetap tercatat sebagai pendapatan.</p>
                </div>
                <hr>
                <h4 class="h6">Ketentuan penting</h4>
                <ul class="small text-secondary mb-0 ps-3">
                    <li>Check-out tidak dapat dilakukan bila masih ada sisa pelunasan.</li>
                    <li>Kesalahan Check-out dapat dikembalikan ke Check-in dari Detail Booking.</li>
                    <li>Data tamu yang sudah memiliki histori reservasi tidak dapat dihapus.</li>
                    <li>Gunakan Cetak Ringkas untuk rombongan agar daftar kamar tidak memanjang.</li>
                    <li>Jangan membagikan akun atau menghapus bukti pembayaran/KTP dari aplikasi.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-1">Peran dan Akses Pengguna</h3>
        <p class="text-secondary small mb-3">Akses menu dibatasi otomatis berdasarkan role. Hak aksi tertentu di dalam menu dapat lebih sempit daripada hak melihat menu.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Role</th><th>Akses utama</th></tr></thead>
                <tbody>
                    <tr><td class="fw-semibold">Atmin</td><td>Superuser internal: semua menu, termasuk Settings dan Support.</td></tr>
                    <tr><td class="fw-semibold">Admin</td><td>Dashboard, Analytics, Operasional, Front Desk, Housekeeping, Settings (dapat menambah user termasuk role Manager, namun tidak bisa mengedit atau menghapus user Manager), dan Support. Nilai finansial Dashboard disensor.</td></tr>
                    <tr><td class="fw-semibold">Manager</td><td>Semua menu: Dashboard, Finance, Analytics, Operasional, Front Desk, Housekeeping, Settings (akses penuh kelola user: tambah, edit, dan hapus semua user), dan Support.</td></tr>
                    <tr><td class="fw-semibold">Finance</td><td>Dashboard, Finance, Analytics, Operasional, Front Desk, Housekeeping, dan Support.</td></tr>
                    <tr><td class="fw-semibold">Operational</td><td>Dashboard, Finance, Analytics, Operasional, Front Desk, Housekeeping, dan Support.</td></tr>
                    <tr><td class="fw-semibold">Marketing</td><td>Dashboard, Analytics, Front Desk, Housekeeping, dan Support.</td></tr>
                    <tr><td class="fw-semibold">Staff</td><td>Dashboard, Analytics, Front Desk, Housekeeping, dan Support. Nilai finansial Dashboard disensor.</td></tr>
                    <tr><td class="fw-semibold">Owner GH 1–5</td><td>Finance → Keuangan Owner/Investor untuk guesthouse (HomiQ) miliknya saja. Tidak dapat membuka menu operasional umum.</td></tr>
                    <tr><td class="fw-semibold">Owner Mitra</td><td>Finance → Keuangan Owner/Investor gabungan untuk Homestay Mitra &amp; Kos Mitra. Tidak dapat membuka menu operasional umum atau setup owner.</td></tr>
                    <tr><td class="fw-semibold text-muted">Owner HS / Kos (Legacy)</td><td class="text-muted">Akses legacy single-properti mitra; disarankan diperbarui ke Owner Mitra berpasangan.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3 p-3 bg-light rounded-2 border border-light-subtle small">
            <div class="fw-semibold text-dark mb-1">Ketentuan Khusus Manajemen User (Menu Settings):</div>
            <ul class="text-secondary mb-0 ps-3">
                <li><strong>Role Manager:</strong> Memiliki akses penuh ke seluruh menu termasuk menu Settings. Dapat menambah user baru, serta bebas mengedit dan menghapus akun pengguna mana pun (termasuk sesama user ber-role Manager).</li>
                <li><strong>Role Admin:</strong> Dapat mengakses menu Settings dan membuat user baru (termasuk membuat user ber-role Manager), namun <strong>tidak dapat mengedit maupun menghapus</strong> akun pengguna yang memiliki role Manager.</li>
            </ul>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h3 class="h5 mb-1">Aturan Bagi Hasil per Properti</h3>
        <p class="text-secondary small mb-3">Persentase Owner diatur di Finance → Setup Owner. Sisa persentasenya menjadi fee kerja sama pengelola.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Properti</th><th>Dasar bagi hasil</th><th>Pendapatan Extra Bed</th></tr></thead>
                <tbody>
                    <tr><td class="fw-semibold">Adiputra Guesthouse by HomiQ</td><td>Laba bersih (total pendapatan dikurangi pengeluaran operasional). Bila laba nol/negatif, bagi hasil Rp0.</td><td>Hak pengelola, tidak masuk bagi hasil owner.</td></tr>
                    <tr><td class="fw-semibold">Homestay Mitra</td><td><strong>Omzet eligible gabungan</strong> Homestay + Kos. Pengeluaran tetap dicatat di Dashboard dan laporan, tetapi tidak mengurangi bagi hasil.</td><td>Dasar owner gabungan.</td></tr>
                    <tr><td class="fw-semibold">Kos Mitra</td><td><strong>Omzet eligible gabungan</strong> Homestay + Kos. Pengeluaran tetap dicatat di Dashboard dan laporan, tetapi tidak mengurangi bagi hasil.</td><td>Dasar owner gabungan.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3 p-3 bg-light rounded-2 border border-light-subtle small text-secondary">
            <strong class="text-dark">Contoh (70% owner / 30% pengelola):</strong> omset Rp1.000.000, pengeluaran Rp500.000.
            HomiQ → dasar Rp500.000 (owner Rp350.000, pengelola Rp150.000).
            Mitra → dasar Rp1.000.000 (owner Rp700.000, pengelola Rp300.000).
        </div>
        <ul class="small text-secondary mb-0 mt-3 ps-3">
            <li>Extra Bed / Biaya Extra Bed Homestay dan Kos menjadi dasar owner gabungan Mitra. Breakfast, Laundry, Massage, dan Sewa Kendaraan menjadi hak pengelola.</li>
            <li>Homestay Mitra disewakan per rumah: kolom reservasi tampil sebagai <strong>Homestay: Mitra</strong>, bukan nomor kamar.</li>
        </ul>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">Arti Data Keuangan</h3>
                <dl class="row small mb-0">
                    <dt class="col-5">Biaya Menginap</dt><dd class="col-7 text-secondary">Total harga kamar sesuai malam menginap.</dd>
                    <dt class="col-5">Biaya Layanan</dt><dd class="col-7 text-secondary">Breakfast, Extra Bed, Laundry, Massage, Sewa Kendaraan, dan layanan tambahan lain.</dd>
                    <dt class="col-5">Terbayar</dt><dd class="col-7 text-secondary">Total pembayaran yang sudah dicatat.</dd>
                    <dt class="col-5">Sisa Pelunasan</dt><dd class="col-7 text-secondary">Total tagihan dikurangi pembayaran.</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">Status Sistem Saat Ini</h3>
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary fw-normal">Environment</dt><dd class="col-7 fw-semibold"><?= esc($status['environment']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">PHP</dt><dd class="col-7 fw-semibold"><?= esc($status['phpVersion']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Database</dt><dd class="col-7 fw-semibold"><?= esc($status['database']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Users</dt><dd class="col-7 fw-semibold"><?= esc($status['users']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Rooms</dt><dd class="col-7 fw-semibold"><?= esc($status['rooms']) ?></dd>
                    <dt class="col-5 text-secondary fw-normal">Reservations</dt><dd class="col-7 fw-semibold"><?= esc($status['reservations']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
