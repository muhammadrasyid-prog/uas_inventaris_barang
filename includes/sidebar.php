<?php requireLogin(); ?>

<!-- Backdrop mobile -->
<div id="sidebar-backdrop" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-box-seam-fill"></i>
        </div>
        <span class="sidebar-brand-text">Sistem Inventaris Barang</span>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="barang.php" class="<?= $current_page === 'barang.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Data Barang
            </a>
            <a href="kategori.php" class="<?= $current_page === 'kategori.php' ? 'active' : '' ?>">
                <i class="bi bi-tag"></i> Kategori
            </a>
            <a href="peminjaman.php" class="<?= $current_page === 'peminjaman.php' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-check"></i> Data Peminjaman
            </a>
            <a href="users.php" class="<?= $current_page === 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Kelola User
            </a>
            <a href="log_perubahan.php" class="<?= $current_page === 'log_perubahan.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Log Perubahan
            </a>
            <a href="recycle_bin.php" class="<?= $current_page === 'recycle_bin.php' ? 'active' : '' ?>">
                <i class="bi bi-trash"></i> Recycle Bin
            </a>
            <a href="pengaturan.php" class="<?= $current_page == 'pengaturan.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Pengaturan</span>
            </a>
        <?php else: ?>
            <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="katalog.php" class="<?= $current_page === 'katalog.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-album"></i> Katalog Barang
            </a>
            <a href="riwayat.php" class="<?= $current_page === 'riwayat.php' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Riwayat Pinjam
            </a>
        <?php endif; ?>
    </nav>

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="../auth/logout.php" onclick="return confirm('Yakin mau logout?')">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>

</aside>

<!-- Main Content Wrapper — DIBUKA di sini, DITUTUP di footer tiap halaman -->
<div id="main-content">