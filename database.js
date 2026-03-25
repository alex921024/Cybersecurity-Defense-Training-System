const GameDB = {
    difficulties: {
        0: { time: 240,  ranges: { syn: [1, 15], udp: [16, 30], dns: null, icmp: null, fishing: [31, 45], none: [46, 100] } },
        1: { time: 240, ranges: { syn: [1, 10], udp: [11, 20], dns: [21, 30], icmp: null, fishing: [31, 60], none: [61, 100] } },
        2: { time: 480, ranges: { syn: [1, 10], udp: [11, 25], dns: [26, 40], icmp: [41, 55], fishing: [56, 75], none: [76, 100] } }
    },
    maliciousIPs: ["103.24.55.12", "45.22.19.8", "188.166.25.190", "8.8.8.8", "167.99.14.22"],
    security: { accountLen: 6, passwordLen: 10 },
    
    emails: {
        normal: [
            { sender: "HR 部門", subject: "本月出勤紀錄確認", content: "請同仁至差勤系統確認本月打卡紀錄是否有誤，若有請假未簽核請盡速處理。" },
            { sender: "IT Support", subject: "系統例行維護通知", content: "伺服器將於本週五晚間進行系統升級，屆時內部網路將中斷 15 分鐘，請見諒。" },
            { sender: "總務處", subject: "下午茶訂購統計", content: "各位同仁好，今天的下午茶是雞排配珍奶，請要訂購的同仁於下午兩點前至總務處登記。" },
            { sender: "系統自動通知", subject: "[成功] DB-01 每日備份報告", content: "系統排程通知：\n昨晚的資料庫排程備份已成功完成，共計 45GB，無錯誤日誌產生。" },
            { sender: "行銷部_林專員", subject: "Q3 行銷活動企劃書初稿", content: "各位好，附件是下季度的企劃書草案，請大家在週三前提供修改建議，我們預計週五開會討論。" },
            { sender: "AWS Billing", subject: "Amazon Web Services Invoice Available", content: "您本月的 AWS 服務帳單已結算，總計 $450.00 USD，款項將於三日內從系統預設之信用卡扣除。" },
            { sender: "法務部", subject: "員工保密協議 (NDA) 條款更新", content: "配合最新個資法規範，公司已更新內部保密協議。請同仁有空時至內網 EIP 系統詳閱新版條款。" },
            { sender: "研發部_張經理", subject: "專案開發進度週報", content: "本週進度符合預期，前端 UI 已經完成 80%，後端 API 正在進行壓力測試，感謝各位的努力。" }
        ],
        malicious: [
            { sender: "IT_Admin (外部)", subject: "[警告] 您的網域密碼即將過期", content: "系統偵測到您的密碼將在 24 小時內過期。\n\n請立即點擊以下連結進行重置：\nhttp://sec-update-portal-login.com/reset\n\n若未完成，您的帳號將被永久凍結。" },
            { sender: "Apple_Support_Team", subject: "Apple ID 異常登入警告", content: "我們偵測到您的帳號在海外有異常登入嘗試。\n\n請回覆此郵件，並附上您的身分證件照片與原密碼以解鎖帳號。" },
            { sender: "財務部_王經理", subject: "RE: 2026年度財務報表 (機密)", content: "附件是今年的財務報表與員工薪資清單，請點擊連結下載查看：\nhttp://download-finance-report-2026.zip.exe \n\n請勿外流。" },
            { sender: "總經理 (CEO)", subject: "急件！匯款指示 (人在外開會)", content: "我現在在外面與客戶開會，不方便接電話。\n\n請立刻幫我匯款 $15,000 美金到這個合作夥伴的帳戶，這是急件！匯款帳戶詳情請點擊下方連結確認：\nhttp://ceo-urgent-transfer-auth.com/doc" },
            { sender: "Microsoft 365 支援", subject: "[重要] 您的信箱容量已滿", content: "您的企業信箱配額已達 99%。\n\n為避免無法收發客戶信件，請立即點擊此處升級您的儲存空間 (請注意網址拼字)：\nhttp://mircosoft-365-update.com/login" },
            { sender: "FedEx 快遞通知", subject: "包裹投遞失敗通知 (追蹤碼: TW8831994)", content: "您的包裹因地址錯誤無法投遞。\n\n請下載附件的電子託運單 (Shipping_Label.pdf.exe) 確認正確地址，並重新安排配送時間。" },
            { sender: "人資部 (偽造)", subject: "2026 年度調薪名單確認", content: "各位同仁好：\n附件是明年度的各部門調薪幅度與名單總表 (Salary_Adjustment_2026.xlsx.vbs)。\n請各主管下載後輸入員工編號確認是否有誤。" },
            { sender: "網路管理中心", subject: "VPN 憑證更新通知 (強制執行)", content: "因應近期資安威脅，公司 VPN 憑證已全面更新。\n\n請同仁務必於今日下班前點擊連結下載並安裝新版憑證，否則明日將無法連線至公司內網：\nhttp://vpn-cert-update-portal.net/download" }
        ]
    }
};
export default GameDB;