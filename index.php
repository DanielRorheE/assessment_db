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
  <link rel="stylesheet" href="/assessment_db/assets/app.css">
</head>
<body>
<?php include "nav.php"; ?>

<main class="page-shell">
  <section class="page-intro">
    <div>
      <p class="eyebrow">WORKSPACE OVERVIEW</p>
      <h1>Dashboard</h1>
      <p class="intro-copy">A clear view of your clients, services, and bookings.</p>
    </div>
    <time class="date-chip" datetime="<?php echo date('Y-m-d'); ?>"><?php echo date('F j, Y'); ?></time>
  </section>

  <section class="metric-grid" aria-label="Business summary">
    <article class="metric-card">
      <span class="metric-label">Total clients</span>
      <strong class="metric-value"><?php echo $clients; ?></strong>
      <span class="metric-note">People in your client list</span>
    </article>
    <article class="metric-card">
      <span class="metric-label">Total services</span>
      <strong class="metric-value"><?php echo $services; ?></strong>
      <span class="metric-note">Services available to book</span>
    </article>
    <article class="metric-card">
      <span class="metric-label">Total bookings</span>
      <strong class="metric-value"><?php echo $bookings; ?></strong>
      <span class="metric-note">Bookings recorded to date</span>
    </article>
    <article class="metric-card">
      <span class="metric-label">Total revenue</span>
      <strong class="metric-value">₱<?php echo number_format($revenue, 2); ?></strong>
      <span class="metric-note">Payments received</span>
    </article>
  </section>

  <section aria-labelledby="actions-title">
    <h2 class="section-heading" id="actions-title">Quick actions</h2>
    <div class="action-row">
      <a class="button-link" href="/assessment_db/pages/bookings_create.php">Create booking</a>
      <a class="button-link secondary" href="/assessment_db/pages/clients_add.php">Add client</a>
    </div>
  </section>
</main>
 
</body>
</html>