<?php
require_once __DIR__ . '/api/core/common.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['student', 'teacher', 'admin'], true)
) {
    header('Location: assets/html/login.html');
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
$cspNonce = base64_encode(random_bytes(18));
header(
    "Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'nonce-{$cspNonce}'; " .
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com data:; " .
    "img-src 'self' data:; connect-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'"
);
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$assetVersions = [];
foreach ([
    'style.css',
    'js/index.js',
    'js/threatRadar.js',
    'js/gameManager.js',
    'js/systemStatus.js',
    'js/database.js',
    'js/cliModule.js'
] as $assetPath) {
    $assetVersions[$assetPath] = hash_file('sha256', __DIR__ . '/assets/' . $assetPath);
}
$moduleImportMap = [];
foreach (['gameManager.js', 'systemStatus.js', 'database.js', 'cliModule.js'] as $module) {
    $moduleImportMap['./assets/js/' . $module] =
        './assets/js/' . $module . '?v=' . $assetVersions['js/' . $module];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資安防禦訓練系統 - 終端監控中心</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $assetVersions['style.css'] ?>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script type="importmap" nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>"><?= json_encode(
        ['imports' => $moduleImportMap],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?></script>
</head>
<body>
    <div id="menu-screen" class="screen active">
        <h1>資安防禦訓練系統</h1>
        <div id="entry-content" class="menu-options">
            <button data-action="toggle-difficulty">開 始</button>
            <button data-nav="teaching/tutorial.php">教 學</button>
            <button data-nav="assets/html/student_dashboard.html">返回控制台</button>
            <?php if (($_SESSION['role'] ?? '') === 'teacher'): ?>
                <button data-nav="assets/html/dashboard.html#difficulties">教師難度</button>
            <?php endif; ?>
        </div>
        <div id="difficulty-content" class="menu-options hidden">
            <h2 style="color: #00FF00; text-align: center; margin-bottom: 20px;">請選擇訓練等級</h2>
            <div id="difficulty-options"><p>正在載入可用難度...</p></div>
            <button class="break-btn" data-action="break-menu">返回</button>
        </div>
    </div>

    <div id="game-screen" class="screen">
        <aside id="game-sidebar">
            <div class="sidebar-header">防禦中心</div>
            <nav class="tab-menu">
                <button class="tab-btn active" data-tab="tab-firewall"><i class="fas fa-shield-alt"></i> 監控與控制台</button>
                <button class="tab-btn" data-tab="tab-status"><i class="fas fa-chart-line"></i> 系統狀態</button>
                <button class="tab-btn" data-tab="tab-mailbox"><i class="fas fa-inbox"></i> 收件匣 <span id="unread-count" style="color:red; font-weight:bold;">0</span></button>
                <button class="tab-btn" data-tab="tab-manual"><i class="fas fa-book"></i> 指令手冊</button>
            </nav>
            <button class="quit-btn" data-action="quit-game">放棄任務</button>
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
                                    <button id="btn-pause-packet" class="cmd-tag" style="background:#ff9900; color:#000; font-weight:bold; padding:6px 12px; margin-right: 10px;" data-action="toggle-packet-capture">暫停擷取</button>
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
                                <span class="cmd-tag" data-command="help">help</span>
                                <span class="cmd-tag" data-command="status">status</span>
                                <span class="cmd-tag" data-command="ipconfig">ipconfig</span>
                                <span class="cmd-tag" data-command="ping 10.0.0.1">ping</span>
                                <span class="cmd-tag" data-command="netstat">netstat</span>
                                <span class="cmd-tag" data-command="whois udp">whois udp</span>
                                <span class="cmd-tag" data-command="limit udp">limit udp</span>
                                <span class="cmd-tag" data-command="block ip">block ip</span>
                                <span class="cmd-tag" data-command="unblock udp">unblock</span>
                                <span class="cmd-tag" data-command="scan-mail">scan-mail</span>
                                <span class="cmd-tag" data-command="flush-dns">flush-dns</span>
                                <span class="cmd-tag" data-command="passwd">passwd</span>
                                <span class="cmd-tag" data-command="clear">clear</span>
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
                                <button class="manual-btn" style="margin: 0; padding: 8px 15px; background: #1a73e8; color: #fff;" data-action="analyze">分析 (Whois)</button>
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
                                <button class="policy-btn" id="btn-mitigate-udp" data-mitigate="udp">阻斷 UDP 流量</button>
                                <button class="policy-btn" id="btn-mitigate-icmp" data-mitigate="icmp">阻斷 ICMP 流量</button>
                                <button class="policy-btn" id="btn-mitigate-dns" data-mitigate="dns">清除 DNS 快取</button>
                                <button class="policy-btn" id="btn-mitigate-ip" data-mitigate="ip">封鎖惡意來源 IP</button>
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
                        <button type="button" class="manual-btn" data-action="change-password">更換遊戲密碼</button>
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
                    <span class="password-input-row"><input id="game-password-initial" type="password" minlength="8" autocomplete="new-password" placeholder="至少 8 碼"><button type="button" class="password-toggle" data-toggle-password="game-password-initial">顯示</button></span>
                </label>
                <label>確認本局遊戲密碼
                    <span class="password-input-row"><input id="game-password-initial-confirm" type="password" minlength="8" autocomplete="new-password" placeholder="再次輸入密碼"><button type="button" class="password-toggle" data-toggle-password="game-password-initial-confirm">顯示</button></span>
                </label>
                <div id="game-password-initial-mismatch" class="password-mismatch" aria-live="polite"></div>
                <div id="game-password-initial-strength" class="password-strength" aria-live="polite">開始前必須設定密碼</div>
            </div>
            <button id="confirm-start-button" class="manual-btn" style="color: #ffffff;background-color: #0fe70f;" data-action="confirm-start" disabled>設定密碼後開始</button>
            <button class="btn-cancel" style="color: #e2e2e2 ;background-color: #e00f0f;" data-action="back-to-difficulty">返回</button>
        </div>
    </div>

    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="game-over-title">任務結束</h2>
            <p id="game-over-reason"></p>
            <button class="manual-btn" data-action="reload">返回選單</button>
        </div>
    </div>

    <script src="assets/js/threatRadar.js?v=<?= $assetVersions['js/threatRadar.js'] ?>"></script>
    <script type="module" src="assets/js/index.js?v=<?= $assetVersions['js/index.js'] ?>"></script>

</body>
</html>