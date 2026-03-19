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
        
        // 郵件系統變數
        this.inbox = [];
        this.mailCounter = 0;

        // Wireshark 歷史封包紀錄陣列
        this.packetHistory = [];

        // 戰報統計資料
        this.stats = { syn: 0, udp: 0, dns: 0, icmp: 0, fishing: 0 };

        // 綁定終端機 Enter 鍵輸入事件
        document.getElementById('cmd-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const val = e.target.value;
                if(val.trim() !== "") this.logTerminal(val, "user");
                const res = this.cli.execute(val);
                if(res) this.logTerminal(res, "system");
                e.target.value = '';
            }
        });

        // 綁定 Wireshark 下拉選單過濾事件
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
        
        // 初始化系統變數
        this.inbox = [];
        this.mailCounter = 0;
        this.packetHistory = []; 
        this.stats = { syn: 0, udp: 0, dns: 0, icmp: 0, fishing: 0 };

        this.logTerminal("System boot successful. Initializing security protocols...", "system");
        this.logTerminal(`開始執行難度等級: ${difficulty}。請隨時注意系統負載與破解進度。`, "alert");
        
        // 遊戲開始時先塞一封正常信件
        this.receiveEmail(false);

        // 啟動主迴圈 (每秒執行一次)
        this.interval = setInterval(() => this.gameLoop(), 1000);
    }

    gameLoop() {
        this.status.timer--;
        
        // 【難度大幅降低版】破解進度變得非常緩慢 (每秒 +0.05% ~ 0.25%)
        this.status.crackProgress += (Math.random() * 0.2 + 0.05); 

        // 【難度大幅降低版】放著不管的懲罰週期從 5 秒拉長到 8 秒
        if (this.activeThreat && this.status.timer % 8 === 0) {
             this.status.applyDamage(this.activeThreat === 'fishing' ? 'Fishing' : 'Network');
             if(this.activeThreat !== 'fishing') {
                 this.logTerminal(`[系統警告] 未處理的網路威脅 (${this.activeThreat.toUpperCase()})！伺服器負載飆升！`, "alert");
             }
        }

        // 【難度大幅降低版】新威脅的觸發間隔從 8 秒拉長到 12 秒
        if (!this.activeThreat && this.status.timer % 12 === 0) {
            this.generateEvent();
        }

        // 偶爾收到正常郵件 (營造真實感)
        if (this.status.timer % 25 === 0 && Math.random() > 0.5) {
            this.receiveEmail(false);
        }

        // 產生並渲染背景封包
        this.generatePacketUI();
        this.updateUI();

        // 檢查勝負條件
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
            if (newThreat === 'fishing') {
                this.receiveEmail(true); // 觸發釣魚信件
                this.logTerminal(`[IDS 警報] 攔截到可疑的外部電子郵件，請至「收件匣」或使用 scan-mail 處理！`, "alert");
            } else {
                this.logTerminal(`[IDS 警報] 偵測到異常活動: ${newThreat.toUpperCase()} 攻擊！請用 netstat 檢查並阻擋。`, "alert");
            }
        }
    }

    // ==========================================
    // 郵件系統邏輯
    // ==========================================
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
            read: false
        };
        this.inbox.unshift(newMail); // 加到陣列最前面
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
            div.className = `mail-item ${mail.read ? 'read' : 'unread'}`;
            div.onclick = () => window.viewMail(mail.id);
            div.innerHTML = `<strong>${mail.sender}</strong><br><span style="font-size:0.9em">${mail.subject}</span>`;
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
            // 玩家選擇正確 (刪除)
            if (mail.isMalicious && this.activeThreat === 'fishing') {
                this.stats.fishing++; // 增加釣魚防禦統計次數
                this.activeThreat = null; 
                this.status.reduceLoad(15);
                this.logTerminal(`[系統通知] 成功刪除惡意釣魚郵件，危機解除。`, "system");
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '<p style="color:#888; text-align:center; margin-top:50px;">信件已刪除</p>';
            this.renderMailList();
        } 
        else if (action === 'click') {
            // 玩家選擇錯誤 (誤點惡意連結)
            if (mail.isMalicious) {
                this.activeThreat = null; 
                this.status.applyDamage('Fishing'); 
                this.status.applyDamage('Fishing'); // 雙重懲罰
                this.logTerminal(`[重大警報] 員工點擊了惡意連結！系統已遭感染，資源嚴重消耗！`, "alert");
                alert("警告：您點擊了釣魚連結，導致系統感染惡意程式碼！資源大幅消耗。");
            } else {
                alert("這是一封正常的信件，已標示為處理完成。");
            }
            this.inbox.splice(mailIndex, 1);
            document.getElementById('mail-viewer-container').innerHTML = '';
            this.renderMailList();
        }
    }

    // ==========================================
    // Wireshark 封包系統邏輯 (加入 Port 模擬)
    // ==========================================
    generatePacketUI() {
        const isAttack = this.activeThreat && this.activeThreat !== 'fishing'; 
        const time = new Date().toLocaleTimeString('en-US', {hour12: false});
        const srcIP = isAttack ? GameDB.maliciousIPs[0] : `192.168.1.${Math.floor(Math.random()*255)}`;
        
        // 混入各種常見的正常協定
        const normalProtos = ["TCP", "TCP", "TLSv1.2", "UDP", "HTTP", "DNS", "ICMP"];
        const proto = isAttack ? this.activeThreat.toUpperCase() : normalProtos[Math.floor(Math.random() * normalProtos.length)];
        
        let srcPort = "";
        let destPort = "";

        // 依據協定產生合理的 Port 號碼
        if (proto === 'ICMP') {
            // ICMP 沒有 Port 的概念
            srcPort = "-";
            destPort = "-";
        } else {
            // 來源端通常是高位址的隨機動態 Port (10000 ~ 65535)
            srcPort = Math.floor(Math.random() * 55535 + 10000).toString();
            
            // 目標端依據服務類型決定 Port
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
        
        // 將包含 Port 的新封包加到陣列最前面
        this.packetHistory.unshift({ time, srcIP, srcPort, destIP: '10.0.0.1', destPort, proto, len, isAttack });
        
        // 背景最多保留最近的 40 筆封包供過濾使用
        if (this.packetHistory.length > 40) {
            this.packetHistory.pop();
        }

        this.renderPackets();
    }

    renderPackets() {
        const list = document.getElementById('packet-list');
        const filterEl = document.getElementById('protocol-filter');
        if (!list) return;
        
        const filter = filterEl ? filterEl.value : 'ALL';
        list.innerHTML = ''; 

        // 依據下拉選單過濾協定
        let filteredPackets = this.packetHistory;
        if (filter !== 'ALL') {
            filteredPackets = this.packetHistory.filter(p => {
                const isMatch = p.proto === filter;
                const isTCPFamily = filter === 'TCP' && (p.proto === 'TLSv1.2' || p.proto === 'HTTP');
                return isMatch || isTCPFamily;
            });
        }

        // 渲染至畫面，加入 srcPort 與 destPort 欄位
        filteredPackets.slice(0, 15).forEach(p => {
            const tr = document.createElement('tr');
            if (p.isAttack) {
                tr.style.color = "#ff4444";
                tr.style.fontWeight = "bold";
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

    // ==========================================
    // 介面更新與遊戲結算
    // ==========================================
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
        updateColor('wifi-load', this.status.wifi);
        
        const crackEl = document.getElementById('crack-progress');
        if (crackEl) {
            crackEl.innerText = Math.floor(this.status.crackProgress);
            crackEl.style.color = this.status.crackProgress > 80 ? "#ff4444" : "#00FF00";
        }

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
        
        // 結算狀態定義與文案
        const results = {
            "SUCCESS": { title: "🎉 任務成功", desc: "伺服器安全撐過指定時間，您完美守護了系統！", color: "#00FF00" },
            "FAILURE_RESOURCE": { title: "💥 任務失敗", desc: "防禦失敗：伺服器硬體資源負載達 100% 崩潰！", color: "#FF4444" },
            "FAILURE_CRACKED": { title: "💀 任務失敗", desc: "防禦失敗：Root 密碼已被字典檔破解，系統控制權喪失！", color: "#FF4444" },
            "FAILURE_OVERLOAD": { title: "⚠️ 任務失敗", desc: "防禦失敗：時間結束，但系統負載未能降至 75% 以下。", color: "#FFA500" }
        };
        
        const finalStatus = results[reason];
        
        setTimeout(() => {
            // 填寫結算標題
            const titleEl = document.getElementById('game-over-title');
            if(titleEl) {
                titleEl.innerText = finalStatus.title;
                titleEl.style.color = finalStatus.color;
            }
            const reasonEl = document.getElementById('game-over-reason');
            if(reasonEl) reasonEl.innerText = finalStatus.desc;

            // 填寫統計數據
            const setStat = (id, val) => { if(document.getElementById(id)) document.getElementById(id).innerText = val; };
            setStat('stat-syn', this.stats.syn);
            setStat('stat-udp', this.stats.udp);
            setStat('stat-dns', this.stats.dns);
            setStat('stat-icmp', this.stats.icmp);
            setStat('stat-fishing', this.stats.fishing);

            // 顯示彈出視窗
            const modalEl = document.getElementById('game-over-modal');
            if(modalEl) modalEl.classList.remove('hidden');
        }, 500); // 延遲半秒確保玩家能看到最後的畫面狀態
    }
}

export default GameManager;