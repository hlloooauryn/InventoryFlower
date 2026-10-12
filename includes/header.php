<?php
// Determine active nav for highlighting
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));

function nav_active(string $dir, string $page = ''): string {
    global $currentDir, $currentPage;
    if ($page !== '') {
        return ($currentDir === $dir && $currentPage === $page) ? 'active' : '';
    }
    return $currentDir === $dir ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Flower Shop - Sistem Manajemen Inventori Toko Bunga">
    <title><?= e($pageTitle ?? 'Flower Shop') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('style.css') ?>">
</head>
<body class="theme-pastel">
    <nav class="navbar navbar-expand-lg navbar-pastel sticky-top py-2">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-3" href="<?= base_url('pages/dashboard.php') ?>">
                <img src="<?= base_url('assets/logo.jpg') ?>" alt="Flower Shop Logo" class="brand-logo shadow-sm rounded-circle">
                <div>
                    <span class="brand-title d-block">Flower Shop</span>
                    <span class="brand-tagline d-block">Inventory Management</span>
                </div>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <?php if (is_logged_in()): ?>
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link nav-link-pastel <?= nav_active('pages', 'dashboard') ?>" href="<?= base_url('pages/dashboard.php') ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-pastel <?= nav_active('products') ?>" href="<?= base_url('pages/products/index.php') ?>">Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-pastel <?= nav_active('categories') ?>" href="<?= base_url('pages/categories/index.php') ?>">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-pastel <?= nav_active('suppliers') ?>" href="<?= base_url('pages/suppliers/index.php') ?>">Supplier</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-pastel dropdown-toggle <?= nav_active('transactions') ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Transaksi
                        </a>
                        <ul class="dropdown-menu dropdown-menu-pastel shadow-sm border-0">
                            <li><a class="dropdown-item" href="<?= base_url('pages/transactions/stock_in.php') ?>">Barang Masuk</a></li>
                            <li><a class="dropdown-item" href="<?= base_url('pages/transactions/stock_out.php') ?>">Barang Keluar</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item" href="<?= base_url('pages/transactions/history.php') ?>">Riwayat</a></li>
                        </ul>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-link-pastel <?= nav_active('users') ?>" href="<?= base_url('pages/users/index.php') ?>">Pengguna</a>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2 nav-user-section pt-3 pt-lg-0 mt-3 mt-lg-0">
                    <span class="badge-role badge-role-<?= e(current_user()['role']) ?>"><?= e(strtoupper(current_user()['role'])) ?></span>
                    <a href="<?= base_url('pages/logout.php') ?>" class="btn btn-nav-logout d-inline-flex align-items-center gap-1" title="Keluar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-75"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>Logout</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container py-4 py-md-5">
