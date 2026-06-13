<?php
// Set default timezone to Israel
date_default_timezone_set('Asia/Jerusalem');

// ====================================================================
// 1. DATABASE CONNECTION
// ====================================================================
$servername = "sql100.byethost31.com";
$username = "b31_41617640";
$password = "gt#Q#P!9RAm76j9";
$dbname = "b31_41617640_my_polyfriends_db";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ====================================================================
// 2. FETCH DATA FROM DATABASE
// ====================================================================
// Fetch all messages from contact_messages table, ordered from newest to oldest
$sql = "SELECT id, username, message, timestamp FROM contact_messages ORDER BY timestamp DESC";
$result = $conn->query($sql);

// Store all fetched rows in an array
$allMessages = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $allMessages[] = $row;
    }
}

// ====================================================================
// 3. FILTERING LOGIC (PHP ARRAYS & STRINGS)
// ====================================================================
/**
 * Function to filter messages based on user input.
 * Implements functions, loops, arrays, and string operations as required.
 */
function filterMessages($msgsArray, $column, $text, $date) {
    $filtered = [];
    foreach ($msgsArray as $row) {
        $matchText = true;
        $matchDate = true;

        // Filter by text in a specific column using mb_stripos for case-insensitive Hebrew/English search
        if (!empty($text) && !empty($column)) {
            $cellValue = !empty($row[$column]) ? (string)$row[$column] : ($column === 'username' ? 'אורח' : '');
            if (mb_stripos($cellValue, $text) === false) {
                $matchText = false;
            }
        }

        // Filter by date matching
        if (!empty($date)) {
            $rowDate = date('Y-m-d', $row['timestamp'] / 1000);
            if ($rowDate !== $date) {
                $matchDate = false;
            }
        }

        // If both text and date conditions are met, add the row to the filtered array
        if ($matchText && $matchDate) {
            $filtered[] = $row;
        }
    }
    return $filtered;
}

// Initialize filter variables
$searchCol = 'message';
$searchText = '';
$searchDate = '';

// Check if the PHP form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searchCol = $_POST['search_col'] ?? 'message';
    $searchText = $_POST['search_text'] ?? '';
    $searchDate = $_POST['search_date'] ?? '';
}

// Apply the filter on the full array
$messagesToDisplay = filterMessages($allMessages, $searchCol, $searchText, $searchDate);

?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>טבלת הודעות - PolyFriends</title>
    <link rel="stylesheet" href="style.css">
    <script src="function.js"></script>
</head>
<body>
    <nav id="main-nav"></nav>
    <div class="info-section">
        <h2 class="info-heading">סינון תוצאות</h2>
        
        <!-- Filter Form: Inline layout -->
        <div class="market-card" style="margin-bottom: 20px;">
            <form method="POST" action="table.php" style="flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px;">
                <select name="search_col" style="flex: 1; min-width: 130px; margin: 0;">
                    <option value="message" <?php echo $searchCol === 'message' ? 'selected' : ''; ?>>תוכן ההודעה</option>
                    <option value="username" <?php echo $searchCol === 'username' ? 'selected' : ''; ?>>שם משתמש</option>
                    <option value="id" <?php echo $searchCol === 'id' ? 'selected' : ''; ?>>מזהה (ID)</option>
                </select>
                <input type="text" name="search_text" placeholder="חפש טקסט..." value="<?php echo htmlspecialchars($searchText); ?>" style="flex: 2; min-width: 150px; margin: 0;">
                <input type="date" name="search_date" value="<?php echo htmlspecialchars($searchDate); ?>" style="flex: 1; min-width: 130px; margin: 0;">
                <button type="submit" style="flex: 1; min-width: 100px; margin-top: 0;">סנן תוצאות</button>
                <button type="button" class="btn-unselected" onclick="window.location.href='table.php'" style="flex: 1; min-width: 100px; margin-top: 0; padding: 12px; border-radius: 8px;">נקה חיפוש</button>
            </form>
        </div>

        <!-- Filtered Results Count -->
        <div class="filter-container">
            <span class="balance-badge balance-positive">תוצאות סינון: <?php echo count($messagesToDisplay); ?> הודעות</span>
        </div>

        <!-- Filtered Results Table -->
        <div class="market-card" style="overflow-x: auto;">
            <table class="features-table">
            <tr>
                <th>מזהה (ID)</th>
                <th>שם משתמש</th>
                <th>תוכן ההודעה</th>
                <th>תאריך שליחה</th>
            </tr>
            <?php if (count($messagesToDisplay) > 0): ?>
                <?php foreach ($messagesToDisplay as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username'] ? $row['username'] : 'אורח'); ?></td>
                    <td><?php echo htmlspecialchars($row['message']); ?></td>
                    <td><?php echo date('d/m/Y H:i', $row['timestamp'] / 1000); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">לא נמצאו הודעות התואמות לחיפוש.</td>
                </tr>
            <?php endif; ?>
            </table>
        </div>

        <h2 class="info-heading">כל ההודעות במערכת</h2>
        
        <!-- Total Count -->
        <div class="filter-container">
            <span class="balance-badge balance-zero">סה"כ במערכת: <?php echo count($allMessages); ?> הודעות</span>
        </div>

        <!-- All Results Table -->
        <div class="market-card" style="overflow-x: auto;">
            <table class="features-table">
            <tr>
                <th>מזהה (ID)</th>
                <th>שם משתמש</th>
                <th>תוכן ההודעה</th>
                <th>תאריך שליחה</th>
            </tr>
            <?php if (count($allMessages) > 0): ?>
                <?php foreach ($allMessages as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username'] ? $row['username'] : 'אורח'); ?></td>
                    <td><?php echo htmlspecialchars($row['message']); ?></td>
                    <td><?php echo date('d/m/Y H:i', $row['timestamp'] / 1000); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">אין הודעות במערכת.</td>
                </tr>
            <?php endif; ?>
            </table>
        </div>
    </div>
    <footer id="main-footer"></footer>
</body>
</html>