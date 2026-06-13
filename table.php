<?php
// Set default timezone to Israel
date_default_timezone_set('Asia/Jerusalem');

// Database connection settings (MySQLi)
$servername = "sql100.byethost31.com";
$username = "b31_41617640";
$password = "gt#Q#P!9RAm76j9";
$dbname = "b31_41617640_my_polyfriends_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset to support Hebrew properly
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all messages from contact_messages table, ordered from newest to oldest
$sql = "SELECT id, username, message, timestamp FROM contact_messages ORDER BY timestamp DESC";
$result = $conn->query($sql);

$messages = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Function to count total messages (implements "use a loop" project requirement)
function countTotalMessages($msgsArray) {
    $count = 0;
    foreach ($msgsArray as $item) {
        $count = $count + 1; 
    }
    return $count;
}

// Function to generate a dynamic title (implements "use string function" requirement via str_replace)
function generateCustomTitle($msgsArray) {
    $count = countTotalMessages($msgsArray);
    $baseString = "סה\"כ הודעות שנקלטו במערכת: {NUMBER}";
    return str_replace("{NUMBER}", $count, $baseString);
}

// Call the function and save the formatted title to display later on the page
$formattedTitle = generateCustomTitle($messages);

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
        <h2 class="info-heading">הודעות שהתקבלו מטופס צור קשר</h2>
        
        <div class="filter-container">
            <span class="balance-badge balance-positive"><?php echo $formattedTitle; ?></span>
        </div>

        <div class="market-card" style="overflow-x: auto;">
            <table class="features-table">
            <tr>
                <th>מזהה (ID)</th>
                <th>שם משתמש</th>
                <th>תוכן ההודעה</th>
                <th>תאריך שליחה</th>
            </tr>
            <?php foreach ($messages as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['id']); ?></td>
                <td><?php echo htmlspecialchars($row['username'] ? $row['username'] : 'אורח'); ?></td>
                <td><?php echo htmlspecialchars($row['message']); ?></td>
                <td><?php echo date('d/m/Y H:i', $row['timestamp'] / 1000); ?></td>
            </tr>
            <?php endforeach; ?>
            </table>
        </div>
    </div>
    <footer id="main-footer"></footer>
</body>
</html>