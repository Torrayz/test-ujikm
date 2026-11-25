# Sistem Informasi Perpustakaan UNPAM

Aplikasi pengelolaan perpustakaan menggunakan **PHP Native 8.x**, MySQL, HTML, CSS, dan JavaScript sederhana. Tidak menggunakan Laravel atau framework PHP.

## Fitur
- Login dan pembagian hak akses petugas/anggota.
- CRUD buku: ISBN, judul, penulis, penerbit, tahun terbit, dan stok.
- Registrasi serta pengelolaan anggota mahasiswa dan dosen.
- Transaksi peminjaman dan pengembalian dengan pembaruan stok otomatis.
- Katalog buku, pencarian, dan riwayat peminjaman anggota.
- Ganti password, CSRF token, PDO prepared statements, dan password hashing.

## Instalasi
1. Salin folder proyek ke `htdocs` XAMPP.
2. Jalankan Apache dan MySQL.
3. Import `database/schema.sql` melalui phpMyAdmin.
4. Sesuaikan koneksi di `config/database.php` bila diperlukan.
5. Jalankan `php setup_admin.php` dari terminal di folder proyek.
6. Buka `http://localhost/test-ujikm`.

Akun awal petugas: `admin@perpus.local` / `Admin123!`. Segera ganti password setelah login.

## Dokumen
Dokumen spesifikasi dan presentasi studi kasus berada pada folder `docs/` dalam bentuk Markdown. File Office asli tersedia sebagai lampiran percakapan.

## Catatan
Proyek ini merupakan prototipe studi kasus/asesmen. Belum terhubung ke SSO atau sistem akademik UNPAM. Pengujian beban dan validasi skalabilitas perlu dilakukan pada server uji sebelum penggunaan produksi.
