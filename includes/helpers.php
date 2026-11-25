<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
function e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function require_login(): void { if(empty($_SESSION['user'])) { header('Location: ../index.php'); exit; } }
function csrf_token(): string { return $_SESSION['csrf_token']??=bin2hex(random_bytes(32)); }
