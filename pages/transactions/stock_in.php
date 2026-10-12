<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_login();

$errors = [];
$selectedProductId = (int) ($_GET['product_id'] ?? ($_POST['product_id'] ?? 0));
$quantity = '';
$note = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $selectedProductId = (int) ($_POST['product_id'] ?? 0);
        $quantity = $_POST['quantity'] ?? '';
        $note = trim($_POST['note'] ?? '');

        if ($selectedProductId <= 0) {
            $errors[] = 'Silakan pilih produk yang akan ditambahkan stoknya.';
        } else {
            $pCheck = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = ?');
            $pCheck->execute([$selectedProductId]);
            $prod = $pCheck->fetch();
            if (!$prod) {
                $errors[] = 'Produk yang dipilih tidak ditemukan.';
            }
        }

        if ($quantity === '' || !is_numeric($quantity) || (int) $quantity <= 0) {
            $errors[] = 'Jumlah kuantitas masuk harus berupa bilangan bulat lebih dari 0.';
        }

        if (empty($errors)) {
            $qty = (int) $quantity;
            try {
                $pdo->beginTransaction();

                // 1. Update product stock
                $updStmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                $updStmt->execute([$qty, $selectedProductId]);

                // 2. Insert stock movement record
                $insMov = $pdo->prepare('
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, note)
                    VALUES (?, ?, "IN", ?, ?)
                ');
                $insMov->execute([
                    $selectedProductId,
                    current_user()['id'],
                    $qty,
                    $note ?: 'Penerimaan stok masuk'
                ]);

                $pdo->commit();
                set_flash('success', "Berhasil menambahkan $qty unit stok untuk {$prod['name']}.");
                header('Location: ' . base_url('pages/transactions/history.php'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Gagal memproses transaksi stok masuk: ' . $e->getMessage();
            }
        }
    }
}

// Fetch list of products for dropdown
$products = $pdo->query('
    SELECT p.id, p.name, p.sku, p.stock, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.name ASC
')->fetchAll();

$pageTitle = 'Barang Masuk (Stock In) - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="font-playfair fs-2 mb-1 text-dark">Barang Masuk (Stock In)</h1>
                <p class="text-secondary small mb-0">Catat penambahan stok dari supplier, panen, atau pengadaan baru.</p>
            </div>
            <a href="<?= base_url('pages/transactions/history.php') ?>" class="btn btn-pastel-outline btn-sm">Lihat Riwayat &rarr;</a>
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
            <form method="POST" action="<?= base_url('pages/transactions/stock_in.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="product_id" class="form-label small fw-semibold text-secondary">Pilih Produk <span class="text-danger">*</span></label>
                    <select class="form-select form-control-pastel" id="product_id" name="product_id" required>
                        <option value="">-- Cari atau Pilih Produk --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $selectedProductId === (int)$p['id'] ? 'selected' : '' ?>>
                                [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Stok saat ini: <?= (int)$p['stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="quantity" class="form-label small fw-semibold text-secondary">Jumlah Masuk (Unit) <span class="text-danger">*</span></label>
                    <input type="number" min="1" step="1" class="form-control form-control-pastel" id="quantity" name="quantity" value="<?= e($quantity) ?>" required placeholder="Contoh: 25">
                </div>

                <div class="mb-4">
                    <label for="note" class="form-label small fw-semibold text-secondary">Catatan / Keterangan Transaksi</label>
                    <textarea class="form-control form-control-pastel" id="note" name="note" rows="3" placeholder="Contoh: Kiriman dari Kebun Lembang no. PO-102"><?= e($note) ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top border-pastel pt-3">
                    <a href="<?= base_url('pages/products/index.php') ?>" class="btn btn-pastel-outline">Batal</a>
                    <button type="submit" class="btn btn-pastel-primary">Simpan Barang Masuk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
