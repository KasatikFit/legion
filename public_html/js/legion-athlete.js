/**
 * Страница карточки спортсмена: /athlete/?name=...&coach=...
 */
const LegionAthletePage = {
    params: { name: '', coach: '' },

    init() {
        this.parseParams();
        LegionCore.bindCommonGlobals();
        LegionCore.initModalClicks();
        window.openAthleteModal = (name) => this.navigateToAthlete(name);
        this.load().catch((err) => {
            console.error('Ошибка загрузки карточки:', err);
            this.showError('Не удалось загрузить карточку: ' + (err.message || err));
        });
    },

    parseParams() {
        const body = document.body;
        const fromBody = body && body.getAttribute('data-athlete-name');
        const coachFromBody = body && body.getAttribute('data-coach-slug');
        const p = new URLSearchParams(location.search);
        this.params.name = (p.get('name') || fromBody || '').trim();
        this.params.coach = (p.get('coach') || coachFromBody || '').trim();
    },

    showError(message) {
        const root = document.getElementById('athlete-profile-root');
        if (root) root.innerHTML = `<p class="error">${LegionCore.escapeHtml(message)}</p>`;
    },

    navigateToAthlete(name) {
        const athlete = (LegionCore.state.athletesData || []).find((a) => a.name === name);
        const coachSlug = athlete ? athlete.coachSlug : this.params.coach;
        location.href = LegionCore.athleteProfileUrl(name, coachSlug);
    },

    findAthlete(athletes) {
        const target = LegionCore.normalizePersonName(this.params.name);
        if (!target) return null;
        const withCoach = athletes.filter((a) =>
            LegionCore.normalizePersonName(a.name) === target
            && (!this.params.coach || a.coachSlug === this.params.coach)
        );
        if (withCoach.length === 1) return withCoach[0];
        if (withCoach.length > 1) return withCoach[0];
        return athletes.find((a) => LegionCore.normalizePersonName(a.name) === target) || null;
    },

    isClubElite(athlete) {
        return LegionCore.isClubTop25(athlete);
    },

    async load() {
        const root = document.getElementById('athlete-profile-root');
        if (!root) return;
        if (!this.params.name) {
            this.showError('Не указано имя спортсмена.');
            return;
        }

        LegionCore.onBeforeDataRefresh();
        const { athletes, rankData } = await LegionCore.loadPageData();
        LegionCore.state.athletesData = athletes;
        LegionCore.applyRankData(rankData, athletes);
        LegionCore.calculateAllRatings(athletes);
        LegionCore.state.overallSorted = LegionCore.sortByTotal([...athletes]);
        LegionCore.state.overallSorted.forEach((a, idx) => {
            a.pointsRank = idx + 1;
            a.overallRank = idx + 1;
        });
        LegionCore.initExerciseSorted(athletes);
        LegionCore.updateAllAchievements();

        await Promise.allSettled([
            LegionCore.loadRankHistoryFromServer(),
            LegionCore.loadSnapshotMetaFromServer()
        ]);
        LegionCore.updateAllAchievements();

        const athlete = this.findAthlete(athletes);
        if (!athlete || athlete.isCoach) {
            this.showError('Спортсмен не найден в рейтинге.');
            return;
        }

        document.title = `${athlete.name} — Легион Силы`;
        root.innerHTML = this.buildProfileHtml(athlete);
        this.applyPhotoFrame(athlete);
        if (typeof LegionUI !== 'undefined' && LegionUI.applyRatingRowEntrance) {
            LegionUI.applyRatingRowEntrance(root, 'profile', { force: true });
        }
    },

    getCoachRank(name, athlete) {
        const coachGroup = (LegionCore.state.athletesData || [])
            .filter((a) => a.coach === athlete.coach && !a.isCoach)
            .sort((a, b) => b.total - a.total);
        const idx = coachGroup.findIndex((a) => a.name === name);
        return idx >= 0 ? idx + 1 : '?';
    },

    buildResultsTable(athlete) {
        let rows = '';
        LegionConfig.EXERCISES.forEach((ex) => {
            const result = athlete[ex.key];
            const place = athlete[ex.key + '_rank'] || '–';
            const pts = athlete[ex.key + '_points'];
            rows += `<tr>
                <td>${ex.label}</td>
                <td>${result > 0 ? result : '–'}</td>
                <td>${result > 0 ? place : '–'}</td>
                <td>${pts != null ? pts : '–'}</td>
            </tr>`;
        });
        return rows;
    },

    coachLinkHtml(athlete) {
        if (!athlete.coachSlug || !athlete.coach) return '';
        const href = `/${encodeURIComponent(athlete.coachSlug)}/`;
        return `<a href="${LegionCore.escapeHtmlAttr(href)}" class="athlete-profile-coach-link">Тренер: ${LegionCore.escapeHtml(athlete.coach)}</a>`;
    },

    getRankMarks(athlete) {
        let marks = LegionCore.lookupRankMarks(athlete.name, athlete.coachSlug);
        if (!marks && Array.isArray(athlete.rankMarks)) {
            marks = LegionCore.normalizeRankMarksValue(athlete.rankMarks);
        }
        return marks;
    },

    buildRankSectionHtml(athlete, clubRank) {
        const marks = this.getRankMarks(athlete);
        if (typeof LegionUI !== 'undefined' && LegionUI.renderAthleteRankProfile) {
            return LegionUI.renderAthleteRankProfile(clubRank, marks);
        }
        if (!clubRank) {
            return '<p class="rank-summary-empty">Данные о рангах пока не загружены.</p>';
        }
        return LegionUI.renderRankSummaryCard(athlete.name, clubRank);
    },

    buildProfileHtml(athlete) {
        const name = athlete.name;
        const clubRank = LegionCore.getClubRank(name, athlete.coachSlug);
        const coachRank = this.getCoachRank(name, athlete);
        const overallRank = LegionCore.getClubOverallRank(athlete);
        const isElite = this.isClubElite(athlete);
        const photoSrc = LegionCore.getAthletePhotoSrc(athlete.photo);

        const nameHtml = LegionCore.escapeHtml(name);
        const ageLabel = LegionCore.formatAthleteAge(athlete);
        const ageHtml = ageLabel
            ? `<p class="athlete-profile-age">${LegionCore.escapeHtml(ageLabel)}</p>`
            : '';
        const leagueHtml = isElite
            ? '<span class="club-elite-badge">Элита Легиона Силы · ТОП-25</span>'
            : '';

        const rankSection = this.buildRankSectionHtml(athlete, clubRank);
        const achData = LegionCore.getAchievementDisplayData(name);
        const achievements = LegionUI.renderAchievementGrid(achData, { variant: 'full', showHeading: false });
        const achEarned = achData.filter((a) => a.active).length;
        const historySections = LegionCore.renderAthleteHistorySections(name);

        const backHref = athlete.coachSlug
            ? `/${encodeURIComponent(athlete.coachSlug)}/`
            : '/';

        return `
            <a href="${LegionCore.escapeHtmlAttr(backHref)}" class="athlete-profile-back">← Назад к рейтингу</a>
            <article class="athlete-profile-card">
                <header class="athlete-profile-hero">
                    <div id="athlete-profile-photo-frame" class="photo-frame league-none athlete-profile-photo-frame">
                        <img id="athlete-profile-photo" class="athlete-photo athlete-profile-photo" src="${LegionCore.escapeHtmlAttr(photoSrc)}" alt="Фото ${LegionCore.escapeHtmlAttr(name)}">
                    </div>
                    ${ageHtml}
                    <h2 class="athlete-profile-name">${nameHtml}</h2>
                    ${leagueHtml ? `<p class="athlete-profile-league">${leagueHtml}</p>` : ''}
                    <p class="athlete-profile-coach">${this.coachLinkHtml(athlete)}</p>
                    <p class="athlete-profile-ranks">
                        <strong>Место у тренера:</strong> ${coachRank} |
                        <strong>Общее место:</strong> ${overallRank}
                    </p>
                </header>

                <section class="athlete-profile-section">
                    <h3 class="section-title">Результаты</h3>
                    <div class="table-wrap">
                        <table class="rating-table">
                            <thead><tr><th>Упражнение</th><th>Результат</th><th>Место</th><th>Очки</th></tr></thead>
                            <tbody>${this.buildResultsTable(athlete)}</tbody>
                        </table>
                    </div>
                </section>

                <section class="athlete-profile-section athlete-profile-ranks-section">
                    <h3 class="section-title">Ранги и нормативы</h3>
                    ${rankSection}
                </section>

                <section class="athlete-profile-section athlete-profile-achievements-section">
                    <h3 class="section-title">Достижения <span class="ach-count">${achEarned} / ${achData.length}</span></h3>
                    ${achievements}
                </section>

                <section class="athlete-profile-section athlete-profile-log-section">
                    <h3 class="section-title">Прогресс</h3>
                    ${historySections}
                </section>
            </article>`;
    },

    applyPhotoFrame(athlete) {
        const frame = document.getElementById('athlete-profile-photo-frame');
        const img = document.getElementById('athlete-profile-photo');
        if (!frame || !img) return;
        const clubRank = LegionCore.getClubRank(athlete.name, athlete.coachSlug);
        LegionUI.applyPhotoFrame(frame, clubRank, false, this.isClubElite(athlete));
        img.onerror = function () {
            this.onerror = null;
            this.src = LegionCore.getAthletePhotoSrc('');
        };
    }
};
