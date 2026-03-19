import GameDB from './database.js';

class ThreatEngine {
    constructor(difficulty) {
        this.config = GameDB.difficulties[difficulty];
    }

    generateEvent() {
        const dice = Math.floor(Math.random() * 100) + 1;
        const r = this.config.ranges;

        if (this.inRange(dice, r.syk)) return "SYK";
        if (this.inRange(dice, r.udp)) return "UDP";
        if (this.inRange(dice, r.dns)) return "DNS";
        if (this.inRange(dice, r.icmp)) return "ICMP";
        if (this.inRange(dice, r.fishing)) return "Fishing";
        return "Normal";
    }

    inRange(val, range) {
        return range && val >= range[0] && val <= range[1];
    }
}
export default ThreatEngine;