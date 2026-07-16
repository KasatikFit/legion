const LegionUI = {
    getPhotoFrameClass(clubRank, isCoachElite, isClubElite) {
        if (clubRank) {
            if (clubRank.league === 1) return 'photo-frame league-gold';
            if (clubRank.league === 2) return 'photo-frame league-silver';
            if (clubRank.league === 3) return 'photo-frame league-bronze';
        }
        if (isClubElite) return 'photo-frame league-club-elite';
        if (isCoachElite) return 'photo-frame league-elite';
        return 'photo-frame league-none';
    },

    applyPhotoFrame(frameEl, clubRank, isCoachElite, isClubElite) {
        if (!frameEl) return;
        frameEl.className = this.getPhotoFrameClass(clubRank, isCoachElite, isClubElite);
    },

    getAchievementRarity(id) {
        if (id === 'top1' || id === 'record_club' || id === 'rank_gold_done') return 'legendary';
        if (id.startsWith('ex_top1_') || id === 'beat_coach_3' || id === 'rank_silver_done') return 'epic';
        if (id === 'top3' || id === 'top25' || id === 'beat_coach_1' || id === 'rank_bronze_done') return 'rare';
        return 'common';
    },

    stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return (tmp.textContent || tmp.innerText || '').trim();
    },

    extractIconHtml(html) {
        const match = html.match(/<img[^>]+>/);
        if (match) return match[0];
        if (html.includes('🏆')) return '<span class="ach-emoji">🏆</span>';
        if (html.includes('🥇')) return '<span class="ach-emoji">🥇</span>';
        if (html.includes('🌟')) return '<span class="ach-emoji">🌟</span>';
        if (html.includes('💪')) return '<span class="ach-emoji">💪</span>';
        return '<span class="ach-emoji">🏅</span>';
    },

    formatAchievementDate(dateStr) {
        if (!dateStr) return '';
        const iso = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (iso) {
            return `${iso[3]}.${iso[2]}.${iso[1]}`;
        }
        return String(dateStr);
    },

    getAchievementTitle(achievement) {
        if (achievement.title) return achievement.title;
        return this.stripHtml(achievement.text);
    },

    renderAchievementIcon(achievement) {
        const emoji = achievement.emoji || '🏅';
        if (achievement.icon) {
            const icon = String(achievement.icon).replace(/\.png$/i, '.svg');
            return `<span class="ach-icon-wrap"><img src="/icons/${icon}" alt="" class="ach-icon-img" loading="lazy" onerror="this.parentElement.outerHTML='<span class=\\'ach-emoji\\'>${emoji}</span>'"></span>`;
        }
        return this.extractIconHtml(achievement.text);
    },

    renderAchievementCard(achievement) {
        const rarity = this.getAchievementRarity(achievement.id);
        const earned = !!achievement.active;
        const cls = earned
            ? `ach-card ach-card--earned active ach-${rarity}`
            : `ach-card ach-card--locked locked ach-${rarity}`;
        const title = LegionCore.escapeHtml(this.getAchievementTitle(achievement));
        const desc = LegionCore.escapeHtml(achievement.desc || '');
        const dateLabel = earned ? this.formatAchievementDate(achievement.date) : '';
        const lockHtml = earned ? '' : '<span class="ach-card-lock" aria-hidden="true">🔒</span>';

        return `<div class="${cls}" title="${desc}">
            ${lockHtml}
            <div class="ach-card-medal ach-card-medal--${rarity}">
                <div class="ach-card-icon">${this.renderAchievementIcon(achievement)}</div>
            </div>
            <div class="ach-card-title">${title}</div>
            <div class="ach-card-desc">${desc}</div>
            ${earned ? `<div class="ach-card-date">${dateLabel}</div>` : '<div class="ach-card-date ach-card-date--locked">Не получено</div>'}
        </div>`;
    },

    renderAchievementCardsGrid(items) {
        if (!items.length) return '';
        let html = '<div class="ach-grid">';
        items.forEach((a) => { html += this.renderAchievementCard(a); });
        html += '</div>';
        return html;
    },

    renderAchievementLockedTeaser(locked) {
        if (!locked.length) return '';
        let html = `<details class="ach-locked-teaser">
            <summary class="ach-locked-teaser-summary">Можно ещё получить <span class="ach-count">${locked.length}</span></summary>
            <div class="ach-locked-teaser-list">`;
        locked.forEach((a) => {
            const title = LegionCore.escapeHtml(this.getAchievementTitle(a));
            const desc = LegionCore.escapeHtml(a.desc || '');
            html += `<div class="ach-locked-teaser-item">
                <span class="ach-locked-teaser-icon">${this.renderAchievementIcon(a)}</span>
                <span class="ach-locked-teaser-body">
                    <span class="ach-locked-teaser-title">${title}</span>
                    <span class="ach-locked-teaser-desc">${desc}</span>
                </span>
            </div>`;
        });
        html += '</div></details>';
        return html;
    },

    renderAchievementGrid(achievements, options) {
        const opts = options || {};
        const variant = opts.variant || 'full';
        const showHeading = opts.showHeading !== false;
        const earned = achievements.filter((a) => a.active);
        const locked = achievements.filter((a) => !a.active);

        let html = '';
        if (showHeading) {
            const countLabel = variant === 'modal'
                ? `${earned.length}`
                : `${earned.length} / ${achievements.length}`;
            html += `<h3 class="section-title">Достижения <span class="ach-count">${countLabel}</span></h3>`;
        }

        if (variant === 'modal') {
            if (!earned.length) {
                html += '<p class="ach-empty">Пока нет достижений — тренируйся и поднимайся в рейтинге!</p>';
            } else {
                html += this.renderAchievementCardsGrid(earned);
            }
            html += this.renderAchievementLockedTeaser(locked);
            return html;
        }

        const categories = (typeof LegionCore !== 'undefined' && LegionCore.getAchievementCategories)
            ? LegionCore.getAchievementCategories()
            : [{ id: 'rating', title: 'Достижения' }];

        categories.forEach((cat) => {
            const items = achievements.filter((a) => (a.category || 'rating') === cat.id);
            if (!items.length) return;
            const catEarned = items.filter((a) => a.active).length;
            html += `<div class="ach-category"><h4 class="ach-category-title">${cat.title} <span class="ach-count">${catEarned}/${items.length}</span></h4>`;
            html += this.renderAchievementCardsGrid(items);
            html += '</div>';
        });

        html += '<p class="ach-legend">Яркие медали — получены · Серые — ещё впереди</p>';
        return html;
    },

    renderClubStats(stats) {
        const items = [
            { value: stats.athletes, label: 'спортсменов' },
            { value: stats.coaches, label: stats.coachesLabel || 'тренеров' }
        ];
        if (stats.updatedAt) {
            items.push({ value: stats.updatedAt, label: 'обновлено', small: true });
        }
        let html = '<div class="club-stats-inner">';
        items.forEach(item => {
            html += `<div class="stat-card">
                <div class="stat-value${item.small ? ' stat-value-sm' : ''}">${item.value}</div>
                <div class="stat-label">${item.label}</div>
            </div>`;
        });
        html += '</div>';
        return html;
    },

    updateClubStats(el, stats) {
        if (!el) return;
        el.innerHTML = this.renderClubStats(stats);
    },

    renderExerciseTabs(container, options) {
        if (!container || typeof LegionConfig === 'undefined') return;
        const opts = options || {};
        const includeHall = opts.includeHall !== false;
        const activeTab = opts.activeTab || 'overall';
        const esc = (value) => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const mkTab = (id, label) => {
            const isActive = id === activeTab;
            return `<div class="tab${isActive ? ' active' : ''}" data-tab="${esc(id)}" role="tab" tabindex="0">${esc(label)}</div>`;
        };

        let html = mkTab('overall', 'Общий рейтинг');
        LegionConfig.EXERCISES.forEach((ex) => {
            html += mkTab(ex.tab, ex.label);
        });
        if (includeHall) {
            html += mkTab('hall', '🏆 Зал славы');
        }
        container.innerHTML = html;
        container.setAttribute('data-tabs-rendered', 'js');
        this.bindExerciseTabs(container);
    },

    bindExerciseTabs(container) {
        if (!container || container.getAttribute('data-tabs-bound') === '1') return;
        container.setAttribute('data-tabs-bound', '1');
        container.querySelectorAll('.tab').forEach((tab) => {
            const activate = () => {
                const id = tab.getAttribute('data-tab');
                if (id && typeof window.switchTab === 'function') {
                    window.switchTab(id);
                }
            };
            tab.addEventListener('click', activate);
            tab.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    activate();
                }
            });
        });
    },

    parseHistoryDate(dateStr) {
        if (!dateStr) return new Date(0);
        const m = String(dateStr).match(/(\d{1,2})\.(\d{1,2})\.(\d{4})/);
        if (!m) return new Date(0);
        return new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
    },

    buildExerciseSeries(athleteName, exerciseKey, history, currentVal) {
        const norm = (n) => (typeof LegionCore !== 'undefined' && LegionCore.normalizePersonName)
            ? LegionCore.normalizePersonName(n)
            : String(n || '').trim();
        const targetName = norm(athleteName);
        const raw = history
            .filter(e => norm(e.name) === targetName && e.exercise === exerciseKey)
            .sort((a, b) => this.parseHistoryDate(a.date) - this.parseHistoryDate(b.date));
        const points = [];
        if (raw.length > 0) {
            points.push({ date: this.parseHistoryDate(raw[0].date), value: raw[0].oldVal });
            raw.forEach(e => points.push({ date: this.parseHistoryDate(e.date), value: e.newVal }));
        }
        if (currentVal > 0) {
            const last = points[points.length - 1];
            if (!last || last.value !== currentVal) {
                points.push({ date: new Date(), value: currentVal });
            }
        }
        return points;
    },

    renderSparkline(title, points) {
        if (points.length === 0) {
            return `<div class="progress-card progress-card-empty"><div class="progress-card-title">${title}</div><div class="progress-card-value">—</div></div>`;
        }
        const w = 200;
        const h = 56;
        const pad = 6;
        const values = points.map(p => p.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = max - min || 1;
        const coords = points.map((p, i) => {
            const x = pad + (i / Math.max(points.length - 1, 1)) * (w - pad * 2);
            const y = h - pad - ((p.value - min) / range) * (h - pad * 2);
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        });
        const lastVal = values[values.length - 1];
        const firstVal = values[0];
        const trend = lastVal > firstVal ? 'up' : (lastVal < firstVal ? 'down' : 'flat');
        let svg = '';
        if (points.length >= 2) {
            svg = `<svg class="progress-sparkline" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" aria-hidden="true">
                <polyline points="${coords.join(' ')}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
        }
        return `<div class="progress-card progress-trend-${trend}">
            <div class="progress-card-title">${title}</div>
            <div class="progress-card-value">${lastVal}</div>
            ${svg}
        </div>`;
    },

    renderProgressCharts(athleteName, history, athlete, options) {
        const opts = options || {};
        const showHeading = opts.showHeading !== false;
        let hasAny = false;
        let html = showHeading ? '<h3 class="section-title">Прогресс</h3>' : '';
        html += '<div class="progress-grid">';
        LegionConfig.EXERCISES.forEach(ex => {
            const series = this.buildExerciseSeries(athleteName, ex.key, history, athlete[ex.key]);
            if (series.length > 0) hasAny = true;
            html += this.renderSparkline(ex.label, series);
        });
        html += '</div>';
        if (!hasAny) {
            const empty = '<p class="note">Пока нет точек для графика — они появятся после первых зафиксированных изменений.</p>';
            return showHeading ? html + empty : empty;
        }
        return html;
    },

    formatHallAthlete(name) {
        if (!name) return '—';
        if (typeof LegionCore !== 'undefined' && LegionCore.formatAthleteLink) {
            return LegionCore.formatAthleteLink(name);
        }
        return `<span class="athlete-name">${name}</span>`;
    },

    formatRecordValue(value) {
        return value > 0 ? `<strong>${value}</strong>` : '—';
    },

    formatRecordDate(date) {
        if (!date) return '<span class="hall-date-now">актуально</span>';
        return `установлен ${date}`;
    },

    renderHallRecordFeed(breaks) {
        let html = `<section class="hall-feed">
            <h2 class="hall-feed-title">Недавние рекорды</h2>`;

        if (!breaks || breaks.length === 0) {
            html += '<p class="hall-feed-empty note">Пока нет зафиксированных рекордов — они появятся после улучшения результатов в рейтинге.</p>';
        } else {
            html += '<div class="history-list hall-feed-list">';
            breaks.forEach((b, idx) => {
                const line = (typeof LegionCore !== 'undefined' && LegionCore.formatHallRecordLine)
                    ? LegionCore.formatHallRecordLine(b, idx)
                    : `${b.date} ${b.name}`;
                html += `<div class="history-item hall-feed-item">${line}</div>`;
            });
            html += '</div>';
        }

        html += '</section>';
        return html;
    },

    renderHallOfFame(records, recentBreaks) {
        let html = `<div class="hall-hero">
            <div class="hall-hero-icon" aria-hidden="true">🏆</div>
            <div>
                <h2 class="hall-title">Зал славы</h2>
                <p class="hall-intro">Лучшие результаты в истории клуба по каждому упражнению. Ниже — лента недавних рекордов из истории изменений.</p>
            </div>
        </div>`;

        html += '<div class="hall-grid">';
        records.forEach(r => {
            const club = r.clubRecord;
            const hasRecord = club.value > 0 && club.name;

            html += `<article class="hall-card">
                <h3 class="hall-card-title">${r.label}</h3>
                <div class="hall-record-main">`;

            if (!hasRecord) {
                html += '<p class="hall-record-empty">Пока нет рекорда</p>';
            } else {
                const activeBadge = r.isActive
                    ? '<span class="hall-badge hall-badge--active">в рейтинге</span>'
                    : '<span class="hall-badge hall-badge--legacy">рекордсмен</span>';

                html += `<div class="hall-record-value">${this.formatRecordValue(club.value)}</div>
                    <div class="hall-record-name">${this.formatHallAthlete(club.name)}</div>
                    <div class="hall-record-meta">
                        <span class="hall-record-date">${this.formatRecordDate(club.date)}</span>
                        ${activeBadge}
                    </div>`;

                if (r.currentLeader) {
                    html += `<p class="hall-record-note">Сейчас впереди: ${this.formatHallAthlete(r.currentLeader.name)} (${r.currentLeader.value})</p>`;
                }
            }

            html += '</div></article>';
        });
        html += '</div>';

        html += this.renderHallRecordFeed(recentBreaks);
        html += '<button class="print-btn no-print" onclick="window.print()">🖨️ Печать</button>';
        return html;
    },

    getLeagueMeta(league) {
        return LegionConfig.LEAGUE_META[league] || { metal: 'none', label: 'Лига', short: '—', icon: '🏅', color: '#888' };
    },

    renderRankSummaryCard(name, clubRank) {
        if (!clubRank) {
            return '<p class="rank-summary-empty">Ранг не определён — нет данных в таблице рангов.</p>';
        }
        const meta = this.getLeagueMeta(clubRank.league);
        const attr = LegionCore.escapeHtmlAttr(name);
        const pct = Math.round((clubRank.completed / clubRank.total) * 100);
        const rankLabel = clubRank.rankName || 'Начало пути';
        return `<button type="button" class="rank-summary-card rank-summary-card--${meta.metal}" data-athlete-name="${attr}">
            <span class="rank-summary-icon" aria-hidden="true">${meta.icon}</span>
            <span class="rank-summary-body">
                <span class="rank-summary-rank">${rankLabel}</span>
                <span class="rank-summary-league">${meta.label} · ${clubRank.completed}/${clubRank.total}</span>
                <span class="rank-summary-bar"><span style="width:${pct}%"></span></span>
            </span>
            <span class="rank-summary-action" aria-hidden="true">Подробнее</span>
        </button>`;
    },

    renderRankNextHint(clubRank) {
        const remaining = clubRank.total - clubRank.completed;
        if (remaining <= 0 && clubRank.league === 1) {
            return '<span class="rank-modal-hint rank-modal-hint--max">Максимальное звание достигнуто</span>';
        }
        if (remaining <= 0) return '';
        const next = clubRank.league === 3
            ? this.getLeagueMeta(2).label
            : (clubRank.league === 2 ? this.getLeagueMeta(1).label : 'следующего звания');
        return `<span class="rank-modal-hint">До ${next.toLowerCase()}: ещё ${remaining} ${this.pluralExercises(remaining)}</span>`;
    },

    pluralExercises(n) {
        const mod10 = n % 10;
        const mod100 = n % 100;
        if (mod10 === 1 && mod100 !== 11) return 'упражнение';
        if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'упражнения';
        return 'упражнений';
    },

    renderRankLadder(clubRank, marks) {
        const leagues = [3, 2, 1];
        let html = '<div class="rank-ladder" aria-label="Путь лиг">';
        leagues.forEach((leagueId, idx) => {
            const meta = this.getLeagueMeta(leagueId);
            const progress = LegionCore.getLeagueProgress(marks, leagueId);
            const state = leagueId > clubRank.league
                ? 'done'
                : (leagueId === clubRank.league ? 'active' : 'locked');
            const countLabel = state === 'locked'
                ? '—'
                : (progress.isComplete ? '20/20' : `${progress.completed}/20`);

            html += `<div class="rank-ladder-step rank-ladder-step--${state}">
                <span class="rank-ladder-icon" aria-hidden="true">${meta.icon}</span>
                <span class="rank-ladder-label">${meta.short}</span>
                <span class="rank-ladder-count">${countLabel}</span>
            </div>`;
            if (idx < leagues.length - 1) {
                const connectorDone = leagueId > clubRank.league;
                html += `<div class="rank-ladder-connector${connectorDone ? ' rank-ladder-connector--done' : ''}"></div>`;
            }
        });
        html += '</div>';
        return html;
    },

    renderRankNameLadder(clubRank) {
        const { names } = LegionCore.getLeagueExerciseList(clubRank.league);
        const currentIdx = clubRank.completed - 1;
        let html = '<div class="rank-names-section"><h3 class="rank-section-title">Звания лиги</h3><div class="rank-names-track">';
        names.forEach((rankName, idx) => {
            let cls = 'rank-name-pill';
            if (idx < currentIdx) cls += ' rank-name-pill--done';
            else if (idx === currentIdx) cls += ' rank-name-pill--current';
            else cls += ' rank-name-pill--future';
            html += `<span class="${cls}" title="${rankName}"><span class="rank-name-pill-num">${idx + 1}</span>${rankName}</span>`;
        });
        html += '</div></div>';
        return html;
    },

    renderRankExercises(clubRank, marks) {
        const meta = this.getLeagueMeta(clubRank.league);
        const progress = LegionCore.getLeagueProgress(marks, clubRank.league);
        let html = `<div class="rank-exercises-section">
            <h3 class="rank-section-title">Упражнения — ${meta.label}</h3>
            <p class="rank-section-note">Выполните все 20 упражнений, чтобы перейти в следующую лигу. Каждое упражнение открывает новое звание.</p>
            <ul class="rank-exercise-grid">`;
        progress.items.forEach((item) => {
            const cls = item.done ? 'rank-exercise-item rank-exercise-item--done' : 'rank-exercise-item';
            html += `<li class="${cls}">
                <span class="rank-exercise-check" aria-hidden="true">${item.done ? '✓' : ''}</span>
                <span class="rank-exercise-body">
                    <span class="rank-exercise-name">${item.name}</span>
                    ${item.description ? `<span class="rank-exercise-desc">${item.description}</span>` : ''}
                </span>
            </li>`;
        });
        html += '</ul></div>';
        return html;
    },

    renderRankModal(name, clubRank, marks) {
        const meta = this.getLeagueMeta(clubRank.league);
        const pct = Math.round((clubRank.completed / clubRank.total) * 100);

        let html = `<div class="rank-modal">
            <div class="rank-modal-hero rank-modal-hero--${meta.metal}">
                <div class="rank-modal-hero-main">
                    <span class="rank-modal-hero-icon" aria-hidden="true">${meta.icon}</span>
                    <div class="rank-modal-hero-text">
                        <div class="rank-modal-hero-league">${meta.label}</div>
                        <div class="rank-modal-hero-rank">${clubRank.rankName || 'Выполните первое упражнение'}</div>
                    </div>
                </div>
                <div class="rank-modal-hero-progress">
                    <div class="rank-modal-progress-track" role="progressbar" aria-valuenow="${clubRank.completed}" aria-valuemin="0" aria-valuemax="${clubRank.total}">
                        <div class="rank-modal-progress-fill" style="width:${pct}%"></div>
                    </div>
                    <div class="rank-modal-progress-meta">
                        <span>${clubRank.completed} из ${clubRank.total} упражнений</span>
                        ${this.renderRankNextHint(clubRank)}
                    </div>
                </div>
            </div>`;

        html += this.renderRankLadder(clubRank, marks);
        html += this.renderRankExercises(clubRank, marks);
        html += this.renderRankNameLadder(clubRank);
        html += '</div>';
        return html;
    },

    renderAthleteRankProfile(clubRank, marks) {
        if (!clubRank) {
            return '<p class="rank-summary-empty">Данные о рангах пока не загружены. Тренер отметит сданные нормативы в режиме тренировки.</p>';
        }

        const meta = this.getLeagueMeta(clubRank.league);
        const progress = LegionCore.getLeagueProgress(marks, clubRank.league);
        const doneItems = progress.items.filter((item) => item.done);
        const todoItems = progress.items.filter((item) => !item.done);
        const remaining = clubRank.total - clubRank.completed;

        let html = `<div class="athlete-rank-profile">
            <div class="athlete-rank-head athlete-rank-head--${meta.metal}">
                <span class="athlete-rank-head-icon" aria-hidden="true">${meta.icon}</span>
                <div class="athlete-rank-head-body">
                    <div class="athlete-rank-head-league">${meta.label}</div>
                    <div class="athlete-rank-head-rank">${LegionCore.escapeHtml(clubRank.rankName || 'Начало пути')}</div>
                    <div class="athlete-rank-head-meta">${clubRank.completed} сдано · ${remaining > 0 ? remaining + ' осталось' : 'лига пройдена'}</div>
                </div>
            </div>`;

        if (clubRank.league <= 2) {
            html += '<div class="athlete-rank-leagues-done">';
            if (clubRank.league <= 2) {
                html += '<span class="athlete-rank-league-badge athlete-rank-league-badge--bronze">🥉 Бронза</span>';
            }
            if (clubRank.league === 1) {
                html += '<span class="athlete-rank-league-badge athlete-rank-league-badge--silver">🥈 Серебро</span>';
            }
            html += '</div>';
        }

        if (todoItems.length === 0) {
            html += '<p class="athlete-rank-all-done">Все нормативы этой лиги сданы — можно переходить дальше.</p>';
        } else {
            html += `<div class="athlete-rank-todo-section">
                <h4 class="athlete-rank-todo-heading">Осталось сдать <span class="athlete-rank-list-count">${todoItems.length}</span></h4>
                <ol class="athlete-rank-todo-list">`;
            todoItems.forEach((item, idx) => {
                const isNext = idx === 0;
                html += `<li class="athlete-rank-todo-item${isNext ? ' athlete-rank-todo-item--next' : ''}">
                    <span class="athlete-rank-todo-num">${idx + 1}</span>
                    <div class="athlete-rank-todo-content">
                        <span class="athlete-rank-todo-name">${LegionCore.escapeHtml(item.name)}</span>
                        ${item.description ? `<span class="athlete-rank-todo-desc">${LegionCore.escapeHtml(item.description)}</span>` : ''}
                        ${isNext ? '<span class="athlete-rank-todo-badge">Следующий</span>' : ''}
                    </div>
                </li>`;
            });
            html += '</ol></div>';
        }

        if (doneItems.length > 0) {
            html += `<details class="athlete-rank-done-details">
                <summary class="athlete-rank-done-summary">Сдано <span class="athlete-rank-list-count">${doneItems.length}</span></summary>
                <ul class="athlete-rank-done-list">`;
            doneItems.forEach((item) => {
                html += `<li class="athlete-rank-done-item">
                    <span class="athlete-rank-done-check" aria-hidden="true">✓</span>
                    <span class="athlete-rank-done-text">
                        <span class="athlete-rank-done-name">${LegionCore.escapeHtml(item.name)}</span>
                        ${item.description ? `<span class="athlete-rank-done-desc">${LegionCore.escapeHtml(item.description)}</span>` : ''}
                    </span>
                </li>`;
            });
            html += '</ul></details>';
        }

        html += '</div>';
        return html;
    },

    prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    },

    shouldStaggerRatingEnter(tabKey) {
        if (!tabKey || tabKey === 'hall') return false;
        const state = (typeof LegionCore !== 'undefined' && LegionCore.state) ? LegionCore.state : {};
        const micro = state._ratingRowEnter || (state._ratingRowEnter = { lastTab: null });
        if (micro.lastTab !== tabKey) {
            micro.lastTab = tabKey;
            return true;
        }
        return false;
    },

    collectRatingTableRows(root) {
        if (!root) return [];
        const rows = [];
        root.querySelectorAll('.rating-table').forEach((table) => {
            const tableRows = table.tBodies.length
                ? table.querySelectorAll('tbody tr')
                : Array.from(table.rows).slice(1);
            tableRows.forEach((tr) => {
                if (tr.querySelector('th')) return;
                const firstCell = tr.cells && tr.cells[0];
                if (firstCell && firstCell.colSpan > 1) return;
                rows.push(tr);
            });
        });
        return rows;
    },

    applyRatingRowEntrance(root, tabKey, options) {
        if (!root || this.prefersReducedMotion()) return;
        const opts = options || {};
        const stagger = opts.force === true || this.shouldStaggerRatingEnter(tabKey);
        if (!stagger) return;

        this.collectRatingTableRows(root).forEach((row, i) => {
            row.classList.add('legion-micro-row');
            row.style.setProperty('--legion-micro-delay', `${Math.min(i * 48, 420)}ms`);
        });
    }
};
