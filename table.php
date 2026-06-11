<?php
$host = 'sql100.byethost31.com';
$db   = 'b31_41617640_my_polyfriends_db';
$user = 'b31_41617640'; 
$pass = 'gt#Q#P!9RAm76j9'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('שגיאה בחיבור למסד הנתונים: ' . $e->getMessage());
}

$stmt = $pdo->query("SELECT id, username, message, timestamp FROM contact_messages ORDER BY timestamp DESC");
$messages = $stmt->fetchAll();

function countTotalMessages($msgsArray) {
    $count = 0;
    foreach ($msgsArray as $item) {
        $count = $count + 1; 
    }
    return $count;
}

function generateCustomTitle($msgsArray) {
    $count = countTotalMessages($msgsArray);
    $baseString = "סה\"כ הודעות שנקלטו במערכת: {NUMBER}";
    return str_replace("{NUMBER}", $count, $baseString);
}
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
        <h2 class="info-heading" style="text-align: center;">הודעות שהתקבלו מטופס צור קשר</h2>
        
        <div style="text-align: center; margin-bottom: 25px;">
            <span class="balance-badge balance-positive" style="font-size: 1.1em;"><?php echo $formattedTitle; ?></span>
        </div>

        <div class="market-card" style="margin: 0 auto; overflow-x: auto;">
            <table class="features-table" style="margin: 0; width: 100%;">
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