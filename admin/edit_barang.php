<?php
require_once '../includes/config.php';
requireAdmin();

// File ini HANYA proses POST edit barang, tidak ada output HTML.

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['edit'])) {
    header("Location: barang.php");
    exit();
}

$id        = (int) ($_POST['id_barang'] ?? 0);
$kode      = trim($_POST['kode_barang'] ?? '');
$nama      = trim($_POST['nama_barang'] ?? '');
$id_kat    = (int) ($_POST['id_kategori'] ?? 0);
$stok      = (int) ($_POST['stok'] ?? 0);
$kondisi   = $_POST['kondisi'] ?? 'Baik';
$foto_lama = trim($_POST['foto_lama'] ?? '');
$foto      = $foto_lama;

// Validasi dasar
if ($id <= 0 || $kode === '' || $nama === '' || $id_kat <= 0) {
    header("Location: barang.php?status=error&msg=" . urlencode("Kode barang, nama barang, dan kategori wajib diisi."));
    exit();
}
if (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)) {
    $kondisi = 'Baik';
}
if ($stok < 0) {
    $stok = 0;
}

// Pastikan barang yang diedit memang ada
$check = mysqli_prepare($conn, "SELECT foto FROM barang WHERE id_barang = ?");
mysqli_stmt_bind_param($check, "i", $id);
mysqli_stmt_execute($check);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
mysqli_stmt_close($check);

if (!$existing) {
    header("Location: barang.php?status=error&msg=" . urlencode("Barang tidak ditemukan."));
    exit();
}

// Upload foto baru jika ada
if (!empty($_FILES['foto']['name'])) {
    $upload = uploadFile($_FILES['foto']);
    if (!$upload['success']) {
        header("Location: barang.php?status=error&msg=" . urlencode($upload['message']));
        exit();
    }
    // Hapus foto lama dari disk setelah upload baru berhasil
    if ($existing['foto']) {
        deleteFile($existing['foto']);
    }
    $foto = $upload['filename'];
}

$foto_value = ($foto !== '') ? $foto : null;

// Update dengan prepared statement
$stmt = mysqli_prepare(
    $conn,
    "UPDATE barang
     SET kode_barang = ?, nama_barang = ?, id_kategori = ?, stok = ?, kondisi = ?, foto = ?
     WHERE id_barang = ?"
);
mysqli_stmt_bind_param($stmt, "ssiissi", $kode, $nama, $id_kat, $stok, $kondisi, $foto_value, $id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    header("Location: barang.php?status=edit&id_barang=" . $id);
} else {
    mysqli_stmt_close($stmt);
    header("Location: barang.php?status=error&msg=" . urlencode("Kode barang sudah digunakan oleh barang lain."));
}
exit();