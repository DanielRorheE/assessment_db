<?php
include "../db.php";
include "../auth.php";
require_role('admin');

$escape = static function ($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
};
$summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS payment_count, COALESCE(SUM(amount_paid), 0) AS total_received FROM payments"));
$sql = "SELECT p.payment_id, p.booking_id, p.amount_paid, p.method, p.payment_date, c.full_name, s.service_name FROM payments p JOIN bookings b ON b.booking_id = p.booking_id JOIN clients c ON c.client_id = b.client_id JOIN services s ON s.service_id = b.service_id ORDER BY p.payment_date DESC, p.payment_id DESC";
$payments = mysqli_query($conn, $sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payments</title>
  <link rel="stylesheet" href="/assessment_db/assets/app.css">
</head>
<body>
<?php include "../nav.php"; ?>
<main class="page-shell">
  <section class="page-intro">
    <div>
      <p class="eyebrow">FINANCE</p>
      <h1>Payments</h1>
      <p class="intro-copy">Review payments received across your bookings.</p>
    </div>
    <a class="button-link" href="bookings_list.php">Go to bookings</a>
  </section>

  <?php if (isset($_GET["recorded"])) { ?>
    <p class="alert alert-success">Payment recorded successfully.</p>
  <?php } ?>

  <section class="metric-grid payment-metrics" aria-label="Payment summary">
    <article class="metric-card">
      <span class="metric-label">Payments recorded</span>
      <strong class="metric-value"><?php echo (int)$summary["payment_count"]; ?></strong>
      <span class="metric-note">Individual transactions</span>
    </article>
    <article class="metric-card">
      <span class="metric-label">Total received</span>
      <strong class="metric-value">₱<?php echo number_format((float)$summary["total_received"], 2); ?></strong>
      <span class="metric-note">Across all bookings</span>
    </article>
  </section>

  <h2 class="section-heading">Payment history</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Payment</th><th>Booking</th><th>Client</th><th>Service</th><th>Method</th><th>Date</th><th>Amount</th></tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($payments) === 0) { ?>
          <tr><td colspan="7" class="empty-cell">No payments have been recorded yet.</td></tr>
        <?php } else { ?>
          <?php while ($payment = mysqli_fetch_assoc($payments)) { ?>
            <tr>
              <td>#<?php echo (int)$payment["payment_id"]; ?></td>
              <td><a href="payment_process.php?booking_id=<?php echo (int)$payment["booking_id"]; ?>">#<?php echo (int)$payment["booking_id"]; ?></a></td>
              <td><?php echo $escape($payment["full_name"]); ?></td>
              <td><?php echo $escape($payment["service_name"]); ?></td>
              <td><?php echo $escape(ucwords(strtolower($payment["method"]))); ?></td>
              <td><?php echo $escape(date("M j, Y g:i A", strtotime($payment["payment_date"]))); ?></td>
              <td>₱<?php echo number_format((float)$payment["amount_paid"], 2); ?></td>
            </tr>
          <?php } ?>
        <?php } ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
