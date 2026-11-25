<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/helpers.php';

$pdo = db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'login') {
            $email = post_string('email', 150);
            $password = (string) ($_POST['password'] ?? '');
            $stmt = $pdo->prepare("SELECT id,name,email,password_hash,role FROM users WHERE email=:email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $account = $stmt->fetch();

            if (!$account || !password_verify($password, $account['password_hash'])) {
                flash('danger', 'Email atau password tidak sesuai.');
                redirect('index.php');
            }

            if ($account['role'] === 'anggota') {
                $check = $pdo->prepare("SELECT active FROM members WHERE user_id=:id");
                $check->execute(['id' => $account['id']]);
                $active = $check->fetchColumn();
                if ((int) $active !== 1) {
                    flash('danger', 'Akun anggota tidak aktif. Hubungi petugas.');
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
        $user = current_user();

        if ($action === 'logout') {
            unset($_SESSION['user']);
            session_regenerate_id(true);
            flash('success', 'Anda berhasil keluar.');
            redirect('index.php');
        }

        if ($action === 'change_password') {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=:id");
            $stmt->execute(['id' => $user['id']]);
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
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password_hash=:hash WHERE id=:id");
                $stmt->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);
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
            $year = (int) ($_POST['publication_year'] ?? 0);
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);

            if ($title === '' || $author === '' || $stock === false || $stock < 0) {
                flash('danger', 'Judul, penulis, dan stok wajib diisi dengan benar.');
                redirect('index.php?page=books');
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
                $data['id'] = $id;
                $stmt = $pdo->prepare("UPDATE books SET isbn=:isbn,title=:title,author=:author,publisher=:publisher,publication_year=:year,stock=:stock WHERE id=:id");
                $stmt->execute($data);
                flash('success', 'Data buku berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO books(isbn,title,author,publisher,publication_year,stock) VALUES(:isbn,:title,:author,:publisher,:year,:stock)");
                $stmt->execute($data);
                flash('success', 'Buku berhasil ditambahkan.');
            }
            redirect('index.php?page=books');
        }

        if ($action === 'delete_book') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE book_id=:id");
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                flash('danger', 'Buku memiliki riwayat transaksi dan tidak dapat dihapus.');
            } else {
                $pdo->prepare("DELETE FROM books WHERE id=:id")->execute(['id' => $id]);
                flash('success', 'Buku berhasil dihapus.');
            }
            redirect('index.php?page=books');
        }

        if ($action === 'add_member') {
            $name = post_string('name', 120);
            $email = post_string('email', 150);
            $number = post_string('member_number', 40);
            $type = post_string('member_type', 20);
            $password = (string) ($_POST['password'] ?? '');
            $phone = post_string('phone', 30);
            $address = post_string('address', 1000);

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $number === '' ||
                !in_array($type, ['mahasiswa','dosen'], true) || strlen($password) < 8) {
                flash('danger', 'Data anggota tidak valid. Password minimal 8 karakter.');
                redirect('index.php?page=members');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(:name,:email,:hash,'anggota')");
            $stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO members(user_id,member_number,member_type,phone,address) VALUES(:uid,:number,:type,:phone,:address)");
            $stmt->execute([
                'uid'=>$userId,'number'=>$number,'type'=>$type,
                'phone'=>$phone !== '' ? $phone : null,
                'address'=>$address !== '' ? $address : null,
            ]);
            $pdo->commit();
            flash('success', 'Anggota berhasil didaftarkan.');
            redirect('index.php?page=members');
        }

        if ($action === 'toggle_member') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0) === 1 ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE members SET active=:active WHERE id=:id");
            $stmt->execute(['active'=>$active,'id'=>$id]);
            flash('success', 'Status anggota diperbarui.');
            redirect('index.php?page=members');
        }

        if ($action === 'delete_member') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE member_id=:id");
            $stmt->execute(['id'=>$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                flash('danger', 'Anggota memiliki riwayat transaksi. Nonaktifkan akun sebagai gantinya.');
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM members WHERE id=:id");
                $stmt->execute(['id'=>$id]);
                $userId = $stmt->fetchColumn();
                if ($userId) {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM members WHERE id=:id")->execute(['id'=>$id]);
                    $pdo->prepare("DELETE FROM users WHERE id=:id AND role='anggota'")->execute(['id'=>$userId]);
                    $pdo->commit();
                }
                flash('success', 'Anggota berhasil dihapus.');
            }
            redirect('index.php?page=members');
        }

        if ($action === 'borrow') {
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $bookId = (int) ($_POST['book_id'] ?? 0);
            if ($memberId < 1 || $bookId < 1) {
                flash('danger', 'Pilih anggota dan buku.');
                redirect('index.php?page=loans');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT stock FROM books WHERE id=:id FOR UPDATE");
            $stmt->execute(['id'=>$bookId]);
            $stock = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT id FROM members WHERE id=:id AND active=1");
            $stmt->execute(['id'=>$memberId]);
            $memberExists = $stmt->fetchColumn();

            if ($stock === false || (int)$stock < 1 || !$memberExists) {
                $pdo->rollBack();
                flash('danger', 'Stok buku habis atau anggota tidak aktif.');
                redirect('index.php?page=loans');
            }

            $pdo->prepare("UPDATE books SET stock=stock-1 WHERE id=:id")->execute(['id'=>$bookId]);
            $stmt = $pdo->prepare("INSERT INTO loans(member_id,book_id,loaned_at,status,processed_by) VALUES(:member,:book,CURDATE(),'dipinjam',:staff)");
            $stmt->execute(['member'=>$memberId,'book'=>$bookId,'staff'=>$user['id']]);
            $pdo->commit();
            flash('success', 'Peminjaman berhasil dicatat dan stok buku diperbarui.');
            redirect('index.php?page=loans');
        }

        if ($action === 'return') {
            $loanId = (int) ($_POST['loan_id'] ?? 0);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT book_id,status FROM loans WHERE id=:id FOR UPDATE");
            $stmt->execute(['id'=>$loanId]);
            $loan = $stmt->fetch();

            if (!$loan || $loan['status'] !== 'dipinjam') {
                $pdo->rollBack();
                flash('warning', 'Transaksi tidak ditemukan atau sudah dikembalikan.');
                redirect('index.php?page=loans');
            }

            $pdo->prepare("UPDATE loans SET status='dikembalikan',returned_at=CURDATE() WHERE id=:id")->execute(['id'=>$loanId]);
            $pdo->prepare("UPDATE books SET stock=stock+1 WHERE id=:id")->execute(['id'=>$loan['book_id']]);
            $pdo->commit();
            flash('success', 'Pengembalian berhasil dicatat dan stok buku bertambah.');
            redirect('index.php?page=loans');
        }

        flash('warning', 'Aksi tidak dikenali.');
        redirect('index.php');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $exception->getCode() === '23000'
            ? 'Data duplikat: email, nomor anggota, atau ISBN mungkin sudah digunakan.'
            : 'Terjadi kesalahan database. Periksa data dan konfigurasi.';
        flash('danger', $message);
        redirect('index.php');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', 'Terjadi kesalahan aplikasi. Silakan coba kembali.');
        redirect('index.php');
    }
}

$flash = get_flash();
$user = current_user();

if (!$user):
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login | Perpustakaan UNPAM</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-wrap"><section class="login">
  <p class="muted">UNIVERSITAS PAMULANG</p><h1>Sistem Informasi Perpustakaan</h1><p class="muted">Masuk untuk mengakses layanan perpustakaan.</p>
  <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
  <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="login">
    <label>Email<input type="email" name="email" required autocomplete="username" placeholder="nama@kampus.ac.id"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password" placeholder="Password"></label>
    <button class="btn primary" type="submit">Masuk ke Sistem</button>
  </form><p class="muted">Belum punya akun? Hubungi petugas perpustakaan.</p>
</section></body></html>
<?php
exit;
endif;

$role = $user['role'];
$allowed = $role === 'petugas'
    ? ['dashboard','books','members','loans','catalog','settings']
    : ['catalog','history','settings'];
$page = (string) ($_GET['page'] ?? ($role === 'petugas' ? 'dashboard' : 'catalog'));
if (!in_array($page, $allowed, true)) $page = $allowed[0];

$search = trim((string)($_GET['q'] ?? ''));
$books = [];
if (in_array($page,['books','catalog'],true)) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE title LIKE :q OR author LIKE :q OR COALESCE(isbn,'') LIKE :q ORDER BY title");
    $stmt->execute(['q'=>'%'.$search.'%']);
    $books = $stmt->fetchAll();
}
$members = [];
if ($role === 'petugas' && in_array($page,['members','loans'],true)) {
    $members = $pdo->query("SELECT m.*,u.name,u.email FROM members m JOIN users u ON u.id=m.user_id ORDER BY u.name")->fetchAll();
}
$activeLoans = $history = $myHistory = [];
if ($role === 'petugas' && $page === 'loans') {
    $activeLoans = $pdo->query("SELECT l.id,l.loaned_at,m.member_number,u.name member_name,b.title book_title FROM loans l JOIN members m ON m.id=l.member_id JOIN users u ON u.id=m.user_id JOIN books b ON b.id=l.book_id WHERE l.status='dipinjam' ORDER BY l.loaned_at")->fetchAll();
    $history = $pdo->query("SELECT l.*,u.name member_name,b.title book_title FROM loans l JOIN members m ON m.id=l.member_id JOIN users u ON u.id=m.user_id JOIN books b ON b.id=l.book_id ORDER BY l.id DESC LIMIT 30")->fetchAll();
}
if ($role === 'anggota' && $page === 'history') {
    $stmt = $pdo->prepare("SELECT l.*,b.title,b.author FROM loans l JOIN members m ON m.id=l.member_id JOIN books b ON b.id=l.book_id WHERE m.user_id=:id ORDER BY l.id DESC");
    $stmt->execute(['id'=>$user['id']]);
    $myHistory = $stmt->fetchAll();
}
$stats = [];
if ($role === 'petugas' && $page === 'dashboard') {
    $stats = [
        'Judul Buku'=>(int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
        'Total Stok'=>(int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM books")->fetchColumn(),
        'Anggota Aktif'=>(int)$pdo->query("SELECT COUNT(*) FROM members WHERE active=1")->fetchColumn(),
        'Sedang Dipinjam'=>(int)$pdo->query("SELECT COUNT(*) FROM loans WHERE status='dipinjam'")->fetchColumn(),
    ];
}
$availableMembers = array_values(array_filter($members,fn($m)=>(int)$m['active']===1));
$availableBooks = $pdo->query("SELECT id,title,stock FROM books WHERE stock>0 ORDER BY title")->fetchAll();
$titles = ['dashboard'=>'Dashboard','books'=>'Manajemen Buku','members'=>'Data Anggota','loans'=>'Peminjaman & Pengembalian','catalog'=>'Katalog Buku','history'=>'Riwayat Saya','settings'=>'Pengaturan Akun'];
$title = $titles[$page] ?? 'Perpustakaan';
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | Perpustakaan UNPAM</title><link rel="stylesheet" href="assets/style.css"></head>
<body><div class="layout">
<aside class="sidebar"><div class="brand">PERPUS UNPAM<span>Library Management System</span></div><div class="nav-title">MENU UTAMA</div><nav class="nav">
<?php if($role==='petugas'): ?>
<a class="<?= $page==='dashboard'?'active':'' ?>" href="index.php?page=dashboard">▦ Dashboard</a>
<a class="<?= $page==='books'?'active':'' ?>" href="index.php?page=books">▤ Data Buku</a>
<a class="<?= $page==='members'?'active':'' ?>" href="index.php?page=members">♙ Data Anggota</a>
<a class="<?= $page==='loans'?'active':'' ?>" href="index.php?page=loans">⇄ Peminjaman</a>
<a class="<?= $page==='catalog'?'active':'' ?>" href="index.php?page=catalog">⌕ Katalog Buku</a>
<?php else: ?>
<a class="<?= $page==='catalog'?'active':'' ?>" href="index.php?page=catalog">⌕ Katalog Buku</a>
<a class="<?= $page==='history'?'active':'' ?>" href="index.php?page=history">◷ Riwayat Saya</a>
<?php endif; ?>
<a class="<?= $page==='settings'?'active':'' ?>" href="index.php?page=settings">⚙ Pengaturan Akun</a>
</nav></aside>
<main class="main"><header class="top"><div><small>Perpustakaan / <?= e($title) ?></small><h1><?= e($title) ?></h1></div><div class="actions"><span><?= e($user['name']) ?> · <?= $role==='petugas'?'Petugas':'Anggota' ?></span><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn" type="submit">Keluar</button></form></div></header>
<section class="content">
<?php if($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<?php if($page==='dashboard' && $role==='petugas'): ?>
<div class="panel"><h2>Selamat bekerja, <?= e($user['name']) ?>.</h2><p class="muted">Ringkasan pengelolaan koleksi dan aktivitas perpustakaan.</p></div>
<div class="stats"><?php foreach($stats as $label=>$value): ?><article class="stat"><span><?= e($label) ?></span><strong><?= number_format($value) ?></strong></article><?php endforeach; ?></div>
<div class="panel"><h3>Akses Cepat</h3><div class="grid"><a class="book" href="index.php?page=books"><strong>Kelola Buku</strong><p>Tambah, ubah, cari, dan hapus koleksi.</p></a><a class="book" href="index.php?page=members"><strong>Kelola Anggota</strong><p>Daftarkan mahasiswa dan dosen.</p></a><a class="book" href="index.php?page=loans"><strong>Transaksi</strong><p>Catat peminjaman dan pengembalian.</p></a></div></div>

<?php elseif($page==='books' && $role==='petugas'): ?>
<div class="panel"><h3><?= isset($_GET['edit'])?'Ubah Data Buku':'Tambah Buku' ?></h3>
<?php $editBook=null; if(isset($_GET['edit'])){$st=$pdo->prepare("SELECT * FROM books WHERE id=:id");$st->execute(['id'=>(int)$_GET['edit']]);$editBook=$st->fetch();} ?>
<form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save_book"><input type="hidden" name="id" value="<?= (int)($editBook['id']??0) ?>">
<label>ISBN<input name="isbn" maxlength="30" value="<?= e($editBook['isbn']??'') ?>"></label>
<label>Judul Buku<input name="title" required maxlength="180" value="<?= e($editBook['title']??'') ?>"></label>
<label>Penulis<input name="author" required maxlength="150" value="<?= e($editBook['author']??'') ?>"></label>
<label>Penerbit<input name="publisher" value="<?= e($editBook['publisher']??'') ?>"></label>
<label>Tahun Terbit<input type="number" name="publication_year" min="1000" max="2100" value="<?= e($editBook['publication_year']??'') ?>"></label>
<label>Stok<input type="number" name="stock" min="0" required value="<?= e($editBook['stock']??1) ?>"></label>
<div class="full actions"><button class="btn primary" type="submit"><?= $editBook?'Simpan Perubahan':'Tambah Buku' ?></button><?php if($editBook): ?><a class="btn" href="index.php?page=books">Batal</a><?php endif; ?></div>
</form></div>
<div class="panel"><div class="row"><div><h3>Daftar Koleksi</h3><p class="muted"><?= count($books) ?> buku ditemukan.</p></div><form method="get" class="actions"><input type="hidden" name="page" value="books"><input name="q" value="<?= e($search) ?>" placeholder="Cari judul / penulis / ISBN"><button class="btn" type="submit">Cari</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Judul & Penulis</th><th>ISBN</th><th>Tahun</th><th>Stok</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($books as $b): ?><tr><td><strong><?= e($b['title']) ?></strong><small><?= e($b['author']) ?> · <?= e($b['publisher']??'-') ?></small></td><td><?= e($b['isbn']??'-') ?></td><td><?= e($b['publication_year']??'-') ?></td><td><?= (int)$b['stock'] ?></td><td class="actions"><a class="btn small" href="index.php?page=books&edit=<?= (int)$b['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus buku ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_book"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn danger small">Hapus</button></form></td></tr><?php endforeach; ?>
<?php if(!$books): ?><tr><td colspan="5">Belum ada data buku.</td></tr><?php endif; ?></tbody></table></div></div>

<?php elseif($page==='members' && $role==='petugas'): ?>
<div class="panel"><h3>Registrasi Anggota</h3><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="add_member">
<label>Nama Lengkap<input name="name" required></label><label>Email Login<input type="email" name="email" required></label><label>Nomor Anggota / NIM / NIP<input name="member_number" required></label>
<label>Jenis Anggota<select name="member_type"><option value="mahasiswa">Mahasiswa</option><option value="dosen">Dosen</option></select></label><label>Password Awal<input type="password" name="password" minlength="8" required></label><label>Telepon<input name="phone"></label><label class="full">Alamat<textarea name="address" rows="2"></textarea></label>
<div class="full"><button class="btn primary">Daftarkan Anggota</button></div></form></div>
<div class="panel"><h3>Daftar Anggota</h3><div class="table-wrap"><table><thead><tr><th>Nama</th><th>Nomor Anggota</th><th>Jenis</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($members as $m): ?><tr><td><strong><?= e($m['name']) ?></strong><small><?= e($m['email']) ?></small></td><td><?= e($m['member_number']) ?></td><td><?= e(ucfirst($m['member_type'])) ?></td><td><span class="badge <?= (int)$m['active']?'':'pending' ?>"><?= (int)$m['active']?'Aktif':'Nonaktif' ?></span></td><td class="actions">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_member"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="active" value="<?= (int)$m['active']?0:1 ?>"><button class="btn small"><?= (int)$m['active']?'Nonaktifkan':'Aktifkan' ?></button></form>
<form method="post" onsubmit="return confirm('Hapus anggota ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_member"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn danger small">Hapus</button></form></td></tr><?php endforeach; ?>
<?php if(!$members): ?><tr><td colspan="5">Belum ada anggota.</td></tr><?php endif; ?></tbody></table></div></div>

<?php elseif($page==='loans' && $role==='petugas'): ?>
<div class="panel"><h3>Catat Peminjaman</h3><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="borrow">
<label>Anggota<select name="member_id" required><option value="">Pilih anggota</option><?php foreach($availableMembers as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?> — <?= e($m['member_number']) ?></option><?php endforeach; ?></select></label>
<label>Buku<select name="book_id" required><option value="">Pilih buku</option><?php foreach($availableBooks as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['title']) ?> (stok <?= (int)$b['stock'] ?>)</option><?php endforeach; ?></select></label>
<div class="actions"><button class="btn primary">Simpan Peminjaman</button></div></form></div>
<div class="panel"><h3>Belum Dikembalikan</h3><div class="table-wrap"><table><thead><tr><th>Anggota</th><th>Buku</th><th>Tanggal Pinjam</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($activeLoans as $l): ?><tr><td><?= e($l['member_name']) ?><small><?= e($l['member_number']) ?></small></td><td><?= e($l['book_title']) ?></td><td><?= e(format_date($l['loaned_at'])) ?></td><td><form method="post" onsubmit="return confirm('Konfirmasi pengembalian buku?')"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="loan_id" value="<?= (int)$l['id'] ?>"><button class="btn success small">Proses Kembali</button></form></td></tr><?php endforeach; ?>
<?php if(!$activeLoans): ?><tr><td colspan="4">Tidak ada peminjaman aktif.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><h3>Riwayat Transaksi Terbaru</h3><div class="table-wrap"><table><thead><tr><th>Anggota</th><th>Buku</th><th>Pinjam</th><th>Kembali</th><th>Status</th></tr></thead><tbody><?php foreach($history as $l): ?><tr><td><?= e($l['member_name']) ?></td><td><?= e($l['book_title']) ?></td><td><?= e(format_date($l['loaned_at'])) ?></td><td><?= e(format_date($l['returned_at'])) ?></td><td><?= e(ucfirst($l['status'])) ?></td></tr><?php endforeach; ?><?php if(!$history): ?><tr><td colspan="5">Belum ada riwayat.</td></tr><?php endif; ?></tbody></table></div></div>

<?php elseif($page==='catalog'): ?>
<div class="panel"><div class="row"><div><h3>Katalog Buku</h3><p class="muted">Cari koleksi berdasarkan judul, penulis, atau ISBN.</p></div><form method="get" class="actions"><input type="hidden" name="page" value="catalog"><input name="q" value="<?= e($search) ?>" placeholder="Cari buku"><button class="btn">Cari</button></form></div>
<div class="grid"><?php foreach($books as $b): ?><article class="book"><div class="cover"><?= e(mb_strtoupper(mb_substr($b['title'],0,1))) ?></div><h3><?= e($b['title']) ?></h3><p><?= e($b['author']) ?> · <?= e($b['publication_year']??'Tahun -') ?></p><span class="badge <?= (int)$b['stock']?'':'pending' ?>"><?= (int)$b['stock']?'Tersedia: '.(int)$b['stock']:'Stok habis' ?></span></article><?php endforeach; ?><?php if(!$books): ?><p class="muted">Buku tidak ditemukan.</p><?php endif; ?></div></div>

<?php elseif($page==='history' && $role==='anggota'): ?>
<div class="panel"><h3>Riwayat Peminjaman Saya</h3><div class="table-wrap"><table><thead><tr><th>Buku</th><th>Penulis</th><th>Tanggal Pinjam</th><th>Tanggal Kembali</th><th>Status</th></tr></thead><tbody><?php foreach($myHistory as $l): ?><tr><td><?= e($l['title']) ?></td><td><?= e($l['author']) ?></td><td><?= e(format_date($l['loaned_at'])) ?></td><td><?= e(format_date($l['returned_at'])) ?></td><td><?= e(ucfirst($l['status'])) ?></td></tr><?php endforeach; ?><?php if(!$myHistory): ?><tr><td colspan="5">Belum ada riwayat peminjaman.</td></tr><?php endif; ?></tbody></table></div></div>

<?php elseif($page==='settings'): ?>
<div class="panel" style="max-width:540px"><h3>Ganti Password</h3><p class="muted">Password baru minimal 8 karakter.</p><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="change_password">
<label>Password Lama<input type="password" name="old_password" required></label><label>Password Baru<input type="password" name="new_password" minlength="8" required></label><label>Konfirmasi Password Baru<input type="password" name="confirm_password" minlength="8" required></label><button class="btn primary">Perbarui Password</button></form></div>
<?php endif; ?>
<div class="footer">© <?= date('Y') ?> Sistem Informasi Perpustakaan Universitas Pamulang · PHP Native</div>
</section></main></div></body></html>
