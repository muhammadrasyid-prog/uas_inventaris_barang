<?php
require_once '../includes/config.php';
requireAdmin();

// PROSES TAMBAH
if (isset($_POST['tambah'])) {
    $id_barang   = (int) $_POST['id_barang'];
    $id_user     = (int) $_POST['id_user'];
    $jumlah_pinjam = max(1, (int) $_POST['jumlah']);
    $tgl_pinjam  = mysqli_real_escape_string($conn, $_POST['tanggal_pinjam']);
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

    if ($jumlah_pinjam > $cek['stok_tersedia']) {
        $_SESSION['error'] = "Jumlah pinjam melebihi stok tersedia (" . $cek['stok_tersedia'] . " unit).";
        header("Location: peminjaman.php");
        exit();
    }

    // Simpan peminjaman dengan jumlah
    $query = "INSERT INTO peminjaman (id_barang, id_user, jumlah, tanggal_pinjam, tanggal_kembali_rencana)
              VALUES ($id_barang, $id_user, $jumlah_pinjam, '$tgl_pinjam', '$tgl_rencana')";
    
    mysqli_query($conn, $query);
    // Stok tersedia dihitung otomatis oleh VIEW — tidak perlu UPDATE barang.stok

    header("Location: peminjaman.php?status=tambah");
    exit();
}

// PROSES KEMBALIKAN
if (isset($_POST['kembalikan'])) {
    $id            = (int) $_POST['id_peminjaman'];
    $jml_baik      = max(0, (int) $_POST['jumlah_baik']);
    $jml_rusak     = max(0, (int) $_POST['jumlah_rusak']);
    $jml_hilang    = max(0, (int) $_POST['jumlah_hilang']);
    $catatan       = mysqli_real_escape_string($conn, trim($_POST['catatan_kembali'] ?? ''));

    // Ambil data peminjaman untuk validasi
    $res_pem = mysqli_query($conn, "SELECT id_barang, jumlah FROM peminjaman WHERE id_peminjaman = $id AND status = 'dipinjam'");

    if (mysqli_num_rows($res_pem) == 1) {
        $pem             = mysqli_fetch_assoc($res_pem);
        $id_barang       = (int) $pem['id_barang'];
        $jumlah_dipinjam = (int) $pem['jumlah'];

        // Validasi: total harus sama persis dengan jumlah dipinjam
        $total = $jml_baik + $jml_rusak + $jml_hilang;
        if ($total !== $jumlah_dipinjam) {
            $_SESSION['error'] = "Total kondisi ($total unit) harus sama dengan jumlah dipinjam ($jumlah_dipinjam unit).";
            header("Location: peminjaman.php");
            exit();
        }

        // Validasi: catatan wajib jika ada rusak atau hilang
        if (($jml_rusak > 0 || $jml_hilang > 0) && $catatan === '') {
            $_SESSION['error'] = "Catatan wajib diisi jika ada barang rusak atau hilang.";
            header("Location: peminjaman.php");
            exit();
        }

        // UPDATE tabel peminjaman
        $query_update = "UPDATE peminjaman SET
                            status               = 'dikembalikan',
                            tanggal_kembali_aktual = NOW(),
                            jumlah_baik          = $jml_baik,
                            jumlah_rusak         = $jml_rusak,
                            jumlah_hilang        = $jml_hilang,
                            catatan_kembali      = '$catatan'
                         WHERE id_peminjaman = $id";

        if (mysqli_query($conn, $query_update)) {
            // VIEW mengeluarkan SELURUH jumlah pinjaman dari hitungan saat status = dikembalikan.
            // Untuk memastikan rusak & hilang TIDAK kembali ke stok:
            // kurangi barang.stok sebesar (rusak + hilang) secara permanen.
            $stok_hilang = $jml_rusak + $jml_hilang;
            if ($stok_hilang > 0) {
                mysqli_query($conn, "UPDATE barang SET stok = stok - $stok_hilang WHERE id_barang = $id_barang");
            }

            header("Location: peminjaman.php?status=kembali");
            exit();
        } else {
            $_SESSION['error'] = "Gagal memproses pengembalian: " . mysqli_error($conn);
            header("Location: peminjaman.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan atau sudah dikembalikan.";
        header("Location: peminjaman.php");
        exit();
    }
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
                                <a href="../struk.php?id=<?= $row['id_peminjaman'] ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-outline-primary me-1"
                                   title="Cetak Struk">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success me-1"
                                        onclick="bukaModalKembali(<?= $row['id_peminjaman'] ?>, '<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>', <?= $row['jumlah'] ?? 1 ?>)"
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
                        <label class="form-label fw-semibold small">Jumlah Pinjam <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="jumlah" id="tambahJumlah" min="1" value="1" required>
                        <div class="form-text" id="tambahJumlahInfo">Stok tersedia akan ditampilkan saat barang dipilih.</div>
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

                    <!-- Info readonly -->
                    <div class="mb-3 p-3 rounded" style="background:#f8f9fa;">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Barang:</span>
                            <strong id="kembaliNamaBarang"></strong>
                        </div>
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="text-muted">Jumlah Dipinjam:</span>
                            <strong id="kembaliInfoJumlah">0 unit</strong>
                        </div>
                    </div>

                    <!-- Tiga field kondisi -->
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label fw-semibold small text-success">Baik <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="jumlah_baik"
                                   id="kembaliJmlBaik" min="0" value="0"
                                   oninput="hitungTotal()" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold small text-warning">Rusak</label>
                            <input type="number" class="form-control" name="jumlah_rusak"
                                   id="kembaliJmlRusak" min="0" value="0"
                                   oninput="hitungTotal(); toggleCatatan()" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label fw-semibold small text-danger">Hilang</label>
                            <input type="number" class="form-control" name="jumlah_hilang"
                                   id="kembaliJmlHilang" min="0" value="0"
                                   oninput="hitungTotal(); toggleCatatan()" required>
                        </div>
                    </div>

                    <!-- Live total counter -->
                    <div class="alert py-2 px-3 mb-3 small d-flex justify-content-between align-items-center" id="totalAlert">
                        <span>Total diisi:</span>
                        <strong id="kembaliTotal">0</strong> / <strong id="kembaliMax">0</strong> unit
                    </div>
                    <div class="invalid-feedback d-block mb-2" id="errKembaliTotal"></div>

                    <!-- Catatan kondisional -->
                    <div class="mb-1" id="catatanKembaliContainer" style="display:none;">
                        <label class="form-label fw-semibold small">Catatan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="catatan_kembali" id="kembaliCatatan"
                                  rows="3" placeholder="Jelaskan kondisi barang rusak atau hilang..."></textarea>
                        <div class="invalid-feedback" id="errKembaliCatatan"></div>
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

    // Map stok tersedia dari dropdown barang
    const stokMap = {
        <?php
        $barang_stok = mysqli_query($conn, "SELECT id_barang, stok_tersedia FROM view_katalog_barang");
        $stok_pairs = [];
        while ($bs = mysqli_fetch_assoc($barang_stok)) {
            $stok_pairs[] = (int)$bs['id_barang'] . ': ' . (int)$bs['stok_tersedia'];
        }
        echo implode(", ", $stok_pairs);
        ?>
    };

    function bukaModalTambah() {
        document.getElementById('formTambah').reset();
        document.getElementById('tambahTglPinjam').value = new Date().toISOString().split('T')[0];
        document.getElementById('tambahJumlah').value = 1;
        document.getElementById('tambahJumlah').max = '';
        document.getElementById('tambahJumlahInfo').textContent = 'Stok tersedia akan ditampilkan saat barang dipilih.';
        new bootstrap.Modal(document.getElementById('modalTambah')).show();
    }

    document.getElementById('tambahBarang').addEventListener('change', function() {
        const idBarang = parseInt(this.value);
        const jumlahInput = document.getElementById('tambahJumlah');
        const infoEl = document.getElementById('tambahJumlahInfo');
        if (idBarang && stokMap[idBarang] !== undefined) {
            const stok = stokMap[idBarang];
            jumlahInput.max = stok;
            infoEl.textContent = 'Stok tersedia: ' + stok + ' unit';
        } else {
            jumlahInput.max = '';
            infoEl.textContent = 'Stok tersedia akan ditampilkan saat barang dipilih.';
        }
    });

    let jumlahDipinjam = 0;

    function bukaModalKembali(id, nama, jumlah) {
        document.getElementById('formKembali').reset();
        document.getElementById('kembaliId').value    = id;
        document.getElementById('kembaliNamaBarang').textContent = nama;

        jumlahDipinjam = parseInt(jumlah) || 1;
        document.getElementById('kembaliInfoJumlah').textContent = jumlahDipinjam + ' unit';
        document.getElementById('kembaliMax').textContent = jumlahDipinjam;

        // Default: semua baik
        document.getElementById('kembaliJmlBaik').value   = jumlahDipinjam;
        document.getElementById('kembaliJmlRusak').value  = 0;
        document.getElementById('kembaliJmlHilang').value = 0;

        hitungTotal();
        toggleCatatan();

        // Bersihkan error lama
        document.getElementById('errKembaliTotal').textContent   = '';
        document.getElementById('errKembaliCatatan').textContent = '';
        document.getElementById('kembaliCatatan').classList.remove('is-invalid');

        new bootstrap.Modal(document.getElementById('modalKembali')).show();
    }

    function hitungTotal() {
        const baik   = parseInt(document.getElementById('kembaliJmlBaik').value)   || 0;
        const rusak  = parseInt(document.getElementById('kembaliJmlRusak').value)  || 0;
        const hilang = parseInt(document.getElementById('kembaliJmlHilang').value) || 0;
        const total  = baik + rusak + hilang;

        document.getElementById('kembaliTotal').textContent = total;

        const alert = document.getElementById('totalAlert');
        const err   = document.getElementById('errKembaliTotal');
        if (total === jumlahDipinjam) {
            alert.className = 'alert alert-success py-2 px-3 mb-3 small d-flex justify-content-between align-items-center';
            err.textContent = '';
        } else {
            alert.className = 'alert alert-danger py-2 px-3 mb-3 small d-flex justify-content-between align-items-center';
            err.textContent = total < jumlahDipinjam
                ? 'Masih kurang ' + (jumlahDipinjam - total) + ' unit.'
                : 'Melebihi jumlah dipinjam sebesar ' + (total - jumlahDipinjam) + ' unit.';
        }
    }

    function toggleCatatan() {
        const rusak  = parseInt(document.getElementById('kembaliJmlRusak').value)  || 0;
        const hilang = parseInt(document.getElementById('kembaliJmlHilang').value) || 0;
        const container = document.getElementById('catatanKembaliContainer');
        const catatan   = document.getElementById('kembaliCatatan');

        if (rusak > 0 || hilang > 0) {
            container.style.display = 'block';
            catatan.required = true;
        } else {
            container.style.display = 'none';
            catatan.required = false;
            catatan.value = '';
        }
    }

    // Validasi form tambah
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        const tglPinjam  = document.getElementById('tambahTglPinjam').value;
        const tglKembali = document.getElementById('tambahTglKembali').value;
        if (tglPinjam && tglKembali && tglKembali <= tglPinjam) {
            alert('Tenggat kembali harus setelah tanggal pinjam!');
            e.preventDefault();
        }
    });

    // Validasi form kembali
    document.getElementById('formKembali').addEventListener('submit', function(e) {
        let isValid = true;

        const baik   = parseInt(document.getElementById('kembaliJmlBaik').value)   || 0;
        const rusak  = parseInt(document.getElementById('kembaliJmlRusak').value)  || 0;
        const hilang = parseInt(document.getElementById('kembaliJmlHilang').value) || 0;
        const total  = baik + rusak + hilang;
        const err    = document.getElementById('errKembaliTotal');

        if (total !== jumlahDipinjam) {
            err.textContent = 'Total kondisi harus tepat ' + jumlahDipinjam + ' unit (sekarang: ' + total + ').';
            isValid = false;
        } else {
            err.textContent = '';
        }

        const catatanInput = document.getElementById('kembaliCatatan');
        const errCatatan   = document.getElementById('errKembaliCatatan');
        catatanInput.classList.remove('is-invalid');
        errCatatan.textContent = '';

        if ((rusak > 0 || hilang > 0) && !catatanInput.value.trim()) {
            catatanInput.classList.add('is-invalid');
            errCatatan.textContent = 'Catatan wajib diisi jika ada barang rusak atau hilang.';
            isValid = false;
        }

        if (!isValid) e.preventDefault();
    });


</script>
<?php require_once '../includes/footer.php'; ?>