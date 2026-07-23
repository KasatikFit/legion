/**

 * Публичный рейтинг пилотной группы + модалки с рангами, историей и ачивками.

 */

const LegionPilotRating = {

    athletes: [],

    sorted: [],

    history: [],

    achievements: {},

    coachSlug: 'pilot-demo',



    async boot() {

        LegionCore.state.athletesData = [];

        LegionCore.state.rankData = {};

        LegionCore.state.serverHistory = [];

        LegionCore.state.serverAchievements = {};

        LegionCore.bindCommonGlobals();

        LegionCore.initModalClicks();

        const pilotSlug = this.coachSlug;

        window.openRankModal = (name) => LegionCore.openRankModal(name, pilotSlug);

        await this.load();

        LegionCore.initSearchBar(() => this.render());

        this.render();

    },



    applyMetaFromApi(data) {

        this.history = Array.isArray(data.history) ? data.history : [];

        this.achievements = data.achievements && typeof data.achievements === 'object'

            ? data.achievements

            : {};

        LegionCore.state.serverHistory = this.history;

        LegionCore.state.serverAchievements = this.achievements;

    },



    applyRankState(data) {

        const ranks = data.ranks || {};

        LegionCore.state.rankData = { ...ranks };

        this.athletes.forEach((a) => {

            if (Array.isArray(a.rankMarks)) {

                a.rankMarks = LegionCore.normalizeRankMarksValue(a.rankMarks);

            }

            LegionCore.applyRankData(LegionCore.state.rankData, [a]);

        });

        LegionCore.state.athletesData = this.athletes;

    },



    async load() {

        const resp = await fetch('/api/pilot/get_athletes.php');

        if (!resp.ok) {

            throw new Error(`API ошибка ${resp.status}`);

        }

        const data = await resp.json();

        this.coach = data.coach || {};

        this.updatedAt = data.updatedAt || '';

        this.athletes = Array.isArray(data.athletes) ? data.athletes : [];

        this.applyMetaFromApi(data);

        this.applyRankState(data);



        if (this.athletes.length) {

            LegionCore.calculateAllRatings(this.athletes);

            this.sorted = LegionCore.sortByTotal([...this.athletes]);

            this.sorted.forEach((a, idx) => {

                a.coachRank = idx + 1;

                a.overallRank = idx + 1;

            });

        } else {

            this.sorted = [];

        }

    },



    fillModalExtras(name) {

        const progressEl = document.getElementById('modal-progress');

        if (progressEl) {

            progressEl.innerHTML = LegionCore.renderAthleteProgressBlock(name, this.coachSlug);

        }



        const achEl = document.getElementById('modal-achievements');

        if (achEl && typeof LegionPilotAchievements !== 'undefined') {

            achEl.innerHTML = LegionPilotAchievements.renderGrid(name, this.achievements, this.coachSlug);

        }

    },



    openModal(name) {

        const athlete = this.athletes.find((a) => a.name === name);

        if (!athlete) return;



        const modal = document.getElementById('athleteModal');

        if (!modal) return;



        const clubRank = LegionCore.getClubRank(name, this.coachSlug);

        LegionUI.applyPhotoFrame(

            document.getElementById('modal-photo-frame'),

            clubRank,

            false,

            false

        );



        const modalPhoto = document.getElementById('modal-photo');
        const photoFrame = document.getElementById('modal-photo-frame');
        if (modalPhoto && typeof LegionPilotPhotos !== 'undefined') {
            modalPhoto.classList.remove('is-broken');
            modalPhoto.style.display = '';
            modalPhoto.src = LegionPilotPhotos.withCacheBust(
                LegionPilotPhotos.photoSrc(athlete),
                this.updatedAt
            );
            modalPhoto.onerror = () => LegionPilotPhotos.onImgError(modalPhoto);
            if (photoFrame) {
                let emojiEl = photoFrame.querySelector('.pilot-avatar-emoji');
                if (!emojiEl) {
                    emojiEl = document.createElement('span');
                    emojiEl.className = 'pilot-avatar-emoji pilot-avatar-emoji--modal';
                    emojiEl.setAttribute('aria-hidden', 'true');
                    photoFrame.appendChild(emojiEl);
                }
                emojiEl.textContent = LegionPilotPhotos.emojiFor(athlete);
            }
        } else if (modalPhoto) {
            modalPhoto.src = athlete.photo || '';
        }

        document.getElementById('modal-name').textContent = name;

        LegionCore.fillAthleteModalAge(athlete);

        document.getElementById('modal-league').textContent = athlete.coach || this.coach.name || '';

        document.getElementById('modal-rank-coach').textContent = athlete.coachRank || '—';



        LegionCore.setAthleteModalRanksRowVisible(true);

        LegionCore.fillAthleteModalTable(athlete, { coachOnly: false });



        const rankInfoDiv = document.getElementById('modal-rank-info');

        if (rankInfoDiv && LegionUI.renderRankSummaryCard) {

            rankInfoDiv.innerHTML = LegionUI.renderRankSummaryCard(name, clubRank);

        }



        this.fillModalExtras(name);



        LegionCore.setOpenAthlete(name);

        modal.classList.add('active');

    },



    closeModal(event) {

        if (event && event.target && event.target.id !== 'athleteModal') return;

        const modal = document.getElementById('athleteModal');

        if (modal) modal.classList.remove('active');

        LegionCore.clearOpenAthlete();

    },



    openRankModal(name) {

        LegionCore.openRankModal(name, this.coachSlug);

    },



    closeRankModal(event) {

        LegionCore.closeRankModal(event);

    },



    render() {

        const content = document.getElementById('content');

        const updatedEl = document.getElementById('pilot-updated');

        if (!content) return;



        if (updatedEl) {

            updatedEl.textContent = this.updatedAt ? `Обновлено: ${this.updatedAt}` : '';

        }



        if (!this.sorted.length) {

            content.innerHTML = '<p class="note">В группе пока нет спортсменов.</p>';

            return;

        }



        const labels = {

            overall: 'Общий рейтинг',

            push: 'Отжимания',

            pull: 'Подтягивания',

            hang: 'Вис (сек)',

            burpee: 'Бёрпи',

            crunch: 'Скручивания',

            jump: 'Прыжок (см)',

            hall: '🏆 Зал славы'

        };



        const tab = this.currentTab || 'overall';

        const q = LegionCore.state.searchQuery;

        let matchCount = 0;

        let html = '<div class="tabs no-print">';

        Object.keys(labels).forEach((key) => {

            html += `<div class="tab${tab === key ? ' active' : ''}" data-pilot-tab="${key}">${labels[key]}</div>`;

        });

        html += '</div>';



        if (tab === 'hall') {

            const records = LegionCore.computeExerciseRecords(this.athletes, this.history);

            const recentBreaks = LegionCore.computeRecentRecordBreaks(this.history);
            html += LegionUI.renderHallOfFame(records, recentBreaks);

            content.innerHTML = html;

            LegionCore.updateSearchStatus(0, { hidden: true });

            content.querySelectorAll('[data-pilot-tab]').forEach((el) => {

                el.addEventListener('click', () => {

                    this.currentTab = el.getAttribute('data-pilot-tab');

                    this.render();

                });

            });

            if (typeof LegionPilotMicro !== 'undefined') {

                LegionPilotMicro.applyHallAnimations(content);

            }

            return;

        }



        if (tab === 'overall') {

            const filtered = LegionCore.filterBySearch(this.sorted);

            matchCount = filtered.length;

            if (!filtered.length && q) {

                html += '<p class="note">Никого не найдено. Попробуйте другое имя.</p>';

            } else {

                html += '<div class="table-wrap"><table class="rating-table pilot-rating-table"><thead><tr>';

                html += '<th>Место</th><th>ФИО</th><th>Ранг</th><th class="col-points">Баллы</th></tr></thead><tbody>';

                filtered.forEach((a) => {

                    const rank = a.overallRank || 0;

                    const cellClass = LegionCore.getCellClass(rank);

                    html += `<tr class="pilot-athlete-row" data-athlete="${LegionCore.escapeHtmlAttr(a.name)}">`;

                    html += `<td class="${cellClass}"><strong>${rank}</strong></td>`;

                    html += `<td class="pilot-name-cell">${a.name}</td>`;

                    html += `<td>${LegionCore.formatRankDisplay(a.name, this.coachSlug)}</td>`;

                    html += `<td class="col-points"><strong>${a.total || 0}</strong></td></tr>`;

                });

                html += '</tbody></table></div>';

            }

        } else {

            const sorted = [...this.athletes].filter((a) => a[tab] > 0).sort((a, b) => b[tab] - a[tab]);

            const filtered = LegionCore.filterBySearch(sorted);

            matchCount = filtered.length;

            if (!filtered.length) {

                html += q

                    ? '<p class="note">Никого не найдено. Попробуйте другое имя.</p>'

                    : '<p class="note">Нет результатов по этому упражнению.</p>';

            } else {

                html += '<div class="table-wrap"><table class="rating-table pilot-rating-table"><thead><tr>';

                html += '<th>Место</th><th>ФИО</th><th>Результат</th><th class="col-points">Баллы</th></tr></thead><tbody>';

                filtered.forEach((a) => {

                    const rank = a[tab + '_rank'] || 0;

                    const cellClass = LegionCore.getCellClass(rank);

                    html += `<tr class="pilot-athlete-row" data-athlete="${LegionCore.escapeHtmlAttr(a.name)}">`;

                    html += `<td class="${cellClass}"><strong>${rank}</strong></td>`;

                    html += `<td class="pilot-name-cell">${a.name}</td>`;

                    html += `<td><strong>${a[tab]}</strong></td>`;

                    html += `<td class="col-points"><strong>${a[tab + '_points'] || 0}</strong></td></tr>`;

                });

                html += '</tbody></table></div>';

            }

        }



        html += '<p class="note pilot-tap-hint">Нажмите на строку — карточка, история прогресса и достижения.</p>';

        const athletesForSnap = tab === 'overall'

            ? this.sorted

            : [...this.athletes].filter((a) => a[tab] > 0).sort((a, b) => b[tab] - a[tab]);

        const rankMap = typeof LegionPilotMicro !== 'undefined'

            ? LegionPilotMicro.buildRankMap(athletesForSnap, tab)

            : {};

        let rankUps = (typeof LegionPilotMicro !== 'undefined')

            ? LegionPilotMicro.getRankUps(this.coachSlug, tab, rankMap)

            : [];

        if (!rankUps.length && typeof LegionPilotMicro !== 'undefined') {

            rankUps = LegionPilotMicro.getPreviewRankUps(

                this.coachSlug,

                tab,

                rankMap,

                this.history

            );

        }

        content.innerHTML = html;

        LegionCore.updateSearchStatus(matchCount);



        if (typeof LegionUI !== 'undefined' && LegionUI.applyRatingRowEntrance) {

            LegionUI.applyRatingRowEntrance(content, tab);

        }

        if (typeof LegionPilotMicro !== 'undefined') {

            LegionPilotMicro.applyTableAnimations(content, { rankUps });

            LegionPilotMicro.saveSnapshot(this.coachSlug, tab, rankMap);

        }



        content.querySelectorAll('[data-pilot-tab]').forEach((el) => {

            el.addEventListener('click', () => {

                this.currentTab = el.getAttribute('data-pilot-tab');

                this.render();

            });

        });



        content.querySelectorAll('.pilot-athlete-row').forEach((row) => {

            row.addEventListener('click', () => {

                const name = row.getAttribute('data-athlete');

                if (name) this.openModal(name);

            });

        });

    }

};



document.addEventListener('DOMContentLoaded', () => {

    if (typeof LegionConfig === 'undefined' || typeof LegionCore === 'undefined' || typeof LegionUI === 'undefined') {

        const c = document.getElementById('content');

        if (c) c.innerHTML = '<p class="error">Не загружены скрипты рейтинга (config, core, ui).</p>';

        return;

    }

    LegionPilotRating.boot().catch((err) => {

        const c = document.getElementById('content');

        if (c) c.innerHTML = `<p class="error">${err.message || err}</p>`;

    });

});


