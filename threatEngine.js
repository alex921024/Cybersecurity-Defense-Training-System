import GameDB from './database.js';

class ThreatEngine {
    constructor(difficulty) {
        this.config = GameDB.difficulties[difficulty];
    }

    generateEvent() {
        const dice = Math.floor(Math.random() * 100) + 1;
        const r = this.config.ranges;

        if (this.inRange(dice, r.syn)) return "syn";
        if (this.inRange(dice, r.udp)) return "udp";
        if (this.inRange(dice, r.dns)) return "dns";
        if (this.inRange(dice, r.icmp)) return "icmp";
        if (this.inRange(dice, r.fishing)) return "fishing";
        return null;
    }

    inRange(val, range) {
        return range && val >= range[0] && val <= range[1];
    }
}
export default ThreatEngine;