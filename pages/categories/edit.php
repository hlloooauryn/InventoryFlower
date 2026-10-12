<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('danger', 'Kategori tidak ditemukan.');
    header('Location: ' . base_url('pages/categories/index.php'));
    exit;
}

$errors = [];
$name = $category['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Nama kategori maksimal 100 karakter.';
        } else {
            // Check uniqueness excluding current category
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                $errors[] = 'Kategori dengan nama tersebut sudah ada.';
            } else {
                $update = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $update->execute([$name, $id]);
                set_flash('success', 'Kategori berhasil diperbarui.');
                header('Location: ' . base_url('pages/categories/index.php'));
                exit;
            }
        }
    }
}

$pageTitle = 'Edit Kategori - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="font-playfair fs-2 mb-0 text-dark">Edit Kategori</h1>
            <a href="<?= base_url('pages/categories/index.php') ?>" class="btn btn-pastel-outline btn-sm">&larr; Kembali</a>
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
            <form method="POST" action="<?= base_url('pages/categories/edit.php?id=' . $id) ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-4">
                    <label for="name" class="form-label small fw-semibold text-secondary">Nama Kategori</label>
                    <input type="text" class="form-control form-control-pastel" id="name" name="name" value="<?= e($name) ?>" placeholder="Nama kategori" required autofocus maxlength="100">
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= base_url('pages/categories/index.php') ?>" class="btn btn-pastel-outline">Batal</a>
                    <button type="submit" class="btn btn-pastel-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
