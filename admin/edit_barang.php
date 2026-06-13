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
$existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM barang WHERE id_barang = $id"));

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

// Update query biasa
$kode_esc    = mysqli_real_escape_string($conn, $kode);
$nama_esc    = mysqli_real_escape_string($conn, $nama);
$kondisi_esc = mysqli_real_escape_string($conn, $kondisi);
$foto_esc    = $foto_value ? "'" . mysqli_real_escape_string($conn, $foto_value) . "'" : "NULL";

$query = "UPDATE barang
          SET kode_barang = '$kode_esc', nama_barang = '$nama_esc', id_kategori = $id_kat,
              stok = $stok, kondisi = '$kondisi_esc', foto = $foto_esc
          WHERE id_barang = $id";

if (mysqli_query($conn, $query)) {
    header("Location: barang.php?status=edit&id_barang=" . $id);
} else {
    header("Location: barang.php?status=error&msg=" . urlencode("Kode barang sudah digunakan oleh barang lain."));
}
exit();