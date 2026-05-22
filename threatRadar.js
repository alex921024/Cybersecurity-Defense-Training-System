class ThreatRadar {
    constructor() {
        this.container = null;
        this.isAnimating = false;
        
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
                    { actor: 'hacker', icon: 'fas fa-mask', desc: '1. 駭客偽造來源 IP (偽裝成目標伺服器)。' },
                    { actor: 'hacker', icon: 'fas fa-server', desc: '2. 向「公開 DNS」發送大量查詢請求。' },
                    { actor: 'dns', icon: 'fas fa-expand-arrows-alt', desc: '3. 公開 DNS 將小請求「放大」成巨大的回應封包。' },
                    { actor: 'target', icon: 'fas fa-water', desc: '4. 巨大回應反射回目標 IP，瞬間癱瘓目標頻寬。' }
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
                <i class="fas fa-crosshairs"></i> 即時威脅雷達 (教學模式)
            </div>
            
            <div id="radar-screen" style="background-color: #0d1117; position: relative;">
                <svg id="radar-svg" viewBox="0 0 450 260" preserveAspectRatio="xMidYMid meet" style="width: 100%; height: 100%;">
                    <defs>
                        <filter id="glow-red" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                        <filter id="glow-cyan" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>

                    <g opacity="0.3">
                        <line x1="90" y1="170" x2="360" y2="170" stroke="#8b949e" stroke-width="2" stroke-dasharray="5,5" id="path-direct" />
                        <line x1="90" y1="170" x2="225" y2="60" stroke="#8b949e" stroke-width="2" stroke-dasharray="5,5" id="path-up" />
                        <line x1="225" y1="60" x2="360" y2="170" stroke="#8b949e" stroke-width="2" stroke-dasharray="5,5" id="path-down" />
                    </g>
                    
                    <g id="dns-node" transform="translate(225, 60)" opacity="0.15" style="transition: opacity 0.5s;">
                        <text fill="#00ebff" font-size="35" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900" filter="url(#glow-cyan)">&#xf233;</text>
                        <text y="-25" fill="#c9d1d9" font-size="12" text-anchor="middle" font-weight="bold">公開 DNS 伺服器</text>
                    </g>

                    <g id="hacker-node" transform="translate(90, 170)">
                        <text fill="#ff4444" font-size="40" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900" filter="url(#glow-red)">&#xf21b;</text>
                        <text y="35" fill="#c9d1d9" font-size="12" text-anchor="middle" font-weight="bold">Botnet 控制器</text>
                    </g>

                    <g id="target-node" transform="translate(360, 170)">
                        <text fill="#00ebff" font-size="40" text-anchor="middle" font-family="'Font Awesome 5 Free'" font-weight="900" filter="url(#glow-cyan)">&#xf233;</text>
                        <text y="35" fill="#00ebff" font-size="12" text-anchor="middle" font-weight="bold" id="target-label">目標伺服器</text>
                        
                        <text y="52" fill="#8b949e" font-size="9" text-anchor="middle">連接 Slots</text>
                        <g transform="translate(-32, 58)">
                            <rect x="0" y="0" width="14" height="12" fill="#21262d" stroke="#8b949e" stroke-width="1" id="slot-1" rx="2" style="transition: fill 0.3s"/>
                            <rect x="18" y="0" width="14" height="12" fill="#21262d" stroke="#8b949e" stroke-width="1" id="slot-2" rx="2" style="transition: fill 0.3s"/>
                            <rect x="36" y="0" width="14" height="12" fill="#21262d" stroke="#8b949e" stroke-width="1" id="slot-3" rx="2" style="transition: fill 0.3s"/>
                            <rect x="54" y="0" width="14" height="12" fill="#21262d" stroke="#8b949e" stroke-width="1" id="slot-4" rx="2" style="transition: fill 0.3s"/>
                        </g>
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

        const activeTab = document.querySelector('.tab-content.active');
        if (activeTab && activeTab.id !== 'tab-terminal' && activeTab.id !== 'tab-firewall') {
            this.container.style.display = 'none';
        }
        
        this._setupDraggable();
    }

    _setupDraggable() {
        const panel = this.container;
        const header = document.getElementById('radar-drag-handle');
        let isDragging = false, startX, startY, initialLeft, initialTop;

        header.addEventListener('mousedown', (e) => {
            isDragging = true;
            header.style.cursor = 'grabbing';
            startX = e.clientX; startY = e.clientY;
            const rect = panel.getBoundingClientRect();
            initialLeft = rect.left; initialTop = rect.top;

            panel.style.right = 'auto'; panel.style.bottom = 'auto';
            panel.style.left = initialLeft + 'px'; panel.style.top = initialTop + 'px';

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        const onMouseMove = (e) => {
            if (!isDragging) return;
            let newLeft = initialLeft + (e.clientX - startX);
            let newTop = initialTop + (e.clientY - startY);
            
            const maxLeft = window.innerWidth - panel.offsetWidth;
            const maxTop = window.innerHeight - panel.offsetHeight;
            panel.style.left = Math.max(0, Math.min(newLeft, maxLeft)) + 'px';
            panel.style.top = Math.max(0, Math.min(newTop, maxTop)) + 'px';
        };

        const onMouseUp = () => {
            isDragging = false; header.style.cursor = 'grab';
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
    }

    trigger(threatType) {
        if (!this.attackData[threatType] || this.isAnimating) return;
        this.isAnimating = true;
        const data = this.attackData[threatType];
        
        const titleEl = document.getElementById('attack-type-title');
        titleEl.innerHTML = `偵測到異常：<span style="color:${data.color}">${data.name}</span>`;
        document.getElementById('target-label').setAttribute('fill', data.color);

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
        const timePerStep = 4000; 

        const nextStep = () => {
            if (currentStep >= data.steps.length) {
                setTimeout(() => this.resetRadar(), 6000); 
                return;
            }

            document.querySelectorAll('.radar-step').forEach(el => el.classList.remove('active'));
            const activeStepEl = document.getElementById(`step-${currentStep}`);
            if (activeStepEl) activeStepEl.classList.add('active');

            this.animateDetailedRadarGraphics(threatType, data.color, currentStep);

            currentStep++;
            setTimeout(nextStep, timePerStep); 
        };
        nextStep();
    }

    animateDetailedRadarGraphics(threatType, color, stepIndex) {
        const svgNS = "http://www.w3.org/2000/svg";
        const POS_HACKER = { x: 90, y: 170 };
        const POS_TARGET = { x: 360, y: 170 };
        const POS_DNS = { x: 225, y: 60 };

        const dnsNode = document.getElementById('dns-node');
        if (dnsNode) {
            if (threatType === 'dns') dnsNode.setAttribute('opacity', '1');
            else dnsNode.setAttribute('opacity', '0.15');
        }

        const shootPacket = (from, to, iconCode, labelText, pktColor, size = 20, delayMs = 0, offsetY = 0) => {
            setTimeout(() => {
                const packetGroup = document.createElementNS(svgNS, "g");
                packetGroup.setAttribute("class", "radar-tutorial-packet-group");

                const pIcon = document.createElementNS(svgNS, "text");
                pIcon.setAttribute("fill", pktColor);
                pIcon.setAttribute("font-size", size);
                pIcon.setAttribute("text-anchor", "middle");
                pIcon.setAttribute("font-family", "'Font Awesome 5 Free'");
                pIcon.setAttribute("font-weight", "900");
                pIcon.innerHTML = iconCode; 

                const pLabel = document.createElementNS(svgNS, "text");
                pLabel.setAttribute("fill", "#fff");
                pLabel.setAttribute("font-size", "11");
                pLabel.setAttribute("text-anchor", "middle");
                pLabel.setAttribute("font-weight", "bold");
                pLabel.textContent = labelText;

                const startX = from.x; const startY = from.y + offsetY;
                const endX = to.x; const endY = to.y + offsetY;

                pIcon.setAttribute("x", startX); pIcon.setAttribute("y", startY);
                pLabel.setAttribute("x", startX); pLabel.setAttribute("y", startY - (size / 2 + 6));

                packetGroup.appendChild(pIcon); packetGroup.appendChild(pLabel);
                const containerNode = document.getElementById('radar-packets');
                if (containerNode) containerNode.appendChild(packetGroup);

                const duration = "3.5s"; 
                const animateX = document.createElementNS(svgNS, "animate");
                animateX.setAttribute("attributeName", "x");
                animateX.setAttribute("from", startX); animateX.setAttribute("to", endX);
                animateX.setAttribute("dur", duration); animateX.setAttribute("fill", "freeze");
                
                const animateY = document.createElementNS(svgNS, "animate");
                animateY.setAttribute("attributeName", "y");
                animateY.setAttribute("from", startY); animateY.setAttribute("to", endY);
                animateY.setAttribute("dur", duration); animateY.setAttribute("fill", "freeze");

                const animateLabelX = animateX.cloneNode(true);
                const animateLabelY = document.createElementNS(svgNS, "animate");
                animateLabelY.setAttribute("attributeName", "y");
                animateLabelY.setAttribute("from", startY - (size / 2 + 6));
                animateLabelY.setAttribute("to", endY - (size / 2 + 6));
                animateLabelY.setAttribute("dur", duration); animateLabelY.setAttribute("fill", "freeze");

                pIcon.appendChild(animateX); pIcon.appendChild(animateY);
                pLabel.appendChild(animateLabelX); pLabel.appendChild(animateLabelY);

                setTimeout(() => packetGroup.remove(), 3500);
            }, delayMs);
        };

        const pulseNode = (pos, iconCode, labelText, pulseColor, offsetY=0) => {
            const packetGroup = document.createElementNS(svgNS, "g");
            packetGroup.setAttribute("class", "radar-tutorial-packet-group pulse-icon");

            const pIcon = document.createElementNS(svgNS, "text");
            pIcon.setAttribute("fill", pulseColor);
            pIcon.setAttribute("font-size", "28");
            pIcon.setAttribute("text-anchor", "middle");
            pIcon.setAttribute("font-family", "'Font Awesome 5 Free'");
            pIcon.setAttribute("font-weight", "900");
            pIcon.setAttribute("x", pos.x); pIcon.setAttribute("y", pos.y + offsetY);
            pIcon.innerHTML = iconCode;

            const pLabel = document.createElementNS(svgNS, "text");
            pLabel.setAttribute("fill", "#fff");
            pLabel.setAttribute("font-size", "12");
            pLabel.setAttribute("text-anchor", "middle");
            pLabel.setAttribute("font-weight", "bold");
            pLabel.setAttribute("x", pos.x); pLabel.setAttribute("y", pos.y + offsetY - 25);
            pLabel.textContent = labelText;

            packetGroup.appendChild(pIcon); packetGroup.appendChild(pLabel);
            const containerNode = document.getElementById('radar-packets');
            if (containerNode) containerNode.appendChild(packetGroup);

            setTimeout(() => packetGroup.remove(), 3800);
        };

        const updateSlots = (colorsArray) => {
            const slots = ['slot-1', 'slot-2', 'slot-3', 'slot-4'];
            slots.forEach((id, index) => {
                const sEl = document.getElementById(id);
                if (sEl && colorsArray[index]) sEl.setAttribute('fill', colorsArray[index]);
            });
        };

        if (threatType === 'syn') {
            if (stepIndex === 0) {
                shootPacket(POS_HACKER, POS_TARGET, '&#xf0e0;', 'SYN', color, 18, 0, -15);
                shootPacket(POS_HACKER, POS_TARGET, '&#xf0e0;', 'SYN', color, 18, 400, 0);
                shootPacket(POS_HACKER, POS_TARGET, '&#xf0e0;', 'SYN', color, 18, 800, 15);
            } else if (stepIndex === 1) {
                updateSlots(['#ffcc00', '#ffcc00', '#21262d', '#21262d']);
                shootPacket(POS_TARGET, POS_HACKER, '&#xf0e0;', 'SYN-ACK', '#ffcc00', 18, 0, -10);
                shootPacket(POS_TARGET, POS_HACKER, '&#xf0e0;', 'SYN-ACK', '#ffcc00', 18, 500, 10);
            } else if (stepIndex === 2) {
                pulseNode(POS_HACKER, '&#xf017;', '忽略 (超時)', '#8b949e', -30);
                updateSlots(['#ffcc00', '#ffcc00', '#ffcc00', '#21262d']);
            } else if (stepIndex === 3) {
                updateSlots(['#ff4444', '#ff4444', '#ff4444', '#ff4444']);
                pulseNode(POS_TARGET, '&#xf071;', '資源耗盡!', '#ff4444', -40);
            }
        } 
        else if (threatType === 'dns') {
            if (stepIndex === 0) {
                pulseNode(POS_HACKER, '&#xf21b;', '偽造 目標IP', '#00ebff', -30);
            } else if (stepIndex === 1) {
                shootPacket(POS_HACKER, POS_DNS, '&#xf0e0;', 'DNS Query', color, 18, 0, 0);
                shootPacket(POS_HACKER, POS_DNS, '&#xf0e0;', 'DNS Query', color, 18, 400, 10);
            } else if (stepIndex === 2) {
                shootPacket(POS_DNS, POS_TARGET, '&#xf0e0;', 'Huge Response', '#00ebff', 45, 0, 0);
                shootPacket(POS_DNS, POS_TARGET, '&#xf0e0;', 'Huge Response', '#00ebff', 45, 600, 15);
            } else if (stepIndex === 3) {
                pulseNode(POS_TARGET, '&#xf071;', '頻寬癱瘓!', '#ff4444', -40);
                updateSlots(['#ff4444', '#ff4444', '#ff4444', '#ff4444']);
            }
        }
        else if (threatType === 'udp' || threatType === 'icmp') {
            const labelReq = threatType === 'udp' ? 'UDP DATA' : 'Ping Req';
            const labelRep = threatType === 'udp' ? 'ICMP Error' : 'Ping Reply';
            const iconReq = '&#xf0e0;'; 
            
            if (stepIndex === 0) {
                shootPacket(POS_HACKER, POS_TARGET, iconReq, labelReq, color, 18, 0, -20);
                shootPacket(POS_HACKER, POS_TARGET, iconReq, labelReq, color, 18, 300, 0);
                shootPacket(POS_HACKER, POS_TARGET, iconReq, labelReq, color, 18, 600, 20);
            } else if (stepIndex === 1) {
                pulseNode(POS_TARGET, '&#xf002;', '被迫處理...', '#ff9900', -40);
            } else if (stepIndex === 2) {
                shootPacket(POS_TARGET, POS_HACKER, iconReq, labelRep, '#ff9900', 18, 0, -15);
                shootPacket(POS_TARGET, POS_HACKER, iconReq, labelRep, '#ff9900', 18, 400, 15);
            } else if (stepIndex === 3) {
                pulseNode(POS_TARGET, '&#xf071;', '頻寬滿載!', '#ff4444', -40);
                updateSlots(['#ff4444', '#ff4444', '#ff4444', '#ff4444']);
            }
        }
    }

    resetRadar() {
        this.isAnimating = false;
        const titleEl = document.getElementById('attack-type-title');
        if (titleEl) titleEl.textContent = `系統安全 - 監控中...`;
        
        const labelEl = document.getElementById('target-label');
        if (labelEl) labelEl.setAttribute('fill', '#00ebff');
        
        const stepsEl = document.getElementById('attack-steps-container');
        if (stepsEl) stepsEl.innerHTML = '';
        
        const packetsEl = document.getElementById('radar-packets');
        if (packetsEl) packetsEl.innerHTML = '';
        
        const dnsEl = document.getElementById('dns-node');
        if (dnsEl) dnsEl.setAttribute('opacity', '0.15');
        
        ['slot-1', 'slot-2', 'slot-3', 'slot-4'].forEach(id => {
            const slot = document.getElementById(id);
            if(slot) slot.setAttribute('fill', '#21262d');
        });
    }
}