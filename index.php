<?php
// Full source code is included in the downloadable project archive.
// This repository copy is a PHP Native starter entry point.
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/helpers.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<h1>Sistem Informasi Perpustakaan UNPAM</h1>
<p>PHP Native starter. See README.md for setup instructions.</p>
