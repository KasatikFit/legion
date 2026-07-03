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
        if (id === 'top1') return 'legendary';
        if (id.startsWith('ex_top1_')) return 'epic';
        if (id === 'top3' || id === 'top25') return 'rare';
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

    renderAchievementGrid(achievements) {
        const earned = achievements.filter(a => a.active).length;
        let html = `<h3 class="section-title">Достижения <span class="ach-count">${earned} / ${achievements.length}</span></h3>`;
        html += '<div class="ach-grid">';
        achievements.forEach(a => {
            const rarity = this.getAchievementRarity(a.id);
            const cls = a.active ? `ach-card active ach-${rarity}` : `ach-card locked ach-${rarity}`;
            const title = this.stripHtml(a.text);
            html += `<div class="${cls}" title="${a.desc}">
                <div class="ach-card-icon">${this.extractIconHtml(a.text)}</div>
                <div class="ach-card-title">${title}</div>
                <div class="ach-card-date">${a.date || '—'}</div>
                ${!a.active ? '<div class="ach-card-lock" aria-hidden="true">🔒</div>' : ''}
            </div>`;
        });
        html += '</div>';
        html += '<p class="ach-legend">Яркие карточки — получены · Серые — ещё впереди</p>';
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

    renderProgressCharts(athleteName, history, athlete) {
        let hasAny = false;
        let html = '<h3 class="section-title">Прогресс</h3><div class="progress-grid">';
        LegionConfig.EXERCISES.forEach(ex => {
            const series = this.buildExerciseSeries(athleteName, ex.key, history, athlete[ex.key]);
            if (series.length > 0) hasAny = true;
            html += this.renderSparkline(ex.label, series);
        });
        html += '</div>';
        if (!hasAny) {
            return '<h3 class="section-title">Прогресс</h3><p class="note">Пока нет данных для графика — они появятся после изменений результатов.</p>';
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
            html += '<ul class="hall-feed-list">';
            breaks.forEach(b => {
                const change = b.oldVal > 0
                    ? `${b.oldVal} → <strong>${b.newVal}</strong>`
                    : `<strong>${b.newVal}</strong>`;
                const verb = b.oldVal > 0 ? 'побил рекорд' : 'установил рекорд';
                html += `<li class="hall-feed-item">
                    <span class="hall-feed-date">${b.date}</span>
                    <span class="hall-feed-text">${this.formatHallAthlete(b.name)} ${verb} в ${b.exerciseLabel}: ${change}</span>
                </li>`;
            });
            html += '</ul>';
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
    }
};
