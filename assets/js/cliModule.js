class CLIModule {
    constructor(gameManager) {
        this.gm = gameManager; 
    }

    execute(command) {
        const args = command.trim().toLowerCase().split(/\s+/);
        const cmd = args[0];
        const commandName = cmd === 'analyze' ? 'whois' : cmd;
        const policyCommands = ['status', 'netstat', 'whois', 'limit', 'block', 'unblock', 'flush-dns', 'scan-mail', 'passwd'];

        if (policyCommands.includes(commandName) && !this.gm.isCommandEnabled(commandName)) {
            return `[拒絕執行] 此訓練難度未啟用 ${commandName} 指令。`;
        }

        switch (cmd) {
            case 'help':
                return this.getHelpText();
            
            case 'status':
                const totalMail = this.gm.inbox.length;
                const phishingCount = this.gm.inbox.filter(mail => mail.isMalicious).length;
                const mailInfo = totalMail > 0 ? `\n郵件: ${totalMail} 封 (${phishingCount} 封可疑)` : "";
                return `[狀態]\nCPU: ${Math.floor(this.gm.status.cpu)}% | GPU: ${Math.floor(this.gm.status.gpu)}%\nRAM: ${Math.floor(this.gm.status.ram)}% | WiFi: ${Math.floor(this.gm.status.wifi)}%\n破解進度: ${Math.floor(this.gm.status.crackProgress)}%${mailInfo}`;

            case 'ipconfig':
                return `IPv4 位址 . . . : 10.0.0.1\n子網路遮罩 . . . : 255.255.255.0\n預設閘道 . . . . : 10.0.0.254`;

            case 'ping':
                if (!args[1]) return "用法: ping [IP]";
                return `回覆自 ${args[1]}: 時間=${Math.floor(Math.random()*50+10)}ms TTL=54`;

            case 'netstat':
                if (this.gm.activeThreat === "syn" || this.gm.activeThreat === "udp" || this.gm.activeThreat === "icmp") {
                    return `[警告] 發現大量異常連線 (協定: ${this.gm.activeThreat.toUpperCase()}) 來自 103.24.55.12\n建議立即使用 'whois' 進行分析。`;
                }
                if (this.gm.activeThreat === "dns") {
                    return `[警告] 網路連線顯示 DNS 流量異常，疑似放大攻擊。請使用 'whois dns' 或 'flush-dns' 檢查。`;
                }
                if (this.gm.activeThreat === "fishing") {
                    return `[警告] 網路連線正常，但 IDS 監測到社交工程攻擊。請檢查收件匣或使用 'scan-mail'。`;
                }
                return "網路連線正常，無異常。";

            case 'whois':
            case 'analyze':
                if (!args[1]) return "錯誤。用法: whois [IP或協定] (如: whois udp)";
                return this.gm.analyzeThreat(args[1]);

            case 'block':
                if (!args[1]) return "錯誤。用法: block udp 或 block ip";
                return this.gm.applyBlockAction(args[1]);

            case 'limit':
                if (!args[1]) return "錯誤。用法: limit [協定] (例如: limit udp)";
                return this.gm.applyLimitAction(args[1]);

            case 'unblock':
                if (!args[1]) return "錯誤。用法: unblock [協定/IP]";
                return this.gm.unblockAction(args[1]);

            case 'flush-dns':
                return this.gm.applyFlushDns();

            case 'scan-mail':
                return this.gm.scanMailInbox();

            case 'passwd':
                return "請至「系統狀態」的密碼防護區輸入新密碼與確認密碼。";

            case 'clear':
                document.getElementById('terminal-output').innerHTML = '';
                return "";

            default:
                if (cmd !== '') return `bash: ${cmd}: command not found`;
                return "";
        }
    }

    getHelpText() {
        const helpItems = [
            ['status', '查看系統狀態'],
            ['ipconfig', '查詢網路設定'],
            ['ping [IP]', '測試連線'],
            ['netstat', '顯示當前異常連線'],
            ['whois [arg]', '分析目標 IP 或協定'],
            ['block [arg]', '封鎖 IP 或協定'],
            ['limit [arg]', '暫時限制指定協定流量'],
            ['unblock [arg]', '解除封鎖或限速'],
            ['flush-dns', '清除 DNS 快取'],
            ['scan-mail', '掃描並隔離釣魚郵件'],
            ['passwd', '重置密碼破解進度'],
            ['clear', '清空畫面']
        ];
        return `[系統指令參考]\n${helpItems
            .filter(([name]) => !policyCommandsForHelp(name) || this.gm.isCommandEnabled(name))
            .map(([name, description]) => `- ${name} : ${description}`)
            .join('\n')}`;
    }
}

function policyCommandsForHelp(command) {
    return ['status', 'netstat', 'whois', 'block', 'limit', 'unblock', 'flush-dns', 'scan-mail', 'passwd'].includes(command);
}
export default CLIModule;