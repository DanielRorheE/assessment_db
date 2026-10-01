<?php
include __DIR__ . '/../db.php';
include __DIR__ . '/../auth.php';
require_role('user');

$statement = mysqli_prepare($conn, 'SELECT b.booking_id, b.booking_date, b.hours, b.total_cost, b.status, s.service_name FROM bookings b JOIN services s ON s.service_id = b.service_id WHERE b.client_id = ? ORDER BY b.booking_date DESC, b.booking_id DESC');
$clientId = (int) $_SESSION['client_id'];
mysqli_stmt_bind_param($statement, 'i', $clientId);
mysqli_stmt_execute($statement);
$bookings = mysqli_stmt_get_result($statement);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Bookings</title>
  <link rel="stylesheet" href="/assessment_db/assets/app.css">
</head>
<body>
<?php include __DIR__ . '/../nav.php'; ?>
<main class="page-shell">
  <section class="page-intro">
    <div>
      <p class="eyebrow">CUSTOMER ACCOUNT</p>
      <h1>My bookings</h1>
      <p class="intro-copy">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?>.</p>
    </div>
  </section>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Booking</th><th>Service</th><th>Date</th><th>Hours</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (mysqli_num_rows($bookings) === 0) { ?>
          <tr><td class="empty-cell" colspan="6">No bookings yet.</td></tr>
        <?php } else { ?>
          <?php while ($booking = mysqli_fetch_assoc($bookings)) { ?>
            <tr>
              <td>#<?php echo (int) $booking['booking_id']; ?></td>
              <td><?php echo htmlspecialchars($booking['service_name'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo (int) $booking['hours']; ?></td>
              <td>₱<?php echo number_format((float) $booking['total_cost'], 2); ?></td>
              <td><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
          <?php } ?>
        <?php } ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
