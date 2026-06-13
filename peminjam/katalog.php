<?php
require_once '../includes/config.php';
requireLogin();

if ($_SESSION['role'] !== 'peminjam') {
    header("Location: ../admin/dashboard.php");
    exit();
}

// Ambil pengaturan sistem
$setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan WHERE id = 1"));
$MAX_PINJAM = $setting['max_pinjam_per_user'] ?? 5;
$MAX_HARI = $setting['max_hari_pinjam'] ?? 7;

$page_title   = "Katalog Barang — Inventaris Barang";
$current_page = basename(__FILE__);

// ===== PROSES PEMINJAMAN =====
if (isset($_POST['pinjam'])) {
    $id_barang       = (int) $_POST['id_barang'];
    $jumlah          = (int) $_POST['jumlah'];
    $tgl_pinjam      = mysqli_real_escape_string($conn, $_POST['tanggal_pinjam']);
    $tgl_kembali_rec = mysqli_real_escape_string($conn, $_POST['tanggal_kembali_rencana']);
    $user_id = (int) $_SESSION['user_id'];

    // Validasi jumlah minimal 1
    if ($jumlah < 1) {
        $_SESSION['error'] = "Jumlah peminjaman minimal 1 barang.";
        header("Location: katalog.php");
        exit();
    }

    // 1. CEK APAKAH USER SEDANG PINJAM BARANG YANG SAMA
    $cek_sama = mysqli_query($conn, "
        SELECT SUM(jumlah) as total_dipinjam
        FROM peminjaman 
        WHERE id_user = $user_id 
          AND id_barang = $id_barang 
          AND status = 'dipinjam'
    ");
    $sudah_pinjam = mysqli_fetch_assoc($cek_sama);
    $total_dipinjam = $sudah_pinjam['total_dipinjam'] ?? 0;
    
    if ($total_dipinjam > 0) {
        $_SESSION['error'] = "⚠️ Anda sudah meminjam barang ini sebanyak $total_dipinjam unit. Silakan kembalikan terlebih dahulu sebelum meminjam lagi.";
        header("Location: katalog.php");
        exit();
    }
    
    // 2. CEK TOTAL PINJAMAN AKTIF USER (BATAS MAKSIMAL)
    $cek_total = mysqli_query($conn, "
        SELECT SUM(jumlah) as total_barang 
        FROM peminjaman 
        WHERE id_user = $user_id AND status = 'dipinjam'
    ");
    $total_pinjam = mysqli_fetch_assoc($cek_total)['total_barang'] ?? 0;
    
    if (($total_pinjam + $jumlah) > $MAX_PINJAM) {
        $_SESSION['error'] = "⚠️ Batas maksimal peminjaman adalah $MAX_PINJAM barang. Anda sudah meminjam $total_pinjam barang, tidak bisa menambah $jumlah barang lagi.";
        header("Location: katalog.php");
        exit();
    }
    
    // 3. CEK STOK
    $query_cek = "SELECT stok_tersedia, nama_barang FROM view_katalog_barang WHERE id_barang = $id_barang";
    $res_cek = mysqli_query($conn, $query_cek);
    
    if (mysqli_num_rows($res_cek) == 1) {
        $barang = mysqli_fetch_assoc($res_cek);
        if ($barang['stok_tersedia'] < $jumlah) {
            $_SESSION['error'] = "⚠️ Stok tidak mencukupi! Stok '{$barang['nama_barang']}' tersisa {$barang['stok_tersedia']} unit, Anda meminta $jumlah unit.";
            header("Location: katalog.php");
            exit();
        }
        
        // Proses insert peminjaman
        $query_insert = "INSERT INTO peminjaman (id_barang, jumlah, id_user, tanggal_pinjam, tanggal_kembali_rencana, status) 
                         VALUES ($id_barang, $jumlah, $user_id, '$tgl_pinjam', '$tgl_kembali_rec', 'dipinjam')";
        
        if (mysqli_query($conn, $query_insert)) {
            header("Location: riwayat.php?status=pinjam_sukses");
            exit();
        } else {
            $_SESSION['error'] = "Gagal memproses peminjaman: " . mysqli_error($conn);
            header("Location: katalog.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Barang tidak ditemukan.";
        header("Location: katalog.php");
        exit();
    }
}

// Ambil error dari session
$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

// FILTER & SEARCH
$search      = htmlspecialchars(trim($_GET['search'] ?? ''));
$id_kategori = (int) ($_GET['kategori'] ?? 0);

$where = "WHERE 1=1";
if ($search) {
    $search_like = mysqli_real_escape_string($conn, $search);
    $where .= " AND (nama_barang LIKE '%$search_like%' OR kode_barang LIKE '%$search_like%')";
}
if ($id_kategori > 0) {
    $where .= " AND id_barang IN (SELECT id_barang FROM barang WHERE id_kategori = $id_kategori)";
}

// Pagination
$per_page    = 8;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM view_katalog_barang $where"))['total'];
$total_pages = ceil($total_rows / $per_page);

// Query utama
$query = "
    SELECT *, cek_status_stok(stok_tersedia) AS status_stok 
    FROM view_katalog_barang
    $where 
    ORDER BY nama_barang ASC 
    LIMIT $per_page OFFSET $offset
";
$result = mysqli_query($conn, $query);

// Kategori List untuk filter
$kategori_list = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Include header dan sidebar
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Katalog Barang</h5>
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

    <!-- Alert Info Batasan -->
    <div class="alert alert-info alert-dismissible d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-info-circle"></i>
        <span>
            <strong>Aturan Peminjaman:</strong> 
            Maksimal <strong><?= $MAX_PINJAM ?></strong> barang sekaligus | 
            Maksimal pinjam <strong><?= $MAX_HARI ?></strong> hari
        </span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Alert Error Modal -->
    <?php
    $modal_data = null;
    if ($error_message) {
        $modal_data = ['danger', 'bi-exclamation-triangle-fill', $error_message];
    }
    ?>
    <?php include_once '../includes/alert_modal.php'; ?>

    <!-- Toolbar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" name="search"
                               placeholder="Cari nama atau kode barang..." value="<?= $search ?>">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select class="form-select" name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php mysqli_data_seek($kategori_list, 0);
                        while ($kat = mysqli_fetch_assoc($kategori_list)): ?>
                            <option value="<?= $kat['id_kategori'] ?>" <?= $id_kategori == $kat['id_kategori'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat['nama_kategori']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if ($search || $id_kategori): ?>
                        <a href="katalog.php" class="btn btn-outline-secondary ms-1">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid Card Katalog -->
    <div class="row g-3 mb-4">
        <?php if (mysqli_num_rows($result) > 0):
            while ($row = mysqli_fetch_assoc($result)): 
                $kondisi_color = match ($row['kondisi']) {
                    'Rusak Ringan' => 'warning',
                    'Rusak Berat'  => 'danger',
                    default        => 'success'
                };
                
                $stok_badge = match ($row['status_stok']) {
                    'Habis'    => 'danger',
                    'Menipis'  => 'warning',
                    default    => 'success'
                };
        ?>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="position-relative" style="height: 180px; background-color: #f8f9fa;">
                    <?php if ($row['foto'] && file_exists("../uploads/barang/" . $row['foto'])): ?>
                        <img src="../uploads/barang/<?= htmlspecialchars($row['foto']) ?>" 
                             class="card-img-top w-100 h-100" style="object-fit: cover;" 
                             alt="<?= htmlspecialchars($row['nama_barang']) ?>">
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                            <i class="bi bi-box fs-1"></i>
                            <span class="small mt-1">Tidak ada foto</span>
                        </div>
                    <?php endif; ?>
                    <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75 rounded-pill py-1 px-3">
                        <?= htmlspecialchars($row['nama_kategori'] ?? 'Uncategorized') ?>
                    </span>
                </div>
                <div class="card-body p-3 d-flex flex-column flex-grow-1">
                    <code class="small text-muted mb-1 d-block"><?= htmlspecialchars($row['kode_barang']) ?></code>
                    <h6 class="card-title fw-bold text-truncate mb-2" style="color: #002645;" 
                        title="<?= htmlspecialchars($row['nama_barang']) ?>">
                        <?= htmlspecialchars($row['nama_barang']) ?>
                    </h6>
                    
                    <div class="d-flex align-items-center justify-content-between mt-auto pt-2 mb-3">
                        <span class="badge bg-<?= $kondisi_color ?> bg-opacity-10 text-<?= $kondisi_color ?> rounded-pill px-3 py-1">
                            <?= $row['kondisi'] ?>
                        </span>
                        <span class="badge bg-<?= $stok_badge ?> rounded-pill py-1 px-3" title="Stok Tersedia">
                            Stok: <?= $row['stok_tersedia'] ?>
                        </span>
                    </div>

                    <?php if ($row['stok_tersedia'] > 0): ?>
                        <button type="button" class="btn btn-primary w-100 mt-auto" 
                                onclick="bukaModalPinjam(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">
                            <i class="bi bi-clipboard-plus me-1"></i> Pinjam Barang
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-auto" disabled>
                            <i class="bi bi-x-circle me-1"></i> Stok Habis
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile;
        else: ?>
        <div class="col-12 py-5 text-center text-muted">
            <i class="bi bi-search fs-1 d-block mb-3 text-muted"></i>
            <h5>Barang Tidak Ditemukan</h5>
            <p class="small">Silakan cari menggunakan kata kunci lain atau pilih kategori berbeda.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">
                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> barang
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= $search ?>&kategori=<?= $id_kategori ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= $search ?>&kategori=<?= $id_kategori ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= $search ?>&kategori=<?= $id_kategori ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Pinjam -->
<div class="modal fade" id="modalPinjam" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color: #002645;">
                    <i class="bi bi-clipboard-plus me-2"></i>Form Pengajuan Pinjam
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPinjam">
                <div class="modal-body">
                    <!-- Detail Barang -->
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded mb-3 border">
                        <div id="pinjamFoto" style="width: 60px; height: 60px;"></div>
                        <div>
                            <span class="badge bg-secondary mb-1" id="pinjamKategori"></span>
                            <h6 class="fw-bold mb-0 text-dark" id="pinjamNama"></h6>
                            <code class="small text-muted" id="pinjamKode"></code>
                        </div>
                    </div>
                    
                    <input type="hidden" name="id_barang" id="pinjamId">

                    <!-- JUMLAH YANG DIPINJAM -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Jumlah yang Dipinjam <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary" onclick="kurangJumlah()">
                                <i class="bi bi-dash"></i>
                            </button>
                            <input type="number" class="form-control text-center" name="jumlah" 
                                   id="pinjamJumlah" value="1" min="1" style="max-width: 80px;" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="tambahJumlah()">
                                <i class="bi bi-plus"></i>
                            </button>
                            <span class="input-group-text">unit</span>
                        </div>
                        <small class="text-muted" id="infoStokTersedia"></small>
                        <div class="invalid-feedback" id="errPinjamJumlah"></div>
                    </div>

                    <!-- Tanggal Pinjam -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Tanggal Pinjam</label>
                        <input type="date" class="form-control" name="tanggal_pinjam" 
                               id="pinjamTglMulai" value="<?= date('Y-m-d') ?>" readonly>
                        <small class="text-muted">Peminjaman tercatat dimulai hari ini.</small>
                    </div>

                    <!-- Tanggal Kembali Rencana -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Rencana Tanggal Pengembalian <span class="text-danger">*</span>
                            <span class="badge bg-info ms-2">Maksimal <?= $MAX_HARI ?> hari</span>
                        </label>
                        <input type="date" class="form-control" name="tanggal_kembali_rencana" id="pinjamTglKembali">
                        <div class="invalid-feedback" id="errPinjamTglKembali"></div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="pinjam" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi Pinjam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Variabel global untuk stok
    let stokTersedia = 0;
    let maxPinjam = 0;

    // Fungsi kurang jumlah
    function kurangJumlah() {
        let input = document.getElementById('pinjamJumlah');
        let val = parseInt(input.value);
        if (val > 1) {
            input.value = val - 1;
            // Trigger validasi
            validateJumlah();
        }
    }

    // Fungsi tambah jumlah
    function tambahJumlah() {
        let input = document.getElementById('pinjamJumlah');
        let val = parseInt(input.value);
        if (val < maxPinjam) {
            input.value = val + 1;
            // Trigger validasi
            validateJumlah();
        }
    }

    // Validasi jumlah
    function validateJumlah() {
        let input = document.getElementById('pinjamJumlah');
        let val = parseInt(input.value);
        let err = document.getElementById('errPinjamJumlah');
        
        if (val > stokTersedia) {
            input.classList.add('is-invalid');
            err.textContent = `Jumlah melebihi stok yang tersedia (${stokTersedia} unit)`;
            return false;
        }
        if (val < 1) {
            input.classList.add('is-invalid');
            err.textContent = `Jumlah minimal 1 unit`;
            return false;
        }
        input.classList.remove('is-invalid');
        err.textContent = '';
        return true;
    }

    // Buka Modal Pinjam
    function bukaModalPinjam(barang) {
        // Set data barang
        document.getElementById('pinjamId').value = barang.id_barang;
        document.getElementById('pinjamNama').textContent = barang.nama_barang;
        document.getElementById('pinjamKode').textContent = barang.kode_barang;
        document.getElementById('pinjamKategori').textContent = barang.nama_kategori ?? 'Uncategorized';
        
        // Set stok
        stokTersedia = barang.stok_tersedia;
        maxPinjam = stokTersedia;
        
        // Reset dan set jumlah
        const jumlahInput = document.getElementById('pinjamJumlah');
        jumlahInput.value = 1;
        jumlahInput.max = stokTersedia;
        jumlahInput.min = 1;
        
        // Tampilkan info stok
        document.getElementById('infoStokTersedia').innerHTML = `Stok tersedia: <strong>${stokTersedia}</strong> unit`;
        
        // Reset validasi jumlah
        jumlahInput.classList.remove('is-invalid');
        document.getElementById('errPinjamJumlah').textContent = '';
        
        // Set foto
        const divFoto = document.getElementById('pinjamFoto');
        if (barang.foto) {
            divFoto.innerHTML = `<img src="../uploads/barang/${barang.foto}" class="rounded w-100 h-100 border" style="object-fit: cover;">`;
        } else {
            divFoto.innerHTML = `<div class="rounded bg-white border w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-box"></i></div>`;
        }

        // Set tanggal kembali (minimal besok, maksimal sesuai pengaturan)
        const hariIni = new Date();
        hariIni.setDate(hariIni.getDate() + 1);
        const yyyy = hariIni.getFullYear();
        const mm = String(hariIni.getMonth() + 1).padStart(2, '0');
        const dd = String(hariIni.getDate()).padStart(2, '0');
        document.getElementById('pinjamTglKembali').min = `${yyyy}-${mm}-${dd}`;
        
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + <?= $MAX_HARI ?>);
        const maxYyyy = maxDate.getFullYear();
        const maxMm = String(maxDate.getMonth() + 1).padStart(2, '0');
        const maxDd = String(maxDate.getDate()).padStart(2, '0');
        document.getElementById('pinjamTglKembali').max = `${maxYyyy}-${maxMm}-${maxDd}`;
        
        // Reset tanggal kembali
        document.getElementById('pinjamTglKembali').value = '';
        document.getElementById('pinjamTglKembali').classList.remove('is-invalid');
        document.getElementById('errPinjamTglKembali').textContent = '';

        // Tampilkan modal
        new bootstrap.Modal(document.getElementById('modalPinjam')).show();
    }

    // Validasi form sebelum submit
    document.getElementById('formPinjam').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validasi jumlah
        if (!validateJumlah()) {
            isValid = false;
        }
        
        // Validasi tanggal kembali
        const tglKembali = document.getElementById('pinjamTglKembali');
        const errTgl = document.getElementById('errPinjamTglKembali');
        
        if (!tglKembali.value) {
            tglKembali.classList.add('is-invalid');
            errTgl.textContent = 'Tanggal pengembalian wajib ditentukan.';
            isValid = false;
        } else {
            const tglMulai = new Date(document.getElementById('pinjamTglMulai').value);
            const tglKembaliVal = new Date(tglKembali.value);
            
            if (tglKembaliVal <= tglMulai) {
                tglKembali.classList.add('is-invalid');
                errTgl.textContent = 'Tanggal kembali harus setelah tanggal pinjam (minimal besok).';
                isValid = false;
            } else {
                tglKembali.classList.remove('is-invalid');
                errTgl.textContent = '';
            }
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });


</script>
<?php require_once '../includes/footer.php'; ?>