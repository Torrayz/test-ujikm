<?php
declare(strict_types=1);

function handle_post_request(PDO $pdo): never
{
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $returnTo = (string) ($_POST['return_to'] ?? '');

    $actionPageMap = [
        'login' => 'index.php',
        'logout' => 'index.php',
        'change_password' => 'index.php?page=settings',
        'update_profile' => 'index.php?page=settings',
        'save_book' => 'index.php?page=books',
        'delete_book' => 'index.php?page=books',
        'add_member' => 'index.php?page=members',
        'save_member' => 'index.php?page=members',
        'toggle_member' => 'index.php?page=members',
        'delete_member' => 'index.php?page=members',
        'borrow' => 'index.php?page=loans',
        'return' => 'index.php?page=loans',
    ];
    $fallbackUrl = $actionPageMap[$action] ?? 'index.php';
    if ($returnTo !== '' && str_starts_with($returnTo, 'index.php')) {
        $fallbackUrl = $returnTo;
    }

    try {
        if ($action === 'login') {
            $email = mb_strtolower(post_string('email', 150));
            $password = (string) ($_POST['password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                flash('danger', 'Masukkan email dan password yang valid.');
                redirect('index.php');
            }

            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $account = $stmt->fetch();

            if (!$account || !password_verify($password, $account['password_hash'])) {
                flash('danger', 'Email atau password tidak sesuai.');
                redirect('index.php');
            }

            if ($account['role'] === 'anggota') {
                $check = $pdo->prepare("SELECT active FROM members WHERE user_id = :id LIMIT 1");
                $check->execute(['id' => $account['id']]);
                $active = $check->fetchColumn();
                if ((int) $active !== 1) {
                    flash('danger', 'Akun anggota tidak aktif. Hubungi petugas perpustakaan.');
                    redirect('index.php');
                }
            }

            session_regenerate_id(true);
            unset($account['password_hash']);
            $_SESSION['user'] = $account;
            flash('success', 'Selamat datang, ' . $account['name'] . '!');
            redirect('index.php');
        }

        require_login();
        enforce_active_session($pdo);
        $user = current_user();

        if ($action === 'logout') {
            unset($_SESSION['user']);
            session_regenerate_id(true);
            flash('success', 'Anda berhasil keluar.');
            redirect('index.php');
        }

        if ($action === 'update_profile') {
            $name = post_string('name', 120);
            if ($name === '') {
                flash('danger', 'Nama lengkap tidak boleh kosong.');
                redirect('index.php?page=settings');
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET name = :name WHERE id = :id")
                ->execute(['name' => $name, 'id' => (int) $user['id']]);

            if ($user['role'] === 'anggota') {
                $phone = post_string('phone', 30);
                $address = post_string('address', 1000);
                $pdo->prepare("UPDATE members SET phone = :phone, address = :address WHERE user_id = :uid")
                    ->execute([
                        'phone' => $phone !== '' ? $phone : null,
                        'address' => $address !== '' ? $address : null,
                        'uid' => (int) $user['id'],
                    ]);
            }
            $pdo->commit();

            $_SESSION['user']['name'] = $name;
            flash('success', 'Profil akun berhasil diperbarui.');
            redirect('index.php?page=settings');
        }

        if ($action === 'change_password') {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute(['id' => (int) $user['id']]);
            $oldHash = $stmt->fetchColumn();
            $old = (string) ($_POST['old_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if (!$oldHash || !password_verify($old, $oldHash)) {
                flash('danger', 'Password lama tidak benar.');
            } elseif (strlen($new) < 8) {
                flash('danger', 'Password baru minimal 8 karakter.');
            } elseif ($new !== $confirm) {
                flash('danger', 'Konfirmasi password tidak sama.');
            } elseif (password_verify($new, $oldHash)) {
                flash('warning', 'Password baru harus berbeda dari password lama.');
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $stmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => (int) $user['id']]);
                flash('success', 'Password berhasil diperbarui.');
            }
            redirect('index.php?page=settings');
        }

        require_role('petugas');

        if ($action === 'save_book') {
            $id = (int) ($_POST['id'] ?? 0);
            $isbn = post_string('isbn', 30);
            $title = post_string('title', 180);
            $author = post_string('author', 150);
            $publisher = post_string('publisher', 150);
            $yearRaw = trim((string) ($_POST['publication_year'] ?? ''));
            $year = $yearRaw === '' ? 0 : filter_var($yearRaw, FILTER_VALIDATE_INT);
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
            $maxYear = (int) date('Y') + 1;

            if ($title === '' || $author === '' || $stock === false || $stock < 0) {
                flash('danger', 'Judul, penulis, dan stok (minimal 0) wajib diisi dengan benar.');
                redirect($id > 0 ? 'index.php?page=books&edit=' . $id : $fallbackUrl);
            }

            if ($year === false || ($year !== 0 && ($year < 1000 || $year > $maxYear))) {
                flash('danger', 'Tahun terbit harus antara 1000 sampai ' . $maxYear . '.');
                redirect($id > 0 ? 'index.php?page=books&edit=' . $id : $fallbackUrl);
            }

            $data = [
                'isbn' => $isbn !== '' ? $isbn : null,
                'title' => $title,
                'author' => $author,
                'publisher' => $publisher !== '' ? $publisher : null,
                'year' => $year > 0 ? $year : null,
                'stock' => $stock,
            ];

            if ($id > 0) {
                $check = $pdo->prepare("SELECT id FROM books WHERE id = :id");
                $check->execute(['id' => $id]);
                if (!$check->fetchColumn()) {
                    flash('danger', 'Data buku yang akan diubah tidak ditemukan.');
                    redirect('index.php?page=books');
                }
                $data['id'] = $id;
                $stmt = $pdo->prepare("UPDATE books SET isbn=:isbn, title=:title, author=:author, publisher=:publisher, publication_year=:year, stock=:stock WHERE id=:id");
                $stmt->execute($data);
                flash('success', 'Data buku berhasil diperbarui.');
                redirect('index.php?page=books');
            }

            $stmt = $pdo->prepare("INSERT INTO books(isbn, title, author, publisher, publication_year, stock) VALUES(:isbn, :title, :author, :publisher, :year, :stock)");
            $stmt->execute($data);
            flash('success', 'Buku berhasil ditambahkan.');
            redirect($fallbackUrl);
        }

        if ($action === 'delete_book') {
            $id = (int) ($_POST['id'] ?? 0);
            $exists = $pdo->prepare("SELECT id FROM books WHERE id = :id");
            $exists->execute(['id' => $id]);
            if (!$exists->fetchColumn()) {
                flash('warning', 'Buku tidak ditemukan.');
                redirect($fallbackUrl);
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE book_id = :id");
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                flash('danger', 'Buku memiliki riwayat transaksi dan tidak dapat dihapus.');
            } else {
                $pdo->prepare("DELETE FROM books WHERE id = :id")->execute(['id' => $id]);
                flash('success', 'Buku berhasil dihapus.');
            }
            redirect($fallbackUrl);
        }

        if ($action === 'add_member' || $action === 'save_member') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = post_string('name', 120);
            $email = mb_strtolower(post_string('email', 150));
            $number = post_string('member_number', 40);
            $type = post_string('member_type', 20);
            $password = (string) ($_POST['password'] ?? '');
            $phone = post_string('phone', 30);
            $address = post_string('address', 1000);

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $number === '' || !in_array($type, ['mahasiswa', 'dosen'], true)) {
                flash('danger', 'Data anggota tidak valid. Periksa nama, email, nomor anggota, dan jenis anggota.');
                redirect($id > 0 ? 'index.php?page=members&edit=' . $id : $fallbackUrl);
            }

            if ($id === 0 && strlen($password) < 8) {
                flash('danger', 'Password awal wajib diisi minimal 8 karakter.');
                redirect($fallbackUrl);
            }

            if ($id > 0 && $password !== '' && strlen($password) < 8) {
                flash('danger', 'Password baru minimal 8 karakter.');
                redirect('index.php?page=members&edit=' . $id);
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $id]);
                $userId = $stmt->fetchColumn();
                if (!$userId) {
                    flash('danger', 'Data anggota tidak ditemukan.');
                    redirect('index.php?page=members');
                }

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET name = :name, email = :email WHERE id = :uid AND role = 'anggota'")
                    ->execute(['name' => $name, 'email' => $email, 'uid' => (int) $userId]);

                if ($password !== '') {
                    $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid AND role = 'anggota'")
                        ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'uid' => (int) $userId]);
                }

                $pdo->prepare("UPDATE members SET member_number = :number, member_type = :type, phone = :phone, address = :address WHERE id = :id")
                    ->execute([
                        'number' => $number,
                        'type' => $type,
                        'phone' => $phone !== '' ? $phone : null,
                        'address' => $address !== '' ? $address : null,
                        'id' => $id,
                    ]);
                $pdo->commit();
                flash('success', 'Data anggota berhasil diperbarui.');
                redirect('index.php?page=members');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users(name, email, password_hash, role) VALUES(:name, :email, :hash, 'anggota')");
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO members(user_id, member_number, member_type, phone, address) VALUES(:uid, :number, :type, :phone, :address)");
            $stmt->execute([
                'uid' => $userId,
                'number' => $number,
                'type' => $type,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null,
            ]);
            $pdo->commit();
            flash('success', 'Anggota berhasil didaftarkan.');
            redirect($fallbackUrl);
        }

        if ($action === 'toggle_member') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE members SET active = :active WHERE id = :id");
            $stmt->execute(['active' => $active, 'id' => $id]);
            if ($stmt->rowCount() === 0) {
                flash('warning', 'Anggota tidak ditemukan atau status tidak berubah.');
            } else {
                flash('success', $active === 1 ? 'Akun anggota diaktifkan.' : 'Akun anggota dinonaktifkan.');
            }
            redirect($fallbackUrl);
        }

        if ($action === 'delete_member') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE member_id = :id");
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                flash('danger', 'Anggota memiliki riwayat transaksi. Nonaktifkan akun sebagai gantinya.');
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $userId = $stmt->fetchColumn();
                if ($userId) {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM members WHERE id = :id")->execute(['id' => $id]);
                    $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'anggota'")->execute(['id' => (int) $userId]);
                    $pdo->commit();
                    flash('success', 'Anggota berhasil dihapus.');
                } else {
                    flash('warning', 'Data anggota tidak ditemukan.');
                }
            }
            redirect($fallbackUrl);
        }

        if ($action === 'borrow') {
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $bookId = (int) ($_POST['book_id'] ?? 0);
            if ($memberId < 1 || $bookId < 1) {
                flash('danger', 'Pilih anggota dan buku terlebih dahulu.');
                redirect('index.php?page=loans');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT stock FROM books WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $bookId]);
            $stock = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT id FROM members WHERE id = :id AND active = 1");
            $stmt->execute(['id' => $memberId]);
            $memberExists = $stmt->fetchColumn();

            if ($stock === false || (int) $stock < 1 || !$memberExists) {
                $pdo->rollBack();
                flash('danger', 'Stok buku habis atau anggota tidak aktif.');
                redirect('index.php?page=loans');
            }

            $pdo->prepare("UPDATE books SET stock = stock - 1 WHERE id = :id")->execute(['id' => $bookId]);
            $stmt = $pdo->prepare("INSERT INTO loans(member_id, book_id, loaned_at, status, processed_by) VALUES(:member, :book, CURDATE(), 'dipinjam', :staff)");
            $stmt->execute(['member' => $memberId, 'book' => $bookId, 'staff' => (int) $user['id']]);
            $pdo->commit();
            flash('success', 'Peminjaman berhasil dicatat dan stok buku diperbarui.');
            redirect('index.php?page=loans');
        }

        if ($action === 'return') {
            $loanId = (int) ($_POST['loan_id'] ?? 0);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT book_id, status FROM loans WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $loanId]);
            $loan = $stmt->fetch();

            if (!$loan || $loan['status'] !== 'dipinjam') {
                $pdo->rollBack();
                flash('warning', 'Transaksi tidak ditemukan atau sudah dikembalikan.');
                redirect($fallbackUrl);
            }

            $pdo->prepare("UPDATE loans SET status = 'dikembalikan', returned_at = CURDATE() WHERE id = :id")
                ->execute(['id' => $loanId]);
            $pdo->prepare("UPDATE books SET stock = stock + 1 WHERE id = :id")
                ->execute(['id' => (int) $loan['book_id']]);
            $pdo->commit();
            flash('success', 'Pengembalian berhasil dicatat dan stok buku bertambah.');
            redirect($fallbackUrl);
        }

        flash('warning', 'Aksi tidak dikenali.');
        redirect('index.php');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $exception->getCode() === '23000'
            ? 'Data duplikat: email, nomor anggota, atau ISBN sudah digunakan.'
            : 'Terjadi kesalahan database. Periksa data dan konfigurasi.';
        flash('danger', $message);
        redirect($fallbackUrl);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', 'Terjadi kesalahan aplikasi. Silakan coba kembali.');
        redirect($fallbackUrl);
    }
}
