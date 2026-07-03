/**
 * Общая логика: CSV, рейтинг, API, ранги, достижения, модалки.
 */
const LegionCore = {
    state: {
        athletesData: [],
        overallSorted: [],
        currentTab: 'overall',
        rankData: {},
        serverHistory: [],
        serverAchievements: {},
        serverLastResults: {},
        searchQuery: '',
        openAthleteName: null,
        loadWarnings: []
    },

    // ---------- CSV & рейтинг ----------

    stripCsvBom(text) {
        return String(text || '').replace(/^\uFEFF/, '');
    },

    normalizePersonName(name) {
        let s = String(name || '').replace(/\u00a0/g, ' ').trim().replace(/\s+/g, ' ');
        if (typeof s.normalize === 'function') {
            s = s.normalize('NFC');
        }
        return s;
    },

    isRankMark(value) {
        let v = String(value || '').trim().replace(/^["']|["']$/g, '');
        if (!v) return false;
        const lower = v.toLowerCase();
        if (lower === 'true' || lower === 'yes' || lower === 'да') return true;
        if (lower === 'false' || lower === '0' || lower === 'нет' || lower === 'no') return false;
        if (v === '✓' || v === '+' || lower === 'x' || lower === 'х') return true;
        const num = Number(v);
        if (!isNaN(num) && num > 0) return true;
        return false;
    },

    parseRankMarksFromRow(cols, headers, nameIdx) {
        const marks = [];
        for (let j = 0; j < headers.length; j++) {
            if (j === nameIdx) continue;
            marks.push(this.isRankMark(cols[j]) ? 1 : 0);
        }
        while (marks.length < 60) marks.push(0);
        return marks.slice(0, 60);
    },

    parseCSV(text) {
        const lines = this.stripCsvBom(text).trim().split(/\r?\n/);
        if (lines.length < 2) return [];
        const headers = lines[0].split(',').map(h => h.trim());
        const exercises = LegionConfig.EXERCISES;
        const nameIdx = headers.findIndex(h => {
            const l = h.toLowerCase();
            return l.includes('фио') || l.includes('имя');
        });
        const photoIdx = headers.findIndex(h => h.toLowerCase().includes('фото'));
        const colIdx = {};
        const missing = [];

        exercises.forEach(ex => {
            const match = LegionConfig.getExerciseCsvMatch(ex);
            const idx = headers.findIndex(h => h.toLowerCase().includes(match));
            colIdx[ex.key] = idx;
            if (idx === -1) missing.push(ex.label);
        });

        if (nameIdx === -1 || missing.length > 0) {
            throw new Error('Нет нужных столбцов' + (missing.length ? ': ' + missing.join(', ') : ''));
        }

        const minCols = Math.max(nameIdx, photoIdx, ...Object.values(colIdx)) + 1;
        const data = [];

        for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(',').map(c => c.trim());
            if (cols.length < minCols) continue;
            const name = this.normalizePersonName(cols[nameIdx]);
            const row = { name, photo: photoIdx >= 0 ? (cols[photoIdx] || '') : '' };
            let valid = !!name;

            exercises.forEach(ex => {
                const val = parseFloat(cols[colIdx[ex.key]]);
                row[ex.key] = val;
                if (isNaN(val)) valid = false;
            });

            if (!valid) continue;
            data.push(row);
        }
        return data;
    },

    parseRankCSV(text) {
        const lines = this.stripCsvBom(text).trim().split(/\r?\n/).filter(l => l.trim());
        if (lines.length < 2) return [];
        const headers = lines[0].split(',').map(h => h.trim());
        const nameIdx = headers.findIndex(h => {
            const l = h.toLowerCase();
            return l.includes('фио') || l.includes('имя');
        });
        if (nameIdx === -1) return [];

        const entries = [];
        for (let i = 1; i < lines.length; i++) {
            const cols = lines[i].split(',').map(c => c.trim());
            const marks = this.parseRankMarksFromRow(cols, headers, nameIdx);
            if (!marks.some(m => m)) continue;
            entries.push({
                name: this.normalizePersonName(cols[nameIdx] || ''),
                marks
            });
        }
        return entries;
    },

    mergeCoachRankEntries(entries, athletesData, coach) {
        const merged = {};
        const coachNames = athletesData
            .filter(a => a.coachSlug === coach.slug)
            .map(a => this.normalizePersonName(a.name));
        let fallbackIdx = 0;

        entries.forEach((entry) => {
            let name = entry.name;
            if (!name) {
                while (fallbackIdx < coachNames.length && merged[coachNames[fallbackIdx]]) {
                    fallbackIdx++;
                }
                if (fallbackIdx < coachNames.length) {
                    name = coachNames[fallbackIdx];
                    fallbackIdx++;
                }
            }
            if (!name) return;
            merged[this.normalizePersonName(name)] = entry.marks;
        });
        return merged;
    },

    lookupRankMarks(name) {
        const data = this.state.rankData || {};
        const normalized = this.normalizePersonName(name);
        if (data[normalized]) return data[normalized];
        if (data[name]) return data[name];

        const keys = Object.keys(data);
        for (let i = 0; i < keys.length; i++) {
            const key = keys[i];
            if (key.startsWith(normalized) || normalized.startsWith(key)) {
                const minLen = Math.min(key.length, normalized.length);
                if (minLen >= 6) return data[key];
            }
        }
        return null;
    },

    pointsForRank(rank) {
        if (rank < 1) return 0;
        if (rank === 1) return 100;
        if (rank === 2) return 95;
        if (rank === 3) return 90;
        return Math.max(0, 90 - (rank - 3) * 2);
    },

    calculateAllRatings(dataArr) {
        const keys = LegionConfig.getExerciseKeys();
        dataArr.forEach(a => {
            keys.forEach(ex => {
                a[ex + '_points'] = 0;
                a[ex + '_rank'] = 0;
            });
            a.total = 0;
        });
        keys.forEach(ex => {
            const nonZero = dataArr.filter(a => a[ex] > 0);
            const sorted = [...nonZero].sort((a, b) => b[ex] - a[ex]);
            let currentRank = 1;
            let prevResult = null;
            sorted.forEach((athlete, idx) => {
                if (athlete[ex] !== prevResult) currentRank = idx + 1;
                prevResult = athlete[ex];
                athlete[ex + '_points'] = this.pointsForRank(currentRank);
                athlete[ex + '_rank'] = currentRank;
            });
        });
        dataArr.forEach(a => {
            a.total = keys.reduce((sum, ex) => sum + a[ex + '_points'], 0);
        });
    },

    sortByTotal(dataArr) {
        const tieKeys = LegionConfig.TIE_BREAK_KEYS || LegionConfig.getExerciseKeys();
        return dataArr
            .filter(a => a.total > 0)
            .sort((a, b) => {
                if (b.total !== a.total) return b.total - a.total;
                for (let i = 0; i < tieKeys.length; i++) {
                    const ex = tieKeys[i];
                    if (b[ex] !== a[ex]) return b[ex] - a[ex];
                }
                return 0;
            });
    },

    initExerciseSorted(athletes) {
        LegionConfig.getExerciseKeys().forEach(key => {
            this.state[key + 'Sorted'] = this.buildExerciseSorted(athletes, key);
        });
    },

    buildExerciseSorted(athletes, exKey) {
        return athletes.filter(a => a[exKey] > 0).sort((a, b) => b[exKey] - a[exKey]);
    },

    async fetchWithTimeout(url, options, timeoutMs) {
        const ms = timeoutMs || 45000;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), ms);
        try {
            return await fetch(url, { ...options, signal: controller.signal });
        } catch (e) {
            if (e && e.name === 'AbortError') {
                throw new Error('Превышено время ожидания ответа. Проверьте интернет и обновите страницу.');
            }
            throw e;
        } finally {
            clearTimeout(timer);
        }
    },

    async fetchApi(url, options) {
        return this.fetchWithTimeout(url, options, 15000);
    },

    async loadAllAthletes() {
        const coaches = LegionConfig.getCoaches();
        if (!coaches.length) {
            throw new Error('Список тренеров пуст. Обновите страницу или проверьте api/coaches.php на сервере.');
        }

        this.state.loadWarnings = [];

        const settled = await Promise.allSettled(coaches.map(async (coach) => {
            const resp = await this.fetchWithTimeout(coach.csvUrl);
            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status} — не удалось загрузить таблицу`);
            }
            const text = await resp.text();
            const parsed = this.parseCSV(text);
            if (parsed.length === 0) {
                throw new Error('Таблица пуста или нет строк с результатами');
            }
            return parsed.map(a => ({
                ...a,
                coach: coach.name,
                coachSlug: coach.slug
            }));
        }));

        const athletesData = [];
        settled.forEach((result, index) => {
            const coach = coaches[index];
            if (result.status === 'fulfilled') {
                athletesData.push(...result.value);
                return;
            }
            const message = (result.reason && result.reason.message)
                ? result.reason.message
                : String(result.reason || 'Неизвестная ошибка');
            this.state.loadWarnings.push({
                coach: coach.name,
                slug: coach.slug,
                message
            });
        });

        if (athletesData.length === 0) {
            const details = this.state.loadWarnings
                .map(w => `${w.coach}: ${w.message}`)
                .join('; ');
            throw new Error(details
                ? `Нет данных ни в одной таблице. ${details}`
                : 'Нет данных ни в одной таблице');
        }

        return athletesData;
    },

    renderLoadWarnings() {
        const warnings = this.state.loadWarnings || [];
        let el = document.getElementById('legion-load-warnings');

        if (!warnings.length) {
            if (el) el.remove();
            return;
        }

        const esc = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        if (!el) {
            el = document.createElement('div');
            el.id = 'legion-load-warnings';
            el.className = 'legion-warnings no-print';
            const content = document.getElementById('content');
            if (content && content.parentNode) {
                content.parentNode.insertBefore(el, content);
            } else {
                document.body.insertBefore(el, document.body.firstChild);
            }
        }

        const items = warnings.map(w =>
            `<li><strong>${esc(w.coach)}</strong>: ${esc(w.message)}</li>`
        ).join('');

        el.innerHTML = `
            <div class="legion-warnings-inner">
                <p class="legion-warnings-title">Часть групп не загрузилась</p>
                <ul class="legion-warnings-list">${items}</ul>
                <p class="legion-warnings-hint">Рейтинг показан по доступным таблицам. <a href="/diagnostics/">Открыть диагностику</a></p>
            </div>`;
    },

    normalizeRankMarksValue(marks) {
        if (Array.isArray(marks)) {
            const padded = marks.map(m => Number(m) > 0 ? 1 : 0);
            while (padded.length < 60) padded.push(0);
            return padded.slice(0, 60);
        }
        if (marks && typeof marks === 'object') {
            const padded = [];
            for (let i = 0; i < 60; i++) {
                const v = marks[i] !== undefined ? marks[i] : marks[String(i)];
                padded.push(Number(v) > 0 ? 1 : 0);
            }
            return padded;
        }
        return null;
    },

    normalizeRankDataFromServer(ranks) {
        const normalized = {};
        Object.keys(ranks || {}).forEach((key) => {
            const marks = this.normalizeRankMarksValue(ranks[key]);
            if (!marks) return;
            normalized[this.normalizePersonName(key)] = marks;
        });
        return normalized;
    },

    applyRankData(rankData, athletesData = []) {
        this.state.rankData = rankData || {};
        athletesData.forEach((a) => {
            a.rankMarks = this.lookupRankMarks(a.name);
        });
    },

    async loadRanksFromGoogle(athletesData = []) {
        const coaches = LegionConfig.getCoaches().filter(c => c.ranksCsvUrl);
        if (!coaches.length) {
            return {};
        }

        const merged = {};
        const settled = await Promise.allSettled(coaches.map(async (coach) => {
            const resp = await this.fetchWithTimeout(coach.ranksCsvUrl);
            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status} — не удалось загрузить таблицу рангов`);
            }
            return {
                coach,
                entries: this.parseRankCSV(await resp.text())
            };
        }));

        settled.forEach((result) => {
            if (result.status !== 'fulfilled') {
                return;
            }
            const { coach, entries } = result.value;
            const data = this.mergeCoachRankEntries(entries, athletesData, coach);
            Object.keys(data).forEach((name) => {
                merged[name] = data[name];
            });
        });

        return merged;
    },

    async loadRanks(athletesData = []) {
        if (typeof window !== 'undefined' && window.__legionRanksFromServer) {
            const fromPage = this.normalizeRankDataFromServer(window.__legionRanksFromServer);
            if (Object.keys(fromPage).length > 0) {
                return fromPage;
            }
        }

        const { API } = LegionConfig;

        try {
            const resp = await this.fetchApi(API.ranksLoad);
            if (resp.ok) {
                const payload = await resp.json();
                const ranks = this.normalizeRankDataFromServer(payload.ranks || {});
                if (Object.keys(ranks).length > 0) {
                    if (Array.isArray(payload.coaches)) {
                        payload.coaches.forEach((stat) => {
                            if (!stat.ok && stat.error) {
                                this.state.loadWarnings.push({
                                    coach: stat.name,
                                    slug: stat.slug,
                                    message: 'Ранги: ' + stat.error
                                });
                            }
                        });
                    }
                    return ranks;
                }
            }
        } catch (e) {
            console.warn('Загрузка рангов через API не удалась:', e);
        }

        const coaches = LegionConfig.getCoaches().filter(c => c.ranksCsvUrl);
        if (!coaches.length) {
            console.warn('Нет таблиц рангов у тренеров (ranksCsvUrl в coaches.php)');
            return {};
        }

        const merged = await this.loadRanksFromGoogle(athletesData);
        if (Object.keys(merged).length === 0) {
            this.state.loadWarnings.push({
                coach: 'Ранги',
                slug: '',
                message: 'Ранги: не удалось загрузить — залейте api/get_ranks.php и api/ranks_lib.php на сервер'
            });
        }
        return merged;
    },

    getClubRank(name) {
        const marks = this.lookupRankMarks(name);
        const cfg = LegionConfig;
        if (!marks) return null;

        let count3 = 0;
        for (let i = 0; i < 20; i++) if (Number(marks[i]) > 0) count3++;
        if (count3 < 20) {
            return {
                league: 3, completed: count3, total: 20,
                rankName: count3 > 0 ? cfg.league3Names[count3 - 1] : null,
                index: count3, exercises: cfg.league3Exercises, names: cfg.league3Names
            };
        }

        let count2 = 0;
        for (let i = 20; i < 40; i++) if (Number(marks[i]) > 0) count2++;
        if (count2 < 20) {
            return {
                league: 2, completed: count2, total: 20,
                rankName: count2 > 0 ? cfg.league2Names[count2 - 1] : null,
                index: count2, exercises: cfg.league2Exercises, names: cfg.league2Names
            };
        }

        let count1 = 0;
        for (let i = 40; i < 60; i++) if (Number(marks[i]) > 0) count1++;
        return {
            league: 1, completed: count1, total: 20,
            rankName: count1 > 0 ? cfg.league1Names[count1 - 1] : null,
            index: count1, exercises: cfg.league1Exercises, names: cfg.league1Names
        };
    },

    // ---------- История ----------

    async loadHistoryFromServer() {
        const { API } = LegionConfig;
        try {
            const resp = await this.fetchApi(API.historyLoad);
            if (!resp.ok) {
                this.state.serverHistory = [];
                return;
            }
            const raw = await resp.json();
            this.state.serverHistory = this.trimHistoryPerAthlete(Array.isArray(raw) ? raw : []);
        } catch (e) {
            console.warn('Ошибка загрузки истории:', e);
            this.state.serverHistory = [];
        }
    },

    trimHistoryPerAthlete(history) {
        const limit = LegionConfig.HISTORY_PER_ATHLETE || 50;
        const byName = {};
        history.forEach((entry, i) => {
            const name = entry.name || '';
            if (!byName[name]) byName[name] = [];
            byName[name].push(i);
        });
        const keep = new Set();
        Object.values(byName).forEach(indices => {
            indices.slice(-limit).forEach(i => keep.add(i));
        });
        return history.filter((_, i) => keep.has(i));
    },

    async saveHistoryToServer(entries) {
        const { API } = LegionConfig;
        try {
            await fetch(API.historySave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entries })
            });
            this.state.serverHistory = this.trimHistoryPerAthlete(
                this.state.serverHistory.concat(entries)
            );
        } catch (e) {
            console.warn('Ошибка сохранения истории:', e);
        }
    },

    getHistory() {
        return this.state.serverHistory;
    },

    addHistoryEntries(entries) {
        if (entries.length > 0) this.saveHistoryToServer(entries);
    },

    computeEliteRotation(prevList, sortedAthletes, keepCount, targetSize) {
        const names = sortedAthletes.map(a => a.name);
        if (prevList.length === 0) {
            const list = names.slice(0, targetSize);
            return { list, out: [], up: list };
        }

        const keep = prevList.slice(0, keepCount);
        const outsiders = names.filter(n => !keep.includes(n));
        const promoteCount = Math.max(0, targetSize - keep.length);
        const promote = outsiders.slice(0, promoteCount);
        const newSet = new Set([...keep, ...promote]);

        if (newSet.size < targetSize) {
            for (const name of outsiders) {
                if (!newSet.has(name)) {
                    newSet.add(name);
                    if (newSet.size === targetSize) break;
                }
            }
        }

        const list = [];
        for (const athlete of sortedAthletes) {
            if (newSet.has(athlete.name)) list.push(athlete.name);
        }
        const finalList = list.slice(0, targetSize);
        const out = prevList.filter(n => !finalList.includes(n));
        const up = finalList.filter(n => !prevList.includes(n));
        return { list: finalList, out, up };
    },

    renderRotationLog(out, up, members, labels) {
        const logDiv = document.getElementById('rotation-log');
        if (!logDiv) return;

        const text = Object.assign({
            title: 'Ротация проведена',
            out: 'Вылетели',
            up: 'Поднялись',
            members: 'Новый состав'
        }, labels || {});

        const esc = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        const checkIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        let html = `<div class="rotation-log-header">${checkIcon} ${text.title}</div>`;
        if (out.length > 0) {
            html += `<div class="rotation-log-section rotation-log-section--out"><strong>${text.out}</strong><span class="rotation-log-names">${esc(out.join(', '))}</span></div>`;
        }
        if (up.length > 0) {
            html += `<div class="rotation-log-section rotation-log-section--up"><strong>${text.up}</strong><span class="rotation-log-names">${esc(up.join(', '))}</span></div>`;
        }
        html += `<div class="rotation-log-section rotation-log-section--elite"><strong>${text.members}</strong><span class="rotation-log-names">${esc(members.join(', '))}</span></div>`;

        logDiv.innerHTML = html;
        logDiv.hidden = false;
    },

    setRotationBusy(busy) {
        const btn = document.getElementById('rotation-btn');
        if (!btn) return;
        btn.disabled = busy;
        btn.classList.toggle('is-loading', busy);
    },

    async verifyRotationPassword(password) {
        try {
            const resp = await this.fetchApi(LegionConfig.API.rotationPassword, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            if (!resp.ok) return false;
            const data = await resp.json();
            return !!data.valid;
        } catch (e) {
            console.warn('Ошибка проверки пароля:', e);
            return false;
        }
    },

    compareAndRecordHistory(lastResults, newData) {
        const now = new Date().toLocaleString('ru-RU');
        const entries = [];
        const exercises = LegionConfig.getExerciseKeys();
        newData.forEach(athlete => {
            const name = athlete.name;
            if (!lastResults[name]) return;
            exercises.forEach(ex => {
                const oldVal = lastResults[name][ex];
                const newVal = athlete[ex];
                if (oldVal !== newVal) {
                    entries.push({ date: now, name, exercise: ex, oldVal, newVal, diff: newVal - oldVal });
                }
            });
        });
        if (entries.length > 0) this.addHistoryEntries(entries);
    },

    // ---------- Прошлые результаты ----------

    async loadLastResultsFromServer(scope) {
        const { API } = LegionConfig;
        try {
            const resp = await this.fetchApi(`${API.lastResultsLoad}?scope=${scope}`);
            if (resp.ok) {
                const data = await resp.json();
                this.state.serverLastResults = data && typeof data === 'object' ? data : {};
            } else {
                this.state.serverLastResults = {};
            }
        } catch (e) {
            console.warn('Ошибка загрузки прошлых результатов:', e);
            this.state.serverLastResults = {};
        }
    },

    async saveLastResultsToServer(scope, data) {
        const { API } = LegionConfig;
        this.state.serverLastResults = data;
        try {
            await fetch(API.lastResultsSave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope, data })
            });
        } catch (e) {
            console.warn('Ошибка сохранения прошлых результатов:', e);
        }
    },

    getLastResults() {
        return this.state.serverLastResults;
    },

    setLastResults(scope, data) {
        this.saveLastResultsToServer(scope, data);
    },

    async migrateLastResultsFromLocalStorage(scope, localKey) {
        const localData = localStorage.getItem(localKey);
        if (!localData || Object.keys(this.state.serverLastResults).length > 0) return;
        try {
            const parsed = JSON.parse(localData);
            if (parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0) {
                await this.saveLastResultsToServer(scope, parsed);
                localStorage.removeItem(localKey);
            }
        } catch (e) {
            console.warn('Ошибка миграции прошлых результатов:', e);
        }
    },

    snapshotCurrentResults(athletes) {
        const currentResults = {};
        athletes.forEach(a => {
            const row = {};
            LegionConfig.getExerciseKeys().forEach(ex => {
                row[ex] = a[ex];
            });
            currentResults[a.name] = row;
        });
        return currentResults;
    },

    // ---------- Достижения ----------

    async loadAchievementsFromServer() {
        const { API, ACHIEVEMENTS_SCOPE } = LegionConfig;
        try {
            const resp = await this.fetchApi(`${API.achievementsLoad}?scope=${ACHIEVEMENTS_SCOPE}`);
            if (resp.ok) {
                const data = await resp.json();
                this.state.serverAchievements = data && typeof data === 'object' ? data : {};
            } else {
                this.state.serverAchievements = {};
            }
        } catch (e) {
            console.warn('Ошибка загрузки достижений:', e);
            this.state.serverAchievements = {};
        }
    },

    async saveAchievementsToServer(data) {
        const { API, ACHIEVEMENTS_SCOPE } = LegionConfig;
        this.state.serverAchievements = data;
        try {
            await fetch(API.achievementsSave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope: ACHIEVEMENTS_SCOPE, data })
            });
        } catch (e) {
            console.warn('Ошибка сохранения достижений:', e);
        }
    },

    getStoredAchievements() {
        return this.state.serverAchievements;
    },

    setStoredAchievements(data) {
        this.saveAchievementsToServer(data);
    },

    async migrateAchievementsFromLocalStorage() {
        const localData = localStorage.getItem('clubAchievements');
        if (!localData || Object.keys(this.state.serverAchievements).length > 0) return;
        try {
            const parsed = JSON.parse(localData);
            if (parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0) {
                await this.saveAchievementsToServer(parsed);
                localStorage.removeItem('clubAchievements');
            }
        } catch (e) {
            console.warn('Ошибка миграции достижений:', e);
        }
    },

    makeAchievementIcon(filename, emojiFallback) {
        const svgName = filename.replace(/\.png$/i, '.svg');
        return `<img src="/icons/${svgName}" alt="" onerror="this.outerHTML='${emojiFallback}'" class="ach-icon-img">`;
    },

    getAchievementDefinitions() {
        const mk = (f, e) => this.makeAchievementIcon(f, e);
        return {
            top1: { text: `${mk('top1.png', '🏆')} ТОП-1 Легиона Силы`, desc: 'Занять 1-е место в общем рейтинге клуба' },
            top3: { text: `${mk('top3.png', '🥇')} ТОП-3 Легиона Силы`, desc: 'Войти в тройку лидеров общего рейтинга' },
            top25: { text: `${mk('top25.png', '🌟')} ТОП-25 Легиона Силы`, desc: 'Попасть в число 25 лучших спортсменов' },
            ex_top10_push: { text: `${mk('top10-push.png', '💪')} ТОП-10 в Отжиманиях`, desc: 'Войти в десятку лучших по отжиманиям' },
            ex_top10_pull: { text: `${mk('top10-pull.png', '💪')} ТОП-10 в Подтягиваниях`, desc: 'Войти в десятку лучших по подтягиваниям' },
            ex_top10_hang: { text: `${mk('top10-hang.png', '💪')} ТОП-10 в Висе`, desc: 'Войти в десятку лучших по вису на турнике' },
            ex_top10_burpee: { text: `${mk('top10-burpee.png', '💪')} ТОП-10 в Бёрпи`, desc: 'Войти в десятку лучших по бёрпи' },
            ex_top10_crunch: { text: `${mk('top10-burpee.png', '💪')} ТОП-10 в Скручиваниях`, desc: 'Войти в десятку лучших по скручиваниям' },
            ex_top10_jump: { text: `${mk('top10-jump.png', '💪')} ТОП-10 в Прыжках`, desc: 'Войти в десятку лучших по прыжкам в длину' },
            ex_top1_push: { text: `${mk('top1-push.png', '🥇')} ТОП-1 в Отжиманиях`, desc: 'Стать абсолютным лидером по отжиманиям' },
            ex_top1_pull: { text: `${mk('top1-pull.png', '🥇')} ТОП-1 в Подтягиваниях`, desc: 'Стать абсолютным лидером по подтягиваниям' },
            ex_top1_hang: { text: `${mk('top1-hang.png', '🥇')} ТОП-1 в Висе`, desc: 'Стать абсолютным лидером по вису' },
            ex_top1_burpee: { text: `${mk('top1-burpee.png', '🥇')} ТОП-1 в Бёрпи`, desc: 'Стать абсолютным лидером по бёрпи' },
            ex_top1_crunch: { text: `${mk('top1-burpee.png', '🥇')} ТОП-1 в Скручиваниях`, desc: 'Стать абсолютным лидером по скручиваниям' },
            ex_top1_jump: { text: `${mk('top1-jump.png', '🥇')} ТОП-1 в Прыжках`, desc: 'Стать абсолютным лидером по прыжкам' }
        };
    },

    getCurrentAchievementIds(athlete, exerciseSortedArrays) {
        const ids = [];
        const pointsRank = athlete.pointsRank || athlete.overallRank;
        if (pointsRank === 1) ids.push('top1');
        if (pointsRank <= 3) ids.push('top3');
        if (pointsRank <= 25) ids.push('top25');

        const keys = LegionConfig.getExerciseKeys();
        keys.forEach((ex, i) => {
            const arr = exerciseSortedArrays[i];
            const idx = arr.findIndex(a => a.name === athlete.name);
            if (idx !== -1) {
                const place = idx + 1;
                if (place <= 10) ids.push(`ex_top10_${ex}`);
                if (place === 1) ids.push(`ex_top1_${ex}`);
            }
        });
        return ids;
    },

    getExerciseSortedArrays() {
        return LegionConfig.getExerciseKeys().map(key => this.state[key + 'Sorted'] || []);
    },

    updateAllAchievements() {
        const stored = this.getStoredAchievements();
        const today = new Date().toISOString().slice(0, 10);
        const exerciseArrays = this.getExerciseSortedArrays();
        const defs = this.getAchievementDefinitions();

        this.state.athletesData.forEach(athlete => {
            const name = athlete.name;
            if (!stored[name]) stored[name] = [];
            const currentAchIds = this.getCurrentAchievementIds(athlete, exerciseArrays);
            currentAchIds.forEach(id => {
                if (!stored[name].some(a => a.id === id)) {
                    stored[name].push({ id, date: today });
                }
            });
        });
        this.setStoredAchievements(stored);
        return defs;
    },

    getAchievementDisplayData(athleteName) {
        const stored = this.getStoredAchievements();
        const athleteAch = stored[athleteName] || [];
        const athlete = this.state.athletesData.find(a => a.name === athleteName);
        const currentIds = athlete
            ? this.getCurrentAchievementIds(athlete, this.getExerciseSortedArrays())
            : [];
        const defs = this.getAchievementDefinitions();
        return Object.keys(defs).map(id => {
            const def = defs[id];
            const storedAch = athleteAch.find(a => a.id === id);
            return {
                id,
                text: def.text,
                desc: def.desc,
                date: storedAch ? storedAch.date : null,
                active: currentIds.includes(id) && !!storedAch
            };
        });
    },

    // ---------- Отрисовка (общие хелперы) ----------

    escapeName(name) {
        return name.replace(/'/g, "\\'");
    },

    escapeHtmlAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;');
    },

    formatAthleteLink(name) {
        const attr = this.escapeHtmlAttr(name);
        return `<span class="athlete-name" role="button" tabindex="0" data-athlete-name="${attr}">${name}</span>`;
    },

    formatEliteIcon(title) {
        const icon = LegionConfig.ELITE_ICON || '🛡️';
        const attr = title ? ` title="${title}"` : '';
        return `<span class="elite-icon"${attr}>${icon}</span> `;
    },

    getCellClass(rank) {
        if (rank === 1) return 'rank-1-cell';
        if (rank === 2) return 'rank-2-cell';
        if (rank === 3) return 'rank-3-cell';
        return '';
    },

    formatRankDisplay(name) {
        const clubRank = this.getClubRank(name);
        if (!clubRank) return '—';
        const meta = LegionConfig.LEAGUE_META[clubRank.league] || {};
        const attr = this.escapeHtmlAttr(name);
        const label = clubRank.rankName
            ? `${clubRank.rankName} — ${meta.short}`
            : `${meta.short} · старт`;
        return `<span class="rank-clickable" role="button" tabindex="0" data-athlete-name="${attr}" style="color:${meta.color || 'inherit'}; font-weight:bold;">${label} (${clubRank.completed}/${clubRank.total})</span>`;
    },

    getLeagueExerciseList(league) {
        const cfg = LegionConfig;
        if (league === 3) return { offset: 0, exercises: cfg.league3Exercises, names: cfg.league3Names, descriptions: cfg.league3ExerciseDesc };
        if (league === 2) return { offset: 20, exercises: cfg.league2Exercises, names: cfg.league2Names, descriptions: cfg.league2ExerciseDesc };
        return { offset: 40, exercises: cfg.league1Exercises, names: cfg.league1Names, descriptions: cfg.league1ExerciseDesc };
    },

    getLeagueProgress(marks, league) {
        const { offset, exercises, descriptions } = this.getLeagueExerciseList(league);
        const items = exercises.map((name, idx) => ({
            name,
            description: descriptions && descriptions[idx] ? descriptions[idx] : '',
            done: !!(marks && marks[offset + idx])
        }));
        const completed = items.filter(i => i.done).length;
        return { items, completed, total: exercises.length, isComplete: completed >= exercises.length };
    },

    renderHistoryBlock(name) {
        const limit = LegionConfig.HISTORY_PER_ATHLETE || 50;
        const personHistory = this.getHistory().filter(e => e.name === name).slice(-limit).reverse();
        if (personHistory.length === 0) return '<p>Нет записей об изменениях.</p>';
        const exNames = {};
        LegionConfig.EXERCISES.forEach(ex => {
            exNames[ex.key] = ex.label;
        });
        let histHtml = `<h3>История изменений <span class="history-count">(${personHistory.length})</span></h3><div class="history-list">`;
        personHistory.forEach(entry => {
            const exName = exNames[entry.exercise] || entry.exercise;
            const arrow = entry.diff > 0 ? '⬆️' : (entry.diff < 0 ? '⬇️' : '➡️');
            const cls = entry.diff > 0 ? 'up' : (entry.diff < 0 ? 'down' : '');
            histHtml += `<div class="history-item">
                <span class="arrow ${cls}">${arrow}</span>
                <span>${exName}: ${entry.oldVal} → ${entry.newVal}</span>
                <span class="date">${entry.date}</span>
            </div>`;
        });
        histHtml += '</div>';
        return histHtml;
    },

    parseHistoryDate(dateStr) {
        if (!dateStr) return new Date(0);
        const m = String(dateStr).match(/(\d{1,2})\.(\d{1,2})\.(\d{4})/);
        if (!m) return new Date(0);
        return new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
    },

    computeExerciseRecords(athletes, history) {
        return LegionConfig.EXERCISES.map(ex => {
            let clubRecord = { value: 0, name: '', date: null };

            const exHistory = history
                .filter(entry => entry.exercise === ex.key)
                .sort((a, b) => this.parseHistoryDate(a.date) - this.parseHistoryDate(b.date));

            exHistory.forEach(entry => {
                [entry.newVal, entry.oldVal].forEach((raw) => {
                    const val = Number(raw);
                    if (isNaN(val) || val <= 0) return;
                    if (val > clubRecord.value) {
                        clubRecord = {
                            value: val,
                            name: entry.name,
                            date: entry.date
                        };
                    }
                });
            });

            athletes.forEach(a => {
                const val = Number(a[ex.key]);
                if (isNaN(val) || val <= 0) return;
                if (val > clubRecord.value) {
                    clubRecord = { value: val, name: a.name, date: null };
                }
            });

            let currentLeader = { value: 0, name: '' };
            athletes.forEach(a => {
                const val = Number(a[ex.key]);
                if (isNaN(val) || val <= 0) return;
                if (val > currentLeader.value) {
                    currentLeader = { value: val, name: a.name };
                }
            });

            const isActive = clubRecord.value > 0 && athletes.some(a =>
                a.name === clubRecord.name && Number(a[ex.key]) === clubRecord.value
            );

            const leaderDiffers = clubRecord.value > 0
                && currentLeader.value > 0
                && (clubRecord.name !== currentLeader.name || clubRecord.value !== currentLeader.value);

            return {
                key: ex.key,
                label: ex.tableTitle || ex.label,
                clubRecord,
                currentLeader: leaderDiffers ? currentLeader : null,
                isActive
            };
        });
    },

    getExerciseLabel(exKey) {
        const ex = LegionConfig.EXERCISES.find(e => e.key === exKey);
        return ex ? (ex.tableTitle || ex.label) : exKey;
    },

    computeRecentRecordBreaks(history, limit) {
        const maxEntries = limit || 25;
        const maxByEx = {};
        const breaks = [];

        const sorted = [...history].sort((a, b) => this.parseHistoryDate(a.date) - this.parseHistoryDate(b.date));

        sorted.forEach(entry => {
            const val = Number(entry.newVal);
            if (isNaN(val) || val <= 0) return;

            const prevMax = maxByEx[entry.exercise] || 0;
            if (val > prevMax) {
                breaks.push({
                    date: entry.date,
                    name: entry.name,
                    exercise: entry.exercise,
                    exerciseLabel: this.getExerciseLabel(entry.exercise),
                    oldVal: prevMax > 0 ? prevMax : Number(entry.oldVal) || 0,
                    newVal: val
                });
                maxByEx[entry.exercise] = val;
            }
        });

        return breaks.reverse().slice(0, maxEntries);
    },

    // ---------- Модалки ----------

    onBeforeDataRefresh() {
        this.state.loadWarnings = [];
        const warnEl = document.getElementById('legion-load-warnings');
        if (warnEl) warnEl.remove();
        const rankModal = document.getElementById('rankModal');
        if (rankModal) rankModal.classList.remove('active');
        const athleteModal = document.getElementById('athleteModal');
        if (!athleteModal || !athleteModal.classList.contains('active')) {
            this.state.openAthleteName = null;
        }
    },

    refreshOpenAthleteModal() {
        const name = this.state.openAthleteName;
        if (!name) return;
        const athlete = this.state.athletesData.find(a => a.name === name);
        if (!athlete) return;
        if (typeof window.openAthleteModal === 'function') {
            window.openAthleteModal(name);
        }
    },

    setOpenAthlete(name) {
        this.state.openAthleteName = name;
    },

    clearOpenAthlete() {
        this.state.openAthleteName = null;
    },

    closeModal(event) {
        const modal = document.getElementById('athleteModal');
        if (!modal) return;
        if (event && event.target !== modal) return;
        modal.classList.remove('active');
        this.clearOpenAthlete();
    },

    closeRankModal(event) {
        const modal = document.getElementById('rankModal');
        if (!modal) return;
        if (event && event.target !== modal) return;
        modal.classList.remove('active');
    },

    openRankModal(name) {
        const modal = document.getElementById('rankModal');
        const titleEl = document.getElementById('rank-modal-title');
        const contentEl = document.getElementById('rank-modal-content');
        if (!modal || !titleEl || !contentEl) {
            console.error('Окно рангов не найдено — залейте modals-coach.php или modals-club.php на сервер.');
            return;
        }
        const clubRank = this.getClubRank(name);
        if (!clubRank) return;
        if (typeof LegionUI === 'undefined' || typeof LegionUI.renderRankModal !== 'function') {
            console.error('Не загружен legion-ui.js');
            return;
        }
        const marks = this.lookupRankMarks(name);
        titleEl.textContent = name;
        contentEl.innerHTML = LegionUI.renderRankModal(name, clubRank, marks);
        modal.classList.add('active');
    },

    fillAthleteModalTable(athlete) {
        let tbody = '';
        LegionConfig.EXERCISES.forEach(ex => {
            const result = athlete[ex.key];
            const place = athlete[ex.key + '_rank'] || '–';
            const pts = athlete[ex.key + '_points'];
            tbody += `<tr><td>${ex.label}</td><td>${result}</td><td>${result > 0 ? place : '–'}</td><td>${pts}</td></tr>`;
        });
        document.getElementById('modal-body').innerHTML = tbody;
    },

    fillAthleteModalExtras(name, athlete, options) {
        const opts = options || {};
        const progressEl = document.getElementById('modal-progress');
        if (progressEl && opts.showProgress !== false) {
            progressEl.innerHTML = LegionUI.renderProgressCharts(name, this.getHistory(), athlete);
        } else if (progressEl) {
            progressEl.innerHTML = '';
        }
        const historyEl = document.getElementById('modal-history');
        if (historyEl) {
            historyEl.innerHTML = this.renderHistoryBlock(name);
        }
        const achEl = document.getElementById('modal-achievements');
        if (achEl && opts.showAchievements !== false) {
            achEl.innerHTML = LegionUI.renderAchievementGrid(this.getAchievementDisplayData(name));
        } else if (achEl) {
            achEl.innerHTML = '';
        }
    },

    // ---------- Поиск и навигация ----------

    filterBySearch(list) {
        const q = this.state.searchQuery;
        if (!q) return list;
        return list.filter(a => a.name.toLowerCase().includes(q));
    },

    initSearchBar(onChange) {
        const input = document.getElementById('athlete-search');
        const clearBtn = document.getElementById('athlete-search-clear');
        if (!input) return;

        const syncClear = () => {
            if (clearBtn) clearBtn.hidden = !input.value;
        };

        input.addEventListener('input', () => {
            this.state.searchQuery = input.value.trim().toLowerCase();
            syncClear();
            if (typeof onChange === 'function') onChange();
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                input.value = '';
                this.state.searchQuery = '';
                clearBtn.hidden = true;
                input.focus();
                if (typeof onChange === 'function') onChange();
            });
        }

        syncClear();
    },

    updateSearchStatus(matchCount, options) {
        const status = document.getElementById('athlete-search-status');
        if (!status) return;
        if (options && options.hidden) {
            status.textContent = '';
            status.className = 'search-status';
            return;
        }
        const q = this.state.searchQuery;
        if (!q) {
            status.textContent = '';
            status.className = 'search-status';
            return;
        }
        if (matchCount > 0) {
            const word = matchCount === 1 ? 'спортсмен' : (matchCount < 5 ? 'спортсмена' : 'спортсменов');
            status.innerHTML = `Найдено: <strong>${matchCount}</strong> ${word}`;
            status.className = 'search-status has-results';
        } else {
            status.textContent = 'Никого не найдено — попробуйте другое имя';
            status.className = 'search-status no-results';
        }
    },

    initDropdownNav() {
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const btn = dropdown.querySelector('.dropbtn');
            const menu = dropdown.querySelector('.dropdown-content');
            if (!btn || !menu) return;
            btn.setAttribute('type', 'button');
            btn.setAttribute('aria-haspopup', 'true');
            btn.setAttribute('aria-expanded', 'false');
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isOpen = dropdown.classList.contains('open');
                document.querySelectorAll('.dropdown.open').forEach(d => {
                    d.classList.remove('open');
                    const b = d.querySelector('.dropbtn');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                    dropdown.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
            menu.addEventListener('click', (e) => e.stopPropagation());
        });
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown.open').forEach(d => {
                d.classList.remove('open');
                const b = d.querySelector('.dropbtn');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        });
    },

    initModalClicks() {
        if (this._modalClicksBound) return;
        this._modalClicksBound = true;

        const handleActivate = (e) => {
            const rankEl = e.target.closest('.rank-clickable, .rank-summary-card');
            if (rankEl) {
                const rankName = rankEl.getAttribute('data-athlete-name');
                if (rankName) {
                    e.preventDefault();
                    this.openRankModal(rankName);
                }
                return;
            }
            const nameEl = e.target.closest('.athlete-name[data-athlete-name]');
            if (nameEl) {
                const athleteName = nameEl.getAttribute('data-athlete-name');
                if (athleteName && typeof window.openAthleteModal === 'function') {
                    e.preventDefault();
                    window.openAthleteModal(athleteName);
                }
            }
        };

        document.addEventListener('click', handleActivate, true);
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const interactive = e.target.closest('.rank-clickable, .rank-summary-card, .athlete-name[data-athlete-name]');
            if (interactive) handleActivate(e);
        }, true);
    },

    initPageUI(onSearchChange) {
        this.initDropdownNav();
        this.initSearchBar(onSearchChange);
        this.initModalClicks();
    },

    bindCommonGlobals() {
        const self = this;
        window.closeModal = (e) => self.closeModal(e);
        window.closeRankModal = (e) => self.closeRankModal(e);
        window.openRankModal = (name) => self.openRankModal(name);
    }
};
