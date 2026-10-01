<?php
include "../db.php";
include "../auth.php";
require_role('admin');
$result = mysqli_query($conn, "SELECT * FROM clients ORDER BY client_id DESC");
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Clients</title><link rel="stylesheet" href="/assessment_db/assets/app.css"></head>
<body>
<?php include "../nav.php"; ?>
 
<h2>Clients</h2>
<p><a class="button-link" href="clients_add.php">Add client</a></p>
 
<table border="1" cellpadding="8">
  <tr>
    <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Action</th>
  </tr>
  <?php while($row = mysqli_fetch_assoc($result)) { ?>
    <tr>
      <td><?php echo $row['client_id']; ?></td>
      <td><?php echo $row['full_name']; ?></td>
      <td><?php echo $row['email']; ?></td>
      <td><?php echo $row['phone']; ?></td>
      <td>
        <a href="clients_edit.php?id=<?php echo $row['client_id']; ?>">Edit</a>
      </td>
    </tr>
  <?php } ?>
</table>
</body>
</html>
