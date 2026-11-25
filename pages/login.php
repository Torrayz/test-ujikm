<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login | Perpustakaan UNPAM</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/app.js" defer></script>
</head>
<body class="login-wrap">
  <section class="login">
    <div class="login-brand">PERPUS UNPAM</div>
    <p class="muted uppercase-label">UNIVERSITAS PAMULANG</p>
    <h1>Sistem Informasi Perpustakaan</h1>
    <p class="muted">Masuk menggunakan akun petugas atau anggota untuk mengakses layanan perpustakaan.</p>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>" role="alert">
        <span><?= e($flash['message']) ?></span>
        <button type="button" class="alert-close" aria-label="Tutup pesan">&times;</button>
      </div>
    <?php endif; ?>

    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <label>Email
        <input type="email" name="email" required autocomplete="username" placeholder="nama@perpus.local" maxlength="150">
      </label>
      <label>Password
        <div class="pw-wrap">
          <input type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password">
          <button type="button" class="pw-toggle" aria-label="Tampilkan password">Lihat</button>
        </div>
      </label>
      <button class="btn primary full-btn" type="submit">Masuk ke Sistem</button>
    </form>

    <p class="muted login-note">Belum memiliki akun anggota? Hubungi petugas perpustakaan untuk pendaftaran.</p>
  </section>
</body>
</html>
