/**
 * Главная страница клуба: ТОП-25, Легионеры, общая статистика.
 */
const LegionClubPage = {
    lastResultsScope: 'global',

    init() {
        this.lastResultsScope = (typeof LegionConfig !== 'undefined' && LegionConfig.CLUB_LAST_RESULTS_SCOPE)
            ? LegionConfig.CLUB_LAST_RESULTS_SCOPE
            : 'global';
        LegionCore.bindCommonGlobals();
        this.bindGlobals();
        LegionCore.initPageUI(() => this.renderCurrentTab());
        this.startPage();
    },

    startPage() {
        const contentDiv = document.getElementById('content');
        if (contentDiv) {
            contentDiv.innerHTML = '<p class="note">Загрузка данных из Google Таблиц…</p>';
        }
        this.onLoad().catch((err) => {
            console.error('Ошибка загрузки:', err);
            if (contentDiv) {
                contentDiv.innerHTML = `<p class="error">Не удалось загрузить рейтинг: ${err.message || err}</p>`;
            }
        });
    },

    bindGlobals() {
        const self = this;
        window.switchTab = (tab) => self.switchTab(tab);
        window.openAthleteModal = (name) => self.openAthleteModal(name);
    },

    async onLoad() {
        await this.loadRating();
        this.syncBackgroundData().catch((err) => {
            console.warn('Фоновая синхронизация:', err);
        });
    },

    async syncBackgroundData() {
        await Promise.allSettled([
            LegionCore.loadHistoryFromServer(),
            LegionCore.loadAchievementsFromServer(),
            LegionCore.loadLastResultsFromServer(this.lastResultsScope)
        ]);
        await Promise.allSettled([
            LegionCore.migrateAchievementsFromLocalStorage(),
            LegionCore.migrateLastResultsFromLocalStorage(this.lastResultsScope, 'clubLastResults')
        ]);

        const athletesData = LegionCore.state.athletesData;
        const lastResults = LegionCore.getLastResults();
        if (athletesData.length > 0 && Object.keys(lastResults).length > 0) {
            LegionCore.compareAndRecordHistory(lastResults, athletesData);
        }
        if (athletesData.length > 0) {
            LegionCore.setLastResults(this.lastResultsScope, LegionCore.snapshotCurrentResults(athletesData));
        }

        LegionCore.updateAllAchievements();
        this.updateClubStats();
        this.renderCurrentTab();
        LegionCore.refreshOpenAthleteModal();
    },

    async loadRating() {
        LegionCore.onBeforeDataRefresh();
        const contentDiv = document.getElementById('content');
        if (!contentDiv) return;

        const athletesData = await LegionCore.loadAllAthletes();
        const rankData = await LegionCore.loadRanks();

        LegionCore.state.athletesData = athletesData;
        LegionCore.state.rankData = rankData;

        LegionCore.calculateAllRatings(athletesData);
        LegionCore.state.overallSorted = LegionCore.sortByTotal([...athletesData]);
        LegionCore.state.overallSorted.forEach((a, idx) => {
            a.pointsRank = idx + 1;
            a.overallRank = idx + 1;
        });

        LegionCore.initExerciseSorted(athletesData);

        LegionCore.updateAllAchievements();
        this.updateClubStats();
        this.renderCurrentTab();
        LegionCore.refreshOpenAthleteModal();
    },

    updateClubStats() {
        const el = document.getElementById('club-stats');
        if (!el || LegionCore.state.athletesData.length === 0) return;
        LegionUI.updateClubStats(el, {
            athletes: LegionCore.state.athletesData.length,
            coaches: LegionConfig.getCoaches().length
        });
    },

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
        if (LegionCore.state.athletesData.length === 0) {
            contentDiv.innerHTML = '<p class="note">Данные не загружены.</p>';
            return;
        }
        const tab = LegionCore.state.currentTab;
        if (tab === 'hall') {
            contentDiv.innerHTML = this.buildHallOfFameView();
            LegionCore.updateSearchStatus(0, { hidden: true });
            return;
        }
        const q = LegionCore.state.searchQuery;
        let html = '';
        let matchCount = 0;
        switch (tab) {
            case 'overall':
                matchCount = LegionCore.filterBySearch(LegionCore.state.overallSorted).length;
                html = this.buildCombinedRating();
                break;
            default: {
                const exCfg = LegionConfig.EXERCISES.find(e => e.tab === tab);
                if (exCfg) {
                    const sorted = LegionCore.state[exCfg.key + 'Sorted'] || [];
                    const filtered = LegionCore.filterBySearch(sorted);
                    matchCount = filtered.length;
                    html = this.buildExerciseTable(filtered, exCfg.key, exCfg.tableTitle);
                }
                break;
            }
        }
        if (q && tab !== 'overall' && html && !html.includes('athlete-name') && !html.includes('Никого не найдено')) {
            html = '<p class="note">Никого не найдено. Попробуйте другое имя.</p>';
        }
        contentDiv.innerHTML = html;
        LegionCore.updateSearchStatus(matchCount);
    },

    isClubElite(athlete) {
        const rank = athlete && (athlete.overallRank || athlete.pointsRank);
        return typeof rank === 'number' && rank <= 25;
    },

    getClubEliteRowClass(rank) {
        if (rank <= 3) return 'row-club-elite row-club-elite-top3';
        if (rank <= 10) return 'row-club-elite row-club-elite-mid';
        return 'row-club-elite row-club-elite-base';
    },

    getAthleteNameDisplay(name, isClubElite) {
        const icon = isClubElite ? LegionCore.formatEliteIcon('Элита Легиона Силы') : '';
        return `${icon}${LegionCore.formatAthleteLink(name)}`;
    },

    buildRatingTableRows(athletes, options) {
        const opts = options || {};
        let html = '';
        athletes.forEach((a) => {
            const rank = a.overallRank;
            const cellClass = LegionCore.getCellClass(rank);
            const rowClass = opts.elite ? this.getClubEliteRowClass(rank) : '';
            const rankLabel = rank === 1
                ? `<strong>${rank}</strong><span class="rank-crown" aria-hidden="true">👑</span>`
                : `<strong>${rank}</strong>`;
            html += `<tr class="${rowClass}">
                <td class="${cellClass}">${rankLabel}</td>
                <td>${this.getAthleteNameDisplay(a.name, opts.elite)}</td>
                <td>${LegionCore.formatRankDisplay(a.name)}</td>
                <td class="col-points"><strong>${a.total}</strong></td>
            </tr>`;
        });
        return html;
    },

    buildCombinedRating() {
        const filtered = LegionCore.filterBySearch(LegionCore.state.overallSorted);
        if (filtered.length === 0) {
            return LegionCore.state.searchQuery
                ? '<p class="note">Никого не найдено. Попробуйте другое имя.</p>'
                : '<p>Нет данных.</p>';
        }

        const top25 = filtered.filter(a => a.overallRank <= 25);
        const legioners = filtered.filter(a => a.overallRank > 25);

        let html = `<section class="club-elite-section">
            <div class="club-elite-header">
                <div class="club-elite-header-icon" aria-hidden="true">🏆</div>
                <div class="club-elite-header-text">
                    <h2 class="club-elite-title">ТОП-25 — Элита Легиона Силы</h2>
                </div>
            </div>`;

        html += '<div class="table-wrap club-elite-table-wrap"><table class="rating-table rating-table-elite rating-table-overall">';
        html += '<tr><th>Место</th><th>ФИО</th><th>Ранг</th><th class="col-points">Баллы</th></tr>';
        if (top25.length === 0) {
            html += '<tr><td colspan="4" class="note">В ТОП-25 никого не найдено по запросу.</td></tr>';
        } else {
            html += this.buildRatingTableRows(top25, { elite: true });
        }
        html += '</table></div></section>';

        html += '<section class="legioners-section"><h2 class="legioners-title">📋 Легионеры</h2>';
        if (legioners.length === 0) {
            html += '<p>В этой части рейтинга никого не найдено.</p>';
        } else {
            html += '<div class="table-wrap"><table class="rating-table rating-table-overall">';
            html += '<tr><th>Место</th><th>ФИО</th><th>Ранг</th><th class="col-points">Баллы</th></tr>';
            html += this.buildRatingTableRows(legioners);
            html += '</table></div>';
        }
        html += '</section>';

        html += '<button class="print-btn no-print" onclick="window.print()">🖨️ Печать</button>';
        return html;
    },

    buildExerciseTable(sortedArray, exKey, exName) {
        if (sortedArray.length === 0) {
            return LegionCore.state.searchQuery
                ? `<h2>🏅 Рейтинг: ${exName}</h2><p class="note">Никого не найдено.</p>`
                : `<h2>🏅 Рейтинг: ${exName}</h2><p>Нет результатов.</p>`;
        }
        let html = `<h2>🏅 Рейтинг: ${exName}</h2><div class="table-wrap"><table class="rating-table rating-table-exercise">`;
        html += '<tr><th>Место</th><th>ФИО</th><th>Ранг</th><th>Результат</th><th class="col-points">Очки</th></tr>';
        let rank = 1;
        let prevVal = null;
        sortedArray.forEach((a, idx) => {
            if (a[exKey] !== prevVal) rank = idx + 1;
            prevVal = a[exKey];
            const cellClass = LegionCore.getCellClass(rank);
            html += `<tr>
                <td class="${cellClass}"><strong>${rank}</strong></td>
                <td>${this.getAthleteNameDisplay(a.name, this.isClubElite(a))}</td>
                <td>${LegionCore.formatRankDisplay(a.name)}</td>
                <td>${a[exKey]}</td>
                <td class="col-points">${a[exKey + '_points']}</td>
            </tr>`;
        });
        html += '</table></div>';
        html += '<button class="print-btn no-print" onclick="window.print()">🖨️ Печать</button>';
        return html;
    },

    buildHallOfFameView() {
        const history = LegionCore.getHistory();
        const records = LegionCore.computeExerciseRecords(
            LegionCore.state.athletesData,
            history
        );
        const recentBreaks = LegionCore.computeRecentRecordBreaks(history);
        return LegionUI.renderHallOfFame(records, recentBreaks);
    },

    openAthleteModal(name) {
        const modal = document.getElementById('athleteModal');
        if (!modal) {
            console.error('Окно спортсмена не найдено — залейте modals-club.php на сервер.');
            return;
        }
        const athlete = LegionCore.state.athletesData.find(a => a.name === name);
        if (!athlete) return;

        LegionCore.setOpenAthlete(name);

        const coachGroup = LegionCore.state.athletesData
            .filter(a => a.coach === athlete.coach)
            .sort((a, b) => b.total - a.total);
        const coachRank = coachGroup.findIndex(a => a.name === name) + 1;
        const overallRank = athlete.overallRank || '?';
        const clubRank = LegionCore.getClubRank(name);
        const isClubElite = this.isClubElite(athlete);

        LegionUI.applyPhotoFrame(document.getElementById('modal-photo-frame'), clubRank, false, isClubElite);

        document.getElementById('modal-photo').src = athlete.photo && athlete.photo.startsWith('http')
            ? athlete.photo
            : "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Ccircle cx=%2260%22 cy=%2260%22 r=%2250%22 fill=%22%23222%22/%3E%3Ctext x=%2260%22 y=%2270%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%23888%22%3E👤%3C/text%3E%3C/svg%3E";
        document.getElementById('modal-name').innerHTML = isClubElite
            ? `<span class="modal-name-elite">${LegionCore.formatEliteIcon()}${name}</span>`
            : name;
        document.getElementById('modal-league').innerHTML = isClubElite
            ? `<span class="club-elite-badge">${LegionConfig.ELITE_ICON} Элита Легиона Силы · ТОП-25</span>`
            : '';
        const coachEl = document.getElementById('modal-coach');
        if (coachEl) coachEl.textContent = athlete.coach ? 'Тренер: ' + athlete.coach : '';
        document.getElementById('modal-rank-coach').textContent = coachRank || '?';
        document.getElementById('modal-rank-overall').textContent = overallRank;

        LegionCore.fillAthleteModalTable(athlete);

        const rankInfoDiv = document.getElementById('modal-rank-info');
        if (rankInfoDiv && LegionUI.renderRankSummaryCard) {
            rankInfoDiv.innerHTML = LegionUI.renderRankSummaryCard(name, clubRank);
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
