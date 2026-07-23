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
        serverRankHistory: [],
        snapshotMeta: {},
        serverAchievements: {},
        serverLastResults: {},
        coachBenchmarks: {},
        searchQuery: '',
        openAthleteName: null,
        loadWarnings: [],
        pageDataStorage: 'sheets',
        clubOverallRankMap: null
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
        let isFirstDataRow = true;

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
            if (isFirstDataRow) {
                row.isCoach = true;
                isFirstDataRow = false;
            }
            data.push(row);
        }
        return data;
    },

    applyCoachBenchmarksFromPayload(payload) {
        const map = payload && payload.coachBenchmarks && typeof payload.coachBenchmarks === 'object'
            ? payload.coachBenchmarks
            : {};
        this.state.coachBenchmarks = map;
    },

    getCoachBenchmark(slug) {
        const benchmarks = this.state.coachBenchmarks || {};
        return slug && benchmarks[slug] ? benchmarks[slug] : null;
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
            .filter(a => a.coachSlug === coach.slug && !a.isCoach)
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
            const norm = this.normalizePersonName(name);
            merged[coach.slug + ':' + norm] = entry.marks;
            if (!merged[norm] || entry.marks.some((m) => m)) {
                merged[norm] = entry.marks;
            }
        });
        return merged;
    },

    lookupRankMarks(name, coachSlug) {
        const data = this.state.rankData || {};
        const normalized = this.normalizePersonName(name);
        if (coachSlug) {
            const scopedKey = coachSlug + ':' + normalized;
            if (data[scopedKey]) return data[scopedKey];
        }
        if (data[normalized]) return data[normalized];
        if (data[name]) return data[name];

        const keys = Object.keys(data);
        for (let i = 0; i < keys.length; i++) {
            const key = keys[i];
            if (key.indexOf(':') !== -1) continue;
            if (key.startsWith(normalized) || normalized.startsWith(key)) {
                const minLen = Math.min(key.length, normalized.length);
                if (minLen >= 6) return data[key];
            }
        }
        return null;
    },

    hasRankMarksIn(rankData, name, coachSlug) {
        const data = rankData || {};
        const normalized = this.normalizePersonName(name);
        if (coachSlug) {
            if (data[coachSlug + ':' + normalized]) return true;
        }
        if (data[normalized] || data[name]) return true;
        return false;
    },

    getCoachesMissingRankCoverage(athletesData, rankData) {
        const byCoach = {};
        (athletesData || []).forEach((a) => {
            const slug = a.coachSlug || '';
            if (!slug) return;
            if (!byCoach[slug]) byCoach[slug] = { total: 0, missing: 0 };
            byCoach[slug].total++;
            if (!this.hasRankMarksIn(rankData, a.name, slug)) {
                byCoach[slug].missing++;
            }
        });
        return Object.keys(byCoach).filter((slug) => {
            const c = byCoach[slug];
            return c.total > 0 && c.missing > 0;
        });
    },

    async loadCoachRanksInto(rankData, athletesData, coachSlug) {
        const coach = LegionConfig.getCoaches().find((c) => c.slug === coachSlug);
        if (!coach || !coach.ranksCsvUrl) return rankData || {};

        const merged = { ...(rankData || {}) };
        try {
            const resp = await this.fetchWithTimeout(coach.ranksCsvUrl);
            if (!resp.ok) return merged;
            const entries = this.parseRankCSV(await resp.text());
            const coachMerged = this.mergeCoachRankEntries(entries, athletesData, coach);
            Object.keys(coachMerged).forEach((name) => {
                const norm = this.normalizePersonName(name);
                merged[coachSlug + ':' + norm] = coachMerged[name];
                if (!merged[norm] || this.normalizeRankMarksValue(coachMerged[name]).filter(Boolean).length > 0) {
                    merged[norm] = coachMerged[name];
                }
            });
        } catch (e) {
            console.warn('Ранги тренера', coachSlug, e);
        }
        return merged;
    },

    async ensureRankCoverage(athletesData, rankData) {
        let data = rankData || {};
        const missing = this.getCoachesMissingRankCoverage(athletesData, data);
        for (const slug of missing) {
            data = await this.loadCoachRanksInto(data, athletesData, slug);
        }
        return data;
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

    athleteRankKey(athleteOrName, coachSlug) {
        let name = '';
        let slug = '';
        if (athleteOrName && typeof athleteOrName === 'object') {
            name = athleteOrName.name;
            slug = athleteOrName.coachSlug || '';
        } else {
            name = athleteOrName;
            slug = coachSlug || '';
        }
        return `${slug}:${this.normalizePersonName(name)}`;
    },

    buildClubOverallRankMap(athletes) {
        const map = new Map();
        this.sortByTotal(athletes.filter((a) => !a.isCoach)).forEach((a, idx) => {
            map.set(this.athleteRankKey(a), idx + 1);
        });
        return map;
    },

    applyClubOverallRanks(athletes, rankMap) {
        if (!Array.isArray(athletes) || !rankMap) return;
        athletes.forEach((a) => {
            if (a.isCoach) return;
            const rank = rankMap.get(this.athleteRankKey(a));
            if (rank) {
                a.overallRank = rank;
                a.pointsRank = rank;
            }
        });
    },

    getClubOverallRank(athlete) {
        if (!athlete) return '?';
        const key = this.athleteRankKey(athlete);
        if (this.state.clubOverallRankMap && this.state.clubOverallRankMap.has(key)) {
            return this.state.clubOverallRankMap.get(key);
        }
        return athlete.overallRank || '?';
    },

    async refreshClubOverallRanks() {
        const saved = {
            athletesData: this.state.athletesData,
            rankData: this.state.rankData,
            serverHistory: this.state.serverHistory,
            serverAchievements: this.state.serverAchievements,
            pageDataStorage: this.state.pageDataStorage,
            loadWarnings: this.state.loadWarnings,
            coachBenchmarks: this.state.coachBenchmarks
        };
        try {
            const { athletes, rankData } = await this.loadPageData();
            this.applyRankData(rankData, athletes);
            this.calculateAllRatings(athletes);
            const map = this.buildClubOverallRankMap(athletes);
            this.state.clubOverallRankMap = map;
            this.applyClubOverallRanks(saved.athletesData, map);
            return map;
        } finally {
            this.state.athletesData = saved.athletesData;
            this.state.rankData = saved.rankData;
            this.state.serverHistory = saved.serverHistory;
            this.state.serverAchievements = saved.serverAchievements;
            this.state.pageDataStorage = saved.pageDataStorage;
            this.state.loadWarnings = saved.loadWarnings;
            this.state.coachBenchmarks = saved.coachBenchmarks;
        }
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

    async loadPageData(options = {}) {
        const coaches = LegionConfig.getCoaches();
        if (!coaches.length) {
            throw new Error('Список тренеров пуст. Обновите страницу или проверьте api/coaches.php на сервере.');
        }

        const coachSlug = options.coachSlug || null;
        const { API } = LegionConfig;
        this.state.loadWarnings = [];

        const url = coachSlug
            ? `${API.pageDataLoad}?coach=${encodeURIComponent(coachSlug)}`
            : API.pageDataLoad;

        try {
            const resp = await this.fetchWithTimeout(url, {}, 45000);
            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }
            const payload = await resp.json();
            return await this.buildPageDataFromPayload(payload, coachSlug);
        } catch (e) {
            console.warn('get_page_data недоступен:', e);
            throw new Error(e.message || 'Не удалось загрузить рейтинг с сервера. Проверьте MySQL и /diagnostics/');
        }
    },

    async buildPageDataFromPayload(payload, coachSlug = null) {
        this.applyCoachBenchmarksFromPayload(payload);

        if (Array.isArray(payload.warnings)) {
            payload.warnings.forEach((w) => {
                this.state.loadWarnings.push({
                    coach: w.coach || '',
                    slug: w.slug || '',
                    message: w.message || 'Ошибка загрузки'
                });
            });
        }

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

        const athletes = Array.isArray(payload.athletes) ? payload.athletes : [];
        let rankData = this.normalizeRankDataFromServer(payload.ranks || {});

        // Сервер уже загрузил ранги — не тянем таблицы из браузера (это +30–60 с).
        if (athletes.length > 0 && Object.keys(rankData).length === 0 && !payload.ranksFromServer) {
            rankData = await this.ensureRankCoverage(athletes, rankData);
        }

        if (athletes.length === 0) {
            const details = this.state.loadWarnings
                .map(w => `${w.coach}: ${w.message}`)
                .join('; ');
            throw new Error(details
                ? `Нет данных в базе. ${details}`
                : 'Нет спортсменов в базе — тренеры должны импортировать группы в режиме тренировки');
        }

        if (Array.isArray(payload.history)) {
            this.state.serverHistory = this.trimHistoryPerAthlete(payload.history);
        }
        if (Array.isArray(payload.rankHistory)) {
            this.state.serverRankHistory = this.trimHistoryPerAthlete(payload.rankHistory);
        }
        if (payload.achievements && typeof payload.achievements === 'object') {
            this.state.serverAchievements = payload.achievements;
        }
        this.state.pageDataStorage = payload.storage === 'mysql' ? 'mysql' : 'sheets';

        if (Object.keys(rankData).length === 0) {
            this.state.loadWarnings.push({
                coach: 'Ранги',
                slug: '',
                message: this.state.pageDataStorage === 'mysql'
                    ? 'Ранги: не удалось загрузить — проверьте данные в режиме тренировки'
                    : 'Ранги: не удалось загрузить — проверьте таблицы рангов и /diagnostics/'
            });
        }

        return { athletes, rankData };
    },

    async loadAllAthletes(options = {}) {
        const coaches = LegionConfig.getCoaches();
        if (!coaches.length) {
            throw new Error('Список тренеров пуст. Обновите страницу или проверьте api/coaches.php на сервере.');
        }

        const coachSlug = options.coachSlug || null;
        const { API } = LegionConfig;
        this.state.loadWarnings = [];

        try {
            const url = coachSlug
                ? `${API.athletesLoad}?coach=${encodeURIComponent(coachSlug)}`
                : API.athletesLoad;
            const resp = await this.fetchApi(url);
            if (resp.ok) {
                const payload = await resp.json();
                this.applyCoachBenchmarksFromPayload(payload);
                const athletes = Array.isArray(payload.athletes) ? payload.athletes : [];
                if (Array.isArray(payload.warnings)) {
                    payload.warnings.forEach((w) => {
                        this.state.loadWarnings.push({
                            coach: w.coach || '',
                            slug: w.slug || '',
                            message: w.message || 'Ошибка загрузки'
                        });
                    });
                }
                if (athletes.length > 0) {
                    return athletes;
                }
            }
        } catch (e) {
            console.warn('Загрузка через API не удалась, пробуем Google Таблицы:', e);
        }

        this.state.loadWarnings = [];

        const coachList = coachSlug
            ? coaches.filter((c) => c.slug === coachSlug)
            : coaches;
        if (!coachList.length) {
            throw new Error('Тренер не найден в конфигурации.');
        }

        const settled = await Promise.allSettled(coachList.map(async (coach) => {
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
        const coachBenchmarks = { ...(this.state.coachBenchmarks || {}) };
        settled.forEach((result, index) => {
            const coach = coachList[index];
            if (result.status === 'fulfilled') {
                result.value.forEach((row) => {
                    if (row.isCoach) {
                        coachBenchmarks[coach.slug] = row;
                    } else {
                        athletesData.push(row);
                    }
                });
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

        this.state.coachBenchmarks = coachBenchmarks;
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
            a.rankMarks = this.lookupRankMarks(a.name, a.coachSlug);
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
        let rankData = {};
        const { API } = LegionConfig;

        try {
            const resp = await this.fetchWithTimeout(API.ranksLoad, {}, 20000);
            if (resp.ok) {
                const payload = await resp.json();
                const ranks = this.normalizeRankDataFromServer(payload.ranks || {});
                if (Object.keys(ranks).length > 0) {
                    rankData = ranks;
                }
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
            }
        } catch (e) {
            console.warn('Загрузка рангов через API не удалась:', e);
        }

        if (athletesData.length > 0) {
            if (Object.keys(rankData).length === 0) {
                rankData = await this.ensureRankCoverage(athletesData, rankData);
            }
        } else if (Object.keys(rankData).length === 0) {
            const coaches = LegionConfig.getCoaches().filter(c => c.ranksCsvUrl);
            if (coaches.length) {
                rankData = await this.loadRanksFromGoogle(athletesData);
            }
        }

        if (Object.keys(rankData).length === 0) {
            this.state.loadWarnings.push({
                coach: 'Ранги',
                slug: '',
                message: 'Ранги: не удалось загрузить — проверьте таблицы рангов и /diagnostics/'
            });
        }
        return rankData;
    },

    getClubRank(name, coachSlug) {
        let marks = this.lookupRankMarks(name, coachSlug);
        if (!marks) {
            const athlete = (this.state.athletesData || []).find((a) => a.name === name);
            if (athlete && Array.isArray(athlete.rankMarks)) {
                marks = this.normalizeRankMarksValue(athlete.rankMarks);
            }
        }
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

    async loadSnapshotMetaFromServer() {
        const { API } = LegionConfig;
        try {
            const resp = await this.fetchApi(API.snapshotMetaLoad);
            if (!resp.ok) {
                this.state.snapshotMeta = {};
                return;
            }
            const data = await resp.json();
            this.state.snapshotMeta = data && typeof data === 'object' ? data : {};
        } catch (e) {
            console.warn('Ошибка загрузки метаданных снимка:', e);
            this.state.snapshotMeta = {};
        }
    },

    async loadRankHistoryFromServer() {
        const { API } = LegionConfig;
        try {
            const resp = await this.fetchApi(API.rankHistoryLoad);
            if (!resp.ok) {
                this.state.serverRankHistory = [];
                return;
            }
            const raw = await resp.json();
            this.state.serverRankHistory = this.trimHistoryPerAthlete(Array.isArray(raw) ? raw : []);
        } catch (e) {
            console.warn('Ошибка загрузки истории рангов:', e);
            this.state.serverRankHistory = [];
        }
    },

    getRankHistory() {
        return this.state.serverRankHistory || [];
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
        if (!entries.length) return;
        const { API } = LegionConfig;
        const prevHistory = this.state.serverHistory;
        this.state.serverHistory = this.trimHistoryPerAthlete(
            this.state.serverHistory.concat(entries)
        );
        try {
            const resp = await this.fetchApi(API.historySave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entries })
            });
            if (!resp.ok) {
                this.state.serverHistory = prevHistory;
                console.warn('Ошибка сохранения истории: HTTP', resp.status);
            }
        } catch (e) {
            this.state.serverHistory = prevHistory;
            console.warn('Ошибка сохранения истории:', e);
        }
    },

    getHistory() {
        return this.state.serverHistory;
    },

    async addHistoryEntries(entries) {
        if (entries.length > 0) await this.saveHistoryToServer(entries);
    },

    normalizeLastResultsMap(lastResults) {
        const out = {};
        Object.keys(lastResults || {}).forEach((key) => {
            out[this.normalizePersonName(key)] = lastResults[key];
        });
        return out;
    },

    buildHistoryEntries(lastResults, newData) {
        const now = new Date().toLocaleString('ru-RU');
        const entries = [];
        const exercises = LegionConfig.getExerciseKeys();
        const normalizedLast = this.normalizeLastResultsMap(lastResults);

        newData.forEach((athlete) => {
            const name = this.normalizePersonName(athlete.name);
            if (!normalizedLast[name]) return;
            exercises.forEach((ex) => {
                const oldVal = normalizedLast[name][ex];
                const newVal = athlete[ex];
                const oldNum = Number(oldVal);
                const newNum = Number(newVal);
                if (oldNum === newNum) return;
                if (isNaN(oldNum) && isNaN(newNum)) return;
                const diff = (isNaN(newNum) ? 0 : newNum) - (isNaN(oldNum) ? 0 : oldNum);
                if (diff <= 0) return;
                entries.push({
                    date: now,
                    name,
                    exercise: ex,
                    oldVal: isNaN(oldNum) ? oldVal : oldNum,
                    newVal: isNaN(newNum) ? newVal : newNum,
                    diff
                });
            });
        });
        return entries;
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

    async compareAndRecordHistory(lastResults, newData) {
        const entries = this.buildHistoryEntries(lastResults, newData);
        if (entries.length > 0) await this.addHistoryEntries(entries);
        return entries.length;
    },

    localResultsBaselineKey(scope) {
        return `legionResultsBaseline:${scope || 'global'}`;
    },

    getLocalResultsBaseline(scope) {
        try {
            const raw = localStorage.getItem(this.localResultsBaselineKey(scope));
            if (!raw) return {};
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    },

    saveLocalResultsBaseline(scope, data) {
        try {
            localStorage.setItem(this.localResultsBaselineKey(scope), JSON.stringify(data));
        } catch (e) {
            console.warn('Не удалось сохранить локальный снимок результатов:', e);
        }
    },

    mergeLastResultsMaps(...maps) {
        const out = {};
        maps.forEach((map) => {
            Object.keys(map || {}).forEach((key) => {
                const name = this.normalizePersonName(key);
                const marks = map[key];
                if (!marks || typeof marks !== 'object') return;
                out[name] = { ...(out[name] || {}), ...marks };
            });
        });
        return out;
    },

    async loadLastResultsBaseline(scope) {
        await this.loadLastResultsFromServer(scope, true);
        const server = this.normalizeLastResultsMap(this.state.serverLastResults);
        const local = this.normalizeLastResultsMap(this.getLocalResultsBaseline(scope));
        return this.mergeLastResultsMaps(local, server);
    },

    async migrateAllLastResultsBaselines(scope) {
        if (Object.keys(this.state.serverLastResults).length > 0) return;
        const keys = ['clubLastResults'];
        LegionConfig.getCoaches().forEach((coach) => {
            keys.push(`${coach.slug}LastResults`);
        });
        for (const key of keys) {
            await this.migrateLastResultsFromLocalStorage(scope, key);
            if (Object.keys(this.state.serverLastResults).length > 0) break;
        }
    },

    snapshotsEqual(a, b) {
        return JSON.stringify(a || {}) === JSON.stringify(b || {});
    },

    /**
     * @deprecated История на MySQL пишется при сохранении в режиме тренировки.
     */
    async processResultHistoryChanges(athletesData, scope) {
        const compareScope = scope || LegionConfig.CLUB_LAST_RESULTS_SCOPE || 'global';
        await this.loadLastResultsFromServer(compareScope, false);
        await this.migrateAllLastResultsBaselines(compareScope);
        const baseline = await this.loadLastResultsBaseline(compareScope);
        let recorded = 0;
        const snapshot = athletesData.length > 0 ? this.snapshotCurrentResults(athletesData) : {};
        if (athletesData.length > 0 && Object.keys(baseline).length > 0) {
            recorded = await this.compareAndRecordHistory(baseline, athletesData);
        }
        if (athletesData.length > 0 && !this.snapshotsEqual(snapshot, baseline)) {
            await this.setLastResults(compareScope, snapshot);
            this.saveLocalResultsBaseline(compareScope, snapshot);
        }
        if (recorded > 0) {
            await this.loadHistoryFromServer();
        }
        return recorded;
    },

    // ---------- Прошлые результаты ----------

    async loadLastResultsFromServer(scope, merge = false) {
        const { API } = LegionConfig;
        const mergeParam = merge ? '&merge=1' : '';
        try {
            const resp = await this.fetchApi(`${API.lastResultsLoad}?scope=${encodeURIComponent(scope)}${mergeParam}`);
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
            const resp = await this.fetchApi(API.lastResultsSave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scope, data })
            });
            if (!resp.ok) {
                console.warn('Ошибка сохранения прошлых результатов: HTTP', resp.status);
            }
        } catch (e) {
            console.warn('Ошибка сохранения прошлых результатов:', e);
        }
    },

    getLastResults() {
        return this.state.serverLastResults;
    },

    setLastResults(scope, data) {
        return this.saveLastResultsToServer(scope, data);
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
        athletes.forEach((a) => {
            const row = {};
            const name = this.normalizePersonName(a.name);
            if (!name) return;
            LegionConfig.getExerciseKeys().forEach((ex) => {
                row[ex] = a[ex];
            });
            currentResults[name] = row;
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
        if (!filename) {
            return `<span class="ach-emoji">${emojiFallback}</span>`;
        }
        const svgName = String(filename).replace(/\.png$/i, '.svg');
        const safeEmoji = String(emojiFallback || '🏅');
        return `<span class="ach-icon-wrap"><img src="/icons/${svgName}" alt="" class="ach-icon-img" loading="lazy" onerror="this.parentElement.outerHTML='<span class=\\'ach-emoji\\'>${safeEmoji}</span>'"></span>`;
    },

    makeAchievementEmoji(emoji) {
        return `<span class="ach-emoji">${emoji}</span>`;
    },

    stripAchievementTitle(text) {
        return String(text || '')
            .replace(/<[^>]+>/g, '')
            .replace(/^[\s🏆🥇🌟💪🥉🥈⚔️👑📈]+/u, '')
            .trim();
    },

    getAchievementDefinitions() {
        const def = (title, icon, emoji, desc, category) => ({
            title,
            icon,
            emoji,
            desc,
            category: category || 'rating',
            text: `${this.makeAchievementIcon(icon, emoji)} ${title}`
        });
        return {
            top1: def('Император Легиона', 'top1.svg', '🏆', 'Первый в общем рейтинге клуба — вершина силы', 'rating'),
            top3: def('Триумвират', 'top3.svg', '🥇', 'Войти в тройку сильнейших воинов Легиона', 'rating'),
            top25: def('Щит Легиона', 'top25.svg', '🌟', 'Быть среди 25 лучших — элита клуба', 'rating'),

            ex_top10_push: def('Специалист · Отжимания', 'top10-push.svg', '💪', 'Один из десяти сильнейших в клубе по отжиманиям', 'rating'),
            ex_top10_pull: def('Специалист · Подтягивания', 'top10-pull.svg', '💪', 'Один из десяти сильнейших в клубе по подтягиваниям', 'rating'),
            ex_top10_hang: def('Специалист · Вис', 'top10-hang.svg', '💪', 'Один из десяти сильнейших в клубе по вису', 'rating'),
            ex_top10_burpee: def('Специалист · Бёрпи', 'top10-burpee.svg', '💪', 'Один из десяти сильнейших в клубе по бёрпи', 'rating'),
            ex_top10_crunch: def('Специалист · Скручивания', 'top10-crunch.svg', '💪', 'Один из десяти сильнейших в клубе по скручиваниям', 'rating'),
            ex_top10_jump: def('Специалист · Прыжок', 'top10-jump.svg', '💪', 'Один из десяти сильнейших в клубе по прыжку', 'rating'),

            ex_top1_push: def('Железные ладони', 'top1-push.svg', '🥇', 'Абсолютный лидер клуба по отжиманиям', 'rating'),
            ex_top1_pull: def('Хозяин перекладины', 'top1-pull.svg', '🥇', 'Абсолютный лидер клуба по подтягиваниям', 'rating'),
            ex_top1_hang: def('Несгибаемый', 'top1-hang.svg', '🥇', 'Абсолютный лидер клуба по вису на турнике', 'rating'),
            ex_top1_burpee: def('Буря на помосте', 'top1-burpee.svg', '🥇', 'Абсолютный лидер клуба по бёрпи', 'rating'),
            ex_top1_crunch: def('Стальной корпус', 'top1-crunch.svg', '🥇', 'Абсолютный лидер клуба по скручиваниям', 'rating'),
            ex_top1_jump: def('Прыжок титана', 'top1-jump.svg', '🥇', 'Абсолютный лидер клуба по прыжку в длину', 'rating'),

            rank_bronze_done: def('Бронзовый легионер', 'rank-bronze.svg', '🥉', 'Закрыть все 20 нормативов бронзовой лиги', 'ranks'),
            rank_silver_done: def('Серебряный центурион', 'rank-silver.svg', '🥈', 'Закрыть все 20 нормативов серебряной лиги', 'ranks'),
            rank_gold_done: def('Золотой император', 'rank-gold.svg', '🥇', 'Закрыть все 20 нормативов золотой лиги', 'ranks'),

            record_club: def('Имя в зале славы', 'record-club.svg', '🏆', 'Установить рекорд клуба — твоё имя в истории', 'records'),

            beat_coach_1: def('Догнал тренера', 'beat-coach.svg', '⚔️', 'Превзойти тренера хотя бы в одном упражнении', 'challenges'),
            beat_coach_3: def('Смена поколений', 'beat-coach-3.svg', '👑', 'Превзойти тренера в трёх и более упражнениях', 'challenges'),

            first_gain: def('Первый удар', null, '📈', 'Улучшить любой результат на тренировке', 'progress'),
            surge_5: def('Рывок недели', null, '🔥', 'Сделать 5 и более улучшений за последние 30 дней', 'progress'),
            warrior_six: def('Воин шести дорог', null, '🛡️', 'Иметь результат во всех шести упражнениях рейтинга', 'progress')
        };
    },

    getAchievementCategories() {
        return [
            { id: 'rating', title: 'Рейтинг' },
            { id: 'ranks', title: 'Ранги' },
            { id: 'records', title: 'Рекорды' },
            { id: 'challenges', title: 'Вызовы' },
            { id: 'progress', title: 'Путь воина' }
        ];
    },

    athletePersonKey(nameOrAthlete, coachSlug) {
        let name = nameOrAthlete;
        let slug = coachSlug || '';
        if (nameOrAthlete && typeof nameOrAthlete === 'object') {
            name = nameOrAthlete.name || '';
            slug = nameOrAthlete.coachSlug || slug || '';
        }
        const norm = this.normalizePersonName(name);
        if (!norm) return '';
        return slug ? `${slug}:${norm}` : norm;
    },

    lookupAchievementEntries(athleteName, coachSlug, storedMap) {
        const stored = storedMap || this.getStoredAchievements() || {};
        const scoped = this.athletePersonKey(athleteName, coachSlug);
        if (scoped && Array.isArray(stored[scoped])) return stored[scoped];
        const norm = this.normalizePersonName(athleteName);
        if (norm && Array.isArray(stored[norm])) return stored[norm];
        if (Array.isArray(stored[athleteName])) return stored[athleteName];
        return [];
    },

    /**
     * @returns {boolean} true, если достижение выдано впервые
     */
    grantAchievement(stored, athleteName, id, date, coachSlug, detail) {
        const key = this.athletePersonKey(athleteName, coachSlug)
            || this.normalizePersonName(athleteName)
            || athleteName;
        if (!key) return false;
        if (!stored[key]) stored[key] = [];
        const existing = stored[key].find((a) => a.id === id);
        if (existing) {
            if (detail && !existing.detail) existing.detail = detail;
            return false;
        }
        const entry = { id, date: date || new Date().toISOString().slice(0, 10) };
        if (detail) entry.detail = detail;
        stored[key].push(entry);
        return true;
    },

    formatExerciseResultLabel(exKey, value) {
        const ex = (LegionConfig.EXERCISES || []).find((e) => e.key === exKey);
        const label = ex ? ex.tabShort || ex.label : exKey;
        const n = Number(value);
        if (!(n > 0)) return '';
        if (exKey === 'jump') return `${n} см · ${label}`;
        if (exKey === 'hang') return `${n} сек · ${label}`;
        return `${n} · ${label}`;
    },

    achievementDetailFor(athlete, id) {
        if (!athlete || !id) return '';
        if (id.startsWith('ex_top1_') || id.startsWith('ex_top10_')) {
            const ex = id.replace(/^ex_top(?:1|10)_/, '');
            return this.formatExerciseResultLabel(ex, athlete[ex]);
        }
        if (id === 'top1' || id === 'top3' || id === 'top25') {
            const place = athlete.overallRank || athlete.pointsRank || '?';
            const pts = Number(athlete.total) || 0;
            return pts > 0 ? `${pts} очков · ${place} место` : `${place} место`;
        }
        if (id === 'beat_coach_1' || id === 'beat_coach_3') {
            const n = this.countCoachBeats(athlete);
            return n > 0 ? `обошёл тренера в ${n} упр.` : '';
        }
        if (id === 'warrior_six') {
            return 'все 6 упражнений';
        }
        if (id === 'surge_5') {
            const n = this.countRecentGains(athlete, 30);
            return n > 0 ? `${n} улучшений за 30 дней` : '';
        }
        if (id === 'first_gain') {
            return 'первый рост на тренировке';
        }
        if (id === 'record_club') {
            return 'рекорд клуба';
        }
        return '';
    },

    countRecentGains(athlete, days) {
        const windowDays = days || 30;
        const cutoff = Date.now() - windowDays * 86400000;
        return this.getHistoryForAthlete(athlete).filter((e) => {
            if (!(Number(e.diff) > 0)) return false;
            const ts = this.parseHistoryDateTime(e.date) || this.parseHistoryDate(e.date);
            return ts >= cutoff;
        }).length;
    },

    athleteHasFirstGain(athlete) {
        return this.getHistoryForAthlete(athlete).some((e) => Number(e.diff) > 0);
    },

    athleteHasAllExercises(athlete) {
        return LegionConfig.getExerciseKeys().every((ex) => Number(athlete[ex]) > 0);
    },

    historyEntryMatchesAthlete(entry, athleteOrName, coachSlug) {
        if (!entry) return false;
        let name = athleteOrName;
        let athleteId = 0;
        let slug = coachSlug || '';
        if (athleteOrName && typeof athleteOrName === 'object') {
            name = athleteOrName.name || '';
            athleteId = Number(athleteOrName.id || athleteOrName.athleteId || 0) || 0;
            slug = athleteOrName.coachSlug || slug || '';
        }
        if (athleteId > 0 && entry.athleteId && Number(entry.athleteId) === athleteId) {
            return true;
        }
        if (slug && entry.coachSlug && entry.coachSlug !== slug) {
            return false;
        }
        const norm = this.normalizePersonName(name);
        if (!norm) return false;
        return this.normalizePersonName(entry.name) === norm;
    },

    getHistoryForAthlete(athleteOrName, coachSlug) {
        return this.getHistory().filter((e) => this.historyEntryMatchesAthlete(e, athleteOrName, coachSlug));
    },

    rankEventToIso(dateStr) {
        const ru = String(dateStr || '').match(/(\d{1,2})\.(\d{1,2})\.(\d{4})/);
        if (ru) {
            return `${ru[3]}-${ru[2].padStart(2, '0')}-${ru[1].padStart(2, '0')}`;
        }
        if (/^\d{4}-\d{2}-\d{2}/.test(String(dateStr || ''))) {
            return String(dateStr).slice(0, 10);
        }
        return new Date().toISOString().slice(0, 10);
    },

    getFirstRankEventDate(name, event, coachSlug) {
        const matches = this.getRankHistory()
            .filter((e) => e.event === event && this.historyEntryMatchesAthlete(e, { name, coachSlug: coachSlug || '' }));
        if (!matches.length) return null;
        matches.sort((a, b) => this.parseHistoryDate(a.date) - this.parseHistoryDate(b.date));
        return this.rankEventToIso(matches[0].date);
    },

    countCoachBeats(athlete) {
        const bench = (this.state.coachBenchmarks || {})[athlete.coachSlug];
        if (!bench || athlete.isCoach) return 0;
        return LegionConfig.getExerciseKeys().filter((ex) => {
            const a = Number(athlete[ex]);
            const b = Number(bench[ex]);
            return b > 0 && a > b;
        }).length;
    },

    athleteHasClubRecord(athleteName, records) {
        const norm = this.normalizePersonName(athleteName);
        return (records || []).some((r) =>
            r.clubRecord
            && r.clubRecord.value > 0
            && this.normalizePersonName(r.clubRecord.name) === norm
        );
    },

    athleteBrokeClubRecord(athleteName) {
        const norm = this.normalizePersonName(athleteName);
        return this.computeRecentRecordBreaks(this.getHistory(), 500).some((b) =>
            this.normalizePersonName(b.name) === norm
        );
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
            const idx = arr.findIndex((a) => this.athletesMatch(a, athlete) || a.name === athlete.name);
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
        const records = this.computeExerciseRecords(this.state.athletesData, this.getHistory());
        const newlyGranted = [];
        const defs = this.getAchievementDefinitions();

        const tryGrant = (athlete, id, date, detail) => {
            const granted = this.grantAchievement(
                stored,
                athlete.name,
                id,
                date || today,
                athlete.coachSlug || '',
                detail || this.achievementDetailFor(athlete, id)
            );
            if (granted && (date || today) === today) {
                newlyGranted.push({
                    athleteName: athlete.name,
                    coachSlug: athlete.coachSlug || '',
                    id,
                    title: (defs[id] && defs[id].title) || id,
                    icon: defs[id] && defs[id].icon,
                    emoji: (defs[id] && defs[id].emoji) || '🏅'
                });
            }
        };

        this.state.athletesData.forEach((athlete) => {
            if (athlete.isCoach) return;
            const name = athlete.name;
            const slug = athlete.coachSlug || '';
            const currentAchIds = this.getCurrentAchievementIds(athlete, exerciseArrays);
            currentAchIds.forEach((id) => tryGrant(athlete, id, today));

            if (this.getFirstRankEventDate(name, 'league_bronze', slug)) {
                tryGrant(athlete, 'rank_bronze_done', this.getFirstRankEventDate(name, 'league_bronze', slug));
            }
            if (this.getFirstRankEventDate(name, 'league_silver', slug)) {
                tryGrant(athlete, 'rank_silver_done', this.getFirstRankEventDate(name, 'league_silver', slug));
            }
            if (this.getFirstRankEventDate(name, 'league_gold', slug)) {
                tryGrant(athlete, 'rank_gold_done', this.getFirstRankEventDate(name, 'league_gold', slug));
            }

            if (this.athleteHasClubRecord(name, records) || this.athleteBrokeClubRecord(name)) {
                const breaks = this.computeRecentRecordBreaks(this.getHistory(), 500)
                    .filter((b) => this.normalizePersonName(b.name) === this.normalizePersonName(name));
                const recordDate = breaks.length > 0 ? this.rankEventToIso(breaks[0].date) : today;
                tryGrant(athlete, 'record_club', recordDate);
            }

            const coachBeats = this.countCoachBeats(athlete);
            if (coachBeats >= 1) tryGrant(athlete, 'beat_coach_1', today);
            if (coachBeats >= 3) tryGrant(athlete, 'beat_coach_3', today);

            if (this.athleteHasFirstGain(athlete)) tryGrant(athlete, 'first_gain', today);
            if (this.countRecentGains(athlete, 30) >= 5) tryGrant(athlete, 'surge_5', today);
            if (this.athleteHasAllExercises(athlete)) tryGrant(athlete, 'warrior_six', today);
        });

        this.setStoredAchievements(stored);

        if (this.state.achievementsReadyForToast
            && newlyGranted.length
            && typeof LegionUI !== 'undefined'
            && LegionUI.showAchievementToasts) {
            LegionUI.showAchievementToasts(newlyGranted.slice(0, 3));
        }
        this.state.achievementsReadyForToast = true;

        return defs;
    },

    getAchievementDisplayData(athleteName, coachSlug) {
        const athleteAch = this.lookupAchievementEntries(athleteName, coachSlug);
        const defs = this.getAchievementDefinitions();
        const athlete = (this.state.athletesData || []).find((a) =>
            a.name === athleteName && (!coachSlug || a.coachSlug === coachSlug)
        ) || (this.state.athletesData || []).find((a) => a.name === athleteName);

        return Object.keys(defs).map((id) => {
            const def = defs[id];
            const storedAch = athleteAch.find((a) => a.id === id);
            let detail = storedAch && storedAch.detail ? storedAch.detail : '';
            if (!detail && storedAch && athlete) {
                detail = this.achievementDetailFor(athlete, id);
            }
            return {
                id,
                title: def.title || this.stripAchievementTitle(def.text),
                icon: def.icon || null,
                emoji: def.emoji || '🏅',
                text: def.text,
                desc: def.desc,
                category: def.category || 'rating',
                date: storedAch ? storedAch.date : null,
                detail: detail || '',
                active: !!storedAch
            };
        });
    },

    getRecentAchievementFeed(limit) {
        const max = limit || 12;
        const defs = this.getAchievementDefinitions();
        const stored = this.getStoredAchievements() || {};
        const athletes = this.state.athletesData || [];
        const items = [];

        Object.keys(stored).forEach((key) => {
            const entries = stored[key];
            if (!Array.isArray(entries)) return;
            let slug = '';
            let norm = key;
            const colon = key.indexOf(':');
            if (colon > 0) {
                slug = key.slice(0, colon);
                norm = key.slice(colon + 1);
            }
            const athlete = athletes.find((a) => {
                if (slug && a.coachSlug && a.coachSlug !== slug) return false;
                return this.normalizePersonName(a.name) === this.normalizePersonName(norm);
            });
            const displayName = athlete ? athlete.name : norm;
            entries.forEach((entry) => {
                const def = defs[entry.id];
                if (!def || !entry.date) return;
                items.push({
                    name: displayName,
                    coachSlug: (athlete && athlete.coachSlug) || slug,
                    athleteId: athlete && athlete.id ? athlete.id : 0,
                    id: entry.id,
                    title: def.title,
                    icon: def.icon,
                    emoji: def.emoji || '🏅',
                    date: entry.date,
                    detail: entry.detail || ''
                });
            });
        });

        items.sort((a, b) => String(b.date).localeCompare(String(a.date)));
        return items.slice(0, max);
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

    escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    formatAthleteLink(name, coachSlug) {
        const attr = this.escapeHtmlAttr(name);
        const slugAttr = coachSlug
            ? ` data-coach-slug="${this.escapeHtmlAttr(coachSlug)}"`
            : '';
        return `<span class="athlete-name" role="button" tabindex="0" data-athlete-name="${attr}"${slugAttr}>${name}</span>`;
    },

    athleteProfileUrl(athleteOrName, coachSlug) {
        const params = new URLSearchParams();
        let name = '';
        let slug = coachSlug || '';
        let id = 0;
        if (athleteOrName && typeof athleteOrName === 'object') {
            name = athleteOrName.name || '';
            slug = athleteOrName.coachSlug || slug;
            id = Number(athleteOrName.id || athleteOrName.athleteId || 0) || 0;
        } else {
            name = athleteOrName || '';
        }
        if (id > 0) {
            params.set('id', String(id));
        } else if (name) {
            params.set('name', name);
        }
        if (slug) params.set('coach', slug);
        return `/athlete/?${params.toString()}`;
    },

    getAthletePhotoSrc(photo, fallback) {
        const fb = fallback || "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2250%22 fill=%22%23222%22/%3E%3Ctext x=%2260%22 y=%2270%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%23888%22%3E👤%3C/text%3E%3C/svg%3E";
        if (!photo) return fb;
        if (photo.startsWith('http') || photo.startsWith('/') || photo.startsWith('data:')) return photo;
        return fb;
    },

    setModalPhoto(athleteOrPhoto) {
        const img = document.getElementById('modal-photo');
        if (!img) return;
        const photo = athleteOrPhoto && typeof athleteOrPhoto === 'object'
            ? athleteOrPhoto.photo
            : athleteOrPhoto;
        img.onerror = () => {
            img.onerror = null;
            img.src = this.getAthletePhotoSrc('');
        };
        img.src = this.getAthletePhotoSrc(photo);
    },

    updateAthleteModalMoreLink(athlete) {
        const links = [
            document.getElementById('athlete-modal-card-top'),
            document.getElementById('athlete-modal-more')
        ].filter(Boolean);
        if (!links.length) return;
        if (!athlete || athlete.isCoach) {
            links.forEach((link) => {
                link.hidden = true;
                link.removeAttribute('href');
                link.onclick = null;
            });
            return;
        }
        const href = this.athleteProfileUrl(athlete);
        links.forEach((link) => {
            link.href = href;
            link.hidden = false;
            link.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                window.location.href = href;
            };
        });
    },

    athletesMatch(a, b) {
        if (!a || !b) return false;
        const aId = Number(a.id || 0);
        const bId = Number(b.id || 0);
        if (aId > 0 && bId > 0) return aId === bId;
        return this.normalizePersonName(a.name) === this.normalizePersonName(b.name)
            && (!a.coachSlug || !b.coachSlug || a.coachSlug === b.coachSlug);
    },

    computePlaceGap(sorted, athlete) {
        const idx = sorted.findIndex((a) => this.athletesMatch(a, athlete));
        if (idx < 0) {
            return { place: null, gapPoints: null, isLeader: false };
        }
        const place = idx + 1;
        if (place <= 1) {
            return { place: 1, gapPoints: null, targetPlace: null, isLeader: true };
        }
        const myTotal = Number(athlete.total) || 0;
        const aboveTotal = Number(sorted[idx - 1].total) || 0;
        return {
            place,
            targetPlace: place - 1,
            gapPoints: Math.max(1, aboveTotal - myTotal + 1),
            isLeader: false
        };
    },

    exerciseUnitHint(exKey) {
        const map = {
            push: { one: 'отжимание', few: 'отжимания', many: 'отжиманий' },
            pull: { one: 'подтягивание', few: 'подтягивания', many: 'подтягиваний' },
            hang: { one: 'сек виса', few: 'сек виса', many: 'сек виса' },
            burpee: { one: 'бёрпи', few: 'бёрпи', many: 'бёрпи' },
            crunch: { one: 'скручивание', few: 'скручивания', many: 'скручиваний' },
            jump: { one: 'см в прыжке', few: 'см в прыжке', many: 'см в прыжке' }
        };
        return map[exKey] || { one: '', few: '', many: '' };
    },

    formatExerciseDelta(exKey, delta) {
        const n = Number(delta);
        if (!(n > 0)) return '';
        const rounded = (exKey === 'jump' || exKey === 'hang')
            ? Math.round(n * 10) / 10
            : Math.ceil(n);
        const absInt = Math.floor(Math.abs(rounded));
        const hint = this.exerciseUnitHint(exKey);
        let word = hint.many;
        const mod100 = absInt % 100;
        const mod10 = absInt % 10;
        if (mod100 < 11 || mod100 > 14) {
            if (mod10 === 1) word = hint.one;
            else if (mod10 >= 2 && mod10 <= 4) word = hint.few;
        }
        const num = (exKey === 'jump' || exKey === 'hang') && rounded % 1 !== 0
            ? String(rounded).replace('.', ',')
            : String(Math.round(rounded));
        return `+${num} ${word}`;
    },

    pointsIfExerciseResult(pool, athlete, exKey, newVal) {
        const target = Number(newVal) || 0;
        if (target <= 0) return 0;
        const values = [];
        pool.forEach((a) => {
            if (a.isCoach) return;
            let val = Number(a[exKey]) || 0;
            if (this.athletesMatch(a, athlete)) val = target;
            if (val > 0) values.push(val);
        });
        if (!values.length) return 0;
        values.sort((a, b) => b - a);
        let rank = 1;
        let prev = null;
        for (let i = 0; i < values.length; i++) {
            if (prev === null || values[i] !== prev) rank = i + 1;
            if (Math.abs(values[i] - target) < 1e-9) {
                return this.pointsForRank(rank);
            }
            prev = values[i];
        }
        return 0;
    },

    findBestExerciseClimb(pool, athlete, gapPoints) {
        const need = Number(gapPoints) || 0;
        if (need <= 0 || !Array.isArray(pool)) return null;

        const competitors = pool.filter((a) => !a.isCoach);
        let bestClose = null;
        let bestPartial = null;

        LegionConfig.EXERCISES.forEach((ex) => {
            const key = ex.key;
            const myVal = Number(athlete[key]) || 0;
            const currentPts = Number(athlete[key + '_points']) || 0;
            if (currentPts >= 100) return;

            const otherVals = competitors
                .filter((a) => !this.athletesMatch(a, athlete))
                .map((a) => Number(a[key]) || 0)
                .filter((v) => v > 0);

            const targets = new Set();
            otherVals.forEach((v) => {
                if (v > myVal) targets.add(v);
            });
            if (myVal <= 0) {
                if (otherVals.length) targets.add(Math.min(...otherVals));
                else targets.add(1);
            }
            if (!targets.size && myVal > 0) return;

            const sortedTargets = [...targets].sort((a, b) => a - b);
            const maxOther = otherVals.length ? Math.max(...otherVals) : 0;
            if (maxOther > 0) {
                const leadTarget = (key === 'jump' || key === 'hang')
                    ? Math.round((maxOther + 0.1) * 10) / 10
                    : maxOther + 1;
                if (leadTarget > myVal) sortedTargets.push(leadTarget);
            }

            let bestForEx = null;
            sortedTargets.forEach((target) => {
                if (!(target > myVal)) return;
                const newPts = this.pointsIfExerciseResult(competitors, athlete, key, target);
                const gained = newPts - currentPts;
                if (gained <= 0) return;
                const delta = Math.round((target - myVal) * 10) / 10;
                const option = {
                    key,
                    label: ex.label,
                    delta,
                    target,
                    gained,
                    newPts,
                    closesGap: gained >= need
                };
                if (!bestForEx
                    || (option.closesGap && !bestForEx.closesGap)
                    || (option.closesGap === bestForEx.closesGap && option.delta < bestForEx.delta)
                    || (option.closesGap === bestForEx.closesGap && option.delta === bestForEx.delta && option.gained > bestForEx.gained)) {
                    bestForEx = option;
                }
            });

            if (!bestForEx) return;

            if (bestForEx.closesGap) {
                if (!bestClose
                    || bestForEx.delta < bestClose.delta
                    || (bestForEx.delta === bestClose.delta && bestForEx.gained > bestClose.gained)) {
                    bestClose = bestForEx;
                }
            } else if (!bestClose) {
                const efficiency = bestForEx.gained / Math.max(bestForEx.delta, 0.1);
                const prevEff = bestPartial
                    ? bestPartial.gained / Math.max(bestPartial.delta, 0.1)
                    : -1;
                if (!bestPartial
                    || bestForEx.gained > bestPartial.gained
                    || (bestForEx.gained === bestPartial.gained && efficiency > prevEff)
                    || (bestForEx.gained === bestPartial.gained && efficiency === prevEff && bestForEx.delta < bestPartial.delta)) {
                    bestPartial = bestForEx;
                }
            }
        });

        return bestClose || bestPartial;
    },

    formatGoalAdvice(scope, climb, gapPoints) {
        if (!climb) {
            if (!(gapPoints > 0)) return '';
            const where = scope === 'coach'
                ? 'место у тренера'
                : 'место в общем рейтинге';
            return `Если хочешь улучшить ${where}, нужно ещё <strong>+${gapPoints}</strong> очков`;
        }
        const deltaText = this.formatExerciseDelta(climb.key, climb.delta);
        if (!deltaText) return '';
        const where = scope === 'coach'
            ? 'место у тренера'
            : 'место в общем рейтинге';
        let html = `Если хочешь улучшить ${where}, сделай <strong>${this.escapeHtml(deltaText)}</strong>`;
        if (!climb.closesGap && gapPoints > 0) {
            html += ` <span class="athlete-profile-goal-sub">(+${climb.gained} из ${gapPoints} очков)</span>`;
        }
        return html;
    },

    /** HTML подсказки «сделай +N …» для карточки / модалки. */
    buildAthleteGoalHtml(athlete, options) {
        const opts = options || {};
        if (!athlete || athlete.isCoach) return '';

        const clubPool = (this.state.athletesData || []).filter((a) => !a.isCoach);
        const overallSorted = this.state.overallSorted || this.sortByTotal(clubPool);
        const overallGap = this.computePlaceGap(overallSorted, athlete);
        const coachPool = clubPool.filter((a) => a.coach === athlete.coach);
        const coachSorted = this.sortByTotal(coachPool);
        const coachGap = this.computePlaceGap(coachSorted, athlete);
        const brief = !!opts.brief;
        const goalClass = opts.goalClass || 'athlete-profile-goal';

        const lines = [];
        if (overallGap.isLeader) {
            lines.push(`<p class="${goalClass} ${goalClass}--leader">1-е общее место в клубе</p>`);
        } else if (overallGap.gapPoints != null) {
            const climb = this.findBestExerciseClimb(clubPool, athlete, overallGap.gapPoints);
            const advice = this.formatGoalAdvice('club', climb, overallGap.gapPoints);
            if (advice) lines.push(`<p class="${goalClass}">${advice}</p>`);
        }

        if (!brief) {
            if (!coachGap.isLeader && coachGap.gapPoints != null
                && !(overallGap.gapPoints === coachGap.gapPoints && overallGap.place === coachGap.place)) {
                const climb = this.findBestExerciseClimb(coachPool, athlete, coachGap.gapPoints);
                const advice = this.formatGoalAdvice('coach', climb, coachGap.gapPoints);
                if (advice) {
                    lines.push(`<p class="${goalClass} ${goalClass}--coach">${advice}</p>`);
                }
            } else if (coachGap.isLeader && !overallGap.isLeader) {
                lines.push(`<p class="${goalClass} ${goalClass}--coach">1-е место у тренера</p>`);
            }
        }

        return lines.join('');
    },

    formatEliteIcon(title) {
        const icon = LegionConfig.ELITE_ICON || '🛡️';
        const attr = title ? ` title="${title}"` : '';
        return `<span class="elite-icon"${attr}>${icon}</span> `;
    },

    isClubTop25(athleteOrName) {
        let athlete = athleteOrName;
        if (typeof athleteOrName === 'string') {
            athlete = (this.state.athletesData || []).find((a) => a.name === athleteOrName);
        }
        if (!athlete || athlete.isCoach) return false;
        const rank = this.getClubOverallRank(athlete);
        return typeof rank === 'number' && rank <= 25;
    },

    formatTop25Icon(title) {
        const label = title || 'ТОП-25 Легиона Силы';
        return `<span class="top25-badge" title="${this.escapeHtmlAttr(label)}" aria-label="${this.escapeHtmlAttr(label)}">ТОП-25</span>`;
    },

    getAthleteAge(athlete) {
        if (!athlete) return null;
        if (typeof athlete.age === 'number' && athlete.age >= 0) {
            return athlete.age;
        }
        const birthdate = athlete.birthdate;
        if (!birthdate) return null;
        const born = new Date(`${birthdate}T12:00:00`);
        if (Number.isNaN(born.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - born.getFullYear();
        const monthDiff = today.getMonth() - born.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < born.getDate())) {
            age -= 1;
        }
        return age >= 0 ? age : null;
    },

    formatAgeYears(age) {
        const n = Math.abs(Math.round(age));
        const mod10 = n % 10;
        const mod100 = n % 100;
        let word = 'лет';
        if (mod100 < 11 || mod100 > 14) {
            if (mod10 === 1) word = 'год';
            else if (mod10 >= 2 && mod10 <= 4) word = 'года';
        }
        return `${n} ${word}`;
    },

    formatAthleteAge(athlete) {
        const age = this.getAthleteAge(athlete);
        return age === null ? '' : this.formatAgeYears(age);
    },

    fillAthleteModalAge(athlete) {
        const el = document.getElementById('modal-age');
        if (!el) return;
        const label = this.formatAthleteAge(athlete);
        if (!label) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.textContent = label;
        el.hidden = false;
    },

    getCellClass(rank) {
        if (rank === 1) return 'rank-1-cell';
        if (rank === 2) return 'rank-2-cell';
        if (rank === 3) return 'rank-3-cell';
        return '';
    },

    formatRankDisplay(name, coachSlug) {
        const athlete = (this.state.athletesData || []).find((a) =>
            a.name === name && (!coachSlug || a.coachSlug === coachSlug)
        ) || (this.state.athletesData || []).find((a) => a.name === name);
        const slug = coachSlug || (athlete && athlete.coachSlug) || '';
        const clubRank = this.getClubRank(name, slug);
        if (!clubRank) return '—';
        const meta = LegionConfig.LEAGUE_META[clubRank.league] || {};
        const attr = this.escapeHtmlAttr(name);
        const slugAttr = slug ? ` data-coach-slug="${this.escapeHtmlAttr(slug)}"` : '';
        const label = clubRank.rankName
            ? `${clubRank.rankName} — ${meta.short}`
            : `${meta.short} · старт`;
        return `<span class="rank-clickable" role="button" tabindex="0" data-athlete-name="${attr}"${slugAttr} style="color:${meta.color || 'inherit'}; font-weight:bold;">${label} (${clubRank.completed}/${clubRank.total})</span>`;
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

    historyDayKey(dateStr) {
        const m = String(dateStr || '').match(/(\d{1,2}\.\d{1,2}\.\d{4})/);
        return m ? m[1] : '';
    },

    parseHistoryDateTime(dateStr) {
        if (!dateStr) return 0;
        const m = String(dateStr).match(/(\d{1,2})\.(\d{1,2})\.(\d{4})(?:,\s*(\d{1,2}):(\d{2}):(\d{2}))?/);
        if (!m) return 0;
        return new Date(
            parseInt(m[3], 10),
            parseInt(m[2], 10) - 1,
            parseInt(m[1], 10),
            m[4] ? parseInt(m[4], 10) : 0,
            m[5] ? parseInt(m[5], 10) : 0,
            m[6] ? parseInt(m[6], 10) : 0
        ).getTime();
    },

    aggregateExerciseHistoryByDay(entries) {
        const groups = new Map();
        entries.forEach((entry) => {
            const day = this.historyDayKey(entry.date);
            const exercise = entry.exercise || '';
            if (!day || !exercise) return;

            const key = `${day}\0${exercise}`;
            const ts = this.parseHistoryDateTime(entry.date);
            const diff = Number(entry.diff) || 0;

            if (!groups.has(key)) {
                groups.set(key, {
                    name: entry.name,
                    exercise,
                    day,
                    diff: 0,
                    sortTs: ts,
                    sortDate: entry.date,
                    firstTs: ts,
                    oldVal: entry.oldVal,
                    newVal: entry.newVal
                });
            }

            const group = groups.get(key);
            group.diff += diff;
            if (ts < group.firstTs) {
                group.firstTs = ts;
                group.oldVal = entry.oldVal;
            }
            if (ts >= group.sortTs) {
                group.sortTs = ts;
                group.sortDate = entry.date;
                group.newVal = entry.newVal;
            }
        });

        return [...groups.values()]
            .filter((group) => group.diff > 0)
            .map((group) => ({
                name: group.name,
                exercise: group.exercise,
                date: group.sortDate,
                diff: group.diff,
                oldVal: group.oldVal,
                newVal: group.newVal
            }));
    },

    getAthleteProgressHistory(name, coachSlug) {
        const limit = LegionConfig.HISTORY_PER_ATHLETE || 50;
        const rankProgressEvents = {
            mark_pass: true,
            league_bronze: true,
            league_silver: true,
            league_gold: true
        };
        const athleteRef = { name, coachSlug: coachSlug || '' };
        const rawExerciseHistory = this.getHistoryForAthlete(athleteRef)
            .filter((e) => Number(e.diff) > 0);
        const exerciseEntries = this.aggregateExerciseHistoryByDay(rawExerciseHistory)
            .map((e) => ({ kind: 'exercise', entry: e }));
        const rankEntries = this.getRankHistory()
            .filter((e) => this.historyEntryMatchesAthlete(e, athleteRef) && rankProgressEvents[e.event])
            .map((e) => ({ kind: 'rank', entry: e }));
        return [...exerciseEntries, ...rankEntries]
            .sort((a, b) => this.parseHistoryDateTime(b.entry.date) - this.parseHistoryDateTime(a.entry.date))
            .slice(0, limit);
    },

    formatProgressHistoryItem(item) {
        if (!item || !item.entry) return '';
        if (item.kind === 'rank') {
            return this.formatRankHistoryEvent(item.entry);
        }
        return this.formatHistoryChangeText(item.entry);
    },

    renderAthleteProgressContent(name, coachSlug) {
        const items = this.getAthleteProgressHistory(name, coachSlug);
        if (!items.length) {
            return '<p class="note athlete-log-empty">Пока нет записей о прогрессе — они появятся после улучшения результата или сдачи норматива на ранг.</p>';
        }
        let html = '<div class="history-list athlete-history-list">';
        items.forEach((item) => {
            const cls = item.kind === 'rank' ? 'history-item history-item--rank' : 'history-item';
            html += `<div class="${cls}">${this.formatProgressHistoryItem(item)}</div>`;
        });
        html += '</div>';
        return html;
    },

    renderAthleteProgressBlock(name, coachSlug) {
        return `<div class="modal-progress-block"><h3 class="section-title">Прогресс</h3>${this.renderAthleteProgressContent(name, coachSlug)}</div>`;
    },

    renderAthleteHistorySections(name, coachSlug) {
        return this.renderAthleteProgressContent(name, coachSlug);
    },

    renderHistoryList(name, options) {
        const opts = options || {};
        const limit = LegionConfig.HISTORY_PER_ATHLETE || 50;
        const coachSlug = opts.coachSlug || '';
        const personHistory = this.getHistoryForAthlete({ name, coachSlug })
            .slice(-limit)
            .reverse();
        if (personHistory.length === 0) return '';
        let histHtml = opts.includeHeading === false
            ? '<div class="history-list">'
            : `<h3>История изменений <span class="history-count">(${personHistory.length})</span></h3><div class="history-list">`;
        personHistory.forEach(entry => {
            histHtml += `<div class="history-item">${this.formatHistoryChangeText(entry)}</div>`;
        });
        histHtml += '</div>';
        return histHtml;
    },

    renderHistoryBlock(name, coachSlug) {
        const list = this.renderHistoryList(name, { coachSlug, includeHeading: false });
        if (!list) return '<p>Нет записей об изменениях.</p>';
        const count = this.getHistoryForAthlete({ name, coachSlug }).length;
        return `<h3>История изменений <span class="history-count">(${count})</span></h3>${list}`;
    },

    formatRankHistoryEvent(entry) {
        const date = this.formatHistoryEntryDate(entry.date);
        if (entry.event === 'mark_pass' || entry.event === 'mark_revoke') {
            let title = 'норматив';
            const idx = typeof entry.markIndex === 'number' ? entry.markIndex : parseInt(entry.markIndex, 10);
            if (!isNaN(idx)) {
                [3, 2, 1].some((league) => {
                    const { offset, exercises } = this.getLeagueExerciseList(league);
                    if (idx >= offset && idx < offset + exercises.length) {
                        title = exercises[idx - offset];
                        return true;
                    }
                    return false;
                });
            }
            const verb = entry.event === 'mark_pass' ? 'Сдал' : 'Снял';
            const icon = entry.event === 'mark_pass' ? '✓' : '✗';
            return `<span class="history-item-date">${date}</span> <span class="history-item-rank-icon" aria-hidden="true">${icon}</span> ${verb}: ${this.escapeHtml(title)}`;
        }
        const map = {
            league_bronze: { icon: '🥉', text: 'Закрыл бронзовую лигу — все 20 нормативов' },
            league_silver: { icon: '🥈', text: 'Закрыл серебряную лигу — все 20 нормативов' },
            league_gold: { icon: '🥇', text: 'Закрыл золотую лигу — все 20 нормативов' }
        };
        const meta = map[entry.event] || { icon: '🏅', text: entry.event || 'Событие ранга' };
        return `<span class="history-item-date">${date}</span> <span class="history-item-rank-icon" aria-hidden="true">${meta.icon}</span> ${meta.text}`;
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

    exerciseHistoryForms: {
        push: ['отжимание', 'отжимания', 'отжиманий'],
        pull: ['подтягивание', 'подтягивания', 'подтягиваний'],
        hang: ['секунду виса', 'секунды виса', 'секунд виса'],
        burpee: ['бёрпи', 'бёрпи', 'бёрпи'],
        crunch: ['скручивание', 'скручивания', 'скручиваний'],
        jump: ['см прыжка', 'см прыжка', 'см прыжка']
    },

    pluralRu(n, forms) {
        const abs = Math.abs(Math.round(n));
        const mod10 = abs % 10;
        const mod100 = abs % 100;
        if (mod10 === 1 && mod100 !== 11) return forms[0];
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return forms[1];
        return forms[2];
    },

    formatHistoryEntryDate(dateStr) {
        const m = String(dateStr || '').match(/(\d{1,2}\.\d{1,2}\.\d{4})/);
        return m ? m[1] : String(dateStr || '');
    },

    getExerciseHistoryWord(exerciseKey, diff) {
        const forms = this.exerciseHistoryForms[exerciseKey] || ['результат', 'результата', 'результатов'];
        return this.pluralRu(diff, forms);
    },

    formatHistoryChangeText(entry) {
        const date = this.formatHistoryEntryDate(entry.date);
        const diff = Number(entry.diff);
        const word = this.getExerciseHistoryWord(entry.exercise, diff);
        if (diff > 0) {
            return `<span class="history-item-date">${date}</span> Сделал <span class="history-item-change history-item-change--up">+${diff}</span> ${word}`;
        }
        if (diff < 0) {
            const abs = Math.abs(diff);
            return `<span class="history-item-date">${date}</span> Сделал <span class="history-item-change history-item-change--down">−${abs}</span> ${word}`;
        }
        const label = this.getExerciseLabel(entry.exercise);
        return `<span class="history-item-date">${date}</span> ${label}: ${entry.oldVal} → ${entry.newVal}`;
    },

    computeRecentRecordBreaks(history, limit) {
        const maxEntries = limit || 25;
        const maxByEx = {};
        const breaks = [];

        const sorted = [...history].sort((a, b) => this.parseHistoryDate(a.date) - this.parseHistoryDate(b.date));

        sorted.forEach(entry => {
            const val = Number(entry.newVal);
            if (isNaN(val) || val <= 0) return;

            const prev = maxByEx[entry.exercise] || { value: 0, name: '' };
            if (val > prev.value) {
                const oldVal = prev.value > 0 ? prev.value : Number(entry.oldVal) || 0;
                breaks.push({
                    date: entry.date,
                    name: entry.name,
                    prevHolder: prev.value > 0 ? prev.name : null,
                    exercise: entry.exercise,
                    exerciseLabel: this.getExerciseLabel(entry.exercise),
                    oldVal,
                    newVal: val,
                    diff: val - (prev.value > 0 ? prev.value : oldVal)
                });
                maxByEx[entry.exercise] = { value: val, name: entry.name };
            }
        });

        return breaks.reverse().slice(0, maxEntries);
    },

    exercisePrepositional: {
        push: 'отжиманиях',
        pull: 'подтягиваниях',
        hang: 'висе',
        burpee: 'бёрпи',
        crunch: 'скручиваниях',
        jump: 'прыжке в длину'
    },

    stablePhraseIndex(seed, count) {
        let hash = 0;
        const str = String(seed || '');
        for (let i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash) % count;
    },

    formatRecordPerson(name) {
        if (!name) return '';
        if (typeof LegionUI !== 'undefined' && LegionUI.formatHallAthlete) {
            return LegionUI.formatHallAthlete(name);
        }
        return this.escapeHtml(name);
    },

    formatHallRecordLine(breakEntry, index) {
        const date = this.formatHistoryEntryDate(breakEntry.date);
        const hero = this.formatRecordPerson(breakEntry.name);
        const prevHolder = breakEntry.prevHolder ? this.formatRecordPerson(breakEntry.prevHolder) : '';
        const ex = this.exercisePrepositional[breakEntry.exercise] || breakEntry.exerciseLabel;
        const oldV = breakEntry.oldVal;
        const newV = breakEntry.newVal;
        const diff = Number(breakEntry.diff) || (newV - oldV);
        const seed = `${breakEntry.date}|${breakEntry.name}|${breakEntry.exercise}|${newV}`;

        if (!breakEntry.prevHolder || oldV <= 0) {
            const templates = [
                `<span class="history-item-date">${date}</span> ${hero} открыл счёт в ${ex}: <strong>${newV}</strong>!`,
                `<span class="history-item-date">${date}</span> Первая планка в ${ex} — <strong>${newV}</strong>. Поставил ${hero}.`,
                `<span class="history-item-date">${date}</span> ${hero} занёс в книгу рекордов ${ex}: <strong>${newV}</strong>.`,
                `<span class="history-item-date">${date}</span> До ${hero} в ${ex} никто не добирался — теперь планка <strong>${newV}</strong>.`,
                `<span class="history-item-date">${date}</span> ${hero} стал первым героем ${ex} с результатом <strong>${newV}</strong>.`
            ];
            return templates[this.stablePhraseIndex(seed, templates.length)];
        }

        const templates = [
            `<span class="history-item-date">${date}</span> ${hero} переписал рекорд ${prevHolder} в ${ex}: <strong>${oldV} → ${newV}</strong>`,
            `<span class="history-item-date">${date}</span> ${prevHolder} держал ${oldV} в ${ex}, но ${hero} добил до <strong>${newV}</strong>`,
            `<span class="history-item-date">${date}</span> ${hero} снял ${prevHolder} с трона ${ex} — было ${oldV}, стало <strong>${newV}</strong>`,
            `<span class="history-item-date">${date}</span> Рекорд ${prevHolder} (${oldV}) не выдержал: ${hero} выжал <strong>${newV}</strong> в ${ex}`,
            `<span class="history-item-date">${date}</span> ${prevHolder} ещё отдыхал, а ${hero} уже <strong>+${diff}</strong> в ${ex} и новые <strong>${newV}</strong>`,
            `<span class="history-item-date">${date}</span> ${hero} обошёл ${prevHolder} в ${ex}: <span class="history-item-change history-item-change--up">+${diff}</span> (${oldV} → <strong>${newV}</strong>)`,
            `<span class="history-item-date">${date}</span> Легенда ${prevHolder} (${oldV}) уступила место — ${hero} поставил <strong>${newV}</strong> в ${ex}`,
            `<span class="history-item-date">${date}</span> ${hero} без церемоний забрал рекорд у ${prevHolder} в ${ex}: <strong>${newV}</strong> вместо ${oldV}`
        ];
        return templates[this.stablePhraseIndex(seed + String(index || 0), templates.length)];
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
        if (athlete) {
            if (typeof window.openAthleteModal === 'function') {
                window.openAthleteModal(name);
            }
            return;
        }
        if (typeof LegionCoachPage !== 'undefined'
            && LegionCoachPage.coachBenchmark
            && LegionCoachPage.coachBenchmark.name === name
            && typeof window.openCoachModal === 'function') {
            window.openCoachModal();
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

    openRankModal(name, coachSlug) {
        const modal = document.getElementById('rankModal');
        const titleEl = document.getElementById('rank-modal-title');
        const contentEl = document.getElementById('rank-modal-content');
        if (!modal || !titleEl || !contentEl) {
            console.error('Окно рангов не найдено — залейте modals-coach.php или modals-club.php на сервер.');
            return;
        }
        const athlete = (this.state.athletesData || []).find((a) =>
            a.name === name && (!coachSlug || a.coachSlug === coachSlug)
        ) || (this.state.athletesData || []).find((a) => a.name === name);
        let slug = coachSlug || (athlete && athlete.coachSlug) || '';
        if (!slug) {
            const benchmarks = this.state.coachBenchmarks || {};
            const norm = this.normalizePersonName(name);
            Object.keys(benchmarks).forEach((key) => {
                if (!slug && this.normalizePersonName(benchmarks[key].name) === norm) {
                    slug = key;
                }
            });
        }
        const clubRank = this.getClubRank(name, slug);
        if (!clubRank) return;
        if (typeof LegionUI === 'undefined' || typeof LegionUI.renderRankModal !== 'function') {
            console.error('Не загружен legion-ui.js');
            return;
        }
        let marks = this.lookupRankMarks(name, slug);
        if (!marks && athlete && Array.isArray(athlete.rankMarks)) {
            marks = this.normalizeRankMarksValue(athlete.rankMarks);
        }
        titleEl.textContent = name;
        contentEl.innerHTML = LegionUI.renderRankModal(name, clubRank, marks);
        modal.classList.add('active');
    },

    fillAthleteModalTable(athlete, options) {
        const opts = options || {};
        const coachOnly = !!opts.coachOnly;
        const thead = document.querySelector('#athleteModal thead tr');
        if (thead) {
            thead.innerHTML = coachOnly
                ? '<th>Упражнение</th><th>Результат</th>'
                : '<th>Упражнение</th><th>Результат</th><th>Место</th><th>Очки</th>';
        }
        let tbody = '';
        LegionConfig.EXERCISES.forEach((ex) => {
            const result = athlete[ex.key];
            if (coachOnly) {
                tbody += `<tr><td>${ex.label}</td><td>${result > 0 ? result : '–'}</td></tr>`;
                return;
            }
            const place = athlete[ex.key + '_rank'] || '–';
            const pts = athlete[ex.key + '_points'];
            tbody += `<tr><td>${ex.label}</td><td>${result}</td><td>${result > 0 ? place : '–'}</td><td>${pts}</td></tr>`;
        });
        document.getElementById('modal-body').innerHTML = tbody;
    },

    setAthleteModalRanksRowVisible(visible) {
        const row = document.getElementById('modal-ranks-row');
        if (row) row.hidden = !visible;
    },

    fillAthleteModalExtras(name, athlete, options) {
        const opts = options || {};
        const slug = (athlete && athlete.coachSlug) || opts.coachSlug || '';
        const progressEl = document.getElementById('modal-progress');
        if (progressEl && opts.showProgress !== false) {
            progressEl.innerHTML = this.renderAthleteProgressBlock(name, slug);
        } else if (progressEl) {
            progressEl.innerHTML = '';
        }
        const achEl = document.getElementById('modal-achievements');
        if (achEl && opts.showAchievements !== false) {
            achEl.innerHTML = LegionUI.renderAchievementGrid(
                this.getAchievementDisplayData(name, slug),
                { variant: 'modal' }
            );
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
                const rankSlug = rankEl.getAttribute('data-coach-slug') || '';
                if (rankName) {
                    e.preventDefault();
                    this.openRankModal(rankName, rankSlug || undefined);
                }
                return;
            }
            const nameEl = e.target.closest('.athlete-name[data-athlete-name]');
            if (nameEl) {
                const athleteName = nameEl.getAttribute('data-athlete-name');
                const coachSlug = nameEl.getAttribute('data-coach-slug') || '';
                if (athleteName && typeof window.openAthleteModal === 'function') {
                    e.preventDefault();
                    window.openAthleteModal(athleteName, coachSlug || undefined);
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
        const tabsEl = document.getElementById('legion-exercise-tabs');
        if (tabsEl && typeof LegionUI !== 'undefined') {
            if (tabsEl.getAttribute('data-tabs-rendered') === 'php' || tabsEl.querySelector('.tab')) {
                if (LegionUI.bindExerciseTabs) {
                    LegionUI.bindExerciseTabs(tabsEl);
                }
            } else if (LegionUI.renderExerciseTabs) {
                LegionUI.renderExerciseTabs(tabsEl, {
                    includeHall: tabsEl.getAttribute('data-include-hall') !== '0',
                    activeTab: this.state.currentTab || 'overall'
                });
            }
        }
    },

    bindCommonGlobals() {
        const self = this;
        window.closeModal = (e) => self.closeModal(e);
        window.closeRankModal = (e) => self.closeRankModal(e);
        window.openRankModal = (name) => self.openRankModal(name);
    }
};
