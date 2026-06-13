<?php
require_once '../includes/config.php';
requireAdmin();

$id_log = (int) ($_GET['id'] ?? 0);

// Fetch data log_perubahan (data lama)
$query_log = "
    SELECT l.*, k.nama_kategori AS nama_kategori_lama
    FROM log_perubahan l
    LEFT JOIN kategori k ON l.id_kategori = k.id_kategori
    WHERE l.id_log = $id_log
";
$log_data = mysqli_fetch_assoc(mysqli_query($conn, $query_log));

if (!$log_data) {
    header("Location: log_perubahan.php?status=error&msg=" . urlencode("Log perubahan tidak ditemukan."));
    exit();
}

$id_barang = (int) $log_data['id_barang'];

// Fetch data barang saat ini (data baru)
$query_barang = "
    SELECT b.*, k.nama_kategori AS nama_kategori_baru
    FROM barang b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    WHERE b.id_barang = $id_barang
";
$barang_data = mysqli_fetch_assoc(mysqli_query($conn, $query_barang));

$barang_deleted = false;
$barang_purged = false;

if (!$barang_data) {
    // Cek apakah barang ada di recycle bin (log_penghapusan)
    $query_deleted = "
        SELECT lp.*, k.nama_kategori AS nama_kategori_baru
        FROM log_penghapusan lp
        LEFT JOIN kategori k ON lp.id_kategori = k.id_kategori
        WHERE lp.id_barang_lama = $id_barang
    ";
    $barang_data = mysqli_fetch_assoc(mysqli_query($conn, $query_deleted));
    if ($barang_data) {
        $barang_deleted = true;
    } else {
        $barang_purged = true;
    }
}

$page_title   = "Detail Log Perubahan — #" . $id_log;
$current_page = "log_perubahan.php";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Detail Log Perubahan</h5>
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
    <a href="log_perubahan.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Log Perubahan
    </a>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color:#002645;">
                <i class="bi bi-clock-history me-1 text-primary"></i>
                Detail Log Perubahan #<?= $id_log ?>
            </h6>
            <span class="text-muted small">
                Waktu Perubahan: <strong><?= date('d M Y, H:i:s', strtotime($log_data['waktu_perubahan'])) ?></strong>
            </span>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Status Barang Saat Ini Banner -->
                <div class="col-12">
                    <?php if ($barang_purged): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-0 border-0 bg-opacity-10 text-danger bg-danger">
                            <i class="bi bi-trash-fill fs-5 me-2"></i>
                            <div>
                                <strong>Barang telah dihapus permanen!</strong> Data saat ini tidak lagi tersedia di database.
                            </div>
                        </div>
                    <?php elseif ($barang_deleted): ?>
                        <div class="alert alert-warning d-flex align-items-center mb-0 border-0 bg-opacity-10 text-warning bg-warning">
                            <i class="bi bi-recycle fs-5 me-2"></i>
                            <div>
                                <strong>Barang berada di Recycle Bin!</strong> Anda dapat memulihkannya melalui halaman Recycle Bin.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success d-flex align-items-center mb-0 border-0 bg-opacity-10 text-success bg-success">
                            <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                            <div>
                                <strong>Barang aktif!</strong> Data saat ini tersedia di tabel barang dan siap digunakan.
                                <a href="detail_barang.php?id=<?= $id_barang ?>" class="alert-link ms-2">Lihat Detail Barang Aktif <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Perbandingan Kolom -->
                <div class="col-12 col-lg-6">
                    <div class="card border border-danger border-opacity-10 bg-light bg-opacity-25 h-100">
                        <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold border-bottom-0">
                            <i class="bi bi-arrow-left-circle me-1"></i> Data Lama (Sebelum Update)
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($log_data['foto'] && file_exists("../uploads/barang/" . $log_data['foto'])): ?>
                                    <img src="../uploads/barang/<?= htmlspecialchars($log_data['foto']) ?>"
                                         alt="Foto Lama" class="rounded border me-3"
                                         style="width: 80px; height: 80px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded bg-light border d-flex align-items-center justify-content-center me-3"
                                         style="width: 80px; height: 80px;">
                                        <i class="bi bi-image text-muted fs-3"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($log_data['nama_barang']) ?></h6>
                                    <code><?= htmlspecialchars($log_data['kode_barang']) ?></code>
                                </div>
                            </div>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted py-1" style="width: 120px;">Kategori</td>
                                    <td class="py-1">: <?= htmlspecialchars($log_data['nama_kategori_lama'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">Stok</td>
                                    <td class="py-1">: <?= (int) $log_data['stok'] ?> unit</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">Kondisi</td>
                                    <td class="py-1">
                                        <?php
                                        $cond_old = $log_data['kondisi'];
                                        $color_old = match($cond_old) {
                                            'Rusak Ringan' => 'warning',
                                            'Rusak Berat'  => 'danger',
                                            default        => 'success'
                                        };
                                        ?>
                                        : <span class="badge bg-<?= $color_old ?> bg-opacity-10 text-<?= $color_old ?> rounded-pill px-2"><?= $cond_old ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card border border-success border-opacity-10 bg-light bg-opacity-25 h-100">
                        <div class="card-header bg-success bg-opacity-10 text-success fw-bold border-bottom-0">
                            <i class="bi bi-arrow-right-circle me-1"></i> Data Baru / Saat Ini
                        </div>
                        <div class="card-body">
                            <?php if ($barang_purged): ?>
                                <div class="d-flex align-items-center justify-content-center h-100 py-4 text-muted">
                                    <div class="text-center">
                                        <i class="bi bi-trash fs-2 d-block mb-2 text-danger"></i>
                                        <span>Data sudah tidak tersedia di database</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($barang_data['foto'] && file_exists("../uploads/barang/" . $barang_data['foto'])): ?>
                                        <img src="../uploads/barang/<?= htmlspecialchars($barang_data['foto']) ?>"
                                             alt="Foto Baru" class="rounded border me-3"
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded bg-light border d-flex align-items-center justify-content-center me-3"
                                             style="width: 80px; height: 80px;">
                                            <i class="bi bi-image text-muted fs-3"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($barang_data['nama_barang']) ?></h6>
                                        <code><?= htmlspecialchars($barang_data['kode_barang']) ?></code>
                                    </div>
                                </div>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted py-1" style="width: 120px;">Kategori</td>
                                        <td class="py-1">: <?= htmlspecialchars($barang_data['nama_kategori_baru'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Stok</td>
                                        <td class="py-1">: <?= (int) $barang_data['stok'] ?> unit</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Kondisi</td>
                                        <td class="py-1">
                                            <?php
                                            $cond_new = $barang_data['kondisi'];
                                            $color_new = match($cond_new) {
                                                'Rusak Ringan' => 'warning',
                                                'Rusak Berat'  => 'danger',
                                                default        => 'success'
                                            };
                                            ?>
                                            : <span class="badge bg-<?= $color_new ?> bg-opacity-10 text-<?= $color_new ?> rounded-pill px-2"><?= $cond_new ?></span>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabel Perbandingan Spesiifik -->
                <?php if (!$barang_purged): ?>
                <div class="col-12">
                    <h6 class="fw-bold mb-3" style="color:#002645;">
                        <i class="bi bi-layout-text-sidebar-reverse me-1"></i>
                        Perbandingan Perubahan Kolom
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0 text-center">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 200px;">Kolom</th>
                                    <th>Data Sebelum Update (Lama)</th>
                                    <th style="width: 100px;">Perubahan</th>
                                    <th>Data Setelah Update (Baru/Saat Ini)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fields = [
                                    'kode_barang' => ['Label' => 'Kode Barang', 'type' => 'text'],
                                    'nama_barang' => ['Label' => 'Nama Barang', 'type' => 'text'],
                                    'id_kategori' => ['Label' => 'Kategori', 'type' => 'kategori', 'old_val' => $log_data['nama_kategori_lama'] ?? '-', 'new_val' => $barang_data['nama_kategori_baru'] ?? '-'],
                                    'stok'        => ['Label' => 'Jumlah Stok', 'type' => 'number'],
                                    'kondisi'     => ['Label' => 'Kondisi Barang', 'type' => 'text'],
                                    'foto'        => ['Label' => 'Foto Barang', 'type' => 'foto'],
                                ];

                                foreach ($fields as $key => $meta):
                                    if ($meta['type'] === 'kategori') {
                                        $old_val = $meta['old_val'];
                                        $new_val = $meta['new_val'];
                                    } elseif ($meta['type'] === 'foto') {
                                        $old_val = $log_data['foto'];
                                        $new_val = $barang_data['foto'];
                                    } else {
                                        $old_val = $log_data[$key];
                                        $new_val = $barang_data[$key];
                                    }

                                    $is_changed = ($old_val !== $new_val);
                                ?>
                                <tr>
                                    <td class="fw-semibold text-start px-3"><?= $meta['Label'] ?></td>
                                    <td>
                                        <?php if ($meta['type'] === 'foto'): ?>
                                            <?php if ($old_val): ?>
                                                <img src="../uploads/barang/<?= htmlspecialchars($old_val) ?>" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <span class="text-muted">— Tidak ada foto —</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($old_val) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_changed): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning px-3 rounded-pill">
                                                <i class="bi bi-arrow-right"></i>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-dash"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $is_changed ? 'table-success bg-opacity-25' : '' ?>">
                                        <?php if ($meta['type'] === 'foto'): ?>
                                            <?php if ($new_val): ?>
                                                <img src="../uploads/barang/<?= htmlspecialchars($new_val) ?>" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <span class="text-muted">— Tidak ada foto —</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($is_changed): ?>
                                                <span class="text-success fw-bold"><?= htmlspecialchars($new_val) ?></span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($new_val) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
