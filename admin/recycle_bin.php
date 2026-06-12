<?php
require_once '../includes/config.php';
requireAdmin();

$page_title   = "Recycle Bin — Inventaris Barang";
$current_page = basename(__FILE__);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// ===== PROSES RESTORE (PEMULIHAN) =====
if (isset($_GET['restore'])) {
    $id_hapus = (int) $_GET['restore'];
    
    // Ambil detail barang dari log_penghapusan
    $query_get = "SELECT * FROM log_penghapusan WHERE id_hapus = $id_hapus";
    $result_get = mysqli_query($conn, $query_get);
    
    if (mysqli_num_rows($result_get) == 1) {
        $data = mysqli_fetch_assoc($result_get);
        
        $id_barang   = $data['id_barang_lama'] ? (int) $data['id_barang_lama'] : 'NULL';
        $kode        = mysqli_real_escape_string($conn, $data['kode_barang']);
        $nama        = mysqli_real_escape_string($conn, $data['nama_barang']);
        $id_kategori = $data['id_kategori'] ? (int) $data['id_kategori'] : 'NULL';
        $stok        = (int) $data['stok'];
        $kondisi     = mysqli_real_escape_string($conn, $data['kondisi']);
        $foto        = $data['foto'] ? "'" . mysqli_real_escape_string($conn, $data['foto']) . "'" : "NULL";

        // Cek apakah kode_barang sudah terpakai kembali di tabel barang aktif
        $cek_kode = mysqli_query($conn, "SELECT id_barang FROM barang WHERE kode_barang = '$kode'");
        if (mysqli_num_rows($cek_kode) > 0) {
            $error_message = "Gagal memulihkan! Kode barang '$kode' saat ini sudah terpakai oleh barang aktif lain.";
        } else {
            // Re-insert ke tabel barang
            // Jika id_barang lama ditentukan, kita coba pakai kembali ID tersebut agar relasi log/peminjaman lawas tetap utuh
            $query_restore = "INSERT INTO barang (id_barang, kode_barang, nama_barang, id_kategori, stok, kondisi, foto) 
                              VALUES ($id_barang, '$kode', '$nama', $id_kategori, $stok, '$kondisi', $foto)";
            
            // Jalankan restore
            if (mysqli_query($conn, $query_restore)) {
                // Hapus dari log_penghapusan
                mysqli_query($conn, "DELETE FROM log_penghapusan WHERE id_hapus = $id_hapus");
                header("Location: recycle_bin.php?status=restore");
                exit();
            } else {
                // Jika bentrok PK id_barang, coba restore tanpa menyertakan id_barang (auto_increment baru)
                $query_restore_fallback = "INSERT INTO barang (kode_barang, nama_barang, id_kategori, stok, kondisi, foto) 
                                           VALUES ('$kode', '$nama', $id_kategori, $stok, '$kondisi', $foto)";
                if (mysqli_query($conn, $query_restore_fallback)) {
                    mysqli_query($conn, "DELETE FROM log_penghapusan WHERE id_hapus = $id_hapus");
                    header("Location: recycle_bin.php?status=restore");
                    exit();
                } else {
                    $error_message = "Gagal memulihkan data ke tabel utama.";
                }
            }
        }
    }
}

// ===== PROSES PURGE (HAPUS PERMANEN SATUAN) =====
if (isset($_GET['purge'])) {
    $id_hapus = (int) $_GET['purge'];
    $query_get = "SELECT foto FROM log_penghapusan WHERE id_hapus = $id_hapus";
    $res = mysqli_query($conn, $query_get);
    if (mysqli_num_rows($res) == 1) {
        $row = mysqli_fetch_assoc($res);
        // Hapus foto fisik dari disk jika ada
        if (!empty($row['foto'])) {
            deleteFile($row['foto']);
        }
        // Hapus dari database
        mysqli_query($conn, "DELETE FROM log_penghapusan WHERE id_hapus = $id_hapus");
        header("Location: recycle_bin.php?status=purge");
        exit();
    }
}

// ===== PROSES KOSONGKAN BIN (KOSONGKAN SEMUA) =====
if (isset($_POST['empty_bin'])) {
    $result_all = mysqli_query($conn, "SELECT foto FROM log_penghapusan");
    while ($row = mysqli_fetch_assoc($result_all)) {
        if (!empty($row['foto'])) {
            deleteFile($row['foto']);
        }
    }
    mysqli_query($conn, "TRUNCATE TABLE log_penghapusan");
    header("Location: recycle_bin.php?status=empty");
    exit();
}

// ===== SEARCH & PAGINATION =====
$search = htmlspecialchars(trim($_GET['search'] ?? ''));
$where  = "WHERE 1=1";
if ($search) {
    $where .= " AND (lp.nama_barang LIKE '%$search%' OR lp.kode_barang LIKE '%$search%')";
}

// Pagination
$per_page    = 10;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM log_penghapusan lp $where"))['total'];
$total_pages = ceil($total_rows / $per_page);

$query = "
    SELECT lp.*, k.nama_kategori 
    FROM log_penghapusan lp
    LEFT JOIN kategori k ON lp.id_kategori = k.id_kategori
    $where
    ORDER BY lp.waktu_dihapus DESC 
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
        <h5 class="topbar-title">Recycle Bin</h5>
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
    <?php if (isset($_GET['status'])): ?>
        <?php
        $messages = [
            'restore' => ['success', 'bi-arrow-counterclockwise', 'Barang berhasil dipulihkan ke tabel aktif.'],
            'purge'   => ['warning', 'bi-trash-fill',             'Barang berhasil dihapus secara permanen.'],
            'empty'   => ['danger',  'bi-check-circle-fill',      'Recycle bin berhasil dikosongkan.'],
        ];
        $s = $messages[$_GET['status']] ?? null;
        if ($s): ?>
        <div class="alert alert-<?= $s[0] ?> alert-dismissible d-flex align-items-center gap-2" role="alert">
            <i class="bi <?= $s[1] ?>"></i>
            <span><?= $s[2] ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= $error_message ?></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Toolbar: Search & Kosongkan -->
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
                        <a href="recycle_bin.php" class="btn btn-outline-secondary ms-1">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="col-auto ms-md-auto">
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengosongkan seluruh Recycle Bin secara permanen beserta semua file fotonya?')">
                        <button type="submit" name="empty_bin" class="btn btn-danger">
                            <i class="bi bi-trash3-fill me-1"></i> Kosongkan Recycle Bin
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
            <h6 class="fw-bold mb-0" style="color:#002645;">Data Barang Terhapus (Trigger BEFORE DELETE)</h6>
            <span class="badge bg-danger rounded-pill"><?= $total_rows ?> item</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Waktu Dihapus</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Foto</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kode</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Barang</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kategori</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Stok</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kondisi</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold text-end">Aksi</th>
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
                            <td class="px-4 text-muted small"><?= date('d M Y, H:i:s', strtotime($row['waktu_dihapus'])) ?></td>
                            <td class="px-4">
                                <?php if ($row['foto']): ?>
                                    <img
                                        src="../uploads/barang/<?= htmlspecialchars($row['foto']) ?>"
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
                            <td class="px-4 text-end">
                                <!-- Restore Button -->
                                <a
                                    href="recycle_bin.php?restore=<?= $row['id_hapus'] ?>"
                                    class="btn btn-sm btn-outline-success me-1"
                                    title="Pulihkan Barang"
                                    onclick="return confirm('Pulihkan barang ini ke daftar aktif?')"
                                >
                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                </a>
                                <!-- Purge Button -->
                                <a
                                    href="recycle_bin.php?purge=<?= $row['id_hapus'] ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Hapus Permanen"
                                    onclick="return confirm('Hapus barang ini secara permanen dari database dan disk? Tindakan ini tidak bisa dibatalkan.')"
                                >
                                    <i class="bi bi-trash-fill"></i> Hapus Permanen
                                </a>
                            </td>
                        </tr>
                        <?php endwhile;
                        else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-trash fs-4 d-block mb-2"></i>
                                <?= $search ? 'Tidak ada data terhapus yang cocok.' : 'Recycle Bin kosong.' ?>
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
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> item terhapus
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

    // Auto dismiss alert
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            new bootstrap.Alert(el).close();
        });
    }, 3000);
</script>
</body>
</html>
