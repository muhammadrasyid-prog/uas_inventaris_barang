<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
requireAdmin();

$page_title   = "Log Perubahan";
$current_page = basename(__FILE__);

//proses reset log
if (isset($_POST['reset_log'])) {
    // Coba dengan DELETE dulu (lebih aman untuk foreign key)
    $delete = mysqli_query($conn, "DELETE FROM log_perubahan");
    
    if (!$delete) {
        // Kalau gagal, tampilkan errornya
        die("Error menghapus log: " . mysqli_error($conn));
    }
    
    // Reset auto increment
    mysqli_query($conn, "ALTER TABLE log_perubahan AUTO_INCREMENT = 1");
    
    header("Location: log_perubahan.php?status=reset");
    exit();
}

// search
$search = htmlspecialchars(trim($_GET['search'] ?? ''));
$where  = "WHERE 1=1";
if ($search) {
    $search_like = mysqli_real_escape_string($conn, $search);
    $where .= " AND (l.nama_barang LIKE '%$search_like%' OR l.kode_barang LIKE '%$search_like%')";
}

// Pagination
$per_page    = 10;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

$total_query = "SELECT COUNT(*) as total FROM log_perubahan l $where";
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, $total_query))['total'];
$total_pages = ceil($total_rows / $per_page);

// Query kompleks JOIN log_perubahan & kategori
$query = "
    SELECT l.*, k.nama_kategori 
    FROM log_perubahan l
    LEFT JOIN kategori k ON l.id_kategori = k.id_kategori
    $where
    ORDER BY l.waktu_perubahan DESC 
    LIMIT $per_page OFFSET $offset
";
$result = mysqli_query($conn, $query);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Log Perubahan</h5>
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

    <!-- Alert notifikasi -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'reset'): ?>
        <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle"></i>
            <span>Seluruh data log perubahan berhasil dihapus.</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Toolbar: Search & Reset -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="search"
                               placeholder="Cari kode atau nama barang..." value="<?= $search ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if ($search): ?>
                        <a href="log_perubahan.php" class="btn btn-outline-secondary ms-1">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($total_rows > 0): ?>
                <div class="col-auto ms-md-auto">
                    <form method="POST" onsubmit="return confirm('Kosongkan seluruh log riwayat perubahan? Tindakan ini tidak bisa dibatalkan.')">
                        <button type="submit" name="reset_log" class="btn btn-outline-danger">
                            <i class="bi bi-trash me-1"></i> Bersihkan Log (<?= $total_rows ?> data)
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color:#002645;">Riwayat Perubahan Data Barang (Trigger BEFORE UPDATE)</h6>
            <span class="badge bg-primary rounded-pill"><?= $total_rows ?> log</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Waktu</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Foto</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kode</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Barang</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kategori</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Stok</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0):
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($result)): 
                                $kondisi_color = match ($row['kondisi']) {
                                    'Rusak Ringan' => 'warning',
                                    'Rusak Berat'  => 'danger',
                                    default        => 'success'
                                };
                        ?>
                        <tr>
                            <td class="px-4"><?= $no++ ?></td>
                            <td class="px-4 text-muted small"><?= date('d M Y, H:i:s', strtotime($row['waktu_perubahan'])) ?></td>
                            <td class="px-4">
                                <?php if ($row['foto'] && file_exists("../uploads/barang/" . $row['foto'])): ?>
                                    <img src="../uploads/barang/<?= htmlspecialchars($row['foto']) ?>"
                                         alt="<?= htmlspecialchars($row['nama_barang']) ?>"
                                         class="rounded border"
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
                            <td class="px-4">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">
                                    <?= htmlspecialchars($row['nama_kategori'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-4"><?= $row['stok'] ?></td>
                            <td class="px-4">
                                <span class="badge bg-<?= $kondisi_color ?> bg-opacity-10 text-<?= $kondisi_color ?> rounded-pill px-3">
                                    <?= $row['kondisi'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-clock-history fs-4 d-block mb-2"></i>
                                <?= $search ? 'Tidak ada log yang cocok.' : 'Belum ada log aktivitas perubahan data.' ?>
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
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> log
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= $search ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= $search ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= $search ?>">
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

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            new bootstrap.Alert(el).close();
        });
    }, 3000);
</script>
</body>
</html>