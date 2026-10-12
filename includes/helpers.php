<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base path for includes resolution
define('BASE_PATH', dirname(__DIR__));

// Dynamic base URL helper for subdirectories or root hosting
function base_url(string $path = ''): string
{
    static $baseUrl = null;
    if ($baseUrl === null) {
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: '') : '';
        $appPath = str_replace('\\', '/', BASE_PATH);
        if ($docRoot !== '' && strpos($appPath, $docRoot) === 0) {
            $sub = substr($appPath, strlen($docRoot));
            $baseUrl = rtrim($sub, '/');
        } else {
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $pos = strpos($scriptDir, '/pages');
            if ($pos !== false) {
                $baseUrl = substr($scriptDir, 0, $pos);
            } else {
                $baseUrl = ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/\\');
            }
        }
    }
    return $baseUrl . '/' . ltrim($path, '/');
}

require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/auth.php';

// CSRF token generation
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF verification
function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Render hidden CSRF input
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Escape output for HTML context
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Validate product data, returns array of error strings
function validate_product(array $data, PDO $pdo, ?int $excludeId = null): array
{
    $errors = [];
    $name = trim($data['name'] ?? '');
    $sku = trim($data['sku'] ?? '');
    $categoryId = $data['category_id'] ?? '';
    $price = $data['price'] ?? '';
    $stock = $data['stock'] ?? '';
    $minimumStock = $data['minimum_stock'] ?? '5';

    // Name validation
    if ($name === '') {
        $errors[] = 'Nama produk wajib diisi.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Nama produk maksimal 150 karakter.';
    }

    // SKU validation - unique
    if ($sku === '') {
        $errors[] = 'SKU wajib diisi.';
    } elseif (mb_strlen($sku) > 50) {
        $errors[] = 'SKU maksimal 50 karakter.';
    } else {
        $sql = 'SELECT id FROM products WHERE sku = ?';
        $params = [$sku];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'SKU sudah digunakan produk lain.';
        }
    }

    // Category validation
    if ($categoryId === '' || !is_numeric($categoryId)) {
        $errors[] = 'Kategori wajib dipilih.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $stmt->execute([(int) $categoryId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Kategori tidak valid.';
        }
    }

    // Price validation
    if ($price === '' || !is_numeric($price)) {
        $errors[] = 'Harga harus berupa angka.';
    } elseif ((float) $price <= 0) {
        $errors[] = 'Harga harus lebih dari 0.';
    }

    // Stock validation
    if ($stock === '' || !is_numeric($stock)) {
        $errors[] = 'Stok harus berupa angka.';
    } elseif ((int) $stock < 0) {
        $errors[] = 'Stok tidak boleh negatif.';
    }

    // Minimum stock validation
    if ($minimumStock !== '' && is_numeric($minimumStock) && (int) $minimumStock < 0) {
        $errors[] = 'Stok minimum tidak boleh negatif.';
    }

    return $errors;
}

// Flash message system
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// Image upload handler
function handle_image_upload(array $file, ?string &$error = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Terjadi kesalahan saat mengunggah berkas gambar.';
        return null;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        $error = 'Ukuran gambar maksimal 3MB.';
        return null;
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Format berkas harus JPG, JPEG, PNG, atau WEBP.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        $error = 'Tipe berkas tidak valid sebagai gambar.';
        return null;
    }

    $uploadDir = BASE_PATH . '/assets/product/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = 'Gagal menyimpan berkas gambar ke server.';
        return null;
    }

    return $newFileName;
}

// Delete product image file
function delete_image_file(?string $filename): void
{
    if (empty($filename)) {
        return;
    }
    $path = BASE_PATH . '/assets/product/' . basename($filename);
    if (file_exists($path) && is_file($path)) {
        unlink($path);
    }
}

// Format rupiah
function format_rupiah(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Build pagination data
function paginate(PDO $pdo, string $countSql, array $countParams, int $page, int $perPage = 10): array
{
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($countParams);
    $total = (int) $stmt->fetchColumn();

    $page = max(1, $page);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

// Build query string preserving existing params
function build_query_string(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    unset($params['page']); // page handled separately
    if (isset($overrides['page'])) {
        $params['page'] = $overrides['page'];
    }
    return http_build_query($params);
}
