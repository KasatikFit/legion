/**
 * Микроанимации пилотной страницы: появление строк, вспышка при росте места, пульс рекордов.
 * Только pilot-demo; уважает prefers-reduced-motion.
 */
const LegionPilotMicro = {
    storageKey: 'legion-pilot-rank-snap',

    prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    },

    loadSnapshot(coachSlug) {
        try {
            const raw = sessionStorage.getItem(`${this.storageKey}:${coachSlug}`);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    },

    saveSnapshot(coachSlug, tab, ranksByName) {
        try {
            const all = this.loadSnapshot(coachSlug);
            all[tab] = ranksByName;
            sessionStorage.setItem(`${this.storageKey}:${coachSlug}`, JSON.stringify(all));
        } catch (e) { /* sessionStorage недоступен */ }
    },

    getRankUps(coachSlug, tab, currentRanks) {
        const prev = this.loadSnapshot(coachSlug)[tab] || {};
        const ups = [];
        Object.keys(currentRanks).forEach((name) => {
            const prevRank = prev[name];
            const curRank = currentRanks[name];
            if (typeof prevRank === 'number' && prevRank > 0 && curRank > 0 && curRank < prevRank) {
                ups.push(name);
            }
        });
        return ups;
    },

    getPreviewRankUps(coachSlug, tab, currentRanks, history) {
        if (Object.keys(this.loadSnapshot(coachSlug)[tab] || {}).length > 0) return [];
        if (!Array.isArray(history) || !history.length) return [];

        const cutoff = Date.now() - 14 * 86400000;
        const movers = new Set();
        history.forEach((entry) => {
            if (entry.event === 'mark_pass' || entry.exercise === 'mark_pass') return;
            const newVal = Number(entry.newVal);
            const oldVal = Number(entry.oldVal);
            if (isNaN(newVal) || isNaN(oldVal) || newVal <= oldVal) return;
            const parsed = typeof LegionCore !== 'undefined' && LegionCore.parseHistoryDate
                ? LegionCore.parseHistoryDate(entry.date)
                : new Date(0);
            if (parsed.getTime() >= cutoff) movers.add(entry.name);
        });

        return Object.keys(currentRanks)
            .filter((name) => movers.has(name))
            .slice(0, 2);
    },

    buildRankMap(athletes, tab) {
        const map = {};
        athletes.forEach((a) => {
            const rank = tab === 'overall'
                ? (a.overallRank || 0)
                : (a[tab + '_rank'] || 0);
            if (rank > 0) map[a.name] = rank;
        });
        return map;
    },

    applyTableAnimations(root, options) {
        if (!root || this.prefersReducedMotion()) return;
        const opts = options || {};

        (opts.rankUps || []).forEach((name) => {
            const esc = typeof CSS !== 'undefined' && CSS.escape
                ? CSS.escape(name)
                : name.replace(/"/g, '\\"');
            const row = root.querySelector(`.pilot-athlete-row[data-athlete="${esc}"]`);
            if (row) row.classList.add('legion-micro-rank-up');
        });
    },

    isRecentHallDate(text) {
        const m = String(text).match(/(\d{1,2})\.(\d{1,2})\.(\d{4})/);
        if (!m) return false;
        const d = new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
        const days = (Date.now() - d.getTime()) / 86400000;
        return days >= 0 && days <= 45;
    },

    applyHallAnimations(root) {
        if (!root || this.prefersReducedMotion()) return;

        root.querySelectorAll('.hall-feed-item').forEach((el, i) => {
            if (i < 2) el.classList.add('legion-micro-record-pulse');
        });

        root.querySelectorAll('.hall-card').forEach((card) => {
            const dateEl = card.querySelector('.hall-record-date');
            if (!dateEl) return;
            if (this.isRecentHallDate(dateEl.textContent)) {
                card.classList.add('legion-micro-record-pulse');
            }
        });
    }
};
