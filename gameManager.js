import SystemStatus from './systemStatus.js';
import GameDB from './database.js';
import CLIModule from './cliModule.js';

class GameManager {
    constructor() {
        this.status = null;
        this.interval = null;
        this.activeThreat = null;
        this.cli = new CLIModule(this);
        this.difficultyConfig = null;
        this.charts = { cpu: null, gpu: null, ram: null, hack: null };
        this.maxDataPoints = 30;
        
        this.inbox = [];
        this.mailCounter = 0;
        this.packetHistory = [];
        this.stats = { syn: 0, udp: 0, dns: 0, icmp: 0, fishing: 0 };
        this.activeRules = []; 
        this.analyzedTargets = new Set(); 

        if (typeof ThreatRadar !== 'undefined') {
            this.radar = new ThreatRadar();
        } else {
            this.radar = null;
        }

        document.getElementById('cmd-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const val = e.target.value;
                if(val.trim() !== "") this.logTerminal(val, "user");
                const res = this.cli.execute(val);
                if(res) this.logTerminal(res, "system");
                e.target.value = '';
            }
        });

        const filterEl = document.getElementById('protocol-filter');
        if (filterEl) filterEl.addEventListener('change', () => this.renderPackets());
        window.gameManager = this;
    }

    init(difficulty) {
        document.getElementById('menu-screen').classList.remove('active');
        document.getElementById('game-screen').classList.add('active');
        document.getElementById('terminal-output').innerHTML = '';
        
        this.difficultyConfig = GameDB.difficulties[difficulty];
        this.status = new SystemStatus();
        this.status.timer = this.difficultyConfig.time;
        this.activeThreat = null;
        
        this.inbox = [];
        this.mailCounter = 0;
        this.packetHistory = []; 
        this.activeRules = [];
        this.stats = { syn: 0, udp: 0, dns: 0, icmp: 0, fishing: 0 };
        
        this.analyzedTargets.clear();
        const resEl = document.getElementById('analyzer-result');
        if (resEl) resEl.innerHTML = "等待輸入分析目標...";

        this.logTerminal("System boot successful. Initializing security protocols...", "system");
        this.logTerminal(`開始執行難度等級: ${difficulty}。請隨時注意系統負載與破解進度。`, "alert");
        
        this.receiveEmail(false);
        this.initCharts();
        this.renderRulesGUI();
        
        const logEl = document.getElementById('ids-alert-log');
        if (logEl) logEl.innerHTML = "系統防禦已初始化，處於全面監控狀態...";

        if(this.interval) clearInterval(this.interval);
        this.interval = setInterval(() => this.gameLoop(), 1000);
    }

    gameLoop() {
        this.status.timer--;
        this.status.crackProgress += (Math.random() * 0.2 + 0.05); 

        const allTabs = document.querySelectorAll('.tab-btn');
        const mailBtn = Array.from(allTabs).find(btn => btn.innerText.includes('收件匣'));

        if (this.activeThreat) {
            if (this.status.timer % 8 === 0) {
                this.status.applyDamage(this.activeThreat === 'fishing' ? 'Fishing' : 'Network');
                if (this.activeThreat === 'fishing') this.triggerErrorFlash();
                else this.logTerminal(`[系統警告] 未處理的網路威脅 (${this.activeThreat.toUpperCase()})！伺服器負載飆升！`, "alert");
            }
            if (mailBtn) mailBtn.classList.toggle('mail-warning', this.activeThreat === 'fishing');
        } else {
            if (mailBtn) mailBtn.classList.remove('mail-warning');
        }

        if (!this.activeThreat && this.status.timer % 12 === 0) this.generateEvent();
        if (this.status.timer % 25 === 0 && Math.random() > 0.5) this.receiveEmail(false);

        this.generatePacketUI();
        this.updateUI();
        this.renderMailList();
        this.updateAllCharts(this.status.cpu, this.status.gpu, this.status.ram, this.status.crackProgress);

        const result = this.status.checkStatus();
        if (result !== "RUNNING") this.endGame(result);
    }
    
    generateEvent() {
        const dice = Math.floor(Math.random() * 100) + 1;
        const r = this.difficultyConfig.ranges;
        let newThreat = null;

        if (r.syn && dice >= r.syn[0] && dice <= r.syn[1]) newThreat = "syn";
        else if (r.udp && dice >= r.udp[0] && dice <= r.udp[1]) newThreat = "udp";
        else if (r.dns && dice >= r.dns[0] && dice <= r.dns[1]) newThreat = "dns";
        else if (r.icmp && dice >= r.icmp[0] && dice <= r.icmp[1]) newThreat = "icmp";
        else if (r.fishing && dice >= r.fishing[0] && dice <= r.fishing[1]) newThreat = "fishing";

        if (newThreat) {
            this.activeThreat = newThreat;
            if (this.radar && ['syn', 'udp', 'dns', 'icmp'].includes(newThreat)) this.radar.trigger(newThreat);

            if (newThreat === 'fishing') {
                this.receiveEmail(true); 
                this.logTerminal(`[IDS 警報] 攔截到可疑郵件，請至「收件匣」或使用 scan-mail 處理！`, "alert");
                this.updateIdsAlert(`[威脅警報] 偵測到社交工程攻擊：惡意釣魚郵件已派發！`);
            } else {
                this.logTerminal(`[IDS 警報] 偵測到異常活動: ${newThreat.toUpperCase()} 攻擊！請用 whois 分析後阻擋。`, "alert");
                this.updateIdsAlert(`[流量突增] 偵測到異常 ${newThreat.toUpperCase()} 流量，請進行分析。`);
            }
            this.syncPolicyButtons();
        }
    }

    analyzeThreat(target) {
        const cleanTarget = target.toLowerCase().trim();
        if (!cleanTarget) return "請輸入有效的 IP 或協定名稱。";

        const resultEl = document.getElementById('analyzer-result');
        if (resultEl) resultEl.innerHTML = `<span style="color:#888;">[查詢中] 正在透過情報庫檢索 ${cleanTarget}...</span>`;

        setTimeout(() => {
            let outputMsg = "";
            if (cleanTarget === this.activeThreat || cleanTarget === 'ip') {
                this.analyzedTargets.add(cleanTarget);
                if (cleanTarget === 'syn') this.analyzedTargets.add('ip'); 
                
                outputMsg = `[分析結果 - 高危險] <br>目標: ${cleanTarget.toUpperCase()}<br>狀態: 偵測到惡意 Payload。<br>建議行動: 立即進行阻斷 (Block)。`;
                this.updateIdsAlert(`[情報解鎖] 已確認 ${cleanTarget.toUpperCase()} 為惡意來源，授權進行攔截。`);
                this.logTerminal(`[Whois 分析] 檢索結果：${cleanTarget.toUpperCase()} 具高風險，授權封鎖！`, "system");
            } 
            else if (cleanTarget === 'dns') {
                this.analyzedTargets.add('dns');
                outputMsg = `[分析結果 - 異常] <br>狀態: DNS 路由表遭異常放大請求污染。<br>建議行動: 清除 DNS 快取 (Flush)。`;
                this.logTerminal(`[Whois 分析] 檢索結果：DNS 遭到污染，授權清理。`, "system");
            } 
            else {
                outputMsg = `[分析結果 - 安全] <br>目標: ${cleanTarget.toUpperCase()}<br>狀態: 目前未發現明顯的威脅情報。`;
                this.logTerminal(`[Whois 分析] 檢索結果：${cleanTarget.toUpperCase()} 狀態正常。`, "system");
            }

            if (resultEl) resultEl.innerHTML = outputMsg;
        }, 800);

        return "情報分析指令已發送...";
    }

    applyBlockAction(arg) {
        const cleanArg = arg.toLowerCase().trim();
        
        if (!this.analyzedTargets.has(cleanArg) && cleanArg !== 'ip') {
            this.triggerErrorFlash();
            this.updateIdsAlert(`[操作駁回] 嘗試盲目封鎖未經確認的目標 ${cleanArg.toUpperCase()}`);
            return `[錯誤：違反 SOP] 請先使用分析儀或 whois 指令查明來源，取得授權後再封鎖！`;
        }
        if (cleanArg === 'ip' && (!this.analyzedTargets.has(this.activeThreat) && !this.analyzedTargets.has('ip'))) {
            this.triggerErrorFlash();
            return `[錯誤：違反 SOP] 請先分析具體的攻擊協定或來源 IP 後再進行封鎖。`;
        }

        if (cleanArg === this.activeThreat || cleanArg === 'ip') {
            if (this.activeThreat && this.activeThreat !== 'fishing' && this.activeThreat !== 'dns') {
                this.stats[this.activeThreat]++;
            }
            
            const ruleId = "RULE-" + Math.floor(Math.random() * 9000 + 1000);
            this.activeRules.unshift({
                id: ruleId,
                target: cleanArg === 'ip' ? '103.24.55.12' : cleanArg.toUpperCase(),
                action: "DROP",
                time: new Date().toLocaleTimeString()
            });
            
            this.status.reduceLoad(20);
            this.activeThreat = null;
            this.analyzedTargets.clear();
            
            this.updateIdsAlert(`[策略部署] 成功套用規則 ${ruleId}，攔截該來源。`);
            this.renderRulesGUI();
            this.syncPolicyButtons();
            
            if (document.getElementById('analyzer-result')) {
                document.getElementById('analyzer-result').innerHTML = "危機已解除，等待下一次分析...";
            }
            return `[執行成功] 防火牆規則已更新：成功阻擋 ${cleanArg.toUpperCase()} 攻擊流量！`;
        }
        return `[提示] 已設定封鎖規則，但未偵測到該類型威脅。`;
    }

    applyFlushDns() {
        if (!this.analyzedTargets.has('dns') && this.activeThreat === "dns") {
            this.triggerErrorFlash();
            return `[錯誤：違反 SOP] 請先使用 whois dns 分析 DNS 狀態，確認污染後再清除。`;
        }

        if (this.activeThreat === "dns") {
            this.stats.dns++; 
            this.status.reduceLoad(20);
            this.activeThreat = null;
            this.analyzedTargets.clear();
            
            const ruleId = "RULE-" + Math.floor(Math.random() * 9000 + 1000);
            this.activeRules.unshift({ id: ruleId, target: "DNS CACHE", action: "FLUSH & RE-ROUTE", time: new Date().toLocaleTimeString() });
            
            this.updateIdsAlert(`[擴展防禦] 執行 DNS 快取淨化 (規則: ${ruleId})。`);
            this.renderRulesGUI();
            this.syncPolicyButtons();
            return "[執行成功] DNS 快取已清除，重新導向安全伺服器。";
        }
        return "DNS 快取已清除。目前無異常。";
    }

    guiMitigate(type) {
        let res = "";
        if (type === 'udp' || type === 'icmp' || type === 'ip') res = this.applyBlockAction(type);
        else if (type === 'dns') res = this.applyFlushDns();
        if (res) this.logTerminal(res, "system");
    }

    updateIdsAlert(msg) {
        const logEl = document.getElementById('ids-alert-log');
        if (logEl) logEl.innerHTML = `[${new Date().toLocaleTimeString()}] ${msg}\n` + logEl.innerHTML;
    }

    renderRulesGUI() {
        const tbody = document.getElementById('acl-rules-body');
        if (!tbody) return;
        if (this.activeRules.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:#666;">尚無自訂過濾規則</td></tr>`;
            return;
        }
        tbody.innerHTML = this.activeRules.map(r => `
            <tr>
                <td><code>${r.id}</code></td>
                <td><span style="color:#ffaa00;">${r.target}</span></td>
                <td><span style="color:#ff4444; font-weight:bold;">${r.action}</span></td>
                <td><span style="color:#00ff00;">ACTIVE</span></td>
            </tr>
        `).join('');
    }

    syncPolicyButtons() {
        ['udp', 'icmp', 'dns', 'ip'].forEach(t => {
            const btn = document.getElementById(`btn-mitigate-${t}`);
            if (btn) btn.classList.remove('active-policy');
        });
        
        if (this.activeThreat) {
            let targetId = `btn-mitigate-${this.activeThreat}`;
            if (this.activeThreat === 'syn') targetId = 'btn-mitigate-ip';
            const activeBtn = document.getElementById(targetId);
            if (activeBtn) activeBtn.classList.add('active-policy');
        }
    }

    receiveEmail(isMalicious) {
        this.mailCounter++;
        const pool = isMalicious ? GameDB.emails.malicious : GameDB.emails.normal;
        const mailData = pool[Math.floor(Math.random() * pool.length)];
        this.inbox.unshift({ id: this.mailCounter, sender: mailData.sender, subject: mailData.subject, content: mailData.content, isMalicious: isMalicious, spawnTime: this.status.timer, read: false });
        this.renderMailList();
    }

    renderMailList() { 
        const list = document.getElementById('mail-list-container');
        if (!list) return;
        list.innerHTML = '';
        let unread = 0;
        this.inbox.forEach(mail => {
            if(!mail.read) unread++;
            const div = document.createElement('div');
            let timeWarning = ""; 
            const limit = 15; 
            const remaining = limit - (mail.spawnTime - this.status.timer);
            if (remaining >= 0) timeWarning = ` <span style="color: #FF0000;"> ${remaining}s</span>`;
            else {
                timeWarning = ` <span style="color: #888;"> 已過期</span>`;
                if (!mail.punished) { 
                    mail.punished = true;
                    if (mail.isMalicious) {
                        this.triggerErrorFlash();
                        this.status.applyDamage('Fishing');
                        this.logTerminal(`[重大警報] 釣魚郵件未處理，系統遭感染！`, "danger"); 
                    } else this.status.cpu += 5;
                }
            }
            div.className = `mail-item ${mail.read ? 'read' : 'unread'}`;
            div.onclick = () => this.viewMail(mail.id);
            div.innerHTML = `<strong>${mail.sender}</strong>${timeWarning}<br><span style="font-size:0.9em">${mail.subject}</span>`;
            list.appendChild(div);
        });
        const unreadBadge = document.getElementById('unread-count');
        if (unreadBadge) unreadBadge.innerText = unread;
    }

    viewMail(id) {
        const mail = this.inbox.find(m => m.id === id);
        if (!mail) return;
        mail.read = true;
        this.renderMailList();
        const viewer = document.getElementById('mail-viewer-container');
        if (viewer) viewer.innerHTML = `
            <div class="mail-header"><h3>${mail.subject}</h3><p><strong>寄件者:</strong> ${mail.sender}</p></div>
            <div class="mail-body"><p>${mail.content.replace(/\n/g, '<br>')}</p></div>
            <div class="mail-actions">
                <button class="btn-delete" onclick="gameManager.handleMail(${mail.id}, 'delete')">🗑️ 刪除信件 (安全)</button>
                <button class="btn-click" onclick="gameManager.handleMail(${mail.id}, 'click')">🔗 點擊連結 / 回覆 (執行)</button>
            </div>`;
    }

    handleMail(id, action) {
        const mailIndex = this.inbox.findIndex(m => m.id === id);
        if (mailIndex === -1) return;
        const mail = this.inbox[mailIndex];

        if (action === 'delete') {
            if (mail.isMalicious && this.activeThreat === 'fishing') {
                this.stats.fishing++; this.activeThreat = null; this.status.reduceLoad(15);
                this.logTerminal(`[系統通知] 成功刪除惡意釣魚郵件。`, "success");
                this.showNotification("成功識別並銷毀釣魚威脅。","success");
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '';
            this.renderMailList();
        } else if (action === 'click') {
            if (mail.isMalicious) {
                this.triggerErrorFlash(); this.activeThreat = null; 
                this.status.applyDamage('Fishing'); this.status.applyDamage('Fishing'); 
                this.logTerminal(`[重大警報] 誤點惡意連結，系統遭感染！`, "danger");
                this.showNotification("警告：誤觸釣魚連結！資源大幅消耗。");
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '';
            this.renderMailList();
        }
    }

    generatePacketUI() {
        const isAttack = this.activeThreat && this.activeThreat !== 'fishing'; 
        const time = new Date().toLocaleTimeString('en-US', {hour12: false});
        const srcIP = isAttack ? GameDB.maliciousIPs[Math.floor(Math.random() * GameDB.maliciousIPs.length)] : `192.168.1.${Math.floor(Math.random()*255)}`;
        const normalProtos = ["TCP", "TCP", "TLSv1.2", "UDP", "HTTP", "DNS", "ICMP"];
        const proto = isAttack ? this.activeThreat.toUpperCase() : normalProtos[Math.floor(Math.random() * normalProtos.length)];
        let srcPort = "-", destPort = "-";

        if (proto !== 'ICMP') {
            srcPort = Math.floor(Math.random() * 55535 + 10000).toString();
            switch(proto) {
                case 'HTTP': destPort = "80"; break;
                case 'TLSv1.2': destPort = "443"; break;
                case 'DNS': destPort = "53"; break;
                case 'UDP': destPort = isAttack ? Math.floor(Math.random() * 65535).toString() : "53"; break;
                case 'SYN': destPort = [80, 443][Math.floor(Math.random()*2)].toString(); break;
                case 'TCP': destPort = [80, 443, 22, 3389, 8080][Math.floor(Math.random()*5)].toString(); break;
                default: destPort = "80";
            }
        }
        const len = isAttack ? Math.floor(Math.random() * 1500 + 1000) : Math.floor(Math.random() * 200 + 40);
        this.packetHistory.unshift({ time, srcIP, srcPort, destIP: '10.0.0.1', destPort, proto, len, isAttack });
        if (this.packetHistory.length > 40) this.packetHistory.pop();
        this.renderPackets();
    }

    renderPackets() {
        const list = document.getElementById('packet-list');
        const filterEl = document.getElementById('protocol-filter');
        if (!list) return;
        const filter = filterEl ? filterEl.value : 'ALL';
        list.innerHTML = ''; 

        let filteredPackets = this.packetHistory;
        if (filter !== 'ALL') {
            filteredPackets = this.packetHistory.filter(p => p.proto === filter || (filter === 'TCP' && (p.proto === 'TLSv1.2' || p.proto === 'HTTP')));
        }
        filteredPackets.slice(0, 14).forEach(p => {
            const tr = document.createElement('tr');
            tr.className = p.isAttack ? 'packet-danger' : `proto-${p.proto.toLowerCase().replace('.', '')}`;  
            tr.innerHTML = `<td>${p.time}</td><td>${p.srcIP}</td><td>${p.srcPort}</td><td>${p.destIP}</td><td style="color:#00ffff;">${p.destPort}</td><td>${p.proto}</td><td>${p.len}</td><td>${p.isAttack ? 'Malicious' : 'Standard'}</td>`;
            list.appendChild(tr);
        });
    }

    updateUI() {
        const updateColor = (id, val) => { const el = document.getElementById(id); if (el) { el.innerText = Math.floor(val); el.className = val > 80 ? "danger" : val > 60 ? "warning" : "safe"; } };
        updateColor('cpu-load', this.status.cpu); updateColor('gpu-load', this.status.gpu); updateColor('ram-load', this.status.ram);
        const crackEl = document.getElementById('crack-progress'); if (crackEl) crackEl.innerText = Math.floor(this.status.crackProgress);
        const timerEl = document.getElementById('timer'); if (timerEl) timerEl.innerText = this.status.timer;
    }

    logTerminal(msg, type) {
        const out = document.getElementById('terminal-output');
        if (!out) return;
        const prefix = type === "user" ? "root@sec-server:~# " : "";
        const color = type === "alert" ? "color:#ffaa00; font-weight:bold;" : type === "success" ? "color:#00ff00; font-weight:bold;" : "color:#00FF00;";
        out.innerHTML += `<div style="${color} margin-bottom: 4px;">${prefix}${msg}</div>`;
        out.scrollTop = out.scrollHeight;
    }

    endGame(reason) {
        clearInterval(this.interval);
        const results = {
            "SUCCESS": { title: "🎉 任務成功", desc: "伺服器安全撐過指定時間，您完美守護了系統！", color: "#00FF00" },
            "FAILURE_RESOURCE": { title: "💥 任務失敗", desc: "防禦失敗：硬體負載達 100% 崩潰！", color: "#FF4444" },
            "FAILURE_CRACKED": { title: "💀 任務失敗", desc: "防禦失敗：密碼已被字典檔破解！", color: "#FF4444" },
            "FAILURE_OVERLOAD": { title: "⚠️ 任務失敗", desc: "防禦失敗：系統負載未能降至 75% 以下。", color: "#FFA500" }
        };
        setTimeout(() => {
            const titleEl = document.getElementById('game-over-title'); if(titleEl) { titleEl.innerText = results[reason].title; titleEl.style.color = results[reason].color; }
            const reasonEl = document.getElementById('game-over-reason'); if(reasonEl) reasonEl.innerText = results[reason].desc;
            const modalEl = document.getElementById('game-over-modal'); if(modalEl) modalEl.classList.remove('hidden');
        }, 500); 
    }   

    initCharts() {
        this.charts.cpu = this.createLineChart('cpuChart', '#00ebff'); this.charts.gpu = this.createLineChart('gpuChart', '#00ff00');
        this.charts.ram = this.createLineChart('ramChart', '#ffff00'); this.charts.hack = this.createLineChart('hackChart', '#ff3333');
    }

    createLineChart(elementId, lineColor) {
        const canvas = document.getElementById(elementId); if (!canvas) return null;
        return new Chart(canvas.getContext('2d'), { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: lineColor, backgroundColor: 'rgba(0,0,0,0)', borderWidth: 1.5, tension: 0, pointRadius: 0 }] }, options: { responsive: true, maintainAspectRatio: false, animation: false, scales: { x: { display: false }, y: { min: 0, max: 100, ticks: { display: false }, grid: { color: '#222' } } }, plugins: { legend: { display: false } } } });
    }

    updateAllCharts(cpuVal, gpuVal, ramVal, hackVal) {
        const baseVals = { cpu: cpuVal, gpu: gpuVal, ram: ramVal, hack: hackVal };
        ['cpu', 'gpu', 'ram', 'hack'].forEach(key => {
            const chartObj = this.charts[key]; if (!chartObj) return;
            let finalVal = Math.max(0, Math.min(100, baseVals[key] + (Math.random() * 2 - 1))); 
            chartObj.data.labels.push(''); chartObj.data.datasets[0].data.push(finalVal); 
            if (chartObj.data.labels.length > this.maxDataPoints) { chartObj.data.labels.shift(); chartObj.data.datasets[0].data.shift(); }
            chartObj.update('none');
        });
    }

    showNotification(message, type = 'danger') {
        let container = document.getElementById('game-notification-container');
        if (!container) { container = document.createElement('div'); container.id = 'game-notification-container'; document.getElementById('game-screen').appendChild(container); }
        const toast = document.createElement('div'); toast.className = `game-toast ${type}`; toast.innerHTML = `<strong>${type === 'success' ? '[系統]' : '[警告]'}</strong> ${message}`;
        container.appendChild(toast); setTimeout(() => { toast.classList.add('toast-fade-out'); setTimeout(() => toast.remove(), 500); }, 3000);
    }

    triggerErrorFlash() {
        const gameEl = document.getElementById('game-screen');
        if (gameEl) { gameEl.classList.remove('screen-crash-active'); void gameEl.offsetWidth; gameEl.classList.add('screen-crash-active'); setTimeout(() => gameEl.classList.remove('screen-crash-active'), 650); }
    }
}
export default GameManager;