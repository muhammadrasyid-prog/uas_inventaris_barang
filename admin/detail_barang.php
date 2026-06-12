<?php
require_once '../includes/config.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "
    SELECT b.*,
           k.nama_kategori,
           (b.stok - COUNT(CASE WHEN p.status = 'dipinjam' THEN 1 END)) AS stok_tersedia,
           cek_status_stok(b.stok - COUNT(CASE WHEN p.status = 'dipinjam' THEN 1 END)) AS status_stok
    FROM barang b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    LEFT JOIN peminjaman p ON b.id_barang = p.id_barang
    WHERE b.id_barang = ?
    GROUP BY b.id_barang
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$barang = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$barang) {
    header("Location: barang.php?status=error&msg=" . urlencode("Barang tidak ditemukan."));
    exit();
}

// Riwayat peminjaman barang ini (untuk konteks tambahan di halaman detail)
$stmt2 = mysqli_prepare($conn, "
    SELECT p.*, u.nama_lengkap
    FROM peminjaman p
    LEFT JOIN users u ON p.id_user = u.id_user
    WHERE p.id_barang = ?
    ORDER BY p.tanggal_pinjam DESC
    LIMIT 10
");
mysqli_stmt_bind_param($stmt2, "i", $id);
mysqli_stmt_execute($stmt2);
$riwayat = mysqli_stmt_get_result($stmt2);

$page_title   = "Detail Barang — " . htmlspecialchars($barang['nama_barang']);
$current_page = "barang.php"; // tetap highlight menu "Data Barang" di sidebar
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$badge_color = match ($barang['status_stok']) {
    'Habis'   => 'danger',
    'Menipis' => 'warning',
    default   => 'success'
};
$kondisi_color = match ($barang['kondisi']) {
    'Rusak Ringan' => 'warning',
    'Rusak Berat'  => 'danger',
    default        => 'success'
};
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Detail Barang</h5>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
        </span>
    </div>
</header>

<!-- Content -->
<div class="p-4">

    <a href="barang.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Barang
    </a>

    <div class="row g-4">
        <!-- Foto + info utama -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <?php if ($barang['foto']): ?>
                        <img src="../uploads/barang/<?= htmlspecialchars($barang['foto']) ?>"
                             alt="<?= htmlspecialchars($barang['nama_barang']) ?>"
                             class="rounded mb-3"
                             style="width:100%; max-width:240px; height:240px; object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded bg-light d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width:240px; height:240px;">
                            <i class="bi bi-image text-muted fs-1"></i>
                        </div>
                    <?php endif; ?>

                    <h5 class="fw-bold mb-1" style="color:#002645;"><?= htmlspecialchars($barang['nama_barang']) ?></h5>
                    <code class="fs-6"><?= htmlspecialchars($barang['kode_barang']) ?></code>
                </div>
            </div>
        </div>

        <!-- Detail informasi -->
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0" style="color:#002645;">Informasi Barang</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="text-muted" style="width:180px;">Kategori</th>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">
                                        <?= htmlspecialchars($barang['nama_kategori'] ?? '-') ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Total Stok</th>
                                <td><?= (int) $barang['stok'] ?> unit</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Stok Tersedia</th>
                                <td>
                                    <span class="badge bg-<?= $badge_color ?> bg-opacity-10 text-<?= $badge_color ?> rounded-pill px-3">
                                        <?= (int) $barang['stok_tersedia'] ?> — <?= htmlspecialchars($barang['status_stok']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">Kondisi</th>
                                <td>
                                    <span class="badge bg-<?= $kondisi_color ?> bg-opacity-10 text-<?= $kondisi_color ?> rounded-pill px-3">
                                        <?= htmlspecialchars($barang['kondisi']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">ID Barang</th>
                                <td>#<?= (int) $barang['id_barang'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Riwayat Peminjaman -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0" style="color:#002645;">Riwayat Peminjaman Terakhir</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3 py-2 small text-uppercase text-muted fw-semibold">Peminjam</th>
                                    <th class="px-3 py-2 small text-uppercase text-muted fw-semibold">Tgl Pinjam</th>
                                    <th class="px-3 py-2 small text-uppercase text-muted fw-semibold">Tgl Kembali</th>
                                    <th class="px-3 py-2 small text-uppercase text-muted fw-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($riwayat) > 0): ?>
                                    <?php while ($r = mysqli_fetch_assoc($riwayat)): ?>
                                        <tr>
                                            <td class="px-3"><?= htmlspecialchars($r['nama_lengkap'] ?? '-') ?></td>
                                            <td class="px-3"><?= htmlspecialchars($r['tanggal_pinjam'] ?? '-') ?></td>
                                            <td class="px-3"><?= htmlspecialchars($r['tanggal_kembali_aktual'] ?? $r['tanggal_kembali_rencana'] ?? '-') ?></td>
                                            <td class="px-3">
                                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-3">
                                                    <?= htmlspecialchars($r['status'] ?? '-') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                            Belum ada riwayat peminjaman untuk barang ini.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- end #main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
        document.getElementById('sidebar-backdrop').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('show');
        document.getElementById('sidebar-backdrop').classList.remove('show');
    }
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) closeSidebar();
    });
</script>
</body>
</html>
<?php require_once '../includes/footer.php'; ?>