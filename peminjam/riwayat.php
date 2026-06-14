<?php
require_once '../includes/config.php';
requireLogin();

if ($_SESSION['role'] !== 'peminjam') {
    header("Location: ../admin/dashboard.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// ===== PROSES KEMBALIKAN =====
if (isset($_POST['kembalikan'])) {
    $id         = (int) $_POST['id_peminjaman'];
    $jml_baik   = max(0, (int) $_POST['jumlah_baik']);
    $jml_rusak  = max(0, (int) $_POST['jumlah_rusak']);
    $jml_hilang = max(0, (int) $_POST['jumlah_hilang']);
    $catatan    = mysqli_real_escape_string($conn, trim($_POST['catatan_kembali'] ?? ''));

    // Ambil data peminjaman milik user ini
    $res = mysqli_query($conn, "SELECT id_barang, jumlah FROM peminjaman
                                WHERE id_peminjaman = $id
                                  AND id_user = $user_id
                                  AND status = 'dipinjam'");

    if (mysqli_num_rows($res) == 1) {
        $pem             = mysqli_fetch_assoc($res);
        $id_barang       = (int) $pem['id_barang'];
        $jumlah_dipinjam = (int) $pem['jumlah'];

        $total = $jml_baik + $jml_rusak + $jml_hilang;
        if ($total !== $jumlah_dipinjam) {
            $_SESSION['error'] = "Total kondisi ($total unit) harus sama dengan jumlah dipinjam ($jumlah_dipinjam unit).";
            header("Location: riwayat.php");
            exit();
        }

        if (($jml_rusak > 0 || $jml_hilang > 0) && $catatan === '') {
            $_SESSION['error'] = "Catatan wajib diisi jika ada barang rusak atau hilang.";
            header("Location: riwayat.php");
            exit();
        }

        $ok = mysqli_query($conn, "UPDATE peminjaman SET
                                        status                = 'dikembalikan',
                                        tanggal_kembali_aktual = NOW(),
                                        jumlah_baik           = $jml_baik,
                                        jumlah_rusak          = $jml_rusak,
                                        jumlah_hilang         = $jml_hilang,
                                        catatan_kembali       = '$catatan'
                                   WHERE id_peminjaman = $id");

        if ($ok) {
            // Kurangi stok fisik untuk barang rusak/hilang (tidak kembali ke inventaris)
            $stok_hilang = $jml_rusak + $jml_hilang;
            if ($stok_hilang > 0) {
                mysqli_query($conn, "UPDATE barang SET stok = stok - $stok_hilang WHERE id_barang = $id_barang");
            }
            header("Location: riwayat.php?status=kembali");
        } else {
            $_SESSION['error'] = "Gagal memproses pengembalian: " . mysqli_error($conn);
            header("Location: riwayat.php");
        }
        exit();
    } else {
        $_SESSION['error'] = "Data tidak ditemukan atau sudah dikembalikan.";
        header("Location: riwayat.php");
        exit();
    }
}

$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

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
    if (isset($_GET['status'])) {
        $messages = [
            'pinjam_sukses' => ['success', 'bi-check-circle-fill', 'Peminjaman berhasil diajukan! Silakan ambil barang di ruang inventaris.'],
            'kembali'       => ['success', 'bi-arrow-return-left', 'Barang berhasil dikembalikan. Terima kasih!'],
        ];
        $modal_data = $messages[$_GET['status']] ?? null;
    } elseif ($error_message) {
        $modal_data = ['danger', 'bi-exclamation-triangle-fill', $error_message];
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
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Aksi</th>
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
                            <td class="px-4">
                                <?php if ($row['status'] === 'dipinjam'): ?>
                                <button class="btn btn-sm btn-outline-success"
                                        onclick="bukaModalKembali(
                                            <?= $row['id_peminjaman'] ?>,
                                            '<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>',
                                            <?= (int)$row['jumlah'] ?>)"
                                        title="Kembalikan Barang">
                                    <i class="bi bi-arrow-return-left me-1"></i> Kembalikan
                                </button>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
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

                    <!-- Live counter -->
                    <div class="alert py-2 px-3 mb-3 small d-flex justify-content-between align-items-center" id="totalAlert">
                        <span>Total diisi:</span>
                        <span><strong id="kembaliTotal">0</strong> / <strong id="kembaliMax">0</strong> unit</span>
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

    let jumlahDipinjam = 0;

    function bukaModalKembali(id, nama, jumlah) {
        document.getElementById('formKembali').reset();
        document.getElementById('kembaliId').value = id;
        document.getElementById('kembaliNamaBarang').textContent = nama;

        jumlahDipinjam = parseInt(jumlah) || 1;
        document.getElementById('kembaliInfoJumlah').textContent = jumlahDipinjam + ' unit';
        document.getElementById('kembaliMax').textContent = jumlahDipinjam;

        document.getElementById('kembaliJmlBaik').value   = jumlahDipinjam;
        document.getElementById('kembaliJmlRusak').value  = 0;
        document.getElementById('kembaliJmlHilang').value = 0;

        hitungTotal();
        toggleCatatan();
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
        const alertEl = document.getElementById('totalAlert');
        const errEl   = document.getElementById('errKembaliTotal');

        if (total === jumlahDipinjam) {
            alertEl.className = 'alert alert-success py-2 px-3 mb-3 small d-flex justify-content-between align-items-center';
            errEl.textContent = '';
        } else {
            alertEl.className = 'alert alert-danger py-2 px-3 mb-3 small d-flex justify-content-between align-items-center';
            errEl.textContent = total < jumlahDipinjam
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

    document.getElementById('formKembali').addEventListener('submit', function(e) {
        let isValid = true;

        const baik   = parseInt(document.getElementById('kembaliJmlBaik').value)   || 0;
        const rusak  = parseInt(document.getElementById('kembaliJmlRusak').value)  || 0;
        const hilang = parseInt(document.getElementById('kembaliJmlHilang').value) || 0;
        const total  = baik + rusak + hilang;
        const errTotal = document.getElementById('errKembaliTotal');

        if (total !== jumlahDipinjam) {
            errTotal.textContent = 'Total kondisi harus tepat ' + jumlahDipinjam + ' unit (sekarang: ' + total + ').';
            isValid = false;
        } else {
            errTotal.textContent = '';
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
</body>
</html>
