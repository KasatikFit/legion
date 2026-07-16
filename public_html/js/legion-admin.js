const LegionAdmin = {
    slugTouched: false,

    async init() {
        if (!window.__legionAdminConfigured) {
            return;
        }
        const loginForm = document.getElementById('admin-login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.login();
            });
        }
        const logoutBtn = document.getElementById('admin-logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }
        const createForm = document.getElementById('admin-create-form');
        if (createForm) {
            createForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createCoach();
            });
        }
        const nameInput = document.getElementById('admin-create-name');
        const slugInput = document.getElementById('admin-create-slug');
        if (nameInput) {
            nameInput.addEventListener('input', () => {
                if (!this.slugTouched) {
                    this.suggestSlug(nameInput.value);
                }
            });
        }
        if (slugInput) {
            slugInput.addEventListener('input', () => {
                this.slugTouched = slugInput.value.trim() !== '';
            });
        }
        const diagRefresh = document.getElementById('admin-diagnostics-refresh');
        if (diagRefresh) {
            diagRefresh.addEventListener('click', () => this.loadDiagnostics());
        }

        const session = await this.fetchSession();
        if (session.authenticated) {
            this.showApp();
            await Promise.all([this.loadCoaches(), this.loadDiagnostics()]);
        }
    },

    async fetchSession() {
        const resp = await fetch('/api/admin/session.php', { credentials: 'same-origin' });
        return resp.json();
    },

    async login() {
        const password = (document.getElementById('admin-login-password') || {}).value || '';
        const errEl = document.getElementById('admin-login-error');
        if (errEl) errEl.hidden = true;
        try {
            const resp = await fetch('/api/admin/login.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка входа');
            this.showApp();
            await Promise.all([this.loadCoaches(), this.loadDiagnostics()]);
        } catch (err) {
            if (errEl) {
                errEl.hidden = false;
                errEl.textContent = err.message;
            }
        }
    },

    async logout() {
        await fetch('/api/admin/logout.php', { method: 'POST', credentials: 'same-origin' });
        document.getElementById('admin-app').hidden = true;
        document.getElementById('admin-login-card').hidden = false;
        document.getElementById('admin-logout-btn').hidden = true;
        const pwd = document.getElementById('admin-login-password');
        if (pwd) pwd.value = '';
    },

    showApp() {
        document.getElementById('admin-login-card').hidden = true;
        document.getElementById('admin-app').hidden = false;
        document.getElementById('admin-logout-btn').hidden = false;
    },

    async suggestSlug(name) {
        const slugInput = document.getElementById('admin-create-slug');
        if (!slugInput || !name.trim()) return;
        try {
            const resp = await fetch('/api/admin/suggest_slug.php?name=' + encodeURIComponent(name.trim()), {
                credentials: 'same-origin'
            });
            const data = await resp.json();
            if (resp.ok && data.slug) {
                slugInput.value = data.slug;
            }
        } catch (e) {
            /* ignore */
        }
    },

    setCreateStatus(msg, kind) {
        const el = document.getElementById('admin-create-status');
        if (!el) return;
        el.hidden = !msg;
        el.textContent = msg || '';
        el.className = 'admin-status' + (kind ? ' admin-status--' + kind : '');
    },

    async createCoach() {
        const name = (document.getElementById('admin-create-name') || {}).value.trim();
        const slug = (document.getElementById('admin-create-slug') || {}).value.trim();
        const password = (document.getElementById('admin-create-password') || {}).value;
        if (!name || !password) return;

        this.setCreateStatus('Создание…', '');
        try {
            const body = { name, password };
            if (slug) body.slug = slug;
            const resp = await fetch('/api/admin/coaches.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');

            this.setCreateStatus(
                `Готово: ${data.coach.name} → ${data.coach.ratingUrl} (тренировка: ${data.coach.trainingUrl})`,
                'ok'
            );
            document.getElementById('admin-create-form').reset();
            this.slugTouched = false;
            await this.loadCoaches();
            await this.loadDiagnostics();
        } catch (err) {
            this.setCreateStatus(err.message, 'error');
        }
    },

    async loadCoaches() {
        const tbody = document.getElementById('admin-coaches-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6">Загрузка…</td></tr>';
        try {
            const resp = await fetch('/api/admin/coaches.php', { credentials: 'same-origin' });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка загрузки');
            this.renderCoaches(data.coaches || []);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" class="admin-error">${this.esc(err.message)}</td></tr>`;
        }
    },

    renderCoaches(coaches) {
        const tbody = document.getElementById('admin-coaches-tbody');
        if (!coaches.length) {
            tbody.innerHTML = '<tr><td colspan="6">Нет тренеров в базе</td></tr>';
            return;
        }
        tbody.innerHTML = coaches.map((c) => {
            const vis = c.isVisible
                ? '<span class="admin-tag admin-tag--ok">Да</span>'
                : '<span class="admin-tag admin-tag--muted">Скрыт</span>';
            const pwd = c.hasTrainingPassword
                ? '<span class="admin-tag admin-tag--ok">Есть</span>'
                : '<span class="admin-tag admin-tag--warn">Нет</span>';
            return `<tr data-slug="${this.escAttr(c.slug)}">
                <td><strong>${this.esc(c.name)}</strong></td>
                <td><code>${this.esc(c.slug)}</code></td>
                <td>${vis}</td>
                <td>${pwd}</td>
                <td class="admin-links">
                    <a href="${this.escAttr(c.ratingUrl)}" target="_blank" rel="noopener">Рейтинг</a>
                    <a href="${this.escAttr(c.trainingUrl)}" target="_blank" rel="noopener">Тренировка</a>
                </td>
                <td class="admin-row-actions">
                    <button type="button" class="admin-btn admin-btn--small" data-action="toggle-visible">${c.isVisible ? 'Скрыть' : 'Показать'}</button>
                    <button type="button" class="admin-btn admin-btn--small" data-action="reset-password">Сменить пароль</button>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                const slug = row ? row.getAttribute('data-slug') : '';
                if (!slug) return;
                if (btn.getAttribute('data-action') === 'toggle-visible') {
                    this.toggleVisible(slug, btn.textContent === 'Показать');
                } else if (btn.getAttribute('data-action') === 'reset-password') {
                    this.resetPassword(slug);
                }
            });
        });
    },

    async toggleVisible(slug, show) {
        try {
            const resp = await fetch('/api/admin/coaches.php', {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug, isVisible: show })
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');
            await this.loadCoaches();
            await this.loadDiagnostics();
        } catch (err) {
            alert(err.message);
        }
    },

    async resetPassword(slug) {
        const password = window.prompt('Новый пароль режима тренировки для ' + slug + ':');
        if (!password || password.length < 4) return;
        try {
            const resp = await fetch('/api/admin/coaches.php', {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ slug, password })
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка');
            await this.loadCoaches();
            await this.loadDiagnostics();
            alert('Пароль обновлён');
        } catch (err) {
            alert(err.message);
        }
    },

    async loadDiagnostics() {
        const body = document.getElementById('admin-diagnostics-body');
        if (!body) return;
        body.innerHTML = '<p class="admin-note">Проверка…</p>';
        try {
            const resp = await fetch('/api/admin/diagnostics.php', { credentials: 'same-origin' });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data.error || 'Ошибка диагностики');
            this.renderDiagnostics(data);
        } catch (err) {
            body.innerHTML = `<p class="admin-error">${this.esc(err.message)}</p>`;
        }
    },

    renderDiagnostics(data) {
        const body = document.getElementById('admin-diagnostics-body');
        if (!body) return;

        const summary = data.summary || { ok: 0, warn: 0, error: 0 };
        const issues = Array.isArray(data.issues) ? data.issues : [];
        const loadWarnings = Array.isArray(data.loadWarnings) ? data.loadWarnings : [];
        const hasProblems = summary.error > 0 || summary.warn > 0 || loadWarnings.length > 0;

        let hintClass = 'admin-diag-hint--ok';
        let hintText = 'Всё в порядке — общий рейтинг может работать без предупреждений для посетителей.';
        if (summary.error > 0) {
            hintClass = 'admin-diag-hint--error';
            hintText = 'Есть критические ошибки — рейтинг может не загружаться или работать частично.';
        } else if (summary.warn > 0 || loadWarnings.length > 0) {
            hintClass = 'admin-diag-hint--warn';
            hintText = 'Есть предупреждения — посетители их не видят, исправьте здесь или в полной диагностике.';
        }

        const summaryHtml = `
            <div class="admin-diag-summary">
                <span class="admin-diag-pill admin-diag-pill--ok">${summary.ok} OK</span>
                <span class="admin-diag-pill admin-diag-pill--warn">${summary.warn} внимание</span>
                <span class="admin-diag-pill admin-diag-pill--error">${summary.error} ошибок</span>
            </div>
            <p class="admin-diag-meta">Проверено: ${this.esc(data.checkedAt || '')} · v${this.esc(data.version || '')}</p>
            <p class="admin-diag-hint ${hintClass}">${hintText}</p>`;

        let listsHtml = '';
        if (loadWarnings.length) {
            const items = loadWarnings.map((w) =>
                `<li><strong>${this.esc(w.coach || 'Группа')}</strong>: ${this.esc(w.message || '')}</li>`
            ).join('');
            listsHtml += `
                <div class="admin-diag-block admin-diag-block--warn">
                    <p class="admin-diag-block-title">Загрузка общего рейтинга</p>
                    <ul class="admin-diag-list">${items}</ul>
                </div>`;
        }
        if (issues.length) {
            const items = issues.map((item) => {
                const statusClass = item.status === 'warn' ? 'admin-tag--warn' : 'admin-tag--error';
                const statusLabel = item.status === 'warn' ? 'Внимание' : 'Ошибка';
                return `<li>
                    <span class="admin-tag ${statusClass}">${statusLabel}</span>
                    <strong>${this.esc(item.group)}</strong> — ${this.esc(item.name)}:
                    ${this.esc(item.detail)}
                </li>`;
            }).join('');
            listsHtml += `
                <div class="admin-diag-block">
                    <p class="admin-diag-block-title">Проблемы системы</p>
                    <ul class="admin-diag-list admin-diag-list--issues">${items}</ul>
                </div>`;
        }

        const footer = hasProblems
            ? '<p class="admin-diag-footer"><a href="/diagnostics/">Открыть полную диагностику</a></p>'
            : '';

        body.innerHTML = summaryHtml + listsHtml + footer;
    },

    esc(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    escAttr(value) {
        return this.esc(value).replace(/'/g, '&#39;');
    }
};

document.addEventListener('DOMContentLoaded', () => LegionAdmin.init());
