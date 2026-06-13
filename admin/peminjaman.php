<?php
require_once '../includes/config.php';
requireAdmin();

// PROSES TAMBAH
if (isset($_POST['tambah'])) {
    $id_barang  = (int) $_POST['id_barang'];
    $id_user    = (int) $_POST['id_user'];
    $tgl_pinjam = mysqli_real_escape_string($conn, $_POST['tanggal_pinjam']);
    $tgl_rencana = mysqli_real_escape_string($conn, $_POST['tanggal_kembali_rencana']);

    // Cek stok tersedia
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT stok_tersedia 
        FROM view_katalog_barang 
        WHERE id_barang = $id_barang
    "));

    if (!$cek || $cek['stok_tersedia'] <= 0) {
        $_SESSION['error'] = "Stok barang tidak tersedia.";
        header("Location: peminjaman.php");
        exit();
    }

    $query = "INSERT INTO peminjaman (id_barang, id_user, tanggal_pinjam, tanggal_kembali_rencana)
              VALUES ($id_barang, $id_user, '$tgl_pinjam', '$tgl_rencana')";
    
    mysqli_query($conn, $query);
    header("Location: peminjaman.php?status=tambah");
    exit();
}

// PROSES KEMBALIKAN
if (isset($_POST['kembalikan'])) {
    $id           = (int) $_POST['id_peminjaman'];
    $tgl_aktual   = mysqli_real_escape_string($conn, $_POST['tanggal_kembali_aktual']);
    
    $query = "UPDATE peminjaman 
              SET status = 'dikembalikan', tanggal_kembali_aktual = '$tgl_aktual' 
              WHERE id_peminjaman = $id";
    
    mysqli_query($conn, $query);
    header("Location: peminjaman.php?status=kembali");
    exit();
}

// PROSES HAPUS
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM peminjaman WHERE id_peminjaman = $id");
    header("Location: peminjaman.php?status=hapus");
    exit();
}

// Ambil error dari session
$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

// FILTER & SEARCH
$search      = htmlspecialchars(trim($_GET['search'] ?? ''));
$filter_status = htmlspecialchars(trim($_GET['filter_status'] ?? ''));

$where = "WHERE 1=1";
if ($search) {
    $search_like = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.nama_lengkap LIKE '%$search_like%' OR b.nama_barang LIKE '%$search_like%')";
}
if ($filter_status) {
    $filter_status = mysqli_real_escape_string($conn, $filter_status);
    $where .= " AND p.status = '$filter_status'";
}

// PAGINATION
$per_page    = 10;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

$total_query = "SELECT COUNT(*) as total FROM peminjaman p
                JOIN users u ON p.id_user = u.id_user
                JOIN barang b ON p.id_barang = b.id_barang
                $where";
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, $total_query))['total'];
$total_pages = ceil($total_rows / $per_page);

// AMBIL DATA
$result = mysqli_query($conn, "
    SELECT p.*,
           u.nama_lengkap,
           b.nama_barang, b.kode_barang,
           CASE
               WHEN p.status = 'dipinjam' AND p.tanggal_kembali_rencana < CURDATE() THEN 'Terlambat'
               WHEN p.status = 'dipinjam' THEN 'Dipinjam'
               ELSE 'Dikembalikan'
           END AS keterangan
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    JOIN barang b ON p.id_barang = b.id_barang
    $where
    ORDER BY p.id_peminjaman DESC
    LIMIT $per_page OFFSET $offset
");

// DROPDOWN
$barang_list = mysqli_query($conn, "
    SELECT id_barang, kode_barang, nama_barang, stok_tersedia
    FROM view_katalog_barang
    WHERE stok_tersedia > 0
    ORDER BY nama_barang ASC
");

$user_list = mysqli_query($conn, "
    SELECT * FROM users WHERE role = 'peminjam' ORDER BY nama_lengkap ASC
");

// SET VARIABEL UNTUK HEADER
$page_title   = "Data Peminjaman";
$current_page = basename(__FILE__);

// INCLUDE HEADER & SIDEBAR (SETELAH SEMUA PROSES SELESAI)
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Data Peminjaman</h5>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
        </span>
    </div>
</header>

<div class="p-4">

    <!-- Alert Modal -->
    <?php
    $modal_data = null;
    if (isset($_GET['status'])) {
        $messages = [
            'tambah' => ['success', 'bi-check-circle', 'Peminjaman berhasil ditambahkan.'],
            'kembali'=> ['success', 'bi-check-circle', 'Barang berhasil dikembalikan.'],
            'hapus'  => ['warning', 'bi-trash', 'Data peminjaman berhasil dihapus.'],
        ];
        $modal_data = $messages[$_GET['status']] ?? null;
    } elseif ($error_message) {
        $modal_data = ['danger', 'bi-exclamation-triangle-fill', $error_message];
    }
    ?>
    <?php include_once '../includes/alert_modal.php'; ?>

    <!-- Toolbar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">

            <!-- Kiri: Search + Filter -->
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group" style="width: 260px;">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" name="search"
                           placeholder="Cari nama peminjam atau barang..." value="<?= $search ?>">
                </div>
                <select class="form-select" style="width: 180px;" name="filter_status">
                    <option value="">Semua Status</option>
                    <option value="dipinjam" <?= $filter_status === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                    <option value="dikembalikan" <?= $filter_status === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                </select>
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <?php if ($search || $filter_status): ?>
                    <a href="peminjaman.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Reset
                    </a>
                <?php endif; ?>
            </form>

            <!-- Kanan: Tombol Tambah -->
            <button type="button" class="btn btn-primary" onclick="bukaModalTambah()">
                <i class="bi bi-plus-lg me-1"></i> Tambah Peminjaman
            </button>

        </div>
    </div>
</div>

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color:#002645;">Daftar Peminjaman</h6>
            <span class="badge bg-primary rounded-pill"><?= $total_rows ?> data</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Peminjam</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Barang</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Jumlah</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Tgl Pinjam</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Tenggat</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Tgl Kembali</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Status</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold aksi-col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0):
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($result)):
                                $badge = match($row['keterangan']) {
                                    'Terlambat' => ['danger', 'bi-clock'],
                                    'Dipinjam'  => ['primary', 'bi-arrow-up-right-circle'],
                                    default     => ['success', 'bi-check-circle'],
                                };
                        ?>
                        <tr>
                            <td class="px-3"><?= $no++ ?></td>
                            <td class="px-3 fw-semibold"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td class="px-3">
                                <div><?= htmlspecialchars($row['nama_barang']) ?></div>
                                <small class="text-muted"><code><?= htmlspecialchars($row['kode_barang']) ?></code></small>
                            </td>
                            <td class="px-3"><?= $row['jumlah'] ?? 1 ?> unit</td>
                            <td class="px-3"><?= date('d M Y', strtotime($row['tanggal_pinjam'])) ?></td>
                            <td class="px-3"><?= date('d M Y', strtotime($row['tanggal_kembali_rencana'])) ?></td>
                            <td class="px-3">
                                <?= $row['tanggal_kembali_aktual'] ? date('d M Y', strtotime($row['tanggal_kembali_aktual'])) : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="px-3">
                                <span class="badge bg-<?= $badge[0] ?> bg-opacity-10 text-<?= $badge[0] ?> rounded-pill px-3">
                                    <i class="bi <?= $badge[1] ?> me-1"></i><?= $row['keterangan'] ?>
                                </span>
                            </td>
                            <td class="px-3 aksi-col">
                                <?php if ($row['status'] === 'dipinjam'): ?>
                                <button class="btn btn-sm btn-outline-success me-1"
                                        onclick="bukaModalKembali(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>')"
                                        title="Kembalikan">
                                    <i class="bi bi-arrow-return-left"></i>
                                </button>
                                <?php endif; ?>
                                <a href="?hapus=<?= $row['id_peminjaman'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Hapus peminjaman barang <?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>?')"
                                   title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                <?= $search || $filter_status ? 'Tidak ada data yang cocok.' : 'Belum ada data peminjaman.' ?>
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
                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> data
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= $search ?>&filter_status=<?= $filter_status ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= $search ?>&filter_status=<?= $filter_status ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= $search ?>&filter_status=<?= $filter_status ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Peminjaman -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Peminjaman
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formTambah">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Peminjam <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_user" id="tambahUser" required>
                            <option value="">Pilih Peminjam</option>
                            <?php mysqli_data_seek($user_list, 0);
                            while ($u = mysqli_fetch_assoc($user_list)): ?>
                            <option value="<?= $u['id_user'] ?>"><?= htmlspecialchars($u['nama_lengkap']) ?> (<?= $u['username'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Barang <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_barang" id="tambahBarang" required>
                            <option value="">Pilih Barang</option>
                            <?php mysqli_data_seek($barang_list, 0);
                            while ($b = mysqli_fetch_assoc($barang_list)): ?>
                            <option value="<?= $b['id_barang'] ?>"><?= htmlspecialchars($b['nama_barang']) ?> (<?= $b['kode_barang'] ?> — tersedia: <?= $b['stok_tersedia'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tanggal Pinjam <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal_pinjam" id="tambahTglPinjam" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tenggat Kembali <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal_kembali_rencana" id="tambahTglKembali" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Kembalikan -->
<div class="modal fade" id="modalKembali" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-arrow-return-left me-2"></i>Kembalikan Barang
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formKembali">
                <div class="modal-body">
                    <input type="hidden" name="id_peminjaman" id="kembaliId">
                    <p class="text-muted mb-3">
                        Konfirmasi pengembalian barang:
                        <strong id="kembaliNamaBarang" class="text-dark"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tanggal Kembali Aktual <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal_kembali_aktual" id="kembaliTgl" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="kembalikan" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi Kembali
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

    function bukaModalTambah() {
        document.getElementById('formTambah').reset();
        document.getElementById('tambahTglPinjam').value = new Date().toISOString().split('T')[0];
        new bootstrap.Modal(document.getElementById('modalTambah')).show();
    }

    function bukaModalKembali(id, nama) {
        document.getElementById('kembaliId').value = id;
        document.getElementById('kembaliNamaBarang').textContent = nama;
        document.getElementById('kembaliTgl').value = new Date().toISOString().split('T')[0];
        new bootstrap.Modal(document.getElementById('modalKembali')).show();
    }

    // Validasi form tambah
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        const tglPinjam = document.getElementById('tambahTglPinjam').value;
        const tglKembali = document.getElementById('tambahTglKembali').value;
        
        if (tglPinjam && tglKembali && tglKembali <= tglPinjam) {
            alert('Tenggat kembali harus setelah tanggal pinjam!');
            e.preventDefault();
        }
    });


</script>
<?php require_once '../includes/footer.php'; ?>