<?php
require_once '../includes/config.php';
requireLogin();

if ($_SESSION['role'] !== 'peminjam') {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title   = "Riwayat Pinjam — Inventaris Barang";
$current_page = basename(__FILE__);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$user_id = (int) $_SESSION['user_id'];

// SEARCH & PAGINATION
$search = htmlspecialchars(trim($_GET['search'] ?? ''));
$where  = "WHERE p.id_user = $user_id";
if ($search) {
    $where .= " AND (b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%')";
}

// Pagination
$per_page    = 10;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM peminjaman p 
    JOIN barang b ON p.id_barang = b.id_barang 
    $where
"))['total'];
$total_pages = ceil($total_rows / $per_page);

// Query kompleks JOIN peminjaman & barang dengan logic CASE
$query = "
    SELECT p.*, b.nama_barang, b.kode_barang, b.foto,
           CASE
               WHEN p.status = 'dikembalikan' AND p.tanggal_kembali_aktual <= p.tanggal_kembali_rencana THEN 'Tepat Waktu'
               WHEN p.status = 'dikembalikan' AND p.tanggal_kembali_aktual > p.tanggal_kembali_rencana THEN 'Terlambat'
               WHEN p.status = 'dipinjam' AND p.tanggal_kembali_rencana < CURDATE() THEN 'Terlambat'
               ELSE 'Aman'
           END AS keterangan_waktu
    FROM peminjaman p
    JOIN barang b ON p.id_barang = b.id_barang
    $where
    ORDER BY p.id_peminjaman DESC
    LIMIT $per_page OFFSET $offset
";
$result = mysqli_query($conn, $query);
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Riwayat Pinjam</h5>
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

    <!-- Alert Modal -->
    <?php
    $modal_data = null;
    if (isset($_GET['status']) && $_GET['status'] === 'pinjam_sukses') {
        $modal_data = ['success', 'bi-check-circle-fill', 'Peminjaman berhasil diajukan! Silakan ambil barang di ruang inventaris.'];
    }
    ?>
    <?php include_once '../includes/alert_modal.php'; ?>

    <!-- Toolbar: Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input
                            type="text"
                            class="form-control border-start-0"
                            name="search"
                            placeholder="Cari kode atau nama barang..."
                            value="<?= $search ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if ($search): ?>
                        <a href="riwayat.php" class="btn btn-outline-secondary ms-1">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Data Riwayat -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color: #002645;">Daftar Transaksi Peminjaman Anda</h6>
            <span class="badge bg-primary rounded-pill"><?= $total_rows ?> transaksi</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Foto</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kode</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Barang</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Tgl Pinjam</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Deadline Kembali</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Tgl Pengembalian</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Status</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0):
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($result)): 
                                $status_badge = $row['status'] === 'dikembalikan' ? 'success' : 'primary';
                                
                                $ket_badge = match ($row['keterangan_waktu']) {
                                    'Terlambat' => 'danger',
                                    'Tepat Waktu' => 'success',
                                    default => 'secondary'
                                };
                        ?>
                        <tr>
                            <td class="px-4"><?= $no++ ?></td>
                            <td class="px-4">
                                <?php if ($row['foto']): ?>
                                    <img
                                        src="../uploads/barang/<?= htmlspecialchars($row['foto']) ?>"
                                        alt="<?= htmlspecialchars($row['nama_barang']) ?>"
                                        class="rounded"
                                        style="width:40px; height:40px; object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded bg-light border d-flex align-items-center justify-content-center"
                                        style="width:40px; height:40px;">
                                        <i class="bi bi-image text-muted" style="font-size: 12px;"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4"><code><?= htmlspecialchars($row['kode_barang']) ?></code></td>
                            <td class="px-4 fw-semibold"><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td class="px-4 text-muted small"><?= date('d M Y', strtotime($row['tanggal_pinjam'])) ?></td>
                            <td class="px-4 text-muted small"><?= date('d M Y', strtotime($row['tanggal_kembali_rencana'])) ?></td>
                            <td class="px-4 text-muted small fw-semibold">
                                <?= $row['tanggal_kembali_aktual'] ? date('d M Y', strtotime($row['tanggal_kembali_aktual'])) : '-' ?>
                            </td>
                            <td class="px-4">
                                <span class="badge bg-<?= $status_badge ?> bg-opacity-10 text-<?= $status_badge ?> rounded-pill px-3">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-4">
                                <span class="badge bg-<?= $ket_badge ?> bg-opacity-10 text-<?= $ket_badge ?> rounded-pill px-3">
                                    <?= $row['keterangan_waktu'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile;
                        else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-clock-history fs-4 d-block mb-2"></i>
                                <?= $search ? 'Tidak ada riwayat pinjam yang cocok.' : 'Belum ada riwayat transaksi peminjaman.' ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3 px-4">
                <small class="text-muted">
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> transaksi
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= $search ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= $search ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= $search ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<footer class="mt-auto p-4 text-center border-top">
    <small class="text-muted">© 2026 Sistem Inventaris Barang</small>
</footer>

</div><!-- end #main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle sidebar mobile
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
