<?php
require_once '../includes/config.php';
requireAdmin();

// ===== PROSES TAMBAH =====
if (isset($_POST['tambah'])) {
    $nama = htmlspecialchars(trim($_POST['nama_kategori']));
    if (!empty($nama)) {
        $query = "INSERT INTO kategori (nama_kategori) VALUES ('$nama')";
        mysqli_query($conn, $query);
        header("Location: kategori.php?status=tambah");
        exit();
    }
}

// ===== PROSES EDIT =====
if (isset($_POST['edit'])) {
    $id   = (int) $_POST['id_kategori'];
    $nama = htmlspecialchars(trim($_POST['nama_kategori']));
    if (!empty($nama) && $id > 0) {
        $query = "UPDATE kategori SET nama_kategori = '$nama' WHERE id_kategori = $id";
        mysqli_query($conn, $query);
        header("Location: kategori.php?status=edit");
        exit();
    }
}

// ===== PROSES HAPUS =====
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM kategori WHERE id_kategori = $id");
    header("Location: kategori.php?status=hapus");
    exit();
}

// ===== AMBIL DATA =====
$result = mysqli_query($conn, "
    SELECT k.*, COUNT(b.id_barang) as jumlah_barang
    FROM kategori k
    LEFT JOIN barang b ON k.id_kategori = b.id_kategori
    GROUP BY k.id_kategori
    ORDER BY k.id_kategori ASC
");

$page_title = "Kategori";
$current_page = basename(__FILE__);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Kategori</h5>
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
            'tambah' => ['success', 'bi-check-circle', 'Kategori berhasil ditambahkan.'],
            'edit'   => ['success', 'bi-check-circle', 'Kategori berhasil diperbarui.'],
            'hapus'  => ['warning', 'bi-trash',        'Kategori berhasil dihapus.'],
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

    <div class="row g-4">

        <!-- Form Tambah -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0" style="color:#002645;">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Kategori
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="formTambah">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nama Kategori</label>
                            <input
                                type="text"
                                class="form-control"
                                name="nama_kategori"
                                id="inputTambah"
                                placeholder="contoh: Elektronik"
                                autocomplete="off"
                            >
                            <div class="invalid-feedback" id="errTambah"></div>
                        </div>
                        <button type="submit" name="tambah" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Simpan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabel Data -->
        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0" style="color:#002645;">Daftar Kategori</h6>
                    <span class="badge bg-primary rounded-pill">
                        <?= mysqli_num_rows($result) ?> kategori
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">No</th>
                                    <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Kategori</th>
                                    <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Jumlah Barang</th>
                                    <th class="px-4 py-3 small text-uppercase text-muted fw-semibold text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0):
                                    $no = 1;
                                    while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="px-4"><?= $no++ ?></td>
                                    <td class="px-4 fw-semibold"><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                    <td class="px-4">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">
                                            <?= $row['jumlah_barang'] ?> barang
                                        </span>
                                    </td>
                                    <td class="px-4 text-end">
                                        <!-- Tombol Edit -->
                                        <button
                                            class="btn btn-sm btn-outline-primary me-1"
                                            onclick="bukaModalEdit(<?= $row['id_kategori'] ?>, '<?= htmlspecialchars($row['nama_kategori'], ENT_QUOTES) ?>')"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <!-- Tombol Hapus -->
                                        <a
                                            href="kategori.php?hapus=<?= $row['id_kategori'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return konfirmasiHapus('<?= htmlspecialchars($row['nama_kategori'], ENT_QUOTES) ?>')"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile;
                                else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        Belum ada kategori
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

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-pencil-square me-2"></i>Edit Kategori
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEdit">
                <div class="modal-body">
                    <input type="hidden" name="id_kategori" id="editId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Kategori</label>
                        <input
                            type="text"
                            class="form-control"
                            name="nama_kategori"
                            id="editNama"
                            autocomplete="off"
                        >
                        <div class="invalid-feedback" id="errEdit"></div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
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

    // Buka modal edit + isi data
    function bukaModalEdit(id, nama) {
        document.getElementById('editId').value  = id;
        document.getElementById('editNama').value = nama;
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // Konfirmasi hapus
    function konfirmasiHapus(nama) {
        return confirm('Hapus kategori "' + nama + '"?\nBarang dalam kategori ini tidak akan terhapus.');
    }

    // Validasi form tambah
    document.getElementById('formTambah').addEventListener('submit', function (e) {
        const input = document.getElementById('inputTambah');
        const err   = document.getElementById('errTambah');
        input.classList.remove('is-invalid');
        err.textContent = '';

        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            err.textContent = 'Nama kategori wajib diisi.';
            e.preventDefault();
        }
    });

    // Validasi form edit
    document.getElementById('formEdit').addEventListener('submit', function (e) {
        const input = document.getElementById('editNama');
        const err   = document.getElementById('errEdit');
        input.classList.remove('is-invalid');
        err.textContent = '';

        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            err.textContent = 'Nama kategori wajib diisi.';
            e.preventDefault();
        }
    });

    // Auto dismiss alert setelah 3 detik
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => {
            new bootstrap.Alert(el).close();
        });
    }, 3000);
</script>
</body>
</html>