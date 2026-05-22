class CLIModule {
    constructor(gameManager) {
        this.gm = gameManager; 
    }

    execute(command) {
        const args = command.trim().toLowerCase().split(' ');
        const cmd = args[0];

        switch (cmd) {
            case 'help':
                return `[系統指令參考]\n- status    : 查看系統狀態\n- ipconfig  : 查詢網路設定\n- ping [IP] : 測試連線\n- netstat   : 顯示當前異常連線\n- whois [arg]: ⚠️ 分析目標IP或協定\n- block [arg]: 封鎖(需先分析)\n- flush-dns : 淨化DNS(需先分析)\n- scan-mail : 掃描並隔離釣魚郵件\n- passwd    : 更改密碼防禦破解\n- clear     : 清空畫面`;
            
            case 'status':
                return `[狀態]\nCPU: ${Math.floor(this.gm.status.cpu)}% | GPU: ${Math.floor(this.gm.status.gpu)}%\nRAM: ${Math.floor(this.gm.status.ram)}% | WiFi: ${Math.floor(this.gm.status.wifi)}%\n破解進度: ${Math.floor(this.gm.status.crackProgress)}%`;

            case 'ipconfig':
                return `IPv4 位址 . . . : 10.0.0.1\n子網路遮罩 . . . : 255.255.255.0\n預設閘道 . . . . : 10.0.0.254`;

            case 'ping':
                if (!args[1]) return "用法: ping [IP]";
                return `回覆自 ${args[1]}: 時間=${Math.floor(Math.random()*50+10)}ms TTL=54`;

            case 'netstat':
                if (this.gm.activeThreat === "syn" || this.gm.activeThreat === "udp" || this.gm.activeThreat === "icmp") {
                    return `[警告] 發現大量異常連線 (協定: ${this.gm.activeThreat.toUpperCase()}) 來自 103.24.55.12\n建議立即使用 'whois' 進行分析。`;
                }
                return "網路連線正常，無異常。";

            case 'whois':
            case 'analyze':
                if (!args[1]) return "錯誤。用法: whois [IP或協定] (如: whois udp)";
                return this.gm.analyzeThreat(args[1]);

            case 'block':
                if (!args[1]) return "錯誤。用法: block udp 或 block ip";
                return this.gm.applyBlockAction(args[1]);

            case 'flush-dns':
                return this.gm.applyFlushDns();

            case 'scan-mail':
                if (this.gm.activeThreat === "fishing") {
                    this.gm.stats.fishing++; this.gm.status.reduceLoad(15); this.gm.activeThreat = null;
                    this.gm.updateIdsAlert("[信件防禦] 成功隔離惡意威脅郵件。");
                    return "[執行成功] 發現並已隔離 1 封釣魚郵件！";
                }
                return "未發現可疑釣魚郵件。";

            case 'passwd': 
                this.gm.status.crackProgress = 0;
                return "請輸入新密碼: ******** \n[成功] 密碼變更完成！破解進度歸零。";

            case 'clear':
                document.getElementById('terminal-output').innerHTML = '';
                return "";

            default:
                if (cmd !== '') return `bash: ${cmd}: command not found`;
                return "";
        }
    }
}
export default CLIModule;