<?php
session_start();
// Konfigurasi koneksi database
$host = "localhost";
$username = "root";
$password = "";
$database = "inventaris_barang";

// Buat koneksi
$conn = mysqli_connect($host, $username, $password, $database);

// Cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Cek apakah user sudah login
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Redirect ke login jika belum login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Cek role admin
function requireAdmin() {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
        header("Location: ../auth/login.php");
        exit();
    }
}


// Fungsi upload foto
function uploadFile($file, $target_dir = null)
{
    if ($target_dir === null) {
        $target_dir = __DIR__ . "/../uploads/barang/";
    }
    
    $imageFileType = strtolower(pathinfo(
        $file["name"],
        PATHINFO_EXTENSION
    ));
    // Validasi ukuran (max 5MB)
    if ($file["size"] > 5000000)
        return ['success' => false, 'message' => 'File terlalu besar. Maks 5MB.'];
    // Validasi format
    $allowed = ["jpg", "jpeg", "png", "gif", "webp"];
    if (!in_array($imageFileType, $allowed))
        return ['success' => false, 'message' => 'Format tidak didukung.'];
    // Generate nama file unik agar tidak tabrakan
    $new_filename = uniqid() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($file["tmp_name"], $target_file))
        return ['success' => true, 'filename' => $new_filename];
    return ['success' => false, 'message' => 'Gagal upload file.'];
}

// Fungsi hapus file foto
function deleteFile($filename, $dir = null)
{
    if ($dir === null) {
        $dir = __DIR__ . "/../uploads/barang/";
    }
    if ($filename && file_exists($dir . $filename))
        unlink($dir . $filename);
}
?>