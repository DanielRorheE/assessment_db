<?php
include "db.php";
 
$clients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM clients"))['c'];
$services = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM services"))['c'];
$bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings"))['c'];
 
$revRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(amount_paid),0) AS s FROM payments"));
$revenue = $revRow['s'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
</head>
<body>
<?php include "nav.php"; ?>
 
<h2>Dashboard</h2>
 
<ul>
  <li>Total Clients: <b><?php echo $clients; ?></b></li>
  <li>Total Services: <b><?php echo $services; ?></b></li>
  <li>Total Bookings: <b><?php echo $bookings; ?></b></li>
  <li>Total Revenue: <b>₱<?php echo number_format($revenue,2); ?></b></li>
</ul>
 
<p>
  Quick links:
  <a href="/assessment_db/pages/clients_add.php">Add Client</a> |
  <a href="/assessment_db/pages/bookings_create.php">Create Booking</a>
</p>
 
</body>
</html>