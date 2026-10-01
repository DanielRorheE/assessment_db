<?php
include __DIR__ . '/../db.php';
include __DIR__ . '/../auth.php';

$role = $authRole ?? '';
$mode = $authMode ?? '';
if (!in_array($role, ['admin', 'user'], true) || !in_array($mode, ['login', 'register'], true)) {
  http_response_code(404);
  exit('Page not found.');
}

$adminSession = $role === 'admin' && $mode === 'register' && ($_SESSION['role'] ?? '') === 'admin';
if (!$adminSession) {
  redirect_authenticated_user();
}

$title = ucfirst($role) . ' ' . ($mode === 'login' ? 'Log in' : 'Registration');
$error = '';
$registrationClosed = false;
$adminCount = 0;
if ($role === 'admin' && $mode === 'register') {
  $result = mysqli_query($conn, "SELECT COUNT(*) AS admin_count FROM accounts WHERE role = 'admin'");
  $adminCount = (int) mysqli_fetch_assoc($result)['admin_count'];
  $registrationClosed = $adminCount > 0 && !$adminSession;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$registrationClosed) {
  $fullName = trim($_POST['full_name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Enter a valid email address.';
  } elseif ($mode === 'register' && ($fullName === '' || strlen($fullName) > 150)) {
    $error = 'Enter your name (up to 150 characters).';
  } elseif ($mode === 'register' && strlen($password) < 8) {
    $error = 'Choose a password with at least 8 characters.';
  } elseif ($mode === 'register' && $password !== ($_POST['confirm_password'] ?? '')) {
    $error = 'The passwords do not match.';
  } elseif ($mode === 'login' && $password === '') {
    $error = 'Enter your password.';
  } else {
    if ($mode === 'login') {
      $statement = mysqli_prepare($conn, 'SELECT account_id, client_id, full_name, email, password_hash, role FROM accounts WHERE email = ? AND role = ? LIMIT 1');
      mysqli_stmt_bind_param($statement, 'ss', $email, $role);
      mysqli_stmt_execute($statement);
      $account = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
      mysqli_stmt_close($statement);

      if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['account_id'];
        $_SESSION['client_id'] = $account['client_id'] === null ? null : (int) $account['client_id'];
        $_SESSION['full_name'] = $account['full_name'];
        $_SESSION['email'] = $account['email'];
        $_SESSION['role'] = $account['role'];
        header('Location: ' . ($role === 'admin' ? '/assessment_db/index.php' : '/assessment_db/pages/user_home.php'));
        exit;
      }
      $error = 'Email or password is incorrect.';
    } else {
      $passwordHash = password_hash($password, PASSWORD_DEFAULT);
      mysqli_begin_transaction($conn);
      try {
        $clientId = null;
        if ($role === 'user') {
          $clientStatement = mysqli_prepare($conn, 'INSERT INTO clients (full_name, email) VALUES (?, ?)');
          mysqli_stmt_bind_param($clientStatement, 'ss', $fullName, $email);
          if (!mysqli_stmt_execute($clientStatement)) {
            throw new RuntimeException('Unable to create the client record.');
          }
          $clientId = mysqli_insert_id($conn);
          mysqli_stmt_close($clientStatement);
        }

        if ($role === 'admin') {
          $accountStatement = mysqli_prepare($conn, 'INSERT INTO accounts (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
          mysqli_stmt_bind_param($accountStatement, 'ssss', $fullName, $email, $passwordHash, $role);
        } else {
          $accountStatement = mysqli_prepare($conn, 'INSERT INTO accounts (client_id, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)');
          mysqli_stmt_bind_param($accountStatement, 'issss', $clientId, $fullName, $email, $passwordHash, $role);
        }
        if (!mysqli_stmt_execute($accountStatement)) {
          throw new RuntimeException('Unable to create the account.');
        }
        $accountId = mysqli_insert_id($conn);
        mysqli_stmt_close($accountStatement);
        mysqli_commit($conn);

        session_regenerate_id(true);
        $_SESSION['account_id'] = $accountId;
        $_SESSION['client_id'] = $clientId;
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        header('Location: ' . ($role === 'admin' ? '/assessment_db/index.php' : '/assessment_db/pages/user_home.php'));
        exit;
      } catch (Throwable $exception) {
        mysqli_rollback($conn);
        $error = 'An account with that email may already exist. Check the details and try again.';
      }
    }
  }
}
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
      <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="auth-copy"><?php echo $role === 'admin' ? 'Manage services, bookings, clients, and payments.' : 'Create and access your customer account.'; ?></p>

      <?php if ($error !== '') { ?>
        <p class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php } ?>

      <?php if ($registrationClosed) { ?>
        <p class="alert alert-error" role="status">Admin registration is closed. Sign in with an existing admin account.</p>
      <?php } else { ?>
        <form method="post" class="auth-form">
          <?php if ($mode === 'register') { ?>
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" type="text" maxlength="150" autocomplete="name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <?php } ?>
          <label for="email">Email</label>
          <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="<?php echo $mode === 'login' ? 'current-password' : 'new-password'; ?>" required>
          <?php if ($mode === 'register') { ?>
            <label for="confirm_password">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
          <?php } ?>
          <button type="submit"><?php echo $mode === 'login' ? 'Log in' : 'Create account'; ?></button>
        </form>
      <?php } ?>

      <p class="auth-switch">
        <?php if ($mode === 'login') { ?>
          New <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>? <a href="<?php echo $role; ?>_register.php">Register here</a>
        <?php } else { ?>
          Already registered? <a href="<?php echo $role; ?>_login.php">Log in</a>
        <?php } ?>
      </p>
      <a class="auth-other" href="<?php echo $role === 'admin' ? 'user_login.php' : 'admin_login.php'; ?>">Use <?php echo $role === 'admin' ? 'user' : 'admin'; ?> sign in</a>
    </section>
  </main>
</body>
</html>
