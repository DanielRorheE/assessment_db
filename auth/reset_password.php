<?php
include __DIR__ . '/../db.php';

$role = $_POST['role'] ?? $_GET['role'] ?? 'user';
if (!in_array($role, ['admin', 'user'], true)) {
  $role = 'user';
}
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$validToken = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$tokenHash = $validToken ? hash('sha256', $token) : '';
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $password = $_POST['password'] ?? '';
  if (strlen($password) < 8) {
    $error = 'Choose a password with at least 8 characters.';
  } elseif ($password !== ($_POST['confirm_password'] ?? '')) {
    $error = 'The passwords do not match.';
  } elseif (!$validToken) {
    $error = 'This reset link is invalid or has expired. Request another link.';
  } else {
    mysqli_begin_transaction($conn);
    try {
      $lookup = mysqli_prepare($conn, 'SELECT pr.account_id FROM password_resets pr JOIN accounts a ON a.account_id = pr.account_id WHERE pr.token_hash = ? AND a.role = ? AND pr.expires_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE');
      mysqli_stmt_bind_param($lookup, 'ss', $tokenHash, $role);
      mysqli_stmt_execute($lookup);
      $reset = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
      mysqli_stmt_close($lookup);

      if (!$reset) {
        mysqli_rollback($conn);
        $validToken = false;
        $error = 'This reset link is invalid or has expired. Request another link.';
      } else {
        $accountId = (int) $reset['account_id'];
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $update = mysqli_prepare($conn, 'UPDATE accounts SET password_hash = ? WHERE account_id = ?');
        mysqli_stmt_bind_param($update, 'si', $passwordHash, $accountId);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);

        $delete = mysqli_prepare($conn, 'DELETE FROM password_resets WHERE account_id = ?');
        mysqli_stmt_bind_param($delete, 'i', $accountId);
        mysqli_stmt_execute($delete);
        mysqli_stmt_close($delete);
        mysqli_commit($conn);
        $success = true;
      }
    } catch (Throwable $exception) {
      mysqli_rollback($conn);
      error_log('Password reset failed: ' . $exception->getMessage());
      $error = 'Unable to reset the password right now. Request a new link and try again.';
    }
  }
} elseif ($validToken) {
  $lookup = mysqli_prepare($conn, 'SELECT pr.reset_id FROM password_resets pr JOIN accounts a ON a.account_id = pr.account_id WHERE pr.token_hash = ? AND a.role = ? AND pr.expires_at > UTC_TIMESTAMP() LIMIT 1');
  mysqli_stmt_bind_param($lookup, 'ss', $tokenHash, $role);
  mysqli_stmt_execute($lookup);
  $validToken = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
  mysqli_stmt_close($lookup);
}

$title = ucfirst($role) . ' reset password';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="/assessment_db/assets/app.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <a class="auth-brand" href="/assessment_db/">
      <span class="brand-mark" aria-hidden="true">A</span>
      <span><strong>Assessment</strong><small>Service management</small></span>
    </a>
    <section class="auth-panel">
      <p class="eyebrow"><?php echo strtoupper(htmlspecialchars($role, ENT_QUOTES, 'UTF-8')); ?> ACCESS</p>
      <?php if ($success) { ?>
        <h1>Password updated</h1>
        <p class="auth-copy">Your password has been changed. You can now sign in with your new password.</p>
        <a class="button-link auth-submit-link" href="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>_login.php">Go to login</a>
      <?php } elseif (!$validToken) { ?>
        <h1>Reset link expired</h1>
        <p class="auth-copy">This reset link is invalid or has expired. Request a new one to continue.</p>
        <a class="button-link auth-submit-link" href="forgot_password.php?role=<?php echo rawurlencode($role); ?>">Request a new link</a>
      <?php } else { ?>
        <h1>Choose a new password</h1>
        <p class="auth-copy">Use at least 8 characters for your new password.</p>
        <?php if ($error !== '') { ?>
          <p class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>
        <form method="post" class="auth-form">
          <input type="hidden" name="role" value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
          <label for="password">New password</label>
          <input id="password" name="password" type="password" autocomplete="new-password" required>
          <label for="confirm_password">Confirm new password</label>
          <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
          <button type="submit">Update password</button>
        </form>
      <?php } ?>
    </section>
  </main>
</body>
</html>
