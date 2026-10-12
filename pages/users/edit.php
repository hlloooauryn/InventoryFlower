<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.role, p.full_name
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE u.id = ?
');
$stmt->execute([$id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    header('Location: ' . base_url('pages/users/index.php'));
    exit;
}

$errors = [];
$username = $targetUser['username'];
$fullName = $targetUser['full_name'] ?: '';
$role = $targetUser['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $newRole = $_POST['role'] ?? $role;

        if ($fullName === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Nama lengkap maksimal 100 karakter.';
        }

        if ($newPassword !== '' && strlen($newPassword) < 6) {
            $errors[] = 'Password baru minimal terdiri dari 6 karakter.';
        }

        // Prevent self-demotion if editing own account
        if ($id === (int) current_user()['id'] && $newRole !== 'admin') {
            $errors[] = 'Anda tidak dapat mengubah peran akun Anda sendiri menjadi Staff.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Update role and optionally password
                if ($newPassword !== '') {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updUser = $pdo->prepare('UPDATE users SET role = ?, password_hash = ? WHERE id = ?');
                    $updUser->execute([$newRole, $newHash, $id]);
                } else {
                    $updUser = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                    $updUser->execute([$newRole, $id]);
                }

                // 2. Update user_profiles
                $chkProf = $pdo->prepare('SELECT id FROM user_profiles WHERE user_id = ?');
                $chkProf->execute([$id]);
                if ($chkProf->fetch()) {
                    $updProf = $pdo->prepare('UPDATE user_profiles SET full_name = ? WHERE user_id = ?');
                    $updProf->execute([$fullName, $id]);
                } else {
                    $insProf = $pdo->prepare('INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)');
                    $insProf->execute([$id, $fullName]);
                }

                // If editing self, update active session data
                if ($id === (int) current_user()['id']) {
                    $_SESSION['user']['full_name'] = $fullName;
                    $_SESSION['user']['role'] = $newRole;
                }

                $pdo->commit();
                set_flash('success', "Akun @$username berhasil diperbarui.");
                header('Location: ' . base_url('pages/users/index.php'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal memperbarui akun: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit Pengguna - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="font-playfair fs-2 mb-0 text-dark">Edit Pengguna</h1>
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
            <form method="POST" action="<?= base_url('pages/users/edit.php?id=' . $id) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Username</label>
                    <input type="text" class="form-control form-control-pastel bg-light font-monospace" value="@<?= e($username) ?>" readonly>
                    <small class="text-muted">Username tidak dapat diubah.</small>
                </div>

                <div class="mb-3">
                    <label for="full_name" class="form-label small fw-semibold text-secondary">Nama Lengkap</label>
                    <input type="text" class="form-control form-control-pastel" id="full_name" name="full_name" value="<?= e($fullName) ?>" required autofocus maxlength="100">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold text-secondary">Password Baru (Opsional)</label>
                    <input type="password" class="form-control form-control-pastel" id="password" name="password" minlength="6" placeholder="Biarkan kosong jika tidak ingin mengubah password">
                </div>

                <div class="mb-4">
                    <label for="role" class="form-label small fw-semibold text-secondary">Peran Akses (Role)</label>
                    <select class="form-select form-control-pastel" id="role" name="role" required <?= $id === (int)current_user()['id'] ? 'disabled' : '' ?>>
                        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff (Kelola Transaksi & Lihat Produk/Kategori)</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin (Akses Penuh CRUD, User & Konfigurasi)</option>
                    </select>
                    <?php if ($id === (int)current_user()['id']): ?>
                        <small class="text-muted d-block mt-1">Anda tidak dapat mengubah peran akun Anda sendiri saat sedang masuk.</small>
                        <input type="hidden" name="role" value="admin">
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top border-pastel pt-3">
                    <a href="<?= base_url('pages/users/index.php') ?>" class="btn btn-pastel-outline">Batal</a>
                    <button type="submit" class="btn btn-pastel-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
