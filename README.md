# 資安防禦訓練系統

Cybersecurity Defense Training System 是一套以 PHP、MySQL 與原生 JavaScript 開發的瀏覽器資安防禦訓練平台。使用者將扮演藍隊防禦人員，透過網路封包監控、威脅情報分析、終端機指令與郵件辨識，學習處理常見的模擬資安事件。

## 功能特色

- 即時封包監控：檢視 TCP、UDP、ICMP 與 DNS 流量。
- 威脅情報分析：以資料庫威脅題庫中的 IP、攻擊類型與封包特徵產生並分析可疑流量。
- 防火牆策略操作：封鎖流量、來源 IP、DNS 快取與其他攻擊面。
- 虛擬終端機：使用 `status`、`netstat`、`whois`、`block`、`scan-mail` 等指令進行防禦。
- 社交工程訓練：辨識正常郵件與釣魚郵件。
- 系統狀態模擬：追蹤 CPU、GPU、RAM、Wi-Fi 與密碼破解進度。
- 遊戲密碼防護：開始前必須設定本局密碼，並可在遊戲中更換；密碼只用於模擬，不會修改登入密碼。
- 系統預設與教師自訂訓練難度：教師可設定遊戲時間、可用防禦指令與攻擊模擬。
- 教師難度管理：已核准且綁定教師的學生，只能使用教師明確分配給自己的啟用中自訂難度。
- 學生教師綁定：學生可查看目前綁定教師與已分配的訓練難度。
- 帳號與權限管理：支援學生、教師與管理員角色。
- 管理員帳號維護：管理員可將學生升級為教師，並修改教師與學生登入密碼；密碼使用 Argon2id 雜湊並留下稽核紀錄。
- 郵件題庫管理：管理員可新增、查看與刪除正常郵件及釣魚郵件訓練題目。
- Google OAuth 登入：首次使用 Google 登入時可自動建立學生帳號。
- 教學模式：提供逐步引導的 SOC 防禦操作流程。
- 響應式介面：登入頁、管理後台、學生控制台、訓練遊戲與教學浮窗可自適應桌面、平板及手機視窗。

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

管理員相關 API 包含：

- [api/admin/update_user.php](api/admin/update_user.php)：升級學生帳號、帳號綁定操作與修改教師／學生密碼。
- [api/admin/manage_emails.php](api/admin/manage_emails.php)：新增與刪除郵件題庫題目。
- [api/admin/get_content_catalog.php](api/admin/get_content_catalog.php)：讀取完整威脅與郵件題庫。
- [api/admin/get_audit_logs.php](api/admin/get_audit_logs.php)：查詢帳號與訓練操作稽核紀錄。

## 資料庫結構

完整定義見 [sql.txt](sql.txt)。所有資料表使用 InnoDB 與 `utf8mb4`。

### 資料表關係

```text
users ─┬─< game_records                 （學生訓練結算）
       ├─< account_operation_logs       （操作者；刪除使用者時設為 NULL）
       ├─< difficulty_configs           （教師自訂難度擁有者；系統預設為 NULL）
       │        └─< difficulty_assignments >─ users（被分配的學生）
       └── users.teacher_id → users.user_id（學生綁定教師）

threat_ips、vip_ips、phishing_emails：獨立題庫，無外鍵
deleted_users_backup、login_attempts：獨立表，無外鍵
```

### 帳號與權限

#### `users`：使用者

| 欄位 | 說明 |
|---|---|
| `user_id` | 主鍵，自動遞增 |
| `username` | 登入帳號，唯一 |
| `password_hash` | Argon2id 密碼雜湊（Google 帳號亦保留此欄位） |
| `role` | 角色：`student`、`teacher`、`admin`，預設 `student` |
| `teacher_id` | 學生綁定的教師，參照 `users.user_id`；教師被刪除時設為 NULL |
| `is_approved` | 是否已核准，`1` 為已核准；未核准的學生不能開始訓練 |
| `google_id`、`email` | Google OAuth 綁定資料，各自唯一 |
| `created_at` | 建立時間 |

#### `deleted_users_backup`：已刪除帳號備份

刪除帳號前保存的資源回收桶，欄位包含 `original_user_id`、`username`、`password_hash`、`role`、`teacher_id`、`account_created_at`、`deleted_at` 與 `deleted_by`（執行刪除的使用者）。此表沒有外鍵，帳號刪除後備份仍保留。

#### `account_operation_logs`：帳號操作稽核紀錄

| 欄位 | 說明 |
|---|---|
| `log_id` | 主鍵 |
| `operator_id` | 操作者，參照 `users.user_id`；操作者被刪除時設為 NULL |
| `action_type` | 操作類型，例如 `LOGIN_SUCCESS` |
| `target_username` | 被操作的帳號 |
| `ip_address`、`device_info` | 來源 IP 與瀏覽器資訊 |
| `details` | JSON 格式的操作細節 |
| `created_at` | 發生時間 |

#### `login_attempts`：登入失敗節流

| 欄位 | 說明 |
|---|---|
| `username`、`ip_hash` | 複合主鍵；帳號與來源 IP 的 SHA-256 雜湊（不存明碼 IP） |
| `failed_attempts` | 目前窗口內的失敗次數 |
| `window_started_at` | 計數窗口開始時間（Unix 時間戳，窗口 15 分鐘） |
| `blocked_until` | 鎖定截止時間；`0` 表示未鎖定 |
| `updated_at` | 最後更新時間，用於清理超過 24 小時的舊資料 |

連續失敗 5 次會鎖定 15 分鐘，登入成功即清除該筆。僅密碼登入使用，Google 登入不經過此表。若測試帳號被鎖定，可執行 `DELETE FROM login_attempts;` 解除。

### 訓練成效

#### `game_records`：遊戲結算紀錄

| 欄位 | 說明 |
|---|---|
| `record_id` | 主鍵 |
| `user_id` | 遊玩者，參照 `users.user_id`；使用者被刪除時一併刪除紀錄 |
| `difficulty` | 使用的難度編號（對應 `difficulty_configs.diff_id`） |
| `survival_time` | 存活秒數 |
| `final_score` | 分數，由伺服器依存活時間與結局計算 |
| `end_reason` | 結局：`SUCCESS`、`FAILURE_RESOURCE`、`FAILURE_CRACKED`、`FAILURE_OVERLOAD` |
| `action_logs` | JSON 陣列，玩家輸入的指令（`time`、`cmd`、`timer_left`），供教師回放 |
| `played_at` | 結算時間 |

索引：`played_at` 與 `(user_id, played_at)`，供分頁與依學生查詢使用。

### 難度與題庫

#### `difficulty_configs`：訓練難度

| 欄位 | 說明 |
|---|---|
| `diff_id` | 主鍵；`0`、`1`、`2` 為系統預設難度 |
| `name`、`description` | 顯示名稱與說明 |
| `owner_user_id` | 擁有者教師；`NULL` 表示系統預設難度，所有核准學生皆可使用 |
| `total_time` | 訓練總秒數 |
| `attack_rates` | JSON，各攻擊類型（`syn`、`udp`、`dns`、`icmp`、`fishing`、`none`）對應的隨機數區間 |
| `command_policy` | JSON，各終端機指令是否啟用 |
| `attack_policy` | JSON，各攻擊類型是否啟用 |
| `game_settings` | JSON，攻擊冷卻、傷害倍率、破解速度、初始負載、密碼規則與字典攻擊設定 |
| `is_active` | 是否啟用 |
| `created_at`、`updated_at` | 建立與更新時間 |

#### `difficulty_assignments`：教師自訂難度分配

| 欄位 | 說明 |
|---|---|
| `assignment_id` | 主鍵 |
| `difficulty_id` | 被分配的難度，參照 `difficulty_configs.diff_id` |
| `student_id` | 取得使用權的學生，參照 `users.user_id` |
| `assigned_by` | 分配的教師，參照 `users.user_id` |
| `assigned_at` | 分配時間 |

`(difficulty_id, student_id)` 唯一。學生只能使用教師明確分配給自己的自訂難度；伺服器在取得難度清單與結算時都會檢查此表。

#### `threat_ips`：惡意 IP 題庫

`ip_address`、`attack_type`（如 SYN、UDP、DNS、ICMP）、`payload_desc`（封包特徵說明）與 `is_active`。遊戲產生攻擊封包，並在 `whois` 分析時使用。

#### `vip_ips`：正常業務 IP（誤殺陷阱）

`ip_address` 與 `description`。這些來源屬於正常業務，若被誤封鎖會扣分。

#### `phishing_emails`：郵件題庫

`sender`、`subject`、`content` 與 `is_malicious`（`1` 為釣魚信，`0` 為正常信），用於社交工程訓練。

### 預設資料

`sql.txt` 只在資料不存在時新增三個預設難度、威脅 IP、VIP IP 與郵件，重複匯入不會覆寫或清除任何現有資料。

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

`sql.txt` 的預設難度、威脅 IP、VIP IP 與郵件種子會在資料不存在時才新增；重複匯入不會清除教師難度、學生分配或管理員維護的題庫內容。

既有資料庫部署時也需匯入更新後的 `sql.txt`，以建立登入節流所需的 `login_attempts` 表。既有 `game_records` 表不會因 `CREATE TABLE IF NOT EXISTS` 自動補上新索引；請確認 `played_at` 與 `(user_id, played_at)` 索引是否存在，缺少時再手動新增。

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

### 部署與資源快取

- 訓練頁只允許已登入的學生、教師與管理員開啟，並對頁面設定不快取、禁止嵌入及基本安全回應標頭。
- 難度選單使用精簡資料庫查詢與回應；同一頁面工作階段只載入一次目錄，開始訓練時才讀取威脅與郵件題庫。
- 本機 CSS 與 JavaScript 模組及其相依檔案使用 SHA-256 內容雜湊版本，部署更新後可避免瀏覽器混用舊模組。
- Chart.js 與 Font Awesome 使用 CDN，部署環境需能連線至 jsDelivr 與 cdnjs；Chart.js 固定使用 4.4.7。
- JSON API 回應設定禁止快取；訓練紀錄採分頁讀取，回放時才另外取得單筆操作日誌。
- 遊戲主頁的 CSP 限制腳本來源，並使用 nonce 授權 import map；管理控制台頁面尚保留舊式 inline 腳本，需另行遷移後才能套用同等嚴格的 `script-src`。
- 密碼登入同一帳號／來源 IP 連續失敗 5 次會暫停 15 分鐘；SQL 初始化需建立 `login_attempts` 表。此節流是應用層補強，不取代部署端防暴力破解與監控。
- 結算 API 會驗證時間、結局與操作日誌格式，分數依通過的存活時間及結局計算。遊戲仍在瀏覽器執行，不能將客戶端回報的存活時間與結局視為具防竄改的競賽成績。

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
2. 管理員可在「帳號清單與審核」中管理教師／學生帳號、修改登入密碼，並在「動態題庫管理」中維護郵件題目。
3. 教師進入管理後台的「我的訓練難度」，建立難度並設定指令與攻擊開關，再將難度分配給指定學生。
4. 學生完成教師綁定並通過核准後，只會看到該教師明確分配給自己的啟用中自訂難度。
5. 學生在控制台查看目前綁定教師，選擇可用的教師難度開始訓練。
6. 從 [index.php](index.php) 選擇可用難度並確認任務簡報。
7. 依序觀察警報、分析流量、執行防禦指令與處理郵件。
8. 從主選單進入 [teaching/tutorial.php](teaching/tutorial.php) 查看互動教學。

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
| `unblock [IP/協定]` | 移除指定 IP／協定的封鎖規則或解除限速 |
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
