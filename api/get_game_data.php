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

        this.isPacketPaused = false;
        this.activeSideEffects = [];
        this.activeLimits = [];
        
        this.discoveredIPs = new Map();
        
        this.peaceCooldown = 0;
        this.attackCooldown = 0;

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
        
        window.togglePacketCapture = () => this.togglePacketCapture();
        window.gameManager = this;
        window.gameManagerInstance = this; 
    }

    // 將 init 改為 async 以支援 fetch 從資料庫讀取動態題庫
    async init(difficulty) {
        document.getElementById('menu-screen').classList.remove('active');
        document.getElementById('game-screen').classList.add('active');
        document.getElementById('terminal-output').innerHTML = '';
        
        // 1. 嘗試從後端資料庫載入動態題庫
        try {
            const response = await fetch('api/get_game_data.php');
            const result = await response.json();
            
            if (result.status === 'success') {
                GameDB.maliciousIPs = result.maliciousIPs;
                GameDB.vipIPs = result.vipIPs;
                GameDB.emails = result.emails;
                console.log("✅ 成功從資料庫載入動態題庫！");
            } else {
                console.warn("⚠️ 無法載入後端題庫，將使用預設本地題庫。");
            }
        } catch (error) {
            console.error("❌ 連線後端題庫 API 失敗，使用預設本地題庫:", error);
        }

        this.difficultyConfig = GameDB.difficulties[difficulty];
        this.status = new SystemStatus();
        this.status.timer = this.difficultyConfig.time;
        this.activeThreat = null;
        
        this.actionLogs = []; 
        
        this.inbox = [];
        this.mailCounter = 0;
        this.packetHistory = []; 
        this.activeRules = [];
        this.stats = { syn: 0, udp: 0, dns: 0, icmp: 0, fishing: 0 };
        this.activeSideEffects = [];
        this.activeLimits = [];
        this.isPacketPaused = false;
        
        this.peaceCooldown = 15;
        this.attackCooldown = 12;
        
        this.analyzedTargets.clear();
        this.discoveredIPs.clear();
        
        const resEl = document.getElementById('analyzer-result');
        if (resEl) resEl.innerHTML = "等待輸入分析目標...";

        this.updateMissionPanel('待命中', '啟動監控，等待異常流量或釣魚郵件。', '保持系統穩定，使用 status 與 netstat 了解當前狀態。', 1);

        this.logTerminal("System boot successful. Initializing security protocols...", "system");
        this.logTerminal(`開始執行難度等級: ${difficulty}。請隨時注意系統負載與破解進度。`, "alert");
        
        this.receiveEmail(false);
        this.initCharts();
        this.renderRulesGUI();
        this.renderThreatIntel();
        
        const logEl = document.getElementById('ids-alert-log');
        if (logEl) logEl.innerHTML = "系統防禦已初始化，處於全面監控狀態...";

        if(this.interval) clearInterval(this.interval);
        this.interval = setInterval(() => this.gameLoop(), 1000);
    }

    updateMissionPanel(phase, objective, tip, stepIndex) {
        const phaseEl = document.getElementById('mission-phase');
        const objEl = document.getElementById('mission-objective');
        const tipEl = document.getElementById('mission-tip');
        if (phaseEl) phaseEl.innerText = phase;
        if (objEl) objEl.innerText = objective;
        if (tipEl) tipEl.innerText = tip;

        for (let idx = 1; idx <= 3; idx++) {
            const stepEl = document.getElementById(`mission-step-${idx}`);
            if (!stepEl) continue;
            if (idx === stepIndex) stepEl.classList.add('active-step');
            else stepEl.classList.remove('active-step');
        }
    }

    gameLoop() {
        this.status.timer--;
        this.status.crackProgress += (Math.random() * 0.1 + 0.02); 

        if (this.peaceCooldown > 0) {
            this.peaceCooldown--;
        }
        if (this.attackCooldown > 0) {
            this.attackCooldown--;
        }

        const allTabs = document.querySelectorAll('.tab-btn');
        const mailBtn = Array.from(allTabs).find(btn => btn.innerText.includes('收件匣'));

        if (this.activeThreat) {
            this.updateMissionPanel('攻擊偵測', `偵測到 ${this.activeThreat.toUpperCase()} 威脅。請先分析或限速，再進行封鎖。`, '使用 whois 或 netstat 取得更多資訊，必要時 deploy block/limit。', 2);

            if (this.status.timer % 8 === 0) {
                if (this.activeLimits.includes(this.activeThreat)) {
                    this.status.wifi = Math.min(100, this.status.wifi + 2); 
                    this.logTerminal(`[系統提示] 攻擊 (${this.activeThreat.toUpperCase()}) 已被限速緩解，請盡快找出來源 IP！`, "success");
                } else {
                    this.status.applyDamage(this.activeThreat === 'fishing' ? 'Fishing' : 'Network');
                    if (this.activeThreat === 'fishing') this.triggerErrorFlash();
                    else this.logTerminal(`[系統警告] 未處理的網路威脅 (${this.activeThreat.toUpperCase()})！伺服器負載飆升！`, "alert");
                }
            }
            if (mailBtn) mailBtn.classList.toggle('mail-warning', this.activeThreat === 'fishing');
        } else {
            if (this.peaceCooldown > 0) {
                this.updateMissionPanel('維持防禦', '已緩解當前威脅，待命下一波攻擊。', '留意系統資源與新進郵件，避免錯過釣魚信件。', 3);
            } else {
                this.updateMissionPanel('待命中', '啟動監控，等待異常流量或釣魚郵件。', '保持系統穩定，使用 status 與 netstat 了解當前狀態。', 1);
            }
            if (mailBtn) mailBtn.classList.remove('mail-warning');
        }

        if (this.activeSideEffects.length > 0) {
            if (this.status.timer % 8 === 0) {
                this.status.wifi = Math.min(100, this.status.wifi + 5);
                this.status.cpu = Math.min(100, this.status.cpu + 3);
                this.logTerminal(`[副作用警告] 全域封鎖 ${this.activeSideEffects.join(', ')} 導致業務受阻！請找出惡意 IP 封鎖後，使用 unblock 恢復正常。`, "alert");
            }
        }
        
        if (this.activeLimits.length > 0) {
            if (this.status.timer % 12 === 0) {
                this.status.wifi = Math.min(100, this.status.wifi + 2); 
                this.logTerminal(`[效能警告] 流量管制 (限速) 影響正常業務。處理完畢後請記得 unblock 解除限速！`, "warning");
            }
        }

        const isSystemClear = !this.activeThreat && this.activeSideEffects.length === 0 && this.activeLimits.length === 0 && this.peaceCooldown <= 0;

        if (isSystemClear && this.attackCooldown <= 0 && this.status.timer % 20 === 0) {
            this.generateEvent();
        }

        if (this.status.timer % 25 === 0 && Math.random() > 0.5) this.receiveEmail(false);

        this.generatePacketUI();
        this.updateUI();
        this.renderMailList();
        this.updateAllCharts(this.status.cpu, this.status.gpu, this.status.ram, this.status.crackProgress);

        const result = this.status.checkStatus();
        if (result !== "RUNNING") this.endGame(result);
    }
    
    generateEvent() {
        if (this.activeThreat) return;

        const dice = Math.floor(Math.random() * 100) + 1;
        const r = this.difficultyConfig.ranges;
        let newThreat = null;

        if (r.syn && dice >= r.syn[0] && dice <= r.syn[1]) newThreat = "syn";
        else if (r.udp && dice >= r.udp[0] && dice <= r.udp[1]) newThreat = "udp";
        else if (r.dns && dice >= r.dns[0] && dice <= r.dns[1]) newThreat = "dns";
        else if (r.icmp && dice >= r.icmp[0] && dice <= r.icmp[1]) newThreat = "icmp";
        else if (r.fishing && dice >= r.fishing[0] && dice <= r.fishing[1]) newThreat = "fishing";

        if (!newThreat) {
            this.attackCooldown = 10;
            return;
        }
        this.activeThreat = newThreat;
        if (this.radar && ['syn', 'udp', 'dns', 'icmp'].includes(newThreat)) this.radar.trigger(newThreat);

        if (newThreat === 'fishing') {
            this.receiveEmail(true); 
            this.logTerminal(`[IDS 警報] 攔截到可疑郵件，請至「收件匣」或使用 scan-mail 處理！`, "alert");
            this.updateIdsAlert(`[威脅警報] 偵測到社交工程攻擊：惡意釣魚郵件已派發！`);
            this.updateMissionPanel('郵件警報', '收到釣魚郵件，請先掃描或刪除可疑郵件。', '不要點擊信中連結，先使用 scan-mail 或刪除信件。', 2);
        } else {
            this.logTerminal(`[IDS 警報] 偵測到異常活動: ${newThreat.toUpperCase()} 攻擊！可先使用 limit 緩解，並暫停擷取封包以分析來源。`, "alert");
            this.updateIdsAlert(`[流量突增] 偵測到異常 ${newThreat.toUpperCase()} 流量，請進行分析。`);
            this.updateMissionPanel('攻擊偵測', `偵測到 ${newThreat.toUpperCase()} 攻擊，請使用 whois 分析來源。`, '先分析再封鎖，避免誤封正常服務。', 2);
        }
        this.syncPolicyButtons();
    }

    analyzeThreat(target) {
        const cleanTarget = target.toLowerCase().trim();
        if (!cleanTarget) return "請輸入有效的 IP 或協定名稱。";

        const resultEl = document.getElementById('analyzer-result');
        if (resultEl) resultEl.innerHTML = `<span style="color:#888;">[查詢中] 正在透過情報庫檢索 ${cleanTarget}...</span>`;

        setTimeout(() => {
            let outputMsg = "";
            if (cleanTarget === this.activeThreat || cleanTarget === 'ip' || GameDB.maliciousIPs.includes(cleanTarget)) {
                this.analyzedTargets.add(cleanTarget);
                if (cleanTarget === 'syn' || GameDB.maliciousIPs.includes(cleanTarget)) this.analyzedTargets.add('ip'); 
                
                const ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
                if (ipRegex.test(cleanTarget) && !this.discoveredIPs.has(cleanTarget)) {
                    let detectedType = 'APT/Malware';
                    if (this.activeThreat && this.activeThreat !== 'fishing' && this.activeThreat !== 'dns') {
                        detectedType = this.activeThreat.toUpperCase();
                    } else if (cleanTarget === 'dns') {
                        detectedType = 'DNS';
                    }
                    this.discoveredIPs.set(cleanTarget, { status: 'active', type: detectedType });
                    this.renderThreatIntel(); 
                }
                
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
            if (cleanTarget === this.activeThreat || GameDB.maliciousIPs.includes(cleanTarget)) {
                this.updateMissionPanel('分析完成', '透過 whois 分析確認惡意來源，請立即封鎖或執行對應防禦。', '攻擊來源已確認，可以使用 block 或 unblock 解除副作用。', 3);
            }
        }, 800);

        return "情報分析指令已發送...";
    }

    blockAllActive() {
        let blockedCount = 0;
        this.discoveredIPs.forEach((info, ip) => {
            if (info.status === 'active') {
                const res = this.applyBlockAction(ip);
                this.logTerminal(`> block ${ip}`, "user");
                this.logTerminal(res, "system");
                blockedCount++;
            }
        });
        if (blockedCount > 0) {
            this.showNotification(`已一鍵封鎖 ${blockedCount} 個惡意來源！`, "success");
        } else {
            this.logTerminal(`[提示] 目前沒有需要封鎖的活躍惡意 IP。`, "warning");
        }
    }

    applyLimitAction(arg) {
        const cleanArg = arg.toLowerCase().trim();
        if (['udp', 'tcp', 'icmp', 'dns'].includes(cleanArg)) {
            if (!this.activeLimits.includes(cleanArg)) {
                this.activeLimits.push(cleanArg);
            }
            this.updateIdsAlert(`[流量管制] 已對 ${cleanArg.toUpperCase()} 實施全域限速 (Rate Limiting)。`);
            this.updateMissionPanel('緩解措施', `已對 ${cleanArg.toUpperCase()} 流量實施限速，降低攻擊壓力。`, '請接續分析來源，或在必要時封鎖惡意 IP。', 2);
            return `[執行成功] 已限制 ${cleanArg.toUpperCase()} 頻寬。攻擊傷害暫時減弱，整體網路效能微幅下降。請把握時間找出惡意 IP！`;
        }
        return `[提示] 只能對特定協定進行限速 (如 tcp, udp, icmp, dns)。`;
    }

    applyBlockAction(arg) {
        const cleanArg = arg.toLowerCase().trim();
        
        if (GameDB.vipIPs && GameDB.vipIPs.includes(cleanArg)) {
            this.status.crackProgress = Math.min(100, this.status.crackProgress + 25);
            this.triggerErrorFlash();
            this.updateIdsAlert(`[重大客訴] 警告！您誤封鎖了 VIP 或重要營運節點 (${cleanArg})！`);
            return `[錯誤：違反 SOP] 您封鎖了正常業務節點，導致營運癱瘓，信任崩潰！`;
        }

        if (['udp', 'tcp', 'icmp'].includes(cleanArg)) {
            if (!this.activeSideEffects.includes(cleanArg)) {
                this.activeSideEffects.push(cleanArg);
            }
            if (this.activeThreat === cleanArg) {
                this.activeThreat = null;
                this.status.reduceLoad(10);
            }
            
            const ruleId = "RULE-" + Math.floor(Math.random() * 9000 + 1000);
            this.activeRules.unshift({ id: ruleId, target: cleanArg.toUpperCase() + " (ALL)", action: "DROP", time: new Date().toLocaleTimeString() });
            this.renderRulesGUI();
            
            this.updateIdsAlert(`[全域封鎖] 阻斷所有 ${cleanArg.toUpperCase()} 流量。注意副作用！`);
            this.updateMissionPanel('策略執行', `已封鎖 ${cleanArg.toUpperCase()} 通訊，請觀察系統反應並排查其他威脅。`, '如果是誤封請使用 unblock 還原服務。', 3);
            return `[警告] 已粗暴阻斷 ${cleanArg.toUpperCase()}。攻擊暫緩，但業務受損！查出 IP 封鎖後請用 'unblock ${cleanArg}' 解除，以免系統持續耗損。`;
        }

        if (GameDB.maliciousIPs.includes(cleanArg) || this.analyzedTargets.has(cleanArg)) {
            if (cleanArg === 'ip' && this.discoveredIPs.size > 0) {
                let resolved = false;
                this.discoveredIPs.forEach((info, ip) => {
                    if (info.status === 'active') {
                        this.applyBlockAction(ip);
                        resolved = true;
                    }
                });
                if (resolved) {
                    return `[執行成功] 已根據分析結果封鎖偵測到的惡意來源 IP。`; 
                }
            }
            
            this.status.reduceLoad(30); 
            this.activeThreat = null;
            this.analyzedTargets.clear();
            
            this.peaceCooldown = 10;
            this.attackCooldown = Math.max(this.attackCooldown, 12);
            
            if (this.discoveredIPs.has(cleanArg)) {
                let info = this.discoveredIPs.get(cleanArg);
                info.status = 'blocked';
                this.discoveredIPs.set(cleanArg, info);
                this.renderThreatIntel();
            }
            
            const ruleId = "RULE-" + Math.floor(Math.random() * 9000 + 1000);
            this.activeRules.unshift({ id: ruleId, target: cleanArg, action: "DROP", time: new Date().toLocaleTimeString() });
            this.renderRulesGUI();
            
            this.updateIdsAlert(`[精準打擊] 成功攔截惡意來源 ${cleanArg}。`);
            this.updateMissionPanel('威脅解除', `已成功封鎖惡意來源 ${cleanArg}，系統防護恢復穩定。`, '持續監控並留意後續攻擊跡象。若發現更多威脅，請繼續分析與封鎖。', 3);
            
            if (document.getElementById('analyzer-result')) {
                document.getElementById('analyzer-result').innerHTML = "危機已解除，等待下一次分析...";
            }
            return `[執行成功] 防火牆規則已更新：精準阻擋威脅流量！若有限速或全域封鎖，請記得 unblock。`;
        }

        return `[提示] 找不到該目標或指令格式錯誤。`;
    }

    unblockAction(arg) {
        const cleanArg = arg.toLowerCase().trim();
        let msg = "";
        
        const blockIndex = this.activeSideEffects.indexOf(cleanArg);
        if (blockIndex > -1) {
            this.activeSideEffects.splice(blockIndex, 1);
            msg += `[解除封鎖] 已恢復 ${cleanArg.toUpperCase()} 通行。 `;
        }
        
        const limitIndex = this.activeLimits.indexOf(cleanArg);
        if (limitIndex > -1) {
            this.activeLimits.splice(limitIndex, 1);
            msg += `[解除限速] 已恢復 ${cleanArg.toUpperCase()} 頻寬。`;
        }
        
        if (msg) {
            this.updateIdsAlert(msg);
            return `[執行成功] ${msg}`;
        }
        return `[提示] 目前沒有針對 ${cleanArg} 執行全域封鎖或限速。`;
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
            
            this.peaceCooldown = 10;
            this.attackCooldown = Math.max(this.attackCooldown, 12);
            
            const ruleId = "RULE-" + Math.floor(Math.random() * 9000 + 1000);
            this.activeRules.unshift({ id: ruleId, target: "DNS CACHE", action: "FLUSH & RE-ROUTE", time: new Date().toLocaleTimeString() });
            
            this.updateIdsAlert(`[擴展防禦] 執行 DNS 快取淨化 (規則: ${ruleId})。`);
            this.renderRulesGUI();
            this.syncPolicyButtons();
            this.updateMissionPanel('分析完成', 'DNS 污染已解除，等待系統回復正常。', '後續可繼續觀察網路流量與伺服器狀態。', 3);
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
        ['udp', 'icmp', 'dns', 'ip'].forEach(type => {
            const btn = document.getElementById(`btn-mitigate-${type}`);
            if (!btn) return;
            const isActive = this.activeThreat === type || (type === 'ip' && this.activeThreat === 'syn');
            btn.classList.toggle('active-policy', isActive);
        });
    }

    renderThreatIntel() {
        const listEl = document.getElementById('threat-intel-list');
        if (!listEl) return;
        
        if (this.discoveredIPs.size === 0) {
            listEl.innerHTML = '<span style="color:#99a9c8;">尚未發現已知威脅 IP，請先使用 whois 分析可疑來源。</span>';
            return;
        }

        let html = '';
        let hasActive = false;
        
        const groups = {};
        this.discoveredIPs.forEach((info, ip) => {
            if (!groups[info.type]) groups[info.type] = [];
            groups[info.type].push({ ip: ip, status: info.status });
            if (info.status === 'active') hasActive = true;
        });

        if (hasActive) {
            html += `<div style="margin-bottom: 12px;">
                        <button onclick="window.gameManagerInstance.blockAllActive()" style="background: #d93025; color: #fff; border: 1px solid #ff4444; padding: 8px 12px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 13px; width: 100%; transition: 0.2s;">
                            <i class="fas fa-ban"></i> ⚠️ 一鍵封鎖所有已分析危險 IP
                        </button>
                     </div>`;
        }

        for (const [attackType, items] of Object.entries(groups)) {
            html += `<div class="intel-group">
                        <strong class="intel-group-title">${attackType} 攻擊偵測</strong>`;
            
            items.forEach(item => {
                const isBlocked = item.status === 'blocked';
                const action = isBlocked ? '' : `onclick="window.quickCmd('block ${item.ip}')" title="點擊立即單獨封鎖此 IP"`;
                const statusClass = isBlocked ? 'intel-tag blocked' : 'intel-tag';
                const labelText = isBlocked ? `${item.ip} (已封鎖)` : item.ip;
                
                html += `<span class="${statusClass}" ${action}>${labelText}</span>`;
            });
            html += '</div>';
        }

        listEl.innerHTML = html;

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
        this.inbox.unshift({ id: this.mailCounter, sender: mailData.sender, subject: mailData.subject, content: mailData.content, isMalicious: isMalicious, spawnTime: this.status.timer, read: false, punished: false });
        this.renderMailList();
        if (isMalicious && !this.activeThreat) {
            this.updateMissionPanel('郵件警報', '檢測到可疑郵件，請前往收件匣閱讀或執行 scan-mail。', '釣魚信件可能夾帶惡意連結，可先刪除或掃描。', 2);
        }
    }

    scanMailInbox() {
        const maliciousMail = this.inbox.find(mail => mail.isMalicious);
        if (!maliciousMail) {
            this.updateIdsAlert("[信件防禦] 目前沒有可疑釣魚郵件。\n");
            this.updateMissionPanel('待命中', '目前無發現釣魚郵件，繼續監控網路與系統狀態。', '可使用 status 與 netstat 追蹤下一波威脅。', 1);
            return "[掃描完成] 目前沒有可疑郵件。";
        }

        let removedCount = 0;
        this.inbox = this.inbox.filter(mail => {
            if (mail.isMalicious) {
                removedCount++;
                return false;
            }
            return true;
        });

        if (removedCount > 0) {
            this.stats.fishing += removedCount;
            this.status.reduceLoad(15);
            if (this.activeThreat === 'fishing') {
                this.activeThreat = null;
                this.peaceCooldown = 10;
                this.attackCooldown = Math.max(this.attackCooldown, 12);
            }
            this.renderMailList();
            this.updateIdsAlert("[信件防禦] 已掃描並隔離釣魚郵件。系統安全提升。\n");
            this.showNotification(`成功隔離 ${removedCount} 封釣魚郵件。`, "success");
            this.updateMissionPanel('威脅解除', '釣魚威脅已清除，繼續監控系統與內部郵件。', '使用 status 與 netstat 追蹤下一波入侵來源。', 3);
            return `[執行成功] 已隔離 ${removedCount} 封釣魚郵件。`;
        }

        return "[掃描完成] 目前無可疑郵件。";
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
            
            const limit = 45; 
            
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
                        
                        if (this.activeThreat === 'fishing') {
                            this.activeThreat = null;
                            this.peaceCooldown = 10; 
                        }
                    } else this.status.cpu += 5;
                }
            }
            div.className = `mail-item ${mail.read ? 'read' : 'unread'}`;
            div.onclick = () => this.viewMail(mail.id);
            div.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:4px;">
                    <strong>${mail.sender}</strong>
                    ${mail.isMalicious ? '<span class="mail-badge phishing">🚨 釣魚</span>' : '<span class="mail-badge normal">📩 正常</span>'}
                </div>
                <div style="font-size:0.9em; color:#c1d5ff;">${mail.subject}</div>
                <div style="font-size:0.8em; color:#888; margin-top:6px;">${timeWarning.trim()}</div>`;
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
            <div class="mail-header"><h3>${mail.subject}</h3><p><strong>寄件者:</strong> ${mail.sender}</p>${mail.isMalicious ? '<p style="color:#ff7b72; font-weight:bold; margin:8px 0 0 0;">⚠️ 此郵件疑似釣魚或惡意攻擊，請勿直接回覆或點擊連結。</p>' : ''}</div>
            <div class="mail-body"><p>${mail.content.replace(/\n/g, '<br>')}</p></div>
            <div class="mail-actions">
                <button class="btn-delete" onclick="window.handleMail(${mail.id}, 'delete')">🗑️ 刪除信件 (安全)</button>
                <button class="btn-click" onclick="window.handleMail(${mail.id}, 'click')">🔗 點擊連結 / 回覆 (執行)</button>
            </div>`;
    }

    handleMail(id, action) {
        const mailIndex = this.inbox.findIndex(m => m.id === id);
        if (mailIndex === -1) return;
        const mail = this.inbox[mailIndex];

        if (action === 'delete') {
            if (mail.isMalicious && this.activeThreat === 'fishing') {
                this.stats.fishing++; 
                this.activeThreat = null; 
                this.status.reduceLoad(20);
                this.peaceCooldown = 10; 
                
                this.logTerminal(`[系統通知] 成功刪除惡意釣魚郵件。`, "success");
                this.showNotification("成功識別並銷毀釣魚威脅。","success");
                this.updateMissionPanel('威脅解除', '已刪除惡意郵件，繼續觀察系統狀態。', '保持監控並留意新郵件。', 3);
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '';
            this.renderMailList();
        } else if (action === 'click') {
            if (mail.isMalicious) {
                this.triggerErrorFlash(); 
                this.activeThreat = null; 
                this.peaceCooldown = 10; 
                this.attackCooldown = Math.max(this.attackCooldown, 12);
                this.status.applyDamage('Fishing'); 
                this.status.applyDamage('Fishing'); 
                this.logTerminal(`[重大警報] 誤點惡意連結，系統遭感染！`, "danger");
                this.showNotification("警告：誤觸釣魚連結！資源大幅消耗。", 'danger');
                this.updateMissionPanel('系統受損', '誤點惡意郵件，請即刻修復並保持監控。', '建議立即使用 passwd 或防火牆規則降低風險。', 3);
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '';
            this.renderMailList();
        }
    }

    togglePacketCapture() {
        this.isPacketPaused = !this.isPacketPaused;
        const btn = document.getElementById('btn-pause-packet');
        if (btn) {
            btn.innerText = this.isPacketPaused ? "▶️ 繼續擷取" : "⏸️ 暫停擷取";
            btn.style.background = this.isPacketPaused ? "#00ff00" : "#ff9900";
        }
        if (!this.isPacketPaused) this.renderPackets();
    }

    generatePacketUI() {
        const isAttack = this.activeThreat && this.activeThreat !== 'fishing'; 
        const time = new Date().toLocaleTimeString('en-US', {hour12: false});
        
        let allIPs = GameDB.maliciousIPs;
        if (GameDB.vipIPs) allIPs = allIPs.concat(GameDB.vipIPs);
        allIPs = allIPs.concat([`192.168.1.${Math.floor(Math.random()*255)}`]);
        
        const srcIP = isAttack ? GameDB.maliciousIPs[Math.floor(Math.random() * GameDB.maliciousIPs.length)] : allIPs[Math.floor(Math.random() * allIPs.length)];
        
        const normalProtos = ["TCP", "UDP", "HTTP", "DNS", "ICMP"];
        const proto = isAttack ? this.activeThreat.toUpperCase() : normalProtos[Math.floor(Math.random() * normalProtos.length)];
        
        let srcPort = Math.floor(Math.random() * 55535 + 10000).toString();
        let destPort = proto === 'HTTP' ? "80" : proto === 'DNS' ? "53" : "443";
        if (proto === 'ICMP') { srcPort = "-"; destPort = "-"; }

        const len = isAttack ? Math.floor(Math.random() * 1500 + 1000) : Math.floor(Math.random() * 200 + 40);
        
        let payloadData = isAttack && GameDB.payloads.malicious[proto.toLowerCase()] ? GameDB.payloads.malicious[proto.toLowerCase()] : GameDB.payloads.normal[Math.floor(Math.random() * GameDB.payloads.normal.length)];
        
        this.packetHistory.unshift({ time, srcIP, srcPort, destIP: '10.0.0.1', destPort, proto, len, isAttack, payload: payloadData });
        
        if (this.packetHistory.length > 200) this.packetHistory.pop();
        
        if (!this.isPacketPaused) {
            this.renderPackets();
        }
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
        
        filteredPackets.slice(0, 150).forEach(p => {
            const tr = document.createElement('tr');
            tr.className = p.isAttack ? 'packet-danger' : `proto-${p.proto.toLowerCase().replace('.', '')}`;  
            const srcIpClass = p.isAttack || GameDB.maliciousIPs.includes(p.srcIP) ? 'warn-ip' : '';
            tr.innerHTML = `<td>${p.time}</td><td class="${srcIpClass}"><strong>${p.srcIP}</strong></td><td>${p.srcPort}</td><td>${p.destIP}</td><td style="color:#0077aa; font-weight:bold;">${p.destPort}</td><td>${p.proto}</td><td>${p.len}</td><td>${p.isAttack ? 'Malicious' : 'Standard'}</td>`;
            
            tr.onclick = () => {
                document.querySelectorAll('#packet-list tr').forEach(row => row.classList.remove('selected-packet'));
                tr.classList.add('selected-packet');
                
                const payloadContent = document.getElementById('packet-payload-content');
                if (payloadContent) {
                    let hint = "";
                    if (p.isAttack && p.len > 1000) hint = ' <span style="color:#ff4444; font-weight:bold;">⚠️ (長度異常)</span>';
                    payloadContent.innerHTML = `
<div style="margin-bottom: 10px; display: flex; align-items: center;">
    <span style="color:#aaa;">[來源 IP]</span> 
    <span onclick="quickAnalyze('${p.srcIP}')" style="cursor:pointer; background:#ffcc00; padding:2px 8px; border-radius:3px; color:#000; font-weight:bold; font-size:12px; box-shadow: 0 0 5px rgba(255, 204, 0, 0.6); display:inline-block; width: fit-content; margin-left: 8px;">
        ${p.srcIP} 🖱️ 分析
    </span>${hint}
</div>
<div style="margin-bottom: 4px;"><span style="color:#aaa;">[目標埠口]</span> <span style="color:#fff;">${p.destPort}</span></div>
<div style="margin-bottom: 12px;"><span style="color:#aaa;">[封包大小]</span> <span style="color:#fff;">${p.len} bytes</span></div>
<div style="color:#aaa; border-bottom: 1px solid #444; padding-bottom: 4px; margin-bottom: 6px;">[Payload 內容擷取]</div>
<div style="color: ${p.isAttack ? '#ff5555' : '#55ff55'}; word-wrap: break-word; font-size: 13px; background: #111; padding: 8px; border-radius: 4px; border: 1px solid #333;">${p.payload}</div>`;
                }
            };
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
        
        if (type === "user") {
            if (!this.actionLogs) this.actionLogs = [];
            this.actionLogs.push({
                time: new Date().toLocaleTimeString('en-US', {hour12: false}),
                cmd: msg,
                timer_left: this.status ? this.status.timer : 0
            });
        }

        const prefix = type === "user" ? "root@sec-server:~# " : "";
        const color = type === "alert" ? "color:#ffaa00; font-weight:bold;" : type === "success" ? "color:#00ff00; font-weight:bold;" : type === "danger" ? "color:#ff0000; font-weight:bold;" : type === "warning" ? "color:#ff9900;" : "color:#00FF00;";
        out.innerHTML += `<div style="${color} margin-bottom: 4px;">${prefix}${msg}</div>`;
        out.scrollTop = out.scrollHeight;
        if (type === 'alert' || type === 'danger') this.showNotification(msg, type === 'alert' ? 'danger' : 'danger');
    }

    async endGame(reason) {
        clearInterval(this.interval);
        const results = {
            "SUCCESS": { title: "🎉 任務成功", desc: "伺服器安全撐過指定時間，您完美守護了系統！", color: "#00FF00" },
            "FAILURE_RESOURCE": { title: "💥 任務失敗", desc: "防禦失敗：硬體負載達 100% 崩潰！", color: "#FF4444" },
            "FAILURE_CRACKED": { title: "💀 任務失敗", desc: "防禦失敗：密碼已被破解或客訴過多導致信任崩潰！", color: "#FF4444" },
            "FAILURE_OVERLOAD": { title: "⚠️ 任務失敗", desc: "防禦失敗：系統負載未能降至 75% 以下。", color: "#FFA500" }
        };

        const maxTime = this.difficultyConfig ? this.difficultyConfig.time : 240;
        const survivalTime = maxTime - (this.status ? this.status.timer : 0);
        const finalScore = (survivalTime * 10) + (reason === "SUCCESS" ? 1000 : 0);

        try {
            await fetch('api/save_record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    difficulty: window.selectedDifficulty || 0,
                    survival_time: survivalTime,
                    final_score: finalScore,
                    end_reason: reason,
                    action_logs: this.actionLogs || []
                })
            });
            console.log("✅ 遊戲紀錄已成功傳送至資料庫");
        } catch (error) {
            console.error("❌ 存檔失敗:", error);
        }

        setTimeout(() => {
            const titleEl = document.getElementById('game-over-title'); 
            if(titleEl) { titleEl.innerText = results[reason].title; titleEl.style.color = results[reason].color; }
            
            const reasonEl = document.getElementById('game-over-reason'); 
            if(reasonEl) reasonEl.innerText = results[reason].desc;
            
            const modalEl = document.getElementById('game-over-modal'); 
            if(modalEl) modalEl.classList.remove('hidden');
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