<?php
require_once '../includes/config.php';
requireAdmin();

// PROSES HAPUS (sebelum ada output apapun, agar header() redirect aman)
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    $stmt = mysqli_prepare($conn, "DELETE FROM barang WHERE id_barang = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: barang.php?status=hapus");
    exit();
}

$page_title   = "Data Barang";
$current_page = basename(__FILE__);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// SEARCH & FILTER
$search     = trim($_GET['search'] ?? '');
$filter_kat = (int) ($_GET['kategori'] ?? 0);

$conditions = "1=1";
$params     = [];
$types      = "";

if ($search !== '') {
    $conditions .= " AND (b.nama_barang LIKE ? OR b.kode_barang LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
if ($filter_kat > 0) {
    $conditions .= " AND b.id_kategori = ?";
    $params[] = $filter_kat;
    $types   .= "i";
}

// TOTAL ROWS (untuk pagination)
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM barang b WHERE $conditions");
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$total_rows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);

// PAGINATION 
$per_page    = 10;
$total_pages = max(1, (int) ceil($total_rows / $per_page));
$page        = max(1, (int) ($_GET['page'] ?? 1));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// AMBIL DATA BARANG (JOIN + stok dinamis + status stok dalam 1 query)
$data_query = "
    SELECT b.*,
           k.nama_kategori,
           (b.stok - COUNT(CASE WHEN p.status = 'dipinjam' THEN 1 END)) AS stok_tersedia,
           cek_status_stok(b.stok - COUNT(CASE WHEN p.status = 'dipinjam' THEN 1 END)) AS status_stok
    FROM barang b
    LEFT JOIN kategori k ON b.id_kategori = k.id_kategori
    LEFT JOIN peminjaman p ON b.id_barang = p.id_barang
    WHERE $conditions
    GROUP BY b.id_barang
    ORDER BY b.id_barang ASC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $data_query);
$bind_params = $params;
$bind_types  = $types . "ii";
$bind_params[] = $per_page;
$bind_params[] = $offset;
mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ===== AMBIL SEMUA KATEGORI untuk dropdown (sekali saja, dipakai ulang) =====
$kategori_result = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
$kategori_list   = mysqli_fetch_all($kategori_result, MYSQLI_ASSOC);
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Data Barang</h5>
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

    <!-- Alert -->
    <?php if (isset($_GET['status'])): ?>
        <?php
        $messages = [
            'tambah' => ['success', 'bi-check-circle', 'Barang berhasil ditambahkan.'],
            'edit'   => ['success', 'bi-check-circle', 'Barang berhasil diperbarui.'],
            'hapus'  => ['warning', 'bi-trash',        'Barang berhasil dihapus.'],
            'error'  => ['danger',  'bi-exclamation-triangle', $_GET['msg'] ?? 'Terjadi kesalahan.'],
        ];
        $s = $messages[$_GET['status']] ?? null;
        if ($s): ?>
            <div class="alert alert-<?= $s[0] ?> alert-dismissible d-flex align-items-center gap-2">
                <i class="bi <?= $s[1] ?>"></i>
                <span><?= htmlspecialchars($s[2]) ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Toolbar: Search + Filter + Tombol Tambah -->
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
                            placeholder="Cari nama atau kode barang..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select" name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $k): ?>
                            <option value="<?= $k['id_kategori'] ?>" <?= $filter_kat == $k['id_kategori'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if ($search !== '' || $filter_kat > 0): ?>
                        <a href="barang.php" class="btn btn-outline-secondary ms-1">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-auto ms-md-auto">
                    <button type="button" class="btn btn-primary" onclick="bukaModalTambah()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Barang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color:#002645;">Daftar Barang</h6>
            <span class="badge bg-primary rounded-pill"><?= $total_rows ?> barang</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Foto</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Kode</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Nama Barang</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Kategori</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Stok</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Tersedia</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold">Kondisi</th>
                            <th class="px-3 py-3 small text-uppercase text-muted fw-semibold text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0):
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($result)):
                                $stok_tersedia = (int) $row['stok_tersedia'];
                                $status_stok   = $row['status_stok'];
                                $badge_color = match ($status_stok) {
                                    'Habis'   => 'danger',
                                    'Menipis' => 'warning',
                                    default   => 'success'
                                };
                                $kondisi_color = match ($row['kondisi']) {
                                    'Rusak Ringan' => 'warning',
                                    'Rusak Berat'  => 'danger',
                                    default        => 'success'
                                };
                        ?>
                                <tr>
                                    <td class="px-3"><?= $no++ ?></td>
                                    <td class="px-3">
                                        <?php if ($row['foto']): ?>
                                            <img
                                                src="../uploads/barang/<?= htmlspecialchars($row['foto']) ?>"
                                                alt="<?= htmlspecialchars($row['nama_barang']) ?>"
                                                class="rounded"
                                                style="width:48px; height:48px; object-fit:cover; cursor:pointer;"
                                                onclick="lihatFoto('../uploads/barang/<?= htmlspecialchars($row['foto']) ?>', '<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>')">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                                style="width:48px; height:48px;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3"><code><?= htmlspecialchars($row['kode_barang']) ?></code></td>
                                    <td class="px-3 fw-semibold">
                                        <a href="detail_barang.php?id=<?= $row['id_barang'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['nama_barang']) ?>
                                        </a>
                                    </td>
                                    <td class="px-3">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3">
                                            <?= htmlspecialchars($row['nama_kategori'] ?? '-') ?>
                                        </span>
                                    </td>
                                    <td class="px-3"><?= (int) $row['stok'] ?></td>
                                    <td class="px-3">
                                        <span class="badge bg-<?= $badge_color ?> bg-opacity-10 text-<?= $badge_color ?> rounded-pill px-3">
                                            <?= $stok_tersedia ?> — <?= htmlspecialchars($status_stok) ?>
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        <span class="badge bg-<?= $kondisi_color ?> bg-opacity-10 text-<?= $kondisi_color ?> rounded-pill px-3">
                                            <?= htmlspecialchars($row['kondisi']) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 text-end">
                                        <a href="detail_barang.php?id=<?= $row['id_barang'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button
                                            class="btn btn-sm btn-outline-primary me-1"
                                            onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="barang.php?hapus=<?= $row['id_barang'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return konfirmasiHapus('<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile;
                        else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                    <?= ($search !== '' || $filter_kat > 0) ? 'Tidak ada barang yang cocok.' : 'Belum ada data barang.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <?php
            $query_params = [
                'search'   => $search,
                'kategori' => $filter_kat ?: '',
            ];
            ?>
            <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-3 px-4">
                <small class="text-muted">
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> barang
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($query_params, ['page' => $page + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-plus-circle me-2"></i>Tambah Barang
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="tambah_barang.php" enctype="multipart/form-data" id="formTambah">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Kode Barang <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_barang" id="tambahKode" placeholder="contoh: ELK-001">
                            <div class="invalid-feedback" id="errTambahKode"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_barang" id="tambahNama" placeholder="contoh: Laptop Lenovo">
                            <div class="invalid-feedback" id="errTambahNama"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_kategori" id="tambahKategori">
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategori_list as $k): ?>
                                    <option value="<?= $k['id_kategori'] ?>">
                                        <?= htmlspecialchars($k['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="errTambahKategori"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="stok" id="tambahStok" min="0" value="0">
                            <div class="invalid-feedback" id="errTambahStok"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Kondisi</label>
                            <select class="form-select" name="kondisi">
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Foto Barang</label>
                            <input type="file" class="form-control" name="foto" accept="image/*" onchange="previewFoto(this, 'previewTambah')">
                            <div class="mt-2" id="previewTambah"></div>
                            <small class="text-muted">Format: JPG, PNG, WEBP. Maks 2MB.</small>
                        </div>
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

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-pencil-square me-2"></i>Edit Barang
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="edit_barang.php" enctype="multipart/form-data" id="formEdit">
                <div class="modal-body">
                    <input type="hidden" name="id_barang" id="editId">
                    <input type="hidden" name="foto_lama" id="editFotoLama">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Kode Barang <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="kode_barang" id="editKode">
                            <div class="invalid-feedback" id="errEditKode"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_barang" id="editNama">
                            <div class="invalid-feedback" id="errEditNama"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_kategori" id="editKategori">
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategori_list as $k): ?>
                                    <option value="<?= $k['id_kategori'] ?>">
                                        <?= htmlspecialchars($k['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="errEditKategori"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="stok" id="editStok" min="0">
                            <div class="invalid-feedback" id="errEditStok"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Kondisi</label>
                            <select class="form-select" name="kondisi" id="editKondisi">
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Foto Barang</label>
                            <div id="fotoLamaPreview" class="mb-2"></div>
                            <input type="file" class="form-control" name="foto" accept="image/*" onchange="previewFoto(this, 'previewEdit')">
                            <div class="mt-2" id="previewEdit"></div>
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti foto.</small>
                        </div>
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

<!-- Modal Lihat Foto -->
<div class="modal fade" id="modalFoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" id="modalFotoTitle" style="color:#002645;"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img id="modalFotoImg" src="" alt="" class="img-fluid rounded" style="max-height:400px;">
            </div>
        </div>
    </div>
</div>

</div><!-- end #main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle
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

    // Buka modal tambah
    function bukaModalTambah() {
        document.getElementById('formTambah').reset();
        document.getElementById('previewTambah').innerHTML = '';
        new bootstrap.Modal(document.getElementById('modalTambah')).show();
    }

    // Buka modal edit + isi data
    function bukaModalEdit(data) {
        document.getElementById('editId').value = data.id_barang;
        document.getElementById('editKode').value = data.kode_barang;
        document.getElementById('editNama').value = data.nama_barang;
        document.getElementById('editStok').value = data.stok;
        document.getElementById('editFotoLama').value = data.foto ?? '';
        document.getElementById('previewEdit').innerHTML = '';

        // Set kondisi
        const kondisiSelect = document.getElementById('editKondisi');
        for (let opt of kondisiSelect.options) {
            opt.selected = opt.value === data.kondisi;
        }

        // Set kategori
        const katSelect = document.getElementById('editKategori');
        for (let opt of katSelect.options) {
            opt.selected = opt.value == data.id_kategori;
        }

        // Tampilkan foto lama
        const fotoPreview = document.getElementById('fotoLamaPreview');
        if (data.foto) {
            fotoPreview.innerHTML = `
                <img src="../uploads/barang/${data.foto}"
                     class="rounded border" style="height:60px; width:60px; object-fit:cover;">
                <small class="text-muted ms-2">Foto saat ini</small>`;
        } else {
            fotoPreview.innerHTML = '<small class="text-muted">Belum ada foto</small>';
        }

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // Preview foto sebelum upload
    function previewFoto(input, targetId) {
        const target = document.getElementById(targetId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                target.innerHTML = `<img src="${e.target.result}" class="rounded border"
                    style="height:80px; width:80px; object-fit:cover;">
                    <small class="text-muted ms-2">Preview foto baru</small>`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Lihat foto fullsize
    function lihatFoto(src, nama) {
        document.getElementById('modalFotoImg').src = src;
        document.getElementById('modalFotoTitle').textContent = nama;
        new bootstrap.Modal(document.getElementById('modalFoto')).show();
    }

    // Konfirmasi hapus
    function konfirmasiHapus(nama) {
        return confirm('Hapus barang "' + nama + '"?\\nData akan dipindahkan ke Recycle Bin.');
    }

    // Validasi form tambah
    document.getElementById('formTambah').addEventListener('submit', function(e) {
        let valid = true;
        const fields = [
            { id: 'tambahKode', errId: 'errTambahKode', msg: 'Kode barang wajib diisi.' },
            { id: 'tambahNama', errId: 'errTambahNama', msg: 'Nama barang wajib diisi.' },
            { id: 'tambahKategori', errId: 'errTambahKategori', msg: 'Pilih kategori terlebih dahulu.' },
            { id: 'tambahStok', errId: 'errTambahStok', msg: 'Stok tidak boleh kosong.' },
        ];
        fields.forEach(f => {
            const el = document.getElementById(f.id);
            const err = document.getElementById(f.errId);
            el.classList.remove('is-invalid');
            err.textContent = '';
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                err.textContent = f.msg;
                valid = false;
            }
        });
        if (!valid) e.preventDefault();
    });

    // Validasi form edit
    document.getElementById('formEdit').addEventListener('submit', function(e) {
        let valid = true;
        const fields = [
            { id: 'editKode', errId: 'errEditKode', msg: 'Kode barang wajib diisi.' },
            { id: 'editNama', errId: 'errEditNama', msg: 'Nama barang wajib diisi.' },
            { id: 'editKategori', errId: 'errEditKategori', msg: 'Pilih kategori terlebih dahulu.' },
            { id: 'editStok', errId: 'errEditStok', msg: 'Stok tidak boleh kosong.' },
        ];
        fields.forEach(f => {
            const el = document.getElementById(f.id);
            const err = document.getElementById(f.errId);
            el.classList.remove('is-invalid');
            err.textContent = '';
            if (!el.value.trim()) {
                el.classList.add('is-invalid');
                err.textContent = f.msg;
                valid = false;
            }
        });
        if (!valid) e.preventDefault();
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
<?php require_once '../includes/footer.php'; ?>