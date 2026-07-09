/**
 * Режим тренировки — ввод результатов и отметки рангов на зале.
 */
const LegionPilotTraining = {
    athletes: [],
    rankData: {},
    history: [],
    achievements: {},
    baseline: {},
    currentExercise: 'pull',
    viewMode: 'results',
    /** 'auto' — общий список; 3|2|1 — только спортсмены в этой лиге */
    rankViewLeague: 'auto',
    historyAthlete: '',
    focusIdx: null,
    authenticated: false,
    shellReady: false,
    importPresets: [],
    coachProfile: null,
    coachRankLeague: 'auto',

    exercises: [
        { key: 'push', label: 'Отжимания' },
        { key: 'pull', label: 'Подтягивания' },
        { key: 'hang', label: 'Вис (сек)' },
        { key: 'burpee', label: 'Бёрпи' },
        { key: 'crunch', label: 'Скручивания' },
        { key: 'jump', label: 'Прыжок (см)' }
    ],

    rankLeagues: [
        { id: 3, label: 'Бронза' },
        { id: 2, label: 'Серебро' },
        { id: 1, label: 'Золото' }
    ],

    get coachSlug() {
        return (typeof window !== 'undefined' && window.__legionCoachSlug)
            ? window.__legionCoachSlug
            : 'pilot-demo';
    },

    apiCoachQuery() {
        return `coach=${encodeURIComponent(this.coachSlug)}`;
    },

    apiCoachBody(payload) {
        return Object.assign({ coach: this.coachSlug }, payload || {});
    },

    async boot() {
        this.bindLoginForm();
        try {
            const session = await this.checkSession();
            if (!session.authenticated) {
                if (!session.authConfigured) {
                    this.showAuthSetupError();
                }
                return;
            }
            this.authenticated = true;
            await this.loadAthletes();
            this.render();
        } catch (err) {
            this.showBootError(err);
        }
    },

    showAuthSetupError() {
        const root = document.getElementById('pilot-training-root');
        if (!root) return;
        root.innerHTML = `
            <div class="pilot-login-card">
                <h2>Нужна настройка на сервере</h2>
                <p class="error">Создайте файл <code>api/coach_auth.php</code> (скопируйте из <code>coach_auth.example.php</code>) и задайте пароль для группы <code>${this.coachSlug}</code>.</p>
            </div>`;
        this.shellReady = false;
    },

    showBootError(err) {
        const root = document.getElementById('pilot-training-root');
        if (!root) return;
        root.innerHTML = `<p class="error">${err.message || err}</p>`;
        this.shellReady = false;
    },

    async checkSession() {
        const resp = await fetch(`/api/coach/session.php?${this.apiCoachQuery()}`, { credentials: 'same-origin' });
        if (!resp.ok) {
            return { authenticated: false, authConfigured: window.__pilotAuthConfigured !== false };
        }
        const data = await resp.json();
        return {
            authenticated: !!data.authenticated,
            authConfigured: data.authConfigured !== false
        };
    },

    bindLoginForm() {
        const form = document.getElementById('pilot-login-form');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('pilot-login-password').value;
            const errEl = document.getElementById('pilot-login-error');
            if (errEl) errEl.style.display = 'none';
            try {
                const resp = await fetch('/api/coach/login.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.apiCoachBody({ password }))
                });
                let data = {};
                try {
                    data = await resp.json();
                } catch (parseErr) {
                    throw new Error('Сервер вернул не JSON — проверьте api/coach/login.php');
                }
                if (!resp.ok) {
                    throw new Error(data.error || 'Неверный пароль');
                }
                this.authenticated = true;
                await this.loadAthletes();
                this.shellReady = false;
                this.render();
            } catch (err) {
                if (errEl) {
                    errEl.textContent = err.message || 'Ошибка входа';
                    errEl.style.display = 'block';
                }
            }
        });
    },

    showLogin() {
        const root = document.getElementById('pilot-training-root');
        if (!root) return;
        const staticLogin = document.getElementById('pilot-login-static');
        if (staticLogin) {
            this.bindLoginForm();
            return;
        }
        root.innerHTML = `
            <div class="pilot-login-card">
                <h2>Вход тренера</h2>
                <p>Пилотная группа — режим тренировки</p>
                <form id="pilot-login-form">
                    <input type="password" id="pilot-login-password" placeholder="Пароль" autocomplete="current-password" required>
                    <button type="submit" class="pilot-btn pilot-btn--primary" style="width:100%">Войти</button>
                </form>
                <p id="pilot-login-error" class="error" style="margin-top:12px;display:none"></p>
            </div>`;
        this.shellReady = false;
        this.bindLoginForm();
    },

    applyMetaFromApi(data) {
        this.history = Array.isArray(data.history) ? data.history : [];
        this.achievements = data.achievements && typeof data.achievements === 'object'
            ? data.achievements
            : {};
        if (typeof LegionCore !== 'undefined') {
            LegionCore.state.serverHistory = this.history;
            LegionCore.state.serverAchievements = this.achievements;
        }
    },

    applyRankState(data) {
        this.rankData = data.ranks || {};
        if (typeof LegionCore !== 'undefined') {
            LegionCore.state.rankData = { ...this.rankData };
        }
        this.athletes.forEach((a) => {
            if (Array.isArray(a.rankMarks) && typeof LegionCore !== 'undefined') {
                a.rankMarks = LegionCore.normalizeRankMarksValue(a.rankMarks);
                LegionCore.applyRankData(this.rankData, [a]);
            }
        });
        this.applyCoachProfileFromApi(data.coachBenchmark, data.ranks);
    },

    applyCoachProfileFromApi(benchmark, ranks) {
        if (!benchmark || typeof benchmark !== 'object' || !benchmark.name) {
            this.coachProfile = null;
            return;
        }
        this.coachProfile = { ...benchmark };
        if (Array.isArray(this.coachProfile.rankMarks) && typeof LegionCore !== 'undefined') {
            this.coachProfile.rankMarks = LegionCore.normalizeRankMarksValue(this.coachProfile.rankMarks);
            if (ranks && typeof ranks === 'object') {
                this.rankData = { ...this.rankData, ...ranks };
            }
            LegionCore.applyRankData(this.rankData, [this.coachProfile]);
            const norm = LegionCore.normalizePersonName(this.coachProfile.name);
            this.rankData[`${this.coachSlug}:${norm}`] = this.coachProfile.rankMarks;
            this.rankData[norm] = this.coachProfile.rankMarks;
            if (typeof LegionCore !== 'undefined') {
                LegionCore.state.rankData = { ...this.rankData };
            }
        }
    },

    async loadAthletes() {
        const resp = await fetch(`/api/coach/get_athletes.php?${this.apiCoachQuery()}`, { credentials: 'same-origin' });
        let data = {};
        try {
            data = await resp.json();
        } catch (parseErr) {
            throw new Error('Сервер вернул не JSON при загрузке группы');
        }
        if (!resp.ok) {
            throw new Error(data.error || 'Не удалось загрузить группу');
        }
        this.athletes = Array.isArray(data.athletes) ? data.athletes : [];
        this.updatedAt = data.updatedAt || '';
        this.applyMetaFromApi(data);
        this.applyRankState(data);
        this.baseline = {};
        this.athletes.forEach((a) => {
            this.baseline[a.name] = {};
            this.exercises.forEach((ex) => {
                this.baseline[a.name][ex.key] = Number(a[ex.key]) || 0;
            });
        });
    },

    setStatus(text, kind) {
        const el = document.getElementById('pilot-save-status');
        if (!el) return;
        el.textContent = text;
        el.className = 'pilot-training-status' + (kind ? ` is-${kind}` : '');
    },

    flushResultInput(input) {
        if (!input) return Promise.resolve();
        const name = input.getAttribute('data-name');
        const exercise = input.getAttribute('data-exercise');
        return Promise.resolve(this.saveResult(name, exercise, input.value, input));
    },

    async saveResult(name, exercise, value, inputEl) {
        const raw = String(value).trim().replace(',', '.');
        if (raw === '') {
            if (inputEl && inputEl.dataset.saved !== undefined) {
                const saved = parseFloat(inputEl.dataset.saved);
                inputEl.value = !Number.isNaN(saved) && saved > 0 ? String(saved) : '';
            }
            return;
        }

        const num = parseFloat(raw);
        if (Number.isNaN(num) || num < 0) {
            this.setStatus('Некорректное значение', 'error');
            return;
        }

        if (inputEl && inputEl.dataset.saved !== undefined) {
            const savedNum = parseFloat(inputEl.dataset.saved);
            if (!Number.isNaN(savedNum) && savedNum === num) {
                return;
            }
        }

        this.setStatus('Сохранение…', 'saving');
        try {
            const resp = await fetch('/api/coach/update_result.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ name, exercise, value: num }))
            });
            const data = await resp.json();
            if (!resp.ok) {
                throw new Error(data.error || 'Ошибка сохранения');
            }
            const athlete = this.athletes.find((a) => a.name === name);
            if (athlete) athlete[exercise] = num;
            if (Array.isArray(data.history)) {
                this.history = data.history;
                if (typeof LegionCore !== 'undefined') {
                    LegionCore.state.serverHistory = this.history;
                }
            }
            if (data.achievements) {
                this.achievements = data.achievements;
                if (typeof LegionCore !== 'undefined') {
                    LegionCore.state.serverAchievements = this.achievements;
                }
            }
            if (inputEl) {
                inputEl.dataset.saved = String(num);
            }
            if (data.updatedAt) {
                this.updatedAt = data.updatedAt;
                const upd = document.getElementById('pilot-training-updated');
                if (upd) upd.textContent = 'Обновлено: ' + data.updatedAt;
            }
            this.setStatus(`Сохранено ✓ ${data.updatedAt || ''}`, 'ok');
        } catch (err) {
            this.setStatus(err.message || 'Ошибка', 'error');
        }
    },

    rankMarkIndex(league, slot) {
        const offset = league === 3 ? 0 : (league === 2 ? 20 : 40);
        return offset + slot;
    },

    getLeagueColumnsMeta(league) {
        if (typeof LegionCore === 'undefined') {
            return Array.from({ length: 20 }, (_, i) => ({
                slot: i,
                rankName: `Звание ${i + 1}`,
                exercise: `Упражнение ${i + 1}`,
                description: ''
            }));
        }
        const list = LegionCore.getLeagueExerciseList(league);
        return Array.from({ length: 20 }, (_, i) => ({
            slot: i,
            rankName: list.names[i] || `Звание ${i + 1}`,
            exercise: list.exercises[i] || '',
            description: list.descriptions[i] || ''
        }));
    },

    getAthleteClubRank(name) {
        if (typeof LegionCore === 'undefined') return null;
        return LegionCore.getClubRank(name, this.coachSlug);
    },

    getAthleteActiveLeague(name) {
        const rank = this.getAthleteClubRank(name);
        return rank ? rank.league : 3;
    },

    athletesForRankView() {
        return this.filterAthletes(this.athletesByLeague());
    },

    athletesByLeague() {
        if (this.rankViewLeague === 'auto') {
            return this.athletes;
        }
        return this.athletes.filter(
            (a) => this.getAthleteActiveLeague(a.name) === this.rankViewLeague
        );
    },

    filterAthletes(list) {
        if (typeof LegionCore === 'undefined' || !LegionCore.filterBySearch) {
            return list;
        }
        return LegionCore.filterBySearch(list);
    },

    updatePilotSearchStatus() {
        if (typeof LegionCore === 'undefined') return;
        if (this.viewMode === 'import' || this.viewMode === 'history') {
            LegionCore.updateSearchStatus(0, { hidden: true });
            return;
        }
        let list = this.athletes;
        if (this.viewMode === 'ranks') {
            list = this.athletesByLeague();
        }
        LegionCore.updateSearchStatus(this.filterAthletes(list).length);
    },

    shortExerciseLabel(exercise, maxLen) {
        const limit = maxLen || 12;
        const s = String(exercise || '').trim();
        if (!s) return '—';
        if (s.length <= limit) return s;
        return s.slice(0, limit - 1) + '…';
    },

    emptyRankMessage() {
        if (this.rankViewLeague === 3) {
            return 'В бронзовой лиге никого нет — все спортсмены уже перешли в серебро или золото.';
        }
        if (this.rankViewLeague === 2) {
            return 'В серебряной лиге никого нет — все ещё на бронзе или уже на золоте.';
        }
        if (this.rankViewLeague === 1) {
            return 'В золотой лиге никого нет — спортсмены ещё не дошли до этой лиги.';
        }
        return 'Нет спортсменов в списке.';
    },

    getLeagueForAthleteRow(name) {
        if (this.rankViewLeague !== 'auto') {
            return this.rankViewLeague;
        }
        return this.getAthleteActiveLeague(name);
    },

    countLeagueDone(name, league) {
        let n = 0;
        for (let slot = 0; slot < 20; slot++) {
            if (this.isRankMarkDone(name, league, slot)) n++;
        }
        return n;
    },

    leagueMeta(leagueId) {
        return this.rankLeagues.find((l) => l.id === leagueId) || { id: leagueId, label: 'Лига' };
    },

    displaySurname(name) {
        const parts = String(name).trim().split(/\s+/);
        return parts[0] || name;
    },

    isRankMarkDone(name, league, slot) {
        let marks = null;
        const athlete = this.athletes.find((a) => a.name === name);
        if (athlete && Array.isArray(athlete.rankMarks)) {
            marks = athlete.rankMarks;
        } else if (
            this.coachProfile
            && this.coachProfile.name === name
            && Array.isArray(this.coachProfile.rankMarks)
        ) {
            marks = this.coachProfile.rankMarks;
        }
        if (!marks) return false;
        const idx = this.rankMarkIndex(league, slot);
        return !!marks[idx];
    },

    formatAthleteRank(name) {
        if (typeof LegionCore === 'undefined') return '—';
        const rank = LegionCore.getClubRank(name, 'pilot-demo');
        if (!rank) return '—';
        return LegionCore.formatRankDisplay(name, this.coachSlug);
    },

    async toggleRankMark(name, league, slot, checked, inputEl) {
        const idx = this.rankMarkIndex(league, slot);
        const athlete = this.athletes.find((a) => a.name === name);
        if (!athlete) return;

        const next = checked ? 1 : 0;
        const leagueBefore = this.getAthleteActiveLeague(name);

        if (inputEl) inputEl.disabled = true;
        this.setStatus('Сохранение…', 'saving');

        try {
            const resp = await fetch('/api/coach/update_rank_mark.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ name, markIndex: idx, value: next }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');

            if (!Array.isArray(athlete.rankMarks)) {
                athlete.rankMarks = new Array(60).fill(0);
            }
            if (Array.isArray(data.rankMarks) && data.rankMarks.length >= 60) {
                athlete.rankMarks = LegionCore.normalizeRankMarksValue(data.rankMarks);
            } else {
                athlete.rankMarks[idx] = next;
            }

            const norm = typeof LegionCore !== 'undefined'
                ? LegionCore.normalizePersonName(name)
                : name;
            this.rankData[`${this.coachSlug}:${norm}`] = athlete.rankMarks;
            this.rankData[norm] = athlete.rankMarks;
            if (typeof LegionCore !== 'undefined') {
                LegionCore.state.rankData = { ...this.rankData };
                LegionCore.applyRankData(this.rankData, [athlete]);
            }

            const leagueAfter = this.getAthleteActiveLeague(name);
            const leagueChanged = leagueBefore !== leagueAfter;
            const leftLeagueTab = this.rankViewLeague !== 'auto'
                && leagueAfter !== this.rankViewLeague;
            const rolledBack = leagueChanged && leagueAfter > leagueBefore;

            if (rolledBack) {
                this.setStatus(
                    `Возврат в «${this.leagueMeta(leagueAfter).label}»`,
                    'ok'
                );
            } else if (leagueChanged || leftLeagueTab) {
                this.setStatus(
                    `Открыта лига «${this.leagueMeta(leagueAfter).label}» ✓`,
                    'ok'
                );
            } else {
                this.setStatus('Сохранено ✓', 'ok');
            }
            this.updateRanksView();

            if (data.updatedAt) {
                this.updatedAt = data.updatedAt;
                const upd = document.getElementById('pilot-training-updated');
                if (upd) upd.textContent = 'Обновлено: ' + data.updatedAt;
            }
        } catch (err) {
            if (inputEl) {
                inputEl.checked = !checked;
                inputEl.disabled = false;
            }
            this.setStatus(err.message || 'Ошибка', 'error');
        }
    },

    render() {
        const root = document.getElementById('pilot-training-root');
        if (!root) return;

        if (!this.shellReady) {
            this.renderShell(root);
            this.shellReady = true;
        }

        this.updateViewMode();
        this.restoreFocus();
    },

    renderShell(root) {
        root.innerHTML = `<div class="pilot-training">
            <div class="pilot-training-header">
                <div>
                    <span class="pilot-badge">Режим тренировки</span>
                    <h2 id="pilot-training-title" style="margin:4px 0 0"></h2>
                </div>
                <div class="pilot-training-actions">
                    <a href="/${this.coachSlug}/" class="pilot-btn pilot-btn--ghost">К рейтингу</a>
                    <button type="button" class="pilot-btn" id="pilot-logout-btn">Выйти</button>
                </div>
            </div>
            <p class="pilot-updated" id="pilot-training-updated"></p>
            <div class="pilot-search-wrap no-print" id="pilot-search-wrap">
                <div class="search-bar" role="search">
                    <div class="search-bar-inner">
                        <span class="search-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                                <path d="M20 20L16.5 16.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <input type="search" id="athlete-search" class="search-input"
                            placeholder="Найти спортсмена по фамилии или имени…"
                            autocomplete="off" enterkeyhint="search" aria-describedby="athlete-search-status">
                        <button type="button" class="search-clear" id="athlete-search-clear" aria-label="Очистить поиск" hidden>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <p class="search-status" id="athlete-search-status" aria-live="polite"></p>
                </div>
            </div>
            <div class="pilot-mode-tabs" id="pilot-mode-tabs"></div>
            <div id="pilot-results-panel">
                <div class="pilot-exercise-tabs" id="pilot-exercise-tabs"></div>
            </div>
            <div id="pilot-ranks-panel" hidden>
                <div class="pilot-rank-league-tabs" id="pilot-rank-league-tabs"></div>
                <p class="pilot-rank-hint" id="pilot-rank-hint"></p>
            </div>
            <div id="pilot-history-panel" hidden>
                <div class="pilot-history-toolbar">
                    <label class="pilot-history-filter">
                        <span>Спортсмен</span>
                        <select id="pilot-history-athlete"></select>
                    </label>
                </div>
            </div>
            <div id="pilot-photos-panel" class="pilot-photos-panel" hidden>
                <p class="pilot-photos-note">Загрузите фото (JPG, PNG или WebP, до 3 МБ). Без фото — один из 10 спортивных персонажей; у каждого ФИО свой постоянный вариант.</p>
                <div class="pilot-photos-grid" id="pilot-photos-grid"></div>
                <input type="file" id="pilot-photo-file" accept="image/jpeg,image/png,image/webp" hidden>
            </div>
            <div id="pilot-coach-panel" class="pilot-coach-panel" hidden>
                <p class="pilot-coach-note">Профиль тренера создаётся автоматически. Имя берётся из настройки группы (<code>api/coaches.php</code>) — это название вкладки тренера на сайте.</p>
                <h3 class="pilot-coach-name" id="pilot-coach-name"></h3>
                <section class="pilot-coach-section">
                    <h4>Результаты</h4>
                    <div class="pilot-coach-results-grid" id="pilot-coach-results-grid"></div>
                </section>
                <section class="pilot-coach-section">
                    <h4>Ранги</h4>
                    <div class="pilot-rank-league-tabs" id="pilot-coach-rank-league-tabs"></div>
                    <p class="pilot-rank-hint" id="pilot-coach-rank-hint"></p>
                    <div class="table-wrap pilot-coach-ranks-wrap">
                        <table class="pilot-training-table pilot-ranks-table" id="pilot-coach-ranks-table">
                            <thead id="pilot-coach-ranks-thead"></thead>
                            <tbody id="pilot-coach-ranks-tbody"></tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div id="pilot-import-panel" class="pilot-import-panel" hidden>
                <p class="pilot-import-note">Импорт спортсменов из Google Таблиц в MySQL. Первая строка с данными в старых таблицах (норматив тренера) пропускается — тренер редактируется во вкладке «Тренер».</p>
                <label class="pilot-import-field">
                    <span>Шаблон тренера (подставить ссылки)</span>
                    <select id="pilot-import-preset">
                        <option value="">— выберите или вставьте вручную —</option>
                    </select>
                </label>
                <label class="pilot-import-field">
                    <span>Ссылка на таблицу результатов (CSV)</span>
                    <input type="url" id="pilot-import-results-url" placeholder="https://docs.google.com/.../pub?...&output=csv" autocomplete="off">
                </label>
                <label class="pilot-import-field">
                    <span>Ссылка на таблицу рангов (CSV, необязательно)</span>
                    <input type="url" id="pilot-import-ranks-url" placeholder="https://docs.google.com/.../pub?...&output=csv" autocomplete="off">
                </label>
                <label class="pilot-import-check">
                    <input type="checkbox" id="pilot-import-keep-history" checked>
                    <span>Сохранить историю изменений и не сбрасывать достижения вручную (пересчитаются после импорта)</span>
                </label>
                <div class="pilot-import-actions">
                    <button type="button" class="pilot-btn pilot-btn--primary" id="pilot-import-btn">Импортировать в базу</button>
                </div>
                <p id="pilot-import-status" class="pilot-import-status" hidden></p>
            </div>
            <p id="pilot-save-status" class="pilot-training-status"></p>
            <div class="pilot-training-table-wrap" id="pilot-training-table-wrap">
                <table class="pilot-training-table">
                    <thead id="pilot-training-thead"></thead>
                    <tbody id="pilot-training-tbody"></tbody>
                </table>
            </div>
            <div class="pilot-add-row" id="pilot-add-row">
                <input type="text" id="pilot-new-name" placeholder="ФИО нового спортсмена" enterkeyhint="done">
                <button type="button" class="pilot-btn pilot-btn--primary" id="pilot-add-btn">Добавить</button>
            </div>
        </div>`;

        const modeTabs = root.querySelector('#pilot-mode-tabs');
        modeTabs.innerHTML = `
            <button type="button" class="pilot-mode-tab" data-mode="results">Результаты</button>
            <button type="button" class="pilot-mode-tab" data-mode="coach">Тренер</button>
            <button type="button" class="pilot-mode-tab" data-mode="photos">Фото</button>
            <button type="button" class="pilot-mode-tab" data-mode="ranks">Ранги</button>
            <button type="button" class="pilot-mode-tab" data-mode="history">История</button>
            <button type="button" class="pilot-mode-tab" data-mode="import">Импорт</button>`;
        modeTabs.querySelectorAll('[data-mode]').forEach((btn) => {
            btn.addEventListener('click', () => {
                this.viewMode = btn.getAttribute('data-mode');
                this.updateViewMode();
            });
        });

        const exTabs = root.querySelector('#pilot-exercise-tabs');
        this.exercises.forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pilot-exercise-tab';
            btn.dataset.exercise = item.key;
            btn.textContent = item.label;
            btn.addEventListener('click', () => {
                this.captureFocus();
                this.currentExercise = item.key;
                this.updateResultsView();
                this.restoreFocus();
            });
            exTabs.appendChild(btn);
        });

        const leagueTabs = root.querySelector('#pilot-rank-league-tabs');
        const autoBtn = document.createElement('button');
        autoBtn.type = 'button';
        autoBtn.className = 'pilot-rank-league-tab';
        autoBtn.dataset.league = 'auto';
        autoBtn.textContent = 'По прогрессу';
        autoBtn.addEventListener('click', () => {
            this.rankViewLeague = 'auto';
            this.updateRanksView();
        });
        leagueTabs.appendChild(autoBtn);

        this.rankLeagues.forEach((lg) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pilot-rank-league-tab';
            btn.dataset.league = String(lg.id);
            btn.textContent = lg.label;
            btn.addEventListener('click', () => {
                this.rankViewLeague = lg.id;
                this.updateRanksView();
            });
            leagueTabs.appendChild(btn);
        });

        const coachLeagueTabs = root.querySelector('#pilot-coach-rank-league-tabs');
        if (coachLeagueTabs) {
            const coachAutoBtn = document.createElement('button');
            coachAutoBtn.type = 'button';
            coachAutoBtn.className = 'pilot-rank-league-tab';
            coachAutoBtn.dataset.league = 'auto';
            coachAutoBtn.textContent = 'По прогрессу';
            coachAutoBtn.addEventListener('click', () => {
                this.coachRankLeague = 'auto';
                this.updateCoachView();
            });
            coachLeagueTabs.appendChild(coachAutoBtn);
            this.rankLeagues.forEach((lg) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pilot-rank-league-tab';
                btn.dataset.league = String(lg.id);
                btn.textContent = lg.label;
                btn.addEventListener('click', () => {
                    this.coachRankLeague = lg.id;
                    this.updateCoachView();
                });
                coachLeagueTabs.appendChild(btn);
            });
        }

        const coachResultsGrid = root.querySelector('#pilot-coach-results-grid');
        if (coachResultsGrid) {
            coachResultsGrid.addEventListener('blur', (e) => {
                const input = e.target.closest('.pilot-coach-result-input');
                if (!input) return;
                this.flushCoachResultInput(input);
            }, true);
        }

        const coachRanksBody = root.querySelector('#pilot-coach-ranks-tbody');
        if (coachRanksBody) {
            coachRanksBody.addEventListener('change', (e) => {
                const input = e.target.closest('.pilot-coach-rank-check');
                if (!input) return;
                const league = parseInt(input.getAttribute('data-league'), 10);
                const slot = parseInt(input.getAttribute('data-slot'), 10);
                this.toggleCoachRankMark(league, slot, input.checked, input);
            });
        }

        root.querySelector('#pilot-logout-btn').addEventListener('click', async () => {
            await fetch(`/api/coach/logout.php?${this.apiCoachQuery()}`, { credentials: 'same-origin' });
            this.authenticated = false;
            this.shellReady = false;
            this.showLogin();
        });

        root.querySelector('#pilot-add-btn').addEventListener('click', () => this.addAthlete());

        const tbody = root.querySelector('#pilot-training-tbody');
        tbody.addEventListener('blur', (e) => {
            const input = e.target.closest('.pilot-result-input');
            if (!input) return;
            this.flushResultInput(input);
        }, true);

        tbody.addEventListener('keydown', (e) => {
            const input = e.target.closest('.pilot-result-input');
            if (!input) return;
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const idx = parseInt(input.getAttribute('data-idx'), 10);
            this.flushResultInput(input).then(() => {
                const next = tbody.querySelector(`.pilot-result-input[data-idx="${idx + 1}"]`);
                if (next) {
                    next.focus();
                    if (next.select) next.select();
                }
            });
        });

        tbody.addEventListener('change', (e) => {
            const input = e.target.closest('.pilot-rank-check');
            if (!input) return;
            const name = input.getAttribute('data-name');
            const league = parseInt(input.getAttribute('data-league'), 10);
            const slot = parseInt(input.getAttribute('data-slot'), 10);
            this.toggleRankMark(name, league, slot, input.checked, input);
        });

        tbody.addEventListener('click', (e) => {
            const plusBtn = e.target.closest('.pilot-plus-btn');
            if (plusBtn) {
                e.preventDefault();
                this.incrementResult(plusBtn);
                return;
            }
            const delBtn = e.target.closest('.pilot-history-delete');
            if (delBtn) {
                e.preventDefault();
                this.deleteHistoryEntry(delBtn.getAttribute('data-id'));
                return;
            }
            const histBtn = e.target.closest('.pilot-history-btn');
            if (histBtn) {
                this.historyAthlete = histBtn.getAttribute('data-name') || '';
                this.viewMode = 'history';
                this.updateViewMode();
                return;
            }
            const removeBtn = e.target.closest('.pilot-remove-btn');
            if (removeBtn) {
                this.removeAthlete(removeBtn.getAttribute('data-remove'));
            }
        });

        tbody.addEventListener('error', (e) => {
            const img = e.target;
            if (!img || !img.classList) return;
            if (!img.classList.contains('pilot-row-photo') && !img.classList.contains('pilot-photo-img')) {
                return;
            }
            if (typeof LegionPilotPhotos !== 'undefined') {
                LegionPilotPhotos.onImgError(img);
            }
        }, true);

        const historyPanel = root.querySelector('#pilot-history-panel');
        if (historyPanel) {
            historyPanel.addEventListener('change', (e) => {
                if (e.target.id === 'pilot-history-athlete') {
                    this.historyAthlete = e.target.value;
                    this.updateHistoryView();
                }
            });
        }

        const importPreset = root.querySelector('#pilot-import-preset');
        if (importPreset) {
            importPreset.addEventListener('change', () => {
                const slug = importPreset.value;
                const preset = this.importPresets.find((c) => c.slug === slug);
                if (!preset) return;
                const resultsInput = document.getElementById('pilot-import-results-url');
                const ranksInput = document.getElementById('pilot-import-ranks-url');
                if (resultsInput) resultsInput.value = preset.resultsUrl || '';
                if (ranksInput) ranksInput.value = preset.ranksUrl || '';
            });
        }

        const importBtn = root.querySelector('#pilot-import-btn');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.runSheetsImport());
        }

        const photoFile = root.querySelector('#pilot-photo-file');
        if (photoFile) {
            photoFile.addEventListener('change', () => {
                const name = photoFile.getAttribute('data-athlete-name') || '';
                const file = photoFile.files && photoFile.files[0];
                photoFile.value = '';
                photoFile.removeAttribute('data-athlete-name');
                if (name && file) {
                    this.uploadPhoto(name, file);
                }
            });
        }

        const photosGrid = root.querySelector('#pilot-photos-grid');
        if (photosGrid) {
            photosGrid.addEventListener('click', (e) => {
                const uploadBtn = e.target.closest('.pilot-photo-upload-btn');
                if (uploadBtn) {
                    const athleteName = uploadBtn.getAttribute('data-name');
                    const input = document.getElementById('pilot-photo-file');
                    if (input && athleteName) {
                        input.setAttribute('data-athlete-name', athleteName);
                        input.click();
                    }
                    return;
                }
                const removeBtn = e.target.closest('.pilot-photo-remove-btn');
                if (removeBtn) {
                    this.removePhoto(removeBtn.getAttribute('data-name'));
                }
            });
        }

        if (typeof LegionCore !== 'undefined') {
            LegionCore.initSearchBar(() => this.updateViewMode());
        }

        this.loadImportPresets();
    },

    captureFocus() {
        const active = document.activeElement;
        if (active && active.classList && active.classList.contains('pilot-result-input')) {
            this.focusIdx = parseInt(active.getAttribute('data-idx'), 10);
            if (Number.isNaN(this.focusIdx)) this.focusIdx = null;
        }
    },

    restoreFocus() {
        if (this.viewMode !== 'results' || this.focusIdx === null) return;
        const input = document.querySelector(`.pilot-result-input[data-idx="${this.focusIdx}"]`);
        if (!input) return;
        input.focus();
        const len = input.value.length;
        try {
            input.setSelectionRange(len, len);
        } catch (e) {
            /* type=number */
        }
    },

    updateViewMode() {
        const resultsPanel = document.getElementById('pilot-results-panel');
        const ranksPanel = document.getElementById('pilot-ranks-panel');
        const historyPanel = document.getElementById('pilot-history-panel');
        const photosPanel = document.getElementById('pilot-photos-panel');
        const importPanel = document.getElementById('pilot-import-panel');
        const coachPanel = document.getElementById('pilot-coach-panel');
        const addRow = document.getElementById('pilot-add-row');
        const tableWrap = document.getElementById('pilot-training-table-wrap');
        const saveStatus = document.getElementById('pilot-save-status');

        document.querySelectorAll('.pilot-mode-tab').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-mode') === this.viewMode);
        });

        if (resultsPanel) resultsPanel.hidden = this.viewMode !== 'results';
        if (ranksPanel) ranksPanel.hidden = this.viewMode !== 'ranks';
        if (historyPanel) historyPanel.hidden = this.viewMode !== 'history';
        if (photosPanel) photosPanel.hidden = this.viewMode !== 'photos';
        if (importPanel) importPanel.hidden = this.viewMode !== 'import';
        if (coachPanel) coachPanel.hidden = this.viewMode !== 'coach';
        if (addRow) addRow.hidden = this.viewMode !== 'results';
        if (tableWrap) {
            tableWrap.hidden = this.viewMode === 'import' || this.viewMode === 'photos' || this.viewMode === 'coach';
            tableWrap.classList.toggle('pilot-training-table-wrap--ranks', this.viewMode === 'ranks');
        }
        if (saveStatus) saveStatus.hidden = this.viewMode === 'import' || this.viewMode === 'photos';

        if (this.viewMode === 'results') {
            this.updateResultsView();
        } else if (this.viewMode === 'coach') {
            this.updateCoachView();
        } else if (this.viewMode === 'photos') {
            this.updatePhotosView();
        } else if (this.viewMode === 'ranks') {
            this.updateRanksView();
        } else if (this.viewMode === 'history') {
            this.updateHistoryView();
        } else if (this.viewMode === 'import') {
            this.updateImportView();
        }

        const searchWrap = document.getElementById('pilot-search-wrap');
        if (searchWrap) searchWrap.hidden = this.viewMode === 'import' || this.viewMode === 'coach';

        this.updatePilotSearchStatus();
    },

    updateCoachView() {
        const title = document.getElementById('pilot-training-title');
        if (title) title.textContent = 'Тренер';

        const upd = document.getElementById('pilot-training-updated');
        if (upd) upd.textContent = this.updatedAt ? 'Обновлено: ' + this.updatedAt : '';

        const nameEl = document.getElementById('pilot-coach-name');
        if (nameEl) {
            nameEl.textContent = this.coachProfile && this.coachProfile.name
                ? this.coachProfile.name
                : 'Загрузка…';
        }

        document.querySelectorAll('#pilot-coach-rank-league-tabs .pilot-rank-league-tab').forEach((btn) => {
            const val = btn.getAttribute('data-league');
            const active = val === 'auto'
                ? this.coachRankLeague === 'auto'
                : parseInt(val, 10) === this.coachRankLeague;
            btn.classList.toggle('is-active', active);
        });

        const hint = document.getElementById('pilot-coach-rank-hint');
        if (hint) {
            if (this.coachRankLeague === 'auto') {
                hint.textContent = 'Отметки нормативов тренера по текущей лиге. Снятие всех галочек серебра/золота — откат на уровень ниже.';
            } else {
                const lg = this.leagueMeta(this.coachRankLeague);
                hint.textContent = `Нормативы «${lg.label}» для тренера.`;
            }
        }

        this.renderCoachResultsGrid();
        this.renderCoachRankRows();
    },

    renderCoachResultsGrid() {
        const grid = document.getElementById('pilot-coach-results-grid');
        if (!grid) return;
        const coach = this.coachProfile;
        if (!coach) {
            grid.innerHTML = '<p class="note">Профиль тренера загружается…</p>';
            return;
        }

        let html = '';
        this.exercises.forEach((ex) => {
            const val = Number(coach[ex.key]) || 0;
            const step = ex.key === 'hang' || ex.key === 'jump' ? 'any' : '1';
            html += `<label class="pilot-coach-result-field">
                <span>${this.esc(ex.label)}</span>
                <input type="number" inputmode="decimal" class="pilot-coach-result-input"
                    min="0" step="${step}" data-exercise="${ex.key}"
                    value="${val > 0 ? val : ''}" data-saved="${val}"
                    aria-label="${this.escAttr(coach.name + ' ' + ex.label)}">
            </label>`;
        });
        grid.innerHTML = html;
    },

    getCoachActiveLeague() {
        const coach = this.coachProfile;
        if (!coach) return 3;
        return this.getAthleteActiveLeague(coach.name);
    },

    renderCoachRankRows() {
        const tbody = document.getElementById('pilot-coach-ranks-tbody');
        const thead = document.getElementById('pilot-coach-ranks-thead');
        const coach = this.coachProfile;
        if (!tbody || !thead) return;

        if (!coach) {
            tbody.innerHTML = '<tr><td colspan="5" class="pilot-rank-empty">Нет данных тренера</td></tr>';
            return;
        }

        const isAuto = this.coachRankLeague === 'auto';
        const league = isAuto ? this.getCoachActiveLeague() : this.coachRankLeague;
        const columns = this.getLeagueColumnsMeta(league);
        const rank = this.getAthleteClubRank(coach.name);
        const meta = this.leagueMeta(league);
        const done = this.countLeagueDone(coach.name, league);
        const nextOpen = Math.min(done, 19);

        let head = `<tr class="pilot-ranks-head-names">
            <th class="col-name sticky-col">ФИО</th>`;
        if (isAuto) {
            head += `<th class="col-rank-league-h">Лига</th>`;
        }
        head += `<th class="col-rank-title-h">Звание</th>
            <th class="col-rank-progress-h">Счёт</th>`;
        if (isAuto) {
            head += `<th colspan="20" class="col-rank-ex-group">Нормативы (текущая лига)</th>`;
        } else {
            columns.forEach((col) => {
                const shortEx = this.shortExerciseLabel(col.exercise);
                const title = col.rankName + ' — ' + (col.description || col.exercise);
                head += `<th class="col-rank-ex" title="${this.escAttr(title)}">${this.esc(shortEx)}</th>`;
            });
        }
        head += `<th class="col-rank-rankname-h">Следующий</th></tr>`;
        thead.innerHTML = head;

        let row = `<tr data-coach="1" data-league="${league}" class="pilot-rank-row row-coach-benchmark${
            done >= 20 ? ' pilot-rank-row--complete' : ''
        }">`;
        row += `<td class="col-name sticky-col"><strong>${this.esc(coach.name)}</strong></td>`;
        if (isAuto) {
            row += `<td class="col-rank-league">${this.esc(meta.label)}</td>`;
        }
        row += `<td class="col-rank-title">${rank && rank.rankName ? this.esc(rank.rankName) : '—'}</td>`;
        row += `<td class="col-rank-progress">${done}/20</td>`;

        columns.forEach((col) => {
            const isDone = this.isRankMarkDone(coach.name, league, col.slot);
            const isNext = !isDone && col.slot === nextOpen;
            const tip = `${col.rankName}: ${col.description || col.exercise}`;
            const shortEx = this.shortExerciseLabel(col.exercise);
            row += `<td class="col-rank-check-cell${isDone ? ' is-done' : ''}${isNext ? ' is-next' : ''}">`;
            row += `<label class="pilot-rank-check-label" title="${this.escAttr(tip)}">`;
            if (isAuto) {
                row += `<span class="pilot-rank-cell-ex">${this.esc(shortEx)}</span>`;
            }
            row += `<input type="checkbox" class="pilot-rank-check pilot-coach-rank-check"`;
            row += ` data-league="${league}" data-slot="${col.slot}"`;
            row += isDone ? ' checked' : '';
            row += ` aria-label="${this.escAttr(coach.name + ' — ' + col.exercise)}">`;
            row += `<span class="pilot-rank-check-ui" aria-hidden="true"></span>`;
            row += `</label></td>`;
        });

        const nextCol = columns[nextOpen] || columns[0];
        const normLabel = nextCol
            ? (done >= 20 ? 'Лига пройдена' : nextCol.exercise)
            : '';
        row += `<td class="col-rank-norm" title="${this.escAttr(normLabel)}">${this.esc(
            this.shortExerciseLabel(normLabel, 28)
        )}</td>`;
        row += '</tr>';
        tbody.innerHTML = row;
    },

    async flushCoachResultInput(input) {
        if (!input || !this.coachProfile) return;
        const exercise = input.getAttribute('data-exercise');
        const raw = String(input.value).trim().replace(',', '.');
        if (raw === '') {
            const saved = parseFloat(input.dataset.saved);
            input.value = !Number.isNaN(saved) && saved > 0 ? String(saved) : '';
            return;
        }
        const num = parseFloat(raw);
        if (Number.isNaN(num) || num < 0) {
            this.setStatus('Некорректное значение', 'error');
            return;
        }
        const saved = parseFloat(input.dataset.saved) || 0;
        if (num === saved) return;

        input.disabled = true;
        this.setStatus('Сохранение…', 'saving');
        try {
            const resp = await fetch('/api/coach/update_coach_result.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ exercise, value: num }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');

            if (data.coachBenchmark) {
                this.applyCoachProfileFromApi(data.coachBenchmark, this.rankData);
            } else if (this.coachProfile) {
                this.coachProfile[exercise] = num;
            }
            input.dataset.saved = String(num);
            input.value = num > 0 ? String(num) : '';
            if (data.updatedAt) {
                this.updatedAt = data.updatedAt;
                const upd = document.getElementById('pilot-training-updated');
                if (upd) upd.textContent = 'Обновлено: ' + data.updatedAt;
            }
            this.setStatus('Сохранено ✓', 'ok');
        } catch (err) {
            this.setStatus(err.message || 'Ошибка', 'error');
        } finally {
            input.disabled = false;
        }
    },

    async toggleCoachRankMark(league, slot, checked, inputEl) {
        if (!this.coachProfile) return;
        const idx = this.rankMarkIndex(league, slot);
        const next = checked ? 1 : 0;
        const leagueBefore = this.getCoachActiveLeague();

        if (inputEl) inputEl.disabled = true;
        this.setStatus('Сохранение…', 'saving');

        try {
            const resp = await fetch('/api/coach/update_coach_rank_mark.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ markIndex: idx, value: next }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');

            if (data.coachBenchmark) {
                this.applyCoachProfileFromApi(data.coachBenchmark, this.rankData);
            } else if (Array.isArray(data.rankMarks) && this.coachProfile) {
                this.coachProfile.rankMarks = LegionCore.normalizeRankMarksValue(data.rankMarks);
            }

            const leagueAfter = this.getCoachActiveLeague();
            if (leagueBefore !== leagueAfter) {
                this.setStatus(`Лига тренера: «${this.leagueMeta(leagueAfter).label}»`, 'ok');
            } else {
                this.setStatus('Сохранено ✓', 'ok');
            }

            if (data.updatedAt) {
                this.updatedAt = data.updatedAt;
                const upd = document.getElementById('pilot-training-updated');
                if (upd) upd.textContent = 'Обновлено: ' + data.updatedAt;
            }
            this.renderCoachRankRows();
        } catch (err) {
            if (inputEl) inputEl.checked = !checked;
            this.setStatus(err.message || 'Ошибка', 'error');
        } finally {
            if (inputEl) inputEl.disabled = false;
        }
    },

    updateResultsView() {
        const ex = this.currentExercise;
        const exLabel = (this.exercises.find((e) => e.key === ex) || {}).label || ex;
        const title = document.getElementById('pilot-training-title');
        if (title) title.textContent = exLabel;

        const upd = document.getElementById('pilot-training-updated');
        if (upd) upd.textContent = this.updatedAt ? 'Обновлено: ' + this.updatedAt : '';

        document.querySelectorAll('.pilot-exercise-tab').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-exercise') === ex);
        });

        const thead = document.getElementById('pilot-training-thead');
        if (thead) {
            thead.innerHTML = `<tr>
                <th class="col-name">ФИО</th>
                <th class="col-was">Было</th>
                <th class="col-input">Стало</th>
                <th class="col-plus" title="Плюс 1"></th>
                <th></th>
            </tr>`;
        }

        const table = document.querySelector('.pilot-training-table');
        if (table) table.classList.remove('pilot-ranks-table');

        this.renderResultRows(ex);
    },

    updateRanksView() {
        const title = document.getElementById('pilot-training-title');
        if (title) title.textContent = 'Ранги';

        const upd = document.getElementById('pilot-training-updated');
        if (upd) upd.textContent = this.updatedAt ? 'Обновлено: ' + this.updatedAt : '';

        const hint = document.getElementById('pilot-rank-hint');
        if (hint) {
            if (this.rankViewLeague === 'auto') {
                hint.textContent = 'Общая таблица достижений: у каждого свои 20 нормативов текущей лиги. Если снять все галочки серебра — спортсмен вернётся в бронзу; все галочки золота — в серебро.';
            } else {
                const lg = this.leagueMeta(this.rankViewLeague);
                hint.textContent = `Только спортсмены в «${lg.label}». Сдали все 20 — появятся во вкладке следующей лиги. Сняли все галочки — вернутся на уровень ниже.`;
            }
        }

        document.querySelectorAll('.pilot-rank-league-tab').forEach((btn) => {
            const val = btn.getAttribute('data-league');
            const active = val === 'auto'
                ? this.rankViewLeague === 'auto'
                : parseInt(val, 10) === this.rankViewLeague;
            btn.classList.toggle('is-active', active);
        });

        const isAuto = this.rankViewLeague === 'auto';
        const showLeagueCol = isAuto;
        const headerLeague = isAuto ? 3 : this.rankViewLeague;
        const columns = this.getLeagueColumnsMeta(headerLeague);

        const thead = document.getElementById('pilot-training-thead');
        if (thead) {
            let head = `<tr class="pilot-ranks-head-names">
                <th class="col-name sticky-col">ФИО</th>`;
            if (showLeagueCol) {
                head += `<th class="col-rank-league-h">Лига</th>`;
            }
            head += `<th class="col-rank-title-h">Звание</th>
                <th class="col-rank-progress-h">Счёт</th>`;
            if (isAuto) {
                head += `<th colspan="20" class="col-rank-ex-group">Нормативы (текущая лига)</th>`;
            } else {
                columns.forEach((col) => {
                    const shortEx = this.shortExerciseLabel(col.exercise);
                    const title = col.rankName + ' — ' + (col.description || col.exercise);
                    head += `<th class="col-rank-ex" title="${this.escAttr(title)}">${this.esc(shortEx)}</th>`;
                });
            }
            head += `<th class="col-rank-rankname-h">Следующий</th></tr>`;
            thead.innerHTML = head;
        }

        const table = document.querySelector('.pilot-training-table');
        if (table) table.classList.add('pilot-ranks-table');

        this.renderRankRows();
    },

    getHistoryForAthlete(name) {
        const norm = typeof LegionCore !== 'undefined'
            ? LegionCore.normalizePersonName(name)
            : name;
        return this.history
            .filter((e) => {
                const en = typeof LegionCore !== 'undefined'
                    ? LegionCore.normalizePersonName(e.name)
                    : e.name;
                return en === norm;
            })
            .slice()
            .reverse();
    },

    formatHistoryEntry(entry) {
        if (typeof LegionCore !== 'undefined' && LegionCore.formatHistoryChangeText) {
            const exLabel = LegionConfig.EXERCISES.find((x) => x.key === entry.exercise);
            const label = exLabel ? exLabel.label : entry.exercise;
            const clone = { ...entry };
            if (!clone.exercise) return LegionCore.formatHistoryChangeText(clone);
            return `<span class="pilot-history-ex">${this.esc(label)}</span> · ${LegionCore.formatHistoryChangeText(clone)}`;
        }
        return `${entry.date}: ${entry.oldVal} → ${entry.newVal}`;
    },

    updateHistoryView() {
        const title = document.getElementById('pilot-training-title');
        if (title) title.textContent = 'История изменений';

        const upd = document.getElementById('pilot-training-updated');
        if (upd) upd.textContent = this.updatedAt ? 'Обновлено: ' + this.updatedAt : '';

        if (!this.historyAthlete && this.athletes.length) {
            this.historyAthlete = this.athletes[0].name;
        }

        const select = document.getElementById('pilot-history-athlete');
        if (select) {
            select.innerHTML = this.athletes.map((a) =>
                `<option value="${this.escAttr(a.name)}"${a.name === this.historyAthlete ? ' selected' : ''}>${this.esc(a.name)}</option>`
            ).join('');
        }

        const thead = document.getElementById('pilot-training-thead');
        if (thead) {
            thead.innerHTML = `<tr>
                <th>Дата</th>
                <th>Изменение</th>
                <th></th>
            </tr>`;
        }

        const table = document.querySelector('.pilot-training-table');
        if (table) table.classList.remove('pilot-ranks-table');

        const tbody = document.getElementById('pilot-training-tbody');
        if (!tbody) return;

        const entries = this.historyAthlete ? this.getHistoryForAthlete(this.historyAthlete) : [];
        if (!entries.length) {
            tbody.innerHTML = `<tr><td colspan="3" class="pilot-history-empty">Нет записей${
                this.historyAthlete ? ' у ' + this.esc(this.historyAthlete) : ''
            }. Изменения появятся после сохранения результатов.</td></tr>`;
            return;
        }

        let html = '';
        entries.forEach((entry) => {
            html += `<tr data-history-id="${this.escAttr(entry.id || '')}">
                <td class="col-history-date">${this.esc(entry.date || '')}</td>
                <td class="col-history-text">${this.formatHistoryEntry(entry)}</td>
                <td class="col-history-actions">`;
            if (entry.id) {
                html += `<button type="button" class="pilot-btn pilot-btn--danger pilot-history-delete" data-id="${this.escAttr(entry.id)}" title="Удалить запись">✕</button>`;
            }
            html += `</td></tr>`;
        });
        tbody.innerHTML = html;
    },

    async deleteHistoryEntry(id) {
        if (!id) return;
        if (!confirm('Удалить эту запись из истории? Текущий результат спортсмена не изменится.')) return;

        this.setStatus('Удаление…', 'saving');
        try {
            const resp = await fetch('/api/coach/delete_history.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ id }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');

            this.applyMetaFromApi(data);
            if (data.updatedAt) {
                this.updatedAt = data.updatedAt;
            }
            this.updateHistoryView();
            this.setStatus('Запись удалена', 'ok');
        } catch (err) {
            this.setStatus(err.message || 'Ошибка', 'error');
        }
    },

    renderResultRows(ex) {
        const tbody = document.getElementById('pilot-training-tbody');
        if (!tbody) return;

        const exLabel = (this.exercises.find((e) => e.key === ex) || {}).label || ex;
        const step = ex === 'hang' || ex === 'jump' ? 'any' : '1';
        const athletes = this.filterAthletes(this.athletes);
        let html = '';

        if (!athletes.length) {
            const q = typeof LegionCore !== 'undefined' ? LegionCore.state.searchQuery : '';
            tbody.innerHTML = `<tr><td colspan="5" class="pilot-rank-empty">${
                q ? 'Никого не найдено. Попробуйте другое имя.' : 'В группе пока нет спортсменов.'
            }</td></tr>`;
            return;
        }

        athletes.forEach((a, idx) => {
            const was = this.baseline[a.name] ? (this.baseline[a.name][ex] || 0) : 0;
            const now = Number(a[ex]) || 0;
            const displayVal = now > 0 ? now : '';

            html += `<tr data-athlete="${this.escAttr(a.name)}">
                <td class="col-name">
                    <span class="pilot-row-name">
                        ${this.photoImgMarkup(a, this.updatedAt, 'pilot-row-photo')}
                        <span>${this.esc(a.name)}</span>
                    </span>
                </td>
                <td class="col-was">${was || '—'}</td>
                <td class="col-input">
                    <input type="number" inputmode="decimal" enterkeyhint="next"
                        class="pilot-result-input" min="0" step="${step}"
                        value="${displayVal}" data-idx="${idx}" data-name="${this.escAttr(a.name)}"
                        data-exercise="${ex}" data-saved="${now}"
                        aria-label="${this.escAttr(a.name)} ${this.escAttr(exLabel)}">
                </td>
                <td class="col-plus">
                    <button type="button" class="pilot-plus-btn" data-name="${this.escAttr(a.name)}"
                        data-exercise="${ex}" title="Плюс 1" aria-label="Добавить 1">+</button>
                </td>
                <td class="col-actions">
                    <button type="button" class="pilot-btn pilot-history-btn" data-name="${this.escAttr(a.name)}" title="История">📋</button>
                    <button type="button" class="pilot-btn pilot-btn--danger pilot-remove-btn" data-remove="${this.escAttr(a.name)}">✕</button>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    },

    renderRankRows() {
        const tbody = document.getElementById('pilot-training-tbody');
        if (!tbody) return;

        const isAuto = this.rankViewLeague === 'auto';
        const list = this.athletesForRankView();
        const colSpan = (isAuto ? 4 : 3) + 20 + 1;

        if (!list.length) {
            const q = typeof LegionCore !== 'undefined' ? LegionCore.state.searchQuery : '';
            const msg = q ? 'Никого не найдено по запросу.' : this.emptyRankMessage();
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="pilot-rank-empty">${this.esc(msg)}</td></tr>`;
            return;
        }

        let html = '';
        list.forEach((a) => {
            const league = this.getLeagueForAthleteRow(a.name);
            const columns = this.getLeagueColumnsMeta(league);
            const rank = this.getAthleteClubRank(a.name);
            const meta = this.leagueMeta(league);
            const done = this.countLeagueDone(a.name, league);
            const nextOpen = Math.min(done, 19);

            html += `<tr data-athlete="${this.escAttr(a.name)}" data-league="${league}"` +
                ` class="pilot-rank-row${done >= 20 ? ' pilot-rank-row--complete' : ''}">`;
            html += `<td class="col-name sticky-col" title="${this.escAttr(a.name)}">${this.esc(a.name)}</td>`;
            if (isAuto) {
                html += `<td class="col-rank-league">${this.esc(meta.label)}</td>`;
            }
            html += `<td class="col-rank-title">${rank && rank.rankName ? this.esc(rank.rankName) : '—'}</td>`;
            html += `<td class="col-rank-progress">${done}/20</td>`;

            columns.forEach((col) => {
                const isDone = this.isRankMarkDone(a.name, league, col.slot);
                const isNext = !isDone && col.slot === nextOpen;
                const tip = `${col.rankName}: ${col.description || col.exercise}`;
                const shortEx = this.shortExerciseLabel(col.exercise);
                html += `<td class="col-rank-check-cell${isDone ? ' is-done' : ''}${isNext ? ' is-next' : ''}">`;
                html += `<label class="pilot-rank-check-label" title="${this.escAttr(tip)}">`;
                if (isAuto) {
                    html += `<span class="pilot-rank-cell-ex">${this.esc(shortEx)}</span>`;
                }
                html += `<input type="checkbox" class="pilot-rank-check"`;
                html += ` data-name="${this.escAttr(a.name)}" data-league="${league}" data-slot="${col.slot}"`;
                html += isDone ? ' checked' : '';
                html += ` aria-label="${this.escAttr(a.name + ' — ' + col.exercise)}">`;
                html += `<span class="pilot-rank-check-ui" aria-hidden="true"></span>`;
                html += `</label></td>`;
            });

            const nextCol = columns[nextOpen] || columns[0];
            const normLabel = nextCol
                ? (done >= 20 ? 'Лига пройдена' : nextCol.exercise)
                : '';
            html += `<td class="col-rank-norm" title="${this.escAttr(normLabel)}">${this.esc(
                this.shortExerciseLabel(normLabel, 28)
            )}</td>`;
            html += `</tr>`;
        });

        tbody.innerHTML = html;
    },

    async addAthlete() {
        const input = document.getElementById('pilot-new-name');
        const name = input ? input.value.trim() : '';
        if (!name) return;
        this.setStatus('Добавление…', 'saving');
        try {
            const resp = await fetch('/api/coach/add_athlete.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ name }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');
            if (input) input.value = '';
            await this.loadAthletes();
            this.focusIdx = this.athletes.length - 1;
            this.updateViewMode();
            this.setStatus('Спортсмен добавлен', 'ok');
        } catch (err) {
            this.setStatus(err.message, 'error');
        }
    },

    async removeAthlete(name) {
        if (!confirm(`Удалить «${name}» из пилотной группы?`)) return;
        try {
            const resp = await fetch('/api/coach/remove_athlete.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ name }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');
            await this.loadAthletes();
            this.focusIdx = null;
            this.updateViewMode();
            this.setStatus('Удалено', 'ok');
        } catch (err) {
            this.setStatus(err.message, 'error');
        }
    },

    incrementResult(btn) {
        const name = btn.getAttribute('data-name');
        const exercise = btn.getAttribute('data-exercise');
        if (!name || !exercise) return;

        const row = btn.closest('tr');
        const input = row
            ? row.querySelector('.pilot-result-input')
            : document.querySelector(
                `.pilot-result-input[data-name="${CSS.escape(name)}"][data-exercise="${exercise}"]`
            );
        if (!input) return;

        const raw = String(input.value).trim().replace(',', '.');
        const current = raw === '' ? 0 : parseFloat(raw);
        const next = (Number.isNaN(current) ? 0 : current) + 1;
        input.value = String(next);
        this.flushResultInput(input);
    },

    photoUrl(athlete, bust) {
        const base = typeof LegionPilotPhotos !== 'undefined'
            ? LegionPilotPhotos.resolveUrl(athlete)
            : (athlete.photo || '');
        if (typeof LegionPilotPhotos !== 'undefined') {
            return LegionPilotPhotos.withCacheBust(base, bust);
        }
        if (!bust || String(base).startsWith('data:')) return base;
        const sep = base.includes('?') ? '&' : '?';
        return `${base}${sep}v=${encodeURIComponent(bust)}`;
    },

    photoImgMarkup(athlete, bust, className) {
        if (typeof LegionPilotPhotos !== 'undefined' && LegionPilotPhotos.slotHtml) {
            return LegionPilotPhotos.slotHtml(athlete, bust, className, (v) => this.escAttr(v));
        }
        const url = this.photoUrl(athlete, bust);
        return `<img src="${this.escAttr(url)}" alt="" class="${className || 'pilot-photo-img'}" loading="lazy">`;
    },

    updatePhotosView() {
        const title = document.getElementById('pilot-training-title');
        if (title) title.textContent = 'Фото спортсменов';

        const upd = document.getElementById('pilot-training-updated');
        if (upd) {
            upd.textContent = this.updatedAt ? 'Обновлено: ' + this.updatedAt : '';
        }

        const grid = document.getElementById('pilot-photos-grid');
        if (!grid) return;

        if (!this.athletes.length) {
            grid.innerHTML = '<p class="pilot-photos-empty">В группе пока нет спортсменов.</p>';
            return;
        }

        const athletes = this.filterAthletes(this.athletes);
        if (!athletes.length) {
            grid.innerHTML = '<p class="pilot-photos-empty">Никого не найдено. Попробуйте другое имя.</p>';
            return;
        }

        const bust = this.updatedAt || Date.now();
        let html = '';
        athletes.forEach((a) => {
            const badge = a.hasPhoto
                ? '<span class="pilot-photo-badge pilot-photo-badge--own">Своё фото</span>'
                : '<span class="pilot-photo-badge">Персонаж</span>';
            html += `<article class="pilot-photo-card" data-athlete="${this.escAttr(a.name)}">
                <div class="pilot-photo-frame">
                    ${this.photoImgMarkup(a, bust, 'pilot-photo-img')}
                </div>
                <h3 class="pilot-photo-name">${this.esc(a.name)}</h3>
                ${badge}
                <div class="pilot-photo-actions">
                    <button type="button" class="pilot-btn pilot-btn--primary pilot-photo-upload-btn" data-name="${this.escAttr(a.name)}">Загрузить</button>`;
            if (a.hasPhoto) {
                html += `<button type="button" class="pilot-btn pilot-photo-remove-btn" data-name="${this.escAttr(a.name)}">Убрать фото</button>`;
            }
            html += `</div></article>`;
        });
        grid.innerHTML = html;
    },

    async uploadPhoto(name, file) {
        if (!name || !file) return;
        this.setStatus('Загрузка фото…', 'saving');
        const fd = new FormData();
        fd.append('name', name);
        fd.append('photo', file);
        fd.append('coach', this.coachSlug);

        try {
            const resp = await fetch('/api/coach/upload_photo.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка загрузки');

            const athlete = this.athletes.find((a) => a.name === name);
            if (athlete) {
                athlete.photo = data.photo;
                athlete.hasPhoto = !!data.hasPhoto;
            }
            if (data.updatedAt) this.updatedAt = data.updatedAt;
            if (this.viewMode === 'photos') this.updatePhotosView();
            else this.updateViewMode();
            this.setStatus('Фото сохранено', 'ok');
        } catch (err) {
            this.setStatus(err.message || 'Ошибка', 'error');
        }
    },

    async removePhoto(name) {
        if (!name) return;
        if (!confirm(`Убрать загруженное фото у «${name}»? Будет показан спортивный персонаж.`)) return;

        this.setStatus('Удаление фото…', 'saving');
        try {
            const resp = await fetch('/api/coach/remove_photo.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ name }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');

            const athlete = this.athletes.find((a) => a.name === name);
            if (athlete) {
                athlete.photo = data.photo;
                athlete.hasPhoto = false;
            }
            if (data.updatedAt) this.updatedAt = data.updatedAt;
            if (this.viewMode === 'photos') this.updatePhotosView();
            else this.updateViewMode();
            this.setStatus('Фото удалено', 'ok');
        } catch (err) {
            this.setStatus(err.message || 'Ошибка', 'error');
        }
    },

    updateImportView() {
        const title = document.getElementById('pilot-training-title');
        if (title) title.textContent = 'Импорт из Google Таблиц';

        const upd = document.getElementById('pilot-training-updated');
        if (upd) {
            upd.textContent = this.updatedAt
                ? `Текущие данные в базе обновлены: ${this.updatedAt}`
                : '';
        }
    },

    async loadImportPresets() {
        const select = document.getElementById('pilot-import-preset');
        if (!select) return;
        try {
            const resp = await fetch(`/api/coach/import_presets.php?${this.apiCoachQuery()}`, { credentials: 'same-origin' });
            if (!resp.ok) return;
            const data = await resp.json();
            this.importPresets = Array.isArray(data.coaches) ? data.coaches : [];
            select.innerHTML = '';
            this.importPresets.forEach((coach) => {
                const opt = document.createElement('option');
                opt.value = coach.slug;
                opt.textContent = coach.name;
                if (coach.slug === this.coachSlug) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
            const preset = this.importPresets.find((c) => c.slug === this.coachSlug) || this.importPresets[0];
            if (preset) {
                const resultsInput = document.getElementById('pilot-import-results-url');
                const ranksInput = document.getElementById('pilot-import-ranks-url');
                if (resultsInput && preset.resultsUrl) resultsInput.value = preset.resultsUrl;
                if (ranksInput && preset.ranksUrl) ranksInput.value = preset.ranksUrl;
            }
        } catch (e) {
            console.warn('Пресеты импорта недоступны', e);
        }
    },

    setImportStatus(message, kind) {
        const el = document.getElementById('pilot-import-status');
        if (!el) return;
        el.hidden = !message;
        el.textContent = message || '';
        el.className = 'pilot-import-status' + (kind ? ` pilot-import-status--${kind}` : '');
    },

    async runSheetsImport() {
        const resultsUrl = (document.getElementById('pilot-import-results-url') || {}).value || '';
        const ranksUrl = (document.getElementById('pilot-import-ranks-url') || {}).value || '';
        const keepHistory = !!(document.getElementById('pilot-import-keep-history') || {}).checked;
        const btn = document.getElementById('pilot-import-btn');

        if (!resultsUrl.trim()) {
            this.setImportStatus('Укажите ссылку на таблицу результатов', 'error');
            return;
        }

        if (!confirm('Импорт заменит список спортсменов и их результаты в базе этой группы. Продолжить?')) {
            return;
        }

        if (btn) btn.disabled = true;
        this.setImportStatus('Загрузка таблиц и запись в базу…', 'saving');

        try {
            const resp = await fetch('/api/coach/import_sheets.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.apiCoachBody({ resultsUrl, ranksUrl, keepHistory }))
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка импорта');

            await this.loadAthletes();
            this.viewMode = 'results';
            this.updateViewMode();
            this.setImportStatus(
                `Готово: ${data.athletes} спортсменов, ранги у ${data.withRanks || 0} · хранилище: ${data.storage || '—'}`,
                'ok'
            );
        } catch (err) {
            this.setImportStatus(err.message || 'Ошибка импорта', 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    esc(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },

    escAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    LegionPilotTraining.boot().catch((err) => {
        const root = document.getElementById('pilot-training-root');
        if (root) root.innerHTML = `<p class="error">${err.message || err}</p>`;
    });
});
