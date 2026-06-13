<?php
header('Content-Type: application/json');

// ====================================================================
// 1. DATABASE CONNECTION SETTINGS
// ====================================================================
$servername = "sql100.byethost31.com";
$username = "b31_41617640";
$password = "gt#Q#P!9RAm76j9";
$dbname = "b31_41617640_my_polyfriends_db";
$charset = 'utf8mb4';

// Enable mysqli exceptions so try/catch blocks work exactly like PDO
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create mysqli connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset($charset);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

// ====================================================================
// 2. ROUTER / CONTROLLER
// ====================================================================
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($action === 'leaderboard') {
        getLeaderboard($conn);
    } else {
        getAllData($conn);
    }
} elseif ($method === 'POST') {
    // Get the raw POST body and parse it as a JSON array
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // If JSON decoding fails, return a 400 Bad Request error
    if ($data === null) {
        sendResponse(400, ['status' => 'error', 'message' => 'Invalid JSON']);
    }

    // Route the request to the appropriate function based on the 'action' parameter
    switch ($action) {
        case 'add_funds':
            addFunds($conn, $data);
            break;
        case 'lock_bet':
            lockBet($conn, $data);
            break;
        case 'close_bet':
            closeBet($conn, $data);
            break;
        case 'create_bet':
            createBet($conn, $data);
            break;
        case 'send_email':
            sendContactEmail($data);
            break;
        default:
            syncAllData($conn, $data);
            break;
    }
} else {
    sendResponse(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

// ====================================================================
// 3. FUNCTIONS
// ====================================================================

/**
 * Helper function to output a JSON response to the client and stop execution.
 * @param int $statusCode HTTP status code (e.g., 200 for success, 400/500 for errors)
 * @param array $response The data array to be encoded as JSON
 */
function sendResponse($statusCode, $response) {
    http_response_code($statusCode);
    echo json_encode($response);
    exit;
}

/**
 * Calculates and returns the leaderboard data (users sorted by net winnings/losses).
 * Logic: 
 * - If the bet isn't closed or has no winning option, net is 0.
 * - If the user guessed the winning option, they get their share of the losers' pool.
 * - If the user guessed wrong, they lose their bet amount.
 */
function getLeaderboard($conn) {
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
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) { $r['net'] = round((float)$r['net'], 2); }
    
    // Return the calculated leaderboard array
    sendResponse(200, $rows);
}

/**
 * Fetches all necessary application data to build the frontend initial state.
 * Pulls data from multiple tables and constructs a single, structured JSON object.
 */
function getAllData($conn) {
    // 1. Fetch Users
    $users = $conn->query("SELECT username, password, full_name AS fullName, balance FROM users")->fetch_all(MYSQLI_ASSOC);
    foreach ($users as &$u) { $u['balance'] = (float)$u['balance']; }

    // 2. Fetch Categories (returns a flat array of names)
    $categories = array_column($conn->query("SELECT name FROM categories")->fetch_all(MYSQLI_ASSOC), 'name');

    // 3. Fetch Admins
    $admins = $conn->query("SELECT name, role, bio, image FROM admins")->fetch_all(MYSQLI_ASSOC);

    // 4. Fetch Bets
    $bets = $conn->query("SELECT id, title, amount, category, creator, status, winning_option AS winningOption FROM bets")->fetch_all(MYSQLI_ASSOC);
    
    // Prepare statements for nested data fetching (options and participants) to optimize performance
    $stmtOpt = $conn->prepare("SELECT option_name FROM bet_options WHERE bet_id = ?");
    $stmtPart = $conn->prepare("SELECT username, option_name FROM bet_participants WHERE bet_id = ?");
    
    $currentBetId = "";
    $stmtOpt->bind_param("s", $currentBetId);
    $stmtPart->bind_param("s", $currentBetId);

    foreach ($bets as &$bet) {
        $bet['amount'] = (float)$bet['amount'];
        $currentBetId = $bet['id'];
        
        // Fetch and attach options for the current bet
        $stmtOpt->execute();
        $bet['options'] = array_column($stmtOpt->get_result()->fetch_all(MYSQLI_ASSOC), 'option_name');

        // Fetch and attach participants for the current bet as an object mapping {username: option_name}
        $stmtPart->execute();
        $parts = [];
        foreach ($stmtPart->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
            $parts[$p['username']] = $p['option_name'];
        }
        $bet['participants'] = $parts;
    }

    // 5. Fetch Settlements
    $settlements = $conn->query("SELECT from_user AS `from`, to_user AS `to`, amount, timestamp FROM settlements ORDER BY timestamp ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($settlements as &$s) {
        $s['amount'] = (float)$s['amount'];
        $s['timestamp'] = (int)$s['timestamp'];
    }

    // 6. Fetch Contact Messages
    $messages = $conn->query("SELECT id, username, message, timestamp FROM contact_messages ORDER BY timestamp ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($messages as &$m) {
        $m['timestamp'] = (int)$m['timestamp'];
    }

    // Return the aggregated application state to the frontend
    sendResponse(200, [
        'users' => $users,
        'categories' => $categories,
        'bets' => $bets,
        'admins' => $admins,
        'settlements' => $settlements,
        'messages' => $messages
    ]);
}

/**
 * Adds virtual funds to a user's account.
 * Validates that the amount is positive and that the total balance doesn't exceed 10,000.
 * Returns the updated balance.
 */
function addFunds($conn, $data) {
    try {
        $amount = (float)$data['amount'];
        if ($amount <= 0) { 
            sendResponse(400, ['status' => 'error', 'message' => 'invalid_amount']); 
        }

        // Fetch current user balance
        $stmtBal = $conn->prepare("SELECT balance FROM users WHERE username = ?");
        $stmtBal->bind_param("s", $data['username']);
        $stmtBal->execute();
        $currentBalance = (float)$stmtBal->get_result()->fetch_row()[0];

        // Check maximum allowed limit (10,000)
        if ($currentBalance + $amount > 10000) {
            $allowed = round(10000 - $currentBalance, 2);
            sendResponse(400, ['status' => 'error', 'message' => 'max_exceeded', 'allowed' => $allowed]);
        }

        // Update balance and return the new balance
        $stmtUpd = $conn->prepare("UPDATE users SET balance = balance + ? WHERE username = ?");
        $stmtUpd->bind_param("ds", $amount, $data['username']);
        $stmtUpd->execute();

        $stmtBal->execute();
        sendResponse(200, ['status' => 'success', 'newBalance' => (float)$stmtBal->get_result()->fetch_row()[0]]);
    } catch (Exception $e) {
        sendResponse(500, ['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Locks a user's bet choice and deducts the bet amount from their balance.
 * Uses a database transaction to ensure both operations succeed or fail together.
 */
function lockBet($conn, $data) {
    try {
        // Retrieve the required bet amount
        $stmtBet = $conn->prepare("SELECT amount FROM bets WHERE id = ?");
        $stmtBet->bind_param("s", $data['betId']);
        $stmtBet->execute();
        $betAmount = (float)$stmtBet->get_result()->fetch_row()[0];

        // Retrieve the current user balance
        $stmtBal = $conn->prepare("SELECT balance FROM users WHERE username = ?");
        $stmtBal->bind_param("s", $data['username']);
        $stmtBal->execute();
        $balance = (float)$stmtBal->get_result()->fetch_row()[0];

        // Verify user has enough funds to place the bet
        if ($balance < $betAmount) {
            sendResponse(400, ['status' => 'error', 'message' => 'insufficient_funds']);
        }

        // Start transaction: Insert participant choice and deduct funds
        $conn->begin_transaction();
        $stmtPart = $conn->prepare("INSERT INTO bet_participants (bet_id, username, option_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_name=VALUES(option_name)");
        $stmtPart->bind_param("sss", $data['betId'], $data['username'], $data['option']);
        $stmtPart->execute();

        $stmtUpd = $conn->prepare("UPDATE users SET balance = balance - ? WHERE username = ?");
        $stmtUpd->bind_param("ds", $betAmount, $data['username']);
        $stmtUpd->execute();

        $conn->commit();
        sendResponse(200, ['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(500, ['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Closes a bet, designates the winning option, and distributes winnings.
 * Logic:
 * - Groups participants into winners and losers.
 * - If everyone won or everyone lost, original bets are simply refunded.
 * - Otherwise, losers' funds are split equally among the winners.
 */
function closeBet($conn, $data) {
    try {
        $betId = $data['betId'];
        $winningOption = $data['winningOption'];

        // Get the bet amount and all participants for this bet
        $stmtAmount = $conn->prepare("SELECT amount FROM bets WHERE id=?");
        $stmtAmount->bind_param("s", $betId);
        $stmtAmount->execute();
        $betAmount = (float)$stmtAmount->get_result()->fetch_row()[0];

        $stmtParts = $conn->prepare("SELECT username, option_name FROM bet_participants WHERE bet_id=?");
        $stmtParts->bind_param("s", $betId);
        $stmtParts->execute();
        $participants = $stmtParts->get_result()->fetch_all(MYSQLI_ASSOC);

        // Separate participants into winners and losers
        $winners = [];
        $losers  = [];
        foreach ($participants as $p) {
            if ($p['option_name'] === $winningOption) $winners[] = $p;
            else $losers[] = $p;
        }

        $conn->begin_transaction();
        
        // Mark the bet as closed and save the winning option
        $stmtUpdBet = $conn->prepare("UPDATE bets SET status='closed', winning_option=? WHERE id=?");
        $stmtUpdBet->bind_param("ss", $winningOption, $betId);
        $stmtUpdBet->execute();

        // Prepare statement for crediting users
        $stmtCredit = $conn->prepare("UPDATE users SET balance = balance + ? WHERE username = ?");
        $creditAmount = 0; $creditUser = "";
        $stmtCredit->bind_param("ds", $creditAmount, $creditUser);

        // Winnings distribution logic
        if (count($winners) === 0 || count($losers) === 0) {
            // Edge case: No winners or no losers -> Refund original bet amount to everyone
            foreach ($participants as $p) {
                $creditAmount = $betAmount;
                $creditUser = $p['username'];
                $stmtCredit->execute();
            }
        } else {
            // Normal case: Calculate share of the losers' pool for each winner
            $winShare = round((count($losers) * $betAmount) / count($winners), 2);
            foreach ($winners as $w) {
                $creditAmount = $betAmount + $winShare;
                $creditUser = $w['username'];
                $stmtCredit->execute();
            }
        }
        $conn->commit();
        sendResponse(200, ['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(500, ['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Creates a new bet and inserts its corresponding options into the DB.
 * Saves category if it doesn't already exist.
 */
function createBet($conn, $data) {
    try {
        $conn->begin_transaction();
        
        $stmtCat = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmtCat->bind_param("s", $data['category']);
        $stmtCat->execute();

        $stmtBet = $conn->prepare("INSERT INTO bets (id, title, amount, category, creator, status, winning_option) VALUES (?, ?, ?, ?, ?, 'open', NULL)");
        $stmtBet->bind_param("ssdss", $data['id'], $data['title'], $data['amount'], $data['category'], $data['creator']);
        $stmtBet->execute();

        $stmtOpt = $conn->prepare("INSERT IGNORE INTO bet_options (bet_id, option_name) VALUES (?, ?)");
        $optName = "";
        $stmtOpt->bind_param("ss", $data['id'], $optName);
        foreach ($data['options'] as $opt) {
            $optName = $opt;
            $stmtOpt->execute();
        }
        $conn->commit();
        sendResponse(200, ['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(500, ['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * Sends a basic email notification using PHP's native mail() function.
 */
function sendContactEmail($data) {
    try {
        $to = 'idanlin97@gmail.com';
        $subject = $data['subject'] ?? 'הודעה חדשה מטופס צור קשר';
        $message = $data['message'] ?? '';
        $replyTo = $data['replyTo'] ?? '';
        
        $headers = "From: noreply@polyfriends.com\r\n";
        if (!empty($replyTo)) {
            $headers .= "Reply-To: $replyTo\r\n";
        }
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (mail($to, $subject, $message, $headers)) {
            sendResponse(200, ['status' => 'success', 'message' => 'Email sent successfully']);
        } else {
            sendResponse(500, ['status' => 'error', 'message' => 'Failed to send email from server']);
        }
    } catch (Exception $e) {
        sendResponse(500, ['status' => 'error', 'message' => $e->getMessage()]);
    }
}

/**
 * A catch-all function to synchronize the entire application state to the database.
 * Uses bulk "INSERT ... ON DUPLICATE KEY UPDATE" to efficiently insert new rows 
 * or update existing rows without raising errors.
 */
function syncAllData($conn, $data) {
    try {
        $conn->begin_transaction();
        
        // Save users
        $stmtUser = $conn->prepare("INSERT INTO users (username, password, full_name, balance) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), password=VALUES(password)");
        $uUser = ""; $uPass = ""; $uName = ""; $uBal = 0;
        $stmtUser->bind_param("sssd", $uUser, $uPass, $uName, $uBal);
        foreach ($data['users'] as $u) {
            $uUser = $u['username']; $uPass = $u['password']; $uName = $u['fullName']; $uBal = $u['balance'];
            $stmtUser->execute();
        }

        // Save categories
        $stmtCat = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $cName = "";
        $stmtCat->bind_param("s", $cName);
        foreach ($data['categories'] as $c) {
            $cName = $c;
            $stmtCat->execute();
        }

        // Save admin profiles
        $stmtAdmin = $conn->prepare("INSERT INTO admins (name, role, bio, image) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role=VALUES(role), bio=VALUES(bio), image=VALUES(image)");
        $aName = ""; $aRole = ""; $aBio = ""; $aImg = "";
        $stmtAdmin->bind_param("ssss", $aName, $aRole, $aBio, $aImg);
        foreach ($data['admins'] as $a) {
            $aName = $a['name']; $aRole = $a['role']; $aBio = $a['bio']; $aImg = $a['image'];
            $stmtAdmin->execute();
        }

        // Save bets, options, and participant choices
        $stmtBet = $conn->prepare("INSERT INTO bets (id, title, amount, category, creator, status, winning_option) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), winning_option=VALUES(winning_option)");
        $bId = ""; $bTitle = ""; $bAmt = 0; $bCat = ""; $bCreator = ""; $bStatus = ""; $bWin = null;
        $stmtBet->bind_param("ssdssss", $bId, $bTitle, $bAmt, $bCat, $bCreator, $bStatus, $bWin);
        
        $stmtOpt = $conn->prepare("INSERT IGNORE INTO bet_options (bet_id, option_name) VALUES (?, ?)");
        $oBetId = ""; $oName = "";
        $stmtOpt->bind_param("ss", $oBetId, $oName);

        $stmtPart = $conn->prepare("INSERT INTO bet_participants (bet_id, username, option_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_name=VALUES(option_name)");
        $pBetId = ""; $pUser = ""; $pOpt = "";
        $stmtPart->bind_param("sss", $pBetId, $pUser, $pOpt);
        
        foreach ($data['bets'] as $b) {
            $bId = $b['id']; $bTitle = $b['title']; $bAmt = $b['amount']; $bCat = $b['category']; $bCreator = $b['creator']; $bStatus = $b['status'];
            $bWin = isset($b['winningOption']) ? $b['winningOption'] : null;
            $stmtBet->execute();
            
            // Save nested options for this bet
            if (isset($b['options'])) {
                $oBetId = $b['id'];
                foreach ($b['options'] as $opt) {
                    $oName = $opt;
                    $stmtOpt->execute();
                }
            }
            
            // Save nested participants for this bet
            if (isset($b['participants'])) {
                $pBetId = $b['id'];
                foreach ($b['participants'] as $username => $opt) {
                    $pUser = $username; $pOpt = $opt;
                    $stmtPart->execute();
                }
            }
        }

        // Save settlements (debt tracking)
        $stmtSet = $conn->prepare("INSERT IGNORE INTO settlements (from_user, to_user, amount, timestamp) VALUES (?, ?, ?, ?)");
        $sFrom = ""; $sTo = ""; $sAmt = 0; $sTime = 0;
        $stmtSet->bind_param("ssds", $sFrom, $sTo, $sAmt, $sTime);
        if (isset($data['settlements'])) {
            foreach ($data['settlements'] as $s) {
                $sFrom = $s['from']; $sTo = $s['to']; $sAmt = $s['amount']; $sTime = $s['timestamp'];
                $stmtSet->execute();
            }
        }

        // Save contact form messages
        $stmtMsg = $conn->prepare("INSERT IGNORE INTO contact_messages (id, username, message, timestamp) VALUES (?, ?, ?, ?)");
        $mId = ""; $mUser = null; $mMsg = ""; $mTime = 0;
        $stmtMsg->bind_param("ssss", $mId, $mUser, $mMsg, $mTime);
        if (isset($data['messages'])) {
            foreach ($data['messages'] as $m) {
                $mId = $m['id']; $mUser = $m['username']; $mMsg = $m['message']; $mTime = $m['timestamp'];
                $stmtMsg->execute();
            }
        }

        $conn->commit();
        sendResponse(200, ['status' => 'success', 'message' => 'Data saved successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(500, ['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
}