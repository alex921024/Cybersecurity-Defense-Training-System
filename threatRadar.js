class ThreatRadar {
    constructor() {
        this.container = null;
        this.isAnimating = false;
        // 定義攻擊教學資料庫
        this.attackData = {
            'syn': {
                name: 'SYN Flood 攻擊',
                color: '#ff3333',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-terminal', desc: '1. 駭客發送大量 SYN (連接請求)。' },
                    { actor: 'target', icon: 'fas fa-server', desc: '2. 伺服器分配資源，回覆 SYN-ACK。' },
                    { actor: 'hacker', icon: 'fas fa-unlink', desc: '3. 駭客故意不回覆 ACK，保持半開連接。' },
                    { actor: 'target', icon: 'fas fa-microchip', desc: '4. 伺服器連線佇列塞滿，拒絕正常用戶。' }
                ]
            },
            'udp': {
                name: 'UDP Flood 攻擊',
                color: '#ff9900',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-terminal', desc: '1. 駭客向目標隨機埠發送大量 UDP 封包。' },
                    { actor: 'target', icon: 'fas fa-search', desc: '2. 伺服器被迫檢查每個埠口是否有應用程式。' },
                    { actor: 'target', icon: 'fas fa-reply', desc: '3. 找不到應用程式，產生 ICMP 不可達回覆。' },
                    { actor: 'target', icon: 'fas fa-signal', desc: '4. 頻寬與 CPU 資源被這些無效封包耗盡。' }
                ]
            },
            'icmp': {
                name: 'ICMP Flood 攻擊',
                color: '#cc33ff',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-terminal', desc: '1. 駭客發送海量 ICMP Echo Request (Ping)。' },
                    { actor: 'target', icon: 'fas fa-microchip', desc: '2. 伺服器處理並產生對應的 Reply。' },
                    { actor: 'target', icon: 'fas fa-reply-all', desc: '3. 伺服器傳送大量 Ping 回覆。' },
                    { actor: 'target', icon: 'fas fa-cloud-download-alt', desc: '4. 網路頻寬被 Ping 流量完全淹沒。' }
                ]
            },
            'dns': {
                name: 'DNS Amplification 攻擊',
                color: '#00ccff',
                steps: [
                    { actor: 'hacker', icon: 'fas fa-mask', desc: '1. 駭客偽造來源 IP (改成目標伺服器的 IP)。' },
                    { actor: 'hacker', icon: 'fas fa-database', desc: '2. 向公開 DNS 伺服器發送大量查詢請求。' },
                    { actor: 'dns', icon: 'fas fa-expand-arrows-alt', desc: '3. DNS 將小請求「放大」成巨大的回應封包。' },
                    { actor: 'target', icon: 'fas fa-water', desc: '4. 巨大回應湧向目標，瞬間癱瘓網路頻寬。' }
                ]
            }
        };
        
        // 確保 DOM 載入後再初始化 UI
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initUI());
        } else {
            this.initUI();
        }
    }

    initUI() {
        if (document.getElementById('threat-radar-panel')) return; // 避免重複建立

        this.container = document.createElement('div');
        this.container.id = 'threat-radar-panel';
        this.container.innerHTML = `
            <div class="radar-header">
                <i class="fas fa-satellite-dish"></i> 即時威脅雷達
            </div>
            <div id="radar-screen">
                <svg id="radar-svg" viewBox="0 0 300 150">
                    <circle cx="150" cy="75" r="60" stroke="#333" stroke-width="1" fill="none"/>
                    <line x1="150" y1="15" x2="150" y2="135" stroke="#222" stroke-width="1"/>
                    <line x1="90" y1="75" x2="210" y2="75" stroke="#222" stroke-width="1"/>
                    
                    <g id="radar-nodes">
                        <circle cx="50" cy="75" r="15" fill="#111" stroke="#555" stroke-width="2"/>
                        <text x="50" y="79" fill="#fff" font-size="10" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900">&#xf21b;</text>
                        <text x="50" y="105" fill="#aaa" font-size="10" text-anchor="middle">Hacker</text>
                        
                        <circle cx="250" cy="75" r="15" fill="#111" stroke="#00FF00" stroke-width="2" id="target-node"/>
                        <text x="250" y="79" fill="#00FF00" font-size="10" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900">&#xf233;</text>
                        <text x="250" y="105" fill="#00FF00" font-size="10" text-anchor="middle">Server</text>
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
    }

    trigger(threatType) {
        if (!this.attackData[threatType] || this.isAnimating) return;
        
        this.isAnimating = true;
        const data = this.attackData[threatType];
        
        const titleEl = document.getElementById('attack-type-title');
        titleEl.textContent = `偵測到異常：${data.name}`;
        titleEl.style.color = data.color;
        document.getElementById('target-node').setAttribute('stroke', data.color);

        const stepsContainer = document.getElementById('attack-steps-container');
        stepsContainer.innerHTML = '';
        data.steps.forEach((step, index) => {
            const stepEl = document.createElement('div');
            stepEl.className = 'radar-step';
            stepEl.id = `step-${index}`;
            stepEl.innerHTML = `<i class="${step.icon}"></i> <span>${step.desc}</span>`;
            stepsContainer.appendChild(stepEl);
        });

        this.playAnimationSequence(threatType, data);
    }

    playAnimationSequence(threatType, data) {
        let currentStep = 0;

        const nextStep = () => {
            if (currentStep >= data.steps.length) {
                setTimeout(() => this.resetRadar(), 3000); 
                return;
            }

            document.querySelectorAll('.radar-step').forEach(el => el.classList.remove('active'));
            const activeStepEl = document.getElementById(`step-${currentStep}`);
            if (activeStepEl) activeStepEl.classList.add('active');

            this.shootPacket(data.color, currentStep);

            currentStep++;
            setTimeout(nextStep, 1500); 
        };

        nextStep();
    }

    shootPacket(color, stepIndex) {
        const svgNS = "http://www.w3.org/2000/svg";
        const packet = document.createElementNS(svgNS, "circle");
        packet.setAttribute("r", "4");
        packet.setAttribute("fill", color);

        let startX, endX;
        // 偶數步驟通常是駭客發送，奇數是伺服器回應
        if (stepIndex % 2 === 0) {
            startX = 65; endX = 235; // Hacker -> Server
        } else {
            startX = 235; endX = 65; // Server -> Hacker
        }

        packet.setAttribute("cx", startX);
        packet.setAttribute("cy", 75);
        document.getElementById('radar-packets').appendChild(packet);

        const animate = document.createElementNS(svgNS, "animate");
        animate.setAttribute("attributeName", "cx");
        animate.setAttribute("from", startX);
        animate.setAttribute("to", endX);
        animate.setAttribute("dur", "0.8s");
        animate.setAttribute("fill", "freeze");
        packet.appendChild(animate);

        setTimeout(() => packet.remove(), 800);
    }

    resetRadar() {
        this.isAnimating = false;
        const titleEl = document.getElementById('attack-type-title');
        titleEl.textContent = `系統安全 - 監控中...`;
        titleEl.style.color = '#00FF00';
        document.getElementById('target-node').setAttribute('stroke', '#00FF00');
        document.getElementById('attack-steps-container').innerHTML = '';
        document.getElementById('radar-packets').innerHTML = '';
    }
}