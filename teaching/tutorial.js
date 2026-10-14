// 全域切換分頁功能
function switchTutorialTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    document.getElementById(tabId).classList.add('active');

    const btnId = "menu-" + tabId;
    if (document.getElementById(btnId)) {
        document.getElementById(btnId).classList.add('active');
    }
}
window.switchTutorialTab = switchTutorialTab;

const TUTORIAL_TARGET_IP = "45.22.19.8";

// 固定正常流量資料 
const FIXED_NORMAL_PACKETS = [
    { time: "22:31:39", srcIp: "168.95.1.1",    srcPort: "39672", dstIp: "10.0.0.1", dstPort: "443", protocol: "TCP",  length: "165", status: "Standard", payload: "TLSv1.2 Application Data (Encrypted)..." },
    { time: "22:31:38", srcIp: "10.0.0.5",      srcPort: "64158", dstIp: "10.0.0.1", dstPort: "80",  protocol: "HTTP", length: "150", status: "Standard", payload: "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n<html><body>Welcome...</body></html>" },
    { time: "22:31:37", srcIp: "192.168.1.38",  srcPort: "33923", dstIp: "10.0.0.1", dstPort: "443", protocol: "UDP",  length: "136", status: "Standard", payload: "UDP Client Packet Data..." },
    { time: "22:31:36", srcIp: "192.168.1.100", srcPort: "24675", dstIp: "10.0.0.1", dstPort: "443", protocol: "UDP",  length: "104", status: "Standard", payload: "UDP Session Data..." },
    { time: "22:31:35", srcIp: "10.0.0.5",      srcPort: "-",     dstIp: "10.0.0.1", dstPort: "-",   protocol: "ICMP", length: "170", status: "Standard", payload: "ICMP Echo Request (id=0x1234, seq=1, ttl=64)..." }
];
function renderBlockedThreatIntel(ip = TUTORIAL_TARGET_IP, attackType = "UDP 攻擊偵測") {
    const threatList = document.getElementById('threat-intel-list');
    if (threatList) {
        threatList.innerHTML = `
            <div class="blocked-intel-container">
                <div class="blocked-intel-title">${attackType}</div>
                <div class="blocked-intel-tags">
                    <span class="blocked-intel-tag">${ip} (已封鎖)</span>
                </div>
            </div>
        `;
    }
}

// 教學關卡與互動邏輯
const tutorialSteps = [
    {
        title: "歡迎進入 SOC 模擬指揮中心",
        desc: "你可以透過點擊左側導覽列在各分頁間切換。",
        action: () => { clearHighlights(); switchTutorialTab('tab-firewall'); }
    },
    {
        title: "熟悉流程",
        desc: "現在要帶你熟悉遊戲流程。",
        action: () => { clearHighlights(); switchTutorialTab('tab-firewall'); }
    },
    {
        title: "監控IP",
        desc: "畫面上的『即時監控流(Wireshark)』，系統會將伺服器接收到的每一筆連線以封包的形式即時呈現，是你需要觀察的核心。",
        action: () => { 
            clearHighlights(); 
            switchTutorialTab('tab-firewall'); 
            const wiresharkSec = document.getElementById('analysis-section');
            if (wiresharkSec) wiresharkSec.classList.add('tutorial-highlight');
        }
    },
    {
        title: "指令控制台",
        desc: "『指令控制台(root@sec-server:~#)』用於分析(status、netstat、whois)、威脅處置(limit、block、scan-mail)、清理復原(flush-dns、passwd、clear)等工作。",
        action: () => {
            clearHighlights();
            const terminalSec = document.getElementById('terminal-section');
            if (terminalSec) terminalSec.classList.add('tutorial-highlight');
        }
    },
    {
        title: "威脅分析",
        desc: "現在請於『指令控制台(root@sec-server:~#)』下方指令按鈕在正式關卡中也可直接使用，不過先試著在輸入框著打入 'whois udp' ，並按下 Enter鍵 進行分析!",
        interactive: true,
        AtargetCmdInput: 'cmd-input',
        expectedTextCmd: 'whois udp',
        action: () => {
            clearHighlights();
            const cmdInput = document.getElementById('cmd-input');
            if (cmdInput) {
                cmdInput.disabled = false;
                cmdInput.focus();
                cmdInput.classList.add('tutorial-highlight');
                cmdInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    },
    {
        title: "正常情況",
        desc: "如果分析後沒有發現威脅，『威脅情報分析儀(sop 認證)』的回報顯示安全",
        action: () => { 
            clearHighlights(); 
            switchTutorialTab('tab-firewall');
            const alertCard = document.getElementById('guide-alert-card');
            const analysisCard = document.getElementById('guide-analysis-card');
            if (alertCard) alertCard.classList.add('tutorial-highlight');
            if (analysisCard) analysisCard.classList.add('tutorial-highlight');
        }
    },
    {
        title: "異常狀態(IDS)",
        desc: "當出現異常情況時， 『IDS 即時警報日誌』 會回報當前情況問題。",
        action: () => { 
            clearHighlights();
            
            appendMaliciousPacket();
            const idsLogBox = document.getElementById('ids-alert-log');
            if (idsLogBox) {
                const now = new Date();
                const timeStr = now.toTimeString().split(' ')[0]; 
                idsLogBox.innerHTML = `<div style="color: #ffe08a; font-weight: bold;">[${timeStr}] [流量突增] 偵測到異常 UDP 流量，請進行分析！</div>` + idsLogBox.innerHTML;
            }
            const alertCard = document.getElementById('guide-alert-card');
            if (alertCard) alertCard.classList.add('tutorial-highlight');
        }
    },
    {
        title: "異常狀態(IP)",
        desc: `看來是 UDP 流量異常，請選擇異常封包並按下 '[來源 IP] ${TUTORIAL_TARGET_IP} [分析]'。`,
        interactive: true,
        targetWireshark: true,
        action: () => {
            clearHighlights();
            const analysisSec = document.getElementById('analysis-section');
            if (analysisSec) analysisSec.classList.add('tutorial-highlight');
            initNormalWiresharkPackets();
            appendMaliciousPacket();
        }
    },
    {
        title: "異常狀態分析(root)",
        desc: "當按下分析後『指令控制台(root@sec-server:~#)』會幫你打好要分析的指令這時只要按 Enter鍵就好。",
        interactive: true,
        BtargetCmdInput: 'cmd-input',
        expectedTextCmd: `whois ${TUTORIAL_TARGET_IP}`,
        action: () => {
            clearHighlights();
            const cmdInput = document.getElementById('cmd-input');
            if (cmdInput) {
                cmdInput.disabled = false;
                cmdInput.focus();
                cmdInput.classList.add('tutorial-highlight');
            }
        }
    },
    {
        title: "策略快捷防禦一鍵阻斷",
        interactive: true,
        desc: "現在分析結果已出爐！請點擊右下角『策略快捷防禦開關』中的『阻斷 UDP 流量』，或者上方的『一鍵封鎖所有已分析危險 IP』按鈕！",
        targetBtnId: 'btn-mitigate-udp',
        targetBlockAllBtn: true,
        action: () => {
            clearHighlights();

            const btn = document.getElementById('btn-mitigate-udp');
            const card = document.getElementById('guide-control-card');
            if (btn) {
                btn.disabled = false;
                btn.classList.add('tutorial-highlight');
            }
            if (card) {
                card.classList.add('tutorial-highlight');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            const intelCard = document.getElementById('guide-intel-card');
            const blockBtn = document.getElementById('btn-block-all-intel');
            if (intelCard) intelCard.classList.add('tutorial-highlight');
            if (blockBtn) blockBtn.classList.add('tutorial-highlight');
        }
    },
    {
        title: "注意",
        desc: "現在已成功解除危機！ 不過要注意在無分析情況下使用暴力阻斷同時也回引響到正常的IP封包。",
        action: () => { 
            clearHighlights();
            renderBlockedThreatIntel(TUTORIAL_TARGET_IP, "UDP 攻擊偵測");
        }
    },
    {
        title: "接下來到系統狀態吧",
        desc: "請點擊左側側邊欄的『系統狀態』按鈕以檢視伺服器狀況。",
        interactive: true,
        targetTabId: 'menu-tab-status',
        action: () => {
            clearHighlights();
            const statusMenuBtn = document.getElementById('menu-tab-status');
            if (statusMenuBtn) {
                statusMenuBtn.classList.add('tutorial-highlight');
            }
        }
    },
    {
        title: "檢視 系統狀態",
        desc: "這是你目前所需要堅守的時間。",
        action: () => {
            clearHighlights();
            const timerBanner = document.querySelector('.time-banner');
            if (timerBanner) {
                timerBanner.classList.add('tutorial-highlight');
            }
        }
    },
    {
        title: "檢視 系統狀態",
        desc: "系統密碼的更新只用於本局的機制，與使用者帳號無關。",
        action: () => {
            clearHighlights();
            const crackBox = document.querySelector('.danger-box');
            if (crackBox) {
                crackBox.classList.add('tutorial-highlight');
            }
        }
    },
    {
        title: "檢視 系統狀態",
        desc: "當字典破解進度過高時本局將直接失敗，所以請多加留意！",
        action: () => {
            clearHighlights();
            const crackBox = document.querySelector('.danger-box');
            if (crackBox) {
                crackBox.classList.add('tutorial-highlight');
            }
        }
    },
    {
        title: "檢視 系統狀態",
        desc: "這裡也可同時觀察到目前電腦的負載情況。",
        action: () => {
            clearHighlights();
            const monitorBoxes = document.querySelectorAll('.monitor-grid .chart-box:not(.danger-box)');
            monitorBoxes.forEach(box => box.classList.add('tutorial-highlight'));
        }
    },
    {
        title: "接下來到收件匣",
        desc: "請點擊左側側邊欄的『收件匣』檢視信件。",
        interactive: true,
        targetTabId: 'menu-tab-mailbox',
        action: () => {
            clearHighlights();
            const mailTab = document.getElementById('menu-tab-mailbox');
            if (mailTab) mailTab.classList.add('tutorial-highlight');
        }
    },
    {
        title: "檢視/判斷 收件匣",
        desc: "收件匣郵件中可能藏有相關訊息或釣魚信件，請點選列表中的『教學關卡用信件』查看詳情。",
        interactive: true,
        targetMailId: 'mail-cat',
        action: () => {
            clearHighlights();
            switchTutorialTab('tab-mailbox');
            renderMailList();
            const targetMailEl = document.getElementById('mail-item-mail-cat') || document.querySelector('.mail-item');
            if (targetMailEl) targetMailEl.classList.add('tutorial-highlight');
        }
    },
    {
        title: "信件處置對策",
        desc: "確認信件內容後，請選擇適當處理方式。",
        interactive: true,
        targetActionButtons: true,
        action: () => {
            clearHighlights();
            const btnGroup = document.getElementById('mail-action-buttons');
            if (btnGroup) btnGroup.classList.add('tutorial-highlight');
        }
    },
    {
        title: "使用者訓練完成",
        desc: "做得非常好！你已成功完成 SOC 模擬防禦演練，點擊完成訓練回到主選單吧！",
        action: () => { clearHighlights(); }
    }
];

let currentStep = 0;
let typeTimer = null;

function clearHighlights() {
    document.querySelectorAll('.tutorial-highlight').forEach(el => el.classList.remove('tutorial-highlight'));
}

function typeWriter(elementId, text, speed = 20) {
    const element = document.getElementById(elementId);
    if (!element) return;
    element.innerHTML = "";
    let i = 0;
    if (typeTimer) clearInterval(typeTimer);
    typeTimer = setInterval(() => {
        if (i < text.length) {
            element.innerHTML += text.charAt(i);
            i++;
        } else {
            clearInterval(typeTimer);
        }
    }, speed);
}

function updateUI() {
    document.body.classList.add('tutorial-locked');

    const step = tutorialSteps[currentStep];
    const titleEl = document.getElementById('tutorial-title');
    if (titleEl) titleEl.innerText = step.title;

    typeWriter('tutorial-desc', step.desc, 15);

    const nextBtn = document.getElementById('next-btn');

    if (nextBtn) {
        if (step.interactive) {
            nextBtn.style.display = 'none';
            setupInteraction(step);
        } else {
            nextBtn.style.display = 'inline-block';
            if (currentStep === tutorialSteps.length - 1) {
                nextBtn.innerText = "完成訓練";
            } else {
                nextBtn.innerText = "下一步";
            }
        }
    }

    if (step.action) step.action();
}

function renderMailList() {
    const mailContainer = document.getElementById('mail-list-container');
    if (!mailContainer) return;
    mailContainer.innerHTML = `
        <div id="mail-item-mail-cat" class="mail-item unread" style="cursor: pointer;">
            <div style="margin-top:5px; font-weight:bold;">教學關卡用信件</div>
            <div style="font-size:12px; color:#aaa; margin-top:3px;">寄件者: 教官的貓</div>
        </div>
    `;
    const mailItem = document.getElementById('mail-item-mail-cat');
    if (mailItem) {
        mailItem.addEventListener('click', () => {
            const mailViewer = document.getElementById('mail-viewer-container');
            if (mailViewer) {
                mailViewer.innerHTML = `
                    <div class="mail-header"><h3>主旨：教學關卡用信件</h3></div>
                    <div style="font-size: 14px; color: #aaa; margin-top: 5px; margin-bottom: 15px;">寄件者：<span style="color: #fff;">教官的貓</span></div>
                    <p style="color: #b8c7de; font-size:14px; line-height:1.6; margin-top:15px;">
                        教學關卡用信件(身為一隻貓不會在意你是否接受)
                    </p>
                    <div id="mail-action-buttons" style="margin-top: 20px;">
                        <button class="cmd-tag" style="background:#444; color:#fff; cursor:pointer; padding:6px 12px; margin-right:10px;">刪除信件 (安全)</button>
                        <button class="cmd-tag" style="background:#00ebff; color:#000; cursor:pointer; padding:6px 12px;">點擊連結 / 回覆 (執行)</button>
                    </div>
                `;
            }
            if (tutorialSteps[currentStep] && tutorialSteps[currentStep].targetMailId === 'mail-cat') {
                unlockAndNext();
            }
        });
    }
}

// 綁定互動事件
function setupInteraction(step) {
    if (step.AtargetCmdInput) {
        const cmdInput = document.getElementById(step.AtargetCmdInput);
        if (cmdInput) {
            const handleCmdKeypress = (e) => {
                if (e.key === 'Enter') {
                    if (cmdInput.value.trim().toLowerCase() === step.expectedTextCmd.toLowerCase()) {
                        cmdInput.disabled = true;
                        cmdInput.removeEventListener('keypress', handleCmdKeypress);

                        const terminalOutput = document.getElementById('terminal-output');
                        if (terminalOutput) {
                            terminalOutput.innerHTML += `<div>> ${cmdInput.value}</div>`;
                            terminalOutput.innerHTML += `<div style="color: #00FF00;">root@sec-server:~# whois udp</div>`;
                            terminalOutput.innerHTML += `<div style="color: #00FF00;">情報分析指令已發送...</div>`;
                        }

                        const analyzerResult = document.getElementById('analyzer-result');
                        if (analyzerResult) {
                            analyzerResult.innerHTML = `<span style="color: #494946;">[查詢中] 正在透過情報庫檢索 udp...</span>`;
                        }

                        setTimeout(() => {
                            if (analyzerResult) {
                                if (terminalOutput) {
                                    terminalOutput.innerHTML += `<div style="color: #00FF00;">[Whois 分析] 檢索結果：UDP 狀態正常。</div>`;
                                }
                                analyzerResult.innerHTML = `<span style="color: #00ffff;">[分析結果 - 安全]<br>目標：UDP<br>狀態：目前未發現明顯的威脅情報。</span>`;
                            }
                            unlockAndNext();
                        }, 2000);

                    } else {
                        alert(`請輸入正確指令：'${step.expectedTextCmd}'`);
                    }
                }
            };
            cmdInput.addEventListener('keypress', handleCmdKeypress);
        }
    }
    
    if (step.BtargetCmdInput) {
        const cmdInput = document.getElementById(step.BtargetCmdInput);
        if (cmdInput) {
            const handleCmdKeypress = (e) => {
                if (e.key === 'Enter') {
                    if (cmdInput.value.trim().toLowerCase() === step.expectedTextCmd.toLowerCase()) {
                        cmdInput.disabled = true;
                        cmdInput.removeEventListener('keypress', handleCmdKeypress);

                        const terminalOutput = document.getElementById('terminal-output');
                        if (terminalOutput) {
                            terminalOutput.innerHTML += `<div>> ${cmdInput.value}</div>`;
                            terminalOutput.innerHTML += `<div style="color: #00FF00;">root@sec-server:~# whois ${TUTORIAL_TARGET_IP}</div>`;
                            terminalOutput.innerHTML += `<div style="color: #00FF00;">情報分析指令已發送...</div>`;
                        }

                        const analyzerResult = document.getElementById('analyzer-result');
                        const idsLogBox = document.getElementById('ids-alert-log');
                        if (analyzerResult) {
                            analyzerResult.innerHTML = `<span style="color: #494946;">[查詢中] 正在透過情報庫檢索 ${TUTORIAL_TARGET_IP}...</span>`;
                        }

                        setTimeout(() => {
                            if (analyzerResult) {
                                if (idsLogBox) {
                                    idsLogBox.innerHTML = `<div style="color: #ffe08a;">[情報解鎖] 已確認 ${TUTORIAL_TARGET_IP} 為惡意來源，授權進行攔截。</div>` + idsLogBox.innerHTML;
                                }
                                if (terminalOutput) {
                                    terminalOutput.innerHTML += `<div style="color: #00FF00;">[Whois 分析] 檢索結果：發現惡意 UDP Flood 威脅來源！</div>`;
                                }
                                analyzerResult.innerHTML = `<span style="color: #00ffff;">[分析結果 - 高危險] <br>目標: ${TUTORIAL_TARGET_IP} <br>狀態: 偵測到惡意 Payload。<br>建議行動: 立即進行阻斷 (Block)。</span>`;
                            }
                            const threatList = document.getElementById('threat-intel-list');
                            if (threatList) {
                                threatList.innerHTML = `
                                    <button id="btn-block-all-intel" class="btn-danger-action tutorial-highlight">
                                        <i class="fas fa-ban"></i> 一鍵封鎖所有已分析危險 IP
                                    </button>
                                `;
                                const intelCard = document.getElementById('guide-intel-card');
                                if (intelCard) intelCard.classList.add('tutorial-highlight');
                            }
                            unlockAndNext();
                        }, 2000);

                    } else {
                        alert(`請輸入正確指令：'${step.expectedTextCmd}'...不是幫你打好了!?`);
                    }
                }
            };
            cmdInput.addEventListener('keypress', handleCmdKeypress);
        }
    }

    // 處理處置階段
    if (step.targetBtnId || step.targetBlockAllBtn) {
        // 快捷按鈕
        const btn = document.getElementById(step.targetBtnId);
        if (btn) {
            const handleMitigateClick = () => {
                btn.removeEventListener('click', handleMitigateClick);
                const resultBox = document.getElementById('analyzer-result');
                if (resultBox) {
                    resultBox.innerHTML = `<span style="color: #ffff00;">[PROCESSING] 正在套用 UDP 阻斷策略...</span>`;
                }

                setTimeout(() => {
                    renderBlockedThreatIntel(TUTORIAL_TARGET_IP, "UDP 攻擊偵測");
                    unlockAndNext();
                }, 800);
            };
            btn.addEventListener('click', handleMitigateClick);
        }

        // 一鍵封鎖按鈕
        const threatContainer = document.getElementById('threat-intel-list');
        if (threatContainer) {
            const handleBlockClick = (e) => {
                if (e.target.closest('#btn-block-all-intel')) {
                    threatContainer.removeEventListener('click', handleBlockClick);
                    const resultBox = document.getElementById('analyzer-result');
                    if (resultBox) {
                        resultBox.innerHTML = `<span style="color: #ffff00;">[PROCESSING] 正在一鍵封鎖惡意 IP...</span>`;
                    }

                    setTimeout(() => {
                        renderBlockedThreatIntel(TUTORIAL_TARGET_IP, "UDP 攻擊偵測");
                        unlockAndNext();
                    }, 800);
                }
            };
            threatContainer.addEventListener('click', handleBlockClick);
        }
    }

    if (step.targetTabId) {
        const tabBtn = document.getElementById(step.targetTabId);
        if (tabBtn) {
            const handleTabClick = () => {
                tabBtn.removeEventListener('click', handleTabClick);
                if (step.targetTabId === 'menu-tab-status') switchTutorialTab('tab-status');
                if (step.targetTabId === 'menu-tab-mailbox') switchTutorialTab('tab-mailbox');
                unlockAndNext();
            };
            tabBtn.addEventListener('click', handleTabClick);
        }
    }

    if (step.targetActionButtons) {
        const actionContainer = document.getElementById('mail-action-buttons');
        if (actionContainer) {
            const handleAction = () => {
                actionContainer.removeEventListener('click', handleAction);
                unlockAndNext();
            };
            actionContainer.addEventListener('click', handleAction);
        }
    }
}

// 渲染固定正常 Wireshark 封包
function initNormalWiresharkPackets() {
    const packetList = document.getElementById('packet-list');
    if (!packetList) return;
    packetList.innerHTML = ''; 

    FIXED_NORMAL_PACKETS.forEach(pkt => {
        const tr = document.createElement('tr');
        const protoClass = `packet-row-${pkt.protocol.toLowerCase()}`;
        tr.className = `packet-row-clickable ${protoClass}`;

        tr.innerHTML = `
            <td>${pkt.time}</td>
            <td style="font-weight: bold;">${pkt.srcIp}</td>
            <td>${pkt.srcPort}</td>
            <td>${pkt.dstIp}</td>
            <td class="port-highlight">${pkt.dstPort}</td>
            <td>${pkt.protocol}</td>
            <td>${pkt.length}</td>
            <td>${pkt.status}</td>
        `;

        tr.addEventListener('click', () => {
            document.querySelectorAll('#packet-list tr').forEach(r => r.classList.remove('packet-row-selected'));
            tr.classList.add('packet-row-selected');

            const payloadPanel = document.getElementById('packet-payload-content');
            if (payloadPanel) {
                payloadPanel.innerHTML = `<span style="color: #55ff55;">${pkt.payload}</span>`;
            }
        });

        packetList.appendChild(tr);
    });
}

// 將惡意封包插入最頂端
function appendMaliciousPacket() {
    createWiresharkPacket({
        time: "00:01:23",
        srcIp: TUTORIAL_TARGET_IP,
        srcPort: "5000",
        dstIp: "10.0.0.1",
        dstPort: "80",
        protocol: "UDP",
        length: "1500",
        status: "FLOOD",
        payload: "0x0000:  ff ff ff ff ff ff ff ff ... (Garbage Data / UDP Flood)",
        isDanger: true
    });
}

function createWiresharkPacket(packetData) {
    const packetList = document.getElementById('packet-list');
    if (!packetList) return;

    const tr = document.createElement('tr');
    tr.className = 'packet-danger packet-row-clickable tutorial-highlight';
    tr.innerHTML = `
        <td>${packetData.time || new Date().toTimeString().split(' ')[0]}</td>
        <td class="warn-ip ip-highlight">${packetData.srcIp}</td>
        <td>${packetData.srcPort || '53'}</td>
        <td>${packetData.dstIp || '10.0.0.1'}</td>
        <td>${packetData.dstPort || '80'}</td>
        <td>${packetData.protocol || 'UDP'}</td>
        <td>${packetData.length || '1500'}</td>
        <td>${packetData.status || 'FLOOD'}</td>
    `;

    bindPacketClickEvent(tr, packetData.srcIp);
    packetList.insertBefore(tr, packetList.firstChild);
}

function bindPacketClickEvent(rowElement, targetIp) {
    rowElement.addEventListener('click', () => {
        document.querySelectorAll('#packet-list tr').forEach(r => r.classList.remove('packet-row-selected'));
        rowElement.classList.add('packet-row-selected');

        const payloadPanel = document.getElementById('packet-payload-content');
        if (payloadPanel) {
            payloadPanel.innerHTML = `
                <div class="analyzer-btn-wrapper">
                    <button id="btn-analyze-ip" class="btn-analyze-ip tutorial-highlight">
                        <span>[來源 IP]</span>
                        <span>${targetIp}</span>
                        <span>[分析]</span>
                    </button> <span style="color: #ff3333; font-weight: bold; font-size: 14px;">長度異常</span>
                </div>
            `;

            const btnAnalyze = document.getElementById('btn-analyze-ip');
            if (btnAnalyze) {
                btnAnalyze.addEventListener('click', (e) => {
                    e.stopPropagation();

                    const cmdInput = document.getElementById('cmd-input');
                    if (cmdInput) {
                        cmdInput.value = `whois ${targetIp}`;
                        cmdInput.focus();
                    }

                    if (tutorialSteps[currentStep] && tutorialSteps[currentStep].targetWireshark) {
                        setTimeout(() => {
                            unlockAndNext();
                        }, 500);
                    }
                });
            }
        }
    });
}

function unlockAndNext() {
    const nextBtn = document.getElementById('next-btn');
    if (nextBtn) nextBtn.style.display = 'inline-block';
    nextStep();
}

function nextStep() {
    if (currentStep < tutorialSteps.length - 1) {
        currentStep++;
        updateUI();
    } else {
        document.body.classList.remove('tutorial-locked');
        location.href = '../index.php';
    }
}
window.nextStep = nextStep;

document.addEventListener("DOMContentLoaded", () => {
    initNormalWiresharkPackets();
    updateUI();
});