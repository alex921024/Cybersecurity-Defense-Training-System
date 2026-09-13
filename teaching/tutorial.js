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

// 教學關卡與掌控時間的動作
const tutorialSteps = [
    {
        title: "歡迎進入 SOC 模擬指揮中心",
        desc: "你可以透過點擊左側導覽列在各分頁間切換。請點擊下一步開始學會防禦黃金 SOP。",
        action: () => { clearHighlights(); switchTutorialTab('tab-firewall'); }
    },
    {
        title: "第一步：檢視 IDS 即時警報日誌",
        desc: "當駭客發動攻擊時，右側的警報日誌會即時亮紅字彈出警告。現在，系統為你模擬一則 UDP DDoS 的警報！",
        action: () => {
            clearHighlights();
            document.getElementById('guide-alert-card').classList.add('tutorial-highlight');
            document.getElementById('ids-alert-log').innerHTML =
                `<span style="color: #ff3333; font-weight: bold;">[WARNING] 偵測到來自未知來源的大量 UDP 異常洪水流量連線！</span>`;
        }
    },
    {
        title: "第二步：利用 Wireshark 分析可疑封包",
        desc: "左側的即時流量監控表會擷取所有網路封包。現在我為你填入這筆攻擊的詳細資訊，你可以看到是誰正在發起 Flood 攻擊。",
        action: () => {
            clearHighlights();
            document.getElementById('analysis-section').classList.add('tutorial-highlight');
            document.getElementById('packet-list').innerHTML = `
                <tr class="packet-danger">
                    <td>00:01:23</td>
                    <td class="warn-ip">192.168.4.12</td>
                    <td>53</td>
                    <td>10.0.0.1</td>
                    <td>80</td>
                    <td>UDP</td>
                    <td>1500</td>
                    <td>FLOOD</td>
                </tr>
            `;
            document.getElementById('packet-payload-content').innerHTML = `[RAW PACKET PAYLOAD]\nSOURCE: 192.168.4.12\nATTACK TYPE: UDP FLOOD DETECTED\nSIZE: 1500 BYTES`;
        }
    },
    {
        title: "第三步：策略快捷防禦一鍵阻斷",
        desc: "確認攻擊型態為 UDP 後，你可以使用右下角的『策略快捷防禦開關』迅速啟用『阻斷 UDP 流量』來化解危機！",
        action: () => {
            clearHighlights();
            document.getElementById('guide-control-card').classList.add('tutorial-highlight');
        }
    },
    {
        title: "切換到『系統狀態』分頁查看硬體",
        desc: "做得很棒！現在請試著點擊左側側邊欄的『系統狀態』分頁，觀察在遭受攻擊時，伺服器的 CPU、RAM 效能負載變化狀況。",
        action: () => {
            clearHighlights();
            document.getElementById('menu-tab-status').classList.add('tutorial-highlight');
        }
    },
    {
        title: "切換到『收件匣』查看釣魚威脅",
        desc: "除網路流量外，員工可能收到誘騙密碼的社交工程信。請點擊左側『收件匣』分頁，學習如何辨識並清除危險的釣魚信件。",
        action: () => {
            clearHighlights();
            document.getElementById('menu-tab-mailbox').classList.add('tutorial-highlight');
        }
    },
    {
        title: "🏆 指揮官受訓完成",
        desc: "你已經精通基本防禦系統架構！點擊完成訓練返回主選單，準備迎接真正的駭客入侵實戰吧！",
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
    const step = tutorialSteps[currentStep];
    document.getElementById('tutorial-title').innerText = step.title;
    typeWriter('tutorial-desc', step.desc, 15);

    const nextBtn = document.getElementById('next-btn');
    if (currentStep === tutorialSteps.length - 1) {
        nextBtn.innerText = "完成訓練 🎓";
    } else {
        nextBtn.innerText = "下一步";
    }

    if (step.action) step.action();
}

function nextStep() {
    if (currentStep < tutorialSteps.length - 1) {
        currentStep++;
        updateUI();
    } else {
        location.href = '../index.php';
    }
}

document.addEventListener("DOMContentLoaded", () => {
    updateUI();
});
