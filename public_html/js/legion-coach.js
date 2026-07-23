/**
 * Страница тренера: элита, ротация, рейтинг группы.
 * Инициализация: <body data-legion-page="coach" data-coach-slug="yakutin">
 */
const LegionCoachPage = {
    coach: null,
    coachAthletes: [],
    coachSorted: [],
    coachBenchmark: null,
    serverElite: [],
    serverLastRotationMonth: null,
    lastResultsScope: null,

    init() {
        const slug = this.resolveCoachSlug();
        this.coach = LegionConfig.getCoach(slug);
        if (!this.coach) {
            const content = document.getElementById('content');
            const msg = slug
                ? `Тренер «${slug}» не найден в конфигурации. Проверьте api/coaches.php на сервере.`
                : 'Не удалось определить страницу тренера. Откройте рейтинг через меню «Тренеры».';
            if (content) content.innerHTML = `<p class="error">${msg}</p>`;
            return;
        }
        this.lastResultsScope = (typeof LegionConfig !== 'undefined' && LegionConfig.CLUB_LAST_RESULTS_SCOPE)
            ? LegionConfig.CLUB_LAST_RESULTS_SCOPE
            : 'global';
        LegionCore.bindCommonGlobals();
        this.bindGlobals();
        LegionCore.initPageUI(() => this.renderCurrentTab());
        document.addEventListener('click', (e) => {
            if (e.target.closest('.rank-clickable, .rank-summary-card')) return;
            const row = e.target.closest('.coach-profile-row');
            if (row && typeof window.openCoachModal === 'function') {
                e.preventDefault();
                window.openCoachModal();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.target.closest('.rank-clickable, .rank-summary-card')) return;
            const row = e.target.closest('.coach-profile-row');
            if (row && typeof window.openCoachModal === 'function') {
                e.preventDefault();
                window.openCoachModal();
            }
        });
        this.updateRotationPanelInfo();
        this.startPage();
    },

    resolveCoachSlug() {
        let slug = (document.body && document.body.dataset.coachSlug) || '';
        if (slug) return slug;
        const known = LegionConfig.getCoaches().map(c => c.slug);
        const segments = window.location.pathname.split('/').filter(Boolean);
        return segments.find(s => known.includes(s)) || '';
    },

    startPage() {
        const contentDiv = document.getElementById('content');
        const loadingMsg = this.coach.storage === 'mysql'
            ? 'Загрузка данных с сервера…'
            : 'Загрузка данных из Google Таблиц…';
        if (contentDiv) {
            contentDiv.innerHTML = `<p class="note">${loadingMsg}</p>`;
        }
        this.onLoad().catch((err) => {
            console.error('Ошибка инициализации страницы тренера:', err);
            const contentDiv = document.getElementById('content');
            if (contentDiv) {
                contentDiv.innerHTML = `<p class="error">Не удалось загрузить рейтинг: ${err.message || err}</p>`;
            }
        });
    },

    bindGlobals() {
        const self = this;
        window.switchTab = (tab) => self.switchTab(tab);
        window.openAthleteModal = (name, coachSlug) => self.openAthleteModal(name, coachSlug);
        window.openCoachModal = () => self.openCoachModal();
        window.rotateLeagues = () => self.rotateLeagues(false);
    },

    async onLoad() {
        await this.loadRating();
    },

    // ---------- Элита (API) ----------

    async loadEliteFromServer() {
        const { API } = LegionConfig;
        try {
            const resp = await LegionCore.fetchApi(`${API.eliteLoad}?scope=${this.coach.slug}`);
            if (resp.ok) {
                const data = await resp.json();
                this.serverElite = Array.isArray(data.elite) ? data.elite : [];
                this.serverLastRotationMonth = data.lastRotationMonth || null;
            } else {
                this.serverElite = [];
                this.serverLastRotationMonth = null;
            }
        } catch (e) {
            console.warn('Ошибка загрузки элиты:', e);
            this.serverElite = [];
            this.serverLastRotationMonth = null;
        }
    },

    async saveEliteToServer() {
        const { API } = LegionConfig;
        try {
            await fetch(API.eliteSave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    scope: this.coach.slug,
                    elite: this.serverElite,
                    lastRotationMonth: this.serverLastRotationMonth
                })
            });
        } catch (e) {
            console.warn('Ошибка сохранения элиты:', e);
        }
    },

    getElite() {
        return this.serverElite;
    },

    setElite(list) {
        this.serverElite = list;
        this.saveEliteToServer();
        this.updateRotationPanelInfo();
    },

    getLastRotationMonth() {
        return this.serverLastRotationMonth;
    },

    setLastRotationMonth(month) {
        this.serverLastRotationMonth = month;
        this.saveEliteToServer();
        this.updateRotationPanelInfo();
    },

    async migrateEliteFromLocalStorage() {
        if (this.serverElite.length > 0) return;
        const slug = this.coach.slug;
        const localElite = localStorage.getItem(`${slug}Elite`);
        const localMonth = localStorage.getItem(`${slug}LastRotationMonth`);
        if (!localElite && !localMonth) return;
        try {
            if (localElite) {
                const parsed = JSON.parse(localElite);
                if (Array.isArray(parsed) && parsed.length > 0) this.serverElite = parsed;
            }
            if (localMonth) this.serverLastRotationMonth = localMonth;
            if (this.serverElite.length > 0 || this.serverLastRotationMonth) {
                await this.saveEliteToServer();
            }
            localStorage.removeItem(`${slug}Elite`);
            localStorage.removeItem(`${slug}LastRotationMonth`);
        } catch (e) {
            console.warn('Ошибка миграции элиты:', e);
        }
    },

    async verifyRotationPassword(password) {
        return LegionCore.verifyRotationPassword(password);
    },

    formatRotationMonth(ym) {
        if (!ym || !/^\d{4}-\d{2}$/.test(ym)) return null;
        const [year, month] = ym.split('-').map(Number);
        const names = [
            'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
            'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'
        ];
        if (month < 1 || month > 12) return null;
        return `${names[month - 1]} ${year}`;
    },

    formatNextRotationDate(ym) {
        let nextYear;
        let nextMonth;
        if (ym && /^\d{4}-\d{2}$/.test(ym)) {
            const [year, month] = ym.split('-').map(Number);
            nextMonth = month === 12 ? 1 : month + 1;
            nextYear = month === 12 ? year + 1 : year;
        } else {
            const now = new Date();
            nextMonth = now.getMonth() + 2;
            nextYear = now.getFullYear();
            while (nextMonth > 12) {
                nextMonth -= 12;
                nextYear += 1;
            }
        }
        return `1 ${this.getMonthNameGenitive(nextMonth)} ${nextYear}`;
    },

    getMonthNameGenitive(month) {
        const names = [
            'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
        ];
        return names[month - 1] || '';
    },

    updateRotationPanelInfo() {
        const statusEl = document.getElementById('rotation-status');
        if (!statusEl) return;

        try {
            const lastMonth = this.getLastRotationMonth();
            const currentMonth = new Date().toISOString().slice(0, 7);
            const lastLabel = this.formatRotationMonth(lastMonth);
            const nextDate = this.formatNextRotationDate(lastMonth);
            const eliteCount = this.getElite().length;

            let autoLine = `Следующая автоматическая ротация: <strong>${nextDate}</strong> (начало месяца).`;
            if (lastMonth && currentMonth > lastMonth) {
                autoLine = 'Наступил новый месяц — <strong>автоматическая ротация выполняется при загрузке страницы</strong>.';
            }

            statusEl.innerHTML = `
                <p class="rotation-status-line">Последняя ротация: <strong>${lastLabel || 'ещё не проводилась'}</strong></p>
                <p class="rotation-status-line">${autoLine}</p>
                <p class="rotation-status-line">Текущая элита: <strong>${eliteCount}</strong> спортсменов (топ-10 по баллам в группе).</p>
            `;
        } catch (e) {
            console.warn('Ошибка обновления панели ротации:', e);
        }
    },

    getRotationPasswordInput() {
        const input = document.getElementById('rotation-password');
        return input ? String(input.value || '').trim() : '';
    },

    clearRotationPasswordInput() {
        const input = document.getElementById('rotation-password');
        if (input) input.value = '';
    },

    // ---------- Загрузка данных ----------

    filterCoachAthletes(athletesData) {
        const slug = this.coach.slug;
        const name = this.coach.name;
        return athletesData.filter(a => (a.coachSlug === slug || a.coach === name) && !a.isCoach);
    },

    setCoachBenchmarkFromState() {
        this.coachBenchmark = LegionCore.getCoachBenchmark(this.coach.slug);
    },

    async loadRating() {
        if (this.coach.storage === 'mysql') {
            return this.loadRatingFromMysql();
        }
        return this.loadRatingFromSheets();
    },

    applyMysqlDataset(data) {
        const athletes = Array.isArray(data.athletes) ? data.athletes : [];
        const ranks = data.ranks || {};
        LegionCore.state.rankData = { ...ranks };
        athletes.forEach((a) => {
            if (Array.isArray(a.rankMarks)) {
                a.rankMarks = LegionCore.normalizeRankMarksValue(a.rankMarks);
            }
            LegionCore.applyRankData(LegionCore.state.rankData, [a]);
        });
        LegionCore.state.serverHistory = LegionCore.trimHistoryPerAthlete(
            Array.isArray(data.history) ? data.history : []
        );
        LegionCore.state.serverAchievements = data.achievements && typeof data.achievements === 'object'
            ? data.achievements
            : {};
        LegionCore.state.athletesData = athletes;
        LegionCore.calculateAllRatings(athletes);
        LegionCore.state.overallSorted = LegionCore.sortByTotal([...athletes.filter((a) => !a.isCoach)]);

        if (data.coachBenchmark && typeof data.coachBenchmark === 'object' && data.coachBenchmark.name) {
            const slug = this.coach.slug;
            const bench = { ...data.coachBenchmark };
            bench.isCoach = true;
            bench.coachSlug = slug;
            bench.coach = this.coach.name;
            if (Array.isArray(bench.rankMarks)) {
                bench.rankMarks = LegionCore.normalizeRankMarksValue(bench.rankMarks);
                const norm = LegionCore.normalizePersonName(bench.name);
                LegionCore.state.rankData[slug + ':' + norm] = bench.rankMarks;
                if (!LegionCore.state.rankData[norm] || bench.rankMarks.some((m) => m)) {
                    LegionCore.state.rankData[norm] = bench.rankMarks;
                }
            }
            if (!LegionCore.state.coachBenchmarks) {
                LegionCore.state.coachBenchmarks = {};
            }
            LegionCore.state.coachBenchmarks[slug] = bench;
        }

        return athletes;
    },

    async loadRatingFromMysql() {
        LegionCore.onBeforeDataRefresh();
        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;

        try {
            contentDiv.innerHTML = '<p class="note">Загрузка данных с сервера…</p>';
            const slug = this.coach.slug;
            const resp = await fetch(`/api/coach/get_athletes.php?coach=${encodeURIComponent(slug)}`);
            let data = {};
            try {
                data = await resp.json();
            } catch (parseErr) {
                throw new Error('Сервер вернул не JSON — проверьте api/coach/get_athletes.php');
            }
            if (!resp.ok) {
                throw new Error(data.error || `API ошибка ${resp.status}`);
            }

            const athletes = this.applyMysqlDataset(data);
            this.coachAthletes = athletes;
            this.setCoachBenchmarkFromState();
            if (this.coachAthletes.length === 0) {
                throw new Error(
                    `У тренера «${this.coach.name}» нет спортсменов в базе. ` +
                    'Откройте режим тренировки и импортируйте данные из Google Таблиц.'
                );
            }

            LegionCore.calculateAllRatings(this.coachAthletes);
            this.coachSorted = LegionCore.sortByTotal([...this.coachAthletes]);
            this.coachSorted.forEach((a, idx) => { a.coachRank = idx + 1; });

            LegionCore.initExerciseSorted(this.coachAthletes);

            if (this.getElite().length === 0 && this.coachSorted.length > 0) {
                this.serverElite = this.coachSorted.slice(0, 10).map(a => a.name);
            }

            try {
                await LegionCore.refreshClubOverallRanks();
            } catch (rankErr) {
                console.warn('Не удалось загрузить общие места клуба:', rankErr);
            }

            LegionCore.updateAllAchievements();
            this.renderCurrentTab();

            await this.syncBackgroundData(athletes);
        } catch (err) {
            contentDiv.innerHTML = `<p class="error">${err.message || err}</p>`;
            throw err;
        }
    },

    async loadRatingFromSheets() {
        LegionCore.onBeforeDataRefresh();
        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;

        try {
            contentDiv.innerHTML = '<p class="note">Загрузка данных из Google Таблиц…</p>';

            const { athletes: athletesData, rankData } = await LegionCore.loadPageData();

            LegionCore.state.athletesData = athletesData;
            LegionCore.applyRankData(rankData, athletesData);
            LegionCore.calculateAllRatings(athletesData);
            LegionCore.state.overallSorted = LegionCore.sortByTotal([...athletesData]);
            LegionCore.state.overallSorted.forEach((a, idx) => {
                a.pointsRank = idx + 1;
                a.overallRank = idx + 1;
            });
            LegionCore.state.clubOverallRankMap = LegionCore.buildClubOverallRankMap(athletesData);

            this.coachAthletes = this.filterCoachAthletes(athletesData);
            this.setCoachBenchmarkFromState();
            if (this.coachAthletes.length === 0) {
                const coachWarning = (LegionCore.state.loadWarnings || []).find(
                    w => w.slug === this.coach.slug || w.coach === this.coach.name
                );
                if (coachWarning) {
                    throw new Error(`${coachWarning.message} (группа «${this.coach.name}»)`);
                }
                throw new Error(`У тренера «${this.coach.name}» нет спортсменов в базе. Импортируйте группу в режиме тренировки.`);
            }

            LegionCore.calculateAllRatings(this.coachAthletes);
            this.coachSorted = LegionCore.sortByTotal([...this.coachAthletes]);
            this.coachSorted.forEach((a, idx) => { a.coachRank = idx + 1; });

            LegionCore.initExerciseSorted(this.coachAthletes);

            if (this.getElite().length === 0 && this.coachSorted.length > 0) {
                this.serverElite = this.coachSorted.slice(0, 10).map(a => a.name);
            }

            LegionCore.updateAllAchievements();
            this.renderCurrentTab();

            await this.syncBackgroundData(athletesData);
        } catch (err) {
            contentDiv.innerHTML = `<p class="error">${err.message || err}</p>`;
            throw err;
        }
    },

    async syncBackgroundData(athletesData) {
        await Promise.allSettled([
            this.loadEliteFromServer(),
            LegionCore.loadRankHistoryFromServer(),
            LegionCore.loadSnapshotMetaFromServer()
        ]);

        await Promise.allSettled([
            LegionCore.migrateAchievementsFromLocalStorage(),
            this.migrateEliteFromLocalStorage()
        ]);

        if (this.coach.storage === 'mysql') {
            try {
                await LegionCore.refreshClubOverallRanks();
            } catch (rankErr) {
                console.warn('Не удалось обновить общие места клуба:', rankErr);
            }
        }

        if (this.getElite().length === 0 && this.coachSorted.length > 0) {
            this.setElite(this.coachSorted.slice(0, 10).map(a => a.name));
            this.setLastRotationMonth(new Date().toISOString().slice(0, 7));
        } else if (!this.getLastRotationMonth()) {
            this.setLastRotationMonth(new Date().toISOString().slice(0, 7));
        }

        LegionCore.updateAllAchievements();
        this.renderCurrentTab();
        this.updateRotationPanelInfo();
        LegionCore.refreshOpenAthleteModal();

        try {
            await this.checkAutoRotation();
        } catch (e) {
            console.warn('Ошибка автоматической ротации:', e);
        }

        this.updateRotationPanelInfo();
        LegionCore.refreshOpenAthleteModal();
    },

    // ---------- Ротация ----------

    getLeaguesFromData() {
        const eliteNames = this.getElite();
        const allNames = this.coachSorted.map(a => a.name);
        return {
            elite: eliteNames.filter(n => allNames.includes(n)),
            amateur: allNames.filter(n => !eliteNames.includes(n))
        };
    },

    checkAutoRotation() {
        const lastMonth = this.getLastRotationMonth();
        const currentMonth = new Date().toISOString().slice(0, 7);
        if (!lastMonth) {
            return this.setLastRotationMonth(currentMonth);
        }
        if (currentMonth > lastMonth) {
            return this.rotateLeagues(true);
        }
        return Promise.resolve();
    },

    async rotateLeagues(bypassPassword = false) {
        if (!bypassPassword) {
            let password = this.getRotationPasswordInput();
            if (!password) {
                password = prompt('Введите пароль для ручной ротации:');
            }
            if (!password) return;
            const valid = await this.verifyRotationPassword(password);
            if (!valid) {
                alert('Неверный пароль!');
                return;
            }
            this.clearRotationPasswordInput();
        }
        if (this.coachSorted.length === 0) {
            alert('Сначала загрузите рейтинг!');
            return;
        }

        LegionCore.setRotationBusy(true);
        try {
            const prevElite = this.getElite();
            const currentMonth = new Date().toISOString().slice(0, 7);

            if (prevElite.length === 0) {
                const newElite = this.coachSorted.slice(0, 10).map(a => a.name);
                this.setElite(newElite);
                this.setLastRotationMonth(currentMonth);
                this.renderCurrentTab();
                this.updateRotationPanelInfo();
                LegionCore.renderRotationLog([], [], newElite, {
                    out: 'Вылетели из элиты',
                    up: 'Поднялись в элиту',
                    members: 'Новый состав элиты'
                });
                return;
            }

            const { list: finalElite, out, up } = LegionCore.computeEliteRotation(
                prevElite,
                this.coachSorted,
                7,
                10
            );

            this.setElite(finalElite);
            this.setLastRotationMonth(currentMonth);
            this.renderCurrentTab();
            this.updateRotationPanelInfo();
            LegionCore.renderRotationLog(out, up, finalElite, {
                out: 'Вылетели из элиты',
                up: 'Поднялись в элиту',
                members: 'Новый состав элиты'
            });
        } finally {
            LegionCore.setRotationBusy(false);
        }
    },

    // ---------- Отображение ----------

    switchTab(tab) {
        LegionCore.state.currentTab = tab;
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        const tabEl = document.querySelector(`.tab[data-tab="${tab}"]`);
        if (tabEl) tabEl.classList.add('active');
        this.renderCurrentTab();
    },

    renderCurrentTab() {
        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;
        if (!this.coachAthletes || this.coachAthletes.length === 0) {
            contentDiv.innerHTML = '<p class="note">Данные не загружены.</p>';
            return;
        }
        const { elite, amateur } = this.getLeaguesFromData();
        const tab = LegionCore.state.currentTab;
        const filteredCoach = LegionCore.filterBySearch(this.coachSorted);
        let html = '';
        let matchCount = filteredCoach.length;
        switch (tab) {
            case 'overall': {
                const eliteAthletes = filteredCoach.filter(a => elite.includes(a.name));
                const amateurAthletes = filteredCoach.filter(a => amateur.includes(a.name));
                if (LegionCore.state.searchQuery && filteredCoach.length === 0) {
                    html = '<p class="note">Никого не найдено. Попробуйте другое имя.</p>';
                } else {
                    html = this.buildLeagueTable(eliteAthletes, `${LegionConfig.ELITE_ICON} Элита (топ-10)`, elite) +
                        this.buildCoachBenchmarkOverall() +
                        '<br>' +
                        this.buildLeagueTable(amateurAthletes, 'Любители', elite);
                }
                break;
            }
            default: {
                const exCfg = LegionConfig.EXERCISES.find(e => e.tab === tab);
                if (exCfg) {
                    const sorted = LegionCore.state[exCfg.key + 'Sorted'] || [];
                    const filtered = LegionCore.filterBySearch(sorted);
                    matchCount = filtered.length;
                    html = this.buildExerciseTable(filtered, exCfg.key, exCfg.tableTitle, elite);
                }
                break;
            }
        }
        const showPrint = !!(html && !(LegionCore.state.searchQuery && matchCount === 0));
        contentDiv.innerHTML = this.appendPrintFooter(html, showPrint);
        LegionCore.updateSearchStatus(matchCount);
        if (typeof LegionUI !== 'undefined' && LegionUI.applyRatingRowEntrance) {
            LegionUI.applyRatingRowEntrance(contentDiv, tab);
        }
    },

    getAthleteNameDisplay(name, eliteList) {
        let prefix = '';
        if (eliteList.includes(name)) {
            prefix += LegionCore.formatEliteIcon('Элита группы');
        }
        const athlete = this.coachAthletes.find((a) => a.name === name)
            || (LegionCore.state.athletesData || []).find((a) => a.name === name);
        const top25 = athlete && LegionCore.isClubTop25(athlete)
            ? LegionCore.formatTop25Icon()
            : '';
        return `${prefix}${LegionCore.formatAthleteLink(name, athlete && athlete.coachSlug)}${top25}`;
    },

    buildCoachBenchmarkOverall() {
        const a = this.coachBenchmark;
        if (!a) return '';
        const slug = this.coach.slug;
        let html = '<section class="coach-benchmark-section no-print">';
        html += '<h2 class="coach-benchmark-title">🏋️ Результаты тренера</h2>';
        html += '<div class="table-wrap"><table class="rating-table rating-table-coach-benchmark">';
        html += '<tr><th>ФИО</th><th>Ранг</th>';
        LegionConfig.EXERCISES.forEach((ex) => {
            html += `<th>${LegionCore.escapeHtml(ex.label)}</th>`;
        });
        html += '</tr>';
        html += `<tr class="row-coach-benchmark coach-profile-row" role="button" tabindex="0" title="Открыть карточку тренера">
            <td><strong>${LegionCore.escapeHtml(a.name)}</strong></td>
            <td>${LegionCore.formatRankDisplay(a.name, slug)}</td>`;
        LegionConfig.EXERCISES.forEach((ex) => {
            const val = Number(a[ex.key]) || 0;
            html += `<td>${val > 0 ? val : '–'}</td>`;
        });
        html += '</tr>';
        html += '</table></div></section>';
        return html;
    },

    buildLeagueTable(athletes, leagueName, eliteList) {
        if (athletes.length === 0) {
            return LegionCore.state.searchQuery
                ? `<h2>${leagueName}</h2><p class="note">Никого не найдено.</p>`
                : `<h2>${leagueName}</h2><p>Нет участников.</p>`;
        }
        let html = `<h2>${leagueName}</h2><div class="table-wrap"><table class="rating-table">`;
        html += '<tr><th>Место</th><th>ФИО</th><th>Ранг</th><th>Сумма очков</th></tr>';
        athletes.forEach((a, idx) => {
            const rank = a.coachRank || (idx + 1);
            const cellClass = LegionCore.getCellClass(rank);
            html += `<tr>
                <td class="${cellClass}"><strong>${rank}</strong></td>
                <td>${this.getAthleteNameDisplay(a.name, eliteList)}</td>
                <td>${LegionCore.formatRankDisplay(a.name, this.coach.slug)}</td>
                <td class="col-points"><strong>${a.total}</strong></td>
            </tr>`;
        });
        html += '</table></div>';
        return html;
    },

    buildExerciseTable(sortedArray, exKey, exName, eliteList) {
        if (sortedArray.length === 0) {
            return LegionCore.state.searchQuery
                ? `<h2>🏅 Рейтинг: ${exName}</h2><p class="note">Никого не найдено.</p>`
                : `<h2>🏅 Рейтинг: ${exName}</h2><p>Нет результатов.</p>`;
        }
        let html = `<h2>🏅 Рейтинг: ${exName}</h2><div class="table-wrap"><table class="rating-table">`;
        html += '<tr><th>Место</th><th>ФИО</th><th>Результат</th><th>Очки</th></tr>';
        let rank = 1;
        let prevVal = null;
        sortedArray.forEach((a, idx) => {
            if (a[exKey] !== prevVal) rank = idx + 1;
            prevVal = a[exKey];
            const cellClass = LegionCore.getCellClass(rank);
            html += `<tr>
                <td class="${cellClass}"><strong>${rank}</strong></td>
                <td>${this.getAthleteNameDisplay(a.name, eliteList)}</td>
                <td class="col-result">${a[exKey]}</td>
                <td class="col-points">${a[exKey + '_points']}</td>
            </tr>`;
        });
        html += '</table></div>';
        return html;
    },

    appendPrintFooter(html, show) {
        if (!show || !html) return html;
        const coachName = (this.coach && this.coach.name) ? this.coach.name : 'группы';
        const dateStr = new Date().toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        const printMeta = `
            <div class="print-rating-meta">
                <div class="print-rating-meta-top">
                    <img class="print-rating-logo" src="/images/legion-logo.png" alt="Легион Силы" width="56" height="56">
                    <div class="print-rating-meta-text">
                        <h1 class="print-rating-title">Рейтинг — ${LegionCore.escapeHtml(coachName)}</h1>
                        <p class="print-rating-brand">легион-силы.рф</p>
                        <p class="print-rating-date">Дата печати: ${LegionCore.escapeHtml(dateStr)}</p>
                    </div>
                </div>
            </div>`;
        const printSheetFooter = `
            <div class="print-rating-sheet-footer">
                <span class="print-rating-sheet-footer-brand">Легион Силы</span>
                <span class="print-rating-sheet-footer-site">легион-силы.рф</span>
            </div>`;
        return printMeta + html + printSheetFooter
            + '<div class="print-footer"><button class="print-btn no-print" onclick="window.print()">🖨️ Печать</button></div>';
    },

    openAthleteModal(name, coachSlug) {
        const modal = document.getElementById('athleteModal');
        if (!modal) {
            console.error('Окно спортсмена не найдено — залейте modals-coach.php на сервер.');
            return;
        }
        const preferredSlug = coachSlug || (this.coach && this.coach.slug) || '';
        const athlete = (LegionCore.state.athletesData || []).find((a) =>
            a.name === name && (!preferredSlug || a.coachSlug === preferredSlug)
        ) || (LegionCore.state.athletesData || []).find((a) => a.name === name);
        if (!athlete) return;

        LegionCore.setOpenAthlete(name);
        LegionCore.setAthleteModalRanksRowVisible(true);

        const eliteList = this.getElite();
        const isElite = eliteList.includes(name);
        const coachAthlete = this.coachAthletes.find(a => a.name === name);
        const coachRank = coachAthlete ? coachAthlete.coachRank : '?';
        const overallRank = LegionCore.getClubOverallRank(athlete);
        const clubRank = LegionCore.getClubRank(name, athlete.coachSlug);

        LegionUI.applyPhotoFrame(document.getElementById('modal-photo-frame'), clubRank, isElite, false);

        LegionCore.setModalPhoto(athlete);
        document.getElementById('modal-name').innerHTML = isElite
            ? `${LegionCore.formatEliteIcon()}${name}`
            : name;
        LegionCore.fillAthleteModalAge(athlete);
        document.getElementById('modal-league').innerHTML = isElite
            ? `${LegionConfig.ELITE_ICON} Элита`
            : 'Любители';
        document.getElementById('modal-rank-coach').textContent = coachRank;
        document.getElementById('modal-rank-overall').textContent = overallRank;

        LegionCore.fillAthleteModalTable(athlete);

        const rankInfoDiv = document.getElementById('modal-rank-info');
        if (rankInfoDiv) {
            rankInfoDiv.innerHTML = (LegionUI && LegionUI.renderRankSummaryCard)
                ? LegionUI.renderRankSummaryCard(name, clubRank)
                : '';
        }

        LegionCore.fillAthleteModalExtras(name, athlete);
        LegionCore.updateAthleteModalMoreLink(athlete);
        modal.classList.add('active');
    },

    openCoachModal() {
        const modal = document.getElementById('athleteModal');
        const coach = this.coachBenchmark;
        if (!modal || !coach) return;

        const slug = this.coach.slug;
        const name = coach.name;
        const clubRank = LegionCore.getClubRank(name, slug);

        LegionCore.setOpenAthlete(name);
        LegionCore.setAthleteModalRanksRowVisible(false);

        LegionUI.applyPhotoFrame(document.getElementById('modal-photo-frame'), clubRank, false, false);

        LegionCore.setModalPhoto(coach);
        document.getElementById('modal-name').innerHTML = `🏋️ ${LegionCore.escapeHtml(name)}`;
        LegionCore.fillAthleteModalAge(null);
        document.getElementById('modal-league').innerHTML = '<span class="coach-benchmark-note">Тренер · вне зачёта</span>';

        LegionCore.fillAthleteModalTable(coach, { coachOnly: true });

        const rankInfoDiv = document.getElementById('modal-rank-info');
        if (rankInfoDiv) {
            rankInfoDiv.innerHTML = (LegionUI && LegionUI.renderRankSummaryCard)
                ? LegionUI.renderRankSummaryCard(name, clubRank)
                : '<p class="note">Нет данных о ранге.</p>';
        }

        const achEl = document.getElementById('modal-achievements');
        if (achEl) achEl.innerHTML = '';

        LegionCore.fillAthleteModalExtras(name, coach, { showAchievements: false });
        LegionCore.updateAthleteModalMoreLink(null);
        modal.classList.add('active');
    },

    showBootError(message) {
        const content = document.getElementById('content');
        if (content) {
            content.innerHTML = `<p class="error">${message}</p>`;
        }
    },

    boot() {
        if (typeof LegionConfig === 'undefined') {
            this.showBootError('Не загружен legion-config.js. Залейте js/legion-config.js на сервер.');
            return;
        }
        if (typeof LegionCore === 'undefined') {
            this.showBootError('Не загружен legion-core.js. Залейте js/legion-core.js на сервер.');
            return;
        }
        if (typeof LegionUI === 'undefined') {
            this.showBootError('Не загружен legion-ui.js. Залейте js/legion-ui.js на сервер.');
            return;
        }
        try {
            this.init();
        } catch (e) {
            this.showBootError('Ошибка JavaScript: ' + (e.message || e));
        }
    }
};
