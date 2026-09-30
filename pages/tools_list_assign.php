<?php
include "../db.php";

$escape = static function ($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
};
$errors = [];
$operation = $_POST["operation"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $transactionStarted = false;
  try {
    if ($operation === "add") {
      $toolName = trim($_POST["tool_name"] ?? "");
      $quantity = filter_var($_POST["quantity_total"] ?? "", FILTER_VALIDATE_INT);
      if ($toolName === "" || $quantity === false || $quantity < 1) {
        $errors[] = "Enter a tool name and an initial quantity of at least one.";
      } else {
        $statement = mysqli_prepare($conn, "INSERT INTO tools (tool_name, quantity_total, quantity_available) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($statement, "sii", $toolName, $quantity, $quantity);
        mysqli_stmt_execute($statement);
        header("Location: tools_list_assign.php?updated=1");
        exit;
      }
    } elseif ($operation === "assign") {
      $bookingId = filter_var($_POST["booking_id"] ?? "", FILTER_VALIDATE_INT);
      $toolId = filter_var($_POST["tool_id"] ?? "", FILTER_VALIDATE_INT);
      $quantity = filter_var($_POST["quantity"] ?? "", FILTER_VALIDATE_INT);
      if (!$bookingId || !$toolId || $quantity === false || $quantity < 1) {
        $errors[] = "Choose a booking and tool, and enter a quantity of at least one.";
      } else {
        mysqli_begin_transaction($conn);
        $transactionStarted = true;

        $bookingStatement = mysqli_prepare($conn, "SELECT booking_id FROM bookings WHERE booking_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($bookingStatement, "i", $bookingId);
        mysqli_stmt_execute($bookingStatement);
        $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($bookingStatement));

        $toolStatement = mysqli_prepare($conn, "SELECT quantity_available FROM tools WHERE tool_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($toolStatement, "i", $toolId);
        mysqli_stmt_execute($toolStatement);
        $tool = mysqli_fetch_assoc(mysqli_stmt_get_result($toolStatement));

        if (!$booking) {
          $errors[] = "That booking could not be found.";
        } elseif (!$tool) {
          $errors[] = "That tool could not be found.";
        } elseif ((int)$tool["quantity_available"] < $quantity) {
          $errors[] = "Only " . (int)$tool["quantity_available"] . " of that tool are currently available.";
        } else {
          $updateStatement = mysqli_prepare($conn, "UPDATE tools SET quantity_available = quantity_available - ? WHERE tool_id = ? AND quantity_available >= ?");
          mysqli_stmt_bind_param($updateStatement, "iii", $quantity, $toolId, $quantity);
          mysqli_stmt_execute($updateStatement);

          $insertStatement = mysqli_prepare($conn, "INSERT INTO booking_tools (booking_id, tool_id, qty_used) VALUES (?, ?, ?)");
          mysqli_stmt_bind_param($insertStatement, "iii", $bookingId, $toolId, $quantity);
          mysqli_stmt_execute($insertStatement);

          mysqli_commit($conn);
          $transactionStarted = false;
          header("Location: tools_list_assign.php?updated=1");
          exit;
        }

        mysqli_rollback($conn);
        $transactionStarted = false;
      }
    } elseif ($operation === "return") {
      $bookingToolId = filter_var($_POST["booking_tool_id"] ?? "", FILTER_VALIDATE_INT);
      if (!$bookingToolId || $bookingToolId < 1) {
        $errors[] = "Choose a valid tool assignment to return.";
      } else {
        mysqli_begin_transaction($conn);
        $transactionStarted = true;

        $assignmentStatement = mysqli_prepare($conn, "SELECT tool_id, qty_used FROM booking_tools WHERE booking_tool_id = ? FOR UPDATE");
        mysqli_stmt_bind_param($assignmentStatement, "i", $bookingToolId);
        mysqli_stmt_execute($assignmentStatement);
        $assignment = mysqli_fetch_assoc(mysqli_stmt_get_result($assignmentStatement));

        if (!$assignment) {
          $errors[] = "That assignment has already been returned or could not be found.";
          mysqli_rollback($conn);
          $transactionStarted = false;
        } else {
          $updateStatement = mysqli_prepare($conn, "UPDATE tools SET quantity_available = LEAST(quantity_total, quantity_available + ?) WHERE tool_id = ?");
          mysqli_stmt_bind_param($updateStatement, "ii", $assignment["qty_used"], $assignment["tool_id"]);
          mysqli_stmt_execute($updateStatement);

          if (mysqli_stmt_affected_rows($updateStatement) < 1) {
            throw new RuntimeException("Assigned tool inventory could not be updated.");
          }

          $deleteStatement = mysqli_prepare($conn, "DELETE FROM booking_tools WHERE booking_tool_id = ?");
          mysqli_stmt_bind_param($deleteStatement, "i", $bookingToolId);
          mysqli_stmt_execute($deleteStatement);

          mysqli_commit($conn);
          $transactionStarted = false;
          header("Location: tools_list_assign.php?updated=1");
          exit;
        }
      }
    } else {
      $errors[] = "Choose a valid tool action.";
    }
  } catch (Throwable $exception) {
    if ($transactionStarted) {
      mysqli_rollback($conn);
    }
    error_log($exception->getMessage());
    $errors[] = "The tool update could not be completed. Please try again.";
  }
}

$tools = mysqli_query($conn, "SELECT tool_id, tool_name, quantity_total, quantity_available FROM tools ORDER BY tool_name");
$bookings = mysqli_query($conn, "SELECT b.booking_id, b.booking_date, c.full_name FROM bookings b JOIN clients c ON c.client_id = b.client_id ORDER BY b.booking_id DESC");
$assignments = mysqli_query($conn, "SELECT bt.booking_tool_id, bt.qty_used, b.booking_id, b.booking_date, c.full_name, t.tool_name FROM booking_tools bt JOIN bookings b ON b.booking_id = bt.booking_id JOIN clients c ON c.client_id = b.client_id JOIN tools t ON t.tool_id = bt.tool_id ORDER BY bt.created_at DESC, bt.booking_tool_id DESC");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tools</title>
  <link rel="stylesheet" href="/assessment_db/assets/app.css">
</head>
<body>
<?php include "../nav.php"; ?>
<main class="page-shell">
  <section class="page-intro">
    <div>
      <p class="eyebrow">INVENTORY</p>
      <h1>Tools</h1>
      <p class="intro-copy">Track stock and manage tools assigned to bookings.</p>
    </div>
    <span class="date-chip">Inventory &amp; assignments</span>
  </section>

  <?php if (isset($_GET["updated"])) { ?>
    <p class="alert alert-success">Tool inventory updated.</p>
  <?php } ?>
  <?php foreach ($errors as $error) { ?>
    <p class="alert alert-error"><?php echo $escape($error); ?></p>
  <?php } ?>

  <section class="tool-workspace">
    <div>
      <h2 class="section-heading">Tool inventory</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Tool</th><th>Total stock</th><th>Available</th><th>Assigned</th></tr></thead>
          <tbody>
            <?php if (mysqli_num_rows($tools) === 0) { ?>
              <tr><td colspan="4" class="empty-cell">No tools in inventory yet.</td></tr>
            <?php } else { ?>
              <?php while ($tool = mysqli_fetch_assoc($tools)) { ?>
                <tr>
                  <td><?php echo $escape($tool["tool_name"]); ?></td>
                  <td><?php echo (int)$tool["quantity_total"]; ?></td>
                  <td><span class="stock-count"><?php echo (int)$tool["quantity_available"]; ?></span></td>
                  <td><?php echo max(0, (int)$tool["quantity_total"] - (int)$tool["quantity_available"]); ?></td>
                </tr>
              <?php } ?>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tool-forms">
      <form method="post">
        <h2 class="form-heading">Add inventory item</h2>
        <input type="hidden" name="operation" value="add">
        <label for="tool_name">Tool name</label>
        <input id="tool_name" type="text" name="tool_name" maxlength="150" required>
        <label for="quantity_total">Starting quantity</label>
        <input id="quantity_total" type="number" name="quantity_total" min="1" step="1" value="1" required>
        <button type="submit">Add tool</button>
      </form>

      <form method="post">
        <h2 class="form-heading">Assign a tool</h2>
        <input type="hidden" name="operation" value="assign">
        <label for="booking_id">Booking</label>
        <select id="booking_id" name="booking_id" required>
          <option value="">Select a booking</option>
          <?php while ($booking = mysqli_fetch_assoc($bookings)) { ?>
            <option value="<?php echo (int)$booking["booking_id"]; ?>"><?php echo "#" . (int)$booking["booking_id"] . " · " . $escape($booking["full_name"]) . " · " . $escape($booking["booking_date"]); ?></option>
          <?php } ?>
        </select>
        <label for="tool_id">Tool</label>
        <select id="tool_id" name="tool_id" required>
          <option value="">Select an available tool</option>
          <?php mysqli_data_seek($tools, 0); while ($tool = mysqli_fetch_assoc($tools)) { ?>
            <?php if ((int)$tool["quantity_available"] > 0) { ?>
              <option value="<?php echo (int)$tool["tool_id"]; ?>"><?php echo $escape($tool["tool_name"]); ?> (<?php echo (int)$tool["quantity_available"]; ?> available)</option>
            <?php } ?>
          <?php } ?>
        </select>
        <label for="quantity">Quantity to assign</label>
        <input id="quantity" type="number" name="quantity" min="1" step="1" value="1" required>
        <button type="submit">Assign tool</button>
      </form>
    </div>
  </section>

  <section class="assignment-section">
    <h2 class="section-heading">Current assignments</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Booking</th><th>Client</th><th>Date</th><th>Tool</th><th>Quantity</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (mysqli_num_rows($assignments) === 0) { ?>
            <tr><td colspan="6" class="empty-cell">There are no tools assigned to bookings.</td></tr>
          <?php } else { ?>
            <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
              <tr>
                <td>#<?php echo (int)$assignment["booking_id"]; ?></td>
                <td><?php echo $escape($assignment["full_name"]); ?></td>
                <td><?php echo $escape($assignment["booking_date"]); ?></td>
                <td><?php echo $escape($assignment["tool_name"]); ?></td>
                <td><?php echo (int)$assignment["qty_used"]; ?></td>
                <td>
                  <form method="post" class="inline-form">
                    <input type="hidden" name="operation" value="return">
                    <input type="hidden" name="booking_tool_id" value="<?php echo (int)$assignment["booking_tool_id"]; ?>">
                    <button type="submit" class="button-small">Return</button>
                  </form>
                </td>
              </tr>
            <?php } ?>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</body>
</html>
