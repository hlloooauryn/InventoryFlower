<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('pages/products/index.php'));
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Token CSRF tidak valid.');
    header('Location: ' . base_url('pages/products/index.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, name, image FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    header('Location: ' . base_url('pages/products/index.php'));
    exit;
}

// Check stock_movements (RESTRICT constraint check)
$moveCheck = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE product_id = ?');
$moveCheck->execute([$id]);
$moveCount = (int) $moveCheck->fetchColumn();

if ($moveCount > 0) {
    set_flash('danger', 'Produk "' . $product['name'] . '" tidak dapat dihapus karena memiliki ' . $moveCount . ' riwayat transaksi stok.');
    header('Location: ' . base_url('pages/products/index.php'));
    exit;
}

try {
    // Delete image if exists
    if (!empty($product['image'])) {
        delete_image_file($product['image']);
    }

    $delStmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $delStmt->execute([$id]);

    set_flash('success', 'Produk "' . $product['name'] . '" berhasil dihapus.');
} catch (PDOException $e) {
    set_flash('danger', 'Gagal menghapus produk: ' . $e->getMessage());
}

header('Location: ' . base_url('pages/products/index.php'));
exit;
