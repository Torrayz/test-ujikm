<?php
declare(strict_types=1);

$memberInfo = null;
if ($user['role'] === 'anggota') {
    $st = $pdo->prepare("SELECT member_number, member_type, phone, address, active FROM members WHERE user_id = :uid LIMIT 1");
    $st->execute(['uid' => (int) $user['id']]);
    $memberInfo = $st->fetch() ?: null;
}
?>

<div class="split-2">
  <div class="panel">
    <h3>Profil Akun</h3>
    <p class="muted">Informasi akun yang sedang masuk.</p>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_profile">
      <label>Nama Lengkap <span class="req">*</span>
        <input name="name" required maxlength="120" value="<?= e($user['name']) ?>">
      </label>
      <label>Email
        <input type="email" value="<?= e($user['email']) ?>" disabled>
      </label>
      <label>Peran
        <input value="<?= $user['role'] === 'petugas' ? 'Petugas Perpustakaan' : 'Anggota (' . ucfirst($memberInfo['member_type'] ?? 'anggota') . ')' ?>" disabled>
      </label>
      <?php if ($memberInfo): ?>
        <label>Nomor Anggota / NIM / NIP
          <input value="<?= e($memberInfo['member_number']) ?>" disabled>
        </label>
        <label>Nomor Telepon
          <input name="phone" maxlength="30" placeholder="08..." value="<?= e($memberInfo['phone'] ?? '') ?>">
        </label>
        <label>Alamat
          <textarea name="address" rows="2" maxlength="1000"><?= e($memberInfo['address'] ?? '') ?></textarea>
        </label>
      <?php endif; ?>
      <div>
        <button class="btn primary" type="submit">Simpan Profil</button>
      </div>
    </form>
  </div>

  <div class="panel">
    <h3>Ganti Password</h3>
    <p class="muted">Gunakan password baru minimal 8 karakter untuk keamanan akun.</p>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="change_password">
      <label>Password Lama <span class="req">*</span>
        <div class="pw-wrap">
          <input type="password" name="old_password" required autocomplete="current-password">
          <button type="button" class="pw-toggle" aria-label="Tampilkan password">Lihat</button>
        </div>
      </label>
      <label>Password Baru <span class="req">*</span>
        <div class="pw-wrap">
          <input type="password" name="new_password" minlength="8" required autocomplete="new-password" placeholder="Minimal 8 karakter">
          <button type="button" class="pw-toggle" aria-label="Tampilkan password">Lihat</button>
        </div>
      </label>
      <label>Konfirmasi Password Baru <span class="req">*</span>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" minlength="8" required autocomplete="new-password" placeholder="Ulangi password baru">
          <button type="button" class="pw-toggle" aria-label="Tampilkan password">Lihat</button>
        </div>
      </label>
      <div>
        <button class="btn primary" type="submit">Perbarui Password</button>
      </div>
    </form>
  </div>
</div>
