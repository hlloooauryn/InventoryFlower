<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

$errors = [];
$username = '';
$fullName = '';
$role = 'staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        if ($username === '') {
            $errors[] = 'Username wajib diisi.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username harus terdiri dari 3-50 karakter (huruf, angka, atau underscore).';
        } else {
            // Check username uniqueness
            $uStmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $uStmt->execute([$username]);
            if ($uStmt->fetch()) {
                $errors[] = 'Username sudah digunakan oleh akun lain.';
            }
        }

        if ($fullName === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Nama lengkap maksimal 100 karakter.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Password minimal terdiri dari 6 karakter.';
        }

        if (!in_array($role, ['admin', 'staff'], true)) {
            $errors[] = 'Peran akun tidak valid.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
                $insUser->execute([$username, $hash, $role]);
                $newUserId = (int) $pdo->lastInsertId();

                $insProfile = $pdo->prepare('INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)');
                $insProfile->execute([$newUserId, $fullName]);

                $pdo->commit();
                set_flash('success', "Akun pengguna @$username berhasil dibuat.");
                header('Location: ' . base_url('pages/users/index.php'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal menyimpan akun pengguna: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Tambah Pengguna - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="font-playfair fs-2 mb-0 text-dark">Tambah Pengguna</h1>
            <a href="<?= base_url('pages/users/index.php') ?>" class="btn btn-pastel-outline btn-sm">&larr; Kembali</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-pastel border-0 small mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card card-pastel border-0 shadow-sm p-4">
            <form method="POST" action="<?= base_url('pages/users/create.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="full_name" class="form-label small fw-semibold text-secondary">Nama Lengkap</label>
                    <input type="text" class="form-control form-control-pastel" id="full_name" name="full_name" value="<?= e($fullName) ?>" required autofocus maxlength="100" placeholder="Contoh: Siti Rahmawati">
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                    <input type="text" class="form-control form-control-pastel font-monospace" id="username" name="username" value="<?= e($username) ?>" required maxlength="50" placeholder="siti_rahma">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password Awal</label>
                    <input type="password" class="form-control form-control-pastel" id="password" name="password" required minlength="6" placeholder="Minimal 6 karakter">
                </div>

                <div class="mb-4">
                    <label for="role" class="form-label small fw-semibold text-secondary">Peran Akses (Role)</label>
                    <select class="form-select form-control-pastel" id="role" name="role" required>
                        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff (Kelola Transaksi & Lihat Produk/Kategori)</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin (Akses Penuh CRUD, User & Konfigurasi)</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top border-pastel pt-3">
                    <a href="<?= base_url('pages/users/index.php') ?>" class="btn btn-pastel-outline">Batal</a>
                    <button type="submit" class="btn btn-pastel-primary">Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
