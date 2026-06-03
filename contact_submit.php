<?php
header('Content-Type: text/html; charset=utf-8');

$host    = 'sql100.byethost31.com';
$db      = 'b31_41617640_my_polyfriends_db';
$user    = 'b31_41617640';
$pass    = 'gt#Q#P!9RAm76j9';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (\PDOException $e) {
    die("שגיאת חיבור למסד הנתונים: " . $e->getMessage());
}

// Create table if it doesn't exist yet
$pdo->exec("CREATE TABLE IF NOT EXISTS contact_form (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    fullname  VARCHAR(255) NOT NULL,
    subject   VARCHAR(255),
    message   TEXT,
    submitted DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = isset($_POST['fullname']) ? htmlspecialchars(strip_tags(trim($_POST['fullname']))) : '';
    $subject  = isset($_POST['subject'])  ? htmlspecialchars(strip_tags(trim($_POST['subject'])))  : '';
    $message  = isset($_POST['message'])  ? htmlspecialchars(strip_tags(trim($_POST['message'])))  : '';

    $stmt = $pdo->prepare("INSERT INTO contact_form (fullname, subject, message) VALUES (?, ?, ?)");
    $stmt->execute([$fullname, $subject, $message]);
    $success = true;
}

// Fetch all rows to display
$rows = $pdo->query("SELECT * FROM contact_form ORDER BY submitted DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyFriends - פניות</title>
    <link rel="stylesheet" href="style.css">
    <script src="function.js"></script>
</head>
<body>
    <nav id="main-nav"></nav>

    <div class="info-section">
        <?php if ($success): ?>
            <div style="background:#e8f8f5; border:1px solid #2ecc71; border-radius:8px; padding:15px; margin-bottom:30px; color:#27ae60; font-weight:bold;">
                הפנייה נשמרה בהצלחה!
            </div>
        <?php endif; ?>

        <h1>כל הפניות שהתקבלו</h1>

        <?php if (empty($rows)): ?>
            <p>אין פניות עדיין.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>שם מלא</th>
                        <th>סוג פנייה</th>
                        <th>הודעה</th>
                        <th>תאריך</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['fullname'] ?></td>
                        <td><?= $row['subject'] ?></td>
                        <td><?= $row['message'] ?></td>
                        <td><?= $row['submitted'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="contact.html" class="auth-btn" style="display:inline-block; margin-top:20px; padding:10px 20px;">חזרה לדף יצירת קשר</a>
    </div>

    <footer id="main-footer"></footer>
</body>
</html>
