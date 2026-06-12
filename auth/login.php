<?php
include_once __DIR__ . "/../includes/config.php";

// Log debug sementara
$log_data = "Time: " . date('Y-m-d H:i:s') . "\n"
          . "Method: " . $_SERVER['REQUEST_METHOD'] . "\n"
          . "POST: " . print_r($_POST, true) . "\n"
          . "SESSION: " . print_r($_SESSION, true) . "\n"
          . "-------------------------\n";
file_put_contents(__DIR__ . "/../debug_login.txt", $log_data, FILE_APPEND);

// Kalau sudah login, redirect sesuai role
if (isLoggedIn()) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../peminjam/dashboard.php");
    }
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = $_POST['password'];

    $query  = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']      = $user['id_user'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role']         = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../peminjam/dashboard.php');
            }
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Inventaris Barang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a3c5e 0%, #2e6da4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-header {
            background: linear-gradient(135deg, #1a3c5e, #2e6da4);
            border-radius: 16px 16px 0 0;
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .login-header i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        .btn-login {
            background: linear-gradient(135deg, #1a3c5e, #2e6da4);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 8px;
            transition: opacity 0.2s;
        }
        .btn-login:hover {
            opacity: 0.9;
            color: white;
        }
        .form-control:focus {
            border-color: #2e6da4;
            box-shadow: 0 0 0 0.2rem rgba(46,109,164,0.25);
        }
        .toggle-password {
            cursor: pointer;
            border-left: none;
            background: white;
        }
        .toggle-password:hover {
            color: #2e6da4;
        }
    </style>
</head>
<body>
    <div class="container px-3">
        <div class="login-card card mx-auto">

            <!-- Header -->
            <div class="login-header">
                <i class="bi bi-box-seam"></i>
                <h5 class="mb-0 fw-bold">Sistem Inventaris Barang</h5>
                <small class="opacity-75">Silakan login untuk melanjutkan</small>
            </div>

            <!-- Form -->
            <div class="card-body p-4">

                <!-- Alert error -->
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2" role="alert" id="errorAlert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $error ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form id="formLogin" method="POST" action="" novalidate>

                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                class="form-control"
                                id="username"
                                name="username"
                                placeholder="Masukkan username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                autocomplete="username"
                            >
                        </div>
                        <div class="invalid-feedback d-block" id="errUsername"></div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Masukkan password"
                                autocomplete="current-password"
                            >
                            <span class="input-group-text toggle-password" id="togglePassword">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </span>
                        </div>
                        <div class="invalid-feedback d-block" id="errPassword"></div>
                    </div>

                    <button type="submit" name="login" class="btn btn-login w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>

                </form>
            </div>

            <div class="card-footer text-center text-muted py-3" style="border-radius: 0 0 16px 16px;">
                <small>Belum punya akun? Hubungi Administrator.</small>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle show/hide password
        document.getElementById('togglePassword').addEventListener('click', function () {
            const input   = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });

        // Validasi JS sebelum submit
        document.getElementById('formLogin').addEventListener('submit', function (e) {
            let valid = true;

            const username   = document.getElementById('username').value.trim();
            const password   = document.getElementById('password').value;
            const errU       = document.getElementById('errUsername');
            const errP       = document.getElementById('errPassword');

            // Reset error
            errU.textContent = '';
            errP.textContent = '';

            if (!username) {
                errU.textContent = 'Username wajib diisi.';
                valid = false;
            }
            if (!password) {
                errP.textContent = 'Password wajib diisi.';
                valid = false;
            }

            if (!valid) e.preventDefault();
        });
    </script>
</body>
</html>