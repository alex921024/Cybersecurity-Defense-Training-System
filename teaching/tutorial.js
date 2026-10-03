// 安全切換分頁功能
function switchTutorialTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    document.getElementById(tabId).classList.add('active');

    const btnId = "menu-" + tabId;
    if (document.getElementById(btnId)) {
        document.getElementById(btnId).classList.add('active');
    }
}

const TUTORIAL_TARGET_IP = "192.168.4.12";

// 教學關卡與互動邏輯
const tutorialSteps = [
    {
        title: "歡迎進入 SOC 模擬指揮中心",
        desc: "你可以透過點擊左側導覽列在各分頁間切換。請點擊下一步開始學會防禦黃金 SOP。",
        action: () => { clearHighlights(); switchTutorialTab('tab-firewall'); }
    },
    {
        title: "檢視 IDS 即時警報日誌",
        desc: "當駭客發動攻擊時，右側的警報日誌會即時亮紅字彈出警告。現在，系統為你模擬一則 UDP DDoS 的警報！",
        action: () => {
            clearHighlights();
            document.getElementById('guide-alert-card').classList.add('tutorial-highlight');
            document.getElementById('ids-alert-log').innerHTML =
                `<span style="color: #ff3333; font-weight: bold;">[WARNING] 偵測到來自未知來源的大量 UDP 異常洪水流量連線！</span>`;
        }
    },
    { 
        title: "威脅情報分析 (Whois)",
        desc: "請在右側『威脅情報分析儀』輸入框打入 'udp'，並點擊『分析 (Whois)』按鈕（或按 Enter）進行分析！",
        interactive: true,
        targetInputId: 'analyzer-input',
        expectedText: 'udp',
        action: () => {
            clearHighlights();
            const input = document.getElementById('analyzer-input');
            const card = document.getElementById('guide-analysis-card');
            
            if (input) {
                input.disabled = false; // 解除禁用
                input.focus();
            }
            if (card) {
                card.classList.add('tutorial-highlight'); // 發亮分析卡片
            }
        }
    },
    {
        title: "做得很好!",
        desc: "發現威脅"
    },
    {
        title: "策略快捷防禦一鍵阻斷",
        interactive: true,
        desc: "現在分析結果已出爐！請點擊右下角『策略快捷防禦開關』中的『阻斷 UDP 流量』來化解危機！",
        targetBtnId: 'btn-mitigate-udp',
        action: () => {
            clearHighlights();
            const btn = document.getElementById('btn-mitigate-udp');
            const card = document.getElementById('guide-control-card');
            if (btn) {
                btn.disabled = false; // 解除禁用
                btn.classList.add('tutorial-highlight');
            }
            if (card) {
                card.classList.add('tutorial-highlight');
                // 平滑滾動頁面，將卡片帶到畫面中央
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    },
    {
        title: "很好",
        desc: "現在已成功解除"
    },
    {
        title: "檢視 即時流量監控 (Wireshark)",
        desc: `即時流量監控 (Wireshark) 會時刻監控 IP 來源等資訊。請點選列表中來源為 ${TUTORIAL_TARGET_IP} 的異常封包列！`,
        interactive: true,
        targetWireshark: true,
        action: () => {
            clearHighlights();
            const analysisSec = document.getElementById('analysis-section');
            if (analysisSec) analysisSec.classList.add('tutorial-highlight');

            // 清空既有列表，並呼叫副函式生成一條固定 IP 封包
            const packetList = document.getElementById('packet-list');
            if (packetList) packetList.innerHTML = '';

            createWiresharkPacket({
                time: "00:01:23",
                srcIp: TUTORIAL_TARGET_IP,
                protocol: "UDP",
                status: "FLOOD"
            });
        }
    },
    {
        title: "檢視 指令控制台 (root@sec-server:~#)",
        desc: "點選分析後 IP 會顯示在指令控制台上方，可較快的查詢 IP 的狀態。",
        action: () => {
            clearHighlights();
            // 控制台輸入框高亮閃爍
            const cmdInput = document.getElementById('cmd-input') || 
                             document.getElementById('terminal-input') || 
                             document.getElementById('cli-input');
            if (cmdInput) {
                cmdInput.classList.add('tutorial-highlight');
                cmdInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    },
    {
        title: "檢視 指令控制台 (root@sec-server:~#)",
        desc: "下方也有針對各類情況的指令",
        action: () => {
            clearHighlights();
            const cmdTags = document.querySelectorAll('.quick-commands .cmd-tag');
            const container = document.querySelector('.quick-commands');
            if (cmdTags.length > 0) {
                // 讓按鈕亮起高亮
                cmdTags.forEach(tag => tag.classList.add('tutorial-highlight'));
                // 自動捲動到按鈕區域
                cmdTags[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else if (container) {
                container.classList.add('tutorial-highlight');
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
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
        desc: "這是你目前所需要堅守的時間",
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
        desc: "系統密碼的更新只用於本局的機制，與使用者帳號無關",
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
        desc: "當字典破解進度過高時本局將直接失敗，所以請多加留意",
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
        desc: "這裡也可同時觀察到目前電腦的負載情況",
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
        // 若該步驟有特定操作，隱藏「下一步」按鈕，強迫玩家手動完成
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
    // 分析 udp 互動（產出紅色異常警報）
    if (step.targetInputId) {
        const input = document.getElementById(step.targetInputId);
        const analyzeBtn = input ? input.nextElementSibling : null;
        
        const executeAnalysis = () => {
            if (input && input.value.trim().toLowerCase() === step.expectedText.toLowerCase()) {
                input.disabled = true;
                const resultBox = document.getElementById('analyzer-result');
                if (resultBox) {
                    resultBox.innerHTML = `<span style="color: #ffff00;">[ANALYZING] 正在向 Threat Intel 資料庫查詢 udp 協議...</span>`;
                }
                
                setTimeout(() => {
                    if (resultBox) {
                        resultBox.innerHTML = `<span style="color: #f42a17;">[分析結果 - 異常] <br>目標：UDP <br>狀態：洪水攻擊 威脅等級：高</span>`;
                    }
                    
                    if (input) input.removeEventListener('keypress', handleKeypress);
                    if (analyzeBtn) analyzeBtn.removeEventListener('click', executeAnalysis);
                    unlockAndNext();
                }, 800);
            } else {
                alert("請輸入 'udp' 後再進行分析！");
            }
        };

        const handleKeypress = (e) => {
            if (e.key === 'Enter') executeAnalysis();
        };

        if (analyzeBtn) analyzeBtn.addEventListener('click', executeAnalysis);
        if (input) input.addEventListener('keypress', handleKeypress);
    }

    // 點擊阻斷按鈕互動
    if (step.targetBtnId) {
        const btn = document.getElementById(step.targetBtnId);
        if (btn) {
            const handleClick = () => {
                btn.removeEventListener('click', handleClick);
                const resultBox = document.getElementById('analyzer-result');
                
                if (resultBox) {
                    resultBox.innerHTML = `<span style="color: #ffff00;">[PROCESSING] 正在套用 UDP 阻斷策略...</span>`;
                }

                setTimeout(() => {
                    if (resultBox) {
                        resultBox.innerHTML = `<span style="color: #00ffff;">[分析結果 - 安全]<br>目標：UDP<br>狀態：目前未發現明顯的威脅情報。</span>`;
                    }
                    unlockAndNext();
                }, 800);
            };
            btn.addEventListener('click', handleClick);
        }
    }

    // 分頁點擊互動（例如：點擊側邊欄「系統狀態」或「收件匣」按鈕）
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

    // 信件處置按鈕點擊互動
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
                    </button>
                </div>
            `;

            const btnAnalyze = document.getElementById('btn-analyze-ip');
            if (btnAnalyze) {
                btnAnalyze.addEventListener('click', (e) => {
                    e.stopPropagation();

                    const cmdInput = document.getElementById('cmd-input') || 
                                     document.getElementById('terminal-input') || 
                                     document.getElementById('cli-input');
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

document.addEventListener("DOMContentLoaded", () => {
    updateUI();
});