<?php
header('Content-Type: application/json; charset=utf-8');

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
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS contact_form (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    fullname  VARCHAR(255) NOT NULL,
    subject   VARCHAR(255),
    message   TEXT,
    submitted DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = isset($_POST['fullname']) ? htmlspecialchars(strip_tags(trim($_POST['fullname']))) : '';
    $subject  = isset($_POST['subject'])  ? htmlspecialchars(strip_tags(trim($_POST['subject'])))  : '';
    $message  = isset($_POST['message'])  ? htmlspecialchars(strip_tags(trim($_POST['message'])))  : '';

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_form (fullname, subject, message) VALUES (?, ?, ?)");
        $stmt->execute([$fullname, $subject, $message]);
        echo json_encode(['status' => 'success']);
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
