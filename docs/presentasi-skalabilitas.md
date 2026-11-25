# Materi Presentasi – Analisis Skalabilitas Perangkat Lunak
## Sistem Informasi Perpustakaan Universitas Pamulang

Materi ini disusun untuk presentasi maksimal lima slide sesuai instruksi studi kasus.

### Slide 1 – Judul dan Latar Belakang
**Sistem Informasi Perpustakaan Universitas Pamulang**

Perpustakaan membutuhkan pengelolaan koleksi buku, keanggotaan, dan transaksi yang terpusat. Proses manual berisiko menimbulkan duplikasi data, kesalahan pencatatan stok, dan keterlambatan pencarian informasi.

### Slide 2 – Analisis Kebutuhan
**Aktor:** petugas perpustakaan, mahasiswa, dan dosen.

**Fungsi utama:**
- Login sesuai hak akses.
- Pengelolaan buku dan anggota.
- Pencarian katalog.
- Pencatatan peminjaman dan pengembalian.
- Riwayat transaksi anggota.

### Slide 3 – Rancangan Teknis dan Basis Data
**Teknologi:** PHP Native 8.1+, MySQL/MariaDB, HTML, CSS, JavaScript, Apache/XAMPP.

**Entitas:** users, members, books, loans.

Relasi utama: satu akun anggota memiliki satu profil anggota; satu anggota dapat memiliki banyak transaksi; satu buku dapat tercatat pada banyak transaksi; satu petugas dapat memproses banyak transaksi.

### Slide 4 – Analisis Skalabilitas
- Gunakan indeks database untuk kolom pencarian dan relasi.
- Terapkan pagination agar tidak memuat seluruh data sekaligus.
- Optimalkan query dan pantau waktu respons.
- Siapkan pemisahan web server dan database saat beban meningkat.
- Uji beban menggunakan skenario pengguna bersamaan dan dataset representatif.

**Catatan:** target performa harus dibuktikan melalui pengujian; belum ada hasil benchmark produksi pada prototipe ini.

### Slide 5 – Kesimpulan
Aplikasi menyediakan fungsi inti pengelolaan perpustakaan melalui web. Struktur PHP Native dan MySQL cukup untuk prototipe studi kasus. Peningkatan skala perlu dilakukan berdasarkan hasil monitoring dan load testing, bukan asumsi.
