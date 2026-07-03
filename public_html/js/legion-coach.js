/**
 * Страница тренера: элита, ротация, рейтинг группы.
 * Инициализация: <body data-legion-page="coach" data-coach-slug="yakutin">
 */
const LegionCoachPage = {
    coach: null,
    coachAthletes: [],
    coachSorted: [],
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
        this.lastResultsScope = this.coach.slug;
        LegionCore.bindCommonGlobals();
        this.bindGlobals();
        LegionCore.initPageUI(() => this.renderCurrentTab());
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
        if (contentDiv) {
            contentDiv.innerHTML = '<p class="note">Загрузка данных из Google Таблиц…</p>';
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
        window.openAthleteModal = (name) => self.openAthleteModal(name);
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
    },

    getLastRotationMonth() {
        return this.serverLastRotationMonth;
    },

    setLastRotationMonth(month) {
        this.serverLastRotationMonth = month;
        this.saveEliteToServer();
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

    // ---------- Загрузка данных ----------

    filterCoachAthletes(athletesData) {
        const slug = this.coach.slug;
        const name = this.coach.name;
        return athletesData.filter(a => a.coachSlug === slug || a.coach === name);
    },

    async loadRating() {
        LegionCore.onBeforeDataRefresh();
        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;

        try {
            contentDiv.innerHTML = '<p class="note">Загрузка данных из Google Таблиц…</p>';

            const [athletesData, rankData] = await Promise.all([
                LegionCore.loadAllAthletes(),
                LegionCore.loadRanks()
            ]);

            LegionCore.state.rankData = rankData;
            LegionCore.state.athletesData = athletesData;
            LegionCore.calculateAllRatings(athletesData);
            LegionCore.state.overallSorted = LegionCore.sortByTotal([...athletesData]);
            LegionCore.state.overallSorted.forEach((a, idx) => {
                a.pointsRank = idx + 1;
                a.overallRank = idx + 1;
            });

            this.coachAthletes = this.filterCoachAthletes(athletesData);
            if (this.coachAthletes.length === 0) {
                throw new Error(`У тренера «${this.coach.name}» нет спортсменов в таблицах. Проверьте api/coaches.php и Google Таблицу.`);
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

            this.syncBackgroundData(athletesData).catch((e) => {
                console.warn('Фоновая синхронизация:', e);
            });
        } catch (err) {
            contentDiv.innerHTML = `<p class="error">${err.message || err}</p>`;
            throw err;
        }
    },

    async syncBackgroundData(athletesData) {
        await Promise.allSettled([
            this.loadEliteFromServer(),
            LegionCore.loadLastResultsFromServer(this.lastResultsScope),
            LegionCore.loadHistoryFromServer(),
            LegionCore.loadAchievementsFromServer()
        ]);

        await Promise.allSettled([
            LegionCore.migrateAchievementsFromLocalStorage(),
            this.migrateEliteFromLocalStorage(),
            LegionCore.migrateLastResultsFromLocalStorage(this.lastResultsScope, `${this.coach.slug}LastResults`)
        ]);

        const lastResults = LegionCore.getLastResults();
        if (Object.keys(lastResults).length > 0) {
            LegionCore.compareAndRecordHistory(lastResults, athletesData);
        }
        LegionCore.setLastResults(this.lastResultsScope, LegionCore.snapshotCurrentResults(athletesData));

        if (this.getElite().length === 0 && this.coachSorted.length > 0) {
            this.setElite(this.coachSorted.slice(0, 10).map(a => a.name));
            this.setLastRotationMonth(new Date().toISOString().slice(0, 7));
        } else if (!this.getLastRotationMonth()) {
            this.setLastRotationMonth(new Date().toISOString().slice(0, 7));
        }

        LegionCore.updateAllAchievements();
        this.renderCurrentTab();

        try {
            await this.checkAutoRotation();
        } catch (e) {
            console.warn('Ошибка автоматической ротации:', e);
        }

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
            const password = prompt('Введите пароль для ручной ротации:');
            if (!password) return;
            const valid = await this.verifyRotationPassword(password);
            if (!valid) {
                alert('Неверный пароль!');
                return;
            }
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
        const tabEl = document.querySelector(`.tab[onclick="switchTab('${tab}')"]`);
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
                    html = this.buildLeagueTable(eliteAthletes, `${LegionConfig.ELITE_ICON} Элита (топ-10)`, elite) + '<br>' +
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
        contentDiv.innerHTML = html;
        LegionCore.updateSearchStatus(matchCount);
    },

    getAthleteNameDisplay(name, eliteList) {
        const isElite = eliteList.includes(name);
        const icon = isElite ? LegionCore.formatEliteIcon('Элита группы') : '';
        return `${icon}${LegionCore.formatAthleteLink(name)}`;
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
                <td>${LegionCore.formatRankDisplay(a.name)}</td>
                <td><strong>${a.total}</strong></td>
            </tr>`;
        });
        html += '</table></div>';
        html += '<button class="print-btn no-print" onclick="window.print()">🖨️ Печать</button>';
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
                <td>${a[exKey]}</td>
                <td>${a[exKey + '_points']}</td>
            </tr>`;
        });
        html += '</table></div>';
        html += '<button class="print-btn no-print" onclick="window.print()">🖨️ Печать</button>';
        return html;
    },

    openAthleteModal(name) {
        const modal = document.getElementById('athleteModal');
        if (!modal) {
            console.error('Окно спортсмена не найдено — залейте modals-coach.php на сервер.');
            return;
        }
        const athlete = LegionCore.state.athletesData.find(a => a.name === name);
        if (!athlete) return;

        LegionCore.setOpenAthlete(name);

        const eliteList = this.getElite();
        const isElite = eliteList.includes(name);
        const coachAthlete = this.coachAthletes.find(a => a.name === name);
        const coachRank = coachAthlete ? coachAthlete.coachRank : '?';
        const overallRank = athlete.overallRank || '?';
        const clubRank = LegionCore.getClubRank(name);

        LegionUI.applyPhotoFrame(document.getElementById('modal-photo-frame'), clubRank, isElite, false);

        document.getElementById('modal-photo').src = athlete.photo && athlete.photo.startsWith('http')
            ? athlete.photo
            : "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2250%22 fill=%22%23222%22/%3E%3Ctext x=%2260%22 y=%2270%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%23888%22%3E👤%3C/text%3E%3C/svg%3E";
        document.getElementById('modal-name').innerHTML = isElite
            ? `${LegionCore.formatEliteIcon()}${name}`
            : name;
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

        LegionCore.fillAthleteModalExtras(name, athlete, { showProgress: false });
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
