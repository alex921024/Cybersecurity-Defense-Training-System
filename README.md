# 🛡️ 資安防禦訓練系統 (Cybersecurity Defense Training System)

這是一個基於瀏覽器的**模擬藍隊 (Blue Team) 防禦訓練系統**。玩家將扮演系統管理員，在充滿挑戰的虛擬終端機環境中，即時監控網路流量、處理惡意郵件，並透過指令執行來抵禦各類資安攻擊。

## 🕹️ 遊戲核心特徵

  * **虛擬終端機 (Interactive CLI)**：透過鍵盤輸入 `netstat`、`block`、`passwd` 等指令進行防禦。
  * **即時流量監控 (Wireshark Style)**：內建封包分析介面，可根據協定（TCP/UDP/ICMP/DNS）過濾並識別惡意 Payload。
  * **模擬郵件系統**：包含正常業務郵件與釣魚郵件 (Phishing)，考驗玩家的社交工程識別能力。
  * **動態負載模擬**：系統會即時追蹤 CPU、GPU、RAM 及 WiFi 負載，一旦資源耗盡或密碼被破解，任務即宣告失敗。
  * **多層次難度設定**：提供從等級 0 到等級 2 的訓練，挑戰更密集的攻擊頻率與更長的守護時間。

-----

## 🛠️ 系統架構

該專案採用模組化 JavaScript 開發，結構清晰易於擴展：

  * `gameManager.js`: 核心驅動引擎，控制遊戲迴圈與事件觸發。
  * `cliModule.js`: 指令解析器，處理玩家的所有終端指令。
  * `systemStatus.js`: 硬體資源與破解進度的狀態追蹤。
  * `database.js`: 存儲攻擊種類、惡意 IP 庫及模擬郵件內容。
  * `threatEngine.js`: 基於機率分佈的威脅產生器。

-----

## 🚀 快速開始

由於專案採用 Vanilla JavaScript 撰寫，無需安裝任何依賴套件：

1.  **複製專案**：
    ```bash
    git clone https://github.com/your-username/security-defense-trainer.git
    ```
2.  **執行遊戲**：
    直接在瀏覽器中開啟 `index.html` 即可開始訓練。
    *(建議使用 VS Code Live Server 擴充功能以獲得最佳體驗)*

-----

## 📋 防禦指令手冊 (Cheat Sheet)

| 指令 | 說明 |
| :--- | :--- |
| `status` | 查看當前硬體負載與密碼破解進度 |
| `netstat` | 分析當前連線，識別異常來源 IP |
| `block [proto/ip]` | 更新防火牆規則，阻斷 SYN/UDP/ICMP 等流量 |
| `flush-dns` | 清除遭受攻擊的 DNS 快取 |
| `scan-mail` | 自動掃描並隔離收件匣中的釣魚信件 |
| `passwd` | 強制變更管理員密碼，將駭客破解進度歸零 |

-----

## ⚠️ 常見攻擊處置 (SOP)

1.  **DDoS 攻擊 (SYN/UDP/ICMP)**：
      * 觀測 Wireshark 窗口是否有大量紅色標記封包。
      * 使用 `netstat` 確認來源。
      * 執行 `block ip` 或 `block udp`。
2.  **DNS 放大攻擊**：
      * 系統負載異常升高且流量出現大量 DNS 請求。
      * 執行 `flush-dns` 重導向安全解析伺服器。
3.  **社交工程 (Phishing)**：
      * 隨時注意「收件匣」的未讀標記。
      * 使用 `scan-mail` 或手動進入郵件系統「刪除」惡意連結。

-----

## 🖥️ 畫面預覽

  * **控制台模式**：具備駭客風格的綠色矩陣介面。
  * **監控儀表板**：直觀的硬體負載進度條，當負載 \> 80% 時會切換為紅色警戒。
  * **Gmail 風格收件匣**：擬真的郵件閱讀與附件處理互動介面。

-----

## ⚖️ 授權聲明

本專案採用 **MIT License** 授權。歡迎學術交流、教學演示或個人練習使用。

-----

## ⚖️ 授權聲明

本專案採用 **MIT License** 授權。歡迎學術交流、教學演示或個人練習使用。

-----

