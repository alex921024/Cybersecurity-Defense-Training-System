<?php
require_once __DIR__ . '/api/core/common.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: assets/html/login.html');
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資安防禦訓練系統 - 終端監控中心</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="menu-screen" class="screen active">
        <h1>資安防禦訓練系統</h1>
        <div id="entry-content" class="menu-options">
            <button onclick="toggleDifficultySelect()">開 始</button>
            <button onclick="location.href='teaching/tutorial.php'">教 學</button>
            <button onclick="location.href='assets/html/student_dashboard.html'">返回控制台</button>
            <?php if (($_SESSION['role'] ?? '') === 'teacher'): ?>
                <button onclick="location.href='assets/html/dashboard.html#difficulties'">教師難度</button>
            <?php endif; ?>
        </div>
        <div id="difficulty-content" class="menu-options hidden">
            <h2 style="color: #00FF00; text-align: center; margin-bottom: 20px;">請選擇訓練等級</h2>
            <div id="difficulty-options"><p>正在載入可用難度...</p></div>
            <button class="break-btn" onclick="breakmenu()">返回</button>
        </div>
    </div>

    <div id="game-screen" class="screen">
        <aside id="game-sidebar">
            <div class="sidebar-header">防禦中心</div>
            <nav class="tab-menu">
                <button class="tab-btn active" onclick="switchTab(event, 'tab-firewall')"><i class="fas fa-shield-alt"></i> 監控與控制台</button>
                <button class="tab-btn" onclick="switchTab(event, 'tab-status')"><i class="fas fa-chart-line"></i> 系統狀態</button>
                <button class="tab-btn" onclick="switchTab(event, 'tab-mailbox')"><i class="fas fa-inbox"></i> 收件匣 <span id="unread-count" style="color:red; font-weight:bold;">0</span></button>
                <button class="tab-btn" onclick="switchTab(event, 'tab-manual')"><i class="fas fa-book"></i> 指令手冊</button>
            </nav>
            <button class="quit-btn" onclick="quitGame()">放棄任務</button>
        </aside>

        <main id="tab-content-container">
            <div id="tab-firewall" class="tab-content active">
                <h2 class="tab-title" style="margin: 0 0 10px 0; font-size: 1.2em;"><i class="fas fa-shield-alt"></i> SOC 即時防禦儀表板 (Monitoring & Terminal)</h2>
                <div id="mission-panel" class="mission-panel">
                    <div class="mission-head">
                        <div>
                            <div class="mission-label">任務階段</div>
                            <div id="mission-phase" class="mission-phase">待命中</div>
                        </div>
                        <div>
                            <div class="mission-label">當前目標</div>
                            <div id="mission-objective" class="mission-objective">啟動監控，等待異常流量或釣魚郵件。</div>
                        </div>
                    </div>
                    <div class="mission-tip">提示：<span id="mission-tip">保持系統穩定，使用 status 與 netstat 了解當前狀態。</span></div>
                    <div class="mission-steps">
                        <span id="mission-step-1" class="mission-step active-step">1. 偵測異常流量</span>
                        <span id="mission-step-2" class="mission-step">2. 分析可疑來源</span>
                        <span id="mission-step-3" class="mission-step">3. 精準封鎖並恢復</span>
                    </div>
                </div>
                <div class="firewall-layout">
                    <div class="firewall-left">
                        <section id="analysis-section">
                            <div class="analysis-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="margin:0;">即時流量監控 (Wireshark)</h3>
                                <div>
                                    <button id="btn-pause-packet" class="cmd-tag" style="background:#ff9900; color:#000; font-weight:bold; padding:6px 12px; margin-right: 10px;" onclick="togglePacketCapture()">暫停擷取</button>
                                    <select id="protocol-filter">
                                        <option value="ALL">全部 (ALL)</option>
                                        <option value="TCP">TCP</option>
                                        <option value="UDP">UDP</option>
                                        <option value="ICMP">ICMP</option>
                                        <option value="DNS">DNS</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="scroll-box" style="flex: 2; border-bottom: 2px solid #444;">
                                <table id="packet-table">
                                    <thead><tr><th>時間</th><th>來源 IP</th><th>來源 Port</th><th>目標 IP</th><th>目標 Port</th><th>協定</th><th>長度</th><th>狀態</th></tr></thead>
                                    <tbody id="packet-list"></tbody>
                                </table>
                            </div>

                            <div id="packet-details-panel" style="flex: 1.2; background: #000; padding: 10px; font-family: monospace; font-size: 13px; color: #aaa; overflow-y: auto;">
                                <h4 style="margin: 0 0 5px 0; color: #fff;">封包 Payload 檢視區 (點擊上方列表查看)</h4>
                                <div id="packet-payload-content" style="white-space: pre-wrap; margin: 0; color: #55ff55;">等待選擇封包...</div>
                            </div>
                        </section>
                        
                        <section id="terminal-section">
                            <h3 style="margin-top: 0; margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">指令控制台 (root@sec-server:~#)</h3>
                            <div id="terminal-output"></div>
                            <div class="input-line">
                                <span>></span>
                                <input type="text" id="cmd-input" placeholder="輸入防禦指令..." autocomplete="off">
                            </div>
                            <div class="quick-commands">
                                <span class="cmd-tag" onclick="quickCmd('help')">help</span>
                                <span class="cmd-tag" onclick="quickCmd('status')">status</span>
                                <span class="cmd-tag" onclick="quickCmd('ipconfig')">ipconfig</span>
                                <span class="cmd-tag" onclick="quickCmd('ping 10.0.0.1')">ping</span>
                                <span class="cmd-tag" onclick="quickCmd('netstat')">netstat</span>
                                <span class="cmd-tag" onclick="quickCmd('whois udp')">whois udp</span>
                                <span class="cmd-tag" onclick="quickCmd('limit udp')">limit udp</span>
                                <span class="cmd-tag" onclick="quickCmd('block ip')">block ip</span>
                                <span class="cmd-tag" onclick="quickCmd('unblock udp')">unblock</span>
                                <span class="cmd-tag" onclick="quickCmd('scan-mail')">scan-mail</span>
                                <span class="cmd-tag" onclick="quickCmd('flush-dns')">flush-dns</span>
                                <span class="cmd-tag" onclick="quickCmd('passwd')">passwd</span>
                                <span class="cmd-tag" onclick="quickCmd('clear')">clear</span>
                            </div>
                        </section>
                    </div>
                    
                    <div class="firewall-right">
                        <div class="firewall-card alert-card">
                            <h3><i class="fas fa-bell"></i> IDS 即時警報日誌</h3>
                            <div id="ids-alert-log" class="log-box">系統監控中，目前無異常活動...</div>
                        </div>
                        
                        <div class="firewall-card analysis-card">
                            <h3><i class="fas fa-search"></i> 威脅情報分析儀 (SOP 認證)</h3>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input type="text" id="analyzer-input" placeholder="輸入可疑 IP 或 協定 (如 udp)..." 
                                       style="flex: 1; background: #111; border: 1px solid #333; color: #00FF00; padding: 8px; font-family: monospace;">
                                <button class="manual-btn" style="margin: 0; padding: 8px 15px; background: #1a73e8; color: #fff;" onclick="analyzeGUI()">分析 (Whois)</button>
                            </div>
                            <div id="analyzer-result" class="log-box" style="height: 60px; color: #00ebff;">
                                等待輸入分析目標...
                            </div>
                        </div>

                        <div class="firewall-card intel-card">
                            <h3><i class="fas fa-bug"></i> 已偵測惡意 IP (Threat Intel)</h3>
                            <div id="threat-intel-list" class="log-box" style="height: 120px; overflow-y: auto; display: block; padding: 10px;">
                                <span style="color:#666;">尚未發現已知威脅 IP...</span>
                            </div>
                        </div>

                        <div class="firewall-card control-card">
                            <h3><i class="fas fa-sliders-h"></i> 策略快捷防禦開關 (需先分析)</h3>
                            <div class="policy-grid">
                                <button class="policy-btn" id="btn-mitigate-udp" onclick="guiMitigate('udp')">阻斷 UDP 流量</button>
                                <button class="policy-btn" id="btn-mitigate-icmp" onclick="guiMitigate('icmp')">阻斷 ICMP 流量</button>
                                <button class="policy-btn" id="btn-mitigate-dns" onclick="guiMitigate('dns')">清除 DNS 快取</button>
                                <button class="policy-btn" id="btn-mitigate-ip" onclick="guiMitigate('ip')">封鎖惡意來源 IP</button>
                            </div>
                        </div>
                        
                        <div class="firewall-card rules-card">
                            <h3><i class="fas fa-list"></i> 當前防火牆規則 (ACL)</h3>
                            <div class="scroll-box" style="background:#111; height: 120px;">
                                <table class="manual-table" style="margin:0; font-size:13px;">
                                    <thead>
                                        <tr><th>規則 ID</th><th>標的</th><th>動作</th><th>狀態</th></tr>
                                    </thead>
                                    <tbody id="acl-rules-body">
                                        <tr><td colspan="4" style="text-align:center; color:#666;">尚無自訂過濾規則</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="tab-status" class="tab-content">
                <h2 class="tab-title"><i class="fas fa-desktop"></i> 系統硬體即時監控中心</h2>
                <div class="time-banner">剩餘時間: <span id="timer">--</span></div>
                <section class="password-panel" aria-labelledby="password-panel-title">
                    <div>
                        <h3 id="password-panel-title">系統密碼防護</h3>
                        <p>遊戲密碼只用於本局字典破解模擬，不會修改登入密碼。</p>
                    </div>
                    <div class="password-form-grid">
                        <label>新遊戲密碼<input id="game-password-change" type="password" minlength="8" autocomplete="new-password" placeholder="至少 8 碼"></label>
                        <label>確認新密碼<input id="game-password-change-confirm" type="password" minlength="8" autocomplete="new-password" placeholder="再次輸入密碼"></label>
                        <button type="button" class="manual-btn" onclick="changeGamePassword()">更換遊戲密碼</button>
                    </div>
                    <div id="game-password-change-strength" class="password-strength" aria-live="polite">尚未輸入新密碼</div>
                </section>
                <div class="monitor-grid">
                    <div class="chart-box">
                        <div class="chart-header"><span>CPU 使用率</span><span class="live-indicator"></span></div>
                        <div class="chart-body"><canvas id="cpuChart"></canvas></div>
                        <div class="chart-footer">負載: <span id="cpu-load">0</span>%</div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-header"><span>GPU 使用率</span><span class="live-indicator"></span></div>
                        <div class="chart-body"><canvas id="gpuChart"></canvas></div>
                        <div class="chart-footer">負載: <span id="gpu-load">0</span>%</div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-header"><span>RAM 使用率</span><span class="live-indicator"></span></div>
                        <div class="chart-body"><canvas id="ramChart"></canvas></div>
                        <div class="chart-footer">負載: <span id="ram-load">0</span>%</div>
                    </div>
                    <div class="chart-box danger-box">
                        <div class="chart-header"><span style="color: #FF3333;">字典破解進度</span><span class="live-indicator danger-pulse"></span></div>
                        <div class="chart-body"><canvas id="hackChart"></canvas></div>
                        <div class="chart-footer" style="color: #FF3333;">進度: <span id="crack-progress">0</span>%<span id="dictionary-crack-stage" style="display:block; color:#ffaaa5; font-size:0.8em;">等待開始</span></div>
                    </div>
                </div>
            </div>

            <div id="tab-mailbox" class="tab-content">
                <h2 class="tab-title">企業收件匣</h2>
                <div class="mail-layout">
                    <div class="mail-list" id="mail-list-container"></div>
                    <div class="mail-viewer" id="mail-viewer-container">
                        <p style="text-align:center; padding-top:50px;">請選擇左側信件閱讀</p>
                    </div>
                </div>
            </div>

            <div id="tab-manual" class="tab-content">
                <h2 class="tab-title">系統指令參考手冊 (Cheat Sheet)</h2>
                <div class="scroll-box">
                    <table class="manual-table">
                        <thead><tr><th>指令</th><th>功能說明</th></tr></thead>
                        <tbody>
                            <tr><td><code>help</code></td><td>顯示目前難度可用的系統指令。</td></tr>
                            <tr><td><code>status</code></td><td>查看系統詳細負載狀態與密碼破解進度。</td></tr>
                            <tr><td><code>ipconfig</code></td><td>查詢目前模擬主機的網路設定。</td></tr>
                            <tr><td><code>ping [IP]</code></td><td>測試指定 IP 的模擬連線。</td></tr>
                            <tr><td><code>netstat</code></td><td>顯示當前網路連線，若有 DDoS 或異常流量會跳出警告。</td></tr>
                            <tr><td><code style="color: #00ebff;">whois [IP/協定]</code></td><td>(重要) 分析可疑目標，取得授權。範例：<code>whois udp</code></td></tr>
                            <tr><td><code>limit [協定]</code></td><td>暫時限速緩解攻擊傷害，爭取時間尋找來源。</td></tr>
                            <tr><td><code>block [IP/協定]</code></td><td>設定防火牆。需先完成分析才可封鎖。注意副作用。</td></tr>
                            <tr><td><code>unblock [IP/協定]</code></td><td>解除封鎖設定，恢復正常服務運作。</td></tr>
                            <tr><td><code>flush-dns</code></td><td>清除 DNS 快取。遭受污染時使用。</td></tr>
                            <tr><td><code>scan-mail</code></td><td>自動掃描並隔離惡意釣魚信件。</td></tr>
                            <tr><td><code>passwd</code></td><td>強制重置系統密碼，將破解進度歸零。</td></tr>
                            <tr><td><code>clear</code></td><td>清空終端機輸出畫面。</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="tutorial-modal" class="modal hidden">
        <div class="modal-content" style="border: 2px solid #FFA500;">
            <h2 style="color: #FFA500;"><i class="fas fa-shield-alt"></i> 任務簡報與密碼設定</h2>
            <p>確保系統不崩潰。防禦 SOP：發現異常 ➡️ Whois 分析 ➡️ 部署規則封鎖。</p>
            <div class="password-setup-box">
                <label>設定本局遊戲密碼
                    <span class="password-input-row"><input id="game-password-initial" type="password" minlength="8" autocomplete="new-password" placeholder="至少 8 碼"><button type="button" class="password-toggle" onclick="togglePasswordVisibility('game-password-initial', this)">顯示</button></span>
                </label>
                <label>確認本局遊戲密碼
                    <span class="password-input-row"><input id="game-password-initial-confirm" type="password" minlength="8" autocomplete="new-password" placeholder="再次輸入密碼"><button type="button" class="password-toggle" onclick="togglePasswordVisibility('game-password-initial-confirm', this)">顯示</button></span>
                </label>
                <div id="game-password-initial-mismatch" class="password-mismatch" aria-live="polite"></div>
                <div id="game-password-initial-strength" class="password-strength" aria-live="polite">開始前必須設定密碼</div>
            </div>
            <button id="confirm-start-button" class="manual-btn" style="color: #ffffff;background-color: #0fe70f;" onclick="confirmStart()" disabled>設定密碼後開始</button>
            <button class="btn-cancel" style="color: #e2e2e2 ;background-color: #e00f0f;" onclick="breaktoDifficulty()">返回</button>
        </div>
    </div>

    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="game-over-title">任務結束</h2>
            <p id="game-over-reason"></p>
            <button class="manual-btn" onclick="location.reload()">返回選單</button>
        </div>
    </div>

    <script>
        async function verifyLogin() {
            try {
                const response = await fetch('api/auth/check_auth.php', { method: 'GET' });
                const data = await response.json();
                if (data.status !== 'success') {
                    window.location.href = 'assets/html/login.html';
                }
            } catch (error) {
                window.location.href = 'assets/html/login.html';
            }
        }
        document.addEventListener('DOMContentLoaded', verifyLogin);
    </script>
    <script src="assets/js/threatRadar.js?v=20260918"></script>
    <script type="module">
        import GameManager from './assets/js/gameManager.js?v=20260918';
        window.gameManagerInstance = new GameManager();
        window.selectedDifficulty = 0;
        window.difficultyCatalog = {};

        window.switchTab = (evt, tabId) => {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            if (evt) evt.currentTarget.classList.add('active');
            
            if (tabId === 'tab-status' && window.gameManagerInstance && window.gameManagerInstance.charts) {
                Object.values(window.gameManagerInstance.charts).forEach(chart => { if (chart) chart.resize(); });
            }
            const radarPanel = document.getElementById('threat-radar-panel');
            if (radarPanel) {
                radarPanel.style.display = (tabId === 'tab-firewall') ? 'flex' : 'none';
            }
        };

        window.loadDifficultyOptions = async () => {
            const container = document.getElementById('difficulty-options');
            if (!container) return;
            container.innerHTML = '<p>正在載入可用難度...</p>';

            try {
                const response = await fetch('api/student/get_game_data.php', { cache: 'no-store' });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') throw new Error(result.message || '無法載入難度');

                window.difficultyCatalog = result.difficulties || {};
                container.innerHTML = '';
                Object.entries(window.difficultyCatalog).forEach(([id, config]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.onclick = () => window.prepareGame(Number(id));
                    button.textContent = `${config.name || `難度 ${id}`} - ${config.time} 秒`;
                    if (config.description) button.title = config.description;
                    container.appendChild(button);
                });

                if (container.children.length === 0) {
                    container.innerHTML = '<p>目前沒有可用的訓練難度。</p>';
                }

                const requestedDifficulty = new URLSearchParams(window.location.search).get('difficulty');
                if (requestedDifficulty !== null && window.difficultyCatalog[requestedDifficulty]) {
                    window.prepareGame(Number(requestedDifficulty));
                }
            } catch (error) {
                container.innerHTML = '<p>難度載入失敗，請重新整理頁面。</p>';
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

        window.prepareGame = (diff) => {
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

        window.validateGamePassword = (password, confirmation) => {
            return window.gameManagerInstance.validateGamePassword(password, confirmation);
        };

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

        window.quickCmd = (cmd) => {
            const input = document.getElementById('cmd-input');
            input.value = cmd; input.focus();
            input.dispatchEvent(new KeyboardEvent('keydown', {'key': 'Enter'}));
        };

        window.guiMitigate = (type) => {
            if (window.gameManagerInstance) window.gameManagerInstance.guiMitigate(type);
        };
        
        window.analyzeGUI = () => {
            const input = document.getElementById('analyzer-input').value;
            if (window.gameManagerInstance) window.gameManagerInstance.analyzeThreat(input);
        };

        window.quitGame = () => { if(confirm("確定放棄？")) location.reload(); };
        window.viewMail = (id) => window.gameManagerInstance.viewMail(id);
        window.handleMail = (id, action) => window.gameManagerInstance.handleMail(id, action);
        
        // 點擊 IP 自動帶入指令並聚焦
        window.quickAnalyze = (ip) => {
            const input = document.getElementById('cmd-input');
            input.value = `whois ${ip}`; 
            input.focus();
            if(window.gameManagerInstance) {
                window.gameManagerInstance.showNotification(`已選定目標 ${ip}，請按 Enter 執行分析。`, 'success');
            }
        };
    </script>
</body>
</html>