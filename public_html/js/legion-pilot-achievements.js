/**
 * Достижения пилотной группы (тестовый набор).
 */
const LegionPilotAchievements = {
    exerciseLabels: {
        push: 'Отжимания',
        pull: 'Подтягивания',
        hang: 'Вис',
        burpee: 'Бёрпи',
        crunch: 'Скручивания',
        jump: 'Прыжок'
    },

    definitions() {
        const em = (e) => `<span class="ach-emoji">${e}</span>`;
        const defs = {
            pilot_top1: {
                text: `${em('🏆')} ТОП-1 группы`,
                desc: 'Первое место в рейтинге пилотной группы',
                category: 'rating'
            },
            pilot_top3: {
                text: `${em('🥇')} ТОП-3 группы`,
                desc: 'Войти в тройку лидеров пилотной группы',
                category: 'rating'
            },
            pilot_first_gain: {
                text: `${em('📈')} Первый прогресс`,
                desc: 'Улучшить результат на тренировке',
                category: 'progress'
            }
        };

        Object.keys(this.exerciseLabels).forEach((key) => {
            defs['pilot_best_' + key] = {
                text: `${em('💪')} Лидер: ${this.exerciseLabels[key]}`,
                desc: `Лучший результат в группе — ${this.exerciseLabels[key].toLowerCase()}`,
                category: 'rating'
            };
        });

        if (typeof LegionCore !== 'undefined' && LegionCore.getAchievementDefinitions) {
            const core = LegionCore.getAchievementDefinitions();
            ['rank_bronze_done', 'rank_silver_done', 'rank_gold_done'].forEach((id) => {
                if (core[id]) defs[id] = core[id];
            });
        } else {
            defs.rank_bronze_done = { text: `${em('🥉')} Бронзовая лига`, desc: 'Закрыть 20 нормативов бронзы', category: 'ranks' };
            defs.rank_silver_done = { text: `${em('🥈')} Серебряная лига`, desc: 'Закрыть 20 нормативов серебра', category: 'ranks' };
            defs.rank_gold_done = { text: `${em('🥇')} Золотая лига`, desc: 'Закрыть 20 нормативов золота', category: 'ranks' };
        }

        return defs;
    },

    categories() {
        return [
            { id: 'rating', title: 'Рейтинг группы' },
            { id: 'progress', title: 'Прогресс' },
            { id: 'ranks', title: 'Ранги' }
        ];
    },

    getDisplayData(athleteName, storedAll, coachSlug) {
        const athleteAch = (typeof LegionCore !== 'undefined' && LegionCore.lookupAchievementEntries)
            ? LegionCore.lookupAchievementEntries(athleteName, coachSlug, storedAll || {})
            : ((storedAll || {})[athleteName] || []);
        const defs = this.definitions();
        return Object.keys(defs).map((id) => {
            const def = defs[id];
            const hit = athleteAch.find((a) => a.id === id);
            return {
                id,
                text: def.text,
                desc: def.desc,
                category: def.category || 'rating',
                date: hit ? hit.date : null,
                active: !!hit
            };
        });
    },

    renderGrid(athleteName, storedAll, coachSlug) {
        const achievements = this.getDisplayData(athleteName, storedAll, coachSlug);
        const earned = achievements.filter((a) => a.active).length;
        let html = `<h3 class="section-title">Достижения <span class="ach-count">${earned} / ${achievements.length}</span></h3>`;
        html += '<p class="note pilot-ach-note">Тестовый режим — только пилотная группа</p>';

        this.categories().forEach((cat) => {
            const items = achievements.filter((a) => (a.category || 'rating') === cat.id);
            if (!items.length) return;
            const catEarned = items.filter((a) => a.active).length;
            html += `<div class="ach-category"><h4 class="ach-category-title">${cat.title} <span class="ach-count">${catEarned}/${items.length}</span></h4>`;
            html += '<div class="ach-grid">';
            items.forEach((a) => {
                const rarity = (LegionUI && LegionUI.getAchievementRarity)
                    ? LegionUI.getAchievementRarity(a.id)
                    : 'common';
                const cls = a.active ? `ach-card active ach-${rarity}` : `ach-card locked ach-${rarity}`;
                const title = LegionUI && LegionUI.stripHtml ? LegionUI.stripHtml(a.text) : a.text;
                const dateLabel = a.active && LegionUI && LegionUI.formatAchievementDate
                    ? LegionUI.formatAchievementDate(a.date)
                    : (a.active ? a.date : '—');
                const icon = LegionUI && LegionUI.extractIconHtml
                    ? LegionUI.extractIconHtml(a.text)
                    : '';
                html += `<div class="${cls}" title="${a.desc}">
                    <div class="ach-card-icon">${icon}</div>
                    <div class="ach-card-title">${title}</div>
                    <div class="ach-card-desc">${a.desc}</div>
                    <div class="ach-card-date">${dateLabel}</div>
                </div>`;
            });
            html += '</div></div>';
        });

        return html;
    }
};
