<?php
require_once '../includes/config.php';
requireAdmin();

$page_title = "Dashboard — Inventaris Barang";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// ===== QUERY STATISTIK =====

// Total barang
$total_barang = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as total FROM barang")
)['total'];

// Total kategori
$total_kategori = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as total FROM kategori")
)['total'];

// Peminjaman aktif
$total_pinjam = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'dipinjam'")
)['total'];

// Stok menipis (stok <= 3)
$stok_menipis = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) as total FROM barang WHERE stok <= 3")
)['total'];

// Tabel peminjaman aktif — query JOIN kompleks
$query_pinjam = "
    SELECT p.id_peminjaman, u.nama_lengkap, b.nama_barang,
           p.tanggal_pinjam, p.tanggal_kembali_rencana,
           CASE
               WHEN p.tanggal_kembali_rencana < CURDATE() THEN 'Terlambat'
               ELSE 'Tepat Waktu'
           END AS keterangan_waktu
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    JOIN barang b ON p.id_barang = b.id_barang
    WHERE p.status = 'dipinjam'
    ORDER BY p.tanggal_kembali_rencana ASC
    LIMIT 5
";
$result_pinjam = mysqli_query($conn, $query_pinjam);
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Dashboard</h5>
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

    <!-- Alert Modal Login Success -->
    <?php
    $modal_data = null;
    if (isset($_GET['login']) && $_GET['login'] === 'success') {
        $modal_data = ['success', 'bi-person-check-fill', 'Selamat datang kembali, ' . htmlspecialchars($_SESSION['nama_lengkap']) . '! Anda berhasil login sebagai Administrator.'];
    }
    ?>
    <?php include_once '../includes/alert_modal.php'; ?>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="p-2 rounded-3 bg-primary bg-opacity-10">
                            <i class="bi bi-box-seam text-primary fs-5"></i>
                        </div>
                    </div>
                    <p class="text-muted small text-uppercase fw-semibold mb-1" style="font-size:11px; letter-spacing:.05em;">Total Barang</p>
                    <h3 class="fw-bold mb-0" style="color:#002645;"><?= $total_barang ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="p-2 rounded-3" style="background:rgba(31,98,152,0.1);">
                            <i class="bi bi-tag fs-5" style="color:#1f6298;"></i>
                        </div>
                    </div>
                    <p class="text-muted small text-uppercase fw-semibold mb-1" style="font-size:11px; letter-spacing:.05em;">Total Kategori</p>
                    <h3 class="fw-bold mb-0" style="color:#002645;"><?= $total_kategori ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="p-2 rounded-3 bg-success bg-opacity-10">
                            <i class="bi bi-clipboard-check text-success fs-5"></i>
                        </div>
                    </div>
                    <p class="text-muted small text-uppercase fw-semibold mb-1" style="font-size:11px; letter-spacing:.05em;">Peminjaman Aktif</p>
                    <h3 class="fw-bold mb-0" style="color:#002645;"><?= $total_pinjam ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="p-2 rounded-3 bg-danger bg-opacity-10">
                            <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
                        </div>
                    </div>
                    <p class="text-muted small text-uppercase fw-semibold mb-1" style="font-size:11px; letter-spacing:.05em;">Stok Menipis</p>
                    <h3 class="fw-bold mb-0" style="color:#002645;"><?= $stok_menipis ?></h3>
                </div>
            </div>
        </div>

    </div>

    <!-- Tabel Peminjaman Aktif -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
            <h6 class="fw-bold mb-0" style="color:#002645;">Peminjaman Aktif Terkini</h6>
            <a href="peminjaman.php" class="btn btn-sm btn-outline-primary">
                Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Peminjam</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Barang</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Tgl Pinjam</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Tenggat</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_pinjam) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result_pinjam)): ?>
                                <tr>
                                    <td class="px-4"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td class="px-4"><?= htmlspecialchars($row['nama_barang']) ?></td>
                                    <td class="px-4"><?= date('d M Y', strtotime($row['tanggal_pinjam'])) ?></td>
                                    <td class="px-4"><?= date('d M Y', strtotime($row['tanggal_kembali_rencana'])) ?></td>
                                    <td class="px-4">
                                        <?php if ($row['keterangan_waktu'] === 'Terlambat'): ?>
                                            <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger px-3 py-2">
                                                <i class="bi bi-clock me-1"></i>Terlambat
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-success bg-opacity-10 text-success px-3 py-2">
                                                <i class="bi bi-check-circle me-1"></i>Tepat Waktu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                    Tidak ada peminjaman aktif
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>