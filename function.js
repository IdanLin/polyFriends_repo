let appData = { users: [], categories: [], bets: [], admins: [] };
let currentUser = null;
let authMode = 'login';

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    try {
        let storedData = localStorage.getItem('polyData');
        if (!storedData) {
            try {
                const response = await fetch('data.json');
                if (!response.ok) throw new Error("Network response was not ok");
                appData = await response.json();
            } catch (err) {
                console.warn("Fetch failed (likely CORS on file://). Using fallback data.");
                appData = { 
                    users: [], categories: ["פוליטיקה", "ספורט", "קריפטו", "תרבות פופ", "כלכלה", "מדע וטכנולוגיה"], bets: [], admins: [], settlements: []
                };
            }
            saveData();
        } else {
            appData = JSON.parse(storedData);
        }
        
        let storedUser = localStorage.getItem('currentUser');
        if (storedUser) currentUser = JSON.parse(storedUser);
    } catch(e) {
        console.error("Error initializing app", e);
    }

    // בניית הממשק מחוץ ל-try-catch כדי להבטיח שיופיע תמיד
    buildNav();
    buildAuthModal();
    buildFooter();

    const path = window.location.pathname;
    if (path.includes('markets.html')) renderMarkets();
    else if (path.includes('manage_bets.html')) renderManageBets();
    else if (path.includes('participants.html')) renderLedger();
    else if (path.includes('contact.html')) renderContact();
    else if (path.includes('team.html')) renderTeamPage();
    else if (path.includes('payout_calc.html')) preparePayoutCalc();
}

function saveData() {
    localStorage.setItem('polyData', JSON.stringify(appData));
}

// ================= Nav & Auth UI =================
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
                    ? `<span class="user-greeting">שלום, ${currentUser.fullName}</span><button class="auth-btn" onclick="logout()">התנתק</button>` 
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

window.toggleMenu = function() {
    document.getElementById("hamburgerMenu").classList.toggle("show");
}

window.changeStatusFilter = function(status) {
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

window.handleSearchInput = function() {
    if (window.location.pathname.includes('markets.html')) {
        renderMarkets();
    }
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

window.toggleAuthMode = function() {
    authMode = authMode === 'login' ? 'register' : 'login';
    document.getElementById('authTitle').innerText = authMode === 'login' ? 'התחברות למערכת' : 'הרשמה למערכת';
    document.getElementById('registerFields').classList.toggle('hidden', authMode === 'login');
    document.getElementById('authToggleText').innerText = authMode === 'login' ? 'משתמש חדש? לחץ כאן להרשמה' : 'משתמש רשום? לחץ כאן להתחברות';
}

window.handleAuth = function(e) {
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
        saveData();
        window.location.reload();
    }
}

window.logout = function() {
    localStorage.removeItem('currentUser');
    window.location.reload();
}

// ================= Footer UI =================
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
                <a href="team.html">צוות האתר</a>
                <a href="payout_calc.html">מחשבון זכיות</a>
                <a href="contact.html">צור קשר</a>
            </div>
            <div class="footer-input">
                <h4>השאר לנו הודעה / הרשם לעדכונים</h4>
                <input type="text" placeholder="כתוב משהו..." id="footerInput">
                <button class="auth-btn" onclick="alert('תודה! קלטנו את ההודעה.')">שלח</button>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 PolyFriends. כל הזכויות שמורות.</p>
        </div>
    `;
}

// ================= Pages Logic =================

// 1. Markets (Open Bets)
window.renderMarkets = function() {
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
        
        let hasJoined = currentUser && bet.participants[currentUser.username];
        
        let buttonsHtml = '';
        if (bet.status === 'closed') {
            const winText = bet.winningOption ? `המנצח: ${bet.winningOption}` : 'נסגר ללא הכרעה';
            buttonsHtml = `<div class="closed-bet-notice">התערבות סגורה.<br>${winText}</div>`;
        } else {
            if (currentUser) {
                buttonsHtml = bet.options.map(opt => {
                    const isSelected = hasJoined === opt;
                    const btnClass = isSelected ? 'btn-selected' : 'btn-unselected';
                    return `<button class="auth-btn bet-option-btn ${btnClass}" onclick="joinBet('${bet.id}', '${opt}')">${opt}</button>`;
                }).join('');
                buttonsHtml = `<div class="bet-options-wrapper">${buttonsHtml}</div>`;
            } else {
                buttonsHtml = `<p class="closed-bet-notice">התחבר כדי להשתתף</p>`;
            }
        }
        
        let potAmount = Object.keys(bet.participants).length * bet.amount;
        
        html += `
        <div class="bet-card">
            <div>
                <h3>${bet.title}</h3>
                <h4>${bet.category}</h4>
                <p>סכום השתתפות: ₪${bet.amount}</p>
            </div>
            <div>
                <h4>קופה נוכחית: ₪${potAmount}</h4>
                ${buttonsHtml}
            </div>
        </div>`;
    });
    container.innerHTML = html || '<p style="text-align:center; width:100%;">לא נמצאו התערבויות העונות לסינון.</p>';
}

window.joinBet = function(betId, option) {
    let bet = appData.bets.find(b => b.id === betId);
    bet.participants[currentUser.username] = option;
    saveData();
    renderMarkets();
}

// 2. Manage Bets
window.renderManageBets = function() {
    const catSelect = document.getElementById('categorySelect');
    const closedBetsContainer = document.getElementById('my-open-bets');
    if(!catSelect || !closedBetsContainer) return;
    
    catSelect.innerHTML = '<option value="" disabled selected>בחר קטגוריה</option>' + 
                          appData.categories.map(c => `<option value="${c}">${c}</option>`).join('') +
                          '<option value="other">אחר (הזן טקסט)...</option>';
                          
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
}

window.checkCustomCat = function() {
    document.getElementById('customCategoryWrapper').style.display = document.getElementById('categorySelect').value === 'other' ? 'block' : 'none';
}

window.createBet = function(e) {
    e.preventDefault();
    if(!currentUser) return alert('יש להתחבר!');
    let title = document.getElementById('betTitle').value;
    let amount = parseInt(document.getElementById('betAmount').value);
    let cat = document.getElementById('categorySelect').value;
    if(cat === 'other') {
        cat = document.getElementById('customCategory').value.trim();
        if(cat && !appData.categories.includes(cat)) appData.categories.push(cat);
    }
    
    let opt1 = document.getElementById('betOption1').value.trim();
    let opt2 = document.getElementById('betOption2').value.trim();
    let opts = [opt1, opt2].filter(o => o);
    
    let newBet = {
        id: 'bet_' + Date.now(), title, amount, category: cat, creator: currentUser.username, status: 'open',
        options: opts, participants: {}, winningOption: null
    };
    appData.bets.push(newBet);
    saveData();
    alert('התערבות נוצרה בהצלחה!');
    e.target.reset();
    initApp();
}

window.closeBet = function(betId) {
    let bet = appData.bets.find(b => b.id === betId);
    let winningOption = prompt(`מי ניצח בהתערבות "${bet.title}"?\nהקלד אחת מהאפשרויות: ${bet.options.join(' / ')}`);
    if(!winningOption || !bet.options.includes(winningOption)) return alert('בחירה לא תקינה או בוטלה.');
    
    bet.status = 'closed';
    bet.winningOption = winningOption;
    saveData();
    renderManageBets();
    alert('התערבות נסגרה בהצלחה! המאזנים התעדכנו אוטומטית.');
}

// 3. Ledger (Participants)
window.renderLedger = function() {
    const container = document.getElementById('ledger-container');
    if (!container) return;
    
    // חישוב אוטומטי של המאזנים מתוך היסטוריית ההתערבויות שנסגרו
    appData.users.forEach(u => {
        u.balance = 0;
        u.debts = {}; // מאזן פנימי מול כל משתמש
    });
    appData.bets.forEach(bet => {
        if(bet.status === 'closed' && bet.winningOption) {
            let winners = [];
            let losers = [];
            for(let [uname, opt] of Object.entries(bet.participants)) {
                // שומרים גם את סכום ההימור למקרה של סכומים שונים למשתמשים
                if(opt === bet.winningOption) winners.push({ username: uname, amount: bet.amount });
                else losers.push({ username: uname, amount: bet.amount });
            }
            
            // 4. מקרי קצה (Void) - אם כולם צדקו או כולם טעו, ההתערבות מבוטלת
            if (winners.length === 0 || losers.length === 0) {
                return;
            }

            // 1. קופת מפסידים - סכום כל ההימורים של מי שטעה
            let losersPot = losers.reduce((sum, l) => sum + l.amount, 0);
            
            // סך כל סכומי ההימור של המנצחים (לצורך חישוב יחסי)
            let totalWinnersAmount = winners.reduce((sum, w) => sum + w.amount, 0);

            // 3. חלוקת רווחים למנצחים (באופן יחסי לסכום שסיכנו)
            winners.forEach(w => {
                let u = appData.users.find(x => x.username === w.username);
                if (u) {
                    let winShare = (w.amount / totalWinnersAmount) * losersPot;
                    u.balance += Math.round(winShare * 100) / 100;
                }
            });

            // 2. עדכון חובות המפסידים (מפחיתים את סכום ההימור מהמאזן)
            losers.forEach(l => {
                let u = appData.users.find(x => x.username === l.username);
                if (u) u.balance -= l.amount;
            });

            // עדכון חובות פרטניים (מי חייב למי) בהתאמה לחלוקה היחסית
            winners.forEach(w => {
                let wUser = appData.users.find(x => x.username === w.username);
                losers.forEach(l => {
                    let lUser = appData.users.find(x => x.username === l.username);
                    let amountOwed = l.amount * (w.amount / totalWinnersAmount);
                    amountOwed = Math.round(amountOwed * 100) / 100;

                    if (wUser) wUser.debts[l.username] = (wUser.debts[l.username] || 0) + amountOwed;
                    if (lUser) lUser.debts[w.username] = (lUser.debts[w.username] || 0) - amountOwed;
                });
            });
        }
    });
    
    // החלת דיווחי תשלומים (החזרי חובות) מהיסטוריית המערכת
    if (!appData.settlements) appData.settlements = [];
    appData.settlements.forEach(s => {
        let debtor = appData.users.find(u => u.username === s.from);
        let creditor = appData.users.find(u => u.username === s.to);
        
        if (debtor) {
            debtor.balance += s.amount;
            debtor.debts[s.to] = (debtor.debts[s.to] || 0) + s.amount;
        }
        if (creditor) {
            creditor.balance -= s.amount;
            creditor.debts[s.from] = (creditor.debts[s.from] || 0) - s.amount;
        }
    });

    saveData(); // Save computed balances
    
    container.innerHTML = appData.users.map(user => {
        let displayBalance = Math.round(user.balance * 100) / 100;
        let bClass = displayBalance > 0 ? 'balance-positive' : (displayBalance < 0 ? 'balance-negative' : 'balance-zero');
        let sign = displayBalance > 0 ? '+' : '';

        let debtsHtml = '';
        if (user.debts && Object.keys(user.debts).length > 0) {
            let debtRows = '';
            for (let [otherUser, amount] of Object.entries(user.debts)) {
                if (Math.round(amount * 100) / 100 !== 0) {
                    let otherUserObj = appData.users.find(u => u.username === otherUser);
                    let otherName = otherUserObj ? otherUserObj.fullName : otherUser;
                    let displayAmount = Math.round(amount * 100) / 100;
                    let color = displayAmount > 0 ? '#2ecc71' : '#e74c3c';
                    let amountSign = displayAmount > 0 ? '+' : '';
                    
                    let settleBtn = '';
                    // רק המשתמש שמגיע לו הכסף (מאזן חיובי) יכול לדווח שהחזירו לו אותו
                    if (currentUser && user.username === currentUser.username && displayAmount > 0) {
                        settleBtn = `<button onclick="settleDebt('${otherUser}', ${displayAmount})" style="font-size:0.75em; padding:3px 8px; margin-right:8px; background:#2196F3; color:white; border:none; border-radius:4px; cursor:pointer;">קבלת תשלום</button>`;
                    }
                    debtRows += `
                        <div class="debt-row">
                            <div class="debt-user"><span>${otherName}</span>${settleBtn}</div>
                            <span style="color: ${color}; font-weight: bold; direction: ltr;">${amountSign}${displayAmount} ₪</span>
                        </div>`;
                }
            }
            if (debtRows) {
                debtsHtml = `
                    <div class="debts-wrapper">
                        <h4>מאזן מול משתתפים:</h4>
                        ${debtRows}
                    </div>`;
            }
        }
        
        return `
            <div>
                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.fullName)}&background=random&color=fff">
                <h3>${user.fullName}</h3>
                <h4>@${user.username}</h4>
                <div class="balance-badge ${bClass}">סך הכל: ${sign}${displayBalance} ₪</div>
                ${debtsHtml}
            </div>`;
    }).join('');
}

window.settleDebt = function(debtorUsername, maxAmount) {
    if (!currentUser) return;
    
    let debtorObj = appData.users.find(u => u.username === debtorUsername);
    let debtorName = debtorObj ? debtorObj.fullName : debtorUsername;
    
    let amountStr = prompt(`כמה כסף ${debtorName} החזיר לך?\n(הזן סכום עד ${maxAmount} ₪)`, maxAmount);
    if (!amountStr) return; // המשתמש ביטל את הפעולה
    
    let amount = parseFloat(amountStr);
    if (isNaN(amount) || amount <= 0) {
        return alert('אנא הזן סכום תקין וגדול מאפס.');
    }
    if (amount > maxAmount) {
        return alert('לא ניתן להזין סכום שגדול מהחוב הקיים.');
    }
    
    if (!appData.settlements) appData.settlements = [];
    appData.settlements.push({
        from: debtorUsername,
        to: currentUser.username,
        amount: amount,
        timestamp: Date.now()
    });
    
    saveData();
    renderLedger(); // רינדור מחדש להצגת המאזנים המעודכנים
    alert('התשלום דווח והמאזנים עודכנו בהצלחה!');
}

// 4. Contact / Admins
window.renderContact = function() {
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

// 6. Team Page
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

// 7. Payout Calculator
function preparePayoutCalc() {
    // This function is mostly a placeholder in case of future setup needs.
    // The actual calculation is bound to the button's onclick event.
}

window.calculatePayout = function() {
    let poolAmount = parseFloat(document.getElementById('pool_amount').value);
    if (isNaN(poolAmount)) return;

    const netAmount = poolAmount * 0.9; // Simplified calculation
    
    document.getElementById('calcResult').innerText = `סכום הזכייה נטו לאחר עמלות הוא: ${netAmount.toFixed(2)} ₪`;
}