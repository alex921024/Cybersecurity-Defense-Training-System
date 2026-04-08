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
        if (filterEl) {
            filterEl.addEventListener('change', () => this.renderPackets());
        }
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
        this.stats = { syn: 0, udp: 0, dns: 0, icmp: 0, fishing: 0 };

        this.logTerminal("System boot successful. Initializing security protocols...", "system");
        this.logTerminal(`開始執行難度等級: ${difficulty}。請隨時注意系統負載與破解進度。`, "alert");
        
        this.receiveEmail(false);
        this.initCharts();

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
            
            if (this.activeThreat === 'fishing') {
                this.triggerErrorFlash();
            } else {
                this.logTerminal(`[系統警告] 未處理的網路威脅 (${this.activeThreat.toUpperCase()})！伺服器負載飆升！`, "alert");
            }
        }

        if (mailBtn) {
            if (this.activeThreat === 'fishing') {
                mailBtn.classList.add('mail-warning');
            } else {
                mailBtn.classList.remove('mail-warning');
            }
        }
        } else {
        if (mailBtn) mailBtn.classList.remove('mail-warning');
        }

        if (!this.activeThreat && this.status.timer % 12 === 0) {
            this.generateEvent();
        }

        if (this.status.timer % 25 === 0 && Math.random() > 0.5) {
            this.receiveEmail(false);
        }

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
            
            if (this.radar && ['syn', 'udp', 'dns', 'icmp'].includes(newThreat)) {
                this.radar.trigger(newThreat);
            }

            if (newThreat === 'fishing') {
                this.receiveEmail(true); 
                this.logTerminal(`[IDS 警報] 攔截到可疑的外部電子郵件，請至「收件匣」或使用 scan-mail 處理！`, "alert");
            } else {
                this.logTerminal(`[IDS 警報] 偵測到異常活動: ${newThreat.toUpperCase()} 攻擊！請用 netstat 檢查並阻擋。`, "alert");
            }
        }
    }

    receiveEmail(isMalicious) {
        this.mailCounter++;
        const pool = isMalicious ? GameDB.emails.malicious : GameDB.emails.normal;
        const mailData = pool[Math.floor(Math.random() * pool.length)];
        
        const newMail = {
            id: this.mailCounter,
            sender: mailData.sender,
            subject: mailData.subject,
            content: mailData.content,
            isMalicious: isMalicious,
            spawnTime: this.status.timer,
            read: false
        };
        this.inbox.unshift(newMail); 
        this.renderMailList();
    }
    // 郵件列表渲染，包含過期警告與樣式變化
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
            if (remaining >= 0) {
                timeWarning = ` <span style="color: #FF0000;"> ${remaining}s</span>`;
                }else {
                timeWarning = ` <span style="color: #888;"> 已過期</span>`;
                if (!mail.punished) { 
                    mail.punished = true;
                    // 執行你的警告動作
                    this.logTerminal(`[系統通知] 信件已過期。`, "alert");
                    // 如果是惡意郵件觸發畫面閃爍
                    if (mail.isMalicious) {
                            this.triggerErrorFlash();
                        // 釣魚郵件過期扣更多的 CPU 或血量 
                        this.status.applyDamage('Fishing');
                        this.logTerminal(`[重大警報] 由於信件未及時處理，負載大幅度提升。`, "System"); 
                    }else {
                    // 一般郵件過期扣一點點 CPU 或血量
                    this.logTerminal(`[重大警報] 由於信件未及時處理，負載提升。`, "System");
                    this.status.cpu += 5;
                    }
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
        if (!viewer) return;
        
        viewer.innerHTML = `
            <div class="mail-header">
                <h3>${mail.subject}</h3>
                <p><strong>寄件者:</strong> ${mail.sender}</p>
            </div>
            <div class="mail-body">
                <p>${mail.content.replace(/\n/g, '<br>')}</p>
            </div>
            <div class="mail-actions">
                <button class="btn-delete" onclick="handleMail(${mail.id}, 'delete')">🗑️ 刪除信件 (安全)</button>
                <button class="btn-click" onclick="handleMail(${mail.id}, 'click')">🔗 點擊連結 / 回覆 (執行)</button>
            </div>
        `;
    }

    handleMail(id, action) {
        const mailIndex = this.inbox.findIndex(m => m.id === id);
        if (mailIndex === -1) return;
        const mail = this.inbox[mailIndex];

        if (action === 'delete') {
            if (mail.isMalicious && this.activeThreat === 'fishing') {
                this.stats.fishing++; 
                this.activeThreat = null; 
                this.status.reduceLoad(15);
                this.logTerminal(`[系統通知] 成功刪除惡意釣魚郵件，危機解除。`, "success");
                this.showNotification("這是一封惡意釣魚郵件，已標示為處理完成。","success");
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '<p style="color:#888; text-align:center; margin-top:50px;">信件已刪除</p>';
            this.renderMailList();
        } 
        else if (action === 'click') {
            if (mail.isMalicious) {
                this.triggerErrorFlash();
                this.activeThreat = null; 
                this.status.applyDamage('Fishing'); 
                this.status.applyDamage('Fishing'); 
                this.logTerminal(`[重大警報] 員工點擊了惡意連結！系統已遭感染，資源嚴重消耗！`, "danger");
                this.showNotification("警告：您點擊了釣魚連結，導致系統感染惡意程式碼！資源大幅消耗。");
            } else {
                this.showNotification("這是一封正常的信件，已標示為處理完成。","success");
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '';
            this.renderMailList();
        }
    }

    generatePacketUI() {
        const isAttack = this.activeThreat && this.activeThreat !== 'fishing'; 
        const time = new Date().toLocaleTimeString('en-US', {hour12: false});
        const srcIP = isAttack ? GameDB.maliciousIPs[0] : `192.168.1.${Math.floor(Math.random()*255)}`;
        const normalProtos = ["TCP", "TCP", "TLSv1.2", "UDP", "HTTP", "DNS", "ICMP"];
        const proto = isAttack ? this.activeThreat.toUpperCase() : normalProtos[Math.floor(Math.random() * normalProtos.length)];
        
        let srcPort = "-";
        let destPort = "-";

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
            filteredPackets = this.packetHistory.filter(p => {
                const isMatch = p.proto === filter;
                const isTCPFamily = filter === 'TCP' && (p.proto === 'TLSv1.2' || p.proto === 'HTTP');
                return isMatch || isTCPFamily;
            });
        }

        filteredPackets.slice(0, 15).forEach(p => {
            const tr = document.createElement('tr');
            const protoClass = `proto-${p.proto.toLowerCase().replace('.', '')}`;
            tr.classList.add(protoClass);
            if (p.isAttack) {
            tr.classList.add('packet-danger');
            } else {
            tr.className = protoClass;  
            }
            tr.innerHTML = `<td>${p.time}</td>
                            <td>${p.srcIP}</td>
                            <td>${p.srcPort}</td>
                            <td>${p.destIP}</td>
                            <td style="color: #00ffff;">${p.destPort}</td>
                            <td>${p.proto}</td>
                            <td>${p.len}</td>
                            <td>${p.isAttack ? 'Malicious Payload' : 'Standard Query'}</td>`;
            list.appendChild(tr);
        });
    }

    updateUI() {
        const updateColor = (id, val) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerText = Math.floor(val);
            el.className = val > 80 ? "danger" : val > 60 ? "warning" : "safe";
        };
        updateColor('cpu-load', this.status.cpu);
        updateColor('gpu-load', this.status.gpu);
        updateColor('ram-load', this.status.ram);
        
        const crackEl = document.getElementById('crack-progress');
        if (crackEl) crackEl.innerText = Math.floor(this.status.crackProgress);
        
        const timerEl = document.getElementById('timer');
        if (timerEl) timerEl.innerText = this.status.timer;
    }

    logTerminal(msg, type) {
        const out = document.getElementById('terminal-output');
        if (!out) return;
        const prefix = type === "user" ? "root@sec-server:~# " : "";
        const color = type === "alert" ? "color: #ffaa00; font-weight: bold;" : "color: #00FF00;";
        out.innerHTML += `<div style="${color} margin-bottom: 4px;">${prefix}${msg}</div>`;
        out.scrollTop = out.scrollHeight;
    }

    endGame(reason) {
        clearInterval(this.interval);
        const results = {
            "SUCCESS": { title: "🎉 任務成功", desc: "伺服器安全撐過指定時間，您完美守護了系統！", color: "#00FF00" },
            "FAILURE_RESOURCE": { title: "💥 任務失敗", desc: "防禦失敗：伺服器硬體資源負載達 100% 崩潰！", color: "#FF4444" },
            "FAILURE_CRACKED": { title: "💀 任務失敗", desc: "防禦失敗：Root 密碼已被字典檔破解，系統控制權喪失！", color: "#FF4444" },
            "FAILURE_OVERLOAD": { title: "⚠️ 任務失敗", desc: "防禦失敗：時間結束，但系統負載未能降至 75% 以下。", color: "#FFA500" }
        };
        
        const finalStatus = results[reason];
        
        setTimeout(() => {
            const titleEl = document.getElementById('game-over-title');
            if(titleEl) {
                titleEl.innerText = finalStatus.title;
                titleEl.style.color = finalStatus.color;
            }
            const reasonEl = document.getElementById('game-over-reason');
            if(reasonEl) reasonEl.innerText = finalStatus.desc;
            const modalEl = document.getElementById('game-over-modal');
            if(modalEl) modalEl.classList.remove('hidden');
        }, 500); 
    }   

    initCharts() {
        this.charts.cpu = this.createLineChart('cpuChart', '#00ebff');
        this.charts.gpu = this.createLineChart('gpuChart', '#00ff00');
        this.charts.ram = this.createLineChart('ramChart', '#ffff00'); 
        this.charts.hack = this.createLineChart('hackChart', '#ff3333');
    }

    createLineChart(elementId, lineColor) {
        const ctx = document.getElementById(elementId).getContext('2d');
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: [], 
                datasets: [{
                    data: [],
                    borderColor: lineColor,
                    backgroundColor: 'rgba(0,0,0,0)', 
                    borderWidth: 1.5,
                    tension: 0, 
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false, 
                scales: {
                    x: { display: false },
                    y: { min: 0, max: 100, ticks: { display: false }, grid: { color: '#222' } }
                },
                plugins: { legend: { display: false } } 
            }
        });
    }

    updateAllCharts(cpuVal, gpuVal, ramVal, hackVal) {
        const chartKeys = ['cpu', 'gpu', 'ram', 'hack'];
        const baseVals = { cpu: cpuVal, gpu: gpuVal, ram: ramVal, hack: hackVal };

        chartKeys.forEach(key => {
            const chartObj = this.charts[key];
            if (!chartObj) return;

            const noise = (Math.random() * 2 - 1); 
            let finalVal = baseVals[key] + noise;
            finalVal = Math.max(0, Math.min(100, finalVal)); 

            chartObj.data.labels.push(''); 
            chartObj.data.datasets[0].data.push(finalVal); 
            
            if (chartObj.data.labels.length > this.maxDataPoints) {
                chartObj.data.labels.shift();
                chartObj.data.datasets[0].data.shift();
            }
            chartObj.update('none');
        });
    }
    showNotification(message, type = 'danger') {
    let container = document.getElementById('game-notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'game-notification-container';
        document.getElementById('game-screen').appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `game-toast ${type}`; 
    const header = type === 'success' ? '[系統]' : '[警告]';
    toast.innerHTML = `<strong>${header}</strong> ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('toast-fade-out');
        setTimeout(() => toast.remove(), 500);
    }, 3000);
    }

    triggerErrorFlash() {
    const gameEl = document.getElementById('game-screen');
    
    if (gameEl) {
        gameEl.classList.remove('border-flash-red');
        void gameEl.offsetWidth;
        gameEl.classList.add('border-flash-red');
        }
    }
    
}

export default GameManager;