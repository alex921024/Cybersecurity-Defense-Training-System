<?php
require_once '../../api/core/common.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資安防禦訓練 - 教學控制中心</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="tutorial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- 頂層教學控制大腦對話框 -->
    <div class="../tutorial-overlay">
        <h2 id="tutorial-title" style="color: #00FF00; margin-top: 0;">載入中...</h2>
        <p id="tutorial-desc" style="color: #fff; min-height: 60px; line-height: 1.6;"></p>
        <div style="text-align: right; margin-top: 15px;">
            <button id="next-btn" class="cmd-tag" style="background: #00FF00; color: #000; font-weight: bold; padding: 8px 20px; cursor: pointer;" onclick="nextStep()">
                下一步 
            </button>
        </div>
    </div>

    <div id="game-screen" class="screen active">
        <aside id="game-sidebar">
            <div class="sidebar-header">防禦中心</div>     
            <nav class="tab-menu">
                <button id="menu-tab-firewall" class="tab-btn active" onclick="switchTutorialTab('tab-firewall')">🛡️ 監控與控制台</button>
                <button id="menu-tab-status" class="tab-btn" onclick="switchTutorialTab('tab-status')">📈 系統狀態</button>
                <button id="menu-tab-mailbox" class="tab-btn" onclick="switchTutorialTab('tab-mailbox')">📧 收件匣 <span id="unread-count" style="color:red; font-weight:bold;">1</span></button>
                <button id="menu-tab-manual" class="tab-btn" onclick="switchTutorialTab('tab-manual')">📘 指令手冊</button>
            </nav>
            <button class="quit-btn" onclick="location.href='index.php'">結束教學</button>
        </aside>

        <main id="tab-content-container">
            <!-- 區塊一：Wireshark 與 控制台 -->
            <div id="tab-firewall" class="tab-content active">
                <h2 class="tab-title" style="margin: 0 0 10px 0; font-size: 1.2em;">🛡️ SOC 即時防禦儀表板 (Monitoring & Terminal)</h2>
                <div id="mission-panel" class="mission-panel">
                    <div class="mission-head">
                        <div>
                            <div class="mission-label">任務階段</div>
                            <div id="mission-phase" class="mission-phase">教學演練中</div>
                        </div>
                        <div>
                            <div class="mission-label">當前目標</div>
                            <div id="mission-objective" class="mission-objective">請跟隨上方的指揮官教學進行操作。</div>
                        </div>
                    </div>
                    <div class="mission-tip">提示：<span id="mission-tip">學習如何看見警報、分析與策略阻斷。</span></div>
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
                                    <button id="btn-pause-packet" class="cmd-tag" style="background:#ff9900; color:#000; font-weight:bold; padding:6px 12px; margin-right: 10px;">⏸️ 暫停擷取</button>
                                    <select id="protocol-filter">
                                        <option value="ALL">全部 (ALL)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="scroll-box" style="flex: 2; border-bottom: 2px solid #444;">
                                <table id="packet-table">
                                    <thead><tr><th>時間</th><th>來源 IP</th><th>來源 Port</th><th>目標 IP</th><th>目標 Port</th><th>協定</th><th>長度</th><th>狀態</th></tr></thead>
                                    <tbody id="packet-list">
                                    </tbody>
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
                                <input type="text" id="cmd-input" placeholder="教學模式中，請遵照指示..." autocomplete="off" disabled>
                            </div>
                            <div class="quick-commands">
                                <span class="cmd-tag">status</span>
                                <span class="cmd-tag">netstat</span>
                                <span class="cmd-tag">whois udp</span>
                                <span class="cmd-tag">limit udp</span>
                                <span class="cmd-tag">block ip</span>
                            </div>
                        </section>
                    </div>
                    
                    <div class="firewall-right">
                        <div class="firewall-card alert-card" id="guide-alert-card">
                            <h3><i class="fas fa-bell"></i> IDS 即時警報日誌</h3>
                            <div id="ids-alert-log" class="log-box">教學系統載入完成，等待威脅模擬...</div>
                        </div>
                        
                        <div class="firewall-card analysis-card" id="guide-analysis-card">
                            <h3><i class="fas fa-search"></i> 威脅情報分析儀 (SOP 認證)</h3>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <input type="text" id="analyzer-input" placeholder="輸入可疑 IP 或 協定 (如 udp)..." disabled style="flex: 1; background: #111; border: 1px solid #333; color: #00FF00; padding: 8px; font-family: monospace;">
                                <button class="manual-btn" style="margin: 0; padding: 8px 15px; background: #1a73e8; color: #fff;">分析 (Whois)</button>
                            </div>
                            <div id="analyzer-result" class="log-box" style="height: 60px; color: #00ebff;">等待輸入分析目標...</div>
                        </div>

                        <div class="firewall-card intel-card" id="guide-intel-card">
                            <h3><i class="fas fa-bug"></i> 已偵測惡意 IP (Threat Intel)</h3>
                            <div id="threat-intel-list" class="log-box" style="height: 120px; overflow-y: auto; display: block; padding: 10px;">
                                <span style="color:#666;">尚未發現已知威脅 IP...</span>
                            </div>
                        </div>

                        <div class="firewall-card control-card" id="guide-control-card">
                            <h3><i class="fas fa-sliders-h"></i> 策略快捷防禦開關 (需先分析)</h3>
                            <div class="policy-grid">
                                <button class="policy-btn" id="btn-mitigate-udp">阻斷 UDP 流量</button>
                                <button class="policy-btn" id="btn-mitigate-icmp">阻斷 ICMP 流量</button>
                                <button class="policy-btn" id="btn-mitigate-dns">清除 DNS 快取</button>
                                <button class="policy-btn" id="btn-mitigate-ip">封鎖惡意來源 IP</button>
                            </div>
                        </div>
                        
                        <div class="firewall-card rules-card" id="guide-rules-card">
                            <h3><i class="fas fa-list"></i> 當前防火牆規則 (ACL)</h3>
                            <div class="scroll-box" style="background:#111; height: 120px;">
                                <table class="manual-table" style="margin:0; font-size:13px;">
                                    <thead><tr><th>規則 ID</th><th>標的</th><th>動作</th><th>狀態</th></tr></thead>
                                    <tbody id="acl-rules-body">
                                        <tr><td colspan="4" style="text-align:center; color:#666;">尚無自訂過濾規則</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 分頁二：系統狀態 -->
            <div id="tab-status" class="tab-content">
                <h2 class="tab-title"> 系統硬體即時監控中心</h2>
                <div class="time-banner"> 剩餘時間: <span id="timer">99:99</span></div>
                <div class="monitor-grid">
                    <div class="chart-box">
                        <div class="chart-header"><span>CPU 使用率</span><span class="live-indicator"></span></div>
                        <div class="chart-body"><div style="color:#00ebff; text-align:center; line-height:150px;">[ 模擬圖表數據 ]</div></div>
                        <div class="chart-footer">負載: <span id="cpu-load">12</span>%</div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-header"><span>GPU 使用率</span><span class="live-indicator"></span></div>
                        <div class="chart-body"><div style="color:#00FF00; text-align:center; line-height:150px;">[ 模擬圖表數據 ]</div></div>
                        <div class="chart-footer">負載: <span id="gpu-load">5</span>%</div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-header"><span>RAM 使用率</span><span class="live-indicator"></span></div>
                        <div class="chart-body"><div style="color:#FFFF00; text-align:center; line-height:150px;">[ 模擬圖表數據 ]</div></div>
                        <div class="chart-footer">負載: <span id="ram-load">42</span>%</div>
                    </div>
                    <div class="chart-box danger-box">
                        <div class="chart-header"><span style="color: #FF3333;">密碼破解進度</span><span class="live-indicator danger-pulse"></span></div>
                        <div class="chart-body"><div style="color:#ff3333; text-align:center; line-height:150px;">🛡️ 教學模式已阻斷破解</div></div>
                        <div class="chart-footer" style="color: #FF3333;">進度: <span id="crack-progress">0</span>%</div>
                    </div>
                </div>
            </div>

            <!-- 分頁三：收件匣 -->
            <div id="tab-mailbox" class="tab-content">
                <h2 class="tab-title">企業收件匣</h2>
                <div class="mail-layout">
                    <div class="mail-list" id="mail-list-container">
                        <div class="mail-item unread" style="cursor: pointer;">
                            <span class="mail-badge phishing">🎣 釣魚嫌疑</span>
                            <div style="margin-top:5px; font-weight:bold;">系統管理員：請立即變更您的重要密碼</div>
                            <div style="font-size:12px; color:#aaa; margin-top:3px;">發件人: admin@fake-security.com</div>
                        </div>
                    </div>
                    <div class="mail-viewer" id="mail-viewer-container">
                        <div class="mail-header"><h3>主旨：請立即變更您的重要密碼</h3></div>
                        <p style="color: #b8c7de; font-size:14px; line-height:1.6; margin-top:15px;">
                            親愛的用戶您好，系統偵測到您的帳號有異常登入風險。<br>
                            請點擊下方連結立即強制重置密碼，否則帳號將在 24 小時內凍結。<br><br>
                            <a href="#" style="color:#ff4444; text-decoration:underline;">👉 點此安全驗證連線並重置密碼 (惡意網址)</a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- 分頁四：指令手冊 -->
            <div id="tab-manual" class="tab-content">
                <h2 class="tab-title">系統指令參考手冊 (Cheat Sheet)</h2>
                <div class="scroll-box">
                    <table class="manual-table">
                        <thead><tr><th>指令</th><th>功能說明</th></tr></thead>
                        <tbody>
                            <tr><td><code>status</code></td><td>查看系統詳細負載狀態與密碼破解進度。</td></tr>
                            <tr><td><code>netstat</code></td><td>顯示當前網路連線，若有 DDoS 或異常流量會跳出警告。</td></tr>
                            <tr><td><code style="color: #00ebff;">whois [IP/協定]</code></td><td>(重要) 分析可疑目標，取得授權。範例：<code>whois udp</code></td></tr>
                            <tr><td><code>limit [協定]</code></td><td>暫時限速緩解攻擊傷害，爭取時間尋找來源。</td></tr>
                            <tr><td><code>block [IP/協定]</code></td><td>設定防火牆。需先完成分析才可封鎖。注意副作用。</td></tr>
                            <tr><td><code>unblock [IP/協定]</code></td><td>解除封鎖設定，恢復正常服務運作。</td></tr>
                            <tr><td><code>flush-dns</code></td><td>清除 DNS 快取。遭受污染時使用。</td></tr>
                            <tr><td><code>scan-mail</code></td><td>自動掃描並隔離惡意釣魚信件。</td></tr>
                            <tr><td><code>passwd</code></td><td>強制重置系統密碼，將破解進度歸零。</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- 教學控制腳本 -->
    <script src="tutorial.js"></script>
</body>
</html>