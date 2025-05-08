<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: signin.php');
    exit;
}

require_once __DIR__ . '/../src/db_connect.php';

// Fetch the two JSON columns
$stmt = $pdo->prepare("
  SELECT `Owned_Ingredients`, `User_Quantity`
    FROM `User`
   WHERE `Username` = ?
");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Decode or default to empty arrays
$names  = json_decode($user['Owned_Ingredients'] ?? '[]', true);
$quants = json_decode($user['User_Quantity']    ?? '[]', true);

// List of units for the dropdown
$units = ['cup','tbsp','tsp','g','kg','ml','l','piece','whole'];

// Build rows array with parsed qty + unit
$rows = [];
foreach ($names as $i => $n) {
    $qty = '';
    $unit = '';
    if (isset($quants[$i]) && preg_match('/^(\S+)\s+(\S+)/', $quants[$i], $m)) {
        $qty  = $m[1];
        $unit = $m[2];
    }
    $rows[] = ['name'=>$n, 'qty'=>$qty, 'unit'=>$unit];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
    <header class="site-header">
        <h1>Ingredients</h1>
        <p><a href="user.php">&laquo; Back to Account</a></p>
    </header>
  <title>Your Ingredients</title>
  <style>
    .edit-row { display: none; }
    .view-row  { }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    button { margin: 0 4px; }
  </style>
</head>
<body>
  <h1>Ingredients You Own</h1>

  <?php if (count($rows)): ?>
    <table>
      <thead>
        <tr>
          <th>Ingredient</th>
          <th>Quantity</th>
          <th>Unit</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <!-- VIEW MODE -->
          <tr class="view-row" data-index="<?= $i ?>">
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['qty']) ?></td>
            <td><?= htmlspecialchars($r['unit']) ?></td>
            <td>
              <button class="edit-btn" data-index="<?= $i ?>">Edit</button>
            </td>
          </tr>
          <!-- EDIT MODE -->
          <tr class="edit-row" data-index="<?= $i ?>">
            <td>
              <form method="post" action="ingredients_handler.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="index"  value="<?= $i ?>">
                <input type="text" name="name"
                       value="<?= htmlspecialchars($r['name']) ?>"
                       required>
            </td>
            <td>
                <input type="text" name="quantity"
                       value="<?= htmlspecialchars($r['qty']) ?>"
                       required>
            </td>
            <td>
                <select name="unit" required>
                  <option value="">—</option>
                  <?php foreach ($units as $u): ?>
                    <option value="<?= $u ?>"
                      <?= $r['unit']==$u ? 'selected' : '' ?>>
                      <?= ucfirst($u) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
            </td>
            <td>
                <button type="submit">Save</button>
                <button type="button" class="cancel-btn" data-index="<?= $i ?>">
                  Cancel
                </button>
                <button type="submit" name="action" value="delete"
                        onclick="return confirm('Delete this ingredient?')">
                  Delete
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>No ingredients yet.</p>
  <?php endif; ?>

  <hr>
  <h2>Add New Ingredient</h2>
  <form method="post" action="ingredients_handler.php">
    <input type="hidden" name="action" value="add">
    <label>
      Ingredient:
      <input type="text" name="name" required>
    </label>
    <label>
      Quantity:
      <input type="text" name="quantity" placeholder="e.g. 4" required>
    </label>
    <label>
      Unit:
      <select name="unit" required>
        <option value="">—</option>
        <?php foreach ($units as $u): ?>
          <option value="<?= $u ?>"><?= ucfirst($u) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Add</button>
  </form>

  <script>
    document.querySelectorAll('.edit-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const i = btn.dataset.index;
        // hide view, show edit as table-row
        document.querySelector(`.view-row[data-index="${i}"]`).style.display = 'none';
        document.querySelector(`.edit-row[data-index="${i}"]`).style.display = 'table-row';
      });
    });
    document.querySelectorAll('.cancel-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const i = btn.dataset.index;
        // hide edit, show view
        document.querySelector(`.edit-row[data-index="${i}"]`).style.display = 'none';
        document.querySelector(`.view-row[data-index="${i}"]`).style.display = '';
      });
    });
  </script>
</body>
</html>