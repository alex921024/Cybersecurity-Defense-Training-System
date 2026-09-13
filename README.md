# 資安防禦訓練系統

Cybersecurity Defense Training System 是一套以 PHP、MySQL 與原生 JavaScript 開發的瀏覽器資安防禦訓練平台。使用者將扮演藍隊防禦人員，透過網路封包監控、威脅情報分析、終端機指令與郵件辨識，學習處理常見的模擬資安事件。

## 功能特色

- 即時封包監控：檢視 TCP、UDP、ICMP 與 DNS 流量。
- 威脅情報分析：分析可疑 IP 與攻擊協定。
- 防火牆策略操作：封鎖流量、來源 IP、DNS 快取與其他攻擊面。
- 虛擬終端機：使用 `status`、`netstat`、`whois`、`block`、`scan-mail` 等指令進行防禦。
- 社交工程訓練：辨識正常郵件與釣魚郵件。
- 系統狀態模擬：追蹤 CPU、GPU、RAM、Wi-Fi 與密碼破解進度。
- 三種訓練難度：簡單、普通與困難。
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
│   └── student/    # 學生資料與訓練紀錄
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
http://localhost:8000/assets/html/login.html
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
2. 登入後依角色進入學生控制台或管理後台。
3. 從學生控制台進入 [index.php](index.php) 開始訓練。
4. 選擇難度並確認任務簡報。
5. 依序觀察警報、分析流量、執行防禦指令與處理郵件。
6. 從主選單進入 [teaching/tutorial.php](teaching/tutorial.php) 查看互動教學。

## 防禦指令

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
