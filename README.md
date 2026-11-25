# Sistem Informasi Perpustakaan Universitas Pamulang

Aplikasi web pengelolaan perpustakaan untuk studi kasus asesmen kompetensi **Menganalisis Skalabilitas Perangkat Lunak**. Dibangun menggunakan **PHP Native (PHP murni)**, MySQL, HTML, CSS, dan JavaScript sederhana. Proyek ini **tidak menggunakan Laravel** maupun framework PHP.

## 1. Fitur aplikasi

### Petugas perpustakaan
- Login dan hak akses berdasarkan peran.
- Dashboard ringkasan jumlah judul buku, total stok, anggota aktif, dan peminjaman yang belum dikembalikan.
- Menambah, melihat, mengubah, mencari, dan menghapus data buku.
- Mengelola data anggota mahasiswa dan dosen.
- Mengaktifkan atau menonaktifkan akun anggota.
- Mencatat transaksi peminjaman dan pengembalian.
- Stok buku otomatis berkurang saat dipinjam dan bertambah saat dikembalikan.
- Melihat riwayat transaksi terbaru.

### Anggota (mahasiswa dan dosen)
- Login menggunakan akun yang dibuat petugas.
- Melihat dan mencari katalog buku.
- Melihat riwayat peminjaman milik akun sendiri.
- Mengganti password.

## 2. Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP Native 8.1+ |
| Database | MySQL 8+ / MariaDB |
| Akses database | PDO MySQL |
| Frontend | HTML5, CSS3, JavaScript sederhana |
| Server lokal | Apache melalui XAMPP |
| Pengelolaan database | phpMyAdmin |

Tidak ada Composer package atau framework yang wajib diinstal.

## 3. Persyaratan

- PHP 8.1 atau lebih baru.
- Ekstensi PHP: `pdo_mysql` dan `mbstring`.
- MySQL 8+ atau MariaDB.
- Apache atau server web PHP.
- XAMPP direkomendasikan untuk pengujian lokal di Windows.

Pastikan ekstensi `pdo_mysql` dan `mbstring` aktif pada konfigurasi PHP.

## 4. Instalasi di XAMPP (Windows)

1. Clone repository:
   ```bash
   git clone https://github.com/Torrayz/test-ujikm.git
   ```
2. Letakkan folder repository di `C:\\xampp\\htdocs\\test-ujikm`.
3. Buka XAMPP Control Panel, lalu jalankan **Apache** dan **MySQL**.
4. Buka `http://localhost/phpmyadmin`.
5. Pilih menu **Import**, pilih file `database/schema.sql`, kemudian jalankan import.
6. Periksa koneksi database di `config/database.php`. Nilai bawaan XAMPP:
   - Host: `127.0.0.1`
   - Database: `perpustakaan_unpam`
   - Username: `root`
   - Password: kosong
7. Buka terminal pada folder proyek dan jalankan:
   ```bash
   php setup_admin.php
   ```
8. Buka aplikasi di `http://localhost/test-ujikm`.

Jika memakai konfigurasi database berbeda, ubah konstanta pada `config/database.php` atau atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.

## 5. Akun petugas awal

Script setup membuat akun awal hanya jika belum ada akun petugas.

| Kolom | Nilai |
|---|---|
| Email | `admin@perpus.local` |
| Password awal | `Admin123!` |

**Penting:** akun tersebut hanya untuk instalasi dan pengujian lokal. Segera masuk ke menu **Pengaturan Akun** dan ganti password. Jangan gunakan kredensial contoh ini di server publik.

## 6. Cara menggunakan aplikasi

1. Login sebagai petugas.
2. Buka **Data Buku** untuk memasukkan koleksi buku.
3. Buka **Data Anggota** untuk membuat akun mahasiswa atau dosen.
4. Buka **Peminjaman** untuk memilih anggota dan buku yang stoknya tersedia.
5. Saat buku dikembalikan, petugas menekan **Proses Kembali** pada transaksi aktif.
6. Anggota login menggunakan email dan password yang dibuat petugas untuk mencari katalog dan melihat riwayatnya.

Satu transaksi saat ini mencatat satu eksemplar dari satu judul buku. Jika anggota meminjam beberapa judul atau eksemplar, petugas membuat transaksi terpisah untuk setiap buku.

## 7. Struktur direktori

```text
test-ujikm/
├── assets/
│   └── style.css
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── docs/
│   ├── spesifikasi-kebutuhan.md
│   ├── presentasi-skalabilitas.md
│   ├── use-case.mmd
│   └── erd.mmd
├── includes/
│   └── helpers.php
├── .gitignore
├── index.php
├── setup_admin.php
└── README.md
```

## 8. Keamanan dasar

- Query database menggunakan PDO prepared statements.
- Password disimpan dengan `password_hash()` dan diperiksa dengan `password_verify()`.
- Form POST dilindungi token CSRF.
- Session cookie menggunakan flag HttpOnly dan SameSite.
- Session ID diperbarui setelah login.
- Hak akses petugas dan anggota dibatasi di sisi server.
- Output yang berasal dari pengguna di-escape menggunakan `htmlspecialchars()`.
- Transaksi peminjaman/pengembalian memakai transaksi database dan penguncian stok saat peminjaman.

## 9. Batasan dan pengembangan berikutnya

Aplikasi ini adalah prototipe studi kasus/asesmen, bukan sistem produksi kampus. Saat ini:
- Belum terhubung dengan SSO, sistem akademik, atau data resmi Universitas Pamulang.
- Belum memiliki denda keterlambatan, perpanjangan pinjaman, reservasi buku, barcode, notifikasi, atau laporan PDF/Excel.
- Belum ada pembatasan jumlah buku per anggota maupun aturan durasi pinjam yang dapat dikonfigurasi.
- Belum diuji beban pada server produksi.
- Pengujian keamanan, backup database, HTTPS, dan hardening server masih diperlukan sebelum deployment publik.

Untuk pengembangan selanjutnya, pertimbangkan pagination, indeks database berdasarkan pola pencarian, caching katalog, logging, automated tests, backup terjadwal, dan uji beban dengan skenario pengguna bersamaan.

## 10. Analisis skalabilitas

Strategi awal yang disarankan:
- **Database:** indeks pada kolom pencarian dan foreign key; gunakan pagination saat data bertambah.
- **Aplikasi:** pisahkan konfigurasi, helper, dan halaman; optimalkan query dan hindari mengambil seluruh data tanpa batas.
- **Infrastruktur:** mulai dari satu server aplikasi dan database; pisahkan database atau tambah kapasitas server ketika metrik menunjukkan kebutuhan.
- **Monitoring:** pantau CPU, RAM, koneksi database, waktu respons, error rate, dan jumlah pengguna bersamaan.
- **Pengujian:** gunakan data uji yang representatif dan alat load testing. Catat hasil aktual sebelum menyatakan target performa tercapai.

Angka target skalabilitas dalam dokumen studi kasus merupakan target rancangan, bukan hasil benchmark yang sudah dilakukan.

## 11. Dokumen pendamping

Dokumen analisis kebutuhan, materi presentasi lima slide, serta sumber diagram Use Case dan ERD tersedia di folder [docs](docs/). File sumber Markdown dan Mermaid dipakai agar isi dokumen/diagram dapat ditinjau dan diperbarui melalui Git.

## 12. Lisensi

Materi ini disiapkan untuk pembelajaran dan asesmen kompetensi.
