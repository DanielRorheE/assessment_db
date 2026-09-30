<?php
include "../db.php";
$result = mysqli_query($conn, "SELECT * FROM services ORDER BY service_id DESC");
if (!$result) {
  die("Unable to load services: " . mysqli_error($conn));
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Services</title><link rel="stylesheet" href="/assessment_db/assets/app.css"></head>
<body>
<?php include "../nav.php"; ?>
 
<h2>Services</h2>
 
<table border="1" cellpadding="8">
  <tr>
    <th>ID</th><th>Name</th><th>Rate</th><th>Active</th><th>Action</th>
  </tr>
  <?php while($row = mysqli_fetch_assoc($result)) { ?>
    <tr>
      <td><?php echo $row['service_id']; ?></td>
      <td><?php echo $row['service_name']; ?></td>
      <td>₱<?php echo number_format($row['hourly_rate'],2); ?></td>
      <td><?php echo $row['is_active'] ? "Yes" : "No"; ?></td>
      <td><a href="services_edit.php?id=<?php echo $row['service_id']; ?>">Edit</a></td>
    </tr>
  <?php } ?>
</table>
</body>
</html>