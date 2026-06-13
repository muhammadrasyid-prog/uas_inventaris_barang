<?php
require_once '../includes/config.php';
requireLogin();

// Proteksi agar hanya peminjam yang bisa masuk
if ($_SESSION['role'] !== 'peminjam') {
    header("Location: ../admin/dashboard.php");
    exit();
}

$page_title   = "Dashboard Peminjam — Inventaris Barang";
$current_page = basename(__FILE__);
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$user_id = (int) $_SESSION['user_id'];

// Hitung statistik personal
$stat_dipinjam = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM peminjaman 
    WHERE id_user = $user_id AND status = 'dipinjam'
"))['total'];

$stat_dikembalikan = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM peminjaman 
    WHERE id_user = $user_id AND status = 'dikembalikan'
"))['total'];

$stat_terlambat = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM peminjaman 
    WHERE id_user = $user_id AND status = 'dipinjam' AND tanggal_kembali_rencana < CURDATE()
"))['total'];

// Ambil daftar pinjaman aktif peminjam
$query_aktif = "
    SELECT p.*, b.nama_barang, b.kode_barang, b.foto 
    FROM peminjaman p
    JOIN barang b ON p.id_barang = b.id_barang
    WHERE p.id_user = $user_id AND p.status = 'dipinjam'
    ORDER BY p.tanggal_kembali_rencana ASC
";
$result_aktif = mysqli_query($conn, $query_aktif);
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Dashboard</h5>
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

    <!-- Alert Modal Login Success -->
    <?php
    $modal_data = null;
    if (isset($_GET['login']) && $_GET['login'] === 'success') {
        $modal_data = ['success', 'bi-person-check-fill', 'Selamat datang kembali, ' . htmlspecialchars($_SESSION['nama_lengkap']) . '! Anda berhasil login sebagai Peminjam.'];
    }
    ?>
    <?php include_once '../includes/alert_modal.php'; ?>

    <!-- Ucapan Selamat Datang -->
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #002645 0%, #013b6b 100%); color: white;">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h4>
            <p class="mb-0 opacity-75">Silakan pilih menu katalog barang untuk melakukan peminjaman inventaris yang Anda butuhkan.</p>
        </div>
    </div>

    <!-- Statistik Widget Cards -->
    <div class="row g-3 mb-4">
        <!-- Sedang Dipinjam -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-box-arrow-right fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-1">Sedang Dipinjam</h6>
                        <h3 class="fw-bold mb-0" style="color: #002645;"><?= $stat_dipinjam ?> <span class="fs-6 fw-normal text-muted">barang</span></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terlambat Kembali -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-1">Terlambat Kembali</h6>
                        <h3 class="fw-bold mb-0 text-danger"><?= $stat_terlambat ?> <span class="fs-6 fw-normal text-muted">barang</span></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sudah Dikembalikan -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3 p-4">
                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-1">Sudah Dikembalikan</h6>
                        <h3 class="fw-bold mb-0 text-success"><?= $stat_dikembalikan ?> <span class="fs-6 fw-normal text-muted">transaksi</span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Peminjaman Aktif -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="fw-bold mb-0" style="color: #002645;">Peminjaman Barang Aktif</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Foto</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Kode Barang</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Barang</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Tanggal Pinjam</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Deadline Kembali</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_aktif) > 0):
                            $no = 1;
                            while ($row = mysqli_fetch_assoc($result_aktif)): 
                                $is_overdue = strtotime($row['tanggal_kembali_rencana']) < strtotime(date('Y-m-d'));
                                $status_badge = $is_overdue ? 'danger' : 'primary';
                                $status_text = $is_overdue ? 'Terlambat' : 'Dipinjam';
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
                            <td class="px-4 text-muted"><?= date('d M Y', strtotime($row['tanggal_pinjam'])) ?></td>
                            <td class="px-4 text-muted fw-semibold <?= $is_overdue ? 'text-danger' : '' ?>">
                                <?= date('d M Y', strtotime($row['tanggal_kembali_rencana'])) ?>
                                <?php if ($is_overdue): ?>
                                    <span class="badge bg-danger rounded-pill ms-1" style="font-size: 10px;">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4">
                                <span class="badge bg-<?= $status_badge ?> bg-opacity-10 text-<?= $status_badge ?> rounded-pill px-3">
                                    <?= $status_text ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile;
                        else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-check-lg text-success fs-3 d-block mb-2"></i>
                                Anda tidak sedang meminjam barang apapun saat ini.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
