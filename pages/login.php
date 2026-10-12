<?php
require_once dirname(__DIR__) . '/includes/helpers.php';

if (is_logged_in()) {
    header('Location: ' . base_url('pages/dashboard.php'));
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($submittedToken)) {
        $error = 'Sesi telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare('
                SELECT u.id, u.username, u.password_hash, u.role, COALESCE(p.full_name, u.username) AS full_name
                FROM users u
                LEFT JOIN user_profiles p ON p.user_id = u.id
                WHERE u.username = ?
                LIMIT 1
            ');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name'],
                ];
                set_flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');
                header('Location: ' . base_url('pages/dashboard.php'));
                exit;
            } else {
                $error = 'Username atau password tidak sesuai.';
            }
        }
    }
}

$pageTitle = 'Masuk - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center align-items-center min-vh-75 py-4">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card card-pastel border-0 shadow-sm p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="<?= base_url('assets/logo.jpg') ?>" alt="Logo" class="rounded-circle shadow-sm mb-3" style="width: 72px; height: 72px; object-fit: cover;">
                <h1 class="font-playfair fs-3 mb-1 text-dark">Selamat Datang</h1>
                <p class="text-muted small">Masuk ke Flower Shop Inventory Management</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php $flash = get_flash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-pastel border-0 py-2 px-3 small mb-4" role="alert">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= base_url('pages/login.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                    <input type="text" class="form-control form-control-pastel" id="username" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username" placeholder="Masukkan username">
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                    <input type="password" class="form-control form-control-pastel" id="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
                </div>

                <button type="submit" class="btn btn-pastel-primary w-100 py-2 fw-semibold">
                    Masuk ke Sistem
                </button>
            </form>

            <div class="text-center mt-4 pt-3 border-top border-pastel">
                <span class="text-muted small">Default Admin: <code>admin</code> / <code>admin123</code></span>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
