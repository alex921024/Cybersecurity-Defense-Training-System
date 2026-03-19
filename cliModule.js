class CLIModule {
    constructor(gameManager) {
        this.gm = gameManager; 
    }

    execute(command) {
        const args = command.trim().toLowerCase().split(' ');
        const cmd = args[0];

        switch (cmd) {
            case 'help':
                return `[系統指令參考]\n- status    : 查看系統狀態\n- ipconfig  : 查詢網路設定\n- ping [IP] : 測試連線\n- netstat   : 顯示當前異常連線統計\n- block [arg]: 封鎖流量 (例如: block udp 或 block ip)\n- flush-dns : 清除 DNS 快取\n- scan-mail : 掃描並隔離釣魚郵件\n- passwd    : 更改密碼防禦破解\n- clear     : 清空畫面\n※ 點選介面上方「📘 指令手冊」可查看詳細說明。`;
            
            case 'status':
                return `[系統狀態報告]\nCPU: ${Math.floor(this.gm.status.cpu)}% | GPU: ${Math.floor(this.gm.status.gpu)}%\nRAM: ${Math.floor(this.gm.status.ram)}% | WiFi: ${Math.floor(this.gm.status.wifi)}%\n密碼破解風險進度: ${Math.floor(this.gm.status.crackProgress)}%`;

            case 'ipconfig':
                return `IPv4 位址 . . . . . . . . . . . : 10.0.0.1\n子網路遮罩 . . . . . . . . . . . : 255.255.255.0\n預設閘道 . . . . . . . . . . . . : 10.0.0.254`;

            case 'ping':
                if (!args[1]) return "用法: ping [Target IP]";
                return `回覆自 ${args[1]}: 位元組=32 時間=${Math.floor(Math.random()*50+10)}ms TTL=54`;

            case 'netstat':
                if (this.gm.activeThreat === "syn" || this.gm.activeThreat === "udp" || this.gm.activeThreat === "icmp") {
                    return `[警告] 發現大量異常連線 (協定: ${this.gm.activeThreat.toUpperCase()}) 來自 103.24.55.12\n建議立即使用 'block' 指令封鎖。`;
                }
                return "目前網路連線狀態正常，無異常流量。";

          case 'block':
                if (!args[1]) return "錯誤: 缺少參數。用法: block udp 或 block ip";
                if (args[1] === this.gm.activeThreat || args[1] === 'ip') {
                    if (this.gm.activeThreat && this.gm.activeThreat !== 'fishing' && this.gm.activeThreat !== 'dns') {
                        this.gm.stats[this.gm.activeThreat]++;
                    }
                    this.gm.status.reduceLoad(20);
                    this.gm.activeThreat = null;
                    return `[執行成功] 防火牆規則已更新：成功阻擋 ${args[1].toUpperCase()} 攻擊流量！系統負載下降。`;
                }
                return `[提示] 已設定封鎖規則 ${args[1]}，但目前並未偵測到該類型的主要威脅。`;

            case 'flush-dns':
                if (this.gm.activeThreat === "dns") {
                    this.gm.stats.dns++; 
                    this.gm.status.reduceLoad(20);
                    this.gm.activeThreat = null;
                    return "[執行成功] DNS 快取已成功清除，連線已重新導向安全解析伺服器。";
                }
                return "DNS 快取已清除。目前無 DNS 異常。";

            case 'scan-mail':
                if (this.gm.activeThreat === "fishing") {
                    this.gm.stats.fishing++; 
                    this.gm.status.reduceLoad(15);
                    this.gm.activeThreat = null;
                    return "[執行成功] 正在掃描收件匣... 發現並已隔離 1 封夾帶惡意連結的釣魚郵件！";
                }
                return "郵件掃描完成。未發現可疑釣魚郵件。";

            case 'passwd': 
                this.gm.status.crackProgress = 0;
                return "請輸入新密碼: ******** \n[成功] Root 密碼變更完成！駭客的字典破解進度已強制歸零。";

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