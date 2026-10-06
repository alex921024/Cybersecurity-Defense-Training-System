import GameManager from './gameManager.js';

window.gameManagerInstance = new GameManager();
window.selectedDifficulty = 0;
window.difficultyCatalog = {};
window.difficultyCatalogLoaded = false;
window.difficultyCatalogPromise = null;

window.switchTab = (evt, tabId) => {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    if (evt) evt.currentTarget.classList.add('active');
    else document.querySelector(`.tab-btn[data-tab="${tabId}"]`)?.classList.add('active');

    if (tabId === 'tab-status' && window.gameManagerInstance?.charts) {
        Object.values(window.gameManagerInstance.charts).forEach(chart => { if (chart) chart.resize(); });
    }
    const radarPanel = document.getElementById('threat-radar-panel');
    if (radarPanel) radarPanel.style.display = tabId === 'tab-firewall' ? 'flex' : 'none';
};

window.loadDifficultyOptions = async () => {
    const container = document.getElementById('difficulty-options');
    if (!container) return;

    try {
        if (!window.difficultyCatalogLoaded) {
            container.textContent = '正在載入可用難度...';
            if (!window.difficultyCatalogPromise) {
                window.difficultyCatalogPromise = (async () => {
                    const response = await fetch('api/student/get_game_data.php?catalog=1', { cache: 'no-store' });
                    const result = await response.json();
                    if (!response.ok || result.status !== 'success') throw new Error(result.message || '無法載入難度');
                    window.difficultyCatalog = result.difficulties || {};
                    window.difficultyCatalogLoaded = true;
                })().catch(error => {
                    window.difficultyCatalogPromise = null;
                    throw error;
                });
            }
            await window.difficultyCatalogPromise;
        }
        container.replaceChildren();
        Object.entries(window.difficultyCatalog).forEach(([id, config]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.addEventListener('click', () => window.prepareGame(Number(id)));
            button.textContent = `${config.name || `難度 ${id}`} - ${config.time} 秒`;
            if (config.description) button.title = config.description;
            container.appendChild(button);
        });

        if (container.children.length === 0) {
            const message = document.createElement('p');
            message.textContent = '目前沒有可用的訓練難度。';
            container.appendChild(message);
        }

        const requestedDifficulty = new URLSearchParams(window.location.search).get('difficulty');
        if (requestedDifficulty !== null && window.difficultyCatalog[requestedDifficulty]) {
            window.prepareGame(Number(requestedDifficulty));
        }
    } catch (error) {
        container.textContent = '難度載入失敗，請重新整理頁面。';
        console.error('載入難度失敗:', error);
    }
};

window.toggleDifficultySelect = async () => {
    document.getElementById('entry-content').classList.toggle('hidden');
    document.getElementById('difficulty-content').classList.toggle('hidden');
    if (!document.getElementById('difficulty-content').classList.contains('hidden')) {
        await window.loadDifficultyOptions();
    }
};

window.breakmenu = () => {
    document.getElementById('game-screen').classList.remove('active');
    document.getElementById('menu-screen').classList.add('active');
    document.getElementById('entry-content').classList.remove('hidden');
    document.getElementById('difficulty-content').classList.add('hidden');
};

window.breaktoDifficulty = () => {
    document.getElementById('tutorial-modal').classList.add('hidden');
    document.getElementById('game-screen').classList.remove('active');
    document.getElementById('menu-screen').classList.add('active');
    document.getElementById('entry-content').classList.add('hidden');
    document.getElementById('difficulty-content').classList.remove('hidden');
};

window.prepareGame = diff => {
    window.selectedDifficulty = diff;
    window.gameManagerInstance.previewDifficultyConfig = window.difficultyCatalog[diff] || null;
    document.getElementById('game-password-initial').value = '';
    document.getElementById('game-password-initial-confirm').value = '';
    updateInitialPasswordStrength();
    document.getElementById('tutorial-modal').classList.remove('hidden');
};

window.confirmStart = async () => {
    const password = document.getElementById('game-password-initial').value;
    const confirmation = document.getElementById('game-password-initial-confirm').value;
    const validation = window.validateGamePassword(password, confirmation);
    if (!validation.valid) {
        updateInitialPasswordStrength(validation.message);
        return;
    }
    window.pendingGamePassword = password;
    const started = await window.gameManagerInstance.init(window.selectedDifficulty);
    if (!started) return;
    document.getElementById('tutorial-modal').classList.add('hidden');
    window.switchTab(null, 'tab-firewall');
};

window.validateGamePassword = (password, confirmation) =>
    window.gameManagerInstance.validateGamePassword(password, confirmation);

function updateInitialPasswordStrength(message = '') {
    const password = document.getElementById('game-password-initial').value;
    const confirmation = document.getElementById('game-password-initial-confirm').value;
    const result = password ? window.gameManagerInstance.getPasswordProfile(password) : null;
    const strength = document.getElementById('game-password-initial-strength');
    const button = document.getElementById('confirm-start-button');
    const mismatch = document.getElementById('game-password-initial-mismatch');
    mismatch.textContent = confirmation && password !== confirmation ? '密碼不一致，請重新確認。' : '';
    if (!result) {
        strength.textContent = message || '開始前必須設定密碼';
        button.disabled = true;
        return;
    }
    strength.textContent = message || `密碼強度：${result.label}（${result.strength}/100）`;
    button.disabled = !window.validateGamePassword(password, confirmation).valid;
}

window.togglePasswordVisibility = (inputId, button) => {
    const input = document.getElementById(inputId);
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    button.textContent = showing ? '顯示' : '隱藏';
    button.setAttribute('aria-pressed', String(!showing));
};

function updateChangePasswordStrength() {
    const password = document.getElementById('game-password-change').value;
    const profile = password ? window.gameManagerInstance.getPasswordProfile(password) : null;
    document.getElementById('game-password-change-strength').textContent = profile
        ? `密碼強度：${profile.label}（${profile.strength}/100）`
        : '尚未輸入新密碼';
}

window.changeGamePassword = () => {
    const password = document.getElementById('game-password-change').value;
    const confirmation = document.getElementById('game-password-change-confirm').value;
    const validation = window.validateGamePassword(password, confirmation);
    const result = document.getElementById('game-password-change-strength');
    if (!validation.valid) {
        result.textContent = validation.message;
        return;
    }
    const changed = window.gameManagerInstance.changeGamePassword(password);
    result.textContent = changed ? '遊戲密碼已更新，破解進度已重置。' : '目前難度不允許更換密碼，或仍在冷卻時間內。';
    if (changed) {
        document.getElementById('game-password-change').value = '';
        document.getElementById('game-password-change-confirm').value = '';
    }
};

document.addEventListener('input', event => {
    if (event.target.id === 'game-password-initial' || event.target.id === 'game-password-initial-confirm') updateInitialPasswordStrength();
    if (event.target.id === 'game-password-change') updateChangePasswordStrength();
});

window.quickCmd = cmd => {
    const input = document.getElementById('cmd-input');
    input.value = cmd;
    input.focus();
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
};

window.guiMitigate = type => {
    if (window.gameManagerInstance) window.gameManagerInstance.guiMitigate(type);
};

window.analyzeGUI = () => {
    const input = document.getElementById('analyzer-input').value;
    if (window.gameManagerInstance) window.gameManagerInstance.analyzeThreat(input);
};

window.quitGame = () => {
    if (confirm('確定放棄？')) location.reload();
};
window.viewMail = id => window.gameManagerInstance.viewMail(id);
window.handleMail = (id, action) => window.gameManagerInstance.handleMail(id, action);

window.quickAnalyze = ip => {
    const input = document.getElementById('cmd-input');
    input.value = `whois ${ip}`;
    input.focus();
    if (window.gameManagerInstance) {
        window.gameManagerInstance.showNotification(`已選定目標 ${ip}，請按 Enter 執行分析。`, 'success');
    }
};

document.addEventListener('click', event => {
    const target = event.target.closest('[data-action], [data-tab], [data-command], [data-mitigate], [data-toggle-password], [data-nav]');
    if (!target) return;

    if (target.dataset.nav) location.href = target.dataset.nav;
    else if (target.dataset.tab) window.switchTab(event, target.dataset.tab);
    else if (target.dataset.command) window.quickCmd(target.dataset.command);
    else if (target.dataset.mitigate) window.guiMitigate(target.dataset.mitigate);
    else if (target.dataset.togglePassword) window.togglePasswordVisibility(target.dataset.togglePassword, target);
    else {
        const actions = {
            'toggle-difficulty': window.toggleDifficultySelect,
            'break-menu': window.breakmenu,
            'quit-game': window.quitGame,
            'toggle-packet-capture': () => window.togglePacketCapture(),
            analyze: window.analyzeGUI,
            'change-password': window.changeGamePassword,
            'confirm-start': window.confirmStart,
            'back-to-difficulty': window.breaktoDifficulty,
            reload: () => location.reload()
        };
        actions[target.dataset.action]?.();
    }
});
