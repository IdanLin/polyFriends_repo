<?php
header('Content-Type: application/json');

// הגדרות חיבור למסד הנתונים MySQL (מותאם ל-XAMPP)
$host = 'sql100.byethost31.com'; // אם השגיאה נמשכת, יש להחליף לכתובת ה-MySQL Host Name שמופיעה ב-cPanel (לרוב מתחיל ב-sql)
$db   = 'b31_41617640_my_polyfriends_db'; // שם מסד הנתונים
$user = 'b31_41617640'; 
$pass = 'gt#Q#P!9RAm76j9'; // <--- חובה להזין כאן את סיסמת החשבון/מסד הנתונים שלך ב-ByetHost!
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// טיפול בבקשות GET (קריאת נתונים מהדאטא-בייס)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'leaderboard') {
    $sql = "
        SELECT
            u.username,
            u.full_name AS fullName,
            COALESCE(SUM(
                CASE
                    WHEN b.status != 'closed' OR b.winning_option IS NULL THEN 0
                    WHEN (SELECT COUNT(*) FROM bet_participants lx WHERE lx.bet_id = b.id AND lx.option_name != b.winning_option) = 0 THEN 0
                    WHEN bp.option_name = b.winning_option THEN
                        (SELECT COUNT(*) FROM bet_participants lw WHERE lw.bet_id = b.id AND lw.option_name != b.winning_option) * b.amount /
                        (SELECT COUNT(*) FROM bet_participants lw2 WHERE lw2.bet_id = b.id AND lw2.option_name = b.winning_option)
                    ELSE -b.amount
                END
            ), 0) AS net
        FROM users u
        LEFT JOIN bet_participants bp ON u.username = bp.username
        LEFT JOIN bets b ON bp.bet_id = b.id
        GROUP BY u.username, u.full_name
        ORDER BY net DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) { $r['net'] = round((float)$r['net'], 2); }
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // נבדוק אם טבלת המשתמשים ריקה (כלומר מסד נתונים חדש לחלוטין)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // נתונים Hardcoded התחלתיים (אם המסד ריק לחלוטין)
        $defaultData = [
            'users' => [
                ["username" => "admin", "password" => "123", "fullName" => "מנהל מערכת", "balance" => 0],
                ["username" => "idan", "password" => "123", "fullName" => "עידן", "balance" => 0]
            ],
            'categories' => ["פוליטיקה", "ספורט", "קריפטו", "תרבות פופ", "כלכלה", "מדע וטכנולוגיה"],
            'bets' => [
                [
                    "id" => "bet_1",
                    "title" => "מי ינצח בבחירות הקרובות בארה\"ב?",
                    "amount" => 100,
                    "category" => "פוליטיקה",
                    "creator" => "admin",
                    "status" => "open",
                    "options" => ["טראמפ", "ביידן"],
                    "participants" => ["idan" => "טראמפ"],
                    "winningOption" => null
                ]
            ],
            'admins' => [
                ["name" => "עידן", "role" => "מייסד ומפתח", "bio" => "מפתח פול-סטאק", "image" => "https://ui-avatars.com/api/?name=Idan&background=0D8ABC&color=fff"]
            ],
            'settlements' => [],
            'messages' => []
        ];
        echo json_encode($defaultData);
    } else {
        // קריאת כל הנתונים מהטבלאות השונות ואריזתם למבנה שהדפדפן מכיר
        $users = $pdo->query("SELECT username, password, full_name AS fullName, balance FROM users")->fetchAll();
        foreach ($users as &$u) { $u['balance'] = (float)$u['balance']; }

        $categories = $pdo->query("SELECT name FROM categories")->fetchAll(PDO::FETCH_COLUMN);

        $admins = $pdo->query("SELECT name, role, bio, image FROM admins")->fetchAll();

        $bets = $pdo->query("SELECT id, title, amount, category, creator, status, winning_option AS winningOption FROM bets")->fetchAll();
        foreach ($bets as &$bet) {
            $bet['amount'] = (float)$bet['amount'];
            
            $stmtOpt = $pdo->prepare("SELECT option_name FROM bet_options WHERE bet_id = ?");
            $stmtOpt->execute([$bet['id']]);
            $bet['options'] = $stmtOpt->fetchAll(PDO::FETCH_COLUMN);

            $stmtPart = $pdo->prepare("SELECT username, option_name FROM bet_participants WHERE bet_id = ?");
            $stmtPart->execute([$bet['id']]);
            $parts = [];
            foreach ($stmtPart->fetchAll() as $p) {
                $parts[$p['username']] = $p['option_name'];
            }
            $bet['participants'] = $parts;
        }

        $settlements = $pdo->query("SELECT from_user AS `from`, to_user AS `to`, amount, timestamp FROM settlements ORDER BY timestamp ASC")->fetchAll();
        foreach ($settlements as &$s) {
            $s['amount'] = (float)$s['amount'];
            $s['timestamp'] = (int)$s['timestamp'];
        }

        $messages = $pdo->query("SELECT id, username, message, timestamp FROM contact_messages ORDER BY timestamp ASC")->fetchAll();
        foreach ($messages as &$m) {
            $m['timestamp'] = (int)$m['timestamp'];
        }

        echo json_encode([
            'users' => $users,
            'categories' => $categories,
            'bets' => $bets,
            'admins' => $admins,
            'settlements' => $settlements,
            'messages' => $messages
        ]);
    }
} 
// טיפול בבקשות POST (שמירת נתונים לדאטא-בייס)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? null;
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if ($data === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }

    if ($action === 'add_funds') {
        try {
            $amount = (float)$data['amount'];
            if ($amount <= 0) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'invalid_amount']); exit; }

            $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE username = ?");
            $stmtBal->execute([$data['username']]);
            $currentBalance = (float)$stmtBal->fetchColumn();

            if ($currentBalance + $amount > 10000) {
                $allowed = round(10000 - $currentBalance, 2);
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'max_exceeded', 'allowed' => $allowed]);
                exit;
            }

            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE username = ?")->execute([$amount, $data['username']]);
            $stmtBal->execute([$data['username']]);
            echo json_encode(['status' => 'success', 'newBalance' => (float)$stmtBal->fetchColumn()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'lock_bet') {
        try {
            $stmtBet = $pdo->prepare("SELECT amount FROM bets WHERE id = ?");
            $stmtBet->execute([$data['betId']]);
            $betAmount = (float)$stmtBet->fetchColumn();

            $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE username = ?");
            $stmtBal->execute([$data['username']]);
            $balance = (float)$stmtBal->fetchColumn();

            if ($balance < $betAmount) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'insufficient_funds']);
                exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO bet_participants (bet_id, username, option_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_name=VALUES(option_name)")->execute([$data['betId'], $data['username'], $data['option']]);
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE username = ?")->execute([$betAmount, $data['username']]);
            $pdo->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'close_bet') {
        try {
            $betId = $data['betId'];
            $winningOption = $data['winningOption'];

            $stmtAmount = $pdo->prepare("SELECT amount FROM bets WHERE id=?");
            $stmtAmount->execute([$betId]);
            $betAmount = (float)$stmtAmount->fetchColumn();

            $stmtParts = $pdo->prepare("SELECT username, option_name FROM bet_participants WHERE bet_id=?");
            $stmtParts->execute([$betId]);
            $participants = $stmtParts->fetchAll();

            $winners = [];
            $losers  = [];
            foreach ($participants as $p) {
                if ($p['option_name'] === $winningOption) $winners[] = $p;
                else $losers[] = $p;
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE bets SET status='closed', winning_option=? WHERE id=?")->execute([$winningOption, $betId]);

            $stmtCredit = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE username = ?");

            if (count($winners) === 0 || count($losers) === 0) {
                foreach ($participants as $p) {
                    $stmtCredit->execute([$betAmount, $p['username']]);
                }
            } else {
                $winShare = round((count($losers) * $betAmount) / count($winners), 2);
                foreach ($winners as $w) {
                    $stmtCredit->execute([$betAmount + $winShare, $w['username']]);
                }
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'create_bet') {
        try {
            $pdo->beginTransaction();
            $stmtCat = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
            $stmtCat->execute([$data['category']]);

            $stmtBet = $pdo->prepare("INSERT INTO bets (id, title, amount, category, creator, status, winning_option) VALUES (?, ?, ?, ?, ?, 'open', NULL)");
            $stmtBet->execute([$data['id'], $data['title'], $data['amount'], $data['category'], $data['creator']]);

            $stmtOpt = $pdo->prepare("INSERT IGNORE INTO bet_options (bet_id, option_name) VALUES (?, ?)");
            foreach ($data['options'] as $opt) {
                $stmtOpt->execute([$data['id'], $opt]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($data !== null) {
        try {
            $pdo->beginTransaction();
            
            // 1. שמירת משתמשים
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, full_name, balance) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), password=VALUES(password)");
            foreach ($data['users'] as $u) {
                $stmtUser->execute([$u['username'], $u['password'], $u['fullName'], $u['balance']]);
            }

            // 2. שמירת קטגוריות
            $stmtCat = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
            foreach ($data['categories'] as $c) {
                $stmtCat->execute([$c]);
            }

            // 3. שמירת מנהלים
            $stmtAdmin = $pdo->prepare("INSERT INTO admins (name, role, bio, image) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role=VALUES(role), bio=VALUES(bio), image=VALUES(image)");
            foreach ($data['admins'] as $a) {
                $stmtAdmin->execute([$a['name'], $a['role'], $a['bio'], $a['image']]);
            }

            // 4. שמירת התערבויות, אפשרויות בחירה והשתתפויות
            $stmtBet = $pdo->prepare("INSERT INTO bets (id, title, amount, category, creator, status, winning_option) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), winning_option=VALUES(winning_option)");
            $stmtOpt = $pdo->prepare("INSERT IGNORE INTO bet_options (bet_id, option_name) VALUES (?, ?)");
            $stmtPart = $pdo->prepare("INSERT INTO bet_participants (bet_id, username, option_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_name=VALUES(option_name)");
            
            foreach ($data['bets'] as $b) {
                $winningOpt = isset($b['winningOption']) ? $b['winningOption'] : null;
                $stmtBet->execute([$b['id'], $b['title'], $b['amount'], $b['category'], $b['creator'], $b['status'], $winningOpt]);
                
                if (isset($b['options'])) {
                    foreach ($b['options'] as $opt) {
                        $stmtOpt->execute([$b['id'], $opt]);
                    }
                }
                
                if (isset($b['participants'])) {
                    foreach ($b['participants'] as $username => $opt) {
                        $stmtPart->execute([$b['id'], $username, $opt]);
                    }
                }
            }

            // 5. שמירת סליקות (Settlements)
            $stmtSet = $pdo->prepare("INSERT IGNORE INTO settlements (from_user, to_user, amount, timestamp) VALUES (?, ?, ?, ?)");
            if (isset($data['settlements'])) {
                foreach ($data['settlements'] as $s) {
                    $stmtSet->execute([$s['from'], $s['to'], $s['amount'], $s['timestamp']]);
                }
            }

            // 6. שמירת הודעות צור קשר (טפסים)
            $stmtMsg = $pdo->prepare("INSERT IGNORE INTO contact_messages (id, username, message, timestamp) VALUES (?, ?, ?, ?)");
            if (isset($data['messages'])) {
                foreach ($data['messages'] as $m) {
                    $stmtMsg->execute([$m['id'], $m['username'], $m['message'], $m['timestamp']]);
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Data saved to structured MySQL successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}