// ============================================================================
// 1. GLOBAL STATE & INITIALIZATION
// ============================================================================
let appData = { users: [], categories: [], bets: [], admins: [], settlements: [], messages: [] };
let currentUser = null;
let authMode = 'login';
let localSelections = {}; // Tracks temporary UI selections before locking a bet

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

/**
 * Initializes the application.
 * Fetches the current state from the database via api.php and restores user session.
 */
async function initApp() {
    try {
        const response = await fetch('api.php');
        if (!response.ok) {
            let errorData = await response.json();
            throw new Error(errorData.message || "Server Error");
        }
        appData = await response.json();
        
        // Restore user session from localStorage
        let storedUser = localStorage.getItem('currentUser');
        if (storedUser) {
            currentUser = JSON.parse(storedUser);
            // Refresh user data from the DB to get the latest balance
            const fresh = appData.users.find(u => u.username === currentUser.username);
            if (fresh) currentUser = fresh;
        }
    } catch(e) {
        console.error("Error initializing app", e);
        alert("שגיאה בטעינת הנתונים: " + e.message);
        // Fallback to empty state to prevent UI crashes if DB fails
        appData = { users: [], categories: [], bets: [], admins: [], settlements: [], messages: [] };
    }

    // Build the global UI components (always visible)
    buildNav();
    buildAuthModal();
    buildAddFundsModal();
    buildCloseBetModal();
    buildFooter();

    // Route to the appropriate renderer based on the current URL path
    const path = window.location.pathname;
    if (path.includes('markets.html')) renderMarkets();
    else if (path.includes('manage_bets.html')) renderManageBets();
    else if (path.includes('participants.html')) renderLedger();
    else if (path.includes('contact.html')) renderContact();
    else if (path.includes('team.html')) renderTeamPage();
}

/**
 * Saves the entire application state (Fallback mass-sync).
 * Primarily used for saving generic contact messages or new users.
 */
async function saveData() {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(appData)
        });
        if (!response.ok) {
            console.error("Server error:", await response.text());
            alert("שגיאה בשמירת הנתונים. נא לבדוק את החיבור לשרת.");
        }
    } catch (e) {
        console.error("Failed to save data to server", e);
        alert("שגיאת תקשורת: הנתונים לא נשמרו בשרת.");
    }
}

// ============================================================================
// 2. AUTHENTICATION & USER LOGIC
// ============================================================================
function toggleAuthMode() {
    authMode = authMode === 'login' ? 'register' : 'login';
    document.getElementById('authTitle').innerText = authMode === 'login' ? 'התחברות למערכת' : 'הרשמה למערכת';
    document.getElementById('registerFields').classList.toggle('hidden', authMode === 'login');
    document.getElementById('authToggleText').innerText = authMode === 'login' ? 'משתמש חדש? לחץ כאן להרשמה' : 'משתמש רשום? לחץ כאן להתחברות';
}

async function handleAuth(e) {
    e.preventDefault();
    let user = document.getElementById('authUser').value.trim();
    let pass = document.getElementById('authPass').value.trim();
    
    if (authMode === 'login') {
        let existing = appData.users.find(u => u.username === user);
        if (existing) {
            if (existing.password === pass) {
                currentUser = existing;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
                window.location.reload();
            } else {
                alert('סיסמה שגויה!');
            }
        } else {
            alert('משתמש לא קיים. מעביר למצב הרשמה...');
            toggleAuthMode();
            document.getElementById('authUser').value = user;
            document.getElementById('authPass').value = pass;
        }
    } else {
        let existing = appData.users.find(u => u.username === user);
        if(existing) return alert('שם משתמש כבר תפוס!');
        let fullName = document.getElementById('authFullName').value.trim();
        
        let newUser = { username: user, password: pass, fullName: fullName || user, balance: 0 };
        appData.users.push(newUser);
        currentUser = newUser;
        localStorage.setItem('currentUser', JSON.stringify(currentUser));
        
        // Save the new user to the database
        await saveData();
        window.location.reload();
    }
}

function logout() {
    localStorage.removeItem('currentUser');
    window.location.reload();
}

/**
 * Makes an API call to add funds to the current user's balance.
 */
async function addFunds() {
    const amount = parseFloat(document.getElementById('addFundsAmount').value);
    if (isNaN(amount) || amount <= 0) return alert('נא להזין סכום תקין.');
    try {
        const res = await fetch('api.php?action=add_funds', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: currentUser.username, amount })
        });
        const result = await res.json();
        if (result.status === 'success') {
            currentUser.balance = result.newBalance;
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            document.getElementById('addFundsModal').style.display = 'none';
            document.getElementById('addFundsAmount').value = '';
            buildNav(); // Refresh navbar balance
            alert(`הסכום התווסף! יתרה חדשה: ₪${result.newBalance}`);
        } else if (result.message === 'max_exceeded') {
            alert(`לא ניתן להוסיף סכום זה. ניתן להוסיף עוד עד ₪${result.allowed} (מגבלה: ₪10,000).`);
        } else {
            alert('שגיאה: ' + result.message);
        }
    } catch(e) {
        alert('שגיאה בהוספת כסף: ' + e.message);
    }
}

// ============================================================================
// 3. UI BUILDERS (NAV, FOOTER, MODALS)
// ============================================================================
function buildNav() {
    const navContainer = document.getElementById('main-nav');
    if (!navContainer) return;

    let categoriesLinks = appData.categories.map(c => `<a href="markets.html?cat=${encodeURIComponent(c)}">${c}</a>`).join('');

    let isMarkets = ['markets.html'].some(p => window.location.pathname.includes(p)) || window.location.pathname.endsWith('/');
    let currentStatus = new URLSearchParams(window.location.search).get('status') || 'open';
    
    let navTopicsHtml = isMarkets ? `
        <div class="nav-topics">
            <div class="toggle-switch">
                <input type="radio" id="nav-open" name="nav-status" value="open" onchange="changeStatusFilter('open')" ${currentStatus !== 'closed' ? 'checked' : ''}>
                <label for="nav-open">פתוחות</label>
                <input type="radio" id="nav-closed" name="nav-status" value="closed" onchange="changeStatusFilter('closed')" ${currentStatus === 'closed' ? 'checked' : ''}>
                <label for="nav-closed">סגורות</label>
                <div class="toggle-slider"></div>
            </div>
            <div class="nav-divider"></div>
            <a href="markets.html?status=${currentStatus}">כל ההתערבויות</a>
            ${appData.categories.map(c => `<a href="markets.html?status=${currentStatus}&cat=${encodeURIComponent(c)}">${c}</a>`).join('')}
        </div>
    ` : '';

    navContainer.innerHTML = `
        <div class="nav-top">
            <button class="hamburger-btn" onclick="toggleMenu()">☰</button>
            <a href="index.html" class="logo"><img src="images/logo.svg" alt="PolyFriends Logo"> PolyFriends</a>
            <div class="desktop-nav-links">
                <div class="dropdown-hover">
                    <a href="markets.html">התערבויות▾</a>
                    <div class="dropdown-content">${categoriesLinks}</div>
                </div>
                <a href="manage_bets.html">ניהול הימורים</a>
                <a href="participants.html">משתתפים</a>
                <a href="contact.html">צור קשר</a>
            </div>
            <input type="search" placeholder="חיפוש התערבויות..." id="globalSearch" oninput="handleSearchInput()" onkeypress="if(event.key==='Enter') window.location.href='markets.html?q='+encodeURIComponent(this.value)">
            <div class="auth-section">
                ${currentUser
                    ? `<span class="user-greeting">שלום, ${currentUser.fullName}</span>
                       <span class="balance-nav-badge">₪${Math.round(currentUser.balance * 100) / 100}</span>
                       <button class="auth-btn add-funds-btn" onclick="document.getElementById('addFundsModal').style.display='flex'">+ הוסף כסף</button>
                       <button class="auth-btn" onclick="logout()">התנתק</button>`
                    : `<button class="auth-btn" onclick="document.getElementById('authModal').style.display='flex'">התחבר / הירשם</button>`}
            </div>
        </div>
        ${navTopicsHtml}
        <div class="dropdown-menu" id="hamburgerMenu">
            <button class="close-menu-btn" onclick="toggleMenu()">&times;</button>
            <a href="markets.html">התערבויות פתוחות</a>
            <a href="manage_bets.html">ניהול הימורים</a>
            <a href="participants.html">משתתפים</a>
            <a href="contact.html">צור קשר</a>
        </div>
    `;
}

function toggleMenu() {
    document.getElementById("hamburgerMenu").classList.toggle("show");
}

function buildAuthModal() {
    if (document.getElementById('authModal')) return;
    const modalHtml = `
    <div id="authModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('authModal').style.display='none'">&times;</span>
            <h2 id="authTitle">התחברות למערכת</h2>
            <form onsubmit="handleAuth(event)">
                <input type="text" id="authUser" placeholder="שם משתמש (באנגלית)" required>
                <input type="password" id="authPass" placeholder="סיסמה" required>
                <div id="registerFields" class="hidden">
                    <input type="text" id="authFullName" placeholder="שם מלא (יוצג באפליקציה)">
                </div>
                <button type="submit" class="auth-btn modal-submit-btn">המשך</button>
                <p id="authToggleText" class="form-toggle-link" onclick="toggleAuthMode()">משתמש חדש? לחץ כאן להרשמה</p>
            </form>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function buildCloseBetModal() {
    if (document.getElementById('closeBetModal')) return;
    document.body.insertAdjacentHTML('beforeend', `
    <div id="closeBetModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('closeBetModal').style.display='none'">&times;</span>
            <h2>סגור התערבות</h2>
            <p id="closeBetTitle" style="color:#555; margin-bottom:15px;"></p>
            <p style="font-weight:bold; margin-bottom:10px;">מי ניצח?</p>
            <div id="closeBetOptions" style="display:flex; gap:12px; justify-content:center;"></div>
        </div>
    </div>`);
}

function buildAddFundsModal() {
    if (document.getElementById('addFundsModal')) return;
    document.body.insertAdjacentHTML('beforeend', `
    <div id="addFundsModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('addFundsModal').style.display='none'">&times;</span>
            <h2>הוסף כסף לחשבון</h2>
            <p id="currentBalanceDisplay" style="color:#555;"></p>
            <input type="number" id="addFundsAmount" placeholder="סכום להוספה (מקסימום ₪10,000)" min="1" max="10000" style="margin-top:10px;">
            <button class="auth-btn modal-submit-btn" onclick="addFunds()">הוסף</button>
        </div>
    </div>`);
}

function buildFooter() {
    const footerContainer = document.getElementById('main-footer');
    if (!footerContainer) return;
    
    footerContainer.innerHTML = `
        <div class="footer-content">
            <div class="footer-brand">
                <a href="index.html" class="logo" style="color: #3498db; font-size: 1.5em;">
                    PolyFriends
                </a>
                <p>זירת ההימורים החברתית שלכם לכל נושא שבעולם, ללא כסף אמיתי.</p>
            </div>
            <div class="footer-links">
                <h4>ניווט מהיר</h4>
                <a href="index.html">דף הבית</a>
                <a href="markets.html">התערבויות פתוחות</a>
                <a href="manage_bets.html">ניהול הימורים</a>
                <a href="participants.html">משתתפים</a>
                <a href="contact.html">צור קשר</a>
                <a href="table.php">טבלת הודעות (DB)</a>
            </div>
            <div class="footer-input">
                <h4>השאר לנו הודעה / הרשם לעדכונים</h4>
                <input type="text" placeholder="כתוב משהו..." id="footerInput">
                <button class="auth-btn" onclick="submitContact()">שלח</button>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 PolyFriends. כל הזכויות שמורות.</p>
        </div>
    `;
}

// ============================================================================
// 4. PAGE RENDERERS
// ============================================================================

/**
 * Renders the main markets page (open & closed bets) with filters and search logic.
 */
function renderMarkets() {
    const container = document.getElementById('markets-container');
    if(!container) return;
    
    let urlParams = new URLSearchParams(window.location.search);
    let searchQuery = document.getElementById('globalSearch') ? document.getElementById('globalSearch').value.trim().toLowerCase() : '';
    if(!searchQuery && urlParams.has('q')) searchQuery = urlParams.get('q').toLowerCase();
    
    let catFilter = urlParams.has('cat') ? urlParams.get('cat') : '';
    
    let selectStatus = document.getElementById('statusFilter');
    if (urlParams.has('status') && !window.initialStatusSet) {
        if (selectStatus) selectStatus.value = urlParams.get('status');
        window.initialStatusSet = true;
    }
    let statusFilter = selectStatus ? selectStatus.value : (urlParams.get('status') || 'open');
    
    let html = '';
    appData.bets.forEach(bet => {
        if(statusFilter !== 'all' && bet.status !== statusFilter) return;
        if(searchQuery && !bet.title.toLowerCase().includes(searchQuery)) return;
        if(catFilter && bet.category !== catFilter) return;
        
        let lockedChoice = currentUser && bet.participants[currentUser.username];
        let hasJoined = lockedChoice || (currentUser && localSelections[bet.id]);
        let isLocked = !!lockedChoice;

        // Stats bar
        const totalVotes = Object.keys(bet.participants).length;
        let statsHtml = '';
        if (bet.options.length === 2) {
            const count0 = Object.values(bet.participants).filter(o => o === bet.options[0]).length;
            const count1 = totalVotes - count0;
            const pct0 = totalVotes > 0 ? Math.round((count0 / totalVotes) * 100) : 50;
            const pct1 = 100 - pct0;
            statsHtml = `
                <div class="vote-bar-wrapper">
                    <div class="vote-bar">
                        <div class="vote-bar-left" style="width:${pct0}%"></div>
                        <div class="vote-bar-right" style="width:${pct1}%"></div>
                    </div>
                    <div class="vote-bar-labels">
                        <span>${bet.options[0]} ${pct0}%</span>
                        <span>${pct1}% ${bet.options[1]}</span>
                    </div>
                </div>`;
        }

        let buttonsHtml = '';
        if (bet.status === 'closed') {
            const winText = bet.winningOption ? `המנצח: ${bet.winningOption}` : 'נסגר ללא הכרעה';
            buttonsHtml = `<div class="closed-bet-notice">התערבות סגורה.<br>${winText}</div>`;
        } else {
            if (currentUser) {
                if (isLocked) {
                    buttonsHtml = `<div class="locked-choice-notice">בחרת ב ${lockedChoice} 🔒</div>`;
                } else {
                    const optBtns = bet.options.map(opt => {
                        const isSelected = hasJoined === opt;
                        const btnClass = isSelected ? 'btn-selected' : 'btn-unselected';
                        return `<button class="auth-btn bet-option-btn ${btnClass}" onclick="joinBet('${bet.id}', '${opt}')">${opt}</button>`;
                    }).join('');
                    buttonsHtml = `<div class="bet-options-wrapper">${optBtns}</div>`;
                    if (hasJoined) {
                        buttonsHtml += `<button class="auth-btn lock-btn" onclick="lockBet('${bet.id}')">🔒 נעל בחירה</button>`;
                    }
                }
            } else {
                buttonsHtml = `<button class="auth-btn" style="width:100%;padding:10px;" onclick="document.getElementById('authModal').style.display='flex'">התחבר כדי להשתתף</button>`;
            }
        }

        let potAmount = Object.keys(bet.participants).length * bet.amount;
        let creatorUser = appData.users.find(u => u.username === bet.creator);
        let creatorName = creatorUser ? creatorUser.fullName : bet.creator;

        html += `
        <div class="bet-card">
            <div>
                <h3>${bet.title}</h3>
                <h4>${bet.category}</h4>
                <p style="font-size:0.85em;color:#aaa;margin:0 0 10px 0;">👤 ${creatorName}</p>
                <p>סכום השתתפות: ₪${bet.amount}</p>
                ${statsHtml}
            </div>
            <div>
                <h4>קופה נוכחית: ₪${potAmount}</h4>
                ${buttonsHtml}
            </div>
        </div>`;
    });
    container.innerHTML = html || '<p style="text-align:center; width:100%;">לא נמצאו התערבויות העונות לסינון.</p>';
}

/**
 * Renders the "Manage Bets" page including bet creation, closing bets, and user history.
 */
function renderManageBets() {
    const catSelect = document.getElementById('categorySelect');
    const closedBetsContainer = document.getElementById('my-open-bets');
    if(!catSelect || !closedBetsContainer) return;
    
    catSelect.innerHTML = '<option value="" disabled selected>בחר קטגוריה</option>' + 
                          appData.categories.map(c => `<option value="${c}">${c}</option>`).join('') +
                          '<option value="other">אחר (הזן טקסט)...</option>';
                          
    // Safe binding for the custom category addition prompt
    catSelect.onchange = checkCustomCat;
    checkCustomCat();

    if(!currentUser) {
        closedBetsContainer.innerHTML = '<p>עליך להתחבר כדי לנהל הימורים.</p>';
        document.getElementById('createBetBtn').disabled = true;
        return;
    }
    
    let myOpenBets = appData.bets.filter(b => b.creator === currentUser.username && b.status === 'open');
    if(myOpenBets.length === 0) {
        closedBetsContainer.innerHTML = '<p style="text-align:center; width:100%;">אין לך הימורים פתוחים לסגירה.</p>';
    } else {
        closedBetsContainer.innerHTML = myOpenBets.map(bet => `
            <div class="market-card" style="text-align: center; display: flex; flex-direction: column; justify-content: space-between;">
                <h3 style="margin-top: 0; margin-bottom: 10px;">${bet.title}</h3>
                <p style="color: #555; margin-bottom: 15px;">קופה: ₪${Object.keys(bet.participants).length * bet.amount}</p>
                <button onclick="closeBet('${bet.id}')">סגור התערבות</button>
            </div>
        `).join('');
    }

    // Bet history
    const historyContainer = document.getElementById('bet-history-container');
    if (!historyContainer) return;
    const myHistory = appData.bets.filter(b => b.status === 'closed' && b.participants && b.participants[currentUser.username]);
    if (myHistory.length === 0) {
        historyContainer.innerHTML = '<p style="text-align:center; width:100%;">אין היסטוריית הימורים עדיין.</p>';
    } else {
        historyContainer.innerHTML = myHistory.map(bet => {
            const myChoice = bet.participants[currentUser.username];
            const won = bet.winningOption && myChoice === bet.winningOption;
            const voided = !bet.winningOption || Object.keys(bet.participants).length === 0;
            const numWinners = Object.values(bet.participants).filter(o => o === bet.winningOption).length;
            const numLosers = Object.keys(bet.participants).length - numWinners;
            let amountText = '';
            if (voided) {
                amountText = '₪0 (בוטל)';
            } else if (won) {
                const winShare = numWinners > 0 ? Math.round((numLosers * bet.amount / numWinners) * 100) / 100 : 0;
                amountText = `+₪${winShare}`;
            } else {
                amountText = `-₪${bet.amount}`;
            }
            const resultClass = voided ? 'balance-zero' : (won ? 'balance-positive' : 'balance-negative');
            const resultIcon = voided ? '🤝' : (won ? '🏆' : '😔');
            return `
                <div class="market-card" style="text-align:center; display:flex; flex-direction:column; gap:8px;">
                    <h3 style="margin:0;">${bet.title}</h3>
                    <p style="margin:0; color:#888; font-size:0.85em;">${bet.category}</p>
                    <p style="margin:0;">הבחירה שלך: <strong>${myChoice}</strong></p>
                    <p style="margin:0;">תוצאה: <strong>${bet.winningOption || 'בוטל'}</strong></p>
                    <div class="balance-badge ${resultClass}">${resultIcon} ${amountText}</div>
                </div>`;
        }).join('');
    }
}

/**
 * Fetches and displays the leaderboard sorted by net winnings.
 */
async function renderLedger() {
    const container = document.getElementById('ledger-container');
    if (!container) return;

    const res = await fetch('api.php?action=leaderboard');
    const rows = await res.json();

    const medals = ['🥇', '🥈', '🥉'];
    container.innerHTML = rows.map((user, i) => {
        const net = user.net;
        const bClass = net > 0 ? 'balance-positive' : (net < 0 ? 'balance-negative' : 'balance-zero');
        const netText = net > 0 ? `+₪${net}` : (net < 0 ? `-₪${Math.abs(net)}` : '₪0');
        const rank = medals[i] || `#${i + 1}`;
        return `
            <div class="market-card" style="margin-bottom: 20px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                    <span style="font-size: 1.8em;">${rank}</span>
                    <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.fullName)}&background=random&color=fff" style="border-radius: 50%; width: 50px; height: 50px;">
                    <div>
                        <h3 style="margin: 0;">${user.fullName}</h3>
                        <h4 style="margin: 0; color: #7f8c8d;">@${user.username}</h4>
                    </div>
                </div>
                <div class="balance-badge ${bClass}">${netText}</div>
            </div>`;
    }).join('');
}

function renderContact() {
    const container = document.getElementById('admins-container');
    if(container) {
        container.innerHTML = appData.admins.map(admin => `
            <div>
                <img src="${admin.image}">
                <h3>${admin.name}</h3>
                <h4>${admin.role}</h4>
                <p>${admin.bio}</p>
            </div>
        `).join('');
    }
}

function renderTeamPage() {
    const container = document.getElementById('team-container');
    if(container) {
        container.innerHTML = appData.admins.map(admin => `
            <div>
                <img src="${admin.image}" alt="${admin.name}">
                <h3>${admin.name}</h3>
                <h4>${admin.role}</h4>
                <p>${admin.bio}</p>
            </div>
        `).join('');
    }
}

// ============================================================================
// 5. BETTING ACTIONS (JOIN, LOCK, CREATE, CLOSE)
// ============================================================================
function changeStatusFilter(status) {
    if (window.location.pathname.includes('markets.html')) {
        let select = document.getElementById('statusFilter');
        if (select) select.value = status;
        
        let url = new URL(window.location);
        url.searchParams.set('status', status);
        window.history.pushState({}, '', url);
        
        renderMarkets();
    } else {
        window.location.href = 'markets.html?status=' + status;
    }
}

function handleSearchInput() {
    if (window.location.pathname.includes('markets.html')) {
        renderMarkets();
    }
}

function joinBet(betId, option) {
    // Updates UI locally to show selection before making DB call
    localSelections[betId] = option;
    renderMarkets();
}

/**
 * Locks the user's selected option for a specific bet.
 * Deducts the bet amount from the user's balance via the API.
 */
async function lockBet(betId) {
    const option = localSelections[betId];
    if (!option) return;
    const bet = appData.bets.find(b => b.id === betId);
    
    if (currentUser.balance < bet.amount) {
        return alert(`אין מספיק כסף! היתרה שלך: ₪${currentUser.balance}. נדרש: ₪${bet.amount}. הוסף כסף דרך כפתור "+ הוסף כסף".`);
    }
    
    try {
        const res = await fetch('api.php?action=lock_bet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ betId, username: currentUser.username, option })
        });
        const result = await res.json();
        if (result.status !== 'success') {
            if (result.message === 'insufficient_funds') return alert('אין מספיק כסף בחשבון!');
            throw new Error(result.message || 'שגיאת שרת');
        }
        delete localSelections[betId];
        
        // Fetch fresh state to update the UI
        const fresh = await fetch('api.php');
        appData = await fresh.json();
        const updatedUser = appData.users.find(u => u.username === currentUser.username);
        if (updatedUser) { currentUser = updatedUser; localStorage.setItem('currentUser', JSON.stringify(currentUser)); }
        
        buildNav();
        renderMarkets();
    } catch(e) {
        alert('שגיאה בנעילת הבחירה: ' + e.message);
    }
}

function checkCustomCat() {
    let select = document.getElementById('categorySelect');
    if (!select) return;
    
    if (select.value === 'other') {
        let newCat = window.prompt("הזן קטגוריה חדשה (למשל: אוכל, סרטים):");
        if (newCat && newCat.trim() !== "") {
            let opt = document.createElement('option');
            opt.value = newCat.trim();
            opt.text = newCat.trim();
            select.insertBefore(opt, select.lastElementChild);
            select.value = newCat.trim();
        } else {
            select.value = "";
        }
    }
}

async function createBet(e) {
    e.preventDefault();
    if(!currentUser) return alert('יש להתחבר!');
    let title = document.getElementById('betTitle').value;
    let amount = parseInt(document.getElementById('betAmount').value);
    let cat = document.getElementById('categorySelect').value;

    let opt1 = document.getElementById('betOption1').value.trim();
    let opt2 = document.getElementById('betOption2').value.trim();
    let opts = [opt1, opt2].filter(o => o);

    let newBet = {
        id: 'bet_' + Date.now(), title, amount, category: cat, creator: currentUser.username, status: 'open',
        options: opts, participants: {}, winningOption: null
    };

    try {
        const res = await fetch('api.php?action=create_bet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newBet)
        });
        if (!res.ok) throw new Error((await res.json()).message || 'שגיאת שרת');
        
        // Fetch fresh state to update the UI
        const fresh = await fetch('api.php');
        appData = await fresh.json();
        alert('התערבות נוצרה בהצלחה!');
        e.target.reset();
        checkCustomCat();
        renderManageBets();
    } catch(e) {
        alert('שגיאה ביצירת ההימור: ' + e.message);
    }
}

function closeBet(betId) {
    const bet = appData.bets.find(b => b.id === betId);
    const modal = document.getElementById('closeBetModal');
    document.getElementById('closeBetTitle').textContent = bet.title;
    const optionsDiv = document.getElementById('closeBetOptions');
    optionsDiv.innerHTML = bet.options.map(opt =>
        `<button class="auth-btn" style="flex:1; padding:10px;" onclick="confirmCloseBet('${betId}', '${opt.replace(/'/g, "\\'")}')">${opt}</button>`
    ).join('');
    modal.style.display = 'flex';
}

async function confirmCloseBet(betId, winningOption) {
    document.getElementById('closeBetModal').style.display = 'none';
    try {
        const res = await fetch('api.php?action=close_bet', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ betId, winningOption })
        });
        const closeResult = await res.json();
        if (closeResult.status !== 'success') throw new Error(closeResult.message || 'שגיאת שרת');
        
        // Fetch fresh state to update the UI
        const fresh = await fetch('api.php');
        appData = await fresh.json();
        const updatedUser = appData.users.find(u => u.username === currentUser.username);
        if (updatedUser) { currentUser = updatedUser; localStorage.setItem('currentUser', JSON.stringify(currentUser)); }
        
        buildNav();
        renderManageBets();
        alert('התערבות נסגרה בהצלחה! הזכיות זוכו אוטומטית.');
    } catch(e) {
        alert('שגיאה בסגירת ההימור: ' + e.message);
    }
}

// ============================================================================
// 6. UTILITIES (CONTACT, PAYOUT CALCULATOR)
// ============================================================================
async function submitContact() {
    let input = document.getElementById('footerInput');
    let msg = input.value.trim();
    if(!msg) return alert('נא להזין הודעה בטופס.');
    
    if(!appData.messages) appData.messages = [];
    appData.messages.push({
        id: 'msg_' + Date.now(),
        username: currentUser ? currentUser.username : null,
        message: msg,
        timestamp: Date.now()
    });
    
    await saveData();
    input.value = '';
    alert('תודה! קלטנו את ההודעה בהצלחה!');
}