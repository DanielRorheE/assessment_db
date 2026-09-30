<?php
include "../db.php";

$escape = static function ($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
};
$bookingId = filter_var($_GET["booking_id"] ?? "", FILTER_VALIDATE_INT) ?: 0;
$errors = [];
$allowedMethods = ["CASH", "GCASH", "BANK TRANSFER"];

if (isset($_POST["record_payment"])) {
  $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT) ?: 0;
  $amountInput = trim($_POST["amount_paid"] ?? "");
  $method = $_POST["method"] ?? "";

  if ($bookingId < 1) {
    $errors[] = "Choose a valid booking.";
  }
  if (!is_numeric($amountInput) || (float)$amountInput <= 0) {
    $errors[] = "Enter a payment amount greater than zero.";
  }
  if (!in_array($method, $allowedMethods, true)) {
    $errors[] = "Choose a valid payment method.";
  }

  if (!$errors) {
    $transactionStarted = false;
    try {
      mysqli_begin_transaction($conn);
      $transactionStarted = true;

      $bookingStatement = mysqli_prepare($conn, "SELECT total_cost FROM bookings WHERE booking_id = ? FOR UPDATE");
      mysqli_stmt_bind_param($bookingStatement, "i", $bookingId);
      mysqli_stmt_execute($bookingStatement);
      $bookingResult = mysqli_stmt_get_result($bookingStatement);
      $lockedBooking = mysqli_fetch_assoc($bookingResult);

      if (!$lockedBooking) {
        $errors[] = "That booking could not be found.";
        mysqli_rollback($conn);
        $transactionStarted = false;
      } else {
        $paidStatement = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS paid FROM payments WHERE booking_id = ?");
        mysqli_stmt_bind_param($paidStatement, "i", $bookingId);
        mysqli_stmt_execute($paidStatement);
        $paidRow = mysqli_fetch_assoc(mysqli_stmt_get_result($paidStatement));
        $balance = max(0, (float)$lockedBooking["total_cost"] - (float)$paidRow["paid"]);
        $amount = round((float)$amountInput, 2);

        if ($amount > $balance + 0.001) {
          $errors[] = "Payment exceeds the remaining balance of ₱" . number_format($balance, 2) . ".";
          mysqli_rollback($conn);
          $transactionStarted = false;
        } else {
          $insertStatement = mysqli_prepare($conn, "INSERT INTO payments (booking_id, amount_paid, method) VALUES (?, ?, ?)");
          mysqli_stmt_bind_param($insertStatement, "ids", $bookingId, $amount, $method);
          mysqli_stmt_execute($insertStatement);

          $newPaid = (float)$paidRow["paid"] + $amount;
          $status = $newPaid + 0.001 >= (float)$lockedBooking["total_cost"] ? "PAID" : "PARTIAL";
          $statusStatement = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE booking_id = ?");
          mysqli_stmt_bind_param($statusStatement, "si", $status, $bookingId);
          mysqli_stmt_execute($statusStatement);

          mysqli_commit($conn);
          $transactionStarted = false;
          header("Location: payments_list.php?recorded=1");
          exit;
        }
      }
    } catch (Throwable $exception) {
      if ($transactionStarted) {
        mysqli_rollback($conn);
      }
      error_log($exception->getMessage());
      $errors[] = "The payment could not be recorded. Please try again.";
    }
  }
}

$booking = null;
$paid = 0.0;
if ($bookingId > 0) {
  $bookingStatement = mysqli_prepare($conn, "SELECT b.booking_id, b.booking_date, b.total_cost, c.full_name, s.service_name FROM bookings b JOIN clients c ON c.client_id = b.client_id JOIN services s ON s.service_id = b.service_id WHERE b.booking_id = ?");
  mysqli_stmt_bind_param($bookingStatement, "i", $bookingId);
  mysqli_stmt_execute($bookingStatement);
  $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($bookingStatement));

  if ($booking) {
    $paidStatement = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount_paid), 0) AS paid FROM payments WHERE booking_id = ?");
    mysqli_stmt_bind_param($paidStatement, "i", $bookingId);
    mysqli_stmt_execute($paidStatement);
    $paidRow = mysqli_fetch_assoc(mysqli_stmt_get_result($paidStatement));
    $paid = (float)$paidRow["paid"];
  }
}
$balance = $booking ? max(0, (float)$booking["total_cost"] - $paid) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Process Payment</title>
  <link rel="stylesheet" href="/assessment_db/assets/app.css">
</head>
<body>
<?php include "../nav.php"; ?>
<main class="page-shell">
  <section class="page-intro">
    <div>
      <p class="eyebrow">PAYMENTS</p>
      <h1>Process payment</h1>
      <p class="intro-copy">Record a payment against a booking balance.</p>
    </div>
    <a class="button-link secondary" href="payments_list.php">Payment history</a>
  </section>

  <?php foreach ($errors as $error) { ?>
    <p class="alert alert-error"><?php echo $escape($error); ?></p>
  <?php } ?>

  <?php if ($booking) { ?>
    <section class="payment-layout">
      <div class="detail-panel">
        <h2>Booking #<?php echo $escape($booking["booking_id"]); ?></h2>
        <dl class="detail-list">
          <div><dt>Client</dt><dd><?php echo $escape($booking["full_name"]); ?></dd></div>
          <div><dt>Service</dt><dd><?php echo $escape($booking["service_name"]); ?></dd></div>
          <div><dt>Booking date</dt><dd><?php echo $escape($booking["booking_date"]); ?></dd></div>
          <div><dt>Booking total</dt><dd>₱<?php echo number_format((float)$booking["total_cost"], 2); ?></dd></div>
          <div><dt>Paid so far</dt><dd>₱<?php echo number_format($paid, 2); ?></dd></div>
          <div class="balance-row"><dt>Balance due</dt><dd>₱<?php echo number_format($balance, 2); ?></dd></div>
        </dl>
      </div>

      <?php if ($balance > 0) { ?>
        <form method="post" class="payment-form">
          <input type="hidden" name="booking_id" value="<?php echo $escape($booking["booking_id"]); ?>">
          <label for="amount_paid">Payment amount</label>
          <input id="amount_paid" type="number" name="amount_paid" min="0.01" max="<?php echo number_format($balance, 2, ".", ""); ?>" step="0.01" value="<?php echo $escape($_POST["amount_paid"] ?? number_format($balance, 2, ".", "")); ?>" required>
          <label for="method">Payment method</label>
          <select id="method" name="method" required>
            <?php foreach ($allowedMethods as $paymentMethod) { ?>
              <option value="<?php echo $escape($paymentMethod); ?>"<?php echo ($_POST["method"] ?? "CASH") === $paymentMethod ? " selected" : ""; ?>><?php echo $escape(ucwords(strtolower($paymentMethod))); ?></option>
            <?php } ?>
          </select>
          <button type="submit" name="record_payment">Record payment</button>
        </form>
      <?php } else { ?>
        <p class="alert alert-success">This booking is fully paid.</p>
      <?php } ?>
    </section>
  <?php } else { ?>
    <p class="alert alert-error">Booking not found. Return to <a href="../pages/bookings_list.php">bookings</a> and choose a valid booking.</p>
  <?php } ?>
</main>
</body>
</html>
