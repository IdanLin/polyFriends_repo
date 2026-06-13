# polyFriends_repo

**Must Have List:**
| \*\*Use Cases\*\* | Use Case 1 - Opening a new bet and friends joining | | **on page "https://idanlin.byethost31.com/manage\_bets.html" file "manage\_bets.html"**

| \*\*Use Cases\*\* | Use Case 2 - Marking a winner, closing the bet, and calculating the balance | | **on page "https://idanlin.byethost31.com/manage\_bets.html" file "manage\_bets.html"**

| \*\*Structure \& Pages\*\* | Total of 5 pages on the website | | **index.html, idanlin.html, guysahar.html, contact.html, manage\_bet.html, markets.html, participants.html table.php**

| \*\*Structure \& Pages\*\* | Top and bottom menus (Header and Footer) | | **on every page build by the JS function in "function.js" -> "buildNav()", "buildFooter()"**

| \*\*Structure \& Pages\*\* | \*\*Home Page:\*\* Image, CSS, links and menus, JS input/output, logo | | **on "index.html"** 

| \*\*Structure \& Pages\*\* | \*\*Team Member Personal Page:\*\* Title, profile picture, short description, contact info, responsive design, JS usage | | **on "idanlin.html", "guysahar.html"**

| \*\*Structure \& Pages\*\* | \*\*Team Page:\*\* Title, links to personal pages, contact info for team leader, responsive design, JS usage, CSS usage | | **on "contact.html" with JS on the Form**

| \*\*Elements (HTML)\*\* | 3 links to external websites | | **in "index.html"**

| \*\*Elements (HTML)\*\* | At least 5 images | | **3 in "index.html", 2 in "contact.html"** 

| \*\*Elements (HTML)\*\* | Include a table | | **in "index.html"**

| \*\*Elements (HTML)\*\* | Include an ordered list (OL) | |  **on "index.html"**

| \*\*Elements (HTML)\*\* | Include an unordered list (UL) | | **on "index.html"**

| \*\*Elements (HTML)\*\* | Include video or audio | | **on "index.html"**

| \*\*Forms\*\* | \*\*Form 1 (Email submission):\*\* Sent to the team member's email | | **on "contact.html"**

| \*\*Forms\*\* | \*\*Form 1:\*\* Contains 3 INPUT controls | | **on "contact.html"**

| \*\*Forms\*\* | \*\*Form 1:\*\* Contains all types of controls (select, button, email, password, url, tel, number, range, color, date, time, datetime, textarea, file, datalist) | | **on "contact.html"**

| \*\*Design (CSS)\*\* | In every page: CSS styling (size, color, and text design). Can be in a style file | | **on style.css**

| \*\*Design (CSS)\*\* | In one of the pages: Background image | | **on "markets.html" body class markets-bg**

| \*\*Design (CSS)\*\* | At least one styling by id | | **on "style.css" #globalSearch**

| \*\*Design (CSS)\*\* | At least one styling by class | | **on "style.css" most of the styles**

| \*\*Design (CSS)\*\* | Use of media queries for responsive design | | **on "index.html"**

| \*\*JS - Client Side\*\* | In every page: Use of JS (in a tag or external file) | | **every page has the nav's and forms or cards with JS on the "function.js"**

| \*\*JS - Client Side\*\* | Use of outputs: window.alert | | **on "function.js" and "contact.html" (multiple times for errors and success messages)**

| \*\*JS - Client Side\*\* | Use of outputs: document.write | | **on "index.html" at the bottom of the page**

| \*\*JS - Client Side\*\* | Use of outputs: innerHTML | | **on "function.js" in buildNav(), buildFooter(), renderMarkets()**

| \*\*JS - Client Side\*\* | Use of a loop | | **on "function.js" in renderMarkets() using forEach loop**

| \*\*JS - Client Side\*\* | Use of conditional statements | | **if-else on "function.js"**

| \*\*JS - Client Side\*\* | At least one user interaction: window.prompt | | **on "function.js" in checkCustomCat() when adding a new category**

| \*\*JS - Client Side\*\* | Use at least once: function | | **on "function.js"**

| \*\*JS - Client Side\*\* | Use at least once: array | | **on "function.js" (appData arrays, medals array in renderLedger)**

| \*\*JS - Client Side\*\* | Use at least once: string | | **on "function.js" buildNav()**

| \*\*PHP \& MySQL\*\* | Create a MySQL database, table must have at least 4 fields | | **there are many, the Form table has 4 rows. that save the info from the send massage form on the footer in each page**

| \*\*PHP \& MySQL\*\* | \*\*Form 2 (DB Update):\*\* Form inputs sent to a PHP page on the server | | **in the footer send message form**
| \*\*PHP \& MySQL\*\* | Update table via form (add a new row) | | **message form from the footer change the message table on the DB**

| \*\*PHP \& MySQL\*\* | Display the database table on one of the site's pages | | **the page "table.php show the message form table from the DB**

| \*\*PHP \& MySQL\*\* | \*\*Form 3 (PHP without DB):\*\* Receive inputs and perform PHP actions, display directly on site without database | | **when send mail to team member the action on "api.php" -> "if ($action === 'send_email')"**

| \*\*PHP \& MySQL\*\* | \*\*In Form 3:\*\* Use at least one function | | **on "table.php" function countTotalMessages($msgsArray)**

| \*\*PHP \& MySQL\*\* | \*\*In Form 3:\*\* Use at least one array | | **on "table.php" $msgsArray**

| \*\*PHP \& MySQL\*\* | \*\*In Form 3:\*\* Use at least one loop | | **on "table.php" -> function countTotalMessages($msgsArray)->  foreach ($msgsArray as $item)**

| \*\*PHP \& MySQL\*\* | \*\*In Form 3:\*\* Use at least one string function | | **on "table.php" -> function generateCustomTitle($msgsArray)**

| \*\*PHP \& MySQL\*\* | \*\*In Form 3:\*\* Use at least one mathematical operation | **on "table.php" -> function countTotalMessages($msgsArray) -> counter**

