/**
 * Страница карточки спортсмена:
 * /athlete/?id=42&coach=...  (стабильная ссылка)
 * /athlete/?name=...&coach=...  (старый формат)
 */
const LegionAthletePage = {
    params: { id: 0, name: '', coach: '' },

    init() {
        this.parseParams();
        LegionCore.bindCommonGlobals();
        LegionCore.initModalClicks();
        window.openAthleteModal = (name, coachSlug) => this.navigateToAthlete(name, coachSlug);
        this.load().catch((err) => {
            console.error('Ошибка загрузки карточки:', err);
            this.showError('Не удалось загрузить карточку: ' + (err.message || err));
        });
    },

    parseParams() {
        const body = document.body;
        const fromBody = body && body.getAttribute('data-athlete-name');
        const idFromBody = body && body.getAttribute('data-athlete-id');
        const coachFromBody = body && body.getAttribute('data-coach-slug');
        const p = new URLSearchParams(location.search);
        const idRaw = p.get('id') || p.get('athleteId') || idFromBody || '';
        this.params.id = Number(idRaw) > 0 ? Number(idRaw) : 0;
        this.params.name = (p.get('name') || fromBody || '').trim();
        this.params.coach = (p.get('coach') || coachFromBody || '').trim();
    },

    showError(message) {
        const root = document.getElementById('athlete-profile-root');
        if (root) root.innerHTML = `<p class="error">${LegionCore.escapeHtml(message)}</p>`;
    },

    navigateToAthlete(name, coachSlug) {
        const preferredSlug = coachSlug || this.params.coach;
        const athlete = (LegionCore.state.athletesData || []).find((a) =>
            a.name === name && (!preferredSlug || a.coachSlug === preferredSlug)
        ) || (LegionCore.state.athletesData || []).find((a) => a.name === name);
        const slug = (athlete && athlete.coachSlug) || preferredSlug;
        location.href = LegionCore.athleteProfileUrl(athlete || name, slug);
    },

    sameAthlete(a, b) {
        return LegionCore.athletesMatch(a, b);
    },

    findAthlete(athletes) {
        if (this.params.id > 0) {
            const byId = athletes.filter((a) => Number(a.id || 0) === this.params.id);
            if (byId.length === 1) return byId[0];
            if (byId.length > 1 && this.params.coach) {
                const withCoach = byId.find((a) => a.coachSlug === this.params.coach);
                if (withCoach) return withCoach;
            }
            if (byId.length > 0) return byId[0];
        }

        const target = LegionCore.normalizePersonName(this.params.name);
        if (!target) return null;
        const withCoach = athletes.filter((a) =>
            LegionCore.normalizePersonName(a.name) === target
            && (!this.params.coach || a.coachSlug === this.params.coach)
        );
        if (withCoach.length >= 1) return withCoach[0];
        return athletes.find((a) => LegionCore.normalizePersonName(a.name) === target) || null;
    },

    isClubElite(athlete) {
        return LegionCore.isClubTop25(athlete);
    },

    async load() {
        const root = document.getElementById('athlete-profile-root');
        if (!root) return;
        if (!this.params.id && !this.params.name) {
            this.showError('Не указан спортсмен.');
            return;
        }

        LegionCore.onBeforeDataRefresh();
        const { athletes, rankData } = await LegionCore.loadPageData();
        LegionCore.state.athletesData = athletes;
        LegionCore.applyRankData(rankData, athletes);
        LegionCore.calculateAllRatings(athletes);
        const overallMap = LegionCore.buildClubOverallRankMap(athletes);
        LegionCore.state.clubOverallRankMap = overallMap;
        LegionCore.applyClubOverallRanks(athletes, overallMap);
        LegionCore.state.overallSorted = LegionCore.sortByTotal(athletes.filter((a) => !a.isCoach));
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

    getCoachGroupSorted(athlete) {
        return LegionCore.sortByTotal(
            (LegionCore.state.athletesData || []).filter((a) =>
                a.coach === athlete.coach && !a.isCoach
            )
        );
    },

    getCoachRank(athlete) {
        const coachGroup = this.getCoachGroupSorted(athlete);
        const idx = coachGroup.findIndex((a) => this.sameAthlete(a, athlete));
        return idx >= 0 ? idx + 1 : '?';
    },

    buildGoalHtml(athlete) {
        return LegionCore.buildAthleteGoalHtml(athlete);
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
        const coachRank = this.getCoachRank(athlete);
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
        const achData = LegionCore.getAchievementDisplayData(name, athlete.coachSlug);
        const achievements = LegionUI.renderAchievementGrid(achData, { variant: 'showcase', showHeading: false });
        const achEarned = achData.filter((a) => a.active).length;
        const historySections = LegionCore.renderAthleteHistorySections(name, athlete.coachSlug);
        const goalHtml = this.buildGoalHtml(athlete);

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
                    ${goalHtml}
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

                <section class="athlete-profile-section athlete-profile-path-section">
                    <h3 class="section-title">Карта легионера</h3>
                    ${LegionUI.renderLegionPathMap(clubRank)}
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
