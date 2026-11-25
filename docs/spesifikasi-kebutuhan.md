# Spesifikasi Kebutuhan Perangkat Lunak
## Sistem Informasi Perpustakaan Universitas Pamulang

### 1. Tujuan
Membangun aplikasi berbasis web untuk membantu pengelolaan koleksi buku, data keanggotaan, serta transaksi peminjaman dan pengembalian. Sistem digunakan oleh petugas perpustakaan, mahasiswa, dan dosen.

### 2. Ruang lingkup
Sistem menyediakan autentikasi, pengelolaan buku, pengelolaan anggota, katalog buku, peminjaman, pengembalian, dan riwayat transaksi. Sistem pada tahap ini merupakan prototipe lokal untuk studi kasus asesmen.

### 3. Aktor
- **Petugas perpustakaan:** mengelola data buku dan anggota serta memproses transaksi.
- **Anggota:** mahasiswa atau dosen yang login untuk melihat katalog dan riwayat peminjaman.

### 4. Kebutuhan fungsional dan prioritas

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-01 | Sistem menyediakan login dan logout berdasarkan hak akses | Must |
| FR-02 | Petugas dapat menambah, melihat, mengubah, mencari, dan menghapus buku | Must |
| FR-03 | Data buku menyimpan ISBN, judul, penulis, penerbit, tahun terbit, dan stok | Must |
| FR-04 | Petugas dapat mendaftarkan dan mengelola anggota mahasiswa/dosen | Must |
| FR-05 | Anggota dapat melihat dan mencari katalog buku | Must |
| FR-06 | Petugas dapat mencatat tanggal peminjaman | Must |
| FR-07 | Petugas dapat mencatat tanggal pengembalian | Must |
| FR-08 | Stok berkurang saat peminjaman dan bertambah saat pengembalian | Must |
| FR-09 | Anggota dapat melihat riwayat peminjaman miliknya sendiri | Should |
| FR-10 | Pengguna dapat mengganti password | Should |

### 5. Kebutuhan non-fungsional

| ID | Kebutuhan | Kriteria rancangan |
|---|---|---|
| NFR-01 | Keamanan autentikasi | Password di-hash, bukan disimpan sebagai teks biasa |
| NFR-02 | Keamanan input | PDO prepared statements dan validasi data |
| NFR-03 | Keamanan form | Token CSRF untuk aksi POST |
| NFR-04 | Hak akses | Petugas dan anggota memiliki akses berbeda |
| NFR-05 | Konsistensi transaksi | Perubahan stok dan transaksi dilakukan dalam database transaction |
| NFR-06 | Kemudahan penggunaan | Antarmuka responsif untuk desktop dan perangkat seluler |
| NFR-07 | Maintainability | Konfigurasi, helper, database, dan tampilan dipisahkan |
| NFR-08 | Skalabilitas | Query, indeks, pagination, dan monitoring disiapkan untuk pertumbuhan data |

### 6. Prioritas implementasi
1. Login dan role access.
2. CRUD buku dan data anggota.
3. Peminjaman dan pengembalian beserta pembaruan stok.
4. Katalog dan riwayat anggota.
5. Pengaturan password dan penyempurnaan keamanan.

### 7. Spesifikasi teknis
- Backend: PHP Native 8.1+.
- Database: MySQL 8+ atau MariaDB.
- Database access: PDO MySQL.
- Frontend: HTML5, CSS3, JavaScript sederhana.
- Web server: Apache/XAMPP untuk pengembangan lokal.

### 8. Entitas basis data
- **users:** akun login dan peran pengguna.
- **members:** profil keanggotaan yang terhubung ke users.
- **books:** data bibliografi dan stok.
- **loans:** transaksi peminjaman/pengembalian yang menghubungkan anggota, buku, dan petugas pemroses.

### 9. Analisis skalabilitas
- Tambahkan pagination pada tabel buku, anggota, dan transaksi ketika jumlah data meningkat.
- Gunakan indeks pada kolom yang sering dicari, seperti judul, penulis, status transaksi, dan foreign key.
- Pantau waktu respons, penggunaan CPU/RAM, koneksi database, dan error rate.
- Lakukan uji beban dengan jumlah pengguna bersamaan dan dataset yang representatif.
- Jika satu server tidak lagi memadai, pisahkan database dari web server dan pertimbangkan caching katalog.
- Jangan menyatakan target waktu respons atau kapasitas pengguna tercapai sebelum ada hasil pengukuran.

### 10. Batasan
Prototipe belum terintegrasi dengan SSO atau sistem akademik UNPAM, belum memiliki fitur denda, reservasi, notifikasi, barcode, dan belum melalui uji beban produksi.
