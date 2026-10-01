<?php
include __DIR__ . '/../db.php';

$role = $_POST['role'] ?? $_GET['role'] ?? 'user';
if (!in_array($role, ['admin', 'user'], true)) {
  $role = 'user';
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Enter a valid email address.';
  } else {
    $accountStatement = mysqli_prepare($conn, 'SELECT account_id FROM accounts WHERE email = ? AND role = ? LIMIT 1');
    mysqli_stmt_bind_param($accountStatement, 'ss', $email, $role);
    mysqli_stmt_execute($accountStatement);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($accountStatement));
    mysqli_stmt_close($accountStatement);

    if ($account) {
      $accountId = (int) $account['account_id'];
      $recentStatement = mysqli_prepare($conn, 'SELECT reset_id FROM password_resets WHERE account_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1');
      mysqli_stmt_bind_param($recentStatement, 'i', $accountId);
      mysqli_stmt_execute($recentStatement);
      $recentRequest = mysqli_fetch_assoc(mysqli_stmt_get_result($recentStatement));
      mysqli_stmt_close($recentStatement);

      if (!$recentRequest) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        mysqli_begin_transaction($conn);
        try {
          $deleteStatement = mysqli_prepare($conn, 'DELETE FROM password_resets WHERE account_id = ?');
          mysqli_stmt_bind_param($deleteStatement, 'i', $accountId);
          mysqli_stmt_execute($deleteStatement);
          mysqli_stmt_close($deleteStatement);

          $insertStatement = mysqli_prepare($conn, 'INSERT INTO password_resets (account_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))');
          mysqli_stmt_bind_param($insertStatement, 'is', $accountId, $tokenHash);
          mysqli_stmt_execute($insertStatement);
          mysqli_stmt_close($insertStatement);
          mysqli_commit($conn);

          $serverName = preg_replace('/[^a-zA-Z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
          $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
          $resetUrl = $scheme . '://' . $serverName . '/assessment_db/auth/reset_password.php?role=' . rawurlencode($role) . '&token=' . rawurlencode($token);
          $fromAddress = getenv('ASSESSMENT_MAIL_FROM') ?: 'no-reply@example.com';
          if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $fromAddress = 'no-reply@example.com';
          }
          $headers = "From: Assessment Service <" . $fromAddress . ">\r\n";
          $body = "We received a request to reset your Assessment Service password.\r\n\r\n";
          $body .= "Use the link below within 30 minutes to choose a new password:\r\n" . $resetUrl . "\r\n\r\n";
          $body .= "If you did not request this change, you can ignore this email.\r\n";

          if (!@mail($email, 'Reset your Assessment Service password', $body, $headers)) {
            error_log('Password reset email could not be sent. Configure PHP mail transport.');
            $cleanupStatement = mysqli_prepare($conn, 'DELETE FROM password_resets WHERE account_id = ?');
            mysqli_stmt_bind_param($cleanupStatement, 'i', $accountId);
            mysqli_stmt_execute($cleanupStatement);
            mysqli_stmt_close($cleanupStatement);
          }
        } catch (Throwable $exception) {
          mysqli_rollback($conn);
          error_log('Password reset request failed: ' . $exception->getMessage());
        }
      }
    }

    $message = 'If an account matches that email and role, a password reset link will be sent shortly.';
  }
}

$title = ucfirst($role) . ' password reset';
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
      <h1>Forgot password?</h1>
      <p class="auth-copy">Enter the email for your account. We’ll send a reset link if the account is found.</p>
      <?php if ($error !== '') { ?>
        <p class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php } ?>
      <?php if ($message !== '') { ?>
        <p class="alert alert-success" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php } ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit">Send reset link</button>
      </form>
      <p class="auth-switch"><a href="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>_login.php">Back to <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?> login</a></p>
    </section>
  </main>
</body>
</html>
