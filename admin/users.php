<?php
require_once '../includes/config.php';
requireAdmin();

$page_title   = "Kelola User";
$current_page = basename(__FILE__);

// PROSES TAMBAH
if (isset($_POST['tambah'])) {
    $nama     = htmlspecialchars(trim($_POST['nama_lengkap']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $role     = htmlspecialchars($_POST['role']);

    if (!empty($nama) && !empty($username) && !empty($password) && !empty($role)) {
        // Cek username sudah digunakan atau belum
        $cek = mysqli_query($conn, "SELECT id_user FROM users WHERE username = '$username'");
        if (mysqli_num_rows($cek) > 0) {
            $error_message = "Username sudah digunakan!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (nama_lengkap, username, password, role) 
                      VALUES ('$nama', '$username', '$hashed_password', '$role')";
            if (mysqli_query($conn, $query)) {
                header("Location: users.php?status=tambah");
                exit();
            } else {
                $error_message = "Gagal menambahkan user baru.";
            }
        }
    }
}

// PROSES EDIT
if (isset($_POST['edit'])) {
    $id       = (int) $_POST['id_user'];
    $nama     = htmlspecialchars(trim($_POST['nama_lengkap']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $role     = htmlspecialchars($_POST['role']);

    if (!empty($nama) && !empty($username) && !empty($role) && $id > 0) {
        // Cek apakah username dipakai user lain
        $cek = mysqli_query($conn, "SELECT id_user FROM users WHERE username = '$username' AND id_user != $id");
        if (mysqli_num_rows($cek) > 0) {
            $error_message = "Username sudah digunakan oleh user lain!";
        } else {
            // Jika ganti password
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE users 
                          SET nama_lengkap = '$nama', username = '$username', password = '$hashed_password', role = '$role' 
                          WHERE id_user = $id";
            } else {
                $query = "UPDATE users 
                          SET nama_lengkap = '$nama', username = '$username', role = '$role' 
                          WHERE id_user = $id";
            }

            if (mysqli_query($conn, $query)) {
                header("Location: users.php?status=edit");
                exit();
            } else {
                $error_message = "Gagal memperbarui user.";
            }
        }
    }
}

// PROSES HAPUS
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    // Cegah admin menghapus dirinya sendiri yang sedang login
    if ($id === (int)$_SESSION['user_id']) {
        $error_message = "Anda tidak bisa menghapus akun Anda sendiri yang sedang aktif!";
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id_user = $id");
        header("Location: users.php?status=hapus");
        exit();
    }
}

// SEARCH & AMBIL DATA
$search = htmlspecialchars(trim($_GET['search'] ?? ''));
$where  = "WHERE 1=1";
if ($search) {
    $where .= " AND (nama_lengkap LIKE '%$search%' OR username LIKE '%$search%')";
}

// Pagination
$per_page    = 10;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users $where"))['total'];
$total_pages = ceil($total_rows / $per_page);

$result = mysqli_query($conn, "SELECT * FROM users $where ORDER BY id_user ASC LIMIT $per_page OFFSET $offset");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="topbar-title">Kelola User</h5>
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
            'tambah' => ['success', 'bi-check-circle', 'User berhasil ditambahkan.'],
            'edit'   => ['success', 'bi-check-circle', 'User berhasil diperbarui.'],
            'hapus'  => ['warning', 'bi-trash',        'User berhasil dihapus.'],
        ];
        $modal_data = $messages[$_GET['status']] ?? null;
    } elseif (isset($error_message)) {
        $modal_data = ['danger', 'bi-exclamation-triangle-fill', $error_message];
    }
    ?>
    <?php include_once '../includes/alert_modal.php'; ?>

    <!-- Toolbar: Search & Tambah -->
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
                            placeholder="Cari nama atau username..."
                            value="<?= $search ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <?php if ($search): ?>
                        <a href="users.php" class="btn btn-outline-secondary ms-1">
                            <i class="bi bi-x-lg"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-auto ms-md-auto">
                    <button type="button" class="btn btn-primary" onclick="bukaModalTambah()">
                        <i class="bi bi-plus-lg me-1"></i> Tambah User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0" style="color:#002645;">Daftar Akun User</h6>
            <span class="badge bg-primary rounded-pill"><?= $total_rows ?> user</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">No</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Nama Lengkap</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Username</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold">Role</th>
                            <th class="px-4 py-3 small text-uppercase text-muted fw-semibold text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0):
                            $no = $offset + 1;
                            while ($row = mysqli_fetch_assoc($result)): 
                                $badge_role = $row['role'] === 'admin' ? 'danger' : 'primary';
                        ?>
                        <tr>
                            <td class="px-4"><?= $no++ ?></td>
                            <td class="px-4 fw-semibold"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                            <td class="px-4"><code><?= htmlspecialchars($row['username']) ?></code></td>
                            <td class="px-4">
                                <span class="badge bg-<?= $badge_role ?> bg-opacity-10 text-<?= $badge_role ?> rounded-pill px-3">
                                    <?= ucfirst($row['role']) ?>
                                </span>
                            </td>
                            <td class="px-4 text-end">
                                <!-- Tombol Edit -->
                                <button
                                    class="btn btn-sm btn-outline-primary me-1"
                                    onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <!-- Tombol Hapus -->
                                <?php if ($row['id_user'] !== (int)$_SESSION['user_id']): ?>
                                <a
                                    href="users.php?hapus=<?= $row['id_user'] ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return konfirmasiHapus('<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')"
                                >
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled title="Akun Anda sedang aktif">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile;
                        else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                <?= $search ? 'Tidak ada user yang cocok.' : 'Belum ada data user.' ?>
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
                    Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> user
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

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-plus-circle me-2"></i>Tambah User
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formTambah">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_lengkap" id="tambahNama" placeholder="contoh: Ahmad Subarjo">
                        <div class="invalid-feedback" id="errTambahNama"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="tambahUsername" placeholder="contoh: ahmad123">
                        <div class="invalid-feedback" id="errTambahUsername"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="tambahPassword" placeholder="Masukkan password baru">
                        <div class="invalid-feedback" id="errTambahPassword"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="tambahRole">
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="peminjam">Peminjam</option>
                        </select>
                        <div class="invalid-feedback" id="errTambahRole"></div>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold" style="color:#002645;">
                    <i class="bi bi-pencil-square me-2"></i>Edit User
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formEdit">
                <div class="modal-body">
                    <input type="hidden" name="id_user" id="editId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_lengkap" id="editNama">
                        <div class="invalid-feedback" id="errEditNama"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="editUsername">
                        <div class="invalid-feedback" id="errEditUsername"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password</label>
                        <input type="password" class="form-control" name="password" id="editPassword" placeholder="Kosongkan jika tidak diganti">
                        <div class="invalid-feedback" id="errEditPassword"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="editRole">
                            <option value="admin">Admin</option>
                            <option value="peminjam">Peminjam</option>
                        </select>
                        <div class="invalid-feedback" id="errEditRole"></div>
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

    // Buka modal tambah
    function bukaModalTambah() {
        document.getElementById('formTambah').reset();
        new bootstrap.Modal(document.getElementById('modalTambah')).show();
    }

    // Buka modal edit + isi data
    function bukaModalEdit(data) {
        document.getElementById('editId').value = data.id_user;
        document.getElementById('editNama').value = data.nama_lengkap;
        document.getElementById('editUsername').value = data.username;
        document.getElementById('editPassword').value = '';
        
        const roleSelect = document.getElementById('editRole');
        for(let opt of roleSelect.options) {
            opt.selected = opt.value === data.role;
        }

        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // Konfirmasi hapus
    function konfirmasiHapus(nama) {
        return confirm('Hapus akun "' + nama + '"?\nData peminjaman terkait user ini juga akan terhapus!');
    }

    // Validasi form tambah
    document.getElementById('formTambah').addEventListener('submit', function (e) {
        let valid = true;
        const fields = [
            { id: 'tambahNama',     errId: 'errTambahNama',     msg: 'Nama lengkap wajib diisi.' },
            { id: 'tambahUsername', errId: 'errTambahUsername', msg: 'Username wajib diisi.' },
            { id: 'tambahPassword', errId: 'errTambahPassword', msg: 'Password wajib diisi.' },
            { id: 'tambahRole',     errId: 'errTambahRole',     msg: 'Pilih role user.' }
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
    document.getElementById('formEdit').addEventListener('submit', function (e) {
        let valid = true;
        const fields = [
            { id: 'editNama',     errId: 'errEditNama',     msg: 'Nama lengkap wajib diisi.' },
            { id: 'editUsername', errId: 'errEditUsername', msg: 'Username wajib diisi.' },
            { id: 'editRole',     errId: 'errEditRole',     msg: 'Pilih role user.' }
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


</script>
</body>
</html>
