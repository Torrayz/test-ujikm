USE perpustakaan_unpam;

INSERT IGNORE INTO users (name, email, password_hash, role) VALUES
  ('Rizky Pratama', 'rizky.mhs@unpam.ac.id', '$2y$10$h8KsL0/MQow5gX7GaTJVs.V0RJ4t92QLStVataA9zeK6gp59acp5a', 'anggota'),
  ('Siti Rahmawati', 'siti.mhs@unpam.ac.id', '$2y$10$h8KsL0/MQow5gX7GaTJVs.V0RJ4t92QLStVataA9zeK6gp59acp5a', 'anggota'),
  ('Budi Santoso, M.Kom.', 'budi.dosen@unpam.ac.id', '$2y$10$h8KsL0/MQow5gX7GaTJVs.V0RJ4t92QLStVataA9zeK6gp59acp5a', 'anggota'),
  ('Dewi Lestari', 'dewi.mhs@unpam.ac.id', '$2y$10$h8KsL0/MQow5gX7GaTJVs.V0RJ4t92QLStVataA9zeK6gp59acp5a', 'anggota');

INSERT IGNORE INTO members (user_id, member_number, member_type, phone, address, active)
SELECT id, '221011400101', 'mahasiswa', '081234567801', 'Pamulang Barat, Tangerang Selatan', 1
FROM users WHERE email = 'rizky.mhs@unpam.ac.id';

INSERT IGNORE INTO members (user_id, member_number, member_type, phone, address, active)
SELECT id, '221011400205', 'mahasiswa', '081234567802', 'Ciputat Timur, Tangerang Selatan', 1
FROM users WHERE email = 'siti.mhs@unpam.ac.id';

INSERT IGNORE INTO members (user_id, member_number, member_type, phone, address, active)
SELECT id, '0415088901', 'dosen', '081234567803', 'Serpong, Tangerang Selatan', 1
FROM users WHERE email = 'budi.dosen@unpam.ac.id';

INSERT IGNORE INTO members (user_id, member_number, member_type, phone, address, active)
SELECT id, '211011400089', 'mahasiswa', '081234567804', 'Pondok Cabe, Tangerang Selatan', 0
FROM users WHERE email = 'dewi.mhs@unpam.ac.id';

INSERT IGNORE INTO books (isbn, title, author, publisher, publication_year, stock) VALUES
  ('978-602-6232-11-1', 'Rekayasa Perangkat Lunak Terstruktur', 'Rosa A.S. & M. Shalahuddin', 'Informatika Bandung', 2021, 5),
  ('978-602-0496-22-4', 'Basis Data Relasional dengan MySQL', 'Abdul Kadir', 'Andi Publisher', 2022, 4),
  ('978-602-8758-33-0', 'Pemrograman Web PHP Native Modern', 'Budi Raharjo', 'Informatika Bandung', 2023, 6),
  ('978-013-2350-88-4', 'Clean Code: A Handbook of Agile Software Craftsmanship', 'Robert C. Martin', 'Prentice Hall', 2019, 2),
  ('978-149-1950-35-7', 'Designing Data-Intensive Applications', 'Martin Kleppmann', 'O\'Reilly Media', 2020, 1),
  ('978-602-1514-44-2', 'Algoritma dan Struktur Data', 'Rinaldi Munir', 'Informatika Bandung', 2022, 7),
  ('978-623-0102-55-8', 'Jaringan Komputer & Keamanan Sistem', 'Iwan Sofana', 'Informatika Bandung', 2023, 3),
  ('978-020-1633-61-0', 'Design Patterns: Elements of Reusable Object-Oriented Software', 'Erich Gamma dkk.', 'Addison-Wesley', 2018, 0),
  ('978-623-7131-66-5', 'Sistem Operasi Konsep dan Implementasi', 'Onno W. Purbo', 'Andi Publisher', 2021, 4),
  ('978-602-6232-77-7', 'Analisis dan Perancangan Sistem Informasi', 'Jogiyanto HM', 'Andi Publisher', 2020, 3),
  ('978-144-9373-32-0', 'Building Microservices', 'Sam Newman', 'O\'Reilly Media', 2021, 2),
  ('978-623-0118-88-5', 'Pengujian & Penjaminan Mutu Perangkat Lunak', 'Suryo Atmojo', 'Deepublish', 2024, 5);

INSERT INTO loans (member_id, book_id, loaned_at, returned_at, status, processed_by)
SELECT m.id, b.id, DATE_SUB(CURDATE(), INTERVAL 4 DAY), NULL, 'dipinjam', s.id
FROM members m
JOIN users u ON u.id = m.user_id AND u.email = 'rizky.mhs@unpam.ac.id'
JOIN books b ON b.isbn = '978-013-2350-88-4'
JOIN users s ON s.role = 'petugas'
WHERE NOT EXISTS (SELECT 1 FROM loans LIMIT 1)
LIMIT 1;

INSERT INTO loans (member_id, book_id, loaned_at, returned_at, status, processed_by)
SELECT m.id, b.id, DATE_SUB(CURDATE(), INTERVAL 9 DAY), NULL, 'dipinjam', s.id
FROM members m
JOIN users u ON u.id = m.user_id AND u.email = 'budi.dosen@unpam.ac.id'
JOIN books b ON b.isbn = '978-149-1950-35-7'
JOIN users s ON s.role = 'petugas'
WHERE NOT EXISTS (
  SELECT 1 FROM loans l2 WHERE l2.member_id = m.id AND l2.book_id = b.id AND l2.status = 'dipinjam'
)
LIMIT 1;

INSERT INTO loans (member_id, book_id, loaned_at, returned_at, status, processed_by)
SELECT m.id, b.id, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'dikembalikan', s.id
FROM members m
JOIN users u ON u.id = m.user_id AND u.email = 'siti.mhs@unpam.ac.id'
JOIN books b ON b.isbn = '978-602-8758-33-0'
JOIN users s ON s.role = 'petugas'
WHERE NOT EXISTS (
  SELECT 1 FROM loans l2 WHERE l2.member_id = m.id AND l2.book_id = b.id AND l2.status = 'dikembalikan'
)
LIMIT 1;
