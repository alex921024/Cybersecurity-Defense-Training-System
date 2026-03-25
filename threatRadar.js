class ThreatRadar {
    constructor() {
        this.container = null;
        this.isAnimating = false;
        
        // 詳細的攻擊教學資料庫
        this.attackData = {
            'syn': {
                name: 'SYN Flood 攻擊',
                color: '#ff3333',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-handshake-slash', desc: '1. 駭客從 Botnet 發送海量 SYN (連接請求)。' },
                    { actor: 'target', icon: 'fas fa-door-open', desc: '2. 伺服器分配連接槽位，回覆 SYN-ACK。' },
                    { actor: 'hacker', icon: 'fas fa-clock', desc: '3. 駭客故意忽略 SYN-ACK (ACK 超時)，保持半開。' },
                    { actor: 'target', icon: 'fas fa-exclamation-triangle', desc: '4. 伺服器槽位填滿、資源耗盡，拒絕正常用戶。' }
                ]
            },
            'udp': {
                name: 'UDP Flood 攻擊',
                color: '#ff9900',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-terminal', desc: '1. 駭客向目標隨機埠發送大量 UDP 封包。' },
                    { actor: 'target', icon: 'fas fa-search', desc: '2. 伺服器被迫檢查每個埠口是否有對應應用程式。' },
                    { actor: 'target', icon: 'fas fa-reply', desc: '3. 找不到應用程式，產生 ICMP 不可達回覆。' },
                    { actor: 'target', icon: 'fas fa-signal', desc: '4. 頻寬與 CPU 資源被海量無效封包與回覆耗盡。' }
                ]
            },
            'icmp': {
                name: 'ICMP Flood 攻擊',
                color: '#cc33ff',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-volume-up', desc: '1. 駭客從多個節點發送海量 Ping (Echo Request)。' },
                    { actor: 'target', icon: 'fas fa-headset', desc: '2. 伺服器必須強制分配 CPU 資源來處理請求。' },
                    { actor: 'target', icon: 'fas fa-reply-all', desc: '3. 伺服器產生並傳送大量的 Ping 回覆。' },
                    { actor: 'target', icon: 'fas fa-cloud-download-alt', desc: '4. 網路頻寬被惡意 Ping 流量完全淹沒癱瘓。' }
                ]
            },
            'dns': {
                name: 'DNS Amplification 攻擊',
                color: '#00ccff',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-mask', desc: '1. 駭客偽造來源 IP (改成目標伺服器的 IP)。' },
                    { actor: 'hacker', icon: 'fas fa-server', desc: '2. 向公開 DNS 發送大量「查詢所有記錄」請求。' },
                    { actor: 'dns', icon: 'fas fa-expand-arrows-alt', desc: '3. DNS 伺服器將小請求「放大」成巨大的回應封包。' },
                    { actor: 'target', icon: 'fas fa-water', desc: '4. 巨大回應湧向偽造的目標 IP，瞬間癱瘓頻寬。' }
                ]
            }
        };
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initUI());
        } else {
            this.initUI();
        }
    }

    initUI() {
        if (document.getElementById('threat-radar-panel')) return;

        this.container = document.createElement('div');
        this.container.id = 'threat-radar-panel';
        this.container.innerHTML = `
            <div class="radar-header" id="radar-drag-handle" title="按住拖曳視窗">
                <i class="fas fa-arrows-alt"></i> 即時威脅雷達 (教學模式)
            </div>
            <div id="radar-screen">
                <svg id="radar-svg" viewBox="0 0 350 180" preserveAspectRatio="xMidYMid meet">
                    <circle cx="175" cy="90" r="75" stroke="#333" stroke-width="1" fill="none"/>
                    <line x1="175" y1="15" x2="175" y2="165" stroke="#222" stroke-width="1"/>
                    <line x1="100" y1="90" x2="250" y2="90" stroke="#222" stroke-width="1"/>
                    
                    <g id="hacker-node">
                        <text x="60" y="85" fill="#fff" font-size="24" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900">&#xf78c;</text>
                        <text x="40" y="105" fill="#cc3333" font-size="12" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900">&#xf108;</text>
                        <text x="80" y="105" fill="#cc3333" font-size="12" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900">&#xf108;</text>
                        <text x="60" y="125" fill="#aaa" font-size="10" text-anchor="middle">Botnet 控制器</text>
                    </g>

                    <g id="target-node">
                        <text x="290" y="80" fill="#fff" font-size="24" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900">&#xf233;</text>
                        <text x="290" y="100" fill="#00FF00" font-size="10" text-anchor="middle" id="target-label">目標伺服器</text>
                        
                        <rect x="260" y="110" width="60" height="15" rx="3" fill="#222" stroke="#444"/>
                        <text x="260" y="107" fill="#888" font-size="8">連接 Slots</text>
                        <rect x="263" y="113" width="10" height="9" fill="#444" id="slot-1"/>
                        <rect x="276" y="113" width="10" height="9" fill="#444" id="slot-2"/>
                        <rect x="289" y="113" width="10" height="9" fill="#444" id="slot-3"/>
                        <rect x="302" y="113" width="10" height="9" fill="#444" id="slot-4"/>
                    </g>

                    <g id="radar-packets"></g>
                </svg>
            </div>
            <div class="attack-info">
                <div id="attack-type-title">系統安全 - 監控中...</div>
                <div id="attack-steps-container"></div>
            </div>
        `;
        document.body.appendChild(this.container);

        // ✅ 新增：初始化時若不在控制台分頁，先將面板隱藏
        const activeTab = document.querySelector('.tab-content.active');
        if (activeTab && activeTab.id !== 'tab-terminal') {
            this.container.style.display = 'none';
        }
        
        // 啟動拖曳功能
        this._setupDraggable();
    }

    _setupDraggable() {
        const panel = this.container;
        const header = document.getElementById('radar-drag-handle');
        
        let isDragging = false;
        let startX, startY, initialLeft, initialTop;

        header.addEventListener('mousedown', (e) => {
            isDragging = true;
            header.style.cursor = 'grabbing';
            startX = e.clientX;
            startY = e.clientY;
            
            // 獲取當前絕對位置
            const rect = panel.getBoundingClientRect();
            initialLeft = rect.left;
            initialTop = rect.top;

            // 取消 right / bottom 限制，改用 left / top 來控制拖曳
            panel.style.right = 'auto';
            panel.style.bottom = 'auto';
            panel.style.left = initialLeft + 'px';
            panel.style.top = initialTop + 'px';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        const onMouseMove = (e) => {
            if (!isDragging) return;
            // 計算滑鼠移動距離
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            
            // 更新面板位置，並確保不會被拖出畫面外
            let newLeft = initialLeft + dx;
            let newTop = initialTop + dy;
            
            // 邊界保護
            const maxLeft = window.innerWidth - panel.offsetWidth;
            const maxTop = window.innerHeight - panel.offsetHeight;
            newLeft = Math.max(0, Math.min(newLeft, maxLeft));
            newTop = Math.max(0, Math.min(newTop, maxTop));

            panel.style.left = newLeft + 'px';
            panel.style.top = newTop + 'px';
        };

        const onMouseUp = () => {
            isDragging = false;
            header.style.cursor = 'grab';
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
    }

    trigger(threatType) {
        if (!this.attackData[threatType] || this.isAnimating) return;
        
        this.isAnimating = true;
        const data = this.attackData[threatType];
        
        // 更新標題與狀態顏色
        const titleEl = document.getElementById('attack-type-title');
        titleEl.textContent = `偵測到異常：${data.name}`;
        titleEl.style.color = data.color;
        document.getElementById('target-label').setAttribute('fill', data.color);

        // 生成教學步驟卡片
        const stepsContainer = document.getElementById('attack-steps-container');
        stepsContainer.innerHTML = '';
        data.steps.forEach((step, index) => {
            const stepEl = document.createElement('div');
            stepEl.className = 'radar-step';
            stepEl.id = `step-${index}`;
            stepEl.innerHTML = `<i class="${step.icon}"></i> <span>${step.desc}</span>`;
            stepsContainer.appendChild(stepEl);
        });

        // 啟動加長版的動畫序列
        this.playAnimationSequence(threatType, data);
    }

    playAnimationSequence(threatType, data) {
        let currentStep = 0;
        const timePerStep = 4000; // 設定動畫停留時間為 4 秒

        const nextStep = () => {
            if (currentStep >= data.steps.length) {
                // 動畫結束後，停留 5 秒讓玩家閱讀，再重置雷達
                setTimeout(() => this.resetRadar(), 5000); 
                return;
            }

            // 更新左側卡片高亮狀態
            document.querySelectorAll('.radar-step').forEach(el => el.classList.remove('active'));
            const activeStepEl = document.getElementById(`step-${currentStep}`);
            if (activeStepEl) activeStepEl.classList.add('active');

            // 生成詳細的封包與 SVG 動態變化
            this.animateDetailedRadarGraphics(threatType, data.color, currentStep);

            currentStep++;
            setTimeout(nextStep, timePerStep); 
        };

        nextStep();
    }

    animateDetailedRadarGraphics(threatType, color, stepIndex) {
        const svgNS = "http://www.w3.org/2000/svg";
        const packetGroup = document.createElementNS(svgNS, "g");
        
        // 使用 setAttribute 來設定 class
        packetGroup.setAttribute("class", "radar-tutorial-packet-group");

        const packetIcon = document.createElementNS(svgNS, "text");
        packetIcon.setAttribute("fill", color);
        packetIcon.setAttribute("font-size", "22");
        packetIcon.setAttribute("text-anchor", "middle");
        packetIcon.setAttribute("font-family", "'Font Awesome 5 Free'");
        packetIcon.setAttribute("font-weight", "900");
        packetIcon.innerHTML = "&#xf0e0;"; // 信封圖示

        const label = document.createElementNS(svgNS, "text");
        label.setAttribute("fill", "#fff");
        label.setAttribute("font-size", "12");
        label.setAttribute("text-anchor", "middle");
        label.setAttribute("font-weight", "bold");

        // 根據不同攻擊定義封包文字
        const symbolText = {
            'syn': ['[SYN Connect]', '[SYN-ACK]', '忽略 ACK (超時)', '資源崩潰!'],
            'udp': ['[UDP DATA]', '檢查埠口...', '[ICMP Unreachable]', '頻寬滿載!'],
            'icmp': ['[Ping Request]', '處理請求...', '[Ping Reply]', '頻寬被淹沒!'],
            'dns': ['[偽造查詢]', '公開DNS處理', '[DNS 放大封包]', '頻寬癱瘓!']
        };

        label.textContent = symbolText[threatType][stepIndex] || '封包數據';

        // 偶數步是駭客發送(左到右)，奇數步是伺服器(右到左)
        let startX, endX;
        let yOffset = 75; // Y 軸高度

        if (stepIndex === 0) { // 駭客 -> 伺服器
            startX = 100; endX = 250;
        } else if (stepIndex === 1) { // 伺服器處理或回覆 -> 駭客
            startX = 250; endX = 100;
            if(threatType === 'syn') {
                document.getElementById('slot-1').setAttribute('fill', '#ffff00');
            }
        } else if (stepIndex === 2) { // 駭客忽略或第三步
            startX = 100; endX = 250;
            if(threatType === 'syn') {
                packetIcon.innerHTML = "&#xf017;"; // 時鐘
                startX = 175; endX = 175; yOffset = 60; 
                packetGroup.setAttribute("class", "radar-tutorial-packet-group pulse-icon");
            }
        } else if (stepIndex === 3) { // 伺服器崩潰結果
            startX = 250; endX = 250; yOffset = 50;
            packetIcon.innerHTML = "&#xf071;"; // 警告三角形
            packetGroup.setAttribute("class", "radar-tutorial-packet-group pulse-icon");
            
            ['slot-1', 'slot-2', 'slot-3', 'slot-4'].forEach(id => {
                document.getElementById(id).setAttribute('fill', '#ff3333');
            });
        }

        packetIcon.setAttribute("x", startX);
        packetIcon.setAttribute("y", yOffset);
        label.setAttribute("x", startX);
        label.setAttribute("y", yOffset - 15);

        packetGroup.appendChild(packetIcon);
        packetGroup.appendChild(label);
        document.getElementById('radar-packets').appendChild(packetGroup);

        const duration = "3.8s"; // 配合 4 秒的動畫步驟

        if (startX !== endX) { // 需要移動的封包
            const animateIcon = document.createElementNS(svgNS, "animate");
            animateIcon.setAttribute("attributeName", "x");
            animateIcon.setAttribute("from", startX);
            animateIcon.setAttribute("to", endX);
            animateIcon.setAttribute("dur", duration);
            animateIcon.setAttribute("fill", "freeze");
            
            const animateLabel = animateIcon.cloneNode(true);
            
            packetIcon.appendChild(animateIcon);
            label.appendChild(animateLabel);
        }

        setTimeout(() => packetGroup.remove(), 3800);
    }

    resetRadar() {
        this.isAnimating = false;
        const titleEl = document.getElementById('attack-type-title');
        titleEl.textContent = `系統安全 - 監控中...`;
        titleEl.style.color = '#00FF00';
        document.getElementById('target-label').setAttribute('fill', '#00FF00');
        document.getElementById('attack-steps-container').innerHTML = '';
        document.getElementById('radar-packets').innerHTML = '';
        
        ['slot-1', 'slot-2', 'slot-3', 'slot-4'].forEach(id => {
            const slot = document.getElementById(id);
            if(slot) slot.setAttribute('fill', '#444');
        });
    }
}