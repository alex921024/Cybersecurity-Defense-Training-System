class SystemStatus {
    constructor() {
        this.cpu = 12; this.gpu = 8; this.ram = 25; this.wifi = 5;
        this.timer = 0; 
        this.crackProgress = 0; 
    }

    applyDamage(type) {
        if (type === 'Fishing') {
            const inc = Math.floor(Math.random() * 4) + 2; 
            this.cpu = Math.min(100, this.cpu + inc);
            this.gpu = Math.min(100, this.gpu + inc);
            this.wifi = Math.min(100, this.wifi + inc);
            this.ram = Math.min(100, this.ram + inc);
        } else {
            const inc = Math.floor(Math.random() * 6) + 5; 
            this.gpu = Math.min(100, this.gpu + inc);
            this.wifi = Math.min(100, this.wifi + inc);
            this.ram = Math.min(100, this.ram + inc);
        }
    }

    reduceLoad(amount) {
        this.cpu = Math.max(5, this.cpu - amount);
        this.gpu = Math.max(5, this.gpu - amount);
        this.ram = Math.max(5, this.ram - amount);
        this.wifi = Math.max(5, this.wifi - amount);
    }

    checkStatus() {
        if (this.crackProgress >= 100) return "FAILURE_CRACKED";
        if (this.cpu >= 100 || this.gpu >= 100 || this.ram >= 100 || this.wifi >= 100) return "FAILURE_RESOURCE";
        if (this.timer <= 0) {
            return (this.cpu <= 75 && this.gpu <= 75 && this.ram <= 75 && this.wifi <= 75) ? "SUCCESS" : "FAILURE_OVERLOAD";
        }
        return "RUNNING";
    }
}
export default SystemStatus;