// 切換登入與註冊表單
function switchTab(tabName) {
    document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    if (tabName === 'login') {
        document.getElementById('loginForm').classList.add('active');
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
    } else {
        document.getElementById('registerForm').classList.add('active');
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
    }
    // 切換時清空提示訊息
    document.getElementById('login_error').innerText = "";
    document.getElementById('login_error').className = "error-msg";
    document.getElementById('reg_error').innerText = "";
    document.getElementById('reg_error').className = "error-msg";
}

function isValidUsername(username) {
    return /^[A-Za-z0-9_-]{4,20}$/.test(username);
}

function isValidPassword(password) {
    return password.length >= 8;
}

// 處理註冊邏輯
async function handleRegister(e) {
    e.preventDefault();
    const username = document.getElementById('reg_username').value.trim();
    const pwd = document.getElementById('reg_password').value;
    const confirmPwd = document.getElementById('reg_confirm_password').value;
    const errorMsg = document.getElementById('reg_error');

    if (username === '' || pwd === '' || confirmPwd === '') {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 帳號和密碼欄位不得為空。";
        return;
    }

    if (!isValidUsername(username)) {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 帳號格式錯誤：請輸入 4-20 字元，僅允許英文、數字、底線或連字號。";
        document.getElementById('reg_username').focus();
        return;
    }

    if (!isValidPassword(pwd)) {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 密碼長度至少 8 個字元。";
        document.getElementById('reg_password').focus();
        return;
    }

    // 前端防呆：密碼雙重確認
    if (pwd !== confirmPwd) {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ [錯誤] 兩次輸入的密碼不一致，請重新確認！";
        document.getElementById('reg_confirm_password').focus();
        return;
    }

    errorMsg.className = "error-msg text-success";
    errorMsg.innerText = "⏳ 驗證通過，連線至伺服器註冊中...";
    
    try {
        // 透過 fetch API 發送 POST 請求到後端
        const response = await fetch('api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: pwd,
                confirm_password: confirmPwd
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            errorMsg.className = "error-msg text-success";
            errorMsg.innerText = "✅ " + result.message;
            // 註冊成功後，自動清空表單並切換到登入分頁
            document.getElementById('registerForm').reset();
            setTimeout(() => {
                switchTab('login');
                document.getElementById('login_username').value = username; // 幫使用者自動填入剛註冊的帳號
            }, 1500);
        } else {
            // 顯示後端傳來的錯誤訊息 (例如帳號已存在)
            errorMsg.className = "error-msg text-danger";
            errorMsg.innerText = "❌ " + result.message;
        }

    } catch (error) {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 網路連線錯誤，請確認伺服器狀態！";
        console.error("Registration Error:", error);
    }
}

// 處理登入邏輯
// 處理登入邏輯
async function handleLogin(e) {
    e.preventDefault();
    const username = document.getElementById('login_username').value.trim();
    const pwd = document.getElementById('login_password').value;
    const errorMsg = document.getElementById('login_error');

    if (username === '' || pwd === '') {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 帳號和密碼不得為空。";
        return;
    }

    if (!isValidUsername(username)) {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 帳號格式錯誤：請輸入 4-20 字元，僅允許英文、數字、底線或連字號。";
        document.getElementById('login_username').focus();
        return;
    }

    errorMsg.className = "error-msg text-success";
    errorMsg.innerText = "🔄 驗證憑證中...";

    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                username: username,
                password: pwd
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            errorMsg.className = "error-msg text-success";
            errorMsg.innerText = "✅ " + result.message;
            
            // 延遲 1 秒後，根據後端指示的網址跳轉
            setTimeout(() => {
                window.location.href = result.redirect;
            }, 1000);
        } else {
            // 登入失敗顯示紅字
            errorMsg.className = "error-msg text-danger";
            errorMsg.innerText = "❌ " + result.message;
            // 清空密碼欄位讓使用者重打
            document.getElementById('login_password').value = "";
            document.getElementById('login_password').focus();
        }
    } catch (error) {
        errorMsg.className = "error-msg text-danger";
        errorMsg.innerText = "❌ 網路連線錯誤，請確認伺服器狀態！";
        console.error("Login Error:", error);
    }
}