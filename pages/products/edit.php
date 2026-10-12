<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header('Location: ' . base_url('pages/products/index.php'));
    exit;
}

$errors = [];
$name = $product['name'];
$sku = $product['sku'];
$categoryId = $product['category_id'];
$price = $product['price'];
$stock = $product['stock'];
$minimumStock = $product['minimum_stock'];
$currentImage = $product['image'];

// Fetch current linked suppliers
$curSupStmt = $pdo->prepare('SELECT supplier_id, supplier_price FROM product_suppliers WHERE product_id = ?');
$curSupStmt->execute([$id]);
$selectedSuppliers = [];
foreach ($curSupStmt->fetchAll() as $row) {
    $selectedSuppliers[(int) $row['supplier_id']] = $row['supplier_price'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $categoryId = $_POST['category_id'] ?? '';
        $price = $_POST['price'] ?? '';
        $minimumStock = $_POST['minimum_stock'] ?? '5';
        $rawSuppliers = $_POST['suppliers'] ?? [];
        $supplierPrices = $_POST['supplier_prices'] ?? [];

        // Validate basic product info (exclude current ID for SKU uniqueness)
        $validationErrors = validate_product([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $categoryId,
            'price' => $price,
            'stock' => $stock, // current stock maintained
            'minimum_stock' => $minimumStock,
        ], $pdo, $id);
        $errors = array_merge($errors, $validationErrors);

        // Process suppliers selection
        $newSelectedSuppliers = [];
        foreach ($rawSuppliers as $sId) {
            $sId = (int) $sId;
            $sPrice = isset($supplierPrices[$sId]) && is_numeric($supplierPrices[$sId]) ? (float) $supplierPrices[$sId] : null;
            $newSelectedSuppliers[$sId] = $sPrice;
        }

        // Handle image upload if provided
        $imageError = null;
        $newImageName = handle_image_upload($_FILES['image'] ?? [], $imageError);
        if ($imageError !== null) {
            $errors[] = $imageError;
        }

        // Handle image deletion checkbox
        $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] === '1';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $finalImage = $currentImage;
                if ($newImageName !== null) {
                    // Remove old image
                    delete_image_file($currentImage);
                    $finalImage = $newImageName;
                } elseif ($deleteImage) {
                    delete_image_file($currentImage);
                    $finalImage = null;
                }

                // 1. Update product
                $updateStmt = $pdo->prepare('
                    UPDATE products
                    SET sku = ?, category_id = ?, name = ?, price = ?, minimum_stock = ?, image = ?
                    WHERE id = ?
                ');
                $updateStmt->execute([
                    $sku,
                    (int) $categoryId,
                    $name,
                    (float) $price,
                    (int) $minimumStock,
                    $finalImage,
                    $id
                ]);

                // 2. Sync suppliers: delete existing and reinsert
                $delPs = $pdo->prepare('DELETE FROM product_suppliers WHERE product_id = ?');
                $delPs->execute([$id]);

                if (!empty($newSelectedSuppliers)) {
                    $insPs = $pdo->prepare('
                        INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                        VALUES (?, ?, ?)
                    ');
                    foreach ($newSelectedSuppliers as $sId => $sPrice) {
                        $insPs->execute([$id, $sId, $sPrice]);
                    }
                }

                $pdo->commit();
                set_flash('success', 'Produk "' . $name . '" berhasil diperbarui.');
                header('Location: ' . base_url('pages/products/index.php'));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                if ($newImageName) {
                    delete_image_file($newImageName);
                }
                $errors[] = 'Gagal memperbarui produk: ' . $e->getMessage();
            }
        } else {
            if ($newImageName) {
                delete_image_file($newImageName);
            }
        }
    }
}

// Fetch categories & suppliers for forms
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll();

$pageTitle = 'Edit Produk - Flower Shop';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="font-playfair fs-2 mb-1 text-dark">Edit Produk</h1>
                <p class="text-secondary small mb-0">Perbarui spesifikasi produk, harga jual, stok minimum, atau foto.</p>
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
            <form method="POST" action="<?= base_url('pages/products/edit.php?id=' . $id) ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label for="sku" class="form-label small fw-semibold text-secondary">SKU / Kode Produk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-pastel font-monospace" id="sku" name="sku" value="<?= e($sku) ?>" required maxlength="50">
                    </div>

                    <div class="col-12 col-md-8">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-pastel" id="name" name="name" value="<?= e($name) ?>" required maxlength="150">
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
                        <input type="number" step="100" min="1" class="form-control form-control-pastel" id="price" name="price" value="<?= e($price) ?>" required>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Stok Saat Ini</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-pastel bg-light" value="<?= (int) $stock ?> unit" readonly>
                            <a href="<?= base_url('pages/transactions/stock_in.php?product_id=' . $id) ?>" class="btn btn-pastel-outline btn-sm">Mutasi Stok</a>
                        </div>
                        <small class="text-muted">Untuk menjaga integritas audit, perubahan stok dilakukan melalui menu Transaksi.</small>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="minimum_stock" class="form-label small fw-semibold text-secondary">Batas Stok Minimum <span class="text-danger">*</span></label>
                        <input type="number" min="0" class="form-control form-control-pastel" id="minimum_stock" name="minimum_stock" value="<?= e($minimumStock) ?>" required>
                        <small class="text-muted">Peringatan otomatis muncul bila stok &le; angka ini.</small>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="image" class="form-label small fw-semibold text-secondary">Foto Produk</label>
                    <?php if (!empty($currentImage)): ?>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="<?= base_url('assets/product/' . $currentImage) ?>" alt="Foto saat ini" class="rounded-3 shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="delete_image" value="1" id="delete_image">
                                <label class="form-check-label text-danger small" for="delete_image">
                                    Hapus gambar saat ini
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control form-control-pastel" id="image" name="image" accept=".jpg,.jpeg,.png,.webp" onchange="previewProductImage(this, 'previewContainer', 'previewImg')">
                    <small class="text-muted d-block">Pilih file baru untuk mengganti gambar saat ini. Format JPG, PNG, atau WEBP.</small>
                    <div id="previewContainer" class="mt-2 d-none">
                        <img id="previewImg" src="#" alt="Preview" class="rounded-3 shadow-sm" style="max-width: 140px; max-height: 140px; object-fit: cover;">
                    </div>
                </div>

                <!-- Supplier Mapping Section -->
                <div class="mb-4 border-top border-pastel pt-3">
                    <label class="form-label small fw-semibold text-secondary d-block mb-1">Mitra Supplier</label>
                    <p class="text-muted small mb-3">Tandai supplier yang memasok produk ini beserta harga beli/kulakan.</p>

                    <?php if (empty($suppliers)): ?>
                        <div class="alert alert-light border small text-muted">Belum ada supplier yang terdaftar.</div>
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
                    <button type="submit" class="btn btn-pastel-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
