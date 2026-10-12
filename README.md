# Flower Shop - Inventory Management System

Aplikasi web berbasis PHP (Native) dan MariaDB/MySQL untuk pengelolaan inventori toko bunga, mutasi stok barang (masuk/keluar), supplier, kategori, dan otentikasi peran pengguna sesuai spesifikasi PRD.

---

## Fitur Utama

1. **Autentikasi & Otorisasi Berbasis Peran (RBAC)**:
   - Login & logout aman dengan `password_hash()` (Bcrypt) dan `password_verify()`.
   - Regenerasi ID sesi untuk mencegah *Session Fixation*.
   - Dua peran: **Admin** (Akses penuh CRUD, User Management) dan **Staff** (Mutasi stok, lihat produk & kategori).
2. **Katalog Produk Dinamis**:
   - Pencarian berdasarkan nama & SKU produk.
   - Filter berdasarkan kategori & filter stok kritis/rendah.
   - Pengurutan dinamis (nama, harga, stok, tanggal) dan paginasi data.
3. **Master Data Kategorisasi & Supplier**:
   - Relasi *One-to-Many* kategori ke produk dengan proteksi `RESTRICT`.
   - Relasi *Many-to-Many* produk ke supplier (`product_suppliers`) dengan pencatatan harga beli supplier.
4. **Transaksi Mutasi Stok (Stock Movements)**:
   - **Barang Masuk (Stock In)**: Penambahan stok dengan pencatatan histori dan catatan mutasi.
   - **Barang Keluar (Stock Out)**: Pengurangan stok dengan locking transaksi database (`FOR UPDATE`) dan validasi stok mencukupi untuk mencegah stok negatif.
   - **Riwayat Transaksi**: Audit log kronologis pergerakan stok lengkap dengan filter tipe, produk, rentang tanggal, dan identitas petugas.
5. **Dashboard Inventori**:
   - Ringkasan metrik: Total produk, valuasi inventori, jumlah produk stok kritis, aktivitas masuk/keluar bulan berjalan.
   - Peringatan stok rendah dengan tombol *restock* cepat.
   - Tabel 8 transaksi terakhir.
6. **Proteksi & Keamanan**:
   - Token CSRF di seluruh form mutasi data.
   - Sanitasi XSS (`htmlspecialchars`) pada seluruh output.
   - Prepared Statements PDO untuk mencegah SQL Injection.
   - Proteksi foreign key `RESTRICT` agar produk yang memiliki riwayat audit tidak dapat dihapus sembarangan.

---

## Kredensial Bawaan (Default Login)

| Role | Username | Password |
|---|---|---|
| **Administrator** | `admin` | `admin123` |

> [!IMPORTANT]
> Segera ubah password administrator atau buat akun admin baru setelah instalasi di lingkungan produksi melalui menu **Pengguna**.

---

## Panduan Instalasi & Migrasi

### Prasyarat
- PHP 8.0 atau lebih tinggi
- MySQL / MariaDB Server aktif
- Web Server (Apache via XAMPP/LAMPP atau PHP Built-in Server)

### Opsi 1: Instalasi Bersih (Fresh Install)
Gunakan berkas `schema.sql`:
```bash
mysql -u root -p < schema.sql
```
Perintah ini akan membuat database `flower_shop`, semua tabel relasional, default kategori, dan akun default `admin`.

### Opsi 2: Migrasi dari Versi Lama (Data Existing Dipertahankan)
Jika Anda sudah memiliki database `flower_shop` dengan data produk lama, jalankan skrip migrasi tanpa kehilangan data:
```bash
mysql -u root -p < migrations/001_inventory_system.sql
```
Skrip ini akan menambahkan tabel baru, memigrasikan kolom kategori lama ke tabel `categories`, dan menambahkan SKU otomatis ke produk lama.

*(Untuk membatalkan migrasi jika diperlukan: `mysql -u root -p < migrations/001_rollback.sql`)*

---

## Konfigurasi Koneksi Database

Pengaturan koneksi berada pada berkas `config/db.php`:
```php
$host = 'localhost';
$dbname = 'flower_shop';
$username = 'root';
$password = '';
```

---

## Menjalankan Aplikasi

### Menggunakan PHP Built-in Server:
Jalankan di direktori proyek:
```bash
php -S localhost:8000
```
Buka browser di: `http://localhost:8000`

### Menggunakan XAMPP (Windows):
Pindahkan folder ke `htdocs`:
```text
C:\xampp\htdocs\inventoryFlower
```
Buka browser di: `http://localhost/inventoryFlower`

---