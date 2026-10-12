<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

$errors = [];
$name = '';
$sku = '';
$categoryId = '';
$price = '';
$stock = '0';
$minimumStock = '5';
$selectedSuppliers = []; // [supplier_id => supplier_price]

// Generate default SKU recommendation
$skuCandidate = 'FL-' . strtoupper(bin2hex(random_bytes(3)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $categoryId = $_POST['category_id'] ?? '';
        $price = $_POST['price'] ?? '';
        $stock = $_POST['stock'] ?? '0';
        $minimumStock = $_POST['minimum_stock'] ?? '5';
        $rawSuppliers = $_POST['suppliers'] ?? [];
        $supplierPrices = $_POST['supplier_prices'] ?? [];

        // Validate basic product info
        $validationErrors = validate_product([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $categoryId,
            'price' => $price,
            'stock' => $stock,
            'minimum_stock' => $minimumStock,
        ], $pdo);
        $errors = array_merge($errors, $validationErrors);

        // Process suppliers
        foreach ($rawSuppliers as $sId) {
            $sId = (int) $sId;
            $sPrice = isset($supplierPrices[$sId]) && is_numeric($supplierPrices[$sId]) ? (float) $supplierPrices[$sId] : null;
            $selectedSuppliers[$sId] = $sPrice;
        }

        // Handle image upload
        $imageError = null;
        $imageName = handle_image_upload($_FILES['image'] ?? [], $imageError);
        if ($imageError !== null) {
            $errors[] = $imageError;
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Insert product
                $insertStmt = $pdo->prepare('
                    INSERT INTO products (sku, category_id, name, price, stock, minimum_stock, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $insertStmt->execute([
                    $sku,
                    (int) $categoryId,
                    $name,
                    (float) $price,
                    (int) $stock,
                    (int) $minimumStock,
                    $imageName,
                ]);
                $productId = (int) $pdo->lastInsertId();

                // 2. If initial stock > 0, record in stock_movements
                if ((int) $stock > 0) {
                    $movStmt = $pdo->prepare('
                        INSERT INTO stock_movements (product_id, user_id, type, quantity, note)
                        VALUES (?, ?, "IN", ?, ?)
                    ');
                    $movStmt->execute([
                        $productId,
                        current_user()['id'],
                        (int) $stock,
                        'Stok awal saat pendaftaran produk'
                    ]);
                }

                // 3. Link suppliers
                if (!empty($selectedSuppliers)) {
                    $psStmt = $pdo->prepare('
                        INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                        VALUES (?, ?, ?)
                    ');
                    foreach ($selectedSuppliers as $sId => $sPrice) {
                        $psStmt->execute([$productId, $sId, $sPrice]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Produk "' . $name . '" berhasil ditambahkan.');
                header('Location: ' . base_url('pages/products/index.php'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                if ($imageName) {
                    delete_image_file($imageName);
                }
                $errors[] = 'Gagal menyimpan produk: ' . $e->getMessage();
            }
        } else {
            if ($imageName) {
                delete_image_file($imageName);
            }
        }
    }
} else {
    $sku = $skuCandidate;
}

// Fetch categories & suppliers for forms
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();

$pageTitle = 'Tambah Produk Baru - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="font-playfair fs-2 mb-1 text-dark">Tambah Produk Baru</h1>
                <p class="text-secondary small mb-0">Isi spesifikasi lengkap produk, stok awal, dan mitra pemasok.</p>
            </div>
            <a href="<?= base_url('pages/products/index.php') ?>" class="btn btn-pastel-outline btn-sm">&larr; Kembali</a>
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
            <form method="POST" action="<?= base_url('pages/products/create.php') ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label for="sku" class="form-label small fw-semibold text-secondary">SKU / Kode Produk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-pastel font-monospace" id="sku" name="sku" value="<?= e($sku) ?>" required maxlength="50" placeholder="FL-00001">
                        <small class="text-muted">Kode unik identifikasi stok barang.</small>
                    </div>

                    <div class="col-12 col-md-8">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-pastel" id="name" name="name" value="<?= e($name) ?>" required maxlength="150" placeholder="Contoh: Mawar Merah Holland Grade A">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="category_id" class="form-label small fw-semibold text-secondary">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select form-control-pastel" id="category_id" name="category_id" required>
                            <option value="">Pilih Kategori...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (string)$categoryId === (string)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="price" class="form-label small fw-semibold text-secondary">Harga Jual Satuan (Rp) <span class="text-danger">*</span></label>
                        <input type="number" step="100" min="1" class="form-control form-control-pastel" id="price" name="price" value="<?= e($price) ?>" required placeholder="Contoh: 75000">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label for="stock" class="form-label small fw-semibold text-secondary">Stok Awal <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control form-control-pastel" id="stock" name="stock" value="<?= e($stock) ?>" required placeholder="0">
                        <small class="text-muted">Akan otomatis dicatat sebagai transaksi stok masuk awal.</small>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="minimum_stock" class="form-label small fw-semibold text-secondary">Batas Stok Minimum <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control form-control-pastel" id="minimum_stock" name="minimum_stock" value="<?= e($minimumStock) ?>" required placeholder="5">
                        <small class="text-muted">Peringatan otomatis muncul bila stok &le; angka ini.</small>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="image" class="form-label small fw-semibold text-secondary">Foto Produk</label>
                    <input type="file" class="form-control form-control-pastel" id="image" name="image" accept=".jpg,.jpeg,.png,.webp" onchange="previewProductImage(this, 'previewContainer', 'previewImg')">
                    <small class="text-muted d-block">Format JPG, PNG, atau WEBP. Maksimum ukuran 3MB.</small>
                    <div id="previewContainer" class="mt-2 d-none">
                        <img id="previewImg" src="#" alt="Preview" class="rounded-3 shadow-sm" style="max-width: 140px; max-height: 140px; object-fit: cover;">
                    </div>
                </div>

                <!-- Supplier Mapping Section -->
                <div class="mb-4 border-top border-pastel pt-3">
                    <label class="form-label small fw-semibold text-secondary d-block mb-1">Hubungkan Mitra Supplier (Opsional)</label>
                    <p class="text-muted small mb-3">Pilih supplier yang memasok produk ini dan tentukan estimasi harga beli/kulakan.</p>

                    <?php if (empty($suppliers)): ?>
                        <div class="alert alert-light border small text-muted">
                            Belum ada supplier yang terdaftar. Anda dapat menambahkannya nanti melalui menu Supplier.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">Pilih</th>
                                        <th>Nama Supplier</th>
                                        <th style="width: 220px;">Harga Beli Supplier (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <tr>
                                            <td>
                                                <input class="form-check-input" type="checkbox" name="suppliers[]" value="<?= $sup['id'] ?>" id="sup_<?= $sup['id'] ?>" <?= isset($selectedSuppliers[$sup['id']]) ? 'checked' : '' ?>>
                                            </td>
                                            <td>
                                                <label class="form-check-label text-dark fw-semibold" for="sup_<?= $sup['id'] ?>">
                                                    <?= e($sup['name']) ?>
                                                </label>
                                            </td>
                                            <td>
                                                <input type="number" step="100" min="0" class="form-control form-control-sm form-control-pastel" name="supplier_prices[<?= $sup['id'] ?>]" placeholder="Harga beli..." value="<?= e($selectedSuppliers[$sup['id']] ?? '') ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top border-pastel pt-3">
                    <a href="<?= base_url('pages/products/index.php') ?>" class="btn btn-pastel-outline">Batal</a>
                    <button type="submit" class="btn btn-pastel-primary">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
