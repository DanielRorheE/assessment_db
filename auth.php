<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

function require_role(string $role): void
{
  if (empty($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== $role) {
    $loginPage = $role === 'admin' ? 'admin_login.php' : 'user_login.php';
    header('Location: /assessment_db/auth/' . $loginPage);
    exit;
  }
}

function redirect_authenticated_user(): void
{
  if (!empty($_SESSION['account_id'])) {
    $destination = ($_SESSION['role'] ?? '') === 'admin'
      ? '/assessment_db/index.php'
      : '/assessment_db/pages/user_home.php';
    header('Location: ' . $destination);
    exit;
  }
}
?>
