<?php
$to = 'sahar.guy@gmail.com';

$fullname = isset($_POST['fullname']) ? htmlspecialchars(strip_tags($_POST['fullname'])) : '';
$subject  = isset($_POST['subject'])  ? htmlspecialchars(strip_tags($_POST['subject']))  : '';
$message  = isset($_POST['message'])  ? htmlspecialchars(strip_tags($_POST['message']))  : '';
$terms    = isset($_POST['terms'])    ? 'אישר תקנון' : 'לא אישר תקנון';

$mail_subject = "פנייה חדשה מ-PolyFriends: $subject";

$mail_body = "פנייה חדשה התקבלה דרך אתר PolyFriends:\n\n";
$mail_body .= "שם מלא: $fullname\n";
$mail_body .= "סוג פנייה: $subject\n";
$mail_body .= "תקנון: $terms\n";
$mail_body .= "הודעה:\n$message\n";

$headers = "From: noreply@polyfriends.com\r\n";
$headers .= "Reply-To: noreply@polyfriends.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, $mail_subject, $mail_body, $headers);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyFriends - תודה</title>
    <link rel="stylesheet" href="style.css">
    <script src="function.js"></script>
</head>
<body>
    <nav id="main-nav"></nav>
    <div class="info-section" style="text-align:center; padding: 60px 20px;">
        <?php if ($sent): ?>
            <h1>תודה, <?= $fullname ?>!</h1>
            <p>הפנייה שלך נשלחה בהצלחה. נחזור אליך בהקדם.</p>
        <?php else: ?>
            <h1>שגיאה בשליחה</h1>
            <p>אירעה שגיאה בשליחת הטופס. נסה שנית מאוחר יותר.</p>
        <?php endif; ?>
        <a href="contact.html" class="auth-btn" style="display:inline-block; margin-top:20px;">חזרה לדף יצירת קשר</a>
    </div>
    <footer id="main-footer"></footer>
</body>
</html>
