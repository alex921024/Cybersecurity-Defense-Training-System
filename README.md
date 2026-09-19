# 資安防禦訓練系統

Cybersecurity Defense Training System 是一套以 PHP、MySQL 與原生 JavaScript 開發的瀏覽器資安防禦訓練平台。使用者將扮演藍隊防禦人員，透過網路封包監控、威脅情報分析、終端機指令與郵件辨識，學習處理常見的模擬資安事件。

## 功能特色

- 即時封包監控：檢視 TCP、UDP、ICMP 與 DNS 流量。
- 威脅情報分析：分析可疑 IP 與攻擊協定。
- 防火牆策略操作：封鎖流量、來源 IP、DNS 快取與其他攻擊面。
- 虛擬終端機：使用 `status`、`netstat`、`whois`、`block`、`scan-mail` 等指令進行防禦。
- 社交工程訓練：辨識正常郵件與釣魚郵件。
- 系統狀態模擬：追蹤 CPU、GPU、RAM、Wi-Fi 與密碼破解進度。
- 遊戲密碼防護：開始前必須設定本局密碼，並可在遊戲中更換；密碼只用於模擬，不會修改登入密碼。
- 系統預設與教師自訂訓練難度：教師可設定遊戲時間、可用防禦指令與攻擊模擬。
- 教師難度管理：已核准且綁定教師的學生可使用該教師啟用中的自訂難度；教師也可使用分配功能進行後續精細控管。
- 學生教師綁定：學生可查看目前綁定教師與已分配的訓練難度。
- 帳號與權限管理：支援學生、教師與管理員角色。
- Google OAuth 登入：首次使用 Google 登入時可自動建立學生帳號。
- 教學模式：提供逐步引導的 SOC 防禦操作流程。

## 技術架構

- 後端：PHP 8.2+、PDO、MySQL 或 MariaDB
- 前端：HTML5、CSS3、原生 JavaScript ES modules
- 驗證：PHP Session、帳號密碼驗證、Google OAuth 2.0
- 圖表：Chart.js
- 圖示：Font Awesome
- 伺服器：Apache、XAMPP、WAMP 或 PHP 內建伺服器

## 專案結構

```text
.
├── api/
│   ├── auth/       # 登入、註冊、登出與登入狀態
│   ├── admin/      # 管理員與教師功能
│   ├── core/       # 共用驗證與資料庫連線
│   ├── oauth/      # Google OAuth 登入流程
│   ├── student/    # 學生資料與訓練紀錄
│   └── teacher/    # 教師自訂難度與學生分配
├── assets/
│   ├── css/        # 共用頁面樣式
│   ├── html/       # 登入、學生控制台與管理後台
│   ├── js/         # 遊戲引擎、登入與威脅模擬邏輯
│   └── vendor/     # 前端第三方資源
├── teaching/       # 教學頁面及其 CSS、JavaScript
├── index.php       # 受保護的主要訓練入口
├── sql.txt         # 資料庫結構與初始資料
└── test_code/      # 測試與開發用程式
```

## 安裝與啟動

### 1. 下載專案

```bash
git clone https://github.com/alex921024/Cybersecurity-Defense-Training-System.git
cd Cybersecurity-Defense-Training-System
```

### 2. 建立資料庫

使用 MySQL 或 MariaDB 建立資料庫，並匯入 [sql.txt](sql.txt)：

```sql
CREATE DATABASE soc_training_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root -p soc_training_db < sql.txt
```

接著檢查 [api/core/db_connect.php](api/core/db_connect.php) 的資料庫主機、帳號、密碼與資料庫名稱。

### 3. 啟動網站

#### PHP 內建伺服器

```bash
php -S localhost:8000
```

開啟：

```text
http://localhost/Cybersecurity-Defense-Training-System/assets/html/login.html
```

```text
http://localhost/Cybersecurity-Defense-Training-System/test_code/create_test_accounts.php
```

#### Apache / XAMPP

將專案放在 Apache 的網站根目錄，例如：

```text
C:\xampp\htdocs\Cybersecurity-Defense-Training-System
```

開啟：

```text
http://localhost/Cybersecurity-Defense-Training-System/assets/html/login.html
```

網站必須透過 PHP 伺服器開啟，不能直接用 `file://` 開啟 PHP 或需要 Session 的頁面。

## Google OAuth 設定

在 Google Cloud Console 的 OAuth 用戶端，加入與實際網址完全一致的「已授權的重新導向 URI」。Apache 本機環境通常使用：

```text
http://localhost/Cybersecurity-Defense-Training-System/api/oauth/google_callback.php
```

PHP 內建伺服器則使用：

```text
http://localhost:8000/api/oauth/google_callback.php
```

請確認以下設定：

1. Google OAuth Client ID 與 Client Secret 已填入專案設定。
2. `redirect_uri` 與 Google Cloud Console 的 URI 完全相同。
3. Google OAuth 同意畫面的測試使用者已加入測試帳號。
4. 網站使用 HTTPS 或本機 `localhost` 測試環境。

OAuth 主要檔案：

- [api/oauth/google_config.php](api/oauth/google_config.php)
- [api/oauth/google_login.php](api/oauth/google_login.php)
- [api/oauth/google_callback.php](api/oauth/google_callback.php)

## 使用流程

1. 開啟登入頁並註冊帳號，或使用 Google 登入。
2. 教師進入管理後台的「我的訓練難度」，建立難度並設定指令與攻擊開關。
3. 學生完成教師綁定並通過核准後，會自動看到該教師啟用中的自訂難度；教師可在學生列表進一步調整分配。
4. 學生在控制台查看目前綁定教師，選擇可用的教師難度開始訓練。
5. 從 [index.php](index.php) 選擇可用難度並確認任務簡報。
6. 依序觀察警報、分析流量、執行防禦指令與處理郵件。
7. 從主選單進入 [teaching/tutorial.php](teaching/tutorial.php) 查看互動教學。

## 教師自訂難度

教師建立難度時可以設定：

- 遊戲時間與難度說明。
- 防禦指令是否可用：`status`、`netstat`、`whois`、`limit`、`block`、`unblock`、`flush-dns`、`scan-mail`、`passwd`。
- 攻擊模擬是否啟用：TCP、UDP、ICMP、DNS、密碼破解與釣魚郵件。
- TCP、UDP、ICMP、DNS 與釣魚郵件的隨機出現機率區間。
- 攻擊冷卻時間、攻擊傷害倍率、破解速度與初始 CPU／GPU／RAM／Wi-Fi 負載。
- 遊戲密碼政策：最低長度、數字／大寫／特殊符號要求、是否允許更換，以及更換冷卻時間。
- 字典破解是否啟用與破解速度倍率。

`status` 與 `netstat` 是必要的基礎觀測指令。後端會驗證難度擁有者、學生綁定關係、核准狀態、機率範圍、密碼政策與指令／攻擊白名單，不能只透過修改瀏覽器內容繞過設定。

## 防禦指令

開始訓練前必須先設定本局遊戲密碼。系統會依密碼長度、大小寫、數字、特殊符號、常見字典詞與重複模式計算破解速度。遊戲中的密碼防護區可更換本局密碼，成功後會重置破解進度；遊戲結束後會清除本局密碼資料。

教師可在難度設定中限制密碼規則與更換行為；關閉字典破解後，本局破解進度不會增加。遊戲密碼只存在本局瀏覽器記憶體，不會修改網站登入密碼，也不會送至後端。

| 指令 | 功能 |
| --- | --- |
| `status` | 查看 CPU、GPU、RAM 與破解進度 |
| `netstat` | 查看目前連線與異常流量 |
| `whois [IP/協定]` | 分析可疑 IP 或協定 |
| `limit [協定]` | 暫時限制指定協定流量 |
| `block [IP/協定]` | 建立防火牆阻擋規則 |
| `unblock [IP/協定]` | 解除封鎖或限速 |
| `flush-dns` | 清除 DNS 快取 |
| `scan-mail` | 掃描並隔離釣魚郵件 |
| `passwd` | 重置密碼破解進度 |

## 安全注意事項

- 不要將正式環境的 Client Secret、資料庫密碼或 Session 設定提交到 GitHub。
- 建議使用環境變數管理 OAuth 與資料庫機密資訊。
- 正式部署時請啟用 HTTPS，並限制資料庫帳號權限。
- `sql.txt` 與測試帳號僅適合開發或教學環境使用。

## 授權

本專案採用 [MIT License](LICENSE) 授權，適合教學、研究與個人練習使用。
