<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

$errors = [];
$name = '';
$contact = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            $errors[] = 'Nama supplier wajib diisi.';
        } elseif (mb_strlen($name) > 150) {
            $errors[] = 'Nama supplier maksimal 150 karakter.';
        }

        if (mb_strlen($contact) > 100) {
            $errors[] = 'Kontak supplier maksimal 100 karakter.';
        }

        if (empty($errors)) {
            $insert = $pdo->prepare('INSERT INTO suppliers (name, contact, address) VALUES (?, ?, ?)');
            $insert->execute([$name, $contact ?: null, $address ?: null]);
            set_flash('success', 'Supplier berhasil ditambahkan.');
            header('Location: ' . base_url('pages/suppliers/index.php'));
            exit;
        }
    }
}

$pageTitle = 'Tambah Supplier - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="font-playfair fs-2 mb-0 text-dark">Tambah Supplier</h1>
            <a href="<?= base_url('pages/suppliers/index.php') ?>" class="btn btn-pastel-outline btn-sm">&larr; Kembali</a>
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
            <form method="POST" action="<?= base_url('pages/suppliers/create.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="name" class="form-label small fw-semibold text-secondary">Nama Supplier</label>
                    <input type="text" class="form-control form-control-pastel" id="name" name="name" value="<?= e($name) ?>" placeholder="Contoh: Kebun Mawar Bandung" required autofocus maxlength="150">
                </div>

                <div class="mb-3">
                    <label for="contact" class="form-label small fw-semibold text-secondary">Kontak (Telepon / Email / WhatsApp)</label>
                    <input type="text" class="form-control form-control-pastel" id="contact" name="contact" value="<?= e($contact) ?>" placeholder="Contoh: 0812-3456-7890 atau supplier@kebun.com" maxlength="100">
                </div>

                <div class="mb-4">
                    <label for="address" class="form-label small fw-semibold text-secondary">Alamat</label>
                    <textarea class="form-control form-control-pastel" id="address" name="address" rows="3" placeholder="Alamat lengkap supplier"><?= e($address) ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= base_url('pages/suppliers/index.php') ?>" class="btn btn-pastel-outline">Batal</a>
                    <button type="submit" class="btn btn-pastel-primary">Simpan Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
